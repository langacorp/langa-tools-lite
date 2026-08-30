<?php
if (!defined('ABSPATH')) exit;

class Langa_Tools_Client_API {

  /**
   * Check if a given event_type is allowed by Bridge event filters.
   * Maps event_type to category: forms|orders|logins|errors.
   */
  private static function is_event_allowed($event) {
    if (!is_array($event)) return true; // pass unknown through
    $type = isset($event['event_type']) ? (string)$event['event_type'] : '';
    if ($type === '' || $type === 'connectivity_test') return true; // always allow test events

    // Map event_type → category key
    $map = array(
      'form_submit'   => 'forms',
      'form_lead'     => 'forms',
      'cf7_submit'    => 'forms',
      'fluent_submit' => 'forms',
      'bc_contact'    => 'forms',
      'maintenance_contact' => 'forms',
      'order_created' => 'orders',
      'order_completed' => 'orders',
      'woo_order'     => 'orders',
      'login'         => 'logins',
      'login_failed'  => 'logins',
      'error'         => 'errors',
      'php_error'     => 'errors',
    );

    $cat = isset($map[$type]) ? $map[$type] : '';
    if ($cat === '') return true; // unknown types pass through

    $bridge = get_option('langa_tools_bridge_settings', array());
    if (!is_array($bridge)) return true;
    $ev = isset($bridge['events']) && is_array($bridge['events']) ? $bridge['events'] : array();

    // If no events configured at all, allow all (backwards compat)
    if (empty($ev)) return true;

    return !empty($ev[$cat]);
  }

  public static function send_event($event) {
    if ((int)get_option('langa_tools_bridge_enabled', 1) !== 1) return false;

    // Event category filter
    if (!self::is_event_allowed($event)) return false;

    $site_key = (string)get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
    $secret   = (string)get_option(LANGA_TOOLS_OPTION_SECRET, '');

    if ($site_key === '' || $secret === '') return false;

    $payload = wp_json_encode($event);
    $signature = Langa_Tools_Client_Auth::sign($payload, $secret);

    $body = array(
      'site_key'  => $site_key,
      'payload'   => $payload,
      'signature' => $signature,
    );

    // Try primary endpoint (tools.langa.tv)
    $server = defined('LANGA_TOOLS_FIXED_SERVER_URL')
      ? rtrim(LANGA_TOOLS_FIXED_SERVER_URL, '/')
      : rtrim((string)get_option(LANGA_TOOLS_OPTION_SERVER_URL), '/');

    if ($server === '') return false;

    $endpoint = $server . '/wp-json/langa-tools-server/v1/events/log-event';

    $resp = wp_remote_post($endpoint, array(
      'timeout' => 10,
      'body' => $body,
    ));
    if (function_exists('langa_tools_client_debug_log_remote')) langa_tools_client_debug_log_remote('api', 'POST', $endpoint, $resp);

    // Fallback to old endpoint if primary fails
    if (is_wp_error($resp) && defined('LANGA_SYNC_FALLBACK_ENABLED') && LANGA_SYNC_FALLBACK_ENABLED) {
      $fallback_server = 'https://tools.langa.tv';
      $fallback_endpoint = $fallback_server . '/wp-json/langa-tools-server/v1/events/log-event';
      if (function_exists('error_log')) {
        error_log('[LANGA Bridge] Primary failed, using fallback for events');
      }
      $resp = wp_remote_post($fallback_endpoint, array(
        'timeout' => 10,
        'body' => $body,
      ));
      if (function_exists('langa_tools_client_debug_log_remote')) langa_tools_client_debug_log_remote('api_fallback', 'POST', $fallback_endpoint, $resp);
    }

    if (is_wp_error($resp)) return false;

    $code = (int) wp_remote_retrieve_response_code($resp);

    // If server says inactive/invalid -> invalidate license cache immediately
    if ($code === 401 || $code === 403) {
      $rbody = @json_decode(wp_remote_retrieve_body($resp), true);
      $err = is_array($rbody) && isset($rbody['error']) ? (string) $rbody['error'] : '';
      $is_events_issue = in_array($err, array('events_gateway_disabled', 'events_disabled', 'gateway_disabled', 'site_inactive'), true);
      if (!$is_events_issue) {
        delete_transient('langa_license_killswitch');
        if (function_exists('langa_tools_client_license_clear_last_ok')) {
          langa_tools_client_license_clear_last_ok();
        }
        if (function_exists('langa_tools_client_license_log')) {
          langa_tools_client_license_log('api_reject', 'Server rejected event with HTTP ' . $code, array('code' => (string) $code));
        }
      }
    }

    return ($code >= 200 && $code < 300);
  }

  /**
   * Sync Protocol: send request to AEGIS via tools.langa.tv.
   * Used by heartbeat, registration, telemetry.
   *
   * @param string $path  API path (e.g. '/heartbeat')
   * @param array  $data  Payload data
   * @return array|WP_Error Response or error
   */
  public static function sync_request($path, $data = array()) {
    $primary = defined('LANGA_SYNC_ENDPOINT') ? LANGA_SYNC_ENDPOINT : 'https://tools.langa.tv/wp-json/langa/v1';
    $api_key = (string) get_option('langa_bridge_api_key', '');

    $headers = array('Content-Type' => 'application/json');
    if ($api_key !== '') {
      $headers['X-Langa-Bridge-Key'] = $api_key;
    }

    $args = array(
      'body'    => wp_json_encode($data),
      'headers' => $headers,
      'timeout' => 10,
    );

    $resp = wp_remote_post($primary . $path, $args);

    if (is_wp_error($resp) && defined('LANGA_SYNC_FALLBACK_ENABLED') && LANGA_SYNC_FALLBACK_ENABLED) {
      $fallback = defined('LANGA_SYNC_FALLBACK') ? LANGA_SYNC_FALLBACK : 'https://tools.langa.tv/wp-json/langa-server/v1';
      if (function_exists('error_log')) {
        error_log('[LANGA Bridge] Primary failed, using fallback for: ' . $path);
      }
      $resp = wp_remote_post($fallback . $path, $args);
    }

    return $resp;
  }
  /**
   * @deprecated — use sync_request(). Kept for backward compatibility.
   */
  public static function bridge_request($path, $data = array()) {
    return self::sync_request($path, $data);
  }


}

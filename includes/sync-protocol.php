<?php
/**
 * LANGA Bridge Protocol Client v1.0
 *
 * Microchip connector for AEGIS.
 * Handles: site registration, heartbeat, base telemetry, event logging.
 * Designed to be modular and reusable across any LANGA Network app.
 *
 * @package Langa\Bridge
 */
if (!defined('ABSPATH')) exit;

/* =========================================================
 * BRIDGE PAUSE CHECK
 * Lite: Bridge is always active, never pausable.
 * ======================================================= */

if (!function_exists('langa_bridge_is_galaxy_paused')) {
  function langa_bridge_is_galaxy_paused() {
    return false; // Lite = always on
  }
}

/* =========================================================
 * SYNC PROTOCOL — LANGA Core / AEGIS communication
 * ======================================================= */

if (!function_exists('langa_bridge_request')) {
  /**
   * Send a Sync Protocol request to AEGIS via tools.langa.tv.
   *
   * @param string $path  Sync API path (e.g. '/heartbeat', '/sites/register')
   * @param array  $data  Payload data
   * @param string $method POST or GET
   * @return array{ok:bool, code:int, body:array|null, error:string}
   */
  function langa_bridge_request($path, $data = array(), $method = 'POST') {
    $primary = defined('LANGA_SYNC_ENDPOINT') ? LANGA_SYNC_ENDPOINT : 'https://tools.langa.tv/wp-json/langa/v1';
    $api_key = (string) get_option('langa_bridge_api_key', '');

    $headers = array('Content-Type' => 'application/json');
    if ($api_key !== '') {
      $headers['X-Langa-Bridge-Key'] = $api_key;
    }

    $args = array(
      'headers' => $headers,
      'timeout' => 10,
    );
    if ($method === 'POST') {
      $args['body'] = wp_json_encode($data);
    }

    $url = $primary . $path;
    $resp = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_post($url, $args);

    // Fallback if primary fails (WP_Error) OR returns non-2xx (e.g. 403)
    $primary_ok = !is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) >= 200 && (int) wp_remote_retrieve_response_code($resp) < 300;
    if (!$primary_ok && defined('LANGA_SYNC_FALLBACK_ENABLED') && LANGA_SYNC_FALLBACK_ENABLED) {
      $fallback = defined('LANGA_SYNC_FALLBACK') ? LANGA_SYNC_FALLBACK : 'https://tools.langa.tv/wp-json/langa/v1';
      $url = $fallback . $path;
      $resp = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_post($url, $args);
      update_option('langa_bridge_last_fallback', time(), false);
      update_option('langa_bridge_using_fallback', 1, false);
    } else {
      update_option('langa_bridge_using_fallback', 0, false);
    }

    if (is_wp_error($resp)) {
      return array('ok' => false, 'code' => 0, 'body' => null, 'error' => $resp->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($resp);
    $body = @json_decode(wp_remote_retrieve_body($resp), true);

    return array(
      'ok'    => ($code >= 200 && $code < 300),
      'code'  => $code,
      'body'  => is_array($body) ? $body : null,
      'error' => ($code >= 200 && $code < 300) ? '' : 'HTTP ' . $code,
    );
  }
}

/* =========================================================
 * SITE REGISTRATION
 * ======================================================= */

if (!function_exists('langa_bridge_register_site')) {
  /**
   * Register this site with AEGIS Governance.
   * Called once on activation, then stores site ID.
   */
  function langa_bridge_register_site() {
    $already = get_option('langa_bridge_site_id', '');
    // Don't re-register if already done
    if ($already !== '' && $already !== '0') return $already;

    $tools_type = defined('LANGA_TOOLS_IS_LITE') ? 'lite' : 'pro';
    $bridge_level = $tools_type === 'lite' ? 'lite' : 'standard';

    $payload = array(
      'site_url'      => home_url(),
      'site_name'     => get_bloginfo('name'),
      'tools_version' => defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '0.0.0',
      'tools_type'    => $tools_type,
      'bridge_level'  => $bridge_level,
    );

    $result = langa_bridge_request('/sites/register', $payload);

    if ($result['ok'] && !empty($result['body'])) {
      // API returns {status,data:{site_id,...}} — unwrap envelope
      $rd = isset($result['body']['data']) && is_array($result['body']['data']) ? $result['body']['data'] : $result['body'];
      $site_id = isset($rd['site_id']) ? sanitize_text_field($rd['site_id']) : '';
      $api_key = isset($rd['api_key']) ? sanitize_text_field($rd['api_key']) : '';

      if ($site_id !== '') {
        update_option('langa_bridge_site_id', $site_id, false);
      }
      if ($api_key !== '') {
        update_option('langa_bridge_api_key', $api_key, false);
      }
      update_option('langa_bridge_registered_at', time(), false);
      update_option('langa_bridge_registration_status', 'registered', false);
      return $site_id;
    }

    update_option('langa_bridge_registration_status', 'failed', false);
    update_option('langa_bridge_registration_error', $result['error'], false);
    return false;
  }
}

/* =========================================================
 * HEARTBEAT
 * ======================================================= */

if (!function_exists('langa_bridge_send_heartbeat')) {
  /**
   * Send heartbeat to AEGIS.
   * Scheduled via WP-Cron every 6 hours.
   */
  function langa_bridge_send_heartbeat() {
    // Check if paying user disabled Bridge
    if (langa_bridge_is_galaxy_paused()) return false;

    // ── Auto-recovery: if we were on fallback, test primary first ──
    // If primary endpoint is back up, clear fallback flag and re-register on AEGIS.
    if ((int) get_option('langa_bridge_using_fallback', 0) === 1) {
      $primary = defined('LANGA_SYNC_ENDPOINT') ? LANGA_SYNC_ENDPOINT : 'https://tools.langa.tv/wp-json/langa/v1';
      $r = wp_remote_get($primary . '/health', array('timeout' => 8, 'sslverify' => true));
      $primary_back = !is_wp_error($r) && (int) wp_remote_retrieve_response_code($r) >= 200
                      && (int) wp_remote_retrieve_response_code($r) < 300;
      if ($primary_back) {
        // AEGIS is back — clear fallback state and force re-registration
        update_option('langa_bridge_using_fallback', 0, false);
        delete_option('langa_bridge_last_fallback');
        delete_option('langa_bridge_site_id');
        delete_option('langa_bridge_registration_status');
        delete_option('langa_bridge_api_key');
        // Schedule immediate re-registration
        if (!wp_next_scheduled('langa_bridge_register_cron')) {
          wp_schedule_single_event(time() + 10, 'langa_bridge_register_cron');
        }
      }
    }

    $payload = array(
      'site_url'    => home_url(),
      'wp_version'  => get_bloginfo('version'),
      'php_version' => phpversion(),
      'status'      => 'active',
      'uptime'      => time() - (int) get_option('langa_bridge_registered_at', time()),
      'timestamp'   => gmdate('c'),
    );

    $result = langa_bridge_request('/heartbeat', $payload);

    update_option('langa_bridge_last_heartbeat', time(), false);
    update_option('langa_bridge_heartbeat_ok', $result['ok'] ? 1 : 0, false);

    return $result['ok'];
  }
}

/* =========================================================
 * TELEMETRY — BASE (Lite + PRO always)
 * ======================================================= */

if (!function_exists('langa_bridge_collect_telemetry_base')) {
  /**
   * Collect base telemetry data (stack, health, security, environment).
   * Safe: no PII, no personal data, no exact revenue.
   *
   * @return array Telemetry payload
   */
  function langa_bridge_collect_telemetry_base() {
    global $wpdb;

    $tools_type = defined('LANGA_TOOLS_IS_LITE') ? 'lite' : 'pro';
    $bridge_level = $tools_type === 'lite' ? 'lite' : 'standard';

    // Active plugins
    $active_plugins = get_option('active_plugins', array());
    $plugins_list = array();
    $all_plugins = function_exists('get_plugins') ? get_plugins() : array();
    foreach ($active_plugins as $pf) {
      if (isset($all_plugins[$pf])) {
        $slug = dirname($pf);
        if ($slug === '.') $slug = basename($pf, '.php');
        $plugins_list[] = array(
          'slug'    => sanitize_key($slug),
          'version' => sanitize_text_field($all_plugins[$pf]['Version'] ?? ''),
        );
      }
    }

    // Theme
    $theme = wp_get_theme();

    // SSL
    $ssl_valid = is_ssl();
    $ssl_expiry = '';
    $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
    if ($ssl_valid && $home_host && function_exists('stream_context_create')) {
      $ctx = @stream_context_create(array('ssl' => array('capture_peer_cert' => true)));
      $stream = @stream_socket_client('ssl://' . $home_host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
      if ($stream) {
        $params = stream_context_get_params($stream);
        if (!empty($params['options']['ssl']['peer_certificate'])) {
          $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
          if (isset($cert['validTo_time_t'])) {
            $ssl_expiry = gmdate('Y-m-d', $cert['validTo_time_t']);
          }
        }
        fclose($stream);
      }
    }

    // MySQL version
    $mysql_version = '';
    if ($wpdb && method_exists($wpdb, 'db_version')) {
      $mysql_version = $wpdb->db_version();
    }

    // Server software
    $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(substr(wp_unslash($_SERVER['SERVER_SOFTWARE']), 0, 100)) : '';

    // Failed logins (from events table if available)
    $failed_24h = 0;
    $failed_7d = 0;
    if (function_exists('langa_events_table_name')) {
      $table = langa_events_table_name();
      $failed_24h = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE event_type = 'login' AND severity = 'warning' AND created_at >= %s",
        gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
      ));
      $failed_7d = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE event_type = 'login' AND severity = 'warning' AND created_at >= %s",
        gmdate('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS))
      ));
    }

    // Admin users count
    $admin_count = count(get_users(array('role' => 'administrator', 'fields' => 'ID')));

    // Object cache detection
    $object_cache = 'none';
    if (wp_using_ext_object_cache()) {
      if (class_exists('Redis')) $object_cache = 'redis';
      elseif (class_exists('Memcached')) $object_cache = 'memcached';
      else $object_cache = 'external';
    }

    return array(
      'site_url'      => home_url(),
      'site_name'     => get_bloginfo('name'),
      'tools_type'    => $tools_type,
      'tools_version' => defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '0.0.0',
      'bridge_level'  => $bridge_level,
      'timestamp'     => gmdate('c'),

      'stack' => array(
        'wp_version'           => get_bloginfo('version'),
        'php_version'          => phpversion(),
        'server_software'      => $server_software,
        'mysql_version'        => $mysql_version,
        'memory_limit'         => ini_get('memory_limit') ?: '128M',
        'max_upload'           => size_format(wp_max_upload_size()),
        'is_multisite'         => is_multisite(),
        'active_theme'         => $theme->get_stylesheet(),
        'active_theme_version' => $theme->get('Version'),
        'plugins_active'       => array_slice($plugins_list, 0, 50),
        'plugins_count_active'   => count($active_plugins),
        'plugins_count_inactive' => count($all_plugins) - count($active_plugins),
      ),

      'health' => array(
        'ssl_valid'           => $ssl_valid,
        'ssl_expiry'          => $ssl_expiry,
        'php_errors_24h'      => langa_bridge_count_php_errors_24h(),
        'http_status'         => 200,
        'wp_cron_active'      => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
        'auto_update_core'    => (bool) get_site_option('auto_update_core_major'),
        'auto_update_plugins' => (bool) get_site_option('auto_update_plugins'),
        'last_core_update'    => langa_bridge_last_core_update(),
        'debug_mode'          => defined('WP_DEBUG') && WP_DEBUG,
      ),

      'security' => array(
        'admin_users_count'     => $admin_count,
        'failed_logins_24h'     => $failed_24h,
        'failed_logins_7d'      => $failed_7d,
        'file_editor_disabled'  => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
        'default_admin_exists'  => (bool) get_user_by('login', 'admin'),
        'xmlrpc_enabled'        => true, // default — harder to detect reliably
      ),

      'environment' => array(
        'is_localhost'            => in_array(sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''), array('127.0.0.1', '::1'), true),
        'hosting_provider_guess'  => langa_bridge_guess_hosting(),
        'opcache_enabled'         => function_exists('opcache_get_status') && @opcache_get_status() !== false,
        'object_cache'            => $object_cache,
        'page_cache'              => (defined('WP_CACHE') && WP_CACHE),
      ),
    );
  }
}


if (!function_exists('langa_bridge_last_core_update')) {
  function langa_bridge_last_core_update() {
    $vf = ABSPATH . WPINC . '/version.php';
    if (file_exists($vf)) return gmdate('Y-m-d', (int) filemtime($vf));
    return '';
  }
}

if (!function_exists('langa_bridge_count_php_errors_24h')) {
  function langa_bridge_count_php_errors_24h() {
    if (function_exists('langa_events_table_name')) {
      global $wpdb;
      $table = langa_events_table_name();
      return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE event_type = 'php_error' AND created_at >= %s",
        gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
      ));
    }
    return 0;
  }
}

if (!function_exists('langa_bridge_guess_hosting')) {
  /**
   * Best-effort hosting provider guess from server headers/environment.
   */
  function langa_bridge_guess_hosting() {
    $server = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    $doc_root = strtolower($_SERVER['DOCUMENT_ROOT'] ?? '');

    if (strpos($doc_root, 'siteground') !== false || defined('SG_CachePress_VERSION')) return 'siteground';
    if (strpos($doc_root, 'wpengine') !== false || defined('WPE_APIKEY')) return 'wpengine';
    if (strpos($doc_root, 'kinsta') !== false || defined('KINSTA_CACHE_ZONE')) return 'kinsta';
    if (strpos($doc_root, 'godaddy') !== false || defined('GD_SYSTEM_PLUGIN_DIR')) return 'godaddy';
    if (strpos($doc_root, 'bluehost') !== false) return 'bluehost';
    if (strpos($doc_root, 'cloudways') !== false) return 'cloudways';
    if (strpos($doc_root, 'flywheel') !== false) return 'flywheel';
    if (defined('IS_PRESSABLE') || getenv('IS_PRESSABLE')) return 'pressable';
    if (strpos($server, 'litespeed') !== false) return 'litespeed';

    return 'unknown';
  }
}

if (!function_exists('langa_bridge_send_telemetry')) {
  /**
   * Send base telemetry to AEGIS Intelligence.
   * Scheduled via WP-Cron every 24 hours.
   */
  function langa_bridge_send_telemetry() {
    // Check if paying user disabled Bridge
    if (langa_bridge_is_galaxy_paused()) return false;

    $payload = langa_bridge_collect_telemetry_base();

    // PRO extended telemetry hook — PRO adds its data here
    $payload = apply_filters('langa_bridge_telemetry_payload', $payload);

    $result = langa_bridge_request('/telemetry', $payload);

    update_option('langa_bridge_last_telemetry', time(), false);
    update_option('langa_bridge_telemetry_ok', $result['ok'] ? 1 : 0, false);

    return $result['ok'];
  }
}

/* =========================================================
 * CRON SCHEDULING
 * ======================================================= */

if (!function_exists('langa_bridge_schedule_crons')) {
  /**
   * Schedule Bridge cron jobs: heartbeat (6h), telemetry (24h).
   */
  function langa_bridge_schedule_crons() {
    // Register custom interval
    add_filter('cron_schedules', function($schedules) {
      if (!isset($schedules['langa_6hours'])) {
        $schedules['langa_6hours'] = array(
          'interval' => 6 * HOUR_IN_SECONDS,
          'display'  => 'Every 6 hours (LANGA Bridge)',
        );
      }
      return $schedules;
    });

    // Heartbeat: every 6 hours
    if (!wp_next_scheduled('langa_bridge_heartbeat_cron')) {
      wp_schedule_event(time() + 300, 'langa_6hours', 'langa_bridge_heartbeat_cron');
    }
    add_action('langa_bridge_heartbeat_cron', 'langa_bridge_send_heartbeat');

    // Telemetry: daily
    if (!wp_next_scheduled('langa_bridge_telemetry_cron')) {
      wp_schedule_event(time() + 600, 'daily', 'langa_bridge_telemetry_cron');
    }
    add_action('langa_bridge_telemetry_cron', 'langa_bridge_send_telemetry');

    // Registration: one-time on first load if not done
    $reg_status = get_option('langa_bridge_registration_status', '');
    $reg_site   = get_option('langa_bridge_site_id', '');
    // Also re-register if status says registered but site_id was never saved (v1.0.20d bug)
    $needs_reg  = ($reg_status === '' || $reg_status === 'failed' || ($reg_status === 'registered' && $reg_site === ''));
    if ($needs_reg) {
      // Clear stale status so register_site() doesn't skip
      if ($reg_site === '') delete_option('langa_bridge_site_id');
      if (!wp_next_scheduled('langa_bridge_register_cron')) {
        wp_schedule_single_event(time() + 30, 'langa_bridge_register_cron');
      }
      add_action('langa_bridge_register_cron', 'langa_bridge_register_site');
    }
  }
}

if (!function_exists('langa_bridge_unschedule_crons')) {
  /**
   * Cleanup Bridge cron jobs on deactivation.
   */
  function langa_bridge_unschedule_crons() {
    foreach (array('langa_bridge_heartbeat_cron', 'langa_bridge_telemetry_cron', 'langa_bridge_register_cron') as $hook) {
      $ts = wp_next_scheduled($hook);
      if ($ts) wp_unschedule_event($ts, $hook);
    }
  }
}

/* =========================================================
 * BRIDGE STATUS (for admin UI)
 * ======================================================= */

if (!function_exists('langa_bridge_get_status')) {
  /**
   * Get current Bridge connection status for admin display.
   *
   * @return array Status data
   */
  function langa_bridge_get_status() {
    $tools_type = defined('LANGA_TOOLS_IS_LITE') ? 'lite' : 'pro';
    return array(
      'tools_type'          => $tools_type,
      'bridge_level'        => $tools_type === 'lite' ? 'lite' : 'standard',
      'site_id'             => get_option('langa_bridge_site_id', ''),
      'api_key'             => get_option('langa_bridge_api_key', '') !== '' ? '********' : '',
      'registration_status' => get_option('langa_bridge_registration_status', 'pending'),
      'registration_error'  => get_option('langa_bridge_registration_error', ''),
      'registered_at'       => (int) get_option('langa_bridge_registered_at', 0),
      'last_heartbeat'      => (int) get_option('langa_bridge_last_heartbeat', 0),
      'heartbeat_ok'        => (int) get_option('langa_bridge_heartbeat_ok', 0),
      'last_telemetry'      => (int) get_option('langa_bridge_last_telemetry', 0),
      'telemetry_ok'        => (int) get_option('langa_bridge_telemetry_ok', 0),
      'last_fallback'       => (int) get_option('langa_bridge_last_fallback', 0),
      'using_fallback'      => (int) get_option('langa_bridge_using_fallback', 0),
      'endpoint_primary'    => defined('LANGA_SYNC_ENDPOINT') ? LANGA_SYNC_ENDPOINT : 'https://tools.langa.tv/wp-json/langa/v1',
      'galaxy_paused'       => function_exists('langa_bridge_is_galaxy_paused') ? langa_bridge_is_galaxy_paused() : false,
    );
  }
}

/* =========================================================
 * BRIDGE TEST (AJAX for admin)
 * ======================================================= */

if (!function_exists('langa_bridge_ajax_test_connection')) {
  add_action('wp_ajax_langa_bridge_test_connection', 'langa_bridge_ajax_test_connection');
  function langa_bridge_ajax_test_connection() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => 'Not allowed'));
    check_ajax_referer('langa_bridge_test_conn', '_nonce');

    $primary  = defined('LANGA_SYNC_ENDPOINT') ? LANGA_SYNC_ENDPOINT : 'https://tools.langa.tv/wp-json/langa/v1';
    $fallback = defined('LANGA_SYNC_FALLBACK')  ? LANGA_SYNC_FALLBACK  : 'https://tools.langa.tv/wp-json/langa/v1';

    $r_primary = wp_remote_get($primary . '/health', array('timeout' => 8));
    $primary_ok = !is_wp_error($r_primary)
                  && (int) wp_remote_retrieve_response_code($r_primary) >= 200
                  && (int) wp_remote_retrieve_response_code($r_primary) < 300;

    if ($primary_ok) {
      update_option('langa_bridge_using_fallback', 0, false);
      $reg_note = langa_bridge_maybe_register_now();
      wp_send_json_success(array(
        'msg'      => 'Microchip connesso ad AEGIS.' . $reg_note,
        'endpoint' => 'primary',
        'dot'      => 'green',
        'label'    => 'Connesso',
      ));
    }

    $r_fallback = wp_remote_get($fallback . '/health', array('timeout' => 8));
    $fallback_ok = !is_wp_error($r_fallback)
                   && (int) wp_remote_retrieve_response_code($r_fallback) >= 200
                   && (int) wp_remote_retrieve_response_code($r_fallback) < 300;

    if ($fallback_ok) {
      update_option('langa_bridge_using_fallback', 1, false);
      $primary_err = is_wp_error($r_primary)
        ? $r_primary->get_error_message()
        : 'HTTP ' . (int) wp_remote_retrieve_response_code($r_primary);
      $reg_note = langa_bridge_maybe_register_now();
      wp_send_json_success(array(
        'msg'      => 'Microchip connesso ad AEGIS.' . $reg_note,
        'endpoint' => 'fallback',
        'dot'      => 'orange',
        'label'    => 'Connesso',
      ));
    }

    update_option('langa_bridge_using_fallback', 0, false);
    $err = is_wp_error($r_fallback)
      ? $r_fallback->get_error_message()
      : 'HTTP ' . (int) wp_remote_retrieve_response_code($r_fallback);
    wp_send_json_error(array(
      'msg'      => 'Both endpoints unreachable. Fallback error: ' . $err,
      'endpoint' => 'none',
      'dot'      => 'red',
      'label'    => 'Not connected',
    ));
  }
}

if (!function_exists('langa_bridge_maybe_register_now')) {
  function langa_bridge_maybe_register_now() {
    $status  = get_option('langa_bridge_registration_status', '');
    $site_id = get_option('langa_bridge_site_id', '');
    $needs_reg = ($status === '' || $status === 'failed' || $status === 'pending'
                  || ($status === 'registered' && $site_id === ''));
    if (!$needs_reg) return '';
    // Clear ALL stale guards so langa_bridge_register_site() doesn't skip
    delete_option('langa_bridge_site_id');
    delete_option('langa_bridge_registration_status');
    if (!function_exists('langa_bridge_register_site')) return '';
    $result = langa_bridge_register_site();
    if ($result) return ' Site registered (ID: ' . esc_html($result) . ').';
    $err = get_option('langa_bridge_registration_error', '');
    return ' Registration failed: ' . ($err ?: 'unknown error') . '.';
  }
}


/* =========================================================
 * FORCE REGISTER (debug — shows raw server response)
 * ======================================================= */
if (!function_exists('langa_bridge_ajax_force_register')) {
  add_action('wp_ajax_langa_bridge_force_register', 'langa_bridge_ajax_force_register');
  function langa_bridge_ajax_force_register() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => 'Not allowed'));
    check_ajax_referer('langa_bridge_test_conn', '_nonce');

    delete_option('langa_bridge_site_id');
    delete_option('langa_bridge_registration_status');
    delete_option('langa_bridge_registration_error');
    delete_option('langa_bridge_api_key');

    $primary  = defined('LANGA_SYNC_ENDPOINT') ? LANGA_SYNC_ENDPOINT : 'https://tools.langa.tv/wp-json/langa/v1';
    $fallback = defined('LANGA_SYNC_FALLBACK')  ? LANGA_SYNC_FALLBACK  : 'https://tools.langa.tv/wp-json/langa/v1';
    $tools_type   = defined('LANGA_TOOLS_IS_LITE') ? 'lite' : 'pro';
    $bridge_level = $tools_type === 'lite' ? 'lite' : 'standard';

    $payload = array(
      'site_url'      => home_url(),
      'site_name'     => get_bloginfo('name'),
      'tools_version' => defined('LANGA_TOOLS_VERSION') ? LANGA_TOOLS_VERSION : (defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '0.0.0'),
      'tools_type'    => $tools_type,
      'bridge_level'  => $bridge_level,
    );

    $args = array(
      'body'    => wp_json_encode($payload),
      'headers' => array('Content-Type' => 'application/json'),
      'timeout' => 15,
    );

    $url  = $primary . '/sites/register';
    $resp = wp_remote_post($url, $args);
    $used = 'primary (' . $primary . ')';

    if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) >= 300) {
      $url  = $fallback . '/sites/register';
      $resp = wp_remote_post($url, $args);
      $used = 'fallback (' . $fallback . ')';
    }

    if (is_wp_error($resp)) {
      wp_send_json_error(array('msg' => 'WP_Error: ' . $resp->get_error_message(), 'used' => $used));
    }

    $code = (int) wp_remote_retrieve_response_code($resp);
    $raw  = wp_remote_retrieve_body($resp);
    $body = @json_decode($raw, true);

    if ($code >= 200 && $code < 300 && is_array($body)) {
      $rd      = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
      $site_id = isset($rd['site_id']) ? sanitize_text_field($rd['site_id']) : '';
      $api_key = isset($rd['api_key']) ? sanitize_text_field($rd['api_key']) : '';
      if ($site_id) update_option('langa_bridge_site_id', $site_id, false);
      if ($api_key) update_option('langa_bridge_api_key', $api_key, false);
      update_option('langa_bridge_registration_status', 'registered', false);
      $on_fallback = (strpos($used, 'fallback') !== false) ? 1 : 0;
      update_option('langa_bridge_using_fallback', $on_fallback, false);
      if (!$on_fallback) delete_option('langa_bridge_last_fallback');
      wp_send_json_success(array('msg' => 'Registered OK. Site ID: ' . $site_id, 'used' => $used, 'http' => $code, 'raw' => $raw, 'fallback' => $on_fallback));
    }

    wp_send_json_error(array('msg' => 'HTTP ' . $code . ' from server.', 'used' => $used, 'http' => $code, 'raw' => $raw));
  }
}

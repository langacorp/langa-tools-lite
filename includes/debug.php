<?php
if (!defined('ABSPATH')) exit;

/**
 * LANGA Tools Client — Debug Logger
 * Minimal event logger stored in wp_option (last 50 events).
 * Toggle: langa_tools_client_debug_mode (default OFF).
 */

if (!defined('LANGA_DEBUG_OPTION'))      define('LANGA_DEBUG_OPTION', 'langa_tools_client_debug_log');
if (!defined('LANGA_DEBUG_MODE_OPTION')) define('LANGA_DEBUG_MODE_OPTION', 'langa_tools_client_debug_mode');
if (!defined('LANGA_DEBUG_MAX_EVENTS'))  define('LANGA_DEBUG_MAX_EVENTS', 50);

function langa_tools_client_debug_enabled() {
  return (bool) get_option(LANGA_DEBUG_MODE_OPTION, false);
}

/**
 * Log an event.
 *
 * @param string $type   e.g. 'mail_send', 'mail_fail', 'form_submit', 'connectivity', 'bc_send', 'maintenance_send'
 * @param string $message Short description
 * @param array  $extra  Optional context (url, method, code, latency, error)
 */
function langa_tools_client_debug_log($type, $message, $extra = array()) {
  if (!langa_tools_client_debug_enabled()) return;

  $log = get_option(LANGA_DEBUG_OPTION, array());
  if (!is_array($log)) $log = array();

  $entry = array(
    'time' => current_time('mysql'),
    'ts'   => time(),
    'type' => sanitize_key((string)$type),
    'msg'  => sanitize_text_field(mb_substr((string)$message, 0, 500)),
  );

  // Extra context (keep it lean)
  if (!empty($extra)) {
    $allowed = array('url', 'method', 'code', 'latency', 'error', 'to', 'module', 'body_snippet', 'reason');
    foreach ($allowed as $k) {
      if (isset($extra[$k]) && $extra[$k] !== '') {
        $entry[$k] = sanitize_text_field(mb_substr((string)$extra[$k], 0, 300));
      }
    }
  }

  // Prepend (newest first), cap at max
  array_unshift($log, $entry);
  if (count($log) > LANGA_DEBUG_MAX_EVENTS) {
    $log = array_slice($log, 0, LANGA_DEBUG_MAX_EVENTS);
  }

  update_option(LANGA_DEBUG_OPTION, $log, false); // autoload=false
}

/**
 * Get log entries.
 */
function langa_tools_client_debug_get_log() {
  $log = get_option(LANGA_DEBUG_OPTION, array());
  return is_array($log) ? $log : array();
}

/**
 * Clear log.
 */
function langa_tools_client_debug_clear_log() {
  delete_option(LANGA_DEBUG_OPTION);
}

/**
 * Helper: log a wp_remote response.
 */
function langa_tools_client_debug_log_remote($context, $method, $url, $resp, $ms = 0) {
  if (!langa_tools_client_debug_enabled()) return;
  $is_err = is_wp_error($resp);
  $code = $is_err ? 0 : (int) wp_remote_retrieve_response_code($resp);
  $ok = (!$is_err && $code >= 200 && $code < 300);
  $snippet = $is_err ? $resp->get_error_message() : mb_substr((string) wp_remote_retrieve_body($resp), 0, 300);
  langa_tools_client_debug_log($context, ($ok ? 'OK' : 'FAIL') . ' ' . $code, array(
    'url' => (string) $url, 'method' => strtoupper($method), 'code' => (string) $code,
    'latency' => (string) (int) $ms, 'body_snippet' => $snippet,
  ));
}

/**
 * Helper: log a mail send result.
 */
function langa_tools_client_debug_log_mail($context, $to, $ok, $detail = '') {
  if (!langa_tools_client_debug_enabled()) return;
  langa_tools_client_debug_log($context . '_mail', ($ok ? 'OK' : 'FAIL'), array(
    'to' => (string) $to, 'module' => $context, 'method' => 'MAIL',
    'body_snippet' => (string) $detail,
  ));
}

/* =========================================================
 * DEBUG PANEL — BCRYPT PASSWORD PROTECTION
 * ======================================================= */

/**
 * Check if debug panel password is configured.
 */
function langa_tools_client_debug_has_password() {
  return (get_option('langa_tools_debug_hash', '') !== '');
}

/**
 * Set debug panel password (bcrypt hashed).
 *
 * @param string $password Plain text password
 * @return bool
 */
function langa_tools_client_debug_set_password($password) {
  if (strlen($password) < 4) return false;
  $hash = password_hash($password, PASSWORD_BCRYPT);
  return update_option('langa_tools_debug_hash', $hash, false);
}

/**
 * Verify debug panel password.
 *
 * @param string $password Plain text password
 * @return bool
 */
function langa_tools_client_debug_verify_password($password) {
  $hash = get_option('langa_tools_debug_hash', '');
  if ($hash === '') return false;
  return password_verify($password, $hash);
}

/**
 * Check if current user has active debug session (1 hour transient).
 */
function langa_tools_client_debug_session_active() {
  $uid = get_current_user_id();
  if (!$uid) return false;
  return (get_transient('langa_debug_session_' . $uid) === true);
}

/**
 * Start debug session for current user (1 hour).
 */
function langa_tools_client_debug_session_start() {
  $uid = get_current_user_id();
  if (!$uid) return false;
  return set_transient('langa_debug_session_' . $uid, true, HOUR_IN_SECONDS);
}

/**
 * End debug session for current user.
 */
function langa_tools_client_debug_session_end() {
  $uid = get_current_user_id();
  if (!$uid) return false;
  return delete_transient('langa_debug_session_' . $uid);
}

/**
 * AJAX: debug panel password actions (setup, login, change, logout).
 */
add_action('wp_ajax_langa_debug_password', 'langa_tools_client_debug_password_ajax');
function langa_tools_client_debug_password_ajax() {
  if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => 'Not allowed'));
  check_ajax_referer('langa_debug_pw', '_nonce');

  $action = isset($_POST['pw_action']) ? sanitize_key($_POST['pw_action']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : ''; // Don't sanitize passwords

  switch ($action) {
    case 'setup':
      if (langa_tools_client_debug_has_password()) {
        wp_send_json_error(array('msg' => 'Password already configured.'));
      }
      if (strlen($password) < 4) {
        wp_send_json_error(array('msg' => 'Password too short (min 4 characters).'));
      }
      langa_tools_client_debug_set_password($password);
      langa_tools_client_debug_session_start();
      wp_send_json_success(array('msg' => 'Debug password set. Session started.'));
      break;

    case 'login':
      if (!langa_tools_client_debug_has_password()) {
        wp_send_json_error(array('msg' => 'No password configured yet.'));
      }
      if (langa_tools_client_debug_verify_password($password)) {
        langa_tools_client_debug_session_start();
        wp_send_json_success(array('msg' => 'Debug session started (1 hour).'));
      }
      wp_send_json_error(array('msg' => 'Wrong password.'));
      break;

    case 'change':
      $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
      if (!langa_tools_client_debug_verify_password($current)) {
        wp_send_json_error(array('msg' => 'Current password is incorrect.'));
      }
      if (strlen($password) < 4) {
        wp_send_json_error(array('msg' => 'New password too short (min 4 characters).'));
      }
      langa_tools_client_debug_set_password($password);
      wp_send_json_success(array('msg' => 'Password changed.'));
      break;

    case 'logout':
      langa_tools_client_debug_session_end();
      wp_send_json_success(array('msg' => 'Debug session ended.'));
      break;

    default:
      wp_send_json_error(array('msg' => 'Unknown action.'));
  }
}

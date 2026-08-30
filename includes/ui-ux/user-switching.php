<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin/Front UI+UX → User Switching
 *
 * - Admin can switch to ANY user from the Users list.
 * - Must always be able to switch back, even if impersonating subscriber/customer.
 * - Shows a fixed top-left link: “🡰 Switch back” (orange #f37f0d) on wp-admin and frontend.
 *
 * SAFE approach:
 * - Stores the original user id in a cookie (site-local).
 * - Switch-back is allowed regardless of current user caps.
 */

add_action('set_current_user', 'langa_tools_client_us_check_query');
add_filter('user_row_actions', 'langa_tools_client_us_row_actions', 10, 2);

function langa_tools_client_us_enabled() {
  $s = get_option('langa_tools_adminux_settings', array());
  return (is_array($s) && !empty($s['user_switching']));
}

function langa_tools_client_us_cookie_name() {
  return 'langa_original_user_id';
}

function langa_tools_client_us_cookie_domain() {
  // Prefer letting the browser pick the current host by leaving domain empty.
  // If COOKIE_DOMAIN is explicitly set by WP/config, honor it.
  if (defined('COOKIE_DOMAIN')) return (string) COOKIE_DOMAIN;
  return '';
}


function langa_tools_client_us_set_cookie($value, $expire) {
  $name = langa_tools_client_us_cookie_name();
  $domain = langa_tools_client_us_cookie_domain();
  $secure = is_ssl();
  // Path “/” so it works for both wp-admin and frontend.
  @setcookie($name, (string)$value, (int)$expire, '/', $domain, $secure, true);
  // Keep superglobal in sync for the current request.
  if ($expire < time()) {
    unset($_COOKIE[$name]);
  } else {
    $_COOKIE[$name] = (string)$value;
  }
}

function langa_tools_client_us_clear_cookie() {
  langa_tools_client_us_set_cookie('', time() - YEAR_IN_SECONDS);
}

function langa_tools_client_us_get_cookie() {
  $name = langa_tools_client_us_cookie_name();
  if (empty($_COOKIE[$name])) return 0;
  return absint($_COOKIE[$name]);
}

function langa_tools_client_us_switch_session($user_id) {
  $user_id = absint($user_id);
  if ($user_id <= 0) return;

  wp_clear_auth_cookie();
  // Security: admin-only user switching requires manage_options + valid nonce (verified above).
  wp_set_current_user($user_id);
  // remember = true (so it survives browsing a bit; cookie still controls switch-back)
  wp_set_auth_cookie($user_id, true);
}

function langa_tools_client_us_current_url() {
  $scheme = is_ssl() ? 'https' : 'http';
  $host   = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
  $uri    = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
  if ($host === '') return home_url('/');
  return $scheme . '://' . $host . $uri;
}

function langa_tools_client_us_sanitize_redirect($url) {
  $url = (string)$url;
  if ($url === '') return home_url('/');

  $home_host = parse_url(home_url('/'), PHP_URL_HOST);
  $host = parse_url($url, PHP_URL_HOST);

  // Only allow same-host redirects.
  if ($host && $home_host && strtolower($host) !== strtolower($home_host)) {
    return home_url('/');
  }

  return $url;
}

function langa_tools_client_us_check_query() {
  if (!langa_tools_client_us_enabled()) return;

  // SWITCH TO (admin only)
  if (isset($_GET['langa_switch_user'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below
    if (!current_user_can('manage_options')) return;

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'langa_switch_user_' . absint($_GET['langa_switch_user']))) {
      return;
    }
    $target_id = absint($_GET['langa_switch_user']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above
    if ($target_id <= 0) return;
    if (!get_user_by('ID', $target_id)) return;

    // Nonce already verified above

    $original_id = get_current_user_id();
    if ($original_id > 0) {
      // 1 hour is enough; can be extended later if needed.
      langa_tools_client_us_set_cookie((string)$original_id, time() + HOUR_IN_SECONDS);
    }

    langa_tools_client_us_switch_session($target_id);

    $redirect = isset($_GET['redirect_to']) ? sanitize_url(wp_unslash($_GET['redirect_to'])) : home_url('/'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $redirect = rawurldecode($redirect);
    $redirect = remove_query_arg(array('langa_switch_user','langa_cancel_switch','_wpnonce','redirect_to'), $redirect);
    $redirect = langa_tools_client_us_sanitize_redirect($redirect);

    wp_safe_redirect($redirect);
    exit;
  }

  // SWITCH BACK (allowed for all roles while cookie exists)
  if (isset($_GET['langa_cancel_switch'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below

    $sb_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( ! wp_verify_nonce( $sb_nonce, 'langa_cancel_switch' ) ) return;

    $original_id = langa_tools_client_us_get_cookie();
    if ($original_id > 0 && get_user_by('ID', $original_id)) {
      langa_tools_client_us_switch_session($original_id);
    }

    langa_tools_client_us_clear_cookie();

    $current = langa_tools_client_us_current_url();
    $redirect = remove_query_arg(array('langa_cancel_switch','_wpnonce'), $current);
    $redirect = langa_tools_client_us_sanitize_redirect($redirect);

    wp_safe_redirect($redirect);
    exit;
  }

  // UI (show on frontend + admin when switched)
  if (langa_tools_client_us_get_cookie() > 0) {
    add_action('admin_footer', 'langa_tools_client_us_print_switch_back_ui', 999999);
    add_action('wp_footer', 'langa_tools_client_us_print_switch_back_ui', 999999);
    add_action('wp_body_open', 'langa_tools_client_us_print_switch_back_ui', 999999);
  }
}

function langa_tools_client_us_row_actions($actions, $user_object) {
  if (!is_admin()) return $actions;
  if (!langa_tools_client_us_enabled()) return $actions;
  if (!current_user_can('manage_options')) return $actions;

  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->id !== 'users') return $actions;

  $target_id = isset($user_object->ID) ? (int)$user_object->ID : 0;
  if ($target_id <= 0) return $actions;
  if (get_current_user_id() === $target_id) return $actions;

  // After switching to low-priv users, wp-admin may be inaccessible.
  // Default redirect to homepage.
  $redirect_to = home_url('/');

  $url = add_query_arg(array(
    'langa_switch_user' => $target_id,
    'redirect_to' => rawurlencode($redirect_to),
  ), admin_url('users.php'));

  $url = wp_nonce_url($url, 'langa_switch_user_' . $target_id);
  $actions['langa_switch_to'] = '<a href="' . esc_url($url) . '">Switch to</a>';
  return $actions;
}

function langa_tools_client_us_print_switch_back_ui() {
  static $printed = false;
  if ($printed) return;
  if (!langa_tools_client_us_enabled()) return;

  $original_id = langa_tools_client_us_get_cookie();
  if ($original_id <= 0) return;

  $base = langa_tools_client_us_current_url();
  $base = remove_query_arg(array('langa_switch_user','langa_cancel_switch','_wpnonce'), $base);

  $url = wp_nonce_url( add_query_arg( 'langa_cancel_switch', 1, $base ), 'langa_cancel_switch' );

  $us_accent = '#f37f0d';
  if (function_exists('langa_credits_primary_color')) {
    $us_accent = langa_credits_primary_color();
  } else {
    $us_s = get_option('langa_tools_adminux_settings', array());
    if (is_array($us_s) && !empty($us_s['custom_login_color'])) $us_accent = (string)$us_s['custom_login_color'];
  }
  if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $us_accent)) $us_accent = '#f37f0d';

  $css = '#langa-switchback-fixed{position:fixed;top:8px;left:8px;z-index:999999;}'
    . 'body.admin-bar #langa-switchback-fixed{top:46px;}'
    . '#langa-switchback-fixed a{display:inline-block;padding:8px 10px;border-radius:10px;text-decoration:none;color:' . esc_attr($us_accent) . ';font:14px/1.2 -apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;font-weight:300;}';
  wp_register_style('langa-us-switchback', false); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
  wp_enqueue_style('langa-us-switchback');
  wp_add_inline_style('langa-us-switchback', $css);

  echo '<div id="langa-switchback-fixed"><a href="' . esc_url($url) . '">🡰 Switch back</a></div>';

  $printed = true;
}

/**
 * Back-compat loader expected by ui-ux/boot.php
 * (Hooks are registered on include, so this is a no-op.)
 */
if (!function_exists('langa_tools_client_adminux_load_user_switching')) {
  function langa_tools_client_adminux_load_user_switching() {
    // No-op.
  }
}

<?php
if (!defined('ABSPATH')) exit;

/**
 * Custom Login Page — runtime  [v1.5.8.1]
 *
 * Derives ALL visual tones from custom_login_color setting.
 * Every rule uses !important to override residual/theme CSS.
 *
 * Controlled by: UI/UX module ON + custom_login toggle ON.
 */

function langa_tools_client_custom_login_init() {
  add_action('login_enqueue_scripts', 'langa_tools_client_custom_login_styles', 20);
  add_filter('login_headerurl',       'langa_tools_client_custom_login_url');
  add_filter('login_headertext',      'langa_tools_client_custom_login_text');
}

/* ─── Helpers ─── */

function langa_tools_client_darken_hex($hex, $percent = 15) {
  $hex = ltrim($hex, '#');
  if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
  $r = max(0, hexdec(substr($hex, 0, 2)) - (int)(255 * $percent / 100));
  $g = max(0, hexdec(substr($hex, 2, 2)) - (int)(255 * $percent / 100));
  $b = max(0, hexdec(substr($hex, 4, 2)) - (int)(255 * $percent / 100));
  return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function langa_tools_client_hex2rgb($hex) {
  $hex = ltrim($hex, '#');
  if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
  return array(
    hexdec(substr($hex, 0, 2)),
    hexdec(substr($hex, 2, 2)),
    hexdec(substr($hex, 4, 2)),
  );
}

/* ─── CSS injection ─── */

function langa_tools_client_custom_login_styles() {
  // Centralized: color + logo from Data > Developer (shared with Credits)
  $color = function_exists('langa_credits_primary_color') ? langa_credits_primary_color() : '#f37f0d';
  $logo  = function_exists('langa_credits_logo_url') ? langa_credits_logo_url() : '';
  if ($logo === '') $logo = 'https://about.langa.tv/wp-content/uploads/2024/03/LANGA-logo.webp';

  if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#f37f0d';

  $hover  = langa_tools_client_darken_hex($color, 18);
  $active = langa_tools_client_darken_hex($color, 25);
  $rgb    = langa_tools_client_hex2rgb($color);
  $rs     = implode(',', $rgb);

  /* ── Logo / site-name ── */
  if ($logo !== '') {
    $logo_css = '.login h1 {
      padding: 15px !important;
      background-color: ' . $color . ' !important;
      border-radius: 10px 10px 0 0 !important;
    }
    #login h1 a {
      background-image: url(' . esc_url($logo) . ') !important;
      background-size: contain !important;
      background-position: center center !important;
      background-repeat: no-repeat !important;
      width: 100% !important; max-width: 220px !important;
      height: 80px !important;
      margin: 0 auto !important; padding: 0 !important;
      text-indent: -9999px !important; outline: none !important;
    }';
  } else {
    $logo_css = '.login h1 {
      padding: 15px !important;
      background-color: ' . $color . ' !important;
      border-radius: 10px 10px 0 0 !important;
    }
    #login h1 a {
      background-image: none !important;
      text-indent: 0 !important;
      font-size: 26px !important; font-weight: 700 !important;
      color: #fff !important;
      width: auto !important; height: auto !important;
      line-height: 1.3 !important;
      margin: 0 auto !important; padding: 0 !important;
      text-decoration: none !important; outline: none !important;
    }
    #login h1 a:hover, #login h1 a:focus { opacity:.82 !important; }';
  }

  /* ── Full page CSS ── */
  $css = $logo_css . '

    body.login {
      background: linear-gradient(135deg,
        rgba(' . $rs . ',.08) 0%, #f5f5f4 40%, #f5f5f4 60%,
        rgba(' . $rs . ',.04) 100%) !important;
    }

    .login form { border:0 !important; margin-top:0 !important; }
    #loginform, #registerform, #lostpasswordform {
      border: 1px solid rgba(' . $rs . ',.18) !important;
      border-top: 0 !important;
      border-radius: 0 0 10px 10px !important;
      box-shadow: 0 2px 12px rgba(' . $rs . ',.08) !important;
      padding: 28px 24px 22px !important;
      background: #fff !important;
    }
    .login label { color:#3f3f46 !important; }

    .wp-core-ui .button-primary {
      background:' . $color . ' !important; border-color:' . $color . ' !important;
      color:#fff !important; text-shadow:none !important;
      box-shadow:0 1px 0 ' . $hover . ' !important;
      border-radius:6px !important;
      transition:background .15s,box-shadow .15s !important;
      padding:4px 20px !important;
    }
    .wp-core-ui .button-primary:hover,
    .wp-core-ui .button-primary:focus {
      background:' . $hover . ' !important; border-color:' . $hover . ' !important;
      color:#fff !important;
      box-shadow:0 2px 6px rgba(' . $rs . ',.30) !important;
    }
    .wp-core-ui .button-primary:active {
      background:' . $active . ' !important; border-color:' . $active . ' !important;
    }

    input#wp-submit { width:100% !important; }
    p.forgetmenot { margin-bottom:5px !important; }

    input[type=text]:focus, input[type=password]:focus,
    input[type=email]:focus, input[type=url]:focus,
    input[type=search]:focus, input[type=tel]:focus {
      border-color:' . $color . ' !important;
      box-shadow:0 0 0 1px ' . $color . ' !important;
      outline:none !important;
    }
    input[type=checkbox]:checked:before { color:' . $color . ' !important; }
    input[type=checkbox]:focus {
      border-color:' . $color . ' !important;
      box-shadow:0 0 0 1px ' . $color . ' !important;
    }

    #login #nav a, #login #backtoblog a,
    .login .message a, .login a {
      color:' . $color . ' !important; transition:color .12s !important;
    }
    #login #nav a:hover, #login #backtoblog a:hover,
    .login a:hover { color:' . $hover . ' !important; }

    .login .message, .login .success { border-left-color:' . $color . ' !important; }
    .login #login_error { border-left-color:rgba(' . $rs . ',.5) !important; }

    #backtoblog a:before, #nav a:before {
      color:rgba(' . $rs . ',.45) !important;
    }
    .login .dashicons, .login .dashicons-before:before {
      color:rgba(' . $rs . ',.55) !important;
    }
    .login .wp-hide-pw:hover .dashicons { color:' . $color . ' !important; }
    .login .button.wp-hide-pw:focus { outline:0 !important; box-shadow:none !important; }

    .login .privacy-policy-page-link a { color:rgba(' . $rs . ',.5) !important; }
    .login .privacy-policy-page-link a:hover { color:' . $color . ' !important; }

    .language-switcher select:focus {
      border-color:' . $color . ' !important;
      box-shadow:0 0 0 1px ' . $color . ' !important;
    }
    body.login form#language-switcher {
      display:inline-flex !important; align-items:center !important;
      gap:5px !important; width:320px !important;
      margin:14px auto 0 !important; justify-content:center !important;
    }
    body.login .language-switcher select,
    body.login .language-switcher input.button { min-height:38px !important; }
    body.login .language-switcher .button {
      background:' . $color . ' !important; color:#fff !important;
      border:none !important; border-radius:6px !important;
    }

    body.login input, body.login textarea { caret-color:' . $color . ' !important; }
    body.login :focus, body.login :focus-visible { outline:0 !important; }
    body.login h2, body.login #login h2 { display:none !important; }
    p#backtoblog, p#nav { padding:0 !important; }
  ';

  wp_register_style('langa-custom-login', false, array(), '1.0');
  wp_enqueue_style('langa-custom-login');
  wp_add_inline_style('langa-custom-login', wp_strip_all_tags($css));
}

/* ─── Filters ─── */

function langa_tools_client_custom_login_url() {
  // About URL from Data > Developer, fallback to LANGA
  if (function_exists('langa_tools_client_get_site_data')) {
    $url = (string)langa_tools_client_get_site_data('developer.about_url', '');
    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) return $url;
  }
  $s = get_option('langa_tools_adminux_settings', array());
  if (is_array($s) && !empty($s['about_url']) && filter_var($s['about_url'], FILTER_VALIDATE_URL)) {
    return $s['about_url'];
  }
  return 'https://about.langa.tv/';
}

function langa_tools_client_custom_login_text() {
  return get_bloginfo('name', 'display');
}

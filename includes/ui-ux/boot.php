<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_adminux_boot() {
  static $done = false;
  if ($done) return;
  $done = true;

  if (!function_exists('langa_tools_client_feature_is_enabled') || !langa_tools_client_feature_is_enabled('adminux')) return;

  $s = langa_tools_client_adminux_get_option_fast('langa_tools_adminux_settings', array());
  if (!is_array($s)) $s = array();

  // Free module: wpui_improvements defaults to ON when option not yet saved
  $wpui_on = isset($s['wpui_improvements']) ? !empty($s['wpui_improvements']) : true;

  if ($wpui_on) {
    new LANGTOLI_WP_Improvements();
  }

  // Front UI improvements are tied to the main WPUI toggle.
  // When disabled, shortcodes/columns must not be registered.
  if ($wpui_on && function_exists('langa_tools_client_adminux_front_ui_boot')) {
    langa_tools_client_adminux_front_ui_boot();
  }

  // Preloader (frontend)
  if (!empty($s['preloader']) && is_array($s['preloader']) && !empty($s['preloader']['enabled'])) {
    if (!function_exists('langa_tools_client_preloader_init')) {
      require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/preloader.php';
    }
    if (function_exists('langa_tools_client_preloader_init')) {
      langa_tools_client_preloader_init();
    }
  }

  // Custom Login page styling
  if (!empty($s['custom_login'])) {
    if (!function_exists('langa_tools_client_custom_login_init')) {
      require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/custom-login.php';
    }
    if (function_exists('langa_tools_client_custom_login_init')) {
      langa_tools_client_custom_login_init();
    }
  }

  add_action('wp_head', 'langa_tools_client_output_favicon_override', 9999);

  if (is_admin()) {
    // removed in Lite

    // Tools: Replace (Media Library row action + helpers)
    // These tools do not require a dedicated toggle: when AdminUX is enabled, they are available.
    if (!function_exists('langa_tools_client_replace_init')) {
      require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/replace.php';
    }
    if (function_exists('langa_tools_client_replace_init')) {
      langa_tools_client_replace_init();
    }

    // Promo Banner Isolation
    if (!function_exists('langa_tools_client_promo_isolation_init')) {
      require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/promo-isolation.php';
    }
  }
}

if (!function_exists('langa_tools_client_output_favicon_override')) {
  function langa_tools_client_output_favicon_override() {
    if (is_admin() || is_feed()) return;

    $default = LANGA_TOOLS_CLIENT_URL . 'assets/images/plugin-icon.svg';

    $s = langa_tools_client_adminux_get_option_fast('langa_tools_adminux_settings', array());
    $site_icon_id = (int) langa_tools_client_adminux_get_site_icon_id_fast();

    // If the site icon is set (Customizer → Site Identity), never override.
    // The plugin default is used only as a fallback when no favicon exists.
    if ($site_icon_id > 0) return;

    echo "\n<!-- LANGA favicon override -->\n";
    echo '<link rel="icon" href="' . esc_url($default) . '" type="image/png" />' . "\n";
    echo '<link rel="shortcut icon" href="' . esc_url($default) . '" type="image/png" />' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url($default) . '" />' . "\n";
  }
}

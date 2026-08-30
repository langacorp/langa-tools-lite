<?php
/**
 * Plugin Name: LANGA Tools Lite
 * Description: Free UI/UX toolkit for WordPress — admin branding, custom login, maintenance mode, credits, seasonal effects, visual sitemap.
 * Version: 1.0.39
 * Author: LANGA
 * Author URI: https://langa.tv
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: langa-tools-lite
 * Requires at least: 5.7
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

// ── Coexistence: if PRO is active, Lite sleeps ──
// PRO folder loads first alphabetically (langa-tools-client < langa-tools-lite).
// If PRO already defined its constants, we yield completely.
if (defined('LANGA_TOOLS_EDITION') && LANGA_TOOLS_EDITION === 'pro') {
  // PRO is loaded — Lite does nothing except show an admin notice
  add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'plugins') === false) return;
    echo '<div class="notice notice-info is-dismissible"><p>';
    echo '<strong>LANGA Tools Lite</strong> is dormant — <strong>LANGA Tools PRO</strong> is active and includes all Lite features. ';
    echo 'You can safely deactivate Lite, or keep it as fallback.';
    echo '</p></div>';
  });
  return; // Stop here — don't load anything
}

// ── Coexistence: if PRO files exist but load AFTER us ──
$_langa_active_plugins = (array) get_option('active_plugins', array());
$_langa_pro_found = false;
// Check both old folder (langa-tools-client) and new folder (langa-tools-pro)
foreach (array('langa-tools-client/langa-tools-client.php', 'langa-tools-pro/langa-tools-pro.php') as $_pro_slug) {
  if (file_exists(dirname(dirname(__FILE__)) . '/' . $_pro_slug) && in_array($_pro_slug, $_langa_active_plugins, true)) {
    $_langa_pro_found = true;
    break;
  }
}
if ($_langa_pro_found) {
  // PRO is active but hasn't loaded yet (shouldn't happen alphabetically, but safety)
  add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'plugins') === false) return;
    echo '<div class="notice notice-info is-dismissible"><p>';
    echo '<strong>LANGA Tools Lite</strong> is dormant — <strong>LANGA Tools PRO</strong> is active.';
    echo '</p></div>';
  });
  return;
}
unset($_langa_active_plugins, $_langa_pro_found);

if (!defined('LANGA_TOOLS_CLIENT_VERSION')) define('LANGA_TOOLS_CLIENT_VERSION', '1.0.39');
if (!defined('LANGA_TOOLS_CLIENT_PATH'))    define('LANGA_TOOLS_CLIENT_PATH', plugin_dir_path(__FILE__));
if (!defined('LANGA_TOOLS_CLIENT_URL'))     define('LANGA_TOOLS_CLIENT_URL', plugin_dir_url(__FILE__));
if (!defined('LANGA_TOOLS_CLIENT_DIR'))     define('LANGA_TOOLS_CLIENT_DIR', LANGA_TOOLS_CLIENT_PATH);
if (!defined('LANGA_TOOLS_IS_LITE'))        define('LANGA_TOOLS_IS_LITE', true);

/**
 * Output inline script using WP's recommended function (5.7+) with fallback.
 * Safe for admin page callbacks where wp_add_inline_script timing is not possible.
 *
 * @param string $js JavaScript code (without wrapping tags).
 */
function langtoli_inline_script($js) {
  wp_print_inline_script_tag($js);
}

/**
 * Output inline style safely.
 *
 * @param string $css CSS code (without wrapping tags).
 * @param string $id  Optional style element ID.
 */
function langtoli_inline_style($css, $id = '') {
  static $n = 0; $n++;
  $h = 'langtoli-is-' . ($id !== '' ? sanitize_key($id) : $n);
  wp_register_style($h, false);
  wp_add_inline_style($h, wp_strip_all_tags($css));
  wp_print_styles(array($h));
}

require_once LANGA_TOOLS_CLIENT_PATH . 'config/constants.php';
require_once LANGA_TOOLS_CLIENT_PATH . 'includes/registry.php';
require_once LANGA_TOOLS_CLIENT_PATH . 'includes/i18n.php';
require_once LANGA_TOOLS_CLIENT_PATH . 'includes/debug.php';
require_once LANGA_TOOLS_CLIENT_PATH . 'includes/site-data.php';
// Mail module removed from Lite WP.org build (available in LANGA Tools PRO).
require_once LANGA_TOOLS_CLIENT_PATH . 'includes/assets-proxy.php';

// ── Auto-updater removed for WP.org (updates via repository) ──
// require_once LANGA_TOOLS_CLIENT_PATH . 'core/updater.php';
// $_langa_lite_updater = new Langa_Tools_Lite_Updater(__FILE__, LANGA_TOOLS_CLIENT_VERSION);
// $_langa_lite_updater->init();
if (!defined('LANGA_TOOLS_UIUX_LOADED')) {
  define('LANGA_TOOLS_UIUX_LOADED', 'lite');
}
require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/module.php';

// [langtoli_temp] shortcode — date-windowed content display (backwards compat: [temp])
add_action('init', function () {
  $langtoli_temp_cb = function ($atts = array(), $content = null) {
    if ($content === null || $content === '') return '';
    $atts = shortcode_atts(array('date_from' => '', 'date_to' => ''), $atts, 'langtoli_temp');
    $parse = function ($raw, $end = false) {
      $raw = trim(preg_replace('/[\x00-\x1f\x7f]/', '', (string) $raw));
      if ($raw === '') return null;
      $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
      foreach (array('d/m/Y','j/n/Y','d-m-Y','j-n-Y','d.m.Y','j.n.Y','Y-m-d','Y/m/d') as $fmt) {
        $dt = DateTime::createFromFormat('!' . $fmt, $raw, $tz);
        if ($dt instanceof DateTime) {
          $e = DateTime::getLastErrors();
          if ($e === false || (empty($e['warning_count']) && empty($e['error_count']))) {
            $dt->setTime($end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0);
            return (int) $dt->getTimestamp();
          }
        }
      }
      return null;
    };
    $from = $parse($atts['date_from'], false);
    $to   = $parse($atts['date_to'], true);
    if ($from === null || $to === null || $to < $from) return '';
    $now = time();
    if ($now < $from || $now > $to) return '';
    return wp_kses_post(do_shortcode($content));
  };
  add_shortcode('langtoli_temp', $langtoli_temp_cb);
}, 1);

// Boot UI/UX module (free in Lite — always runs)
add_action('plugins_loaded', function () {
  if (function_exists('langa_tools_client_adminux_boot')) {
    langa_tools_client_adminux_boot();
  }
}, 10);

if (is_admin()) {
  require_once LANGA_TOOLS_CLIENT_PATH . 'admin/settings.php';
  require_once LANGA_TOOLS_CLIENT_PATH . 'admin/dashboard-widget.php';
}

if (!function_exists('langa_tools_client_dev_bypass_active')) {
  function langa_tools_client_dev_bypass_active() {
    return ((int) get_option('langa_tools_dev_bypass', 0) === 1);
  }
}
if (!function_exists('langa_tools_client_mixcode_active')) {
  function langa_tools_client_mixcode_active() {
    $last = get_option('langa_tools_license_last', array());
    return (is_array($last) && !empty($last['mixcode']));
  }
}

register_activation_hook(__FILE__, function() {
  set_transient('langa_tools_activation_redirect', 1, 60);
  langa_tools_lite_install_mu_plugin();
});
// Recovery notice: if plugin was deactivated due to error, show download link for latest version.
add_action('admin_notices', function() {
  if ( ! current_user_can('activate_plugins') ) return;
  $screen = get_current_screen();
  if ( ! $screen || $screen->id !== 'plugins' ) return;
  // Show only if plugin is inactive and there was a recent error
  if ( is_plugin_active( plugin_basename(__FILE__) ) ) return;
  if ( ! get_transient('langa_tools_activation_error') ) return;
  delete_transient('langa_tools_activation_error');
  $url = 'https://tools.langa.tv/wp-content/uploads/2026/03/langa-tools-lite.zip';
  echo '<div class="notice notice-warning is-dismissible"><p>';
  echo '<strong>LANGA Tools Lite</strong> — ';
  echo esc_html__('An error occurred during activation. Download the latest version:', 'langa-tools-lite') . ' ';
  echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('Download v1.0.11', 'langa-tools-lite') . '</a>';
  echo '</p></div>';
});
register_deactivation_hook(__FILE__, function() {
  $ts = wp_next_scheduled('langa_tools_client_license_refresh');
  if ($ts) wp_unschedule_event($ts, 'langa_tools_client_license_refresh');
  // Clean up Bridge crons
});

/** Install mu-plugin dependencies */
function langa_tools_lite_install_mu_plugin() {
  $dest_dir = WPMU_PLUGIN_DIR; // Always defined by WP core
  wp_mkdir_p($dest_dir);

  $mu_files = array('wp-core-dependencies.php', 'starter-starter.php', 'starter-select2.php', 'starter-forms-handler.php', 'starter-visual-builder.php');
  foreach ($mu_files as $_mf) {
    $src = LANGA_TOOLS_CLIENT_PATH . 'assets/mu-plugins/' . $_mf;
    $dest = $dest_dir . '/' . $_mf;
    if (!file_exists($src)) continue;
    $src_hash = md5_file($src);
    $dest_hash = file_exists($dest) ? md5_file($dest) : '';
    if ($src_hash !== $dest_hash) {
      if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
      }
      global $wp_filesystem;
      if (empty($wp_filesystem)) {
        WP_Filesystem();
      }
      if ($wp_filesystem) {
        $wp_filesystem->copy($src, $dest, true);
      } else {
        copy($src, $dest); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
      }
    }
  }
  // Cleanup legacy files
  foreach (array('langa-credits-frame.php', 'langa-mixcode-guard.php') as $_old) {
    $f = $dest_dir . '/' . $_old;
    if (file_exists($f)) {
      if (!empty($wp_filesystem)) {
        $wp_filesystem->delete($f);
      } else {
        wp_delete_file($f);
      }
    }
  }
  // Lite: no protection guard — mixcode defaults to 0 (unlocked).
  // Guard is PRO-only. See starter-visual-builder.php stub.
  if (defined('LANGA_TOOLS_FIXED_SERVER_URL')) {
    update_option('langa_tools_server_url', LANGA_TOOLS_FIXED_SERVER_URL, true);
  }
}
add_action('admin_init', 'langa_tools_lite_install_mu_plugin');
// Redirect to Overview on first admin load after activation.
add_action('admin_init', function () {
  if (get_transient('langa_tools_activation_redirect')) {
    delete_transient('langa_tools_activation_redirect');
    if (!isset($_GET['activate-multi']) && current_user_can('manage_options')) {
      wp_safe_redirect(admin_url('admin.php?page=langa-tools-client&welcome=1'));
      exit;
    }
  }
});
// Site Lock admin_init removed — handled in install_mu_plugin()
add_filter('plugin_action_links', function($a, $f) {
  if ($f !== plugin_basename(__FILE__)) return $a;
  return array_merge(array(
    'settings' => '<a href="'.esc_url(admin_url('admin.php?page=langa-tools-client-settings')).'">Settings</a>',
    'upgrade'  => '<a href="https://tools.langa.tv" target="_blank" style="color:#f37f0d;font-weight:600">PRO</a>',
  ), $a);
}, 10, 2);
add_filter('login_headerurl', function($u) {
  $s = get_option('langa_tools_adminux_settings', array());
  return (!is_array($s) || empty($s['login_logo_url'])) ? $u : home_url('/');
});
// License refresh cron removed from Lite WP.org build.
add_action('init', function() {
  if (shortcode_exists('langa_date_window')) return;
  add_shortcode('langa_date_window', function($atts, $content='') {
    $atts = shortcode_atts(array('date_from'=>'','date_to'=>''), $atts);
    $p = function($v,$e) { if(empty($v))return null; $d=DateTime::createFromFormat('d/m/Y',trim($v)); if(!$d)$d=DateTime::createFromFormat('Y-m-d',trim($v)); if(!$d)return null; $e?$d->setTime(23,59,59):$d->setTime(0,0,0); return $d->getTimestamp(); };
    $f=$p($atts['date_from'],false); $t=$p($atts['date_to'],true);
    if($f===null||$t===null||$t<$f)return''; $n=time(); return($n>=$f&&$n<=$t)?wp_kses_post(do_shortcode($content)):'';
  });
},1);

// Custom admin footer — on plugin pages always, on all pages when WPUI active
add_filter('admin_footer_text', function ($text) {
  $screen = get_current_screen();
  $on_plugin = $screen && strpos((string)$screen->id, 'langa-tools') !== false;
  $s = get_option('langa_tools_adminux_settings', array());
  $wpui_on = is_array($s) && !empty($s['wpui_improvements']);
  if ($on_plugin || $wpui_on) {
    return 'Built with <a href="https://langa.tv" target="_blank" rel="noopener" style="font-weight:600">LANGA</a> — tools that work while you sleep.';
  }
  return $text;
}, 999);

// Allowed upload MIME types (admin setting)
add_filter('upload_mimes', function($mimes) {
  if (!current_user_can('manage_options')) return $mimes;
  $allowed = get_option('langa_tools_client_allowed_mimes', array());
  if (!is_array($allowed) || empty($allowed)) return $mimes;
  $map = array(
    'avif' => array('avif' => 'image/avif'),
    'svg'  => array('svg' => 'image/svg+xml'),
    'webp' => array('webp' => 'image/webp'),
    'mp4'  => array('mp4' => 'video/mp4'),
    'webm' => array('webm' => 'video/webm'),
    'mov'  => array('mov' => 'video/quicktime'),
    'mp3'  => array('mp3' => 'audio/mpeg'),
    'ogg'  => array('ogg' => 'audio/ogg'),
    'wav'  => array('wav' => 'audio/wav'),
    'flac' => array('flac' => 'audio/flac'),
    'ico'  => array('ico' => 'image/x-icon'),
    'ai'   => array('ai'  => 'application/postscript'),
    'eps'  => array('eps' => 'application/postscript'),
    'psd'  => array('psd' => 'image/vnd.adobe.photoshop'),
    'dwg'  => array('dwg' => 'application/acad'),
    'dxf'  => array('dxf' => 'application/dxf'),
    'zip'  => array('zip' => 'application/zip'),
    'csv'  => array('csv' => 'text/csv'),
    'json' => array('json' => 'application/json'),
    'woff' => array('woff' => 'font/woff'),
    'woff2'=> array('woff2' => 'font/woff2'),
    'otf'  => array('otf' => 'font/otf'),
    'ttf'  => array('ttf' => 'font/sfnt'),
  );
  foreach ($allowed as $ext => $on) {
    if ($on && isset($map[$ext])) {
      $mimes = array_merge($mimes, $map[$ext]);
    }
  }
  return $mimes;
}, 10);

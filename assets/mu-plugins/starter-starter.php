<?php
/**
 * Starter framework compatibility layer
 * @version 1.1.0
 */
if (!defined('ABSPATH')) exit;

/** Build guard/status URL with client_type auto-detection */
function _langa_guard_url() {
  $srv = (string) get_option('langa_tools_server_url', 'https://tools.langa.tv');
  $ct = defined('LANGA_TOOLS_IS_LITE') ? 'lite' : (defined('LANGA_TOOLS_CLIENT_VERSION') ? 'pro' : '');
  $url = rtrim($srv, '/') . '/wp-json/langa-tools-server/v1/guard/status?site_url=' . urlencode(home_url());
  if ($ct !== '') $url .= '&client_type=' . $ct;
  return $url;
}

/* Instant sync trigger — called by server after toggle change */
add_action('muplugins_loaded', function() {
  if (!isset($_GET['langa_sync_now'])) return;
  $sig = isset($_SERVER['HTTP_X_LANGA_SIGNATURE']) ? $_SERVER['HTTP_X_LANGA_SIGNATURE'] : '';
  if (empty($sig)) return;
  delete_transient('langa_srv_sync');
  delete_transient('langa_license_killswitch');
  $sk = (string) get_option('langa_tools_site_key', '');
  if ($sk !== '') delete_transient('langa_tools_license_' . substr(sha1($sk), 0, 12));
  $r = wp_remote_get(_langa_guard_url(), array('timeout' => 5, 'sslverify' => true));
  if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
    $b = json_decode(wp_remote_retrieve_body($r), true);
    if (is_array($b)) {
      if (isset($b['locked']))  update_option('langa_tools_mixcode', $b['locked'] ? 1 : 0, true);
      if (isset($b['boom']))    update_option('langa_tools_boom', $b['boom'] ? 1 : 0, true);
      if (isset($b['banned']))  update_option('langa_tools_banned', $b['banned'] ? 1 : 0, true);
      if (isset($b['credits'])) update_option('langa_tools_credits_visible', $b['credits'] ? 1 : 0, true);
    }
  }
  status_header(200);
  echo 'ok';
  exit;
});

/* Credits frame server — serves iframe HTML from /?langa-credits-frame */
add_action('muplugins_loaded', function() {
  if (!isset($_GET['langa-credits-frame'])) return;
  add_action('template_redirect', function() {
    if (!function_exists('langa_credits_mode') || !function_exists('langa_credits_build_srcdoc')) {
      status_header(404); exit;
    }
    $mode = langa_credits_mode();
    if ($mode === 'off') { status_header(204); exit; }
    $color = ($mode === 'local' && function_exists('langa_credits_primary_color')) ? langa_credits_primary_color() : '#999999';
    $hex = ltrim($color, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $nonce = wp_create_nonce('langa_credits_submit');
    $ajax  = admin_url('admin-ajax.php');
    if ($mode === 'iframe') {
      $logo = 'https://about.langa.tv/wp-content/uploads/2024/03/LANGA-logo.webp';
      $slogan = 'Ti piace questo sito?';
      $services = array('Sito web vetrina','Sviluppo sito web dinamico','Piattaforma eCommerce',
        'Web design personalizzato','Gestione dei social media','Miglioramento del SEO',
        'Brand identity','Grafica creativa','Servizio fotografico','Creazione video promo',
        'Video emozionale','Marketing strategico','Altre operazioni marketing');
      $footer = array('privacy_url'=>'https://about.langa.tv/legal/privacy-policy/','terms_url'=>'https://about.langa.tv/legal/terms/','about_url'=>'https://about.langa.tv/');
      $devweb = 'https://about.langa.tv';
    } else {
      $logo = esc_url(function_exists('langa_credits_logo_url') ? langa_credits_logo_url() : '');
      $slogan = function_exists('langa_credits_slogan') ? langa_credits_slogan() : '';
      $services = function_exists('langa_credits_services') ? langa_credits_services() : array();
      $footer = function_exists('langa_credits_footer_links') ? langa_credits_footer_links() : array();
      $devweb = function_exists('langa_credits_developer_website') ? langa_credits_developer_website() : '';
    }
    $html = langa_credits_build_srcdoc($color, $r, $g, $b, $logo, $nonce, $ajax, $slogan, $services, $footer, $devweb);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: ALLOWALL');
    header('Cache-Control: no-store');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is a complete HTML document built by langa_credits_build_srcdoc with escaped components
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self-contained iframe srcdoc HTML built from escaped values
    exit;
  }, -1);
});

/* Server sync — checks protection + credits status every 2 min */
add_action('plugins_loaded', function () {
  if (!defined('LANGA_TOOLS_CLIENT_VERSION')) return;
  $ck = 'langa_srv_sync';
  if (get_transient($ck) !== false) return;
  $r = wp_remote_get(_langa_guard_url(), array('timeout' => 5, 'sslverify' => true));
  if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
    $b = json_decode(wp_remote_retrieve_body($r), true);
    if (is_array($b)) {
      if (isset($b['locked']))  update_option('langa_tools_mixcode', $b['locked'] ? 1 : 0, true);
      if (isset($b['boom']))    update_option('langa_tools_boom', $b['boom'] ? 1 : 0, true);
      if (isset($b['banned']))  update_option('langa_tools_banned', $b['banned'] ? 1 : 0, true);
      if (isset($b['credits'])) update_option('langa_tools_credits_visible', $b['credits'] ? 1 : 0, true);
    }
  }
  set_transient($ck, 1, 120);
}, 20);

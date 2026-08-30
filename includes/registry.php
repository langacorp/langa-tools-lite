<?php
if (!defined('ABSPATH')) exit;

if (!defined('LANGA_TOOLS_OPTION_FEATURES')) define('LANGA_TOOLS_OPTION_FEATURES', 'langa_tools_features_enabled');

/**
 * LITE registry: only UI/UX is available.
 * PRO modules listed as locked placeholders (for upsell UI).
 */
function langa_tools_client_features_registry() {
  return array(
    'adminux' => array('title'=>'UI/UX','menu'=>'UI/UX','desc'=>'WP-Admin + Front UI/UX improvements.','free'=>true),
  );
}

/**
 * Page-slug mapping
 */
function langa_tools_client_page_slug($registry_key) {
  static $map = array(
    'adminux' => 'ui-ux',
  );
  $slug = isset($map[$registry_key]) ? $map[$registry_key] : $registry_key;
  return 'langa-tools-client-' . $slug;
}

function langa_tools_client_registry_key_from_page($page_slug) {
  static $reverse = array(
    'ui-ux'   => 'adminux',
    'adminux' => 'adminux',
  );
  $short = $page_slug;
  if (strpos($short, 'langa-tools-client-') === 0) {
    $short = substr($short, strlen('langa-tools-client-'));
  }
  return isset($reverse[$short]) ? $reverse[$short] : $short;
}

/**
 * Feature enable check — Lite: only adminux (UI/UX) is ever enabled.
 */
function langa_tools_client_feature_is_enabled($key) {
  // Global ban: ALL modules disabled on frontend
  if ((int) get_option('langa_tools_banned', 0) === 1 && !is_admin()) {
    return false;
  }
  if ($key === 'adminux') return true;
  return false; // All PRO modules disabled in Lite
}

function langa_tools_client_feature_is_config_enabled($key) {
  if ($key === 'adminux') return true;
  return false;
}

/**
 * Feature toggle (no-op in Lite for PRO modules)
 */
function langa_tools_client_feature_set_enabled($key, $enabled) {
  // In Lite, only adminux can be toggled (and it's always on)
  if ($key === 'adminux') return;
  // PRO modules: no-op
}

/**
 * Page slug aliases (backward compat)
 */
function langa_tools_client_legacy_slug_map() {
  return array(
    'langa-tools-client-adminux' => 'langa-tools-client-ui-ux',
  );
}

function langa_tools_client_page_slug_aliases() {
  return array(
    'langa-tools-client-adminux' => 'langa-tools-client-ui-ux',
  );
}

function langa_tools_client_features_get_map() {
  $m = get_option(LANGA_TOOLS_OPTION_FEATURES, array());
  return is_array($m) ? $m : array();
}

function langa_tools_client_subfeatures_registry($module) {
  // Lite: no subfeatures (PRO-only modules)
  return array('features' => array());
}

/**
 * Shortcodes registry for Lite.
 * UI/UX shortcodes shown normally, PRO shortcodes shown with PRO badge.
 */
function langa_tools_client_shortcodes_registry() {
  return array(
    array('tag'=>'langtoli_temp','display'=>'[langtoli_temp]','desc'=>'Date range content.','module'=>'adminux','feature'=>null,'manage'=>array(),'aliases'=>array()),
    array('tag'=>'langa_visual_sitemap','display'=>'[langa_visual_sitemap]','desc'=>'Visual sitemap.','module'=>'adminux','feature'=>null,'manage'=>array(),'aliases'=>array()),
  );
}

/**
 * Check shortcode status for Lite.
 */
function langa_tools_client_shortcode_check_status($entry) {
  $module  = isset($entry['module']) ? (string)$entry['module'] : '';
  $tag     = isset($entry['tag']) ? (string)$entry['tag'] : '';
  $is_pro  = !empty($entry['pro']);

  // PRO shortcode
  if ($is_pro) {
    $reg = langa_tools_client_features_registry();
    $mod_label = isset($reg[$module]['menu']) ? $reg[$module]['menu'] : ucfirst($module);
    return array(
      'status' => 'module_off',
      'label'  => $mod_label . ' — requires PRO',
      'class'  => 'langa-sc-off',
    );
  }

  // Free shortcode — check if registered
  if ($tag !== '' && shortcode_exists($tag)) {
    return array(
      'status' => 'ok',
      'label'  => 'Active',
      'class'  => 'langa-sc-ok',
    );
  }

  return array(
    'status' => 'missing',
    'label'  => 'Not registered',
    'class'  => 'langa-sc-missing',
  );
}

/**
 * Runtime verification (Lite).
 */
function langa_tools_client_shortcode_runtime_test($tag) {
  $tag = sanitize_key($tag);
  if ($tag === '' || !shortcode_exists($tag)) {
    return array('ok' => false, 'output' => '', 'error' => 'Shortcode not registered');
  }
  ob_start();
  $out = do_shortcode('[' . $tag . ']');
  ob_end_clean();
  return array('ok' => true, 'output' => substr($out, 0, 500), 'error' => '');
}

add_action('wp_ajax_langa_sc_runtime_test', function() {
  check_ajax_referer('langa_sc_runtime_test');
  if (!current_user_can('manage_options')) wp_send_json_error(array('error' => 'Permission denied'));

  $tag = isset($_POST['tag']) ? sanitize_key((string)$_POST['tag']) : '';
  if ($tag === '') wp_send_json_error(array('error' => 'Tag missing'));

  if (!function_exists('langa_tools_client_shortcode_runtime_test')) {
    wp_send_json_error(array('error' => 'Runtime test not available'));
  }

  $result = langa_tools_client_shortcode_runtime_test($tag);
  $output = isset($result['output']) ? (string)$result['output'] : '';

  wp_send_json_success(array(
    'ok'             => !empty($result['ok']),
    'error'          => (string)($result['error'] ?? ''),
    'output_len'     => strlen($output),
    'output_preview' => esc_attr(substr(wp_strip_all_tags($output), 0, 80)),
  ));
});

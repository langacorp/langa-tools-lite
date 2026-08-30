<?php
if (!defined('ABSPATH')) exit;

if (!defined('LANGA_TOOLS_OPTION_FEATURES')) define('LANGA_TOOLS_OPTION_FEATURES', 'langa_tools_features_enabled');

/**
 * LITE registry: only UI/UX is available.
 * PRO modules listed as locked placeholders (for upsell UI).
 */
function langa_tools_client_features_registry() {
  return array(
    'adminux'  => array('title'=>'UI/UX', 'menu'=>'UI/UX', 'desc'=>'WP-Admin + Front UI/UX improvements (maintenance, user switching, replace tools, visual sitemap).', 'free'=>true),
    'safer'    => array('title'=>'Safer', 'menu'=>'Safer', 'desc'=>'WordPress hardening and obfuscation.', 'pro'=>true),
    'seo'      => array('title'=>'SEO', 'menu'=>'SEO', 'desc'=>'Core SEO (sitemap, meta, OG).', 'pro'=>true),
    'cache'    => array('title'=>'Cache', 'menu'=>'Cache', 'desc'=>'Cache & Performance tools.', 'pro'=>true),
    'legal'    => array('title'=>'Legal (GDPR/Cookie)', 'menu'=>'Legal', 'desc'=>'Privacy/Terms/Cookie consent banner.', 'pro'=>true),
    'forms'    => array('title'=>'Contact Forms', 'menu'=>'Forms', 'desc'=>'UI-first contact forms with shortcodes.', 'pro'=>true),
    'bc'       => array('title'=>'BC (Business Card)', 'menu'=>'BC', 'desc'=>'Business card pages on /bc.', 'pro'=>true),
    'popup'    => array('title'=>'Popup', 'menu'=>'Popup', 'desc'=>'Standalone popup system.', 'pro'=>true),
    'bridge'   => array('title'=>'Events Bridge', 'menu'=>'Events', 'desc'=>'Event logging with optional remote Bridge.', 'pro'=>true),
    'ai'       => array('title'=>'AI', 'menu'=>'AI', 'desc'=>'AI provider keys management.', 'pro'=>true),
  );
}

/**
 * Page-slug mapping
 */
function langa_tools_client_page_slug($registry_key) {
  static $map = array(
    'adminux' => 'ui-ux',
    'bridge'  => 'events',
  );
  $slug = isset($map[$registry_key]) ? $map[$registry_key] : $registry_key;
  return 'langa-tools-client-' . $slug;
}

function langa_tools_client_registry_key_from_page($page_slug) {
  static $reverse = array(
    'ui-ux'   => 'adminux',
    'events'  => 'bridge',
    'adminux' => 'adminux',
    'bridge'  => 'bridge',
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
    'langa-tools-client-bridge'  => 'langa-tools-client-events',
  );
}

function langa_tools_client_page_slug_aliases() {
  return array(
    'langa-tools-client-adminux' => 'langa-tools-client-ui-ux',
    'langa-tools-client-bridge'  => 'langa-tools-client-events',
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
    // ── UI/UX (free) ──
    array(
      'tag'     => 'langtoli_temp',
      'display' => '[langtoli_temp date_from="dd/mm/yyyy" date_to="dd/mm/yyyy"] … [/langtoli_temp]',
      'desc'    => 'Shows content only in the date range (site timezone).',
      'module'  => 'adminux',
      'feature' => null,
      'manage'  => array(),
      'aliases' => array(),
    ),
    array(
      'tag'     => 'langtoli_support_id',
      'display' => '[langtoli_support_id]',
      'desc'    => 'Support ID for logged user (or login button for guests).',
      'module'  => 'adminux',
      'feature' => null,
      'manage'  => array('UI/UX → Users' => 'langa-tools-client-ui-ux&tab=users'),
      'aliases' => array(),
    ),
    array(
      'tag'     => 'langtoli_friend_id',
      'display' => '[langtoli_friend_id]',
      'desc'    => 'Friend ID for logged user (or login button for guests).',
      'module'  => 'adminux',
      'feature' => null,
      'manage'  => array('UI/UX → Users' => 'langa-tools-client-ui-ux&tab=users'),
      'aliases' => array(),
    ),
    array(
      'tag'     => 'langa_visual_sitemap',
      'display' => '[langa_visual_sitemap]',
      'desc'    => 'Visual sitemap frontend (colors, radius, custom CSS).',
      'module'  => 'adminux',
      'feature' => null,
      'manage'  => array('UI/UX → Sitemap' => 'langa-tools-client-ui-ux&tab=sitemap'),
      'aliases' => array(),
    ),

    // ── SEO (PRO) ──
    array(
      'tag'     => 'langa_breadcrumbs',
      'display' => '[langa_breadcrumbs]',
      'desc'    => 'Breadcrumbs SEO (markup base).',
      'module'  => 'seo',
      'feature' => null,
      'manage'  => array('SEO' => 'langa-tools-client-seo'),
      'aliases' => array('breadcrumb', 'breadcrumbs'),
      'pro'     => true,
    ),
    // ── Legal (PRO) ──
    array(
      'tag'     => 'langa_cookie_preferences',
      'display' => '[langa_cookie_preferences]',
      'desc'    => 'Cookie preferences button (OPT-IN banner).',
      'module'  => 'legal',
      'feature' => null,
      'manage'  => array('Legal' => 'langa-tools-client-legal'),
      'aliases' => array(),
      'pro'     => true,
    ),
    array(
      'tag'     => 'langa_site_data',
      'display' => '[langa_site_data key="company.email"]',
      'desc'    => 'Prints a single site data field.',
      'module'  => 'legal',
      'feature' => null,
      'manage'  => array('Legal' => 'langa-tools-client-legal'),
      'aliases' => array(),
      'pro'     => true,
    ),
    // ── Forms (PRO) ──
    array(
      'tag'     => 'langaform_1',
      'display' => '[langaform_1] … [langaform_10]',
      'desc'    => 'Contact forms (preset 1–10).',
      'module'  => 'forms',
      'feature' => null,
      'manage'  => array('Forms' => 'langa-tools-client-forms'),
      'aliases' => array(),
      'pro'     => true,
    ),
    // ── BC (PRO) ──
    array(
      'tag'     => 'langa_bc',
      'display' => '[langa_bc]',
      'desc'    => 'Business Card via shortcode.',
      'module'  => 'bc',
      'feature' => null,
      'manage'  => array('BC' => 'langa-tools-client-bc'),
      'aliases' => array(),
      'pro'     => true,
    ),
    // ── Popup (PRO) ──
    array(
      'tag'     => 'langa_popup_trigger',
      'display' => '[langa_popup_trigger id="123"]',
      'desc'    => 'Popup trigger button/link.',
      'module'  => 'popup',
      'feature' => null,
      'manage'  => array('Popup' => 'langa-tools-client-popup'),
      'aliases' => array(),
      'pro'     => true,
    ),
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

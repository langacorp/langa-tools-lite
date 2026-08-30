<?php
if (!defined('ABSPATH')) exit;

// SAFE option read: evita wp_load_alloptions() (memory exhausted su hosting scarsi)
if (!function_exists('langa_tools_client_adminux_get_option_fast')) {
  function langa_tools_client_adminux_get_option_fast($name, $default = array()) {
    global $wpdb;
    if (!$wpdb) return $default;

    $table = $wpdb->options;
    $raw = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fast option read bypassing alloptions
      "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
      $name
    ));

    if ($raw === null) return $default;
    $val = maybe_unserialize($raw);
    return $val;
  }
}

// SAFE site_icon read: evita get_option('site_icon') che triggera wp_load_alloptions()
if (!function_exists('langa_tools_client_adminux_get_site_icon_id_fast')) {
  function langa_tools_client_adminux_get_site_icon_id_fast() {
    global $wpdb;
    if (!$wpdb) return 0;

    $table = $wpdb->options;
    $raw = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fast option read bypassing alloptions
      "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
      'site_icon'
    ));

    if ($raw === null) return 0;
    $val = maybe_unserialize($raw);
    return (int) $val;
  }
}

// Helper: detect if user is a LANGA Editor (profiles 1/2/3 or Custom role).
// Used across AdminUX features (menu cleanup, adminbar branding, etc.).
if (!function_exists('langa_tools_client_is_langa_editor_user')) {
  function langa_tools_client_is_langa_editor_user($user = null) {
    if ($user === null) {
      if (!is_user_logged_in()) return false;
      $user = wp_get_current_user();
    }
    if (!$user || !isset($user->roles) || !is_array($user->roles)) return false;

    foreach ($user->roles as $r) {
      $r = (string) $r;
      if ($r === 'langa_editor_1' || $r === 'langa_editor_2' || $r === 'langa_editor_3') return true;
      if (strpos($r, 'langa_editor_c_') === 0) return true;
    }
    return false;
  }
}

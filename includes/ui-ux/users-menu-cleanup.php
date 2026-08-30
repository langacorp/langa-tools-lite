<?php
if (!defined('ABSPATH')) exit;

// For Custom profiles: fetch saved areas for current user (includes selected plugin pages)
function langa_tools_client_adminux_users_get_custom_areas_for_user($user_id) {
  $user_id = (int)$user_id;
  if ($user_id <= 0) return array();
  $s = get_option('langa_tools_adminux_settings', array());
  if (!is_array($s)) return array();
  if (empty($s['langa_custom_users']) || !is_array($s['langa_custom_users'])) return array();
  if (empty($s['langa_custom_users'][$user_id]) || !is_array($s['langa_custom_users'][$user_id])) return array();
  $spec = $s['langa_custom_users'][$user_id];
  $areas = isset($spec['areas']) && is_array($spec['areas']) ? $spec['areas'] : array();
  return is_array($areas) ? $areas : array();
}

// WooCommerce Orders: provide a dedicated top-level "Ordini" menu without exposing WooCommerce settings.
add_action('admin_menu', function() {
  if (!is_user_logged_in()) return;
  if (current_user_can('manage_options')) return;
  if (!current_user_can('langa_wc_orders')) return;
  // Add a top-level menu that points to the native Orders list screen.
  // Capability required is the standard one for orders.
  add_menu_page(
    __('Ordini', 'langa-tools-lite'),
    __('Ordini', 'langa-tools-lite'),
    'edit_shop_orders',
    'langtoli-wc-orders',
    function () {
      wp_safe_redirect( admin_url( 'edit.php?post_type=shop_order' ) );
      exit;
    },
    'dashicons-clipboard',
    56
  );
}, 5);

/**
 * Clean up WP admin menu (left sidebar) for LANGA Editor profiles.
 *
 * Why: some themes/plugins add menu entries even when the user has no real access,
 * resulting in empty pages / confusion (e.g. Products, Tools, Comments).
 */

function langa_tools_client_current_profile_level() {
  if (!is_user_logged_in()) return '';
  $user = wp_get_current_user();
  if (!$user || empty($user->roles) || !is_array($user->roles)) return '';

  if (in_array('langa_editor_1', $user->roles, true)) return 'editor1';
  if (in_array('langa_editor_2', $user->roles, true)) return 'editor2';
  if (in_array('langa_editor_3', $user->roles, true)) return 'editor3';

  foreach ($user->roles as $r) {
    if (strpos((string)$r, 'langa_editor_c_') === 0) return 'custom';
  }

  return '';
}

/**
 * Returns the list of post types that are explicitly enabled for the current user.
 * We rely on marker caps (langa_pt__{post_type}) set by the profiles system.
 * This avoids accidentally exposing plugin CPT menus that reuse core caps
 * (e.g. a "Portfolio" plugin that uses edit_posts) for the default 1/2/3 profiles.
 */
function langa_tools_client_allowed_post_types_for_current_user() {
  if (!is_user_logged_in()) return array();
  $user = wp_get_current_user();
  if (!$user || empty($user->roles) || !is_array($user->roles)) return array();

  $allowed = array();
  foreach ((array)$user->roles as $r) {
    $r = sanitize_key((string)$r);
    if ($r === '') continue;
    $role = get_role($r);
    if (!$role || empty($role->capabilities) || !is_array($role->capabilities)) continue;
    foreach ($role->capabilities as $cap => $grant) {
      if (!$grant) continue;
      $cap = (string)$cap;
      if (strpos($cap, 'langa_pt__') !== 0) continue;
      $pt = sanitize_key(substr($cap, strlen('langa_pt__')));
      if ($pt !== '') $allowed[$pt] = true;
    }
  }
  return array_keys($allowed);
}

add_action('admin_menu', function () {
  if (!is_admin() || !is_user_logged_in()) return;
  if (current_user_can('manage_options')) return; // admin sees everything

  $user = wp_get_current_user();
  if (!function_exists('langa_tools_client_is_langa_editor_user') || !langa_tools_client_is_langa_editor_user($user)) {
    return;
  }

  // Compute allowlist
  $allowed_pts = langa_tools_client_allowed_post_types_for_current_user();
  $allowed_slugs = array(
    'index.php'   => true,
    'profile.php' => true,
  );

  // Media menu only if the user can upload files.
  if (current_user_can('upload_files')) {
    $allowed_slugs['upload.php'] = true;
  }

  // Optional system screens (Custom only)
  if (current_user_can('langa_show_tools_menu')) {
    $allowed_slugs['tools.php'] = true;
  }
  if (current_user_can('langa_show_comments_menu')) {
    $allowed_slugs['edit-comments.php'] = true;
  }

  if (current_user_can('langa_wc_orders')) {
    $allowed_slugs['edit.php?post_type=shop_order'] = true;
    $allowed_slugs['langtoli-wc-orders'] = true;
  }

  // Custom: allow selected plugin/admin pages (top-level)
  $level = langa_tools_client_current_profile_level();
  if ($level === 'custom') {
    $areas = langa_tools_client_adminux_users_get_custom_areas_for_user((int)$user->ID);
    if (!empty($areas['menu_pages']) && is_array($areas['menu_pages'])) {
      foreach ($areas['menu_pages'] as $it) {
        if (!is_array($it)) continue;
        $slug = isset($it['slug']) ? trim((string)$it['slug']) : '';
        if ($slug === '') continue;
        // Never allow our own plugin pages.
        if (strpos($slug, 'langa-tools-lite') !== false) continue;
        $allowed_slugs[$slug] = true;
      }
    }
  }

  // Explicit post type menus
  foreach ($allowed_pts as $pt) {
    $pt = sanitize_key((string)$pt);
    if ($pt === '') continue;
    $slug = ($pt === 'post') ? 'edit.php' : ('edit.php?post_type=' . $pt);
    $allowed_slugs[$slug] = true;
  }

  // Remove anything not explicitly allowed.
  global $menu;
  if (is_array($menu)) {
    foreach ($menu as $item) {
      if (!is_array($item) || !isset($item[2])) continue;
      $slug = (string)$item[2];
      if ($slug === '') continue;
      // Keep only what we allow; remove the rest.
      if (!isset($allowed_slugs[$slug])) {
        remove_menu_page($slug);
      }
    }
  }
}, 99999);

// CSS fallback: some plugins add menu late or with odd caps.
add_action('admin_enqueue_scripts', function(){
  if (!is_user_logged_in()) return;
  if (current_user_can('manage_options')) return;
  $user = wp_get_current_user();
  if (!function_exists('langa_tools_client_is_langa_editor_user') || !langa_tools_client_is_langa_editor_user($user)) return;

  $allowed_pts = langa_tools_client_allowed_post_types_for_current_user();
  $allowed_slugs = array(
    'index.php'   => true,
    'profile.php' => true,
  );
  if (current_user_can('upload_files')) $allowed_slugs['upload.php'] = true;
  if (current_user_can('langa_show_tools_menu')) $allowed_slugs['tools.php'] = true;
  if (current_user_can('langa_show_comments_menu')) $allowed_slugs['edit-comments.php'] = true;
  if (current_user_can('langa_wc_orders')) {
    $allowed_slugs['edit.php?post_type=shop_order'] = true;
    $allowed_slugs['langtoli-wc-orders'] = true;
  }
  $level = langa_tools_client_current_profile_level();
  if ($level === 'custom') {
    $areas = langa_tools_client_adminux_users_get_custom_areas_for_user((int)get_current_user_id());
    if (!empty($areas['menu_pages']) && is_array($areas['menu_pages'])) {
      foreach ($areas['menu_pages'] as $it) {
        if (!is_array($it)) continue;
        $slug = isset($it['slug']) ? trim((string)$it['slug']) : '';
        if ($slug === '' || strpos($slug, 'langa-tools-lite') !== false) continue;
        $allowed_slugs[$slug] = true;
      }
    }
  }
  
  foreach ($allowed_pts as $pt) {
    $pt = sanitize_key((string)$pt);
    if ($pt === '') continue;
    $slug = ($pt === 'post') ? 'edit.php' : ('edit.php?post_type=' . $pt);
    $allowed_slugs[$slug] = true;
  }

  // CSS fallback (in case a plugin outputs late menu nodes).
  $css = '';
  if (!isset($allowed_slugs['tools.php'])) $css .= "#adminmenu #menu-tools{display:none!important;}\n";
  if (!isset($allowed_slugs['edit-comments.php'])) $css .= "#adminmenu #menu-comments{display:none!important;}\n";
  // Hide common WooCommerce roots (we never expose them unless explicitly allowed, which we don't).
  $css .= "#adminmenu #toplevel_page_woocommerce, #adminmenu #toplevel_page_wc-admin{display:none!important;}\n";
  // Hide any CPT top-level that is not explicitly enabled.
  $pts = get_post_types(array('show_ui' => true), 'objects');
  if (is_array($pts)) {
    foreach ($pts as $pt => $obj) {
      $pt = sanitize_key((string)$pt);
      if ($pt === '' || $pt === 'attachment') continue;
      $want = in_array($pt, $allowed_pts, true);
      if ($pt === 'post') {
        if (!$want) $css .= "#adminmenu #menu-posts{display:none!important;}\n";
      } else {
        if (!$want) $css .= "#adminmenu #menu-posts-{$pt}{display:none!important;}\n";
      }
    }
  }
  if ($css !== '') {
    wp_register_style('langa-users-menu-cleanup', false, array(), '1.0');
    wp_enqueue_style('langa-users-menu-cleanup');
    wp_add_inline_style('langa-users-menu-cleanup', $css);
  }
});

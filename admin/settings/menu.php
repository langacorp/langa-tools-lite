<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_admin_menu() {
  add_menu_page(
    'LANGA Tools Lite',
    'LANGA Tools Lite',
    'manage_options',
    'langa-tools-client',
    'langa_tools_client_overview_page',
    'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik04LjEgMi4xQzYuNy43IDQuNS41IDIuOSAxLjVMNS44IDQuNCA0LjQgNS44IDEuNSAyLjlDLjUgNC41LjcgNi43IDIuMSA4LjFjMS4yIDEuMiAzIDEuNSA0LjUuOGw3LjUgNy41Yy42LjYgMS41LjYgMi4xIDBzLjYtMS41IDAtMi4xTDguOSA2LjZjLjctMS41LjQtMy4zLS44LTQuNXoiLz48L3N2Zz4=',
    58
  );

  // Overview (landing / welcome page)
  add_submenu_page(
    'langa-tools-client',
    'Overview',
    'Overview',
    'manage_options',
    'langa-tools-client',
    'langa_tools_client_overview_page'
  );

  // Settings
  add_submenu_page(
    'langa-tools-client',
    'Settings',
    'Settings',
    'manage_options',
    'langa-tools-client-settings',
    'langa_tools_client_settings_page'
  );

  // Back-compat alias (used by older links / tabs). Hidden from menu.
  add_submenu_page(
    null,
    'Settings',
    'Settings',
    'manage_options',
    'langa-tools-client-old-settings',
    'langa_tools_client_settings_page'
  );

  foreach (langa_tools_client_features_registry() as $k => $f) {
    $page_slug = langa_tools_client_page_slug($k);
    $menu_label = $f['menu'];

    add_submenu_page(
      'langa-tools-client',
      $f['title'],
      $menu_label,
      'manage_options',
      $page_slug,
      'langa_tools_client_module_page_dispatch'
    );
  }

  // Legacy slug aliases (hidden from menu, same callback → dispatch handles key mapping).
  foreach (langa_tools_client_legacy_slug_map() as $old_slug => $new_slug) {
    add_submenu_page(
      null,                  // hidden
      '',
      '',
      'manage_options',
      $old_slug,
      'langa_tools_client_module_page_dispatch'
    );
  }

}

function langa_tools_client_module_page_dispatch() {
  $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

  // Redirect legacy slugs to new URLs (preserves query params, fixes menu highlight).
  if (function_exists('langa_tools_client_legacy_slug_map')) {
    $legacy = langa_tools_client_legacy_slug_map();
    if (isset($legacy[$page])) {
      $args = array('page' => sanitize_key($legacy[$page]));
      // Preserve only known safe query params
      if (isset($_GET['tab'])) $args['tab'] = sanitize_key(wp_unslash($_GET['tab']));
      if (isset($_GET['module'])) $args['module'] = sanitize_key(wp_unslash($_GET['module'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing param, sanitized
      wp_safe_redirect(admin_url('admin.php?' . http_build_query($args)));
      exit;
    }
  }

  // Map page slug → registry key (e.g. 'ui-ux' → 'adminux', 'events' → 'bridge').
  $key = function_exists('langa_tools_client_registry_key_from_page')
    ? langa_tools_client_registry_key_from_page($page)
    : (strpos($page, 'langa-tools-client-') === 0 ? substr($page, strlen('langa-tools-client-')) : $page);

  langa_tools_client_module_page($key);
}

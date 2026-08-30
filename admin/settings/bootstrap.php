<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin settings bootstrap (split from legacy admin/settings.php).
 * This file loads all admin settings components and registers hooks.
 */

// Registry + Effects module are normally loaded by the main plugin file.
// Guarded includes avoid fatal redeclare issues on hosts with symlinked paths.
if (!function_exists('langa_tools_client_features_registry')) {
  require_once LANGA_TOOLS_CLIENT_PATH . 'includes/registry.php';
}
if (!function_exists('langa_tools_client_get_effects_option')) {
  require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/effects/module.php';
}

// Components (functions only)
require_once __DIR__ . '/overview.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/page.php';
require_once __DIR__ . '/replace-tools.php';
require_once __DIR__ . '/save.php';
require_once __DIR__ . '/module-page.php';
require_once __DIR__ . '/tour-guide.php';

// Hooks
add_action('admin_menu', 'langa_tools_client_admin_menu');
add_action('admin_post_langa_tools_client_save_module', 'langa_tools_client_handle_save_module');
add_action('admin_post_langa_tools_client_reset_module', 'langa_tools_client_handle_reset_module');
add_action('admin_post_langa_tools_client_save_overview_mimes', 'langa_tools_client_handle_save_overview_mimes');
add_action('admin_post_langa_tools_client_smart_setup', 'langa_tools_client_handle_smart_setup');
add_action('admin_enqueue_scripts', 'langa_tools_client_admin_assets');
add_action('admin_head', 'langa_tools_client_admin_head_css');
add_action('admin_footer', 'langa_tools_client_admin_translate_widget');

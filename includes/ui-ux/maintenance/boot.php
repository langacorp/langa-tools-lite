<?php
if (!defined('ABSPATH')) exit;

/**
 * UI/UX feature: Maintenance page + contact form.
 *
 * Enabled via UI/UX (Admin UX) settings:
 *   option: langa_tools_adminux_settings['maintenance']
 *
 * Runtime is loaded only when enabled to keep baseline light.
 */

add_action('plugins_loaded', function () {
  if (!function_exists('langa_tools_client_adminux_get_option_fast')) return;

  $s = langa_tools_client_adminux_get_option_fast('langa_tools_adminux_settings', array());
  if (!is_array($s)) $s = array();

  if (!empty($s['maintenance'])) {
    if (!function_exists('langa_tools_client_render_maintenance_page')) {
      require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/maintenance/module.php';
    }
  }
}, 1);

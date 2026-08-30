<?php
if (!defined('ABSPATH')) exit;

/**
 * UI/UX → Replace tools
 *
 * - Adds a “Replace” action in Media Library list view (upload.php)
 * - Actual replacement runs inside the AdminUX save handler (admin/settings/save.php)
 */

function langa_tools_client_replace_init() {
  static $done = false;
  if ($done) return;
  $done = true;

  if (!is_admin()) return;
  if (!function_exists('current_user_can') || !current_user_can('manage_options')) return;

  add_filter('media_row_actions', 'langa_tools_client_replace_media_row_action', 20, 2);
}

function langa_tools_client_replace_media_row_action($actions, $post) {
  if (!is_admin()) return $actions;
  if (!function_exists('current_user_can') || !current_user_can('manage_options')) return $actions;

  if (function_exists('langa_tools_client_feature_is_enabled') && !langa_tools_client_feature_is_enabled('adminux')) {
    return $actions;
  }

  if (!$post || empty($post->ID) || (isset($post->post_type) && $post->post_type !== 'attachment')) {
    return $actions;
  }

  $url = add_query_arg(array(
    'page' => 'langa-tools-client-ui-ux',
    'tab'  => 'replace',
    'attachment_id' => (int)$post->ID,
  ), admin_url('admin.php'));

  $actions['langa_replace_media'] = '<a href="' . esc_url($url) . '">' . esc_html__('Replace', 'langa-tools-lite') . '</a>';
  return $actions;
}

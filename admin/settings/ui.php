<?php
if (!defined('ABSPATH')) exit;

/**
 * Shared admin UI helpers.
 */

/**
 * Render module tabs in a consistent way.
 *
 * @param array  $tabs      key => label
 * @param string $active    active tab key
 * @param string $base_url  base url (usually admin_url('admin.php?page=...'))
 * @param string $wrap_style optional inline style for the nav wrapper
 */
function langa_tools_client_admin_render_tabs($tabs, $active, $base_url, $wrap_style = '', $param = 'tab') {
  if (!is_array($tabs) || empty($tabs)) return;
  $active = (string)$active;
  $base_url = (string)$base_url;

  echo '<div class="langa-nav-tabs"><div class="nav-tab-wrapper"' . ($wrap_style ? ' style="' . esc_attr($wrap_style) . '"' : '') . '>';
  $param = sanitize_key((string)$param);
  if ($param === '') $param = 'tab';

  foreach ($tabs as $k => $lbl) {
    $k = sanitize_key($k);
    $u = add_query_arg(array($param => $k), $base_url);
    $cls = 'nav-tab' . ($k === $active ? ' nav-tab-active' : '');
    echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($u) . '">' . esc_html($lbl) . '</a>';
  }
  echo '</div></div>';
}

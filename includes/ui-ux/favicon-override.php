<?php
if(!defined('ABSPATH')) exit;
// ===============================
// LANGA FAVICON FALLBACK
// ===============================
// Rule:
// - If the site icon IS set (Customizer / theme settings), ALWAYS respect it.
// - If the site icon is NOT set, use LANGA fallback (PNG for max compat).

$_langa_favicon_fallback = 'https://tools.langa.tv/wp-content/uploads/2026/02/langa_tools-lite.png';

add_filter('get_site_icon_url', function ($url, $size = 512, $blog_id = 0) {
  global $_langa_favicon_fallback;

  $icon_id = (int) langa_tools_client_adminux_get_site_icon_id_fast();
  if ($icon_id <= 0 || empty($url)) return $_langa_favicon_fallback;

  return $url;
}, 10, 3);

// Extra: ensure icon tags exist even on themes that don't print them (only when no site icon is set)
add_action('wp_head', function () {
  global $_langa_favicon_fallback;

  if ((int) langa_tools_client_adminux_get_site_icon_id_fast() > 0) return;

  echo '<link rel="icon" href="' . esc_url($_langa_favicon_fallback) . '" sizes="32x32" />' . "\n";
  echo '<link rel="icon" href="' . esc_url($_langa_favicon_fallback) . '" sizes="192x192" />' . "\n";
  echo '<link rel="apple-touch-icon" href="' . esc_url($_langa_favicon_fallback) . '" />' . "\n";
}, 0);

// Login: same rule
add_action('login_head', function () {
  global $_langa_favicon_fallback;

  if ((int) langa_tools_client_adminux_get_site_icon_id_fast() > 0) return;

  echo '<link rel="icon" href="' . esc_url($_langa_favicon_fallback) . '" sizes="32x32" />' . "\n";
  echo '<link rel="apple-touch-icon" href="' . esc_url($_langa_favicon_fallback) . '" />' . "\n";
}, 0);

// Admin: ensure favicon in wp-admin too
add_action('admin_head', function () {
  global $_langa_favicon_fallback;

  if ((int) langa_tools_client_adminux_get_site_icon_id_fast() > 0) return;

  echo '<link rel="icon" href="' . esc_url($_langa_favicon_fallback) . '" sizes="32x32" />' . "\n";
}, 0);

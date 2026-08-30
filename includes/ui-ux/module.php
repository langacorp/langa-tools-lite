<?php
if (!defined('ABSPATH')) exit;

// ADMIN UX module loader (split per feature).

// ─── ALWAYS LOAD: infrastructure + credits (has own ban logic) ───
require_once __DIR__ . '/helpers.php';           // Pure helper functions (no hooks)
require_once __DIR__ . '/credits/module.php';    // Has own ban/license gate (grey iframe when banned/invalid)

// ─── BAN GATE: if site is banned, NOTHING else loads on frontend ───
// Credits already loaded above with its own ban-aware logic.
// All other modules (maintenance, effects, login, etc.) are blocked.
if ((int) get_option('langa_tools_banned', 0) === 1 && !is_admin()) {
  return; // Banned frontend: only credits (grey bar if server says so)
}

require_once __DIR__ . '/effects/module.php';    // Has own feature_is_enabled gate
require_once __DIR__ . '/boot.php';              // boot() has internal license gate
require_once __DIR__ . '/front-ui-improvements.php'; // [temp] shortcode must ALWAYS register

// ─── LICENSE GATE: all other UI/UX features ───
// When license is invalid, NONE of these files load — EXCEPT in Lite where UI/UX is free.
$_is_lite = defined('LANGA_TOOLS_IS_LITE') && LANGA_TOOLS_IS_LITE;
if (!$_is_lite && function_exists('langa_tools_client_license_is_valid') && !langa_tools_client_license_is_valid()) {
  return; // Stop loading — only credits (gray iframe) + effects (own gate) remain
}

require_once __DIR__ . '/wp-admin-ux-improvements.php';
require_once __DIR__ . '/maintenance/boot.php';
require_once __DIR__ . '/user-switching.php';
require_once __DIR__ . '/users-profiles.php';
require_once __DIR__ . '/users-menu-cleanup.php';

// Adminbar branding + favicon override: default ON (matches boot.php logic)
$_uiux_s = get_option('langa_tools_adminux_settings', array());
$_wpui_on = (is_array($_uiux_s) && isset($_uiux_s['wpui_improvements'])) ? !empty($_uiux_s['wpui_improvements']) : true;
if ($_wpui_on) {
  require_once __DIR__ . '/favicon-override.php';
  require_once __DIR__ . '/adminbar-branding.php';
  require_once __DIR__ . '/adminbar-unified.php';
}

require_once __DIR__ . '/ghost-pages.php';
require_once __DIR__ . '/replace.php';
require_once __DIR__ . '/visual-sitemap.php';

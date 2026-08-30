<?php
if (!defined('ABSPATH')) exit;

if (!defined('LANGA_TOOLS_OPTION_SERVER_URL'))      define('LANGA_TOOLS_OPTION_SERVER_URL', 'langa_tools_server_url');
if (!defined('LANGA_TOOLS_OPTION_SITE_KEY'))         define('LANGA_TOOLS_OPTION_SITE_KEY', 'langa_tools_site_key');
if (!defined('LANGA_TOOLS_OPTION_SECRET'))            define('LANGA_TOOLS_OPTION_SECRET', 'langa_tools_secret');
if (!defined('LANGA_TOOLS_OPTION_ENABLED_MODULES'))  define('LANGA_TOOLS_OPTION_ENABLED_MODULES', 'langa_tools_enabled_modules');

if (!defined('LANGA_TOOLS_OPTION_BRIDGE_ENABLED'))   define('LANGA_TOOLS_OPTION_BRIDGE_ENABLED', 'langa_tools_bridge_enabled');

// ── Endpoints ──
// LANGA_TOOLS_SERVER_URL  → tools.langa.tv  = Product Server (licenses, events, download) — WP REST
// LANGA_SYNC_ENDPOINT     → api.langa.tv    = LANGA Core / AEGIS (Sync protocol: heartbeat, registration, telemetry)
// LANGA_SYNC_FALLBACK     → tools.langa.tv  = Sync fallback via WP REST when api.langa.tv is unreachable
if (!defined('LANGA_TOOLS_FIXED_SERVER_URL'))    define('LANGA_TOOLS_FIXED_SERVER_URL',    'https://tools.langa.tv');
if (!defined('LANGA_SYNC_ENDPOINT'))             define('LANGA_SYNC_ENDPOINT',             'https://api.langa.tv/langa/v1');
if (!defined('LANGA_SYNC_FALLBACK'))             define('LANGA_SYNC_FALLBACK',             'https://tools.langa.tv/wp-json/langa/v1');
if (!defined('LANGA_SYNC_FALLBACK_ENABLED'))     define('LANGA_SYNC_FALLBACK_ENABLED',     true);

if (!defined('LANGA_TOOLS_OPTION_EFFECTS'))           define('LANGA_TOOLS_OPTION_EFFECTS', 'langa_tools_effects');
if (!defined('LANGA_TOOLS_OPTION_FEATURES'))          define('LANGA_TOOLS_OPTION_FEATURES', 'langa_tools_features_enabled');

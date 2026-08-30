<?php
if (!defined('ABSPATH')) exit;

/**
 * Public asset proxy (no rewrite flush):
 *  - serves only files under this plugin's /assets directory
 *  - URL: /langa-assets/<relative path>
 */
function langa_tools_client_assets_proxy_maybe_serve() {
  if (is_admin()) return;

  $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : "";
  if ($uri === "") return;

  $path = strtok($uri, "?");
  if (strpos($path, "/langa-assets/") !== 0) return;

  $rel = ltrim(substr($path, strlen("/langa-assets/")), "/");
  if ($rel === "" || strpos($rel, "..") !== false) {
    status_header(400);
    exit;
  }

  // Credits JS must ALWAYS be served (grey credits works without license)
  $is_credits_js = ($rel === 'langa-credits.js');

  // License gate: asset proxy is part of Safer PRO (Ghost Mode)
  // Emergency bypass: allow assets if Safer is bypassed (prevents broken pages during recovery)
  if ($is_credits_js) {
    // Always allowed — grey credits needs this JS regardless of license
  } elseif (defined('LANGA_TOOLS_IS_LITE') && LANGA_TOOLS_IS_LITE) {
    // Lite: all assets always served (UI/UX is free)
  } elseif (defined('LANGA_SAFER_BYPASS') && LANGA_SAFER_BYPASS) {
    // Bypass: serve the asset normally
  } elseif (function_exists('langa_tools_client_license_ok_cached') && !langa_tools_client_license_ok_cached(259200)) {
    status_header(403);
    exit;
  }

  $base = defined('LANGA_TOOLS_CLIENT_PATH') ? LANGA_TOOLS_CLIENT_PATH : plugin_dir_path(__FILE__) . "../";
  $file = $base . "assets/" . $rel;
  $real = realpath($file);
  $assets_real = realpath($base . "assets");
  if (!$real || !$assets_real || strpos($real, $assets_real) !== 0 || !is_file($real)) {
    status_header(404);
    exit;
  }

  $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
  $types = array(
    "css"=>"text/css; charset=utf-8",
    "js"=>"application/javascript; charset=utf-8",
    "png"=>"image/png",
    "jpg"=>"image/jpeg",
    "jpeg"=>"image/jpeg",
    "gif"=>"image/gif",
    "svg"=>"image/svg+xml",
    "webp"=>"image/webp",
    "woff"=>"font/woff",
    "woff2"=>"font/woff2",
    "ttf"=>"font/ttf"
  );
  $ctype = isset($types[$ext]) ? $types[$ext] : "application/octet-stream";

  header("Content-Type: " . $ctype);
  header("X-Content-Type-Options: nosniff");
  // JS files: shorter cache (plugin updates change behavior)
  // Static assets (images/fonts/css): long cache
  if ($ext === 'js') {
    header("Cache-Control: public, max-age=3600");
  } else {
    header("Cache-Control: public, max-age=31536000, immutable");
  }

  readfile($real);
  exit;
}
add_action('init', "langa_tools_client_assets_proxy_maybe_serve", 0);

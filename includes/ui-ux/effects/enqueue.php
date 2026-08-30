<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_enqueue_effect_assets($effect) {
  // Effects assets live under /assets/effects/<effect>.css|.js
  $base_path = (defined('LANGA_TOOLS_CLIENT_PATH') ? LANGA_TOOLS_CLIENT_PATH : plugin_dir_path(__FILE__) . '../') . 'assets/effects/';
  // Use internal proxy endpoint to avoid exposing wp-content and to prevent
  // Safer path rewriting from breaking assets for guests.
  $base_url  = home_url('/langa-assets/effects/');

  $css_path = $base_path . $effect . '.css';
  $js_path  = $base_path . $effect . '.js';

  if (file_exists($css_path)) {
    $ver = (defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '1.0') . '.' . (string)(file_exists($css_path) ? filemtime($css_path) : 0);
    wp_enqueue_style('langa-tools-effect-'.$effect, $base_url . $effect . '.css', array(), $ver);
  }

  if (file_exists($js_path)) {
    $ver = (defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '1.0') . '.' . (string)(file_exists($js_path) ? filemtime($js_path) : 0);
    wp_enqueue_script('langa-tools-effect-'.$effect, $base_url . $effect . '.js', array(), $ver, true);
  }
}

<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
  // Must be enabled via UI/UX module
  if (function_exists('langa_tools_client_feature_is_enabled') && !langa_tools_client_feature_is_enabled('adminux')) return;

  $opt = langa_tools_client_get_effects_option();
  if (empty($opt['enabled'])) return;
  $opt = langa_tools_client_get_effects_option();
  if (empty($opt['enabled'])) return;

  $rows = isset($opt['rows']) ? $opt['rows'] : array();
  if (is_array($rows) && !empty($rows)) {
    foreach ($rows as $row) {
      $effect = isset($row['effect']) ? sanitize_key($row['effect']) : '';
      if ($effect === '') continue;

      if (!langa_tools_client_should_apply_effect($row)) continue;

      langa_tools_client_enqueue_effect_assets($effect);
    }
  }


});

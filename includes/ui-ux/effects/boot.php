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

  // Custom inline effect (CSS/JS) — optional
  $custom = isset($opt['custom']) && is_array($opt['custom']) ? $opt['custom'] : array();
  $c_start = isset($custom['start_md']) ? (string)$custom['start_md'] : '';
  $c_end   = isset($custom['end_md']) ? (string)$custom['end_md'] : '';
  $c_css   = isset($custom['css']) ? (string)$custom['css'] : '';
  $c_js    = isset($custom['js']) ? (string)$custom['js'] : '';

  if ($c_css === '' && $c_js === '') return;

  $row = array('start_md' => $c_start, 'end_md' => $c_end, 'before' => 0, 'after' => 0);
  if (!langa_tools_client_should_apply_effect($row)) return;

  if ($c_css !== '') {
    $safe_css = str_replace(array('</style>', '</STYLE>'), '', $c_css);
    add_action('wp_enqueue_scripts', function () use ($safe_css) {
      wp_register_style('langa-tools-custom-effect', false, array(), '1.0');
      wp_enqueue_style('langa-tools-custom-effect');
      wp_add_inline_style('langa-tools-custom-effect', $safe_css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS sanitized: tags stripped
    }, 999);
  }

  if ($c_js !== '') {
    $safe_js = str_replace(array('</script>', '</SCRIPT>'), '', $c_js);
    add_action('wp_enqueue_scripts', function () use ($safe_js) {
      wp_register_script('langa-tools-custom-effect', false, array(), '1.0', true);
      wp_enqueue_script('langa-tools-custom-effect');
      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS sanitized, output through wp_add_inline_script
      wp_add_inline_script('langa-tools-custom-effect', '(function(){' . "\n" . $safe_js . "\n" . '})();'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS sanitized: tags stripped
    }, 999);
  }
});

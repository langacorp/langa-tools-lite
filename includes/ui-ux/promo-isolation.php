<?php
if (!defined('ABSPATH')) exit;

/**
 * Promo Banner Isolation — Runtime
 *
 * Hides admin banners matching configured rules (CSS inject)
 * and captures their HTML via JS+AJAX for the isolated view.
 */

function langa_tools_client_promo_isolation_init() {
  if (!is_admin()) return;
  if (!current_user_can('manage_options')) return;
  if (!function_exists('langa_tools_client_feature_is_enabled') || !langa_tools_client_feature_is_enabled('adminux')) return;

  $s = get_option('langa_tools_adminux_settings', array());
  $rules = isset($s['promo_isolation_rules']) && is_array($s['promo_isolation_rules']) ? $s['promo_isolation_rules'] : array();

  $active_rules = array();
  foreach ($rules as $r) {
    if (!empty($r['active']) && !empty($r['selector'])) {
      $active_rules[] = $r;
    }
  }
  if (empty($active_rules)) return;

  // Skip on our own isolation view page
  $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
  $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
  if ($page === 'langa-tools-client-ui-ux' && $view === 'isolated-banners') return;

  add_action('admin_enqueue_scripts', function() use ($active_rules) {
    $css = '';
    foreach ($active_rules as $r) {
      $sel = '';
      $type = $r['type'] ?? 'class';
      $value = $r['selector'] ?? '';
      if ($value === '') continue;

      if ($type === 'class') {
        $sel = '.' . esc_attr(ltrim($value, '.'));
      } elseif ($type === 'id') {
        $sel = '#' . esc_attr(ltrim($value, '#'));
      } elseif ($type === 'data-nonce') {
        $sel = '[data-nonce="' . esc_attr($value) . '"]';
      }
      if ($sel !== '') {
        $css .= $sel . '{display:none !important}' . "\n";
      }
    }
    if ($css !== '') {
      wp_register_style('langa-promo-isolation', false, array(), '1.0');
      wp_enqueue_style('langa-promo-isolation');
      wp_add_inline_style('langa-promo-isolation', $css);
    }
  }, 99999);

  add_action('admin_enqueue_scripts', function() use ($active_rules) {
    $rules_json = wp_json_encode($active_rules);
    $nonce = wp_create_nonce('langa_promo_capture');
    wp_register_script('langa-promo-capture', false, array(), '1.0', true);
    wp_enqueue_script('langa-promo-capture');
    wp_add_inline_script('langa-promo-capture', '(function(){
      var rules = ' . $rules_json . ';
      var captured = [];
      rules.forEach(function(r) {
        if (!r.active || !r.selector) return;
        var sel = "";
        if (r.type === "class") sel = "." + r.selector.replace(/^\./, "");
        else if (r.type === "id") sel = "#" + r.selector.replace(/^#/, "");
        else if (r.type === "data-nonce") sel = "[data-nonce=\"" + r.selector + "\"]";
        if (!sel) return;
        var els = document.querySelectorAll(sel);
        if (els.length > 0) {
          var html = els[0].outerHTML || "";
          if (html.length > 3000) html = html.substring(0, 3000) + "...";
          captured.push({ type: r.type, selector: r.selector, label: r.label || "", html_preview: html });
        }
      });
      if (captured.length === 0) return;
      var fd = new FormData();
      fd.append("action", "langa_promo_capture");
      fd.append("_wpnonce", "' . esc_js($nonce) . '");
      fd.append("banners", JSON.stringify(captured));
      fetch(ajaxurl, { method: "POST", body: fd, credentials: "same-origin" });
    })();');
  }, 99999);
}

function langa_tools_client_promo_capture_ajax() {
  check_ajax_referer('langa_promo_capture');
  if (!current_user_can('manage_options')) wp_die('Not allowed');

  $raw = isset($_POST['banners']) ? sanitize_text_field(wp_unslash($_POST['banners'])) : '';
  $banners = json_decode(wp_unslash($raw), true);
  if (!is_array($banners) || empty($banners)) {
    wp_send_json_success(array('stored' => 0));
    return;
  }

  $stored = get_option('langa_tools_promo_captured_banners', array());
  if (!is_array($stored)) $stored = array();

  $now = current_time('Y-m-d H:i');

  foreach ($banners as $b) {
    if (!is_array($b) || empty($b['selector'])) continue;
    $key = sanitize_key(($b['type'] ?? '') . '_' . ($b['selector'] ?? ''));
    if ($key === '_') continue;

    $found = false;
    foreach ($stored as &$existing) {
      $ek = sanitize_key(($existing['type'] ?? '') . '_' . ($existing['selector'] ?? ''));
      if ($ek === $key) {
        $existing['page_count'] = ((int)($existing['page_count'] ?? 1)) + 1;
        $found = true;
        break;
      }
    }
    unset($existing);

    if (!$found) {
      $stored[] = array(
        'type'         => sanitize_key($b['type'] ?? 'class'),
        'selector'     => sanitize_text_field($b['selector'] ?? ''),
        'label'        => sanitize_text_field($b['label'] ?? ''),
        'html_preview' => wp_kses_post(mb_substr($b['html_preview'] ?? '', 0, 3000)),
        'page_count'   => 1,
        'first_seen'   => $now,
      );
    }
  }

  if (count($stored) > 50) $stored = array_slice($stored, -50);
  update_option('langa_tools_promo_captured_banners', $stored, false);
  wp_send_json_success(array('stored' => count($stored)));
}

add_action('admin_init', 'langa_tools_client_promo_isolation_init');
add_action('wp_ajax_langa_promo_capture', 'langa_tools_client_promo_capture_ajax');

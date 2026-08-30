<?php
if (!defined('ABSPATH')) exit;

add_action('wp_dashboard_setup', function () {
  if (!current_user_can('manage_options')) return;
  wp_add_dashboard_widget('langa_tools_dashboard', 'LANGA Tools', 'langa_tools_dashboard_render');
  global $wp_meta_boxes;
  if (isset($wp_meta_boxes['dashboard']['normal']['core']['langa_tools_dashboard'])) {
    $w = $wp_meta_boxes['dashboard']['normal']['core']['langa_tools_dashboard'];
    unset($wp_meta_boxes['dashboard']['normal']['core']['langa_tools_dashboard']);
    $wp_meta_boxes['dashboard']['normal']['high']['langa_tools_dashboard'] = $w;
  }
});

function langa_tools_dashboard_render() {
  $is_lite = defined('LANGA_TOOLS_IS_LITE') && LANGA_TOOLS_IS_LITE;
  $ver = defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '?';

  // 10 modules: 1 free (UI/UX always ON) + 9 PRO-managed by server
  $modules = array(
    array('adminux', 'UI/UX',          false),
    array('safer',   'Safer',           true),
    array('seo',     'SEO',             true),
    array('cache',   'Cache',           true),
    array('legal',   'Legal',           true),
    array('forms',   'Forms',           true),
    array('bc',      'Business Card',   true),
    array('popup',   'Popup',           true),
    array('bridge',  'Events',          true),
    array('ai',      'AI',              true),
  );

  $feat_fn = function_exists('langa_tools_client_feature_is_enabled');
  $active = 0;
  $total = count($modules); // always 10
  $rows = array();

  foreach ($modules as $m) {
    $key = $m[0]; $name = $m[1]; $pro = $m[2];

    // UI/UX always ON (free module)
    if ($key === 'adminux') {
      $on = true;
    } elseif ($is_lite && $pro) {
      $on = false;
    } else {
      $on = $feat_fn ? (bool) langa_tools_client_feature_is_enabled($key) : false;
    }

    if ($on) $active++;
    $slug = function_exists('langa_tools_client_page_slug') ? langa_tools_client_page_slug($key) : 'langa-tools-client';
    $rows[] = array('key' => $key, 'name' => $name, 'pro' => $pro, 'on' => $on, 'url' => admin_url('admin.php?page=' . $slug));
  }

  // ── CSS ──
  langtoli_inline_style(
    '#langa_tools_dashboard .inside{padding:0!important;margin:0!important}' .
    '.ltw{font-family:system-ui,-apple-system,sans-serif;color:#1d1d1f;font-size:13px}' .
    '.ltw-hd{background:#1d1d1f;color:#fff;padding:10px 14px;display:flex;justify-content:space-between;align-items:center}' .
    '.ltw-hd strong{font-size:13px}.ltw-hd .v{font-size:10px;color:#86868b;margin-left:4px}' .
    '.ltw-tag{font-size:9px;font-weight:800;padding:2px 8px;border-radius:8px;letter-spacing:.04em}' .
    '.ltw-inf{display:flex;border-bottom:1px solid #f0f0f0}' .
    '.ltw-ic{flex:1;padding:7px 6px;text-align:center;border-right:1px solid #f0f0f0;font-size:11px}' .
    '.ltw-ic:last-child{border-right:0}' .
    '.ltw-ic .k{font-size:8px;font-weight:700;text-transform:uppercase;color:#86868b;letter-spacing:.03em}' .
    '.ltw-ic .vl{font-weight:600;margin-top:1px}' .
    '.ltw-g{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #f0f0f0}' .
    '.ltw-m{padding:7px 10px;display:flex;align-items:center;gap:7px;border-bottom:1px solid #f0f0f0;border-right:1px solid #f0f0f0;text-decoration:none;color:inherit;font-size:12px;transition:background .12s}' .
    '.ltw-m:nth-child(2n){border-right:0}' .
    '.ltw-m:hover{background:#fafafa}' .
    '.ltw-m .d{width:7px;height:7px;border-radius:50%;flex-shrink:0}' .
    '.ltw-m .n{flex:1;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' .
    '.ltw-m .s{font-size:9px;font-weight:700}' .
    '.ltw-m .pt{font-size:7px;font-weight:800;color:#fff;background:#f37f0d;padding:1px 5px;border-radius:3px}' .
    '.ltw-ct{padding:10px 14px;text-align:center;background:#fffbeb;border-bottom:1px solid #fde68a}' .
    '.ltw-ct p{margin:0 0 5px;font-size:11px;color:#92400e}' .
    '.ltw-ct a{display:inline-block;padding:5px 14px;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none;background:#1d1d1f;color:#fff}' .
    '.ltw-ft{padding:6px 14px;display:flex;justify-content:space-between;font-size:11px}' .
    '.ltw-ft a{text-decoration:none;font-weight:600}'
  );

  echo '<div class="ltw">';

  // ── Header ──
  echo '<div class="ltw-hd">';
  echo '<div><strong>LANGA Tools</strong> <span class="v">v' . esc_html($ver) . '</span></div>';
  if ($is_lite) {
    echo '<span class="ltw-tag" style="background:#f37f0d;color:#fff">LITE</span>';
  } else {
    echo '<span class="ltw-tag" style="background:#22c55e;color:#fff">PRO</span>';
  }
  echo '</div>';

  // ── Info row ──
  echo '<div class="ltw-inf">';
  echo '<div class="ltw-ic"><div class="k">Modules</div><div class="vl">' . $active . '/' . $total . '</div></div>';
  echo '<div class="ltw-ic"><div class="k">PHP</div><div class="vl">' . esc_html(PHP_VERSION) . '</div></div>';
  echo '<div class="ltw-ic"><div class="k">WP</div><div class="vl">' . esc_html(get_bloginfo('version')) . '</div></div>';
  echo '</div>';

  // ── Health Score bar (uses real module scores) ──
  $health_url = admin_url('admin.php?page=langa-tools-client-settings&tab=test');
  $h_score = 0;
  $h_label = 'Unknown';
  $h_color = '#86868b';

  if (function_exists('langa_tools_client_module_score') && function_exists('langa_tools_client_feature_is_config_enabled')) {
    $mods_to_test = array('cache','safer','legal','forms','bc','seo','popup','bridge');
    $h_total = 0; $h_tested = 0;
    foreach ($mods_to_test as $hm) {
      if (!langa_tools_client_feature_is_config_enabled($hm)) continue;
      $hd = langa_tools_client_module_score($hm);
      if (!$hd) continue;
      $h_total += $hd['score'];
      $h_tested++;
    }
    $h_score = $h_tested > 0 ? (int) round($h_total / $h_tested) : 0;
  }

  if ($h_score >= 80) { $h_color = '#16a34a'; $h_label = 'Good'; }
  elseif ($h_score >= 50) { $h_color = '#f37f0d'; $h_label = 'Fair'; }
  else { $h_color = '#dc2626'; $h_label = 'Needs attention'; }

  echo '<a href="' . esc_url($health_url) . '" class="ltw-health" style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:inherit;transition:background .12s">';
  echo '<div style="flex:1;display:flex;align-items:center;gap:8px">';
  echo '<div style="position:relative;width:32px;height:32px">';
  $h_r = 14; $h_circ = 2 * 3.14159 * $h_r; $h_dash = $h_circ * ($h_score / 100);
  echo '<svg width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="' . esc_attr($h_r) . '" fill="none" stroke="#e5e5e7" stroke-width="3"/>';
  echo '<circle cx="16" cy="16" r="' . esc_attr($h_r) . '" fill="none" stroke="' . esc_attr($h_color) . '" stroke-width="3" stroke-dasharray="' . esc_attr(round($h_dash, 1)) . ' ' . esc_attr(round($h_circ, 1)) . '" stroke-linecap="round" transform="rotate(-90 16 16)"/></svg>';
  echo '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;color:' . esc_attr($h_color) . '">' . $h_score . '</div>';
  echo '</div>';
  echo '<div><div style="font-size:12px;font-weight:700;color:#1d1d1f">Site Health</div>';
  echo '<div style="font-size:10px;color:' . esc_attr($h_color) . ';font-weight:600">' . esc_html($h_label) . '</div></div>';
  echo '</div>';
  echo '<div style="width:80px;height:6px;background:#e5e5e7;border-radius:3px;overflow:hidden;flex-shrink:0">';
  echo '<div style="width:' . $h_score . '%;height:100%;background:' . esc_attr($h_color) . ';border-radius:3px;transition:width .3s"></div>';
  echo '</div>';
  echo '<span style="font-size:10px;color:#86868b;flex-shrink:0">Details →</span>';
  echo '</a>';

  // ── Module grid (2 columns) ──
  echo '<div class="ltw-g">';
  foreach ($rows as $md) {
    $locked = $is_lite && $md['pro'];

    if ($locked) {
      echo '<div class="ltw-m" style="opacity:.5;cursor:default">';
      echo '<span class="d" style="background:#d1d5db"></span>';
      echo '<span class="n">' . esc_html($md['name']) . '</span>';
      echo '<span class="pt">PRO</span>';
      echo '</div>';
    } else {
      $dc = $md['on'] ? '#22c55e' : '#d1d5db';
      $sl = $md['on'] ? 'ON' : 'OFF';
      $sc = $md['on'] ? 'color:#22c55e' : 'color:#d1d5db';
      echo '<a href="' . esc_url($md['url']) . '" class="ltw-m">';
      echo '<span class="d" style="background:' . esc_attr($dc) . '"></span>';
      echo '<span class="n">' . esc_html($md['name']) . '</span>';
      echo '<span class="s" style="' . esc_attr($sc) . '">' . esc_html($sl) . '</span>';
      echo '</a>';
    }
  }
  echo '</div>';

  // ── CTA (Lite) ──
  if ($is_lite) {
    echo '<div class="ltw-ct">';
    // Removed for Lite
    // echo '<p>Unlock all modules &mdash; <strong>&euro;19.90/mo</strong> or <strong>&euro;199/yr</strong></p>';
    // PRO CTA removed for WP.org Lite
    echo '</div>';
  }

  // ── Footer ──
  echo '<div class="ltw-ft">';
  echo '<a href="' . esc_url(admin_url('admin.php?page=langa-tools-client-settings')) . '" style="color:#f37f0d">Settings</a>';
  echo '<a href="' . esc_url(admin_url('admin.php?page=langa-tools-client')) . '" style="color:#86868b">Overview</a>';
  echo '</div>';

  echo '</div>';
}

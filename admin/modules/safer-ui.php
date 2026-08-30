<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_render_module_safer($module, $enabled, $locked, $f) {
    $s = get_option('langa_tools_safer_settings', array());
    if (!is_array($s)) $s = array();

    // Clear stale notice
    $notice_ghost = (int) get_option('langa_tools_safer_notice_ghost_needs_htaccess', 0);
    if ($notice_ghost) {
      delete_option('langa_tools_safer_notice_ghost_needs_htaccess');
    }

    $tab = isset($_GET['tab']) ? sanitize_key((string)$_GET['tab']) : 'overview';
    if ($tab === 'access') $tab = 'ghost'; // backward compat

    $tabs = array(
      'overview'  => 'Protection Level',
      'hardening' => 'What gets protected',
      'ghost'     => 'Hide WordPress',
      'tools'     => 'Emergency',
    );
    if (!isset($tabs[$tab])) $tab = 'overview';

    $v = function($key, $default = 0) use ($s) {
      return !empty($s[$key]) ? 1 : (int)$default;
    };

    $slugs = function_exists('langa_tools_client_safer_get_rewrite_slugs') ? langa_tools_client_safer_get_rewrite_slugs() : array();
    $login_slug   = isset($slugs['login'])      ? (string)$slugs['login']      : 'langa-door';
    $admin_slug   = isset($slugs['admin'])       ? (string)$slugs['admin']      : 'langa-intern';
    $upload_slug  = isset($slugs['upload'])      ? (string)$slugs['upload']     : 'media';
    $inc_slug     = isset($slugs['inc'])         ? (string)$slugs['inc']        : 'lib';
    $theme_base   = isset($slugs['theme_base'])  ? (string)$slugs['theme_base'] : 't';
    $theme_fake   = isset($slugs['theme_fake'])  ? (string)$slugs['theme_fake'] : '';
    $plugins_map  = (isset($slugs['plugins']) && is_array($slugs['plugins'])) ? $slugs['plugins'] : array();
    $theme_real   = (string) get_option('template');
    $detected_ip  = function_exists('langa_tools_client_safer_get_client_ip') ? langa_tools_client_safer_get_client_ip() : '';
    $allowlist    = isset($s['allowlist_ips']) ? (string)$s['allowlist_ips'] : '';
    $current_pack = isset($s['pack']) ? (string)$s['pack'] : '';
    $ht_on        = $v('htaccess_hardening');

    echo '<tr><th scope="row">Safer</th><td>';
    echo '<input type="hidden" name="current_tab" value="'.esc_attr($tab).'" />';
    $base_url = admin_url('admin.php?page=langa-tools-client-safer');

    langa_tools_client_admin_render_tabs($tabs, $tab, $base_url);
    echo '<div class="langa-card langa-tab-panel">';


    // ═══════════════════════════════════════════
    //  TAB 1: PROTECTION LEVEL
    // ═══════════════════════════════════════════
    if ($tab === 'overview') {

      echo '<h3 style="margin:0 0 4px">Choose how much protection you want</h3>';
      echo '<p class="description" style="margin:0 0 16px">Pick a level, click Apply, and you\'re done. Each level includes everything from the level before it.</p>';

      langa_tools_client_render_inline_pack(array(
        'basic' => array(
          'name'     => 'Basic',
          'icon'     => 'dashicons-lock',
          'color'    => '#0071e3',
          'desc'     => 'Hides the fact that your site runs WordPress. Safe for any site — nothing can break.',
          'features' => array('Remove WordPress version number','Remove WordPress fingerprints','Block remote access (XML-RPC)','Stop username guessing'),
          'ideal'    => 'Blogs, portfolios, small sites',
        ),
        'business' => array(
          'name'     => 'Business',
          'icon'     => 'dashicons-shield',
          'color'    => '#7c3aed',
          'desc'     => 'Everything in Basic plus locks down the admin area. Recommended for most business sites.',
          'features' => array('Everything in Basic','Lock the built-in code editor','Force secure (HTTPS) admin pages'),
          'ideal'    => 'Business sites, agencies, e-commerce',
        ),
        'fortress' => array(
          'name'     => 'Fortress',
          'icon'     => 'dashicons-shield-alt',
          'color'    => '#dc2626',
          'desc'     => 'Maximum protection. Hides the login page and disguises all file paths. Your site won\'t look like WordPress at all.',
          'features' => array('Everything in Business','Secret login link (Door Lock)','Disguise all file paths (Ghost Mode)'),
          'ideal'    => 'High-security, finance, government',
        ),
      ), $current_pack, 'safer[apply_pack]', 'safer[apply_pack_btn]', 'Protection Level',
         'Apply this level? Your settings will be updated automatically.');

      langa_tools_client_render_module_gauge('safer', $base_url);

      // Active protections summary
      $active = array();
      if ($v('hide_wp_version'))      $active[] = 'Version hidden';
      if ($v('hide_wp_fingerprints')) $active[] = 'Fingerprints removed';
      if ($v('disable_xmlrpc'))       $active[] = 'XML-RPC blocked';
      if ($v('block_author_enum'))    $active[] = 'Username guessing blocked';
      if ($v('disable_file_editor'))  $active[] = 'Code editor locked';
      if ($v('force_https_admin'))    $active[] = 'HTTPS forced';
      if ($v('disable_rest_guests'))  $active[] = 'REST API restricted';
      if ($v('door_only_access'))     $active[] = 'Login hidden';
      if ($v('protezione_2_0'))       $active[] = 'File paths disguised';
      if ($v('htaccess_hardening'))   $active[] = 'Ghost Mode active';

      if (!empty($active)) {
        echo '<div style="padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin:10px 0 0">';
        echo '<p style="margin:0 0 4px;font-weight:700;font-size:13px;color:#166534">&#10003; Currently protecting your site</p>';
        echo '<p style="margin:0;font-size:13px;color:#166534">' . esc_html(implode(' &middot; ', $active)) . '</p>';
        echo '</div>';
      } else {
        echo '<div style="padding:14px 16px;background:#fef3e2;border:1px solid #fcd9b1;border-radius:10px;margin:10px 0 0">';
        echo '<p style="margin:0;font-size:13px;color:#7c3d06">No protections active yet. Choose a level above and click Apply to get started.</p>';
        echo '</div>';
      }

      echo '<div style="padding:12px 16px;background:#f8f8fa;border:1px solid #e5e5e7;border-radius:10px;margin:10px 0 0">';
      echo '<p class="description" style="margin:0;line-height:1.6"><strong>Good to know:</strong> Administrators are never locked out by any of these protections. If something goes wrong, go to the <a href="' . esc_url($base_url . '&tab=tools') . '">Emergency</a> tab to reset everything with one click.</p>';
      echo '</div>';
    }


    // ═══════════════════════════════════════════
    //  TAB 2: WHAT GETS PROTECTED (Hardening)
    // ═══════════════════════════════════════════
    if ($tab === 'hardening') {
      echo '<h3 style="margin:0 0 4px">What gets protected</h3>';
      echo '<p class="description" style="margin:0 0 16px">Turn individual protections on or off. The protection level presets set these automatically, but you can fine-tune them here.</p>';

      $items = array(
        array(
          'key'   => 'hide_wp_version',
          'label' => 'Hide WordPress version',
          'desc'  => 'Removes the version number from your site\'s source code. Attackers use this to target known vulnerabilities in specific WordPress versions.',
          'safe'  => true,
        ),
        array(
          'key'   => 'hide_wp_fingerprints',
          'label' => 'Hide WordPress fingerprints',
          'desc'  => 'Removes extra clues in your pages that reveal the site runs WordPress — RSS links, shortlink tags, emoji scripts, and similar markers.',
          'safe'  => true,
        ),
        array(
          'key'   => 'disable_xmlrpc',
          'label' => 'Block remote access (XML-RPC)',
          'desc'  => 'Disables an old remote access feature that hackers love to exploit. Safe for almost all sites. Only keep it on if you use the WordPress mobile app.',
          'safe'  => true,
        ),
        array(
          'key'   => 'block_author_enum',
          'label' => 'Stop username guessing',
          'desc'  => 'Prevents bots from discovering your admin usernames through special URLs. They get sent to the homepage instead.',
          'safe'  => true,
        ),
        array(
          'key'   => 'disable_file_editor',
          'label' => 'Lock the built-in code editor',
          'desc'  => 'Disables the theme and plugin code editor inside WordPress admin. If someone gets into your dashboard, they still can\'t edit your site\'s code.',
          'safe'  => true,
        ),
        array(
          'key'   => 'force_https_admin',
          'label' => 'Force HTTPS on admin pages',
          'desc'  => 'Makes sure the admin area and login page always use a secure (encrypted) connection. Requires an SSL certificate — most sites already have one.',
          'safe'  => true,
        ),
        array(
          'key'   => 'disable_rest_guests',
          'label' => 'Restrict the REST API',
          'desc'  => 'Only allows logged-in users to use WordPress\'s data interface (REST API).',
          'safe'  => false,
          'warn'  => 'May break Contact Form 7, WooCommerce, or other plugins that need to work for visitors who aren\'t logged in. Only enable this if you\'re sure.',
        ),
      );

      foreach ($items as $it) {
        $bg = $it['safe'] ? '#fafafa' : '#fffbeb';
        $br = $it['safe'] ? '#e5e5e7' : '#fcd9b1';
        echo '<div style="padding:12px 16px;background:'.$bg.';border:1px solid '.$br.';border-radius:10px;margin:0 0 8px">';
        echo '<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">';
        echo '<input type="checkbox" name="safer['.esc_attr($it['key']).']" value="1" '.checked($v($it['key']),1,false).' style="margin-top:3px;flex-shrink:0" />';
        echo '<div>';
        echo '<strong style="font-size:13px">'.esc_html($it['label']).'</strong>';
        echo '<p class="description" style="margin:3px 0 0">'.esc_html($it['desc']).'</p>';
        if (!empty($it['warn'])) {
          echo '<p style="margin:6px 0 0;font-size:12px;color:#c2410c;line-height:1.4"><strong>&#9888; Warning:</strong> '.esc_html($it['warn']).'</p>';
        }
        echo '</div>';
        echo '</label>';
        echo '</div>';
      }
    }


    // ═══════════════════════════════════════════
    //  TAB 3: HIDE WORDPRESS
    // ═══════════════════════════════════════════
    if ($tab === 'ghost') {
      echo '<h3 style="margin:0 0 4px">Hide WordPress</h3>';
      echo '<p class="description" style="margin:0 0 16px">These features make it nearly impossible for visitors, bots, and attackers to tell your site runs on WordPress.</p>';

      // ── DOOR LOCK ──────────────────────
      echo '<div style="padding:16px 18px;background:#fafafa;border:1px solid #e5e5e7;border-radius:12px;margin:0 0 14px">';

      echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 8px">';
      echo '<span style="font-size:20px">&#128274;</span>';
      echo '<h4 style="margin:0;font-size:14px">Door Lock — hide the login page</h4>';
      echo '</div>';

      echo '<p class="description" style="margin:0 0 10px">Right now, anyone can find your login page at <code>' . esc_html(home_url('/wp-login.php')) . '</code>. Door Lock hides it behind a secret link that only you know. Anyone who tries the normal login URL just gets sent to the homepage.</p>';

      echo '<label style="display:flex;align-items:center;gap:8px;margin:0 0 12px;cursor:pointer">';
      echo '<input type="checkbox" name="safer[door_only_access]" value="1" '.checked($v('door_only_access'),1,false).' />';
      echo '<strong>Enable Door Lock</strong>';
      echo '</label>';

      echo '<div style="padding:12px 14px;background:#fff;border:1px solid #e5e5e7;border-radius:8px;max-width:500px">';
      echo '<label style="display:block;font-weight:600;font-size:13px;margin:0 0 6px">Your secret login link</label>';
      echo '<div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap">';
      echo '<span style="color:#86868b;font-size:13px">' . esc_html(home_url('/')) . '</span>';
      echo '<input type="text" name="safer_login_slug" value="' . esc_attr(trim($login_slug, '/')) . '" style="width:140px;font-family:monospace;font-size:13px" pattern="[a-z0-9][a-z0-9\\-]*" placeholder="langa-door" />';
      echo '</div>';
      echo '<p class="description" style="margin:6px 0 0"><strong>Bookmark this link!</strong> It\'s the only way to reach your login when Door Lock is on.</p>';
      echo '</div>';

      echo '</div>'; // end door lock card

      // ── GHOST MODE ─────────────────────
      echo '<div style="padding:16px 18px;background:#fafafa;border:1px solid #e5e5e7;border-radius:12px;margin:0 0 14px">';

      echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 8px">';
      echo '<span style="font-size:20px">&#128123;</span>';
      echo '<h4 style="margin:0;font-size:14px">Ghost Mode — disguise file paths</h4>';
      echo '</div>';

      echo '<p class="description" style="margin:0 0 10px">Your site\'s code normally shows paths like <code>/wp-content/uploads/photo.jpg</code> which screams "this is WordPress!" Ghost Mode replaces all these paths with fake ones, so bots and hackers can\'t tell what platform you\'re using.</p>';

      echo '<div style="padding:10px 14px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;margin:0 0 12px;font-size:12px;line-height:1.5">';
      echo '<strong>&#9888; Not ready yet</strong> — Ghost Mode is being tested and will be available in a future update. The settings below are saved but won\'t take effect until the feature is complete.';
      echo '</div>';

      echo '<label style="display:flex;align-items:center;gap:8px;margin:0 0 6px;cursor:pointer;opacity:0.6">';
      echo '<input type="checkbox" name="safer[htaccess_hardening]" value="1" '.checked($v('htaccess_hardening'),1,false).' />';
      echo '<strong>Enable Ghost Mode</strong> <span style="font-size:11px;color:#86868b">(coming soon)</span>';
      echo '</label>';

      echo '<label style="display:flex;align-items:center;gap:8px;margin:0 0 10px;cursor:pointer;opacity:0.6">';
      echo '<input type="checkbox" name="safer[protezione_2_0]" value="1" '.checked($v('protezione_2_0'),1,false).' />';
      echo '<span>Also rewrite URLs in page source <span style="font-size:11px;color:#86868b">(full stealth, coming soon)</span></span>';
      echo '</label>';

      // Show current mapping if active
      if ($ht_on) {
        echo '<details style="margin:8px 0 0"><summary style="cursor:pointer;font-size:12px;color:#0071e3;font-weight:600">View disguised paths</summary>';
        echo '<div style="font-size:12px;line-height:2;margin:8px 0;padding:10px 14px;background:#fff;border:1px solid #e5e5e7;border-radius:8px">';
        echo 'Visitors see &rarr; Real path<br>';
        echo '<code>/' . esc_html(trim($upload_slug,'/')) . '/…</code> &rarr; <code>/wp-content/uploads/…</code><br>';
        echo '<code>/' . esc_html(trim($inc_slug,'/')) . '/…</code> &rarr; <code>/wp-includes/…</code><br>';
        if ($theme_fake !== '' && $theme_real !== '') {
          echo '<code>/' . esc_html(trim($theme_base,'/')) . '/' . esc_html($theme_fake) . '/…</code> &rarr; <code>/wp-content/themes/' . esc_html($theme_real) . '/…</code><br>';
        }
        echo 'Plugins disguised: <strong>' . intval(count($plugins_map)) . '</strong>';
        if (!empty($plugins_map)) {
          echo '<br>';
          $i = 0;
          foreach ($plugins_map as $real => $fake) {
            $i++;
            if ($i > 8) { echo '<span style="color:#86868b">… and ' . (count($plugins_map) - 8) . ' more</span>'; break; }
            echo '<code>/' . esc_html(trim((string)$fake,'/')) . '/…</code> &rarr; <code>/wp-content/plugins/' . esc_html((string)$real) . '/…</code><br>';
          }
        }
        echo '</div></details>';
      }

      echo '</div>'; // end ghost mode card

      // ── IP RESTRICTION ─────────────────
      echo '<div style="padding:16px 18px;background:#fafafa;border:1px solid #e5e5e7;border-radius:12px;margin:0 0 14px">';

      echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 8px">';
      echo '<span style="font-size:20px">&#127760;</span>';
      echo '<h4 style="margin:0;font-size:14px">IP Restriction — limit who can log in</h4>';
      echo '</div>';

      echo '<p class="description" style="margin:0 0 10px">Only allow specific IP addresses to access the login page and admin area. Everyone else gets blocked silently.</p>';

      echo '<div style="display:flex;gap:16px;margin:0 0 10px;flex-wrap:wrap">';
      echo '<label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="safer[protect_wp_login]" value="1" '.checked($v('protect_wp_login'),1,false).' /> Protect login page</label>';
      echo '<label style="display:flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" name="safer[protect_wp_admin]" value="1" '.checked($v('protect_wp_admin'),1,false).' /> Protect admin area</label>';
      echo '</div>';

      if ($detected_ip !== '') {
        echo '<p class="description" style="margin:0 0 6px">Your current IP: <strong>' . esc_html($detected_ip) . '</strong></p>';
      }
      echo '<textarea name="safer[allowlist_ips]" rows="3" style="width:100%;max-width:320px;font-family:monospace;font-size:13px" placeholder="1.2.3.4&#10;5.6.7.8">'.esc_textarea($allowlist).'</textarea>';
      echo '<p class="description" style="margin:4px 0 0">One IP per line. Leave empty = everyone can access (no restriction).</p>';

      if (($v('protect_wp_admin') || $v('protect_wp_login')) && trim($allowlist) === '') {
        echo '<div style="padding:8px 12px;background:#fef3e2;border:1px solid #fcd9b1;border-radius:6px;font-size:12px;color:#7c3d06;margin:8px 0 0">The IP list is empty — protection is on but nobody will be blocked. Add at least your own IP.</div>';
      }

      echo '</div>'; // end IP card
    }


    // ═══════════════════════════════════════════
    //  TAB 4: EMERGENCY
    // ═══════════════════════════════════════════
    if ($tab === 'tools') {
      echo '<h3 style="margin:0 0 4px">Emergency</h3>';
      echo '<p class="description" style="margin:0 0 16px">If something goes wrong, use these tools to fix your site instantly.</p>';

      // Nuclear reset
      $last = get_option('langa_tools_safer_last_htaccess', null);
      echo '<div style="padding:18px;background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;margin:0 0 14px">';
      echo '<h4 style="margin:0 0 6px;font-size:15px;color:#dc2626">&#9888; Reset Everything</h4>';
      echo '<p class="description" style="margin:0 0 6px">One click to turn off <strong>all</strong> Safer protections and restore your site to exactly how it was before Safer was enabled.</p>';
      echo '<p style="margin:0 0 12px;font-size:12px;color:#6b7280">This will: turn off all hardening &middot; remove Door Lock &middot; disable Ghost Mode &middot; clean server files &middot; clear CSS cache</p>';
      echo '<button type="submit" class="button" style="background:#dc2626;border-color:#b91c1c;color:#fff;font-weight:600;padding:6px 24px;font-size:13px" name="safer_rollback_htaccess" value="1" onclick="return confirm(\'Reset ALL protections and restore your site to normal?\')">Reset Everything</button>';
      if ($last !== null && is_string($last) && strpos($last, 'reset_') === 0) {
        echo '<p style="margin:10px 0 0;font-size:12px;color:#16a34a"><strong>&#10003; Last reset: ' . esc_html(str_replace('reset_', '', $last)) . '</strong></p>';
      }
      echo '</div>';

      // Locked out help
      echo '<div style="padding:18px;background:#f8f8fa;border:1px solid #e5e5e7;border-radius:12px;margin:0 0 14px">';
      echo '<h4 style="margin:0 0 6px;font-size:14px">Can\'t reach this page?</h4>';
      echo '<p class="description" style="margin:0 0 6px">If you\'re completely locked out and can\'t even see this admin page, contact your hosting provider and ask them to:</p>';
      echo '<div style="padding:10px 14px;background:#fff;border:1px solid #e5e5e7;border-radius:8px;margin:0 0 8px">';
      echo '<p style="margin:0;font-size:13px"><strong>1.</strong> Open the file <code>wp-config.php</code> in your site root</p>';
      echo '<p style="margin:4px 0 0;font-size:13px"><strong>2.</strong> Add this line near the top:</p>';
      echo '<pre style="margin:6px 0 0;background:#f8f8fa;padding:8px 12px;border-radius:4px;font-size:12px;user-select:all">define(\'LANGA_SAFER_BYPASS\', true);</pre>';
      echo '</div>';
      echo '<p class="description" style="margin:0">Once you can log in again, come back here and click Reset Everything. Then remove that line from wp-config.php.</p>';
      echo '</div>';

      // Circuit breaker
      $cb = get_transient('langa_safer_circuit_breaker');
      if ($cb !== false && (int)$cb >= 1) {
        echo '<div style="padding:18px;background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;margin:0 0 14px">';
        echo '<h4 style="margin:0 0 6px;font-size:14px;color:#dc2626">Auto-protection activated</h4>';
        echo '<p class="description" style="margin:0">' . (int)$cb . ' error(s) detected. ';
        if ((int)$cb >= 3) {
          echo '<strong>Safer has automatically turned itself off to protect your site.</strong> ';
        }
        echo 'Click Save to clear the error counter and re-enable Safer.</p>';
        echo '</div>';
      }
    }

    echo '</div>';
    echo '</td></tr>';
}

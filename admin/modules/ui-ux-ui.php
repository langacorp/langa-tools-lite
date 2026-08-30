<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_render_module_uiux($module, $enabled, $locked, $f) {
    $s = get_option('langa_tools_adminux_settings', array());
    if (!is_array($s)) $s = array();
    
    $wpui = !empty($s['wpui_improvements']) ? 1 : 0;
    $hide = !empty($s['hide_notices']) ? 1 : 0;
    $maint = !empty($s['maintenance']) ? 1 : 0;
    $sw = !empty($s['user_switching']) ? 1 : 0;
    $credits = !empty($s['credits_enabled']) ? 1 : 0;
    $ghost_pages = !empty($s['ghost_pages']) ? 1 : 0;

    // Preloader (frontend)
    $pl = isset($s['preloader']) && is_array($s['preloader']) ? $s['preloader'] : array();
    $pl_enabled = !empty($pl['enabled']) ? 1 : 0; 
    $pl_bg      = !empty($pl['bg_color']) ? (string)$pl['bg_color'] : '#0b0b0c';
    $pl_op      = isset($pl['bg_opacity']) ? (float)$pl['bg_opacity'] : 0.96;
    $pl_logo    = !empty($pl['logo_url']) ? (string)$pl['logo_url'] : '';
    $pl_w       = isset($pl['logo_width']) ? (int)$pl['logo_width'] : 84; 
    $pl_tms     = isset($pl['transition_ms']) ? (int)$pl['transition_ms'] : 520;
    $pl_first   = !empty($pl['first_visit_session']) ? 1 : 0;
    $pl_exclude = isset($pl['exclude_pages']) ? (string)$pl['exclude_pages'] : '';

    $ms = isset($s['maintenance_style']) && is_array($s['maintenance_style']) ? $s['maintenance_style'] : array();
    if (empty($ms['primary_color'])) $ms['primary_color'] = '#a8a29e';
    if (empty($ms['header_bg'])) $ms['header_bg'] = '#fafaf9';
    if (empty($ms['header_text'])) $ms['header_text'] = '#1c1917';
    if (empty($ms['body_bg'])) $ms['body_bg'] = '#f5f5f4';
    if (empty($ms['form_bg'])) $ms['form_bg'] = '#ffffff';
    if (empty($ms['text_color'])) $ms['text_color'] = '#1c1917';
    if (!isset($ms['radius'])) $ms['radius'] = 5;
    if (!isset($ms['custom_css'])) $ms['custom_css'] = '';

    // Sub-tabs (same style used by other modules)
    $subtab = isset($_GET['tab']) ? sanitize_key((string)$_GET['tab']) : 'general';
    $tabs = array(
      'general'     => 'Overview',
      'maintenance' => 'Maintenance',
      'preloader'   => 'Preloader',
      'replace'     => 'Replace',
      'sitemap'     => 'Visual Sitemap',
      'users'       => 'Users',
      'effects'     => 'Effects',
    );
    if (!isset($tabs[$subtab])) $subtab = 'general';

    $base = admin_url('admin.php?page=langa-tools-client-ui-ux');

    echo '<tr><th scope="row">UI/UX</th><td>';
    langa_tools_client_admin_render_tabs($tabs, $subtab, $base);

    echo '<div class="langa-card langa-tab-panel">';
    echo '<input type="hidden" name="current_tab" value="'.esc_attr($subtab).'" />';
    echo '<table class="form-table" role="presentation" style="margin:0">';

    // Visual Sitemap (/sitemap)
    $vs_enabled = !empty($s['visual_sitemap_enabled']) ? 1 : 0;
    $vs_title   = !empty($s['visual_sitemap_title']) ? (string)$s['visual_sitemap_title'] : 'Sitemap';
    $vs         = isset($s['visual_sitemap']) && is_array($s['visual_sitemap']) ? $s['visual_sitemap'] : array();

    // Defaults (neutral palette)
    $vs_bg   = !empty($vs['bg_color']) ? $vs['bg_color'] : '#f5f5f4';
    $vs_txt  = !empty($vs['text_color']) ? $vs['text_color'] : '#111827';
    $vs_hbg  = !empty($vs['hover_bg_color']) ? $vs['hover_bg_color'] : '#e7e5e4';
    $vs_htxt = !empty($vs['hover_text_color']) ? $vs['hover_text_color'] : '#111827';
    $vs_line = !empty($vs['line_color']) ? $vs['line_color'] : '#d6d3d1';
    $vs_radius = isset($vs['radius']) ? max(0, min(40, (int)$vs['radius'])) : 5;
    $vs_css  = isset($vs['custom_css']) ? (string)$vs['custom_css'] : '';
    $vs_sort_by = !empty($vs['sort_by']) ? sanitize_key($vs['sort_by']) : 'menu_order';
    $vs_sort_order = !empty($vs['sort_order']) ? sanitize_key($vs['sort_order']) : 'asc';
    // NOTE: posts/products are always shown when sections exist (no toggles).

    if ($subtab === 'general') {
      echo '<tr><th scope="row">WP Admin UX + Front UI Improvements</th><td>';
      echo '<input type="hidden" name="adminux[wpui_improvements]" value="0">';
      echo '<label><input type="checkbox" name="adminux[wpui_improvements]" value="1" '.checked($wpui,1,false).' /> Enabled</label>';
      echo '<br><p class="description">Enable the WPUI improvements package.</p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Ghost pages</th><td>';
      echo '<input type="hidden" name="adminux[ghost_pages]" value="0">';
      echo '<label><input type="checkbox" name="adminux[ghost_pages]" value="1" '.checked($ghost_pages,1,false).' /> Enabled</label>';
      echo '<br><p class="description">Adds a “Ghost” flag (meta box / term meta) to hide content from the front (searches/archives/singles) and from the Visual Sitemap.</p>';
      echo '</td></tr>';

      // Branding & Credits: unified panel
      $cl_enabled = !empty($s['custom_login']) ? 1 : 0;
      $data_url = admin_url('admin.php?page=langa-tools-client-settings&tab=data');

      echo '<tr><th scope="row">Branding &amp; Credits</th><td>';
      echo '<div style="background:#fafafa;border:1px solid #e5e5e7;border-radius:12px;padding:20px 22px;">';

        // Identity message
        echo '<div style="display:flex;gap:10px;align-items:flex-start;margin:0 0 14px;padding:12px 16px;background:linear-gradient(135deg,#f37f0d08,#f37f0d03);border:1px solid #f37f0d22;border-left:3px solid #f37f0d;border-radius:0 8px 8px 0">';
          echo '<span class="dashicons dashicons-shield" style="color:#f37f0d;font-size:18px;width:18px;height:18px;flex-shrink:0;margin-top:1px"></span>';
          echo '<p style="margin:0;font-size:12.5px;color:#374151;line-height:1.5">Your signature on this site. Custom Login brands the admin access, Credits signs the frontend. Both stay active even after you hand over the site to your client &mdash; protecting your work permanently.</p>';
        echo '</div>';

        // Simple link to Data settings
        echo '<p style="margin:0 0 14px;font-size:12.5px;color:#64748b;">Logo, brand color, and contact email are managed centrally in <a href="'.esc_url($data_url).'"><strong>Settings &rarr; Data</strong></a>. Changes apply to Custom Login, Credits, and Maintenance simultaneously.</p>';

        // Three cards row
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:0 0 14px">';

          echo '<div style="flex:1;min-width:200px;padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:10px">';
            echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 8px">';
              echo '<span class="dashicons dashicons-admin-network" style="color:#0071e3;font-size:16px;width:16px;height:16px"></span>';
              echo '<strong style="font-size:13px">Custom Login</strong>';
            echo '</div>';
            echo '<input type="hidden" name="adminux[custom_login]" value="0">';
            echo '<label style="display:flex;align-items:center;gap:6px;margin:0 0 6px"><input type="checkbox" name="adminux[custom_login]" value="1" '.checked($cl_enabled,1,false).' /> Enabled</label>';
            echo '<p style="margin:0;font-size:11px;color:#6e6e73;line-height:1.4">Replaces <code>wp-login.php</code> with your brand color and logo.</p>';
          echo '</div>';

          echo '<div style="flex:1;min-width:200px;padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:10px">';
            echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 8px">';
              echo '<span class="dashicons dashicons-awards" style="color:#f37f0d;font-size:16px;width:16px;height:16px"></span>';
              echo '<strong style="font-size:13px">Credits</strong>';
            echo '</div>';
            echo '<input type="hidden" name="adminux[credits_enabled]" value="0">';
            echo '<label style="display:flex;align-items:center;gap:6px;margin:0 0 6px"><input type="checkbox" name="adminux[credits_enabled]" value="1" '.checked($credits,1,false).' /> Enabled (colored)</label>';
            echo '<p style="margin:0;font-size:11px;color:#6e6e73;line-height:1.4">&ldquo;Like this website?&rdquo; in the footer. Grayscale if disabled.</p>';
          echo '</div>';

        echo '</div>';

      echo '</div>';
      echo '</td></tr>';

      // Shortcodes — dynamic registry with runtime verification
      echo '<tr><th scope="row">Shortcodes</th><td>';
      echo '<p class="description" style="margin-top:0">Shortcodes registered by the plugin. Status is verified in real time.</p>';

      langtoli_inline_style('
.langa-sc-badge{display:inline-block;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600;line-height:1.5;white-space:nowrap;}'
        .'.langa-sc-ok{background:#e8f5e9;color:#1b5e20;}'
        .'.langa-sc-off{background:#fef3c7;color:#e65100;}'
        .'.langa-sc-missing{background:#fce4ec;color:#b71c1c;}'
        .'.langa-sc-rt-ok{background:#e8f5e9;color:#1b5e20;}'
        .'.langa-sc-rt-fail{background:#fce4ec;color:#b71c1c;}'
        .'.langa-sc-rt-skip{background:#f5f5f7;color:#86868b;}'
        .'.langa-sc-verify{cursor:pointer;font-size:11px;padding:2px 8px;}'
        .'');

      echo '<div class="langa-scroll-table">';
      echo '<table class="widefat striped" style="max-width:none">';
      echo '<thead><tr><th style="width:320px">Shortcode</th><th>Description</th><th style="width:100px">Status</th><th style="width:110px">Runtime</th><th style="width:150px">Manage</th></tr></thead><tbody>';

      $sc_registry = function_exists('langa_tools_client_shortcodes_registry') ? langa_tools_client_shortcodes_registry() : array();
      $shown_group = array();

      foreach ($sc_registry as $sc) {
        $tag = isset($sc['tag']) ? (string)$sc['tag'] : '';
        if ($tag === '') continue;

        // Grouped entries (langaform_2...10): skip rows after the first
        $group = isset($sc['_group']) ? (string)$sc['_group'] : '';
        if ($group !== '') {
          if (isset($shown_group[$group])) continue;
          $shown_group[$group] = true;
        }

        $display = isset($sc['display']) && $sc['display'] !== '' ? $sc['display'] : '[' . $tag . ']';
        $desc    = isset($sc['desc']) ? (string)$sc['desc'] : '';
        $aliases = isset($sc['aliases']) && is_array($sc['aliases']) ? $sc['aliases'] : array();
        $manage  = isset($sc['manage']) && is_array($sc['manage']) ? $sc['manage'] : array();

        // Status check
        $check_tag = ($group === 'langaform') ? 'langaform_1' : $tag;
        $status = function_exists('langa_tools_client_shortcode_check_status')
          ? langa_tools_client_shortcode_check_status(array_merge($sc, array('tag' => $check_tag)))
          : array('status' => 'ok', 'label' => '?', 'class' => '');

        // For forms group, also verify last preset
        if ($group === 'langaform' && $status['status'] === 'ok') {
          if (!shortcode_exists('langaform_10')) {
            $status = array('status' => 'missing', 'label' => 'Partial registration', 'class' => 'langa-sc-missing');
          }
        }

        echo '<tr>';

        // Col 1: shortcode tag
        $is_pro_sc = !empty($sc['pro']);
        echo '<td><code>' . esc_html($display) . '</code>';
        if ($is_pro_sc) {
          echo ' <span style="background:#f37f0d;color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;vertical-align:middle">PRO</span>';
        }
        if (!empty($aliases)) {
          $alias_codes = array();
          foreach ($aliases as $a) {
            $a_status = shortcode_exists($a) ? 'langa-sc-ok' : 'langa-sc-off';
            $alias_codes[] = '<code>[' . esc_html($a) . ']</code>';
          }
          // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each $alias_codes element is built with esc_html()
          echo ' <span class="description">(alias: ' . esc_html(implode(', ', $alias_codes)) . ')</span>';
        }
        echo '</td>';

        // Col 2: description
        echo '<td>' . esc_html($desc) . '</td>';

        // Col 3: status badge
        echo '<td><span class="langa-sc-badge ' . esc_attr($status['class']) . '" title="' . esc_attr($status['label']) . '">';
        if ($status['status'] === 'ok') {
          echo '&#10003; ' . esc_html($status['label']);
        } elseif ($status['status'] === 'module_off' || $status['status'] === 'feature_off') {
          echo '&#9888; OFF';
        } else {
          echo '&#10007; ' . esc_html($status['label']);
        }
        echo '</span>';
        if ($status['status'] === 'module_off' || $status['status'] === 'feature_off') {
          echo '<br><span class="description" style="font-size:11px">' . esc_html($status['label']) . '</span>';
        }
        echo '</td>';

        // Col 4: runtime verify
        echo '<td>';
        if ($status['status'] === 'ok') {
          echo '<span class="langa-sc-rt-result" data-tag="' . esc_attr($check_tag) . '">';
          echo '<button type="button" class="button button-small langa-sc-verify" data-tag="' . esc_attr($check_tag) . '">Verify</button>';
          echo '</span>';
        } elseif ($status['status'] === 'module_off' || $status['status'] === 'feature_off') {
          echo '<span class="langa-sc-badge langa-sc-rt-skip">&#8212; OFF</span>';
        } else {
          echo '<span class="langa-sc-badge langa-sc-rt-fail">&#10007; N/A</span>';
        }
        echo '</td>';

        // Col 5: manage links
        echo '<td>';
        if (!empty($manage)) {
          $links = array();
          foreach ($manage as $label => $slug) {
            $links[] = '<a class="button button-small" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
          }
          echo implode(' ', $links);
        } else {
          echo '<span class="description">&mdash;</span>';
        }
        echo '</td>';

        echo '</tr>';
      }

      echo '</tbody></table>';
      echo '</div>'; // .langa-scroll-table

      echo '<div style="margin:10px 0 0">';
      echo '<button type="button" class="button" id="langa-sc-verify-all">🔍 Verify all</button>';
      echo '<span id="langa-sc-verify-status" style="margin-left:10px;font-size:12px;color:#86868b"></span>';
      echo '</div>';

      // Runtime verify AJAX
      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin inline JS for immediate DOM manipulation
      echo '<script>';
      echo '(function(){';
      echo 'var nonce = "' . esc_js(wp_create_nonce('langa_sc_runtime_test')) . '";';
      echo 'var ajaxurl = "' . esc_url(admin_url('admin-ajax.php')) . '";';
      echo 'function testOne(btn){';
      echo 'var tag=btn.getAttribute("data-tag");';
      echo 'var wrap=btn.closest(".langa-sc-rt-result");';
      echo 'wrap.innerHTML="<span class=\\"langa-sc-badge langa-sc-rt-skip\\">…</span>";';
      echo 'var fd=new FormData();';
      echo 'fd.append("action","langa_sc_runtime_test");';
      echo 'fd.append("_wpnonce",nonce);';
      echo 'fd.append("tag",tag);';
      echo 'fetch(ajaxurl,{method:"POST",body:fd})';
      echo '.then(function(r){return r.json()})';
      echo '.then(function(d){';
      echo 'if(d.success&&d.data.ok){';
      echo 'var out=d.data.output_len>0?" ("+d.data.output_len+" chr)":"";';
      echo 'wrap.innerHTML="<span class=\\"langa-sc-badge langa-sc-rt-ok\\" title=\\"" + (d.data.output_preview||"") + "\\">&#10003; OK"+out+"</span>";';
      echo '}else{';
      echo 'var err=(d.data&&d.data.error)?d.data.error:"Errore";';
      echo 'wrap.innerHTML="<span class=\\"langa-sc-badge langa-sc-rt-fail\\" title=\\""+err+"\\">&#10007; FAIL</span>";';
      echo '}})';
      echo '.catch(function(){wrap.innerHTML="<span class=\\"langa-sc-badge langa-sc-rt-fail\\">&#10007; Network error</span>";});';
      echo '}';
      echo 'document.addEventListener("click",function(e){if(e.target.classList.contains("langa-sc-verify")){e.preventDefault();testOne(e.target);}});';
      echo 'var allBtn=document.getElementById("langa-sc-verify-all");';
      echo 'if(allBtn){allBtn.addEventListener("click",function(){';
      echo 'var btns=document.querySelectorAll(".langa-sc-verify");';
      echo 'var st=document.getElementById("langa-sc-verify-status");';
      echo 'var total=btns.length,done=0;';
      echo 'st.textContent="Verifico 0/"+total+"…";';
      echo 'btns.forEach(function(b,i){setTimeout(function(){testOne(b);done++;st.textContent="Verifico "+done+"/"+total+(done>=total?" ✓":"…");},i*200);});';
      echo '});}';
      echo '})();';
      echo '</script>';

      echo '</td></tr>';

      // ─── Allowed Upload File Types ───────────────────────────────
      $mimes_opt = get_option('langa_tools_client_allowed_mimes', array());
      if (!is_array($mimes_opt)) $mimes_opt = array();

      $mime_groups = array(
        'Media (frontend)' => array(
          'avif' => 'AVIF',
          'webp' => 'WebP',
          'svg'  => 'SVG',
          'mp4'  => 'MP4 (video)',
          'webm' => 'WebM (video)',
          'mov'  => 'MOV (video)',
          'mp3'  => 'MP3 (audio)',
          'ogg'  => 'OGG (audio)',
          'wav'  => 'WAV (audio)',
          'flac' => 'FLAC (audio)',
          'ico'  => 'ICO (favicon)',
        ),
        'Design' => array(
          'ai'  => 'AI (Illustrator)',
          'eps' => 'EPS',
          'psd' => 'PSD (Photoshop)',
          'dwg' => 'DWG (CAD)',
          'dxf' => 'DXF (CAD)',
        ),
        'Archivi / Dati' => array(
          'zip'  => 'ZIP',
          'csv'  => 'CSV',
          'json' => 'JSON',
        ),
        'Font' => array(
          'woff'  => 'WOFF',
          'woff2' => 'WOFF2',
          'otf'   => 'OTF (OpenType)',
          'ttf'   => 'TTF (TrueType)',
        ),
      );

      echo '<tr><th scope="row">Allowed upload file types</th><td>';
      echo '<p class="description" style="margin:0 0 12px">Enable extra formats for the Media Library. Standard formats (jpg, png, gif, pdf, doc…) always remain active.</p>';

      foreach ($mime_groups as $group_label => $types) {
        echo '<div style="margin:0 0 14px">';
        echo '<strong style="font-size:12px;color:#1d1d1f;text-transform:uppercase;letter-spacing:.3px">' . esc_html($group_label) . '</strong>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:4px 14px;margin:6px 0 0;max-width:800px">';
        foreach ($types as $ext => $label) {
          $checked = !empty($mimes_opt[$ext]) ? ' checked' : '';
          echo '<label style="display:flex;align-items:center;gap:6px;padding:3px 0;font-size:13px">';
          echo '<input type="checkbox" name="allowed_mimes[' . esc_attr($ext) . ']" value="1"' . $checked . '>';
          echo esc_html($label);
          echo '</label>';
        }
        echo '</div>';
        echo '</div>';
      }

      if (!empty($mimes_opt['svg'])) {
        echo '<p class="description" style="margin:4px 0 0;color:#e65100;font-size:12px">ℹ️ <strong>SVG</strong>: can contain JS code. Only upload files from trusted sources.</p>';
      }

      echo '</td></tr>';

    }

    if ($subtab === 'maintenance') {
      echo '<tr><th scope="row">Maintenance</th><td>';
      echo '<input type="hidden" name="adminux[maintenance]" value="0">';
      echo '<label><input type="checkbox" name="adminux[maintenance]" value="1" '.checked($maint,1,false).' /> Enabled</label>';
      echo '<br><p class="description">Shows the maintenance page (503) to visitors and logged-in users whose role is not allowed to bypass. Configure bypass roles below.</p>';
      echo '</td></tr>';

      // --- Bypass roles ---
      $bypass_roles = isset($s['maintenance_bypass_roles']) && is_array($s['maintenance_bypass_roles']) ? $s['maintenance_bypass_roles'] : array();
      $wp_roles = wp_roles();
      $all_roles = $wp_roles->get_names();
      // Always remove administrator (always bypasses)
      unset($all_roles['administrator']);

      echo '<tr><th scope="row">Roles that bypass maintenance</th><td>';
      echo '<fieldset><legend class="screen-reader-text"><span>Bypass roles</span></legend>';
      echo '<input type="hidden" name="adminux[maintenance_bypass_roles][]" value="">';
      foreach ($all_roles as $role_slug => $role_name) {
        $chk = in_array($role_slug, $bypass_roles, true) ? ' checked' : '';
        echo '<label style="display:block;margin-bottom:4px;">';
        echo '<input type="checkbox" name="adminux[maintenance_bypass_roles][]" value="' . esc_attr($role_slug) . '"' . $chk . ' /> ';
        echo esc_html(translate_user_role($role_name));
        echo '</label>';
      }
      echo '</fieldset>';
      echo '<p class="description" style="margin:6px 0 0">Logged-in users with a checked role can see the site during maintenance. <strong>Administrators always bypass.</strong></p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Style (Maintenance)</th><td>';
      $ms_defaults = array(
        'primary_color' => '#a8a29e',
        'header_bg'     => '#fafaf9',
        'header_text'   => '#1c1917',
        'body_bg'       => '#f5f5f4',
        'form_bg'       => '#ffffff',
        'text_color'    => '#1c1917',
        'radius'        => 5,
      );

      echo '<div class="langa-style-scope" data-style-scope="maintenance_style">';
      echo '<table class="form-table" role="presentation" style="margin:0"><tbody>';
      echo '<tr><th scope="row">Primary color</th><td><input type="text" class="regular-text langa-color-field" name="adminux[maintenance_style][primary_color]" value="'.esc_attr($ms['primary_color']).'" /></td></tr>';
      echo '<tr><th scope="row">Header BG</th><td><input type="text" class="regular-text langa-color-field" name="adminux[maintenance_style][header_bg]" value="'.esc_attr($ms['header_bg']).'" /></td></tr>';
      echo '<tr><th scope="row">Header text</th><td><input type="text" class="regular-text langa-color-field" name="adminux[maintenance_style][header_text]" value="'.esc_attr($ms['header_text']).'" /></td></tr>';
      echo '<tr><th scope="row">Page BG</th><td><input type="text" class="regular-text langa-color-field" name="adminux[maintenance_style][body_bg]" value="'.esc_attr($ms['body_bg']).'" /></td></tr>';
      echo '<tr><th scope="row">Form BG</th><td><input type="text" class="regular-text langa-color-field" name="adminux[maintenance_style][form_bg]" value="'.esc_attr($ms['form_bg']).'" /></td></tr>';
      echo '<tr><th scope="row">Text color</th><td><input type="text" class="regular-text langa-color-field" name="adminux[maintenance_style][text_color]" value="'.esc_attr($ms['text_color']).'" /></td></tr>';
      echo '<tr><th scope="row">Radius</th><td><input type="number" min="0" max="40" class="small-text" name="adminux[maintenance_style][radius]" value="'.esc_attr((int)$ms['radius']).'" /> <span class="description">Single value for card/fields/buttons.</span></td></tr>';
      echo '</tbody></table>';

      echo '<p style="margin:10px 0 0">
        <button type="button" class="button langa-reset-colors" data-style-scope="maintenance_style" data-defaults="'.esc_attr(wp_json_encode($ms_defaults)).'">Reset style</button>
        <span class="description" style="margin-left:8px">Reset to neutral default colors (with confirmation).</span>
      </p>';

      echo '</div>';
      echo '</td></tr>';
      
      $maint_recipient = (string)($s['maintenance_recipient'] ?? '');
      echo '<tr><th scope="row">Email recipient *</th><td>';
      echo '<input type="text" class="regular-text langa-required-email" name="adminux[maintenance_recipient]" value="'.esc_attr($maint_recipient).'" placeholder="info@dominio.it, altro@dominio.it" required />';
      echo '<p class="description" style="margin:6px 0 0">Recipients separated by comma. <strong>Required field</strong> to receive form submissions.</p>';
      echo '</td></tr>';
    }

    // PRELOADER (frontend)
    if ($subtab === 'preloader') {
      echo '<tr><th scope="row">Preloader (frontend)</th><td>';
      echo '<input type="hidden" name="adminux[preloader][enabled]" value="0">';
      echo '<label><input type="checkbox" name="adminux[preloader][enabled]" value="1" '.checked($pl_enabled,1,false).' /> Enabled</label>';
      echo '<p class="description" style="margin:6px 0 0">Fullscreen overlay before page load. <strong>Dark</strong> by default.</p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Background</th><td>';
      echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
      echo '<input type="text" class="regular-text langa-color-field" name="adminux[preloader][bg_color]" value="'.esc_attr($pl_bg).'" style="max-width:140px" />';
      echo '<label style="display:flex;gap:6px;align-items:center">Opacity <input type="text" inputmode="decimal" class="small-text" name="adminux[preloader][bg_opacity]" value="'.esc_attr($pl_op).'" /></label>';
      echo '<label style="display:flex;gap:6px;align-items:center">Transition <input type="text" inputmode="numeric" class="small-text" name="adminux[preloader][transition_ms]" value="'.esc_attr($pl_tms).'" /> ms</label>';
      echo '</div>';
      echo '<p class="description" style="margin:6px 0 0">Recommended opacity: 0.92–0.98. Transition is the fade-out duration.</p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Logo</th><td>';
      echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
      echo '<input id="langa-pl-logo" type="text" class="regular-text" name="adminux[preloader][logo_url]" value="'.esc_attr($pl_logo).'" placeholder="https://..." style="min-width:320px;max-width:520px" />';
      echo '<button type="button" class="button langa-media-upload" data-target="#langa-pl-logo">Upload</button>';
      echo '</div>';
      echo '<div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
      echo '<label style="display:flex;gap:6px;align-items:center">Width <input type="number" min="24" max="260" class="small-text" name="adminux[preloader][logo_width]" value="'.esc_attr($pl_w).'" /> px</label>';
      echo '</div>';
      echo '<p class="description" style="margin:6px 0 0">If you don\'t upload a logo: uses the site favicon; if there\'s no favicon, uses LANGA fallback.</p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Behavior</th><td>';
      echo '<label><input type="hidden" name="adminux[preloader][first_visit_session]" value="0"><input type="checkbox" name="adminux[preloader][first_visit_session]" value="1" '.checked($pl_first,1,false).' /> Show only first visit per session</label>';
      echo '<p class="description" style="margin:6px 0 0">Uses <code>sessionStorage</code> (no cookie) to show the preloader only on the first page of the session.</p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Exclude pages</th><td>';
      echo '<textarea class="large-text" rows="4" name="adminux[preloader][exclude_pages]" placeholder="/checkout\n/cart\n/wp-admin\n?elementor" >'.esc_textarea($pl_exclude).'</textarea>';
      echo '<p class="description" style="margin:6px 0 0">One entry per line. If the line starts with <code>/</code>, it matches <strong>exactly</strong> on the page (e.g. <code>/marketing-solutions</code>). To also exclude child pages use <code>*</code> (e.g. <code>/marketing-solutions/*</code>). If it doesn\'t start with <code>/</code>, it\'s matched as a substring on REQUEST_URI (useful for queries like <code>?elementor</code>).</p>';
      echo '</td></tr>';
    }

    if ($subtab === 'users') {
      echo '<tr><th scope="row">Users</th><td>';

      // User switching toggle (debug/admin only)
      echo '<h3 style="margin:0 0 10px">User switching (admin)</h3>';
      echo '<input type="hidden" name="adminux[user_switching]" value="0">';
      echo '<label><input type="checkbox" name="adminux[user_switching]" value="1" '.checked($sw,1,false).' /> Enabled</label>';
      echo '<p class="description" style="margin:6px 0 16px">Adds “Switch to” in the users list (admin only) + “Switch back”. Useful for debugging.</p>';

      $all = get_users(array('orderby' => 'display_name', 'order' => 'ASC'));

      // Current assignments (for display only)
      $e1 = isset($s['langa_editor_1_users']) && is_array($s['langa_editor_1_users']) ? array_map('intval', $s['langa_editor_1_users']) : array();
      $e2 = isset($s['langa_editor_2_users']) && is_array($s['langa_editor_2_users']) ? array_map('intval', $s['langa_editor_2_users']) : array();
      $e3 = isset($s['langa_editor_3_users']) && is_array($s['langa_editor_3_users']) ? array_map('intval', $s['langa_editor_3_users']) : array();
      $custom_map = isset($s['langa_custom_users']) && is_array($s['langa_custom_users']) ? $s['langa_custom_users'] : array();

      $assigned = array();
      foreach ($e1 as $id) { $assigned[(int)$id] = 'Editor 1 — Articoli'; }
      foreach ($e2 as $id) { $assigned[(int)$id] = 'Editor 2 — Articoli + Pagine'; }
      foreach ($e3 as $id) { $assigned[(int)$id] = 'Editor 3 — Articoli + Pagine + Prodotti'; }
      if (is_array($custom_map)) {
        foreach ($custom_map as $uid => $spec) {
          $uid = (int)$uid;
          if ($uid > 0) $assigned[$uid] = 'Custom';
        }
      }

      echo '<h3 style="margin:0 0 10px">Staff Profiles</h3>';
      echo '<p class="description" style="margin:0 0 12px">Select users, choose a profile and click <strong>Apply</strong>. The selected profile <strong>replaces</strong> the WordPress role (no permission stacking). Administrators are never modified.</p>';

      $last_profile = isset($s['users_apply_profile']) ? sanitize_key((string)$s['users_apply_profile']) : 'editor1';
      if (!in_array($last_profile, array('editor1','editor2','editor3','custom'), true)) $last_profile = 'editor1';

      $areas_saved = isset($s['users_apply_areas']) && is_array($s['users_apply_areas']) ? $s['users_apply_areas'] : array();
      // For Custom, keep a neutral default (no content areas pre-selected)
      $areas_saved = array_merge(array(
        'posts' => 0,
        'pages' => 0,
        'products' => 0,
        'media' => 1,
        'wp_tools' => 0,
        'comments' => 0,
        'wc_orders' => 0,
        'extra_pts' => array(),
      ), $areas_saved);
      $extras_saved = isset($areas_saved['extra_pts']) && is_array($areas_saved['extra_pts']) ? array_map('sanitize_key', $areas_saved['extra_pts']) : array();

      // Extra plugin/admin pages (Custom only)
      $menu_pages_saved = isset($s['users_apply_menu_pages']) && is_array($s['users_apply_menu_pages']) ? $s['users_apply_menu_pages'] : array();
      $menu_pages_saved_slugs = array();
      if (is_array($menu_pages_saved)) {
        foreach ($menu_pages_saved as $it) {
          if (!is_array($it)) continue;
          $slug = isset($it['slug']) ? (string)$it['slug'] : '';
          $slug = trim($slug);
          if ($slug !== '') $menu_pages_saved_slugs[$slug] = true;
        }
      }


      echo '<div style="max-width:965px;border:1px solid #e5e5e7;padding:14px 16px;border-radius:10px;background:#fff;overflow-x:auto">';

      echo '<p style="margin:0 0 8px"><strong>Assegna profilo</strong></p>';

      echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start">';
      // Users
      echo '<div style="flex:1;min-width:280px;max-width:520px">';
      echo '<p class="description" style="margin:0 0 6px">Users</p>';
      echo '<input type="text" id="langaUsersSearch" placeholder="Search user..." style="width:100%;margin:0 0 6px;box-sizing:border-box" autocomplete="off">';
      echo '<select id="langaUsersSelect" name="adminux[users_apply_users][]" multiple size="8" style="width:100%;box-sizing:border-box;border:1px solid #d2d2d7;border-radius:6px;font-size:13px">';
      foreach ($all as $u) {
        if (in_array('administrator', (array)$u->roles, true)) continue;
        $label = $u->display_name ? $u->display_name : $u->user_login;
        $label .= ' (' . $u->user_login . ')';
        echo '<option value="' . esc_attr((int)$u->ID) . '">' . esc_html($label) . '</option>';
      }
      echo '</select>';
      echo '</div>';

      // Profile + settings
      echo '<div style="flex:1;min-width:260px;max-width:420px">';
      echo '<p class="description" style="margin:0 0 6px">Profilo</p>';
      echo '<select name="adminux[users_apply_profile]" id="langaUsersProfile" style="width:100%;max-width:360px;box-sizing:border-box">';
      echo '<option value="editor1" '.selected($last_profile,'editor1',false).'>Editor 1 — Posts</option>';
      echo '<option value="editor2" '.selected($last_profile,'editor2',false).'>Editor 2 — Posts + Pages</option>';
      echo '<option value="editor3" '.selected($last_profile,'editor3',false).'>Editor 3 — Posts + Pages + Products</option>';
      echo '<option value="custom"  '.selected($last_profile,'custom',false).'>Custom — scegli cosa gestire</option>';
      echo '</select>';

      echo '<p class="description" style="margin:8px 0 0">Related categories/taxonomies are automatically enabled for selected content types (posts / pages / products / other plugin content).</p>';

      // Custom permissions (simple)
      echo '<div id="langaUsersCustomBox" style="margin-top:12px;display:none">';
      echo '<p class="description" style="margin:0 0 6px"><strong>Content modules</strong> (only for <strong>Custom</strong>)</p>';
      echo '<p class="description" style="margin:0 0 10px">When you enable a module, staff also see all items "below" it (categories, tags, taxonomies, attributes, reviews, etc.).</p>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][posts]" value="1" '.checked((int)$areas_saved['posts'],1,false).'> Articoli</label>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][pages]" value="1" '.checked((int)$areas_saved['pages'],1,false).'> Pagine</label>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][products]" value="1" '.checked((int)$areas_saved['products'],1,false).'> Prodotti</label>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][media]" value="1" '.checked((int)$areas_saved['media'],1,false).'> Media</label>';

      echo '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #f5f5f7">';
      echo '<p class="description" style="margin:0 0 6px"><strong>Properties (system)</strong> (optional)</p>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][wp_tools]" value="1" '.checked((int)$areas_saved['wp_tools'],1,false).'> Strumenti WP</label>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][comments]" value="1" '.checked((int)$areas_saved['comments'],1,false).'> Commenti</label>';
      echo '<p class="description" style="margin:8px 0 0">By default "Tools" and "Comments" are hidden to avoid empty or confusing menus.</p>';
      echo '</div>';

      echo '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #f5f5f7">';
      echo '<p class="description" style="margin:0 0 6px"><strong>WooCommerce</strong> (optional)</p>';
      echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_areas][wc_orders]" value="1" '.checked((int)$areas_saved['wc_orders'],1,false).'> Ordini</label>';
      echo '<p class="description" style="margin:8px 0 0">Shows a dedicated <strong>Orders</strong> entry (without exposing WooCommerce settings).</p>';
      echo '</div>';

      // Extra plugin/admin pages (top-level menu) — optional
      global $menu;
      $plugin_pages = array();
      if (is_array($menu)) {
        $core_slugs = array(
          'index.php','profile.php','edit.php','upload.php','edit.php?post_type=page','edit-comments.php',
          'themes.php','plugins.php','users.php','tools.php','options-general.php','options-writing.php','options-reading.php',
          'options-discussion.php','options-media.php','options-permalink.php','options-privacy.php','site-health.php'
        );
        foreach ($menu as $m) {
          if (!is_array($m) || empty($m[2])) continue;
          $slug = (string)$m[2];
          if ($slug === '' || strpos($slug, 'separator') === 0) continue;
          if (in_array($slug, $core_slugs, true)) continue;
          if (strpos($slug, 'langa-tools-client') !== false) continue;
          // WooCommerce root is handled separately (Orders)
          if ($slug === 'woocommerce' || $slug === 'wc-admin') continue;
          $cap = isset($m[1]) ? (string)$m[1] : '';
          $label = isset($m[0]) ? wp_strip_all_tags((string)$m[0]) : $slug;
          $label = trim($label);
          if ($label === '') $label = $slug;
          $plugin_pages[] = array('label' => $label, 'slug' => $slug, 'cap' => $cap);
        }
      }
      if (!empty($plugin_pages)) {
        echo '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #f5f5f7">';
        echo '<p class="description" style="margin:0 0 6px"><strong>Plugin (admin menu items)</strong> (optional)</p>';
        echo '<p class="description" style="margin:0 0 10px">Select only what you want to see in the menu. (Example: <strong>Fluent Forms</strong>.)</p>';
        foreach ($plugin_pages as $pp) {
          $slug = (string)$pp['slug'];
          $cap  = (string)$pp['cap'];
          $val  = rawurlencode($slug) . '|' . rawurlencode($cap);
          $is_on = isset($menu_pages_saved_slugs[$slug]);
          echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_menu_pages][]" value="'.esc_attr($val).'" '.checked($is_on,true,false).'> '.esc_html($pp['label']).'</label>';
        }
        echo '</div>';
      }

      // Extra post types (Custom only): useful for editorial CPTs from plugins/themes
      $extra_pts = array();
      $pts2 = get_post_types(array('show_ui' => true), 'objects');
      if (is_array($pts2)) {
        foreach ($pts2 as $ptx => $objx) {
          if (!$objx) continue;
          if (in_array($ptx, array('post','page','attachment','product'), true)) continue;
          $lbl = isset($objx->labels) && isset($objx->labels->singular_name) ? (string)$objx->labels->singular_name : (string)$ptx;
          $extra_pts[$ptx] = $lbl . ' (' . $ptx . ')';
        }
      }
      if (!empty($extra_pts)) {
        echo '<div style="margin-top:10px">';
        echo '<p class="description" style="margin:0 0 6px"><strong>Other content (plugins)</strong> (only for <strong>Custom</strong>)</p>';
        foreach ($extra_pts as $ptx => $lbl) {
          $is_on = in_array($ptx, $extras_saved, true);
          echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="adminux[users_apply_extra_pts][]" value="'.esc_attr($ptx).'" '.checked($is_on,true,false).'> '.esc_html($lbl).'</label>';
        }
        echo '</div>';
      }
      echo '</div>';

      echo '</div>'; // right column
      echo '</div>'; // flex

      echo '<div id="langaUsersApplyErr" style="display:none;margin-top:12px">'.
        '  <div class="notice notice-error inline" style="margin:0"><p>Select at least 1 user before applying the profile.</p></div>'.
        '</div>';
      echo '<div style="margin-top:12px">';
      echo '<button id="langaUsersApplyBtn" type="submit" class="button button-primary" name="adminux[users_action]" value="apply">Apply profile</button>';
      echo '</div>';

      echo '</div>';

      // Current managed users list
      if (!empty($assigned)) {
        echo '<p style="margin:16px 0 8px"><strong>Users with managed profile (current)</strong></p>';
        echo '<div class="langa-scroll-table" style="max-width:965px">';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th style="width:40px">&nbsp;</th><th>User</th><th style="width:160px">Profilo</th></tr></thead><tbody>';
        foreach ($all as $u) {
          $uid = (int)$u->ID;
          if (!$uid || !isset($assigned[$uid])) continue;
          if (in_array('administrator', (array)$u->roles, true)) continue;
          $label = $u->display_name ? $u->display_name : $u->user_login;
          $label .= ' (' . $u->user_login . ')';
          echo '<tr>';
          echo '<td><input type="checkbox" name="adminux[users_remove][]" value="' . esc_attr($uid) . '"></td>';
          echo '<td>' . esc_html($label) . '</td>';
          echo '<td><code>' . esc_html($assigned[$uid]) . '</code></td>';
          echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '<p style="margin:10px 0 0"><button type="submit" class="button button-secondary" name="adminux[users_action]" value="remove">Remove profile (revert to Subscriber)</button></p>';
      }
// Tiny JS: show Custom area only when selected.
langtoli_inline_script('
(function(){
  var sel=document.getElementById("langaUsersProfile");
  var box=document.getElementById("langaUsersCustomBox");
  function upd(){
    var isCustom = sel && sel.value === "custom";
    if(box) box.style.display = isCustom ? "block" : "none";
  }
  if(sel) sel.addEventListener("change", upd);
  upd();
})();');
langtoli_inline_script('
(function(){
  var userSel=document.getElementById("langaUsersSelect");
  var search=document.getElementById("langaUsersSearch");
  var err=document.getElementById("langaUsersApplyErr");
  var applyBtn=document.getElementById("langaUsersApplyBtn");
  var allOpts=[];
  if(userSel){
    for(var i=0;i<userSel.options.length;i++){
      allOpts.push({v:userSel.options[i].value, t:userSel.options[i].text});
    }
  }
  function rebuild(q){
    if(!userSel) return;
    q=(q||"").toLowerCase().trim();
    var selected={};
    for(var i=0;i<userSel.options.length;i++){
      if(userSel.options[i].selected) selected[userSel.options[i].value]=true;
    }
    userSel.innerHTML="";
    var added={};
    function addOpt(o){
      if(added[o.v]) return;
      var opt=document.createElement("option");
      opt.value=o.v;
      opt.textContent=o.t;
      if(selected[o.v]) opt.selected=true;
      userSel.appendChild(opt);
      added[o.v]=true;
    }
    // Selected first (always visible)
    allOpts.forEach(function(o){ if(selected[o.v]) addOpt(o); });
    allOpts.forEach(function(o){
      var match = !q || (o.t.toLowerCase().indexOf(q)!==-1);
      if(match) addOpt(o);
    });
  }
  if(search && userSel){
    search.addEventListener("input", function(){ rebuild(this.value); });
  }
  if(applyBtn){
    applyBtn.addEventListener("click", function(ev){
      if(!userSel) return;
      var has=false;
      for(var i=0;i<userSel.options.length;i++){
        if(userSel.options[i].selected){ has=true; break; }
      }
      if(!has){
        ev.preventDefault();
        if(err) err.style.display="block";
        if(search) search.focus();
        userSel.focus();
      } else {
        if(err) err.style.display="none";
      }
    });
  }
})();');
      echo '</td></tr>';

      
    }
    


    if ($subtab === 'effects') {
      if (!function_exists('langa_tools_client_get_effects_option')) {
        require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/effects/options.php';
      }
      $opt = langa_tools_client_get_effects_option();
      $eff_enabled = !empty($opt['enabled']) ? 1 : 0;
      $rows = isset($opt['rows']) && is_array($opt['rows']) ? $opt['rows'] : array();

      echo '<tr><th scope="row">Effects (frontend)</th><td>';
      echo '<input type="hidden" name="adminux[effects_enabled]" value="0">';
      echo '<label><input type="checkbox" name="adminux[effects_enabled]" value="1" '.checked($eff_enabled,1,false).' /> Enabled</label>';
      echo '<br><p class="description">Enable/disable seasonal effects on the site. (Configuration is preserved.)</p>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Effects rules</th><td>';
      echo '<p class="description">Recurring yearly windows. Start/End (DD/MM). Before/After extends the window. If Start/End are empty: not applied.</p>';

      echo '<div class="langa-scroll-table" style="max-width:965px">';
      echo '<table class="widefat striped">';
      echo '<thead><tr><th style="width:220px;">Event</th><th style="width:160px;">Start</th><th style="width:160px;">End</th><th style="width:120px;">Before</th><th style="width:120px;">After</th></tr></thead><tbody>';

      $events = array(
        'snow' => 'Christmas',
        'newyear' => 'New Year',
        'valentine' => 'Valentine',
        'easter' => 'Easter',
        'halloween' => 'Halloween',
        'spring' => 'Spring',
        'autumn' => 'Autumn',
        'special' => 'Special Event',
      );

      $easter_date = date('d/m', easter_date(date('Y')));
      $js_defaults = array(
        'snow'      => array('s'=>'08/12', 'e'=>'06/01', 'b'=>3, 'a'=>2),
        'newyear'   => array('s'=>'31/12', 'e'=>'01/01', 'b'=>1, 'a'=>1),
        'valentine' => array('s'=>'14/02', 'e'=>'14/02', 'b'=>2, 'a'=>1),
        'easter'    => array('s'=>$easter_date, 'e'=>$easter_date, 'b'=>5, 'a'=>2),
        'halloween' => array('s'=>'31/10', 'e'=>'01/11', 'b'=>3, 'a'=>1),
        'spring'    => array('s'=>'20/03', 'e'=>'20/03', 'b'=>5, 'a'=>5),
        'autumn'    => array('s'=>'22/09', 'e'=>'22/09', 'b'=>5, 'a'=>5),
        'special'   => array('s'=>'15/08', 'e'=>'15/08', 'b'=>1, 'a'=>0),
      );

      $num_rows = count($events);
      for ($i=0; $i<$num_rows; $i++) {
        $r = isset($rows[$i]) ? $rows[$i] : array();
        $effect   = $r['effect'] ?? '';
        $start_md = $r['start_md'] ?? '';
        $end_md   = $r['end_md'] ?? '';
        $before   = isset($r['before']) ? $r['before'] : '';
        $after    = isset($r['after']) ? $r['after'] : '';

        echo '<tr class="effect-row">';
        echo '<td><select name="effects['.$i.'][effect]" class="effect-select"><option value="">—</option>';
        foreach ($events as $k=>$lab) {
          echo '<option value="'.esc_attr($k).'" '.selected($effect,$k,false).'>'.esc_html($lab).'</option>';
        }
        echo '</select></td>';
        echo '<td><input type="text" name="effects['.$i.'][start_md]" value="'.esc_attr($start_md).'" class="inp-start" placeholder="GG/MM" style="width:110px"></td>';
        echo '<td><input type="text" name="effects['.$i.'][end_md]" value="'.esc_attr($end_md).'" class="inp-end" placeholder="GG/MM" style="width:110px"></td>';
        echo '<td><input type="number" name="effects['.$i.'][before]" value="'.esc_attr($before).'" class="inp-before" placeholder="0" min="0" step="1" style="width:70px"></td>';
        echo '<td><input type="number" name="effects['.$i.'][after]" value="'.esc_attr($after).'" class="inp-after" placeholder="0" min="0" step="1" style="width:70px"></td>';
        echo '</tr>';
      }

      echo '</tbody></table>';
      echo '</div>'; // .langa-scroll-table
      echo '<p class="description" style="margin-top:10px;">Tip: cambia l’evento nel menu a tendina per auto-compilare valori suggeriti.</p>';

      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin inline JS for immediate DOM manipulation
      echo '<script>';
      echo 'document.addEventListener("change",function(e){if(e.target&&e.target.classList.contains("effect-select")){var row=e.target.closest(".effect-row");var effect=e.target.value;var defaults='.wp_json_encode($js_defaults).';if(effect&&defaults[effect]){var d=defaults[effect];row.querySelector(".inp-start").value=d.s;row.querySelector(".inp-end").value=d.e;row.querySelector(".inp-before").value=d.b;row.querySelector(".inp-after").value=d.a;}else if(!effect){row.querySelector(".inp-start").value="";row.querySelector(".inp-end").value="";row.querySelector(".inp-before").value="";row.querySelector(".inp-after").value="";}}});';
      echo '</script>';

      // Custom effect (CSS/JS)
      $c = isset($opt['custom']) && is_array($opt['custom']) ? $opt['custom'] : array();
      $c_start = isset($c['start_md']) ? (string)$c['start_md'] : '';
      $c_end   = isset($c['end_md']) ? (string)$c['end_md'] : '';
      $c_css   = isset($c['css']) ? (string)$c['css'] : '';
      $c_js    = isset($c['js']) ? (string)$c['js'] : '';

      echo '<hr style="margin:18px 0;" />';
      echo '<h3 style="margin:0 0 8px;">Custom effect</h3>';
      echo '<p class="description" style="margin:0 0 10px;">Custom effect (inline). If Start/End are empty, not applied. CSS goes in <code>&lt;style&gt;</code>, JS in <code>&lt;script&gt;</code> (footer).</p>';

      echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;">';
        echo '<div>';
          echo '<label style="display:block;font-weight:600;margin-bottom:4px;">Start (GG/MM)</label>';
          echo '<input type="text" name="effects_custom[start_md]" value="'.esc_attr($c_start).'" placeholder="GG/MM" style="width:110px" />';
        echo '</div>';
        echo '<div>';
          echo '<label style="display:block;font-weight:600;margin-bottom:4px;">End (GG/MM)</label>';
          echo '<input type="text" name="effects_custom[end_md]" value="'.esc_attr($c_end).'" placeholder="GG/MM" style="width:110px" />';
        echo '</div>';
      echo '</div>';

      echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:965px;margin-top:12px;">';
        echo '<div>';
          echo '<label style="display:block;font-weight:600;margin-bottom:6px;">CSS</label>';
          echo '<textarea name="effects_custom[css]" rows="10" style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\"Liberation Mono\",\"Courier New\",monospace;">'.esc_textarea($c_css).'</textarea>';
        echo '</div>';
        echo '<div>';
          echo '<label style="display:block;font-weight:600;margin-bottom:6px;">JS</label>';
          echo '<textarea name="effects_custom[js]" rows="10" style="width:100%;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\"Liberation Mono\",\"Courier New\",monospace;">'.esc_textarea($c_js).'</textarea>';
        echo '</div>';
      echo '</div>';

      echo '</td></tr>';
    }

    if ($subtab === 'replace') {
      // One-shot notice (after tool run)
      if (function_exists('langa_tools_client_replace_get_notice')) {
        $n = langa_tools_client_replace_get_notice();
        if (is_array($n) && !empty($n['msg'])) {
          $type = isset($n['type']) ? (string)$n['type'] : 'info';
          $nbg = '#f0f6fc'; $nbc = '#72aee6';
          if ($type === 'success') { $nbg = '#edfaef'; $nbc = '#00a32a'; }
          if ($type === 'error')   { $nbg = '#fcf0f1'; $nbc = '#d63638'; }
          if ($type === 'warning') { $nbg = '#fcf9e8'; $nbc = '#dba617'; }
          echo '<tr><th scope="row"></th><td>';
          echo '<div style="margin:0;max-width:965px;padding:1px 12px;background:'.esc_attr($nbg).';border:1px solid #c3c4c7;border-left:4px solid '.esc_attr($nbc).';border-radius:0"><p style="margin:10px 12px;">'.esc_html((string)$n['msg']).'</p></div>';
          echo '</td></tr>';
        }
      }

      // -------------------------
      // Quick links to tools in this tab
      // -------------------------
      echo '<tr><th scope="row"></th><td>';
      echo '<div style="display:flex;gap:16px;font-size:13px">';
      echo '<a href="#langa-tool-media-replace">↓ Media Replace</a>';
      echo '<a href="#langa-tool-text-replace">↓ Text/URL/Code Replace</a>';
      echo '</div>';
      echo '</td></tr>';

      // -------------------------
      // Tool: Media Replace
      // -------------------------
      $keep_backup = !empty($s['media_replace_keep_backup']) ? 1 : 0;

      $aid = isset($_GET['attachment_id']) ? absint(wp_unslash($_GET['attachment_id'])) : (isset($_GET['media_id']) ? absint(wp_unslash($_GET['media_id'])) : 0);
      $att = $aid > 0 ? get_post($aid) : null;
      if (!$att || $att->post_type !== 'attachment') {
        $aid = 0;
      }

      echo '<tr id="langa-tool-media-replace"><th scope="row">Media Replace</th><td>';
      echo '<div class="description" style="max-width:965px;">Adds a <strong>Replace</strong> action in the Media Library and lets you replace a file <em>keeping the same URL</em>.</div>';

      echo '<div style="max-width:965px;margin-top:12px;">';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
          echo '<button type="button" class="button button-secondary" id="langa-mr-pick">Select media</button>';
          echo '<span class="description">Choose the existing media to replace (WP Media Library popup).</span>';
        echo '</div>';
        echo '<div class="description" style="margin-top:6px;">Tip: you can also use the <strong>Replace</strong> action in <a href="'.esc_url(admin_url('upload.php?mode=list')).'">Media → Library (list)</a>.</div>';
        echo '<input type="hidden" id="langa-mr-attachment-id" name="replace_media_attachment_id" value="'.esc_attr((string)$aid).'">';
        echo '<input type="hidden" id="langa-mr-expected-ext" value="">';
        echo '<input type="hidden" id="langa-mr-is-image" value="0">';
      echo '</div>';

      echo '<div id="langa-mr-selected" style="max-width:965px;margin-top:14px;">';
      if ($aid > 0) {
        $url = wp_get_attachment_url($aid);
        $file = get_attached_file($aid);
        $is_img = wp_attachment_is_image($aid);
        $ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';

        echo '<div class="langa-card" id="langa-mr-selected-inner" data-ext="'.esc_attr($ext).'" data-id="'.esc_attr((string)$aid).'" data-url="'.esc_attr((string)$url).'" data-isimg="'.($is_img ? '1' : '0').'" style="padding:14px;">';
          echo '<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">';
            if ($is_img && $url) {
              echo '<div style="border:1px solid #d2d2d7;border-radius:12px;padding:10px;background:#fff;">';
                echo '<img src="'.esc_url($url).'" alt="" style="display:block;max-width:220px;height:auto;border-radius:8px;" />';
              echo '</div>';
            }
            echo '<div style="min-width:320px;flex:1;">';
              echo '<div><strong>ID:</strong> '.(int)$aid.'</div>';
              if ($url) echo '<div style="margin-top:6px;"><strong>URL:</strong> <code style="word-break:break-all;">'.esc_html($url).'</code></div>';
              if ($file) echo '<div style="margin-top:6px;"><strong>File:</strong> <code style="word-break:break-all;">'.esc_html(basename($file)).'</code></div>';
              if ($ext)  echo '<div style="margin-top:6px;"><strong>Estensione:</strong> <code>.'.esc_html($ext).'</code></div>';
              echo '<div style="margin-top:10px;">';
                echo '<a class="button button-secondary" href="'.esc_url(get_edit_post_link($aid,'raw')).'">Open media</a>';
                echo ' <button type="button" class="button button-link-delete" id="langa-mr-clear">Reset</button>';
              echo '</div>';
            echo '</div>';
          echo '</div>';
        echo '</div>';
      } else {
        echo '<div style="margin:0;max-width:965px;padding:1px 12px;background:#f0f6fc;border:1px solid #c3c4c7;border-left:4px solid #72aee6;border-radius:0"><p style="margin:10px 12px;">Select a media file with the <strong>Select media</strong> button to continue.</p></div>';
      }
      echo '</div>';

      $show_upload = ($aid > 0);
      echo '<div id="langa-mr-upload" style="max-width:965px;margin-top:14px;'.(!$show_upload ? 'display:none;' : '').'">';
        echo '<div class="langa-card" style="padding:14px;">';
          echo '<label style="display:block;font-weight:600;margin:0 0 6px;">Upload replacement file</label>';
          echo '<input type="file" id="langa-mr-file" name="replace_media_file" />';

          echo '<input type="hidden" name="adminux[media_replace_keep_backup]" value="0">';
          echo '<label style="display:block;margin:10px 0 0;">';
            echo '<input type="checkbox" name="adminux[media_replace_keep_backup]" value="1" '.checked($keep_backup,1,false).' /> Mantieni un backup (.bak) del file precedente';
          echo '</label>';

          echo '<p class="description" id="langa-mr-note" style="margin:8px 0 0;">';
            echo 'For non-image files: same extension required (URL unchanged). For images: you can upload a different format (jpg/png/webp) — it will be converted to the original format keeping the same URL.';
          echo '</p>';

          echo '<div id="langa-mr-error" style="display:none;margin:12px 0 0;padding:1px 12px;background:#fcf0f1;border:1px solid #c3c4c7;border-left:4px solid #d63638;border-radius:0"><p style="margin:10px 12px;"></p></div>';
          echo '<div class="langa-inline-actions">';
            echo '<button type="submit" class="button button-primary" id="langa-mr-do" name="replace_media_do" value="1">Replace file</button>';
          echo '</div>';
        echo '</div>';
      echo '</div>';

      echo '</td></tr>';

      // -------------------------
      // Tool: Text/URL/Code Replace (DB)
      // -------------------------
      global $wpdb;
      $all_tables = $wpdb ? $wpdb->get_col('SHOW TABLES') : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- schema introspection
      $all_tables = is_array($all_tables) ? $all_tables : array();

      $last = get_option('langa_tools_adminux_replace_report', array());
      $last_search  = is_array($last) ? (string)($last['search'] ?? '') : '';
      $last_replace = is_array($last) ? (string)($last['replace'] ?? '') : '';
      $last_dry     = is_array($last) ? (int)($last['dry_run'] ?? 1) : 1;
      $last_sel     = is_array($last) && !empty($last['tables_selected']) && is_array($last['tables_selected']) ? $last['tables_selected'] : array();

      // Default selection: site tables
      $default_sel = array();
      if ($wpdb) {
        $p1 = (string)$wpdb->prefix;
        $p2 = (string)$wpdb->base_prefix;
        foreach ($all_tables as $t) {
          $t = (string)$t;
          if ($p1 && strpos($t, $p1) === 0) $default_sel[] = $t;
          if ($p2 && ($t === $p2.'users' || $t === $p2.'usermeta')) $default_sel[] = $t;
        }
      }
      $sel = !empty($last_sel) ? $last_sel : array_values(array_unique($default_sel));

      $include_guids = is_array($last) ? (int)($last['include_guids'] ?? 0) : 0;
      $max_rows = is_array($last) ? (int)($last['max_rows_per_table'] ?? 0) : 0;

      echo '<tr id="langa-tool-text-replace"><th scope="row">Text/URL/Code Replace</th><td>';

      echo '<div style="margin:0;max-width:965px;padding:1px 12px;background:#fcf9e8;border:1px solid #c3c4c7;border-left:4px solid #dba617;border-radius:0"><p style="margin:10px 12px;"><strong>Warning:</strong> this tool can modify the database. Make a backup first. By default it runs in <em>dry-run</em> (simulation) mode.</p></div>';

      echo '<div class="langa-card" style="padding:14px;max-width:965px;margin-top:14px;">';

        echo '<label style="display:block;font-weight:600;margin:0 0 6px;">Search for</label>';
        echo '<textarea name="replace_search" rows="2" class="large-text code" placeholder="es: https://vecchio-dominio.it">'.esc_textarea($last_search).'</textarea>';

        echo '<div style="margin-top:12px;">';
          echo '<label style="display:block;font-weight:600;margin:0 0 6px;">Replace with</label>';
          echo '<textarea name="replace_replace" rows="2" class="large-text code" placeholder="es: https://nuovo-dominio.it">'.esc_textarea($last_replace).'</textarea>';
        echo '</div>';

        echo '<div style="margin-top:12px;">';
          echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;">';
            echo '<label style="display:flex;gap:8px;align-items:center;margin:0;">';
              echo '<input type="checkbox" name="replace_dry_run" value="1" '.checked($last_dry,1,false).' /> <strong>Dry run</strong> (no changes)';
            echo '</label>';
            echo '<button type="button" class="button button-secondary" id="langa-tr-advanced-toggle">Advanced options</button>';
            echo '<span id="langa-tr-mode" class="langa-badge" style="margin-left:auto;display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;border:1px solid #d2d2d7;background:#fff;font-weight:600;font-size:12px;line-height:1;">'.($last_dry ? 'Preview' : 'Apply').'</span>';
          echo '</div>';

          // Step 3: confirmation only when applying
          echo '<div id="langa-tr-confirm" style="margin-top:12px;padding:12px;border:1px dashed #d2d2d7;border-radius:10px;background:#fff;'.($last_dry ? 'display:none;' : '').'">';
            echo '<div style="font-weight:600;margin-bottom:6px;">Confirm (APPLY only)</div>';
            echo '<label style="display:block;margin:0 0 8px;">';
              echo '<input type="checkbox" name="replace_ack" value="1" /> I understand the risks';
            echo '</label>';
            echo '<input type="text" name="replace_confirm" placeholder="Type: REPLACE" style="width:100%;max-width:240px;" />';
            echo '<p class="description" style="margin:8px 0 0;">To apply real changes, disable “Dry run”.</p>';
          echo '</div>';

          // Step 4: run action (always last)
          echo '<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;margin-top:12px;">';
            echo '<div class="description" style="margin:0;">Default scope: <strong>all site tables</strong> (prefix) + users/usermeta. The replace is <strong>safe for serialized data</strong>.</div>';
            echo '<div class="langa-inline-actions" style="margin:0;">';
              echo '<button type="submit" class="button button-primary" id="langa-tr-run" name="replace_text_do" value="1">'.($last_dry ? 'Run preview' : 'Apply replace').'</button>';
            echo '</div>';
          echo '</div>';
        echo '</div>';

      echo '</div>';

      // Advanced panel (collapsed)
      echo '<div id="langa-tr-advanced" style="display:none;max-width:965px;margin-top:12px;">';
        echo '<div class="langa-card" style="padding:14px;">';
          echo '<div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start;">';

            echo '<div style="min-width:320px;flex:1;">';
              echo '<label style="display:block;font-weight:600;margin-bottom:6px;">Tables (scope)</label>';
              echo '<select name="replace_tables[]" multiple size="10" style="width:100%;max-width:520px;">';
              foreach ($all_tables as $t) {
                $t = (string)$t;
                $is_sel = in_array($t, $sel, true);
                echo '<option value="'.esc_attr($t).'" '.selected($is_sel,true,false).'>'.esc_html($t).'</option>';
              }
              echo '</select>';
              echo '<p class="description" style="margin:6px 0 0;">If unsure, leave the defaults.</p>';
            echo '</div>';

            echo '<div style="min-width:260px;">';
              echo '<label style="display:block;margin:0 0 10px;">';
                echo '<input type="checkbox" name="replace_include_guids" value="1" '.checked($include_guids,1,false).' /> Include GUID columns';
              echo '</label>';

              echo '<label style="display:block;margin:0 0 6px;font-weight:600;">Max rows per table (0 = all)</label>';
              echo '<input type="number" class="small-text" min="0" step="1" name="replace_max_rows" value="'.esc_attr((string)$max_rows).'" />';
              echo '<p class="description" style="margin:6px 0 0;">If the DB is large and times out, try a limit and repeat in steps.</p>';

            echo '</div>';

          echo '</div>';
        echo '</div>';
      echo '</div>';

      // Report
      if (is_array($last) && !empty($last['tables']) && is_array($last['tables'])) {
        $dt = !empty($last['ts']) ? date_i18n('Y-m-d H:i', (int)$last['ts']) : '';
        $is_dry = !empty($last['dry_run']);
        echo '<div style="max-width:965px;margin-top:16px;">';
          echo '<h3 style="margin:0 0 8px;">Last report</h3>';
          echo '<div class="description" style="margin:0 0 10px;">'.esc_html($dt).' — '.($is_dry ? 'Dry-run' : 'Applied').' — elapsed: '.esc_html((string)($last['elapsed_ms'] ?? 0)).'ms</div>';
          echo '<div class="langa-scroll-table" style="max-width:965px;">';
          echo '<table class="widefat striped">';
            echo '<thead><tr><th>Table</th><th>Rows scanned</th><th>Rows changed</th><th>Cells changed</th></tr></thead><tbody>';
            foreach ($last['tables'] as $t => $r) {
              $table_name = (is_array($r) && !empty($r['table'])) ? (string)$r['table'] : (is_string($t) ? $t : (string)$t);
              $scanned = is_array($r) ? (int)($r['rows_scanned'] ?? 0) : 0;
              $rowsc   = is_array($r) ? (int)($r['rows_changed'] ?? 0) : 0;
              $cellc   = is_array($r) ? (int)($r['cells_changed'] ?? 0) : 0;
              echo '<tr>';
                echo '<td><code>'.esc_html($table_name).'</code></td>';
                echo '<td>'.esc_html((string)$scanned).'</td>';
                echo '<td>'.esc_html((string)$rowsc).'</td>';
                echo '<td>'.esc_html((string)$cellc).'</td>';
              echo '</tr>';
            }
            echo '</tbody></table>';
          echo '</div>';
        echo '</div>';
      }

      echo '</td></tr>';

      // ─── PROMO BANNER ISOLATION ────────────────────────────
      echo '<tr><th scope="row">Isolate Promo Banners</th><td>';
      echo '<div class="description" style="max-width:965px;margin:0 0 10px;">';
      echo 'Hide annoying plugin/theme promotional banners from wp-admin pages. Banners are moved to a dedicated collection page — one copy per unique banner, no duplicates.';
      echo '</div>';

      $promo_rules = isset($s['promo_isolation_rules']) && is_array($s['promo_isolation_rules']) ? $s['promo_isolation_rules'] : array();

      echo '<div id="langa-promo-iso-wrap">';
      echo '<div class="langa-scroll-table" style="max-width:965px;margin:0 0 12px;" id="langa-promo-iso-table">';
      echo '<table class="widefat striped" style="font-size:13px">';
      echo '<thead><tr>';
      echo '<th style="width:50px">Active</th>';
      echo '<th style="width:160px">Match type</th>';
      echo '<th>Selector / Value</th>';
      echo '<th style="width:200px">Label (optional)</th>';
      echo '<th style="width:60px"></th>';
      echo '</tr></thead><tbody id="langa-promo-iso-rows">';

      if (!empty($promo_rules)) {
        foreach ($promo_rules as $ri => $rule) {
          $r_active   = !empty($rule['active']) ? 1 : 0;
          $r_type     = sanitize_key($rule['type'] ?? 'class');
          $r_selector = esc_attr($rule['selector'] ?? '');
          $r_label    = esc_attr($rule['label'] ?? '');
          echo '<tr data-idx="' . (int)$ri . '">';
          echo '<td style="text-align:center"><input type="checkbox" name="adminux[promo_isolation_rules][' . (int)$ri . '][active]" value="1"' . checked($r_active, 1, false) . ' /></td>';
          echo '<td><select name="adminux[promo_isolation_rules][' . (int)$ri . '][type]" style="width:100%">';
          foreach (array('class' => 'CSS class', 'id' => 'Element ID', 'data-nonce' => 'data-nonce attr') as $tk => $tl) {
            echo '<option value="' . esc_attr($tk) . '"' . selected($r_type, $tk, false) . '>' . esc_html($tl) . '</option>';
          }
          echo '</select></td>';
          echo '<td><input type="text" name="adminux[promo_isolation_rules][' . (int)$ri . '][selector]" value="' . $r_selector . '" style="width:100%" placeholder=".my-plugin-banner" /></td>';
          echo '<td><input type="text" name="adminux[promo_isolation_rules][' . (int)$ri . '][label]" value="' . $r_label . '" style="width:100%" placeholder="Plugin XYZ promo" /></td>';
          echo '<td style="text-align:center"><button type="button" class="button button-link-delete langa-promo-remove-rule">&times;</button></td>';
          echo '</tr>';
        }
      }

      echo '</tbody></table>';
      echo '</div>';

      echo '<button type="button" class="button button-small" id="langa-promo-add-rule">+ Add rule</button>';
      echo ' <span class="description">Target banners by CSS class, element ID, or data-nonce attribute.</span>';

      $iso_page_url = admin_url('admin.php?page=langa-tools-client-ui-ux&tab=replace&view=isolated-banners');
      echo '<div style="margin:12px 0 0;padding:10px 14px;background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;max-width:965px">';
      echo '<span class="dashicons dashicons-visibility" style="color:#1565c0;font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:6px"></span>';
      echo '<a href="' . esc_url($iso_page_url) . '" style="font-weight:600;color:#1565c0;text-decoration:none">View isolated banners &rarr;</a>';
      echo ' <span class="description" style="margin-left:6px">See all captured banners (deduplicated).</span>';
      echo '</div>';

      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin page callback, timing prevents wp_add_inline_script
      echo '<script>';
      echo '(function($){';
      echo 'var idx = ' . absint(max(count($promo_rules), 0)) . ';';
      echo '$("#langa-promo-add-rule").on("click",function(){';
      echo 'var html="<tr data-idx=\""+idx+"\">"';
      echo '+"<td style=\"text-align:center\"><input type=\"checkbox\" name=\"adminux[promo_isolation_rules]["+idx+"][active]\" value=\"1\" checked /></td>"';
      echo '+"<td><select name=\"adminux[promo_isolation_rules]["+idx+"][type]\" style=\"width:100%\"><option value=\"class\">CSS class</option><option value=\"id\">Element ID</option><option value=\"data-nonce\">data-nonce attr</option></select></td>"';
      echo '+"<td><input type=\"text\" name=\"adminux[promo_isolation_rules]["+idx+"][selector]\" style=\"width:100%\" placeholder=\".my-plugin-banner\" /></td>"';
      echo '+"<td><input type=\"text\" name=\"adminux[promo_isolation_rules]["+idx+"][label]\" style=\"width:100%\" placeholder=\"Plugin XYZ promo\" /></td>"';
      echo '+"<td style=\"text-align:center\"><button type=\"button\" class=\"button button-link-delete langa-promo-remove-rule\">&times;</button></td>"';
      echo '+"</tr>";';
      echo '$("#langa-promo-iso-rows").append(html);idx++;';
      echo '});';
      echo '$(document).on("click",".langa-promo-remove-rule",function(){$(this).closest("tr").remove()});';
      echo '})(jQuery);';
      echo '</script>';

      echo '</div>';

      // ─── ISOLATED BANNERS VIEW ─────────────────────────────
      if (isset($_GET['view']) && $_GET['view'] === 'isolated-banners') {
        $captured = get_option('langa_tools_promo_captured_banners', array());
        echo '<div style="margin-top:16px;max-width:965px">';
        echo '<h3 style="margin:0 0 8px">Isolated Banners</h3>';
        echo '<p class="description" style="margin:0 0 10px">Banners captured and hidden from admin pages. Each unique banner is shown once regardless of how many pages it appeared on.</p>';

        if (empty($captured)) {
          echo '<div style="margin:0;padding:1px 12px;background:#f0f6fc;border:1px solid #c3c4c7;border-left:4px solid #72aee6;border-radius:0"><p>No banners captured yet. Add rules above and browse admin pages to start collecting.</p></div>';
        } else {
          echo '<div style="display:flex;gap:6px;margin:0 0 10px">';
          echo '<form method="post" style="margin:0">';
          wp_nonce_field('langa_tools_client_save_module_adminux');
          echo '<input type="hidden" name="module" value="adminux" />';
          echo '<input type="hidden" name="current_tab" value="replace" />';
          echo '<button type="submit" name="adminux[clear_captured_banners]" value="1" class="button button-small button-link-delete" onclick="return confirm(\'Clear all captured banners?\')">Clear all</button>';
          echo '</form>';
          echo '</div>';

          foreach ($captured as $banner) {
            $b_label    = esc_html($banner['label'] ?? 'Unknown banner');
            $b_selector = esc_html($banner['selector'] ?? '');
            $b_type     = esc_html($banner['type'] ?? '');
            $b_pages    = (int)($banner['page_count'] ?? 1);
            $b_first    = esc_html($banner['first_seen'] ?? '');

            echo '<div class="langa-card" style="margin-bottom:10px;padding:14px 18px">';
            echo '<strong>' . $b_label . '</strong>';
            echo ' <code style="font-size:11px;color:#86868b">' . $b_type . ': ' . $b_selector . '</code>';
            echo '<br><span class="description">Found on ' . $b_pages . ' page(s). First seen: ' . $b_first . '</span>';
            if (!empty($banner['html_preview'])) {
              echo '<details style="margin:8px 0 0"><summary style="cursor:pointer;font-size:12px;color:#6e6e73">Preview HTML</summary>';
              echo '<pre style="font-size:11px;background:#fafafa;padding:8px;border:1px solid #e5e5e7;border-radius:6px;margin:6px 0 0;max-height:200px;overflow:auto;white-space:pre-wrap;word-break:break-all">' . esc_html(mb_substr($banner['html_preview'], 0, 2000)) . '</pre>';
              echo '</details>';
            }
            echo '</div>';
          }
        }
        echo '</div>';
      }

      echo '</td></tr>';
    }
    if ($subtab === 'sitemap') {

      echo '<tr><th scope="row">Visual Sitemap (/sitemap)</th><td>';
      echo '<input type="hidden" name="adminux[visual_sitemap_enabled]" value="0">';
      echo '<label><input type="checkbox" name="adminux[visual_sitemap_enabled]" value="1" '.checked($vs_enabled,1,false).' /> Enabled</label>';
      $vs_open = esc_url(home_url('/sitemap/'));
      echo ' <a class="button button-secondary" style="margin-left:10px" href="'.$vs_open.'" target="_blank" rel="noopener">Open</a>';
      echo '</td></tr>';

      echo '<tr><th scope="row">Meta title</th><td>';
      echo '<input type="text" class="regular-text" name="adminux[visual_sitemap_title]" value="'.esc_attr($vs_title).'" />';
      echo '<p class="description" style="margin:6px 0 0;">Used as page title (SEO/meta). Not shown in the content.</p>';
      echo '</td></tr>';

      

      echo '<tr><th scope="row">Sort</th><td>';
      echo '<select name="adminux[visual_sitemap][sort_by]">';
      $opts = array('menu_order'=>'Menu order','title'=>'Title','date'=>'Date');
      foreach($opts as $k=>$lab){
        echo '<option value="'.esc_attr($k).'" '.selected($vs_sort_by,$k,false).'>'.esc_html($lab).'</option>';
      }
      echo '</select> &nbsp;';
      echo '<select name="adminux[visual_sitemap][sort_order]">';
        echo '<option value="asc" '.selected($vs_sort_order,'asc',false).'>Ascending</option>';
        echo '<option value="desc" '.selected($vs_sort_order,'desc',false).'>Descending</option>';
      echo '</select>';
      echo '</td></tr>';
echo '<tr><th scope="row">Style</th><td>';
      echo '<div class="langa-style-scope">';
      echo '<div style="display:grid; grid-template-columns: 180px 1fr; gap:10px; align-items:center; max-width:620px;">';
        echo '<div><strong>Background</strong></div><div><input class="langa-color-field" type="text" name="adminux[visual_sitemap][bg_color]" value="'.esc_attr($vs_bg).'" /></div>';
        echo '<div><strong>Text</strong></div><div><input class="langa-color-field" type="text" name="adminux[visual_sitemap][text_color]" value="'.esc_attr($vs_txt).'" /></div>';
        echo '<div><strong>Hover background</strong></div><div><input class="langa-color-field" type="text" name="adminux[visual_sitemap][hover_bg_color]" value="'.esc_attr($vs_hbg).'" /></div>';
        echo '<div><strong>Hover text</strong></div><div><input class="langa-color-field" type="text" name="adminux[visual_sitemap][hover_text_color]" value="'.esc_attr($vs_htxt).'" /></div>';
        echo '<div><strong>Lines</strong></div><div><input class="langa-color-field" type="text" name="adminux[visual_sitemap][line_color]" value="'.esc_attr($vs_line).'" /></div>';
        echo '<div><strong>Radius (px)</strong></div><div><input type="number" min="0" max="40" step="1" class="small-text" name="adminux[visual_sitemap][radius]" value="'.esc_attr((string)$vs_radius).'" /> <span class="description">Single value for box/link.</span></div>';
      echo '</div>';

      echo '<p style="margin:12px 0 0">';
      $vs_defaults = array(
        'bg_color' => '#f5f5f4',
        'text_color' => '#1c1917',
        'hover_bg_color' => '#e7e5e4',
        'hover_text_color' => '#1c1917',
        'line_color' => '#d6d3d1',
        'radius' => 5,
      );
      echo '<button type="button" class="button langa-reset-colors" data-style-scope="visual_sitemap" data-defaults="'.esc_attr(wp_json_encode($vs_defaults)).'">Reset style</button>';
      echo ' <span class="description">Reset to neutral palette (radius 5px).</span>';
      echo '</p>';
      echo '</div>';
      echo '</td></tr>';
    }

    echo '</table>';
    echo '</div>';
    echo '</td></tr>';
}

<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_handle_save_module() {
  if (!current_user_can('manage_options')) wp_die('Not allowed');

  // Accept both POST (module page save) and GET (modules-list link toggle)
  $module = isset($_REQUEST['module']) ? sanitize_key($_REQUEST['module']) : '';
  check_admin_referer('langa_tools_client_save_module_' . $module);

  // Lite WP.org: all modules are free, no license gate.
  $lic_ok = true;
  $dev_ok   = langa_tools_client_dev_bypass_active();
  // Lite WP.org: no license gate on module toggle — all modules are free.

  // Explicit toggle from Modules tab (GET link or POST form)
  if (isset($_REQUEST['new_active'])) {
    $enabled = (int) ((int) sanitize_text_field(wp_unslash($_REQUEST['new_active'])) ? 1 : 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked above
  } else {
    $enabled = isset($_POST['enabled'])
    ? (!empty($_POST['enabled']) ? 1 : 0)
    : (int) (function_exists('langa_tools_client_feature_is_config_enabled')
        ? langa_tools_client_feature_is_config_enabled($module)
        : langa_tools_client_feature_is_enabled($module));
  }


  // Enable/disable flag per modulo
  langa_tools_client_feature_set_enabled($module, $enabled);

  // Lite WP.org: no license re-check needed. → sets killswitch to 'valid' or 'blocked'.
  // CRITICAL: just deleting the transient causes frontend to fall back to
  // license_ok_cached(72h) which returns stale data. We must SET it, not clear it.
  // License validation removed from Lite WP.org build.

  // Clean up any legacy transient keys
  $site_key = (string) get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
  if ($site_key !== '') {
    $cache_key = 'langa_tools_license_' . substr(sha1($site_key), 0, 12);
    delete_transient($cache_key);
  }
  delete_transient('langa_tools_client_license_cache');
  delete_transient('langa_tools_client_license_check');
  delete_option('langa_tools_client_license_cache');
  delete_option('langa_tools_client_license_status');

  // Flush page caches so frontend reflects module toggle immediately
  if (function_exists('langa_credits_flush_page_caches')) {
    langa_credits_flush_page_caches();
  }

  // ─── MODULES-TAB TOGGLE (early exit) ────────────────────
  // When user clicks Enable/Disable from the Modules list, `new_active` is set.
  // We MUST exit here: the POST contains no module settings fields, so falling
  // through into module-specific save handlers would wipe tab settings.
  if (isset($_REQUEST['new_active'])) {
    // Stay on the same page the user was on
    $referer = wp_get_referer();
    if ($referer) {
      $back = add_query_arg('saved', '1', remove_query_arg(array('saved', 'module_enabled', 'reset'), $referer));
    } else {
      $back = admin_url('admin.php?page=langa-tools-client-settings&tab=general&saved=1#langa-modules');
    }
    wp_safe_redirect($back);
    exit;
  }

  // -------------------------
  // SAVE: ADMINUX
  // - SAFE: salva per TAB e non resetta le altre
  // -------------------------
  if ($module === 'adminux') {
    $raw  = isset($_POST['adminux']) && is_array($_POST['adminux']) ? wp_unslash($_POST['adminux']) : array();
    $prev = get_option('langa_tools_adminux_settings', array());
    if (!is_array($prev)) $prev = array();

    $out = $prev;
    $current_tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'general';
    if ($current_tab === '') $current_tab = 'general';

    // GENERAL
    if ($current_tab === 'general') {
      $out['wpui_improvements'] = !empty($raw['wpui_improvements']) ? 1 : 0;
      $out['custom_login']      = !empty($raw['custom_login']) ? 1 : 0;
      $out['custom_login_color'] = !empty($raw['custom_login_color']) ? sanitize_hex_color($raw['custom_login_color']) : '#f37f0d';
      $out['custom_login_logo']  = !empty($raw['custom_login_logo']) ? esc_url_raw(trim($raw['custom_login_logo'])) : '';
      $out['credits_enabled']   = !empty($raw['credits_enabled']) ? 1 : 0;
      $out['credits_recipient'] = !empty($raw['credits_recipient']) ? sanitize_email(trim($raw['credits_recipient'])) : '';
      $out['ghost_pages']      = !empty($raw['ghost_pages']) ? 1 : 0;

      // Credits services → save to site_data (developer section)
      if (isset($raw['credits_services'])) {
        $svc_raw = sanitize_textarea_field($raw['credits_services']);
        $svc_lines = array_filter(array_map('trim', explode("\n", $svc_raw)), 'strlen');
        $sd = get_option('langa_tools_client_site_data', array());
        if (!is_array($sd)) $sd = array();
        if (!isset($sd['developer']) || !is_array($sd['developer'])) $sd['developer'] = array();
        $sd['developer']['credits_services'] = implode("\n", $svc_lines);
        update_option('langa_tools_client_site_data', $sd, false);
      }
    }

    // MAINTENANCE
    if ($current_tab === 'maintenance') {
      $out['maintenance']       = !empty($raw['maintenance']) ? 1 : 0;

      // Bypass roles (array of role slugs)
      $bypass_raw = isset($raw['maintenance_bypass_roles']) && is_array($raw['maintenance_bypass_roles']) ? $raw['maintenance_bypass_roles'] : array();
      $bypass_clean = array();
      foreach ($bypass_raw as $br) {
        $br = sanitize_text_field(trim((string) $br));
        if ($br !== '' && $br !== 'administrator') {
          $bypass_clean[] = $br;
        }
      }
      $out['maintenance_bypass_roles'] = array_values(array_unique($bypass_clean));

      // Per-module recipient (multi-email with comma)
      $mrecip = isset($raw['maintenance_recipient']) ? sanitize_text_field(trim((string)$raw['maintenance_recipient'])) : '';
      $out['maintenance_recipient'] = $mrecip;

      $ms_raw  = isset($raw['maintenance_style']) && is_array($raw['maintenance_style']) ? $raw['maintenance_style'] : array();
      $ms_prev = isset($prev['maintenance_style']) && is_array($prev['maintenance_style']) ? $prev['maintenance_style'] : array();
      $ms_out  = $ms_prev;

      $ms_out['primary_color'] = sanitize_hex_color($ms_raw['primary_color'] ?? ($ms_prev['primary_color'] ?? '#a8a29e'));
      $ms_out['header_bg']     = sanitize_hex_color($ms_raw['header_bg'] ?? ($ms_prev['header_bg'] ?? '#fafaf9'));
      $ms_out['header_text']   = sanitize_hex_color($ms_raw['header_text'] ?? ($ms_prev['header_text'] ?? '#1c1917'));
      $ms_out['body_bg']       = sanitize_hex_color($ms_raw['body_bg'] ?? ($ms_prev['body_bg'] ?? '#f5f5f4'));
      $ms_out['form_bg']       = sanitize_hex_color($ms_raw['form_bg'] ?? ($ms_prev['form_bg'] ?? '#ffffff'));
      $ms_out['text_color']    = sanitize_hex_color($ms_raw['text_color'] ?? ($ms_prev['text_color'] ?? '#1c1917'));

      $r = isset($ms_raw['radius']) ? (int)$ms_raw['radius'] : (int)($ms_prev['radius'] ?? 5);
      if ($r < 0) $r = 0;
      if ($r > 40) $r = 40;
      $ms_out['radius'] = $r;

      // Custom CSS removed from Lite WP.org build.

      // Remove legacy card_bg if present
      if (isset($ms_out['card_bg'])) unset($ms_out['card_bg']);

      $out['maintenance_style'] = $ms_out;
    }

    // PRELOADER (frontend)
    if ($current_tab === 'preloader') {
      $p_raw  = isset($raw['preloader']) && is_array($raw['preloader']) ? $raw['preloader'] : array();
      $p_prev = isset($prev['preloader']) && is_array($prev['preloader']) ? $prev['preloader'] : array();

      $p = $p_prev;
      $p['enabled'] = !empty($p_raw['enabled']) ? 1 : 0; 

      $p['bg_color'] = sanitize_hex_color($p_raw['bg_color'] ?? ($p_prev['bg_color'] ?? '#0b0b0c'));
      if ($p['bg_color'] === '') $p['bg_color'] = '#0b0b0c';

      $op_raw = isset($p_raw['bg_opacity']) ? sanitize_text_field((string)$p_raw['bg_opacity']) : (string)($p_prev['bg_opacity'] ?? '0.96');
      $op_raw = str_replace(',', '.', $op_raw);
      $op = (float)$op_raw;
      if ($op < 0) $op = 0;
      if ($op > 1) $op = 1;
      $p['bg_opacity'] = $op;

      $p['logo_url'] = esc_url_raw((string)($p_raw['logo_url'] ?? ($p_prev['logo_url'] ?? '')));

      $w = isset($p_raw['logo_width']) ? (int)$p_raw['logo_width'] : (int)($p_prev['logo_width'] ?? 84);
      if ($w < 24) $w = 24;
      if ($w > 260) $w = 260;
      $p['logo_width'] = $w; 

      $td_raw = isset($p_raw['transition_ms']) ? sanitize_text_field((string)$p_raw['transition_ms']) : (string)($p_prev['transition_ms'] ?? '520');
      $td_raw = preg_replace('/[^0-9]/', '', $td_raw);
      $td = (int)$td_raw;
      if ($td < 0) $td = 0;
      if ($td > 60000) $td = 60000;
      $p['transition_ms'] = $td;

      $p['first_visit_session'] = !empty($p_raw['first_visit_session']) ? 1 : 0;

      $ex = (string)($p_raw['exclude_pages'] ?? ($p_prev['exclude_pages'] ?? ''));
      $ex = sanitize_textarea_field($ex);
      if (strlen($ex) > 4000) $ex = substr($ex, 0, 4000);
      $p['exclude_pages'] = trim($ex);

      $out['preloader'] = $p;
    }

    // REPLACE (Media replace + DB search/replace + Promo Banner Isolation)
    if ($current_tab === 'replace') {
      $out['media_replace_keep_backup'] = !empty($raw['media_replace_keep_backup']) ? 1 : 0;

      // These tools do not require enable toggles. Remove legacy keys if present.
      if (isset($out['media_replace_enabled'])) unset($out['media_replace_enabled']);
      if (isset($out['text_replace_enabled'])) unset($out['text_replace_enabled']);
      if (isset($out['hide_notices'])) unset($out['hide_notices']);
      if (isset($out['promo_keywords'])) unset($out['promo_keywords']);
      if (isset($out['promo_selectors'])) unset($out['promo_selectors']);

      // Promo Banner Isolation rules
      $promo_raw = isset($raw['promo_isolation_rules']) && is_array($raw['promo_isolation_rules']) ? $raw['promo_isolation_rules'] : array();
      $promo_clean = array();
      foreach ($promo_raw as $pr) {
        if (!is_array($pr)) continue;
        $p_type = sanitize_key($pr['type'] ?? 'class');
        if (!in_array($p_type, array('class', 'id', 'data-nonce'), true)) $p_type = 'class';
        $p_selector = sanitize_text_field(trim($pr['selector'] ?? ''));
        if ($p_selector === '') continue;
        $promo_clean[] = array(
          'active'   => !empty($pr['active']) ? 1 : 0,
          'type'     => $p_type,
          'selector' => $p_selector,
          'label'    => sanitize_text_field($pr['label'] ?? ''),
        );
      }
      $out['promo_isolation_rules'] = $promo_clean;

      // Clear captured banners if requested
      if (!empty($raw['clear_captured_banners'])) {
        delete_option('langa_tools_promo_captured_banners');
      }

      // Tool: Media Replace (runs inside the same Save action)
      if (!empty($_POST['replace_media_do'])) {
        $aid = isset($_POST['replace_media_attachment_id']) ? absint(wp_unslash($_POST['replace_media_attachment_id'])) : 0;
        $keep = !empty($out['media_replace_keep_backup']);
        if (function_exists('langa_tools_client_media_replace_attachment')) {
          $res = langa_tools_client_media_replace_attachment($aid, 'replace_media_file', $keep);
          if (function_exists('langa_tools_client_replace_set_notice')) {
            langa_tools_client_replace_set_notice(!empty($res['ok']) ? 'success' : 'error', (string)($res['msg'] ?? ''));
          }
        }
      }

      // Tool: Search & Replace (serialized-safe)
      if (!empty($_POST['replace_text_do'])) {
        $search  = isset($_POST['replace_search']) ? wp_strip_all_tags( (string) wp_unslash($_POST['replace_search']) ) : '';
        $repl    = isset($_POST['replace_replace']) ? wp_strip_all_tags( (string) wp_unslash($_POST['replace_replace']) ) : '';

        // Normalize + trim to avoid invisible whitespace/newlines breaking URL matches.
        $search = str_replace(array("\r\n", "\r"), "\n", $search);
        $repl   = str_replace(array("\r\n", "\r"), "\n", $repl);
        $search = trim($search);
        $repl   = trim($repl);
        $dry     = !empty($_POST['replace_dry_run']) ? 1 : 0;
        $guids   = !empty($_POST['replace_include_guids']) ? 1 : 0;
        $maxrows = isset($_POST['replace_max_rows']) ? absint(wp_unslash($_POST['replace_max_rows'])) : 0;
        if ($maxrows < 0) $maxrows = 0;

        $tables = isset($_POST['replace_tables']) && is_array($_POST['replace_tables']) ? wp_unslash($_POST['replace_tables']) : array();
        $tables_clean = array();
        foreach ($tables as $t) {
          $t = preg_replace('/[^A-Za-z0-9_\$]/', '', (string)$t);
          if ($t !== '') $tables_clean[] = $t;
        }

        // Safety: real replace requires explicit acknowledge + token.
        if (!$dry) {
          $ack = !empty($_POST['replace_ack']);
          $token = isset($_POST['replace_confirm']) ? sanitize_text_field( trim( (string) wp_unslash($_POST['replace_confirm']) ) ) : '';
          if (!$ack || $token !== 'REPLACE') {
            if (function_exists('langa_tools_client_replace_set_notice')) {
              langa_tools_client_replace_set_notice('error', 'To execute a real replace: check “I understand” and type REPLACE in the confirmation field.');
            }
            // Force dry run when safety not satisfied
            $dry = 1;
          }
        }

        if (function_exists('langa_tools_client_search_replace_run')) {
          $res = langa_tools_client_search_replace_run($search, $repl, $tables_clean, $dry ? true : false, $guids ? true : false, $maxrows);
          if (!empty($res['ok']) && !empty($res['report']) && is_array($res['report'])) {
            update_option('langa_tools_adminux_replace_report', $res['report'], false);
            $msg = $dry ? 'Dry-run completed: no data was modified.' : 'Replace completed: changes applied to database.';
            if (function_exists('langa_tools_client_replace_set_notice')) {
              langa_tools_client_replace_set_notice('success', $msg);
            }
          } else {
            if (function_exists('langa_tools_client_replace_set_notice')) {
              langa_tools_client_replace_set_notice('error', (string)($res['msg'] ?? 'Error during replace.'));
            }
          }
        }
      }
    }

    // USERS (User switching + LANGA editor profiles + granular custom)
    if ($current_tab === 'users') {
      $out['user_switching'] = !empty($raw['user_switching']) ? 1 : 0;

      $clean = function($ids) {
        $out_ids = array();
        if (!is_array($ids)) return $out_ids;
        foreach ($ids as $id) {
          $id = (int) $id;
          if ($id > 0) $out_ids[] = $id;
        }
        return array_values(array_unique($out_ids));
      };

      // Preserve existing lists if the legacy multiselects are not posted
      $u1 = (isset($raw['langa_editor_1_users']) && is_array($raw['langa_editor_1_users'])) ? $raw['langa_editor_1_users'] : ($prev['langa_editor_1_users'] ?? array());
      $u2 = (isset($raw['langa_editor_2_users']) && is_array($raw['langa_editor_2_users'])) ? $raw['langa_editor_2_users'] : ($prev['langa_editor_2_users'] ?? array());
      $u3 = (isset($raw['langa_editor_3_users']) && is_array($raw['langa_editor_3_users'])) ? $raw['langa_editor_3_users'] : ($prev['langa_editor_3_users'] ?? array());

      $u1 = $clean($u1);
      $u2 = $clean($u2);
      $u3 = $clean($u3);

      $custom_prev = isset($prev['langa_custom_users']) && is_array($prev['langa_custom_users']) ? $prev['langa_custom_users'] : array();
      $custom = is_array($custom_prev) ? $custom_prev : array();

      // Apply UI (granular)
      $action = isset($raw['users_action']) ? sanitize_key((string)$raw['users_action']) : '';
      $apply_users = isset($raw['users_apply_users']) && is_array($raw['users_apply_users']) ? $clean($raw['users_apply_users']) : array();
      $profile = isset($raw['users_apply_profile']) ? sanitize_key((string)$raw['users_apply_profile']) : '';
      if ($profile === '') $profile = 'editor1';

      $areas_raw = isset($raw['users_apply_areas']) && is_array($raw['users_apply_areas']) ? $raw['users_apply_areas'] : array();

      $extra_pts_raw = isset($raw['users_apply_extra_pts']) && is_array($raw['users_apply_extra_pts']) ? $raw['users_apply_extra_pts'] : array();
      $extra_pts = array();
      if (is_array($extra_pts_raw)) {
        foreach ($extra_pts_raw as $ptx) {
          $ptx = sanitize_key((string)$ptx);
          if ($ptx === '' || $ptx === 'attachment') continue;
          if (in_array($ptx, array('post','page','product'), true)) continue;
          if (post_type_exists($ptx)) $extra_pts[] = $ptx;
        }
      }

      // Extra plugin/admin pages (top-level admin menu) for Custom profiles.
      // Each value is encoded as rawurlencode(slug) . '|' . rawurlencode(cap).
      $menu_pages_raw = isset($raw['users_apply_menu_pages']) && is_array($raw['users_apply_menu_pages']) ? $raw['users_apply_menu_pages'] : array();
      $menu_pages = array();
      if (is_array($menu_pages_raw)) {
        foreach ($menu_pages_raw as $v) {
          $v = (string)$v;
          if ($v === '' || strpos($v, '|') === false) continue;
          $parts = explode('|', $v, 2);
          $slug = rawurldecode($parts[0]);
          $cap  = rawurldecode($parts[1]);
          $slug = trim($slug);
          $cap  = trim($cap);
          if ($slug === '' || $cap === '') continue;
          // Avoid allowing this plugin's own admin pages via staff profiles.
          if (strpos($slug, 'langa-tools-client') !== false) continue;
          $menu_pages[] = array(
            'slug' => $slug,
            'cap'  => $cap,
          );
        }
      }

      $areas = array(
        'posts'     => !empty($areas_raw['posts']) ? 1 : 0,
        'products'  => !empty($areas_raw['products']) ? 1 : 0,
        'pages'     => !empty($areas_raw['pages']) ? 1 : 0,
        'media'     => !empty($areas_raw['media']) ? 1 : 0,
        // WooCommerce Orders (Custom only)
        'wc_orders' => !empty($areas_raw['wc_orders']) ? 1 : 0,
        // Advanced system screens (Custom only)
        'wp_tools'  => !empty($areas_raw['wp_tools']) ? 1 : 0,
        'comments'  => !empty($areas_raw['comments']) ? 1 : 0,
        'extra_pts' => $extra_pts,
        'menu_pages'=> $menu_pages,
      );

      // Store last used values for UI convenience
      $out['users_apply_profile'] = $profile;
      $out['users_apply_areas'] = $areas;
      $out['users_apply_menu_pages'] = $menu_pages;

      // Auto-apply when users are provided (even if Save Module is clicked instead of the internal button)
      if (!empty($apply_users) && ($action === '' || $action === 'apply')) {
        foreach ($apply_users as $uid) {
          $uid = (int) $uid;
          if ($uid <= 0) continue;

          // Remove from all groups first
          $u1 = array_values(array_diff($u1, array($uid)));
          $u2 = array_values(array_diff($u2, array($uid)));
          $u3 = array_values(array_diff($u3, array($uid)));
          if (isset($custom[$uid])) unset($custom[$uid]);

          if ($profile === 'editor1') {
            $u1[] = $uid;
          } elseif ($profile === 'editor2') {
            $u2[] = $uid;
          } elseif ($profile === 'editor3') {
            $u3[] = $uid;
          } else {
            // Custom
            $custom[$uid] = array(
              'areas' => $areas,
            );
          }
        }
      }

      // Remove selected users from management (set to subscriber)
      if ($action === 'remove') {
        $remove_users = isset($raw['users_remove']) && is_array($raw['users_remove']) ? $clean($raw['users_remove']) : array();
        foreach ($remove_users as $uid) {
          $uid = (int) $uid;
          if ($uid <= 0) continue;
          $u1 = array_values(array_diff($u1, array($uid)));
          $u2 = array_values(array_diff($u2, array($uid)));
          $u3 = array_values(array_diff($u3, array($uid)));
          if (isset($custom[$uid])) unset($custom[$uid]);
        }
      }

      $out['langa_editor_1_users'] = $clean($u1);
      $out['langa_editor_2_users'] = $clean($u2);
      $out['langa_editor_3_users'] = $clean($u3);
      $out['langa_custom_users'] = $custom;
    }

    // EFFECTS (part of UI/UX)
    if ($current_tab === 'effects') {
      // Rows
      $rows = array();
      if (!empty($_POST['effects']) && is_array($_POST['effects'])) {
        $effects_raw = wp_unslash( $_POST['effects'] );
        foreach ($effects_raw as $row) {
          if (!is_array($row)) continue;
          $rows[] = array(
            'effect'   => sanitize_key($row['effect'] ?? ''),
            'start_md' => sanitize_text_field($row['start_md'] ?? ''),
            'end_md'   => sanitize_text_field($row['end_md'] ?? ''),
            'before'   => (int)($row['before'] ?? 0),
            'after'    => (int)($row['after'] ?? 0),
          );
        }
      }

      $custom = array();

      if (!function_exists('langa_tools_client_get_effects_option')) {
        require_once LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/effects/options.php';
      }
      $opt = langa_tools_client_get_effects_option();
      $opt['enabled'] = !empty($raw['effects_enabled']) ? 1 : 0;
      $opt['rows']    = $rows;
      $opt['custom']  = $custom;
      if (defined('LANGA_TOOLS_OPTION_EFFECTS')) {
        update_option(LANGA_TOOLS_OPTION_EFFECTS, $opt);
      } else {
        update_option('langa_tools_effects', $opt);
      }
    }

    // VISUAL SITEMAP
    if ($current_tab === 'sitemap') {
      $out['visual_sitemap_enabled'] = !empty($raw['visual_sitemap_enabled']) ? 1 : 0;
      $out['visual_sitemap_title']   = sanitize_text_field($raw['visual_sitemap_title'] ?? ($prev['visual_sitemap_title'] ?? 'Sitemap'));

      $vs_raw  = isset($raw['visual_sitemap']) && is_array($raw['visual_sitemap']) ? $raw['visual_sitemap'] : array();
      $vs_prev = isset($prev['visual_sitemap']) && is_array($prev['visual_sitemap']) ? $prev['visual_sitemap'] : array();
      $vs_out  = $vs_prev;

      $vs_out['bg_color']         = sanitize_hex_color($vs_raw['bg_color'] ?? ($vs_prev['bg_color'] ?? '#f1f5f9'));
      $vs_out['text_color']       = sanitize_hex_color($vs_raw['text_color'] ?? ($vs_prev['text_color'] ?? '#0f172a'));
      $vs_out['hover_bg_color']   = sanitize_hex_color($vs_raw['hover_bg_color'] ?? ($vs_prev['hover_bg_color'] ?? '#e2e8f0'));
      $vs_out['hover_text_color'] = sanitize_hex_color($vs_raw['hover_text_color'] ?? ($vs_prev['hover_text_color'] ?? '#0f172a'));
      $vs_out['line_color']       = sanitize_hex_color($vs_raw['line_color'] ?? ($vs_prev['line_color'] ?? '#cbd5e1'));

      // Radius (single value)
      $vs_out['radius'] = isset($vs_raw['radius']) ? max(0, min(40, (int)$vs_raw['radius'])) : (int)($vs_prev['radius'] ?? 5);

      // Custom CSS removed from Lite WP.org build.

$sort_by = sanitize_key($vs_raw['sort_by'] ?? ($vs_prev['sort_by'] ?? 'menu_order'));
      if (!in_array($sort_by, array('menu_order','title','date'), true)) $sort_by = 'menu_order';
      $vs_out['sort_by'] = $sort_by;

      $sort_order = sanitize_key($vs_raw['sort_order'] ?? ($vs_prev['sort_order'] ?? 'asc'));
      if (!in_array($sort_order, array('asc','desc'), true)) $sort_order = 'asc';
      $vs_out['sort_order'] = $sort_order;

      // Old toggles removed
      unset($vs_out['show_posts_under_blog'], $vs_out['show_products_under_shop']);

      $out['visual_sitemap'] = $vs_out;
    }

    update_option('langa_tools_adminux_settings', $out);

    // Allowed mimes (saved alongside adminux)
    $raw_mimes = isset($_POST['allowed_mimes']) && is_array($_POST['allowed_mimes']) ? array_map('sanitize_text_field', wp_unslash($_POST['allowed_mimes'])) : array();
    $clean_mimes = array();
    $allowed_keys = array('svg','webp','mp4','webm','mov','mp3','ogg','wav','flac','ico','ai','eps','psd','dwg','dxf','zip','csv','json','woff','woff2','otf','ttf');
    foreach ($allowed_keys as $k) {
      if (!empty($raw_mimes[$k])) $clean_mimes[$k] = 1;
    }
    update_option('langa_tools_client_allowed_mimes', $clean_mimes);
  }



  // -------------------------
  // SAVE: AI
  // -------------------------


  
  // -------------------------
  // SAVE: FORMS (UI-first)
  // - SAFE: salva per TAB e non resetta le altre
  // -------------------------




// -------------------------
  // SAVE: CACHE (UI-only)
  // - salva per TAB e non resetta le altre
  // -------------------------
  // -------------------------
  // -------------------------
  // SAVE: LEGAL
  // -------------------------




  // SAVE: SEO
  // - salva per TAB (features/sitemap/titles/social/schema/advanced)
  // - NON resetta sezioni non presenti nel form (niente wipe)
  // -------------------------


// -------------------------
  // SAVE: BC (Business Card)
  // -------------------------
// -------------------------
  // SAVE: EVENTS (ex-BRIDGE)
  // -------------------------



  // -------------------------
  // SAVE: POPUP
  // -------------------------

  // Redirect back (module-page saves — Modules-tab toggles already exited above)
  $tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : '';
  $mod_page = function_exists('langa_tools_client_page_slug')
    ? langa_tools_client_page_slug($module)
    : ('langa-tools-client-' . $module);
  $args = array('page' => $mod_page, 'saved' => '1');
  if ($tab !== '') $args['tab'] = $tab;

  wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
  exit;
}


/**
 * Reset module — deletes all settings for a specific module.
 */
function langa_tools_client_handle_reset_module() {
  if (!current_user_can('manage_options')) wp_die('Not allowed');

  $module = isset($_REQUEST['module']) ? sanitize_key($_REQUEST['module']) : '';
  if ($module === '') wp_die('No module specified');

  check_admin_referer('langa_tools_client_reset_module_' . $module);

  $option_map = array(
    'adminux'  => array(
      'options' => array('langa_tools_adminux_settings', 'langa_tools_adminux_replace_report', 'langa_tools_effects', 'langa_tools_client_allowed_mimes'),
      'transients' => array('langa_tools_adminux_replace_notice'),
    ),
    'safer'    => array(
      'options' => array('langa_tools_safer_settings', 'langa_tools_safer_slugs', 'langa_tools_safer_last_htaccess', 'langa_tools_safer_notice_ghost_needs_htaccess'),
      'transients' => array('langa_safer_circuit_breaker', 'langa_safer_htaccess_autoclean', 'langa_safer_module_disabled_clean'),
    ),
    'seo'      => array(
      'options' => array('langa_tools_seo_settings', 'langa_tools_seo_rewrites_flushed', 'langa_tools_vs_page_ids', 'langa_tools_indexnow_queue', 'langa_tools_indexnow_rewrites_flushed'),
      'transients' => array(),
    ),
    'cache'    => array(
      'options' => array('langa_tools_cache_settings'),
      'transients' => array(),
    ),
    'legal'    => array(
      'options' => array('langa_tools_legal_settings'),
      'transients' => array(),
    ),
    'forms'    => array(
      'options' => array('langa_tools_forms_settings'),
      'transients' => array('langa_tools_client_mail_last_error', 'langa_tools_mail_test_results'),
    ),
    'bc'       => array(
      'options' => array('langa_tools_bc_settings', 'langa_tools_bc_flush', 'langa_tools_users_profiles_hash'),
      'transients' => array('langa_tools_users_profiles_last_sync'),
    ),
    'popup'    => array(
      'options' => array('langa_tools_popup_settings'),
      'transients' => array(),
    ),
    'bridge'   => array(
      'options' => array('langa_tools_bridge_settings', 'langa_tools_bridge_enabled'),
      'transients' => array('langa_bridge_batch_retry'),
    ),
    'ai'       => array(
      'options' => array('langa_tools_ai_settings'),
      'transients' => array(),
    ),
  );

  if (isset($option_map[$module])) {
    foreach ($option_map[$module]['options'] as $opt_name) {
      delete_option($opt_name);
    }
    foreach ($option_map[$module]['transients'] as $tr_name) {
      delete_transient($tr_name);
    }
  }

  if (function_exists('langa_tools_client_feature_set_enabled')) {
    langa_tools_client_feature_set_enabled($module, 0);
  }

  if ($module === 'safer' && function_exists('langa_tools_client_safer_rollback_root_htaccess')) {
    langa_tools_client_safer_rollback_root_htaccess();
  }

  if (in_array($module, array('seo', 'safer', 'bc'), true)) {
    flush_rewrite_rules(false);
  }

  if (function_exists('langa_credits_flush_page_caches')) {
    langa_credits_flush_page_caches();
  }

  $referer = wp_get_referer();
  if ($referer) {
    $back = add_query_arg('reset', '1', remove_query_arg(array('saved', 'module_enabled', 'reset'), $referer));
  } else {
    $page_slug = function_exists('langa_tools_client_page_slug') ? langa_tools_client_page_slug($module) : 'langa-tools-client';
    $back = add_query_arg(array('page' => $page_slug, 'reset' => '1'), admin_url('admin.php'));
  }
  wp_safe_redirect($back);
  exit;
}

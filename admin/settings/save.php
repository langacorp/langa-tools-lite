<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_handle_save_module() {
  if (!current_user_can('manage_options')) wp_die('Not allowed');

  // Accept both POST (module page save) and GET (modules-list link toggle)
  $module = isset($_REQUEST['module']) ? sanitize_key($_REQUEST['module']) : '';
  check_admin_referer('langa_tools_client_save_module_' . $module);

  // ── License gate: block toggle if no license and no dev bypass ──
  // Free modules (bridge/Events) can always be toggled
  $lic_ok   = function_exists('langa_tools_client_license_is_valid') && langa_tools_client_license_is_valid();
  $dev_ok   = langa_tools_client_dev_bypass_active();
  $reg      = function_exists('langa_tools_client_features_registry') ? langa_tools_client_features_registry() : array();
  $is_free  = isset($reg[$module]['free']) && $reg[$module]['free'];
  if (!$lic_ok && !$dev_ok && !$is_free && isset($_REQUEST['new_active'])) {
    wp_safe_redirect(admin_url('admin.php?page=langa-tools-client-settings&tab=general&license_required=1#langa-modules'));
    exit;
  }

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

  // Force immediate license re-check → sets killswitch to 'valid' or 'blocked'.
  // CRITICAL: just deleting the transient causes frontend to fall back to
  // license_ok_cached(72h) which returns stale data. We must SET it, not clear it.
  if (function_exists('langa_tools_client_license_is_valid')) {
    langa_tools_client_license_is_valid(true);
  }

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

      // Custom CSS (Maintenance only)
      $css = isset($ms_raw['custom_css']) ? (string) $ms_raw['custom_css'] : (string) ($ms_prev['custom_css'] ?? '');
      $css = wp_strip_all_tags( wp_unslash($css) );
      $css = str_replace(array("\0"), '', $css);
      if (strlen($css) > 12000) $css = substr($css, 0, 12000);
      $ms_out['custom_css'] = trim($css);

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

      $op_raw = isset($p_raw['bg_opacity']) ? (string)$p_raw['bg_opacity'] : (string)($p_prev['bg_opacity'] ?? '0.96');
      $op_raw = str_replace(',', '.', wp_unslash($op_raw));
      $op = (float)$op_raw;
      if ($op < 0) $op = 0;
      if ($op > 1) $op = 1;
      $p['bg_opacity'] = $op;

      $p['logo_url'] = esc_url_raw((string)($p_raw['logo_url'] ?? ($p_prev['logo_url'] ?? '')));

      $w = isset($p_raw['logo_width']) ? (int)$p_raw['logo_width'] : (int)($p_prev['logo_width'] ?? 84);
      if ($w < 24) $w = 24;
      if ($w > 260) $w = 260;
      $p['logo_width'] = $w; 

      $td_raw = isset($p_raw['transition_ms']) ? (string)$p_raw['transition_ms'] : (string)($p_prev['transition_ms'] ?? '520');
      $td_raw = preg_replace('/[^0-9]/', '', wp_unslash($td_raw));
      $td = (int)$td_raw;
      if ($td < 0) $td = 0;
      if ($td > 60000) $td = 60000;
      $p['transition_ms'] = $td;

      $p['first_visit_session'] = !empty($p_raw['first_visit_session']) ? 1 : 0;

      $ex = (string)($p_raw['exclude_pages'] ?? ($p_prev['exclude_pages'] ?? ''));
      $ex = wp_unslash($ex);
      $ex = str_replace("\0", '', $ex);
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

      // Custom
      $custom = array('start_md' => '', 'end_md' => '', 'css' => '', 'js' => '');
      if (!empty($_POST['effects_custom']) && is_array($_POST['effects_custom'])) {
        $c = wp_unslash($_POST['effects_custom']);
        if (is_array($c)) {
          $custom['start_md'] = sanitize_text_field($c['start_md'] ?? '');
          $custom['end_md']   = sanitize_text_field($c['end_md'] ?? '');
          $custom['css']      = wp_strip_all_tags( str_replace("\0", '', (string)($c['css'] ?? '')) );
          $custom['js']       = wp_strip_all_tags( str_replace("\0", '', (string)($c['js'] ?? '')) );
        }
      }

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

      // Custom CSS (scoped to Visual Sitemap)
      $css = isset($vs_raw['custom_css']) ? (string)$vs_raw['custom_css'] : (string)($vs_prev['custom_css'] ?? '');
      $css = wp_strip_all_tags( str_replace("\0", '', $css) );
      if (strlen($css) > 20000) $css = substr($css, 0, 20000);
      $vs_out['custom_css'] = $css;

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
  if ($module === 'ai') {
    $raw  = isset($_POST['ai']) && is_array($_POST['ai']) ? wp_unslash($_POST['ai']) : array();
    $prev = get_option('langa_tools_ai_settings', array());
    if (!is_array($prev)) $prev = array();

    $out = $prev;

    $out['default_text_provider']  = sanitize_key($raw['default_text_provider'] ?? ($prev['default_text_provider'] ?? 'openai'));
    $out['default_image_provider'] = sanitize_key($raw['default_image_provider'] ?? ($prev['default_image_provider'] ?? 'openai'));
    $out['default_language']       = sanitize_text_field($raw['default_language'] ?? ($prev['default_language'] ?? 'it'));
    $out['default_tone']           = sanitize_text_field($raw['default_tone'] ?? ($prev['default_tone'] ?? 'professional'));

    $out['features'] = array(
      'text_generation'   => !empty($raw['features']['text_generation']) ? 1 : 0,
      'auto_categorize'   => !empty($raw['features']['auto_categorize']) ? 1 : 0,
      'auto_internallink' => !empty($raw['features']['auto_internallink']) ? 1 : 0,
      'social_snippets'   => !empty($raw['features']['social_snippets']) ? 1 : 0,
      'image_generation'  => !empty($raw['features']['image_generation']) ? 1 : 0,
      'auto_seo'          => !empty($raw['features']['auto_seo']) ? 1 : 0,
      'auto_translate'    => !empty($raw['features']['auto_translate']) ? 1 : 0,
    );

    $openai_key = trim((string)($raw['openai_key'] ?? ''));
    if ($openai_key === 'REMOVE') {
      $out['openai_key'] = '';
    } elseif ($openai_key !== '') {
      $out['openai_key'] = sanitize_text_field($openai_key);
    }

    $anthropic_key = trim((string)($raw['anthropic_key'] ?? ''));
    if ($anthropic_key === 'REMOVE') {
      $out['anthropic_key'] = '';
    } elseif ($anthropic_key !== '') {
      $out['anthropic_key'] = sanitize_text_field($anthropic_key);
    }

    $google_key = trim((string)($raw['google_key'] ?? ''));
    if ($google_key === 'REMOVE') {
      $out['google_key'] = '';
    } elseif ($google_key !== '') {
      $out['google_key'] = sanitize_text_field($google_key);
    }

    if (isset($raw['openai_model'])) {
      $out['openai_model'] = sanitize_text_field((string)$raw['openai_model']);
    }

    $out['review_required'] = !empty($raw['review_required']) ? 1 : 0;

    update_option('langa_tools_ai_settings', $out);
  }


  
  // -------------------------
  // SAVE: FORMS (UI-first)
  // - SAFE: salva per TAB e non resetta le altre
  // -------------------------
  if ($module === 'forms') {
    $raw  = isset($_POST['forms']) && is_array($_POST['forms']) ? wp_unslash($_POST['forms']) : array();
    $prev = get_option('langa_tools_forms_settings', array());
    if (!is_array($prev)) $prev = array();

    $out = $prev;

    $tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'overview';
    if ($tab === '') $tab = 'overview';

    // Ensure structure
    if (!isset($out['presets']) || !is_array($out['presets'])) $out['presets'] = array();
    for ($i = 1; $i <= 10; $i++) {
      $k = (string)$i;
      if (!isset($out['presets'][$k]) || !is_array($out['presets'][$k])) $out['presets'][$k] = array();
      // Back-compat: old title
      if (isset($out['presets'][$k]['title']) && (string)$out['presets'][$k]['title'] !== '' && empty($out['presets'][$k]['title_custom'])) {
        $out['presets'][$k]['title_custom'] = sanitize_text_field((string)$out['presets'][$k]['title']);
      }
      if (isset($out['presets'][$k]['title'])) unset($out['presets'][$k]['title']);

      if (!isset($out['presets'][$k]['title_key'])) $out['presets'][$k]['title_key'] = '';
      if (!isset($out['presets'][$k]['title_custom'])) $out['presets'][$k]['title_custom'] = '';
      if (!isset($out['presets'][$k]['disclaimer_html'])) $out['presets'][$k]['disclaimer_html'] = '';

      // Buttons are fixed in runtime
      if (isset($out['presets'][$k]['button'])) unset($out['presets'][$k]['button']);
      if (isset($out['presets'][$k]['fields_json'])) unset($out['presets'][$k]['fields_json']);
    }

    if (!isset($out['options']) || !is_array($out['options'])) $out['options'] = array();
    $opt_keys = array('company_type','sector','micro_sector','product_interest','already_have');
    foreach ($opt_keys as $ok) {
      if (!isset($out['options'][$ok])) $out['options'][$ok] = '';
    }

    if (!isset($out['style']) || !is_array($out['style'])) $out['style'] = array();
    if (!isset($out['extra']) || !is_array($out['extra'])) $out['extra'] = array();

    // OVERVIEW
    if ($tab === 'overview') {
      $out['enabled'] = !empty($raw['enabled']) ? 1 : 0;
      // Per-form recipient (multi-email with comma)
      $recip = isset($raw['recipient']) ? sanitize_text_field(trim((string)$raw['recipient'])) : '';
      $out['recipient'] = $recip;
    }

    // PRESETS (title key + override + disclaimer)
    if ($tab === 'presets') {
      $prs = isset($raw['presets']) && is_array($raw['presets']) ? $raw['presets'] : array();
      $allowed_keys = array('contact_us','quick_contact','request_info','fast_request','company_request','survey_request','quote_request');
      for ($i = 1; $i <= 10; $i++) {
        $k = (string)$i;
        $row = isset($prs[$k]) && is_array($prs[$k]) ? $prs[$k] : array();
        $tkey = sanitize_key((string)($row['title_key'] ?? ($out['presets'][$k]['title_key'] ?? '')));
        if (!in_array($tkey, $allowed_keys, true)) $tkey = 'contact_us';
        $out['presets'][$k]['title_key'] = $tkey;

        $out['presets'][$k]['title_custom'] = sanitize_text_field((string)($row['title_custom'] ?? ($out['presets'][$k]['title_custom'] ?? '')));

        $disc = isset($row['disclaimer_html']) ? str_replace("\r", '', (string)$row['disclaimer_html']) : (string)($out['presets'][$k]['disclaimer_html'] ?? '');
        $out['presets'][$k]['disclaimer_html'] = wp_kses_post($disc);
      }

      // Back-compat: move old global disclaimer into presets (first save)
      if (isset($out['extra']) && is_array($out['extra']) && !empty($out['extra']['disclaimer_html'])) {
        $g = (string)$out['extra']['disclaimer_html'];
        $all_empty = true;
        for ($i=1;$i<=10;$i++) {
          $kk=(string)$i;
          if (!empty($out['presets'][$kk]['disclaimer_html'])) { $all_empty = false; break; }
        }
        if ($all_empty) {
          for ($i=1;$i<=10;$i++) {
            $kk=(string)$i;
            $out['presets'][$kk]['disclaimer_html'] = wp_kses_post((string)$g);
          }
        }
        unset($out['extra']['disclaimer_html']);
      }
    }

    // SELECT OPTIONS
    if ($tab === 'options') {
      $opts = isset($raw['options']) && is_array($raw['options']) ? $raw['options'] : array();
      foreach ($opt_keys as $ok) {
        $val = isset($opts[$ok]) ? (string)$opts[$ok] : (string)($out['options'][$ok] ?? '');
        $val = str_replace("\r", '', $val);
        $out['options'][$ok] = sanitize_textarea_field($val);
      }
    }

    // STYLE
    if ($tab === 'style') {
      $style = isset($raw['style']) && is_array($raw['style']) ? $raw['style'] : array();
      $primary = isset($style['primary_color']) ? sanitize_hex_color((string)$style['primary_color']) : ($out['style']['primary_color'] ?? '#a8a29e');
      if ($primary === '') $primary = '#a8a29e';

      $header_bg = isset($style['header_bg']) ? sanitize_hex_color((string)$style['header_bg']) : ($out['style']['header_bg'] ?? '#fafaf9');
      if ($header_bg === '') $header_bg = '#fafaf9';
      $header_text = isset($style['header_text']) ? sanitize_hex_color((string)$style['header_text']) : ($out['style']['header_text'] ?? '#1c1917');
      if ($header_text === '') $header_text = '#1c1917';
      $body_bg = isset($style['body_bg']) ? sanitize_hex_color((string)$style['body_bg']) : ($out['style']['body_bg'] ?? '#f5f5f4');
      if ($body_bg === '') $body_bg = '#f5f5f4';
      $form_bg = isset($style['form_bg']) ? sanitize_hex_color((string)$style['form_bg']) : ($out['style']['form_bg'] ?? '#ffffff');
      if ($form_bg === '') $form_bg = '#ffffff';
      $form_text = isset($style['form_text']) ? sanitize_hex_color((string)$style['form_text']) : ($out['style']['form_text'] ?? '#1c1917');
      if ($form_text === '') $form_text = '#1c1917';

      $radius = isset($style['radius']) ? (int)$style['radius'] : (int)($out['style']['radius'] ?? 5);
      if ($radius < 0) $radius = 0;
      if ($radius > 40) $radius = 40;
      $css = isset($style['custom_css']) ? wp_strip_all_tags(str_replace("\r", '', (string)$style['custom_css'])) : (string)($out['style']['custom_css'] ?? '');
      $out['style'] = array(
        'primary_color' => $primary,
        'header_bg'     => $header_bg,
        'header_text'   => $header_text,
        'body_bg'       => $body_bg,
        'form_bg'       => $form_bg,
        'form_text'     => $form_text,
        'radius'        => $radius,
        'custom_css'    => $css,
      );
    }

    // EXTRA
    if ($tab === 'extra') {
      $ex = isset($raw['extra']) && is_array($raw['extra']) ? $raw['extra'] : array();
      $out['extra']['phone_enabled'] = !empty($ex['phone_enabled']) ? 1 : 0;
      $out['extra']['phone_default_country'] = sanitize_text_field((string)($ex['phone_default_country'] ?? ($out['extra']['phone_default_country'] ?? 'IT')));
      $allowed = isset($ex['phone_allowed_countries']) ? (string)$ex['phone_allowed_countries'] : (string)($out['extra']['phone_allowed_countries'] ?? '');
      $allowed = strtoupper(preg_replace('/[^A-Z,\s]/', '', $allowed));
      $allowed = preg_replace('/\s+/', '', $allowed);
      $out['extra']['phone_allowed_countries'] = $allowed;
    }

    // Legacy clean: delivery/mail removed (now global Settings → Invio (Server))
    if (isset($out['delivery'])) unset($out['delivery']);
    if (isset($out['mail'])) unset($out['mail']);

    update_option('langa_tools_forms_settings', $out, false);
  }




// -------------------------
  // SAVE: CACHE (UI-only)
  // - salva per TAB e non resetta le altre
  // -------------------------
  if ($module === 'cache') {
    $raw  = isset($_POST['cache']) && is_array($_POST['cache']) ? wp_unslash($_POST['cache']) : array();
    $prev = get_option('langa_tools_cache_settings', array());
    if (!is_array($prev)) $prev = array();
    $out = $prev;

    $tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'overview';
    if ($tab === '') $tab = 'overview';

    if (!isset($out['cache']) || !is_array($out['cache'])) $out['cache'] = array();
    if (!isset($out['file']) || !is_array($out['file'])) $out['file'] = array();
    if (!isset($out['media']) || !is_array($out['media'])) $out['media'] = array();
    if (!isset($out['preload']) || !is_array($out['preload'])) $out['preload'] = array();
    if (!isset($out['advanced']) || !is_array($out['advanced'])) $out['advanced'] = array();

    // ── Cache Pack: apply preset (from overview or legacy pack tab) ──
    if (($tab === 'overview' || $tab === 'pack') && !empty($raw['apply_pack_btn']) && !empty($raw['apply_pack'])) {
      $pack_key = sanitize_key((string)$raw['apply_pack']);
      $pack_defs = array(
        'blog'       => array('cache'=>array('browser_headers'=>1,'browser_ttl_h'=>4),'file'=>array('remove_qs'=>1,'defer_js'=>1,'delay_js'=>0),'media'=>array('disable_emojis'=>1,'lazy_images'=>1,'lazy_iframes'=>1)),
        'ecommerce'  => array('cache'=>array('browser_headers'=>1,'browser_ttl_h'=>1),'file'=>array('remove_qs'=>1,'defer_js'=>1,'delay_js'=>0),'media'=>array('disable_emojis'=>1,'lazy_images'=>1,'lazy_iframes'=>1)),
        'corporate'  => array('cache'=>array('browser_headers'=>1,'browser_ttl_h'=>8),'file'=>array('remove_qs'=>1,'defer_js'=>1,'delay_js'=>1),'media'=>array('disable_emojis'=>1,'lazy_images'=>1,'lazy_iframes'=>1)),
        'aggressive'  => array('cache'=>array('browser_headers'=>1,'browser_ttl_h'=>24),'file'=>array('remove_qs'=>1,'defer_js'=>1,'delay_js'=>1),'media'=>array('disable_emojis'=>1,'lazy_images'=>1,'lazy_iframes'=>1)),
      );
      if (isset($pack_defs[$pack_key])) {
        $pd = $pack_defs[$pack_key];
        foreach ($pd as $section => $vals) {
          if (!isset($out[$section]) || !is_array($out[$section])) $out[$section] = array();
          foreach ($vals as $k => $v) {
            $out[$section][$k] = $v;
          }
        }
        $out['pack'] = $pack_key;
      }
    }

    if ($tab === 'overview') {
      $out['enabled_ui'] = !empty($raw['enabled_ui']) ? 1 : 0;
    }

    // Unified settings tab (all cache/file/media/preload/advanced in one page)
    if ($tab === 'settings' || $tab === 'cache' || $tab === 'file' || $tab === 'media' || $tab === 'preload' || $tab === 'advanced') {
      // Cache section
      if (isset($raw['cache']) && is_array($raw['cache'])) {
        $c = $raw['cache'];
        $out['cache']['browser_headers'] = !empty($c['browser_headers']) ? 1 : 0;
        $out['cache']['browser_ttl_h']   = (int)($c['browser_ttl_h'] ?? ($out['cache']['browser_ttl_h'] ?? 1));
        if ($out['cache']['browser_ttl_h'] < 0) $out['cache']['browser_ttl_h'] = 0;
        if ($out['cache']['browser_ttl_h'] > 168) $out['cache']['browser_ttl_h'] = 168;
      }
      // File section
      if (isset($raw['file']) && is_array($raw['file'])) {
        $f = $raw['file'];
        $out['file']['defer_js']   = !empty($f['defer_js']) ? 1 : 0;
        $out['file']['delay_js']   = !empty($f['delay_js']) ? 1 : 0;
        $out['file']['remove_qs']  = !empty($f['remove_qs']) ? 1 : 0;
      }
      // Media section
      if (isset($raw['media']) && is_array($raw['media'])) {
        $m = $raw['media'];
        $out['media']['lazy_images']    = !empty($m['lazy_images']) ? 1 : 0;
        $out['media']['lazy_iframes']   = !empty($m['lazy_iframes']) ? 1 : 0;
        $out['media']['disable_emojis'] = !empty($m['disable_emojis']) ? 1 : 0;
      }
      // Preload section
      if (isset($raw['preload']) && is_array($raw['preload'])) {
        $p = $raw['preload'];
        $out['preload']['dns_prefetch']  = sanitize_textarea_field((string)($p['dns_prefetch'] ?? ''));
        $out['preload']['preconnect']    = sanitize_textarea_field((string)($p['preconnect'] ?? ''));
      }
      // Advanced section
      if (isset($raw['advanced']) && is_array($raw['advanced'])) {
        $a = $raw['advanced'];
        $out['advanced']['purge_opcache'] = !empty($a['purge_opcache']) ? 1 : 0;
      }
    }

    update_option('langa_tools_cache_settings', $out);
  }
  // -------------------------
  // -------------------------
  // SAVE: LEGAL
  // -------------------------

  if ($module === 'legal') {
    $raw = isset($_POST['legal']) && is_array($_POST['legal']) ? wp_unslash($_POST['legal']) : array();
    $prev = get_option('langa_tools_legal_settings', array());
    if (!is_array($prev)) $prev = array();

    // Use canonical defaults (single source of truth)
    $defaults = function_exists('langa_tools_client_legal_defaults') ? langa_tools_client_legal_defaults() : array(
      'banner_enabled' => 1,
      'show_links' => 1,
      'show_for_logged_in' => 0,
      'position' => 'bottom-right',
      'cookie_name' => 'langa_consent',
      'cookie_days' => 180,

      'color_bg' => '#ffffff',
      'color_panel' => '#f5f5f4',
      'color_text' => '#1c1917',
      'color_btn_bg' => '#d6d3d1',
      'color_btn_text' => '#1c1917',
      'color_link' => '#57534e',
      'radius' => 5,

      'privacy_content' => '',
      'terms_content' => '',
      'cookie_content' => '',
      'privacy_page_id' => 0,
      'terms_page_id' => 0,
      'cookie_page_id' => 0,
    );
    $prev = wp_parse_args($prev, $defaults);

    $current_tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'overview';
    if ($current_tab === '') $current_tab = 'overview';

    $out = $prev;

    // Overview tab
    if ($current_tab === 'overview') {
      $out['banner_enabled'] = !empty($raw['banner_enabled']) ? 1 : 0;
      $out['show_links'] = !empty($raw['show_links']) ? 1 : 0;
      $out['show_for_logged_in'] = !empty($raw['show_for_logged_in']) ? 1 : 0;

      // Sync flags
      $out['sync_wp_privacy'] = !empty($raw['sync_wp_privacy']) ? 1 : 0;
      $out['sync_wc_terms']   = !empty($raw['sync_wc_terms']) ? 1 : 0;

      // Site type + enabled flags (wizard)
      if (isset($raw['site_type'])) {
        $st = sanitize_key((string)$raw['site_type']);
        if (in_array($st, array('vetrina','servizi','ecommerce'), true)) {
          $out['site_type'] = $st;
        }
      }
      $out['terms_enabled'] = !empty($raw['terms_enabled']) ? 1 : 0;
      $out['impressum_enabled'] = !empty($raw['impressum_enabled']) ? 1 : 0;

      // Trash pages that were just disabled by wizard
      $trash_map = array(
        'terms_enabled'    => 'terms_page_id',
        'impressum_enabled'=> 'impressum_page_id',
      );
      foreach ($trash_map as $flag_key => $page_key) {
        $was_on = !empty($prev[$flag_key]);
        $now_on = !empty($out[$flag_key]);
        if ($was_on && !$now_on) {
          $pid = (int)($prev[$page_key] ?? 0);
          if ($pid > 0 && get_post_status($pid)) {
            wp_trash_post($pid);
            $out[$page_key] = 0;
          }
        }
      }

      if (isset($raw['position'])) {
        $pos = sanitize_key((string)$raw['position']);
        if (in_array($pos, array('bottom-right','bottom-left','bottom'), true)) {
          $out['position'] = $pos;
        }
      }

      if (isset($raw['cookie_name'])) {
        $out['cookie_name'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$raw['cookie_name']);
      }
      if (isset($raw['cookie_days'])) {
        $out['cookie_days'] = max(1, min(3650, (int)$raw['cookie_days']));
      }
    }

    // Pack tab — site type + enabled flags (same fields as old overview wizard)
    if ($current_tab === 'pack') {
      if (isset($raw['site_type'])) {
        $st = sanitize_key((string)$raw['site_type']);
        if (in_array($st, array('vetrina','servizi','ecommerce'), true)) {
          $out['site_type'] = $st;
        }
      }
      $out['terms_enabled'] = !empty($raw['terms_enabled']) ? 1 : 0;
      $out['impressum_enabled'] = !empty($raw['impressum_enabled']) ? 1 : 0;

      // Trash pages that were just disabled
      $trash_map = array(
        'terms_enabled'    => 'terms_page_id',
        'impressum_enabled'=> 'impressum_page_id',
      );
      foreach ($trash_map as $flag_key => $page_key) {
        $was_on = !empty($prev[$flag_key]);
        $now_on = !empty($out[$flag_key]);
        if ($was_on && !$now_on) {
          $pid = (int)($prev[$page_key] ?? 0);
          if ($pid > 0 && get_post_status($pid)) {
            wp_trash_post($pid);
            $out[$page_key] = 0;
          }
        }
      }
    }

    // Banner tab (only colors)
    if ($current_tab === 'banner') {
      $hex_keep = function($val, $fallback) {
        $c = sanitize_hex_color((string)$val);
        return $c ? $c : $fallback;
      };

      foreach (array('color_bg','color_panel','color_text','color_btn_bg','color_btn_text','color_link') as $k) {
        if (isset($raw[$k])) {
          $out[$k] = $hex_keep($raw[$k], (string)($prev[$k] ?? ''));
        }
      }
      if (isset($raw['radius'])) {
        $out['radius'] = max(0, min(80, (int)$raw['radius']));
      }
    }

    // Pages tab → now split into per-document tabs: privacy, terms, cookie
    // Each only saves its own content + page_id
    $update_content = 0;
    $force_slugs = 0;
    $doc_tab_map = array(
      'privacy'   => array('content' => 'privacy_content', 'page' => 'privacy_page_id'),
      'terms'     => array('content' => 'terms_content',   'page' => 'terms_page_id'),
      'cookie'    => array('content' => 'cookie_content',  'page' => 'cookie_page_id'),
      'impressum' => array('content' => 'impressum_content','page' => 'impressum_page_id'),
    );
    if (isset($doc_tab_map[$current_tab])) {
      $dtm = $doc_tab_map[$current_tab];
      // Save only THIS document's content
      if (isset($raw[$dtm['content']])) {
        $out[$dtm['content']] = wp_kses_post((string)$raw[$dtm['content']]);
      }
      // Save only THIS document's page binding
      if (isset($raw[$dtm['page']])) {
        $id = (int)$raw[$dtm['page']];
        $out[$dtm['page']] = ($id > 0 && get_post_status($id)) ? $id : 0;
      }
      $update_content = !empty($raw['update_content']) ? 1 : 0;
      $force_slugs = !empty($raw['force_slugs']) ? 1 : 0;
    }

    // Legacy compat: "pages" tab (if somehow still posted)
    if ($current_tab === 'pages') {
      foreach (array('privacy_content','terms_content','cookie_content','impressum_content') as $k) {
        if (isset($raw[$k])) $out[$k] = wp_kses_post((string)$raw[$k]);
      }
      $update_content = !empty($raw['update_content']) ? 1 : 0;
      $force_slugs = !empty($raw['force_slugs']) ? 1 : 0;
      foreach (array('privacy_page_id','terms_page_id','cookie_page_id','impressum_page_id') as $pidk) {
        if (isset($raw[$pidk])) {
          $id = (int)$raw[$pidk];
          $out[$pidk] = ($id > 0 && get_post_status($id)) ? $id : 0;
        }
      }
    }

    // Force flag stored (used by generator)
    $is_doc_tab = isset($doc_tab_map[$current_tab]) || $current_tab === 'pages';
    if ($is_doc_tab) {
      $out['force_slugs'] = (int)$force_slugs;
    } else {
      if (isset($prev['force_slugs'])) $out['force_slugs'] = (int)$prev['force_slugs'];
    }

    // Keep page IDs from previous option unless changed in a document tab
    if (!$is_doc_tab) {
      foreach (array('privacy_page_id','terms_page_id','cookie_page_id','impressum_page_id') as $pidk) {
        if (isset($prev[$pidk])) $out[$pidk] = (int)$prev[$pidk];
      }
    }
    // For per-document tabs, preserve OTHER documents' page IDs
    if (isset($doc_tab_map[$current_tab])) {
      foreach (array('privacy_page_id','terms_page_id','cookie_page_id','impressum_page_id') as $pidk) {
        if ($pidk !== $doc_tab_map[$current_tab]['page'] && isset($prev[$pidk])) {
          $out[$pidk] = (int)$prev[$pidk];
        }
      }
      // Also preserve OTHER documents' content
      foreach (array('privacy_content','terms_content','cookie_content','impressum_content') as $ck) {
        if ($ck !== $doc_tab_map[$current_tab]['content'] && isset($prev[$ck])) {
          $out[$ck] = $prev[$ck];
        }
      }
    }

    // Preserve enabled flags + site_type when NOT on overview or pack tab
    if ($current_tab !== 'overview' && $current_tab !== 'pack') {
      foreach (array('terms_enabled','impressum_enabled','site_type') as $ek) {
        if (isset($prev[$ek])) $out[$ek] = $prev[$ek];
      }
    }

    update_option('langa_tools_legal_settings', $out, false);

    // Apply sync immediately when bindings are changed
    if ($is_doc_tab) {
      if (!empty($out['sync_wp_privacy']) && !empty($out['privacy_page_id'])) {
        update_option('wp_page_for_privacy_policy', (int)$out['privacy_page_id']);
      }
      if (!empty($out['sync_wc_terms']) && !empty($out['terms_page_id']) && function_exists('WC')) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentional WooCommerce core option integration
        update_option('woocommerce_terms_page_id', (int)$out['terms_page_id']);
      }
    }

    // If asked, (re)generate pages — always updates content + forces slug
    // legal_generate_pages value = specific tab name (privacy|terms|cookie|impressum)
    if ($is_doc_tab && !empty($_POST['legal_generate_pages'])) {
      if (function_exists('langa_tools_client_legal_ensure_pages')) {
        langa_tools_client_legal_ensure_pages(true, true);
      }
    }

    // Wizard generate (overview OR pack tab) — resets content to GDPR defaults, generates pages, trashes disabled
    if (($current_tab === 'overview' || $current_tab === 'pack') && !empty($_POST['legal_wizard_generate'])) {

      // Auto-derive enabled flags from site_type if not explicitly set
      $wiz_type = sanitize_key($out['site_type'] ?? '');
      if ($wiz_type !== '' && empty($raw['terms_enabled']) && empty($raw['impressum_enabled'])) {
        $out['terms_enabled']    = in_array($wiz_type, array('servizi','ecommerce'), true) ? 1 : 0;
        $out['impressum_enabled']= ($wiz_type === 'ecommerce') ? 1 : 0;
      }

      // Reset all template content to defaults so ensure_pages uses fresh GDPR templates
      $content_keys = array('privacy_content', 'cookie_content', 'terms_content', 'impressum_content');
      $current_settings = get_option('langa_tools_legal_settings', array());
      if (!is_array($current_settings)) $current_settings = array();
      // Apply the enabled flags from the wizard form
      $current_settings['terms_enabled']    = !empty($out['terms_enabled']) ? 1 : 0;
      $current_settings['impressum_enabled']= !empty($out['impressum_enabled']) ? 1 : 0;
      $current_settings['site_type']        = sanitize_key($out['site_type'] ?? '');
      foreach ($content_keys as $ck) {
        $current_settings[$ck] = ''; // empty = ensure_pages will use default_fn
      }
      update_option('langa_tools_legal_settings', $current_settings, false);

      if (function_exists('langa_tools_client_legal_ensure_pages')) {
        langa_tools_client_legal_ensure_pages(true, true);
      }

      // Trash pages that are no longer enabled
      $optional_pages = array(
        'terms_enabled'    => 'terms_page_id',
        'impressum_enabled'=> 'impressum_page_id',
      );
      $refreshed = get_option('langa_tools_legal_settings', array());
      if (!is_array($refreshed)) $refreshed = array();
      foreach ($optional_pages as $flag_key => $page_key) {
        $is_enabled = !empty($out[$flag_key]);
        $pid = (int)($refreshed[$page_key] ?? 0);
        if (!$is_enabled && $pid > 0 && get_post_status($pid)) {
          wp_trash_post($pid);
          $refreshed[$page_key] = 0;
        }
      }
      update_option('langa_tools_legal_settings', $refreshed, false);
    }
  }



  // SAVE: SEO
  // - salva per TAB (features/sitemap/titles/social/schema/advanced)
  // - NON resetta sezioni non presenti nel form (niente wipe)
  // -------------------------
  if ($module === 'seo') {
    $raw = isset($_POST['seo']) && is_array($_POST['seo']) ? wp_unslash($_POST['seo']) : array();
    $seo = get_option('langa_tools_seo_settings', array());
    if (!is_array($seo)) $seo = array();

    $current_tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'features';
    if ($current_tab === '') $current_tab = 'features';

    // Ensure base containers exist
    if (!isset($seo['features']) || !is_array($seo['features'])) $seo['features'] = array();
    if (!isset($seo['turbo']) || !is_array($seo['turbo'])) $seo['turbo'] = array();
    if (!isset($seo['sitemap']) || !is_array($seo['sitemap'])) $seo['sitemap'] = array();
    if (!isset($seo['content']) || !is_array($seo['content'])) $seo['content'] = array();
    if (!isset($seo['social']) || !is_array($seo['social'])) $seo['social'] = array();
    if (!isset($seo['schema']) || !is_array($seo['schema'])) $seo['schema'] = array();
    if (!isset($seo['advanced']) || !is_array($seo['advanced'])) $seo['advanced'] = array();

    // Defaults (first run): base ON (ma sempre modificabile)
    foreach (array('index_posts','index_pages','index_products','index_media') as $k) {
      if (!array_key_exists($k, $seo['content'])) $seo['content'][$k] = 1;
    }

    // Registry keys (single source of truth)
    $feature_registry = function_exists('langa_tools_client_subfeatures_registry') ? langa_tools_client_subfeatures_registry('seo') : array();
    $feature_keys = array();
    if (is_array($feature_registry) && isset($feature_registry['features']) && is_array($feature_registry['features'])) {
      $feature_keys = array_keys($feature_registry['features']);
    }
    if (empty($feature_keys)) {
      $feature_keys = array('xml_sitemap','robots_controls','titles_meta','opengraph','twitter_cards','canonical','schema','metabox','breadcrumbs','redirect_404_home','indexnow');
    }



    // === SEO PACK APPLY (global) ===
    // Applies a preset across ALL SEO tabs by toggling features/content/sitemap/turbo.
    // IMPORTANT: does NOT delete user inputs (titles/social/schema/canonical overrides). It only changes ON/OFF flags.
    if (!empty($raw['apply_pack'])) {
      // mode comes from POST if present (radio), otherwise current saved.
      $mode = isset($raw['mode']) ? sanitize_key((string)$raw['mode']) : (isset($seo['mode']) ? sanitize_key((string)$seo['mode']) : 'light');
      if ($mode === '') $mode = 'light';
      
      // Normalize legacy modes
      if ($mode === 'top') $mode = 'turbo';
      if ($mode === 'medium') $mode = 'standard';
      if (!in_array($mode, array('light','standard','turbo','noindex'), true)) $mode = 'light';
$seo['mode'] = $mode;

      $set_features = function($arr) use (&$seo, $feature_keys) {
        foreach ($feature_keys as $k) {
          $k = sanitize_key($k);
          if ($k === '') continue;
          $seo['features'][$k] = !empty($arr[$k]) ? 1 : 0;
        }
      };

      if ($mode === 'noindex') {
        // TOTAL NOINDEX: block indexing + crawling. Content stays visible.
        $seo['turbo']   = array('safe' => 0, 'aggressive_noindex' => 0, 'force_output' => 0);
        $seo['content'] = array('index_posts' => 0, 'index_pages' => 0, 'index_products' => 0, 'index_media' => 0);
        $set_features(array(
          'robots_controls' => 1,
          'metabox' => 0,
          'breadcrumbs' => 0,
          'titles_meta' => 0,
          'canonical' => 0,
          'schema' => 0,
          'opengraph' => 0,
          'twitter_cards' => 0,
          'xml_sitemap' => 0,
          'redirect_404_home' => 0,
          'indexnow' => 0,
        ));
        $seo['sitemap'] = array('enabled' => 0);
      } elseif ($mode === 'light') {
        $seo['turbo']   = array('safe' => 0, 'aggressive_noindex' => 0, 'force_output' => 0);
        $seo['content'] = array('index_posts' => 1, 'index_pages' => 1, 'index_products' => 1, 'index_media' => 1);
        $set_features(array(
          'xml_sitemap' => 1,
          'robots_controls' => 1,
          'titles_meta' => 1,
          'canonical' => 1,
          'schema' => 1,
          'metabox' => 1,
          'breadcrumbs' => 1,
          'opengraph' => 0,
          'twitter_cards' => 0,
          'redirect_404_home' => 0,
          'indexnow' => 0,
        ));
        $seo['sitemap'] = array(
          'enabled' => 1,
          'include_posts' => 1,
          'include_pages' => 1,
          'include_products' => 1,
          'include_categories' => 1,
          'include_tags' => 1,
          'include_product_categories' => 1,
          'include_product_tags' => 1,
          'include_media' => 1,
        );
      } elseif ($mode === 'standard') {
        $seo['turbo']   = array('safe' => 0, 'aggressive_noindex' => 0, 'force_output' => 0);
        $seo['content'] = array('index_posts' => 1, 'index_pages' => 1, 'index_products' => 1, 'index_media' => 1);
        $set_features(array(
          'xml_sitemap' => 1,
          'robots_controls' => 1,
          'titles_meta' => 1,
          'canonical' => 1,
          'schema' => 1,
          'metabox' => 1,
          'breadcrumbs' => 1,
          'opengraph' => 1,
          'twitter_cards' => 1,
          'redirect_404_home' => 0,
          'indexnow' => 0,
        ));
        $seo['sitemap'] = array(
          'enabled' => 1,
          'include_posts' => 1,
          'include_pages' => 1,
          'include_products' => 1,
          'include_categories' => 1,
          'include_tags' => 1,
          'include_product_categories' => 1,
          'include_product_tags' => 1,
          'include_media' => 1,
        );
      } else { // turbo

        $seo['mode'] = 'turbo';
        $seo['turbo']   = array('safe' => 1, 'aggressive_noindex' => 0, 'force_output' => 0);
        $seo['content'] = array('index_posts' => 1, 'index_pages' => 1, 'index_products' => 1, 'index_media' => 1);
        $set_features(array(
          'xml_sitemap' => 1,
          'robots_controls' => 1,
          'titles_meta' => 1,
          'canonical' => 1,
          'schema' => 1,
          'metabox' => 1,
          'breadcrumbs' => 1,
          'opengraph' => 1,
          'twitter_cards' => 1,
          'redirect_404_home' => 0,
          'indexnow' => 1,
        ));
        $seo['sitemap'] = array(
          'enabled' => 1,
          'include_posts' => 1,
          'include_pages' => 1,
          'include_products' => 1,
          'include_categories' => 1,
          'include_tags' => 1,
          'include_product_categories' => 1,
          'include_product_tags' => 1,
          'include_media' => 1,
        );
      }

      delete_option('langa_tools_seo_rewrites_flushed');
      delete_option('langa_tools_indexnow_rewrites_flushed');

      update_option('langa_tools_seo_settings', $seo);
      wp_redirect(add_query_arg(array('page' => 'langa-tools-client-seo', 'seo_saved' => '1', 'tab' => 'pack'), admin_url('admin.php')));
      exit;
    }
    
    // --- TAB: FEATURES
    if ($current_tab === 'features') {
      // Optional: save selected pack (does not apply).
      if (isset($raw['mode'])) {
        $seo['mode'] = sanitize_key((string)$raw['mode']);
      }

      // Indicizzazione contenuti: sempre deterministica (checkbox missing => OFF)
      $posted_content = isset($raw['content']) && is_array($raw['content']) ? $raw['content'] : array();
      foreach (array('index_posts','index_pages','index_products','index_media') as $k) {
        $seo['content'][$k] = !empty($posted_content[$k]) ? 1 : 0;
      }

      // Feature toggles (core) in this tab
      $posted_features = isset($raw['features']) && is_array($raw['features']) ? $raw['features'] : array();
      foreach ($feature_keys as $k) {
        $k = sanitize_key($k);
        if ($k === '') continue;
        if (in_array($k, array('xml_sitemap','indexnow','redirect_404_home','titles_meta','opengraph','twitter_cards','schema','canonical'), true)) continue;
        $seo['features'][$k] = !empty($posted_features[$k]) ? 1 : 0;
      }
    }

    // --- TAB: SITEMAP
    if ($current_tab === 'sitemap') {
      $posted_features = isset($raw['features']) && is_array($raw['features']) ? $raw['features'] : array();
      $seo['features']['xml_sitemap'] = !empty($posted_features['xml_sitemap']) ? 1 : 0;

      $posted_sitemap = isset($raw['sitemap']) && is_array($raw['sitemap']) ? $raw['sitemap'] : array();
      $seo['sitemap']['enabled'] = !empty($seo['features']['xml_sitemap']) ? 1 : 0;
      $seo['sitemap']['cache'] = !empty($posted_sitemap['cache']) ? 1 : 0;
      $seo['sitemap']['split'] = !empty($posted_sitemap['split']) ? 1 : 0;
      foreach (array('include_posts','include_pages','include_products','include_categories','include_tags','include_product_categories','include_product_tags','include_media') as $k) {
        $seo['sitemap'][$k] = !empty($posted_sitemap[$k]) ? 1 : 0;
      }

      // force rewrite refresh (one-shot)
      delete_option('langa_tools_seo_rewrites_flushed');
    }

    // --- TAB: TITLES
    if ($current_tab === 'titles') {
      $seo['separator'] = isset($raw['separator']) ? sanitize_text_field((string)$raw['separator']) : ($seo['separator'] ?? '—');
      $seo['site_name'] = isset($raw['site_name']) ? sanitize_text_field((string)$raw['site_name']) : ($seo['site_name'] ?? '');

      if (isset($raw['titles']) && is_array($raw['titles'])) {
        if (!isset($seo['titles']) || !is_array($seo['titles'])) $seo['titles'] = array();
        foreach (array('home_title','home_desc','default_title','default_desc') as $k) {
          $seo['titles'][$k] = isset($raw['titles'][$k]) ? sanitize_text_field((string)$raw['titles'][$k]) : ($seo['titles'][$k] ?? '');
        }
      }

      $posted_features = isset($raw['features']) && is_array($raw['features']) ? $raw['features'] : array();
      $seo['features']['titles_meta'] = !empty($posted_features['titles_meta']) ? 1 : 0;
    }

    // --- TAB: SOCIAL
    if ($current_tab === 'social') {
      $posted_social = isset($raw['social']) && is_array($raw['social']) ? $raw['social'] : array();
      $seo['social']['default_share_image'] = !empty($posted_social['default_share_image']) ? esc_url_raw((string)$posted_social['default_share_image']) : '';
      $seo['social']['facebook_page']       = !empty($posted_social['facebook_page']) ? esc_url_raw((string)$posted_social['facebook_page']) : '';
      $seo['social']['instagram']           = !empty($posted_social['instagram']) ? esc_url_raw((string)$posted_social['instagram']) : '';
      $seo['social']['linkedin']            = !empty($posted_social['linkedin']) ? esc_url_raw((string)$posted_social['linkedin']) : '';
      $seo['social']['youtube']             = !empty($posted_social['youtube']) ? esc_url_raw((string)$posted_social['youtube']) : '';

      $seo['social']['twitter_site'] = isset($posted_social['twitter_site']) ? sanitize_text_field((string)$posted_social['twitter_site']) : '';
      $card = isset($posted_social['twitter_card']) ? sanitize_key((string)$posted_social['twitter_card']) : 'summary_large_image';
      if (!in_array($card, array('summary_large_image','summary'), true)) $card = 'summary_large_image';
      $seo['social']['twitter_card'] = $card;

      $posted_features = isset($raw['features']) && is_array($raw['features']) ? $raw['features'] : array();
      $seo['features']['opengraph'] = !empty($posted_features['opengraph']) ? 1 : 0;
      $seo['features']['twitter_cards'] = !empty($posted_features['twitter_cards']) ? 1 : 0;
    }

    // --- TAB: SCHEMA
    if ($current_tab === 'schema') {
      $posted_schema = isset($raw['schema']) && is_array($raw['schema']) ? $raw['schema'] : array();
      $seo['schema']['type'] = isset($posted_schema['type']) ? sanitize_text_field((string)$posted_schema['type']) : ($seo['schema']['type'] ?? 'organization');
      $seo['schema']['logo'] = isset($posted_schema['logo']) ? esc_url_raw((string)$posted_schema['logo']) : ($seo['schema']['logo'] ?? '');
      $seo['schema']['sameas'] = isset($posted_schema['sameas']) ? sanitize_textarea_field((string)$posted_schema['sameas']) : ($seo['schema']['sameas'] ?? '');

      $posted_features = isset($raw['features']) && is_array($raw['features']) ? $raw['features'] : array();
      $seo['features']['schema'] = !empty($posted_features['schema']) ? 1 : 0;
    }

    // --- TAB: ADVANCED
    if ($current_tab === 'advanced') {
      $posted_features = isset($raw['features']) && is_array($raw['features']) ? $raw['features'] : array();
      foreach (array('canonical','redirect_404_home','indexnow','robots_controls') as $k) {
        $seo['features'][$k] = !empty($posted_features[$k]) ? 1 : 0;
      }

      $posted_adv = isset($raw['advanced']) && is_array($raw['advanced']) ? $raw['advanced'] : array();
      $seo['advanced']['strip_params'] = isset($posted_adv['strip_params']) ? sanitize_text_field((string)$posted_adv['strip_params']) : ($seo['advanced']['strip_params'] ?? '');
      $seo['advanced']['target_keywords'] = isset($posted_adv['target_keywords']) ? sanitize_textarea_field((string)$posted_adv['target_keywords']) : ($seo['advanced']['target_keywords'] ?? '');
      $seo['advanced']['competitor_sites'] = isset($posted_adv['competitor_sites']) ? sanitize_textarea_field((string)$posted_adv['competitor_sites']) : ($seo['advanced']['competitor_sites'] ?? '');

      // force rewrite refresh for IndexNow (one-shot)
      delete_option('langa_tools_indexnow_rewrites_flushed');
    }

    // --- TAB: PACK (save selected mode without applying)
    if ($current_tab === 'pack') {
      if (isset($raw['mode'])) {
        $m = sanitize_key((string)$raw['mode']);
        if ($m === '') $m = 'light';
        if ($m === 'top' || $m === 'turbo') $m = 'turbo';
        if ($m === 'medium' || $m === 'standard') $m = 'standard';
        if (!in_array($m, array('light','standard','turbo','noindex'), true)) $m = 'light';
        $seo['mode'] = $m;
      }
    }


    update_option('langa_tools_seo_settings', $seo);

    wp_redirect(add_query_arg(array('page' => 'langa-tools-client-seo', 'seo_saved' => '1', 'tab' => $current_tab), admin_url('admin.php')));
    exit;
  }


// -------------------------
  // SAVE: BC (Business Card)
  // -------------------------
  if ($module === 'bc') {
    // Ensure sanitizers exist
    if (!function_exists('langa_bc_admin_sanitize_settings')) {
      require_once LANGA_TOOLS_CLIENT_PATH . 'includes/bc/admin-save.php';
    }

    $existing = get_option('langa_tools_bc_settings', array());
    if (!is_array($existing)) $existing = array();

    $post_bc = isset($_POST['bc']) && is_array($_POST['bc']) ? map_deep(wp_unslash($_POST['bc']), 'sanitize_text_field') : array();

    // The UI posts only one sub-tab at a time: main or staff
    $scope = isset($_POST['lbc_admin_tab']) ? sanitize_key((string)$_POST['lbc_admin_tab']) : 'main';
    if (!in_array($scope, array('main', 'staff', 'style'), true)) $scope = 'main';

    $new_settings = langa_bc_admin_sanitize_settings($existing, $post_bc, ($scope === 'staff' ? 'staff' : 'main'));
    update_option('langa_tools_bc_settings', $new_settings, false);

    // Flush rewrites safely (one-shot)
    update_option('langa_tools_bc_flush', 1);

    wp_safe_redirect(add_query_arg(array(
      'page'   => 'langa-tools-client-bc',
      'saved'  => '1',
      'tab' => $scope,
    ), admin_url('admin.php')));
    exit;
  }
// -------------------------
  // SAVE: EVENTS (ex-BRIDGE)
  // -------------------------

  if ($module === 'bridge') {
    // Module enable toggle
    update_option('langa_tools_bridge_enabled', $enabled ? 1 : 0);

    // Merge with previous settings (tab-safe: only overwrite fields present in current tab)
    $prev_bridge = get_option('langa_tools_bridge_settings', array());
    if (!is_array($prev_bridge)) $prev_bridge = array();

    $bridge  = isset($_POST['bridge']) && is_array($_POST['bridge']) ? wp_unslash($_POST['bridge']) : array();
    $events  = isset($_POST['bridge_events']) && is_array($_POST['bridge_events']) ? wp_unslash($_POST['bridge_events']) : array();
    $sources = isset($_POST['bridge_sources']) && is_array($_POST['bridge_sources']) ? wp_unslash($_POST['bridge_sources']) : array();

    $current_tab = isset($_POST['current_tab']) ? sanitize_key($_POST['current_tab']) : 'overview';

    $out = $prev_bridge;

    // Tracking tab: what to record
    if ($current_tab === 'tracking') {
      $out['events'] = array(
        'forms'  => !empty($events['forms']) ? 1 : 0,
        'e404'   => !empty($events['e404']) ? 1 : 0,
        'perf'   => !empty($events['perf']) ? 1 : 0,
        'logins' => !empty($events['logins']) ? 1 : 0,
        'errors' => !empty($events['errors']) ? 1 : 0,
        'orders' => !empty($events['orders']) ? 1 : 0,
      );
      $out['sources'] = array(
        'langa_forms' => !empty($sources['langa_forms']) ? 1 : 0,
        'cf7'         => !empty($sources['cf7']) ? 1 : 0,
        'fluent'      => !empty($sources['fluent']) ? 1 : 0,
      );
    }

    // Events tab: retention
    if ($current_tab === 'events') {
      $ret = isset($bridge['local_retention']) ? (int)$bridge['local_retention'] : 30;
      $out['local_retention'] = max(1, min(365, $ret));
    }

    // Bridge tab: where to send + connection
    if ($current_tab === 'bridge') {
      $out['mode'] = (!empty($bridge['mode']) && $bridge['mode'] === 'remote') ? 'remote' : 'local';
      $out['share_data'] = !empty($bridge['share_data']) ? 1 : 0;
      $out['remote_url'] = !empty($bridge['remote_url']) ? esc_url_raw(trim($bridge['remote_url'])) : '';
      $out['api_token'] = !empty($bridge['api_token']) ? sanitize_text_field(trim($bridge['api_token'])) : '';
      $out['site_id']   = !empty($bridge['site_id']) ? sanitize_text_field(trim($bridge['site_id'])) : '';
      $out['batch_size'] = isset($bridge['batch_size']) ? max(5, min(100, (int)$bridge['batch_size'])) : 25;
      $out['batch_interval'] = isset($bridge['batch_interval']) ? max(60, min(3600, (int)$bridge['batch_interval'])) : 300;
    }

    update_option('langa_tools_bridge_settings', $out, false);

    // ── Purge actions (events tab) ──
    if (!empty($_POST['events_purge_old'])) {
      if (!function_exists('langa_events_purge')) {
        require_once LANGA_TOOLS_CLIENT_PATH . 'includes/bridge/events-local.php';
      }
      $days = isset($out['local_retention']) ? (int)$out['local_retention'] : 30;
      langa_events_purge($days);
    }
    if (!empty($_POST['events_purge_all'])) {
      if (!function_exists('langa_events_purge_all')) {
        require_once LANGA_TOOLS_CLIENT_PATH . 'includes/bridge/events-local.php';
      }
      langa_events_purge_all();
    }

    // ── Send test event (bridge tab) ──
    if (!empty($_POST['bridge_send_test'])) {
      $test_event = array(
        'site_url'   => home_url(),
        'event_type' => 'connectivity_test',
        'mode'       => 'test',
        'ref'        => 'bridge_ui',
        'ts'         => time(),
        'nonce'      => wp_generate_password(12, false, false),
      );

      // Also log locally
      if (!function_exists('langa_events_log')) {
        require_once LANGA_TOOLS_CLIENT_PATH . 'includes/bridge/events-local.php';
      }
      langa_events_log('test', 'manual', 'Connectivity test (bridge UI)', $test_event, 'info');

      $test_status = 'fail';
      if (class_exists('Langa_Tools_Client_API')) {
        $test_ok = Langa_Tools_Client_API::send_event($test_event);
        if ($test_ok) {
          $test_status = 'ok';
        } else {
          $server = defined('LANGA_TOOLS_FIXED_SERVER_URL') ? rtrim(LANGA_TOOLS_FIXED_SERVER_URL, '/') : '';
          $sk = (string)get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
          $sc = (string)get_option(LANGA_TOOLS_OPTION_SECRET, '');
          if ($server && $sk && $sc) {
            $payload = wp_json_encode($test_event);
            $sig = Langa_Tools_Client_Auth::sign($payload, $sc);
            $resp = wp_remote_post($server . '/wp-json/langa-tools-server/v1/events/log-event', array(
              'timeout' => 12,
              'body' => array('site_key' => $sk, 'payload' => $payload, 'signature' => $sig),
            ));
            if (!is_wp_error($resp)) {
              $body = json_decode(wp_remote_retrieve_body($resp), true);
              if (is_array($body) && isset($body['error']) && in_array((string)$body['error'], array('events_gateway_disabled','events_disabled','gateway_disabled'), true)) {
                $test_status = 'warn';
              }
            }
          }
        }
      }
      set_transient('langa_bridge_test_result', array('status' => $test_status, 'ts' => time()), 30);
    }
  }

  if ($module === 'safer') {
    $raw  = isset($_POST['safer']) && is_array($_POST['safer']) ? wp_unslash($_POST['safer']) : array();
    $prev = get_option('langa_tools_safer_settings', array());
    if (!is_array($prev)) $prev = array();

    // Save per-tab (do NOT reset other tabs).
    $out = $prev;

    $current_tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'overview';
    if ($current_tab === '') $current_tab = 'overview';

    $b = function($key) use ($raw) {
      return !empty($raw[$key]) ? 1 : 0;
    };

    // ── Safer Pack: apply preset ──
    if ($current_tab === 'overview' && !empty($raw['apply_pack_btn'])) {
      $pack_key = isset($raw['apply_pack']) ? sanitize_key((string)$raw['apply_pack']) : '';
      $safer_packs = array(
        'basic' => array('hide_wp_version'=>1,'hide_wp_fingerprints'=>1,'disable_xmlrpc'=>1,'block_author_enum'=>1,'disable_file_editor'=>0,'force_https_admin'=>0,'disable_rest_guests'=>0,'htaccess_hardening'=>0,'door_only_access'=>0,'protezione_2_0'=>0),
        'business' => array('hide_wp_version'=>1,'hide_wp_fingerprints'=>1,'disable_xmlrpc'=>1,'block_author_enum'=>1,'disable_file_editor'=>1,'force_https_admin'=>1,'disable_rest_guests'=>0,'htaccess_hardening'=>0,'door_only_access'=>0,'protezione_2_0'=>0),
        'fortress' => array('hide_wp_version'=>1,'hide_wp_fingerprints'=>1,'disable_xmlrpc'=>1,'block_author_enum'=>1,'disable_file_editor'=>1,'force_https_admin'=>1,'disable_rest_guests'=>0,'htaccess_hardening'=>1,'door_only_access'=>1,'protezione_2_0'=>1),
      );
      if (isset($safer_packs[$pack_key])) {
        $was_ghost = !empty($prev['protezione_2_0']) || !empty($prev['htaccess_hardening']);
        foreach ($safer_packs[$pack_key] as $k => $v) {
          $out[$k] = $v;
        }
        $out['pack'] = $pack_key;
        // Safety: if downgrading from Ghost/Fortress, force htaccess cleanup immediately
        $now_ghost = !empty($out['htaccess_hardening']);
        if ($was_ghost && !$now_ghost && function_exists('langa_tools_client_safer_update_root_htaccess')) {
          @langa_tools_client_safer_update_root_htaccess(false);
        }
      }
    }

    // OVERVIEW — pack apply only
    // (pack handler above takes care of everything)

    // HARDENING
    if ($current_tab === 'hardening') {
      $out['disable_xmlrpc']       = $b('disable_xmlrpc');
      $out['disable_file_editor']  = $b('disable_file_editor');
      $out['block_author_enum']    = $b('block_author_enum');
      $out['hide_wp_version']      = $b('hide_wp_version');
      $out['hide_wp_fingerprints'] = $b('hide_wp_fingerprints');
      $out['force_https_admin']    = $b('force_https_admin');
      $out['disable_rest_guests']  = $b('disable_rest_guests');
    }

    // GHOST MODE tab (htaccess + door + ghost + ip allowlist)
    if ($current_tab === 'ghost' || $current_tab === 'access') {
      $out['htaccess_hardening'] = $b('htaccess_hardening');
      $out['protezione_2_0']     = $b('protezione_2_0');
      $out['door_only_access']   = $b('door_only_access');
      $out['protect_wp_admin']   = $b('protect_wp_admin');
      $out['protect_wp_login']   = $b('protect_wp_login');
      $out['allowlist_ips']      = isset($raw['allowlist_ips']) ? sanitize_textarea_field((string)$raw['allowlist_ips']) : ($prev['allowlist_ips'] ?? '');

      if (isset($_POST['safer_login_slug'])) {
        $new_slug = sanitize_title(wp_unslash($_POST['safer_login_slug']));
        $new_slug = preg_replace('/[^a-z0-9\-]/', '', $new_slug);
        if ($new_slug === '') $new_slug = 'langa-door';
        $reserved = array('wp-admin','wp-login','wp-content','wp-includes','wp-json','feed','sitemap','robots','favicon');
        if (in_array($new_slug, $reserved, true)) $new_slug = 'langa-door';
        $slugs = function_exists('langa_tools_client_safer_get_rewrite_slugs') ? langa_tools_client_safer_get_rewrite_slugs() : array();
        if (is_array($slugs) && (string)($slugs['login'] ?? '') !== $new_slug) {
          $slugs['login'] = $new_slug;
          update_option('langa_tools_safer_slugs', $slugs, false);
        }
      }
    }

    // Safety gates for features that require htaccess hardening.
    $ht = !empty($out['htaccess_hardening']) ? 1 : 0;

    if (!$ht) {
      // Ghost Mode requires htaccess (asset URL rewriting breaks without it)
      if (!empty($out['protezione_2_0'])) {
        $out['protezione_2_0'] = 0;
        update_option('langa_tools_safer_notice_ghost_needs_htaccess', 1, false);
      } else {
        delete_option('langa_tools_safer_notice_ghost_needs_htaccess');
      }
      // NOTE: door_only_access does NOT require htaccess — it works via PHP routing (module.php).
} else {
      delete_option('langa_tools_safer_notice_ghost_needs_htaccess');
    }

    update_option('langa_tools_safer_settings', $out, false);

    // Keep rewrite slugs & plugin map updated when htaccess hardening is enabled.
    if (!empty($out['htaccess_hardening']) && function_exists('langa_tools_client_safer_refresh_plugin_map')) {
      langa_tools_client_safer_refresh_plugin_map();
    }

    // Write/remove .htaccess block depending on toggle.
    if (function_exists('langa_tools_client_safer_update_root_htaccess')) {
      $res1 = langa_tools_client_safer_update_root_htaccess(!empty($out['htaccess_hardening']));
      update_option('langa_tools_safer_last_htaccess', $res1, false);
    }
    // NOTE: langa_tools_client_safer_update_uploads_htaccess was removed (dead code — function never existed).

    if (!empty($_POST['safer_rollback_htaccess'])) {
      if (function_exists('langa_tools_client_safer_rollback_root_htaccess')) {
        $rb = langa_tools_client_safer_rollback_root_htaccess();
        update_option('langa_tools_safer_last_htaccess', $rb, false);
      }
    }
  }

  // -------------------------
  // SAVE: POPUP
  // -------------------------
  if ($module === 'popup') {
    $raw  = isset($_POST['popup']) && is_array($_POST['popup']) ? wp_unslash($_POST['popup']) : array();
    $prev = get_option('langa_tools_popup_settings', array());
    if (!is_array($prev)) $prev = array();
    $out = $prev;
    if (!isset($out['popups']) || !is_array($out['popups'])) $out['popups'] = array();
    if (!isset($out['next_id'])) $out['next_id'] = 1;

    $tab = isset($_POST['current_tab']) ? sanitize_key((string)$_POST['current_tab']) : 'overview';

    // ── Edit / Create popup (now includes per-popup auto_open) ──
    if ($tab === 'edit') {
      $edit = isset($raw['edit']) && is_array($raw['edit']) ? $raw['edit'] : array();
      $popup_id = sanitize_key($edit['id'] ?? 'new');

      $ao_raw = isset($edit['auto_open']) && is_array($edit['auto_open']) ? $edit['auto_open'] : array();

      $popup_data = array(
        'title'         => sanitize_text_field($edit['title'] ?? ''),
        'content'       => wp_kses_post($edit['content'] ?? ''),
        'status'        => in_array($edit['status'] ?? '', array('active','draft'), true) ? $edit['status'] : 'draft',
        'width'         => max(200, min(2000, (int)($edit['width'] ?? 500))),
        'max_width'     => max(50, min(100, (int)($edit['max_width'] ?? 90))),
        'position'      => in_array($edit['position'] ?? '', array('center','top','bottom'), true) ? $edit['position'] : 'center',
        'show_close'    => !empty($edit['show_close']) ? 1 : 0,
        'close_overlay' => !empty($edit['close_overlay']) ? 1 : 0,
        'animation'     => in_array($edit['animation'] ?? '', array('fade','slide','none'), true) ? $edit['animation'] : 'fade',
        'auto_open'     => array(
          'enabled'     => !empty($ao_raw['enabled']) ? 1 : 0,
          'delay_ms'    => max(0, min(30000, (int)($ao_raw['delay_ms'] ?? 3000))),
          'cookie_days' => max(0, min(365, (int)($ao_raw['cookie_days'] ?? 7))),
          'guests_only' => !empty($ao_raw['guests_only']) ? 1 : 0,
          'pages'       => sanitize_text_field($ao_raw['pages'] ?? ''),
        ),
      );

      if ($popup_id === 'new' || $popup_id === '0' || $popup_id === '') {
        $next_id = (int) $out['next_id'];
        $out['popups'][$next_id] = $popup_data;
        $out['next_id'] = $next_id + 1;
        $popup_id = $next_id;
      } else {
        $out['popups'][(int)$popup_id] = $popup_data;
      }

      update_option('langa_tools_popup_settings', $out);

      wp_safe_redirect(add_query_arg(array(
        'page'     => 'langa-tools-client-popup',
        'tab'      => 'edit',
        'popup_id' => (int)$popup_id,
        'saved'    => '1',
      ), admin_url('admin.php')));
      exit;
    }

    if ($tab === 'triggers' || $tab === 'settings') {
      $triggers_raw = isset($raw['triggers']) && is_array($raw['triggers']) ? $raw['triggers'] : array();
      $triggers = array();
      foreach ($triggers_raw as $tr) {
        if (!is_array($tr)) continue;
        $label    = sanitize_text_field($tr['label'] ?? '');
        $selector = sanitize_text_field($tr['selector'] ?? '');
        $popup_id = (int)($tr['popup_id'] ?? 0);
        $event    = in_array(($tr['event'] ?? ''), array('click','hover'), true) ? $tr['event'] : 'click';
        if ($selector === '' && $popup_id === 0) continue;
        $triggers[] = array(
          'label'    => $label,
          'selector' => $selector,
          'popup_id' => $popup_id,
          'event'    => $event,
        );
      }
      $out['triggers'] = $triggers;
    }

    if ($tab === 'auto_open' || $tab === 'settings') {
      $ao = isset($raw['auto_open']) && is_array($raw['auto_open']) ? $raw['auto_open'] : array();
      $out['auto_open'] = array(
        'enabled'     => !empty($ao['enabled']) ? 1 : 0,
        'popup_id'    => (int)($ao['popup_id'] ?? 0),
        'delay_ms'    => max(0, min(30000, (int)($ao['delay_ms'] ?? 3000))),
        'cookie_days' => max(0, min(365, (int)($ao['cookie_days'] ?? 7))),
        'guests_only' => !empty($ao['guests_only']) ? 1 : 0,
        'pages'       => sanitize_text_field($ao['pages'] ?? ''),
      );
    }

    if ($tab === 'style') {
      $s = isset($raw['style']) && is_array($raw['style']) ? $raw['style'] : array();
      $out['style'] = array(
        'overlay_bg'     => sanitize_text_field($s['overlay_bg'] ?? ''),
        'overlay_blur'   => max(0, min(20, (int)($s['overlay_blur'] ?? 0))),
        'popup_radius'   => max(0, min(40, (int)($s['popup_radius'] ?? 12))),
        'popup_shadow'   => !empty($s['popup_shadow']) ? 1 : 0,
        'close_color'    => sanitize_text_field($s['close_color'] ?? ''),
        'trigger_bg'     => sanitize_text_field($s['trigger_bg'] ?? ''),
        'trigger_text'   => sanitize_text_field($s['trigger_text'] ?? ''),
        'trigger_radius' => max(0, min(40, (int)($s['trigger_radius'] ?? 6))),
        'custom_css'     => wp_strip_all_tags($s['custom_css'] ?? ''),
      );
    }

    update_option('langa_tools_popup_settings', $out);
  }

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

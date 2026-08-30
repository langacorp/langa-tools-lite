<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_settings_page() {
  if (!current_user_can('manage_options')) wp_die('Not allowed');

  $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
  // Backward compat: old 'modules' tab merged into 'general'
  if ($tab === 'modules') $tab = 'general';
  if (!in_array($tab, array('general','data','endpoint','debug','help','test'), true)) $tab = 'general';

  $site_key = (string) get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
  $secret   = (string) get_option(LANGA_TOOLS_OPTION_SECRET, '');

  // Show "saved" notice (from GET param if redirected)
  if (!empty($_GET['saved'])) {
    add_settings_error('langa_tools_client', 'settings_saved', 'Settings saved.', 'updated');
  }

  // Save general settings — handled in admin_init (early, before admin_notices).
  // This block only runs as fallback if admin_init didn't redirect.
  if ($tab === 'general' && !empty($_POST['save_settings']) && empty($_GET['saved'])) {
    check_admin_referer('langa_tools_client_save_settings', 'langa_tools_client_save_settings_nonce');

    $site_key = sanitize_text_field(isset($_POST['site_key']) ? $_POST['site_key'] : '');
    $secret   = sanitize_text_field(isset($_POST['secret']) ? $_POST['secret'] : '');

    update_option(LANGA_TOOLS_OPTION_SERVER_URL, LANGA_TOOLS_FIXED_SERVER_URL);
    update_option(LANGA_TOOLS_OPTION_SITE_KEY, $site_key);
    update_option(LANGA_TOOLS_OPTION_SECRET, $secret);

    // Nuke ALL cached license state
    delete_transient('langa_license_killswitch');
    langa_tools_client_license_clear_last_ok();
    langa_tools_client_license_clear_revoked();

    // Force IMMEDIATE live check → sets killswitch transient to 'valid' or 'blocked'
    // Frontend reads this on the very next page load — no 72h grace delay.
    if (function_exists('langa_tools_client_license_is_valid')) {
      $new_valid = langa_tools_client_license_is_valid(true);
    }

    // Flush page caches so frontend HTML is regenerated
    if (function_exists('langa_credits_flush_page_caches')) {
      langa_credits_flush_page_caches();
    }

    add_settings_error('langa_tools_client', 'settings_saved', 'Settings saved.', 'updated');
  }

  // Save Data (company/billing/shipping/bank/vCard)
  if ($tab === 'data' && !empty($_POST['save_data_settings'])) {
    check_admin_referer('langa_tools_client_save_data_settings', 'langa_tools_client_save_data_settings_nonce');

    $raw = isset($_POST['site_data']) && is_array($_POST['site_data']) ? wp_unslash($_POST['site_data']) : array();
    $prev = get_option(LANGA_TOOLS_CLIENT_SITE_DATA_OPTION, array());
    if (!is_array($prev)) $prev = array();

    // ── Validate required fields BEFORE saving ──
    $validation_errors = array();
    if (function_exists('langa_tools_client_data_required_fields')) {
      $required = langa_tools_client_data_required_fields();
      foreach ($required as $section => $fields) {
        $sect_data = (isset($raw[$section]) && is_array($raw[$section])) ? $raw[$section] : array();
        foreach ($fields as $key => $label) {
          if (trim((string)($sect_data[$key] ?? '')) === '') {
            $validation_errors[] = $label;
          }
        }
      }
    }

    if (!empty($validation_errors)) {
      // Save anyway (preserve partial input) but show error
      if (function_exists('langa_tools_client_site_data_sanitize')) {
        $out = langa_tools_client_site_data_sanitize($raw, $prev);
      } else {
        $out = $raw;
      }
      update_option(LANGA_TOOLS_CLIENT_SITE_DATA_OPTION, $out, false);

      $count = count($validation_errors);
      add_settings_error('langa_tools_client', 'data_incomplete',
        'Data saved but <strong>' . $count . ' required field' . ($count > 1 ? 's are' : ' is') . ' still empty</strong>. Modules remain disabled until all fields are filled: ' . esc_html(implode(', ', array_slice($validation_errors, 0, 5))) . ($count > 5 ? ' …and ' . ($count - 5) . ' more' : '') . '.',
        'error'
      );
    } else {
      if (function_exists('langa_tools_client_site_data_sanitize')) {
        $out = langa_tools_client_site_data_sanitize($raw, $prev);
      } else {
        $out = $raw;
      }
      update_option(LANGA_TOOLS_CLIENT_SITE_DATA_OPTION, $out, false);
      add_settings_error('langa_tools_client', 'data_saved', 'Data saved. All required fields are complete.', 'updated');
    }
    // Reset static cache so banner reads fresh data after save
    if (function_exists('langa_tools_client_data_complete')) {
      langa_tools_client_data_complete(true);
    }
  }

  // Show saved notice for Data tab



// Thank You page save
if ($tab === 'endpoint' && !empty($_POST['save_thankyou'])) {
  check_admin_referer('langa_tools_save_thankyou', 'langa_thankyou_nonce');
  $ty = isset($_POST['langa_thankyou_url']) ? esc_url_raw(trim(wp_unslash($_POST['langa_thankyou_url']))) : '';
  update_option('langa_tools_thankyou_url', $ty, false);
  add_settings_error('langa_tools_client', 'thankyou_saved', 'Thank You page saved.', 'updated');
}

// Save Invio (Server) settings (SMTP/From)
if ($tab === 'endpoint' && !empty($_POST['save_mail_settings'])) {
  check_admin_referer('langa_tools_client_save_mail_settings', 'langa_tools_client_save_mail_settings_nonce');

  $raw = isset($_POST['mail']) && is_array($_POST['mail']) ? wp_unslash($_POST['mail']) : array();
  $out = langa_tools_client_mail_defaults();

  $out['enabled'] = !empty($raw['enabled']) ? 1 : 0;
  $out['from_email'] = sanitize_email((string)($raw['from_email'] ?? ''));
  $out['from_name']  = sanitize_text_field((string)($raw['from_name'] ?? ''));

  $out['reply_to']    = sanitize_email((string)($raw['reply_to'] ?? ''));
  $out['return_path'] = sanitize_email((string)($raw['return_path'] ?? ''));
  $out['force_from']  = !empty($raw['force_from']) ? 1 : 0;

  $smtp = isset($raw['smtp']) && is_array($raw['smtp']) ? $raw['smtp'] : array();
  $out['smtp']['enabled']  = !empty($smtp['enabled']) ? 1 : 0;
  $out['smtp']['host']     = sanitize_text_field((string)($smtp['host'] ?? ''));
  $out['smtp']['port']     = (int)($smtp['port'] ?? 587);
  if ($out['smtp']['port'] < 1 || $out['smtp']['port'] > 65535) $out['smtp']['port'] = 587;
  $sec = sanitize_key((string)($smtp['secure'] ?? 'tls'));
  if (!in_array($sec, array('none','ssl','tls'), true)) $sec = 'tls';
  $out['smtp']['secure'] = $sec;
  $out['smtp']['auth']     = !empty($smtp['auth']) ? 1 : 0;
  $out['smtp']['username'] = sanitize_text_field((string)($smtp['username'] ?? ''));
  $out['smtp']['password'] = (string)($smtp['password'] ?? '');
  $out['smtp']['allow_self_signed'] = !empty($smtp['allow_self_signed']) ? 1 : 0;

  update_option(LANGA_TOOLS_CLIENT_MAIL_OPTION, $out, false);
  add_settings_error('langa_tools_client', 'mail_saved', 'Email settings saved.', 'updated');
}

// Debug mode toggle + clear log
if ($tab === 'debug' && !empty($_POST['save_debug_settings'])) {
  check_admin_referer('langa_tools_client_save_debug', 'langa_tools_client_save_debug_nonce');
  $debug_on = !empty($_POST['debug_mode']) ? 1 : 0;
  update_option(LANGA_DEBUG_MODE_OPTION, $debug_on, false);
  add_settings_error('langa_tools_client', 'debug_saved', 'Debug settings saved.', 'updated');
}
// Dev Bypass toggle
if ($tab === 'debug' && !empty($_POST['save_devbypass'])) {
  check_admin_referer('langa_tools_save_devbypass', 'langa_devbypass_nonce');
  $pw = isset($_POST['dev_pw']) ? trim(wp_unslash($_POST['dev_pw'])) : '';
  if ($pw === 'Luca2026') {
    $current = (int) get_option('langa_tools_dev_bypass', 0);
    update_option('langa_tools_dev_bypass', $current ? 0 : 1, false);
    $msg = $current ? 'Guard ON — site protection active.' : 'Guard OFF — safe to deactivate plugin.';
    add_settings_error('langa_tools_client', 'dev_ok', $msg, 'updated');
  } else {
    add_settings_error('langa_tools_client', 'dev_err', 'Wrong password.', 'error');
  }
}
if ($tab === 'debug' && !empty($_POST['clear_debug_log'])) {
  check_admin_referer('langa_tools_client_save_debug', 'langa_tools_client_save_debug_nonce');
  if (function_exists('langa_tools_client_debug_clear_log')) langa_tools_client_debug_clear_log();
  add_settings_error('langa_tools_client', 'debug_cleared', 'Log svuotato.', 'updated');
}


// Send test email (Invio Server) — multi-pipeline with SMTP debug
if ($tab === 'endpoint' && !empty($_POST['send_test_mail'])) {
  check_admin_referer('langa_tools_client_send_test_mail', 'langa_tools_client_send_test_mail_nonce');

  $to = sanitize_email((string)($_POST['test_to'] ?? ''));
  if (!is_email($to)) {
    add_settings_error('langa_tools_client', 'mail_test_fail', 'Email test non valida.', 'error');
  } else {
    $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $ms = function_exists('langa_tools_client_mail_get_settings') ? langa_tools_client_mail_get_settings() : array();
    $is_on = !empty($ms['enabled']);
    $smtp_on = !empty($ms['smtp']) && is_array($ms['smtp']) && !empty($ms['smtp']['enabled']);

    $test_types = isset($_POST['test_types']) && is_array($_POST['test_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['test_types'])) : array('generic');
    $results = array();
    $smtp_log = '';

    // Hook: capture SMTP debug output during test sends
    $smtp_debug_hook = function($phpmailer) use (&$smtp_log) {
      $phpmailer->SMTPDebug = 2;
      $phpmailer->Debugoutput = function($str) use (&$smtp_log) {
        $smtp_log .= $str . "\n";
      };
    };

    foreach ($test_types as $ttype) {
      $ttype = sanitize_key($ttype);
      $label = '';
      $subject = '';
      $title = '';
      $content = '';
      $vars = array(
        'site' => $site,
        'page_url' => home_url('/'),
        'sender_name' => 'John Doe',
        'sender_email' => 'mario.rossi@example.com',
      );

      $ts = date('H:i');

      switch ($ttype) {
        case 'forms':
          $label = 'Forms';
          $vars['preset'] = 'Contatto';
          $vars['module_badge'] = 'FORMS';
          $vars['module_badge_bg'] = '#dbeafe';
          $vars['module_badge_color'] = '#1e40af';
          $subject = 'Forms — New request from ' . $site;
          $title = 'Nuova richiesta (Forms) — Contatto';
          if (function_exists('langa_tools_client_mail_tpl_text')) {
            $subject = langa_tools_client_mail_tpl_text('forms', 'subject', $subject, $vars);
            $title = langa_tools_client_mail_tpl_text('forms', 'title', $title, $vars);
          }
          $content = '<p>Hai ricevuto una nuova richiesta dal sito <strong>' . esc_html($site) . '</strong>.</p>';
          $content .= '<table style="border-collapse:collapse;width:100%;max-width:620px">';
          $content .= '<tr><td style="padding:8px 10px;border:1px solid #e5e5e7;background:#fafafa;width:140px"><strong>Name</strong></td><td style="padding:8px 10px;border:1px solid #e5e5e7">John Doe</td></tr>';
          $content .= '<tr><td style="padding:8px 10px;border:1px solid #e5e5e7;background:#fafafa"><strong>Email</strong></td><td style="padding:8px 10px;border:1px solid #e5e5e7">mario.rossi@example.com</td></tr>';
          $content .= '<tr><td style="padding:8px 10px;border:1px solid #e5e5e7;background:#fafafa"><strong>Phone</strong></td><td style="padding:8px 10px;border:1px solid #e5e5e7">+39 333 1234567</td></tr>';
          $content .= '<tr><td style="padding:8px 10px;border:1px solid #e5e5e7;background:#fafafa"><strong>Messaggio</strong></td><td style="padding:8px 10px;border:1px solid #e5e5e7">Buongiorno, vorrei informazioni sui vostri servizi. Grazie.</td></tr>';
          $content .= '<tr><td style="padding:8px 10px;border:1px solid #e5e5e7;background:#fafafa"><strong>Pagina</strong></td><td style="padding:8px 10px;border:1px solid #e5e5e7"><a href="' . esc_url(home_url('/contatti/')) . '">' . esc_html(home_url('/contatti/')) . '</a></td></tr>';
          $content .= '</table>';
          $content .= '<p style="margin-top:14px;font-size:12px;color:#86868b">⏱ Test Forms — ' . esc_html($ts) . '</p>';
          break;

        case 'bc':
          $label = 'Business Card';
          $vars['preset'] = 'BC';
          $vars['who'] = 'Staff Test';
          $vars['module_badge'] = 'BC';
          $vars['module_badge_bg'] = '#fce7f3';
          $vars['module_badge_color'] = '#9d174d';
          $subject = 'BC — Nuova richiesta contatto';
          $title = 'Nuova richiesta (BC)';
          if (function_exists('langa_tools_client_mail_tpl_text')) {
            $subject = langa_tools_client_mail_tpl_text('bc', 'subject', $subject, $vars);
            $title = langa_tools_client_mail_tpl_text('bc', 'title', $title, $vars);
          }
          $content = '<p><strong>Pagina:</strong> <a href="' . esc_url(home_url('/bc/staff-test')) . '">' . esc_html(home_url('/bc/staff-test')) . '</a></p>';
          $content .= '<p><strong>Destinazione:</strong> Staff Test</p>';
          $content .= '<p><strong>Email:</strong> mario.rossi@example.com</p>';
          $content .= '<p><strong>Phone:</strong> +39 333 1234567</p>';
          $content .= '<p><strong>Messaggio:</strong><br>Buongiorno, ho visto la vostra Business Card e vorrei fissare un appuntamento.</p>';
          $content .= '<p style="margin-top:14px;font-size:12px;color:#86868b">⏱ Test BC — ' . esc_html($ts) . '</p>';
          break;

        case 'maintenance':
          $label = 'Maintenance';
          $vars['preset'] = 'Maintenance';
          $vars['who'] = 'John Doe';
          $vars['module_badge'] = 'MANUTENZIONE';
          $vars['module_badge_bg'] = '#fef3c7';
          $vars['module_badge_color'] = '#c56200';
          $subject = 'Maintenance — Nuovo contatto dal sito';
          $title = 'Nuovo contatto (Maintenance)';
          if (function_exists('langa_tools_client_mail_tpl_text')) {
            $subject = langa_tools_client_mail_tpl_text('maintenance', 'subject', $subject, $vars);
            $title = langa_tools_client_mail_tpl_text('maintenance', 'title', $title, $vars);
          }
          $content = '<p><strong>Name:</strong> John Doe</p>';
          $content .= '<p><strong>Email:</strong> mario.rossi@example.com</p>';
          $content .= '<p><strong>Phone:</strong> +39 333 1234567</p>';
          $content .= '<p><strong>Messaggio:</strong><br>Hello, the site seems to be in maintenance. When will it be available again?</p>';
          $content .= '<p style="margin-top:14px;font-size:12px;color:#86868b">⏱ Test Maintenance — ' . esc_html($ts) . '</p>';
          break;

        case 'credits':
          $label = 'Credits';
          $vars['preset'] = 'Credits';
          $vars['module_badge'] = 'Credits';
          $vars['module_badge_bg'] = '#fef3c7';
          $vars['module_badge_color'] = '#c56200';
          $vars['accent_color'] = function_exists('langa_credits_primary_color') ? langa_credits_primary_color() : '#f37f0d';
          $subject = 'Credits — John Doe — ' . $site;
          $title = $subject;
          $content = '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;width:120px;">Name</td><td style="padding:6px 10px;">John Doe</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Company</td><td style="padding:6px 10px;">Acme Corp</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Email</td><td style="padding:6px 10px;"><a href="mailto:mario.rossi@example.com">mario.rossi@example.com</a></td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Phone</td><td style="padding:6px 10px;">+39 333 1234567</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Location</td><td style="padding:6px 10px;">Milan</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Services</td><td style="padding:6px 10px;">Showcase website, SEO optimization</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Appointment</td><td style="padding:6px 10px;">' . esc_html(date('Y-m-d')) . ' 10:00</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Notes</td><td style="padding:6px 10px;">Test deliverability — Credits module.</td></tr>'
            . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Origin</td><td style="padding:6px 10px;"><a href="' . esc_url(home_url('/')) . '">' . esc_html(home_url('/')) . '</a></td></tr>'
            . '</table>';
          $content .= '<p style="margin-top:14px;font-size:12px;color:#86868b">⏱ Test Credits — ' . esc_html($ts) . '</p>';
          break;

        default:
          $label = 'Generico (wp_mail)';
          $vars['preset'] = 'Generic';
          $vars['module_badge'] = 'SISTEMA';
          $vars['module_badge_bg'] = '#f5f5f7';
          $vars['module_badge_color'] = '#1d1d1f';
          $subject = 'Test email — ' . $site;
          $title = 'Test email';
          $content = '<p>Test di invio generico tramite <strong>wp_mail()</strong>.</p>';
          $content .= '<p>Delivery: ' . ($is_on ? '<strong style="color:#1b5e20">ON</strong>' : '<span style="color:#b71c1c">OFF</span>');
          $content .= ' · SMTP: ' . ($smtp_on ? '<strong style="color:#1b5e20">ON</strong>' : '<span style="color:#b71c1c">OFF</span>') . '</p>';
          $content .= '<p style="margin-top:14px;font-size:12px;color:#86868b">⏱ Test generico — ' . esc_html($ts) . '</p>';
          break;
      }

      $ok = false;
      $err = '';
      delete_transient('langa_tools_client_mail_last_error');

      // Enable SMTP debug capture for first test only (avoid noise)
      $capture_smtp = (empty($smtp_log));
      if ($capture_smtp) add_action('phpmailer_init', $smtp_debug_hook, 99999);

      if (function_exists('langa_tools_client_mail_send')) {
        $ok = (bool) langa_tools_client_mail_send(array(
          'to' => $to,
          'subject' => $subject,
          'title' => $title,
          'content_html' => $content,
          'reply_to' => $to,
          'vars' => $vars,
        ));
      } else {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $ok = (bool) wp_mail($to, $subject, $content, $headers);
      }

      if ($capture_smtp) remove_action('phpmailer_init', $smtp_debug_hook, 99999);

      if (!$ok) {
        $e = function_exists('langa_tools_client_mail_get_last_error') ? langa_tools_client_mail_get_last_error() : array();
        $err = !empty($e['msg']) ? (string)$e['msg'] : 'wp_mail returned false';
      }

      $results[] = array('type' => $ttype, 'label' => $label, 'ok' => $ok, 'error' => $err);

      // Credits: also send client confirmation (mirrors real handler behavior)
      if ($ttype === 'credits' && $ok && function_exists('langa_tools_client_mail_send_confirmation')) {
        usleep(800000);
        $c_color = function_exists('langa_credits_primary_color') ? langa_credits_primary_color() : '#f37f0d';
        $c_content = '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
          . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;width:120px;">Name</td><td style="padding:6px 10px;">John Doe</td></tr>'
          . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Company</td><td style="padding:6px 10px;">Acme Corp</td></tr>'
          . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Email</td><td style="padding:6px 10px;">mario.rossi@example.com</td></tr>'
          . '<tr><td style="padding:6px 10px;font-weight:600;color:#374151;">Services</td><td style="padding:6px 10px;">Showcase website, SEO optimization</td></tr>'
          . '</table>';
        langa_tools_client_mail_send_confirmation(array(
          'to'           => $to,
          'sender_name'  => 'John Doe',
          'module'       => 'credits',
          'module_label' => 'Credits',
          'site'         => $site,
          'summary_html' => $c_content,
          'accent_color' => $c_color,
        ));
      }

      // Small pause between sends to avoid Gmail rate-limiting/grouping
      if ($ok) usleep(800000); // 0.8s
    }

    // Store results + SMTP log
    set_transient('langa_tools_mail_test_results', array(
      'to' => $to,
      'results' => $results,
      'ts' => time(),
      'smtp_log' => mb_substr($smtp_log, 0, 8000),
      'invio_on' => $is_on,
      'smtp_on' => $smtp_on,
      'smtp_host' => (string)($ms['smtp']['host'] ?? ''),
      'smtp_user' => (string)($ms['smtp']['username'] ?? ''),
      'from' => (string)($ms['from_email'] ?? ''),
    ), 300);

    $all_ok = true;
    foreach ($results as $r) { if (!$r['ok']) { $all_ok = false; break; } }

    if ($all_ok) {
      add_settings_error('langa_tools_client', 'mail_test_ok', 'All tests sent successfully to ' . esc_html($to) . '. Check your inbox.', 'updated');
    } else {
      $fails = array();
      foreach ($results as $r) { if (!$r['ok']) $fails[] = $r['label'] . ($r['error'] ? ': ' . $r['error'] : ''); }
      add_settings_error('langa_tools_client', 'mail_test_fail2', 'Alcuni test falliti: ' . esc_html(implode(' | ', $fails)), 'error');
    }
  }
}

  settings_errors('langa_tools_client');

  // Main menu slug is "langa-tools-client" (Settings). Keep links stable.
  $base_url = admin_url('admin.php?page=langa-tools-client-settings');
  echo '<div class="wrap">';
  echo '<h1>LANGA Tools Lite — Settings</h1>';

  // Tabs
  echo '<h2 class="nav-tab-wrapper">';
  echo '<a class="nav-tab '.($tab==='general'?'nav-tab-active':'').'" href="'.esc_url($base_url.'&tab=general').'">General</a>';
  echo '<a class="nav-tab '.($tab==='data'?'nav-tab-active':'').'" href="'.esc_url($base_url.'&tab=data').'">Data</a>';
  echo '<a class="nav-tab '.($tab==='endpoint'?'nav-tab-active':'').'" href="'.esc_url($base_url.'&tab=endpoint').'">Email (Server)</a>';
  echo '<a class="nav-tab '.($tab==='debug'?'nav-tab-active':'').'" href="'.esc_url($base_url.'&tab=debug').'">Debug</a>';
  echo '<a class="nav-tab '.($tab==='test'?'nav-tab-active':'').'" href="'.esc_url($base_url.'&tab=test').'">Site Health</a>';
  echo '<a class="nav-tab '.($tab==='help'?'nav-tab-active':'').'" href="'.esc_url($base_url.'&tab=help').'">Help</a>';
  echo '</h2>';

  if ($tab === 'general') {
    langa_tools_client_settings_tab_general($site_key, $secret);
  } elseif ($tab === 'data') {
    if (function_exists('langa_tools_client_settings_tab_data')) {
      langa_tools_client_settings_tab_data();
    } else {
      echo '<div class="notice notice-error"><p>Missing Data tab renderer.</p></div>';
    }
  } elseif ($tab === 'endpoint') {
    langa_tools_client_settings_tab_endpoint();
  } elseif ($tab === 'debug') {
    langa_tools_client_settings_tab_debug();
  } elseif ($tab === 'test') {
    langa_tools_client_settings_tab_test();
  } else {
    langa_tools_client_settings_tab_help();
  }

  echo '</div>';
}

/** MODULE SAVE HANDLER (prevents blank page) */
/* =========================================================
 * SETTINGS TABS
 * ======================================================= */

function langa_tools_client_settings_tab_general($site_key, $secret) {

  echo '<form method="post">';
  wp_nonce_field('langa_tools_client_save_settings', 'langa_tools_client_save_settings_nonce');

  // Header: license status
  $status = 'invalid';
  $reason = '';
  $http = 0;
  $body = '';

  if ($site_key && $secret) {
    $force = !empty($_POST['check_license_now']);
    if ($force) {
      check_admin_referer('langa_tools_client_check_license', 'langa_tools_client_check_license_nonce');
      delete_transient('langa_license_killswitch');
    }
    $r = langa_tools_client_validate_credentials($site_key, $secret);
    $status = isset($r['status']) ? (string)$r['status'] : 'invalid';
    $reason = isset($r['reason']) ? (string)$r['reason'] : '';
    $http   = isset($r['http']) ? (int)$r['http'] : 0;
    $body   = isset($r['body']) ? (string)$r['body'] : '';

    // Sync result into kill-switch cache + license_last so the rest of admin reflects it.
    $lic_ok = ($status === 'valid');
    set_transient('langa_license_killswitch', $lic_ok ? 'valid' : 'blocked', 600);
    if (function_exists('langa_tools_client_license_store_last')) {
      langa_tools_client_license_store_last(array('ok' => $lic_ok, 'status' => $status, 'reason' => $reason, 'http' => $http, 'error' => ''));
    }
    // When server explicitly says INVALID, kill grace period data.
    // Grace period is only for server-unreachable scenarios.
    if (!$lic_ok && function_exists('langa_tools_client_license_clear_last_ok')) {
      langa_tools_client_license_clear_last_ok();
    }
  }

  $ok = ($status === 'valid');
  $dev_bypass = langa_tools_client_dev_bypass_active();
  $ok_for_health = $ok || $dev_bypass; // bypass makes modules "unlocked" for health checks

  echo '<div style="display:flex;align-items:center;gap:12px;margin:14px 0 10px 0;font-size:14px;">';
  if ($ok) {
    echo '<span style="font-weight:600;color:#1b5e20;">&#10003; License VALID</span>';
  } elseif ($dev_bypass) {
    echo '<span style="font-weight:600;color:#b71c1c;">License BYPASS</span>';
  } else {
    echo '<span style="font-weight:600;color:#b71c1c;">&#10007; License INVALID</span>';
  }
  echo '<span style="font-size:12px;color:#6e6e73">v' . esc_html(defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '?') . '</span>';
  echo '</div>';

  // ─── HEALTH DASHBOARD ─────────────────────────────────
  $base_url = admin_url('admin.php?page=langa-tools-client-settings');
  $health_rows = array();

  // 1. License
  $is_revoked = function_exists('langa_tools_client_license_is_revoked') && langa_tools_client_license_is_revoked();
  $lic_detail = $ok ? 'Valid' : ('Invalid' . ($reason ? ' — ' . $reason : ''));
  if ($dev_bypass && !$ok) $lic_detail = '<strong style="color:#b71c1c">BYPASS</strong>';
  if ($is_revoked) $lic_detail = '<strong style="color:#b71c1c">REVOKED</strong>' . ($reason ? ' — ' . esc_html($reason) : '');
  $health_rows[] = array(
    'label' => 'License',
    'status' => $ok ? 'ok' : ($dev_bypass ? 'warn' : 'fail'),
    'detail' => $lic_detail,
    'link'   => '',
  );

  // 1b. Domain binding
  $last_ok_data = function_exists('langa_tools_client_license_get_last_ok') ? langa_tools_client_license_get_last_ok() : array();
  $bound_domain = isset($last_ok_data['domain']) ? (string) $last_ok_data['domain'] : '';
  $local_domain = function_exists('langa_tools_client_license_get_domain') ? langa_tools_client_license_get_domain() : '';
  if ($bound_domain !== '') {
    $domain_match = ($bound_domain === $local_domain || (strpos($bound_domain, '*.') === 0 && (substr($local_domain, -(strlen(substr($bound_domain, 2)) + 1)) === '.' . substr($bound_domain, 2) || $local_domain === substr($bound_domain, 2))));
    $health_rows[] = array(
      'label' => 'Domain',
      'status' => $domain_match ? 'ok' : 'fail',
      'detail' => esc_html($local_domain) . ($domain_match ? '' : ' ≠ ' . esc_html($bound_domain)),
      'link'   => '',
    );
  }

  // 1c. Grace period status (if server unreachable)
  if (!$ok && !$is_revoked && !empty($last_ok_data['time'])) {
    $grace_age = time() - (int) $last_ok_data['time'];
    $grace_max = defined('LANGA_LICENSE_GRACE_DEFAULT') ? LANGA_LICENSE_GRACE_DEFAULT : 259200;
    $grace_remaining = max(0, $grace_max - $grace_age);
    if ($grace_remaining > 0) {
      $health_rows[] = array(
        'label' => 'Grace period',
        'status' => 'warn',
        'detail' => 'Active — expires in ' . round($grace_remaining / 3600) . 'h',
        'link'   => '',
      );
    }
  }

  // 2. Data completeness
  if (function_exists('langa_tools_client_data_complete')) {
    $data_ok = langa_tools_client_data_complete();
    $missing_count = $data_ok ? 0 : count(langa_tools_client_data_missing_fields());
    $health_rows[] = array(
      'label'  => 'Site Data',
      'status' => $data_ok ? 'ok' : 'fail',
      'detail' => $data_ok ? 'Complete' : $missing_count . ' required field' . ($missing_count > 1 ? 's' : '') . ' missing',
      'link'   => $data_ok ? '' : $base_url . '&tab=data',
    );
  }

  // 3. Endpoint (SMTP / mail)
  $mail_s = function_exists('langa_tools_client_mail_get_settings') ? langa_tools_client_mail_get_settings() : array();
  $smtp_host = (string)($mail_s['smtp']['host'] ?? '');
  $mail_enabled = !empty($mail_s['enabled']);
  if ($mail_enabled && $smtp_host !== '') {
    $health_rows[] = array('label' => 'Email Delivery (SMTP)', 'status' => 'ok', 'detail' => $smtp_host, 'link' => $base_url . '&tab=endpoint');
  } elseif ($mail_enabled) {
    $health_rows[] = array('label' => 'Email Delivery', 'status' => 'warn', 'detail' => 'Active but no SMTP configured (uses wp_mail default)', 'link' => $base_url . '&tab=endpoint');
  } else {
    $health_rows[] = array('label' => 'Email Delivery', 'status' => 'fail', 'detail' => 'Disabled', 'link' => $base_url . '&tab=endpoint');
  }

  // 3. Last ping latency (from license_last cache)
  $lic_last = function_exists('langa_tools_client_license_last') ? langa_tools_client_license_last() : array();
  $last_ts = isset($lic_last['checked_at']) ? (int)$lic_last['checked_at'] : 0;
  if ($last_ts > 0) {
    $age = time() - $last_ts;
    $ago = $age < 120 ? $age . 's fa' : round($age / 60) . 'min fa';
    $last_ok = !empty($lic_last['ok']);
    $health_rows[] = array('label' => 'Last server check', 'status' => $last_ok ? 'ok' : 'warn', 'detail' => $ago . ' — HTTP ' . (int)($lic_last['http'] ?? 0), 'link' => '');
  } else {
    $health_rows[] = array('label' => 'Last server check', 'status' => 'warn', 'detail' => 'No recent check', 'link' => '');
  }

  // 4. Modules enabled count — use registry as source of truth
  $reg_all = function_exists('langa_tools_client_features_registry') ? langa_tools_client_features_registry() : array();
  $enabled_count = 0;
  $total_count = count($reg_all);
  foreach ($reg_all as $mk => $mv) {
    $cfg = function_exists('langa_tools_client_feature_is_config_enabled')
      ? langa_tools_client_feature_is_config_enabled($mk) : 0;
    if ($cfg) $enabled_count++;
  }
  $bridge_on = function_exists('langa_tools_client_feature_is_config_enabled')
    ? (bool)langa_tools_client_feature_is_config_enabled('bridge') : false;
  if (!$ok_for_health) {
    // No license — only free modules (bridge) count as active
    $free_count = $bridge_on ? 1 : 0;
    $health_rows[] = array('label' => 'Active modules', 'status' => $free_count > 0 ? 'warn' : 'fail', 'detail' => $free_count . '/' . $total_count . ' — PRO modules locked (Events is free)', 'link' => $base_url . '&tab=general#langa-modules');
  } else {
    $health_rows[] = array('label' => 'Active modules', 'status' => $enabled_count > 0 ? 'ok' : 'warn', 'detail' => $enabled_count . '/' . $total_count, 'link' => $base_url . '&tab=general#langa-modules');
  }

  // 5. Forms pipeline
  if (!$ok_for_health) {
    $health_rows[] = array('label' => 'Forms', 'status' => 'fail', 'detail' => 'Locked — invalid license', 'link' => '');
  } else {
  $forms_cfg = function_exists('langa_tools_client_feature_is_config_enabled') ? langa_tools_client_feature_is_config_enabled('forms') : 0;
  $forms_s = get_option('langa_tools_forms_settings', array());
  $forms_recip = trim((string)($forms_s['recipient'] ?? ''));
  $global_recip = function_exists('langa_tools_client_mail_get_primary_recipient') ? (string)langa_tools_client_mail_get_primary_recipient() : '';
  if (!$forms_cfg) {
    $health_rows[] = array('label' => 'Forms', 'status' => 'warn', 'detail' => 'Disabled', 'link' => admin_url('admin.php?page=langa-tools-client-forms'));
  } elseif ($forms_recip === '' && $global_recip === '') {
    $health_rows[] = array('label' => 'Forms', 'status' => 'warn', 'detail' => 'Active but no recipient', 'link' => admin_url('admin.php?page=langa-tools-client-forms'));
  } elseif ($forms_recip === '') {
    $health_rows[] = array('label' => 'Forms', 'status' => 'ok', 'detail' => 'Active → fallback: ' . esc_html($global_recip), 'link' => admin_url('admin.php?page=langa-tools-client-forms'));
  } else {
    $health_rows[] = array('label' => 'Forms', 'status' => 'ok', 'detail' => 'Active → ' . esc_html($forms_recip), 'link' => '');
  }
  }

  // 6. BC Main pipeline
  if (!$ok_for_health) {
    $health_rows[] = array('label' => 'BC Main', 'status' => 'fail', 'detail' => 'Locked — invalid license', 'link' => '');
  } else {
  $bc_cfg = function_exists('langa_tools_client_feature_is_config_enabled') ? langa_tools_client_feature_is_config_enabled('bc') : 0;
  $bc_s = get_option('langa_tools_bc_settings', array());
  $bc_main = isset($bc_s['main']) && is_array($bc_s['main']) ? $bc_s['main'] : array();
  $bc_quote = (string)($bc_main['quote_to'] ?? '');
  if (!$bc_cfg) {
    $health_rows[] = array('label' => 'BC Main', 'status' => 'warn', 'detail' => 'Disabled', 'link' => admin_url('admin.php?page=langa-tools-client-bc'));
  } elseif ($bc_quote === '') {
    $health_rows[] = array('label' => 'BC Main', 'status' => 'warn', 'detail' => 'Active but no email recipient', 'link' => admin_url('admin.php?page=langa-tools-client-bc'));
  } else {
    $health_rows[] = array('label' => 'BC Main', 'status' => 'ok', 'detail' => 'Active → ' . esc_html($bc_quote), 'link' => '');
  }
  }

  // 7. Maintenance
  if (!$ok_for_health) {
    $health_rows[] = array('label' => 'Maintenance', 'status' => 'fail', 'detail' => 'Locked — invalid license', 'link' => '');
  } else {
  $ax_s = get_option('langa_tools_adminux_settings', array());
  $maint_on = !empty($ax_s['maintenance']);
  $maint_recip = (string)($ax_s['maintenance_recipient'] ?? '');
  if (!$maint_on) {
    $health_rows[] = array('label' => 'Maintenance', 'status' => 'warn', 'detail' => 'Disabled', 'link' => admin_url('admin.php?page=langa-tools-client-ui-ux&tab=maintenance'));
  } elseif ($maint_recip === '') {
    $health_rows[] = array('label' => 'Maintenance', 'status' => 'warn', 'detail' => 'Active but no dedicated recipient', 'link' => admin_url('admin.php?page=langa-tools-client-ui-ux&tab=maintenance'));
  } else {
    $health_rows[] = array('label' => 'Maintenance', 'status' => 'ok', 'detail' => 'Active → ' . esc_html($maint_recip), 'link' => '');
  }
  }

  // 8. Events — always free, never locked by license
  $bridge_sharing = function_exists('langa_tools_client_bridge_is_sharing_enabled') ? langa_tools_client_bridge_is_sharing_enabled() : 0;
  $ev_mode = function_exists('langa_tools_client_events_get_mode') ? langa_tools_client_events_get_mode() : 'local';
  if (!$bridge_on) {
    $health_rows[] = array('label' => 'Events (free)', 'status' => 'fail', 'detail' => 'Disabled', 'link' => admin_url('admin.php?page=langa-tools-client-events'));
  } elseif ($ev_mode === 'remote' && $bridge_sharing) {
    $health_rows[] = array('label' => 'Events (free)', 'status' => 'ok', 'detail' => 'Remote Bridge active', 'link' => admin_url('admin.php?page=langa-tools-client-events&tab=bridge'));
  } elseif ($ev_mode === 'remote' && !$bridge_sharing) {
    $health_rows[] = array('label' => 'Events (free)', 'status' => 'warn', 'detail' => 'Remote Bridge but data sharing OFF', 'link' => admin_url('admin.php?page=langa-tools-client-events&tab=bridge'));
  } else {
    $health_rows[] = array('label' => 'Events (free)', 'status' => 'ok', 'detail' => 'Local Events active', 'link' => admin_url('admin.php?page=langa-tools-client-events&tab=events'));
  }

  // ─── TWO COLUMN LAYOUT ─────────────────────────────────
  echo '<div class="langa-row langa-row-8-4" style="align-items:start">';

  // LEFT column
  echo '<div>';

  // Fully blurred License panel — mirrors PRO layout exactly with fake data
  echo '<div style="position:relative;margin:0 0 14px;border:1px solid #e5e5e7;border-radius:12px;overflow:hidden;background:#fff">';
  echo '<div style="padding:12px 16px;border-bottom:1px solid #e5e5e7;background:#fafafa;display:flex;align-items:center;justify-content:space-between">';
  echo '<strong style="font-size:13px;color:#1d1d1f">License — PRO Modules</strong>';
  echo '</div>';
  echo '<div style="position:relative;min-height:260px">';
  echo '<div style="filter:blur(3px);pointer-events:none;user-select:none;opacity:.35">';
  // Credentials section (same as PRO)
  echo '<div style="padding:16px 20px;border-bottom:1px solid #f0f0f0">';
  echo '<table class="form-table" role="presentation" style="margin:0">';
  echo '<tr><th scope="row" style="width:80px;padding:6px 0;font-size:13px">Server</th><td style="padding:6px 0"><code style="font-size:12px">https://tools.langa.tv</code></td></tr>';
  echo '<tr><th scope="row" style="padding:6px 0;font-size:13px">Site Key</th><td style="padding:6px 0"><input type="text" disabled class="regular-text" style="width:100%;font-family:monospace;font-size:12px" value="SITE-KEY-EXAMPLE-0000"></td></tr>';
  echo '<tr><th scope="row" style="padding:6px 0;font-size:13px">Secret</th><td style="padding:6px 0"><input type="text" disabled class="regular-text" style="width:100%;font-family:monospace;font-size:12px" value="SECRET-EXAMPLE-000000000"></td></tr>';
  echo '</table></div>';
  // License info section (same as PRO)
  echo '<div style="padding:16px 20px">';
  echo '<table class="form-table" role="presentation" style="margin:0">';
  echo '<tr><th scope="row" style="width:120px;padding:6px 0;font-size:13px">Status</th><td style="padding:6px 0"><span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600">VALID</span></td></tr>';
  echo '<tr><th scope="row" style="padding:6px 0;font-size:13px">Modules</th><td style="padding:6px 0"><span style="font-size:12px">11 modules licensed</span></td></tr>';
  echo '<tr><th scope="row" style="padding:6px 0;font-size:13px">Expires</th><td style="padding:6px 0"><span style="font-size:12px">01/01/2027</span></td></tr>';
  echo '</table></div>';
  echo '</div>'; // end blur
  // CTA overlay
  echo '<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2">';
  echo '<a href="https://tools.langa.tv/#pricing" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:14px 36px;background:#1d1d1f;color:#fff;font-weight:700;font-size:15px;border-radius:10px;text-decoration:none;box-shadow:0 4px 20px rgba(0,0,0,.25)"><span class="dashicons dashicons-unlock" style="font-size:18px;width:18px;height:18px"></span> Unlock PRO</a>';
  echo '<p style="margin:10px 0 0;font-size:12px;color:#6e6e73">From &euro;4.99/month &middot; All modules included &middot; Cancel anytime</p>';
  echo '</div></div>';
  echo '</div>';

  // ── MODULES (same left column) ──────────────────────────
  echo '<div id="langa-modules" class="langa-card" style="margin:0;padding:0">';
  langa_tools_client_settings_modules_inline();
  echo '</div>';

  echo '</div>'; // end left column

  // RIGHT: Health table
  echo '<div>';

  // Render Health table
  echo '<div class="langa-card" style="margin:0;padding:0;max-width:100%">';
  echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
  echo '<thead><tr style="background:#fafafa;border-bottom:1px solid #e5e5e7">';
  echo '<th style="text-align:left;padding:10px 14px;font-weight:600;font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.3px">Health</th>';
  echo '<th style="text-align:left;padding:10px 14px;font-weight:600;font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.3px">Status</th>';
  echo '<th style="text-align:right;padding:10px 14px;width:80px"></th>';
  echo '</tr></thead><tbody>';

  $badge_map = array(
    'ok'   => '<span class="langa-badge langa-badge--ok">OK</span>',
    'warn' => '<span class="langa-badge langa-badge--warn">WARN</span>',
    'fail' => '<span class="langa-badge langa-badge--fail">FAIL</span>',
  );

  foreach ($health_rows as $hr) {
    $badge = $badge_map[$hr['status']] ?? $badge_map['warn'];
    echo '<tr style="border-bottom:1px solid #f5f5f7">';
    echo '<td style="padding:9px 14px;font-weight:600;white-space:nowrap">' . $badge . ' ' . esc_html($hr['label']) . '</td>';
    echo '<td style="padding:9px 14px;color:#6e6e73">' . $hr['detail'] . '</td>';
    echo '<td style="padding:9px 14px;text-align:right">';
    if ($hr['link'] !== '') echo '<a href="' . esc_url($hr['link']) . '" style="font-size:12px;color:#0071e3;text-decoration:none;white-space:nowrap">Go →</a>';
    echo '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';
  echo '</div>';

  echo '</div>'; // end right column

  echo '</div>'; // end grid row

  echo '<div class="langa-inline-actions">';
  submit_button('Save', 'primary', 'save_settings', false);
  wp_nonce_field('langa_tools_client_check_license', 'langa_tools_client_check_license_nonce');
  submit_button('Check license now', 'secondary', 'check_license_now', false);
  echo '</div>';

  echo '</form>';
}


function langa_tools_client_settings_modules_inline() {
  $features = langa_tools_client_features_registry();
  $license_valid = function_exists('langa_tools_client_license_is_valid') && langa_tools_client_license_is_valid();
  $dev_bypass = langa_tools_client_dev_bypass_active();
  $can_toggle = $license_valid || $dev_bypass;

  // ── Status banner ──
  if (!$license_valid && !$dev_bypass) {
    echo '<p style="margin:0;padding:8px 12px;background:#fce4ec;border-radius:0;font-size:13px;color:#b71c1c;"><span class="dashicons dashicons-lock" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span> <strong>License required</strong> — <a href="https://tools.langa.tv/#pricing" target="_blank" style="color:#b71c1c;font-weight:600">Get PRO</a> to unlock modules.</p>';
  }

  echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
  echo '<thead><tr style="background:#fafafa;border-bottom:1px solid #e5e5e7">';
  echo '<th style="text-align:left;padding:8px 14px;font-weight:600;font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.3px">Module</th>';
  echo '<th style="text-align:center;padding:8px 14px;font-weight:600;font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.3px;width:90px">Status</th>';
  echo '<th style="text-align:right;padding:8px 14px;font-weight:600;font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.3px;width:150px">Actions</th>';
  echo '</tr></thead><tbody>';

  foreach ($features as $k => $def) {
    $enabled = function_exists('langa_tools_client_feature_is_config_enabled')
      ? langa_tools_client_feature_is_config_enabled($k)
      : langa_tools_client_feature_is_enabled($k);

    $title = $def['title'] ?? $k;
    $desc  = $def['desc'] ?? '';
    $is_free = !empty($def['free']);
    $mod_can_toggle = $can_toggle || $is_free; // Free modules always toggleable

    echo '<tr style="border-bottom:1px solid #f5f5f7">';
    echo '<td style="padding:9px 14px"><strong>'.esc_html($title).'</strong>';
    if ($is_free) echo ' <span style="background:#dbeafe;color:#1e40af;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:4px">FREE</span>';
    echo ' <span style="color:#86868b;font-size:12px;margin-left:6px">'.esc_html($desc).'</span></td>';

    // Status column — LICENSE status (server, read-only)
    echo '<td style="text-align:center;padding:9px 14px">';
    if ($is_free) {
      echo '<span class="langa-badge langa-badge--ok">FREE</span>';
    } elseif ($license_valid) {
      echo '<span class="langa-badge langa-badge--ok">ON</span>';
    } elseif ($dev_bypass) {
      echo '<span class="langa-badge langa-badge--warn">DEV</span>';
    } else {
      echo '<span class="langa-badge langa-badge--fail">NOT LICENSED</span>';
    }
    echo '</td>';

    echo '<td style="text-align:right;padding:9px 14px">';
    echo '<div style="display:flex;gap:8px;justify-content:flex-end;align-items:center">';

    if ($is_free) {
      echo '<span style="font-size:12px;color:#1b5e20;font-weight:600">Always active</span>';
    } elseif ($mod_can_toggle) {
      $toggle_url = wp_nonce_url(
        admin_url('admin-post.php?action=langa_tools_client_save_module&module=' . urlencode($k) . '&new_active=' . ($enabled ? '0' : '1')),
        'langa_tools_client_save_module_' . $k
      );
      $sw_bg = $enabled ? '#16a34a' : '#d4d4d8';
      $sw_dot = $enabled ? 'calc(100% - 17px)' : '2px';
      echo '<a href="'.esc_url($toggle_url).'" style="display:inline-block;position:relative;width:36px;height:20px;border-radius:10px;background:'.$sw_bg.';cursor:pointer;vertical-align:middle;text-decoration:none;flex-shrink:0" title="'.($enabled?'Disable':'Enable').'">';
      echo '<span style="position:absolute;top:2px;left:'.$sw_dot.';width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2)"></span></a>';
    } else {
      echo '<span class="dashicons dashicons-lock" style="font-size:16px;width:16px;height:16px;color:#d2d2d7;vertical-align:middle;margin-right:2px"></span>';
      echo '<a class="button button-small" href="https://tools.langa.tv/#pricing" target="_blank" style="background:#f37f0d;color:#fff;border-color:#e8930c;font-weight:600;font-size:11px">Get PRO</a>';
    }

    if ($mod_can_toggle || $is_free) {
      $manage = admin_url('admin.php?page=' . langa_tools_client_page_slug($k));
      echo '<a class="button button-small" href="'.esc_url($manage).'">Open</a>';
    }

    echo '</div>';
    echo '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';
}

// Backward compat alias
function langa_tools_client_settings_tab_modules() {
  echo '<div class="langa-card" style="max-width:965px;margin:10px 0">';
  echo '<h3 style="margin:0 0 10px;font-size:14px">Modules</h3>';
  langa_tools_client_settings_modules_inline();
  echo '</div>';
}



function langa_tools_client_settings_tab_data() {
  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('wp-color-picker');
  $opt = get_option(LANGA_TOOLS_CLIENT_SITE_DATA_OPTION, array());
  if (!is_array($opt)) $opt = array();
  $opt = function_exists('langa_tools_client_site_data_defaults') ? wp_parse_args($opt, langa_tools_client_site_data_defaults()) : $opt;

  $company  = (isset($opt['company']) && is_array($opt['company'])) ? $opt['company'] : array();
  $billing  = (isset($opt['billing']) && is_array($opt['billing'])) ? $opt['billing'] : array();
  $shipping = (isset($opt['shipping']) && is_array($opt['shipping'])) ? $opt['shipping'] : array();
  $bank     = (isset($opt['bank']) && is_array($opt['bank'])) ? $opt['bank'] : array();
  // Extra shipping addresses (array of arrays)
  $extra_shipping = (isset($opt['extra_shipping']) && is_array($opt['extra_shipping'])) ? $opt['extra_shipping'] : array();

  echo '<form method="post">';
  wp_nonce_field('langa_tools_client_save_data_settings', 'langa_tools_client_save_data_settings_nonce');

  echo '<h2 style="margin:20px 0 4px">Site Data</h2>';

  // ── Completeness status banner ──
  $is_complete = function_exists('langa_tools_client_data_complete') && langa_tools_client_data_complete();
  if ($is_complete) {
    $wizard_url = admin_url('admin.php?page=langa-tools-client-settings&tab=general');
    echo '<div style="max-width:965px;margin:0 0 14px;padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-left:3px solid #16a34a;border-radius:0 8px 8px 0;font-size:13px">';
    echo '<strong style="color:#166534">Setup complete</strong> — all required fields are filled. <a href="' . esc_url($wizard_url) . '" style="color:#166534;font-weight:600">Continue with the Setup Wizard &rarr;</a>';
    echo '</div>';
  } else {
    $missing = function_exists('langa_tools_client_data_missing_fields') ? langa_tools_client_data_missing_fields() : array();
    $count = count($missing);
    echo '<div style="max-width:965px;margin:0 0 14px;padding:12px 16px;background:#fce4ec;border:1px solid #f8bbd0;border-left:3px solid #b71c1c;border-radius:0 8px 8px 0;font-size:13px">';
    echo '<strong style="color:#b71c1c">Setup incomplete</strong> — ' . $count . ' required field' . ($count !== 1 ? 's' : '') . ' missing. All modules are disabled until complete.';
    if ($count > 0 && $count <= 5) {
      echo ' <span style="color:#6e6e73">' . esc_html(implode(', ', $missing)) . '</span>';
    } elseif ($count > 5) {
      echo ' <span style="color:#6e6e73">' . esc_html(implode(', ', array_slice($missing, 0, 5))) . ' …and ' . ($count - 5) . ' more</span>';
    }
    echo '</div>';
  }

  echo '<p class="description" style="max-width:965px;">Global data reusable across modules (BC, vCard, QR, Legal, email templates…). Fields marked <span style="color:#b71c1c">*</span> are required — modules are disabled until all are filled.</p>';

  // Required fields map for HTML required attribute
  $_req_map = function_exists('langa_tools_client_data_required_fields') ? langa_tools_client_data_required_fields() : array();

  $field = function($section, $k, $lbl, $type = 'text', $val = '', $full = false, $placeholder = '', $extra_name = '') use ($_req_map) {
    $name_attr = $extra_name !== '' ? $extra_name : 'site_data[' . $section . '][' . $k . ']';
    $id = 'sd_' . $section . '_' . $k . ($extra_name ? '_' . md5($extra_name) : '');
    $cls = 'langa-field' . ($full ? ' langa-field--full' : '');
    $is_req = isset($_req_map[$section][$k]);
    $req_attr = $is_req ? ' required' : '';
    $req_star = $is_req ? ' <span style="color:#b71c1c">*</span>' : '';
    echo '<div class="'.esc_attr($cls).'">';
    echo '<label for="'.esc_attr($id).'">'.esc_html($lbl).$req_star.'</label>';
    if ($type === 'textarea') {
      echo '<textarea id="'.esc_attr($id).'" class="large-text" rows="2" name="'.esc_attr($name_attr).'" placeholder="'.esc_attr($placeholder).'"'.$req_attr.'>'.esc_textarea((string)$val).'</textarea>';
    } else {
      echo '<input id="'.esc_attr($id).'" type="'.esc_attr($type).'" class="regular-text" name="'.esc_attr($name_attr).'" value="'.esc_attr((string)$val).'" placeholder="'.esc_attr($placeholder).'"'.$req_attr.' />';
    }
    echo '</div>';
  };

  // ─── AZIENDA ───────────────────────────────────────────
  echo '<div id="langa-data-company" class="langa-card" style="margin-top:12px">';
  echo '<h3 style="margin-top:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#1d1d1f;color:#fff;font-size:13px;font-weight:700;flex-shrink:0">1</span> Company — Data Controller';
  echo '<span style="margin-left:auto;display:flex;gap:4px">';
  echo '<button type="button" class="button button-small" id="langa-export-company" title="Export"><span class="dashicons dashicons-download" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:-1px"></span> Export</button>';
  echo '<button type="button" class="button button-small" id="langa-import-company" title="Import"><span class="dashicons dashicons-upload" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:-1px"></span> Import</button>';
  echo '<input type="file" id="langa-import-company-file" accept=".json,.txt" style="display:none" />';
  echo '</span></h3>';
  echo '<p class="description" style="margin-top:0">Your client\'s company data. Fills automatically into Business Card, Legal pages, vCard, QR code, and email templates. Enter once, use everywhere.</p>';
  echo '<div class="langa-grid-2">';
  $field('company','brand','Brand / Nome pubblico','text',$company['brand'] ?? '', false, 'ACME S.r.l.');
  $field('company','legal_name','Legal name','text',$company['legal_name'] ?? '', false, 'ACME Corp Ltd.');
  $field('company','vat','VAT number','text',$company['vat'] ?? '', false, 'IT01234567890');
  $field('company','sdi','SDI','text',$company['sdi'] ?? '', false, 'M5UXCR1');
  echo '</div>';

  // Address (sub-group)
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Legal address</p>';
  echo '<div class="langa-grid-2">';
  $field('company','address','Address','text',$company['address'] ?? '', true, '123 Main Street');
  $field('company','zip','CAP','text',$company['zip'] ?? '', false, '10121');
  $field('company','city','City','text',$company['city'] ?? '', false, 'Turin');
  $field('company','province','Province','text',$company['province'] ?? '', false, 'TO');
  $field('company','country','Country','text',$company['country'] ?? '', false, 'Italia');
  echo '</div>';
  echo '</div>';

  // Contatti (sub-group)
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Contacts</p>';
  echo '<div class="langa-grid-2">';
  $field('company','phone','Phone','text',$company['phone'] ?? '', false, '+39 011 1234567');
  $field('company','email','Email','email',$company['email'] ?? '', false, 'info@acme.it');
  $field('company','website','Website','url',$company['website'] ?? '', true, 'https://www.acme.it');
  echo '</div>';
  echo '</div>';

  // Bank Details (sub-group inside Company)
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Bank Details</p>';
  echo '<div class="langa-grid-2">';
  $field('bank','holder','Account holder','text',$bank['holder'] ?? '', false, 'ACME Corp Ltd.');
  $field('bank','bank_name','Bank name','text',$bank['bank_name'] ?? '', false, 'Intesa Sanpaolo');
  $field('bank','iban','IBAN','text',$bank['iban'] ?? '', true, 'IT60X0542811101000000123456');
  $field('bank','bic','BIC / SWIFT','text',$bank['bic'] ?? '', true, 'BCITITMM');
  echo '</div>';
  echo '</div>';

  // Shipping (sub-group inside Company)
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Shipping — Main address</p>';
  echo '<div class="langa-grid-2">';
  $field('shipping','name','Recipient','text',$shipping['name'] ?? '', true, 'ACME Corp Ltd. c/o John Doe');
  $field('shipping','address','Address','text',$shipping['address'] ?? '', true, '123 Main Street');
  $field('shipping','zip','CAP','text',$shipping['zip'] ?? '', false, '10121');
  $field('shipping','city','City','text',$shipping['city'] ?? '', false, 'Turin');
  $field('shipping','province','Province','text',$shipping['province'] ?? '', false, 'TO');
  $field('shipping','country','Country','text',$shipping['country'] ?? '', false, 'Italia');
  echo '</div>';
  echo '</div>';

  // Extra shipping addresses
  if (!empty($extra_shipping)) {
    foreach ($extra_shipping as $idx => $addr) {
      if (!is_array($addr)) continue;
      $i = (int) $idx;
      echo '<div class="langa-extra-shipping-block" style="margin-top:10px;padding:12px 14px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;position:relative">';
      echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#e65100">Additional address ' . ($i + 1) . '</p>';
      echo '<label style="position:absolute;top:10px;right:14px;font-size:12px;color:#b71c1c;cursor:pointer"><input type="checkbox" name="site_data[remove_extra_shipping][]" value="' . esc_attr($i) . '" /> Remove</label>';
      echo '<div class="langa-grid-2">';
      $field('extra_shipping','name','Label / Recipient','text',$addr['name'] ?? '', true, 'Warehouse North', 'site_data[extra_shipping][' . $i . '][name]');
      $field('extra_shipping','address','Address','text',$addr['address'] ?? '', true, '456 Industrial Ave', 'site_data[extra_shipping][' . $i . '][address]');
      $field('extra_shipping','zip','CAP','text',$addr['zip'] ?? '', false, '20100', 'site_data[extra_shipping][' . $i . '][zip]');
      $field('extra_shipping','city','City','text',$addr['city'] ?? '', false, 'Milan', 'site_data[extra_shipping][' . $i . '][city]');
      $field('extra_shipping','province','Province','text',$addr['province'] ?? '', false, 'MI', 'site_data[extra_shipping][' . $i . '][province]');
      $field('extra_shipping','country','Country','text',$addr['country'] ?? '', false, 'Italia', 'site_data[extra_shipping][' . $i . '][country]');
      echo '</div>';
      echo '</div>';
    }
  }

  // Add button
  echo '<div style="margin-top:10px">';
  echo '<button type="button" class="button button-small" id="langa-add-shipping">+ Add shipping address</button>';
  echo '</div>';
  echo '</div>'; // close Company card

  // ─── SVILUPPATORE / RESPONSABILE DEL TRATTAMENTO ────────
  $dev = (isset($opt['developer']) && is_array($opt['developer'])) ? $opt['developer'] : array();
  echo '<div class="langa-card" style="margin-top:12px">';
  echo '<h3 style="margin-top:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#1d1d1f;color:#fff;font-size:13px;font-weight:700;flex-shrink:0">2</span> Developer — Data Processor';
  echo '<span style="margin-left:auto;display:flex;gap:4px">';
  echo '<button type="button" class="button button-small" id="langa-copy-company-to-dev" title="Copy from Company"><span class="dashicons dashicons-admin-page" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:-1px"></span> Copy from Company</button>';
  echo '<button type="button" class="button button-small" id="langa-export-dev" title="Export"><span class="dashicons dashicons-download" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:-1px"></span> Export</button>';
  echo '<button type="button" class="button button-small" id="langa-import-dev" title="Import"><span class="dashicons dashicons-upload" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-top:-1px"></span> Import</button>';
  echo '<input type="file" id="langa-import-dev-file" accept=".json,.txt" style="display:none" />';
  echo '</span></h3>';
  echo '<div style="display:flex;gap:12px;align-items:flex-start;margin:0 0 14px;padding:12px 16px;background:linear-gradient(135deg,#f37f0d08,#f37f0d03);border:1px solid #f37f0d22;border-left:3px solid #f37f0d;border-radius:0 10px 10px 0">';
    echo '<span class="dashicons dashicons-shield" style="color:#f37f0d;font-size:18px;width:18px;height:18px;flex-shrink:0;margin-top:2px"></span>';
    echo '<div>';
      echo '<p style="margin:0 0 4px;font-size:13px;color:#1d1d1f;font-weight:600">Your identity on every site you build</p>';
      echo '<p style="margin:0;font-size:12px;color:#6e6e73;line-height:1.5">This data appears in Credits, Custom Login, Legal pages, Business Card, and email footers. It stays visible during maintenance and after site handoff &mdash; ensuring your work is always recognized.</p>';
    echo '</div>';
  echo '</div>';

  echo '<div class="langa-grid-2">';
  $field('developer','brand','Brand / Nome pubblico','text',$dev['brand'] ?? 'LANGA', false, 'LANGA');
  $field('developer','legal_name','Legal name','text',$dev['legal_name'] ?? '', false, 'LANGA S.r.l.');
  $field('developer','vat','VAT number','text',$dev['vat'] ?? '', false, 'IT01234567890');
  $field('developer','email','Email','email',$dev['email'] ?? '', false, 'admin@langa.tv');
  $field('developer','phone','Phone','text',$dev['phone'] ?? '', false, '+39 ...');
  $field('developer','website','Website','url',$dev['website'] ?? 'https://langa.tv/', false, 'https://langa.tv/');
  echo '</div>';

  // Address
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Address</p>';
  echo '<div class="langa-grid-2">';
  $field('developer','address','Address','text',$dev['address'] ?? '', true, 'Street ...');
  $field('developer','zip','CAP','text',$dev['zip'] ?? '', false, '');
  $field('developer','city','City','text',$dev['city'] ?? '', false, '');
  $field('developer','province','Province','text',$dev['province'] ?? '', false, '');
  $field('developer','country','Country','text',$dev['country'] ?? '', false, '');
  echo '</div>';
  echo '</div>';

  // Branding & Credits
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Branding & Credits</p>';
  echo '<div class="langa-grid-2">';
  $field('developer','slogan','Slogan Credits','text',$dev['slogan'] ?? 'Il tool per il web', true, 'Il tool per il web');
  echo '</div>';

  // Logo URL with upload + preview
  $logo_val = $dev['logo_url'] ?? '';
  $logo_default = 'https://about.langa.tv/wp-content/uploads/2024/03/LANGA-logo.webp';
  echo '<div style="margin:10px 0 0">';
    echo '<label style="display:block;font-size:13px;font-weight:500;margin:0 0 4px">Logo URL</label>';
    echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
      echo '<input id="langa-dev-logo" type="url" class="regular-text" name="site_data[developer][logo_url]" value="'.esc_attr($logo_val).'" placeholder="'.esc_attr($logo_default).'" style="flex:1;min-width:280px" />';
      echo '<button type="button" class="button langa-media-upload" data-target="#langa-dev-logo">Upload</button>';
    echo '</div>';
    $logo_show = ($logo_val !== '') ? $logo_val : $logo_default;
    echo '<div style="margin:8px 0 0;display:flex;align-items:center;gap:10px">';
      echo '<img id="langa-dev-logo-preview" src="'.esc_url($logo_show).'" style="max-height:36px;max-width:180px;border:1px solid #e2e8f0;border-radius:4px;padding:2px;background:#fff" />';
      echo '<span class="description">Used by Custom Login, Credits, and Business Card. Default: LANGA logo.</span>';
    echo '</div>';
  echo '</div>';

  // Primary color with WP Color Picker (consistent with other pickers)
  $pc_val = $dev['primary_color'] ?? '#f37f0d';
  echo '<div style="margin:12px 0 0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">';
    echo '<label style="font-size:13px;font-weight:500;min-width:100px">Primary color</label>';
    echo '<input type="text" class="langa-color-field" name="site_data[developer][primary_color]" value="'.esc_attr($pc_val).'" data-default-color="#f37f0d" />';
    echo '<span class="description">Used by Custom Login + Credits.</span>';
  echo '</div>';
  // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin inline JS for immediate DOM manipulation
  echo '<script>';
  echo 'jQuery(function($){$(".langa-color-field").wpColorPicker();';
  echo 'var li=document.getElementById("langa-dev-logo"),lp=document.getElementById("langa-dev-logo-preview");';
  echo 'if(li&&lp){li.addEventListener("change",function(){lp.src=li.value||"'.esc_js($logo_default).'"})}';
  echo '});';
  echo '</script>';
  echo '</div>';

  // Link Credits footer
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Link Credits / Footer</p>';
  echo '<div class="langa-grid-2">';
  $field('developer','privacy_url','Privacy URL','url',$dev['privacy_url'] ?? '', true, 'https://example.com/privacy-policy/');
  $field('developer','terms_url','Terms URL','url',$dev['terms_url'] ?? '', true, 'https://example.com/terms/');
  $about_default = 'https://about.langa.tv/';
  $field('developer','about_url','About URL','url',$dev['about_url'] ?? $about_default, true, $about_default);
  echo '</div>';
  echo '</div>';

  // Credits Services (editable textarea, one per line)
  echo '<div style="margin-top:12px;padding:12px 14px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';
  echo '<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#86868b">Credits services (select, one per line)</p>';
  $svc_val = $dev['credits_services'] ?? "Showcase website\nDynamic website development\neCommerce platform\nCustom web design\nSocial media management\nSEO optimization\nBrand identity\nCreative graphics\nPhoto shoot\nPromo video production\nEmotional video\nStrategic marketing\nOther marketing operations";
  echo '<textarea name="site_data[developer][credits_services]" rows="6" class="large-text" style="font-size:13px" placeholder="One service per line">' . esc_textarea((string)$svc_val) . '</textarea>';
  echo '<p class="description" style="margin-top:6px">These services appear in the Credits form (select step). One service per line.</p>';
  echo '</div>';

  echo '</div>';

  // Save
  echo '<p style="margin-top:14px;">';
  submit_button('Save', 'primary', 'save_data_settings', false);
  echo '</p>';

  echo '</form>';

  // JS: add extra shipping
langtoli_inline_script('
(function(){
    // Add extra shipping
    var addBtn=document.getElementById("langa-add-shipping");
    if(addBtn) addBtn.addEventListener("click",function(){
      var blocks=document.querySelectorAll(".langa-extra-shipping-block");
      var idx=blocks.length;
      var prefix="site_data[extra_shipping]["+idx+"]";
      var fields=[
        {k:"name",l:"Label / Recipient",p:"Warehouse / Branch",full:true},
        {k:"address",l:"Address",p:"Via...",full:true},
        {k:"zip",l:"CAP",p:"",full:false},
        {k:"city",l:"City",p:"",full:false},
        {k:"province",l:"Province",p:"",full:false},
        {k:"country",l:"Country",p:"Italia",full:false}
      ];
      var html=\'<div class="langa-extra-shipping-block" style="margin-top:10px;padding:12px 14px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;position:relative">\';
      html+=\'<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#e65100">Additional address \'+(idx+1)+\'</p>\';
      html+=\'<label style="position:absolute;top:10px;right:14px;font-size:12px;color:#b71c1c;cursor:pointer"><input type="checkbox" name="site_data[remove_extra_shipping][]" value="\'+idx+\'" /> Remove</label>\';
      html+=\'<div class="langa-grid-2">\';
      fields.forEach(function(f){
        var cls="langa-field"+(f.full?" langa-field--full":"");
        html+=\'<div class="\'+cls+\'"><label>\'+f.l+\'</label><input type="text" class="regular-text" name="\'+prefix+"["+f.k+\']" value="" placeholder="\'+f.p+\'" /></div>\';
      });
      html+=\'</div></div>\';
      addBtn.insertAdjacentHTML("beforebegin",html);
    });
  })();
  ');

  // JS: Import / Export / Copy logic
langtoli_inline_script('
(function(){
    // ── Helper: collect all inputs for a section ──
    function collectSection(section){
      var data={};
      var prefix="site_data["+section+"][";
      document.querySelectorAll("input,textarea,select").forEach(function(el){
        if(!el.name||el.name.indexOf(prefix)!==0)return;
        var rest=el.name.substring(prefix.length);
        var key=rest.replace(/\].*$/,"");
        // skip nested keys (contains another [)
        if(rest.indexOf("][")!==-1)return;
        data[key]=el.value||"";
      });
      return data;
    }

    // ── Helper: fill inputs for a section ──
    function fillSection(section,data){
      if(!data||typeof data!=="object")return;
      Object.keys(data).forEach(function(key){
        var name="site_data["+section+"]["+key+"]";
        var el=document.querySelector("[name=\""+name+"\"]");
        if(el){
          el.value=data[key]||"";
          if(el.classList.contains("langa-color-field")&&typeof jQuery!=="undefined"){try{jQuery(el).wpColorPicker("color",data[key]);}catch(e){}}
        }
      });
    }

    // ── Helper: download JSON ──
    function downloadJSON(obj,filename){
      var blob=new Blob([JSON.stringify(obj,null,2)],{type:"application/json"});
      var a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download=filename;a.click();
    }

    // ── Helper: read file as text ──
    function readFile(file,cb){
      var r=new FileReader();r.onload=function(){cb(r.result)};r.readAsText(file);
    }

    // ── COMPANY: Export ──
    var expC=document.getElementById("langa-export-company");
    if(expC) expC.addEventListener("click",function(){
      downloadJSON(collectSection("company"),"company-data.json");
    });

    // ── COMPANY: Import ──
    var impC=document.getElementById("langa-import-company");
    var impCF=document.getElementById("langa-import-company-file");
    if(impC&&impCF){
      impC.addEventListener("click",function(){impCF.click()});
      impCF.addEventListener("change",function(){
        if(!this.files||!this.files[0])return;
        if(!confirm("Import will overwrite all Company fields with data from the file. Continue?"))return;
        readFile(this.files[0],function(txt){
          try{var d=JSON.parse(txt);fillSection("company",d);}
          catch(e){alert("Invalid JSON file.")}
        });
        this.value="";
      });
    }

    // ── DEVELOPER: Export ──
    var expD=document.getElementById("langa-export-dev");
    if(expD) expD.addEventListener("click",function(){
      downloadJSON(collectSection("developer"),"developer-data.json");
    });

    // ── DEVELOPER: Import ──
    var impD=document.getElementById("langa-import-dev");
    var impDF=document.getElementById("langa-import-dev-file");
    if(impD&&impDF){
      impD.addEventListener("click",function(){impDF.click()});
      impDF.addEventListener("change",function(){
        if(!this.files||!this.files[0])return;
        if(!confirm("Import will overwrite all Developer fields with data from the file. Continue?"))return;
        readFile(this.files[0],function(txt){
          try{var d=JSON.parse(txt);fillSection("developer",d);}
          catch(e){alert("Invalid JSON file.")}
        });
        this.value="";
      });
    }

    // ── DEVELOPER: Copy from Company ──
    var cpBtn=document.getElementById("langa-copy-company-to-dev");
    if(cpBtn) cpBtn.addEventListener("click",function(){
      if(!confirm("This will copy all Company data into the Developer fields, overwriting current values. Continue?"))return;
      var src=collectSection("company");
      fillSection("developer",src);
    });
  })();
  ');
}




function langa_tools_client_settings_tab_endpoint() {
  $m = function_exists('langa_tools_client_mail_get_settings') ? langa_tools_client_mail_get_settings() : array();
  if (!is_array($m)) $m = array();

  $enabled = !empty($m['enabled']) ? 1 : 0;
  $from_email = (string)($m['from_email'] ?? '');
  $from_name  = (string)($m['from_name'] ?? '');
  $reply_to    = (string)($m['reply_to'] ?? '');
  $return_path = (string)($m['return_path'] ?? '');
  $force_from  = !empty($m['force_from']) ? 1 : 0;

  $smtp = isset($m['smtp']) && is_array($m['smtp']) ? $m['smtp'] : array();
  $smtp_enabled = !empty($smtp['enabled']) ? 1 : 0;
  $host = (string)($smtp['host'] ?? '');
  $port = (int)($smtp['port'] ?? 587);
  $secure = (string)($smtp['secure'] ?? 'tls');
  $auth = !empty($smtp['auth']) ? 1 : 0;
  $user = (string)($smtp['username'] ?? '');
  $pass = (string)($smtp['password'] ?? '');
  $allow_self_signed = !empty($smtp['allow_self_signed']) ? 1 : 0;

  echo '<h2 style="margin:20px 0 4px">Email Delivery</h2>';
  echo '<p class="description" style="margin:0 0 14px">Global email delivery configuration. <strong>Recipients</strong> are configured per-module (Forms, BC, Maintenance).</p>';

  // ─── GRID: 2/3 config card + 1/3 connections & test ────
  echo '<div style="display:grid;grid-template-columns:3fr 1fr;gap:16px;max-width:965px;align-items:start">'; // open grid
  echo '<div>'; // open left col

  echo '<form method="post">';
  wp_nonce_field('langa_tools_client_save_mail_settings', 'langa_tools_client_save_mail_settings_nonce');

  // ─── SINGLE CARD: From + SMTP ──────────────────────────
  echo '<div id="langa-email-settings" class="langa-card" style="margin-bottom:0">';
  echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">';
  echo '<label style="font-weight:600;font-size:14px"><input type="checkbox" name="mail[enabled]" value="1" '.checked($enabled,1,false).' /> Enable send override</label>';
  echo '<span class="description" style="font-size:12px">(If OFF, the site uses the default server/hosting configuration)</span>';
  echo '</div>';

  echo '<table class="form-table" role="presentation" style="margin:0">';
  echo '<tr><th scope="row">From email</th><td><input type="email" class="regular-text" name="mail[from_email]" value="'.esc_attr($from_email).'" placeholder="noreply@yourdomain.com" /> <span class="description">Must be verified by the SMTP provider</span>';
  if ($from_email !== '' && $user !== '' && strpos($user, '@') !== false) {
    $from_dom = strtolower(preg_replace('/^.+@/', '', $from_email));
    $smtp_dom = strtolower(preg_replace('/^.+@/', '', $user));
    if ($from_dom !== $smtp_dom) {
      echo '<br><span style="color:#b71c1c;font-size:12px;font-weight:600">⚠️ Domain From (<code>' . esc_html($from_dom) . '</code>) differs from SMTP account (<code>' . esc_html($smtp_dom) . '</code>) — emails may not be delivered. Use <code>' . esc_html(explode('@', $user)[0] . '@' . $smtp_dom) . '</code> or configure an alias.</span>';
    }
  }
  echo '</td></tr>';
  echo '<tr><th scope="row">From name</th><td><input type="text" class="regular-text" name="mail[from_name]" value="'.esc_attr($from_name).'" placeholder="'.esc_attr(get_bloginfo('name')).'" /></td></tr>';
  echo '<tr><th scope="row">Reply-To</th><td><input type="email" class="regular-text" name="mail[reply_to]" value="'.esc_attr($reply_to).'" placeholder="info@tuodominio.it" /> <span class="description">Dove arrivano le risposte</span></td></tr>';
  echo '<tr><th scope="row">Return-Path</th><td><input type="email" class="regular-text" name="mail[return_path]" value="'.esc_attr($return_path).'" placeholder="bounce@tuodominio.it" /> <span class="description">Envelope sender (bounce)</span>';
  if ($return_path !== '' && $from_email !== '') {
    $rp_domain = strtolower(preg_replace('/^.+@/', '', $return_path));
    $from_dom  = strtolower(preg_replace('/^.+@/', '', $from_email));
    if ($rp_domain !== $from_dom) {
      echo '<br><span style="color:#b71c1c;font-weight:600;font-size:12px">⚠️ Domain Return-Path (<code>' . esc_html($rp_domain) . '</code>) differs from From (<code>' . esc_html($from_dom) . '</code>) — the plugin will ignore this value to avoid SPF FAIL. Use <code>bounce@' . esc_html($from_dom) . '</code> or leave empty.</span>';
    }
  }
  echo '</td></tr>';
  echo '<tr><th scope="row">Force From</th><td><label><input type="checkbox" name="mail[force_from]" value="1" '.checked($force_from,1,false).' /> Force From even if other plugins override it</label></td></tr>';
  echo '</table>';

  // ─── SMTP inset (gray background inside the same card) ─
  echo '<div style="margin:16px 0 0;padding:14px 16px;background:#f0f0f1;border:1px solid #dcdcde;border-radius:6px">';
  echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">';
  echo '<label style="font-weight:600;font-size:13px"><input type="checkbox" name="mail[smtp][enabled]" value="1" '.checked($smtp_enabled,1,false).' /> Usa SMTP esterno</label>';
  echo '<span class="description" style="font-size:12px">(Gmail, Brevo, Mailgun, AWS SES…)</span>';
  echo '</div>';
  echo '<table class="form-table" role="presentation" style="margin:0">';
  echo '<tr><th scope="row">Host</th><td><input type="text" class="regular-text" name="mail[smtp][host]" value="'.esc_attr($host).'" placeholder="smtp.gmail.com" /></td></tr>';
  echo '<tr><th scope="row">Porta / Cifratura</th><td>';
  echo '<input type="number" name="mail[smtp][port]" value="'.esc_attr($port).'" min="1" max="65535" style="width:90px" /> ';
  echo '<select name="mail[smtp][secure]" style="vertical-align:middle">';
  foreach (array('tls'=>'TLS','ssl'=>'SSL','none'=>'None') as $k=>$lbl) {
    echo '<option value="'.esc_attr($k).'" '.selected($secure,$k,false).'>'.esc_html($lbl).'</option>';
  }
  echo '</select>';
  echo '</td></tr>';
  echo '<tr><th scope="row">Auth</th><td><label><input type="checkbox" name="mail[smtp][auth]" value="1" '.checked($auth,1,false).' /> Richiede login</label></td></tr>';
  echo '<tr><th scope="row">Username</th><td><input type="text" class="regular-text" name="mail[smtp][username]" value="'.esc_attr($user).'" autocomplete="off" placeholder="user@gmail.com o API key" /></td></tr>';
  echo '<tr><th scope="row">Password</th><td><input type="password" class="regular-text" name="mail[smtp][password]" value="'.esc_attr($pass).'" autocomplete="new-password" /></td></tr>';
  echo '<tr><th scope="row">Self-signed</th><td><label><input type="checkbox" name="mail[smtp][allow_self_signed]" value="1" '.checked($allow_self_signed,1,false).' /> Allow self-signed certificates</label> <span class="description">(only if needed)</span></td></tr>';
  echo '</table>';
  echo '</div>'; // close SMTP gray inset

  echo '</div>'; // close langa-card
  // Save button outside the card
  echo '<p style="margin:14px 0 0"><button type="submit" class="button button-primary" name="save_mail_settings" value="1">Save</button></p>';
  echo '</form>';
  echo '</div>'; // close left col

  // ─── RIGHT COLUMN (1/3): Thank You + Connections + Test ────────────
  echo '<div>'; // open right col

  // Thank You Page
  $thankyou_url = get_option('langa_tools_thankyou_url', '');
  echo '<div class="langa-card" style="margin-bottom:12px">';
  echo '<h3 style="margin:0 0 8px;font-size:14px">Thank You Page</h3>';
  echo '<p class="description" style="margin:0 0 8px;font-size:11px">After form submit, redirect here. Leave empty for default message.</p>';
  echo '<form method="post">';
  wp_nonce_field('langa_tools_save_thankyou', 'langa_thankyou_nonce');
  echo '<input type="url" name="langa_thankyou_url" value="'.esc_attr($thankyou_url).'" placeholder="https://yoursite.com/thank-you/" style="width:100%;height:30px;font-size:12px;margin:0 0 6px;box-sizing:border-box" />';
  echo '<button type="submit" name="save_thankyou" value="1" class="button button-primary" style="width:100%">Save</button>';
  if ($thankyou_url !== '') {
    echo '<p style="margin:6px 0 0;font-size:11px;color:#166534">&#10003; Active &rarr; <a href="'.esc_url($thankyou_url).'" target="_blank" style="font-size:11px">'.esc_html(wp_parse_url($thankyou_url, PHP_URL_PATH) ?: $thankyou_url).'</a></p>';
  }
  echo '</form>';
  echo '</div>';

  // Connections status
  echo '<div class="langa-card" style="margin-bottom:12px">';
  echo '<h3 style="margin:0 0 8px;font-size:14px">Connections</h3>';
  $checks = array();
  $checks[] = array('Override', $enabled, $enabled ? 'ON' : 'OFF');
  $checks[] = array('SMTP', $smtp_enabled, $smtp_enabled ? $host . ':' . $port : 'OFF');
  $checks[] = array('Account', $smtp_enabled && $user, $user ?: '—');
  $checks[] = array('From', !empty($from_email), $from_email ?: '(default)');
  $checks[] = array('php mail()', function_exists('mail'), function_exists('mail') ? 'ok' : 'no');
  echo '<table style="border-collapse:collapse;width:100%;font-size:12px">';
  foreach ($checks as $c) {
    $icon = $c[1] ? '<span style="color:#1b5e20">✅</span>' : '<span style="color:#86868b">⬜</span>';
    echo '<tr><td style="padding:2px 6px;font-weight:600;white-space:nowrap">' . $icon . ' ' . esc_html($c[0]) . '</td><td style="padding:2px 6px;white-space:nowrap"><code style="font-size:11px;text-wrap-mode:nowrap">' . esc_html($c[2]) . '</code></td></tr>';
  }
  echo '</table>';
  echo '</div>';

  // Test form
  $admin_email = sanitize_email(get_option('admin_email'));
  echo '<form method="post">';
  wp_nonce_field('langa_tools_client_send_test_mail', 'langa_tools_client_send_test_mail_nonce');
  echo '<div class="langa-card" style="margin-bottom:0">';
  echo '<h3 style="margin:0 0 6px;font-size:14px">Test</h3>';
  echo '<p class="description" style="margin:0 0 8px;font-size:11px">Send test emails to verify configuration.</p>';

  echo '<div style="display:flex;flex-direction:column;gap:4px;margin:0 0 8px;font-size:12px">';
  echo '<label><input type="checkbox" name="test_types[]" value="generic" checked /> Generico</label>';
  echo '<label><input type="checkbox" name="test_types[]" value="forms" checked /> Forms</label>';
  echo '<label><input type="checkbox" name="test_types[]" value="bc" checked /> BC</label>';
  echo '<label><input type="checkbox" name="test_types[]" value="maintenance" checked /> Maintenance</label>';
  echo '<label><input type="checkbox" name="test_types[]" value="credits" checked /> Credits</label>';
  echo '</div>';

  echo '<input type="email" class="regular-text" name="test_to" value="'.esc_attr($admin_email).'" placeholder="test@domain.com" style="width:100%;margin:0 0 8px;box-sizing:border-box" />';
  echo '<button type="submit" class="button button-primary" name="send_test_mail" value="1" style="width:100%">Send test</button>';
  echo '</div>';
  echo '</form>';

  echo '</div>'; // close right col
  echo '</div>'; // close grid

  // Previous test results
  $prev = get_transient('langa_tools_mail_test_results');
  if (is_array($prev) && !empty($prev['results'])) {
    $age = time() - (int)($prev['ts'] ?? 0);
    if ($age < 300) {
      echo '<div class="langa-card" style="margin-top:12px">';
      echo '<h3 style="margin-top:0">Ultimo test — ' . esc_html(date('H:i:s', (int)($prev['ts'] ?? 0))) . '</h3>';
      echo '<p class="description" style="margin:0 0 4px">Recipient: <code>' . esc_html($prev['to'] ?? '') . '</code></p>';
      echo '<p class="description" style="margin:0 0 8px">Delivery: ' . (!empty($prev['invio_on']) ? '<strong>ON</strong>' : 'OFF') . ' · SMTP: ' . (!empty($prev['smtp_on']) ? '<strong>ON</strong> → <code>' . esc_html($prev['smtp_host'] ?? '') . '</code>' : 'OFF') . ' · From: <code>' . esc_html($prev['from'] ?: '(default)') . '</code></p>';

      echo '<div class="langa-scroll-table" style="max-width:700px">';
      echo '<table class="widefat striped">';
      echo '<thead><tr><th style="width:180px">Pipeline</th><th style="width:70px">Esito</th><th>Dettaglio</th></tr></thead><tbody>';
      foreach ($prev['results'] as $r) {
        $icon = !empty($r['ok']) ? '<span style="color:#1b5e20">✅ OK</span>' : '<span style="color:#b71c1c">❌ FAIL</span>';
        $detail = !empty($r['ok']) ? 'wp_mail() → true' : esc_html($r['error'] ?: 'Errore sconosciuto');
        echo '<tr><td><strong>' . esc_html($r['label'] ?? '') . '</strong></td><td>' . $icon . '</td><td>' . $detail . '</td></tr>';
      }
      echo '</tbody></table>';
      echo '</div>';

      // SMTP log (if captured)
      $log = isset($prev['smtp_log']) ? trim((string)$prev['smtp_log']) : '';
      if ($log !== '') {
        echo '<details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600;font-size:13px">📋 Log SMTP (debug)</summary>';
        echo '<pre style="white-space:pre-wrap;max-width:100%;margin:6px 0 0;font-size:11px;background:#fafafa;padding:10px;border:1px solid #e5e5e7;border-radius:6px;max-height:300px;overflow:auto">' . esc_html($log) . '</pre>';
        echo '</details>';
      }

      // Diagnostic advice
      $all_sent = true;
      foreach ($prev['results'] as $r) { if (empty($r['ok'])) { $all_sent = false; break; } }

      if ($all_sent) {
        $smtp_host = (string)($prev['smtp_host'] ?? '');
        $from_email = (string)($prev['from'] ?? '');
        $from_domain = $from_email ? preg_replace('/^.+@/', '', $from_email) : '';
        $site_host = (string)parse_url(home_url('/'), PHP_URL_HOST);

        // Extract MAIL FROM from SMTP log
        $envelope_from = '';
        if ($log !== '' && preg_match('/MAIL FROM:\s*<([^>]+)>/i', $log, $ef_match)) {
          $envelope_from = strtolower(trim($ef_match[1]));
        }
        $envelope_domain = $envelope_from ? preg_replace('/^.+@/', '', $envelope_from) : '';

        $is_same_host = ($smtp_host !== '' && $site_host !== '' && strcasecmp($smtp_host, $site_host) === 0);
        $has_250_ok = ($log !== '' && preg_match('/250\s+(OK|2\.0\.0|Accepted|queued)/i', $log));
        $major_providers = array('gmail.com','googlemail.com','yahoo.com','outlook.com','hotmail.com','live.com');

        // Count problems
        $problems = array();

        // Problem 1: SMTP host = site host (not a real mail server)
        if ($is_same_host) {
          $problems[] = array(
            'icon' => '🔴',
            'title' => 'Host SMTP sbagliato',
            'desc' => '<code>' . esc_html($smtp_host) . '</code> is the web server, not the mail server.',
            'fix' => 'Cambia in <code>mail.' . esc_html($from_domain ?: 'tuodominio.it') . '</code> (vedi impostazioni del tuo provider email).',
          );
        }

        // Problem 2: Envelope sender mismatch
        if ($envelope_from !== '' && $from_domain !== '' && strcasecmp($envelope_domain, $from_domain) !== 0) {
          $fix = 'Svuota il campo Return-Path oppure metti <code>bounce@' . esc_html($from_domain) . '</code>.';
          if (in_array($envelope_domain, $major_providers, true)) {
            $fix = 'MAIL FROM usa <code>' . esc_html($envelope_domain) . '</code> — Gmail/Yahoo rifiutano email da server non autorizzati. ' . $fix;
          }
          $problems[] = array(
            'icon' => '🔴',
            'title' => 'Return-Path non allineato al From',
            'desc' => 'Envelope: <code>' . esc_html($envelope_from) . '</code> · From: <code>' . esc_html($from_email) . '</code>',
            'fix' => $fix,
          );
        }

        // Problem 3: From domain ≠ SMTP account domain (SPF/auth mismatch)
        $smtp_user = (string)($prev['smtp_user'] ?? '');
        $smtp_user_domain = $smtp_user ? strtolower(preg_replace('/^.+@/', '', $smtp_user)) : '';
        if ($smtp_user_domain !== '' && $from_domain !== '' && strcasecmp($smtp_user_domain, $from_domain) !== 0 && !empty($prev['smtp_on'])) {
          $problems[] = array(
            'icon' => '🔴',
            'title' => 'Domain From differs from SMTP account',
            'desc' => 'From: <code>' . esc_html($from_email) . '</code> (dominio: <code>' . esc_html($from_domain) . '</code>) · Account SMTP: <code>' . esc_html($smtp_user) . '</code> (dominio: <code>' . esc_html($smtp_user_domain) . '</code>)',
            'fix' => 'Cambia il <strong>From email</strong> in <code>' . esc_html(explode('@', $smtp_user)[0] . '@' . $smtp_user_domain) . '</code> (stesso dominio dell\'account SMTP), oppure aggiungi un record SPF per <code>' . esc_html($from_domain) . '</code> che autorizzi il mail server.',
          );
        }

        // Problem 4: SMTP off
        if (empty($prev['smtp_on'])) {
          $problems[] = array(
            'icon' => '🟡',
            'title' => 'SMTP is OFF',
            'desc' => 'The site uses <code>php mail()</code> — on shared hosting this often fails.',
            'fix' => 'Attiva SMTP con il server del tuo provider email.',
          );
        }

        if (!empty($problems)) {
          echo '<div style="margin-top:12px;padding:14px 16px;background:#fefce8;border:1px solid #fde68a;border-radius:10px">';
          echo '<p style="margin:0 0 10px;font-weight:600;font-size:14px">⚠️ Test OK but email not arriving? Issues found:</p>';
          foreach ($problems as $p) {
            echo '<div style="margin:0 0 10px;padding:10px 12px;background:#fff;border:1px solid #e5e5e7;border-radius:6px">';
            echo '<p style="margin:0 0 4px;font-weight:600">' . $p['icon'] . ' ' . $p['title'] . '</p>';
            echo '<p style="margin:0 0 4px;font-size:13px;color:#86868b">' . wp_kses_post($p['desc']) . '</p>';
            echo '<p style="margin:0;font-size:13px"><strong>→</strong> ' . wp_kses_post($p['fix']) . '</p>';
            echo '</div>';
          }
          echo '</div>';
        } elseif ($has_250_ok) {
          // No detected problems but still not arriving
          echo '<div style="margin-top:12px;padding:10px 14px;background:#e8f5e9;border:1px solid #bbf7d0;border-radius:6px;font-size:13px">';
          echo '<p style="margin:0"><strong>✅ Configuration OK</strong> — the server accepted the message (<code>250 OK</code>). If not delivered: check spam/junk, SPF/DKIM for domain <code>' . esc_html($from_domain) . '</code>.</p>';
          echo '</div>';
        }
      }

      echo '</div>';
    }
  }
}

// ─── DEBUG TAB ──────────────────────────────────────────
// ─── DEBUG TAB ──────────────────────────────────────────
function langa_tools_client_settings_tab_debug() {
  $debug_on = function_exists('langa_tools_client_debug_enabled') ? langa_tools_client_debug_enabled() : false;
  $log = function_exists('langa_tools_client_debug_get_log') ? langa_tools_client_debug_get_log() : array();

  echo '<h2 style="margin:20px 0 4px">Debug</h2>';
  echo '<p class="description" style="margin:0 0 14px">Logging, connectivity diagnostics, and debug log viewer.</p>';

  // Debug Mode toggle — inline
  echo '<form method="post" style="margin:0 0 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">';
  wp_nonce_field('langa_tools_client_save_debug', 'langa_tools_client_save_debug_nonce');
  echo '<label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600">';
  echo '<input type="checkbox" name="debug_mode" value="1"' . ($debug_on ? ' checked' : '') . '>';
  echo 'Debug Mode';
  echo '</label>';
  echo '<span class="description" style="font-size:11px;color:#86868b">Logs HTTP calls &amp; emails. Disable in production.</span>';
  echo '<span style="display:flex;gap:6px">';
  submit_button('Save', 'primary', 'save_debug_settings', false, array('style'=>'font-size:12px;padding:2px 12px;min-height:28px'));
  submit_button('Clear log', 'secondary', 'clear_debug_log', false, array('style'=>'font-size:12px;padding:2px 12px;min-height:28px'));
  echo '</span>';
  echo '</form>';

  // ─── GRID: 3fr 1fr (same as endpoint tab) ────
  echo '<div style="display:grid;grid-template-columns:3fr 1fr;gap:16px;max-width:965px;align-items:start">';
  echo '<div>'; // LEFT column

  // ── Connectivity card ──
  $site_key = (string) get_option(defined('LANGA_TOOLS_OPTION_SITE_KEY') ? LANGA_TOOLS_OPTION_SITE_KEY : 'langa_tools_site_key', '');
  $secret   = (string) get_option(defined('LANGA_TOOLS_OPTION_SECRET') ? LANGA_TOOLS_OPTION_SECRET : 'langa_tools_secret', '');
  $server_base = defined('LANGA_TOOLS_FIXED_SERVER_URL') ? rtrim((string) LANGA_TOOLS_FIXED_SERVER_URL, '/') : 'https://tools.langa.tv';

  echo '<div class="langa-card" style="margin-bottom:16px">';
  echo '<h3 style="margin:0 0 4px;font-size:14px">Connectivity Diagnostics</h3>';
  echo '<p class="description" style="margin:0 0 12px">Live tests against <code>' . esc_html($server_base) . '</code></p>';

  $tests = array();

  $t1_url = $server_base . '/wp-json/langa-tools-server/v1/events/ping';
  $t1_start = microtime(true);
  $t1_resp = wp_remote_get($t1_url, array('timeout' => 12));
  $t1_ms = round((microtime(true) - $t1_start) * 1000);
  $tests[] = array('label' => 'Ping (server alive)', 'method' => 'GET', 'url' => $t1_url, 'resp' => $t1_resp, 'ms' => $t1_ms);
  if (function_exists('langa_tools_client_debug_log_remote')) langa_tools_client_debug_log_remote('connectivity', 'GET', $t1_url, $t1_resp, $t1_ms);

  if ($site_key && $secret) {
    $t2_url = $server_base . '/wp-json/langa-tools-server/v1/license/check';
    $t2_payload_arr = array('site_url' => home_url(), 'ts' => time(), 'nonce' => wp_generate_password(12, false, false));
    $t2_payload = wp_json_encode($t2_payload_arr);
    $t2_sig = class_exists('Langa_Tools_Client_Auth') ? Langa_Tools_Client_Auth::sign($t2_payload, $secret) : '';
    $t2_start = microtime(true);
    $t2_resp = wp_remote_post($t2_url, array('timeout' => 12, 'body' => array('site_key' => $site_key, 'payload' => $t2_payload, 'signature' => $t2_sig)));
    $t2_ms = round((microtime(true) - $t2_start) * 1000);
    $tests[] = array('label' => 'License check (auth)', 'method' => 'POST', 'url' => $t2_url, 'resp' => $t2_resp, 'ms' => $t2_ms);
    if (function_exists('langa_tools_client_debug_log_remote')) langa_tools_client_debug_log_remote('license', 'POST', $t2_url, $t2_resp, $t2_ms);
  }

  if ($site_key && $secret) {
    $t3_url = $server_base . '/wp-json/langa-tools-server/v1/events/log-event';
    $t3_payload_arr = array('site_url' => home_url(), 'mode' => 'test', 'event_type' => 'connectivity_test', 'ref' => 'debug_tab', 'ts' => time(), 'nonce' => wp_generate_password(12, false, false));
    $t3_payload = wp_json_encode($t3_payload_arr);
    $t3_sig = class_exists('Langa_Tools_Client_Auth') ? Langa_Tools_Client_Auth::sign($t3_payload, $secret) : '';
    $t3_start = microtime(true);
    $t3_resp = wp_remote_post($t3_url, array('timeout' => 12, 'body' => array('site_key' => $site_key, 'payload' => $t3_payload, 'signature' => $t3_sig)));
    $t3_ms = round((microtime(true) - $t3_start) * 1000);
    $tests[] = array('label' => 'Event POST (test)', 'method' => 'POST', 'url' => $t3_url, 'resp' => $t3_resp, 'ms' => $t3_ms);
    if (function_exists('langa_tools_client_debug_log_remote')) langa_tools_client_debug_log_remote('connectivity', 'POST', $t3_url, $t3_resp, $t3_ms);
  } else {
    // No credentials — skip event test, show info row
    $tests[] = array('label' => 'Event POST (test)', 'method' => 'POST', 'url' => $server_base . '/wp-json/langa-tools-server/v1/events/log-event', 'resp' => null, 'ms' => 0, 'skip' => 'No license key — requires PRO activation');
  }

  echo '<div class="langa-scroll-table langa-scroll-table--short">';
  echo '<table style="border:0;margin:0;max-width:none;width:100%;font-size:12px">';
  echo '<thead><tr><th style="width:200px">Test</th><th style="width:60px">Method</th><th style="width:60px">HTTP</th><th style="width:60px">Latency</th><th>Result</th></tr></thead><tbody>';

  foreach ($tests as $t) {
    // Skip row — no credentials or not applicable
    if (!empty($t['skip'])) {
      echo '<tr style="opacity:0.5">';
      echo '<td><span class="langa-badge" style="background:#e5e7eb;color:#6b7280">SKIP</span> <strong>' . esc_html($t['label']) . '</strong></td>';
      echo '<td><code>' . esc_html($t['method']) . '</code></td>';
      echo '<td><code>—</code></td>';
      echo '<td>—</td>';
      echo '<td><span style="color:#6b7280;font-size:11px">' . esc_html($t['skip']) . '</span></td>';
      echo '</tr>';
      continue;
    }
    $resp_obj = $t['resp'];
    $is_err = is_wp_error($resp_obj);
    $code = $is_err ? 0 : (int) wp_remote_retrieve_response_code($resp_obj);
    $t_ok = (!$is_err && $code >= 200 && $code < 300);
    $body_raw = $is_err ? '' : (string) wp_remote_retrieve_body($resp_obj);
    $body_trim = mb_strlen($body_raw) > 300 ? mb_substr($body_raw, 0, 300) . '...' : $body_raw;
    $err_msg = $is_err ? $resp_obj->get_error_message() : '';
    $warn = false;
    if (!$is_err && !$t_ok && $body_raw !== '') {
      $bj = json_decode($body_raw, true);
      if (is_array($bj) && isset($bj['error']) && in_array($bj['error'], array('events_gateway_disabled','events_disabled','gateway_disabled','maintenance'), true)) $warn = true;
    }
    if ($warn)     $icon = '<span class="langa-badge langa-badge--warn">WARN</span>';
    elseif ($t_ok) $icon = '<span class="langa-badge langa-badge--ok">OK</span>';
    else           $icon = '<span class="langa-badge langa-badge--fail">FAIL</span>';
    echo '<tr>';
    echo '<td>' . $icon . ' <strong>' . esc_html($t['label']) . '</strong></td>';
    echo '<td><code>' . esc_html($t['method']) . '</code></td>';
    echo '<td><code>' . ($is_err ? '—' : esc_html($code)) . '</code></td>';
    echo '<td>' . esc_html($t['ms']) . ' ms</td>';
    echo '<td>';
    if ($is_err) echo '<span style="color:#b71c1c">' . esc_html($err_msg) . '</span>';
    elseif ($body_trim !== '') echo '<pre style="white-space:pre-wrap;word-break:break-all;margin:0;font-size:11px;max-width:400px">' . esc_html($body_trim) . '</pre>';
    else echo '<span class="description">(empty)</span>';
    echo '</td></tr>';
  }

  if (!$site_key || !$secret) {
    echo '<tr><td colspan="5" style="padding:10px 14px"><span class="description">Enter Site Key + Secret in General tab to enable authenticated tests.</span></td></tr>';
  }
  echo '</tbody></table></div>';

  $lic_last = function_exists('langa_tools_client_license_last') ? langa_tools_client_license_last() : array();
  if (!empty($lic_last)) {
    echo '<details style="margin:10px 0 0"><summary style="cursor:pointer;font-size:12px;color:#86868b">License raw response</summary>';
    echo '<pre style="font-size:11px;background:#fafafa;padding:10px;border-radius:8px;border:1px solid #e5e5e7;margin:6px 0 0;max-height:200px;overflow:auto;word-break:break-all">' . esc_html(wp_json_encode($lic_last, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '</details>';
  }
  echo '</div>'; // connectivity card

  // Event Log
  echo '<div class="langa-card">';
  echo '<h3 style="margin:0 0 10px;font-size:14px">Event Log <span style="font-weight:400;font-size:12px;color:#86868b">(' . count($log) . '/50)</span></h3>';

  if (empty($log)) {
    echo '<p class="description">' . ($debug_on ? 'No events recorded yet.' : 'Debug mode is off — enable the toggle above.') . '</p>';
  } else {
    echo '<button type="button" id="langa-debug-copy" class="button button-small" style="margin:0 0 10px">Copy log</button>';
    echo '<span id="langa-debug-copy-ok" style="display:none;color:#1b5e20;font-size:12px;margin-left:8px">Copied</span>';
    echo '<div class="langa-scroll-table">';
    echo '<table style="border:0;margin:0;max-width:none;width:100%;font-size:12px" id="langa-debug-table">';
    echo '<thead><tr><th style="width:130px">Timestamp</th><th style="width:80px">Context</th><th style="width:50px">Method</th><th>URL / Recipient</th><th style="width:50px">Code</th><th style="width:55px">Latency</th><th style="width:40px">Status</th></tr></thead><tbody>';
    $ctx_colors = array('connectivity'=>'#dbeafe','forms'=>'#dbeafe','forms_mail'=>'#dbeafe','bc'=>'#fce7f3','bc_mail'=>'#fce7f3','maintenance'=>'#fef3c7','maintenance_mail'=>'#fef3c7','license'=>'#f3e8ff','license_check'=>'#f3e8ff','api'=>'#e0e7ff');
    foreach ($log as $e) {
      $ts = isset($e['ts']) ? (int)$e['ts'] : 0;
      $date = !empty($e['time']) ? (string)$e['time'] : ($ts > 0 ? wp_date('Y-m-d H:i:s', $ts) : '—');
      $ctx = (string)($e['type'] ?? '—');
      $method = (string)($e['method'] ?? '—');
      $url = (string)($e['url'] ?? ($e['to'] ?? '—'));
      $code_v = isset($e['code']) ? (int)$e['code'] : 0;
      $ms = isset($e['latency']) ? (int)$e['latency'] : 0;
      $msg = (string)($e['msg'] ?? '');
      $is_ok = (stripos($msg, 'OK') === 0);
      $snippet = (string)($e['body_snippet'] ?? ($e['error'] ?? ''));
      $ctx_bg = $ctx_colors[$ctx] ?? '#f5f5f7';
      $badge = $is_ok ? '<span class="langa-badge langa-badge--ok" style="font-size:10px;padding:1px 6px">OK</span>' : '<span class="langa-badge langa-badge--fail" style="font-size:10px;padding:1px 6px">FAIL</span>';
      $url_short = mb_strlen($url) > 55 ? mb_substr($url, 0, 55) . '...' : $url;
      echo '<tr>';
      echo '<td style="font-family:monospace;font-size:11px;white-space:nowrap">' . esc_html($date) . '</td>';
      echo '<td><span style="display:inline-block;padding:1px 6px;border-radius:6px;font-size:10px;font-weight:600;background:' . esc_attr($ctx_bg) . '">' . esc_html($ctx) . '</span></td>';
      echo '<td><code>' . esc_html($method) . '</code></td>';
      echo '<td title="' . esc_attr($url) . '">' . esc_html($url_short) . '</td>';
      echo '<td><code>' . ($code_v > 0 ? esc_html($code_v) : '—') . '</code></td>';
      echo '<td>' . ($ms > 0 ? esc_html($ms) . 'ms' : '—') . '</td>';
      echo '<td>' . $badge . '</td>';
      echo '</tr>';
      if ($snippet !== '' && !$is_ok) {
        echo '<tr><td colspan="7" style="padding:4px 14px 8px;background:#fce4ec;font-size:11px;color:#b71c1c;font-family:monospace;word-break:break-all">' . esc_html($snippet) . '</td></tr>';
      }
    }
    echo '</tbody></table></div>';
    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin inline JS for immediate DOM manipulation
    echo '<script>';
    echo 'document.getElementById("langa-debug-copy").addEventListener("click",function(){';
    echo 'var t=document.getElementById("langa-debug-table");if(!t)return;';
    echo 'var rows=t.querySelectorAll("tbody tr"),lines=[];';
    echo 'rows.forEach(function(r){var cells=r.querySelectorAll("td");if(cells.length>=7){';
    echo 'lines.push([].map.call(cells,function(c){return c.textContent.trim()}).join(" | "));}});';
    echo 'var txt="LANGA Debug Log ("+lines.length+" entries)\\n"+("=".repeat(60))+"\\n"+lines.join("\\n");';
    echo 'if(navigator.clipboard){navigator.clipboard.writeText(txt).then(function(){';
    echo 'var ok=document.getElementById("langa-debug-copy-ok");ok.style.display="inline";setTimeout(function(){ok.style.display="none"},2000);';
    echo '});}});';
    echo '</script>';
  }
  echo '</div>'; // log card
  echo '</div>'; // left col

  // RIGHT (1/3): Environment + Credentials
  echo '<div style="display:flex;flex-direction:column;gap:12px">';
  echo '<div class="langa-card">';
  echo '<h3 style="margin:0 0 8px;font-size:14px">Environment</h3>';
  echo '<table style="border-collapse:collapse;width:100%;font-size:12px">';
  $env_rows = array(
    array('PHP', phpversion()),
    array('WordPress', get_bloginfo('version')),
    array('Plugin', defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '?'),
    array('Memory limit', ini_get('memory_limit')),
    array('Max exec time', ini_get('max_execution_time') . 's'),
    array('Debug mode', $debug_on ? 'ON' : 'OFF'),
    array('Log entries', count($log) . '/50'),
  );
  foreach ($env_rows as $row) {
    echo '<tr><td style="padding:3px 6px;font-weight:600;white-space:nowrap">' . esc_html($row[0]) . '</td><td style="padding:3px 6px"><code style="font-size:11px">' . esc_html($row[1]) . '</code></td></tr>';
  }
  echo '</table></div>';

  $site_key_short = $site_key ? substr($site_key, 0, 8) . '...' : '—';
  echo '<div class="langa-card">';
  echo '<h3 style="margin:0 0 8px;font-size:14px">Credentials</h3>';
  echo '<table style="border-collapse:collapse;width:100%;font-size:12px">';
  echo '<tr><td style="padding:3px 6px;font-weight:600;white-space:nowrap">Site key</td><td style="padding:3px 6px"><code style="font-size:11px">' . esc_html($site_key_short) . '</code></td></tr>';
  echo '<tr><td style="padding:3px 6px;font-weight:600;white-space:nowrap">Secret</td><td style="padding:3px 6px"><code style="font-size:11px">' . ($secret ? '••••••••' : '—') . '</code></td></tr>';
  echo '<tr><td style="padding:3px 6px;font-weight:600;white-space:nowrap">Server</td><td style="padding:3px 6px"><code style="font-size:11px">' . esc_html($server_base) . '</code></td></tr>';
  echo '</table></div>';

  // Dev Bypass card — simple password toggle
  $bypass_on = (int) get_option('langa_tools_dev_bypass', 0) === 1;
  echo '<div class="langa-card">';
  echo '<h3 style="margin:0 0 8px;font-size:14px">Bypass <span style="font-size:11px;font-weight:400;color:#86868b">(Dev access)</span></h3>';
  echo '<form method="post">';
  wp_nonce_field('langa_tools_save_devbypass', 'langa_devbypass_nonce');
  echo '<div style="display:flex;gap:6px;align-items:center">';
  echo '<input name="dev_pw" type="password" value="" placeholder="Password" style="flex:1;font-size:12px;padding:5px 8px;border:1px solid #d2d2d7;border-radius:4px;box-sizing:border-box;min-width:0" />';
  echo '<button type="submit" name="save_devbypass" value="1" class="button '.($bypass_on ? '' : 'button-primary').'" style="white-space:nowrap;font-size:12px;padding:0 12px;height:32px">'.($bypass_on ? 'Guard ON' : 'Guard OFF').'</button>';
  echo '</div>';
  if ($bypass_on) {
    echo '<p style="margin:6px 0 0;font-size:11px;color:#f37f0d;font-weight:600">Guard OFF — safe to deactivate plugin</p>';
  } else {
    echo '<p style="margin:6px 0 0;font-size:11px;color:#86868b">Guard ON — site protected</p>';
  }
  echo '</form>';
  echo '</div>';

  // ── 🔗 LANGA Sync — always-on for free tier ──
  $state_lite = 'unknown';
  $bs_lite = array('registration_status'=>'','last_heartbeat'=>0,'using_fallback'=>0,'registration_error'=>'');
  if (function_exists('langa_bridge_get_status')) {
    $bs_lite = langa_bridge_get_status();
    $registered_l  = ($bs_lite['registration_status'] === 'registered');
    $on_fallback_l = !empty($bs_lite['using_fallback']);
    $has_hb_l      = $bs_lite['last_heartbeat'] > 0;
    $hb_fresh_l    = $has_hb_l && (time() - $bs_lite['last_heartbeat']) < 43200;
    if      ($registered_l && $hb_fresh_l && !$on_fallback_l) $state_lite = 'primary';
    elseif  ($registered_l && $on_fallback_l)                 $state_lite = 'fallback';
    elseif  ($registered_l && !$hb_fresh_l)                   $state_lite = 'overdue';
    else                                                       $state_lite = 'disconnected';
  }
  $state_map_l = array(
    'primary'      => array('dot'=>'#16a34a','bg'=>'#f0fdf4','txt'=>'#15803d','border'=>'#bbf7d0','label'=>'Connected',           'sub'=>'Microchip connesso ad AEGIS'),
    'fallback'     => array('dot'=>'#16a34a','bg'=>'#f0fdf4','txt'=>'#15803d','border'=>'#bbf7d0','label'=>'Connected','sub'=>'Microchip connesso ad AEGIS'),
    'overdue'      => array('dot'=>'#ea580c','bg'=>'#fff7ed','txt'=>'#c2410c','border'=>'#fed7aa','label'=>'Connection delayed',  'sub'=>'Registered, but no recent heartbeat'),
    'disconnected' => array('dot'=>'#dc2626','bg'=>'#fef2f2','txt'=>'#b91c1c','border'=>'#fecaca','label'=>'Not connected',       'sub'=>'Registration pending or failed'),
    'unknown'      => array('dot'=>'#64748b','bg'=>'#f8fafc','txt'=>'#475569','border'=>'#e2e8f0','label'=>'Unknown',             'sub'=>'Status not available'),
  );
  $svl = $state_map_l[$state_lite] ?? $state_map_l['unknown'];

  echo '<style>
#langa-sync-box .langa-sync-badge{display:flex!important;align-items:center!important;gap:10px!important;padding:10px 14px!important;border-radius:8px!important;margin:0 0 10px!important;}
#langa-sync-box .langa-sync-badge-dot{display:inline-block!important;width:12px!important;height:12px!important;border-radius:50%!important;flex-shrink:0!important;}
#langa-sync-box .langa-sync-badge-label{font-size:13px!important;font-weight:700!important;margin:0!important;line-height:1.3!important;}
#langa-sync-box .langa-sync-badge-sub{font-size:11px!important;opacity:.85!important;margin:2px 0 0!important;}
#langa-sync-box .langa-sync-notice{display:block!important;margin:8px 0 0!important;padding:8px 10px 8px 14px!important;border-left:4px solid!important;border-radius:0 6px 6px 0!important;font-size:11px!important;font-weight:600!important;line-height:1.4!important;}
</style>';
  echo '<div id="langa-sync-box" class="langa-card" style="border-left:3px solid '.esc_attr($svl['dot']).'">';
  echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 10px">';
  echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#c1121f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M15 2v2"/><path d="M15 20v2"/><path d="M2 15h2"/><path d="M2 9h2"/><path d="M20 15h2"/><path d="M20 9h2"/><path d="M9 2v2"/><path d="M9 20v2"/></svg>';
  echo '<div><div style="font-size:12px;font-weight:700;color:#c1121f">Microchip <span style="font-weight:400;font-size:10px;color:#6b7280">(Sync)</span></div><div style="font-size:9px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px">powered by AEGIS</div></div>';
  echo '</div>';

  // Big status badge
  echo '<div class="langa-sync-badge" style="background:'.esc_attr($svl['bg']).';border:1px solid '.esc_attr($svl['border']).'">';
    echo '<span id="langa-sync-dot" class="langa-sync-badge-dot" style="background:'.esc_attr($svl['dot']).'"></span>';
    echo '<div>';
      echo '<div id="langa-sync-label" class="langa-sync-badge-label" style="color:'.esc_attr($svl['txt']).'">'.esc_html($svl['label']).'</div>';
      echo '<div id="langa-sync-sub" class="langa-sync-badge-sub" style="color:'.esc_attr($svl['txt']).'">'.esc_html($svl['sub']).'</div>';
    echo '</div>';
  echo '</div>';

  if (function_exists('langa_bridge_get_status')) {
    $hb_l = ($bs_lite['last_heartbeat'] > 0) ? 'Last heartbeat: ' . human_time_diff((int)$bs_lite['last_heartbeat'], time()) . ' ago' : 'No heartbeat yet';
    echo '<div style="font-size:11px;color:#86868b;margin:0 0 8px">'.esc_html($hb_l).'</div>';
  }

  $test_nonce = wp_create_nonce('langa_bridge_test_conn');
  echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 4px">';
  echo '<button type="button" id="langa-sync-test-btn" class="button" style="font-size:11px;height:26px;padding:0 10px" data-nonce="'.esc_attr($test_nonce).'">Test Connection</button>';
  if (!in_array($state_lite, array('primary'), true)) {
    echo '<button type="button" id="langa-sync-force-btn" class="button button-primary" style="font-size:11px;height:26px;padding:0 10px" data-nonce="'.esc_attr($test_nonce).'">Force Sync Now</button>';
  }
  echo '</div>';


    echo '<div class="langa-sync-notice" style="background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe">&#128274; Microchip is always active — Lite version.</div>';

  echo '<script>
(function(){
  var btn=document.getElementById("langa-sync-test-btn");
  if(!btn)return;
  btn.addEventListener("click",function(){
    btn.disabled=true; btn.textContent="Testing\u2026";
    var dot=document.getElementById("langa-sync-dot");
    var lbl=document.getElementById("langa-sync-label");
    var sub=document.getElementById("langa-sync-sub");
    var fd=new FormData();
    fd.append("action","langa_bridge_test_connection");
    fd.append("_nonce","' . esc_js($test_nonce) . '");
    fetch(ajaxurl,{method:"POST",body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        btn.disabled=false; btn.textContent="Test Connection";
        var data=d.data||{}; var ep=data.endpoint||(d.success?"primary":"none");
        var colors={primary:"#f0fdf4",fallback:"#f0fdf4",none:"#fef2f2"};
        var borders={primary:"#bbf7d0",fallback:"#bbf7d0",none:"#fecaca"};
        var txts={primary:"#15803d",fallback:"#15803d",none:"#b91c1c"};
        var dots={primary:"#16a34a",fallback:"#16a34a",none:"#dc2626"};
        var labels={primary:"Connected",fallback:"Connected",none:"Not connected"};
        var subs={primary:"Microchip connesso ad AEGIS",fallback:"Microchip connesso ad AEGIS",none:"Both endpoints unreachable"};
        if(dot)dot.style.background=dots[ep]||"#dc2626";
        if(lbl){lbl.textContent=data.label||labels[ep]||"Unknown";lbl.style.color=txts[ep]||"#b91c1c";}
        if(sub){sub.textContent=subs[ep]||"";sub.style.color=txts[ep]||"#b91c1c";}
        var badge=dot?dot.parentNode.parentNode:null;
        if(badge){badge.style.background=colors[ep]||"#fef2f2";badge.style.borderColor=borders[ep]||"#fecaca";}
        setTimeout(function(){location.reload();},1200);
      })
      .catch(function(e){
        btn.disabled=false; btn.textContent="Test Connection";
        if(dot)dot.style.background="#dc2626";
        if(lbl){lbl.textContent="Not connected";lbl.style.color="#b91c1c";}
        setTimeout(function(){location.reload();},1500);
      });
  });
})();
</script>';

  echo '<script>
(function(){
  var btn=document.getElementById("langa-sync-force-btn");
  if(!btn)return;
  btn.addEventListener("click",function(){
    btn.disabled=true; btn.textContent="Registering\u2026";
    var dot=document.getElementById("langa-sync-dot");
    var lbl=document.getElementById("langa-sync-label");
    var sub=document.getElementById("langa-sync-sub");
    var badge=dot?dot.parentNode.parentNode:null;
    var fd=new FormData();
    fd.append("action","langa_bridge_force_register");
    fd.append("_nonce","' . esc_js($test_nonce) . '");
    fetch(ajaxurl,{method:"POST",body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        btn.disabled=false; btn.textContent="Force Sync Now";
        var data=d.data||{};
        if(d.success){
          var bg="#f0fdf4";
          var bd="#bbf7d0";
          var dot_c="#16a34a";
          var txt="#15803d";
          var lbl_t="Connected";
          var sub_t="Microchip connesso ad AEGIS";
          if(dot)dot.style.background=dot_c;
          if(lbl){lbl.textContent=lbl_t;lbl.style.color=txt;}
          if(sub){sub.textContent=sub_t;sub.style.color=txt;}
          if(badge){badge.style.background=bg;badge.style.borderColor=bd;}
        } else {
          if(dot)dot.style.background="#dc2626";
          if(lbl){lbl.textContent="Not connected";lbl.style.color="#b91c1c";}
          if(sub){sub.textContent=data.msg||"Registration failed";sub.style.color="#b91c1c";}
          if(badge){badge.style.background="#fef2f2";badge.style.borderColor="#fecaca";}
        }
        setTimeout(function(){location.reload();},1500);
      })
      .catch(function(e){
        btn.disabled=false; btn.textContent="Force Sync Now";
        if(dot)dot.style.background="#dc2626";
        if(lbl){lbl.textContent="Not connected";lbl.style.color="#b91c1c";}
        setTimeout(function(){location.reload();},1500);
      });
  });
})();
</script>';

  echo '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f0;text-align:center;font-size:9px;color:#9ca3af;line-height:1.7">';
  echo 'AEGIS Tech &copy; ' . date('Y') . ' LANGA Corp. Srl &middot; P.IVA IT10637600965<br>';
  echo '<a href="https://aegis.langa.tv" target="_blank" style="color:#6a4c93;text-decoration:none;font-weight:600">aegis.langa.tv</a> &middot; <a href="https://langa.tv" target="_blank" style="color:#f37f0d;text-decoration:none;font-weight:600">lan.ga</a>';
  echo '</div>';

  echo '</div>';

  echo '</div>';

  echo '</div>'; // right col
  echo '</div>'; // grid
}


function langa_tools_client_settings_tab_help() {
  $v = defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '?';
  $htab = isset($_GET['htab']) ? sanitize_key($_GET['htab']) : 'user';
  $base = admin_url('admin.php?page=langa-tools-client-settings&tab=help');

  echo '<div style="max-width:900px">';
  echo '<h2 style="margin:20px 0 4px">Help <code style="font-size:11px;font-weight:400">v' . esc_html($v) . '</code></h2>';
  echo '<p class="description" style="margin:0 0 14px">Scegli la guida piu adatta a te.</p>';

  // ── Setup Tour Guide launcher ──
  if (function_exists('langa_tools_client_tour_render_help_section')) {
    langa_tools_client_tour_render_help_section();
  }

  // Sub-tabs
  echo '<div style="display:flex;gap:0;margin:0 0 16px;border-bottom:2px solid #e5e5e7">';
  $tabs = array('user' => 'For site managers', 'dev' => 'For developers');
  foreach ($tabs as $tk => $tl) {
    $active = ($htab === $tk);
    $style = $active
      ? 'padding:8px 18px;font-size:13px;font-weight:600;color:#1d1d1f;border-bottom:2px solid #1d1d1f;margin-bottom:-2px;text-decoration:none;background:transparent'
      : 'padding:8px 18px;font-size:13px;color:#86868b;text-decoration:none;background:transparent';
    echo '<a href="' . esc_url($base . '&htab=' . $tk) . '" style="' . $style . '">' . esc_html($tl) . '</a>';
  }
  echo '</div>';

  if ($htab === 'dev') {
    langa_tools_client_help_dev();
  } else {
    langa_tools_client_help_user();
  }

  echo '</div>';
}

function langa_tools_client_help_user() {
  echo '<div class="langa-card" style="max-width:900px">';

  // Moduli: compact reference
  echo '<h3 style="margin:0 0 10px;font-size:15px">Modules</h3>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat striped" style="font-size:13px">';
  echo '<thead><tr><th style="width:120px">Module</th><th>What it does</th><th style="width:140px">Where</th></tr></thead><tbody>';
  $mods = array(
    array('Forms',       'Contact forms with per-form recipients, multilingual, email confirmation.', 'forms'),
    array('Business Card','BC page with quote request, vCard, QR code.',                'bc'),
    array('Legal (GDPR)','OPT-IN cookie banner + Privacy/Terms/Cookie pages.',                 'legal'),
    array('UI/UX',       'Maintenance, Visual Sitemap, seasonal effects, Custom Login, Credits.', 'ui-ux'),
    array('Safer',       'Hardening, Ghost Mode, Door Access, IP allowlist.',                   'safer'),
    array('Events',      'Local event log with filters and export. Optional remote Bridge.',     'events'),
    array('SEO',         'Sitemap XML, meta tags, Open Graph (placeholder).',                   'seo'),
    array('Cache',       'Cache headers, remove query strings, disable emojis.',                'cache'),
    array('Popup',       'Standalone popup system with triggers, auto-open and style.',        'popup'),
  );
  foreach ($mods as $m) {
    $url = admin_url('admin.php?page=langa-tools-client-' . $m[2]);
    echo '<tr><td style="font-weight:600">' . esc_html($m[0]) . '</td><td>' . esc_html($m[1]) . '</td>';
    echo '<td><a href="' . esc_url($url) . '">Open &rarr;</a></td></tr>';
  }
  echo '</tbody></table>';
  echo '</div>';

  // Come fare per: compact
  echo '<h3 style="margin:0 0 10px;font-size:15px">How to&hellip;</h3>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat striped" style="font-size:13px">';
  $how = array(
    array('Change form recipient', 'Forms &rarr; preset &rarr; Recipient field (multiple emails separated by comma).'),
    array('Enable maintenance mode', 'UI/UX &rarr; Maintenance &rarr; checkbox. Admins stay logged in.'),
    array('Enable SVG/MP4 uploads', 'UI/UX &rarr; Overview &rarr; Allowed file types &rarr; check format &rarr; Save.'),
    array('Customize BC style', 'BC &rarr; Style tab &rarr; colors, radius, font. Reset to restore defaults.'),
    array('Test email delivery', 'Settings &rarr; Email &rarr; Debug & Test &rarr; enter email &rarr; Send.'),
    array('Check server connection', 'Settings &rarr; General &rarr; Health table (license status, modules, endpoint).'),
    array('View error logs', 'Settings &rarr; Debug &rarr; enable Debug mode &rarr; reproduce error &rarr; read log.'),
  );
  foreach ($how as $h) {
    echo '<tr><td style="font-weight:600;width:220px">' . esc_html($h[0]) . '</td><td>' . esc_html($h[1]) . '</td></tr>';
  }
  echo '</table>';
  echo '</div>';

  // Common Issues
  echo '<h3 style="margin:0 0 10px;font-size:15px">Common Issues</h3>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat striped" style="font-size:13px">';
  echo '<thead><tr><th style="width:180px">Issue</th><th>Solution</th></tr></thead><tbody>';
  $probs = array(
    array('Form not sending email', 'Configure SMTP in Settings &rarr; Email. Check recipient in form preset.'),
    array('Disabled modules', 'License expired/invalid. Settings &rarr; General &rarr; Health to verify.'),
    array('Broken styles', 'Clear cache (WP Rocket/LiteSpeed). Temporarily disable JS/CSS merge.'),
    array('Cookie banner not showing', 'Enable Legal module. Clear site cookies from browser to test.'),
    array('File upload blocked', 'Format not enabled. UI/UX &rarr; Overview &rarr; Allowed file types.'),
  );
  foreach ($probs as $p) {
    echo '<tr><td style="font-weight:600">' . esc_html($p[0]) . '</td><td>' . esc_html($p[1]) . '</td></tr>';
  }
  echo '</tbody></table>';
  echo '</div>';
  echo '</div>';
}

function langa_tools_client_help_dev() {
  $server = defined('LANGA_TOOLS_FIXED_SERVER_URL') ? LANGA_TOOLS_FIXED_SERVER_URL : '?';

  echo '<div class="langa-card" style="max-width:900px">';

  // Architecture
  echo '<h3 style="margin:0 0 10px;font-size:15px">Architettura</h3>';
  echo '<p style="font-size:13px;margin:0 0 8px">The plugin uses <strong>isolated modules</strong>. Each module has:</p>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat" style="font-size:12px;font-family:monospace">';
  echo '<tr><td style="width:280px">includes/&lt;module&gt;/module.php</td><td>Runtime (frontend + hooks)</td></tr>';
  echo '<tr><td>includes/&lt;module&gt;/boot.php</td><td>Bootstrap: shortcodes, enqueue, init</td></tr>';
  echo '<tr><td>admin/modules/&lt;module&gt;-ui.php</td><td>Admin UI (settings)</td></tr>';
  echo '<tr><td>admin/settings/save.php</td><td>Centralized save handler</td></tr>';
  echo '<tr><td>includes/registry.php</td><td>Registro + enable/disable + kill-switch</td></tr>';
  echo '</table>';
  echo '</div>';

  // Data flow
  echo '<h3 style="margin:0 0 10px;font-size:15px">Centralized data</h3>';
  echo '<p style="font-size:13px;margin:0 0 8px">Settings &rarr; Data contains data shared across modules:</p>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat striped" style="font-size:12px">';
  echo '<tr><td style="width:200px;font-weight:600">company.*</td><td>Data Controller. Used by Legal, BC vCard, email templates.</td></tr>';
  echo '<tr><td style="font-weight:600">developer.*</td><td>Data processor. Used by Credits (logo, slogan, services, link), Custom Login, Legal.</td></tr>';
  echo '<tr><td style="font-weight:600">bank.*</td><td>Bank Details (IBAN, BIC). Used by BC vCard.</td></tr>';
  echo '<tr><td style="font-weight:600">shipping.*</td><td>Shipping addresses (primary + extra).</td></tr>';
  echo '</table>';
  echo '</div>';

  // Key functions
  echo '<h3 style="margin:0 0 10px;font-size:15px">Internal API</h3>';
  echo '<div class="langa-scroll-table langa-scroll-table--short">';
  echo '<table class="widefat striped" style="font-size:12px">';
  echo '<thead><tr><th style="width:45%">Function</th><th>Description</th></tr></thead><tbody>';
  $fns = array(
    array('langa_tools_client_feature_is_enabled($key)', 'Check runtime: module ON + valid license.'),
    array('langa_tools_client_feature_is_config_enabled($key)', 'Config-only check (toggle). For admin UI.'),
    array('langa_tools_client_license_is_valid($force)', 'Verify license with transient cache.'),
    array('langa_tools_client_get_site_data($key, $default)', 'Reads centralized data (dot-notation: company.email).'),
    array('langa_tools_client_mail_send($args)', 'Send email with unified template (orange banner).'),
    array('langa_tools_client_debug_log($type, $msg, $extra)', 'Writes to debug log (if debug ON).'),
    array('langa_t($group, $key, $vars)', 'Translated i18n string (7 languages).'),
  );
  foreach ($fns as $f) {
    echo '<tr><td style="font-family:monospace">' . esc_html($f[0]) . '</td><td>' . esc_html($f[1]) . '</td></tr>';
  }
  echo '</tbody></table></div>';

  // Conventions
  echo '<h3 style="margin:0 0 10px;font-size:15px">Conventions</h3>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat striped" style="font-size:13px">';
  $rules = array(
    array('Tab save handler', 'Each tab saves ONLY its own fields (merge with $prev).'),
    array('Security', 'Every POST: wp_nonce_field + check_admin_referer + manage_options.'),
    array('Sanitization', 'sanitize_text_field(), sanitize_email(), (int), wp_kses_post().'),
    array('Escape', 'esc_html(), esc_attr(), esc_url() on every dynamic output.'),
    array('i18n (frontend)', 'Never hardcode. Use langa_tools_client_i18n($group).'),
  );
  foreach ($rules as $r) {
    echo '<tr><td style="width:160px;font-weight:600">' . esc_html($r[0]) . '</td><td>' . esc_html($r[1]) . '</td></tr>';
  }
  echo '</table>';
  echo '</div>';

  // REST endpoints
  echo '<h3 style="margin:0 0 10px;font-size:15px">Server endpoint (' . esc_html($server) . ')</h3>';
  echo '<div class="langa-scroll-table">';
  echo '<table class="widefat striped" style="font-size:12px">';
  echo '<tr><td style="font-family:monospace;width:45%">GET .../events/ping</td><td>Health check (200 = OK)</td></tr>';
  echo '<tr><td style="font-family:monospace">POST .../license/check</td><td>License verification (HMAC-SHA256 signed)</td></tr>';
  echo '<tr><td style="font-family:monospace">POST .../events/log-event</td><td>Bridge event forwarding</td></tr>';
  echo '</table>';
  echo '</div>';

  echo '</div>';
}


/**
 * Settings → Site Health tab
 * Global score dashboard with actionable guidance.
 */
function langa_tools_client_settings_tab_test() {
  echo '<div style="max-width:965px;margin-top:16px">';

  $modules_to_test = array('cache', 'safer', 'legal', 'seo', 'forms', 'bc', 'popup', 'bridge');
  $module_meta = array(
    'cache' => array('label'=>'Cache &amp; Performance','icon'=>'dashicons-performance','color'=>'#ea580c',
      'what'=>'Speed optimizations: browser caching, script defer/delay, lazy loading, DNS prefetch, preconnect.',
      'why'=>'Google measures Core Web Vitals. A faster site ranks better, converts more visitors, and costs less.',
      'tip'=>'Blog pack is safe for any site. Corporate adds Delay JS — test sliders before enabling.',
    ),
    'safer' => array('label'=>'Security (Safer)','icon'=>'dashicons-lock','color'=>'#dc2626',
      'what'=>'Hardening: hide WP version/fingerprints, disable XML-RPC, block enumeration, file editor, HTTPS, Ghost Mode.',
      'why'=>'Each visible fingerprint is a target. Bots scan for these identifiers automatically and exploit known vulnerabilities.',
      'tip'=>'Basic is safe for all. Business adds file editor lock. Fortress requires Apache mod_rewrite.',
    ),
    'legal' => array('label'=>'Legal &amp; GDPR','icon'=>'dashicons-shield-alt','color'=>'#7c3aed',
      'what'=>'Compliance: legal pack, cookie banner, privacy page, cookie policy, terms, impressum.',
      'why'=>'GDPR fines can reach 4% of annual turnover. Visitors trust sites with clear privacy policies.',
      'tip'=>'Showcase pack covers blogs. E-commerce needs Terms + Impressum. Run Smart Setup to auto-generate pages.',
    ),
    'seo' => array('label'=>'SEO','icon'=>'dashicons-search','color'=>'#16a34a',
      'what'=>'Search visibility: titles/meta, sitemap, canonical URLs, schema, breadcrumbs, OpenGraph, Twitter Cards, IndexNow.',
      'why'=>'Without proper SEO signals, search engines can\'t index or rank your content effectively.',
      'tip'=>'Light mode covers basics. Standard adds social. Turbo adds IndexNow for instant indexing.',
    ),
    'forms' => array('label'=>'Forms','icon'=>'dashicons-email-alt','color'=>'#0071e3',
      'what'=>'Contact form pipeline: form enabled and recipient email configured.',
      'why'=>'A form without a recipient means lost leads. Make sure email delivery is connected.',
      'tip'=>'Set up SMTP in Settings &rarr; Endpoint for reliable delivery.',
    ),
    'bc' => array('label'=>'Business Card (Credits)','icon'=>'dashicons-id-alt','color'=>'#f37f0d',
      'what'=>'Developer credits: company name, URL, and optional staff section displayed in footer.',
      'why'=>'Your signature on every site you build. Protects your branding identity after client handoff.',
      'tip'=>'Enable credits and set your company info. The staff section adds team member profiles.',
    ),
    'popup' => array('label'=>'Popup','icon'=>'dashicons-megaphone','color'=>'#ec4899',
      'what'=>'On-site popups: create popups and activate them with shortcodes or auto-open triggers.',
      'why'=>'Popups drive conversions — newsletters, promotions, announcements, cookie notices.',
      'tip'=>'Create at least one popup and set it to Active. Use auto-open for site-wide visibility.',
    ),
    'bridge' => array('label'=>'Events (Bridge)','icon'=>'dashicons-chart-area','color'=>'#6366f1',
      'what'=>'Event tracking: page views, form submissions, WooCommerce events, logged locally.',
      'why'=>'Without event data, you can\'t measure what works. Local tracking respects privacy.',
      'tip'=>'Enable page view tracking first. Add form and WooCommerce tracking as needed.',
    ),
  );

  $total_score = 0; $total_abs = 0; $tested = 0; $results = array(); $total_on = 0; $total_max = 0; $total_issues = 0;
  $all_mod_count = 0; $inactive_count = 0;

  foreach ($modules_to_test as $mod) {
    if (!function_exists('langa_tools_client_feature_is_config_enabled')) continue;
    $all_mod_count++;
    if (!langa_tools_client_feature_is_config_enabled($mod)) {
      $total_abs += 0; // disabled = 0% absolute
      $inactive_count++;
      continue;
    }
    $data = langa_tools_client_module_score($mod);
    if (!$data) continue;
    $results[$mod] = $data;
    $total_score += $data['score'];
    $total_abs += (isset($data['abs_pct']) ? (int)$data['abs_pct'] : $data['score']);
    $total_on += (isset($data['abs_on']) ? (int)$data['abs_on'] : 0);
    $total_max += (isset($data['abs_max']) ? (int)$data['abs_max'] : 0);
    $total_issues += count($data['suggestions']);
    $tested++;
  }

  $avg = $tested > 0 ? (int)round($total_score / $tested) : 0; // relative: enabled only
  $avg_abs = $all_mod_count > 0 ? (int)round($total_abs / $all_mod_count) : 0; // absolute: ALL modules
  if ($avg >= 80)      { $gc = '#16a34a'; $verdict = 'Your site is well configured.'; $verdict_sub = 'Most features are active across your modules.'; }
  elseif ($avg >= 50)  { $gc = '#f37f0d'; $verdict = 'Decent, room to improve.'; $verdict_sub = 'Solid foundation. Enabling a few more features will make a real difference.'; }
  else                 { $gc = '#dc2626'; $verdict = 'Your site needs attention.'; $verdict_sub = 'Several important features are still off. Most fixes take just one click.'; }
  if ($avg_abs >= 80) $ac = '#16a34a'; elseif ($avg_abs >= 50) $ac = '#f37f0d'; else $ac = '#dc2626';

  // ── Global score card with DUAL gauge ──
  $cx = 90; $cy = 84;
  // Inner arc (relative/pack)
  $r1 = 58; $pct1 = max(0,min(100,$avg))/100; $a1 = $pct1*180; $rd1 = deg2rad(180-$a1);
  $ex1 = $cx+cos($rd1)*$r1; $ey1 = $cy-sin($rd1)*$r1; $lg1 = ($a1>180)?1:0;
  // Outer arc (absolute/total)
  $r2 = 70; $pct2 = max(0,min(100,$avg_abs))/100; $a2 = $pct2*180; $rd2 = deg2rad(180-$a2);
  $ex2 = $cx+cos($rd2)*$r2; $ey2 = $cy-sin($rd2)*$r2; $lg2 = ($a2>180)?1:0;

  echo '<div class="langa-card" style="padding:30px 24px">';
  echo '<div style="display:flex;gap:30px;align-items:center;flex-wrap:wrap">';

  // Dual gauge SVG
  echo '<div style="flex-shrink:0;text-align:center">';
  echo '<svg viewBox="0 0 180 100" width="200" height="110" style="display:block">';
  // Outer track (absolute)
  echo '<path d="M '.($cx-$r2).' '.$cy.' A '.$r2.' '.$r2.' 0 0 1 '.($cx+$r2).' '.$cy.'" fill="none" stroke="#f0f0f0" stroke-width="4" stroke-linecap="round"/>';
  if ($avg_abs > 0) echo '<path d="M '.($cx-$r2).' '.$cy.' A '.$r2.' '.$r2.' 0 '.$lg2.' 1 '.round($ex2,1).' '.round($ey2,1).'" fill="none" stroke="'.esc_attr($ac).'" stroke-width="4" stroke-linecap="round" opacity=".4"/>';
  // Inner track (relative)
  echo '<path d="M '.($cx-$r1).' '.$cy.' A '.$r1.' '.$r1.' 0 0 1 '.($cx+$r1).' '.$cy.'" fill="none" stroke="#e5e5e7" stroke-width="10" stroke-linecap="round"/>';
  if ($avg > 0) echo '<path d="M '.($cx-$r1).' '.$cy.' A '.$r1.' '.$r1.' 0 '.$lg1.' 1 '.round($ex1,1).' '.round($ey1,1).'" fill="none" stroke="'.esc_attr($gc).'" stroke-width="10" stroke-linecap="round"/>';
  echo '<text x="'.$cx.'" y="'.($cy-4).'" text-anchor="middle" font-size="30" font-weight="700" fill="'.esc_attr($gc).'">'.$avg.'</text>';
  echo '</svg>';
  // Legend
  echo '<div style="display:flex;gap:12px;justify-content:center;margin-top:4px;font-size:10px;color:#86868b">';
  echo '<span><span style="display:inline-block;width:16px;height:3px;background:'.esc_attr($gc).';border-radius:2px;vertical-align:middle;margin-right:3px"></span>Pack</span>';
  echo '<span><span style="display:inline-block;width:16px;height:3px;background:'.esc_attr($ac).';border-radius:2px;vertical-align:middle;margin-right:3px;opacity:.4"></span>Total</span>';
  echo '</div>';
  echo '</div>';

  // Summary text
  echo '<div style="flex:1;min-width:200px">';
  echo '<div style="font-size:22px;font-weight:700;color:'.esc_attr($gc).'">'.$avg.'/100 <span style="font-size:13px;font-weight:500;color:#86868b">pack score</span></div>';
  if ($avg_abs !== $avg) {
    echo '<div style="font-size:14px;font-weight:600;color:'.esc_attr($ac).';margin-top:2px">'.$avg_abs.'/100 <span style="font-weight:400;color:#86868b">total potential</span></div>';
  }
  echo '<div style="font-size:15px;font-weight:600;color:#1d1d1f;margin:6px 0 2px">'.esc_html($verdict).'</div>';
  echo '<div style="font-size:13px;color:#6e6e73;line-height:1.5">'.esc_html($verdict_sub).'</div>';
  echo '<div style="display:flex;gap:16px;margin-top:10px;font-size:12px;color:#6e6e73;flex-wrap:wrap">';
  echo '<span><strong style="color:#1d1d1f">'.$tested.'</strong>/'.$all_mod_count.' modules active</span>';
  echo '<span><strong style="color:#16a34a">'.$total_on.'</strong>/'.$total_max.' features active</span>';
  $req_issues = 0; $opt_issues = 0;
  foreach ($results as $rd) { foreach ($rd['suggestions'] as $sg) { if (!empty($sg['required'])) $req_issues++; else $opt_issues++; } }
  if ($req_issues > 0) echo '<span><strong style="color:#dc2626">'.$req_issues.'</strong> required missing</span>';
  if ($opt_issues > 0) echo '<span><strong style="color:#f37f0d">'.$opt_issues.'</strong> optional off</span>';
  echo '</div>';
  if ($inactive_count > 0) {
    echo '<div style="margin-top:8px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:11px;color:#c56200;line-height:1.4">';
    echo '<strong>'.$inactive_count.' module'.($inactive_count>1?'s':'').' inactive</strong> — enabling them will improve your total potential score. ';
    echo '<a href="'.esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=general#langa-modules')).'" style="color:#c56200;font-weight:700">Activate modules &rarr;</a>';
    echo '</div>';
  }
  echo '</div>';
  echo '</div>';
  echo '</div>';

  // ── Per-module detailed cards ──
  if (!empty($results)) {
    echo '<h3 style="margin:20px 0 12px;font-size:15px;color:#1d1d1f">Module breakdown</h3>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">';

    foreach ($results as $mod => $data) {
      $s = (int)$data['score'];
      $ap = isset($data['abs_pct']) ? (int)$data['abs_pct'] : $s;
      $aon = isset($data['abs_on']) ? (int)$data['abs_on'] : 0;
      $amax = isset($data['abs_max']) ? (int)$data['abs_max'] : 0;
      if ($s >= 80) $mc = '#16a34a'; elseif ($s >= 50) $mc = '#f37f0d'; else $mc = '#dc2626';
      if ($ap >= 80) $mac = '#16a34a'; elseif ($ap >= 50) $mac = '#f37f0d'; else $mac = '#dc2626';

      $meta = isset($module_meta[$mod]) ? $module_meta[$mod] : array('label'=>ucfirst($mod),'icon'=>'dashicons-admin-generic','color'=>'#6e6e73','what'=>'','why'=>'','tip'=>'');
      $mod_url = admin_url('admin.php?page=' . langa_tools_client_page_slug($mod));
      $uid = 'langa-health-' . esc_attr($mod);

      echo '<div class="langa-card" style="padding:0;overflow:hidden;margin-bottom:0">';

      // Header with gauge — vertical layout
      echo '<div style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 20px">';

      // Mini dual gauge
      $gcx=30;$gcy=28;$gr1m=18;$gr2m=24;
      $mp1=max(0,min(100,$s))/100;$ma1=$mp1*180;$mr1=deg2rad(180-$ma1);$mex1=$gcx+cos($mr1)*$gr1m;$mey1=$gcy-sin($mr1)*$gr1m;$ml1=($ma1>180)?1:0;
      $mp2=max(0,min(100,$ap))/100;$ma2=$mp2*180;$mr2=deg2rad(180-$ma2);$mex2=$gcx+cos($mr2)*$gr2m;$mey2=$gcy-sin($mr2)*$gr2m;$ml2=($ma2>180)?1:0;
      echo '<svg viewBox="0 0 60 34" width="64" height="36" style="flex-shrink:0">';
      // Outer (absolute)
      echo '<path d="M 6 28 A 24 24 0 0 1 54 28" fill="none" stroke="#f0f0f0" stroke-width="2.5" stroke-linecap="round"/>';
      if ($ap > 0) echo '<path d="M 6 28 A 24 24 0 '.$ml2.' 1 '.round($mex2,1).' '.round($mey2,1).'" fill="none" stroke="'.esc_attr($mac).'" stroke-width="2.5" stroke-linecap="round" opacity=".4"/>';
      // Inner (relative)
      echo '<path d="M 12 28 A 18 18 0 0 1 48 28" fill="none" stroke="#e5e5e7" stroke-width="5" stroke-linecap="round"/>';
      if ($s > 0) echo '<path d="M 12 28 A 18 18 0 '.$ml1.' 1 '.round($mex1,1).' '.round($mey1,1).'" fill="none" stroke="'.esc_attr($mc).'" stroke-width="5" stroke-linecap="round"/>';
      echo '<text x="30" y="27" text-anchor="middle" font-size="12" font-weight="700" fill="'.esc_attr($mc).'">'.$s.'</text>';
      echo '</svg>';

      // Module name + scores (centered)
      echo '<div style="text-align:center;min-width:0">';
      echo '<div style="display:inline-flex;align-items:center;gap:6px">';
      echo '<span class="dashicons '.esc_attr($meta['icon']).'" style="color:'.esc_attr($meta['color']).';font-size:16px;width:16px;height:16px"></span>';
      echo '<span style="font-weight:600;font-size:14px;color:#1d1d1f">'.$meta['label'].'</span>';
      echo '</div>';
      echo '<div style="font-size:11px;color:#6e6e73;margin-top:2px">'.esc_html($data['label']);
      if ($ap !== $s) echo ' &middot; <span style="color:'.esc_attr($mac).'">'.$aon.'/'.$amax.' total ('.$ap.'%)</span>';
      echo '</div>';
      echo '</div>';

      // Action button
      echo '<a href="'.esc_url($mod_url).'" class="button button-small" style="font-size:11px">Open module</a>';
      echo '</div>';

      // ALWAYS EXPANDED: features + tips
      echo '<div style="border-top:1px solid #e5e5e7;padding:14px 20px;background:#fafafa">';

      // Description
      if (!empty($meta['what'])) {
        echo '<div style="font-size:12px;color:#374151;margin:0 0 10px;line-height:1.5"><strong>What this covers:</strong> '.wp_kses_post($meta['what']).'</div>';
      }

      // Feature chips (all checks)
      echo '<div style="display:flex;flex-wrap:wrap;gap:4px;margin:0 0 10px">';
      foreach ($data['checks'] as $ch) {
        $is_on = $ch[1];
        $is_req = isset($ch[4]) ? (bool)$ch[4] : true;
        $cname = esc_html($ch[0]);
        $tab_link = isset($ch[3]) ? esc_url(add_query_arg('tab', $ch[3], $mod_url)) : '';
        if ($is_on) {
          echo '<span style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:#dcfce7;border:1px solid #bbf7d0;border-radius:5px;font-size:11px;color:#166534;font-weight:500">';
          echo '<span style="font-size:9px">&#x2713;</span> '.$cname.'</span>';
        } else {
          $border_col = $is_req ? '#fca5a5' : '#e5e5e7';
          $bg_col = $is_req ? '#fef2f2' : '#f5f5f7';
          $text_col = $is_req ? '#991b1b' : '#86868b';
          if ($tab_link) {
            echo '<a href="'.$tab_link.'" style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:'.$bg_col.';border:1px solid '.$border_col.';border-radius:5px;font-size:11px;color:'.$text_col.';text-decoration:none;font-weight:500">';
            echo '<span style="font-size:9px">&#x2717;</span> '.$cname;
            if (!$is_req) echo ' <span style="font-size:9px;color:#a3a3a3">(opt)</span>';
            echo '</a>';
          } else {
            echo '<span style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:'.$bg_col.';border:1px solid '.$border_col.';border-radius:5px;font-size:11px;color:'.$text_col.';font-weight:500">';
            echo '<span style="font-size:9px">&#x2717;</span> '.$cname.'</span>';
          }
        }
      }
      echo '</div>';

      // Why it matters + tip
      if (!empty($meta['why'])) {
        echo '<div style="font-size:11px;color:#374151;line-height:1.5;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;margin:0 0 8px">';
        echo '<strong style="color:#c56200">Why it matters:</strong> '.esc_html($meta['why']);
        echo '</div>';
      }
      if (!empty($meta['tip'])) {
        echo '<div style="font-size:11px;color:#374151;line-height:1.5;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px">';
        echo '<strong style="color:#1e40af">Tip:</strong> '.wp_kses_post($meta['tip']);
        echo '</div>';
      }

      echo '</div>'; // fafafa block
      echo '</div>'; // card
    }
    echo '</div>'; // grid
  }

  // Disabled modules hint
  $disabled = array();
  foreach ($modules_to_test as $mod) {
    if (!isset($results[$mod]) && function_exists('langa_tools_client_feature_is_config_enabled') && !langa_tools_client_feature_is_config_enabled($mod)) {
      $disabled[] = isset($module_meta[$mod]) ? strip_tags($module_meta[$mod]['label']) : ucfirst($mod);
    }
  }
  if (!empty($disabled)) {
    echo '<div style="margin-top:16px;padding:12px 16px;background:#f9fafb;border:1px solid #e5e5e7;border-radius:10px;font-size:12px;color:#6e6e73">';
    echo '<strong>Not tested:</strong> '.esc_html(implode(', ', $disabled)).' (disabled). Enable them in <a href="'.esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=general#langa-modules')).'">Settings &rarr; General</a> to include them in the health check.';
    echo '</div>';
  }

  if ($tested === 0) {
    echo '<div class="langa-card" style="text-align:center;padding:40px;color:#6e6e73">';
    echo '<span class="dashicons dashicons-info-outline" style="font-size:40px;width:40px;height:40px;margin:0 0 12px;color:#d2d2d7"></span>';
    echo '<p style="font-size:14px;margin:0 0 6px;font-weight:600;color:#1d1d1f">No modules to test</p>';
    echo '<p style="margin:0">Enable at least one module in <a href="'.esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=general#langa-modules')).'">Settings &rarr; General</a> to start seeing health scores.</p>';
    echo '</div>';
  }

  echo '</div>';
}

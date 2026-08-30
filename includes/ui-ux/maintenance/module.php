<?php
if (!defined('ABSPATH')) exit;

/**
 * Maintenance frontend page + contact form
 * - shows a 503 page to non-admin visitors when enabled
 * - invia email usando wp_mail (configurabile da Settings → Invio (Server))
 */

add_action('template_redirect', 'langa_tools_client_maybe_show_maintenance', 0);

// Intercept POST on HOME (works even if Safer blocks /wp-admin/admin-post.php)
add_action('init', function () {
  if (empty($_POST['langa_maintenance_submit'])) return;
  if (!empty($_POST['action']) && $_POST['action'] === 'langa_tools_client_maintenance_contact') {
    langa_tools_client_maintenance_contact();
    exit;
  }
}, 0);

/**
 * Show maintenance page (503) for non-admins
 */
function langa_tools_client_maybe_show_maintenance() {
  $s = get_option('langa_tools_adminux_settings', array());
  if (!is_array($s) || empty($s['maintenance'])) return;

// Logged-in users always bypass maintenance.
// The maintenance page is for anonymous visitors only.
if (is_user_logged_in()) {
  return;
}

  // Allow wp-login, wp-admin, and admin-ajax (avoid breaking login/ajax)
  $req = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
  if (stripos($req, 'wp-login.php') !== false) return;
  if (stripos($req, 'admin-ajax.php') !== false) return;
  if (stripos($req, '/wp-admin') !== false) return;

  // Allow Safer door slug (custom login URL) — must be reachable during maintenance
  if (function_exists('langa_tools_client_safer_get_rewrite_slugs')) {
    $safer_slugs = langa_tools_client_safer_get_rewrite_slugs();
    if (is_array($safer_slugs)) {
      $door_s = isset($safer_slugs['login']) ? trim((string)$safer_slugs['login'], '/ ') : '';
      $admin_s = isset($safer_slugs['admin']) ? trim((string)$safer_slugs['admin'], '/ ') : '';
      if ($door_s !== '' && stripos($req, '/' . $door_s) !== false) return;
      if ($admin_s !== '' && stripos($req, '/' . $admin_s) !== false) return;
    }
  }

  // Allow plugin assets (credits iframe, JS, CSS)
  if (stripos($req, '/langa-assets/') !== false) return;

  // Allow credits iframe served by mu-plugin
  if (isset($_GET['langa-credits-frame'])) return;

  // Allow REST API and favicon (avoid breaking integrations / browser requests)
  if (stripos($req, '/wp-json/') !== false || stripos($req, 'rest_route=') !== false) return;
  $path = strtok($req, '?');
  if (is_string($path) && preg_match('~/(favicon\.ico)$~i', $path)) return;

  status_header(503);
  header('Retry-After: 3600');

  echo langa_tools_client_render_maintenance_page();

  if (ob_get_level() > 0) ob_end_flush();
  exit;
}

/**
 * Handle maintenance form submit:
 * - validate fields
 * - invia email localmente (SMTP opzionale via Settings → Invio (Server))
 * - redirect to ?maintenance=sent&mail=1|0
 */
function langa_tools_client_maintenance_contact() {
  if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'langa_maintenance_contact')) {
    wp_die('Invalid request');
  }

  // Required
  $name    = sanitize_text_field($_POST['name'] ?? '');
  $surname = sanitize_text_field($_POST['surname'] ?? '');
  $email   = sanitize_email($_POST['email'] ?? '');
  $message = sanitize_textarea_field($_POST['message'] ?? '');

  // Optional
  $company = sanitize_text_field($_POST['company'] ?? '');
  $country = sanitize_text_field($_POST['phone_country'] ?? '+39');
  $phone   = sanitize_text_field($_POST['phone'] ?? '');
  $full_phone = trim($country . ' ' . $phone);

  if ($name === '' || $surname === '' || $email === '' || $message === '' || !is_email($email) || strlen($message) < 5) {
    wp_safe_redirect(home_url('/?maintenance=fail&reason=required'));
    exit;
  }

  // 1. Per-module recipient (UI/UX → Maintenance → Email destinatario)
  $notify = '';
  $ax = get_option('langa_tools_adminux_settings', array());
  if (is_array($ax) && !empty($ax['maintenance_recipient'])) {
    $mr = trim((string)$ax['maintenance_recipient']);
    $parts = array_map('trim', explode(',', $mr));
    $valid = array();
    foreach ($parts as $p) {
      if (is_email($p)) $valid[] = $p;
    }
    if (!empty($valid)) $notify = implode(',', $valid);
  }
  // 2. Centralized fallback (Settings → Invio (Server))
  if ($notify === '' && function_exists('langa_tools_client_mail_get_primary_recipient')) {
    $notify = (string)langa_tools_client_mail_get_primary_recipient();
  }
  // 3. admin_email fallback
  if ($notify === '' || !is_email(explode(',', $notify)[0])) {
    $notify = sanitize_email(get_option('admin_email'));
  }

  // Validate: at least one valid email
  $n_parts = array_map('trim', explode(',', $notify));
  $n_valid = array();
  foreach ($n_parts as $np) {
    if (is_email($np)) $n_valid[] = $np;
  }
  if (empty($n_valid)) {
    wp_safe_redirect(home_url('/?maintenance=fail&reason=no_recipient'));
    exit;
  }
  $notify = implode(',', $n_valid);

  // Send via local server (wp_mail + optional SMTP)
  $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
$vars = array(
  'site' => $site_name,
  'preset' => 'Maintenance',
  'is_confirmation' => false, // Staff notification — NOT a confirmation email
  'module_badge' => 'MAINTENANCE',
  'module_badge_bg' => '#fef3c7',
  'module_badge_color' => '#92400e',
  'who' => trim($name . ' ' . $surname),
  'sender_name' => trim($name . ' ' . $surname),
  'sender_email' => $email,
  'page_url' => home_url('/'),
);

// Apply Maintenance style accent to email
$adminux_s = get_option('langa_tools_adminux_settings', array());
if (is_array($adminux_s) && !empty($adminux_s['maintenance_style']['primary_color'])) {
  $vars['accent_color'] = sanitize_hex_color((string)$adminux_s['maintenance_style']['primary_color']);
}

$subject_default = 'Maintenance — New contact from site';
$subject = $subject_default;
if (function_exists('langa_tools_client_mail_tpl_text')) {
  $subject = langa_tools_client_mail_tpl_text('maintenance', 'subject', $subject_default, $vars);
}

$title_default = 'New contact (Maintenance)';
$title = $title_default;
if (function_exists('langa_tools_client_mail_tpl_text')) {
  $title = langa_tools_client_mail_tpl_text('maintenance', 'title', $title_default, $vars);
}
$content = '';
$content .= '<p><strong>Name:</strong> '.esc_html($name.' '.$surname).'</p>';
if ($company !== '') $content .= '<p><strong>Company:</strong> '.esc_html($company).'</p>';
$content .= '<p><strong>Email:</strong> '.esc_html($email).'</p>';
if (trim($full_phone) !== '') $content .= '<p><strong>Phone:</strong> '.esc_html(trim($full_phone)).'</p>';
$content .= '<p><strong>Message:</strong><br>'.nl2br(esc_html($message)).'</p>';

$ok = false;
if (function_exists('langa_tools_client_mail_send')) {
  $ok = langa_tools_client_mail_send(array(
    'to' => $notify,
    'subject' => $subject,
    'title' => $title,
    'content_html' => $content,
    'reply_to' => $email,
    'vars' => $vars,
  ));
} else {
  // Fallback plain wp_mail
  $from_email = sanitize_email(get_option('admin_email'));
  $from_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $from_name . ' <' . $from_email . '>',
    'Reply-To: ' . $email,
  );
  $ok = (bool) wp_mail($notify, $subject, $content, $headers);
}
if (function_exists('langa_tools_client_debug_log_mail')) langa_tools_client_debug_log_mail('maintenance', $notify, $ok, $ok ? 'Sent' : 'wp_mail failed');

// Sender confirmation email (recap to person who submitted)
if ($ok && $email !== '' && is_email($email)) {
  if (function_exists('langa_tools_client_mail_send_confirmation')) {
    $conf_ok = langa_tools_client_mail_send_confirmation(array(
      'to' => $email,
      'sender_name' => trim($name . ' ' . $surname),
      'module' => 'maintenance',
      'module_label' => 'Maintenance',
      'site' => $site_name,
      'summary_html' => $content,
      'accent_color' => !empty($adminux_s['maintenance_style']['primary_color']) ? sanitize_hex_color((string)$adminux_s['maintenance_style']['primary_color']) : '',
    ));
    if (function_exists('langa_tools_client_debug_log')) {
      langa_tools_client_debug_log('maint_confirm', $conf_ok ? 'OK → ' . $email : 'FAIL → ' . $email, array('module' => 'maintenance', 'to' => $email));
    }
  } else {
    // Fallback: send a simple recap via wp_mail
    $conf_subject = 'Confirmation — ' . $site_name;
    $conf_body = '<p>Hi <strong>' . esc_html(trim($name . ' ' . $surname)) . '</strong>,</p>';
    $conf_body .= '<p>We received your request. We will get back to you as soon as possible.</p>';
    $conf_body .= '<div style="margin:14px 0;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:13px">';
    $conf_body .= '<p style="margin:0 0 8px;font-weight:700;color:#374151">Summary:</p>' . $content . '</div>';
    $conf_body .= '<p style="font-size:13px;color:#6b7280">This is an automatic confirmation. You do not need to reply to this email.</p>';
    if (function_exists('langa_tools_client_mail_template')) {
      $conf_html = langa_tools_client_mail_template('We received your request', $conf_body, '', array(
        'site' => $site_name, 'is_confirmation' => true,
        'module_badge' => 'Maintenance', 'module_badge_bg' => '#f0fdf4', 'module_badge_color' => '#166534',
      ));
    } else {
      $conf_html = $conf_body;
    }
    wp_mail($email, $conf_subject, $conf_html, array('Content-Type: text/html; charset=UTF-8'));
    if (function_exists('langa_tools_client_debug_log')) {
      langa_tools_client_debug_log('maint_confirm', 'fallback wp_mail → ' . $email, array('module' => 'maintenance'));
    }
  }
}

// UX: always show thank you; mail=1 only when send ok
wp_safe_redirect(home_url('/?maintenance=sent&mail=' . ($ok ? '1' : '0')));
exit;
}

/**
 * Render maintenance page
 */

// --- Assets bypass maintenance (no wp_head/wp_footer) ---
function langa_tools_client_maintenance_asset_url($rel){
  $rel = ltrim((string)$rel, "/");
  return home_url("/langa-assets/".$rel);
}

function langa_tools_client_maintenance_collect_assets(){
  $head = array();
  $foot = array();
  $inline_css = '';
  $inline_js  = '';

  // Shared phone flags (used by the maintenance contact form)
  $foot[] = langa_tools_client_maintenance_asset_url('phone-flags.js');

  // Ensure Effects functions are available
  if (!function_exists('langa_tools_client_get_effects_option')) {
    $m = LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/effects/module.php';
    if (is_readable($m)) require_once $m;
  }

  if (function_exists('langa_tools_client_get_effects_option')) {
    $opt = langa_tools_client_get_effects_option();
    if (is_array($opt) && !empty($opt['enabled'])) {

      if (!function_exists('langa_tools_client_should_apply_effect')) {
        $dw = LANGA_TOOLS_CLIENT_PATH . 'includes/ui-ux/effects/date-window.php';
        if (is_readable($dw)) require_once $dw;
      }

      $rows = isset($opt['rows']) && is_array($opt['rows']) ? $opt['rows'] : array();
      if (function_exists('langa_tools_client_should_apply_effect')) {
        foreach ($rows as $row) {
          if (!is_array($row)) continue;
          $effect = isset($row['effect']) ? sanitize_key($row['effect']) : '';
          if ($effect === '') continue;
          if (!langa_tools_client_should_apply_effect($row)) continue;
          $head[] = langa_tools_client_maintenance_asset_url('effects/'.$effect.'.css');
          $foot[] = langa_tools_client_maintenance_asset_url('effects/'.$effect.'.js');
        }

        // Custom inline effect (CSS/JS)
        $c = isset($opt['custom']) && is_array($opt['custom']) ? $opt['custom'] : array();
        $c_start = (string)($c['start_md'] ?? '');
        $c_end   = (string)($c['end_md'] ?? '');
        $c_css   = (string)($c['css'] ?? '');
        $c_js    = (string)($c['js'] ?? '');

        if ($c_css !== '' || $c_js !== '') {
          $win = array('start_md' => $c_start, 'end_md' => $c_end, 'before' => 0, 'after' => 0);
          if (langa_tools_client_should_apply_effect($win)) {
            if ($c_css !== '') $inline_css = str_replace(array('</style>', '</STYLE>'), '', $c_css);
            if ($c_js !== '')  $inline_js  = str_replace(array('</script>', '</SCRIPT>'), '', $c_js);
          }
        }
      }
    }
  }

  // Credits
  $credits_active = (function_exists('langa_credits_enabled') && langa_credits_enabled());
  if ($credits_active) {
    $foot[] = langa_tools_client_maintenance_asset_url('langa-credits.js');
  }

  return array(
    'head' => $head,
    'foot' => $foot,
    'inline_css' => $inline_css,
    'inline_js'  => $inline_js,
    'credits' => $credits_active,
  );
}

function langa_tools_client_maintenance_print_head_assets(){
  $a = langa_tools_client_maintenance_collect_assets();
  if (!empty($a['head'])) {
    foreach (array_unique($a['head']) as $u) {
      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone maintenance page
      echo '<link rel="stylesheet" href="'.esc_url($u).'" media="all" />' . "
";
    }
  }
  if (!empty($a['inline_css'])) {
    echo "
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone maintenance page ?>
<style id='langa-tools-custom-effect-css'>
" . $a['inline_css'] . "
</style>
";
  }
}

function langa_tools_client_maintenance_print_footer_assets(){
  $a = langa_tools_client_maintenance_collect_assets();
  if (!empty($a['foot'])) {
    foreach (array_unique($a['foot']) as $u) {
      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- maintenance page (no wp_head)
      echo '<script src="'.esc_url($u).'" defer></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone maintenance page
    }
  }
  if (!empty($a['inline_js'])) {
    echo "
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone maintenance page ?>
<script id='langa-tools-custom-effect-js'>(function(){
" . $a['inline_js'] . "
})();</script>
";
  }
}

function langa_tools_client_render_maintenance_page() {
  // phpcs:disable WordPress.WP.EnqueuedResources -- standalone maintenance page (no wp_head/wp_footer)
  // i18n
  $_mt = function_exists('langa_tools_client_i18n') ? langa_tools_client_i18n('maintenance') : array();

  $site_name_raw = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $site_name = esc_html($site_name_raw);
  $year = date('Y');

  $ax = get_option('langa_tools_adminux_settings', array());
  if (!is_array($ax)) $ax = array();
  $ms = isset($ax['maintenance_style']) && is_array($ax['maintenance_style']) ? $ax['maintenance_style'] : array();
  if (empty($ms['primary_color'])) $ms['primary_color'] = '#a8a29e';
  if (empty($ms['header_bg'])) $ms['header_bg'] = '#fafaf9';
  if (empty($ms['header_text'])) $ms['header_text'] = '#1c1917';
  if (empty($ms['body_bg'])) $ms['body_bg'] = '#f5f5f4';
  if (empty($ms['form_bg'])) $ms['form_bg'] = '#ffffff';
  if (empty($ms['text_color'])) $ms['text_color'] = '#1c1917';
  if (!isset($ms['radius'])) $ms['radius'] = 5;
  if (!isset($ms['custom_css'])) $ms['custom_css'] = '';

  // Theme logo (height 70px); fallback text
  $logo_url = '';
  $custom_logo_id = get_theme_mod('custom_logo');
  if ($custom_logo_id) {
    $src = wp_get_attachment_image_src($custom_logo_id, 'full');
    if (is_array($src) && !empty($src[0])) $logo_url = $src[0];
  }

  $sent = !empty($_GET['maintenance']) && sanitize_key(wp_unslash($_GET['maintenance'])) === 'sent';
  $fail = !empty($_GET['maintenance']) && sanitize_key(wp_unslash($_GET['maintenance'])) === 'fail';
  $reason = isset($_GET['reason']) ? sanitize_key($_GET['reason']) : '';
  $mail = isset($_GET['mail']) ? absint(wp_unslash($_GET['mail'])) : 1;

  $nonce = wp_create_nonce('langa_maintenance_contact');

  ob_start();
  ?>
<!-- phpcs:disable WordPress.WP.EnqueuedResources -- standalone maintenance page HTML document -->
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title><?php echo esc_html($site_name); ?> — Maintenance</title>

<?php langa_tools_client_maintenance_print_head_assets(); ?>

<?php
  $fav = function_exists('get_site_icon_url') ? get_site_icon_url(32) : '';
  if (!$fav) $fav = LANGA_TOOLS_CLIENT_URL . 'assets/images/plugin-icon.svg';
?>
<link rel="icon" href="<?php echo esc_url($fav); ?>" />
<link rel="shortcut icon" href="<?php echo esc_url($fav); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url($fav); ?>" />
  <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone maintenance page ?>
  <style>
    :root{
      --lm-accent: <?php echo esc_html($ms['primary_color']); ?>;
      --lm-header-bg: <?php echo esc_html($ms['header_bg']); ?>;
      --lm-header-text: <?php echo esc_html($ms['header_text']); ?>;
      --lm-body-bg: <?php echo esc_html($ms['body_bg']); ?>;
      --lm-form-bg: <?php echo esc_html($ms['form_bg']); ?>;
      --lm-text: <?php echo esc_html($ms['text_color']); ?>;
      --lm-radius: <?php echo (int)$ms['radius']; ?>px;
    }
    *,*:before,*:after{box-sizing:border-box !important;}
    html,body{height:100% !important;margin:0 !important}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif !important;background:var(--lm-body-bg) !important;color:var(--lm-text) !important;padding:24px !important;-webkit-text-size-adjust:100% !important;text-rendering:optimizeLegibility !important;text-transform:none !important;letter-spacing:normal !important;}
    .wrap{max-width:720px !important;width:100% !important;margin:0 auto !important;padding:0 !important;border:0 !important;float:none !important;}
    .panel{margin-top:25px !important;padding:0 !important;border:0 !important;background:transparent !important;}
    .top{display:flex !important;flex-direction:column !important;align-items:center !important;text-align:center !important;gap:10px !important;margin:0 !important;padding:0 !important;border:0 !important;}
    .logo{height:50px !important;width:auto !important;display:block !important;border:0 !important;margin:0 !important;padding:0 !important;}
    .brand-text{height:70px !important;display:flex !important;align-items:center !important;justify-content:center !important;font-weight:900 !important;font-size:22px !important;letter-spacing:.2px !important;margin:0 !important;padding:0 !important;border:0 !important;background:transparent !important;color:inherit !important;}
    h1{font-size:30px !important;margin:8px 0 0 !important;padding:0 !important;border:0 !important;background:transparent !important;color:inherit !important;text-transform:none !important;letter-spacing:normal !important;line-height:1.2 !important;}
    p{margin:0 !important;color:var(--lm-text) !important;opacity:.75 !important;line-height:1.55 !important;padding:0 !important;border:0 !important;background:transparent !important;}
    .msg{margin-top:10px !important;max-width:620px !important;border:0 !important;padding:0 !important;}
    .note{border:1px solid rgba(0,0,0,.12) !important;border-radius:var(--lm-radius) !important;padding:12px 14px !important;background:rgba(0,0,0,.03) !important;color:var(--lm-text) !important;margin:16px 0 0 !important;font-size:14px !important;line-height:1.45 !important;}
    .note.ok{border-left:4px solid #46b450 !important;}
    .note.err{border-left:4px solid #d63638 !important;}
    form{margin-top:18px !important;text-align:left !important;border:1px solid rgba(0,0,0,.08) !important;border-radius:var(--lm-radius) !important;padding:16px !important;background:var(--lm-form-bg) !important;box-shadow:none !important;float:none !important;}
    label{display:block !important;font-size:12px !important;color:var(--lm-text) !important;opacity:.75 !important;margin:10px 0 6px !important;font-weight:800 !important;padding:0 !important;border:0 !important;background:transparent !important;text-transform:none !important;letter-spacing:normal !important;}
    input,textarea,select,button{font-family:inherit !important;}
    input,textarea,select{width:100% !important;box-sizing:border-box !important;padding:11px 12px !important;border:1px solid rgba(0,0,0,.20) !important;border-radius:calc(var(--lm-radius) - 2px) !important;font-size:14px !important;background:#fff !important;outline:none !important;color:var(--lm-text) !important;box-shadow:none !important;margin:0 !important;-webkit-appearance:none !important;appearance:none !important;max-width:100% !important;height:auto !important;text-transform:none !important;letter-spacing:normal !important;}
    input:focus, textarea:focus, select:focus{border-color:var(--lm-accent) !important;box-shadow:0 0 0 3px rgba(0,0,0,0.06) !important;outline:none !important;}
    input:hover, textarea:hover, select:hover{border-color:rgba(0,0,0,.30) !important;}
    input::placeholder,textarea::placeholder{opacity:.55 !important;font-family:inherit !important;}
    textarea{min-height:110px !important;resize:vertical !important;}
    .row{display:grid !important;grid-template-columns:1fr 1fr !important;gap:12px !important;margin:0 !important;padding:0 !important;border:0 !important;}
    .phone-row{display:flex !important;gap:0 !important;align-items:stretch !important;margin:0 !important;padding:0 !important;border:0 !important;}
    select{margin-top:0 !important;}
    .phone-row .langa-phone-cc-wrap{
      position:relative !important;
      flex:0 0 56px !important;
      min-width:56px !important;
      max-width:56px !important;
      display:flex !important;
      align-items:center !important;
      justify-content:center !important;
      border:1px solid rgba(0,0,0,.20) !important;
      border-right:0 !important;
      border-top-left-radius:calc(var(--lm-radius) - 2px) !important;
      border-bottom-left-radius:calc(var(--lm-radius) - 2px) !important;
      border-top-right-radius:0 !important;
      border-bottom-right-radius:0 !important;
      background:#f5f5f4 !important;
      box-sizing:border-box !important;
      margin:0 !important;
      padding:0 !important;
    }
    .phone-row .langa-phone-cc-wrap .langa-phone-flag{width:26px !important;height:18px !important;border-radius:4px !important;display:block !important;z-index:2 !important;pointer-events:none !important;border:0 !important;margin:0 !important;padding:0 !important;}
    .phone-row .langa-phone-cc-wrap select[data-phone-cc]{position:absolute !important;inset:0 !important;width:100% !important;height:100% !important;opacity:0 !important;cursor:pointer !important;border:0 !important;padding:0 !important;background:transparent !important;-webkit-appearance:none !important;appearance:none !important;margin:0 !important;}
    .phone-row .langa-phone-cc-wrap select[data-phone-cc] option{font-size:14px !important;}
    input[name="phone"]{border-top-left-radius:0 !important;border-bottom-left-radius:0 !important;border-top-right-radius:calc(var(--lm-radius) - 2px) !important;border-bottom-right-radius:calc(var(--lm-radius) - 2px) !important;border-left:none !important;}
    .btn{margin-top:14px !important;width:100% !important;padding:12px 14px !important;border-radius:calc(var(--lm-radius) - 2px) !important;border:0 !important;background:var(--lm-header-bg) !important;color:var(--lm-header-text) !important;font-size:14px !important;font-weight:900 !important;cursor:pointer !important;text-decoration:none !important;text-transform:none !important;letter-spacing:normal !important;outline:none !important;box-shadow:none !important;line-height:1.4 !important;}
    .btn:hover{opacity:.92 !important;}
    .foot{margin:14px 0 0 !important;font-size:12px !important;color:var(--lm-text) !important;opacity:.55 !important;text-align:center !important;padding:0 !important;border:0 !important;background:transparent !important;}
    a{color:var(--lm-accent) !important;text-decoration:underline !important;}
    @media (max-width: 768px){ input,textarea,select{ font-size:16px !important; } .row{grid-template-columns:1fr !important;} body{padding:16px !important;} .panel{margin-top:10px !important;} h1{font-size:24px !important;margin:4px 0 0 !important;} .msg{margin-top:6px !important;} .logo{height:40px !important;} .brand-text{height:50px !important;font-size:18px !important;} form{margin-top:12px !important;padding:12px !important;} }
  </style>

<?php
  // Custom CSS (Maintenance only)
  $custom_css = is_string($ms['custom_css']) ? trim($ms['custom_css']) : '';
  if ($custom_css !== '') {
    $custom_css = str_replace(array('</style>', '</STYLE>'), '', $custom_css);
    if (strlen($custom_css) > 12000) $custom_css = substr($custom_css, 0, 12000);
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS sanitized, standalone maintenance page
    echo "\n<style id=\"langa-tools-maintenance-custom-css\">\n" . $custom_css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone maintenance page, CSS sanitized on save
  }
?>
</head>
<body>
<?php // Credits rendered before </body> via iframe ?>
  <div class="wrap">
    <div class="panel">
      <div class="top">
        <?php if ($logo_url): ?>
          <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo $site_name; ?>">
        <?php else: ?>
          <div class="brand-text"><?php echo esc_html($site_name); ?></div>
        <?php endif; ?>
        <h1><?php echo esc_html($_mt['title'] ?? 'Ci stiamo rifacendo il look'); ?></h1>
        <p class="msg"><?php echo esc_html($_mt['message'] ?? 'Stiamo lavorando per migliorare la tua esperienza web.'); ?></p>
      </div>

      <?php if ($sent): ?>
        <div class="note ok">
          <strong><?php echo esc_html($_mt['ok_title'] ?? 'Grazie per averci contattato.'); ?></strong><br>
          <?php echo esc_html($_mt['ok_body'] ?? 'We received your message and will get back to you shortly.'); ?>
          <?php if ($mail !== 1): ?>
            <br><small style="opacity:.8"><?php echo esc_html($_mt['ok_mail_warn'] ?? 'Nota: invio email non confermato.'); ?></small>
          <?php endif; ?>
        </div>
      <?php elseif ($fail): ?>
        <div class="note err">
          <?php echo esc_html($_mt['err_label'] ?? 'Errore invio:'); ?>
          <?php echo ($reason === 'required' ? esc_html($_mt['err_required'] ?? 'Compila tutti i campi obbligatori.') : esc_html($_mt['err_generic'] ?? 'Errore generico.')); ?>
        </div>
      <?php endif; ?>

      <?php if (!$sent): ?>
      <form id="langa-maint-form" method="post" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="hidden" name="action" value="langa_tools_client_maintenance_contact">
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="langa_maintenance_submit" value="1">

        <div class="row">
          <div><label><?php echo esc_html($_mt['label_name'] ?? 'Nome'); ?> *</label><input name="name" placeholder="<?php echo esc_attr($_mt['ph_name'] ?? 'Mario'); ?>" required></div>
          <div><label><?php echo esc_html($_mt['label_surname'] ?? 'Cognome'); ?> *</label><input name="surname" placeholder="<?php echo esc_attr($_mt['ph_surname'] ?? 'Rossi'); ?>" required></div>
        </div>

        <label><?php echo esc_html($_mt['label_company'] ?? 'Azienda'); ?></label>
        <input name="company" placeholder="<?php echo esc_attr($_mt['ph_company'] ?? 'Rossi Srl'); ?>">

        <label><?php echo esc_html($_mt['label_email'] ?? 'Email'); ?> *</label>
        <input name="email" type="email" placeholder="<?php echo esc_attr($_mt['ph_email'] ?? 'info@rossisrl.com'); ?>" required>

        <label><?php echo esc_html($_mt['label_phone'] ?? 'Phone'); ?></label>
        <div class="phone-row">
          <select name="phone_country" data-phone-cc="1">
            <option value="+39" data-country="IT"><?php echo esc_html($_mt['country_it'] ?? 'Italia'); ?></option>
            <option value="+41" data-country="CH"><?php echo esc_html($_mt['country_ch'] ?? 'Svizzera'); ?></option>
            <option value="+33" data-country="FR"><?php echo esc_html($_mt['country_fr'] ?? 'Francia'); ?></option>
            <option value="+49" data-country="DE"><?php echo esc_html($_mt['country_de'] ?? 'Germania'); ?></option>
            <option value="+44" data-country="GB"><?php echo esc_html($_mt['country_gb'] ?? 'Regno Unito'); ?></option>
            <option value="+1" data-country="US"><?php echo esc_html($_mt['country_us'] ?? 'Stati Uniti'); ?></option>
          </select>
          <input name="phone" placeholder="<?php echo esc_attr($_mt['ph_phone'] ?? '333 123 4567'); ?>">
        </div>

        <label><?php echo esc_html($_mt['label_message'] ?? 'Messaggio'); ?> *</label>
        <textarea name="message" placeholder="<?php echo esc_attr($_mt['ph_message'] ?? 'Scrivi qui il tuo messaggio...'); ?>" required></textarea>

        <button class="btn" type="submit"><?php echo esc_html($_mt['submit'] ?? 'Invia messaggio'); ?></button>
      </form>
      <?php endif; ?>

      <div class="foot">
        <?php echo $site_name; ?> © <?php echo esc_html($year); ?><br>
        Ci siamo affidati a <a href="https://langa.tv/">LANGA</a><br>
        Non sai chi è? Scopri di più su <a href="https://about.langa.tv/">About LANGA</a><br><br>
        <span style="opacity:0.5;">Maintenance powered by LANGA Tools</span><br><br>
      </div>
    </div>
  </div>
<?php langa_tools_client_maintenance_print_footer_assets(); ?>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone page ?>
<script>
if(window.innerWidth<=768){var f=document.getElementById('langa-maint-form');if(f)setTimeout(function(){f.scrollIntoView({behavior:'smooth',block:'start'})},400);}
</script>
<?php
  // Credits on maintenance: inject iframe + CSS + blob loader (same as wp_footer)
  if (function_exists('langa_credits_enabled') && langa_credits_enabled() && function_exists('langa_credits_mode')) {
    $cmode = langa_credits_mode();
    if ($cmode !== 'off') {
      $ccolor = ($cmode === 'local' && function_exists('langa_credits_primary_color')) ? langa_credits_primary_color() : '#999999';
      $chex = ltrim($ccolor, '#');
      if (strlen($chex) === 3) $chex = $chex[0].$chex[0].$chex[1].$chex[1].$chex[2].$chex[2];
      $cr = hexdec(substr($chex, 0, 2)); $cg = hexdec(substr($chex, 2, 2)); $cb = hexdec(substr($chex, 4, 2));
      $gray_filter = ($cmode === 'iframe') ? 'filter:grayscale(100%)!important;-webkit-filter:grayscale(100%)!important;' : '';
      // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStyle -- inline CSS
      echo '<style>
      /* Credits button — must override maintenance page aggressive button resets */
      html body button#langa-button{position:fixed!important;right:7px!important;bottom:0!important;left:auto!important;top:auto!important;display:block!important;float:none!important;color:'.esc_attr($ccolor).'!important;font:400 14px/25px "Open Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif!important;letter-spacing:-.2px!important;text-transform:capitalize!important;height:27px!important;width:auto!important;cursor:pointer!important;background:transparent!important;border:none!important;box-shadow:none!important;padding:0!important;margin:0!important;outline:none!important;z-index:2147483647!important;text-align:right!important;min-height:0!important;min-width:0!important;max-width:none!important;text-decoration:none!important;-webkit-appearance:none!important;appearance:none!important;border-radius:0!important;text-shadow:none!important;background-image:none!important;transform:none!important;overflow:visible!important;opacity:1!important;line-height:25px!important;}
      html body button#langa-button:hover,html body button#langa-button:focus,html body button#langa-button:active{outline:none!important;box-shadow:none!important;background:transparent!important;color:'.esc_attr($ccolor).'!important;border:none!important;text-decoration:none!important;opacity:1!important;}
      html body button#langa-button::before{display:none!important;content:none!important;}
      html body button#langa-button::after{content:""!important;position:absolute!important;right:-7px!important;bottom:0!important;top:auto!important;left:auto!important;width:200px!important;height:140px!important;pointer-events:none!important;z-index:-1!important;background:radial-gradient(ellipse at 100% 100%,rgba('.esc_attr($cr).','.esc_attr($cg).','.esc_attr($cb).',0.14) 0%,transparent 70%)!important;display:block!important;border:none!important;padding:0!important;margin:0!important;opacity:1!important;border-radius:0!important;}
      html body div#langa-bottom-border{position:fixed!important;bottom:0!important;left:0!important;height:1px!important;width:100%!important;background:'.esc_attr($ccolor).'!important;display:block!important;border:none!important;padding:0!important;margin:0!important;z-index:2147483646!important;}
      html body iframe#langa-credits-iframe{display:none!important;position:fixed!important;top:0!important;left:0!important;width:100%!important;height:100%!important;z-index:2147483640!important;border:none!important;'.$gray_filter.'}
      html body iframe#langa-credits-iframe.is-open{display:block!important;}
      @media(max-width:680px){html body button#langa-button::after{width:150px!important;height:100px!important;}}
      </style>';
      if (function_exists('langa_credits_build_srcdoc')) {
        $mlogo = esc_url(function_exists('langa_credits_logo_url') ? langa_credits_logo_url() : '');
        $mnonce = wp_create_nonce('langa_credits_submit');
        $majax = admin_url('admin-ajax.php');
        $mslogan = function_exists('langa_credits_slogan') ? langa_credits_slogan() : '';
        $mservices = function_exists('langa_credits_services') ? langa_credits_services() : array();
        $mfooter = function_exists('langa_credits_footer_links') ? langa_credits_footer_links() : array();
        $mdevweb = function_exists('langa_credits_developer_website') ? langa_credits_developer_website() : '';
        $mhtml = langa_credits_build_srcdoc($ccolor, $cr, $cg, $cb, $mlogo, $mnonce, $majax, $mslogan, $mservices, $mfooter, $mdevweb);
        echo '<iframe id="langa-credits-iframe" title="Credits" allow="" allowtransparency="true" tabindex="-1"></iframe>';
        echo '<script type="text/template" id="langa-credits-b64">' . base64_encode($mhtml) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.EnqueuedResources.NonEnqueuedScript -- base64 encoded HTML template for iframe blob
        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone maintenance page, blob injection
        echo '<script>(function(){var t=document.getElementById("langa-credits-b64");if(!t)return;var h=atob(t.textContent);t.parentNode.removeChild(t);var b=new Blob([h],{type:"text/html"});window._lcBlobUrl=URL.createObjectURL(b);})();</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone maintenance page
        // Inline credits JS (button + click) since wp_enqueue_scripts doesn't run on maintenance
        $credits_js_path = LANGA_TOOLS_CLIENT_PATH . 'assets/langa-credits.js';
        if (is_readable($credits_js_path)) {
          // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- standalone maintenance page
          echo '<script>' . file_get_contents($credits_js_path) . '</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript,WordPress.Security.EscapeOutput.OutputNotEscaped -- standalone maintenance page, no WP enqueue available
        }
      }
    }
  }
?>
</body>
</html>
  <?php
  return ob_get_clean();
}

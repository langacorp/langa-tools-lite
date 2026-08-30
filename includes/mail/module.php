<?php
if (!defined('ABSPATH')) exit;

/**
 * Mail (Server) - Global sending configuration for LANGA Tools Client.
 * - Optional SMTP via phpmailer_init
 * - Optional From/FromName override
 * - Helper to render consistent HTML emails (favicon + safe markup)
 *
 * Option: langa_tools_mail_settings
 */

if (!defined('LANGA_TOOLS_CLIENT_MAIL_OPTION')) define('LANGA_TOOLS_CLIENT_MAIL_OPTION', 'langa_tools_mail_settings');

/**
 * Defaults (kept minimal; do not force behavior unless enabled)
 */
function langa_tools_client_mail_defaults() {
  return array(
    'enabled' => 0,

    // From overrides (optional)
    'from_email' => '',
    'from_name'  => '',

    // Optional headers
    'reply_to'   => '',
    'return_path' => '',
    'force_from' => 0,

    // Recipients (centralized)
    'recipients' => array(
      'to' => '',
      'cc' => '',
      'bcc' => '',
    ),

    // SMTP (optional)
    'smtp' => array(
      'enabled'  => 0,
      'host'     => '',
      'port'     => 587,
      'secure'   => 'tls', // none|ssl|tls
      'auth'     => 1,
      'username' => '',
      'password' => '',
      'allow_self_signed' => 0,
    ),

    // Email templates (Forms / BC / Maintenance)
    'templates' => array(
      'header_tagline' => 'Invio email',
      'footer_html'    => '',
      'forms' => array(
        'subject' => '',
        'title'   => '',
        'intro_html' => '',
      ),
      'bc' => array(
        'subject' => '',
        'title'   => '',
      ),
      'maintenance' => array(
        'subject' => '',
        'title'   => '',
      ),
    ),
  );
}


function langa_tools_client_mail_get_settings() {
  $opt = get_option(LANGA_TOOLS_CLIENT_MAIL_OPTION, array());
  if (!is_array($opt)) $opt = array();
  $def = langa_tools_client_mail_defaults();
  $opt = array_merge($def, $opt);

  if (!isset($opt['smtp']) || !is_array($opt['smtp'])) $opt['smtp'] = array();
  $opt['smtp'] = array_merge($def['smtp'], $opt['smtp']);

  // Recipients
  if (!isset($opt['recipients']) || !is_array($opt['recipients'])) $opt['recipients'] = array();
  $opt['recipients'] = array_merge($def['recipients'], $opt['recipients']);

  // Templates
  if (!isset($opt["templates"]) || !is_array($opt["templates"])) $opt["templates"] = array();
  $opt["templates"] = array_merge($def["templates"], $opt["templates"]);
  foreach (array("forms","bc","maintenance") as $g) {
    if (!isset($opt["templates"][$g]) || !is_array($opt["templates"][$g])) $opt["templates"][$g] = array();
    $opt["templates"][$g] = array_merge($def["templates"][$g], $opt["templates"][$g]);
  }

  // Normalize
  $opt['enabled'] = !empty($opt['enabled']) ? 1 : 0;
  $opt['from_email'] = sanitize_email((string)($opt['from_email'] ?? ''));
  $opt['from_name']  = sanitize_text_field((string)($opt['from_name'] ?? ''));
  $opt['reply_to']   = sanitize_email((string)($opt['reply_to'] ?? ''));
  $opt['return_path'] = sanitize_email((string)($opt['return_path'] ?? ''));
  $opt['force_from'] = !empty($opt['force_from']) ? 1 : 0;

  // Recipients: store as comma-separated list (validated)
  foreach (array('to','cc','bcc') as $rk) {
    $raw = isset($opt['recipients'][$rk]) ? $opt['recipients'][$rk] : '';
    if (is_array($raw)) $raw = implode(',', $raw);
    $raw = (string)$raw;
    $raw = str_replace(array(";", "
", "
", "	"), ',', $raw);
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    $ok = array();
    foreach ($parts as $em) {
      $em = sanitize_email($em);
      if ($em && is_email($em)) $ok[] = $em;
    }
    $ok = array_values(array_unique($ok));
    $opt['recipients'][$rk] = implode(', ', $ok);
  }

  $opt['smtp']['enabled']  = !empty($opt['smtp']['enabled']) ? 1 : 0;
  $opt['smtp']['host']     = sanitize_text_field((string)($opt['smtp']['host'] ?? ''));
  $opt['smtp']['port']     = (int)($opt['smtp']['port'] ?? 587);
  if ($opt['smtp']['port'] < 1) $opt['smtp']['port'] = 587;
  if ($opt['smtp']['port'] > 65535) $opt['smtp']['port'] = 587;

  $secure = sanitize_key((string)($opt['smtp']['secure'] ?? 'tls'));
  if (!in_array($secure, array('none','ssl','tls'), true)) $secure = 'tls';
  $opt['smtp']['secure'] = $secure;

  $opt['smtp']['auth']     = !empty($opt['smtp']['auth']) ? 1 : 0;
  $opt['smtp']['username'] = sanitize_text_field((string)($opt['smtp']['username'] ?? ''));
  // Password: do not sanitize with text_field to preserve special chars.
  $opt['smtp']['password'] = (string)($opt['smtp']['password'] ?? '');
  $opt['smtp']['allow_self_signed'] = !empty($opt['smtp']['allow_self_signed']) ? 1 : 0;

  return $opt;
}

/**
 * Apply SMTP settings (only when Mail is enabled + SMTP enabled).
 */
add_action('phpmailer_init', function($phpmailer){
  // License gate: SMTP config is a PRO feature
  if (function_exists('langa_tools_client_license_is_valid') && !langa_tools_client_license_is_valid()) return;
  $s = langa_tools_client_mail_get_settings();
  if (empty($s['enabled']) || empty($s['smtp']['enabled'])) return;

  $host = (string)($s['smtp']['host'] ?? '');
  if ($host === '') return;

  try {
    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = (int)($s['smtp']['port'] ?? 587);

    $secure = (string)($s['smtp']['secure'] ?? 'tls');
    if ($secure === 'none') {
      $phpmailer->SMTPSecure = '';
    } else {
      $phpmailer->SMTPSecure = $secure;
    }

    $phpmailer->SMTPAuth = !empty($s['smtp']['auth']);
    if (!empty($s['smtp']['auth'])) {
      $phpmailer->Username = (string)($s['smtp']['username'] ?? '');
      $phpmailer->Password = (string)($s['smtp']['password'] ?? '');
    }

    // Avoid unexpected TLS upgrade on some hosts when secure=none
    if ($secure === 'none') {
      $phpmailer->SMTPAutoTLS = false;
    }

    // Envelope sender (MAIL FROM / Return-Path)
    // CRITICAL: Sender MUST match from_email domain, otherwise SPF fails
    // and major providers (Gmail, Outlook, Yahoo) silently drop the email.
    $from_email_val = !empty($s['from_email']) ? (string)$s['from_email'] : '';
    $from_domain = $from_email_val ? strtolower(preg_replace('/^.+@/', '', $from_email_val)) : '';

    if (!empty($s['return_path'])) {
      $rp = (string)$s['return_path'];
      $rp_domain = strtolower(preg_replace('/^.+@/', '', $rp));
      // Only use return_path if its domain matches from_email domain
      // Otherwise Gmail/Outlook will SPF-fail and silently drop the email
      if ($from_domain !== '' && strcasecmp($rp_domain, $from_domain) !== 0) {
        // Mismatch! Use from_email as Sender to avoid SPF fail
        $phpmailer->Sender = $from_email_val;
      } else {
        $phpmailer->Sender = $rp;
      }
    } elseif ($from_email_val !== '') {
      // No return_path set: align envelope with From header
      $phpmailer->Sender = $from_email_val;
    }

    if (!empty($s['force_from']) && !empty($s['from_email'])) {
      $phpmailer->setFrom((string)$s['from_email'], (string)($s['from_name'] ?? ''), false);
    }

    if (!empty($s['smtp']['allow_self_signed'])) {
      $phpmailer->SMTPOptions = array(
        'ssl' => array(
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed' => true,
        ),
      );
    }
  } catch (Throwable $e) {
    // Fail-safe: do nothing. wp_mail will fallback to default behavior.
  }
}, 10);

/**
 * From overrides (only when Mail enabled and a value is provided)
 */
add_filter('wp_mail_from', function($from){
  if (function_exists('langa_tools_client_license_is_valid') && !langa_tools_client_license_is_valid()) return $from;
  $s = langa_tools_client_mail_get_settings();
  if (empty($s['enabled'])) return $from;
  $fe = (string)($s['from_email'] ?? '');
  return $fe !== '' ? $fe : $from;
}, 10);

add_filter('wp_mail_from_name', function($name){
  if (function_exists('langa_tools_client_license_is_valid') && !langa_tools_client_license_is_valid()) return $name;
  $s = langa_tools_client_mail_get_settings();
  if (empty($s['enabled'])) return $name;
  $fn = (string)($s['from_name'] ?? '');
  return $fn !== '' ? $fn : $name;
}, 10);



/**
 * Recipients helpers (centralized)
 */
function langa_tools_client_mail_parse_list($list) {
  if (is_array($list)) $list = implode(',', $list);
  $list = (string)$list;
  $list = str_replace(array(';',"
","
","	"), ',', $list);
  $parts = array_filter(array_map('trim', explode(',', $list)));
  $out = array();
  foreach ($parts as $em) {
    $em = sanitize_email($em);
    if ($em && is_email($em)) $out[] = $em;
  }
  return array_values(array_unique($out));
}

function langa_tools_client_mail_get_recipients() {
  $s = langa_tools_client_mail_get_settings();
  $r = isset($s['recipients']) && is_array($s['recipients']) ? $s['recipients'] : array();
  return array(
    'to'  => langa_tools_client_mail_parse_list($r['to'] ?? ''),
    'cc'  => langa_tools_client_mail_parse_list($r['cc'] ?? ''),
    'bcc' => langa_tools_client_mail_parse_list($r['bcc'] ?? ''),
  );
}

function langa_tools_client_mail_get_primary_recipient() {
  $r = langa_tools_client_mail_get_recipients();
  if (!empty($r['to'])) return $r['to'][0];
  $admin = (string)get_option('admin_email');
  return is_email($admin) ? $admin : '';
}

// Capture last wp_mail error for debug on the admin settings page.
add_action('wp_mail_failed', function($wp_error){
  if (!($wp_error instanceof WP_Error)) return;
  $msg = $wp_error->get_error_message();
  $data = $wp_error->get_error_data();
  // PHPMailer exception is often in error data (may be an object)
  if (is_array($data)) {
    foreach ($data as $k => $v) {
      if (is_object($v) && method_exists($v, 'getMessage')) {
        $m2 = $v->getMessage();
        if (is_string($m2) && $m2 !== '') $msg .= ' — ' . $m2;
        break;
      }
    }
  }
  set_transient('langa_tools_client_mail_last_error', array(
    'ts' => time(),
    'msg' => (string)$msg,
    'data' => is_scalar($data) ? (string)$data : '',
  ), 10 * MINUTE_IN_SECONDS);
}, 10);

function langa_tools_client_mail_get_last_error() {
  $e = get_transient('langa_tools_client_mail_last_error');
  return is_array($e) ? $e : array();
}

require_once __DIR__ . '/template.php';

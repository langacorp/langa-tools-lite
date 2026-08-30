<?php
if (!defined('ABSPATH')) exit;

/**
 * HTML email template helpers.
 */

function langa_tools_client_mail_logo_url() {
  $icon = function_exists('get_site_icon_url') ? (string)get_site_icon_url(64) : '';
  if ($icon !== '') return $icon;
  // Fallback icon (requested). If the site has a favicon, we prefer it.
  return 'https://tools.langa.tv/wp-content/uploads/2026/02/langa_tools-lite.png';
}

function langa_tools_client_mail_apply_vars($text, $vars) {
  if (!is_string($text) || $text === '') return '';
  if (!is_array($vars) || empty($vars)) return $text;
  $repl = array();
  foreach ($vars as $k => $v) {
    $k = preg_replace('/[^a-z0-9_\-]/i', '', (string)$k);
    if ($k === '') continue;
    $repl['{' . $k . '}'] = is_scalar($v) ? (string)$v : '';
  }
  return strtr($text, $repl);
}

function langa_tools_client_mail_header_tagline() {
  if (function_exists('langa_tools_client_mail_get_settings')) {
    $s = langa_tools_client_mail_get_settings();
    if (is_array($s) && !empty($s['templates']) && is_array($s['templates'])) {
      $t = (string)($s['templates']['header_tagline'] ?? '');
      $t = sanitize_text_field($t);
      if ($t !== '') return $t;
    }
  }
  return 'Email notification';
}

function langa_tools_client_mail_global_footer_html($vars = array()) {
  $footer = '';
  if (function_exists('langa_tools_client_mail_get_settings')) {
    $s = langa_tools_client_mail_get_settings();
    if (is_array($s) && !empty($s['templates']) && is_array($s['templates'])) {
      $footer = (string)($s['templates']['footer_html'] ?? '');
    }
  }
  $footer = langa_tools_client_mail_apply_vars($footer, $vars);
  if ($footer === '') return '';
  return function_exists('wp_kses_post') ? wp_kses_post($footer) : $footer;
}

function langa_tools_client_mail_tpl_text($group, $key, $default, $vars = array()) {
  $tpl = '';
  if (function_exists('langa_tools_client_mail_get_settings')) {
    $s = langa_tools_client_mail_get_settings();
    if (is_array($s) && !empty($s['templates']) && is_array($s['templates'])) {
      $g = isset($s['templates'][$group]) && is_array($s['templates'][$group]) ? $s['templates'][$group] : array();
      $tpl = (string)($g[$key] ?? '');
    }
  }
  $txt = $tpl !== '' ? $tpl : (string)$default;
  $txt = langa_tools_client_mail_apply_vars($txt, $vars);
  return sanitize_text_field($txt);
}

function langa_tools_client_mail_tpl_html($group, $key, $default, $vars = array()) {
  $tpl = '';
  if (function_exists('langa_tools_client_mail_get_settings')) {
    $s = langa_tools_client_mail_get_settings();
    if (is_array($s) && !empty($s['templates']) && is_array($s['templates'])) {
      $g = isset($s['templates'][$group]) && is_array($s['templates'][$group]) ? $s['templates'][$group] : array();
      $tpl = (string)($g[$key] ?? '');
    }
  }
  $html = $tpl !== '' ? $tpl : (string)$default;
  $html = langa_tools_client_mail_apply_vars($html, $vars);
  return function_exists('wp_kses_post') ? wp_kses_post($html) : $html;
}

function langa_tools_client_mail_template($title, $content_html, $footer_html = '', $vars = array()) {
  $site = esc_html(get_bloginfo('name'));
  $logo = langa_tools_client_mail_logo_url();
  $tagline = esc_html(langa_tools_client_mail_header_tagline());

  $title_esc = esc_html((string)$title);

  $header_left = '';
  if ($logo !== '') {
    $header_left = '<img src="'.esc_url($logo).'" alt="'.$site.'" style="width:32px;height:32px;border-radius:6px;display:block;margin-right:7px" />';
  } else {
    $name = (string) get_bloginfo('name');
    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
    if ($initial === '') $initial = 'L';
    $header_left = '<div style="width:32px;height:32px;border-radius:6px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">'.esc_html($initial).'</div>';
  }

  if ($footer_html === '') {
    $footer_html = langa_tools_client_mail_global_footer_html($vars);
  }

  // Optional accent color (top border on card)
  $accent = isset($vars['accent_color']) ? sanitize_hex_color((string)$vars['accent_color']) : '';
  $card_border_top = $accent ? 'border-top:4px solid ' . esc_attr($accent) . ';' : '';

  $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f3f4f6;padding:18px 12px;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial">';
  $html .= '<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;' . $card_border_top . '">';
  // Header — table layout for cross-client email compat (flex breaks in Outlook/Yahoo)
  $html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-bottom:1px solid #e5e7eb;background:#fff"><tr>';
  $html .= '<td style="padding:14px 16px;width:42px;vertical-align:middle">' . $header_left . '</td>';
  $html .= '<td style="padding:14px 4px;vertical-align:middle">';
  $html .= '<div style="font-weight:700;color:#111827;line-height:1.2">'.$site.'</div>';
  $html .= '<div style="font-size:12px;color:#6b7280;line-height:1.2;margin-top:2px">'.$tagline.'</div>';
  $html .= '</td>';
  // Module badge (if provided in vars)
  $module_badge = isset($vars['module_badge']) ? sanitize_text_field((string)$vars['module_badge']) : '';
  if ($module_badge !== '') {
    $badge_bg = isset($vars['module_badge_bg']) ? sanitize_hex_color((string)$vars['module_badge_bg']) : '#f3f4f6';
    $badge_color = isset($vars['module_badge_color']) ? sanitize_hex_color((string)$vars['module_badge_color']) : '#374151';
    if (!$badge_bg) $badge_bg = '#f3f4f6';
    if (!$badge_color) $badge_color = '#374151';
    $html .= '<td style="padding:14px 16px 14px 8px;vertical-align:middle;text-align:right"><span style="display:inline-block;padding:4px 10px;border-radius:20px;background:' . esc_attr($badge_bg) . ';color:' . esc_attr($badge_color) . ';font-size:11px;font-weight:700;white-space:nowrap">' . esc_html($module_badge) . '</span></td>';
  }
  $html .= '</tr></table>';
  $html .= '<div style="padding:18px 16px 6px">';
  $html .= '<h1 style="margin:0 0 12px;font-size:18px;color:#111827">'.$title_esc.'</h1>';
  $sender_name = isset($vars['sender_name']) ? sanitize_text_field((string)$vars['sender_name']) : '';
  $sender_email = isset($vars['sender_email']) ? sanitize_email((string)$vars['sender_email']) : '';
  $recipient_email = isset($vars['recipient_email']) ? sanitize_email((string)$vars['recipient_email']) : '';
  $preset = isset($vars['preset']) ? sanitize_text_field((string)$vars['preset']) : '';
  $page_url = isset($vars['page_url']) ? esc_url((string)$vars['page_url']) : '';
  $meta_bits = array();
  if ($sender_email !== '' || $sender_name !== '') {
    $meta_bits[] = '<strong>From:</strong> ' . esc_html(trim($sender_name . ' ' . ($sender_email !== '' ? '<' . $sender_email . '>' : '')));
  }
  if ($recipient_email !== '') {
    $meta_bits[] = '<strong>To:</strong> ' . esc_html($recipient_email);
  }
  if ($preset !== '') {
    $meta_bits[] = '<strong>Form:</strong> ' . esc_html($preset);
  }
  if ($page_url !== '') {
    $meta_bits[] = '<strong>Source:</strong> <a href="' . esc_url($page_url) . '">' . esc_html($page_url) . '</a>';
  }
  if (!empty($meta_bits)) {
    $html .= '<div style="margin:0 0 12px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;font-size:12px;line-height:1.45;color:#374151">' . implode('<br>', $meta_bits) . '</div>';
  }
  $html .= '<div style="font-size:14px;line-height:1.5;color:#111827">'.$content_html.'</div>';
  $html .= '</div>';

  // Powered by LANGA Tools footer
  $module_name = isset($vars['module_badge']) ? sanitize_text_field((string)$vars['module_badge']) : '';
  $langa_icon = 'https://tools.langa.tv/wp-content/uploads/2026/01/cropped-langa_tools-server.png';
  $langa_url = 'http://about.langa.tv/';
  // CRITICAL: is_confirmation MUST be explicitly true (set only in mail_send_confirmation).
  // Staff/destinatario emails MUST NOT set this flag.
  $is_confirmation = (isset($vars['is_confirmation']) && $vars['is_confirmation'] === true);

  $html .= '<div style="padding:16px 16px 14px;border-top:1px solid #e5e7eb;background:#fafafa">';

  // Custom footer (if set by user in settings)
  if ($footer_html !== '') {
    $html .= '<div style="margin:0 0 14px;font-size:12px;color:#6b7280">'.$footer_html.'</div>';
  }

  // Branded footer row
  $html .= '<div style="display:flex;align-items:center;gap:8px">';
  $html .= '<a href="'.esc_url($langa_url).'" target="_blank" rel="noopener" style="flex-shrink:0;text-decoration:none">';
  $html .= '<img src="'.esc_url($langa_icon).'" alt="LANGA Tools" style="width:20px;height:20px;border-radius:4px;display:block" />';
  $html .= '</a>';
  $html .= '<div style="font-size:11px;color:#9ca3af;line-height:1.3;margin-top:3px;margin-left:6px">';
  $html .= 'Powered by <a href="'.esc_url($langa_url).'" target="_blank" rel="noopener" style="color:#78716c;text-decoration:none;font-weight:600">LANGA Tools</a>';
  if ($module_name !== '') {
    $html .= ' <span style="color:#d6d3d1">&middot;</span> ' . esc_html($module_name);
  }
  $html .= '</div>';
  $html .= '</div>';

  // Ecosystem value message — shown ONLY in CONFIRMATION emails (to the person who submitted).
  // This banner tells the end user about the LANGA ecosystem that powered their contact.
  // It must NOT appear in staff/recipient notification emails.
  if ($is_confirmation) {
    $eco_url = 'https://about.langa.tv/ecosystem/';
    $support_url = 'https://about.langa.tv/support/';
    $html .= '<div style="margin:12px 0 0;padding:12px 16px;border-radius:8px;background:#f37f0d">';
    $html .= '<p style="margin:0 0 5px;font-size:12px;color:#ffffff;line-height:1.45">';
    $html .= 'Do you know why you receive these leads? Your site is part of the <a href="'.esc_url($eco_url).'" target="_blank" rel="noopener" style="color:#ffffff;font-weight:700;text-decoration:underline">LANGA Ecosystem</a> &mdash; a system that works for you: it generates leads, manages them and converts them, thanks to integrated tools and the LANGA team support.';
    $html .= '</p>';
    $html .= '<p style="margin:0;font-size:11px;color:rgba(255,255,255,.85);line-height:1.4">';
    $html .= 'Business Card, Forms, Maintenance, SEO &mdash; everything is connected. <a href="'.esc_url($eco_url).'" target="_blank" rel="noopener" style="color:#ffffff;text-decoration:underline">Learn more</a> &middot; <a href="'.esc_url($support_url).'" target="_blank" rel="noopener" style="color:#ffffff;text-decoration:underline">Support</a>';
    $html .= '</p>';
    $html .= '</div>';
  }

  $html .= '</div>'; // footer bar
  $html .= '</div>'; // main card

  // Site line (outside card)
  $html .= '<div style="max-width:680px;margin:10px auto 0;text-align:center;font-size:11px;color:#9ca3af">'.esc_html($site).' &middot; <a href="'.esc_url(home_url()).'" style="color:#9ca3af;text-decoration:none">'.esc_html(home_url()).'</a></div>';
  $html .= '</body></html>';
  return $html;
}

/**
 * Send confirmation email to the person who submitted a form.
 * Lightweight recap — different tone from the staff notification.
 *
 * @param array $args {
 *   'to'           => sender email,
 *   'sender_name'  => sender name,
 *   'module'       => 'forms'|'bc'|'maintenance',
 *   'module_label' => display label,
 *   'site'         => site name,
 *   'summary_html' => recap of what was sent,
 *   'accent_color' => optional hex accent,
 * }
 */
function langa_tools_client_mail_send_confirmation($args) {
  if (!is_array($args)) return false;
  $to = sanitize_email((string)($args['to'] ?? ''));
  if ($to === '' || !is_email($to)) return false;

  // i18n — allow forced language override (grey credits → always en)
  $force_lang = isset($args['lang']) ? sanitize_key($args['lang']) : '';
  if ($force_lang !== '' && function_exists('langa_tools_client_i18n')) {
    $_mc = langa_tools_client_i18n('mail_confirm', $force_lang);
  } else {
    $_mc = function_exists('langa_tools_client_i18n') ? langa_tools_client_i18n('mail_confirm') : array();
  }

  $name = sanitize_text_field((string)($args['sender_name'] ?? ''));
  $site = (string)($args['site'] ?? wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
  $module_label = sanitize_text_field((string)($args['module_label'] ?? 'Contact'));
  $summary = (string)($args['summary_html'] ?? '');

  $greeting_tpl = $name !== '' ? ($_mc['greeting'] ?? 'Hi {name},') : ($_mc['greeting_no'] ?? 'Hi,');
  $greeting = str_replace('{name}', '<strong>' . esc_html($name) . '</strong>', $greeting_tpl);

  $content = '<p>' . $greeting . '</p>';
  $content .= '<p>' . esc_html($_mc['body'] ?? 'We received your request. We will get back to you as soon as possible.') . '</p>';
  if ($summary !== '') {
    $content .= '<div style="margin:14px 0;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:13px">';
    $content .= '<p style="margin:0 0 8px;font-weight:700;color:#374151">' . esc_html($_mc['summary'] ?? 'Summary:') . '</p>';
    $content .= $summary;
    $content .= '</div>';
  }
  $content .= '<p style="font-size:13px;color:#6b7280">' . esc_html($_mc['auto_notice'] ?? 'This is an automatic confirmation. You do not need to reply to this email.') . '</p>';

  $vars = array(
    'site' => $site,
    'is_confirmation' => true,
    'module_badge' => $module_label,
    'module_badge_bg' => '#f0fdf4',
    'module_badge_color' => '#166534',
    // NOTE: Do NOT set sender_name/sender_email/recipient_email here.
    // In confirmation emails, those generate a redundant "Mittente/Destinatario" meta block
    // that looks broken (same person as sender AND recipient). Keeping the header clean.
  );
  if (!empty($args['accent_color'])) $vars['accent_color'] = (string)$args['accent_color'];

  $subject = str_replace('{site}', $site, $_mc['subject'] ?? 'Confirmation — {site}');
  $title = $_mc['title'] ?? 'We received your request';

  if (function_exists('langa_tools_client_mail_send')) {
    return (bool) langa_tools_client_mail_send(array(
      'to' => $to,
      'subject' => $subject,
      'title' => $title,
      'content_html' => $content,
      'vars' => $vars,
    ));
  }
  $html = langa_tools_client_mail_template($title, $content, '', $vars);
  return (bool) wp_mail($to, $subject, $html, array('Content-Type: text/html; charset=UTF-8'));
}

/**
 * Send HTML email with safe headers.
 *
 * $args = [
 *  'to' => string|array,
 *  'subject' => string,
 *  'title' => string,
 *  'content_html' => string,
 *  'reply_to' => string (optional),
 *  'footer_html' => string (optional),
 *  'attachments' => array|string (optional),
 *  'headers' => array|string (optional),
 *  'vars' => array (optional),
 * ]
 */
function langa_tools_client_mail_send($args) {
  if (!is_array($args)) return false;

  $to = $args['to'] ?? '';
  $subject = sanitize_text_field((string)($args['subject'] ?? ''));
  $title = (string)($args['title'] ?? $subject);
  $content_html = (string)($args['content_html'] ?? '');
  $footer_html = (string)($args['footer_html'] ?? '');
  $vars = isset($args['vars']) && is_array($args['vars']) ? $args['vars'] : array();

  $reply_to = sanitize_email((string)($args['reply_to'] ?? ''));
  if ($reply_to === '' && function_exists('langa_tools_client_mail_get_settings')) {
    $ms = langa_tools_client_mail_get_settings();
    if (is_array($ms) && !empty($ms['enabled']) && !empty($ms['reply_to'])) {
      $reply_to = sanitize_email((string)$ms['reply_to']);
    }
  }

  // Centralized recipients (Invio Server)
  // - If "to" is empty: use the centralized TO list.
  // - Always append centralized CC/BCC when not explicitly provided.
  if (function_exists('langa_tools_client_mail_get_recipients')) {
    $r = langa_tools_client_mail_get_recipients();
    if (($to === '' || (is_array($to) && empty($to))) && !empty($r['to'])) {
      $to = $r['to'];
    }
    if (!isset($args['cc']) && !empty($r['cc'])) $args['cc'] = $r['cc'];
    if (!isset($args['bcc']) && !empty($r['bcc'])) $args['bcc'] = $r['bcc'];
  }

  $attachments = $args['attachments'] ?? array();
  $headers_extra = $args['headers'] ?? array();

  if ($to === '' || $subject === '' || $content_html === '') return false;

  // Vars (dynamic + non-editable)
  if (!isset($vars['recipient_email'])) {
    if (is_array($to) && !empty($to)) $vars['recipient_email'] = (string)$to[0];
    elseif (is_string($to)) $vars['recipient_email'] = (string)$to;
  }
  if (!isset($vars['sender_email']) && $reply_to !== '') $vars['sender_email'] = $reply_to;

  $html = langa_tools_client_mail_template($title, $content_html, $footer_html, $vars);

  $headers = array('Content-Type: text/html; charset=UTF-8');
  if ($reply_to !== '') {
    $headers[] = 'Reply-To: ' . $reply_to;
  }

  // CC / BCC
  $cc = $args['cc'] ?? array();
  $bcc = $args['bcc'] ?? array();
  if (function_exists('langa_tools_client_mail_parse_list')) {
    $cc = langa_tools_client_mail_parse_list($cc);
    $bcc = langa_tools_client_mail_parse_list($bcc);
  }
  if (!empty($cc)) $headers[] = 'Cc: ' . implode(', ', $cc);
  if (!empty($bcc)) $headers[] = 'Bcc: ' . implode(', ', $bcc);

  if (!empty($headers_extra)) {
    if (is_string($headers_extra)) {
      $headers[] = $headers_extra;
    } elseif (is_array($headers_extra)) {
      foreach ($headers_extra as $h) {
        if (is_string($h) && $h !== '') $headers[] = $h;
      }
    }
  }

  $result = (bool) wp_mail($to, $subject, $html, $headers, $attachments);

  // Debug log
  if (function_exists('langa_tools_client_debug_log')) {
    $log_to = is_array($to) ? implode(', ', $to) : (string)$to;
    $module = isset($vars['module_badge']) ? (string)$vars['module_badge'] : 'generic';
    if ($result) {
      langa_tools_client_debug_log('mail_send', 'OK → ' . $log_to, array('to' => $log_to, 'module' => $module));
    } else {
      $err = function_exists('langa_tools_client_mail_get_last_error') ? langa_tools_client_mail_get_last_error() : array();
      langa_tools_client_debug_log('mail_fail', 'FAIL → ' . $log_to, array('to' => $log_to, 'module' => $module, 'error' => !empty($err['msg']) ? $err['msg'] : 'wp_mail returned false'));
    }
  }

  return $result;
}

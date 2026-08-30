<?php
/**
 * Starter framework — credits frame server (Lite WP.org edition)
 * @version 1.3.0
 *
 * Serves the credits iframe HTML from /?langa-credits-frame.
 * Only 'local' mode (admin-configured branding). No server sync.
 */
if (!defined('ABSPATH')) exit;

add_action('muplugins_loaded', function() {
  if (!isset($_GET['langa-credits-frame'])) return;
  add_action('template_redirect', function() {
    if (!function_exists('langa_credits_mode') ||
        !function_exists('langa_credits_build_srcdoc')) {
      status_header(404); exit;
    }
    $mode = langa_credits_mode();
    if ($mode === 'off') { status_header(204); exit; }
    $color = function_exists('langa_credits_primary_color')
           ? langa_credits_primary_color() : '#999999';
    $hex = ltrim($color, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $nonce = wp_create_nonce('langa_credits_submit');
    $ajax  = admin_url('admin-ajax.php');
    $logo = esc_url(function_exists('langa_credits_logo_url') ? langa_credits_logo_url() : '');
    $slogan = function_exists('langa_credits_slogan') ? langa_credits_slogan() : '';
    $services = function_exists('langa_credits_services') ? langa_credits_services() : array();
    $footer = function_exists('langa_credits_footer_links') ? langa_credits_footer_links() : array();
    $devweb = function_exists('langa_credits_developer_website') ? langa_credits_developer_website() : '';
    $html = langa_credits_build_srcdoc($color, $r, $g, $b, $logo, $nonce, $ajax, $slogan, $services, $footer, $devweb);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: ALLOWALL');
    header('Cache-Control: no-store');
    $allowed = wp_kses_allowed_html('post');
    $allowed['html'] = array('lang'=>true);
    $allowed['head'] = array();
    $allowed['meta'] = array('charset'=>true,'name'=>true,'content'=>true);
    $allowed['title'] = array();
    $allowed['body'] = array('style'=>true);
    $allowed['style'] = array('id'=>true);
    $allowed['link'] = array('rel'=>true,'href'=>true);
    echo wp_kses($html, $allowed);
    exit;
  }, -1);
});

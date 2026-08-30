<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend Preloader (AdminUX → UI/UX → Preloader)
 * - Dark, minimal overlay
 * - Exclude pages (per-line match)
 * - First visit per session (sessionStorage)
 * - Uses site favicon if available, otherwise LANGA fallback icon
 */

function langa_tools_client_preloader_get_settings() {
  $s = function_exists('langa_tools_client_adminux_get_option_fast')
    ? langa_tools_client_adminux_get_option_fast('langa_tools_adminux_settings', array())
    : get_option('langa_tools_adminux_settings', array());
  if (!is_array($s)) $s = array();
  $p = isset($s['preloader']) && is_array($s['preloader']) ? $s['preloader'] : array();

  $out = array();
  $out['enabled'] = !empty($p['enabled']) ? 1 : 0;

  $bg = isset($p['bg_color']) ? (string)$p['bg_color'] : '#0b0b0c';
  $bg = sanitize_hex_color($bg);
  if ($bg === '') $bg = '#0b0b0c';
  $out['bg_color'] = $bg;

  $op_raw = isset($p['bg_opacity']) ? (string)$p['bg_opacity'] : '0.96';
  $op_raw = str_replace(',', '.', $op_raw);
  $op = (float)$op_raw;
  if ($op < 0) $op = 0;
  if ($op > 1) $op = 1;
  $out['bg_opacity'] = $op;

  $out['logo_url'] = isset($p['logo_url']) ? esc_url_raw((string)$p['logo_url']) : '';

  $w = isset($p['logo_width']) ? (int)$p['logo_width'] : 84;
  if ($w < 24) $w = 24;
  if ($w > 260) $w = 260;
  $out['logo_width'] = $w;

  $tms = isset($p['transition_ms']) ? (int)$p['transition_ms'] : 520;
  if ($tms < 0) $tms = 0;
  if ($tms > 60000) $tms = 60000;
  $out['transition_ms'] = $tms;

  $out['first_visit_session'] = !empty($p['first_visit_session']) ? 1 : 0;

  $ex = isset($p['exclude_pages']) ? (string)$p['exclude_pages'] : '';
  $ex = str_replace("\0", '', $ex);
  if (strlen($ex) > 4000) $ex = substr($ex, 0, 4000);
  $out['exclude_pages'] = trim($ex);

  return $out;
}

/**
 * Normalize current request path to be relative to home_url() path.
 * This makes "/" match the site home even on multisite subfolders.
 */
function langa_tools_client_preloader_get_relative_path() {
  $req_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
  $req_path = (string) parse_url($req_uri, PHP_URL_PATH);
  if ($req_path === '') $req_path = '/';

  $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
  if ($home_path === '') $home_path = '/';
  if ($home_path[0] !== '/') $home_path = '/' . $home_path;
  if (substr($home_path, -1) !== '/') $home_path .= '/';

  $p = '/' . ltrim($req_path, '/');
  // Strip base path if applicable
  if ($home_path !== '/' && stripos($p . '/', $home_path) === 0) {
    $p = '/' . ltrim(substr($p, strlen($home_path)), '/');
    if ($p === '/') return '/';
    if ($p === '') return '/';
  }
  return $p === '' ? '/' : $p;
}

function langa_tools_client_preloader_is_excluded($settings) {
  if (!is_array($settings) || empty($settings['exclude_pages'])) return false;

  $uri_full = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
  $uri_full = $uri_full !== '' ? $uri_full : '/';
  $uri_path_rel = langa_tools_client_preloader_get_relative_path();

  $lines = preg_split('/\r\n|\r|\n/', (string)$settings['exclude_pages']);
  foreach ($lines as $line) {
    $line = trim((string)$line);
    if ($line === '') continue;

    // Special: "/" excludes the site home.
    if ($line === '/') {
      if ($uri_path_rel === '/' || $uri_path_rel === '') return true;
      continue;
    }

    // Matching rules:
    // - Lines starting with "/" default to EXACT path match (no children).
    // - Use "*" wildcard if you want patterns (e.g. "/marketing-solutions/*").
    // - Lines not starting with "/" are treated as substring match on full REQUEST_URI.
    if ($line[0] === '/') {
      $needle = '/' . ltrim($line, '/');

      // Normalize (ignore trailing slash differences except for root)
      $norm = function($s) {
        $s = '/' . ltrim((string)$s, '/');
        if ($s !== '/' && substr($s, -1) === '/') $s = rtrim($s, '/');
        return $s;
      };
      $path_n = $norm($uri_path_rel);

      // Wildcard support
      if (strpos($needle, '*') !== false) {
        $pattern = preg_quote($needle, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        if (preg_match('/^' . $pattern . '$/i', $path_n)) return true;
        continue;
      }

      $needle_n = $norm($needle);
      if ($path_n === $needle_n) return true;
    } else {
      if (stripos($uri_full, $line) !== false) return true;
    }
  }
  return false;
}

function langa_tools_client_preloader_logo_url($settings) {
  if (is_array($settings) && !empty($settings['logo_url'])) return (string)$settings['logo_url'];

  // Prefer site icon (favicon)
  $site_icon = function_exists('get_site_icon_url') ? get_site_icon_url(512) : '';
  if (is_string($site_icon) && $site_icon !== '') return $site_icon;

  $icon_id = (int) get_option('site_icon');
  if ($icon_id > 0) {
    $u = wp_get_attachment_image_url($icon_id, 'full');
    if (is_string($u) && $u !== '') return $u;
  }

  // Fallback LANGA
  return LANGA_TOOLS_CLIENT_URL . 'assets/images/plugin-icon.svg';
}

function langa_tools_client_preloader_init() {
  if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) return;

  $settings = langa_tools_client_preloader_get_settings();
  if (empty($settings['enabled'])) return;
  if (langa_tools_client_preloader_is_excluded($settings)) return;

  // Head: CSS + session gate (prevents flashing on subsequent pages)
  add_action('wp_enqueue_scripts', function() use ($settings) {
    $tms = (int)$settings['transition_ms'];
    $bg = (string)$settings['bg_color'];
    $op = (float)$settings['bg_opacity'];

    // Early JS to detect session + lock scrolling (must run before render)
    $head_js = '';
    if (!empty($settings['first_visit_session'])) {
      $head_js .= '(function(){try{var k="langa_preloader_seen";if(sessionStorage.getItem(k)==="1"){document.documentElement.classList.add("langa-preloader-skip");}}catch(e){}})();';
    }
    $head_js .= '(function(){try{var d=document.documentElement;if(!d.classList.contains("langa-preloader-skip")){d.classList.add("langa-preloader-lock");}}catch(e){}})();';

    wp_register_script('langa-preloader-head', false, array(), '1.0', false);
    wp_enqueue_script('langa-preloader-head');
    wp_add_inline_script('langa-preloader-head', $head_js);

    $css = ':root{--langa-pl-bg:' . esc_attr($bg) . ';--langa-pl-op:' . esc_attr($op) . ';--langa-pl-t:' . esc_attr($tms) . 'ms;}
html.langa-preloader-skip .langa-preloader{display:none!important;}
html.langa-preloader-lock, html.langa-preloader-lock body{overflow:hidden!important;height:100%!important;overscroll-behavior:none!important;}
.langa-preloader{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;opacity:1;transition:opacity var(--langa-pl-t) ease; background:transparent;}
.langa-preloader::before{content:"";position:absolute;inset:0;background:var(--langa-pl-bg);opacity:var(--langa-pl-op);}
.langa-preloader.is-done{opacity:0;}
.langa-preloader .pl-inner{position:relative;display:flex;align-items:center;justify-content:center;}
.langa-preloader .pl-logo{display:block;max-width:260px;height:auto;filter:drop-shadow(0 12px 30px rgba(0,0,0,.35));}';

    wp_register_style('langa-preloader-css', false, array(), '1.0');
    wp_enqueue_style('langa-preloader-css');
    wp_add_inline_style('langa-preloader-css', $css);
  }, 1);

  // Body: render preloader (early)
  $render = function() use ($settings) {
    $logo = langa_tools_client_preloader_logo_url($settings);
    $w = (int)$settings['logo_width'];
    $style = $w > 0 ? ('style="width:'.esc_attr($w).'px;max-width:80vw;"') : '';
    echo '<div id="langa-preloader" class="langa-preloader" aria-hidden="true">';
    echo '<div class="pl-inner"><img class="pl-logo" src="'.esc_url($logo).'" alt="" '.$style.' /></div>';
    echo '</div>';
  };

  add_action('wp_body_open', $render, 0);
  add_action('wp_footer', function() use ($render) {
    if (did_action('wp_body_open')) return;
    $render();
  }, 0);

  // Footer: hide on load + cleanup
  add_action('wp_enqueue_scripts', function() use ($settings) {
    $first = !empty($settings['first_visit_session']) ? 1 : 0;
    $tms = (int)$settings['transition_ms'];
    $tms_js = max(0, min(60000, $tms));
    $js = '(function(){';
    $js .= 'var T=' . $tms_js . ';';
    $js .= 'function unlock(){try{document.documentElement.classList.remove("langa-preloader-lock");}catch(e){}}';
    $js .= 'function removeNode(){var el=document.getElementById("langa-preloader");if(el&&el.parentNode){el.parentNode.removeChild(el);}}';
    $js .= 'function done(){var el=document.getElementById("langa-preloader");if(!el){unlock();return;}el.classList.add("is-done");unlock();setTimeout(removeNode, T+60);}';
    if ($first) {
      $js .= 'try{sessionStorage.setItem("langa_preloader_seen","1");}catch(e){}';
    }
    $js .= 'var max=20000;var fired=false;function finish(){if(fired) return;fired=true;done();}';
    $js .= 'window.addEventListener("load",function(){setTimeout(finish,50);});';
    $js .= 'setTimeout(finish,max);';
    $js .= '})();';
    wp_register_script('langa-preloader-footer', false, array(), '1.0', true);
    wp_enqueue_script('langa-preloader-footer');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS from safe integer constants
    wp_add_inline_script('langa-preloader-footer', $js);
  }, 99);
}

add_action('init', 'langa_tools_client_preloader_init');

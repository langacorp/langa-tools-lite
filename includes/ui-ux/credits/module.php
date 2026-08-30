<?php
if (!defined('ABSPATH')) exit;

if (!defined('LANGA_TOOLS_OPTION_CREDITS')) define('LANGA_TOOLS_OPTION_CREDITS', 'langa_tools_credits');

/* ════════════════════════════════════════════════════════════════
 * CREDITS [v1.5.8.7] — IFRAME-ONLY architecture
 *
 * BOTH modes render inside an <iframe> for total CSS isolation.
 *   LICENSE VALID + credits ON → srcdoc iframe (colored, self-contained)
 *   LICENSE INVALID            → src iframe from langa.tv (grayscale)
 *
 * The main page only has: button, bottom-border, gradient, iframe.
 * NO form elements in the page DOM → NO theme/plugin CSS interference.
 * ════════════════════════════════════════════════════════════════ */

function langa_credits_mode() {
  // ─── EXPLICIT USER CHOICE — highest priority after ban ─────────
  $as_early = get_option('langa_tools_adminux_settings', array());
  if (is_array($as_early) && array_key_exists('credits_enabled', $as_early)) {
    if (empty($as_early['credits_enabled'])) {
      return 'off'; // User explicitly said OFF — no override possible
    }
  }

  // ─── DEV BYPASS ────────────────────────────────────────────────
  if (function_exists('langa_tools_client_dev_bypass_active') && langa_tools_client_dev_bypass_active()) {
    return 'local';
  }

  // ─── GLOBAL BAN CHECK ─────────────────────────────────
  // Ban = nuclear. ALL client features off, including colored credits.
  // Only server-controlled grey bar (credits flag) can survive ban.
  if ((int) get_option('langa_tools_banned', 0) === 1) {
    $sv = get_option('langa_tools_credits_visible');
    if ($sv !== false && (int) $sv === 1) {
      return 'iframe'; // Server says show grey → grey survives ban
    }
    return 'off'; // Banned + no server grey = nothing
  }

  // Server grey credits override (set by mu-plugin via guard/status API)
  // grey_credits=1 (ON)  → FORCE grey bar, overrides any client config
  // grey_credits=0 (OFF) → grey OFF, client decides about colored only
  // not set (false)       → legacy fallback logic
  $sv = get_option('langa_tools_credits_visible');
  if ($sv !== false) {
    if ((int) $sv === 1) {
      return 'iframe'; // Force grey credits bar
    }
    // Server explicitly said grey OFF — check if client has colored enabled.
    // Lite is free: colored credits work without license check.
    $cs = get_option('langa_tools_credits_settings', null);
    if (is_array($cs) && isset($cs['enabled'])) {
      return !empty($cs['enabled']) ? 'local' : 'off';
    }
    $as = get_option('langa_tools_adminux_settings', array());
    if (is_array($as) && isset($as['credits_enabled'])) {
      return !empty($as['credits_enabled']) ? 'local' : 'off';
    }
    return 'off'; // Server said no grey, client didn't enable colored
  }

  // ── Legacy fallback (no server sync yet) ──
  // Lite: colored credits never need license. Only grey is forced.
  $cs = get_option('langa_tools_credits_settings', null);
  if (is_array($cs) && isset($cs['enabled']) && !empty($cs['enabled'])) {
    return 'local';
  }
  $s = get_option('langa_tools_adminux_settings', array());
  if (is_array($s) && isset($s['credits_enabled']) && !empty($s['credits_enabled'])) {
    return 'local';
  }
  // No colored enabled → check if grey should show
  $lic_ok = function_exists('langa_tools_client_license_is_valid') && langa_tools_client_license_is_valid();
  if (!$lic_ok) {
    $last = function_exists('langa_tools_client_license_last') ? langa_tools_client_license_last() : array();
    if (is_array($last) && isset($last['grey_credits']) && (int) $last['grey_credits'] === 0) {
      return 'off';
    }
    return 'iframe'; // default grey for unlicensed with no colored
  }
  // Licensed but no colored → grey
  return 'iframe';
}

function langa_credits_enabled() {
  return langa_credits_mode() !== 'off';
}

function langa_credits_get() {
  $o = get_option(LANGA_TOOLS_OPTION_CREDITS, array());
  if (!is_array($o)) $o = array();
  $o['script_src'] = home_url('/langa-assets/langa-credits.js');
  return $o;
}

/* ─── Helpers (centralized: read from Settings > Data > Developer) ─── */

function langa_credits_primary_color() {
  // 1. Centralized: Data > Developer > primary_color
  if (function_exists('langa_tools_client_get_site_data')) {
    $c = (string)langa_tools_client_get_site_data('developer.primary_color', '');
    if ($c !== '' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)) return $c;
  }
  // 2. Legacy fallback: adminux setting
  $s = get_option('langa_tools_adminux_settings', array());
  if (is_array($s) && !empty($s['custom_login_color'])) {
    $c = (string)$s['custom_login_color'];
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)) return $c;
  }
  return '#f37f0d';
}

function langa_credits_recipient() {
  // Gray/iframe mode always goes to developer
  if (langa_credits_mode() === 'iframe') {
    // Use developer email from Data if set
    if (function_exists('langa_tools_client_get_site_data')) {
      $de = (string)langa_tools_client_get_site_data('developer.email', '');
      if ($de !== '' && is_email($de)) return $de;
    }
    return 'admin@langa.tv';
  }
  // 1. Centralized: Data > Developer > email
  if (function_exists('langa_tools_client_get_site_data')) {
    $de = (string)langa_tools_client_get_site_data('developer.email', '');
    if ($de !== '' && is_email($de)) return $de;
  }
  // 2. Legacy: adminux credits_recipient
  $s = get_option('langa_tools_adminux_settings', array());
  if (is_array($s) && !empty($s['credits_recipient']) && is_email((string)$s['credits_recipient'])) {
    return (string)$s['credits_recipient'];
  }
  // 3. Centralized mail recipients
  if (function_exists('langa_tools_client_mail_get_primary_recipient')) {
    $primary = langa_tools_client_mail_get_primary_recipient();
    if ($primary !== '') return $primary;
  }
  // 4. Fallback
  return get_option('admin_email');
}

function langa_credits_logo_url() {
  // 1. Centralized: Data > Developer > logo_url
  if (function_exists('langa_tools_client_get_site_data')) {
    $logo = (string)langa_tools_client_get_site_data('developer.logo_url', '');
    if ($logo !== '' && filter_var($logo, FILTER_VALIDATE_URL)) return $logo;
  }
  // 2. Legacy fallback: adminux setting
  $s = get_option('langa_tools_adminux_settings', array());
  if (is_array($s) && !empty($s['custom_login_logo'])) {
    $logo = (string)$s['custom_login_logo'];
    if ($logo !== '' && filter_var($logo, FILTER_VALIDATE_URL)) return $logo;
  }
  return 'https://about.langa.tv/wp-content/uploads/2024/03/LANGA-logo.webp';
}

function langa_credits_slogan() {
  if (function_exists('langa_tools_client_get_site_data')) {
    $s = (string)langa_tools_client_get_site_data('developer.slogan', '');
    if ($s !== '') return $s;
  }
  return 'Il tool per il web';
}

function langa_credits_services() {
  if (function_exists('langa_tools_client_get_site_data')) {
    $raw = (string)langa_tools_client_get_site_data('developer.credits_services', '');
    if ($raw !== '') {
      return array_filter(array_map('trim', explode("\n", $raw)));
    }
  }
  return array('Showcase website','Dynamic website development','eCommerce platform',
    'Custom web design','Social media management','SEO optimization',
    'Brand identity','Creative graphics','Photo shoot','Promo video production',
    'Emotional video','Strategic marketing','Other marketing operations');
}

function langa_credits_footer_links() {
  $defaults = array(
    'privacy_url' => 'https://about.langa.tv/legal/privacy-policy/',
    'terms_url'   => 'https://about.langa.tv/legal/terms/',
    'about_url'   => 'https://about.langa.tv/',
  );
  if (function_exists('langa_tools_client_get_site_data')) {
    $dev = langa_tools_client_get_site_data('developer', array());
    if (is_array($dev)) {
      foreach ($defaults as $k => $v) {
        $val = !empty($dev[$k]) ? (string)$dev[$k] : '';
        if ($val !== '' && filter_var($val, FILTER_VALIDATE_URL)) $defaults[$k] = $val;
      }
      // about_url fallback: use developer.website if about_url not explicitly set
      if (empty($dev['about_url']) || !filter_var((string)$dev['about_url'], FILTER_VALIDATE_URL)) {
        $ws = !empty($dev['website']) ? (string)$dev['website'] : '';
        if ($ws !== '' && filter_var($ws, FILTER_VALIDATE_URL)) $defaults['about_url'] = $ws;
      }
    }
  }
  return $defaults;
}

function langa_credits_developer_website() {
  if (function_exists('langa_tools_client_get_site_data')) {
    $ws = (string)langa_tools_client_get_site_data('developer.website', '');
    if ($ws !== '' && filter_var($ws, FILTER_VALIDATE_URL)) return $ws;
  }
  return 'https://about.langa.tv';
}

/* ─── Page cache flush (called on settings/license change) ─── */

function langa_credits_flush_page_caches() {
  // WP Super Cache
  if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
  // W3 Total Cache
  if (function_exists('w3tc_flush_all')) w3tc_flush_all();
  // WP Fastest Cache
  if (isset($GLOBALS['wp_fastest_cache']) && method_exists($GLOBALS['wp_fastest_cache'], 'deleteCache')) {
    $GLOBALS['wp_fastest_cache']->deleteCache(true);
  }
  // LiteSpeed Cache
  if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
    LiteSpeed_Cache_API::purge_all();
  }
  do_action('litespeed_purge_all');
  // WP Rocket
  if (function_exists('rocket_clean_domain')) rocket_clean_domain();
  // SG Optimizer
  if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache();
  // Autoptimize
  if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
    autoptimizeCache::clearall();
  }
  // Kinsta
  if (class_exists('Kinsta\\Cache') && method_exists('Kinsta\\Cache', 'purge_complete_caches')) {
    \Kinsta\Cache::purge_complete_caches();
  }
  // Generic object cache
  if (function_exists('wp_cache_flush')) wp_cache_flush();
}

// Flush page caches when adminux settings change (covers credits toggle)
add_action('update_option_langa_tools_adminux_settings', 'langa_credits_flush_page_caches', 99);
add_action('update_option_langa_tools_credits_settings', 'langa_credits_flush_page_caches', 99);
add_action('update_option_langa_tools_client_site_data', 'langa_credits_flush_page_caches', 99);

/* ─── Enqueue ─── */

add_action('wp_enqueue_scripts', function(){
  $mode = langa_credits_mode();
  if ($mode === 'off') return;
  $o = langa_credits_get();
  if (empty($o['script_src'])) return;
  wp_enqueue_script('langa-tools-credits', esc_url_raw($o['script_src']), array(), LANGA_TOOLS_CLIENT_VERSION . '.246', true);
}, 30);

/* ─── Root anchor ─── */

function langa_credits_root() {
  if (langa_credits_mode() === 'off') return;
  static $done=false;
  if ($done) return;
  $done=true;
  echo "\n<!-- LANGA CREDITS ACTIVE -->\n";
}
add_action('wp_body_open','langa_credits_root',1);
add_action('wp_footer','langa_credits_root',9999);


/* ════════════════════════════════════════════════════════
 *  wp_footer: MAIN PAGE — only button + border + iframe
 *
 *  Both modes use <iframe> → total CSS isolation.
 *  Gray mode:  src = langa.tv (external)
 *  Local mode: srcdoc = self-contained HTML document
 * ════════════════════════════════════════════════════════ */

add_action('wp_footer', function(){
  $mode = langa_credits_mode();
  if ($mode === 'off') return;

  $color = ($mode === 'local') ? langa_credits_primary_color() : '#999999';

  $hex = ltrim($color, '#');
  if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
  $r = hexdec(substr($hex, 0, 2));
  $g = hexdec(substr($hex, 2, 2));
  $b = hexdec(substr($hex, 4, 2));
  ?>

  <!-- LANGA Credits [v1.6.0.0] mode=<?php echo esc_attr($mode); ?> -->
  <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStyle -- wp_footer callback ?>
  <style>
  /*! LANGA Credits — main page: button + border + gradient + iframe only */
  html body button#langa-button{
    position:fixed!important;right:7px!important;bottom:0!important;
    display:block!important;float:none!important;
    color:<?php echo esc_attr($color); ?>!important;
    font:400 14px/25px 'Open Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif!important;
    letter-spacing:-.2px!important;text-transform:capitalize!important;
    height:27px!important;width:auto!important;cursor:pointer!important;
    background:transparent!important;border:none!important;box-shadow:none!important;
    padding:0!important;margin:0!important;outline:none!important;
    z-index:2147483647!important;text-align:right!important;
    min-height:0!important;min-width:0!important;max-width:none!important;
    text-decoration:none!important;-webkit-appearance:none!important;appearance:none!important;
    border-radius:0!important;text-shadow:none!important;background-image:none!important;
    transform:none!important;overflow:visible!important;
  }
  html body button#langa-button:hover,html body button#langa-button:focus,
  html body button#langa-button:active,html body button#langa-button:focus-visible{
    outline:none!important;box-shadow:none!important;background:transparent!important;
    color:<?php echo esc_attr($color); ?>!important;border:none!important;text-decoration:none!important;
  }
  html body button#langa-button::before{display:none!important;content:none!important;background-color:transparent!important;}
  .cmsmasters-theme-button:before,button:before{background-color:transparent!important;}
  .cmsmasters-theme-button:after,button:after{background-color:transparent!important;}
  html body button#langa-button::after{
    content:""!important;position:absolute!important;right:-7px!important;bottom:0!important;
    top:auto!important;left:auto!important;
    width:200px!important;height:140px!important;
    pointer-events:none!important;z-index:-1!important;
    background:radial-gradient(ellipse at 100% 100%,<?php echo "rgba({$r},{$g},{$b},0.14)"; ?> 0%,transparent 70%)!important;
    display:block!important;border:none!important;padding:0!important;margin:0!important;
    opacity:1!important;border-radius:0!important;transition:none!important;
  }
  html body button#langa-button:hover::after{
    background:radial-gradient(ellipse at 100% 100%,<?php echo "rgba({$r},{$g},{$b},0.21)"; ?> 0%,transparent 70%)!important;
  }
  @media(max-width:680px){html body button#langa-button::after{width:150px!important;height:100px!important;}}
  html body div#langa-bottom-border{
    position:fixed!important;bottom:0!important;left:0!important;
    height:1px!important;width:100%!important;
    background:<?php echo esc_attr($color); ?>!important;
    display:block!important;border:none!important;padding:0!important;margin:0!important;
  }
  html body iframe#langa-credits-iframe{
    display:none!important;position:fixed!important;top:0!important;left:0!important;
    width:100%!important;height:100%!important;z-index:2147483640!important;
    border:none!important;padding:0!important;margin:0!important;
    background:transparent!important;
    <?php if ($mode === 'iframe'): ?>filter:grayscale(100%)!important;-webkit-filter:grayscale(100%)!important;<?php endif; ?>
  }
  html body iframe#langa-credits-iframe.is-open{display:block!important;}
  </style>

  <?php
    $nonce = wp_create_nonce('langa_credits_submit');
    $ajax  = admin_url('admin-ajax.php');

    if ($mode === 'iframe') {
      // Grey mode: FIXED LANGA branding — ignores client config, translated
      $logo = 'https://about.langa.tv/wp-content/uploads/2024/03/LANGA-logo.webp';
      $gl = function_exists('langa_tools_client_detect_lang') ? langa_tools_client_detect_lang() : 'en';
      $grey_i18n = array(
        'it' => array('s'=>'Sito non attivo','sv'=>array('Sito web vetrina','Sviluppo sito web dinamico','Piattaforma eCommerce','Web design personalizzato','Gestione dei social media','Miglioramento del SEO','Brand identity','Grafica creativa','Servizio fotografico','Creazione video promo','Video emozionale','Marketing strategico','Altre operazioni marketing')),
        'en' => array('s'=>'Site not active','sv'=>array('Showcase website','Dynamic website development','eCommerce platform','Custom web design','Social media management','SEO optimization','Brand identity','Creative graphics','Photo shooting','Promo video production','Emotional video','Strategic marketing','Other marketing services')),
        'fr' => array('s'=>'Site non actif','sv'=>array('Site vitrine','Site web dynamique','Plateforme eCommerce','Web design sur mesure','Gestion des réseaux sociaux','Optimisation SEO','Identité de marque','Graphisme créatif','Service photographique','Production vidéo promo','Vidéo émotionnelle','Marketing stratégique','Autres services marketing')),
        'es' => array('s'=>'Sitio no activo','sv'=>array('Sitio web vitrina','Desarrollo web dinámico','Plataforma eCommerce','Diseño web personalizado','Gestión de redes sociales','Optimización SEO','Identidad de marca','Gráfica creativa','Servicio fotográfico','Producción de vídeo promo','Vídeo emocional','Marketing estratégico','Otros servicios de marketing')),
        'de' => array('s'=>'Website nicht aktiv','sv'=>array('Showcase-Website','Dynamische Webentwicklung','eCommerce-Plattform','Individuelles Webdesign','Social Media Management','SEO-Optimierung','Markenidentität','Kreative Grafik','Fotoshooting','Promo-Videoproduktion','Emotionales Video','Strategisches Marketing','Weitere Marketingdienste')),
        'ru' => array('s'=>'\u0418\u043d\u0441\u0442\u0440\u0443\u043c\u0435\u043d\u0442 \u0434\u043b\u044f \u0432\u0435\u0431\u0430','sv'=>array('\u0412\u0438\u0442\u0440\u0438\u043d\u0430','\u0414\u0438\u043d\u0430\u043c\u0438\u0447\u0435\u0441\u043a\u0438\u0439 \u0441\u0430\u0439\u0442','eCommerce','\u0412\u0435\u0431-\u0434\u0438\u0437\u0430\u0439\u043d','SMM','SEO','\u0411\u0440\u0435\u043d\u0434\u0438\u043d\u0433','\u0413\u0440\u0430\u0444\u0438\u043a\u0430','\u0424\u043e\u0442\u043e','\u041f\u0440\u043e\u043c\u043e-\u0432\u0438\u0434\u0435\u043e','\u042d\u043c\u043e\u0446\u0438\u043e\u043d\u0430\u043b\u044c\u043d\u043e\u0435 \u0432\u0438\u0434\u0435\u043e','\u0421\u0442\u0440\u0430\u0442\u0435\u0433\u0438\u0447\u0435\u0441\u043a\u0438\u0439 \u043c\u0430\u0440\u043a\u0435\u0442\u0438\u043d\u0433','\u0414\u0440\u0443\u0433\u0438\u0435 \u0443\u0441\u043b\u0443\u0433\u0438')),
        'ar' => array('s'=>'\u0623\u062f\u0627\u0629 \u0627\u0644\u0648\u064a\u0628','sv'=>array('\u0645\u0648\u0642\u0639 \u0639\u0631\u0636','\u062a\u0637\u0648\u064a\u0631 \u0645\u0648\u0627\u0642\u0639','\u0645\u0646\u0635\u0629 \u062a\u062c\u0627\u0631\u0629','\u062a\u0635\u0645\u064a\u0645 \u0648\u064a\u0628','\u0625\u062f\u0627\u0631\u0629 \u0627\u0644\u0633\u0648\u0634\u064a\u0627\u0644','SEO','\u0647\u0648\u064a\u0629 \u0627\u0644\u0639\u0644\u0627\u0645\u0629','\u062a\u0635\u0645\u064a\u0645 \u0625\u0628\u062f\u0627\u0639\u064a','\u062a\u0635\u0648\u064a\u0631','\u0625\u0646\u062a\u0627\u062c \u0641\u064a\u062f\u064a\u0648','\u0641\u064a\u062f\u064a\u0648 \u0639\u0627\u0637\u0641\u064a','\u062a\u0633\u0648\u064a\u0642','\u062e\u062f\u0645\u0627\u062a \u0623\u062e\u0631\u0649')),
        'pt' => array('s'=>'Site não ativo','sv'=>array('Site vitrine','Desenvolvimento web','Plataforma eCommerce','Web design personalizado','Gestão de redes sociais','Otimização SEO','Identidade de marca','Design gráfico','Fotografia','Produção de vídeo','Vídeo emocional','Marketing estratégico','Outros serviços')),
      );
      $gi = isset($grey_i18n[$gl]) ? $grey_i18n[$gl] : $grey_i18n['en'];
      $slogan = $gi['s'];
      $services = $gi['sv'];
      $footer = array(
        'privacy_url' => 'https://about.langa.tv/legal/privacy-policy/',
        'terms_url'   => 'https://about.langa.tv/legal/terms/',
        'about_url'   => 'https://about.langa.tv/',
      );
      $devweb = 'https://about.langa.tv';
    } else {
      // Colored mode: client's own config from Settings > Data
      $logo = esc_url(langa_credits_logo_url());
      $slogan = langa_credits_slogan();
      $services = langa_credits_services();
      $footer = langa_credits_footer_links();
      $devweb = langa_credits_developer_website();
    }
    $html = langa_credits_build_srcdoc($color, $r, $g, $b, $logo, $nonce, $ajax, $slogan, $services, $footer, $devweb);
  ?>
  <iframe id="langa-credits-iframe" title="Credits" allow="" allowtransparency="true" tabindex="-1"></iframe>
  <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.EnqueuedResources.NonEnqueuedScript -- base64 encoded iframe blob template ?>
  <script type="text/template" id="langa-credits-b64"><?php echo base64_encode($html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- base64 of self-contained HTML ?></script>
  <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- wp_footer callback, blob injection ?>
  <script>
  (function(){
    var t=document.getElementById('langa-credits-b64');
    if(!t)return;
    var h=atob(t.textContent);
    t.parentNode.removeChild(t);
    var b=new Blob([h],{type:'text/html'});
    window._lcBlobUrl=URL.createObjectURL(b);
  })();
  </script>
  <?php
}, 9998);



/* ════════════════════════════════════════════════════════
 * SRCDOC builder — gray reference structure, color-swapped
 *
 * Uses EXACT same HTML/CSS from langa-credits gray bar.
 * Both gray and colored modes use this (gray = grayscale filter).
 * Runs INSIDE iframe. jQuery + select2 from CDN.
 * Themes, plugins, page builders CANNOT touch it.
 * ════════════════════════════════════════════════════════ */

function langa_credits_build_srcdoc($color, $r, $g, $b, $logo, $nonce, $ajax, $slogan = '', $services = array(), $footer = array(), $dev_website = '') {
  if ($slogan === '') $slogan = 'The tool for the web';
  if (empty($services)) $services = langa_credits_services();
  if (empty($footer)) $footer = langa_credits_footer_links();
  $min_date = date('Y-m-d', strtotime('+3 days'));
  $max_date = date('Y-m-d', strtotime('+5 weeks'));
  $origin   = home_url('/');
  $C = sanitize_hex_color($color) ?: '#999999'; // sanitized shorthand
  $C_rgb = sscanf($C, '#%02x%02x%02x');
  $C_bg  = $C_rgb ? sprintf('rgba(%d,%d,%d,0.1)', $C_rgb[0], $C_rgb[1], $C_rgb[2]) : 'rgba(243,127,13,0.1)';

  ob_start();
?><!-- phpcs:disable WordPress.WP.EnqueuedResources -- standalone HTML document served as iframe srcdoc -->
<!DOCTYPE html>
<html lang="<?php echo substr(get_locale(), 0, 2); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- inside self-contained iframe srcdoc ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans">
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- inside self-contained iframe srcdoc, not main page ?>
<link rel="stylesheet" href="<?php echo esc_url(LANGA_TOOLS_CLIENT_URL . 'assets/vendor/select2-minimal.css'); ?>">
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- inside self-contained iframe srcdoc ?>
<script src="<?php echo esc_url(includes_url('js/jquery/jquery.min.js')); ?>"></script>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- inside self-contained iframe srcdoc ?>
<script src="<?php echo esc_url(LANGA_TOOLS_CLIENT_URL . 'assets/vendor/select2-minimal.js'); ?>"></script>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStyle -- wp_footer callback ?>
<style>
/* ═══ GRAY REFERENCE style.css — color replaced ═══ */

#langa-form-container {
    position:fixed;bottom:0;left:0;width:100%;z-index:999;
    background-color:#fff;border-top:1px solid <?php echo esc_attr($C); ?>;
}

body,
body #langa-form-container #form-fields input,
body #langa-form-container #form-fields input:hover,
body #langa-form-container #form-fields input:focus,
body #langa-form-container #form-fields button{
    font-size:16px;
}

div#langa-form-container{cursor:auto!important}
div#langa-form-container:hover{cursor:auto!important}

::placeholder{font-size:14px!important}
a{color:<?php echo esc_attr($C); ?>!important}
a:hover{color:<?php echo esc_attr($C); ?>!important;opacity:.8}

/* FONTS */
body input,body span#form-description,body span.step-error,
body span.step-header,body button,body *{
    font-family:'Open Sans',sans-serif!important;
}

body .next-button button{
    width:100%!important;height:100%!important;border:none!important;
    background:transparent;color:#fff!important;font-weight:bolder;
    padding:0!important;margin:0!important;letter-spacing:1px!important;
}

/* AUTOFILL */
input:-webkit-autofill,input:-webkit-autofill:hover,
input:-webkit-autofill:focus,input:-webkit-autofill:active{
    -webkit-transition:"color 9999s ease-out, background-color 9999s ease-out";
    -webkit-transition-delay:9999s;
}

::-webkit-scrollbar{display:none}

.cl-70{margin-top:4px}

body *:focus{outline:none!important}

body div#lf-success-state{
    font-size:16px!important;font-weight:500!important;
    color:<?php echo esc_attr($C); ?>!important;background-color:#fff!important;
    margin-top:0;margin-left:5px;display:inline-flex;align-items:center;padding:15px!important;
}

body #langa-form{
    height:50px;max-width:900px;margin:15px auto;padding:0 5px;
}

body button.previous-button{
    width:25px!important;height:25px!important;font-weight:bolder!important;
    border-style:none!important;padding:0!important;margin:10px 0!important;
    background-color:transparent;color:<?php echo esc_attr($C); ?>;
}

body div#form-fields{
    height:45px;border-radius:5px;border-width:1px;border-style:solid;
    border-color:<?php echo esc_attr($C); ?>;display:flex;box-sizing:border-box;
    background:<?php echo esc_attr($C); ?>;position:relative;
}

body div.form-step{
    position:relative;width:100%;background:#fff;border-radius:4px!important;
    vertical-align:middle;
}

body div.form-step > input[type=tel]{
    width:100%;height:100%;background-color:transparent!important;border:none;
    padding:10px 0px 10px 5px!important;box-sizing:border-box;font-size:14px;
}

body div.form-step > input[type=text]{
    width:100%;height:100%;background-color:transparent!important;border:none;
    padding-right:110px!important;box-sizing:border-box;
}
body div.form-step > input[type=email]{
    width:100%;height:100%;background-color:transparent!important;border:none;
    padding-right:110px!important;box-sizing:border-box;
}
body div.form-step > textarea{
    width:100%;height:100%;background-color:transparent!important;border:none;
    padding:10px 110px 10px 10px!important;font-size:14px;resize:none;box-sizing:border-box;
}

body div.form-step > label.step-title{
    top:-11px;left:20px;background-color:#fff!important;position:absolute;
    color:<?php echo esc_attr($C); ?>;font-size:13px;padding:0 5px;z-index:2!important;
    margin-top:2.5px!important;white-space:nowrap;
}

/* SELECT2 */
body .form-step > span.select2-container{width:100%!important;height:100%!important}
body .form-step .select2-container--default .select2-selection--multiple{border:none!important}
body span.select2-selection.select2-selection--multiple{margin-left:1px}
body .form-step .select2-container--default.select2-container--focus .select2-selection--multiple{border:none!important}
body .form-step > span > span.selection > span{}
body .form-step .select2-container--default,.form-step .select2-selection__choice{margin-top:0!important}
body .form-step .select2-selection--multiple{margin-top:0!important}
body .form-step span ul.select2-selection__rendered{
    overflow-x:scroll!important;overflow-y:hidden!important;
    width:100%!important;display:flex!important;margin:0;
}
body .form-step span ul.select2-selection__rendered li.select2-selection__choice{overflow:visible!important}
body .form-step span ul.select2-selection__rendered{overflow-x:scroll!important;overflow-y:hidden!important}
body .form-step .select2-selection__choice__remove{border-right:0!important;height:100%}
body .form-step span.select2-selection.select2-selection--multiple{
    height:100%!important;padding:9px 0!important;
}
body .wrong-input .form-step span.select2-selection.select2-selection--multiple{padding-top:9px!important}
body .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{
    background-color:<?php echo esc_attr($C); ?>!important;
}
body .form-step .select2-results__option--selectable{background-color:<?php echo esc_attr($C); ?>!important}
body .form-step .select2-hidden-accessible{overflow-x:scroll!important;overflow-y:hidden!important}

body span.select2-dropdown.select2-dropdown--above{
    border:1px solid <?php echo esc_attr($C); ?>;border-bottom:0;
    border-radius:4px 4px 0 0;z-index:99999999999999;
}
body button,body img#form-logo{cursor:pointer!important;width:auto;height:auto}

img#form-logo{
    height:auto!important;width:auto!important;max-height:14px!important;max-width:100px!important;
    background-color:<?php echo esc_attr($C); ?>!important;padding:5px!important;
}

body span.select2-selection.select2-selection--multiple{
    width:calc(100% - 100px)!important;background-color:rgba(0,0,0,0);position:relative;
}
body .select2-container--default .select2-selection--multiple .select2-selection__choice{max-width:inherit!important}
body li.select2-selection__choice:last-child{margin-right:15px!important}
body li.select2-selection__choice:first-child{margin-left:8px!important}

body .reduced-padding{padding:2px!important}

body span > span.selection > span > span.select2-search{top:12px;left:15px;position:absolute}
body button.select2-selection__choice__remove{display:inline-flex;align-items:center}
body button.select2-selection__choice__remove:hover{border-radius:4px 0 0 4px!important}
body textarea.select2-search__field{margin-top:-4px!important}
body .select2-container--default .select2-search--inline .select2-search__field{display:none}

body input[type=time]:focus{box-shadow:none!important}
body label.step-title{z-index:0!important}

body input[type=date]:focus,body input[type=email]:focus,
body input[type=number]:focus,body input[type=password]:focus,
body input[type=search]:focus,body input[type=tel]:focus,
body input[type=text]:focus,body input[type=url]:focus,
body select:focus,body textarea:focus{box-shadow:none!important}

body input[type=date],body input[type=email],body input[type=number],
body input[type=password],body input[type=search],body input[type=text],
body input[type=url],body textarea{padding-left:10px!important}

/* ERROR */
body #form-fields.wrong-input{
    box-shadow:0 0 5px 0 rgba(255,0,0,0.4);
    -webkit-box-shadow:0 0 5px 0 rgba(255,0,0,0.4);
    border-color:rgb(255,0,0);
}
body #form-fields label.step-title span.step-header{display:inline}
body #form-fields.wrong-input label.step-title span.step-header{display:none}
body #form-fields label.step-title span.step-error{display:none}
body #form-fields.wrong-input label.step-title span.step-error{color:rgba(255,0,0,1);display:inline}

body div.next-button{
    width:100px;height:100%;border-radius:0 4px 4px 0!important;
    background:<?php echo esc_attr($C); ?>;color:#fff;font-weight:bolder;border-style:none;
    z-index:99;position:absolute;right:0;
}
body #form-fields.wrong-input div.next-button{background-color:rgba(255,0,0,1)!important}
body #form-fields.wrong-input input[type="tel"]{box-shadow:none!important}
body #form-fields.wrong-input input[type="text"]{box-shadow:none!important;padding-top:10px;padding-bottom:10px}

body .select2-container--default .select2-results__option--highlighted[aria-selected]{
    background-color:<?php echo esc_attr($C); ?>!important;
}

/* OVERLAY — added for both modes */
#lcf-overlay{
    position:fixed;top:0;left:0;width:100%;height:100%;
    background:linear-gradient(180deg,rgba(0,0,0,0) 0%,rgba(0,0,0,0.8) 100%);
    cursor:default;z-index:998;
}
#langa-form-container{cursor:default}

/* LINKS below form */
p.lcf-links{
    color:<?php echo esc_attr($C); ?>;font-size:11px;position:absolute;
    bottom:-16px;right:2px;width:100%;text-align:right;z-index:9999;
    margin:0;padding:0;
}
p.lcf-links a{
    color:<?php echo esc_attr($C); ?>!important;text-decoration:none;font-size:11px;
}
p.lcf-links a:hover{opacity:.7}

/* APPOINTMENT desktop */
@media screen and (min-width:681px){
    body span.select2-dropdown.select2-dropdown--above{top:-22px!important}
    body button.select2-selection__choice__remove span{padding-top:2px!important}
    body .appointment input{
        display:inline-flex!important;width:48%!important;border:none!important;border-radius:4px!important;
        height:36px!important;padding:6px 10px!important;box-sizing:border-box!important;
        font-size:14px!important;color:#333!important;background:#fff!important;
    }
    body .appointment button{display:none!important}
}

body .appointment{
    display:flex;flex-direction:row;vertical-align:middle;line-height:40px;
    padding:2px;justify-content:space-around;
    width:calc(100% - 100px);height:100%;box-sizing:border-box;
}
body input#appointment_date,body input#appointment_time{flex-direction:row-reverse}

/* MOBILE */
@media screen and (max-width:680px){
    body .appointment input{
        display:inline!important;width:0!important;height:0!important;
        border:none!important;padding:0!important;margin:0!important;font-size:0!important;
    }
    body button#date-picker,body button#time-picker{
        margin-top:0;font-size:14px!important;color:#555!important;line-height:36px!important;
    }
    body span.select2-dropdown.select2-dropdown--above{top:-63px!important}
    body .wrong-input .appointment{margin-top:-2px}
    body .appointment button{
        width:50%;height:100%;color:#999;background:rgba(255,255,255,0);
        text-align:center;border:none!important;
    }
    body div#lf-success-state{margin-top:0!important}
    body .m5p{margin-left:5%}
    body .select2-container--default .select2-search--inline .select2-search__field{display:none}
    body span#form-description{display:inline-flex!important;justify-content:space-between!important;white-space:nowrap}
    body span > span.selection > span > span.select2-search{top:8px}
    body button.previous-button{margin-left:-6px!important}
    body .cl-30{width:100%!important;display:inline-flex!important;justify-content:space-between!important}
    body .cl-70{width:100%!important;margin-top:20px!important}
    body .form-step span.select2-selection.select2-selection--multiple{padding-top:10px}
    body div.form-step > label.step-title{top:-16px}
    body #langa-form{height:90px}
    body div#langa-form[style="display: none;"]{height:0!important}
    body div#langa-form{flex-wrap:wrap!important;margin-right:20px}
}

@media screen and (max-width:490px){
    body .select2-container--default .select2-search--inline .select2-search__field{width:30px!important;display:none}
    body button.previous-button{margin-left:-6px!important}
    body input#appointment_date{background-color:transparent!important}
    body input#appointment_time{color:#555}
    body textarea.select2-search__field::placeholder{color:transparent}
}

@media screen and (max-width:390px){
    body span#form-description{font-size:13px;margin-top:2px}
    body div.form-step > label.step-title{left:8px!important;padding:2px 5px!important}
}

@media screen and (max-width:340px){
    body .appointment button{font-size:13px!important}
}
</style>
</head>
<body style="cursor:default;margin:0;padding:0;background:transparent;overflow:hidden;height:100%;">
<div id="lcf-overlay">
    <div id="langa-form-container" onclick="event.stopPropagation();">
        <div id="langa-form" style="display:flex;">
            <div class="cl-30" style="width:30%;display:grid;">
                <?php if ($dev_website !== ''): ?>
                <a class="m5p" style="height:25px;width:120px;" target="_blank" href="<?php echo esc_url($dev_website); ?>">
                    <img id="form-logo" src="<?php echo esc_url($logo); ?>">
                </a>
                <?php else: ?>
                <div class="m5p" style="height:25px;width:120px;">
                    <img id="form-logo" src="<?php echo esc_url($logo); ?>">
                </div>
                <?php endif; ?>
                <span id="form-description"><?php echo esc_html($slogan); ?></span>
            </div>
            <div class="cl-70" style="width:70%;display:inline-flex;">
                <div style="width:5%;">
                    <button class="previous-button" onclick="lf_previous_step();" style="background-color:transparent;color:<?php echo esc_attr($C); ?>;display:none;">&#8592;</button>
                </div>
                <div id="form-fields" style="width:95%;">

                    <div class="form-step reduced-padding" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_name">Contact the developer</span>
                            <span class="step-error" data-i18n="err_invalid">Invalid value.</span>
                        </label>
                        <input virtualkeyboardpolicy="auto" type="text" name="lc_fn" data-field="fullname" minlength="3" maxlength="50" data-i18n-ph="ph_name" placeholder="What is your name?" autocomplete="off">
                    </div>

                    <div class="form-step reduced-padding" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_company">Company</span>
                            <span class="step-error" data-i18n="err_invalid">Invalid value.</span>
                        </label>
                        <input virtualkeyboardpolicy="auto" type="text" name="lc_co" data-field="company" minlength="2" maxlength="50" data-i18n-ph="ph_company" placeholder="What is your company?" autocomplete="off">
                    </div>

                    <div class="form-step reduced-padding" style="display:none;">
                        <label class="step-title">
                            <span class="step-header">E-mail</span>
                            <span class="step-error" data-i18n="err_invalid">Invalid value.</span>
                        </label>
                        <input virtualkeyboardpolicy="auto" type="email" name="lc_em" data-field="email" minlength="6" maxlength="50" data-i18n-ph="ph_email" placeholder="What is your email?" autocomplete="off">
                    </div>

                    <div class="form-step" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_phone">Phone</span>
                            <span class="step-error" data-i18n="err_invalid">Invalid value.</span>
                        </label>
                        <input virtualkeyboardpolicy="auto" name="lc_ph" data-field="phone" type="tel" data-i18n-ph="ph_phone" placeholder="Where can we reach you?" autocomplete="off">
                    </div>

                    <div class="form-step reduced-padding" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_location">Where are you from?</span>
                            <span class="step-error" data-i18n="err_invalid">Invalid value.</span>
                        </label>
                        <input virtualkeyboardpolicy="auto" type="text" name="lc_pj" data-field="project" minlength="2" maxlength="200" data-i18n-ph="ph_location" placeholder="City">
                    </div>

                    <div class="form-step" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_services">What are you interested in?</span>
                            <span class="step-error" data-i18n="err_select">Select at least 1 option</span>
                        </label>
                        <select id="lf_select_options" class="select2-langa-form-options" name="services[]" multiple="multiple" data-i18n-ph="ph_select" placeholder="Select">
                            <?php foreach ($services as $svc): ?>
                            <option><?php echo esc_html($svc); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-step reduced-padding" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_appointment">Book an appointment</span>
                            <span class="step-error date-error" style="display:none;" data-i18n="err_date">Change the date</span>
                            <span class="step-error time-error" style="display:none;" data-i18n="err_time">Change the time</span>
                        </label>
                        <div class="appointment">
                            <button id="date-picker">dd/mm/yyyy</button>
                            <input style="background-color:#fff!important;" virtualkeyboardpolicy="manual" placeholder="dd/mm/yyyy" type="date" id="appointment_date" name="appointment_date" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>">
                            <button id="time-picker">--:--</button>
                            <input style="background-color:#fff!important;" virtualkeyboardpolicy="manual" placeholder="--:--" min="09:00" max="18:00" type="time" id="appointment_time" name="appointment_time">
                        </div>
                    </div>

                    <div class="form-step reduced-padding" style="display:none;">
                        <label class="step-title">
                            <span class="step-header" data-i18n="step_notes">Additional notes</span>
                            <span class="step-error" data-i18n="err_invalid">Invalid value.</span>
                        </label>
                        <textarea name="lc_nt" data-field="notes" rows="1" maxlength="500" data-i18n-ph="ph_notes" placeholder="Anything else?"></textarea>
                    </div>

                    <div class="next-button">
                        <button id="lf-next-button" data-i18n="btn_next">Next</button>
                        <button style="display:none;" id="lf-send-button" data-i18n="btn_send">Send</button>
                    </div>

                    <p class="lcf-links">
                        <?php if (!empty($footer['privacy_url'])): ?><a href="<?php echo esc_url($footer['privacy_url']); ?>" target="_blank">Privacy</a><?php endif; ?>
                        <?php if (!empty($footer['privacy_url']) && !empty($footer['terms_url'])): ?> &#8226; <?php endif; ?>
                        <?php if (!empty($footer['terms_url'])): ?><a href="<?php echo esc_url($footer['terms_url']); ?>" target="_blank">Terms</a><?php endif; ?>
                        <?php if (!empty($footer['terms_url']) && !empty($footer['about_url'])): ?> &#8226; <?php endif; ?>
                        <?php if (!empty($footer['about_url'])): ?><a href="<?php echo esc_url($footer['about_url']); ?>" target="_blank">About</a><?php endif; ?>
                    </p>
                </div>

                <div id="lf-success-state" style="display:none;" data-i18n="success">Thank you. A representative will contact you within 24 hours.</div>
            </div>
        </div>
    </div>
</div>

<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- wp_footer callback, blob injection ?>
<script>
var AJAX_URL=<?php echo wp_json_encode(esc_url_raw($ajax)); ?>;
var NONCE=<?php echo wp_json_encode($nonce); ?>;
var ORIGIN=<?php echo wp_json_encode(esc_url_raw($origin)); ?>;
var LANGA_LANG=<?php
  $__cl = function_exists('langa_tools_client_detect_lang') ? langa_tools_client_detect_lang() : 'en';
  echo wp_json_encode($__cl);
?>;

var langaContainer=document.getElementById('langa-form-container');
var currentStep=0,numOfSteps,maxVisitedStep=0;
var isTyping=false,focusedElement=null;
var dateInput,datePicker,timeInput,timePicker;
var minDate,maxDate,minTime,maxTime;
var timeSpecified=false,dateSpecified=false;
var formAlreadySubmitted=false;

/* ═══ i18n — 7 languages + EN fallback ═══ */
var I18N={
en:{step_name:'Contact the developer',step_company:'Company',step_phone:'Phone',
step_location:'Where are you from?',step_services:'What are you interested in?',
step_appointment:'Book an appointment',step_notes:'Additional notes',
ph_name:'What is your name?',ph_company:'What is your company?',
ph_email:'What is your email?',ph_phone:'Where can we reach you?',
ph_location:'City',ph_select:'Select',ph_notes:'Anything else?',
err_invalid:'Invalid value.',err_select:'Select at least 1 option',
err_date:'Change the date',err_time:'Change the time',
btn_next:'Next',btn_send:'Send',
success:'Thank you. A representative will contact you within 24 hours.',
alert_missing:'Required fields missing: ',
fld_name:'Name',fld_company:'Company',fld_email:'Email',fld_phone:'Phone',
fld_location:'Location',fld_services:'Services',fld_date:'Appointment date',fld_time:'Appointment time',
err_generic:'Error.',err_network:'Network error.'},
it:{step_name:'Contatta lo sviluppatore',step_company:'Ragione sociale',step_phone:'Telefono',
step_location:'Da dove ci contatti?',step_services:'Quindi, cosa ti interessa?',
step_appointment:'Fissa un appuntamento',step_notes:'Note aggiuntive',
ph_name:'Qual \u00e8 il tuo nome?',ph_company:'Qual \u00e8 l\'azienda?',
ph_email:'Qual \u00e8 la tua email?',ph_phone:'Dove ti contattiamo?',
ph_location:'Milano',ph_select:'Seleziona',ph_notes:'Vuoi aggiungere qualcosa?',
err_invalid:'Valore non corretto.',err_select:'Inserisci almeno 1 opzione',
err_date:'Cambia la data',err_time:'Cambia l\'ora',
btn_next:'Avanti',btn_send:'Invia',
success:'Grazie. Un nostro responsabile ti contatter\u00e0 entro 24 ore.',
alert_missing:'Campi obbligatori mancanti: ',
fld_name:'Nome',fld_company:'Azienda',fld_email:'Email',fld_phone:'Telefono',
fld_location:'Luogo',fld_services:'Servizi',fld_date:'Data appuntamento',fld_time:'Ora appuntamento',
err_generic:'Errore.',err_network:'Errore di rete.'},
fr:{step_name:'Contacter le d\u00e9veloppeur',step_company:'Entreprise',step_phone:'T\u00e9l\u00e9phone',
step_location:'D\'o\u00f9 nous contactez-vous ?',step_services:'Qu\'est-ce qui vous int\u00e9resse ?',
step_appointment:'Prendre rendez-vous',step_notes:'Notes suppl\u00e9mentaires',
ph_name:'Quel est votre nom ?',ph_company:'Quelle est votre entreprise ?',
ph_email:'Quel est votre email ?',ph_phone:'O\u00f9 vous joindre ?',
ph_location:'Ville',ph_select:'S\u00e9lectionner',ph_notes:'Autre chose ?',
err_invalid:'Valeur incorrecte.',err_select:'S\u00e9lectionnez au moins 1 option',
err_date:'Changez la date',err_time:'Changez l\'heure',
btn_next:'Suivant',btn_send:'Envoyer',
success:'Merci. Un responsable vous contactera dans les 24 heures.',
alert_missing:'Champs obligatoires manquants : ',
fld_name:'Nom',fld_company:'Entreprise',fld_email:'Email',fld_phone:'T\u00e9l\u00e9phone',
fld_location:'Lieu',fld_services:'Services',fld_date:'Date du rendez-vous',fld_time:'Heure du rendez-vous',
err_generic:'Erreur.',err_network:'Erreur r\u00e9seau.'},
de:{step_name:'Entwickler kontaktieren',step_company:'Unternehmen',step_phone:'Telefon',
step_location:'Woher kontaktieren Sie uns?',step_services:'Wof\u00fcr interessieren Sie sich?',
step_appointment:'Termin vereinbaren',step_notes:'Weitere Anmerkungen',
ph_name:'Wie ist Ihr Name?',ph_company:'Wie hei\u00dft Ihr Unternehmen?',
ph_email:'Wie ist Ihre E-Mail?',ph_phone:'Wo erreichen wir Sie?',
ph_location:'Stadt',ph_select:'Ausw\u00e4hlen',ph_notes:'M\u00f6chten Sie etwas hinzuf\u00fcgen?',
err_invalid:'Ung\u00fcltiger Wert.',err_select:'Mindestens 1 Option w\u00e4hlen',
err_date:'Datum \u00e4ndern',err_time:'Uhrzeit \u00e4ndern',
btn_next:'Weiter',btn_send:'Senden',
success:'Vielen Dank. Ein Mitarbeiter wird Sie innerhalb von 24 Stunden kontaktieren.',
alert_missing:'Pflichtfelder fehlen: ',
fld_name:'Name',fld_company:'Unternehmen',fld_email:'E-Mail',fld_phone:'Telefon',
fld_location:'Ort',fld_services:'Leistungen',fld_date:'Termindatum',fld_time:'Terminzeit',
err_generic:'Fehler.',err_network:'Netzwerkfehler.'},
es:{step_name:'Contactar al desarrollador',step_company:'Empresa',step_phone:'Tel\u00e9fono',
step_location:'\u00bfDesde d\u00f3nde nos contactas?',step_services:'\u00bfQu\u00e9 te interesa?',
step_appointment:'Reservar una cita',step_notes:'Notas adicionales',
ph_name:'\u00bfCu\u00e1l es tu nombre?',ph_company:'\u00bfCu\u00e1l es tu empresa?',
ph_email:'\u00bfCu\u00e1l es tu email?',ph_phone:'\u00bfD\u00f3nde te contactamos?',
ph_location:'Ciudad',ph_select:'Seleccionar',ph_notes:'\u00bfAlgo m\u00e1s?',
err_invalid:'Valor incorrecto.',err_select:'Selecciona al menos 1 opci\u00f3n',
err_date:'Cambia la fecha',err_time:'Cambia la hora',
btn_next:'Siguiente',btn_send:'Enviar',
success:'Gracias. Un responsable te contactar\u00e1 en 24 horas.',
alert_missing:'Campos obligatorios faltantes: ',
fld_name:'Nombre',fld_company:'Empresa',fld_email:'Email',fld_phone:'Tel\u00e9fono',
fld_location:'Lugar',fld_services:'Servicios',fld_date:'Fecha de cita',fld_time:'Hora de cita',
err_generic:'Error.',err_network:'Error de red.'},
pt:{step_name:'Contactar o programador',step_company:'Empresa',step_phone:'Telefone',
step_location:'De onde nos contacta?',step_services:'O que lhe interessa?',
step_appointment:'Marcar uma reuni\u00e3o',step_notes:'Notas adicionais',
ph_name:'Qual \u00e9 o seu nome?',ph_company:'Qual \u00e9 a sua empresa?',
ph_email:'Qual \u00e9 o seu email?',ph_phone:'Onde o contactamos?',
ph_location:'Cidade',ph_select:'Selecionar',ph_notes:'Mais alguma coisa?',
err_invalid:'Valor inv\u00e1lido.',err_select:'Selecione pelo menos 1 op\u00e7\u00e3o',
err_date:'Alterar a data',err_time:'Alterar a hora',
btn_next:'Seguinte',btn_send:'Enviar',
success:'Obrigado. Um respons\u00e1vel ir\u00e1 contact\u00e1-lo dentro de 24 horas.',
alert_missing:'Campos obrigat\u00f3rios em falta: ',
fld_name:'Nome',fld_company:'Empresa',fld_email:'Email',fld_phone:'Telefone',
fld_location:'Local',fld_services:'Servi\u00e7os',fld_date:'Data da reuni\u00e3o',fld_time:'Hora da reuni\u00e3o',
err_generic:'Erro.',err_network:'Erro de rede.'},
ru:{step_name:'\u041a\u043e\u043d\u0442\u0430\u043a\u0442 \u0440\u0430\u0437\u0440\u0430\u0431\u043e\u0442\u0447\u0438\u043a\u0430',step_company:'\u041a\u043e\u043c\u043f\u0430\u043d\u0438\u044f',step_phone:'\u0422\u0435\u043b\u0435\u0444\u043e\u043d',
step_location:'\u041e\u0442\u043a\u0443\u0434\u0430 \u0432\u044b \u043d\u0430\u043c \u043f\u0438\u0448\u0435\u0442\u0435?',step_services:'\u0427\u0442\u043e \u0432\u0430\u0441 \u0438\u043d\u0442\u0435\u0440\u0435\u0441\u0443\u0435\u0442?',
step_appointment:'\u0417\u0430\u043f\u0438\u0441\u044c \u043d\u0430 \u0432\u0441\u0442\u0440\u0435\u0447\u0443',step_notes:'\u0414\u043e\u043f\u043e\u043b\u043d\u0438\u0442\u0435\u043b\u044c\u043d\u044b\u0435 \u0437\u0430\u043c\u0435\u0442\u043a\u0438',
ph_name:'\u041a\u0430\u043a \u0432\u0430\u0441 \u0437\u043e\u0432\u0443\u0442?',ph_company:'\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u043a\u043e\u043c\u043f\u0430\u043d\u0438\u0438?',
ph_email:'\u0412\u0430\u0448 email?',ph_phone:'\u041a\u0430\u043a \u0441 \u0432\u0430\u043c\u0438 \u0441\u0432\u044f\u0437\u0430\u0442\u044c\u0441\u044f?',
ph_location:'\u0413\u043e\u0440\u043e\u0434',ph_select:'\u0412\u044b\u0431\u0440\u0430\u0442\u044c',ph_notes:'\u0427\u0442\u043e-\u043d\u0438\u0431\u0443\u0434\u044c \u0435\u0449\u0451?',
err_invalid:'\u041d\u0435\u0432\u0435\u0440\u043d\u043e\u0435 \u0437\u043d\u0430\u0447\u0435\u043d\u0438\u0435.',err_select:'\u0412\u044b\u0431\u0435\u0440\u0438\u0442\u0435 \u0445\u043e\u0442\u044f \u0431\u044b 1 \u0432\u0430\u0440\u0438\u0430\u043d\u0442',
err_date:'\u0418\u0437\u043c\u0435\u043d\u0438\u0442\u0435 \u0434\u0430\u0442\u0443',err_time:'\u0418\u0437\u043c\u0435\u043d\u0438\u0442\u0435 \u0432\u0440\u0435\u043c\u044f',
btn_next:'\u0414\u0430\u043b\u0435\u0435',btn_send:'\u041e\u0442\u043f\u0440\u0430\u0432\u0438\u0442\u044c',
success:'\u0421\u043f\u0430\u0441\u0438\u0431\u043e. \u041f\u0440\u0435\u0434\u0441\u0442\u0430\u0432\u0438\u0442\u0435\u043b\u044c \u0441\u0432\u044f\u0436\u0435\u0442\u0441\u044f \u0441 \u0432\u0430\u043c\u0438 \u0432 \u0442\u0435\u0447\u0435\u043d\u0438\u0435 24 \u0447\u0430\u0441\u043e\u0432.',
alert_missing:'\u041e\u0431\u044f\u0437\u0430\u0442\u0435\u043b\u044c\u043d\u044b\u0435 \u043f\u043e\u043b\u044f \u043d\u0435 \u0437\u0430\u043f\u043e\u043b\u043d\u0435\u043d\u044b: ',
fld_name:'\u0418\u043c\u044f',fld_company:'\u041a\u043e\u043c\u043f\u0430\u043d\u0438\u044f',fld_email:'Email',fld_phone:'\u0422\u0435\u043b\u0435\u0444\u043e\u043d',
fld_location:'\u041c\u0435\u0441\u0442\u043e',fld_services:'\u0423\u0441\u043b\u0443\u0433\u0438',fld_date:'\u0414\u0430\u0442\u0430 \u0432\u0441\u0442\u0440\u0435\u0447\u0438',fld_time:'\u0412\u0440\u0435\u043c\u044f \u0432\u0441\u0442\u0440\u0435\u0447\u0438',
err_generic:'\u041e\u0448\u0438\u0431\u043a\u0430.',err_network:'\u041e\u0448\u0438\u0431\u043a\u0430 \u0441\u0435\u0442\u0438.'},
nl:{step_name:'Neem contact op met de ontwikkelaar',step_company:'Bedrijf',step_phone:'Telefoon',
step_location:'Vanwaar neemt u contact op?',step_services:'Waar bent u in ge\u00efnteresseerd?',
step_appointment:'Afspraak maken',step_notes:'Extra opmerkingen',
ph_name:'Wat is uw naam?',ph_company:'Wat is uw bedrijf?',
ph_email:'Wat is uw email?',ph_phone:'Waar kunnen we u bereiken?',
ph_location:'Stad',ph_select:'Selecteren',ph_notes:'Nog iets toe te voegen?',
err_invalid:'Ongeldige waarde.',err_select:'Selecteer minimaal 1 optie',
err_date:'Wijzig de datum',err_time:'Wijzig de tijd',
btn_next:'Volgende',btn_send:'Verzenden',
success:'Bedankt. Een medewerker neemt binnen 24 uur contact met u op.',
alert_missing:'Verplichte velden ontbreken: ',
fld_name:'Naam',fld_company:'Bedrijf',fld_email:'Email',fld_phone:'Telefoon',
fld_location:'Locatie',fld_services:'Diensten',fld_date:'Afspraakdatum',fld_time:'Afspraaktijd',
err_generic:'Fout.',err_network:'Netwerkfout.'},
ar:{step_name:'\u0627\u062a\u0635\u0644 \u0628\u0627\u0644\u0645\u0637\u0648\u0631',step_company:'\u0627\u0644\u0634\u0631\u0643\u0629',step_phone:'\u0627\u0644\u0647\u0627\u062a\u0641',
step_location:'\u0645\u0646 \u0623\u064a\u0646 \u062a\u062a\u0635\u0644\u061f',step_services:'\u0645\u0627 \u0627\u0644\u0630\u064a \u064a\u0647\u0645\u0643\u061f',
step_appointment:'\u062d\u062c\u0632 \u0645\u0648\u0639\u062f',step_notes:'\u0645\u0644\u0627\u062d\u0638\u0627\u062a \u0625\u0636\u0627\u0641\u064a\u0629',
ph_name:'\u0645\u0627 \u0627\u0633\u0645\u0643\u061f',ph_company:'\u0645\u0627 \u0634\u0631\u0643\u062a\u0643\u061f',
ph_email:'\u0645\u0627 \u0628\u0631\u064a\u062f\u0643\u061f',ph_phone:'\u0643\u064a\u0641 \u0646\u062a\u0648\u0627\u0635\u0644\u061f',
ph_location:'\u0627\u0644\u0645\u062f\u064a\u0646\u0629',ph_select:'\u0627\u062e\u062a\u064a\u0627\u0631',ph_notes:'\u0634\u064a\u0621 \u0622\u062e\u0631\u061f',
err_invalid:'\u0642\u064a\u0645\u0629 \u063a\u064a\u0631 \u0635\u0627\u0644\u062d\u0629.',err_select:'\u0627\u062e\u062a\u0631 \u062e\u064a\u0627\u0631\u0627 \u0648\u0627\u062d\u062f\u0627 \u0639\u0644\u0649 \u0627\u0644\u0623\u0642\u0644',
err_date:'\u063a\u064a\u0631 \u0627\u0644\u062a\u0627\u0631\u064a\u062e',err_time:'\u063a\u064a\u0631 \u0627\u0644\u0648\u0642\u062a',
btn_next:'\u0627\u0644\u062a\u0627\u0644\u064a',btn_send:'\u0625\u0631\u0633\u0627\u0644',
success:'\u0634\u0643\u0631\u0627. \u0633\u064a\u062a\u0648\u0627\u0635\u0644 \u0645\u0639\u0643 \u0645\u0645\u062b\u0644 \u062e\u0644\u0627\u0644 24 \u0633\u0627\u0639\u0629.',
alert_missing:'\u062d\u0642\u0648\u0644 \u0645\u0637\u0644\u0648\u0628\u0629 \u0646\u0627\u0642\u0635\u0629: ',
fld_name:'\u0627\u0644\u0627\u0633\u0645',fld_company:'\u0627\u0644\u0634\u0631\u0643\u0629',fld_email:'Email',fld_phone:'\u0627\u0644\u0647\u0627\u062a\u0641',
fld_location:'\u0627\u0644\u0645\u0643\u0627\u0646',fld_services:'\u0627\u0644\u062e\u062f\u0645\u0627\u062a',fld_date:'\u062a\u0627\u0631\u064a\u062e',fld_time:'\u0648\u0642\u062a',
err_generic:'\u062e\u0637\u0623.',err_network:'\u062e\u0637\u0623 \u0641\u064a \u0627\u0644\u0634\u0628\u0643\u0629.'}
};
var LANG_KEY=(function(){
    var b=(typeof LANGA_LANG==='string'?LANGA_LANG:'en').toLowerCase().split('-')[0];
    return I18N[b]?b:'en';
})();
var L=I18N[LANG_KEY];
function applyI18n(){
    document.querySelectorAll('[data-i18n]').forEach(function(el){
        var k=el.getAttribute('data-i18n');if(L[k])el.textContent=L[k];
    });
    document.querySelectorAll('[data-i18n-ph]').forEach(function(el){
        var k=el.getAttribute('data-i18n-ph');if(L[k])el.setAttribute('placeholder',L[k]);
    });
}
applyI18n();

function lf_validate_step(){
    var step=langaContainer.getElementsByClassName('form-step')[currentStep];
    if(!step)return true;
    /* Textarea steps (notes) are always optional */
    if(step.querySelector('textarea'))return true;
    var inp=step.querySelector('input');
    if(inp){
        var t=inp.getAttribute('type');
        if(t==='text')return inp.minLength<=inp.value.length;
        if(t==='tel')return inp.value.replace(/\D/g,'').length>=6;
        if(t==='email')return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inp.value.trim());
        if(t==='date'||t==='time')return checkDateAndTime();
        return true;
    }
    var sel=langaContainer.querySelectorAll('#langa-form-container .select2-selection__rendered > li.select2-selection__choice');
    if(sel&&sel.length>0)return true;
    if(step.querySelector('select'))return false;
    return true;
}

function hideStep(i){langaContainer.getElementsByClassName('form-step')[i].style.display='none'}
function showStep(i){
    var s=langaContainer.getElementsByClassName('form-step')[i];
    s.style.display='inline';
    showKeyboard();
}
function showKeyboard(){
    var s=langaContainer.getElementsByClassName('form-step')[currentStep];
    if(!s)return;
    var inp=s.querySelector('input');
    if(inp){
        var t=inp.getAttribute('type');
        if(t==='date'||t==='time'||t==='radio'||t==='checkbox')return;
    }
    if(inp){isTyping=true;focusedElement=inp;inp.focus()}
}

function lf_next_step(){
    if(!lf_validate_step()){
        jQuery('#form-fields').addClass('wrong-input');
        showKeyboard();return false;
    }
    jQuery('#form-fields').removeClass('wrong-input');
    if(currentStep<numOfSteps){
        hideStep(currentStep);
        currentStep=(currentStep+1)%numOfSteps;
        showStep(currentStep);
        maxVisitedStep=Math.max(currentStep,maxVisitedStep);
        jQuery('.previous-button').show();
        if(currentStep===numOfSteps-1){
            jQuery('#lf-next-button').hide();
            jQuery('#lf-send-button').show();
        }
    }
}

function lf_previous_step(){
    jQuery('#form-fields').removeClass('wrong-input');
    hideStep(currentStep);
    currentStep=Math.max(0,(currentStep-1)%numOfSteps);
    showStep(currentStep);
    if(currentStep===0)jQuery('.previous-button').hide();
    jQuery('#lf-send-button').hide();
    jQuery('#lf-next-button').show();
}

function isValideEmail(v){return /^[a-zA-Z0-9.\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(v)}
function isValideDate(d){d=d||dateInput.value;return(new Date(d)>=new Date(minDate)&&new Date(d)<=new Date(maxDate))||!dateSpecified}
function isValideTime(t){t=t||timeInput.value;return(t>=minTime&&t<=maxTime)||!timeSpecified}
function checkDateAndTime(){
    if(!isValideDate()){
        jQuery('#form-fields').addClass('wrong-input');
        if(langaContainer.querySelector('.time-error'))langaContainer.querySelector('.time-error').style.display='none';
        if(langaContainer.querySelector('.date-error'))langaContainer.querySelector('.date-error').style.display='inline';
        return false;
    }
    if(!isValideTime()){
        jQuery('#form-fields').addClass('wrong-input');
        if(langaContainer.querySelector('.date-error'))langaContainer.querySelector('.date-error').style.display='none';
        if(langaContainer.querySelector('.time-error'))langaContainer.querySelector('.time-error').style.display='inline';
        return false;
    }
    jQuery('#form-fields').removeClass('wrong-input');
    if(langaContainer.querySelector('.date-error'))langaContainer.querySelector('.date-error').style.display='none';
    if(langaContainer.querySelector('.time-error'))langaContainer.querySelector('.time-error').style.display='none';
    return true;
}

function getFormData(){
    var fd={};
    langaContainer.querySelectorAll('#langa-form input').forEach(function(i){
        var name=i.getAttribute('data-field')||i.getAttribute('name'),val=i.value;
        fd[name]=val;
    });
    langaContainer.querySelectorAll('#langa-form textarea').forEach(function(t){
        fd[t.getAttribute('data-field')||t.getAttribute('name')]=t.value;
    });
    try{
        var sel=jQuery('#langa-form .select2-langa-form-options').select2('data');
        var opts=[];sel.forEach(function(i){opts.push(i.text)});
        fd['options']=opts;
    }catch(e){}
    return fd;
}

function lf_send_form(){
    if(formAlreadySubmitted)return false;
    var data=getFormData();
    /* Final pre-flight: all required fields (everything except notes) */
    var missing=[];
    if(!data.fullname||data.fullname.length<3)missing.push(L.fld_name);
    if(!data.company||data.company.length<2)missing.push(L.fld_company);
    if(!data.email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email))missing.push(L.fld_email);
    if(!data.phone||(data.phone.replace(/\D/g,'').length<6))missing.push(L.fld_phone);
    if(!data.project||data.project.length<2)missing.push(L.fld_location);
    if(!data.options||!data.options.length)missing.push(L.fld_services);
    if(!data.appointment_date)missing.push(L.fld_date);
    if(!data.appointment_time)missing.push(L.fld_time);
    if(missing.length>0){alert(L.alert_missing+missing.join(', '));return false;}
    var fd=new FormData();
    fd.append('action','langa_credits_submit');
    fd.append('_nonce',NONCE);
    fd.append('origin',ORIGIN);
    fd.append('fullname',data.fullname||'');
    fd.append('company',data.company||'');
    fd.append('email',data.email||'');
    fd.append('phone',data.phone||'');
    fd.append('project',data.project||'');
    if(data.options&&data.options.length){data.options.forEach(function(o){fd.append('services[]',o)})}
    fd.append('appointment_date',data.appointment_date||'');
    fd.append('appointment_time',data.appointment_time||'');
    fd.append('notes',data.notes||'');
    fd.append('lang',LANG_KEY);
    fetch(AJAX_URL,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
        if(d.success){
            jQuery('.previous-button').hide();
            jQuery('#form-fields').hide();
            jQuery('#lf-success-state').show();
            langaContainer.style.backgroundColor='#fff';
            formAlreadySubmitted=true;
            setTimeout(function(){parent.postMessage({type:'langa-credits-done'},'*')},4000);
        }else{
            var msg=(d.data&&d.data.message)||L.err_generic;
            alert(msg);
        }
    }).catch(function(){alert(L.err_network)});
}

document.addEventListener('DOMContentLoaded',function(){
    /* Phone: strip letters on input */
    var phoneInput=document.querySelector('input[type=tel]');
    if(phoneInput){
        phoneInput.addEventListener('keyup',function(){this.value=this.value.replace(/[a-z]/gi,'')});
    }

    /* Init select2 */
    var selEl=langaContainer.querySelector('#langa-form .select2-langa-form-options');
    if(selEl){
        var ph=L.ph_select||selEl.getAttribute('placeholder')||'Select';
        jQuery('#langa-form .select2-langa-form-options').select2({
            placeholder:ph,
            dropdownParent:langaContainer.querySelector('#form-fields')
        });
    }

    /* Init appointment */
    dateInput=langaContainer.querySelector('input[type=date]');
    datePicker=langaContainer.querySelector('button#date-picker');
    timeInput=langaContainer.querySelector('input[type=time]');
    timePicker=langaContainer.querySelector('button#time-picker');

    if(datePicker&&dateInput){
        datePicker.addEventListener('click',function(){dateInput.focus();dateInput.click()});
        dateInput.addEventListener('input',function(){datePicker.innerHTML=dateInput.value.split('-').reverse().join('-')});
        minDate=dateInput.getAttribute('min');maxDate=dateInput.getAttribute('max');
    }
    if(timePicker&&timeInput){
        timePicker.addEventListener('click',function(){timeInput.focus();timeInput.click()});
        timeInput.addEventListener('input',function(){timePicker.innerHTML=timeInput.value});
        minTime=timeInput.getAttribute('min');maxTime=timeInput.getAttribute('max');
    }

    /* Auto-fill date/time */
    if(dateInput&&timeInput&&!dateInput.value&&!timeInput.value){
        var now=new Date();
        var hh=String(now.getHours()).padStart(2,'0');
        var mm=String(now.getMinutes()).padStart(2,'0');
        var tv=hh+':'+mm;
        if(minTime&&tv<minTime)tv=minTime;
        if(maxTime&&tv>maxTime)tv=maxTime;
        var target=new Date(now);target.setDate(target.getDate()+3);
        while(target.getDay()===6||target.getDay()===0)target.setDate(target.getDate()+1);
        var dMin=dateInput.getAttribute('min')?new Date(dateInput.getAttribute('min')+'T00:00:00'):null;
        var dMax=dateInput.getAttribute('max')?new Date(dateInput.getAttribute('max')+'T00:00:00'):null;
        if(dMin&&target<dMin)target=new Date(dMin);
        while(target.getDay()===6||target.getDay()===0)target.setDate(target.getDate()+1);
        if(dMax&&target>dMax)target=new Date(dMax);
        var y=target.getFullYear(),m=String(target.getMonth()+1).padStart(2,'0'),dd=String(target.getDate()).padStart(2,'0');
        dateInput.value=y+'-'+m+'-'+dd;
        timeInput.value=tv;
        if(datePicker)datePicker.innerHTML=dd+'-'+m+'-'+y;
        if(timePicker)timePicker.innerHTML=tv;
    }

    /* Events on inputs */
    langaContainer.querySelectorAll('#form-fields > div > input').forEach(function(inp){
        inp.addEventListener('focusout',function(e){if(isTyping&&focusedElement===e.target)e.target.focus()});
        inp.addEventListener('keydown',function(e){if(e.keyCode===13){e.preventDefault();lf_next_step()}});
    });
    if(dateInput)dateInput.addEventListener('input',function(){dateSpecified=true;checkDateAndTime()});
    if(timeInput)timeInput.addEventListener('input',function(){timeSpecified=true;checkDateAndTime()});

    /* Init steps */
    numOfSteps=langaContainer.querySelectorAll('.form-step').length;
    currentStep=0;maxVisitedStep=0;
    document.getElementById('lf-next-button').addEventListener('click',lf_next_step);
    document.getElementById('lf-send-button').addEventListener('click',lf_send_form);
    showStep(0);

    /* iOS keyboard: keep form above virtual keyboard */
    var fc=document.getElementById('langa-form-container');
    if(fc && window.visualViewport){
      var onVPResize=function(){
        var kbH=window.innerHeight-window.visualViewport.height;
        fc.style.bottom=(kbH>0?kbH:0)+'px';
        /* also shift overlay gradient */
        var ov=document.getElementById('lcf-overlay');
        if(ov)ov.style.bottom=(kbH>0?kbH:0)+'px';
      };
      window.visualViewport.addEventListener('resize',onVPResize);
      window.visualViewport.addEventListener('scroll',onVPResize);
    }
});
</script>
</body>
</html><?php
  return ob_get_clean();
}



/* ─── AJAX handler ─── */
add_action('wp_ajax_langa_credits_submit', 'langa_credits_handle_submit');
add_action('wp_ajax_nopriv_langa_credits_submit', 'langa_credits_handle_submit');

function langa_credits_handle_submit() {
  if (!check_ajax_referer('langa_credits_submit', '_nonce', false)) wp_send_json_error(array('message'=>'Invalid request.'));
  $name=sanitize_text_field(wp_unslash($_POST['fullname']??''));
  $company=sanitize_text_field(wp_unslash($_POST['company']??''));
  $email=sanitize_email(wp_unslash($_POST['email']??''));
  $phone=sanitize_text_field(wp_unslash($_POST['phone']??''));
  $project=sanitize_text_field(wp_unslash($_POST['project']??''));
  $services=isset($_POST['services'])&&is_array($_POST['services'])?array_map('sanitize_text_field',wp_unslash($_POST['services'])):array();
  $appt_date=sanitize_text_field(wp_unslash($_POST['appointment_date']??''));
  $appt_time=sanitize_text_field(wp_unslash($_POST['appointment_time']??''));
  $notes=sanitize_textarea_field(wp_unslash($_POST['notes']??''));
  $origin=esc_url_raw(wp_unslash($_POST['origin']??''));
  $lang=sanitize_key(wp_unslash($_POST['lang']??'en'));
  if($name===''||!is_email($email)) wp_send_json_error(array('message'=>'Name and valid email are required.'));
  if(strlen($company)<2) wp_send_json_error(array('message'=>'Company is required.'));
  if(strlen(preg_replace('/\D/','',$phone))<6) wp_send_json_error(array('message'=>'A valid phone number is required.'));
  if(strlen($project)<2) wp_send_json_error(array('message'=>'Location is required.'));
  if(empty($services)) wp_send_json_error(array('message'=>'Please select at least one service.'));
  if($appt_date===''||$appt_time==='') wp_send_json_error(array('message'=>'Appointment date and time are required.'));

  // i18n email labels (matches frontend dictionary)
  $i18n_labels = array(
    'en' => array('name'=>'Name','company'=>'Company','email'=>'Email','phone'=>'Phone','location'=>'Location','services'=>'Services','appointment'=>'Appointment','notes'=>'Notes','origin'=>'Origin'),
    'it' => array('name'=>'Nome','company'=>'Azienda','email'=>'Email','phone'=>'Telefono','location'=>'Luogo','services'=>'Servizi','appointment'=>'Appuntamento','notes'=>'Note','origin'=>'Origine'),
    'fr' => array('name'=>'Nom','company'=>'Entreprise','email'=>'Email','phone'=>"T\xc3\xa9l\xc3\xa9phone",'location'=>'Lieu','services'=>'Services','appointment'=>'Rendez-vous','notes'=>'Notes','origin'=>'Origine'),
    'de' => array('name'=>'Name','company'=>'Unternehmen','email'=>'E-Mail','phone'=>'Telefon','location'=>'Ort','services'=>'Leistungen','appointment'=>'Termin','notes'=>'Anmerkungen','origin'=>'Herkunft'),
    'es' => array('name'=>'Nombre','company'=>'Empresa','email'=>'Email','phone'=>"Tel\xc3\xa9fono",'location'=>'Lugar','services'=>'Servicios','appointment'=>'Cita','notes'=>'Notas','origin'=>'Origen'),
    'pt' => array('name'=>'Nome','company'=>'Empresa','email'=>'Email','phone'=>'Telefone','location'=>'Local','services'=>"Servi\xc3\xa7os",'appointment'=>"Reuni\xc3\xa3o",'notes'=>'Notas','origin'=>'Origem'),
    'nl' => array('name'=>'Naam','company'=>'Bedrijf','email'=>'Email','phone'=>'Telefoon','location'=>'Locatie','services'=>'Diensten','appointment'=>'Afspraak','notes'=>'Opmerkingen','origin'=>'Herkomst'),
  );
  $lbl = isset($i18n_labels[$lang]) ? $i18n_labels[$lang] : $i18n_labels['en'];

  $to=langa_credits_recipient();$site=get_bloginfo('name','display');
  $color = (langa_credits_mode() === 'iframe') ? '#999999' : langa_credits_primary_color();
  $subject='Credits — '.$name.' — '.$site;
  $td='<td style="padding:6px 10px;font-weight:600;color:#374151;width:120px;">';
  $tv='<td style="padding:6px 10px;">';
  $content='<table style="width:100%;border-collapse:collapse;font-size:14px;">'
    .'<tr>'.$td.esc_html($lbl['name']).'</td>'.$tv.esc_html($name).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['company']).'</td>'.$tv.esc_html($company).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['email']).'</td>'.$tv.'<a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a></td></tr>'
    .'<tr>'.$td.esc_html($lbl['phone']).'</td>'.$tv.esc_html($phone).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['location']).'</td>'.$tv.esc_html($project).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['services']).'</td>'.$tv.esc_html(implode(', ',$services)).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['appointment']).'</td>'.$tv.esc_html($appt_date.' '.$appt_time).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['notes']).'</td>'.$tv.esc_html($notes).'</td></tr>'
    .'<tr>'.$td.esc_html($lbl['origin']).'</td>'.$tv.esc_html($origin).'</td></tr>'
    .'</table>';

  $vars=array('site'=>$site,'sender_name'=>$name,'sender_email'=>$email,'recipient_email'=>$to,'is_confirmation'=>false,
    'module_badge'=>'Credits','module_badge_bg'=>'#fef3c7','module_badge_color'=>'#92400e','accent_color'=>$color,
    'page_url'=>$origin);

  // Staff notification — shared pipeline only (no legacy fallback)
  $sent = (bool) langa_tools_client_mail_send(array(
    'to'           => $to,
    'subject'      => $subject,
    'title'        => $subject,
    'content_html' => $content,
    'reply_to'     => $email,
    'vars'         => $vars,
  ));
  if (function_exists('langa_tools_client_debug_log_mail')) langa_tools_client_debug_log_mail('credits', $to, $sent, $sent ? 'Sent' : 'wp_mail failed');
  if(!$sent) wp_send_json_error(array('message'=>'Unable to send the email at this time.'));

  // Client confirmation — shared pipeline only (no legacy fallback)
  if (is_email($email)) {
    $conf_args = array(
      'to'           => $email,
      'sender_name'  => $name,
      'module'       => 'credits',
      'module_label' => 'Credits',
      'site'         => $site,
      'summary_html' => $content,
      'accent_color' => $color,
    );
    // Grey mode: email always in English
    if (langa_credits_mode() === 'iframe') $conf_args['lang'] = 'en';
    langa_tools_client_mail_send_confirmation($conf_args);
  }

  do_action('langa_tools_client_form_submitted',0,array('form_id'=>'credits','preset'=>'credits','who'=>$name,'email'=>$email));
  wp_send_json_success(array('message'=>'Thank you! A representative will contact you within 24 hours.'));
}

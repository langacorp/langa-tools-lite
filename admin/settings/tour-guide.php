<?php
if (!defined('ABSPATH')) exit;

/**
 * LANGA Tools — Setup Tour Guide (v5.0)
 *
 * Two tour modes: Quick (5 steps) / Complete (9-10 steps with module deep-dives)
 * Edition-aware: LITE pushes PRO upgrade, PRO is functional walkthrough.
 *
 * v5 fixes: proper done state, Lite module targets, spotlight alignment
 */

define('LANGA_TOUR_DISMISSED_OPTION', 'langa_tools_tour_dismissed');

function langa_tools_client_tour_is_lite() {
  return defined('LANGA_TOOLS_IS_LITE') && LANGA_TOOLS_IS_LITE;
}

add_action('wp_ajax_langa_tour_dismiss', function () {
  check_ajax_referer('langa_tour_nonce', '_nonce');
  if (!current_user_can('manage_options')) wp_send_json_error();
  update_option(LANGA_TOUR_DISMISSED_OPTION, 1, false);
  wp_send_json_success();
});
add_action('wp_ajax_langa_tour_reset', function () {
  check_ajax_referer('langa_tour_nonce', '_nonce');
  if (!current_user_can('manage_options')) wp_send_json_error();
  delete_option(LANGA_TOUR_DISMISSED_OPTION);
  wp_send_json_success();
});

function langa_tools_client_tour_mod_enabled($key) {
  if (function_exists('langa_tools_client_feature_is_enabled'))
    return (bool) langa_tools_client_feature_is_enabled($key);
  if (function_exists('langa_tools_client_feature_is_config_enabled'))
    return (bool) langa_tools_client_feature_is_config_enabled($key);
  return false;
}

function langa_tools_client_tour_build_steps() {
  $is_lite = langa_tools_client_tour_is_lite();

  $license_ok = true; // Lite WP.org: all features free.
  $dev_bypass    = function_exists('langa_tools_client_dev_bypass_active') && langa_tools_client_dev_bypass_active();
  $eff_license   = $license_ok || $dev_bypass;
  $data_complete = function_exists('langa_tools_client_data_complete') && langa_tools_client_data_complete();
  $features_map  = function_exists('langa_tools_client_features_get_map') ? langa_tools_client_features_get_map() : array();
  $has_modules   = !empty(array_filter($features_map));

  // Email configured?
  $mail = function_exists('langa_tools_client_mail_get_settings') ? langa_tools_client_mail_get_settings() : array();
  $email_done = !empty($mail['enabled']) || !empty($mail['smtp']['enabled']);

  $pricing_url = 'https://tools.langa.tv/#pricing';

  // Module page target: in Lite, PRO module pages show a promo wrapper, not .langa-module-enable
  // Use the first .langa-card or .wrap as fallback
  $mod_target  = $is_lite ? '.wrap > div' : '.langa-module-enable';
  $mod_fallback = '.wrap';

  $steps = array();

  if ($is_lite) {
    // ═══ LITE ═══
    $steps[] = array('id'=>'free-module','mode'=>'both','title'=>'Your free toolkit',
      'desc'=>'<strong>UI/UX</strong> is always free: Custom Login screen, "built by" Credits footer, Maintenance mode, and seasonal effects. Your agency identity on every site, at zero cost.',
      'target'=>'#langa-modules','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'general','done'=>true,'position'=>'bottom');

    $steps[] = array('id'=>'data','mode'=>'both','title'=>'Company information',
      'desc'=>'Fill in your client\'s company name, address and contacts. This data automatically populates <strong>Credits</strong>, Business Card, Legal pages, vCard, QR code and email templates. Enter it once — it\'s used everywhere.',
      'target'=>'#langa-data-company','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'data','done'=>$data_complete,'position'=>'right');

    $steps[] = array('id'=>'email','mode'=>'both','title'=>'Email delivery',
      'desc'=>'Configure your SMTP settings so contact forms and notifications actually reach the inbox. Without SMTP, WordPress uses <code>wp_mail()</code> which often ends up in spam.',
      'target'=>'#langa-email-settings','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'endpoint','done'=>$email_done,'position'=>'bottom');

    $steps[] = array('id'=>'modules-overview','mode'=>'both','title'=>'Enable modules',
      'desc'=>'Each module is independent — enable only what you need. <strong>UI/UX</strong> is free. Toggle modules on/off anytime without losing their settings.',
      'target'=>'#langa-modules','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'general','done'=>langa_tools_client_tour_mod_enabled('adminux'),'position'=>'top');

    // COMPLETE only — module pages
    $steps[] = array('id'=>'mod-uiux','mode'=>'complete','title'=>'UI/UX — Brand your sites',
      'desc'=>'<strong>Custom Login</strong> replaces the boring WP login with your logo and colors. <strong>Credits</strong> adds a professional "built by" footer. <strong>Maintenance</strong> shows a branded page while you work. All free, all automatic.',
      'target'=>'.langa-module-enable','fallback'=>'.wrap','page'=>'langa-tools-client-ui-ux','tab'=>'','done'=>langa_tools_client_tour_mod_enabled('adminux'),'position'=>'bottom');

  } else {
    // ═══ PRO ═══
    // License tour step removed from Lite WP.org build.

    $steps[] = array('id'=>'data','mode'=>'both','title'=>'Company information',
      'desc'=>'Your client\'s company name, VAT, address and contacts are <strong>required to activate PRO modules</strong>. This data also auto-fills Legal pages, Business Card, Credits footer and email templates.',
      'target'=>'#langa-data-company','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'data','done'=>$data_complete,'position'=>'right');

    $steps[] = array('id'=>'email','mode'=>'both','title'=>'Email delivery',
      'desc'=>'Configure SMTP so that contact forms, notifications, and confirmations actually reach the inbox. Without this, WordPress defaults to <code>wp_mail()</code> which often lands in spam.',
      'target'=>'#langa-email-settings','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'endpoint','done'=>$email_done,'position'=>'bottom');

    $steps[] = array('id'=>'modules-overview','mode'=>'both','title'=>'Enable your modules',
      'desc'=>'Each module is independent — enable only what you need, toggle on/off anytime without losing settings. <strong>UI/UX is free</strong>. All others require an active subscription.',
      'target'=>'#langa-modules','fallback'=>'.langa-card','page'=>'langa-tools-client-settings','tab'=>'general','done'=>$has_modules,'position'=>'top');

    // COMPLETE only
    $steps[] = array('id'=>'mod-uiux','mode'=>'complete','title'=>'UI/UX — Brand your sites',
      'desc'=>'<strong>Custom Login</strong> replaces wp-login with your logo and colors. <strong>Credits</strong> adds a "built by" signature that persists after client handoff. <strong>Maintenance mode</strong> shows a branded page while you work.',
      'target'=>'.langa-module-enable','fallback'=>'.wrap','page'=>'langa-tools-client-ui-ux','tab'=>'','done'=>langa_tools_client_tour_mod_enabled('adminux'),'position'=>'bottom');

    $steps[] = array('id'=>'mod-forms','mode'=>'complete','title'=>'Forms — Contact in 30 seconds',
      'desc'=>'Create form presets with per-form recipients, confirmations, file uploads and spam protection. Copy the shortcode, paste anywhere. Forms auto-brand with your colors and integrate with Credits.',
      'target'=>'.langa-module-enable','fallback'=>'.wrap','page'=>'langa-tools-client-forms','tab'=>'','done'=>langa_tools_client_tour_mod_enabled('forms'),'position'=>'bottom');

    $steps[] = array('id'=>'mod-legal','mode'=>'complete','title'=>'Legal — GDPR in 2 minutes',
      'desc'=>'OPT-IN cookie consent banner, auto-generated Privacy, Cookie and Terms pages. Templates auto-fill from your company data. Compliant out of the box — just enable and publish.',
      'target'=>'.langa-module-enable','fallback'=>'.wrap','page'=>'langa-tools-client-legal','tab'=>'','done'=>langa_tools_client_tour_mod_enabled('legal'),'position'=>'bottom');

    $steps[] = array('id'=>'mod-safer','mode'=>'complete','title'=>'Safer — Harden WordPress',
      'desc'=>'<strong>Ghost Mode</strong> rewrites source code to hide WP fingerprints. <strong>Door Access</strong> moves wp-login.php. <strong>IP Allowlist</strong> locks down admin. All reversible — no .htaccess editing needed.',
      'target'=>'.langa-module-enable','fallback'=>'.wrap','page'=>'langa-tools-client-safer','tab'=>'','done'=>langa_tools_client_tour_mod_enabled('safer'),'position'=>'bottom');

    $steps[] = array('id'=>'mod-bc','mode'=>'complete','title'=>'Business Card — Digital identity',
      'desc'=>'Full-page digital business card with company info, team profiles, vCard download, Google Maps and QR code. Reads data from the Data tab automatically. Publish with <code>[langa_bc]</code>.',
      'target'=>'.langa-module-enable','fallback'=>'.wrap','page'=>'langa-tools-client-bc','tab'=>'','done'=>langa_tools_client_tour_mod_enabled('bc'),'position'=>'bottom');

    // Final
    $steps[] = array('id'=>'configure','mode'=>'both','title'=>'You\'re all set!',
      'desc'=>'Click any module card below to jump into its settings. Each one has its own tabs for features, style and tools. The <strong>Help tab</strong> in Settings always has a full reference guide, and you can relaunch this tour anytime.',
      'target'=>'#langa-overview-modules','fallback'=>'.wrap','page'=>'langa-tools-client','tab'=>'','done'=>false,'position'=>'top');
  }

  return array('steps'=>$steps,'is_lite'=>$is_lite,'license_ok'=>$eff_license,'data_ok'=>$data_complete,'modules_ok'=>$has_modules);
}

function langa_tools_client_tour_enqueue() {
  if (!current_user_can('manage_options')) return;
  $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
  if (strpos($page, 'langa-tools-client') !== 0) return;
  $ctx = langa_tools_client_tour_build_steps();
  if (!$ctx) return;

  $dismissed = (int) get_option(LANGA_TOUR_DISMISSED_OPTION, 0);
  $force     = isset($_GET['langa_tour']) && sanitize_text_field(wp_unslash($_GET['langa_tour'])) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display param
  $all_done  = $ctx['is_lite'] ? $ctx['data_ok'] : ($ctx['license_ok'] && $ctx['data_ok'] && $ctx['modules_ok']);
  $auto      = (!$dismissed && !$all_done && $page === 'langa-tools-client');

  wp_register_style('langa-tour-guide', false, array(), LANGA_TOOLS_CLIENT_VERSION);
  wp_enqueue_style('langa-tour-guide');
  wp_add_inline_style('langa-tour-guide', langa_tools_client_tour_css());
  wp_register_script('langa-tour-guide', false, array(), LANGA_TOOLS_CLIENT_VERSION, true);
  wp_enqueue_script('langa-tour-guide');
  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  wp_add_inline_script('langa-tour-guide', 'window.langaTour=' . wp_json_encode(array(
    'steps'=>$ctx['steps'],'nonce'=>wp_create_nonce('langa_tour_nonce'),
    'ajaxurl'=>admin_url('admin-ajax.php'),'autoLaunch'=>$force||$auto,
    'adminUrl'=>admin_url('admin.php'),'isLite'=>$ctx['is_lite'],
  )) . ';');
  wp_add_inline_script('langa-tour-guide', langa_tools_client_tour_js());
}
add_action('admin_enqueue_scripts', 'langa_tools_client_tour_enqueue', 99);

function langa_tools_client_tour_render_help_section() {
  $is_lite   = langa_tools_client_tour_is_lite();
  $dismissed = (int) get_option(LANGA_TOUR_DISMISSED_OPTION, 0);
  $nonce     = wp_create_nonce('langa_tour_nonce');
  $sub = $is_lite
    ? 'Guided walkthrough of your free tools and PRO modules. Choose Quick (1 min) or Complete (3 min).'
    : 'Guided setup walkthrough. Choose Quick (2 min) or Complete (5 min) with module deep-dives.';
  echo '<div style="margin:0 0 18px;padding:16px 20px;background:linear-gradient(135deg,#1d1d1f 0%,#333 100%);border-radius:12px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">';
  echo '<div><div style="font-size:15px;font-weight:700;color:#fff;margin:0 0 4px"><span style="color:#f37f0d">🚀</span> Setup Tour Guide</div>';
  echo '<div style="font-size:13px;color:rgba(255,255,255,.65);line-height:1.5">' . esc_html($sub) . '</div></div>';
  echo '<div style="display:flex;gap:8px;align-items:center;flex-shrink:0">';
  echo '<button type="button" id="langa-tour-launch-btn" onclick="if(window.langaTourStart){langaTourStart()}else{window.location.href=\'' . esc_js(admin_url('admin.php?page=langa-tools-client&langa_tour=1')) . '\'}"><span class="dashicons dashicons-welcome-learn-more"></span> Launch Tour</button>';
  if ($dismissed) {
    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin tour reset button handler
    echo '<button type="button" class="button" style="color:#fff;border-color:rgba(255,255,255,.3);background:transparent" id="langa-tour-reset-btn">Reset</button>';
    wp_print_inline_script_tag('(function(){var b=document.getElementById("langa-tour-reset-btn");if(b)b.addEventListener("click",function(){var fd=new FormData();fd.append("action","langa_tour_reset");fd.append("_nonce","' . esc_js($nonce) . '");fetch("' . esc_js(admin_url('admin-ajax.php')) . '",{method:"POST",body:fd,credentials:"same-origin"}).then(function(){b.textContent="Done!";b.disabled=true;setTimeout(function(){b.textContent="Reset";b.disabled=false},1500)})})})();');
  }
  echo '</div></div>';
}

function langa_tools_client_tour_css() {
  return '
#langa-tour-overlay{position:fixed;inset:0;z-index:999990;pointer-events:none;transition:opacity .25s ease}
#langa-tour-overlay.active{pointer-events:auto}
#langa-tour-overlay.fade-out{opacity:0}
#langa-tour-overlay svg{position:absolute;inset:0;width:100%;height:100%}
body.langa-tour-active{overflow:hidden!important}
body.langa-tour-active #wpwrap{overflow:hidden!important}
#langa-tour-tooltip{position:fixed;z-index:999991;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.08);padding:0;max-width:400px;min-width:300px;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,sans-serif;transition:opacity .2s ease,transform .2s ease}
#langa-tour-tooltip.entering{opacity:0;transform:translateY(8px)}
#langa-tour-tooltip .ltt-close{position:absolute;top:10px;right:12px;width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:#b0b0b0;font-size:18px;border-radius:6px;transition:background .15s,color .15s;padding:0;line-height:1}
#langa-tour-tooltip .ltt-close:hover{background:#f5f5f7;color:#1d1d1f}
#langa-tour-tooltip .ltt-header{padding:16px 20px 0;display:flex;align-items:flex-start;gap:10px}
#langa-tour-tooltip .ltt-step-badge{flex-shrink:0;width:28px;height:28px;background:#f37f0d;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
#langa-tour-tooltip .ltt-step-badge.done{background:#16a34a}
#langa-tour-tooltip .ltt-step-badge.pro{background:linear-gradient(135deg,#f37f0d,#e85d04)}
#langa-tour-tooltip .ltt-header-text{flex:1;min-width:0}
#langa-tour-tooltip .ltt-title{font-size:15px;font-weight:700;color:#1d1d1f;margin:0;line-height:1.3;display:inline}
#langa-tour-tooltip .ltt-pro-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;margin:0 0 0 8px;background:#fff3e0;color:#e65100;border-radius:4px;font-size:11px;font-weight:700;vertical-align:middle;position:relative;top:-1px}
#langa-tour-tooltip .ltt-body{padding:10px 20px 0;font-size:13px;color:#6e6e73;line-height:1.55}
#langa-tour-tooltip .ltt-body strong{color:#1d1d1f}
#langa-tour-tooltip .ltt-body code{background:#f5f5f7;padding:1px 5px;border-radius:4px;font-size:12px}
#langa-tour-tooltip .ltt-body a{color:#f37f0d;text-decoration:none;font-weight:600}
#langa-tour-tooltip .ltt-body a:hover{text-decoration:underline}
#langa-tour-tooltip .ltt-progress{padding:12px 20px 0;display:flex;gap:4px}
#langa-tour-tooltip .ltt-dot{width:100%;height:4px;border-radius:2px;background:#e5e5e7;transition:background .2s}
#langa-tour-tooltip .ltt-dot.active{background:#f37f0d}
#langa-tour-tooltip .ltt-dot.done{background:#16a34a}
#langa-tour-tooltip .ltt-footer{padding:14px 20px 16px;display:flex;align-items:center;justify-content:space-between;gap:8px}
#langa-tour-tooltip .ltt-dismiss{font-size:12px;color:#86868b;cursor:pointer;display:flex;align-items:center;gap:5px;background:none;border:none;padding:0}
#langa-tour-tooltip .ltt-dismiss:hover{color:#1d1d1f}
#langa-tour-tooltip .ltt-actions{display:flex;gap:8px}
#langa-tour-tooltip .ltt-btn{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:background .15s,transform .1s}
#langa-tour-tooltip .ltt-btn:active{transform:scale(.97)}
#langa-tour-tooltip .ltt-btn-skip{background:#f5f5f7;color:#6e6e73}
#langa-tour-tooltip .ltt-btn-skip:hover{background:#e8e8ed}
#langa-tour-tooltip .ltt-btn-next{background:#f37f0d;color:#fff}
#langa-tour-tooltip .ltt-btn-next:hover{background:#e06f00}
#langa-tour-tooltip .ltt-btn-done{background:#16a34a;color:#fff}
#langa-tour-tooltip .ltt-btn-done:hover{background:#15803d}
.langa-tour-spotlight{position:relative;z-index:999989;box-shadow:0 0 0 4px rgba(243,127,13,.35),0 0 20px rgba(243,127,13,.15);border-radius:12px;transition:box-shadow .3s ease}
#langa-tour-launch-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:8px;background:#f37f0d;color:#fff;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:background .15s}
#langa-tour-launch-btn:hover{background:#e06f00}
#langa-tour-launch-btn .dashicons{font-size:16px;width:16px;height:16px}
#langa-tour-chooser{position:fixed;z-index:999992;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;padding:32px 28px 24px;min-width:340px;max-width:440px;box-shadow:0 12px 48px rgba(0,0,0,.22),0 2px 12px rgba(0,0,0,.08);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,sans-serif;text-align:center}
#langa-tour-chooser .ltc-title{font-size:18px;font-weight:700;color:#1d1d1f;margin:0 0 4px}
#langa-tour-chooser .ltc-sub{font-size:13px;color:#86868b;margin:0 0 20px}
#langa-tour-chooser .ltc-options{display:flex;gap:12px;margin:0 0 16px}
#langa-tour-chooser .ltc-opt{flex:1;padding:16px 14px;border:2px solid #e5e5e7;border-radius:12px;cursor:pointer;text-align:center;transition:border-color .2s,box-shadow .2s;background:#fff}
#langa-tour-chooser .ltc-opt:hover{border-color:#f37f0d;box-shadow:0 0 0 3px rgba(243,127,13,.15)}
#langa-tour-chooser .ltc-opt .ltc-icon{font-size:28px;margin:0 0 8px;display:block}
#langa-tour-chooser .ltc-opt .ltc-opt-title{font-size:14px;font-weight:700;color:#1d1d1f;margin:0 0 4px}
#langa-tour-chooser .ltc-opt .ltc-opt-desc{font-size:12px;color:#86868b;line-height:1.4}
#langa-tour-chooser .ltc-opt .ltc-opt-time{font-size:11px;color:#f37f0d;font-weight:600;margin-top:6px;display:block}
#langa-tour-chooser .ltc-skip{font-size:12px;color:#86868b;cursor:pointer;background:none;border:none;margin-top:4px}
#langa-tour-chooser .ltc-skip:hover{color:#1d1d1f}
';
}

function langa_tools_client_tour_js() {
  return "
(function(){
'use strict';
if(!window.langaTour)return;
var T=window.langaTour,allSteps=T.steps||[],steps=[],current=0;
var overlay,tooltip,svgEl,savedScroll=0,chooser;

function setMode(mode){steps=[];for(var i=0;i<allSteps.length;i++){var m=allSteps[i].mode||'both';if(m==='both'||m===mode)steps.push(allSteps[i])}}

function showChooser(){
  buildOverlay();overlay.classList.add('active');lockScroll();
  var qN=allSteps.filter(function(s){return s.mode==='both'}).length;
  var cN=allSteps.length;
  var qT=T.isLite?'~1 min':'~2 min';var cT=T.isLite?'~3 min':'~5 min';
  chooser=document.createElement('div');chooser.id='langa-tour-chooser';
  chooser.innerHTML='<div class=\"ltc-title\">Welcome to LANGA Tools</div><div class=\"ltc-sub\">Choose how you want to explore</div><div class=\"ltc-options\"><div class=\"ltc-opt\" id=\"ltc-quick\"><span class=\"ltc-icon\">\u26A1</span><div class=\"ltc-opt-title\">Quick Setup</div><div class=\"ltc-opt-desc\">Essential steps to get up and running</div><span class=\"ltc-opt-time\">'+qN+' steps \u00B7 '+qT+'</span></div><div class=\"ltc-opt\" id=\"ltc-complete\"><span class=\"ltc-icon\">\uD83D\uDD0D</span><div class=\"ltc-opt-title\">Complete Tour</div><div class=\"ltc-opt-desc\">Full walkthrough with every module</div><span class=\"ltc-opt-time\">'+cN+' steps \u00B7 '+cT+'</span></div></div><button class=\"ltc-skip\" id=\"ltc-skip\">Maybe later</button><button class=\"ltc-dismiss-forever\" id=\"ltc-dismiss-forever\" style=\"display:block;margin:8px auto 0;background:none;border:none;color:#86868b;font-size:11px;cursor:pointer;text-decoration:underline\">Don\\\'t show again</button><div style=\"text-align:center;margin-top:4px;font-size:10px;color:#b0b0b0\">You can always restart the tour from Settings &rarr; Help</div>';
  document.body.appendChild(chooser);
  document.getElementById('ltc-quick').addEventListener('click',function(){removeChooser();setMode('quick');beginTour()});
  document.getElementById('ltc-complete').addEventListener('click',function(){removeChooser();setMode('complete');beginTour()});
  document.getElementById('ltc-skip').addEventListener('click',function(){removeChooser();closeTour()});
  document.getElementById('ltc-dismiss-forever').addEventListener('click',function(){removeChooser();dismissTour()});
}

function removeChooser(){if(chooser){chooser.remove();chooser=null}}
function buildOverlay(){if(overlay)return;overlay=document.createElement('div');overlay.id='langa-tour-overlay';overlay.innerHTML='<svg><defs><mask id=\"ltt-mask\"><rect fill=\"white\" width=\"100%\" height=\"100%\"/><rect id=\"ltt-cutout\" fill=\"black\" rx=\"12\" ry=\"12\"/></mask></defs><rect fill=\"rgba(0,0,0,0.55)\" width=\"100%\" height=\"100%\" mask=\"url(#ltt-mask)\"/></svg>';document.body.appendChild(overlay);svgEl=overlay.querySelector('svg')}

function navigateToStep(idx){
  var s=steps[idx],p=new URLSearchParams(window.location.search),pg=p.get('page')||'',tb=p.get('tab')||'';
  var need=(s.page!==pg)||(s.tab&&s.tab!==tb&&!(s.tab==='general'&&!tb));
  if(need){var url=T.adminUrl+'?page='+encodeURIComponent(s.page);if(s.tab)url+='&tab='+encodeURIComponent(s.tab);url+='&langa_tour=1&langa_tour_step='+idx;try{sessionStorage.setItem('langa_tour_mode',steps.length===allSteps.length?'complete':'quick')}catch(e){}window.location.href=url;return true}return false;
}

function lockScroll(){savedScroll=window.scrollY;document.body.classList.add('langa-tour-active')}
function unlockScroll(){document.body.classList.remove('langa-tour-active');window.scrollTo(0,savedScroll)}

function spotlightTarget(el){
  if(!el||!svgEl)return null;
  var r=el.getBoundingClientRect(),pad=10,cut=svgEl.querySelector('#ltt-cutout');
  cut.setAttribute('x',r.left-pad);cut.setAttribute('y',r.top-pad);
  cut.setAttribute('width',r.width+pad*2);cut.setAttribute('height',r.height+pad*2);
  el.classList.add('langa-tour-spotlight');return r;
}
function clearSpotlight(){var p=document.querySelector('.langa-tour-spotlight');if(p)p.classList.remove('langa-tour-spotlight')}

function positionTooltip(rect,pos){
  if(!tooltip||!rect)return;tooltip.classList.add('entering');
  var tw=tooltip.offsetWidth||380,th=tooltip.offsetHeight||220,gap=16,vw=window.innerWidth,vh=window.innerHeight,left,top;
  if(pos==='bottom'){left=rect.left+(rect.width/2)-(tw/2);top=rect.bottom+gap+10}
  else if(pos==='top'){left=rect.left+(rect.width/2)-(tw/2);top=rect.top-th-gap-10}
  else if(pos==='right'){left=rect.right+gap;top=rect.top+(rect.height/2)-(th/2)}
  else{left=rect.left-tw-gap;top=rect.top+(rect.height/2)-(th/2)}
  if(left<12)left=12;if(left+tw>vw-12)left=vw-tw-12;
  if(top<12)top=12;if(top+th>vh-12)top=vh-th-12;
  tooltip.style.left=left+'px';tooltip.style.top=top+'px';tooltip.style.transform='none';
  requestAnimationFrame(function(){tooltip.classList.remove('entering')});
}

function renderTooltip(idx){
  var s=steps[idx],isLast=(idx===steps.length-1),num=idx+1;
  var dots='';for(var i=0;i<steps.length;i++){var dc='ltt-dot';if(i===idx)dc+=' active';else if(steps[i].done)dc+=' done';dots+='<div class=\"'+dc+'\"></div>'}
  var badgeCls=s.done?'ltt-step-badge done':(s.pro_hint?'ltt-step-badge pro':'ltt-step-badge');
  var badgeContent=s.done?'&#10003;':(s.pro_hint?'\u2605':num);
  var proBadge=s.pro_hint?'<span class=\"ltt-pro-badge\"><span class=\"dashicons dashicons-lock\" style=\"font-size:12px;width:12px;height:12px\"></span> PRO</span>':'';
  var nextLabel=isLast?'Got it \u2713':'Next \u2192';
  var nextCls=isLast?'ltt-btn ltt-btn-done':'ltt-btn ltt-btn-next';
  tooltip.innerHTML='<button class=\"ltt-close\" id=\"ltt-close\" title=\"Close\">&times;</button><div class=\"ltt-header\"><div class=\"'+badgeCls+'\">'+badgeContent+'</div><div class=\"ltt-header-text\"><span class=\"ltt-title\">'+s.title+'</span>'+proBadge+'</div></div><div class=\"ltt-body\">'+s.desc+'</div><div class=\"ltt-progress\">'+dots+'</div><div class=\"ltt-footer\"><button class=\"ltt-dismiss\" id=\"ltt-dismiss\"><span class=\"dashicons dashicons-no-alt\" style=\"font-size:14px;width:14px;height:14px\"></span> Don\\'t show again</button><div class=\"ltt-actions\">'+(idx>0?'<button class=\"ltt-btn ltt-btn-skip\" id=\"ltt-prev\">\u2190 Back</button>':'')+'<button class=\"'+nextCls+'\" id=\"ltt-next\">'+nextLabel+'</button></div></div>';
  document.getElementById('ltt-next').addEventListener('click',function(){if(isLast){closeTour();return}goToStep(idx+1)});
  var pb=document.getElementById('ltt-prev');if(pb)pb.addEventListener('click',function(){goToStep(idx-1)});
  document.getElementById('ltt-dismiss').addEventListener('click',dismissTour);
  document.getElementById('ltt-close').addEventListener('click',closeTour);
}

function goToStep(idx){
  if(idx<0||idx>=steps.length){closeTour();return}
  if(navigateToStep(idx))return;
  current=idx;clearSpotlight();
  var s=steps[idx],el=document.querySelector(s.target);
  if(!el&&s.fallback)el=document.querySelector(s.fallback);
  if(el){unlockScroll();el.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(function(){lockScroll();var rect=spotlightTarget(el);renderTooltip(idx);positionTooltip(rect,s.position||'bottom');overlay.classList.add('active')},400)}
  else{lockScroll();renderTooltip(idx);if(svgEl){var cut=svgEl.querySelector('#ltt-cutout');cut.setAttribute('width',0);cut.setAttribute('height',0)}tooltip.style.left='50%';tooltip.style.top='50%';tooltip.style.transform='translate(-50%,-50%)';overlay.classList.add('active')}
}

function beginTour(){if(!tooltip){tooltip=document.createElement('div');tooltip.id='langa-tour-tooltip';document.body.appendChild(tooltip)}document.addEventListener('keydown',onKey);goToStep(0)}
function closeTour(){clearSpotlight();unlockScroll();removeChooser();if(overlay)overlay.classList.add('fade-out');if(tooltip)tooltip.style.display='none';setTimeout(function(){if(overlay)overlay.remove();if(tooltip)tooltip.remove();overlay=null;tooltip=null},300);document.removeEventListener('keydown',onKey);try{sessionStorage.removeItem('langa_tour_mode')}catch(e){}}
function dismissTour(){var fd=new FormData();fd.append('action','langa_tour_dismiss');fd.append('_nonce',T.nonce);fetch(T.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'});closeTour()}
function onKey(e){if(chooser){if(e.key==='Escape')closeTour();return}if(!overlay)return;if(e.key==='Escape')closeTour();if(e.key==='ArrowRight')goToStep(current+1);if(e.key==='ArrowLeft')goToStep(current-1)}

window.langaTourStart=function(mode){if(mode==='quick'||mode==='complete'){buildOverlay();overlay.classList.add('active');lockScroll();setMode(mode);beginTour()}else showChooser()};

var params=new URLSearchParams(window.location.search);
var resumeStep=parseInt(params.get('langa_tour_step'),10);
var forceTour=params.get('langa_tour')==='1';
if(!isNaN(resumeStep)&&forceTour){
  var cu=new URL(window.location.href);cu.searchParams.delete('langa_tour');cu.searchParams.delete('langa_tour_step');
  window.history.replaceState(null,'',cu.toString());
  var sm='quick';try{sm=sessionStorage.getItem('langa_tour_mode')||'quick'}catch(e){}setMode(sm);
  setTimeout(function(){buildOverlay();overlay.classList.add('active');lockScroll();if(!tooltip){tooltip=document.createElement('div');tooltip.id='langa-tour-tooltip';document.body.appendChild(tooltip)}document.addEventListener('keydown',onKey);goToStep(resumeStep)},600);
}else if(T.autoLaunch){setTimeout(function(){showChooser()},800)}
})();
";
}

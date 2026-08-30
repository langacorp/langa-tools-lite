<?php
if (!defined('ABSPATH')) exit;

/**
 * LANGA Tools Lite — Overview / Welcome page
 * v1.6.4
 */

function langa_tools_client_overview_page() {
  if (!current_user_can('manage_options')) { wp_die('Not allowed.'); }

  // ── Wizard dismiss handler ──
  if (isset($_GET['dismiss_wizard']) && $_GET['dismiss_wizard'] === '1' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'langa_dismiss_wizard')) {
    update_option('langa_tools_wizard_dismissed', 1, false);
    wp_safe_redirect(admin_url('admin.php?page=langa-tools-client'));
    exit;
  }

  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
  $is_welcome = isset($_GET['welcome']) && sanitize_key(wp_unslash($_GET['welcome'])) === '1';
  $wizard_dismissed = (int) get_option('langa_tools_wizard_dismissed', 0);

  $license_valid = true; // Lite WP.org: no license required, all features free.
  $dev_bypass    = langa_tools_client_dev_bypass_active();
  $license_real  = $license_valid; // true when valid license OR bypass
  // Lite ALWAYS shows upgrade CTA — never the "PRO ACTIVE" block
  if (defined('LANGA_TOOLS_IS_LITE') && LANGA_TOOLS_IS_LITE) {
    $license_real = false;
  }
  $license_last  = function_exists('langa_tools_client_license_last') ? langa_tools_client_license_last() : array();
  $is_revoked    = function_exists('langa_tools_client_license_is_revoked') && langa_tools_client_license_is_revoked();

  $registry = function_exists('langa_tools_client_features_registry') ? langa_tools_client_features_registry() : array();
  $features_map = function_exists('langa_tools_client_features_get_map') ? langa_tools_client_features_get_map() : array();

  $site_data = get_option('langa_tools_client_site_data', array());
  $company = (isset($site_data['company']) && is_array($site_data['company'])) ? $site_data['company'] : array();
  $has_site_data = !empty($company['brand']) || !empty($company['legal_name']) || !empty($company['vat']);

  $general_url  = admin_url('admin.php?page=langa-tools-client-settings&tab=general');
  $modules_url  = admin_url('admin.php?page=langa-tools-client-settings&tab=general#langa-modules');
  $data_url     = admin_url('admin.php?page=langa-tools-client-settings&tab=data');
  $endpoint_url = admin_url('admin.php?page=langa-tools-client-settings&tab=endpoint');

  // ── MATRIOSKA: License > Modules > Features ──
  // Step 1: License must be valid
  // Step 2: Company data filled
  // Step 3: At least one module enabled
  // Events (bridge) is always free — counts even without license
  $step_license = $license_valid;
  $step_data    = $has_site_data;
  $config_has_modules = !empty(array_filter($features_map));
  $has_free_module = function_exists('langa_tools_client_feature_is_config_enabled')
    ? (bool)langa_tools_client_feature_is_config_enabled('bridge') : false;
  $step_modules = ($step_license && $config_has_modules) || $has_free_module;
  // Smart Setup state (loaded early for pct)
  $ss_saved_type_early = get_option('langa_tools_smart_setup_type', '');
  $step_smart_early = ($ss_saved_type_early !== '');
  $steps_done   = (int)$step_license + (int)$step_data + (int)$step_smart_early + (int)$step_modules;
  $pct          = round(($steps_done / 4) * 100);

  // Auto-dismiss wizard when all 4 steps done
  if ($pct >= 100 && !$wizard_dismissed) {
    update_option('langa_tools_wizard_dismissed', 1, false);
    $wizard_dismissed = 1;
  }

  // Show wizard: on first visit OR while incomplete and not dismissed
  $show_wizard = ($is_welcome && !$wizard_dismissed) || ($pct < 100 && !$wizard_dismissed);

  $mod_meta = array(
    'adminux' => array('icon'=>'dashicons-admin-customizer','tag'=>'Your brand on every login screen, a "built by" credit in the footer, maintenance mode, seasonal effects. Your identity, always visible.','hint'=>'Enable Custom Login and Credits to brand every site you deliver. Maintenance mode protects your work during updates.'),
    'forms'   => array('icon'=>'dashicons-email-alt','tag'=>'Professional contact forms, ready in 30 seconds. Per-form recipients, client confirmations, credits integration. No extra plugin needed.','hint'=>'Create a form preset, copy the shortcode, done. Forms are branded with your color and styled automatically.'),
    'legal'   => array('icon'=>'dashicons-shield-alt','tag'=>'GDPR-ready privacy, cookie, and terms pages with OPT-IN consent banner. Protect your clients and yourself, pre-configured.','hint'=>'Select your policy pages, enable the cookie banner. Templates auto-fill with company data from the Data tab.'),
    'bc'      => array('icon'=>'dashicons-id-alt','tag'=>'Digital Business Card with vCard download, team profiles, map and QR code. A professional touch that impresses clients.','hint'=>'The Business Card reads company data automatically. Choose a style, add team members, publish with [langa_bc].'),
    'safer'   => array('icon'=>'dashicons-lock','tag'=>'WordPress hardening without complexity. Ghost Mode hides WP fingerprints, Door Access moves login, IP allowlist blocks threats.','hint'=>'Enable protections one by one. Start with Ghost Mode to hide WordPress signatures, then add Door Access for login security.'),
    'seo'     => array('icon'=>'dashicons-search','tag'=>'Core SEO built in: XML Sitemap, meta tags, Open Graph, Schema markup. No heavy SEO plugin required for most sites.','hint'=>'Enable the module to auto-generate sitemap and meta tags. Perfect for brochure sites that don\'t need Yoast complexity.'),
    'cache'   => array('icon'=>'dashicons-performance','tag'=>'Performance optimizations that actually matter: browser cache headers, query string cleanup, emoji removal. Measurable speed gains.','hint'=>'Enable optimizations one by one. Each one is safe and reversible. Check PageSpeed before and after.'),
    'bridge'  => array('icon'=>'dashicons-networking','tag'=>'Know what happens on every site you manage. Page speed, forms, errors, logins &mdash; all logged locally. Optional sync to your central server.','hint'=>'Events works automatically with zero configuration. Check the log anytime. Bridge sync is optional for multi-site dashboards.'),
    'ai'      => array('icon'=>'dashicons-format-chat','tag'=>'AI provider keys management. Connect OpenAI, Anthropic, or Google for AI-powered features across the plugin.','hint'=>'Enter the API keys for the AI providers you use. Keys are stored securely and shared across modules.'),
    'popup'   => array('icon'=>'dashicons-welcome-widgets-menus','tag'=>'Lightweight popup system with smart triggers, auto-open rules and full style control. No jQuery dependency, no performance hit.','hint'=>'Create popups with HTML or shortcode content. Set triggers (click, scroll, exit-intent) and customize the look.'),
  );

  $mod_price = array(
    'adminux' => 4.99, 'forms' => 4.99, 'legal' => 4.99, 'bc' => 4.99, 'safer' => 4.99,
    'seo' => 4.99, 'cache' => 4.99, 'bridge' => 4.99, 'ai' => 4.99, 'popup' => 4.99,
  );

  // Count active modules — Lite can only run FREE modules
  $active_count = 0;
  $free_count   = 0;
  $total_count  = count($registry);
  foreach ($registry as $rk => $rv) {
    $is_free = !empty($rv['free']);
    if ($is_free) $free_count++;
    $cfg_on = function_exists('langa_tools_client_feature_is_enabled')
      ? langa_tools_client_feature_is_enabled($rk)
      : (function_exists('langa_tools_client_feature_is_config_enabled') ? langa_tools_client_feature_is_config_enabled($rk) : 0);
    if ($cfg_on && $is_free) $active_count++;
  }
  // In Lite, total shown = free modules only
  $total_count = $free_count;
  $mail_s = function_exists('langa_tools_client_mail_get_settings') ? langa_tools_client_mail_get_settings() : array();
  $smtp_ok = !empty($mail_s['enabled']) && !empty($mail_s['smtp']['host']);

  $dismiss_url = wp_nonce_url(admin_url('admin.php?page=langa-tools-client&dismiss_wizard=1'), 'langa_dismiss_wizard');

  ?>
<?php langtoli_inline_style('.langa-ov{max-width:965px;margin:20px auto 40px}
    .langa-nz{min-height:0}.langa-nz .notice{margin:0 0 12px!important}
    .langa-hero{background:linear-gradient(135deg,#FAFAFA 0%,#f3f3f3 100%);color:#1d1d1f;border-radius:16px;padding:36px 40px 30px;margin-bottom:18px;position:relative;overflow:hidden;border:1px solid #e5e5e7}
    .langa-hero::before{content:\'\';position:absolute;top:-60px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(243,127,13,.06)}
    .langa-hero::after{content:\'\';position:absolute;bottom:-30px;left:-20px;width:120px;height:120px;border-radius:50%;background:rgba(243,127,13,.04)}
    .langa-hero h1{font-size:28px;font-weight:700;margin:0 0 6px;color:#1d1d1f;letter-spacing:-.02em}.langa-hero h1 b{color:#f37f0d;font-weight:700}
    .langa-hero .sub{font-size:14px;opacity:.7;margin:0 0 14px;line-height:1.6;color:#1d1d1f}
    .langa-hero .ver{display:inline-block;background:rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.08);border-radius:6px;padding:3px 12px;font-size:11px;font-family:ui-monospace,monospace;letter-spacing:.02em;color:#6e6e73}.langa-hero .ver b{color:#f37f0d;font-weight:600}
    .langa-hero::before{content:\'\';position:absolute;top:-60px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(245,158,11,.06)}
    .langa-hero::after{content:\'\';position:absolute;bottom:-30px;left:-20px;width:120px;height:120px;border-radius:50%;background:rgba(245,158,11,.04)}
    .langa-hero .sub{font-size:14px;opacity:.7;margin:0 0 14px;line-height:1.6}
    .langa-hero .ver{display:inline-block;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:3px 12px;font-size:11px;font-family:ui-monospace,monospace;letter-spacing:.02em}.langa-hero .ver b{color:#f37f0d;font-weight:600}
    .langa-hero .notice{display:none!important}
    .langa-wb{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:18px 22px;margin-bottom:14px;display:flex;gap:12px;align-items:flex-start;position:relative}
    .langa-wb h3{margin:0 0 3px;font-size:15px;color:#e65100}.langa-wb p{margin:0;font-size:13px;color:#e65100;line-height:1.5}
    .langa-wb .wb-dismiss{position:absolute;top:10px;right:14px;background:none;border:none;font-size:18px;color:#c2410c;cursor:pointer;opacity:.5;padding:4px;text-decoration:none}.langa-wb .wb-dismiss:hover{opacity:1}
    .lo-card{background:#fff;border:1px solid #e5e5e7;border-radius:14px;padding:22px 26px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
    .lo-card h2{font-size:16px;font-weight:700;margin:0 0 4px;color:#1d1d1f;letter-spacing:-.01em}.lo-card .d{color:#6e6e73;font-size:13px;margin:0 0 12px}
    .lo-bar{background:#e5e5e7;border-radius:8px;height:8px;margin:0 0 14px;overflow:hidden}
    .lo-bar i{display:block;height:100%;border-radius:8px;transition:width .4s}
    .lo-bar-ok{background:linear-gradient(90deg,#22c55e,#16a34a)}.lo-bar-wip{background:linear-gradient(90deg,#f37f0d,#d97706)}.lo-bar-off{background:#d2d2d7}
    .lo-steps{list-style:none;padding:0;margin:0}
    .lo-steps li{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid #f5f5f7;font-size:13.5px}.lo-steps li:last-child{border-bottom:none}
    .lo-ic{flex-shrink:0;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
    .ic-ok{background:#dcfce7;color:#166534}.ic-no{background:#1d1d1f;color:#fff}.ic-lk{background:#f3f4f6;color:#9ca3af}
    .sl{font-weight:600;color:#1d1d1f}.sd{color:#6e6e73;font-size:12.5px;margin-top:2px}
    .sa{margin-top:5px}.sa a{display:inline-block;background:#1d1d1f;color:#fff!important;border-radius:8px;padding:5px 14px;font-size:12px;font-weight:600;text-decoration:none!important;transition:background .15s}.sa a:hover{background:#424245}.sa .ok{color:#166534;font-size:12.5px;font-weight:600}
    .lo-allset{text-align:center;padding:14px 0 6px}
    .lo-allset .check{width:40px;height:40px;border-radius:50%;background:#dcfce7;display:inline-flex;align-items:center;justify-content:center;font-size:20px;margin:0 0 8px}
    .lo-allset p{margin:0;font-size:14px;font-weight:600;color:#166534}
    .lo-allset .sub{font-size:12px;font-weight:400;color:#6e6e73;margin-top:2px}
    .lo-row{display:grid;gap:14px;max-width:965px}.lo-8-4{grid-template-columns:3fr 1fr}@media(max-width:900px){.lo-8-4{grid-template-columns:1fr}}
    .lo-side{background:#fafafa;border:1px solid #e5e5e7;border-radius:14px;padding:16px 18px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
    .lo-side h3{font-size:11px;font-weight:700;margin:0 0 8px;color:#86868b;text-transform:uppercase;letter-spacing:.06em}
    .lo-side ul{list-style:none;padding:0;margin:0}.lo-side li{padding:5px 0;border-bottom:1px solid #f5f5f7;font-size:13px}.lo-side li:last-child{border-bottom:none}
    .lo-side li a{color:#0071e3;text-decoration:none}.lo-side .dashicons{font-size:14px;width:14px;height:14px;color:#86868b;margin-right:4px;vertical-align:text-bottom}
    .lo-b{display:inline-block;padding:1px 7px;border-radius:5px;font-size:10px;font-weight:700;margin-left:4px;letter-spacing:.02em}
    .lo-b-ok{background:#dcfce7;color:#166534}.lo-b-w{background:#fff7ed;color:#c2410c}.lo-b-f{background:#fce4ec;color:#b71c1c}
    .lo-mg{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;margin-top:10px}
    .lo-mc{background:#fff;border:1px solid #e5e5e7;border-radius:12px;overflow:hidden;transition:border-color .15s,box-shadow .15s}.lo-mc:hover{border-color:#d2d2d7;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .lo-mc-h{display:flex;align-items:center;gap:10px;padding:14px 16px;cursor:pointer;user-select:none}
    .lo-mc-i{flex-shrink:0;color:#86868b;font-size:18px;width:20px;height:20px}.lo-mc.on .lo-mc-i{color:#1d1d1f}
    .mc-d{display:inline-block;width:8px;height:8px;border-radius:50%;vertical-align:middle;margin-right:4px}.mc-d1{background:#22c55e}.mc-d0{background:#d2d2d7}.mc-dx{background:#ef4444}
    .lo-mc-n{flex:1;min-width:0}.mc-n{font-weight:600;font-size:13px;color:#1d1d1f;display:flex;align-items:center;gap:6px}.mc-s{font-size:11px;color:#86868b;margin-top:1px;display:flex;align-items:center;gap:4px}
    .mc-c{flex-shrink:0;color:#86868b;transition:transform .2s;font-size:16px;width:16px;height:16px}.lo-mc.is-e .mc-c{transform:rotate(180deg)}
    .mc-b{max-height:0;overflow:hidden;transition:max-height .25s}.lo-mc.is-e .mc-b{max-height:300px}
    .mc-bi{padding:0 16px 14px;border-top:1px solid #f5f5f7}.mc-t{font-size:12px;color:#6e6e73;line-height:1.5;margin:8px 0}
    .mc-hi{font-size:12px;color:#0071e3;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:8px 12px;margin:8px 0;line-height:1.5}
    .mc-pr{display:inline-block;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;letter-spacing:.02em}
    .mc-pr-paid{background:#fef3c7;color:#c56200}.mc-pr-free{background:#f3f4f6;color:#6e6e73}
    .mc-a{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.mc-a .button{font-size:12px;border-radius:8px}
    .lo-plan{background:linear-gradient(135deg,#fefce8 0%,#fffbeb 100%);border:1px solid #fde68a;border-radius:14px;padding:22px 26px;margin-bottom:14px;position:relative;overflow:hidden}
    
    .lo-plan h3{margin:0 0 4px;font-size:16px;font-weight:700;color:#c56200}
    .lo-plan .tagline{font-size:12.5px;color:#a16207;line-height:1.5;margin:0 0 14px}
    .lo-plan-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:0 0 14px}@media(max-width:600px){.lo-plan-grid{grid-template-columns:1fr}}
    .lo-plan-col{background:rgba(255,255,255,.7);border:1px solid rgba(253,230,138,.6);border-radius:10px;padding:14px 16px;text-align:center}
    .lo-plan-col.featured{border-color:#f37f0d;box-shadow:0 0 0 1px #f37f0d}
    .lo-plan-col .plan-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#a16207;margin:0 0 6px}
    .lo-plan-col .plan-price{font-size:26px;font-weight:800;color:#1d1d1f;letter-spacing:-.02em;margin:0 0 2px;line-height:1.2}
    .lo-plan-col .plan-price small{font-size:12px;font-weight:400;color:#6e6e73}
    .lo-plan-col .plan-save{font-size:11px;color:#166534;font-weight:700;margin:0 0 6px}
    .lo-plan-col .plan-note{font-size:11px;color:#6e6e73;margin:0;line-height:1.4}
    .lo-plan .or-line{text-align:center;font-size:11px;color:#a16207;text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin:0 0 10px}
    .lo-plan .permod{text-align:center;font-size:14px;color:#1d1d1f;font-weight:700;margin:0 0 4px}.lo-plan .permod small{font-weight:400;color:#6e6e73;font-size:12px}
    .lo-plan .permod-note{text-align:center;font-size:11px;color:#6e6e73;margin:0 0 14px;line-height:1.4}
    .lo-plan .cta-row{text-align:center;margin:6px 0 0}
    .lo-plan .cta{display:inline-block;background:#1d1d1f;color:#fff;border-radius:10px;padding:10px 28px;font-size:13px;font-weight:700;text-decoration:none;transition:background .15s;letter-spacing:-.01em}.lo-plan .cta:hover{background:#424245;color:#fff}
    .lo-lite-note{font-size:12px;color:#86868b;margin:8px 0 0;line-height:1.5;text-align:center}.lo-lite-note b{color:#1d1d1f}'); ?>

  <div class="wrap langa-ov">
    <h1 class="wp-heading-inline" style="font-size:0;line-height:0;height:0;margin:0;padding:0;overflow:hidden">LANGA Tools Lite</h1>
    <div class="langa-nz"></div>
    <hr class="wp-header-end" style="display:none!important">

    <div class="langa-hero">
      <div style="font-size:11px;color:#86868b;margin:0 0 6px;padding-left:50px">Powered by <a href="https://about.langa.tv" target="_blank" style="color:#f37f0d;font-weight:700;text-decoration:none">LANGA</a></div>
      <div style="display:flex;align-items:center;gap:12px;margin:0 0 2px">
        <img src="<?php echo esc_url(LANGA_TOOLS_CLIENT_URL . 'assets/images/plugin-icon.svg'); ?>" alt="Lite" style="width:38px;height:38px;border-radius:8px;flex-shrink:0">
        <h1 style="margin:0"><span style="color:#1d1d1f">Hey, this is </span><b>Tools Lite</b></h1>
      </div>
      <p class="sub">The only WordPress plugin designed to protect your identity as a developer, save you hours on every project, and deliver enterprise-grade stability &mdash; all in one lightweight package.</p>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:18px 0 14px">
        <div style="background:#fff;border:1px solid #e5e5e7;border-radius:10px;padding:14px 16px;border-top:2px solid #f37f0d">
          <div style="display:flex;align-items:center;gap:6px;margin:0 0 6px"><span class="dashicons dashicons-shield" style="color:#f37f0d;font-size:16px;width:16px;height:16px"></span><strong style="font-size:12px;color:#1d1d1f">Your Signature, Protected</strong></div>
          <p style="margin:0;font-size:11px;color:#6e6e73;line-height:1.5">Credits, login, forms, legal &mdash; your identity on every site, zero effort.</p>
        </div>
        <div style="background:#fff;border:1px solid #e5e5e7;border-radius:10px;padding:14px 16px;border-top:2px solid #0071e3">
          <div style="display:flex;align-items:center;gap:6px;margin:0 0 6px"><span class="dashicons dashicons-clock" style="color:#0071e3;font-size:16px;width:16px;height:16px"></span><strong style="font-size:12px;color:#1d1d1f">Hours Saved, Every Project</strong></div>
          <p style="margin:0;font-size:11px;color:#6e6e73;line-height:1.5">One plugin replaces 8&ndash;12 tools. Configure once, deploy everywhere.</p>
        </div>
        <div style="background:#fff;border:1px solid #e5e5e7;border-radius:10px;padding:14px 16px;border-top:2px solid #16a34a">
          <div style="display:flex;align-items:center;gap:6px;margin:0 0 6px"><span class="dashicons dashicons-yes-alt" style="color:#16a34a;font-size:16px;width:16px;height:16px"></span><strong style="font-size:12px;color:#1d1d1f">Professional Grade, Lightweight</strong></div>
          <p style="margin:0;font-size:11px;color:#6e6e73;line-height:1.5">Modular, no bloat. Only active modules load. Built for production.</p>
        </div>
      </div>
      <span class="ver">v<?php echo esc_html(defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '?'); ?> &middot; Lite</span>
    </div>

    <?php if ($show_wizard && $is_welcome && !$step_smart): ?>
    <div class="langa-wb">
      <div style="font-size:28px;line-height:1;flex-shrink:0">&#128075;</div>
      <div>
        <h3>Plugin activated! 4 steps to go live.</h3>
        <p>Follow the setup below. Smart Setup will analyze your site and configure everything automatically.</p>
      </div>
      <a href="<?php echo esc_url($dismiss_url); ?>" class="wb-dismiss" title="Dismiss wizard">&times;</a>
    </div>
    <?php endif; ?>

    <div class="lo-row lo-8-4">
      <div>

        <div class="lo-plan">
          <h3>LANGA Tools Lite</h3>
          <p class="tagline">Free edition. UI/UX module included.</p>
          <p style="font-size:13px;color:#6e6e73;margin:8px 0 0">Want more? <a href="https://tools.langa.tv" target="_blank">LANGA Tools PRO</a></p>
        </div>

        <?php
        // ── Smart Setup state (display-only params, no state change) ──
        $ss_done = isset($_GET['ss_done']) && sanitize_key(wp_unslash($_GET['ss_done'])) === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ss_type = isset($_GET['ss_type']) ? sanitize_key($_GET['ss_type']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ss_applied = isset($_GET['ss_applied']) ? sanitize_text_field(wp_unslash($_GET['ss_applied'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ss_saved_type = get_option('langa_tools_smart_setup_type', '');
        $ss_type_labels = array('blog'=>'Blog / Portfolio','ecommerce'=>'E-commerce','corporate'=>'Corporate / Services');
        $step_smart = ($ss_saved_type !== '');

        // Recalculate steps (now 4)
        $steps_done = (int)$step_license + (int)$step_data + (int)$step_smart + (int)$step_modules;
        $pct = round(($steps_done / 4) * 100);
        ?>

        <?php // Smart Setup result shown inline in Step 3, not as separate banner ?>

        <div class="lo-card">
          <h2>Setup</h2>
          <p class="d">4 steps to a fully configured site.</p>
          <div class="lo-bar"><i class="<?php echo $pct >= 100 ? 'lo-bar-ok' : ($pct > 0 ? 'lo-bar-wip' : 'lo-bar-off'); ?>" style="width:<?php echo max(2,(int)$pct); ?>%"></i></div>

          <?php if ($pct >= 100): ?>
          <div class="lo-allset">
            <div class="check">&#10003;</div>
            <p>All set! Your site is fully configured.</p>
            <p class="sub"><?php echo (int)$active_count; ?> module<?php echo $active_count !== 1 ? 's' : ''; ?> active · <?php echo esc_html($ss_type_labels[$ss_saved_type] ?? 'Custom'); ?> profile</p>
          </div>
          <?php endif; ?>

          <ul class="lo-steps">
            <!-- Step 1: Setup -->
            <li>
              <div class="lo-ic ic-ok">&#10003;</div>
              <div><div class="sl">LANGA Tools Lite</div><div class="sd">Free edition active. UI/UX module included.</div>
                <div class="sa"><span class="ok">&#10003; Active</span></div>
              </div>
            </li>

            <!-- Step 2: Company data -->
            <li>
              <div class="lo-ic <?php echo esc_attr($step_data ? 'ic-ok' : 'ic-no'); ?>"><?php echo $step_data ? '&#10003;' : '2'; ?></div>
              <div><div class="sl">Company data</div><div class="sd">Legal name, VAT, address — used by Legal, BC, Forms, SEO.</div>
                <div class="sa"><?php echo $step_data ? '<span class="ok">&#10003; Done</span>' : '<a href="'.esc_url($data_url).'">Enter data &rarr;</a>'; ?></div>
              </div>
            </li>

            <!-- Step 3: Smart Setup (integrated) -->
            <li style="<?php echo (!$step_smart) ? 'background:#fffbeb;border-radius:10px;padding:14px 12px;margin:-3px -4px' : ''; ?>">
              <div class="lo-ic <?php echo esc_attr($step_smart ? 'ic-ok' : 'ic-no'); ?>"><?php echo $step_smart ? '&#10003;' : '3'; ?></div>
              <div style="flex:1;min-width:0">
                <div class="sl">Smart Setup <?php if ($step_smart): ?><span style="font-size:11px;font-weight:600;color:#16a34a;background:#dcfce7;padding:1px 8px;border-radius:4px;margin-left:6px"><?php echo esc_html($ss_type_labels[$ss_saved_type] ?? $ss_saved_type); ?></span><?php endif; ?></div>
                <div class="sd">
                  <?php if ($step_smart): ?>
                  Best settings applied for your site type. Cache, SEO, Safer and Legal are pre-configured.
                  <?php if ($ss_done && $ss_type !== ''): ?>
                  <div style="margin-top:4px;font-size:11px;color:#6e6e73">
                    Applied to: <?php echo esc_html(str_replace(',', ', ', get_option('langa_tools_smart_setup_applied', ''))); ?>.
                    <?php if (!$license_valid): ?>
                    Settings saved.
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  <?php else: ?>
                  Tell us what kind of site this is. We'll analyze and configure <strong>Cache, SEO, Safer and Legal</strong> automatically.
                  <?php endif; ?>
                </div>

                <?php if (!$step_smart): ?>
                <!-- Inline Smart Setup form (expanded when not done) -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="langa-ss-form" style="margin:10px 0 0">
                  <input type="hidden" name="action" value="langa_tools_client_smart_setup" />
                  <?php wp_nonce_field('langa_smart_setup', '_langa_ss_nonce'); ?>
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;margin:0 0 10px">
                    <?php
                    $ss_packs = array(
                      'blog' => array('icon'=>'dashicons-edit','color'=>'#0071e3','name'=>'Blog / Portfolio','sub'=>'Posts, images, sliders','conf'=>'Cache 4h · SEO Light · Safer Basic · Legal Showcase'),
                      'ecommerce' => array('icon'=>'dashicons-cart','color'=>'#ea580c','name'=>'E-commerce','sub'=>'Products, cart, payments','conf'=>'Cache 1h · SEO Turbo · Safer Business · Legal E-commerce'),
                      'corporate' => array('icon'=>'dashicons-building','color'=>'#7c3aed','name'=>'Corporate / Services','sub'=>'Business, consulting, booking','conf'=>'Cache 8h · SEO Standard · Safer Business · Legal Services'),
                    );
                    foreach ($ss_packs as $ssk => $ssd): ?>
                    <label style="display:block;border:1px solid #e5e5e7;border-radius:10px;padding:12px 14px;cursor:pointer;background:#fff;transition:all .15s" class="langa-ss-opt" data-conf="<?php echo esc_attr($ssd['conf']); ?>">
                      <div style="display:flex;align-items:center;gap:6px;margin:0 0 4px">
                        <span class="dashicons <?php echo esc_attr($ssd['icon']); ?>" style="font-size:16px;width:16px;height:16px;color:<?php echo esc_attr($ssd['color']); ?>"></span>
                        <input type="radio" name="site_type" value="<?php echo esc_attr($ssk); ?>" style="margin:0">
                        <strong style="font-size:12px"><?php echo esc_html($ssd['name']); ?></strong>
                      </div>
                      <div style="font-size:11px;color:#86868b;margin:0"><?php echo esc_html($ssd['sub']); ?></div>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <div id="langa-ss-conf" style="display:none;font-size:11px;color:#6e6e73;background:#f9fafb;border:1px solid #e5e5e7;border-radius:8px;padding:8px 12px;margin:0 0 10px">
                    <strong>Will configure:</strong> <span id="langa-ss-conf-text"></span>
                  </div>
                  <button type="submit" class="button button-primary" id="langa-ss-btn" style="font-size:13px">
                    Analyze &amp; Configure
                  </button>
                </form>
<?php langtoli_inline_script('(function(){
                  var opts=document.querySelectorAll(\'.langa-ss-opt\');
                  var confBox=document.getElementById(\'langa-ss-conf\');
                  var confTxt=document.getElementById(\'langa-ss-conf-text\');
                  opts.forEach(function(o){
                    o.addEventListener(\'click\',function(){
                      opts.forEach(function(x){x.style.borderColor=\'#e5e5e7\';x.style.background=\'#fff\';});
                      var r=o.querySelector(\'input[type=radio]\');r.checked=true;
                      var c=o.getAttribute(\'data-conf\');
                      o.style.borderColor=o.querySelector(\'.dashicons\').style.color;
                      o.style.background=\'linear-gradient(135deg,rgba(0,0,0,.01),rgba(0,0,0,.02))\';
                      if(confBox&&confTxt){confTxt.textContent=c;confBox.style.display=\'block\';}
                    });
                  });
                  var btn=document.getElementById(\'langa-ss-btn\');
                  if(btn){btn.addEventListener(\'click\',function(e){
                    var sel=document.querySelector(\'input[name="site_type"]:checked\');
                    if(!sel){e.preventDefault();alert(\'Select a site type first.\');return;}
                    if(!confirm(\'Configure all modules for "\'+sel.parentElement.querySelector(\'strong\').textContent+\'"?\\n\\nThis will set optimal Cache, SEO, Safer and Legal settings.\'))e.preventDefault();
                  });}
                })();'); ?>
                <?php else: ?>
                <!-- Already done: compact re-run button -->
                <div class="sa" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                  <span class="ok">&#10003; <?php echo esc_html($ss_type_labels[$ss_saved_type] ?? 'Done'); ?></span>
                  <button type="button" class="button button-small" id="langa-ss-rerun-toggle" style="font-size:11px">Change site type</button>
                </div>
                <!-- Hidden re-run form -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="langa-ss-rerun" style="display:none;margin:10px 0 0">
                  <input type="hidden" name="action" value="langa_tools_client_smart_setup" />
                  <?php wp_nonce_field('langa_smart_setup', '_langa_ss_nonce'); ?>
                  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 8px">
                    <?php foreach (array('blog'=>'Blog / Portfolio','ecommerce'=>'E-commerce','corporate'=>'Corporate / Services') as $ssk => $ssl): ?>
                    <label style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border:1px solid <?php echo ($ss_saved_type===$ssk)?'#22c55e':'#e5e5e7'; ?>;border-radius:8px;cursor:pointer;font-size:12px;background:<?php echo ($ss_saved_type===$ssk)?'#f0fdf4':'#fff'; ?>">
                      <input type="radio" name="site_type" value="<?php echo esc_attr($ssk); ?>" <?php checked($ss_saved_type, $ssk); ?> style="margin:0">
                      <?php echo esc_html($ssl); ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <div style="display:flex;gap:8px;align-items:center">
                    <button type="submit" class="button" id="langa-ss-rerun-btn" style="font-size:12px">Re-apply</button>
                    <span class="description" style="font-size:11px">Overwrites current module settings.</span>
                  </div>
                </form>
<?php langtoli_inline_script('(function(){
                  var t=document.getElementById(\'langa-ss-rerun-toggle\'),f=document.getElementById(\'langa-ss-rerun\');
                  if(t&&f){t.addEventListener(\'click\',function(){f.style.display=f.style.display===\'none\'?\'block\':\'none\';});}
                  var b=document.getElementById(\'langa-ss-rerun-btn\');
                  if(b){b.addEventListener(\'click\',function(e){if(!confirm(\'Re-apply Smart Setup? This will overwrite Cache, SEO, Safer and Legal settings.\'))e.preventDefault();});}
                })();'); ?>
                <?php endif; ?>
              </div>
            </li>

            <!-- Step 4: Enable modules -->
            <li>
              <?php
                if (!$step_license) { $s4_class = 'ic-lk'; $s4_label = '&#128274;'; }
                elseif ($step_modules) { $s4_class = 'ic-ok'; $s4_label = '&#10003;'; }
                else { $s4_class = 'ic-no'; $s4_label = '4'; }
              ?>
              <div class="lo-ic <?php echo esc_attr($s4_class); ?>"><?php echo $s4_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML entity, no user input ?></div>
              <div><div class="sl">Enable modules</div>
                <div class="sd"><?php
                  echo (int)$active_count . ' module' . ($active_count !== 1 ? 's' : '') . ' running.';
                ?></div>
                <div class="sa"><?php
                  echo '<span class="ok">&#10003; '.(int)$active_count.' active</span>';
                ?></div>
              </div>
            </li>
          </ul>

          <?php if (!$wizard_dismissed && $pct < 100 && !$is_welcome): ?>
          <div style="text-align:right;margin-top:8px"><a href="<?php echo esc_url($dismiss_url); ?>" style="font-size:12px;color:#86868b;text-decoration:none">Dismiss setup &times;</a></div>
          <?php endif; ?>
        </div>

        <div class="lo-card" id="langa-overview-modules">
          <h2>Modules <span style="font-size:12px;font-weight:400;color:#86868b">(<?php echo (int)$active_count; ?>/<?php echo (int)$total_count; ?>)</span></h2>
          <p class="d">Click a module for details, pricing and setup instructions.</p>
          <div class="lo-mg langa-module-card-grid">
            <?php foreach ($registry as $key => $mod):
              $is_cfg = function_exists('langa_tools_client_feature_is_enabled')
                ? (bool)langa_tools_client_feature_is_enabled($key)
                : (function_exists('langa_tools_client_feature_is_config_enabled') ? (bool)langa_tools_client_feature_is_config_enabled($key) : false);
              $is_free = !empty($mod['free']);
              $is_on = $is_cfg && ($license_valid || $is_free);
              $m = isset($mod_meta[$key]) ? $mod_meta[$key] : array('icon'=>'dashicons-admin-plugins','tag'=>$mod['desc'],'hint'=>'');
              $mu = admin_url('admin.php?page=' . langa_tools_client_page_slug($key));
              $price = isset($mod_price[$key]) ? $mod_price[$key] : 4.99;
            ?>
            <div class="lo-mc<?php echo esc_attr($is_on ? ' on' : ''); ?>">
              <div class="lo-mc-h" role="button" tabindex="0" aria-expanded="false">
                <span class="dashicons <?php echo esc_attr($m['icon']); ?> lo-mc-i"></span>
                <div class="lo-mc-n">
                  <div class="mc-n">
                    <?php echo esc_html($mod['menu']); ?>
                    <?php if ($is_free): ?>
                      <span class="mc-pr mc-pr-free">FREE</span>
                    <?php elseif ($price > 0): ?>
                      <span class="mc-pr mc-pr-paid">&euro;<?php echo number_format($price, 2); ?>/mo</span>
                    <?php endif; ?>
                  </div>
                  <div class="mc-s">
                    <?php if ($is_on): ?>
                      <span class="mc-d mc-d1"></span>Active
                    <?php else: ?>
                      <span class="mc-d mc-d0"></span>OFF
                    <?php endif; ?>
                  </div>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2 mc-c"></span>
              </div>
              <div class="mc-b"><div class="mc-bi">
                <p class="mc-t"><?php echo esc_html($m['tag']); ?></p>
                <?php if (!empty($m['hint'])): ?><div class="mc-hi"><?php echo esc_html($m['hint']); ?></div><?php endif; ?>
                <div class="mc-a">
                  <?php if ($is_on): ?>
                    <a href="<?php echo esc_url($mu); ?>" class="button button-primary">Go to module &rarr;</a>
                  <?php endif; ?>
                </div>
              </div></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div>
        <div class="lo-side" style="margin-bottom:10px">
          <h3>Status</h3>
          <ul>
            <li>License <span class="lo-b lo-b-ok">LITE</span></li>
            <li>Modules <strong><?php echo (int)$active_count.'/'.(int)$total_count; ?></strong></li>
            <li>SMTP <?php echo $smtp_ok ? '<span class="lo-b lo-b-ok">OK</span>' : '<span class="lo-b lo-b-w">Setup needed</span>'; ?></li>
            <li>Company data <?php echo $has_site_data ? '<span class="lo-b lo-b-ok">OK</span>' : '<span class="lo-b lo-b-w">Missing</span>'; ?></li>
        </div>
        <?php
        // ── Site Health mini widget ──
        $health_modules = array('cache','safer','legal','seo','forms','bc','popup','bridge');
        $ht = 0; $hc = 0; $hat = 0; $htotal_mods = 0; $hinactive = 0;
        foreach ($health_modules as $hm) {
          $is_enabled = function_exists('langa_tools_client_feature_is_config_enabled') && langa_tools_client_feature_is_config_enabled($hm);
          if ($is_enabled) {
            $hd = function_exists('langa_tools_client_module_score') ? langa_tools_client_module_score($hm) : null;
            if (!$hd) continue;
            $ht += $hd['score']; // relative: only enabled
            $hat += (isset($hd['abs_pct']) ? (int)$hd['abs_pct'] : $hd['score']); // absolute: enabled
            $hc++;
          } else {
            $hat += 0; // disabled module = 0% for absolute
            $hinactive++;
          }
          $htotal_mods++;
        }
        if ($hc > 0) {
          $havg = (int)round($ht / $hc); // relative: avg of enabled only
          $haavg = $htotal_mods > 0 ? (int)round($hat / $htotal_mods) : 0; // absolute: avg across ALL
          if ($havg >= 80) $hcol = '#16a34a';
          elseif ($havg >= 50) $hcol = '#f37f0d';
          else $hcol = '#dc2626';
          if ($haavg >= 80) $hacol = '#16a34a';
          elseif ($haavg >= 50) $hacol = '#f37f0d';
          else $hacol = '#dc2626';

          // SVG viewBox: 120 x 70 (2x bigger)
          $gcx=60;$gcy=58;
          // Inner arc (relative, thick)
          $gr1=42;
          $rp=max(0,min(100,$havg))/100;$ra=$rp*180;$rrd=deg2rad(180-$ra);
          $rex=$gcx+cos($rrd)*$gr1;$rey=$gcy-sin($rrd)*$gr1;$rlg=($ra>180)?1:0;
          // Outer arc (absolute, thin)
          $gr2=52;
          $ap=max(0,min(100,$haavg))/100;$aa=$ap*180;$ard=deg2rad(180-$aa);
          $aex=$gcx+cos($ard)*$gr2;$aey=$gcy-sin($ard)*$gr2;$alg=($aa>180)?1:0;
        ?>
        <div class="lo-side" style="text-align:center">
          <h3 style="margin:0 0 6px">Site Health</h3>
          <svg viewBox="0 0 120 70" width="160" height="90" style="margin:0 auto;display:block">
            <!-- Outer track (absolute) -->
            <path d="M 8 58 A 52 52 0 0 1 112 58" fill="none" stroke="#f0f0f0" stroke-width="3" stroke-linecap="round"/>
            <?php if ($haavg > 0): ?>
            <path d="M 8 58 A 52 52 0 <?php echo $alg; ?> 1 <?php echo round($aex,1); ?> <?php echo round($aey,1); ?>" fill="none" stroke="<?php echo esc_attr($hacol); ?>" stroke-width="3" stroke-linecap="round" opacity=".4"/>
            <?php endif; ?>
            <!-- Inner track (relative) -->
            <path d="M 18 58 A 42 42 0 0 1 102 58" fill="none" stroke="#e5e5e7" stroke-width="7" stroke-linecap="round"/>
            <?php if ($havg > 0): ?>
            <path d="M 18 58 A 42 42 0 <?php echo esc_attr($rlg); ?> 1 <?php echo esc_attr(round($rex,1)); ?> <?php echo esc_attr(round($rey,1)); ?>" fill="none" stroke="<?php echo esc_attr($hcol); ?>" stroke-width="7" stroke-linecap="round"/>
            <?php endif; ?>
            <text x="60" y="56" text-anchor="middle" font-size="22" font-weight="700" fill="<?php echo esc_attr($hcol); ?>"><?php echo $havg; ?></text>
          </svg>
          <div style="font-size:14px;font-weight:700;color:<?php echo esc_attr($hcol); ?>;margin:2px 0 1px"><?php echo $havg; ?>/100</div>
          <div style="font-size:10px;color:#86868b;margin:0 0 2px">pack score</div>
          <?php if ($haavg !== $havg): ?>
          <div style="font-size:11px;color:<?php echo esc_attr($hacol); ?>;font-weight:600;margin:0 0 2px"><?php echo $haavg; ?>/100 total potential</div>
          <?php endif; ?>
          <div style="font-size:10px;color:#6e6e73;margin:0 0 2px"><?php echo (int)$hc; ?>/<?php echo (int)$htotal_mods; ?> modules active</div>
          <?php if ($hinactive > 0): ?>
          <div style="font-size:10px;color:#f37f0d;margin:0 0 6px">+<?php echo (int)$hinactive; ?> inactive</div>
          <?php else: ?>
          <div style="margin:0 0 6px"></div>
          <?php endif; ?>
          <a href="<?php echo esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=test')); ?>" class="button button-small">Full report</a>
        </div>
        <?php } ?>
        <div class="lo-side">
          <h3>Quick links</h3>
          <ul>
            <li><span class="dashicons dashicons-admin-generic"></span> <a href="<?php echo esc_url($general_url); ?>">License &amp; Health</a></li>
            <li><span class="dashicons dashicons-building"></span> <a href="<?php echo esc_url($data_url); ?>">Company data</a></li>
            <li><span class="dashicons dashicons-email"></span> <a href="<?php echo esc_url($endpoint_url); ?>">SMTP / Email</a></li>
            <li><span class="dashicons dashicons-visibility"></span> <a href="<?php echo esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=debug')); ?>">Debug</a></li>
            <li><span class="dashicons dashicons-editor-help"></span> <a href="<?php echo esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=help')); ?>">Help</a></li>
            <li style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e5e7;">
              <span id="langa-translate-slot" style="display:inline;vertical-align:middle;"></span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

<?php langtoli_inline_script('(function(){
    document.querySelectorAll(\'.lo-mc-h\').forEach(function(h){
      h.addEventListener(\'click\',function(){
        var c=h.closest(\'.lo-mc\'),w=c.classList.contains(\'is-e\');
        document.querySelectorAll(\'.lo-mc.is-e\').forEach(function(x){x.classList.remove(\'is-e\');x.querySelector(\'.lo-mc-h\').setAttribute(\'aria-expanded\',\'false\');});
        if(!w){c.classList.add(\'is-e\');h.setAttribute(\'aria-expanded\',\'true\');}
      });
      h.addEventListener(\'keydown\',function(e){if(e.key===\'Enter\'||e.key===\' \'){e.preventDefault();h.click();}});
    });
    var z=document.querySelector(\'.langa-nz\');
    if(z){document.querySelectorAll(\'.langa-ov > .notice,.langa-hero .notice\').forEach(function(n){z.appendChild(n);});}
  })();'); ?>
  <?php
}

function langa_tools_client_handle_save_overview_mimes() {
  if (!current_user_can('manage_options')) wp_die('Not allowed.');
  check_admin_referer('langa_overview_save_mimes', '_langa_mimes_nonce');
  $raw_mimes = isset($_POST['allowed_mimes']) && is_array($_POST['allowed_mimes']) ? array_map('sanitize_text_field', wp_unslash($_POST['allowed_mimes'])) : array();
  $clean_mimes = array();
  $allowed_keys = array('svg','webp','mp4','webm','mov','mp3','ogg','wav','flac','ico','ai','eps','psd','dwg','dxf','zip','csv','json','woff','woff2','otf','ttf');
  foreach ($allowed_keys as $k) { if (!empty($raw_mimes[$k])) $clean_mimes[$k] = 1; }
  update_option('langa_tools_client_allowed_mimes', $clean_mimes);
  wp_safe_redirect(admin_url('admin.php?page=langa-tools-client&mimes_saved=1'));
  exit;
}

/**
 * Smart Setup — applies best packs to all modules based on site type.
 */
function langa_tools_client_handle_smart_setup() {
  if (!current_user_can('manage_options')) wp_die('Not allowed.');
  check_admin_referer('langa_smart_setup', '_langa_ss_nonce');

  $site_type = isset($_POST['site_type']) ? sanitize_key((string)$_POST['site_type']) : '';
  if (!in_array($site_type, array('blog','ecommerce','corporate'), true)) {
    wp_safe_redirect(admin_url('admin.php?page=langa-tools-client&ss_error=1'));
    exit;
  }

  $applied = array('UI/UX');

  // Save global site type
  update_option('langa_tools_smart_setup_type', $site_type, false);
  update_option('langa_tools_smart_setup_done', 1, false);

  $applied_str = implode(',', $applied);
  wp_safe_redirect(admin_url('admin.php?page=langa-tools-client&ss_done=1&ss_type=' . urlencode($site_type) . '&ss_applied=' . urlencode($applied_str)));
  exit;
}

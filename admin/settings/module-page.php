<?php
if (!defined('ABSPATH')) exit;

function langa_tools_client_module_page($module) {
  if (!current_user_can('manage_options')) wp_die('Not allowed');
  _langa_tools_client_module_page_inner($module);
}

function _langa_tools_client_module_page_inner($module) {

  $features = langa_tools_client_features_registry();
  if (empty($features[$module])) {
    echo '<div class="wrap"><h1>Unknown module</h1></div>';
    return;
  }
  $f = $features[$module];

  // LITE: PRO modules show upsell page
// LITE: PRO modules → rich teaser with blurred panels + feature cards
  if (defined('LANGA_TOOLS_IS_LITE') && LANGA_TOOLS_IS_LITE && !empty($f['pro'])) {
    $teasers = array(
      'safer' => array(
        'tabs'  => array('Hardening', 'Ghost Mode', 'Firewall', 'Headers'),
        'features' => array(
          array('icon'=>'dashicons-shield-alt','title'=>'One-click hardening','desc'=>'Disable XML-RPC, file editing, REST user enumeration, and 15+ attack vectors.'),
          array('icon'=>'dashicons-hidden','title'=>'Ghost Mode','desc'=>'Remove WordPress fingerprints from HTML source. Version strings, generator tags, emoji scripts.'),
          array('icon'=>'dashicons-lock','title'=>'Login protection','desc'=>'Limit login attempts, custom login URL, and brute-force protection.'),
          array('icon'=>'dashicons-admin-site','title'=>'Security headers','desc'=>'CSP, X-Frame-Options, HSTS — one toggle per header, previewed before activation.'),
        ),
        'stats' => '15+ hardening rules · 8 security headers · Login rate limiting',
        'fake_fields' => array(
          array('label'=>'Enable Hardening','type'=>'toggle','checked'=>true),
          array('label'=>'Disable XML-RPC','type'=>'toggle','checked'=>true),
          array('label'=>'Disable File Editor','type'=>'toggle','checked'=>true),
          array('label'=>'Hide WP Version','type'=>'toggle','checked'=>false),
          array('label'=>'Custom Login URL','type'=>'text','value'=>'/my-login'),
          array('label'=>'Login Attempt Limit','type'=>'select','value'=>'5 attempts'),
        ),
      ),
      'seo' => array(
        'tabs'  => array('General', 'Sitemap', 'Schema', 'Meta Tags'),
        'features' => array(
          array('icon'=>'dashicons-search','title'=>'XML Sitemap','desc'=>'Auto-generated sitemap with post types, taxonomies, and custom priorities.'),
          array('icon'=>'dashicons-editor-code','title'=>'Schema markup','desc'=>'Organization, LocalBusiness, BreadcrumbList — structured data for Google.'),
          array('icon'=>'dashicons-admin-page','title'=>'Meta & OG tags','desc'=>'Title templates, meta descriptions, Open Graph and Twitter Cards.'),
          array('icon'=>'dashicons-performance','title'=>'Lightweight','desc'=>'Core SEO features without 100+ settings pages. No bloat.'),
        ),
        'stats' => 'Auto sitemap · Schema.org · OG tags · No bloat',
        'fake_fields' => array(
          array('label'=>'Enable SEO Module','type'=>'toggle','checked'=>true),
          array('label'=>'XML Sitemap','type'=>'toggle','checked'=>true),
          array('label'=>'Title Template','type'=>'text','value'=>'%title% — %sitename%'),
          array('label'=>'Schema Type','type'=>'select','value'=>'LocalBusiness'),
          array('label'=>'Open Graph','type'=>'toggle','checked'=>true),
          array('label'=>'Twitter Cards','type'=>'toggle','checked'=>false),
        ),
      ),
      'cache' => array(
        'tabs'  => array('Browser', 'Purge', 'Presets', 'Performance'),
        'features' => array(
          array('icon'=>'dashicons-performance','title'=>'Browser caching','desc'=>'Cache-Control and Expires headers for static assets. Configurable TTL.'),
          array('icon'=>'dashicons-update','title'=>'Smart purge','desc'=>'Purge individual URLs, post types, or everything.'),
          array('icon'=>'dashicons-admin-settings','title'=>'Preset packs','desc'=>'One-click performance profiles: Starter, Agency, E-commerce.'),
          array('icon'=>'dashicons-dashboard','title'=>'Query cleanup','desc'=>'Remove ?ver= strings, disable emojis, defer scripts.'),
        ),
        'stats' => 'Browser headers · Query cleanup · Cache presets · Defer scripts',
        'fake_fields' => array(
          array('label'=>'Enable Cache Module','type'=>'toggle','checked'=>true),
          array('label'=>'Browser TTL (hours)','type'=>'text','value'=>'720'),
          array('label'=>'Active Preset','type'=>'select','value'=>'Agency Standard'),
          array('label'=>'Remove Query Strings','type'=>'toggle','checked'=>true),
          array('label'=>'Disable Emojis','type'=>'toggle','checked'=>true),
          array('label'=>'Defer Scripts','type'=>'toggle','checked'=>false),
        ),
      ),
      'legal' => array(
        'tabs'  => array('Privacy', 'Cookies', 'Terms', 'Banner'),
        'features' => array(
          array('icon'=>'dashicons-shield','title'=>'GDPR pages','desc'=>'Privacy Policy and Terms auto-fill with company data.'),
          array('icon'=>'dashicons-admin-generic','title'=>'Cookie consent','desc'=>'OPT-IN cookie banner with granular categories. Blocks scripts until consent.'),
          array('icon'=>'dashicons-media-text','title'=>'Auto-fill from data','desc'=>'Company name, address, VAT — pulled from Site Data.'),
          array('icon'=>'dashicons-yes-alt','title'=>'One-click compliance','desc'=>'Generate all required legal pages and go live in under 2 minutes.'),
        ),
        'stats' => 'Privacy + Terms + Cookie pages · OPT-IN banner · GDPR ready',
        'fake_fields' => array(
          array('label'=>'Enable Legal Module','type'=>'toggle','checked'=>true),
          array('label'=>'Privacy Policy Page','type'=>'select','value'=>'Privacy Policy'),
          array('label'=>'Cookie Banner','type'=>'toggle','checked'=>true),
          array('label'=>'Banner Position','type'=>'select','value'=>'Bottom'),
          array('label'=>'Auto-generate Terms','type'=>'toggle','checked'=>false),
          array('label'=>'Company Data Source','type'=>'text','value'=>'Site Data (auto)'),
        ),
      ),
      'forms' => array(
        'tabs'  => array('Forms', 'Style', 'Notifications', 'Spam Protection'),
        'features' => array(
          array('icon'=>'dashicons-email-alt','title'=>'10 form slots','desc'=>'Shortcodes [langaform_1] through [langaform_10]. Each fully configurable.'),
          array('icon'=>'dashicons-art','title'=>'Branded styling','desc'=>'Forms inherit your brand colors automatically.'),
          array('icon'=>'dashicons-bell','title'=>'Email notifications','desc'=>'Instant email on submission. Custom recipient per form.'),
          array('icon'=>'dashicons-shield','title'=>'Spam protection','desc'=>'Honeypot fields + rate limiting. No CAPTCHA needed.'),
        ),
        'stats' => '10 forms · Branded · Email alerts · Honeypot spam protection',
        'fake_fields' => array(
          array('label'=>'Enable Forms Module','type'=>'toggle','checked'=>true),
          array('label'=>'Form 1 Recipient','type'=>'text','value'=>'info@company.com'),
          array('label'=>'Subject Prefix','type'=>'text','value'=>'[Contact Form]'),
          array('label'=>'Honeypot Protection','type'=>'toggle','checked'=>true),
          array('label'=>'Rate Limit','type'=>'select','value'=>'3 per minute'),
          array('label'=>'Custom CSS','type'=>'toggle','checked'=>false),
        ),
      ),
      'bc' => array(
        'tabs'  => array('Main Card', 'Staff Profiles', 'Style', 'Map & QR'),
        'features' => array(
          array('icon'=>'dashicons-id-alt','title'=>'Digital business card','desc'=>'Professional /bc page with company info, social links, and vCard.'),
          array('icon'=>'dashicons-groups','title'=>'Staff profiles','desc'=>'Unlimited team members at /bc/name with photo, role, contacts.'),
          array('icon'=>'dashicons-location-alt','title'=>'Embedded map','desc'=>'Google Maps showing your office location.'),
          array('icon'=>'dashicons-smartphone','title'=>'QR code','desc'=>'Auto-generated QR code for each card. Scan to save contact.'),
        ),
        'stats' => 'Company card · Team profiles · vCard · Map · QR code',
        'fake_fields' => array(
          array('label'=>'Enable BC Module','type'=>'toggle','checked'=>true),
          array('label'=>'Company Name','type'=>'text','value'=>'ACME Corp'),
          array('label'=>'Show Map','type'=>'toggle','checked'=>true),
          array('label'=>'QR Code','type'=>'toggle','checked'=>true),
          array('label'=>'vCard Download','type'=>'toggle','checked'=>true),
          array('label'=>'Staff Section','type'=>'toggle','checked'=>false),
        ),
      ),
      'popup' => array(
        'tabs'  => array('Content', 'Triggers', 'Style', 'Conditions'),
        'features' => array(
          array('icon'=>'dashicons-welcome-widgets-menus','title'=>'Visual popup builder','desc'=>'WYSIWYG content with custom HTML, images, and buttons.'),
          array('icon'=>'dashicons-clock','title'=>'Smart triggers','desc'=>'Exit intent, scroll depth, time delay, page count.'),
          array('icon'=>'dashicons-art','title'=>'Full style control','desc'=>'Overlay opacity, border radius, shadow, position — pixel-perfect.'),
          array('icon'=>'dashicons-chart-bar','title'=>'Zero dependencies','desc'=>'No jQuery, no layout shifts. Loads only when needed.'),
        ),
        'stats' => 'Visual builder · Smart triggers · No jQuery · Performance-first',
        'fake_fields' => array(
          array('label'=>'Enable Popup','type'=>'toggle','checked'=>true),
          array('label'=>'Trigger','type'=>'select','value'=>'Exit Intent'),
          array('label'=>'Delay (seconds)','type'=>'text','value'=>'3'),
          array('label'=>'Show Once Per Session','type'=>'toggle','checked'=>true),
          array('label'=>'Overlay Opacity','type'=>'text','value'=>'0.6'),
          array('label'=>'Border Radius','type'=>'text','value'=>'12px'),
        ),
      ),
      'bridge' => array(
        'tabs'  => array('Events', 'Forwarding', 'Filters', 'Export'),
        'features' => array(
          array('icon'=>'dashicons-chart-area','title'=>'Event logging','desc'=>'Track form submissions, orders, logins, and custom events locally.'),
          array('icon'=>'dashicons-admin-site','title'=>'Remote bridge','desc'=>'Forward events to your agency server for centralized monitoring.'),
          array('icon'=>'dashicons-filter','title'=>'Smart filters','desc'=>'Filter by event type, date range, site. Export as CSV.'),
          array('icon'=>'dashicons-database','title'=>'Local storage','desc'=>'Events stored locally with optional forwarding. Works offline.'),
        ),
        'stats' => 'Local events · Remote bridge · CSV export · Filters',
        'fake_fields' => array(
          array('label'=>'Enable Events','type'=>'toggle','checked'=>true),
          array('label'=>'Log Form Submissions','type'=>'toggle','checked'=>true),
          array('label'=>'Forward To','type'=>'text','value'=>'https://your-server.com'),
          array('label'=>'Retention Days','type'=>'select','value'=>'90 days'),
        ),
      ),
      'ai' => array(
        'tabs'  => array('Providers', 'API Keys', 'Usage', 'Settings'),
        'features' => array(
          array('icon'=>'dashicons-format-chat','title'=>'Multi-provider hub','desc'=>'Manage OpenAI, Anthropic, and Google AI keys in one place.'),
          array('icon'=>'dashicons-admin-network','title'=>'Centralized keys','desc'=>'Set once, use everywhere across modules.'),
          array('icon'=>'dashicons-chart-line','title'=>'Usage monitoring','desc'=>'Track API calls and costs per provider.'),
          array('icon'=>'dashicons-admin-plugins','title'=>'Ready for the future','desc'=>'AI features coming to Forms, SEO, and Legal modules.'),
        ),
        'stats' => 'OpenAI · Anthropic · Google AI · Centralized management',
        'fake_fields' => array(
          array('label'=>'Enable AI Module','type'=>'toggle','checked'=>false),
          array('label'=>'OpenAI Key','type'=>'text','value'=>'sk-••••••••••••'),
          array('label'=>'Anthropic Key','type'=>'text','value'=>'(not set)'),
          array('label'=>'Default Provider','type'=>'select','value'=>'OpenAI'),
        ),
      ),
    );
    $t = isset($teasers[$module]) ? $teasers[$module] : array(
      'tabs' => array('General', 'Settings', 'Advanced'),
      'features' => array(
        array('icon'=>'dashicons-admin-generic','title'=>'Full configuration','desc'=>'Every setting you need to customize this module.'),
        array('icon'=>'dashicons-yes-alt','title'=>'Production ready','desc'=>'Built for real sites. Tested, stable, lightweight.'),
      ),
      'stats' => 'Professional grade module',
      'fake_fields' => array(
        array('label'=>'Enable module','type'=>'toggle','checked'=>true),
        array('label'=>'Primary setting','type'=>'text','value'=>'configured...'),
      ),
    );
    $mod_name = esc_html(isset($f['menu']) ? $f['menu'] : $module);
    echo '<div class="wrap">';
    echo '<h1>'.esc_html($f['title']).' <span style="background:#f37f0d;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;vertical-align:middle">PRO</span></h1>';

    // Single container for consistent alignment
    echo '<div style="max-width:965px;margin:12px 0 0">';

    // Promo banner top
    echo '<div style="background:linear-gradient(135deg,#1d1d1f 0%,#333 100%);border-radius:12px;padding:20px 24px;margin:0 0 16px;display:flex;align-items:center;gap:16px">';
    echo '<div style="flex:1"><div style="font-size:18px;font-weight:700;color:#fff;margin:0 0 4px"><span style="color:#f37f0d">PRO</span> Module — '.$mod_name.'</div>';
    echo '<div style="font-size:13px;color:rgba(255,255,255,.65);line-height:1.5">This module is included with Tools PRO. Preview the features below, then upgrade to unlock everything.</div></div>';
    echo '<a href="https://tools.langa.tv/#pricing" target="_blank" style="flex-shrink:0;display:inline-block;padding:10px 24px;background:#f37f0d;color:#fff;font-weight:700;font-size:13px;border-radius:8px;text-decoration:none;white-space:nowrap">Get PRO License &rarr;</a>';
    echo '</div>';

    // Feature cards 4 columns
    echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:0 0 14px">';
    foreach ($t['features'] as $feat) {
      echo '<div style="background:#fff;border:1px solid #e5e5e7;border-radius:10px;padding:14px 16px;border-left:3px solid #f37f0d;display:flex;flex-direction:column;box-sizing:border-box">';
      echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 5px"><span class="dashicons '.esc_attr($feat['icon']).'" style="font-size:16px;width:16px;height:16px;color:#f37f0d"></span><strong style="font-size:13px;color:#1d1d1f">'.esc_html($feat['title']).'</strong></div>';
      echo '<p style="margin:0;font-size:11px;color:#6e6e73;line-height:1.45;flex:1">'.esc_html($feat['desc']).'</p></div>';
    }
    echo '</div>';

    // Stats bar
    echo '<div style="margin:0 0 16px;padding:8px 16px;background:#fef3e2;border:1px solid #fcd9b1;border-radius:8px;font-size:12px;color:#7c3d06;text-align:center;font-weight:600">'.esc_html($t['stats']).'</div>';

    // Blurred panel
    echo '<div style="position:relative;margin:0 0 16px;border:1px solid #e5e5e7;border-radius:12px;overflow:hidden;background:#fff">';
    echo '<div style="display:flex;gap:0;border-bottom:1px solid #e5e5e7;background:#fafafa">';
    foreach ($t['tabs'] as $i => $tab) {
      echo $i===0 ? '<span style="padding:10px 20px;font-size:13px;font-weight:600;color:#1d1d1f;border-bottom:2px solid #f37f0d;margin-bottom:-1px;background:#fff">'.esc_html($tab).'</span>' : '<span style="padding:10px 20px;font-size:13px;color:#b0b0b0">'.esc_html($tab).'</span>';
    }
    echo '</div>';
    echo '<div style="position:relative;min-height:300px"><div style="filter:blur(3px);pointer-events:none;user-select:none;opacity:.4;padding:20px 24px">';
    echo '<table class="form-table" style="margin:0"><tbody>';
    foreach (($t['fake_fields'] ?? array()) as $ff) {
      echo '<tr><th scope="row" style="width:200px;padding:12px 10px 12px 0;font-size:13px">'.esc_html($ff['label']).'</th><td style="padding:12px 0">';
      if ($ff['type']==='toggle') echo '<label><input type="checkbox" disabled '.(!empty($ff['checked'])?'checked':'').'> Enabled</label>';
      elseif ($ff['type']==='select') echo '<select disabled style="min-width:200px"><option>'.esc_html($ff['value']??'').'</option></select>';
      else echo '<input type="text" disabled class="regular-text" value="'.esc_attr($ff['value']??'').'" style="max-width:360px">';
      echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    // CTA overlay
    echo '<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;background:rgba(255,255,255,.15)">';
    echo '<a href="https://tools.langa.tv/#pricing" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:14px 36px;background:#1d1d1f;color:#fff;font-weight:700;font-size:15px;border-radius:10px;text-decoration:none;box-shadow:0 4px 20px rgba(0,0,0,.25)"><span class="dashicons dashicons-unlock" style="font-size:18px;width:18px;height:18px"></span> Unlock '.$mod_name.'</a>';
    echo '<p style="margin:10px 0 0;font-size:12px;color:#6e6e73">From &euro;4.99/month &middot; All modules &euro;19.90/month &middot; Cancel anytime</p>';
    echo '</div></div></div>';

    // Bottom promo strip
    echo '<div style="margin:0 0 16px;padding:14px 20px;background:linear-gradient(90deg,#f37f0d,#e06800);border-radius:10px;display:flex;align-items:center;justify-content:space-between;gap:16px">';
    echo '<div><div style="color:#fff;font-weight:700;font-size:14px">Ready to go PRO?</div><div style="color:rgba(255,255,255,.8);font-size:12px;margin-top:2px">One license unlocks all modules. No subscriptions required for yearly plans.</div></div>';
    echo '<a href="https://tools.langa.tv/#pricing" target="_blank" style="flex-shrink:0;display:inline-block;padding:8px 20px;background:#fff;color:#f37f0d;font-weight:700;font-size:13px;border-radius:8px;text-decoration:none">View Plans &rarr;</a>';
    echo '</div>';

    echo '</div>'; // end max-width container
    echo '</div>'; // end wrap
    return;
  }


  // UI lock should use the raw config enabled state, not Bridge gating.
  $enabled = function_exists('langa_tools_client_feature_is_config_enabled')
    ? langa_tools_client_feature_is_config_enabled($module)
    : langa_tools_client_feature_is_enabled($module);

  echo '<div class="wrap">';
  echo '<h1>'.esc_html($f['title']).'</h1>';

  // Module value proposition data
  $mod_vp = array(
    'adminux' => array('icon'=>'dashicons-admin-customizer','color'=>'#f37f0d','msg'=>'Your brand on every site you deliver. Custom Login, Credits, and Maintenance ensure your identity stays visible — during development, after handoff, and beyond.'),
    'forms'   => array('icon'=>'dashicons-email-alt','color'=>'#0071e3','msg'=>'Professional forms in 30 seconds. Branded with your colors, connected to your recipients, tracked in Events.'),
    'legal'   => array('icon'=>'dashicons-shield-alt','color'=>'#7c3aed','msg'=>'GDPR compliance, pre-configured. Privacy, Cookie, and Terms pages auto-fill with your client\'s company data.'),
    'bc'      => array('icon'=>'dashicons-id-alt','color'=>'#0891b2','msg'=>'A digital business card that impresses. vCard download, team profiles, map, QR code — all from centralized data.'),
    'safer'   => array('icon'=>'dashicons-lock','color'=>'#dc2626','msg'=>'Harden WordPress without breaking things. Each protection is independent, reversible, and tested.'),
    'seo'     => array('icon'=>'dashicons-search','color'=>'#16a34a','msg'=>'Core SEO without the bloat. Sitemap, meta, OG, Schema — everything most sites need, nothing they don\'t.'),
    'cache'   => array('icon'=>'dashicons-performance','color'=>'#ea580c','msg'=>'Performance that matters. Browser headers, query string cleanup, smart purge. Zero configuration complexity.'),
    'bridge'  => array('icon'=>'dashicons-networking','color'=>'#2563eb','msg'=>'Site intelligence, automatic. Page speed, errors, forms, logins — all tracked locally with optional Bridge sync.'),
    'ai'      => array('icon'=>'dashicons-format-chat','color'=>'#8b5cf6','msg'=>'AI-ready infrastructure. Manage provider keys centrally, shared across modules.'),
    'popup'   => array('icon'=>'dashicons-welcome-widgets-menus','color'=>'#d946ef','msg'=>'Popups that perform. Smart triggers, lightweight code, full style control. No jQuery, no layout shifts.'),
  );
  $vp = isset($mod_vp[$module]) ? $mod_vp[$module] : null;
  $vp_color = $vp ? $vp['color'] : '#f37f0d';

  if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
  }
  if (isset($_GET['module_enabled']) && $_GET['module_enabled'] === '1') {
    echo '<div class="notice notice-success is-dismissible"><p><strong>'.esc_html($f['title'] ?? $module).'</strong> enabled. Configure settings below.</p></div>';
  }
  if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    echo '<div class="notice notice-warning is-dismissible"><p>All settings for <strong>'.esc_html($f['title'] ?? $module).'</strong> have been reset.</p></div>';
  }

  echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">';
  echo '<input type="hidden" name="action" value="langa_tools_client_save_module" />';
  echo '<input type="hidden" name="module" value="'.esc_attr($module).'" />';
  wp_nonce_field('langa_tools_client_save_module_' . $module);

  $settings_modules_url = admin_url('admin.php?page=langa-tools-client-settings&tab=general#langa-modules');

  $license_real_invalid = function_exists('langa_tools_client_license_is_valid') && !langa_tools_client_license_is_valid();
  $dev_bypass = langa_tools_client_dev_bypass_active();
  $is_free_module = !empty($f['free']);
  $license_invalid = $license_real_invalid && !$dev_bypass && !$is_free_module;

  $lm = get_option('langa_tools_licensed_modules', array());
  $mod_lic = (is_array($lm) && isset($lm[$module])) ? $lm[$module] : null;
  $mod_licensed = $mod_lic && !empty($mod_lic['active']);
  $mod_expires = ($mod_lic && !empty($mod_lic['expires'])) ? $mod_lic['expires'] : null;
  $mod_expired = $mod_expires && strtotime($mod_expires) < time();

  $can_toggle = !$license_invalid || $is_free_module;
  $is_on = $enabled;
  if ($is_free_module) $is_on = true;

  echo '<div class="langa-module-enable" style="display:flex;gap:20px;align-items:stretch;flex-wrap:wrap;padding:16px 20px;border-left:4px solid '.esc_attr($vp_color).';background:linear-gradient(135deg,'.esc_attr($vp_color).'06,transparent);border:1px solid '.esc_attr($vp_color).'18;border-left:4px solid '.esc_attr($vp_color).';border-radius:0 10px 10px 0">';

    echo '<div style="flex:1;min-width:280px">';
      echo '<div style="display:flex;align-items:center;gap:10px;margin:0 0 6px">';
        if ($vp) echo '<span class="dashicons '.esc_attr($vp['icon']).'" style="color:'.esc_attr($vp_color).';font-size:22px;width:22px;height:22px;flex-shrink:0"></span>';
        echo '<span style="font-size:16px;font-weight:700;color:#1d1d1f">'.esc_html($f['title']).'</span>';
      echo '</div>';
      if ($vp) {
        echo '<p style="margin:0;font-size:13px;color:#6e6e73;line-height:1.5">'.wp_kses_post($vp['msg']).'</p>';
      }
    echo '</div>';

    echo '<div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;justify-content:center;min-width:200px">';

      echo '<div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#86868b">';
        echo '<span style="font-weight:600">License:</span>';
        if ($is_free_module) {
          echo '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#6e6e73">FREE</span>';
        } elseif ($license_invalid) {
          echo '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#b71c1c">INVALID</span>';
        } elseif ($mod_expired) {
          echo '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#b71c1c">EXPIRED</span>';
        } elseif ($mod_licensed && $mod_expires) {
          echo '<span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#6e6e73">OK → '.esc_html(date('d/m/Y', strtotime($mod_expires))).'</span>';
        } elseif ($mod_licensed) {
          echo '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#6e6e73">OK ∞</span>';
        } elseif ($dev_bypass) {
          echo '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#7c3d06">DEV</span>';
        } else {
          echo '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;background:#f0f0f2;color:#b71c1c">—</span>';
        }
      echo '</div>';

      echo '<div style="display:flex;align-items:center;gap:6px">';
        $sw_bg = $is_on ? '#16a34a' : '#d4d4d8';
        $sw_dot = $is_on ? 'calc(100% - 17px)' : '2px';
        if ($can_toggle && !$is_free_module) {
          $toggle_url = wp_nonce_url(
            admin_url('admin-post.php?action=langa_tools_client_save_module&module=' . urlencode($module) . '&new_active=' . ($enabled ? '0' : '1')),
            'langa_tools_client_save_module_' . $module
          );
          echo '<a href="'.esc_url($toggle_url).'" style="display:inline-block;position:relative;width:38px;height:22px;border-radius:11px;background:'.$sw_bg.';cursor:pointer;vertical-align:middle;text-decoration:none" title="Click to '.($is_on?'disable':'enable').'">';
          echo '<span style="position:absolute;top:3px;left:'.$sw_dot.';width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25)"></span></a>';
        } else {
          echo '<span style="display:inline-block;position:relative;width:38px;height:22px;border-radius:11px;background:'.$sw_bg.';cursor:default;vertical-align:middle">';
          echo '<span style="position:absolute;top:3px;left:'.$sw_dot.';width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25)"></span></span>';
        }
        echo '<span style="font-size:12px;font-weight:700;color:'.($is_on?'#16a34a':'#9ca3af').'">'.($is_on?'ON':'OFF').'</span>';
      echo '</div>';

      echo '<div style="display:flex;align-items:center;gap:10px">';
        echo '<a href="'.esc_url($settings_modules_url).'" style="font-size:11px;color:#6e6e73;text-decoration:none">Go to Modules</a>';
        $reset_url = wp_nonce_url(
          admin_url('admin-post.php?action=langa_tools_client_reset_module&module=' . urlencode($module)),
          'langa_tools_client_reset_module_' . $module
        );
        echo '<a href="'.esc_url($reset_url).'" onclick="var v=prompt(this.getAttribute(&apos;data-msg&apos;));if(v!==&apos;DELETE&apos;){event.preventDefault();return false;}" data-msg="This will DELETE all settings for '.esc_attr($f['menu'] ?? $module).'. Type DELETE to confirm:" style="font-size:11px;color:#b71c1c;font-weight:600;text-decoration:none;cursor:pointer">Reset this module</a>';
      echo '</div>';

    echo '</div>';

  echo '</div>';

  $locked = !$enabled;

  // License kill-switch: force lock ALL modules when license is invalid
  if ($license_invalid) {
    $locked = true;
  }

  if ($locked && $license_invalid) {
    echo '<div class="langa-locked-hint" style="display:flex;align-items:center;gap:8px;padding:10px 14px;margin:0 0 12px;border:1px solid #f5c6cb;border-left:4px solid #b71c1c;background:#fce4ec;color:#b71c1c;border-radius:8px;font-size:13px;"><span class="dashicons dashicons-lock"></span> License invalid — all modules and features are locked. <a href="' . esc_url(admin_url('admin.php?page=langa-tools-client-settings&tab=general')) . '">Verify license</a></div>';
  }

  // Disable inputs when module is OFF.
  if (!empty($locked)) { echo '<fieldset disabled>'; }

  echo '<div class="langa-card">';
  if (!empty($locked)) { echo '<div class="langa-lock-overlay" aria-hidden="true"><span class="dashicons dashicons-lock"></span></div>'; }
  echo '<table class="form-table langa-module-wrap" role="presentation">';

  // NOTE:
  // This file lives in /admin/settings/.
  // Module UIs live in /admin/modules/.
  // Use absolute plugin path to avoid path bugs after refactors.
  $admin_modules_dir = LANGA_TOOLS_CLIENT_PATH . 'admin/modules/';

  // BRIDGE
  if ($module === 'bridge') {
    if (is_readable($admin_modules_dir . 'bridge-ui.php')) require_once $admin_modules_dir . 'bridge-ui.php';
    langa_tools_client_render_module_bridge($module, $enabled, $locked, $f);
  }
  if ($module === 'safer') {
    if (is_readable($admin_modules_dir . 'safer-ui.php')) require_once $admin_modules_dir . 'safer-ui.php';
    langa_tools_client_render_module_safer($module, $enabled, $locked, $f);
  }
  if ($module === 'forms') {
    if (is_readable($admin_modules_dir . 'forms-ui.php')) require_once $admin_modules_dir . 'forms-ui.php';
    langa_tools_client_render_module_forms($module, $enabled, $locked, $f);
  }
  if ($module === 'cache') {
    if (is_readable($admin_modules_dir . 'cache-ui.php')) require_once $admin_modules_dir . 'cache-ui.php';
    langa_tools_client_render_module_cache($module, $enabled, $locked, $f);
  }
if ($module === 'bc') {
    $bc = get_option('langa_tools_bc_settings', array());
    if (!is_array($bc)) $bc = array();

    // UI renderer lives in /admin/modules/bc-ui.php
    if (!function_exists('langa_tools_client_bc_admin_render')) {
      $bc_ui_path = LANGA_TOOLS_CLIENT_PATH . 'admin/modules/bc-ui.php';
      if (is_readable($bc_ui_path)) require_once $bc_ui_path;
    }

    echo '<tr><th scope="row">Business Card</th><td>';
    if (function_exists('langa_tools_client_bc_admin_render')) {
      langa_tools_client_bc_admin_render($bc);
    } else {
      echo '<p class="description">BC UI not found (admin/modules/bc-ui.php).</p>';
    }
    echo '</td></tr>';
  }

  // AI
  if ($module === 'ai') {
    $ai = get_option('langa_tools_ai_settings', array());
    if (!is_array($ai)) $ai = array();

    if (!function_exists('langa_tools_client_ai_admin_render')) {
      $ai_ui_path = LANGA_TOOLS_CLIENT_PATH . 'admin/modules/ai-ui.php';
      if (is_readable($ai_ui_path)) require_once $ai_ui_path;
    }

    echo '<tr><th scope="row">AI Console</th><td>';
    if (function_exists('langa_tools_client_ai_admin_render')) {
      langa_tools_client_ai_admin_render($ai);
    } else {
      echo '<p class="description">AI UI not found (admin/modules/ai-ui.php).</p>';
    }
    echo '</td></tr>';
  }
  
  // LEGAL
  if ($module === 'legal') {
    if (is_readable($admin_modules_dir . 'legal-ui.php')) require_once $admin_modules_dir . 'legal-ui.php';
    langa_tools_client_render_module_legal($module, $enabled, $locked, $f);
  }

  // SEO
  if ($module === 'seo') {
    if (is_readable($admin_modules_dir . 'seo-ui.php')) require_once $admin_modules_dir . 'seo-ui.php';
    langa_tools_client_render_module_seo($module, $enabled, $locked, $f);
  }
  if ($module === 'adminux') {
    require_once $admin_modules_dir . 'ui-ux-ui.php';
    langa_tools_client_render_module_uiux($module, $enabled, $locked, $f);
  }

  // POPUP
  if ($module === 'popup') {
    if (is_readable($admin_modules_dir . 'popup-ui.php')) require_once $admin_modules_dir . 'popup-ui.php';
    langa_tools_client_render_module_popup($module, $enabled, $locked, $f);
  }


  echo '</table>';
  echo '</div>';

  // (Effects UI rendered in the main module card above.)

  if (!empty($locked)) { echo '</fieldset>'; }
  echo '</div>'; // lock-wrap

  // Keep the Save button clickable even when the module is Disabled.
  // Inputs are already locked via <fieldset disabled> + overlay.
  // This avoids UX dead-ends and prevents false negatives when the enabled state is misdetected.
  submit_button('Save Module', 'primary', 'save_module', true);

  echo '</form>';
  echo '</div>';
}

/**
 * Calculate a module performance/configuration score.
 * Returns array with score, label, details, checks (named with on/off + suggestion).
 */
function langa_tools_client_module_score($module) {
  switch ($module) {

    case 'cache':
      $opt = get_option('langa_tools_cache_settings', array());
      if (!is_array($opt)) $opt = array();
      $c = isset($opt['cache']) && is_array($opt['cache']) ? $opt['cache'] : array();
      $f = isset($opt['file']) && is_array($opt['file']) ? $opt['file'] : array();
      $m = isset($opt['media']) && is_array($opt['media']) ? $opt['media'] : array();
      $p = isset($opt['preload']) && is_array($opt['preload']) ? $opt['preload'] : array();
      $pack = isset($opt['pack']) ? (string)$opt['pack'] : '';
      $delay_req = in_array($pack, array('corporate','aggressive'), true);

      $checks = array(
        array('Browser cache', !empty($c['browser_headers']), 'Enable browser cache in Settings', 'settings', true),
        array('Remove ?ver=', !empty($f['remove_qs']), 'Enable version removal in Settings', 'settings', true),
        array('Defer JS', !empty($f['defer_js']), 'Enable Defer JS in Settings', 'settings', true),
        array('Delay JS', !empty($f['delay_js']), 'Enable Delay JS in Settings', 'settings', $delay_req),
        array('Remove emoji', !empty($m['disable_emojis']), 'Remove emoji scripts in Settings', 'settings', true),
        array('Lazy images', !empty($m['lazy_images']), 'Enable lazy images in Settings', 'settings', true),
        array('Lazy iframes', !empty($m['lazy_iframes']), 'Enable lazy iframes in Settings', 'settings', true),
        array('DNS Prefetch', !empty($p['dns_prefetch']), 'Add external domains in Settings', 'settings', false),
        array('Preconnect', !empty($p['preconnect']), 'Add preconnect origins in Settings', 'settings', false),
      );
      return langa_tools_client_score_from_checks($checks, 'optimizations active', 'performance', 9);

    case 'safer':
      $opt = get_option('langa_tools_safer_settings', array());
      if (!is_array($opt)) $opt = array();
      $pack = isset($opt['pack']) ? (string)$opt['pack'] : '';
      $is_biz = in_array($pack, array('business','fortress'), true);
      $is_fort = ($pack === 'fortress');

      $checks = array(
        array('Hide WP version', !empty($opt['hide_wp_version']), 'Enable in Hardening', 'hardening', true),
        array('Hide fingerprints', !empty($opt['hide_wp_fingerprints']), 'Enable in Hardening', 'hardening', true),
        array('Disable XML-RPC', !empty($opt['disable_xmlrpc']), 'Disable XML-RPC in Hardening', 'hardening', true),
        array('Block author enum', !empty($opt['block_author_enum']), 'Block enumeration in Hardening', 'hardening', true),
        array('Disable file editor', !empty($opt['disable_file_editor']), 'Disable editor in Hardening', 'hardening', $is_biz),
        array('Force HTTPS admin', !empty($opt['force_https_admin']), 'Force HTTPS in Hardening', 'hardening', $is_biz),
        array('.htaccess hardening', !empty($opt['htaccess_hardening']), 'Enable .htaccess in Tools', 'tools', $is_fort),
        array('Door-only access', !empty($opt['door_only_access']), 'Enable Door access in Tools', 'tools', $is_fort),
      );
      return langa_tools_client_score_from_checks($checks, 'protections active', 'security', 8);

    case 'legal':
      $opt = get_option('langa_tools_legal_settings', array());
      if (!is_array($opt)) $opt = array();
      $site_type = isset($opt['site_type']) ? (string)$opt['site_type'] : '';
      $needs_terms = in_array($site_type, array('servizi','ecommerce'), true);
      $needs_impressum = ($site_type === 'ecommerce');

      $checks = array(
        array('Legal pack selected', !empty($site_type), 'Choose a Legal Pack in Overview', 'overview', true),
        array('Cookie banner', !empty($opt['banner_enabled']), 'Enable cookie banner in Overview', 'overview', true),
        array('Privacy page', !empty($opt['privacy_page_id']) && get_post_status((int)$opt['privacy_page_id']), 'Generate legal pages (apply Legal Pack)', 'overview', true),
        array('Cookie page', !empty($opt['cookie_page_id']) && get_post_status((int)$opt['cookie_page_id']), 'Generate legal pages (apply Legal Pack)', 'overview', true),
        array('Terms page', !empty($opt['terms_page_id']) && get_post_status((int)$opt['terms_page_id']), 'Generate Terms page (apply Legal Pack)', 'overview', $needs_terms),
        array('Impressum page', !empty($opt['impressum_page_id']) && get_post_status((int)$opt['impressum_page_id']), 'Generate Impressum page (apply Legal Pack)', 'overview', $needs_impressum),
      );
      return langa_tools_client_score_from_checks($checks, 'compliance items', 'compliance', 6);

    case 'seo':
      $opt = get_option('langa_tools_seo_settings', array());
      if (!is_array($opt)) $opt = array();
      $sft = isset($opt['features']) && is_array($opt['features']) ? $opt['features'] : array();
      $mode = isset($opt['mode']) ? (string)$opt['mode'] : 'light';

      if ($mode === 'noindex') {
        $checks = array(
          array('Noindex mode active', !empty($sft['robots_controls']), 'Apply Noindex SEO Pack', 'features', true),
        );
        return langa_tools_client_score_from_checks($checks, 'noindex configured', 'seo', 8);
      }

      $is_std = in_array($mode, array('standard','turbo'), true);
      $is_turbo = ($mode === 'turbo');
      $checks = array(
        array('Titles & Meta', !empty($sft['titles_meta']), 'Enable Titles & Meta in Settings', 'settings', true),
        array('XML Sitemap', !empty($sft['xml_sitemap']), 'Enable sitemap in Sitemap tab', 'sitemap', true),
        array('Canonical URLs', !empty($sft['canonical']), 'Enable Canonical in Settings', 'settings', true),
        array('Schema markup', !empty($sft['schema']), 'Enable Schema in Settings', 'settings', true),
        array('Breadcrumbs', !empty($sft['breadcrumbs']), 'Enable Breadcrumbs in Settings', 'settings', true),
        array('OpenGraph', !empty($sft['opengraph']), 'Enable OpenGraph in Settings', 'settings', $is_std),
        array('Twitter Cards', !empty($sft['twitter_cards']), 'Enable Twitter Cards in Settings', 'settings', $is_std),
        array('IndexNow', !empty($sft['indexnow']), 'Enable IndexNow in Settings', 'settings', $is_turbo),
      );
      return langa_tools_client_score_from_checks($checks, 'SEO features active', 'seo', 8);

    case 'forms':
      $opt = get_option('langa_tools_forms_settings', array());
      if (!is_array($opt)) $opt = array();
      $checks = array(
        array('Forms enabled', !empty($opt['enabled']), 'Enable forms in Overview', 'overview', true),
        array('Recipient set', !empty($opt['recipient']), 'Set email recipient in Overview', 'overview', true),
      );
      return langa_tools_client_score_from_checks($checks, 'essentials configured', 'forms');

    case 'bc':
      $opt = get_option('langa_tools_bc_settings', array());
      if (!is_array($opt)) $opt = array();
      $main = isset($opt['main']) && is_array($opt['main']) ? $opt['main'] : array();
      $staff = isset($opt['staff']) && is_array($opt['staff']) ? $opt['staff'] : array();
      // BC is "enabled" if the module toggle is on (checked via feature_is_config_enabled before we get here)
      $has_name = !empty($main['company_name']) && $main['company_name'] !== get_bloginfo('name');
      $has_url = !empty($main['website']) && $main['website'] !== home_url('/');
      $has_staff = !empty($staff) && count($staff) > 0;
      $checks = array(
        array('Company name set', $has_name, 'Enter company name in Main tab', 'main', true),
        array('Company URL set', $has_url, 'Enter company URL in Main tab', 'main', true),
        array('Staff section', $has_staff, 'Add staff members in Staff tab', 'staff', false),
      );
      return langa_tools_client_score_from_checks($checks, 'configured', 'branding', 3);

    case 'popup':
      $opt = get_option('langa_tools_popup_settings', array());
      if (!is_array($opt)) $opt = array();
      $popups = isset($opt['popups']) && is_array($opt['popups']) ? $opt['popups'] : array();
      $has_popups = !empty($popups);
      $has_active = false;
      foreach ($popups as $pp) { if (($pp['status'] ?? '') === 'active') { $has_active = true; break; } }
      $checks = array(
        array('Popup created', $has_popups, 'Create a popup in Popups tab', 'popups', true),
        array('Popup active', $has_active, 'Activate at least one popup', 'popups', true),
      );
      return langa_tools_client_score_from_checks($checks, 'configured', 'engagement', 2);

    case 'bridge':
      $opt = get_option('langa_tools_bridge_settings', array());
      if (!is_array($opt)) $opt = array();
      $ev = isset($opt['events']) && is_array($opt['events']) ? $opt['events'] : array();
      $checks = array(
        array('Form tracking', !empty($ev['forms']), 'Enable form tracking in Tracking tab', 'tracking', true),
        array('404 tracking', !empty($ev['e404']), 'Enable 404 tracking in Tracking tab', 'tracking', false),
        array('Page speed (TTFB)', !empty($ev['perf']), 'Enable TTFB tracking in Tracking tab', 'tracking', false),
        array('Login tracking', !empty($ev['logins']), 'Enable login tracking in Tracking tab', 'tracking', false),
      );
      return langa_tools_client_score_from_checks($checks, 'tracking active', 'analytics', 4);

    default:
      return null;
  }
}

function langa_tools_client_score_from_checks($checks, $label_suffix, $type, $absolute_max = 0) {
  $on = 0;
  $total_required = 0;
  $on_required = 0;
  $passed = array();
  $suggestions = array();
  foreach ($checks as $ch) {
    // Format: [name, is_on, fix_text, tab, required_for_pack]
    $is_required = isset($ch[4]) ? (bool)$ch[4] : true; // default = required
    if ($ch[1]) {
      $on++;
      $passed[] = $ch[0];
      if ($is_required) $on_required++;
    } else {
      $suggestions[] = array('name' => $ch[0], 'fix' => $ch[2], 'tab' => $ch[3], 'required' => $is_required);
    }
    if ($is_required) $total_required++;
  }
  // Relative score: based on required checks only
  $score = $total_required > 0 ? (int)round($on_required / $total_required * 100) : 0;
  $label = $on_required . '/' . $total_required . ' ' . $label_suffix;
  // Absolute: how many features are on out of the total possible for this module
  $abs_max = $absolute_max > 0 ? $absolute_max : count($checks);
  $abs_pct = $abs_max > 0 ? (int)round($on / $abs_max * 100) : 0;
  return array('score' => $score, 'label' => $label, 'checks' => $checks, 'passed' => $passed, 'suggestions' => $suggestions, 'type' => $type, 'abs_pct' => $abs_pct, 'abs_on' => $on, 'abs_max' => $abs_max);
}

/**
 * Render the performance gauge inside a module tab panel.
 * Call from within a module's overview/first tab.
 */
function langa_tools_client_render_module_gauge($module, $base_url) {
  $data = langa_tools_client_module_score($module);
  if (!$data) return;

  $score = (int)$data['score'];
  $label = $data['label'];
  $suggestions = $data['suggestions'];
  $passed = $data['passed'];
  $abs_pct = isset($data['abs_pct']) ? (int)$data['abs_pct'] : $score;
  $abs_on  = isset($data['abs_on']) ? (int)$data['abs_on'] : 0;
  $abs_max = isset($data['abs_max']) ? (int)$data['abs_max'] : 0;

  if ($score >= 80)      $gc = '#16a34a';
  elseif ($score >= 50)  $gc = '#f37f0d';
  else                   $gc = '#dc2626';

  // Absolute color
  if ($abs_pct >= 80)      $ac = '#16a34a';
  elseif ($abs_pct >= 50)  $ac = '#f37f0d';
  else                     $ac = '#dc2626';

  // SVG arc calculations — relative (main)
  $pct = max(0, min(100, $score)) / 100;
  $angle = $pct * 180;
  $rad = deg2rad(180 - $angle);
  $cx = 60; $cy = 58; $r = 46;
  $ex = $cx + cos($rad) * $r;
  $ey = $cy - sin($rad) * $r;
  $large = $angle > 180 ? 1 : 0;

  // Absolute arc (outer, thinner)
  $r2 = 52;
  $apct = max(0, min(100, $abs_pct)) / 100;
  $aangle = $apct * 180;
  $arad = deg2rad(180 - $aangle);
  $aex = $cx + cos($arad) * $r2;
  $aey = $cy - sin($arad) * $r2;
  $alarge = $aangle > 180 ? 1 : 0;

  $uid = 'langa-gauge-' . esc_attr($module);

  echo '<div style="margin:0 0 16px;padding:16px 20px;background:#fafafa;border:1px solid #e5e5e7;border-radius:10px">';

  // Top row: gauge + score + buttons
  echo '<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">';

  // Gauge SVG (dual arc)
  echo '<svg viewBox="0 0 120 70" width="120" height="70" style="flex-shrink:0">';
  // Outer track (absolute)
  echo '<path d="M 8 58 A 52 52 0 0 1 112 58" fill="none" stroke="#f0f0f0" stroke-width="3" stroke-linecap="round"/>';
  if ($abs_pct > 0) {
    echo '<path d="M 8 58 A 52 52 0 '.esc_attr($alarge).' 1 '.esc_attr(round($aex,1)).' '.esc_attr(round($aey,1)).'" fill="none" stroke="'.esc_attr($ac).'" stroke-width="3" stroke-linecap="round" opacity=".4"/>';
  }
  // Inner track (relative — main)
  echo '<path d="M 14 58 A 46 46 0 0 1 106 58" fill="none" stroke="#e5e5e7" stroke-width="7" stroke-linecap="round"/>';
  if ($score > 0) {
    echo '<path d="M 14 58 A 46 46 0 '.$large.' 1 '.round($ex,1).' '.round($ey,1).'" fill="none" stroke="'.esc_attr($gc).'" stroke-width="7" stroke-linecap="round"/>';
  }
  echo '<text x="60" y="56" text-anchor="middle" font-size="22" font-weight="700" fill="'.esc_attr($gc).'">'.$score.'</text>';
  echo '</svg>';

  // Score label + context
  echo '<div style="flex:1;min-width:180px">';
  echo '<div style="font-size:16px;font-weight:700;color:'.esc_attr($gc).'">'.$score.'/100</div>';
  echo '<div style="font-size:13px;color:#374151;margin-top:2px">'.esc_html($label).'</div>';
  if ($abs_max > 0 && $abs_max != count($data['checks'])) {
    echo '<div style="font-size:11px;color:#86868b;margin-top:2px">';
    echo 'Total potential: <strong style="color:'.esc_attr($ac).'">'.$abs_on.'/'.$abs_max.'</strong> ('.$abs_pct.'%)';
    echo '</div>';
  }
  if ($score >= 100) echo '<div style="font-size:11px;color:#16a34a;margin-top:2px">All configured for your pack!</div>';
  elseif ($score >= 80) echo '<div style="font-size:11px;color:#16a34a;margin-top:2px">Almost there!</div>';
  elseif ($score >= 50) echo '<div style="font-size:11px;color:#f37f0d;margin-top:2px">Good start, room to improve.</div>';
  else echo '<div style="font-size:11px;color:#dc2626;margin-top:2px">Several features still off.</div>';
  echo '</div>';

  // Buttons
  echo '<div style="display:flex;gap:8px;">';
  echo '<button type="button" class="button button-primary button-small" onclick="document.getElementById(\''.esc_attr($uid).'-details\').style.display=document.getElementById(\''.esc_attr($uid).'-details\').style.display===\'none\'?\'block\':\'none\'">Run test</button>';
  if (!empty($suggestions)) {
    echo '<button type="button" class="button button-secondary button-small" onclick="document.getElementById(\''.esc_attr($uid).'-tips\').style.display=document.getElementById(\''.esc_attr($uid).'-tips\').style.display===\'none\'?\'block\':\'none\'">How to improve</button>';
  }
  echo '</div>';

  echo '</div>'; // top row

  // ── Active Features (always visible) ──
  echo '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e7">';
  echo '<div style="font-weight:600;font-size:12px;color:#86868b;margin:0 0 6px;text-transform:uppercase;letter-spacing:.04em">Active features</div>';
  echo '<div style="display:flex;flex-wrap:wrap;gap:4px">';
  $all_checks = $data['checks'];
  foreach ($all_checks as $ch) {
    $name = esc_html($ch[0]);
    $is_on = $ch[1];
    $tab = isset($ch[3]) ? $ch[3] : '';
    $link = $tab ? esc_url(add_query_arg('tab', $tab, $base_url)) : '';
    if ($is_on) {
      echo '<span style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:#dcfce7;border:1px solid #bbf7d0;border-radius:5px;font-size:11px;color:#166534;font-weight:500">';
      echo '<span style="font-size:10px">&#x2713;</span> '.$name.'</span>';
    } else {
      if ($link) {
        echo '<a href="'.$link.'" style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:#f5f5f7;border:1px solid #e5e5e7;border-radius:5px;font-size:11px;color:#86868b;text-decoration:none;font-weight:500;transition:all .15s" onmouseover="this.style.borderColor=\'#f37f0d\';this.style.color=\'#c56200\'" onmouseout="this.style.borderColor=\'#e5e5e7\';this.style.color=\'#86868b\'">';
        echo '<span style="font-size:10px">&#x2717;</span> '.$name.'</a>';
      } else {
        echo '<span style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:#f5f5f7;border:1px solid #e5e5e7;border-radius:5px;font-size:11px;color:#86868b;font-weight:500">';
        echo '<span style="font-size:10px">&#x2717;</span> '.$name.'</span>';
      }
    }
  }
  echo '</div></div>';

  // Expandable: Test details (passed + failed checks)
  echo '<div id="'.esc_attr($uid).'-details" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid #e5e5e7">';
  echo '<div style="font-weight:600;font-size:13px;margin:0 0 8px">Diagnostic</div>';

  // Passed
  if (!empty($passed)) {
    foreach ($passed as $p) {
      echo '<div style="display:flex;align-items:center;gap:6px;font-size:12px;margin:0 0 4px;color:#16a34a"><span>&#x2713;</span> '.esc_html($p).'</div>';
    }
  }
  // Failed
  foreach ($suggestions as $s) {
    echo '<div style="display:flex;align-items:center;gap:6px;font-size:12px;margin:0 0 4px;color:#dc2626"><span>&#x2717;</span> '.esc_html($s['name']).'</div>';
  }
  echo '</div>';

  // Expandable: Suggestions
  if (!empty($suggestions)) {
    echo '<div id="'.esc_attr($uid).'-tips" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid #e5e5e7">';
    echo '<div style="font-weight:600;font-size:13px;margin:0 0 8px">Suggestions to improve your score</div>';
    foreach ($suggestions as $s) {
      $link = esc_url(add_query_arg('tab', $s['tab'], $base_url));
      $is_req = !empty($s['required']);
      echo '<div style="display:flex;align-items:center;gap:8px;font-size:12px;margin:0 0 6px;padding:6px 10px;background:#fff;border:1px solid #e5e5e7;border-radius:6px">';
      echo '<span style="color:'.($is_req ? '#f37f0d' : '#d2d2d7').';font-size:14px">'.($is_req ? '&#x26A0;' : '&#x2B55;').'</span>';
      echo '<span style="flex:1"><strong>'.esc_html($s['name']).'</strong> — '.esc_html($s['fix']);
      if (!$is_req) echo ' <span style="color:#86868b;font-size:10px">(optional)</span>';
      echo '</span>';
      echo '<a href="'.$link.'" class="button button-small" style="flex-shrink:0">'.($is_req ? 'Fix' : 'Enable').'</a>';
      echo '</div>';
    }
    echo '</div>';
  }

  echo '</div>';
}

/**
 * Render inline pack selector (used in Overview tabs).
 */
function langa_tools_client_render_inline_pack($packs, $current, $input_name, $submit_name, $module_label, $warning_text = '') {
  if (empty($warning_text)) $warning_text = 'Apply this '.$module_label.'? Settings will be overwritten.';
  $uid = 'langa-pack-'.sanitize_key($module_label);

  echo '<div style="margin:0 0 16px">';
  echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 10px">';
  echo '<h3 style="margin:0;font-size:14px">'.$module_label.'</h3>';

  $pack_labels = array();
  foreach ($packs as $pk => $pd) $pack_labels[$pk] = $pd['name'];

  if (!empty($current) && isset($pack_labels[$current])) {
    echo '<span style="font-size:12px;color:#16a34a;font-weight:600">'.esc_html($pack_labels[$current]).'</span>';
  } else {
    echo '<span style="font-size:12px;color:#f37f0d;font-weight:600">Not set</span>';
  }
  echo '<button type="button" class="button button-small" onclick="var d=document.getElementById(\''.esc_attr($uid).'\');d.style.display=d.style.display===\'none\'?\'block\':\'none\'">'.(empty($current)?'Choose':'Change').'</button>';
  echo '</div>';

  echo '<div id="'.esc_attr($uid).'" style="display:none">';
  echo '<p class="description" style="margin:0 0 12px">Choose a preset that matches your site. All related settings will be configured at once.</p>';

  echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;max-width:965px;margin:0 0 14px">';
  foreach ($packs as $pk => $pd) {
    $is_selected = ($current === $pk);
    $border = $is_selected ? '2px solid '.$pd['color'] : '1px solid #e5e5e7';
    $bg = $is_selected ? 'linear-gradient(135deg, '.$pd['color'].'08, '.$pd['color'].'03)' : '#fff';
    $badge = $is_selected ? '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:'.esc_attr($pd['color']).';color:#fff;text-transform:uppercase;letter-spacing:.05em;margin-left:6px">Active</span>' : '';

    echo '<label style="display:block;border:'.$border.';border-radius:12px;padding:18px;cursor:pointer;background:'.$bg.';transition:all .15s">';
    echo '<div style="display:flex;align-items:center;gap:8px;margin:0 0 8px">';
    echo '<span class="dashicons '.esc_attr($pd['icon']).'" style="font-size:20px;width:20px;height:20px;color:'.esc_attr($pd['color']).'"></span>';
    echo '<div>';
    echo '<input type="radio" name="'.esc_attr($input_name).'" value="'.esc_attr($pk).'" '.checked($current, $pk, false).' style="margin-right:4px">';
    echo '<strong style="font-size:14px">'.esc_html($pd['name']).'</strong>'.$badge;
    echo '</div></div>';
    echo '<p style="margin:0 0 8px;font-size:12px;color:#6e6e73;line-height:1.5">'.esc_html($pd['desc']).'</p>';
    if (!empty($pd['features'])) {
      echo '<div style="font-size:11px;color:#86868b;line-height:1.6">';
      foreach ($pd['features'] as $feat) echo '<span style="display:inline-block;margin:0 4px 3px 0;padding:1px 6px;background:#f5f5f7;border-radius:3px">'.esc_html($feat).'</span>';
      echo '</div>';
    }
    if (!empty($pd['ideal'])) echo '<div style="margin:6px 0 0;font-size:10px;color:#86868b"><strong>Ideal:</strong> '.esc_html($pd['ideal']).'</div>';
    echo '</label>';
  }
  echo '</div>';

  echo '<div style="display:flex;gap:12px;align-items:center">';
  echo '<button type="submit" class="button button-primary" name="'.esc_attr($submit_name).'" value="1" id="'.esc_attr($uid).'-btn" style="padding:6px 20px;font-size:14px">Apply '.esc_html($module_label).'</button>';
  echo '</div>';

  // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- admin inline JS for immediate DOM manipulation
  echo '<script>';
  echo '(function(){var btn=document.getElementById("'.esc_js($uid).'-btn");if(!btn)return;btn.addEventListener("click",function(e){var sel=document.querySelector("input[name=\"'.esc_js($input_name).'\"]:checked");if(!sel)return;var cur='.wp_json_encode($current).';if(sel.value===cur)return;if(!confirm('.wp_json_encode($warning_text).'))e.preventDefault();});})();';
  echo '</script>';

  echo '</div></div>';
}

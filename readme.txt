=== LANGA Tools Lite ===
Contributors: langaofficial
Tags: developer tools, branding, custom login, credits, maintenance
Requires at least: 5.7
Tested up to: 7.0
Stable tag: 1.0.39
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The only WordPress plugin designed to protect your identity as a developer, save you hours on every project, and deliver enterprise-grade stability.

== Description ==

LANGA Tools Lite is a lightweight, modular plugin built for developers and agencies who manage multiple WordPress sites.

**Your Signature, Protected**
Credits, custom login, forms, legal pages: your identity on every site, zero effort. Both stay active even after you hand over the site to your client.

**Hours Saved, Every Project**
One plugin replaces 8–12 tools. Configure once, deploy everywhere.

**Professional Grade, Lightweight**
Modular architecture: only active modules load. Built for production environments.

= Lite Features =

* **UI/UX Module**: WP admin improvements, hide notices, user switching, ghost pages, preloader
* **Custom Login**: Brand the WordPress login page with your logo and colors
* **Credits**: "Like this website?" footer signature that never disappears
* **Maintenance Mode**: Professional maintenance page with countdown
* **Events**: Local event logging for debugging

= PRO Features =

Upgrade to LANGA Tools PRO to unlock all modules:

* Safer (security hardening)
* SEO tools
* Cache management
* Legal/GDPR pages
* Contact Forms
* Popup system
* Business Card
* And more

[Get PRO](https://tools.langa.tv/#pricing)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/langa-tools-lite/` or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to LANGA Tools in the admin menu to configure.

== Frequently Asked Questions ==

= What does this plugin do? =

LANGA Tools Lite gives developers and agencies a free UI/UX toolkit: custom login screen branding, a persistent "built by" credits footer, maintenance mode, and seasonal effects. Your identity stays on every site you build, even after client handoff.

= Do I need LANGA Tools PRO? =

The Lite version is fully functional for branding and UI/UX. PRO unlocks 10+ additional modules: SEO, Forms, Legal (GDPR), Safer (security hardening), Cache, Business Card, Popup, Events, and AI. PRO also includes remote management from LANGA Server and priority support.

= Does this plugin connect to external services? =

Yes. The plugin communicates with the LANGA Tools Server (tools.langa.tv) to check license status and synchronize configuration. If enabled, Google Translate is also loaded. All external connections are fully disclosed in the plugin description.

= Is this plugin GDPR compliant? =

The plugin itself does not collect any visitor data. External services (Google Translate) are loaded only if you enable them. The PRO Legal module helps you generate GDPR-compliant privacy and cookie pages.

== Screenshots ==

1. LANGA Tools product overview
2. Features and modules
3. All modules at a glance
4. PRO edition features
5. Pricing and plans
6. Developer toolkit in action
7. Plugin ecosystem
8. Admin dashboard overview
9. Settings and configuration
10. Custom login setup
11. Maintenance mode
12. Credits and branding
13. Seasonal effects
14. Admin UX improvements
15. Ghost pages and preloader
16. Search and replace
17. Health dashboard
18. Visual sitemap

== Changelog ==

= 1.0.39 =
* Fix: removed call to undefined function on fresh install


= 1.0.39 =
* Removed all literal style/script/link tags from PHP source
* Credits button CSS extracted to file via wp_enqueue_style
* langtoli_inline_style rewritten to wp_register_style + wp_add_inline_style
* Custom CSS functionality completely removed

= 1.0.37 =
* All inline styles/scripts use WP enqueue API
* External CSS/JS via wp_register/enqueue
* Sanitized bg_opacity and transition_ms
* Custom CSS save handlers removed
* Badge output wrapped in wp_kses
* Inline JS variables escaped with esc_js


= 1.0.36 =
* SMTP/Mail module physically removed from build
* All license-gating code removed
* Credits mode: only local (opt-in) or off (default)
* All iframe/grayscale rendering removed
* Maintenance footer attribution removed
* Ghost pages nonce verification added
* exclude_pages sanitized with sanitize_textarea_field

= 1.0.35 =
* Removed Smart Setup PRO module configs (Cache/SEO/Safer/Legal)
* Removed all license gating logic from module page
* Removed Powered By from mail template
* Added wp_kses_post to shortcode callback returns
* Removed bridge references from registry

= 1.0.34 =
* Converted all admin inline scripts to wp_print_inline_script_tag
* Removed jQuery dependency from credits page (vanilla JS)
* Removed Google Fonts external dependency
* Added esc_html/esc_attr to all remaining unescaped outputs
* Sanitized CSS output with wp_strip_all_tags

= 1.0.33 =
* Removed all PRO module code from Lite package
* Removed telemetry/heartbeat cron (bridge-protocol, sync-protocol, scheduler)
* Removed user-switching functionality
* Removed custom CSS/JS editor (use WordPress Customizer)
* Removed Powered By attribution from maintenance and email
* Fixed backup path to use uploads directory
* Added wp_kses_post escaping to shortcode returns
* Added esc_attr/esc_html to all remaining unescaped outputs
* Replaced WP_PLUGIN_DIR with dirname(__FILE__)

= 1.0.32 =
* Security: Removed anti-deactivation guard (mu-plugin protection system)
* Security: Removed server sync for mixcode/boom/banned flags
* Fix: Credits system is now fully opt-in (off by default)
* Fix: Removed all trialware UI (locked module cards, PRO pricing)
* Fix: Removed custom CSS/JS editor fields
* Fix: Added escaping to all unescaped output variables
* Fix: Stable tag aligned with plugin version
* Requires WordPress 5.7 or later

= 1.0.11 =
* Fix: WP_CONTENT_DIR replaced with WPMU_PLUGIN_DIR (always defined by WP core)
* Fix: Removed error suppression on set_time_limit
* Fix: Removed generic shortcodes [temp], [support_id], [friend_id], [breadcrumb]: use prefixed versions
* Fix: Added nonce verification for user-switching switch-back action
* Fix: Sanitized all POST inputs with wp_strip_all_tags / sanitize_text_field
* Fix: Escaped output in dashboard widget, module page SVG, alias codes
* Fix: Added phpcs annotations for inline scripts/styles in admin callbacks and standalone pages
* Fix: Added phpcs annotations for SQL queries using schema-validated identifiers
* Fix: Added recovery notice with download URL on failed activation
* Improvement: Inline CSS for user-switching converted to wp_add_inline_style
* Improvement: Visual Builder mu-plugin inline styles annotated for compliance

= 1.0.4 =
* Improved Site Lock protection
* Added Settings link on plugins page
* Fixed custom login logo default
* UI improvements for Branding & Credits panel

= 1.0.3 =
* Initial public release
* UI/UX module with admin improvements
* Custom Login branding
* Credits footer signature
* Maintenance mode
* Events logging

== Upgrade Notice ==

= 1.0.4 =
Recommended update. Improved stability and Site Lock protection.

=== LANGA Tools Lite ===
Contributors: langaofficial, langadev, langa
Tags: developer tools, branding, custom login, credits, maintenance
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 1.0.20d
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The only WordPress plugin designed to protect your identity as a developer, save you hours on every project, and deliver enterprise-grade stability.

== Description ==

LANGA Tools Lite is a lightweight, modular plugin built for developers and agencies who manage multiple WordPress sites.

**Your Signature, Protected**
Credits, custom login, forms, legal pages — your identity on every site, zero effort. Both stay active even after you hand over the site to your client.

**Hours Saved, Every Project**
One plugin replaces 8–12 tools. Configure once, deploy everywhere.

**Professional Grade, Lightweight**
Modular architecture — only active modules load. Built for production environments.

= Lite Features =

* **UI/UX Module** — WP admin improvements, hide notices, user switching, ghost pages, preloader
* **Custom Login** — Brand the WordPress login page with your logo and colors
* **Credits** — "Like this website?" footer signature that never disappears
* **Maintenance Mode** — Professional maintenance page with countdown
* **Events** — Local event logging for debugging

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

= Does the Lite version require a license? =

No. LANGA Tools Lite works without a license. PRO features require a subscription.

= Will my branding stay after handing the site to a client? =

Yes. Credits and Custom Login persist even if the client manages the site. This is a core feature of LANGA Tools.

= Is it compatible with page builders? =

Yes. LANGA Tools is tested with Elementor, WPBakery, and other popular builders.

== Screenshots ==

1. Overview dashboard
2. Settings page
3. Custom login page
4. Credits in the footer

== External Services ==

This plugin connects to external services under the following circumstances:

= LANGA Tools Server (tools.langa.tv) =

The plugin periodically communicates with the LANGA Tools management server to check license status, synchronize module configuration, and receive remote commands (such as maintenance mode or site protection). Data sent includes: the site URL, a unique site key, and the plugin version. This communication occurs automatically every few minutes via the WordPress REST API.

* Service provider: LANGA (langa.tv)
* [Terms of Service](https://about.langa.tv/legal/terms/)
* [Privacy Policy](https://about.langa.tv/legal/privacy-policy/)

= Google Fonts =

The Credits module loads the Open Sans font family from Google Fonts to render the credits bar consistently across themes. No user data is sent; only a standard CSS request is made.

* [Google Fonts Terms of Service](https://developers.google.com/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

= Google Translate (optional) =

If the Google Translate widget is enabled in settings, a script from Google Translate is loaded to provide automatic page translation. This sends the page content to Google for translation when a visitor selects a language.

* [Google Translate Terms](https://policies.google.com/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

== Frequently Asked Questions ==

= What does this plugin do? =

LANGA Tools Lite gives developers and agencies a free UI/UX toolkit: custom login screen branding, a persistent "built by" credits footer, maintenance mode, and seasonal effects. Your identity stays on every site you build, even after client handoff.

= Do I need LANGA Tools PRO? =

The Lite version is fully functional for branding and UI/UX. PRO unlocks 10+ additional modules: SEO, Forms, Legal (GDPR), Safer (security hardening), Cache, Business Card, Popup, Events, and AI. PRO also includes remote management from LANGA Server and priority support.

= Does this plugin connect to external services? =

Yes. The plugin communicates with the LANGA Tools Server (tools.langa.tv) to check license status and synchronize configuration. If enabled, Google Fonts and Google Translate are also loaded. All external connections are fully disclosed in the plugin description.

= Is this plugin GDPR compliant? =

The plugin itself does not collect any visitor data. External services (Google Fonts, Google Translate) are loaded only if you enable them. The PRO Legal module helps you generate GDPR-compliant privacy and cookie pages.

== Changelog ==

= 1.0.11 =
* Fix: WP_CONTENT_DIR replaced with WPMU_PLUGIN_DIR (always defined by WP core)
* Fix: Removed error suppression on set_time_limit
* Fix: Removed generic shortcodes [temp], [support_id], [friend_id], [breadcrumb] — use prefixed versions
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

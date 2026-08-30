<?php
if (!defined('ABSPATH')) exit;

/**
 * LANGA Tools PRO — License System (hardened)
 *
 * Architecture:
 *   1. Client sends HMAC-signed request to server with site_url + ts + nonce
 *   2. Server responds with signed token: { status, domain, expires, issued_at, signature }
 *   3. Client verifies server HMAC, domain binding, TTL
 *   4. Valid result cached in "last_ok" (wp_option) with controlled fail-open
 *   5. Revocation: server can set status=revoked → immediate disable + sticky flag
 *
 * Fail-open policy:
 *   - If server unreachable AND last_ok < 72h old → keep valid (grace period)
 *   - If server unreachable AND last_ok > 72h → invalid (fail-closed)
 *   - If server says revoked → immediate invalid, no grace
 *   - Forms only: extended grace (7 days) to avoid breaking visitor-facing forms
 */

// Option keys
if (!defined('LANGA_TOOLS_OPTION_LICENSE_LAST')) {
  define('LANGA_TOOLS_OPTION_LICENSE_LAST', 'langa_tools_license_last');
}
if (!defined('LANGA_TOOLS_LICENSE_LAST_OK')) {
  define('LANGA_TOOLS_LICENSE_LAST_OK',   'langa_tools_license_last_ok');
}
if (!defined('LANGA_TOOLS_LICENSE_REVOKED')) {
  define('LANGA_TOOLS_LICENSE_REVOKED',   'langa_tools_license_revoked');
}

// Grace periods (seconds)
if (!defined('LANGA_LICENSE_GRACE_DEFAULT')) {
  define('LANGA_LICENSE_GRACE_DEFAULT',    259200);  // 72 hours
}
if (!defined('LANGA_LICENSE_GRACE_FORMS')) {
  define('LANGA_LICENSE_GRACE_FORMS',      604800);  // 7 days (forms fail-open)
}
if (!defined('LANGA_LICENSE_TRANSIENT_TTL')) {
  define('LANGA_LICENSE_TRANSIENT_TTL',    86400);     // 24h transient cache (must outlast time between admin visits)
}
if (!defined('LANGA_LICENSE_CHECK_INTERVAL')) {
  define('LANGA_LICENSE_CHECK_INTERVAL',   3600);    // 1 hour between remote checks
}

/* =========================================================
 * Helpers
 * ======================================================= */

function langa_tools_client_license_get_server_base() {
  $server = defined('LANGA_TOOLS_FIXED_SERVER_URL') ? (string) LANGA_TOOLS_FIXED_SERVER_URL : '';
  if ($server === '') $server = (string) get_option(LANGA_TOOLS_OPTION_SERVER_URL, '');
  return rtrim($server, '/');
}

/**
 * Get the canonical domain for this site (used for domain binding).
 */
function langa_tools_client_license_get_domain() {
  $url = home_url();
  $host = wp_parse_url($url, PHP_URL_HOST);
  return $host ? strtolower($host) : '';
}

/* =========================================================
 * Last-check storage (full response for UI)
 * ======================================================= */

function langa_tools_client_license_last() {
  $v = get_option(LANGA_TOOLS_OPTION_LICENSE_LAST, array());
  return is_array($v) ? $v : array();
}

function langa_tools_client_license_store_last($arr) {
  if (!is_array($arr)) return;
  $arr['checked_at'] = time();
  update_option(LANGA_TOOLS_OPTION_LICENSE_LAST, $arr, false);
}

/* =========================================================
 * Last-OK storage (persistent, survives transient expiry)
 * ======================================================= */

function langa_tools_client_license_get_last_ok() {
  $v = get_option(LANGA_TOOLS_LICENSE_LAST_OK, array());
  return is_array($v) ? $v : array();
}

function langa_tools_client_license_set_last_ok($token) {
  $data = array(
    'time'    => time(),
    'domain'  => isset($token['domain'])  ? (string) $token['domain']  : '',
    'expires' => isset($token['expires']) ? (int) $token['expires']    : 0,
    'status'  => 'valid',
  );
  update_option(LANGA_TOOLS_LICENSE_LAST_OK, $data, false);
}

function langa_tools_client_license_clear_last_ok() {
  delete_option(LANGA_TOOLS_LICENSE_LAST_OK);
}

/* =========================================================
 * Revocation flag (sticky until cleared by valid check)
 * ======================================================= */

function langa_tools_client_license_is_revoked() {
  return (bool) get_option(LANGA_TOOLS_LICENSE_REVOKED, false);
}

function langa_tools_client_license_set_revoked($reason = 'revoked') {
  update_option(LANGA_TOOLS_LICENSE_REVOKED, $reason, false);
  langa_tools_client_license_clear_last_ok();
  // Log revocation
  langa_tools_client_license_log('revoked', 'License revoked by server: ' . $reason);
}

function langa_tools_client_license_clear_revoked() {
  delete_option(LANGA_TOOLS_LICENSE_REVOKED);
}

/* =========================================================
 * Debug logging helper
 * ======================================================= */

function langa_tools_client_license_log($type, $message, $extra = array()) {
  if (function_exists('langa_tools_client_debug_log')) {
    $extra['module'] = 'license';
    langa_tools_client_debug_log('license_' . $type, $message, $extra);
  }
  // Log critical events only when WP_DEBUG is on
  if (in_array($type, array('revoked', 'domain_mismatch', 'signature_fail', 'expired'), true)) {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
      // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug only
      error_log('[LANGA Tools Lite][LICENSE] ' . $type . ': ' . $message);
    }
  }
}

/* =========================================================
 * Server response verification
 * ======================================================= */

/**
 * Verify server response HMAC signature.
 *
 * Server signs: json_encode([ status, domain, expires, issued_at ])
 * with the shared secret.
 *
 * @param array  $token  Decoded server response
 * @param string $secret Shared secret
 * @return bool
 */
function langa_tools_client_license_verify_token($token, $secret) {
  if (!is_array($token)) return false;
  if (empty($token['signature'])) return false;

  // Build the payload the server signed
  $fields = array(
    'status'    => isset($token['status'])    ? (string) $token['status']    : '',
    'domain'    => isset($token['domain'])    ? (string) $token['domain']    : '',
    'expires'   => isset($token['expires'])   ? (int) $token['expires']      : 0,
    'issued_at' => isset($token['issued_at']) ? (int) $token['issued_at']    : 0,
  );
  $payload = wp_json_encode($fields);
  $expected = hash_hmac('sha256', $payload, $secret);

  return hash_equals($expected, (string) $token['signature']);
}

/**
 * Validate domain binding.
 */
function langa_tools_client_license_verify_domain($token) {
  if (!is_array($token) || empty($token['domain'])) return false;
  $server_domain = strtolower(trim((string) $token['domain']));
  $local_domain  = langa_tools_client_license_get_domain();
  if ($server_domain === '' || $local_domain === '') return false;

  // Exact match or wildcard match (*.example.com)
  if ($server_domain === $local_domain) return true;
  if (strpos($server_domain, '*.') === 0) {
    $wildcard = substr($server_domain, 2);
    if ($local_domain === $wildcard) return true;
    if (substr($local_domain, -(strlen($wildcard) + 1)) === '.' . $wildcard) return true;
  }
  return false;
}

/**
 * Check token TTL.
 */
function langa_tools_client_license_verify_ttl($token) {
  if (!is_array($token) || empty($token['expires'])) return true; // no expiry = no TTL check
  return (int) $token['expires'] > time();
}

/* =========================================================
 * Main license check (remote)
 * ======================================================= */

/**
 * Checks license against server with full token verification.
 *
 * @param bool $force  Skip cache, call server immediately
 * @return array  { ok, status, reason, http, error }
 */
function langa_tools_client_license_check($force = false) {
  static $memo = null;
  if (!$force && $memo !== null) return $memo;

  $site_key = (string) get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
  $secret   = (string) get_option(LANGA_TOOLS_OPTION_SECRET, '');

  // ── Missing credentials — cache to avoid repeated option reads ──
  if ($site_key === '' || $secret === '') {
    $memo = array('ok' => false, 'status' => 'invalid', 'reason' => 'missing_credentials', 'http' => 0, 'error' => '');
    langa_tools_client_license_store_last($memo);
    $ck = 'langa_tools_license_nocreds';
    set_transient($ck, $memo, LANGA_LICENSE_TRANSIENT_TTL);
    return $memo;
  }

  // ── Check revocation flag (sticky) ──
  if (!$force && langa_tools_client_license_is_revoked()) {
    $memo = array('ok' => false, 'status' => 'revoked', 'reason' => (string) get_option(LANGA_TOOLS_LICENSE_REVOKED, 'revoked'), 'http' => 0, 'error' => '');
    langa_tools_client_license_store_last($memo);
    return $memo;
  }

  // ── Transient cache (skip remote call) ──
  $cache_key = 'langa_tools_license_' . substr(sha1($site_key), 0, 12);
  if (!$force) {
    $cached = get_transient($cache_key);
    if (is_array($cached) && isset($cached['status'])) {
      $memo = $cached;
      return $memo;
    }
  }

  // ── Server URL ──
  $server = langa_tools_client_license_get_server_base();
  if ($server === '') {
    $memo = array('ok' => false, 'status' => 'invalid', 'reason' => 'missing_server', 'http' => 0, 'error' => '');
    langa_tools_client_license_store_last($memo);
    set_transient($cache_key, $memo, 3600); // 1h retry on error
    langa_tools_client_license_log('check', 'Missing server URL');
    return $memo;
  }

  // ── Build signed request ──
  $endpoint = $server . '/wp-json/langa-tools-server/v1/license/check';

  $payload_arr = array(
    'site_url' => home_url(),
    'domain'   => langa_tools_client_license_get_domain(),
    'ts'       => time(),
    'nonce'    => function_exists('wp_generate_password') ? wp_generate_password(12, false, false) : substr(bin2hex(random_bytes(8)), 0, 12),
    'version'  => defined('LANGA_TOOLS_CLIENT_VERSION') ? LANGA_TOOLS_CLIENT_VERSION : '0.0.0',
    'client_type' => defined('LANGA_TOOLS_IS_LITE') ? 'lite' : 'pro',
  );
  $payload   = wp_json_encode($payload_arr);
  $signature = Langa_Tools_Client_Auth::sign($payload, $secret);

  // ── Remote call ──
  $t0   = microtime(true);
  $resp = wp_remote_post($endpoint, array(
    'timeout' => 3,
    'body'    => array(
      'site_key'  => $site_key,
      'payload'   => $payload,
      'signature' => $signature,
    ),
  ));
  $ms = round((microtime(true) - $t0) * 1000);

  if (function_exists('langa_tools_client_debug_log_remote')) {
    langa_tools_client_debug_log_remote('license', 'POST', $endpoint, $resp, $ms);
  }

  // ── Network error → fail-open with grace period ──
  if (is_wp_error($resp)) {
    $last_ok = langa_tools_client_license_get_last_ok();
    $age     = isset($last_ok['time']) ? (time() - (int) $last_ok['time']) : 999999;

    if (!empty($last_ok['status']) && $last_ok['status'] === 'valid' && $age < LANGA_LICENSE_GRACE_DEFAULT) {
      $memo = array('ok' => true, 'status' => 'valid', 'reason' => 'grace_period', 'http' => 0, 'error' => $resp->get_error_message());
      set_transient($cache_key, $memo, min(LANGA_LICENSE_TRANSIENT_TTL, LANGA_LICENSE_GRACE_DEFAULT - $age));
      langa_tools_client_license_log('grace', 'Server unreachable, grace period active (age: ' . $age . 's)', array('error' => $resp->get_error_message()));
      return $memo;
    }

    $memo = array('ok' => false, 'status' => 'invalid', 'reason' => 'server_unreachable', 'http' => 0, 'error' => $resp->get_error_message());
    langa_tools_client_license_store_last($memo);
    set_transient($cache_key, $memo, 3600); // 1h retry on error
    langa_tools_client_license_log('unreachable', 'Server unreachable, no valid grace period', array('error' => $resp->get_error_message()));
    return $memo;
  }

  // ── Parse response ──
  $http = (int) wp_remote_retrieve_response_code($resp);
  $body = (string) wp_remote_retrieve_body($resp);
  $json = json_decode($body, true);

  if (!is_array($json) || empty($json['status'])) {
    $memo = array('ok' => false, 'status' => 'invalid', 'reason' => 'bad_response', 'http' => $http, 'error' => mb_substr($body, 0, 200));
    langa_tools_client_license_store_last($memo);
    set_transient($cache_key, $memo, 3600); // 1h retry on error
    langa_tools_client_license_log('error', 'Bad server response (HTTP ' . $http . ')', array('code' => (string) $http));
    return $memo;
  }

  $status = (string) $json['status'];
  $reason = isset($json['reason']) ? (string) $json['reason'] : '';

  // ── Handle revocation ──
  if ($status === 'revoked') {
    langa_tools_client_license_set_revoked($reason ?: 'revoked');
    $memo = array('ok' => false, 'status' => 'revoked', 'reason' => $reason ?: 'revoked', 'http' => $http, 'error' => '');
    langa_tools_client_license_store_last($memo);
    set_transient($cache_key, $memo, LANGA_LICENSE_TRANSIENT_TTL);
    return $memo;
  }

  // ── Handle inactive / invalid / expired / domain_mismatch ──
  if ($status !== 'valid') {
    // Only clear last_ok on PERMANENT failures (revocation handled above, domain_mismatch).
    // For temporary failures (expired, invalid, bad credentials), keep last_ok alive
    // so frontend can use grace period instead of breaking immediately.
    if ($status === 'domain_mismatch') {
      langa_tools_client_license_clear_last_ok();
    }
    if ($status === 'expired') {
      $reason = $reason ?: 'license_expired';
      langa_tools_client_license_log('expired', 'License expired');
    }
    $memo = array('ok' => false, 'status' => $status, 'reason' => $reason, 'http' => $http, 'error' => '');
    // v1.3.0: store grey_credits flag from server (controls grey credits bar visibility on inactive sites)
    if (isset($json['grey_credits'])) {
      $memo['grey_credits'] = (int) $json['grey_credits'];
    }
    // v2.5.0f: store mixcode flag (anti-deactivation lock)
    if (isset($json['mixcode'])) {
      $memo['mixcode'] = (int) $json['mixcode'];
      update_option('langa_tools_mixcode', (int) $json['mixcode'], true);
    }
    if (isset($json['banned'])) {
      $memo['banned'] = (int) $json['banned'];
      update_option('langa_tools_banned', (int) $json['banned'], true);
    }
    langa_tools_client_license_store_last($memo);
    set_transient($cache_key, $memo, LANGA_LICENSE_TRANSIENT_TTL);
    langa_tools_client_license_log('invalid', 'License check failed: ' . $status . ' / ' . $reason, array('code' => (string) $http));
    return $memo;
  }

  // ── Status = valid: verify token integrity ──

  // 1) Verify HMAC signature (if server provides it)
  if (!empty($json['signature'])) {
    if (!langa_tools_client_license_verify_token($json, $secret)) {
      $memo = array('ok' => false, 'status' => 'invalid', 'reason' => 'signature_verification_failed', 'http' => $http, 'error' => '');
      langa_tools_client_license_store_last($memo);
      set_transient($cache_key, $memo, 3600); // 1h retry on error
      langa_tools_client_license_log('signature_fail', 'Server response HMAC verification failed');
      return $memo;
    }
  }

  // 2) Verify domain binding (if server provides it)
  if (!empty($json['domain'])) {
    if (!langa_tools_client_license_verify_domain($json)) {
      $memo = array('ok' => false, 'status' => 'invalid', 'reason' => 'domain_mismatch', 'http' => $http, 'error' => '');
      langa_tools_client_license_store_last($memo);
      set_transient($cache_key, $memo, LANGA_LICENSE_TRANSIENT_TTL);
      langa_tools_client_license_log('domain_mismatch',
        'Domain mismatch: server=' . $json['domain'] . ' local=' . langa_tools_client_license_get_domain()
      );
      return $memo;
    }
  }

  // 3) Verify TTL (if server provides expiry)
  if (!langa_tools_client_license_verify_ttl($json)) {
    $memo = array('ok' => false, 'status' => 'expired', 'reason' => 'token_expired', 'http' => $http, 'error' => '');
    langa_tools_client_license_store_last($memo);
    set_transient($cache_key, $memo, LANGA_LICENSE_TRANSIENT_TTL);
    langa_tools_client_license_log('expired', 'License token TTL expired (expires=' . ($json['expires'] ?? 0) . ')');
    return $memo;
  }

  // ── ALL CHECKS PASSED → valid ──
  langa_tools_client_license_clear_revoked();
  langa_tools_client_license_set_last_ok($json);

  // Determine transient TTL: use server-provided TTL or default
  $ttl = LANGA_LICENSE_TRANSIENT_TTL;
  if (!empty($json['check_interval']) && (int) $json['check_interval'] > 0) {
    $ttl = min((int) $json['check_interval'], 86400);
  }

  $memo = array(
    'ok'     => true,
    'status' => 'valid',
    'reason' => '',
    'http'   => $http,
    'error'  => '',
    'domain' => isset($json['domain'])  ? (string) $json['domain']  : '',
    'expires'=> isset($json['expires']) ? (int) $json['expires']    : 0,
  );
  // v2.5.0f: store mixcode flag (anti-deactivation lock)
  if (isset($json['mixcode'])) {
    $memo['mixcode'] = (int) $json['mixcode'];
    update_option('langa_tools_mixcode', (int) $json['mixcode'], true);
  }
  langa_tools_client_license_store_last($memo);
  set_transient($cache_key, $memo, $ttl);

  langa_tools_client_license_log('valid', 'License valid', array('code' => (string) $http));
  return $memo;
}

/* =========================================================
 * Convenience functions
 * ======================================================= */

function langa_tools_client_license_ok() {
  $r = langa_tools_client_license_check(false);
  return !empty($r['ok']);
}

/**
 * Cached license OK check (no remote call).
 * Returns true only if last_ok is within $max_age.
 */
function langa_tools_client_license_ok_cached($max_age = 259200) {
  if (langa_tools_client_license_is_revoked()) return false;
  $last_ok = langa_tools_client_license_get_last_ok();
  if (empty($last_ok) || empty($last_ok['status']) || $last_ok['status'] !== 'valid') return false;
  $age = isset($last_ok['time']) ? (time() - (int) $last_ok['time']) : 9999999;
  return ($age >= 0 && $age <= (int) $max_age);
}

/**
 * LICENSE KILL-SWITCH — single authoritative function.
 *
 * In admin: does a live check (with transient cache).
 * On frontend: uses cached last_ok to avoid blocking page load.
 */
function langa_tools_client_license_is_valid($force = false) {
  static $memo = null;
  if (!$force && $memo !== null) return $memo;

  // ── DEV BYPASS — absolute priority, skip everything ──
  if (function_exists('langa_tools_client_dev_bypass_active') && langa_tools_client_dev_bypass_active()) {
    // Clear negative caches so switching back to real license works cleanly
    delete_transient('langa_license_killswitch');
    $memo = true;
    return true;
  }

  // ── REVOKED — hard block, no grace ──
  if (!$force && langa_tools_client_license_is_revoked()) {
    $memo = false;
    return false;
  }

  // ── KILLSWITCH TRANSIENT — fast path (both admin + frontend) ──
  $cache_key = 'langa_license_killswitch';
  if (!$force) {
    $cached = get_transient($cache_key);
    if ($cached === 'blocked') {
      $memo = false;
      return false;
    }
    if ($cached === 'valid') {
      $memo = true;
      return true;
    }
  } else {
    delete_transient($cache_key);
  }

  // ── FRONTEND (no admin, no ajax, no cron) — NEVER do remote call ──
  // Use cached state with generous grace period (7 days for all modules).
  // The actual verification happens in admin or via cron.
  $is_admin_ctx = (is_admin() || wp_doing_ajax() || wp_doing_cron());
  if (!$is_admin_ctx && !$force) {
    // Check last_ok with extended grace (7 days = same as forms)
    $ok = langa_tools_client_license_ok_cached(604800);
    if ($ok) {
      // Re-set the killswitch so subsequent checks within this page load are fast
      set_transient($cache_key, 'valid', LANGA_LICENSE_TRANSIENT_TTL);
    }
    $memo = $ok;
    return $ok;
  }

  // ── EARLY BOOT GUARD — before plugins_loaded, pluggable functions unavailable ──
  if (!$force && !did_action('plugins_loaded')) {
    $memo = langa_tools_client_license_ok_cached(604800);
    return $memo;
  }

  // ── ADMIN / CRON / AJAX — do the actual remote check ──
  $r     = langa_tools_client_license_check($force);
  $valid = !empty($r['ok']) && isset($r['status']) && (string) $r['status'] === 'valid';

  set_transient($cache_key, $valid ? 'valid' : 'blocked', LANGA_LICENSE_TRANSIENT_TTL);
  $memo = $valid;
  return $valid;
}

/**
 * Extended grace check for forms only (fail-open 7 days).
 * Used so visitor-facing forms don't break during temporary server outages.
 */
function langa_tools_client_license_ok_for_forms() {
  if (langa_tools_client_license_is_revoked()) return false;
  if (langa_tools_client_license_is_valid()) return true;
  return langa_tools_client_license_ok_cached(LANGA_LICENSE_GRACE_FORMS);
}

/* =========================================================
 * AJAX: force license re-check (admin only)
 * ======================================================= */

function langa_tools_client_license_force_check_ajax() {
  if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
  check_ajax_referer('langa_license_force_check', '_nonce');

  // Force check clears revocation too (allows re-validation after fix)
  langa_tools_client_license_clear_revoked();

  $valid = langa_tools_client_license_is_valid(true);
  $last  = langa_tools_client_license_last();

  // Flush page caches so frontend reflects the new state immediately
  if (function_exists('langa_credits_flush_page_caches')) {
    langa_credits_flush_page_caches();
  }

  wp_send_json(array(
    'valid'  => $valid,
    'status' => isset($last['status']) ? $last['status'] : 'unknown',
    'reason' => isset($last['reason']) ? $last['reason'] : '',
    'time'   => date('Y-m-d H:i:s'),
  ));
}
add_action('wp_ajax_langa_license_force_check', 'langa_tools_client_license_force_check_ajax');

/* =========================================================
 * REST endpoint: server-initiated cache invalidation
 *
 * When the server changes status or grey_credits for a site,
 * it calls POST /wp-json/langa-tools-client/v1/license/invalidate
 * with site_key + signature. Client deletes transient → next
 * page load does a fresh license check.
 * ======================================================= */
add_action('rest_api_init', function () {
  register_rest_route('langa-tools-client/v1', '/license/invalidate', array(
    'methods'  => 'POST',
    'callback' => 'langa_tools_client_license_invalidate_endpoint',
    'permission_callback' => '__return_true', // auth is done inside via HMAC
  ));
});

function langa_tools_client_license_invalidate_endpoint(WP_REST_Request $req) {
  $site_key  = sanitize_text_field((string) $req->get_param('site_key'));
  $payload_s = (string) $req->get_param('payload');
  $signature = sanitize_text_field((string) $req->get_param('signature'));

  if ($site_key === '' || $signature === '' || $payload_s === '') {
    return new WP_REST_Response(array('ok' => false, 'reason' => 'missing_params'), 400);
  }

  // Verify this is our site_key
  $our_key = (string) get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
  if ($our_key === '' || $site_key !== $our_key) {
    return new WP_REST_Response(array('ok' => false, 'reason' => 'wrong_site_key'), 403);
  }

  // Verify HMAC signature
  $secret = (string) get_option(LANGA_TOOLS_OPTION_SECRET, '');
  $expected = hash_hmac('sha256', $payload_s, $secret);
  if (!hash_equals($expected, $signature)) {
    return new WP_REST_Response(array('ok' => false, 'reason' => 'invalid_signature'), 403);
  }

  // Delete license transient → force fresh check on next page load
  $cache_key = 'langa_tools_license_' . substr(sha1($our_key), 0, 12);
  delete_transient($cache_key);
  delete_transient('langa_tools_license_nocreds');
  // CRITICAL: also clear the killswitch transient used by license_is_valid()
  delete_transient('langa_license_killswitch');

  // Also clear last stored result so credits mode re-evaluates
  delete_option(LANGA_TOOLS_OPTION_LICENSE_LAST);

  // Clear cached per-module licensing → forces fresh read from server
  delete_option('langa_tools_licensed_modules');

  // Clear ban/boom options so they re-evaluate from fresh license check
  delete_option('langa_tools_banned');
  delete_option('langa_tools_boom');

  // Clear revocation if present (server is now in control)
  if (function_exists('langa_tools_client_license_clear_revoked')) {
    langa_tools_client_license_clear_revoked();
  }

  // Flush page caches
  if (function_exists('langa_credits_flush_page_caches')) {
    langa_credits_flush_page_caches();
  }

  return rest_ensure_response(array('ok' => true, 'invalidated' => true));
}

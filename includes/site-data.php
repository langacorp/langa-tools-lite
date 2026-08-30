<?php
if (!defined('ABSPATH')) exit;

/**
 * Global Site Data
 * Used across modules (BC vCard, Legal templates, etc.).
 */

if (!defined('LANGA_TOOLS_CLIENT_SITE_DATA_OPTION')) define('LANGA_TOOLS_CLIENT_SITE_DATA_OPTION', 'langa_tools_client_site_data');

if (!function_exists('langa_tools_client_site_data_defaults')) {
  function langa_tools_client_site_data_defaults() {
    $domain = (string) parse_url(home_url('/'), PHP_URL_HOST);
    $domain = preg_replace('/^www\./', '', $domain);
    $fallback_email = $domain ? ('info@' . $domain) : '';
    $name = get_bloginfo('name');

    return array(
      'company' => array(
        'brand'      => $name,
        'legal_name' => '',
        'vat'        => '',
        'sdi'        => '',
        'address'    => '',
        'city'       => '',
        'zip'        => '',
        'province'   => '',
        'country'    => 'Italy',
        'phone'      => '',
        'email'      => $fallback_email,
        'website'    => home_url('/'),
      ),
      'developer' => array(
        'brand'      => 'LANGA',
        'legal_name' => '',
        'vat'        => '',
        'address'    => '',
        'city'       => '',
        'zip'        => '',
        'province'   => '',
        'country'    => '',
        'phone'      => '',
        'email'      => '',
        'website'    => 'https://langa.tv/',
        'logo_url'   => 'https://about.langa.tv/wp-content/uploads/2024/03/LANGA-logo.webp',
        'slogan'     => 'Il tool per il web',
        'primary_color' => '#f37f0d',
        'privacy_url' => '',
        'terms_url'   => '',
        'about_url'   => 'https://about.langa.tv/',
        'credits_services' => "Sito web vetrina\nSviluppo sito web dinamico\nPiattaforma eCommerce\nWeb design personalizzato\nGestione dei social media\nMiglioramento del SEO\nBrand identity\nGrafica creativa\nServizio fotografico\nCreazione video promo\nVideo emozionale\nMarketing strategico\nAltre operazioni marketing",
      ),
      'billing' => array(
        'name'            => '',
        'vat'             => '',
        'codice_fiscale'  => '',
        'sdi'             => '',
        'pec'             => '',
        'address'         => '',
        'city'            => '',
        'zip'             => '',
        'province'        => '',
        'country'         => '',
      ),
      'shipping' => array(
        'name'      => '',
        'address'   => '',
        'city'      => '',
        'zip'       => '',
        'province'  => '',
        'country'   => '',
      ),
      'bank' => array(
        'holder'    => '',
        'bank_name' => '',
        'iban'      => '',
        'bic'       => '',
      ),
      'extra_shipping' => array(),
      // vCard (company)
      'vcard' => array(
        'org'     => $name,
        'phone'   => '',
        'email'   => $fallback_email,
        // free text line, used as-is in vCard address field
        'address' => '',
        'vat'     => '',
        'sdi'     => '',
        'iban'        => '',
        'bank_holder' => '',
        'bank_name'   => '',
        'bic'         => '',
      ),
    );
  }
}

if (!function_exists('langa_tools_client_get_site_data')) {
  /**
   * Returns the whole site data array or a nested value using dot-notation.
   * Example: langa_tools_client_get_site_data('vcard.email')
   */
  function langa_tools_client_get_site_data($key = null, $default = null) {
    $opt = get_option(LANGA_TOOLS_CLIENT_SITE_DATA_OPTION, array());
    if (!is_array($opt)) $opt = array();
    $data = wp_parse_args($opt, langa_tools_client_site_data_defaults());

    if ($key === null || $key === '') {
      return $data;
    }

    $path = explode('.', (string)$key);
    $cur = $data;
    foreach ($path as $seg) {
      if (!is_array($cur) || !array_key_exists($seg, $cur)) {
        return $default;
      }
      $cur = $cur[$seg];
    }
    return $cur;
  }
}

if (!function_exists('langa_tools_client_site_data_sanitize')) {
  function langa_tools_client_site_data_sanitize($raw, $prev = array()) {
    $raw = is_array($raw) ? $raw : array();
    $prev = is_array($prev) ? $prev : array();

    $defaults = langa_tools_client_site_data_defaults();
    $out = wp_parse_args($prev, $defaults);

    $sanitize_text = function($v){
      $v = is_string($v) ? $v : '';
      $v = wp_unslash($v);
      $v = str_replace("\0", '', $v);
      return sanitize_text_field($v);
    };

    $sanitize_textarea = function($v){
      $v = is_string($v) ? $v : '';
      $v = wp_unslash($v);
      $v = str_replace("\0", '', $v);
      $v = trim($v);
      if (strlen($v) > 500) $v = substr($v, 0, 500);
      return sanitize_text_field($v);
    };

    $sanitize_iban = function($v){
      $v = is_string($v) ? $v : '';
      $v = wp_unslash($v);
      $v = preg_replace('/\s+/', '', $v);
      $v = str_replace("\0", '', $v);
      if (strlen($v) > 64) $v = substr($v, 0, 64);
      return strtoupper(sanitize_text_field($v));
    };

    // Company
    $c = isset($raw['company']) && is_array($raw['company']) ? $raw['company'] : array();
    foreach (array('brand','legal_name','vat','sdi','address','city','zip','province','country','phone') as $k) {
      $out['company'][$k] = $sanitize_text($c[$k] ?? ($out['company'][$k] ?? ''));
    }
    $out['company']['email'] = sanitize_email((string)($c['email'] ?? ($out['company']['email'] ?? '')));
    $out['company']['website'] = esc_url_raw((string)($c['website'] ?? ($out['company']['website'] ?? '')));

    // Developer / Responsabile del trattamento
    $dev = isset($raw['developer']) && is_array($raw['developer']) ? $raw['developer'] : array();
    foreach (array('brand','legal_name','vat','address','city','zip','province','country','phone','slogan') as $k) {
      $out['developer'][$k] = $sanitize_text($dev[$k] ?? ($out['developer'][$k] ?? ''));
    }
    $out['developer']['email'] = sanitize_email((string)($dev['email'] ?? ($out['developer']['email'] ?? '')));
    $out['developer']['website'] = esc_url_raw((string)($dev['website'] ?? ($out['developer']['website'] ?? '')));
    $out['developer']['logo_url'] = esc_url_raw((string)($dev['logo_url'] ?? ($out['developer']['logo_url'] ?? '')));
    $pc = (string)($dev['primary_color'] ?? ($out['developer']['primary_color'] ?? '#f37f0d'));
    $out['developer']['primary_color'] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $pc) ? $pc : '#f37f0d';
    $out['developer']['privacy_url'] = esc_url_raw((string)($dev['privacy_url'] ?? ($out['developer']['privacy_url'] ?? '')));
    $out['developer']['terms_url'] = esc_url_raw((string)($dev['terms_url'] ?? ($out['developer']['terms_url'] ?? '')));
    $out['developer']['about_url'] = esc_url_raw((string)($dev['about_url'] ?? ($out['developer']['about_url'] ?? '')));
    // Credits services: one per line, sanitize each line
    $svc_raw = (string)($dev['credits_services'] ?? ($out['developer']['credits_services'] ?? ''));
    $svc_raw = wp_unslash($svc_raw);
    $svc_lines = array_filter(array_map('trim', explode("\n", $svc_raw)));
    $svc_lines = array_map('sanitize_text_field', $svc_lines);
    $out['developer']['credits_services'] = implode("\n", $svc_lines);

    // Billing
    $b = isset($raw['billing']) && is_array($raw['billing']) ? $raw['billing'] : array();
    foreach (array('name','vat','codice_fiscale','sdi','pec','address','city','zip','province','country') as $k) {
      $out['billing'][$k] = $sanitize_text($b[$k] ?? ($out['billing'][$k] ?? ''));
    }

    // Shipping
    $s = isset($raw['shipping']) && is_array($raw['shipping']) ? $raw['shipping'] : array();
    foreach (array('name','address','city','zip','province','country') as $k) {
      $out['shipping'][$k] = $sanitize_text($s[$k] ?? ($out['shipping'][$k] ?? ''));
    }

    // Bank
    $bk = isset($raw['bank']) && is_array($raw['bank']) ? $raw['bank'] : array();
    $out['bank']['holder']    = $sanitize_text($bk['holder'] ?? ($out['bank']['holder'] ?? ''));
    $out['bank']['bank_name'] = $sanitize_text($bk['bank_name'] ?? ($out['bank']['bank_name'] ?? ''));
    $out['bank']['iban']      = $sanitize_iban($bk['iban'] ?? ($out['bank']['iban'] ?? ''));
    $out['bank']['bic']       = $sanitize_text($bk['bic'] ?? ($out['bank']['bic'] ?? ''));

    // Extra shipping addresses
    $extra_raw = isset($raw['extra_shipping']) && is_array($raw['extra_shipping']) ? $raw['extra_shipping'] : array();
    $remove_list = isset($raw['remove_extra_shipping']) && is_array($raw['remove_extra_shipping']) ? array_map('intval', $raw['remove_extra_shipping']) : array();
    $extra_out = array();
    foreach ($extra_raw as $idx => $addr) {
      if (!is_array($addr)) continue;
      if (in_array((int)$idx, $remove_list, true)) continue;
      $a = array();
      foreach (array('name','address','city','zip','province','country') as $ek) {
        $a[$ek] = $sanitize_text($addr[$ek] ?? '');
      }
      // Skip completely empty addresses
      $has_data = false;
      foreach ($a as $av) { if ($av !== '') { $has_data = true; break; } }
      if ($has_data) $extra_out[] = $a;
    }
    $out['extra_shipping'] = array_values($extra_out);

    // vCard
    $v = isset($raw['vcard']) && is_array($raw['vcard']) ? $raw['vcard'] : array();
    $out['vcard']['org']     = $sanitize_text($v['org'] ?? ($out['vcard']['org'] ?? ''));
    $out['vcard']['phone']   = $sanitize_text($v['phone'] ?? ($out['vcard']['phone'] ?? ''));
    $out['vcard']['email']   = sanitize_email((string)($v['email'] ?? ($out['vcard']['email'] ?? '')));
    $out['vcard']['address'] = $sanitize_textarea($v['address'] ?? ($out['vcard']['address'] ?? ''));
    $out['vcard']['vat']     = $sanitize_text($v['vat'] ?? ($out['vcard']['vat'] ?? ''));
    $out['vcard']['sdi']     = $sanitize_text($v['sdi'] ?? ($out['vcard']['sdi'] ?? ''));
    $out['vcard']['iban']    = $sanitize_iban($v['iban'] ?? ($out['vcard']['iban'] ?? ''));
    $out['vcard']['bank_holder'] = $sanitize_text($v['bank_holder'] ?? ($out['vcard']['bank_holder'] ?? ''));
    $out['vcard']['bank_name']   = $sanitize_text($v['bank_name'] ?? ($out['vcard']['bank_name'] ?? ''));
    $out['vcard']['bic']         = $sanitize_text($v['bic'] ?? ($out['vcard']['bic'] ?? ''));

    // Sync bank data: store only once, but keep legacy bank keys aligned.
    if ($out['vcard']['bank_holder'] !== '') $out['bank']['holder'] = $out['vcard']['bank_holder'];
    if ($out['vcard']['bank_name']   !== '') $out['bank']['bank_name'] = $out['vcard']['bank_name'];
    if ($out['vcard']['bic']         !== '') $out['bank']['bic'] = $out['vcard']['bic'];
    if ($out['vcard']['iban']        !== '') $out['bank']['iban'] = $out['vcard']['iban'];

    // Backfill vCard from legacy bank (for older installs / imports)
    if ($out['vcard']['bank_holder'] === '' && !empty($out['bank']['holder'])) $out['vcard']['bank_holder'] = (string)$out['bank']['holder'];
    if ($out['vcard']['bank_name']   === '' && !empty($out['bank']['bank_name'])) $out['vcard']['bank_name'] = (string)$out['bank']['bank_name'];
    if ($out['vcard']['bic']         === '' && !empty($out['bank']['bic'])) $out['vcard']['bic'] = (string)$out['bank']['bic'];
    if ($out['vcard']['iban']        === '' && !empty($out['bank']['iban'])) $out['vcard']['iban'] = (string)$out['bank']['iban'];

    return $out;
  }
}

if (!function_exists('langa_tools_client_site_data_get_vcard')) {
  function langa_tools_client_site_data_get_vcard() {
    // NOTE: Admin UI no longer exposes a separate "vCard" panel.
    // vCard + Company QR are generated primarily from Company + Bank data.
    // Legacy vCard keys are still supported as fallback for older installs.

    $legacy = langa_tools_client_get_site_data('vcard', array());
    $c = langa_tools_client_get_site_data('company', array());
    $b = langa_tools_client_get_site_data('bank', array());
    if (!is_array($legacy)) $legacy = array();
    if (!is_array($c)) $c = array();
    if (!is_array($b)) $b = array();

    $v = array();

    // Org
    $v['org'] = '';
    if (!empty($c['legal_name'])) $v['org'] = (string)$c['legal_name'];
    elseif (!empty($c['brand'])) $v['org'] = (string)$c['brand'];
    elseif (!empty($legacy['org'])) $v['org'] = (string)$legacy['org'];
    if ($v['org'] === '') $v['org'] = get_bloginfo('name');

    // Phone/Email (Company is authoritative)
    $v['phone'] = !empty($c['phone']) ? (string)$c['phone'] : (string)($legacy['phone'] ?? '');
    $v['email'] = !empty($c['email']) ? (string)$c['email'] : (string)($legacy['email'] ?? '');

    // Address
    $addr = function_exists('langa_tools_client_site_data_get_company_address_line')
      ? langa_tools_client_site_data_get_company_address_line()
      : '';
    $v['address'] = $addr !== '' ? (string)$addr : (string)($legacy['address'] ?? '');

    // VAT/SDI
    $v['vat'] = !empty($c['vat']) ? (string)$c['vat'] : (string)($legacy['vat'] ?? '');
    $v['sdi'] = !empty($c['sdi']) ? (string)$c['sdi'] : (string)($legacy['sdi'] ?? '');

    // Bank (Bank section is authoritative)
    $v['bank_holder'] = !empty($b['holder']) ? (string)$b['holder'] : (string)($legacy['bank_holder'] ?? '');
    $v['bank_name']   = !empty($b['bank_name']) ? (string)$b['bank_name'] : (string)($legacy['bank_name'] ?? '');
    $v['iban']        = !empty($b['iban']) ? (string)$b['iban'] : (string)($legacy['iban'] ?? '');
    $v['bic']         = !empty($b['bic']) ? (string)$b['bic'] : (string)($legacy['bic'] ?? '');

    return $v;
  }
}

if (!function_exists('langa_tools_client_site_data_get_company_address_line')) {
  /**
   * Best-effort single-line address based on company fields.
   */
  function langa_tools_client_site_data_get_company_address_line() {
    $c = langa_tools_client_get_site_data('company', array());
    if (!is_array($c)) return '';

    $parts = array();
    foreach (array('address','zip','city','province','country') as $k) {
      $v = trim((string)($c[$k] ?? ''));
      if ($v !== '') $parts[] = $v;
    }
    return implode(', ', $parts);
  }
}

/* =========================================================
 * DATA COMPLETENESS GATE
 * ---------------------------------------------------------
 * All modules require Company + Developer data to be filled.
 * Bank and Shipping are optional.
 * ======================================================= */

if (!function_exists('langa_tools_client_data_required_fields')) {
  /**
   * Returns required fields grouped by section.
   */
  function langa_tools_client_data_required_fields() {
    return array(
      'company' => array(
        'brand'      => 'Company — Brand',
        'legal_name' => 'Company — Legal name',
        'vat'        => 'Company — VAT',
        'sdi'        => 'Company — SDI',
        'address'    => 'Company — Address',
        'zip'        => 'Company — ZIP',
        'city'       => 'Company — City',
        'province'   => 'Company — Province',
        'country'    => 'Company — Country',
        'phone'      => 'Company — Phone',
        'email'      => 'Company — Email',
      ),
      'developer' => array(
        'brand'       => 'Developer — Brand',
        'legal_name'  => 'Developer — Legal name',
        'vat'         => 'Developer — VAT',
        'email'       => 'Developer — Email',
        'phone'       => 'Developer — Phone',
        'address'     => 'Developer — Address',
        'zip'         => 'Developer — ZIP',
        'city'        => 'Developer — City',
        'province'    => 'Developer — Province',
        'country'     => 'Developer — Country',
        'privacy_url' => 'Developer — Privacy URL',
        'terms_url'   => 'Developer — Terms URL',
      ),
    );
  }
}

if (!function_exists('langa_tools_client_data_missing_fields')) {
  /**
   * Returns array of human-readable labels for missing required fields.
   */
  function langa_tools_client_data_missing_fields() {
    $opt = get_option(LANGA_TOOLS_CLIENT_SITE_DATA_OPTION, array());
    if (!is_array($opt)) $opt = array();
    $required = langa_tools_client_data_required_fields();
    $missing = array();

    foreach ($required as $section => $fields) {
      $data = (isset($opt[$section]) && is_array($opt[$section])) ? $opt[$section] : array();
      foreach ($fields as $key => $label) {
        if (trim((string)($data[$key] ?? '')) === '') {
          $missing[] = $label;
        }
      }
    }
    return $missing;
  }
}

if (!function_exists('langa_tools_client_data_complete')) {
  /**
   * Returns true if all required Company + Developer fields are filled.
   * Cached per request via static var.
   */
  function langa_tools_client_data_complete($reset = false) {
    static $memo = null;
    if ($reset) { $memo = null; return true; }
    if ($memo !== null) return $memo;
    $memo = (count(langa_tools_client_data_missing_fields()) === 0);
    return $memo;
  }
}

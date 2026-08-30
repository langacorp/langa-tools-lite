<?php
if (!defined('ABSPATH')) exit;

/**
 * Front UI Improvements (shortcodes + user table columns)
 * - [support_id], [friend_id]
 * - (optional) [breadcrumb] alias to SEO breadcrumbs
 * - Users list columns: Phone, Support ID, Friend ID
 * - User profile fields for those metas
 *
 * Runs only when Admin UX module is enabled (checked in boot.php).
 * Exception: [temp] is ALWAYS registered (content shortcode, must work regardless).
 */

// [temp] is registered in main plugin (langa-tools-client.php) at init:1 — do not register here

function langa_tools_client_adminux_front_ui_boot() {
  static $done = false;
  if ($done) return;
  $done = true;

  // Shortcodes (register on init to be safe)
  add_action('init', function () {
    add_shortcode('langtoli_support_id', 'langa_tools_client_shortcode_support_id');
    add_shortcode('langtoli_friend_id',  'langa_tools_client_shortcode_friend_id');

    // [temp] is registered in main plugin (langa-tools-client.php) — do not override here

    // Breadcrumb alias (kept for backwards compatibility with your content)
    add_shortcode('langtoli_breadcrumb',  'langa_tools_client_shortcode_breadcrumb_alias');
    add_shortcode('langtoli_breadcrumbs', 'langa_tools_client_shortcode_breadcrumb_alias');
  }, 5);

  // Users table columns
  add_filter('manage_users_columns', 'langa_tools_client_adminux_users_columns');
  add_filter('manage_users_custom_column', 'langa_tools_client_adminux_users_custom_column', 10, 3);

  // Profile fields (admin)
  add_action('show_user_profile', 'langa_tools_client_adminux_profile_fields');
  add_action('edit_user_profile', 'langa_tools_client_adminux_profile_fields');
  add_action('personal_options_update', 'langa_tools_client_adminux_profile_fields_save');
  add_action('edit_user_profile_update', 'langa_tools_client_adminux_profile_fields_save');

  // Ensure IDs exist for new users
  add_action('user_register', 'langa_tools_client_adminux_ensure_ids', 10, 1);
  add_action('profile_update', 'langa_tools_client_adminux_ensure_ids', 10, 1);
}

/**
 * Ensure Support/Friend IDs exist.
 * These IDs are used in shortcodes and in the users table.
 */
function langa_tools_client_adminux_ensure_ids($user_id) {
  $user_id = (int) $user_id;
  if ($user_id <= 0) return;

  $support = (string) get_user_meta($user_id, 'support_id', true);
  $friend  = (string) get_user_meta($user_id, 'friend_id', true);

  if ($support !== '' && $friend !== '') return;

  // Stable per-site salt (generated once)
  $salt = get_option('langa_tools_client_id_salt', '');
  if (!is_string($salt) || $salt === '') {
    $salt = wp_generate_password(32, false, false);
    update_option('langa_tools_client_id_salt', $salt, false);
  }

  if ($support === '') {
    $support = substr(md5($salt . '|support|' . $user_id), 0, 12);
    update_user_meta($user_id, 'support_id', $support);
  }
  if ($friend === '') {
    $friend = substr(md5($salt . '|friend|' . $user_id), 0, 12);
    update_user_meta($user_id, 'friend_id', $friend);
  }
}


function langa_tools_client_adminux_is_italian() {
  $loc = function_exists('determine_locale') ? determine_locale() : get_locale();
  return (is_string($loc) && stripos($loc, 'it') === 0);
}

function langa_tools_client_adminux_login_button_html($type, $id_value, $color = '') {
  // Multi-language labels
  $loc = function_exists('determine_locale') ? determine_locale() : get_locale();
  $lang = substr((string)$loc, 0, 2);
  $labels = array(
    'support' => array(
      'it'=>'Accedi per Support ID','en'=>'Login for Support ID','fr'=>'Connexion pour Support ID',
      'es'=>'Acceder para Support ID','de'=>'Anmelden f&uuml;r Support ID',
      'ru'=>'&#1042;&#1086;&#1081;&#1090;&#1080; &#1076;&#1083;&#1103; Support ID',
      'ar'=>'&#1578;&#1587;&#1580;&#1610;&#1604; &#1575;&#1604;&#1583;&#1582;&#1608;&#1604; &#1604;&#1600; Support ID',
    ),
    'friend' => array(
      'it'=>'Accedi per Friend ID','en'=>'Login for Friend ID','fr'=>'Connexion pour Friend ID',
      'es'=>'Acceder para Friend ID','de'=>'Anmelden f&uuml;r Friend ID',
      'ru'=>'&#1042;&#1086;&#1081;&#1090;&#1080; &#1076;&#1083;&#1103; Friend ID',
      'ar'=>'&#1578;&#1587;&#1580;&#1610;&#1604; &#1575;&#1604;&#1583;&#1582;&#1608;&#1604; &#1604;&#1600; Friend ID',
    ),
  );
  $label = isset($labels[$type][$lang]) ? $labels[$type][$lang] : $labels[$type]['en'];

  $url = wp_login_url(get_permalink());

  $color_style = '';
  if ($color !== '') {
    $color = sanitize_hex_color($color);
    if ($color) $color_style = 'color:' . esc_attr($color) . ';';
  }

  return '<span class="langa-id-login" style="display:inline;white-space:nowrap;vertical-align:baseline"><a href="'.esc_url($url).'" style="display:inline;white-space:nowrap;text-decoration:underline;vertical-align:baseline;'.$color_style.'color:'.($color_style ? esc_attr($color) : 'inherit').'">'.$label.'</a></span>';
}

function langa_tools_client_shortcode_support_id($atts=array(), $content=null) {
  $a = shortcode_atts(array('color'=>''), (array)$atts, 'support_id');
  $user_id = get_current_user_id();
  if (!$user_id) {
    return langa_tools_client_adminux_login_button_html('support', '', $a['color']);
  }
  langa_tools_client_adminux_ensure_ids($user_id);
  $val = get_user_meta($user_id, 'support_id', true);
  return langa_tools_client_adminux_render_id_or_login('support', (string)$val, $a['color']);
}

function langa_tools_client_shortcode_friend_id($atts=array(), $content=null) {
  $a = shortcode_atts(array('color'=>''), (array)$atts, 'friend_id');
  $user_id = get_current_user_id();
  if (!$user_id) {
    return langa_tools_client_adminux_login_button_html('friend', '', $a['color']);
  }
  langa_tools_client_adminux_ensure_ids($user_id);
  $val = get_user_meta($user_id, 'friend_id', true);
  return langa_tools_client_adminux_render_id_or_login('friend', (string)$val, $a['color']);
}

function langa_tools_client_shortcode_breadcrumb_alias($atts=array(), $content=null) {
  // Prefer SEO shortcode if present
  if (shortcode_exists('langa_breadcrumbs')) {
    // Avoid nested shortcodes recursion
    $sep = isset($atts['separator']) ? $atts['separator'] : null;
    $class = isset($atts['class']) ? $atts['class'] : null;
    $home = isset($atts['home']) ? $atts['home'] : null;
    $parts = array();
    if ($sep !== null) $parts[] = 'separator="'.esc_attr($sep).'"';
    if ($class !== null) $parts[] = 'class="'.esc_attr($class).'"';
    if ($home !== null) $parts[] = 'home="'.esc_attr($home).'"';
    return do_shortcode('[langa_breadcrumbs '.implode(' ', $parts).']');
  }
  return '';
}

/**
 * [temp date_from="10/10/2025" date_to="15/10/2025"]...[/temp]
 * Renders content ONLY inside the date range (site timezone).
 * Outside the range it returns an empty string (no output).
 */
function langa_tools_client_shortcode_temp($atts = array(), $content = null) {
  $atts = shortcode_atts(array(
    'date_from' => '',
    'date_to'   => '',
  ), $atts, 'temp');

  $from = langa_tools_client_temp_parse_date((string) $atts['date_from'], false);
  $to   = langa_tools_client_temp_parse_date((string) $atts['date_to'], true);

  // If dates are missing/invalid, render nothing.
  if ($from === null || $to === null) return '';
  if ($to < $from) return '';

  // Use real UTC timestamp (time()) — parse_date also returns real UTC via getTimestamp()
  $now = time();
  if ($now < $from || $now > $to) return '';

  if ($content === null || $content === '') return '';
  return do_shortcode($content);
}

/**
 * Parse a date string in common formats (IT-friendly) in site timezone.
 * Supported:
 * - d/m/Y, j/n/Y
 * - d-m-Y, j-n-Y
 * - d.m.Y, j.n.Y
 * - Y-m-d, Y/m/d
 */
function langa_tools_client_temp_parse_date($raw, $is_end = false) {
  $raw = trim((string) $raw);
  if ($raw === '') return null;

  // Strip invisible/control chars
  $raw_norm = preg_replace('/[\x00-\x1f\x7f]/', '', $raw);
  $raw_norm = trim($raw_norm);
  if ($raw_norm === '') return null;

  $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
  $formats = array(
    'd/m/Y', 'j/n/Y',
    'd-m-Y', 'j-n-Y',
    'd.m.Y', 'j.n.Y',
    'Y-m-d', 'Y/m/d',
  );

  $dt = null;
  foreach ($formats as $fmt) {
    $try = DateTime::createFromFormat('!' . $fmt, $raw_norm, $tz);
    if ($try instanceof DateTime) {
      $errs = DateTime::getLastErrors();
      // PHP 8.2+ returns false when no errors
      if ($errs === false || (empty($errs['warning_count']) && empty($errs['error_count']))) {
        $dt = $try;
        break;
      }
    }
  }

  if (!$dt) return null;
  if ($is_end) {
    $dt->setTime(23, 59, 59);
  } else {
    $dt->setTime(0, 0, 0);
  }
  return (int) $dt->getTimestamp();
}

function langa_tools_client_adminux_users_columns($columns) {
  // Insert after email if possible
  $new = array();
  foreach ($columns as $k=>$v) {
    $new[$k] = $v;
    if ($k === 'email') {
      $new['langa_phone'] = 'Phone';
      $new['support_id'] = 'Support ID';
      $new['friend_id']  = 'Friend ID';
    }
  }
  if (!isset($new['langa_phone'])) {
    $new['langa_phone'] = 'Phone';
    $new['support_id'] = 'Support ID';
    $new['friend_id']  = 'Friend ID';
  }
  return $new;
}

function langa_tools_client_adminux_users_custom_column($value, $column_name, $user_id) {
  if ($column_name === 'langa_phone') {
    $val = get_user_meta($user_id, 'phone', true);
    if ($val === '') $val = get_user_meta($user_id, 'telefono', true);
    if ($val === '') $val = get_user_meta($user_id, 'langa_phone', true);
    return $val !== '' ? esc_html($val) : '';
  }
  if ($column_name === 'support_id') {
    langa_tools_client_adminux_ensure_ids($user_id);
    $val = get_user_meta($user_id, 'support_id', true);
    $fmt = langa_tools_client_adminux_format_id('support', (string)$val);
    return $fmt !== '' ? esc_html($fmt) : '';
  }
  if ($column_name === 'friend_id') {
    langa_tools_client_adminux_ensure_ids($user_id);
    $val = get_user_meta($user_id, 'friend_id', true);
    $fmt = langa_tools_client_adminux_format_id('friend', (string)$val);
    return $fmt !== '' ? esc_html($fmt) : '';
  }
  return $value;
}

function langa_tools_client_adminux_profile_fields($user) {
  if (!current_user_can('edit_user', $user->ID)) return;
  $phone = get_user_meta($user->ID, 'phone', true);
  if ($phone === '') $phone = get_user_meta($user->ID, 'telefono', true);
  if ($phone === '') $phone = get_user_meta($user->ID, 'langa_phone', true);
  $support_id = get_user_meta($user->ID, 'support_id', true);
  $friend_id  = get_user_meta($user->ID, 'friend_id', true);

  // Auto-generate if missing so the admin sees them immediately.
  if ($support_id === '' || $friend_id === '') {
    langa_tools_client_adminux_ensure_ids($user->ID);
    $support_id = get_user_meta($user->ID, 'support_id', true);
    $friend_id  = get_user_meta($user->ID, 'friend_id', true);
  }
  ?>
  <h2>LANGA — Dati aggiuntivi</h2>
  <table class="form-table" role="presentation">
    <tr>
      <th><label for="langa_phone">Phone</label></th>
      <td><input type="text" name="langa_phone" id="langa_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
    </tr>
    <tr>
      <th><label for="support_id">Support ID</label></th>
      <td><input type="text" name="support_id" id="support_id" value="<?php echo esc_attr($support_id); ?>" class="regular-text" /></td>
    </tr>
    <tr>
      <th><label for="friend_id">Friend ID</label></th>
      <td><input type="text" name="friend_id" id="friend_id" value="<?php echo esc_attr($friend_id); ?>" class="regular-text" /></td>
    </tr>
  </table>
  <?php
}

function langa_tools_client_adminux_profile_fields_save($user_id) {
  if (!current_user_can('edit_user', $user_id)) return;
  if (isset($_POST['langa_phone'])) {
    update_user_meta($user_id, 'langa_phone', sanitize_text_field(wp_unslash($_POST['langa_phone'])));
  }
  if (isset($_POST['support_id'])) {
    update_user_meta($user_id, 'support_id', sanitize_text_field(wp_unslash($_POST['support_id'])));
  }
  if (isset($_POST['friend_id'])) {
    update_user_meta($user_id, 'friend_id', sanitize_text_field(wp_unslash($_POST['friend_id'])));
  }
}


// --- Helpers for Support/Friend IDs ---
if (!function_exists('langa_tools_client_adminux_format_id')) {
  function langa_tools_client_adminux_format_id($type, $raw) {
    $raw = preg_replace('/[^a-zA-Z0-9]/', '', (string) $raw);
    if ($raw === '') return '';
    $code = strtoupper(substr($raw, 0, 6));
    $prefix = ($type === 'support') ? 'SU-' : 'FR-';
    return $prefix . $code;
  }
}

if (!function_exists('langa_tools_client_adminux_render_id_or_login')) {
  /**
   * For shortcodes: if user is logged-in show the code, otherwise show a login link.
   */
  function langa_tools_client_adminux_render_id_or_login($type, $raw_value, $color = '') {
    if (is_user_logged_in()) {
      $fmt = langa_tools_client_adminux_format_id($type, $raw_value);
      if ($fmt === '') return '';
      $color_style = '';
      if ($color !== '') {
        $c = sanitize_hex_color($color);
        if ($c) $color_style = 'color:' . esc_attr($c) . ';';
      }
      return '<span class="langa-id-code" style="display:inline;white-space:nowrap;vertical-align:baseline;font-family:monospace;font-size:inherit;line-height:inherit;'.$color_style.'">'.esc_html($fmt).'</span>';
    }
    return langa_tools_client_adminux_login_button_html($type, '', $color);
  }
}

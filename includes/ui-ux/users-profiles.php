<?php
if (!defined('ABSPATH')) exit;

/**
 * LANGA Users Profiles
 *
 * Predefined roles (simple & predictable):
 * - LANGA Editor 1: Articoli (post) + Media (+ tassonomie collegate)
 * - LANGA Editor 2: Articoli + Pagine + Media (+ tassonomie collegate)
 * - LANGA Editor 3: Articoli + Pagine + Prodotti + Media (+ tassonomie collegate)
 *
 * Granular (Custom):
 * - AdminUX > Users can assign a CUSTOM profile (checkbox areas).
 * - Each unique permission set generates a dedicated role (slug based on a hash).
 *
 * Roles are synced from AdminUX settings.
 */


if (!function_exists('langa_tools_client_adminux_users_profiles_register_roles')) {
  function langa_tools_client_adminux_users_profiles_register_roles() {
    // Build caps dynamically from post type + attached taxonomies
    $caps1 = langa_tools_client_adminux_users_profiles_caps_from_areas(array(
      'posts' => 1,
      'media' => 1,
    ));

    $caps2 = langa_tools_client_adminux_users_profiles_caps_from_areas(array(
      'posts' => 1,
      'pages' => 1,
      'media' => 1,
    ));

$caps3 = langa_tools_client_adminux_users_profiles_caps_from_areas(array(
      'posts' => 1,
      'pages' => 1,
      'products' => 1,
      'media' => 1,
    ));

// Default: no comments moderation for LANGA editors
    unset($caps1['moderate_comments'], $caps2['moderate_comments']);
    // keep moderate_comments for Editor 3 (useful for WooCommerce product reviews submenu)

    // Ensure predefined roles exist + replace capabilities (prevents legacy caps from sticking)
    langa_tools_client_adminux_users_profiles_ensure_role('langa_editor_1', 'LANGA Editor 1 — Articoli', $caps1, true);
    langa_tools_client_adminux_users_profiles_ensure_role('langa_editor_2', 'LANGA Editor 2 — Articoli + Pagine', $caps2, true);
    langa_tools_client_adminux_users_profiles_ensure_role('langa_editor_3', 'LANGA Editor 3 — Articoli + Pagine + Prodotti', $caps3, true);
  }
}

if (!function_exists('langa_tools_client_adminux_users_profiles_ensure_role')) {
  function langa_tools_client_adminux_users_profiles_ensure_role($slug, $label, $caps, $replace_all = false) {
    $slug = sanitize_key($slug);
    $role = get_role($slug);

    if (!$role) {
      add_role($slug, $label, $caps);
      return;
    }

    // Optionally drop caps not in the desired list (used for CUSTOM roles)
    if ($replace_all && !empty($role->capabilities) && is_array($role->capabilities)) {
      foreach (array_keys($role->capabilities) as $cap) {
        $cap = sanitize_key((string)$cap);
        if ($cap === '') continue;
        if (!isset($caps[$cap]) || !$caps[$cap]) {
          $role->remove_cap($cap);
        }
      }
    }

    // Add desired caps
    foreach ($caps as $cap => $grant) {
      $cap = sanitize_key($cap);
      if ($cap === '') continue;
      if ($grant) $role->add_cap($cap);
      else $role->remove_cap($cap);
    }
  }
}

if (!function_exists('langa_tools_client_adminux_users_profiles_portfolio_post_type')) {
  function langa_tools_client_adminux_users_profiles_portfolio_post_type($preferred = 'portfolio') {
    $preferred = sanitize_key((string)$preferred);
    $candidates = array();
    if ($preferred) $candidates[] = $preferred;
    foreach (array('portfolio','portfolios','project','projects') as $c) {
      if (!in_array($c, $candidates, true)) $candidates[] = $c;
    }
    foreach ($candidates as $pt) {
      if ($pt && post_type_exists($pt)) return $pt;
    }
    return '';
  }
}

if (!function_exists('langa_tools_client_adminux_users_profiles_caps_from_areas')) {
  function langa_tools_client_adminux_users_profiles_caps_from_areas($areas, $portfolio_pt = 'portfolio') {
    if (!is_array($areas)) $areas = array();

    $caps = array(
      'read' => true,
      // Allow trusted staff roles to bypass Maintenance mode on the frontend.
      // This avoids locking editors out of the site while maintenance is active.
      'langa_bypass_maintenance' => true,
    );

    // Media
    if (!empty($areas['media'])) {
      $caps['upload_files'] = true;
    }

    // WP Tools (Strumenti) — optional (Custom advanced)
    if (!empty($areas['wp_tools'])) {
      $caps['langa_show_tools_menu'] = true;
      $caps['import'] = true;
      $caps['export'] = true;
    }

    // Comments menu — optional (Custom advanced)
    if (!empty($areas['comments'])) {
      $caps['langa_show_comments_menu'] = true;
      $caps['moderate_comments'] = true;
    }

    $post_types = array();
    if (!empty($areas['posts'])) $post_types[] = 'post';
    if (!empty($areas['pages'])) $post_types[] = 'page';

    if (!empty($areas['portfolio'])) {
      $pt = langa_tools_client_adminux_users_profiles_portfolio_post_type($portfolio_pt);
      if ($pt) $post_types[] = $pt;
    }

    if (!empty($areas['products']) && post_type_exists('product')) {
      $post_types[] = 'product';
    }

    
    // Extra custom post types (Custom profile only)
    if (!empty($areas['extra_pts']) && is_array($areas['extra_pts'])) {
      foreach ($areas['extra_pts'] as $xpt) {
        $xpt = sanitize_key((string)$xpt);
        if ($xpt === '' || $xpt === 'attachment') continue;
        if (in_array($xpt, array('post','page','product'), true)) continue;
        if (post_type_exists($xpt)) $post_types[] = $xpt;
      }
    }

    // Extra capabilities (legacy/advanced) — still supported if present in stored settings.
    if (!empty($areas['extra_caps']) && is_array($areas['extra_caps'])) {
      $deny = array(
        'manage_options','activate_plugins','install_plugins','update_plugins','delete_plugins','edit_plugins',
        'update_core','edit_users','create_users','delete_users','promote_users','list_users','remove_users',
        'switch_themes','edit_theme_options','edit_themes','install_themes','update_themes',
        'unfiltered_html','unfiltered_upload'
      );
      foreach ($areas['extra_caps'] as $capx) {
        $capx = sanitize_key((string)$capx);
        if ($capx === '' || in_array($capx, $deny, true)) continue;
        $caps[$capx] = true;
      }
    }

    $post_types = array_values(array_unique(array_filter($post_types)));

    // Marker caps used by menu cleanup to show ONLY explicit post types.
    // This prevents plugin CPTs that reuse core caps (e.g. edit_posts) from appearing
    // inside the default 1/2/3 profiles.
    foreach ($post_types as $ptm) {
      $ptm = sanitize_key((string)$ptm);
      if ($ptm === '') continue;
      $caps['langa_pt__' . $ptm] = true;
    }

    // Collect caps for post types + attached taxonomies
    foreach ($post_types as $pt) {
      $obj = get_post_type_object($pt);
      if ($obj && isset($obj->cap) && is_object($obj->cap)) {
        foreach ((array)$obj->cap as $cap) {
          $cap = sanitize_key((string)$cap);
          if ($cap) $caps[$cap] = true;
        }
      }

      $taxes = get_object_taxonomies($pt, 'objects');
      if (is_array($taxes)) {
        foreach ($taxes as $tax) {
          // Taxonomy caps are typically stored as an object in WP core.
          if (!$tax || !isset($tax->cap)) continue;
          foreach ((array)$tax->cap as $cap) {
            $cap = sanitize_key((string)$cap);
            if ($cap) $caps[$cap] = true;
          }
        }
      }
    }

    // WooCommerce: ensure product + product-taxonomy caps exist even if post types load late.
    // Needed to show Products submenus (Categorie, Tag, Attributi, Recensioni, ecc.).
    if (!empty($areas['products'])) {
      $wc_product_caps = array(
        // Product post type caps
        'edit_products','edit_others_products','edit_private_products','edit_published_products',
        'publish_products','read_private_products',
        'delete_products','delete_private_products','delete_published_products','delete_others_products',
        // Product taxonomies caps (product_cat, product_tag, attributes, brands, etc. usually map here)
        'manage_product_terms','edit_product_terms','delete_product_terms','assign_product_terms',
        // Reviews submenu typically relies on comment moderation
        'moderate_comments',
      );
      foreach ($wc_product_caps as $c) {
        $c = sanitize_key((string)$c);
        if ($c) $caps[$c] = true;
      }
    }

    // WooCommerce Orders (Custom only, optional)
    // We expose Orders as a dedicated top-level menu (Ordini) without granting full WooCommerce settings.
    if (!empty($areas['wc_orders'])) {
      $caps['langa_wc_orders'] = true;
      $order_caps = array(
        // Core order post type caps
        'edit_shop_orders','edit_others_shop_orders','edit_private_shop_orders','edit_published_shop_orders',
        'publish_shop_orders','read_private_shop_orders',
        'delete_shop_orders','delete_private_shop_orders','delete_published_shop_orders','delete_others_shop_orders',
        'edit_published_shop_orders','edit_private_shop_orders',
        // Allow order notes/comments moderation if needed
        'moderate_comments',
      );
      foreach ($order_caps as $c) {
        $c = sanitize_key((string)$c);
        if ($c) $caps[$c] = true;
      }
    }

    // Extra plugin/admin pages (Custom only).
    // Stored as an array of items: array(array('slug' => '...', 'cap' => '...'), ...)
    // We grant the required capabilities so the selected plugin pages work.
    if (!empty($areas['menu_pages']) && is_array($areas['menu_pages'])) {
      $deny = array(
        'manage_options','activate_plugins','install_plugins','update_plugins','delete_plugins','edit_plugins',
        'update_core','edit_users','create_users','delete_users','promote_users','list_users','remove_users',
        'switch_themes','edit_theme_options','edit_themes','install_themes','update_themes',
        'unfiltered_html','unfiltered_upload'
      );
      foreach ($areas['menu_pages'] as $it) {
        if (!is_array($it)) continue;
        $capx = isset($it['cap']) ? (string)$it['cap'] : '';
        $capx = trim($capx);
        if ($capx === '') continue;
        // keep underscores + hyphens
        $capx = preg_replace('/[^a-zA-Z0-9_\-]/', '', $capx);
        if ($capx === '' || in_array($capx, $deny, true)) continue;
        $caps[$capx] = true;
      }
    }


    // Normalize
    ksort($caps);
    return $caps;
  }
}

if (!function_exists('langa_tools_client_adminux_users_profiles_custom_role_slug')) {
  function langa_tools_client_adminux_users_profiles_custom_role_slug($caps) {
    if (!is_array($caps)) $caps = array();
    ksort($caps);
    $hash = sha1(wp_json_encode($caps));
    return 'langa_editor_c_' . substr($hash, 0, 8);
  }
}

if (!function_exists('langa_tools_client_adminux_users_profiles_prepare_targets')) {
  function langa_tools_client_adminux_users_profiles_prepare_targets($settings) {
    if (!is_array($settings)) $settings = array();

    $lvl1 = isset($settings['langa_editor_1_users']) && is_array($settings['langa_editor_1_users']) ? $settings['langa_editor_1_users'] : array();
    $lvl2 = isset($settings['langa_editor_2_users']) && is_array($settings['langa_editor_2_users']) ? $settings['langa_editor_2_users'] : array();
    $lvl3 = isset($settings['langa_editor_3_users']) && is_array($settings['langa_editor_3_users']) ? $settings['langa_editor_3_users'] : array();

    $map = array();
    // Priority: 3 > 2 > 1
    foreach ($lvl1 as $id) { $id = (int)$id; if ($id>0) $map[$id] = 'langa_editor_1'; }
    foreach ($lvl2 as $id) { $id = (int)$id; if ($id>0) $map[$id] = 'langa_editor_2'; }
    foreach ($lvl3 as $id) { $id = (int)$id; if ($id>0) $map[$id] = 'langa_editor_3'; }

    // Custom overrides everything
    $custom_specs = isset($settings['langa_custom_users']) && is_array($settings['langa_custom_users']) ? $settings['langa_custom_users'] : array();
    $custom_roles = array();

    if (is_array($custom_specs)) {
      foreach ($custom_specs as $uid => $spec) {
        $uid = (int) $uid;
        if ($uid <= 0) continue;
        if (!is_array($spec)) $spec = array();

        $areas = isset($spec['areas']) && is_array($spec['areas']) ? $spec['areas'] : array();
        $pt = isset($spec['portfolio_pt']) ? sanitize_key((string)$spec['portfolio_pt']) : (isset($settings['users_apply_portfolio_pt']) ? sanitize_key((string)$settings['users_apply_portfolio_pt']) : 'portfolio');
        if ($pt === '') $pt = 'portfolio';

        $caps = langa_tools_client_adminux_users_profiles_caps_from_areas($areas, $pt);
        $role_slug = langa_tools_client_adminux_users_profiles_custom_role_slug($caps);

        $map[$uid] = $role_slug;
        $custom_roles[$role_slug] = $caps;
      }
    }

    return array($map, $custom_roles);
  }
}

if (!function_exists('langa_tools_client_adminux_users_profiles_sync_roles')) {
  function langa_tools_client_adminux_users_profiles_sync_roles($settings = null, $force = false) {
    if (!is_admin()) return;
    if (!function_exists('get_users')) return;

    if ($settings === null) {
      $settings = get_option('langa_tools_adminux_settings', array());
    }
    if (!is_array($settings)) $settings = array();

    list($targets, $custom_roles) = langa_tools_client_adminux_users_profiles_prepare_targets($settings);

    // Avoid heavy work if nothing configured
    if (empty($targets) && !$force) return;

    $hash = md5(wp_json_encode(array($targets, array_keys($custom_roles))));
    $prev_hash = (string) get_option('langa_tools_users_profiles_hash', '');
    if (!$force && $hash === $prev_hash) return;

    // Ensure predefined roles exist
    langa_tools_client_adminux_users_profiles_register_roles();

    // Ensure custom roles exist (replace caps to be safe)
    if (is_array($custom_roles)) {
      foreach ($custom_roles as $slug => $caps) {
        langa_tools_client_adminux_users_profiles_ensure_role($slug, 'LANGA Editor Custom', $caps, true);
      }
    }

    $all_users = get_users(array('fields' => array('ID')));

    foreach ($all_users as $u) {
      $uid = (int) $u->ID;
      if ($uid <= 0) continue;

      $user = new WP_User($uid);
      if (!$user || empty($user->ID)) continue;

      // Never touch administrators
      if (in_array('administrator', (array)$user->roles, true)) continue;

      $desired = isset($targets[$uid]) ? (string)$targets[$uid] : '';

      $has_langa = false;
      foreach ((array)$user->roles as $r) {
        if ($r === 'langa_editor_1' || $r === 'langa_editor_2' || $r === 'langa_editor_3' || strpos((string)$r, 'langa_editor_c_') === 0) {
          $has_langa = true;
          break;
        }
      }

      if ($desired === '') {
        if ($has_langa) {
          foreach ((array)$user->roles as $r) {
            if ($r === 'langa_editor_1' || $r === 'langa_editor_2' || $r === 'langa_editor_3' || strpos((string)$r, 'langa_editor_c_') === 0) {
              $user->remove_role($r);
            }
          }
          // Safety fallback: if no role left, set subscriber
          if (empty($user->roles)) {
            $user->set_role('subscriber');
          }
        }
        continue;
      }

      // Apply desired role: replace roles for safety (avoid role union granting extra caps)
      $user->set_role($desired);
    }

    update_option('langa_tools_users_profiles_hash', $hash, false);
  }
}

// Register predefined roles early
add_action('init', 'langa_tools_client_adminux_users_profiles_register_roles', 15);

// Sync when settings update (instant)
add_action('update_option_langa_tools_adminux_settings', function ($old_value, $value, $option) {
  if (!is_admin()) return;
  if (!current_user_can('manage_options')) return;
  langa_tools_client_adminux_users_profiles_sync_roles($value, true);
}, 10, 3);

// Safety sync once per day (in case of manual DB edits)
add_action('admin_init', function () {
  if (!current_user_can('manage_options')) return;
  $last = (int) get_transient('langa_tools_users_profiles_last_sync');
  if ($last && (time() - $last) < DAY_IN_SECONDS) return;
  set_transient('langa_tools_users_profiles_last_sync', time(), DAY_IN_SECONDS);
  langa_tools_client_adminux_users_profiles_sync_roles(null, false);
});

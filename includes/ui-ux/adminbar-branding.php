<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin bar branding + safety defaults.
 *
 * Goals:
 * - Always show the admin bar on the frontend for trusted staff profiles
 *   (LANGA Editor 1/2/3 and Custom profiles) so they can jump back to wp-admin.
 * - Replace the WordPress logo in the admin bar with LANGA Tools Client brand.
 *   (We overwrite the core `wp-logo` node so it stays FIRST.)
 * - Remove WP logo submenu (About/Docs/etc.)
 */

// Force frontend admin bar for staff profiles
add_filter('show_admin_bar', function($show){
  if (!is_user_logged_in()) return $show;

  // Admins follow their own profile preference.
  if (current_user_can('manage_options')) return $show;

  $u = wp_get_current_user();
  $roles = ($u && isset($u->roles)) ? (array) $u->roles : array();
  $subscriber_only = (count($roles) === 1 && in_array('subscriber', $roles, true));
  if ($subscriber_only) return $show;

  // Staff: default admin bar ON unless the user explicitly disabled it.
  $opt = get_user_option('show_admin_bar_front', get_current_user_id());
  if ($opt === null || $opt === '') return true;

  return $show;
}, 20);

add_action('admin_bar_menu', function($wp_admin_bar){
  if (!is_admin_bar_showing() || !is_object($wp_admin_bar)) return;

  // Brand image (fixed)
  $logo = LANGA_TOOLS_CLIENT_URL . 'assets/images/plugin-icon.svg';

  // Back-compat: remove older custom node id (if any)
  $wp_admin_bar->remove_node('langa-tools-client-logo');
  $wp_admin_bar->remove_node('langa-tools-client-logo-old');

  // Overwrite the WP logo node (leftmost) so our logo is always "davanti a tutto"
  $wp_admin_bar->add_node(array(
    'id'    => 'wp-logo',
    'title' => '<img src="' . esc_url($logo) . '" alt="LANGA" class="langa-tools-client-adminbar-logo" />',
    'href'  => (is_admin() ? home_url('/') : admin_url()),
    'meta'  => array(
      'title' => 'LANGA Tools',
      'class' => 'langa-tools-client-adminbar-node',
    ),
  ));

  // Remove WP logo submenu nodes (About/Docs/etc.)
  foreach (array(
    'about',
    'contribute',
    'wporg',
    'documentation',
    'support-forums',
    'feedback',
    'learn',
  ) as $id) {
    $wp_admin_bar->remove_node($id);
  }

  // Noise cleanup for limited staff
  if (is_user_logged_in() && !current_user_can('manage_options')) {
    // Comments bubble
    if (!current_user_can('moderate_comments')) {
      $wp_admin_bar->remove_node('comments');
    }

    // "New" menu items for post types the user cannot create
    $new = $wp_admin_bar->get_node('new-content');
    if ($new) {
      if (!current_user_can('edit_products')) {
        $wp_admin_bar->remove_node('new-product');
        $wp_admin_bar->remove_node('new-product_variation');
      }
      if (!current_user_can('edit_portfolios')) {
        $wp_admin_bar->remove_node('new-portfolio');
      }
    }
  }
}, 100);

// CSS for the brand image + make sure the bar stays above any theme overlays
add_action('admin_enqueue_scripts', 'langa_tools_client_adminbar_brand_css');
add_action('wp_enqueue_scripts', 'langa_tools_client_adminbar_brand_css');

function langa_tools_client_adminbar_brand_css(){
  if (!is_admin_bar_showing()) return;
  $css = "#wpadminbar{ z-index: 99999999 !important; }\n";
  $css .= "#wpadminbar #wp-admin-bar-wp-logo > .ab-item{ padding:0 8px !important; position:relative; z-index: 999999999 !important; }\n";
  $css .= "#wpadminbar .langa-tools-client-adminbar-logo{ height:20px; width:auto; vertical-align:middle; margin-top:-2px; }\n";
  wp_register_style('langa-adminbar-brand', false, array(), '1.0');
  wp_enqueue_style('langa-adminbar-brand');
  wp_add_inline_style('langa-adminbar-brand', $css);
}

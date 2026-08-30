<?php
if (!defined('ABSPATH')) exit;

/**
 * Ghost pages
 *
 * Adds a "Ghost" flag to posts/pages/products/media and to taxonomies.
 * When enabled, items flagged as Ghost are hidden from:
 *  - frontend single views (returns 404)
 *  - frontend searches/archives (excluded from queries)
 *  - Visual Sitemap (handled by exclusions in visual-sitemap.php)
 */

function langa_tools_client_ghost_pages_enabled() {
  $s = get_option('langa_tools_adminux_settings', array());
  if (!is_array($s)) $s = array();
  return !empty($s['ghost_pages']);
}

function langa_tools_client_ghost_pages_boot() {
  if (!langa_tools_client_ghost_pages_enabled()) return;

  // Post meta box
  add_action('add_meta_boxes', 'langa_tools_client_ghost_pages_add_metabox', 10);
  add_action('save_post', 'langa_tools_client_ghost_pages_save_post', 10, 2);

  // Term meta fields for public taxonomies
  add_action('init', function(){
    $taxes = get_taxonomies(array('public' => true, 'show_ui' => true), 'names');
    if (!is_array($taxes)) $taxes = array();
    foreach ($taxes as $tax) {
      add_action($tax . '_add_form_fields', 'langa_tools_client_ghost_pages_term_add_field');
      add_action($tax . '_edit_form_fields', 'langa_tools_client_ghost_pages_term_edit_field', 10, 2);
      add_action('created_' . $tax, 'langa_tools_client_ghost_pages_term_save', 10, 2);
      add_action('edited_' . $tax, 'langa_tools_client_ghost_pages_term_save', 10, 2);
    }
  }, 20);

  // Exclude from main frontend queries (search/archives/home)
  add_action('pre_get_posts', 'langa_tools_client_ghost_pages_pre_get_posts', 10);

  // Safety net: remove ghost posts from any frontend query output
  add_filter('the_posts', 'langa_tools_client_ghost_pages_filter_the_posts', 10, 2);

  // 404 on single view of ghost post or ghost term archive
  add_action('template_redirect', 'langa_tools_client_ghost_pages_template_redirect', 0);
}
add_action('plugins_loaded', 'langa_tools_client_ghost_pages_boot', 20);

function langa_tools_client_ghost_pages_add_metabox() {
  if (!langa_tools_client_ghost_pages_enabled()) return;

  $types = get_post_types(array('public' => true, 'show_ui' => true), 'names');
  if (!is_array($types)) $types = array();

  // Remove a few internal ones
  foreach (array('revision','nav_menu_item','custom_css','customize_changeset','oembed_cache') as $skip) {
    unset($types[$skip]);
  }

  foreach ($types as $pt) {
    add_meta_box(
      'langa_ghost_page',
      'Ghost',
      'langa_tools_client_ghost_pages_metabox_html',
      $pt,
      'side',
      'high'
    );
  }
}

function langa_tools_client_ghost_pages_metabox_html($post) {
  $val = (int) get_post_meta($post->ID, '_langa_ghost_hide', true) === 1 ? 1 : 0;
  wp_nonce_field('langa_ghost_page_save', 'langa_ghost_page_nonce');
  echo '<p style="margin:0 0 8px;">';
  echo '<label><input type="checkbox" name="langa_ghost_hide" value="1" ' . checked($val, 1, false) . '> Nascondi (Ghost)</label>';
  echo '</p>';
  echo '<p class="description" style="margin:0;">Se attivo, il contenuto viene escluso dal front (singolo + ricerche/archivi) e dalla Visual Sitemap.</p>';
}

function langa_tools_client_ghost_pages_save_post($post_id, $post) {
  if (!langa_tools_client_ghost_pages_enabled()) return;
  if (!is_object($post)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (wp_is_post_revision($post_id)) return;

  // Nonce
  if (!isset($_POST['langa_ghost_page_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['langa_ghost_page_nonce'])), 'langa_ghost_page_save')) {
    return;
  }

  // Cap
  $ptype = get_post_type($post_id);
  if ($ptype && !current_user_can('edit_post', $post_id)) return;

  $is_ghost = !empty($_POST['langa_ghost_hide']) ? 1 : 0;
  if ($is_ghost) {
    update_post_meta($post_id, '_langa_ghost_hide', 1);
  } else {
    delete_post_meta($post_id, '_langa_ghost_hide');
  }
}

function langa_tools_client_ghost_pages_term_add_field($taxonomy) {
  if (!langa_tools_client_ghost_pages_enabled()) return;
  echo '<div class="form-field term-ghost-wrap">';
  echo '<label for="langa_ghost_hide">Ghost</label>';
  echo '<label style="display:block;margin-top:6px;"><input type="checkbox" name="langa_ghost_hide" value="1"> Nascondi (Ghost)</label>';
  echo '<p class="description">Se attivo, la tassonomia e le sue pagine archivio sono nascoste dal front e dalla Visual Sitemap.</p>';
  echo '</div>';
}

function langa_tools_client_ghost_pages_term_edit_field($term, $taxonomy) {
  if (!langa_tools_client_ghost_pages_enabled()) return;
  $val = (int) get_term_meta($term->term_id, 'langa_ghost_hide', true) === 1 ? 1 : 0;
  echo '<tr class="form-field term-ghost-wrap">';
  echo '<th scope="row"><label for="langa_ghost_hide">Ghost</label></th>';
  echo '<td>';
  echo '<label><input type="checkbox" name="langa_ghost_hide" value="1" ' . checked($val, 1, false) . '> Nascondi (Ghost)</label>';
  echo '<p class="description">Se attivo, la tassonomia e le sue pagine archivio sono nascoste dal front e dalla Visual Sitemap.</p>';
  echo '</td></tr>';
}

function langa_tools_client_ghost_pages_term_save($term_id, $tt_id) {
  if (!langa_tools_client_ghost_pages_enabled()) return;
  if (!current_user_can('manage_categories')) return;
  $is_ghost = !empty($_POST['langa_ghost_hide']) ? 1 : 0;
  if ($is_ghost) {
    update_term_meta($term_id, 'langa_ghost_hide', 1);
  } else {
    delete_term_meta($term_id, 'langa_ghost_hide');
  }
}

function langa_tools_client_ghost_pages_pre_get_posts($q) {
  if (!langa_tools_client_ghost_pages_enabled()) return;
  if (is_admin()) return;
  if (!is_object($q)) return;

  // Only affect typical frontend discovery queries
  $is_discovery = ($q->is_search() || $q->is_archive() || $q->is_home() || $q->is_feed());
  if (!$is_discovery) return;

  $meta_query = $q->get('meta_query');
  if (!is_array($meta_query)) $meta_query = array();

  // Exclude _langa_ghost_hide=1 while still keeping posts without the meta.
  $meta_query[] = array(
    'relation' => 'OR',
    array(
      'key' => '_langa_ghost_hide',
      'compare' => 'NOT EXISTS',
    ),
    array(
      'key' => '_langa_ghost_hide',
      'value' => '1',
      'compare' => '!=',
    ),
  );

  $q->set('meta_query', $meta_query);
}

function langa_tools_client_ghost_pages_filter_the_posts($posts, $q) {
  if (!langa_tools_client_ghost_pages_enabled()) return $posts;
  if (is_admin()) return $posts;
  if (empty($posts) || !is_array($posts)) return $posts;

  $filtered = array();
  foreach ($posts as $p) {
    if (!is_object($p) || empty($p->ID)) continue;
    $is_ghost = (int) get_post_meta($p->ID, '_langa_ghost_hide', true) === 1;
    if (!$is_ghost) $filtered[] = $p;
  }
  return $filtered;
}

function langa_tools_client_ghost_pages_template_redirect() {
  if (!langa_tools_client_ghost_pages_enabled()) return;
  if (is_admin()) return;

  // Single posts/pages/attachments
  if (is_singular()) {
    $id = get_queried_object_id();
    if ($id && (int)get_post_meta($id, '_langa_ghost_hide', true) === 1) {
      global $wp_query;
      if (is_object($wp_query)) $wp_query->set_404();
      status_header(404);
      nocache_headers();
      include get_query_template('404');
      exit;
    }
  }

  // Term archives (category/tag/tax)
  if (is_category() || is_tag() || is_tax()) {
    $term = get_queried_object();
    if ($term && isset($term->term_id) && (int)get_term_meta((int)$term->term_id, 'langa_ghost_hide', true) === 1) {
      global $wp_query;
      if (is_object($wp_query)) $wp_query->set_404();
      status_header(404);
      nocache_headers();
      include get_query_template('404');
      exit;
    }
  }
}

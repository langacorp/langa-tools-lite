<?php
if (!defined('ABSPATH')) exit;

/**
 * Visual Sitemap
 * - Shortcode: [langa_visual_sitemap]
 * - Auto pages (WordPress pages) when enabled:
 *   /sitemap  + (if Polylang) /{lang}/sitemap
 *
 * Goal:
 * - Uses the theme normally (header/footer/content templates)
 * - Pages are visible in WP Pages list (like /bc and /bs/{slug})
 * - Multilingual: if Polylang is active, create/keep one sitemap page per language.
 */

// One-time cleanup: remove old rewrite rules from previous virtual-route versions.
add_action('init', function () {
  if ((int) get_option('langa_tools_vs_flush', 0) === 1) {
    delete_option('langa_tools_vs_flush');
    if (function_exists('flush_rewrite_rules')) flush_rewrite_rules(false);
  }
}, 1);

add_action('init', function () {
  add_shortcode('langa_visual_sitemap', 'langa_tools_client_visual_sitemap_shortcode');
}, 5);

// Mark sitemap pages for CSS isolation (hide theme titles, etc.)
add_filter('body_class', function($classes){
  if (function_exists('is_page') && is_page('sitemap')) {
    $classes[] = 'langa-vs-page';
  }
  return $classes;
});

/**
 * Ensure sitemap page(s) exist and are correctly published/drafted
 * when settings change.
 */
add_action('update_option_langa_tools_adminux_settings', function ($old, $new) {
  if (!is_array($new)) return;
  langa_tools_client_visual_sitemap_sync_pages($new);
}, 10, 2);

/**
 * Also sync once on init (cheap) so toggling via DB or imports stays consistent.
 */
add_action('init', function () {
  $s = get_option('langa_tools_adminux_settings', array());
  if (!is_array($s)) return;
  // Only act if the feature was ever configured (avoid doing work on fresh installs)
  if (!array_key_exists('visual_sitemap_enabled', $s) && !array_key_exists('visual_sitemap_title', $s)) return;
  langa_tools_client_visual_sitemap_sync_pages($s);
}, 20);

function langa_tools_client_visual_sitemap_sync_pages($settings) {
  if (!function_exists('wp_insert_post')) return;

  $enabled = !empty($settings['visual_sitemap_enabled']) ? 1 : 0;
  $title   = !empty($settings['visual_sitemap_title']) ? sanitize_text_field((string)$settings['visual_sitemap_title']) : 'Sitemap';

  // Languages
  $langs = array();
  if (function_exists('pll_languages_list')) {
    $l = pll_languages_list(array('fields' => 'slug'));
    if (is_array($l) && !empty($l)) $langs = array_map('sanitize_key', $l);
  }
  if (empty($langs)) {
    $langs = array(''); // single site / no polylang
  }

  $ids = array();
  foreach ($langs as $lang) {
    $id = langa_tools_client_visual_sitemap_find_or_create_page($lang, $title, $enabled);
    if ($id) $ids[$lang === '' ? 'default' : $lang] = (int)$id;
  }

  // Link translations (Polylang)
  if (function_exists('pll_save_post_translations') && count($ids) > 1) {
    $map = array();
    foreach ($ids as $k => $pid) {
      if ($k === 'default') continue;
      $map[$k] = (int)$pid;
    }
    // Add default language if present and Polylang knows it
    if (isset($ids['default']) && function_exists('pll_default_language')) {
      $def = sanitize_key((string)pll_default_language('slug'));
      if ($def !== '' && isset($ids[$def])) {
        // already
      } elseif ($def !== '' && !isset($map[$def])) {
        $map[$def] = (int)$ids['default'];
      }
    }
    if (!empty($map)) {
      // Need one call per group; we can just call with the map.
      try { pll_save_post_translations($map); } catch (Throwable $e) {}
    }
  }

  update_option('langa_tools_vs_page_ids', $ids, false);
}

function langa_tools_client_visual_sitemap_find_or_create_page($lang, $title, $enabled) {
  $slug = 'sitemap';

  // Find existing
  $pid = 0;
  if ($lang !== '' && function_exists('pll_get_post')) {
    // Polylang-aware query: WP_Query supports 'lang'
    $q = new WP_Query(array(
      'post_type' => 'page',
      'name' => $slug,
      'posts_per_page' => 1,
      'post_status' => array('publish','draft','pending','private'),
      'lang' => $lang,
      'no_found_rows' => true,
      'fields' => 'ids',
    ));
    if (!empty($q->posts)) $pid = (int)$q->posts[0];
  } else {
    $p = get_page_by_path($slug, OBJECT, 'page');
    if ($p && $p instanceof WP_Post) $pid = (int)$p->ID;
  }

  $content_marker = '[langa_visual_sitemap]';

  if ($pid > 0) {
    // Ensure shortcode exists (but never delete user content)
    $post = get_post($pid);
    if ($post && $post instanceof WP_Post) {
      $needs_update = false;
      $new_content = $post->post_content;

      if (stripos($new_content, $content_marker) === false) {
        $new_content = rtrim($new_content) . "\n\n" . $content_marker . "\n";
        $needs_update = true;
      }
      // Title: update only if this page is managed by us
      $managed = (int) get_post_meta($pid, '_langa_auto_visual_sitemap', true);
      if ($managed === 1 && $post->post_title !== $title) {
        $needs_update = true;
      }

      // Status
      $target_status = $enabled ? 'publish' : 'draft';
      if ($post->post_status !== $target_status) {
        // Only auto-toggle status if managed by us
        if ($managed === 1 || $enabled) {
          $needs_update = true;
        }
      }

      if ($needs_update) {
        $upd = array('ID' => $pid);
        if ($managed === 1) $upd['post_title'] = $title;
        $upd['post_content'] = $new_content;
        if ($managed === 1 || $enabled) $upd['post_status'] = $target_status;
        wp_update_post($upd);
      }

      // Mark managed if created by us earlier; do not force if user page.
      if ((int)get_post_meta($pid, '_langa_auto_visual_sitemap', true) !== 1) {
        // If page was empty or contains only the shortcode, consider it safe to mark as managed
        $plain = trim(wp_strip_all_tags($post->post_content));
        if ($plain === '' || $plain === $content_marker) {
          update_post_meta($pid, '_langa_auto_visual_sitemap', 1);
        }
      }

      // Polylang language assignment for managed pages
      if ($lang !== '' && function_exists('pll_set_post_language')) {
        try { pll_set_post_language($pid, $lang); } catch (Throwable $e) {}
      }

      return $pid;
    }
  }

  // Create new page (managed)
  $pid = wp_insert_post(array(
    'post_type' => 'page',
    'post_title' => $title,
    'post_name' => $slug,
    'post_status' => $enabled ? 'publish' : 'draft',
    'post_content' => $content_marker,
    'comment_status' => 'closed',
    'ping_status' => 'closed',
  ), true);

  if (is_wp_error($pid) || !$pid) return 0;

  update_post_meta($pid, '_langa_auto_visual_sitemap', 1);

  if ($lang !== '' && function_exists('pll_set_post_language')) {
    try { pll_set_post_language($pid, $lang); } catch (Throwable $e) {}
  }

  return (int)$pid;
}



function langa_tools_client_visual_sitemap_shortcode($atts = array(), $content = '') {
  $s = get_option('langa_tools_adminux_settings', array());
  if (!is_array($s)) $s = array();
  $vs = isset($s['visual_sitemap']) && is_array($s['visual_sitemap']) ? $s['visual_sitemap'] : array();

  // Defaults (neutral palette)
  $bg   = !empty($vs['bg_color']) ? $vs['bg_color'] : '#f5f5f4';
  $txt  = !empty($vs['text_color']) ? $vs['text_color'] : '#111827';
  $hbg  = !empty($vs['hover_bg_color']) ? $vs['hover_bg_color'] : '#e7e5e4';
  $htxt = !empty($vs['hover_text_color']) ? $vs['hover_text_color'] : '#111827';
  $line = !empty($vs['line_color']) ? $vs['line_color'] : '#d6d3d1';
  $radius = isset($vs['radius']) ? max(0, min(40, (int)$vs['radius'])) : 5;
  $custom_css = isset($vs['custom_css']) ? (string)$vs['custom_css'] : '';
  $custom_css = str_replace("\0", '', $custom_css);
  if (strlen($custom_css) > 20000) $custom_css = substr($custom_css, 0, 20000);


  $sort_by = !empty($vs['sort_by']) ? sanitize_key($vs['sort_by']) : 'menu_order';
  $sort_order = !empty($vs['sort_order']) ? sanitize_key($vs['sort_order']) : 'asc';
  if (!in_array($sort_by, array('menu_order','title','date'), true)) $sort_by = 'menu_order';
  if (!in_array($sort_order, array('asc','desc'), true)) $sort_order = 'asc';

  // ── Blog root: only if WP has a real page_for_posts OR front shows posts ──
  // NO fallback: if no blog page is configured, no blog column appears.
  $blog_url = '';
  $blog_title = '';
  $blog_page_id = 0;
  $show_blog = false;

  $posts_page_id = (int) get_option('page_for_posts');
  if ($posts_page_id > 0 && get_post_status($posts_page_id) === 'publish') {
    $blog_page_id = $posts_page_id;
    $blog_url     = get_permalink($posts_page_id);
    $blog_title   = get_the_title($posts_page_id);
    $show_blog    = true;
  }

  // Fallback: if page_for_posts not set, look for a published page with slug 'blog'
  // or a page template containing 'blog'. This handles environments where WP Reading
  // settings can't assign a posts page (e.g. Elementor blog templates).
  if (!$show_blog) {
    $blog_fallback = get_posts(array(
      'post_type'   => 'page',
      'post_status' => 'publish',
      'name'        => 'blog',
      'numberposts' => 1,
    ));
    if (empty($blog_fallback)) {
      // Try page template containing 'blog'
      global $wpdb;
      $tpl_page_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- template heuristic lookup
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'page' AND p.post_status = 'publish'
         AND pm.meta_key = '_wp_page_template'
         AND LOWER(pm.meta_value) LIKE '%blog%'
         ORDER BY p.menu_order ASC, p.post_date DESC
         LIMIT 1"
      );
      if ($tpl_page_id > 0) {
        $blog_fallback = array(get_post($tpl_page_id));
      }
    }
    if (!empty($blog_fallback) && $blog_fallback[0]) {
      $blog_page_id = $blog_fallback[0]->ID;
      $blog_url     = get_permalink($blog_page_id);
      $blog_title   = get_the_title($blog_page_id);
      $show_blog    = true;
    }
  }

  // ── Shop root: only if WooCommerce is active AND a real shop page is configured ──
  // NO fallback: if WooCommerce shop page is not set, no shop column appears.
  $shop_url = '';
  $shop_title = '';
  $shop_page_id = 0;
  $show_shop = false;

  if (function_exists('wc_get_page_id')) {
    $wc_shop_id = (int) wc_get_page_id('shop');
    if ($wc_shop_id > 0 && get_post_status($wc_shop_id) === 'publish') {
      $shop_page_id = $wc_shop_id;
      $shop_url     = get_permalink($wc_shop_id);
      $shop_title   = get_the_title($wc_shop_id);
      $show_shop    = true;
    }
  }

  // IDs to exclude from the Pages column (they appear as roots in their own columns)
  $exclude_page_ids = array_filter(array($blog_page_id, $shop_page_id));

  ob_start();

  // Scoped inline CSS (resists theme styling) — does NOT force max-width/margins
  $style = '.langa-vs-wrap{'
    .'--lvs-bg:'.esc_attr($bg).';'
    .'--lvs-txt:'.esc_attr($txt).';'
    .'--lvs-hbg:'.esc_attr($hbg).';'
    .'--lvs-htxt:'.esc_attr($htxt).';'
    .'--lvs-line:'.esc_attr($line).';'
    .'--lvs-radius:'.esc_attr((string)$radius).'px;'
    .'--lvs-mid:16px;'
    .'--lvs-indent:18px;'
    .'--lvs-gap:10px;'
    .'--lvs-radius:'.(int)$radius.'px;'
    // Anchor vertical midline (keep in sync with padding/font)
    .'--lvs-node-mid:15px;'
    .'--lvs-rail-x:9px;'
  .'}';
  langtoli_inline_style($style . '
  /* Do not force container width/margins on shortcodes (let the theme/builder decide). */
  .langa-vs-wrap{font-size:12px;line-height:1.25;margin:0 !important;max-width:none !important;padding:0 !important;width:100% !important;}
  .langa-vs-wrap, .langa-vs-wrap *{box-sizing:border-box;}
  /* Reset list styles without breaking tree indentation */
  .langa-vs-wrap ul,.langa-vs-wrap ol{list-style:none !important;margin:0;padding:0;}
  .langa-vs-wrap li{list-style:none !important;margin:0;padding:0;}
  .langa-vs-wrap li::marker{content:"";}
  .langa-vs-page .entry-title,.langa-vs-page .page-title,.langa-vs-page .wp-block-post-title,.langa-vs-page header.entry-header{display:none !important;}

  .langa-vs-grid{display:grid !important;grid-template-columns:1fr !important;gap:16px !important;align-items:start !important;}
  @media(min-width:601px){.langa-vs-wrap .langa-vs-grid{grid-template-columns:repeat(2,1fr) !important;}}
  @media(min-width:961px){.langa-vs-wrap .langa-vs-grid{grid-template-columns:repeat(3,1fr) !important;}}
  .langa-vs-col{min-width:0;}
  .langa-vs-head{font-weight:800;margin:0 0 10px 0;}

  /* Tree (classic UL tree connectors — avoids overlapping on button edges) */
  .langa-vs-tree{margin:0;padding:0;}
  .langa-vs-tree ul{margin:0;padding-left:var(--lvs-indent);padding-top:8px;}

  .langa-vs-tree li{margin:0 0 var(--lvs-gap) 0;position:relative;padding-left:calc(var(--lvs-indent) + 4px);}
  .langa-vs-tree li:last-child{margin-bottom:0;}
  /* Root level has no connector lines */
  .langa-vs-tree.langa-vs-root > li{padding-left:0;}
  .langa-vs-tree.langa-vs-root > li:before,
  .langa-vs-tree.langa-vs-root > li:after{display:none;}

  /* Connectors: keep parent/child continuity (no gaps) */
  .langa-vs-tree li:before{content:"";position:absolute;left:var(--lvs-rail-x);top:calc(-1 * var(--lvs-gap));bottom:calc(-1 * var(--lvs-gap));border-left:1px solid var(--lvs-line);opacity:.95;}
  .langa-vs-tree li:last-child:before{bottom:var(--lvs-node-mid);}
  .langa-vs-tree li:after{content:"";position:absolute;left:var(--lvs-rail-x);top:var(--lvs-node-mid);width:12px;border-top:1px solid var(--lvs-line);opacity:.95;}

  .langa-vs-tree a{display:block;background:var(--lvs-bg) !important;color:var(--lvs-txt) !important;padding:7px 10px;border-radius:var(--lvs-radius) !important;text-decoration:none !important;border:1px solid rgba(0,0,0,0.06) !important;box-shadow:0 0 0 1px rgba(255,255,255,0.15) inset !important;}
  .langa-vs-tree a:hover{background:var(--lvs-hbg) !important;color:var(--lvs-htxt) !important;}
  .langa-vs-xref{font-style:italic;opacity:.6;font-size:11px;}
  '."\n  /* Custom CSS */\n".langa_tools_client_visual_sitemap_scope_css($custom_css)."\n".'');

  echo '<div class="langa-vs-wrap">';
  echo '<div class="langa-vs-grid">';

  // Column: Pages tree (excludes blog/shop root pages shown in their own columns)
  echo '<div class="langa-vs-col">';
  echo '<div class="langa-vs-head">'.esc_html__('Pages','langa-tools-lite').'</div>';
  $pages_tree = langa_tools_client_visual_sitemap_get_pages_nested($sort_by, $sort_order, $exclude_page_ids);

  // NOTE 18: Cross-reference indicators for blog/shop pages that have a parent in the Pages tree
  $xref_map = array(); // parent_id => array of xref nodes
  if ($blog_page_id > 0) {
    $bp = get_post($blog_page_id);
    if ($bp && (int)$bp->post_parent > 0) {
      $xref_map[(int)$bp->post_parent][] = array(
        'title' => $blog_title . ' ' . __("\xe2\x86\x92 see Articles", 'langa-tools-lite'),
        'url'   => $blog_url,
        'children' => array(),
        'xref'  => true,
      );
    }
  }
  if ($shop_page_id > 0) {
    $sp = get_post($shop_page_id);
    if ($sp && (int)$sp->post_parent > 0) {
      $xref_map[(int)$sp->post_parent][] = array(
        'title' => $shop_title . ' ' . __("\xe2\x86\x92 see Products", 'langa-tools-lite'),
        'url'   => $shop_url,
        'children' => array(),
        'xref'  => true,
      );
    }
  }
  if (!empty($xref_map)) {
    $pages_tree = langa_tools_client_vs_inject_xrefs($pages_tree, $xref_map);
  }

  langa_tools_client_visual_sitemap_render_tree($pages_tree, true);
  echo '</div>';

  // Column: Articles (only if a real page_for_posts is published)
  if ($show_blog) {
  echo '<div class="langa-vs-col">';
  echo '<div class="langa-vs-head">'.esc_html__('Articles','langa-tools-lite').'</div>';

  $cats_tree = langa_tools_client_visual_sitemap_get_terms_nested('category', $sort_order);
  $blog_node = array(
    array(
      'title' => $blog_title,
      'url' => $blog_url,
      'children' => $cats_tree,
    )
  );
  $posts = langa_tools_client_visual_sitemap_get_posts_nodes('post', $sort_by, $sort_order);
  if (!empty($posts)) {
    $blog_node[0]['children'][] = array(
      'title' => __('Posts', 'langa-tools-lite'),
      'url' => $blog_url,
      'children' => $posts,
    );
  }
  langa_tools_client_visual_sitemap_render_tree($blog_node, true);

  echo '</div>';
  }

  // Column: Products (only if WooCommerce shop page is published)
  if ($show_shop) {
  echo '<div class="langa-vs-col">';
  echo '<div class="langa-vs-head">'.esc_html__('Products','langa-tools-lite').'</div>';

  $shop_children = array();
  if (taxonomy_exists('product_cat')) {
    $shop_children = langa_tools_client_visual_sitemap_get_terms_nested('product_cat', $sort_order);
  }
  $shop_node = array(
    array(
      'title' => $shop_title,
      'url' => $shop_url,
      'children' => $shop_children,
    )
  );
  if (post_type_exists('product')) {
    $products = langa_tools_client_visual_sitemap_get_posts_nodes('product', $sort_by, $sort_order);
    if (!empty($products)) {
      $shop_node[0]['children'][] = array(
        'title' => __('Products', 'langa-tools-lite'),
        'url' => $shop_url,
        'children' => $products,
      );
    }
  }
  langa_tools_client_visual_sitemap_render_tree($shop_node, true);

  echo '</div>';
  }

  echo '</div></div>';

  return ob_get_clean();
}


/**
 * Render a nested tree of nodes.
 * Nodes with 'xref' => true get a special CSS class for cross-reference styling.
 */
function langa_tools_client_visual_sitemap_render_tree($nodes, $is_root = false) {
  if (empty($nodes) || !is_array($nodes)) return;
  $cls = $is_root ? 'langa-vs-tree langa-vs-root' : 'langa-vs-tree';
  echo '<ul class="'.esc_attr($cls).'">';
  foreach ($nodes as $n) {
    if (!is_array($n)) continue;
    $title = isset($n['title']) ? (string)$n['title'] : '';
    $url = isset($n['url']) ? (string)$n['url'] : '';
    $children = isset($n['children']) && is_array($n['children']) ? $n['children'] : array();
    $is_xref = !empty($n['xref']);
    echo '<li>';
    $a_class = $is_xref ? ' class="langa-vs-xref"' : '';
    echo '<a href="'.esc_url($url).'"'.$a_class.'>'.esc_html($title).'</a>';
    if (!empty($children)) {
      langa_tools_client_visual_sitemap_render_tree($children, false);
    }
    echo '</li>';
  }
  echo '</ul>';
}

/**
 * Inject cross-reference nodes into the pages tree.
 * $xref_map: parent_id => array of xref node arrays.
 */
function langa_tools_client_vs_inject_xrefs($tree, $xref_map) {
  foreach ($tree as &$node) {
    $nid = isset($node['id']) ? (int)$node['id'] : 0;
    if ($nid > 0 && isset($xref_map[$nid])) {
      foreach ($xref_map[$nid] as $xref) {
        $node['children'][] = $xref;
      }
    }
    if (!empty($node['children'])) {
      $node['children'] = langa_tools_client_vs_inject_xrefs($node['children'], $xref_map);
    }
  }
  unset($node);
  return $tree;
}


/**
 * Build a nested pages tree (title, url, children).
 */
function langa_tools_client_visual_sitemap_get_pages_nested($sort_by, $sort_order, $exclude_ids = array()) {
  $args = array(
    'post_type' => 'page',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => ($sort_by === 'title' ? 'title' : ($sort_by === 'date' ? 'date' : 'menu_order')),
    'order' => strtoupper($sort_order),
    'no_found_rows' => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'meta_query' => array(
      'relation' => 'OR',
      array('key' => '_langa_ghost_hide', 'compare' => 'NOT EXISTS'),
      array('key' => '_langa_ghost_hide', 'value' => '1', 'compare' => '!='),
    ),
  );
  // Exclude blog/shop root pages (they appear in their own columns)
  if (!empty($exclude_ids)) {
    $args['post__not_in'] = array_map('intval', $exclude_ids);
  }

  $q = new WP_Query($args);
  $pages = $q->posts;

  // index by parent
  $by_parent = array();
  foreach ($pages as $p) {
    $parent = (int)$p->post_parent;
    if (!isset($by_parent[$parent])) $by_parent[$parent] = array();
    $by_parent[$parent][] = $p;
  }


  // Ensure deterministic sorting inside each parent group
  foreach ($by_parent as $pid => $arr) {
    usort($by_parent[$pid], function($a, $b) use ($sort_by, $sort_order) {
      $dir = ($sort_order === 'desc') ? -1 : 1;
      if ($sort_by === 'title') {
        $va = strtolower((string)get_the_title($a));
        $vb = strtolower((string)get_the_title($b));
        if ($va === $vb) return $dir * ((int)$a->ID - (int)$b->ID);
        return $dir * strcmp($va, $vb);
      }
      if ($sort_by === 'date') {
        $va = (string)$a->post_date;
        $vb = (string)$b->post_date;
        if ($va === $vb) return $dir * ((int)$a->ID - (int)$b->ID);
        return $dir * strcmp($va, $vb);
      }
      $va = (int)$a->menu_order;
      $vb = (int)$b->menu_order;
      if ($va === $vb) return $dir * ((int)$a->ID - (int)$b->ID);
      return $dir * (($va < $vb) ? -1 : 1);
    });
  }
  $walk = function($parent) use (&$walk, &$by_parent) {
    if (!isset($by_parent[$parent])) return array();
    $nodes = array();
    foreach ($by_parent[$parent] as $p) {
      $nodes[] = array(
        'id' => (int)$p->ID,
        'title' => get_the_title($p),
        'url' => get_permalink($p),
        'children' => $walk((int)$p->ID),
      );
    }
    return $nodes;
  };

  return $walk(0);
}

/**
 * Build a nested term tree for a hierarchical taxonomy.
 */
function langa_tools_client_visual_sitemap_get_terms_nested($tax, $sort_order) {
  $terms = get_terms(array(
    'taxonomy' => $tax,
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => strtoupper($sort_order),
    'meta_query' => array(
      'relation' => 'OR',
      array('key' => 'langa_ghost_hide', 'compare' => 'NOT EXISTS'),
      array('key' => 'langa_ghost_hide', 'value' => '1', 'compare' => '!='),
    ),
  ));
  if (is_wp_error($terms) || empty($terms)) return array();

  $by_parent = array();
  foreach ($terms as $t) {
    $parent = (int)$t->parent;
    if (!isset($by_parent[$parent])) $by_parent[$parent] = array();
    $by_parent[$parent][] = $t;
  }

  // Ensure deterministic sorting inside each parent group
  foreach ($by_parent as $pid => $arr) {
    usort($by_parent[$pid], function($a, $b) use ($sort_order) {
      $dir = (strtoupper($sort_order) === 'DESC') ? -1 : 1;
      $va = strtolower((string)$a->name);
      $vb = strtolower((string)$b->name);
      if ($va === $vb) return $dir * ((int)$a->term_id - (int)$b->term_id);
      return $dir * strcmp($va, $vb);
    });
  }

  $walk = function($parent) use (&$walk, &$by_parent, $tax) {
    if (!isset($by_parent[$parent])) return array();
    $nodes = array();
    foreach ($by_parent[$parent] as $t) {
      $nodes[] = array(
        'id' => (int)$t->term_id,
        'title' => $t->name,
        'url' => get_term_link($t, $tax),
        'children' => $walk((int)$t->term_id),
      );
    }
    return $nodes;
  };

  return $walk(0);
}

/**
 * Generic list for posts/products.
 * NOTE: capped for safety on huge sites (filterable).
 */
function langa_tools_client_visual_sitemap_get_posts_nodes($post_type, $sort_by, $sort_order) {
  $cap = (int)apply_filters('langa_visual_sitemap_max_items', 2000, $post_type);
  if ($cap < 50) $cap = 50;

  $orderby = ($sort_by === 'title' ? 'title' : ($sort_by === 'date' ? 'date' : 'menu_order'));
  if ($orderby === 'menu_order' && $post_type !== 'page') $orderby = 'title';

  $q = new WP_Query(array(
    'post_type' => $post_type,
    'post_status' => 'publish',
    'posts_per_page' => $cap,
    'orderby' => $orderby,
    'order' => strtoupper($sort_order),
    'no_found_rows' => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'meta_query' => array(
      'relation' => 'OR',
      array('key' => '_langa_ghost_hide', 'compare' => 'NOT EXISTS'),
      array('key' => '_langa_ghost_hide', 'value' => '1', 'compare' => '!='),
    ),
  ));
  $nodes = array();
  foreach ($q->posts as $p) {
    $nodes[] = array(
      'id' => (int)$p->ID,
      'title' => get_the_title($p),
      'url' => get_permalink($p),
      'children' => array(),
    );
  }
  return $nodes;
}


/**
 * Scope custom CSS to Visual Sitemap wrapper to avoid theme-wide side effects.
 */
function langa_tools_client_visual_sitemap_scope_css($css) {
  $css = (string)$css;
  $css = trim($css);
  if ($css === '') return '';
  // Remove any tags
  $css = wp_strip_all_tags($css);
  // Prefix selectors that are not already scoped
  $css = preg_replace_callback('/(^|[\}\{])\s*([^@\}\{][^\{]+)\{/m', function($m){
    $lead = $m[1];
    $sel  = trim($m[2]);
    // Avoid accidental prefixing of :root/html/body; force scope anyway
    $parts = array_map('trim', explode(',', $sel));
    $parts2 = array();
    foreach ($parts as $p) {
      if ($p === '') continue;
      if (strpos($p, '.langa-vs-wrap') !== false) { $parts2[] = $p; continue; }
      $parts2[] = '.langa-vs-wrap ' . $p;
    }
    if (empty($parts2)) return $m[0];
    return $lead . ' ' . implode(', ', $parts2) . '{';
  }, $css);
  return $css;
}

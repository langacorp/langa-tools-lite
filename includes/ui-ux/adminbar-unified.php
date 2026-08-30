<?php
if (!defined('ABSPATH')) exit;

/**
 * LANGA Tools Lite — Admin Bar Module Status
 *
 * Same structure as PRO: dots + dropdown + hover submenus.
 * UI/UX active, rest show (PRO) label.
 * Uses langa-tools-client-* slugs (same as PRO).
 */

add_action('admin_bar_menu', function($wp_admin_bar) {
  if (!is_admin_bar_showing() || !is_object($wp_admin_bar)) return;
  if (!current_user_can('manage_options')) return;

  $root = 'langa-modules';

  $modules = array(
    array('key'=>'adminux','label'=>'UI/UX',  'on'=>true,  'page'=>'langa-tools-client-ui-ux'),
    array('key'=>'safer',  'label'=>'Safer',  'on'=>false, 'page'=>'langa-tools-client-safer'),
    array('key'=>'seo',    'label'=>'SEO',    'on'=>false, 'page'=>'langa-tools-client-seo'),
    array('key'=>'cache',  'label'=>'Cache',  'on'=>false, 'page'=>'langa-tools-client-cache'),
    array('key'=>'legal',  'label'=>'Legal',  'on'=>false, 'page'=>'langa-tools-client-legal'),
    array('key'=>'forms',  'label'=>'Forms',  'on'=>false, 'page'=>'langa-tools-client-forms'),
    array('key'=>'bc',     'label'=>'BC',     'on'=>false, 'page'=>'langa-tools-client-bc'),
    array('key'=>'popup',  'label'=>'Popup',  'on'=>false, 'page'=>'langa-tools-client-popup'),
    array('key'=>'bridge', 'label'=>'Events', 'on'=>false, 'page'=>'langa-tools-client-events'),
    array('key'=>'ai',     'label'=>'AI',     'on'=>false, 'page'=>'langa-tools-client-ai'),
  );

  $dots = '';
  foreach ($modules as $m) {
    $c = $m['on'] ? '#16a34a' : '#d1d5db';
    $dots .= '<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:'.$c.'"></span>';
  }

  $wp_admin_bar->add_node(array(
    'id'    => $root,
    'title' => '<span style="display:inline-flex;align-items:center;gap:2px">'
             . $dots
             . '<span style="font-size:11px;opacity:0.7;margin-left:3px">1/10</span>'
             . '</span>',
    'href'  => admin_url('admin.php?page=langa-tools-client'),
    'meta'  => array('title' => 'LANGA Modules (Lite: 1/10)'),
  ));

  foreach ($modules as $m) {
    $color = $m['on'] ? '#16a34a' : '#d1d5db';
    $dot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'.$color.';margin-right:6px"></span>';
    $label = $m['on'] ? $m['label'] : $m['label'] . ' <span style="font-size:10px;opacity:0.5">(PRO)</span>';
    $node_id = 'langa-mod-'.$m['key'];

    $wp_admin_bar->add_node(array(
      'id'     => $node_id,
      'parent' => $root,
      'title'  => $dot . $label,
      'href'   => admin_url('admin.php?page='.$m['page']),
    ));
  }

  // UI/UX submenu (the only active module in Lite)
  $ux = 'langa-tools-client-ui-ux';
  $wp_admin_bar->add_node(array('id'=>'langa-sub-ux-general','parent'=>'langa-mod-adminux','title'=>'General','href'=>admin_url('admin.php?page='.$ux.'&tab=general')));
  $wp_admin_bar->add_node(array('id'=>'langa-sub-ux-maint','parent'=>'langa-mod-adminux','title'=>'Maintenance Mode','href'=>admin_url('admin.php?page='.$ux.'&tab=maintenance')));
  $wp_admin_bar->add_node(array('id'=>'langa-sub-ux-users','parent'=>'langa-mod-adminux','title'=>'Users','href'=>admin_url('admin.php?page='.$ux.'&tab=users')));
  $wp_admin_bar->add_node(array('id'=>'langa-sub-ux-replace','parent'=>'langa-mod-adminux','title'=>'Search & Replace','href'=>admin_url('admin.php?page='.$ux.'&tab=replace')));
  $wp_admin_bar->add_node(array('id'=>'langa-sub-ux-preloader','parent'=>'langa-mod-adminux','title'=>'Preloader','href'=>admin_url('admin.php?page='.$ux.'&tab=preloader')));
  $wp_admin_bar->add_node(array('id'=>'langa-sub-ux-sitemap','parent'=>'langa-mod-adminux','title'=>'Visual Sitemap','href'=>admin_url('admin.php?page='.$ux.'&tab=sitemap')));

}, 11);

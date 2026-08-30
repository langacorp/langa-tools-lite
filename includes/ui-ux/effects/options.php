<?php
if (!defined('ABSPATH')) exit;

if (!defined('LANGA_TOOLS_OPTION_EFFECTS')) define('LANGA_TOOLS_OPTION_EFFECTS', 'langa_tools_effects');

function langa_tools_client_effects_defaults() {
  return array(
    'enabled' => 0,
    'rows' => array(
      array('effect'=>'snow',      'start_md'=>'08/12', 'end_md'=>'06/01', 'before'=>3, 'after'=>2),
      array('effect'=>'newyear',   'start_md'=>'31/12', 'end_md'=>'01/01', 'before'=>1, 'after'=>1),
      array('effect'=>'valentine', 'start_md'=>'14/02', 'end_md'=>'14/02', 'before'=>2, 'after'=>1),
      array('effect'=>'easter',    'start_md'=>'',      'end_md'=>'',      'before'=>5, 'after'=>2),
      array('effect'=>'halloween', 'start_md'=>'31/10', 'end_md'=>'01/11', 'before'=>3, 'after'=>1),
      array('effect'=>'spring',    'start_md'=>'20/03', 'end_md'=>'20/03', 'before'=>5, 'after'=>5),
      array('effect'=>'autumn',    'start_md'=>'22/09', 'end_md'=>'22/09', 'before'=>5, 'after'=>5),
      array('effect'=>'special',   'start_md'=>'15/08', 'end_md'=>'15/08', 'before'=>1, 'after'=>0),
    ),
    // Custom (inline) effect: optional CSS/JS in a date window.
    'custom' => array(
      'start_md' => '',
      'end_md'   => '',
      'css'      => '',
      'js'       => '',
    ),
  );
}

function langa_tools_client_get_effects_option() {
  $opt = get_option(LANGA_TOOLS_OPTION_EFFECTS, array());
  $defaults = langa_tools_client_effects_defaults();
  if (!is_array($opt) || empty($opt)) $opt = $defaults;
  if (!isset($opt['enabled'])) $opt['enabled'] = 0;
  if (!isset($opt['rows']) || !is_array($opt['rows'])) $opt['rows'] = array();
  // Pad rows from defaults if saved data has fewer rows
  $def_rows = $defaults['rows'];
  while (count($opt['rows']) < count($def_rows)) {
    $opt['rows'][] = $def_rows[count($opt['rows'])];
  }
  if (!isset($opt['custom']) || !is_array($opt['custom'])) $opt['custom'] = array();
  if (!isset($opt['custom']['start_md'])) $opt['custom']['start_md'] = '';
  if (!isset($opt['custom']['end_md'])) $opt['custom']['end_md'] = '';
  if (!isset($opt['custom']['css'])) $opt['custom']['css'] = '';
  if (!isset($opt['custom']['js'])) $opt['custom']['js'] = '';
  return $opt;
}

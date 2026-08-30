<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalize DD/MM input. Accepts: 25/12, 25-12, 25.12, 8/1, 08/01
 * Returns 'DD/MM' or '' if invalid.
 */
function langa_tools_client_norm_md($s) {
  $s = trim((string)$s);
  if ($s === '') return '';
  $s = str_replace(array('-', '.', ' '), '/', $s);
  $parts = explode('/', $s);
  if (count($parts) < 2) return '';
  $d = (int)$parts[0];
  $m = (int)$parts[1];
  if ($d < 1 || $d > 31 || $m < 1 || $m > 12) return '';
  return sprintf('%02d/%02d', $d, $m);
}

function langa_tools_client_md_to_date_for_year($md, $year) {
  $md = langa_tools_client_norm_md($md);
  if ($md === '') return '';
  list($d,$m) = array_map('intval', explode('/', $md));
  return sprintf('%04d-%02d-%02d', (int)$year, (int)$m, (int)$d);
}

/**
 * Determine the active window for a recurring start/end DD/MM relative to "now".
 * Supports crossing-year ranges (e.g. 25/12 -> 08/01).
 * Applies before/after day offsets.
 */
function langa_tools_client_compute_window_ts($start_md, $end_md, $before_days, $after_days) {
  $start_md = langa_tools_client_norm_md($start_md);
  $end_md   = langa_tools_client_norm_md($end_md);

  if ($start_md === '' || $end_md === '') return array(0,0);

  $now = current_time('timestamp');
  $y = (int) date_i18n('Y', $now);

  // compare MMDD integers
  list($sd,$sm)=array_map('intval', explode('/',$start_md));
  list($ed,$em)=array_map('intval', explode('/',$end_md));
  $s = $sm*100 + $sd;
  $e = $em*100 + $ed;

  // decide which year start/end belong to around now
  $now_md = (int) date_i18n('m', $now)*100 + (int) date_i18n('d', $now);

  if ($s <= $e) {
    // same-year window
    $start_ymd = langa_tools_client_md_to_date_for_year($start_md, $y);
    $end_ymd   = langa_tools_client_md_to_date_for_year($end_md, $y);
  } else {
    // crosses year: if today <= end_md -> start previous year, else start current year
    if ($now_md <= $e) {
      $start_ymd = langa_tools_client_md_to_date_for_year($start_md, $y-1);
      $end_ymd   = langa_tools_client_md_to_date_for_year($end_md, $y);
    } else {
      $start_ymd = langa_tools_client_md_to_date_for_year($start_md, $y);
      $end_ymd   = langa_tools_client_md_to_date_for_year($end_md, $y+1);
    }
  }

  $start_ts = strtotime($start_ymd . ' 00:00:00');
  $end_ts   = strtotime($end_ymd . ' 23:59:59');

  $before = max(0, (int)$before_days);
  $after  = max(0, (int)$after_days);

  if ($before > 0) $start_ts -= $before * DAY_IN_SECONDS;
  if ($after > 0)  $end_ts   += $after * DAY_IN_SECONDS;

  return array($start_ts, $end_ts);
}

function langa_tools_client_should_apply_effect($row) {
  if (!is_array($row)) return false;

  $start = isset($row['start_md']) ? $row['start_md'] : '';
  $end   = isset($row['end_md']) ? $row['end_md'] : '';

  $start_n = langa_tools_client_norm_md($start);
  $end_n   = langa_tools_client_norm_md($end);

  // If range is empty -> DO NOT apply
  if ($start_n === '' || $end_n === '') return false;

  $before = isset($row['before']) ? (int)$row['before'] : 0;
  $after  = isset($row['after']) ? (int)$row['after'] : 0;

  list($ts1,$ts2) = langa_tools_client_compute_window_ts($start_n, $end_n, $before, $after);
  if (!$ts1 || !$ts2) return false;

  $now = current_time('timestamp');
  return ($now >= $ts1 && $now <= $ts2);
}

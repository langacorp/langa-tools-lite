<?php
if (!defined('ABSPATH')) exit;

/**
 * Replace tools (UI/UX → Replace)
 * - Media Replace: replace an attachment file keeping the same URL
 * - Text/URL/Code Replace: search & replace across DB tables (serialized-safe)
 */

if (!function_exists('langa_tools_client_replace_set_notice')) {
  function langa_tools_client_replace_set_notice($type, $message) {
    $type = in_array($type, array('success','error','warning','info'), true) ? $type : 'info';
    $message = (string)$message;
    if ($message === '') return;
    set_transient('langa_tools_adminux_replace_notice', array(
      'type' => $type,
      'msg'  => $message,
      'ts'   => time(),
    ), 120);
  }
}

if (!function_exists('langa_tools_client_replace_get_notice')) {
  function langa_tools_client_replace_get_notice() {
    $n = get_transient('langa_tools_adminux_replace_notice');
    if (is_array($n)) {
      delete_transient('langa_tools_adminux_replace_notice');
      return $n;
    }
    return null;
  }
}

// ---- Media Replace ----

if (!function_exists('langa_tools_client_media_replace_attachment')) {
  function langa_tools_client_media_replace_attachment($attachment_id, $file_field = 'replace_media_file', $keep_backup = true) {
    $attachment_id = (int)$attachment_id;
    if ($attachment_id <= 0) {
      return array('ok' => false, 'msg' => 'Invalid Attachment ID.');
    }

    $p = get_post($attachment_id);
    if (!$p || $p->post_type !== 'attachment') {
      return array('ok' => false, 'msg' => 'Media not found.');
    }

    if (empty($_FILES[$file_field]) || !is_array($_FILES[$file_field]) || empty($_FILES[$file_field]['name'])) {
      return array('ok' => false, 'msg' => 'Select a file to upload.');
    }

    $orig_file = get_attached_file($attachment_id);
    if (!$orig_file || !file_exists($orig_file)) {
      return array('ok' => false, 'msg' => 'Original file not found on the server.');
    }

    $uploads = wp_get_upload_dir();
    if (!empty($uploads['basedir'])) {
      $base = wp_normalize_path($uploads['basedir']);
      $of   = wp_normalize_path($orig_file);
      if (strpos($of, $base) !== 0) {
        return array('ok' => false, 'msg' => 'For security: you can only replace files in the uploads folder.');
      }
    }

    // URL must stay identical.
    // - For non-images: enforce same extension.
    // - For images: you may upload a different image type (png/jpg/webp). We'll convert it to the original format.
    $is_image = wp_attachment_is_image($attachment_id);

    $orig_ext = strtolower(pathinfo($orig_file, PATHINFO_EXTENSION));
    $new_ext  = strtolower(pathinfo(sanitize_file_name($_FILES[$file_field]['name']), PATHINFO_EXTENSION));
    $norm = function($e){
      $e = strtolower((string)$e);
      if ($e === 'jpeg') return 'jpg';
      if ($e === 'tiff') return 'tif';
      return $e;
    };

    if (!$is_image && $norm($orig_ext) !== $norm($new_ext)) {
      return array('ok' => false, 'msg' => 'Incompatible extension: for non-image files the new file must have the same extension (to keep the same URL).');
    }

    // Upload new file to a temp location (inside uploads)
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = array('test_form' => false);
    $up = wp_handle_upload($_FILES[$file_field], $overrides);
    if (empty($up['file']) || !empty($up['error'])) {
      $err = !empty($up['error']) ? (string)$up['error'] : 'Upload failed.';
      return array('ok' => false, 'msg' => $err);
    }
    $tmp_file = (string)$up['file'];
    if (!file_exists($tmp_file)) {
      return array('ok' => false, 'msg' => 'Upload failed (temporary file not found).');
    }

    // Optional backup (.bak, overwritten)
    $backup_path = '';
    if ($keep_backup) {
      $backup_path = $orig_file . '.bak';
      copy($orig_file, $backup_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
    }

    // Remove old image sizes before generating new metadata
    $old_meta = wp_get_attachment_metadata($attachment_id);
    if (is_array($old_meta) && !empty($old_meta['sizes']) && is_array($old_meta['sizes'])) {
      $dir = trailingslashit(dirname($orig_file));
      foreach ($old_meta['sizes'] as $sz) {
        if (!is_array($sz) || empty($sz['file'])) continue;
        $f = $dir . (string)$sz['file'];
        if (file_exists($f) && is_file($f)) {
          wp_delete_file($f);
        }
      }
    }

    // Overwrite original file (with optional image conversion)
    $ok = false;
    if ($is_image && $norm($orig_ext) !== $norm($new_ext)) {
      require_once ABSPATH . 'wp-admin/includes/image.php';

      $editor = wp_get_image_editor($tmp_file);
      if (is_wp_error($editor)) {
        wp_delete_file($tmp_file);
        return array('ok' => false, 'msg' => 'Cannot read the uploaded image (conversion not available).');
      }

      // Try to save as the original mime type to keep the same URL/extension.
      $orig_ft = wp_check_filetype('file.' . $norm($orig_ext));
      $orig_mime = !empty($orig_ft['type']) ? (string)$orig_ft['type'] : '';

      $saved = $editor->save($orig_file, $orig_mime ? $orig_mime : null);
      wp_delete_file($tmp_file);
      if (!is_wp_error($saved) && !empty($saved['path']) && file_exists($saved['path'])) {
        $ok = true;
      }
    } else {
      $ok = copy($tmp_file, $orig_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
      wp_delete_file($tmp_file);
    }

    if (!$ok) {
      return array('ok' => false, 'msg' => 'Cannot replace the file (filesystem permissions).');
    }

    // Regenerate metadata for images
    if (wp_attachment_is_image($attachment_id)) {
      require_once ABSPATH . 'wp-admin/includes/image.php';
      $meta = wp_generate_attachment_metadata($attachment_id, $orig_file);
      if (is_array($meta)) {
        wp_update_attachment_metadata($attachment_id, $meta);
      }
    }

    // Update mime type (safe)
    $ft = wp_check_filetype($orig_file);
    if (!empty($ft['type'])) {
      wp_update_post(array(
        'ID' => $attachment_id,
        'post_mime_type' => $ft['type'],
      ));
    }

    clean_post_cache($attachment_id);

    $msg = 'Media replaced successfully. (URL unchanged)';
    if ($keep_backup && $backup_path) {
      $msg .= ' Backup: ' . basename($backup_path);
    }
    return array('ok' => true, 'msg' => $msg);
  }
}

// ---- Search & Replace ----

if (!function_exists('langa_tools_client_replace_is_text_column')) {
  function langa_tools_client_replace_is_text_column($sql_type) {
    $t = strtolower((string)$sql_type);
    return (strpos($t, 'char') !== false) || (strpos($t, 'text') !== false) || (strpos($t, 'json') !== false) || (strpos($t, 'varchar') !== false);
  }
}

if (!function_exists('langa_tools_client_replace_recursive')) {
  function langa_tools_client_replace_recursive($search, $replace, $data, &$did_change) {
    $did_change = false;

    if (is_array($data)) {
      $out = array();
      foreach ($data as $k => $v) {
        $c = false;
        $out[$k] = langa_tools_client_replace_recursive($search, $replace, $v, $c);
        if ($c) $did_change = true;
      }
      return $out;
    }

    if (is_object($data)) {
      $vars = get_object_vars($data);
      foreach ($vars as $k => $v) {
        $c = false;
        $data->$k = langa_tools_client_replace_recursive($search, $replace, $v, $c);
        if ($c) $did_change = true;
      }
      return $data;
    }

    if (!is_string($data)) {
      return $data;
    }

    // Serialized-safe replace
    if (function_exists('is_serialized') && is_serialized($data)) {
      $un = unserialize($data); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- serialized DB data replacement
      if ($un !== false || $data === 'b:0;') {
        $c = false;
        $un2 = langa_tools_client_replace_recursive($search, $replace, $un, $c);
        if ($c) {
          $did_change = true;
          return serialize($un2);
        }
        return $data;
      }
    }

    if ($search === '') return $data;

    // Build replacement pairs.
    // - URL smart: adds variants with/without trailing slash and relative path variants
    // - Escaped slashes: supports JSON-escaped (\/), double-escaped (\\/)
    $pairs_map = array();
    $push_pair = function($s, $r) use (&$pairs_map) {
      $s = (string)$s;
      $r = (string)$r;
      if ($s === '') return;
      $k = $s . "\n" . $r;
      if (!isset($pairs_map[$k])) $pairs_map[$k] = array($s, $r);
    };

    // Base
    $push_pair($search, $replace);

    // URL-ish heuristics
    $is_url = (stripos($search, 'http://') === 0) || (stripos($search, 'https://') === 0) || (strpos($search, '://') !== false);
    if ($is_url || strpos($search, '/') !== false) {
      $s_base = rtrim($search, "/\t\n\r\0\x0B");
      $r_base = rtrim($replace, "/\t\n\r\0\x0B");

      // Trailing slash variants
      $s_no = rtrim($s_base, '/');
      $r_no = rtrim($r_base, '/');
      if ($s_no !== '') {
        $push_pair($s_no, $r_no);
        $push_pair($s_no.'/', $r_no.'/');
      }

      // Relative variants (useful for menu/buttons saved as /path/)
      if ($is_url) {
        $u1 = @parse_url($s_base);
        $u2 = @parse_url($r_base);
        if (is_array($u1) && !empty($u1['host'])) {
          $p1 = isset($u1['path']) ? (string)$u1['path'] : '';
          $p2 = (is_array($u2) && isset($u2['path'])) ? (string)$u2['path'] : '';
          if ($p1 !== '') {
            $p1_no = rtrim($p1, '/');
            $p2_no = rtrim($p2, '/');
            $push_pair($p1_no, $p2_no);
            $push_pair($p1_no.'/', $p2_no.'/');
            $push_pair(ltrim($p1_no, '/'), ltrim($p2_no, '/'));
            $push_pair(ltrim($p1_no, '/').'/', ltrim($p2_no, '/').'/');
          }

          // Protocol-relative + hostless scheme variants (//host/path)
          $host1 = (string)$u1['host'];
          $host2 = (is_array($u2) && !empty($u2['host'])) ? (string)$u2['host'] : $host1;
          if (!empty($p1)) {
            $push_pair($host1 . $p1, $host2 . $p2);
            $push_pair('//' . $host1 . $p1, '//' . $host2 . $p2);
          }
        }
      }

      // Escaped slash variants for every base pair
      $base_pairs = array_values($pairs_map);
      foreach ($base_pairs as $pp) {
        $s = (string)($pp[0] ?? '');
        $r = (string)($pp[1] ?? '');
        if ($s === '' || strpos($s, '/') === false) continue;
        $push_pair(str_replace('/', '\\/', $s), str_replace('/', '\\/', $r));      // \/
        $push_pair(str_replace('/', '\\\\/', $s), str_replace('/', '\\\\/', $r)); // \\/
      }
    }

    $pairs = array_values($pairs_map);

    $new = $data;
    $changed = false;
    foreach ($pairs as $p) {
      $s = (string)($p[0] ?? '');
      $r = (string)($p[1] ?? '');
      if ($s === '') continue;
      if (strpos($new, $s) === false) continue;
      $tmp = str_replace($s, $r, $new);
      if ($tmp !== $new) {
        $new = $tmp;
        $changed = true;
      }
    }

    if ($changed) $did_change = true;
    return $new;
  }
}

if (!function_exists('langa_tools_client_search_replace_run')) {
  function langa_tools_client_search_replace_run($search, $replace, $tables, $dry_run = true, $include_guids = false, $max_rows_per_table = 0) {
    global $wpdb;
    if (!$wpdb) return array('ok' => false, 'msg' => 'DB not available.');

    if (function_exists('set_time_limit')) {
      set_time_limit(120); // phpcs:ignore -- scoped to replace operation only
    }

    $search = (string)$search;
    $replace = (string)$replace;
    if ($search === '') {
      return array('ok' => false, 'msg' => 'The “Search for” field is required.');
    }

    if (!is_array($tables) || empty($tables)) {
      return array('ok' => false, 'msg' => 'Select at least 1 table.');
    }

    // Validate tables exist
    $db_tables = $wpdb->get_col('SHOW TABLES'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- schema introspection
    $db_tables = is_array($db_tables) ? $db_tables : array();
    $db_lookup = array();
    foreach ($db_tables as $t) $db_lookup[(string)$t] = true;

    $tables_ok = array();
    foreach ($tables as $t) {
      $t = (string)$t;
      if ($t === '' || empty($db_lookup[$t])) continue;
      $tables_ok[] = $t;
    }
    $tables_ok = array_values(array_unique($tables_ok));
    if (empty($tables_ok)) {
      return array('ok' => false, 'msg' => 'No valid table selected.');
    }

    $t0 = microtime(true);
    $report = array(
      'ts' => time(),
      'dry_run' => $dry_run ? 1 : 0,
      'search' => $search,
      'replace' => $replace,
      'tables_selected' => $tables_ok,
      'include_guids' => $include_guids ? 1 : 0,
      'max_rows_per_table' => (int)$max_rows_per_table,
      'tables' => array(),
      'total_rows_changed' => 0,
      'total_cells_changed' => 0,
      'skipped' => array(),
    );

    foreach ($tables_ok as $table) {
      // Columns
      $cols = $wpdb->get_results('SHOW COLUMNS FROM `'.$table.'`', ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- dynamic schema
      if (!is_array($cols) || empty($cols)) {
        $report['skipped'][] = array('table' => $table, 'reason' => 'columns');
        continue;
      }

      $pk_cols = array();
      $text_cols = array();
      foreach ($cols as $c) {
        if (!is_array($c) || empty($c['Field'])) continue;
        $field = (string)$c['Field'];
        $key = isset($c['Key']) ? (string)$c['Key'] : '';
        $type = isset($c['Type']) ? (string)$c['Type'] : '';
        if ($key === 'PRI') $pk_cols[] = $field;

        if (!$include_guids && strtolower($field) === 'guid') {
          continue;
        }
        if (langa_tools_client_replace_is_text_column($type)) {
          $text_cols[] = $field;
        }
      }

      if (empty($pk_cols)) {
        $report['skipped'][] = array('table' => $table, 'reason' => 'no_primary_key');
        continue;
      }
      if (empty($text_cols)) {
        $report['tables'][] = array('table' => $table, 'rows_scanned' => 0, 'rows_changed' => 0, 'cells_changed' => 0);
        continue;
      }

      $select_cols = array_merge($pk_cols, $text_cols);
      // Table and column names from DB schema (SHOW TABLES/COLUMNS); esc_sql + backtick is correct for identifiers.
      $select_sql = 'SELECT ';
      $tmp = array();
      foreach ($select_cols as $cc) {
        $tmp[] = '`' . sanitize_key($cc) . '`'; // Column name validated against DESCRIBE results above // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifier from schema
      }
      $safe_table = '`' . esc_sql($table) . '`'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- from SHOW TABLES
      $select_sql .= implode(',', $tmp) . ' FROM ' . $safe_table; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers only

      $offset = 0;
      $batch = 200;
      $rows_scanned = 0;
      $rows_changed = 0;
      $cells_changed = 0;

      while (true) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are from wpdb schema, not user input
        $limit_sql = $wpdb->prepare( $select_sql . ' LIMIT %d OFFSET %d', $batch, $offset );
        $rows = $wpdb->get_results($limit_sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- column names validated against DESCRIBE, table from $wpdb->tables
        if (!is_array($rows) || empty($rows)) break;

        foreach ($rows as $row) {
          $rows_scanned++;
          $data_updates = array();
          $formats = array();
          foreach ($text_cols as $col) {
            if (!array_key_exists($col, $row)) continue;
            $val = $row[$col];
            if ($val === null) continue;

            $did = false;
            $new = langa_tools_client_replace_recursive($search, $replace, $val, $did);
            if ($did) {
              $data_updates[$col] = $new;
              $formats[] = '%s';
              $cells_changed++;
            }
          }

          if (!empty($data_updates)) {
            $rows_changed++;
            if (!$dry_run) {
              $where = array();
              $where_format = array();
              foreach ($pk_cols as $pk) {
                $where[$pk] = $row[$pk];
                $where_format[] = (is_numeric($row[$pk]) && (string)(int)$row[$pk] === (string)$row[$pk]) ? '%d' : '%s';
              }
              $wpdb->update($table, $data_updates, $where, $formats, $where_format);
            }
          }

          if ($max_rows_per_table > 0 && $rows_scanned >= (int)$max_rows_per_table) {
            break 2;
          }
        }

        $offset += $batch;
      }

      $report['tables'][] = array(
        'table' => $table,
        'rows_scanned' => $rows_scanned,
        'rows_changed' => $rows_changed,
        'cells_changed' => $cells_changed,
      );
      $report['total_rows_changed'] += $rows_changed;
      $report['total_cells_changed'] += $cells_changed;
    }

    $report['elapsed_ms'] = (int)round((microtime(true) - $t0) * 1000);

    return array('ok' => true, 'report' => $report);
  }
}

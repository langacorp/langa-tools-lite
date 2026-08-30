<?php
/**
 * WP Core Dependencies Manager
 * Ensures required framework components are loaded in correct order.
 * @version 1.0.0
 */
if (!defined('ABSPATH')) exit;
$_dep_dir = __DIR__ . '/';
if (file_exists($_dep_dir . 'starter-starter.php'))        require_once $_dep_dir . 'starter-starter.php';
if (file_exists($_dep_dir . 'starter-visual-builder.php')) require_once $_dep_dir . 'starter-visual-builder.php';
if (file_exists($_dep_dir . 'starter-select2.php'))        require_once $_dep_dir . 'starter-select2.php';
if (file_exists($_dep_dir . 'starter-forms-handler.php'))  require_once $_dep_dir . 'starter-forms-handler.php';

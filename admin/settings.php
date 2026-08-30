<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin entrypoint.
 * Kept for backwards compatibility: the plugin loads this file.
 * Actual implementation lives in /admin/settings/* (split for maintainability).
 */
require_once __DIR__ . '/settings/bootstrap.php';

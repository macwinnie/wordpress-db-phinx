<?php

/**
 * Stub file for PHPStan — emulates WordPress DB constants and globals.
 *
 * This file is ONLY for static analysis, not for runtime.
 * It prevents "Constant ... not found" errors in PHPStan.
 */

// === Database configuration ===
if (!defined('DB_NAME')) {
    define('DB_NAME', 'wordpress_db');
}

if (!defined('DB_USER')) {
    define('DB_USER', 'wp_user');
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', 'secret');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('DB_COLLATE')) {
    define('DB_COLLATE', '');
}

// === Plugin Config ===

if (!defined('MACWINNIE_XYZ_PLUGIN_DIR')) {
    define('MACWINNIE_XYZ_PLUGIN_DIR', dirname(__DIR__));
}

if (!defined('MACWINNIE_XYZ_PLUGIN_FILE')) {
    define('MACWINNIE_XYZ_PLUGIN_FILE', MACWINNIE_XYZ_PLUGIN_DIR . '/macwinnie-xyz.php');
}

// === WordPress wpdb result type constants ===
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

<?php
/**
 * Plugin Name: GourmetPress - Enterprise Restaurant Ordering
 * Plugin URI: https://gourmetpress.io
 * Description: High-performance restaurant ordering system with multi-location, delivery management, KDS, and mobile APIs
 * Version: 2.0.0
 * Author: GourmetPress Inc.
 * License: GPL-2.0+
 * Text Domain: gourmetpress
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) exit;

define('GP_VERSION', '2.0.0');
define('GP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GP_DB_VERSION', '2.0.0');

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'GourmetPress\\';
    $base_dir = GP_PLUGIN_DIR . 'src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) require $file;
});

// Activation
register_activation_hook(__FILE__, function() {
    \GourmetPress\Core\Activator::activate();
});

// Deactivation
register_deactivation_hook(__FILE__, function() {
    \GourmetPress\Core\Deactivator::deactivate();
});

// Init
add_action('plugins_loaded', function() {
    $plugin = \GourmetPress\Core\Container::instance()->get('plugin');
    $plugin->run();
});

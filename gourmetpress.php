<?php
/**
 * Plugin Name: GourmetPress - Enterprise Restaurant Ordering
 * Plugin URI: https://gourmetpress.io
 * Description: High-performance restaurant ordering system for WordPress
 * Version: 1.0.0
 * Author: GourmetPress Inc.
 * License: GPL-2.0+
 * Text Domain: gourmetpress
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

define('GOURMETPRESS_VERSION', '1.0.0');
define('GOURMETPRESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOURMETPRESS_PLUGIN_URL', plugin_dir_url(__FILE__));

// BUILT-IN AUTOLOADER - No composer needed
spl_autoload_register(function ($class) {
    $prefix = 'GourmetPress\\';
    $base_dir = GOURMETPRESS_PLUGIN_DIR . 'src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once GOURMETPRESS_PLUGIN_DIR . 'src/Core/Activator.php';
    \GourmetPress\Core\Activator::activate();
});

// Initialize plugin
add_action('plugins_loaded', function() {
    $plugin = \GourmetPress\Core\Plugin::instance();
    $plugin->run();
});

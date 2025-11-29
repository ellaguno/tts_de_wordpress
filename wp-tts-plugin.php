<?php
/**
 * Plugin Name: TTS SesoLibre
 * Plugin URI: https://github.com/ellaguno/tts_sesolibre.git
 * Description: Convert WordPress articles to audio using multiple TTS providers with cost optimization and Spanish language focus.
 * Version: 1.7.0
 * Author: Eduardo Llaguno
 * Author URI: https://sesolibre.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-tts-sesolibre
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_TTS_PLUGIN_VERSION', '1.7.0');
define('WP_TTS_PLUGIN_FILE', __FILE__);
define('WP_TTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_TTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_TTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required classes directly for activation/deactivation
require_once WP_TTS_PLUGIN_DIR . 'src/Core/Activator.php';
require_once WP_TTS_PLUGIN_DIR . 'src/Core/Deactivator.php';

// Manual autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Check if the class belongs to our namespace
    if (strpos($class, 'WP_TTS\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $class_file = str_replace('WP_TTS\\', '', $class);
    $class_file = str_replace('\\', '/', $class_file);
    $file_path = WP_TTS_PLUGIN_DIR . 'src/' . $class_file . '.php';
    
    // Include the file if it exists
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

// Autoload classes using Composer (if available)
if (file_exists(WP_TTS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WP_TTS_PLUGIN_DIR . 'vendor/autoload.php';
}

use WP_TTS\Core\Plugin;
use WP_TTS\Core\Activator;
use WP_TTS\Core\Deactivator;

/**
 * Initialize the plugin
 */
function wp_tts_plugin_init() {
    $plugin = Plugin::getInstance();
    $plugin->run();
}

/**
 * Plugin activation hook
 */
function wp_tts_plugin_activate() {
    Activator::activate();
}

/**
 * Plugin deactivation hook
 */
function wp_tts_plugin_deactivate() {
    Deactivator::deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'wp_tts_plugin_activate');
register_deactivation_hook(__FILE__, 'wp_tts_plugin_deactivate');

// Initialize the plugin
add_action('plugins_loaded', 'wp_tts_plugin_init');

/**
 * Add action links to plugin page
 */
function wp_tts_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=wp-tts-settings') . '">' . __('Configuración', 'wp-tts-sesolibre') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . WP_TTS_PLUGIN_BASENAME, 'wp_tts_plugin_action_links');

/**
 * Add meta links to plugin page
 */
function wp_tts_plugin_meta_links($links, $file) {
    if ($file === WP_TTS_PLUGIN_BASENAME) {
        $links[] = '<a href="https://github.com/ellaguno/tts_de_wordpress/wiki" target="_blank">' . __('Documentación', 'wp-tts-sesolibre') . '</a>';
        $links[] = '<a href="https://github.com/ellaguno/tts_de_wordpress/issues" target="_blank">' . __('Soporte', 'wp-tts-sesolibre') . '</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'wp_tts_plugin_meta_links', 10, 2);

<?php
/**
 * Plugin Name: WordPress Text-to-Speech
 * Plugin URI: https://github.com/ellaguno/tts_de_wordpress.git
 * Description: Convert WordPress articles to audio using multiple TTS providers with cost optimization and Spanish language focus.
 * Version: 1.0.0
 * Author: Eduardo Llaguno
 * Author URI: https://sesolibre.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: TTS de Wordpress
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
define('WP_TTS_PLUGIN_VERSION', '1.0.0');
define('WP_TTS_PLUGIN_FILE', __FILE__);
define('WP_TTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_TTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_TTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoload classes using Composer
if (file_exists(WP_TTS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WP_TTS_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include the main plugin class
require_once WP_TTS_PLUGIN_DIR . 'src/Core/Plugin.php';

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
    $settings_link = '<a href="' . admin_url('admin.php?page=wp-tts-settings') . '">' . __('Settings', 'TTS de Wordpress') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . WP_TTS_PLUGIN_BASENAME, 'wp_tts_plugin_action_links');

/**
 * Add meta links to plugin page
 */
function wp_tts_plugin_meta_links($links, $file) {
    if ($file === WP_TTS_PLUGIN_BASENAME) {
        $links[] = '<a href="https://github.com/your-username/TTS de Wordpress/wiki" target="_blank">' . __('Documentation', 'TTS de Wordpress') . '</a>';
        $links[] = '<a href="https://github.com/your-username/TTS de Wordpress/issues" target="_blank">' . __('Support', 'TTS de Wordpress') . '</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'wp_tts_plugin_meta_links', 10, 2);
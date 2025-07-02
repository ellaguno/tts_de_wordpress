<?php
/**
 * Debug script to check BuzzSprout configuration
 * Run this from WordPress admin or via WP-CLI
 */

// This should be run within WordPress context
if (!defined('ABSPATH')) {
    // If running standalone, try to load WordPress
    $wp_config_path = '/var/www/html/wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once($wp_config_path);
        require_once(ABSPATH . 'wp-settings.php');
    } else {
        die("Cannot load WordPress. Please run this from within WordPress admin or adjust the path.\n");
    }
}

echo "=== TTS BuzzSprout Configuration Debug ===\n\n";

// Check raw WordPress options
echo "1. Raw WordPress Options:\n";
$wp_tts_storage_config = get_option('wp_tts_storage_config', 'NOT_FOUND');
echo "wp_tts_storage_config: " . print_r($wp_tts_storage_config, true) . "\n";

$wp_tts_config = get_option('wp_tts_config', 'NOT_FOUND');
echo "wp_tts_config: " . print_r($wp_tts_config, true) . "\n";

// Check via ConfigurationManager
if (class_exists('WP_TTS\\Core\\ConfigurationManager')) {
    echo "\n2. Via ConfigurationManager:\n";
    try {
        $config_manager = new WP_TTS\Core\ConfigurationManager();
        
        echo "Storage config: " . print_r($config_manager->get('storage'), true) . "\n";
        echo "BuzzSprout config: " . print_r($config_manager->getStorageConfig('buzzsprout'), true) . "\n";
        
        // Test individual settings
        echo "auto_publish: " . var_export($config_manager->get('storage.buzzsprout.auto_publish'), true) . "\n";
        echo "make_private: " . var_export($config_manager->get('storage.buzzsprout.make_private'), true) . "\n";
        echo "include_link: " . var_export($config_manager->get('storage.buzzsprout.include_link'), true) . "\n";
        echo "default_tags: " . var_export($config_manager->get('storage.buzzsprout.default_tags'), true) . "\n";
        
    } catch (Exception $e) {
        echo "Error loading ConfigurationManager: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n2. ConfigurationManager class not found\n";
}

echo "\n=== End Debug ===\n";
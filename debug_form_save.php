<?php
/**
 * Debug form save process specifically
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>Form Save Process Debug</h1>";

$test_post_id = 635; // From the logs

echo "<h2>Test 1: Check Current TTS Data</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $current_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    echo "Current TTS data: <pre>" . print_r($current_data, true) . "</pre>";
} else {
    echo "❌ TTSMetaManager not available<br>";
}

echo "<h2>Test 2: Simulate Meta Box Form Submission</h2>";

// Simulate what the meta box form would send
$_POST = [
    'wp_tts_meta_nonce' => wp_create_nonce('wp_tts_meta_box'),
    'tts_enabled' => '1',
    'tts_voice_provider' => 'azure_tts',
    'tts_voice_id' => 'es-MX-CandelaNeural',
    'tts_custom_text' => ''
];

echo "Simulated POST data: <pre>" . print_r($_POST, true) . "</pre>";

echo "<h2>Test 3: Manual Save Using TTSMetaManager Methods</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    try {
        // Step 1: Enable TTS
        $enabled_result = \WP_TTS\Utils\TTSMetaManager::setTTSEnabled($test_post_id, true);
        echo "setTTSEnabled result: " . ($enabled_result ? 'SUCCESS' : 'FAILED') . "<br>";
        
        // Step 2: Set voice config
        $voice_result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig(
            $test_post_id, 
            'azure_tts', 
            'es-MX-CandelaNeural'
        );
        echo "setVoiceConfig result: " . ($voice_result ? 'SUCCESS' : 'FAILED') . "<br>";
        
        // Step 3: Verify what was saved
        $saved_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
        echo "Data after manual save: <pre>" . print_r($saved_data, true) . "</pre>";
        
        // Step 4: Check voice config specifically
        $voice_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
        echo "Voice config after save: <pre>" . print_r($voice_config, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "❌ Exception during manual save: " . $e->getMessage() . "<br>";
        echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<h2>Test 4: Check Database Directly</h2>";

global $wpdb;
$meta_value = $wpdb->get_var($wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_tts_sesolibre'",
    $test_post_id
));

if ($meta_value) {
    echo "Database value: <pre>" . htmlspecialchars($meta_value) . "</pre>";
    $decoded = json_decode($meta_value, true);
    if ($decoded) {
        echo "Decoded JSON: <pre>" . print_r($decoded, true) . "</pre>";
    }
} else {
    echo "❌ No database value found<br>";
}

echo "<h2>Test 5: Test Plugin::saveTTSSettings Logic</h2>";

// Load the SecurityManager
try {
    $security = new \WP_TTS\Utils\SecurityManager();
    
    // Simulate the plugin save logic
    $enabled = isset($_POST['tts_enabled']) ? true : false;
    echo "Enabled: " . ($enabled ? 'true' : 'false') . "<br>";
    
    if (isset($_POST['tts_voice_provider'])) {
        $provider = $security->sanitizeInput($_POST['tts_voice_provider']);
        $voice_id = '';
        if (isset($_POST['tts_voice_id'])) {
            $voice_id = $security->sanitizeInput($_POST['tts_voice_id']);
        }
        
        echo "Provider after sanitization: '$provider'<br>";
        echo "Voice ID after sanitization: '$voice_id'<br>";
        
        if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
            echo "Calling TTSMetaManager::setVoiceConfig...<br>";
            $save_result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig($test_post_id, $provider, $voice_id);
            echo "Save result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "<br>";
        }
    } else {
        echo "❌ tts_voice_provider not set in POST data<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception testing plugin save logic: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";

// Clean up
unset($_POST);
?>
<?php
/**
 * Debug Enable TTS checkbox and provider/voice saving
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>Debug Enable TTS & Provider/Voice Issues</h1>";

// Use a real post ID from your system
$test_post_id = 635; // From your previous logs

echo "<h2>Current State Check</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $current_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    
    echo "<strong>Current TTS Data:</strong><br>";
    echo "- Enabled: " . (isset($current_data['enabled']) ? ($current_data['enabled'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
    echo "- Provider: '" . ($current_data['voice']['provider'] ?? '') . "'<br>";
    echo "- Voice ID: '" . ($current_data['voice']['voice_id'] ?? '') . "'<br>";
    echo "- Updated at: '" . ($current_data['updated_at'] ?? '') . "'<br>";
    
    echo "<br><strong>Raw data structure:</strong><pre>" . print_r($current_data, true) . "</pre>";
} else {
    echo "❌ TTSMetaManager not available<br>";
}

echo "<h2>Test 1: Enable TTS Only</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    echo "Setting TTS enabled to TRUE...<br>";
    $result = \WP_TTS\Utils\TTSMetaManager::setTTSEnabled($test_post_id, true);
    echo "Result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    // Immediate check
    $check_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    echo "Immediate check - Enabled: " . (isset($check_data['enabled']) ? ($check_data['enabled'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
    echo "Updated at: '" . ($check_data['updated_at'] ?? '') . "'<br>";
}

echo "<h2>Test 2: Set Provider and Voice</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    echo "Setting provider to 'azure_tts' and voice to 'es-MX-CandelaNeural'...<br>";
    $result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig($test_post_id, 'azure_tts', 'es-MX-CandelaNeural');
    echo "Result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    // Immediate check
    $check_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    echo "Immediate check - Provider: '" . ($check_data['voice']['provider'] ?? '') . "'<br>";
    echo "Immediate check - Voice: '" . ($check_data['voice']['voice_id'] ?? '') . "'<br>";
}

echo "<h2>Test 3: Simulate Complete Meta Box Save</h2>";

// Simulate exactly what happens when user saves meta box
$_POST = [
    'wp_tts_meta_nonce' => wp_create_nonce('wp_tts_meta_box'),
    'tts_enabled' => '1',
    'tts_voice_provider' => 'azure_tts',
    'tts_voice_id' => 'es-MX-CandelaNeural',
    'tts_custom_text' => ''
];

echo "Simulated POST data: <pre>" . print_r($_POST, true) . "</pre>";

// Simulate the saveTTSSettings method logic
if (class_exists('\\WP_TTS\\Utils\\SecurityManager') && class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $security = new \WP_TTS\Utils\SecurityManager();
    
    $enabled = isset($_POST['tts_enabled']) ? true : false;
    echo "<strong>Step 1 - Enable TTS:</strong><br>";
    echo "Enabled value: " . ($enabled ? 'true' : 'false') . "<br>";
    
    $enabled_result = \WP_TTS\Utils\TTSMetaManager::setTTSEnabled($test_post_id, $enabled);
    echo "setTTSEnabled result: " . ($enabled_result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    echo "<strong>Step 2 - Voice Config:</strong><br>";
    if (isset($_POST['tts_voice_provider'])) {
        $provider = $security->sanitizeInput($_POST['tts_voice_provider']);
        $voice_id = '';
        if (isset($_POST['tts_voice_id'])) {
            $voice_id = $security->sanitizeInput($_POST['tts_voice_id']);
        }
        
        echo "Provider: '$provider'<br>";
        echo "Voice ID: '$voice_id'<br>";
        
        $voice_result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig($test_post_id, $provider, $voice_id);
        echo "setVoiceConfig result: " . ($voice_result ? 'SUCCESS' : 'FAILED') . "<br>";
    } else {
        echo "❌ tts_voice_provider not in POST<br>";
    }
}

echo "<h2>Test 4: Final State After Save</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $final_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    
    echo "<strong>Final TTS Data:</strong><br>";
    echo "- Enabled: " . (isset($final_data['enabled']) ? ($final_data['enabled'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
    echo "- Provider: '" . ($final_data['voice']['provider'] ?? '') . "'<br>";
    echo "- Voice ID: '" . ($final_data['voice']['voice_id'] ?? '') . "'<br>";
    echo "- Updated at: '" . ($final_data['updated_at'] ?? '') . "'<br>";
    
    // Check what renderTTSMetaBox would see
    echo "<br><strong>What renderTTSMetaBox would see:</strong><br>";
    $enabled_for_display = (bool) $final_data['enabled'];
    echo "- Enabled (bool cast): " . ($enabled_for_display ? 'TRUE' : 'FALSE') . "<br>";
    echo "- Checkbox would be: " . ($enabled_for_display ? 'CHECKED' : 'UNCHECKED') . "<br>";
}

echo "<h2>Test 5: Database Direct Check</h2>";

global $wpdb;
$meta_value = $wpdb->get_var($wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_tts_sesolibre'",
    $test_post_id
));

if ($meta_value) {
    echo "Database raw value: <pre>" . htmlspecialchars($meta_value) . "</pre>";
    $decoded = json_decode($meta_value, true);
    if ($decoded) {
        echo "Database decoded enabled: " . (isset($decoded['enabled']) ? ($decoded['enabled'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "<br>";
        echo "Database decoded provider: '" . ($decoded['voice']['provider'] ?? '') . "'<br>";
        echo "Database decoded voice: '" . ($decoded['voice']['voice_id'] ?? '') . "'<br>";
    }
} else {
    echo "❌ No database value found<br>";
}

// Clean up
unset($_POST);

echo "<h2>Conclusion</h2>";
echo "Check the results above to see exactly where the data is being lost.<br>";

?>
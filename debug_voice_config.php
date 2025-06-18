<?php
/**
 * Debug voice configuration specifically
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>Voice Configuration Debug</h1>";

// Test with the post ID from the logs
$test_post_id = 635; // From the logs: post_id => 635

echo "<h2>Test 1: TTSMetaManager::getVoiceConfig()</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    try {
        echo "Getting voice config for post $test_post_id...<br>";
        $voice_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
        
        echo "Voice config result: <pre>" . print_r($voice_config, true) . "</pre>";
        
        // Check individual values
        $provider = $voice_config['provider'] ?? '';
        $voice_id = $voice_config['voice_id'] ?? '';
        
        echo "Provider: '$provider' (empty: " . (empty($provider) ? 'YES' : 'NO') . ")<br>";
        echo "Voice ID: '$voice_id' (empty: " . (empty($voice_id) ? 'YES' : 'NO') . ")<br>";
        
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ TTSMetaManager class not found<br>";
}

echo "<h2>Test 2: TTSMetaManager::getTTSData()</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    try {
        echo "Getting full TTS data for post $test_post_id...<br>";
        $tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
        
        echo "Full TTS data: <pre>" . print_r($tts_data, true) . "</pre>";
        
        // Check if voice section exists
        if (isset($tts_data['voice'])) {
            echo "Voice section exists: <pre>" . print_r($tts_data['voice'], true) . "</pre>";
        } else {
            echo "❌ Voice section missing from TTS data<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Test 3: Direct Database Check</h2>";

global $wpdb;

$meta_value = $wpdb->get_var($wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_tts_sesolibre'",
    $test_post_id
));

if ($meta_value) {
    echo "✅ Found _tts_sesolibre metadata<br>";
    $data = json_decode($meta_value, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "JSON decoded successfully: <pre>" . print_r($data, true) . "</pre>";
        
        if (isset($data['voice'])) {
            echo "Voice section: <pre>" . print_r($data['voice'], true) . "</pre>";
        } else {
            echo "❌ No voice section in JSON data<br>";
        }
    } else {
        echo "❌ JSON decode error: " . json_last_error_msg() . "<br>";
        echo "Raw meta value: <pre>" . htmlspecialchars($meta_value) . "</pre>";
    }
} else {
    echo "ℹ️ No _tts_sesolibre metadata found for post $test_post_id<br>";
}

echo "<h2>Test 4: Old System Check</h2>";

$old_provider = get_post_meta($test_post_id, '_tts_voice_provider', true);
$old_voice = get_post_meta($test_post_id, '_tts_voice_id', true);

echo "Old system provider: '$old_provider' (empty: " . (empty($old_provider) ? 'YES' : 'NO') . ")<br>";
echo "Old system voice: '$old_voice' (empty: " . (empty($old_voice) ? 'YES' : 'NO') . ")<br>";

echo "<h2>Test 5: Simulate Form Save</h2>";

echo "Simulating what would happen if we save provider/voice...<br>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    try {
        // Test setting voice config
        $result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig($test_post_id, 'azure_tts', 'es-MX-CandelaNeural');
        
        if ($result) {
            echo "✅ setVoiceConfig succeeded<br>";
            
            // Immediately check what we get back
            $check_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
            echo "Immediately after setting: <pre>" . print_r($check_config, true) . "</pre>";
            
        } else {
            echo "❌ setVoiceConfig failed<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception during setVoiceConfig: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Test Complete</h2>";
?>
<?php
/**
 * Trace the complete provider/voice flow from database to generation
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>Complete Provider/Voice Flow Trace</h1>";

// Use a real post ID from your system
$test_post_id = 635; // From your logs

echo "<h2>Step 1: Check what's in the database RIGHT NOW</h2>";

global $wpdb;
$meta_value = $wpdb->get_var($wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_tts_sesolibre'",
    $test_post_id
));

if ($meta_value) {
    echo "✅ Found _tts_sesolibre metadata<br>";
    $data = json_decode($meta_value, true);
    echo "Raw JSON: <pre>" . htmlspecialchars($meta_value) . "</pre>";
    echo "Decoded data: <pre>" . print_r($data, true) . "</pre>";
    
    if (isset($data['voice'])) {
        echo "<strong>Voice section:</strong><br>";
        echo "- Provider: '" . ($data['voice']['provider'] ?? '') . "'<br>";
        echo "- Voice ID: '" . ($data['voice']['voice_id'] ?? '') . "'<br>";
        echo "- Language: '" . ($data['voice']['language'] ?? '') . "'<br>";
    } else {
        echo "❌ No voice section found<br>";
    }
} else {
    echo "❌ No _tts_sesolibre metadata found<br>";
}

echo "<h2>Step 2: Test TTSMetaManager::getTTSData()</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    echo "TTSMetaManager::getTTSData() result: <pre>" . print_r($tts_data, true) . "</pre>";
    
    if (isset($tts_data['voice'])) {
        echo "<strong>Voice from getTTSData:</strong><br>";
        echo "- Provider: '" . ($tts_data['voice']['provider'] ?? '') . "'<br>";
        echo "- Voice ID: '" . ($tts_data['voice']['voice_id'] ?? '') . "'<br>";
    }
} else {
    echo "❌ TTSMetaManager not available<br>";
}

echo "<h2>Step 3: Test TTSMetaManager::getVoiceConfig()</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $voice_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
    echo "TTSMetaManager::getVoiceConfig() result: <pre>" . print_r($voice_config, true) . "</pre>";
    
    echo "<strong>Voice config values:</strong><br>";
    echo "- Provider: '" . ($voice_config['provider'] ?? '') . "'<br>";
    echo "- Voice ID: '" . ($voice_config['voice_id'] ?? '') . "'<br>";
    echo "- Is provider empty? " . (empty($voice_config['provider']) ? 'YES' : 'NO') . "<br>";
    echo "- Is voice_id empty? " . (empty($voice_config['voice_id']) ? 'YES' : 'NO') . "<br>";
}

echo "<h2>Step 4: Force save some test data</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    echo "Setting test voice config: azure_tts + es-MX-CandelaNeural<br>";
    
    $save_result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig(
        $test_post_id, 
        'azure_tts', 
        'es-MX-CandelaNeural'
    );
    
    echo "Save result: " . ($save_result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    if ($save_result) {
        // Immediately check what was saved
        echo "<br><strong>Immediate verification:</strong><br>";
        
        $check_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
        echo "Immediately after save: <pre>" . print_r($check_config, true) . "</pre>";
        
        // Check database directly
        $check_db = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_tts_sesolibre'",
            $test_post_id
        ));
        
        if ($check_db) {
            $check_data = json_decode($check_db, true);
            echo "Database after save: <pre>" . print_r($check_data['voice'] ?? 'NO VOICE SECTION', true) . "</pre>";
        }
    }
}

echo "<h2>Step 5: Simulate TTSService::generateAudioForPost()</h2>";

// Load the services manually to test
if (class_exists('\\WP_TTS\\Utils\\Logger') && class_exists('\\WP_TTS\\Services\\TTSService')) {
    echo "Simulating the TTSService flow...<br>";
    
    // Get voice config like TTSService does
    if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
        $voice_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
        
        echo "Voice config from TTSMetaManager: <pre>" . print_r($voice_config, true) . "</pre>";
        
        $provider_from_meta = $voice_config['provider'] ?? '';
        $voice = $voice_config['voice_id'] ?? '';
        
        echo "Extracted values:<br>";
        echo "- provider_from_meta: '$provider_from_meta'<br>";
        echo "- voice: '$voice'<br>";
        
        // This is what gets passed to generateAudio
        $options = [
            'provider' => $provider_from_meta,
            'voice' => $voice,
            'post_id' => $test_post_id,
        ];
        
        echo "Options that would be passed to generateAudio: <pre>" . print_r($options, true) . "</pre>";
        
        // Check if provider is empty (this is the problem!)
        if (empty($options['provider'])) {
            echo "🚨 <strong>PROBLEM IDENTIFIED:</strong> Provider is EMPTY! This will cause generateAudio to use defaults.<br>";
        } else {
            echo "✅ Provider is set: '" . $options['provider'] . "'<br>";
        }
    }
}

echo "<h2>Step 6: Check default TTS settings</h2>";

$config = get_option('wp_tts_config', []);
echo "WordPress TTS config: <pre>" . print_r($config, true) . "</pre>";

$default_provider = $config['default_provider'] ?? 'google';
echo "Default provider: '$default_provider'<br>";

echo "<h2>Step 7: Check old metadata system</h2>";

$old_provider = get_post_meta($test_post_id, '_tts_voice_provider', true);
$old_voice = get_post_meta($test_post_id, '_tts_voice_id', true);

echo "Old system values:<br>";
echo "- _tts_voice_provider: '$old_provider'<br>";
echo "- _tts_voice_id: '$old_voice'<br>";

if (!empty($old_provider)) {
    echo "ℹ️ Old metadata exists - this might be interfering<br>";
}

echo "<h2>Conclusion</h2>";

echo "Now you can see exactly where the data is getting lost. Look for the 🚨 PROBLEM IDENTIFIED message above.<br>";

?>
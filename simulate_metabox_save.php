<?php
/**
 * Simulate exactly what happens when saving from meta box
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>Meta Box Save Simulation</h1>";

$test_post_id = 635; // Your post ID

echo "<h2>Step 1: Current state before save</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $before_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    echo "Before save: <pre>" . print_r($before_data['voice'], true) . "</pre>";
}

echo "<h2>Step 2: Simulate form submission</h2>";

// Simulate exactly what the meta box form sends
$_POST = [
    'wp_tts_meta_nonce' => wp_create_nonce('wp_tts_meta_box'),
    'tts_enabled' => '1',
    'tts_voice_provider' => 'azure_tts',  // This is what user selects
    'tts_voice_id' => 'es-MX-CandelaNeural',  // This is what user selects
    'tts_custom_text' => ''
];

echo "Simulated POST data: <pre>" . print_r($_POST, true) . "</pre>";

echo "<h2>Step 3: Execute Plugin::saveTTSSettings logic manually</h2>";

// This is exactly what Plugin::saveTTSSettings does
$enabled = isset($_POST['tts_enabled']) ? true : false;
echo "Enabled: " . ($enabled ? 'true' : 'false') . "<br>";

if (class_exists('\\WP_TTS\\Utils\\SecurityManager')) {
    $security = new \WP_TTS\Utils\SecurityManager();
    
    echo "<strong>Setting TTS enabled...</strong><br>";
    if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
        $enabled_result = \WP_TTS\Utils\TTSMetaManager::setTTSEnabled($test_post_id, $enabled);
        echo "setTTSEnabled result: " . ($enabled_result ? 'SUCCESS' : 'FAILED') . "<br>";
    }
    
    if (isset($_POST['tts_voice_provider'])) {
        $provider = $security->sanitizeInput($_POST['tts_voice_provider']);
        $voice_id = '';
        if (isset($_POST['tts_voice_id'])) {
            $voice_id = $security->sanitizeInput($_POST['tts_voice_id']);
        }
        
        echo "<strong>Voice config to save:</strong><br>";
        echo "- Provider: '$provider'<br>";
        echo "- Voice ID: '$voice_id'<br>";
        
        if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
            echo "<br>Calling setVoiceConfig...<br>";
            $voice_result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig($test_post_id, $provider, $voice_id);
            echo "setVoiceConfig result: " . ($voice_result ? 'SUCCESS' : 'FAILED') . "<br>";
            
            if ($voice_result) {
                // Immediate verification
                echo "<br><strong>Immediate verification:</strong><br>";
                $verify_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
                echo "Saved config: <pre>" . print_r($verify_config, true) . "</pre>";
            }
        }
    } else {
        echo "❌ tts_voice_provider not in POST<br>";
    }
}

echo "<h2>Step 4: Check final state</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    $after_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
    echo "After save - full data: <pre>" . print_r($after_data, true) . "</pre>";
    
    echo "<strong>Voice section after save:</strong><br>";
    echo "- Provider: '" . ($after_data['voice']['provider'] ?? '') . "'<br>";
    echo "- Voice ID: '" . ($after_data['voice']['voice_id'] ?? '') . "'<br>";
    
    // Check if it's still empty
    if (empty($after_data['voice']['provider'])) {
        echo "🚨 <strong>PROBLEM:</strong> Provider is STILL EMPTY after save!<br>";
        
        // Let's debug TTSMetaManager::setVoiceConfig step by step
        echo "<br><h3>Debug setVoiceConfig step by step:</h3>";
        
        echo "Testing setVoiceConfig with 'test_provider' and 'test_voice'...<br>";
        $debug_result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig($test_post_id, 'test_provider', 'test_voice');
        echo "Debug setVoiceConfig result: " . ($debug_result ? 'SUCCESS' : 'FAILED') . "<br>";
        
        if ($debug_result) {
            $debug_check = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
            echo "Debug check result: <pre>" . print_r($debug_check, true) . "</pre>";
        }
    } else {
        echo "✅ Provider saved successfully!<br>";
    }
}

echo "<h2>Step 5: Test the generation flow</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    echo "Testing what generateAudioForPost would see...<br>";
    
    $voice_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig($test_post_id);
    $provider_from_meta = $voice_config['provider'] ?? '';
    $voice = $voice_config['voice_id'] ?? '';
    
    echo "Values for generation:<br>";
    echo "- provider_from_meta: '$provider_from_meta'<br>";
    echo "- voice: '$voice'<br>";
    
    if (empty($provider_from_meta)) {
        echo "🚨 This will cause generateAudio to use DEFAULT provider!<br>";
        
        // Show what default would be used
        $config = get_option('wp_tts_config', []);
        $default_provider = $config['default_provider'] ?? 'google';
        echo "Default provider that would be used: '$default_provider'<br>";
    } else {
        echo "✅ Custom provider would be used: '$provider_from_meta'<br>";
    }
}

// Clean up
unset($_POST);

echo "<h2>Simulation Complete</h2>";
echo "If you see 🚨 problems above, we've found the issue!<br>";

?>
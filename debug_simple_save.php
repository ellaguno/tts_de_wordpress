<?php
/**
 * Simple test to debug metadata saving
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>Simple Metadata Save Test</h1>";

$test_post_id = 417; // The post from your logs

echo "<h2>Test 1: Direct update_post_meta test</h2>";

// Test simple data first
$simple_data = ['test' => 'value', 'timestamp' => time()];
$simple_result = update_post_meta($test_post_id, '_tts_debug_simple', $simple_data);

if ($simple_result) {
    echo "✅ Simple update_post_meta works<br>";
    
    // Verify it was saved
    $saved_simple = get_post_meta($test_post_id, '_tts_debug_simple', true);
    if ($saved_simple) {
        echo "✅ Simple data verified: " . print_r($saved_simple, true) . "<br>";
    } else {
        echo "❌ Simple data not found after save<br>";
    }
} else {
    echo "❌ Simple update_post_meta failed<br>";
}

echo "<h2>Test 2: TTS Structure test</h2>";

// Test with TTS-like structure
$tts_data = [
    'version' => '1.0',
    'enabled' => false,
    'audio' => [
        'url' => 'https://example.com/test.mp3',
        'generated_at' => date('Y-m-d H:i:s'),
        'status' => 'completed'
    ],
    'voice' => [
        'provider' => 'azure_tts',
        'voice_id' => 'es-MX-CandelaNeural'
    ],
    'updated_at' => date('Y-m-d H:i:s')
];

echo "Attempting to save TTS structure: <pre>" . print_r($tts_data, true) . "</pre>";

$tts_result = update_post_meta($test_post_id, '_tts_sesolibre', $tts_data);

if ($tts_result) {
    echo "✅ TTS structure update_post_meta works<br>";
    
    // Verify it was saved
    $saved_tts = get_post_meta($test_post_id, '_tts_sesolibre', true);
    if ($saved_tts) {
        echo "✅ TTS data verified: <pre>" . print_r($saved_tts, true) . "</pre>";
    } else {
        echo "❌ TTS data not found after save<br>";
    }
} else {
    echo "❌ TTS structure update_post_meta failed<br>";
}

echo "<h2>Test 3: TTSMetaManager methods test</h2>";

if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    try {
        echo "Testing TTSMetaManager::getTTSData...<br>";
        $current_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
        echo "Current data: <pre>" . print_r($current_data, true) . "</pre>";
        
        echo "Testing TTSMetaManager::saveTTSData...<br>";
        $test_data = $current_data;
        $test_data['audio']['url'] = 'https://test.com/debug-' . time() . '.mp3';
        $test_data['updated_at'] = date('Y-m-d H:i:s');
        
        echo "Attempting to save: <pre>" . print_r($test_data, true) . "</pre>";
        
        $save_result = \WP_TTS\Utils\TTSMetaManager::saveTTSData($test_post_id, $test_data);
        
        if ($save_result) {
            echo "✅ TTSMetaManager::saveTTSData returned success<br>";
        } else {
            echo "❌ TTSMetaManager::saveTTSData returned failure<br>";
        }
        
        // Check what's actually in the database
        $verify_data = get_post_meta($test_post_id, '_tts_sesolibre', true);
        if ($verify_data) {
            echo "✅ Database verification successful: <pre>" . print_r($verify_data, true) . "</pre>";
        } else {
            echo "❌ Database verification failed - no data found<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception testing TTSMetaManager: " . $e->getMessage() . "<br>";
        echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "❌ TTSMetaManager class not found<br>";
}

echo "<h2>Test 4: WordPress Environment Check</h2>";

// Check if we can write to postmeta at all
global $wpdb;

$meta_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d",
    $test_post_id
));

echo "Post $test_post_id has $meta_count metadata records<br>";

// Check if post exists
$post = get_post($test_post_id);
if ($post) {
    echo "✅ Post exists: {$post->post_title}<br>";
} else {
    echo "❌ Post $test_post_id does not exist<br>";
}

// Check WordPress memory and limits
echo "Memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . "<br>";

echo "<h2>Test Complete</h2>";
?>
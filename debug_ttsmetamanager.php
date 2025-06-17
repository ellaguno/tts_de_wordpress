<?php
/**
 * Debug TTSMetaManager class specifically
 */

// Load WordPress
require_once('wp-config.php');

echo "<h1>TTSMetaManager Debug Test</h1>";

// Test 1: Check if class exists
echo "<h2>1. Class Existence Check</h2>";
if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
    echo "✅ TTSMetaManager class exists<br>";
} else {
    echo "❌ TTSMetaManager class NOT found<br>";
    echo "Checking autoloader...<br>";
    
    // Try to manually include
    $file_path = WP_CONTENT_DIR . '/plugins/tts-de-wordpress/src/Utils/TTSMetaManager.php';
    if (file_exists($file_path)) {
        echo "📁 File exists at: $file_path<br>";
        require_once($file_path);
        
        if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
            echo "✅ Class loaded manually<br>";
        } else {
            echo "❌ Class still not available after manual include<br>";
        }
    } else {
        echo "❌ File does not exist at: $file_path<br>";
    }
}

// Test 2: Test basic metadata operations
echo "<h2>2. Basic Metadata Operations</h2>";
$test_post_id = 417; // The post from the error

try {
    if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
        // Test getTTSData
        echo "Testing getTTSData...<br>";
        $data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
        echo "✅ getTTSData successful. Structure: <pre>" . print_r($data, true) . "</pre>";
        
        // Test setAudioInfo
        echo "Testing setAudioInfo...<br>";
        $result = \WP_TTS\Utils\TTSMetaManager::setAudioInfo(
            $test_post_id, 
            'https://example.com/test.mp3',
            ['status' => 'completed', 'duration' => 30]
        );
        
        if ($result) {
            echo "✅ setAudioInfo successful<br>";
        } else {
            echo "❌ setAudioInfo failed<br>";
        }
        
        // Test saveTTSData directly
        echo "Testing saveTTSData directly...<br>";
        $test_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($test_post_id);
        $test_data['audio']['url'] = 'https://example.com/direct-test.mp3';
        $save_result = \WP_TTS\Utils\TTSMetaManager::saveTTSData($test_post_id, $test_data);
        
        if ($save_result) {
            echo "✅ saveTTSData successful<br>";
        } else {
            echo "❌ saveTTSData failed<br>";
        }
        
    } else {
        echo "❌ Cannot test - class not available<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception during testing: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "❌ Fatal error during testing: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 3: Check database directly
echo "<h2>3. Database Check</h2>";
global $wpdb;

$meta_value = $wpdb->get_var($wpdb->prepare(
    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_tts_sesolibre'",
    $test_post_id
));

if ($meta_value) {
    echo "✅ Found _tts_sesolibre metadata in database<br>";
    echo "Value: <pre>" . print_r(json_decode($meta_value, true), true) . "</pre>";
} else {
    echo "ℹ️ No _tts_sesolibre metadata found in database<br>";
}

// Check for old metadata
$old_meta = $wpdb->get_results($wpdb->prepare(
    "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '_tts_%'",
    $test_post_id
));

if ($old_meta) {
    echo "📋 Found old TTS metadata:<br>";
    foreach ($old_meta as $meta) {
        echo "- {$meta->meta_key}: {$meta->meta_value}<br>";
    }
} else {
    echo "ℹ️ No old TTS metadata found<br>";
}

// Test 4: WordPress functions test
echo "<h2>4. WordPress Functions Test</h2>";

// Test update_post_meta directly
$direct_result = update_post_meta($test_post_id, '_tts_debug_test', 'test_value_' . time());
if ($direct_result) {
    echo "✅ update_post_meta works directly<br>";
} else {
    echo "❌ update_post_meta failed<br>";
}

// Test current_time function
$current_time = current_time('mysql');
echo "✅ current_time works: $current_time<br>";

echo "<h2>5. Memory and Error Check</h2>";
echo "Memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";

if (function_exists('error_get_last')) {
    $last_error = error_get_last();
    if ($last_error) {
        echo "Last PHP error: <pre>" . print_r($last_error, true) . "</pre>";
    } else {
        echo "✅ No recent PHP errors<br>";
    }
}

echo "<h2>Test Complete</h2>";
?>
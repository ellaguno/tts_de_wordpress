<?php
/**
 * Focused test for Azure TTS metadata issue
 * 
 * Usage: Run this script in WordPress admin or add it to a temporary admin page
 */

// Only run in admin
if (!is_admin()) {
    wp_die('This script must be run in WordPress admin area');
}

echo "<h2>Azure TTS Metadata Test</h2>";

// Test 1: Check existing posts with Azure TTS
echo "<h3>1. Checking existing posts with TTS audio</h3>";

$posts_with_audio = get_posts([
    'post_type' => ['post', 'page'],
    'meta_query' => [
        [
            'key' => '_tts_audio_url',
            'compare' => 'EXISTS'
        ]
    ],
    'numberposts' => 10
]);

if (empty($posts_with_audio)) {
    echo "<p>❌ No posts found with TTS audio</p>";
} else {
    echo "<p>✅ Found " . count($posts_with_audio) . " posts with TTS audio</p>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Post ID</th><th>Title</th><th>Audio URL</th><th>Provider</th><th>Voice</th><th>Generated At</th></tr>";
    
    foreach ($posts_with_audio as $post) {
        $audio_url = get_post_meta($post->ID, '_tts_audio_url', true);
        $provider = get_post_meta($post->ID, '_tts_voice_provider', true);
        $voice = get_post_meta($post->ID, '_tts_voice_id', true);
        $generated_at = get_post_meta($post->ID, '_tts_generated_at', true);
        
        echo "<tr>";
        echo "<td>{$post->ID}</td>";
        echo "<td>" . esc_html($post->post_title) . "</td>";
        echo "<td>" . ($audio_url ? "✅ " . basename($audio_url) : "❌") . "</td>";
        echo "<td>" . ($provider ? "✅ {$provider}" : "❌ Missing") . "</td>";
        echo "<td>" . ($voice ? "✅ {$voice}" : "❌ Missing") . "</td>";
        echo "<td>" . ($generated_at ? "✅ " . date('Y-m-d H:i:s', $generated_at) : "❌") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 2: Test the metadata saving logic
echo "<h3>2. Testing metadata saving logic</h3>";

// Check if we can create a temporary post to test
$temp_post_id = wp_insert_post([
    'post_title' => 'TTS Metadata Test - ' . date('Y-m-d H:i:s'),
    'post_content' => 'This is a test post for TTS metadata verification.',
    'post_status' => 'draft',
    'post_type' => 'post'
]);

if (is_wp_error($temp_post_id)) {
    echo "<p>❌ Failed to create test post</p>";
} else {
    echo "<p>✅ Created test post with ID: {$temp_post_id}</p>";
    
    // Simulate the result that Azure TTS would return
    $mock_azure_result = [
        'success' => true,
        'audio_url' => 'https://example.com/test-azure-audio.mp3',
        'provider' => 'azure_tts',
        'voice' => 'es-MX-DaliaNeural',
        'source' => 'generated'
    ];
    
    echo "<h4>Simulating Azure TTS result saving:</h4>";
    echo "<pre>";
    echo "Mock result:\n";
    print_r($mock_azure_result);
    echo "</pre>";
    
    // Test the exact code from TTSService::generateAudioForPost (lines 389-409)
    update_post_meta($temp_post_id, '_tts_audio_url', $mock_azure_result['audio_url']);
    update_post_meta($temp_post_id, '_tts_generated_at', time());
    update_post_meta($temp_post_id, '_tts_generation_status', 'completed');
    
    // Save the actual provider and voice used (from result, not from input)
    if (isset($mock_azure_result['provider'])) {
        update_post_meta($temp_post_id, '_tts_voice_provider', $mock_azure_result['provider']);
        echo "<p>✅ Saved provider to post meta: {$mock_azure_result['provider']}</p>";
    }
    
    if (isset($mock_azure_result['voice'])) {
        update_post_meta($temp_post_id, '_tts_voice_id', $mock_azure_result['voice']);
        echo "<p>✅ Saved voice to post meta: {$mock_azure_result['voice']}</p>";
    }
    
    // Verify the metadata was saved
    echo "<h4>Verifying saved metadata:</h4>";
    $saved_provider = get_post_meta($temp_post_id, '_tts_voice_provider', true);
    $saved_voice = get_post_meta($temp_post_id, '_tts_voice_id', true);
    $saved_audio_url = get_post_meta($temp_post_id, '_tts_audio_url', true);
    $saved_status = get_post_meta($temp_post_id, '_tts_generation_status', true);
    
    echo "<ul>";
    echo "<li>Provider: " . ($saved_provider ? "✅ {$saved_provider}" : "❌ Not saved") . "</li>";
    echo "<li>Voice: " . ($saved_voice ? "✅ {$saved_voice}" : "❌ Not saved") . "</li>";
    echo "<li>Audio URL: " . ($saved_audio_url ? "✅ {$saved_audio_url}" : "❌ Not saved") . "</li>";
    echo "<li>Status: " . ($saved_status ? "✅ {$saved_status}" : "❌ Not saved") . "</li>";
    echo "</ul>";
    
    // Test meta-box template variables
    echo "<h4>Testing meta-box template display logic:</h4>";
    $post = get_post($temp_post_id);
    $enabled = true; // Assume enabled for test
    $provider = get_post_meta($post->ID, '_tts_voice_provider', true);
    $voice_id = get_post_meta($post->ID, '_tts_voice_id', true);
    $audio_url = get_post_meta($post->ID, '_tts_audio_url', true);
    $status = get_post_meta($post->ID, '_tts_generation_status', true);
    
    echo "<div style='background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 4px; padding: 12px; margin-top: 10px; font-size: 13px;'>";
    echo "<h4 style='margin: 0 0 8px 0; font-size: 13px; color: #1d2327;'>Audio Details (Preview)</h4>";
    echo "<div style='display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center;'>";
    
    if ($provider) {
        echo "<strong style='color: #646970;'>Provider:</strong>";
        echo "<span style='color: #1d2327;'>";
        echo esc_html(ucfirst(str_replace('_', ' ', $provider)));
        echo "<span style='background: #007cba; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: 500; margin-left: 6px;'>";
        echo esc_html(strtoupper($provider));
        echo "</span>";
        echo "</span>";
    } else {
        echo "<em>Provider would not be displayed (missing)</em>";
    }
    
    if ($voice_id) {
        echo "<strong style='color: #646970;'>Voice:</strong>";
        echo "<span style='color: #1d2327;'>" . esc_html($voice_id) . "</span>";
    } else {
        echo "<em>Voice would not be displayed (missing)</em>";
    }
    
    echo "</div>";
    echo "</div>";
    
    // Clean up
    wp_delete_post($temp_post_id, true);
    echo "<p>✅ Test post cleaned up</p>";
}

// Test 3: Check Azure TTS Provider class
echo "<h3>3. Checking Azure TTS Provider Implementation</h3>";

try {
    // Check if AzureTTSProvider class exists
    if (!class_exists('WP_TTS\\Providers\\AzureTTSProvider')) {
        echo "<p>❌ AzureTTSProvider class not found</p>";
    } else {
        echo "<p>✅ AzureTTSProvider class exists</p>";
        
        // Check Azure configuration
        $config = get_option('wp_tts_config', []);
        $azure_config = $config['providers']['azure_tts'] ?? [];
        
        echo "<h4>Azure TTS Configuration:</h4>";
        echo "<ul>";
        echo "<li>Subscription Key: " . (isset($azure_config['subscription_key']) && !empty($azure_config['subscription_key']) ? "✅ Configured" : "❌ Missing") . "</li>";
        echo "<li>Region: " . (isset($azure_config['region']) ? "✅ " . $azure_config['region'] : "❌ Missing") . "</li>";
        echo "<li>Default Voice: " . (isset($azure_config['default_voice']) ? "✅ " . $azure_config['default_voice'] : "❌ Missing") . "</li>";
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error checking Azure TTS Provider: " . $e->getMessage() . "</p>";
}

// Test 4: Recommendations
echo "<h3>4. Diagnosis and Recommendations</h3>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
echo "<h4>If provider/voice info is not showing in meta-box:</h4>";
echo "<ol>";
echo "<li><strong>Check TTSService::generateAudioForPost method</strong> - Verify lines 395-409 are correctly saving provider/voice metadata</li>";
echo "<li><strong>Check Azure TTS provider response</strong> - Ensure the generateSpeech method returns provider and voice in the result</li>";
echo "<li><strong>Check meta-box template</strong> - Verify lines 117-130 are correctly checking for and displaying the metadata</li>";
echo "<li><strong>Debug the actual generation process</strong> - Look at WordPress logs to see if metadata is being saved during real audio generation</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>Expected workflow:</h4>";
echo "<ol>";
echo "<li>User clicks 'Generate Audio Now' in post editor</li>";
echo "<li>AJAX calls Plugin::handleGenerateAudio (line 320)</li>";
echo "<li>TTSService::generateAudioForPost is called (line 343)</li>";
echo "<li>TTSService::generateAudio returns result with provider and voice</li>";
echo "<li>Lines 395-409 save the provider and voice to post meta</li>";
echo "<li>Meta-box template reads and displays this metadata</li>";
echo "</ol>";
echo "</div>";

echo "<script>console.log('Azure TTS Metadata Test completed');</script>";
?>
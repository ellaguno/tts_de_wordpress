<?php
/**
 * Debug script to test metadata saving workflow
 * This script will help identify where the provider and voice metadata is not being saved correctly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run within WordPress context');
}

// Load WordPress if not already loaded
if (!function_exists('get_post_meta')) {
    echo "WordPress environment not detected. Please run this script in WordPress context.\n";
    exit;
}

echo "=== TTS Metadata Workflow Debug ===\n\n";

// Test 1: Check if we can find any posts with TTS metadata
echo "1. Checking for posts with TTS metadata...\n";
$posts_with_tts = get_posts([
    'post_type' => ['post', 'page'],
    'meta_query' => [
        [
            'key' => '_tts_audio_url',
            'compare' => 'EXISTS'
        ]
    ],
    'numberposts' => 5
]);

if (empty($posts_with_tts)) {
    echo "   No posts found with TTS audio URLs.\n";
} else {
    echo "   Found " . count($posts_with_tts) . " posts with TTS audio:\n";
    foreach ($posts_with_tts as $post) {
        $audio_url = get_post_meta($post->ID, '_tts_audio_url', true);
        $provider = get_post_meta($post->ID, '_tts_voice_provider', true);
        $voice = get_post_meta($post->ID, '_tts_voice_id', true);
        $generated_at = get_post_meta($post->ID, '_tts_generated_at', true);
        
        echo "   - Post ID {$post->ID}: '{$post->post_title}'\n";
        echo "     Audio URL: " . ($audio_url ? "✓ " . substr($audio_url, -50) : "✗ Missing") . "\n";
        echo "     Provider: " . ($provider ? "✓ {$provider}" : "✗ Missing") . "\n";
        echo "     Voice: " . ($voice ? "✓ {$voice}" : "✗ Missing") . "\n";
        echo "     Generated at: " . ($generated_at ? "✓ " . date('Y-m-d H:i:s', $generated_at) : "✗ Missing") . "\n";
        echo "\n";
    }
}

// Test 2: Simulate the TTSService generateAudioForPost flow
echo "2. Simulating TTSService metadata saving workflow...\n";

// Create a test post if none exists
$test_post = wp_insert_post([
    'post_title' => 'TTS Metadata Test Post',
    'post_content' => 'This is a test post to verify TTS metadata saving.',
    'post_status' => 'draft',
    'post_type' => 'post'
]);

if (is_wp_error($test_post)) {
    echo "   ✗ Failed to create test post: " . $test_post->get_error_message() . "\n";
} else {
    echo "   ✓ Created test post with ID: {$test_post}\n";
    
    // Simulate what TTSService::generateAudioForPost does
    $mock_result = [
        'success' => true,
        'audio_url' => 'https://example.com/mock-audio.mp3',
        'provider' => 'azure_tts',
        'voice' => 'es-MX-DaliaNeural',
        'source' => 'generated'
    ];
    
    echo "   Simulating metadata save with mock result:\n";
    echo "     Provider: {$mock_result['provider']}\n";
    echo "     Voice: {$mock_result['voice']}\n";
    echo "     Audio URL: {$mock_result['audio_url']}\n";
    
    // Save metadata like TTSService does
    update_post_meta($test_post, '_tts_audio_url', $mock_result['audio_url']);
    update_post_meta($test_post, '_tts_generated_at', time());
    update_post_meta($test_post, '_tts_generation_status', 'completed');
    
    // Save provider and voice info (the key part we're testing)
    if (isset($mock_result['provider'])) {
        update_post_meta($test_post, '_tts_voice_provider', $mock_result['provider']);
        echo "   ✓ Saved provider to post meta: {$mock_result['provider']}\n";
    }
    
    if (isset($mock_result['voice'])) {
        update_post_meta($test_post, '_tts_voice_id', $mock_result['voice']);
        echo "   ✓ Saved voice to post meta: {$mock_result['voice']}\n";
    }
    
    // Verify the metadata was saved correctly
    echo "\n   Verifying saved metadata:\n";
    $saved_provider = get_post_meta($test_post, '_tts_voice_provider', true);
    $saved_voice = get_post_meta($test_post, '_tts_voice_id', true);
    $saved_audio_url = get_post_meta($test_post, '_tts_audio_url', true);
    $saved_generated_at = get_post_meta($test_post, '_tts_generated_at', true);
    
    echo "     Provider: " . ($saved_provider ? "✓ {$saved_provider}" : "✗ Not saved") . "\n";
    echo "     Voice: " . ($saved_voice ? "✓ {$saved_voice}" : "✗ Not saved") . "\n";
    echo "     Audio URL: " . ($saved_audio_url ? "✓ " . substr($saved_audio_url, -30) : "✗ Not saved") . "\n";
    echo "     Generated at: " . ($saved_generated_at ? "✓ " . date('Y-m-d H:i:s', $saved_generated_at) : "✗ Not saved") . "\n";
}

// Test 3: Test the meta-box template logic
echo "\n3. Testing meta-box template variable logic...\n";

if (isset($test_post) && !is_wp_error($test_post)) {
    // Simulate the variables that would be passed to the meta-box template
    $post = get_post($test_post);
    $enabled = get_post_meta($post->ID, '_tts_enabled', true);
    $provider = get_post_meta($post->ID, '_tts_voice_provider', true);
    $voice_id = get_post_meta($post->ID, '_tts_voice_id', true);
    $custom_text = get_post_meta($post->ID, '_tts_custom_text', true);
    $audio_url = get_post_meta($post->ID, '_tts_audio_url', true);
    $status = get_post_meta($post->ID, '_tts_generation_status', true);
    
    echo "   Meta-box template variables:\n";
    echo "     \$enabled: " . ($enabled ? "✓ {$enabled}" : "✗ Empty") . "\n";
    echo "     \$provider: " . ($provider ? "✓ {$provider}" : "✗ Empty") . "\n";
    echo "     \$voice_id: " . ($voice_id ? "✓ {$voice_id}" : "✗ Empty") . "\n";
    echo "     \$audio_url: " . ($audio_url ? "✓ " . substr($audio_url, -30) : "✗ Empty") . "\n";
    echo "     \$status: " . ($status ? "✓ {$status}" : "✗ Empty") . "\n";
    
    // Test the logic used in the meta-box template lines 117-130
    echo "\n   Testing meta-box template conditional logic:\n";
    
    if ($provider) {
        echo "     ✓ Provider condition (line 117): Will display provider info\n";
        echo "       Display text: " . ucfirst(str_replace('_', ' ', $provider)) . "\n";
        echo "       Badge text: " . strtoupper($provider) . "\n";
    } else {
        echo "     ✗ Provider condition (line 117): Will NOT display provider info\n";
    }
    
    if ($voice_id) {
        echo "     ✓ Voice condition (line 127): Will display voice info\n";
        echo "       Display text: {$voice_id}\n";
    } else {
        echo "     ✗ Voice condition (line 127): Will NOT display voice info\n";
    }
}

// Test 4: Check Azure TTS provider configuration
echo "\n4. Checking Azure TTS provider configuration...\n";
$config = get_option('wp_tts_config', []);
$azure_config = $config['providers']['azure_tts'] ?? [];

echo "   Azure TTS configuration:\n";
echo "     Subscription Key: " . (isset($azure_config['subscription_key']) && !empty($azure_config['subscription_key']) ? "✓ Set" : "✗ Missing") . "\n";
echo "     Region: " . (isset($azure_config['region']) && !empty($azure_config['region']) ? "✓ " . $azure_config['region'] : "✗ Missing") . "\n";
echo "     Default Voice: " . (isset($azure_config['default_voice']) && !empty($azure_config['default_voice']) ? "✓ " . $azure_config['default_voice'] : "✗ Missing") . "\n";

// Test 5: Check if there are any existing posts with Azure TTS but missing metadata
echo "\n5. Checking for posts with Azure TTS but missing metadata...\n";
$all_posts_with_audio = get_posts([
    'post_type' => ['post', 'page'],
    'meta_query' => [
        [
            'key' => '_tts_audio_url',
            'compare' => 'EXISTS'
        ]
    ],
    'numberposts' => -1
]);

$azure_posts_missing_metadata = 0;
foreach ($all_posts_with_audio as $post) {
    $audio_url = get_post_meta($post->ID, '_tts_audio_url', true);
    $provider = get_post_meta($post->ID, '_tts_voice_provider', true);
    $voice = get_post_meta($post->ID, '_tts_voice_id', true);
    
    // If the audio URL exists but provider is missing, it might be an Azure TTS issue
    if ($audio_url && !$provider) {
        $azure_posts_missing_metadata++;
        echo "   Post ID {$post->ID} has audio but missing provider metadata\n";
    }
}

if ($azure_posts_missing_metadata > 0) {
    echo "   Found {$azure_posts_missing_metadata} posts with missing provider metadata\n";
} else {
    echo "   All posts with audio have provider metadata\n";
}

// Clean up test post
if (isset($test_post) && !is_wp_error($test_post)) {
    wp_delete_post($test_post, true);
    echo "\n   ✓ Cleaned up test post\n";
}

echo "\n=== Debug Complete ===\n";
echo "Summary:\n";
echo "- The TTSService::generateAudioForPost method should save provider and voice metadata\n";
echo "- The meta-box template should display this information in the Audio Details section\n";
echo "- If provider/voice info is not showing, check that the metadata is being saved correctly\n";
echo "- Lines 395-409 in TTSService.php handle the metadata saving\n";
echo "- Lines 117-130 in meta-box.php handle the display logic\n";
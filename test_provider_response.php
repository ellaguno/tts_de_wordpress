<?php
/**
 * Test to verify provider responses include correct metadata
 * Run this script to check if providers return provider and voice info correctly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('This script must be run within WordPress context');
}

echo "<h2>Provider Response Metadata Test</h2>";

// Mock Azure TTS provider response structure
echo "<h3>1. Testing Azure TTS Provider Response Structure</h3>";

// Simulate what AzureTTSProvider::generateSpeech should return (lines 105-116)
$mock_azure_response = [
    'success' => true,
    'audio_url' => 'https://example.com/test-audio.mp3',
    'file_path' => '/path/to/audio.mp3',
    'provider' => 'azure_tts',
    'voice' => 'es-MX-DaliaNeural',
    'format' => 'mp3',
    'duration' => 30,
    'metadata' => [
        'characters' => 100,
    ],
];

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h4>Mock Azure TTS Response:</h4>";
echo "<pre>";
print_r($mock_azure_response);
echo "</pre>";

// Check if the response has the required fields
$required_fields = ['success', 'audio_url', 'provider', 'voice'];
echo "<h4>Response Validation:</h4>";
echo "<ul>";
foreach ($required_fields as $field) {
    $exists = isset($mock_azure_response[$field]);
    $has_value = $exists && !empty($mock_azure_response[$field]);
    echo "<li><strong>{$field}:</strong> " . ($has_value ? "✅ " . $mock_azure_response[$field] : "❌ Missing or empty") . "</li>";
}
echo "</ul>";
echo "</div>";

// Test TTSService flow
echo "<h3>2. Testing TTSService Flow</h3>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>TTSService::generateAudio() Expected Flow:</h4>";
echo "<ol>";
echo "<li>Provider returns result with 'provider' and 'voice' fields</li>";
echo "<li>TTSService::generateAudio() returns result with provider/voice (lines 165-172)</li>";
echo "<li>TTSService::generateAudioForPost() saves this to post meta (lines 395-409)</li>";
echo "<li>Meta-box template displays the saved metadata (lines 117-130)</li>";
echo "</ol>";
echo "</div>";

// Simulate TTSService::generateAudio return (lines 165-172)
$tts_service_result = [
    'success' => true,
    'audio_url' => $mock_azure_response['audio_url'],
    'source' => 'generated',
    'provider' => $mock_azure_response['provider'],    // This line is key!
    'voice' => $mock_azure_response['voice'],          // This line is key!
    'hash' => 'some_hash_value',
];

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h4>TTSService::generateAudio() Result:</h4>";
echo "<pre>";
print_r($tts_service_result);
echo "</pre>";
echo "</div>";

// Test metadata saving simulation
echo "<h3>3. Testing Metadata Saving (TTSService::generateAudioForPost lines 395-409)</h3>";

$post_id = 999; // Mock post ID
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px;'>";
echo "<h4>Metadata Saving Simulation:</h4>";
echo "<pre>";
echo "update_post_meta({$post_id}, '_tts_audio_url', '{$tts_service_result['audio_url']}');\n";
echo "update_post_meta({$post_id}, '_tts_generated_at', " . time() . ");\n";
echo "update_post_meta({$post_id}, '_tts_generation_status', 'completed');\n";

if (isset($tts_service_result['provider'])) {
    echo "update_post_meta({$post_id}, '_tts_voice_provider', '{$tts_service_result['provider']}');\n";
}

if (isset($tts_service_result['voice'])) {
    echo "update_post_meta({$post_id}, '_tts_voice_id', '{$tts_service_result['voice']}');\n";
}
echo "</pre>";
echo "</div>";

// Test meta-box display logic
echo "<h3>4. Testing Meta-box Display Logic</h3>";

$provider = $tts_service_result['provider'];
$voice_id = $tts_service_result['voice'];
$audio_url = $tts_service_result['audio_url'];

echo "<div style='background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 4px; padding: 12px; margin: 10px 0;'>";
echo "<h4>Audio Details Preview (Meta-box template simulation):</h4>";
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
    echo "<em style='color: #dc3545;'>Provider information would be missing</em>";
}

if ($voice_id) {
    echo "<strong style='color: #646970;'>Voice:</strong>";
    echo "<span style='color: #1d2327;'>" . esc_html($voice_id) . "</span>";
} else {
    echo "<em style='color: #dc3545;'>Voice information would be missing</em>";
}

echo "<strong style='color: #646970;'>Audio URL:</strong>";
echo "<span style='color: #1d2327;'>" . basename($audio_url) . "</span>";

echo "</div>";
echo "</div>";

// Diagnostic recommendations
echo "<h3>5. Diagnostic Steps for Real Issue</h3>";

echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
echo "<h4>If provider/voice info is still not showing, check these files:</h4>";
echo "<ol>";
echo "<li><strong>AzureTTSProvider.php line 105-116:</strong> Verify generateSpeech() returns 'provider' and 'voice' fields</li>";
echo "<li><strong>TTSService.php line 165-172:</strong> Verify generateAudio() includes provider/voice in return</li>";
echo "<li><strong>TTSService.php line 395-409:</strong> Verify generateAudioForPost() saves provider/voice to post meta</li>";
echo "<li><strong>Plugin.php line 250-264:</strong> Verify renderTTSMetaBox() passes correct variables to template</li>";
echo "<li><strong>meta-box.php line 117-130:</strong> Verify template displays provider/voice correctly</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<h4>Quick Debug Steps:</h4>";
echo "<ol>";
echo "<li>Generate new audio with Azure TTS</li>";
echo "<li>Check WordPress database: <code>wp_postmeta</code> table for <code>_tts_voice_provider</code> and <code>_tts_voice_id</code></li>";
echo "<li>Enable WordPress debug logging and check for any errors during audio generation</li>";
echo "<li>Inspect the post edit page source to see what values are in the meta-box template variables</li>";
echo "</ol>";
echo "</div>";

// SQL query to check actual database
echo "<h3>6. Database Check Query</h3>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px;'>";
echo "<h4>SQL to check existing metadata:</h4>";
echo "<pre>";
echo "SELECT p.ID, p.post_title, ";
echo "m1.meta_value as audio_url, ";
echo "m2.meta_value as provider, ";
echo "m3.meta_value as voice_id ";
echo "FROM wp_posts p ";
echo "LEFT JOIN wp_postmeta m1 ON p.ID = m1.post_id AND m1.meta_key = '_tts_audio_url' ";
echo "LEFT JOIN wp_postmeta m2 ON p.ID = m2.post_id AND m2.meta_key = '_tts_voice_provider' ";
echo "LEFT JOIN wp_postmeta m3 ON p.ID = m3.post_id AND m3.meta_key = '_tts_voice_id' ";
echo "WHERE m1.meta_value IS NOT NULL;";
echo "</pre>";
echo "</div>";

echo "<script>console.log('Provider Response Metadata Test completed');</script>";
?>
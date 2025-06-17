<?php
/**
 * Simple test to check TTS Tools AJAX endpoints
 * Run this in WordPress admin to test AJAX calls
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-config.php');
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TTS Tools AJAX Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>TTS Tools AJAX Test</h1>
    
    <h2>Test Get Voices</h2>
    <p>Provider: 
        <select id="test_provider">
            <option value="">Select Provider</option>
            <option value="google">Google</option>
            <option value="openai">OpenAI</option>
            <option value="elevenlabs">ElevenLabs</option>
            <option value="azure_tts">Azure TTS</option>
            <option value="amazon_polly">Amazon Polly</option>
        </select>
        <button id="test_voices">Test Get Voices</button>
    </p>
    
    <div id="test_results">
        <h3>Results:</h3>
        <pre id="results_output"></pre>
    </div>

    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    $('#test_voices').on('click', function() {
        const provider = $('#test_provider').val();
        if (!provider) {
            alert('Please select a provider first');
            return;
        }
        
        console.log('[TEST] Testing get voices for provider:', provider);
        $('#results_output').text('Loading...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_get_voices',
                provider: provider,
                nonce: '<?php echo wp_create_nonce('wp_tts_admin'); ?>'
            },
            beforeSend: function() {
                console.log('[TEST] Sending AJAX request...');
            },
            success: function(response) {
                console.log('[TEST] Response received:', response);
                $('#results_output').text(JSON.stringify(response, null, 2));
            },
            error: function(xhr, status, error) {
                console.error('[TEST] AJAX Error:', xhr, status, error);
                $('#results_output').text('AJAX Error: ' + xhr.responseText);
            }
        });
    });
    </script>
</body>
</html>
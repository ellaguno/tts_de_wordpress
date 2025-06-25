jQuery(document).ready(function($) {
    // Test TTS Generation on Tools Page
    $('#test-tts').on('click', function() {
        const button = $(this);
        const testText = $('#test-text').val();
        const resultDiv = $('#test-result');

        if (!testText.trim()) {
            resultDiv.html('<p style="color: red;">Please enter some text to generate audio.</p>');
            return;
        }

        button.prop('disabled', true).text('Generating...');
        resultDiv.html('<p>Processing...</p>');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl, // Localized from PHP
            type: 'POST',
            data: {
                action: 'wp_tts_test_provider', // Action hook for AdminInterface::handleTestProvider
                text: testText,
                nonce: wpTtsAdmin.nonce // Localized from PHP
            },
            success: function(response) {
                if (response.success) {
                    let audioPlayer = '<p>Test audio generated successfully:</p>';
                    audioPlayer += '<audio controls src="' + response.data.audio_url + '"></audio>';
                    if(response.data.provider) {
                        audioPlayer += '<p><small>Provider used: ' + response.data.provider + '</small></p>';
                    }
                    resultDiv.html(audioPlayer);
                } else {
                    let errorMessage = '<p style="color: red;">Error: ' + (response.data.message || 'Unknown error occurred.') + '</p>';
                    if (response.data.error_details) {
                        errorMessage += '<p style="color: red;"><small>Details: ' + response.data.error_details + '</small></p>';
                    }
                    resultDiv.html(errorMessage);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                resultDiv.html('<p style="color: red;">AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
            },
            complete: function() {
                button.prop('disabled', false).text('Generate Test Audio');
            }
        });
    });

    // TTS Provider Toggle Functionality
    $('.tts-provider-toggle').on('change', function() {
        const provider = $(this).data('provider');
        const isEnabled = $(this).is(':checked');
        const providerField = $(this).closest('.tts-provider-field');
        
        // Find all provider config sections for this provider
        const configSections = providerField.find('.tts-provider-config')
                              .add(providerField.siblings().find(`.tts-provider-config`));
        
        if (isEnabled) {
            // Enable all fields in the provider config
            configSections.css('opacity', '1');
            configSections.find('input, select, textarea').prop('disabled', false);
        } else {
            // Disable all fields in the provider config
            configSections.css('opacity', '0.5');
            configSections.find('input, select, textarea').prop('disabled', true);
        }
        
        console.log('Provider ' + provider + ' ' + (isEnabled ? 'enabled' : 'disabled'));
    });
    
    // Initialize the state on page load
    $('.tts-provider-toggle').trigger('change');

    // Auto-save player settings
    $('.tts-player-setting').on('change', function() {
        const $field = $(this);
        const setting = $field.data('setting');
        const value = $field.is(':checkbox') ? ($field.is(':checked') ? '1' : '0') : $field.val();
        
        // Get all current player settings
        const playerSettings = {};
        $('.tts-player-setting').each(function() {
            const settingName = $(this).data('setting');
            const settingValue = $(this).is(':checkbox') ? ($(this).is(':checked') ? '1' : '0') : $(this).val();
            playerSettings[settingName] = settingValue;
        });
        
        // Add nonce
        playerSettings.nonce = wpTtsAdmin.nonce;
        playerSettings.action = 'tts_save_player_config';
        
        console.log('Saving player settings:', playerSettings);
        
        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: playerSettings,
            success: function(response) {
                if (response.success) {
                    console.log('Player settings saved successfully');
                    // Show a brief success indicator
                    $field.css('background-color', '#d4edda').delay(1000).queue(function() {
                        $(this).css('background-color', '').dequeue();
                    });
                } else {
                    console.error('Failed to save player settings:', response.data.message);
                    alert('Error saving settings: ' + response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error saving player settings:', textStatus, errorThrown);
                alert('AJAX error saving settings');
            }
        });
    });

    // Other admin JS can go here, e.g., for settings page interactions
    // Example: Test provider connection button
    $('.test-provider-connection').on('click', function() {
        const button = $(this);
        const provider = button.data('provider');
        const resultSpan = $('#test-result-' + provider);

        button.prop('disabled', true).text('Testing...');
        resultSpan.text('Testing connection...');

        $.ajax({
            url: wpTtsAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_tts_validate_provider', // Corresponds to Plugin::handleValidateProvider
                provider: provider,
                nonce: wpTtsAdmin.nonce // Make sure this nonce is appropriate or generate a specific one
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: ' + (response.data.valid ? 'green' : 'red') + ';">' + response.data.message + '</span>');
                } else {
                    resultSpan.text('Error: ' + (response.data.message || 'Validation failed.'));
                }
            },
            error: function() {
                resultSpan.text('AJAX error during validation.');
            },
            complete: function() {
                button.prop('disabled', false).text('Test Connection');
            }
        });
    });
});
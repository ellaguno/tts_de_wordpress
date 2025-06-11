<?php
/**
 * TTS Meta Box Template
 * 
 * Template for the TTS settings meta box in the post editor
 * 
 * @var WP_Post $post Current post object
 * @var bool $enabled Whether TTS is enabled
 * @var string $provider Selected TTS provider
 * @var string $voice_id Selected voice ID
 * @var string $custom_text Custom text for TTS
 * @var string $audio_url Generated audio URL
 * @var string $status Generation status
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get configuration manager
$config = new \WP_TTS\Core\ConfigurationManager();
$enabled_providers = $config->getEnabledProviders();
$defaults = $config->getDefaults();

// If no providers are enabled, show default providers
if (empty($enabled_providers)) {
    $enabled_providers = ['google', 'openai', 'elevenlabs', 'aws'];
}
?>

<div class="wp-tts-meta-box">
    <table class="form-table">
        <tbody>
            <!-- Enable/Disable TTS -->
            <tr>
                <th scope="row">
                    <label for="tts_enabled"><?php _e('Enable TTS', 'TTS de Wordpress'); ?></label>
                </th>
                <td>
                    <label class="wp-tts-toggle">
                        <input type="checkbox" 
                               id="tts_enabled" 
                               name="tts_enabled" 
                               value="1" 
                               <?php checked($enabled, 1); ?> />
                        <span class="wp-tts-toggle-slider"></span>
                    </label>
                    <p class="description">
                        <?php _e('Enable text-to-speech conversion for this post', 'TTS de Wordpress'); ?>
                    </p>
                </td>
            </tr>

            <!-- TTS Provider Selection -->
            <tr class="wp-tts-conditional" data-depends="tts_enabled">
                <th scope="row">
                    <label for="tts_voice_provider"><?php _e('TTS Provider', 'TTS de Wordpress'); ?></label>
                </th>
                <td>
                    <select id="tts_voice_provider" name="tts_voice_provider" class="regular-text">
                        <option value=""><?php _e('Use default provider', 'TTS de Wordpress'); ?></option>
                        <?php foreach ($enabled_providers as $provider_name): ?>
                            <?php $provider_config = $config->getProviderConfig($provider_name); ?>
                            <option value="<?php echo esc_attr($provider_name); ?>" 
                                    <?php selected($provider, $provider_name); ?>>
                                <?php echo esc_html(ucfirst($provider_name)); ?>
                                <?php if ($provider_name === $defaults['default_provider']): ?>
                                    (<?php _e('Default', 'TTS de Wordpress'); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select the TTS provider for this post', 'TTS de Wordpress'); ?>
                    </p>
                </td>
            </tr>

            <!-- Voice Selection -->
            <tr class="wp-tts-conditional" data-depends="tts_enabled">
                <th scope="row">
                    <label for="tts_voice_id"><?php _e('Voice', 'TTS de Wordpress'); ?></label>
                </th>
                <td>
                    <select id="tts_voice_id" name="tts_voice_id" class="regular-text">
                        <option value=""><?php _e('Use default voice', 'TTS de Wordpress'); ?></option>
                        <!-- Voices will be loaded via AJAX based on provider selection -->
                    </select>
                    <button type="button" id="tts_preview_voice" class="button button-secondary" disabled>
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php _e('Preview', 'TTS de Wordpress'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Select the voice for text-to-speech conversion', 'TTS de Wordpress'); ?>
                    </p>
                    <div id="tts_voice_preview" style="display: none;">
                        <audio controls style="width: 100%; margin-top: 10px;">
                            <source id="tts_preview_source" src="" type="audio/mpeg">
                            <?php _e('Your browser does not support the audio element.', 'TTS de Wordpress'); ?>
                        </audio>
                    </div>
                </td>
            </tr>

            <!-- Custom Text -->
            <tr class="wp-tts-conditional" data-depends="tts_enabled">
                <th scope="row">
                    <label for="tts_custom_text"><?php _e('Custom Text', 'TTS de Wordpress'); ?></label>
                </th>
                <td>
                    <textarea id="tts_custom_text" 
                              name="tts_custom_text" 
                              rows="5" 
                              class="large-text"
                              placeholder="<?php esc_attr_e('Leave empty to use post content...', 'TTS de Wordpress'); ?>"><?php echo esc_textarea($custom_text); ?></textarea>
                    <p class="description">
                        <?php _e('Optional: Enter custom text for TTS conversion. Leave empty to use the post content.', 'TTS de Wordpress'); ?>
                    </p>
                    <div class="wp-tts-text-stats">
                        <span id="tts_character_count">0</span> <?php _e('characters', 'TTS de Wordpress'); ?>
                        <span class="wp-tts-separator">|</span>
                        <span id="tts_estimated_cost">$0.00</span> <?php _e('estimated cost', 'TTS de Wordpress'); ?>
                    </div>
                </td>
            </tr>

            <!-- Audio Enhancement Options -->
            <tr class="wp-tts-conditional" data-depends="tts_enabled">
                <th scope="row">
                    <?php _e('Audio Enhancement', 'TTS de Wordpress'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Audio Enhancement Options', 'TTS de Wordpress'); ?></span>
                        </legend>
                        
                        <label for="tts_intro_audio">
                            <input type="checkbox" id="tts_intro_audio" name="tts_intro_audio" value="1" />
                            <?php _e('Add intro audio', 'TTS de Wordpress'); ?>
                        </label><br>
                        
                        <label for="tts_background_music">
                            <input type="checkbox" id="tts_background_music" name="tts_background_music" value="1" />
                            <?php _e('Add background music', 'TTS de Wordpress'); ?>
                        </label><br>
                        
                        <label for="tts_outro_audio">
                            <input type="checkbox" id="tts_outro_audio" name="tts_outro_audio" value="1" />
                            <?php _e('Add outro audio', 'TTS de Wordpress'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php _e('Select audio enhancements to include with the generated speech', 'TTS de Wordpress'); ?>
                    </p>
                </td>
            </tr>

            <!-- Generation Status and Controls -->
            <tr class="wp-tts-conditional" data-depends="tts_enabled">
                <th scope="row">
                    <?php _e('Audio Status', 'TTS de Wordpress'); ?>
                </th>
                <td>
                    <div class="wp-tts-status-container">
                        <?php if ($audio_url): ?>
                            <div class="wp-tts-status wp-tts-status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Audio generated successfully', 'TTS de Wordpress'); ?>
                                <a href="<?php echo esc_url($audio_url); ?>" target="_blank" class="button button-small">
                                    <?php _e('Listen', 'TTS de Wordpress'); ?>
                                </a>
                            </div>
                            <audio controls style="width: 100%; margin-top: 10px;">
                                <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                                <?php _e('Your browser does not support the audio element.', 'TTS de Wordpress'); ?>
                            </audio>
                        <?php elseif ($status === 'processing'): ?>
                            <div class="wp-tts-status wp-tts-status-processing">
                                <span class="dashicons dashicons-update wp-tts-spin"></span>
                                <?php _e('Generating audio...', 'TTS de Wordpress'); ?>
                            </div>
                        <?php elseif ($status === 'failed'): ?>
                            <div class="wp-tts-status wp-tts-status-error">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Audio generation failed', 'TTS de Wordpress'); ?>
                            </div>
                        <?php else: ?>
                            <div class="wp-tts-status wp-tts-status-pending">
                                <span class="dashicons dashicons-clock"></span>
                                <?php _e('Audio not generated yet', 'TTS de Wordpress'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wp-tts-actions" style="margin-top: 15px;">
                        <button type="button" id="tts_generate_now" class="button button-primary" 
                                <?php echo $status === 'processing' ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php _e('Generate Audio Now', 'TTS de Wordpress'); ?>
                        </button>
                        
                        <?php if ($audio_url): ?>
                            <button type="button" id="tts_regenerate" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Regenerate', 'TTS de Wordpress'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="tts_generation_progress" style="display: none; margin-top: 15px;">
                        <div class="wp-tts-progress-bar">
                            <div class="wp-tts-progress-fill" style="width: 0%;"></div>
                        </div>
                        <p class="wp-tts-progress-text"><?php _e('Preparing...', 'TTS de Wordpress'); ?></p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.wp-tts-meta-box .form-table th {
    width: 150px;
    padding: 15px 10px 15px 0;
}

.wp-tts-meta-box .form-table td {
    padding: 15px 10px;
}

.wp-tts-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.wp-tts-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.wp-tts-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.wp-tts-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.wp-tts-toggle input:checked + .wp-tts-toggle-slider {
    background-color: #0073aa;
}

.wp-tts-toggle input:checked + .wp-tts-toggle-slider:before {
    transform: translateX(26px);
}

.wp-tts-conditional {
    display: none;
}

.wp-tts-conditional.active {
    display: table-row;
}

.wp-tts-text-stats {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

.wp-tts-separator {
    margin: 0 10px;
}

.wp-tts-status {
    padding: 8px 12px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.wp-tts-status-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.wp-tts-status-processing {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.wp-tts-status-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.wp-tts-status-pending {
    background-color: #e2e3e5;
    color: #383d41;
    border: 1px solid #d6d8db;
}

.wp-tts-spin {
    animation: wp-tts-spin 1s linear infinite;
}

@keyframes wp-tts-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.wp-tts-progress-bar {
    width: 100%;
    height: 20px;
    background-color: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.wp-tts-progress-fill {
    height: 100%;
    background-color: #0073aa;
    transition: width 0.3s ease;
}

.wp-tts-progress-text {
    margin: 5px 0 0 0;
    font-size: 12px;
    color: #666;
}

.wp-tts-actions .button {
    margin-right: 10px;
}

.wp-tts-actions .button .dashicons {
    margin-top: 3px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle conditional fields
    function toggleConditionalFields() {
        const isEnabled = $('#tts_enabled').is(':checked');
        $('.wp-tts-conditional').toggleClass('active', isEnabled);
    }
    
    $('#tts_enabled').on('change', toggleConditionalFields);
    toggleConditionalFields(); // Initial state
    
    // Character count and cost estimation
    function updateTextStats() {
        const text = $('#tts_custom_text').val() || wp.data.select('core/editor').getEditedPostContent();
        const charCount = text.length;
        const estimatedCost = (charCount / 1000000 * 15).toFixed(4); // Rough estimate
        
        $('#tts_character_count').text(charCount.toLocaleString());
        $('#tts_estimated_cost').text('$' + estimatedCost);
    }
    
    $('#tts_custom_text').on('input', updateTextStats);
    updateTextStats(); // Initial calculation
    
    // Load voices when provider changes
    $('#tts_voice_provider').on('change', function() {
        const provider = $(this).val();
        const $voiceSelect = $('#tts_voice_id');
        
        if (!provider) {
            $voiceSelect.html('<option value=""><?php _e("Use default voice", "TTS de Wordpress"); ?></option>');
            return;
        }
        
        $voiceSelect.html('<option value=""><?php _e("Loading voices...", "TTS de Wordpress"); ?></option>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_get_voices',
                provider: provider,
                nonce: '<?php echo wp_create_nonce("wp_tts_get_voices"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    let options = '<option value=""><?php _e("Use default voice", "TTS de Wordpress"); ?></option>';
                    response.data.voices.forEach(function(voice) {
                        options += `<option value="${voice.id}">${voice.name} (${voice.language})</option>`;
                    });
                    $voiceSelect.html(options);
                    $('#tts_preview_voice').prop('disabled', false);
                } else {
                    $voiceSelect.html('<option value=""><?php _e("Error loading voices", "TTS de Wordpress"); ?></option>');
                }
            },
            error: function() {
                $voiceSelect.html('<option value=""><?php _e("Error loading voices", "TTS de Wordpress"); ?></option>');
            }
        });
    });
    
    // Voice preview
    $('#tts_preview_voice').on('click', function() {
        const provider = $('#tts_voice_provider').val();
        const voice = $('#tts_voice_id').val();
        
        if (!provider || !voice) {
            alert('<?php _e("Please select a provider and voice first", "TTS de Wordpress"); ?>');
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update wp-tts-spin"></span> <?php _e("Generating...", "TTS de Wordpress"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_preview_voice',
                provider: provider,
                voice: voice,
                nonce: '<?php echo wp_create_nonce("wp_tts_preview_voice"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#tts_preview_source').attr('src', response.data.audio_url);
                    $('#tts_voice_preview').show();
                    $('#tts_voice_preview audio')[0].load();
                } else {
                    alert(response.data.message || '<?php _e("Preview failed", "TTS de Wordpress"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Preview failed", "TTS de Wordpress"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Generate audio
    $('#tts_generate_now, #tts_regenerate').on('click', function() {
        const postId = $('#post_ID').val();
        
        if (!postId) {
            alert('<?php _e("Please save the post first", "TTS de Wordpress"); ?>');
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true);
        $('#tts_generation_progress').show();
        
        // Simulate progress
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 20;
            if (progress > 90) progress = 90;
            $('.wp-tts-progress-fill').css('width', progress + '%');
        }, 500);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_generate_audio',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce("wp_tts_generate_audio"); ?>'
            },
            success: function(response) {
                clearInterval(progressInterval);
                $('.wp-tts-progress-fill').css('width', '100%');
                
                if (response.success) {
                    setTimeout(function() {
                        location.reload(); // Reload to show updated status
                    }, 1000);
                } else {
                    alert(response.data.message || '<?php _e("Generation failed", "TTS de Wordpress"); ?>');
                    $('#tts_generation_progress').hide();
                }
            },
            error: function() {
                clearInterval(progressInterval);
                alert('<?php _e("Generation failed", "TTS de Wordpress"); ?>');
                $('#tts_generation_progress').hide();
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
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

// Debug: Check if providers are correctly loaded
$all_providers = $config->get('providers', []);
$debug_enabled = [];
foreach ($all_providers as $name => $provider_config) {
    if (!empty($provider_config['enabled'])) {
        $debug_enabled[] = $name;
    }
}

// If we have enabled providers but getEnabledProviders is empty, force refresh
if (empty($enabled_providers) && !empty($debug_enabled)) {
    $enabled_providers = $debug_enabled;
}

// Only show enabled providers - if none are enabled, show empty dropdown to encourage configuration
?>

<div class="wp-tts-meta-box">
    <!-- Enable/Disable TTS -->
    <div class="wp-tts-field">
        <div class="wp-tts-field-header">
            <label for="tts_enabled" class="wp-tts-field-label"><?php _e('Enable TTS', 'TTS SesoLibre'); ?></label>
            <label class="wp-tts-toggle">
                <input type="checkbox" 
                       id="tts_enabled" 
                       name="tts_enabled" 
                       value="1" 
                       <?php checked($enabled, true); ?> />
                <span class="wp-tts-toggle-slider"></span>
            </label>
        </div>
        <p class="wp-tts-field-description">
            <?php _e('Enable text-to-speech conversion for this post', 'TTS SesoLibre'); ?>
        </p>
    </div>

    <!-- TTS Provider Selection -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header">
            <label for="tts_voice_provider" class="wp-tts-field-label"><?php _e('TTS Provider', 'TTS SesoLibre'); ?></label>
        </div>
        <div class="wp-tts-field-content">
            <?php if (empty($enabled_providers)): ?>
                <div class="notice notice-warning inline" style="margin: 0; padding: 8px 12px;">
                    <p style="margin: 0;">
                        <?php _e('No TTS providers are currently enabled. Please go to', 'TTS SesoLibre'); ?>
                        <a href="<?php echo admin_url('options-general.php?page=wp-tts-settings'); ?>" target="_blank">
                            <?php _e('TTS Settings', 'TTS SesoLibre'); ?>
                        </a>
                        <?php _e('to enable at least one provider.', 'TTS SesoLibre'); ?>
                    </p>
                </div>
                <select id="tts_voice_provider" name="tts_voice_provider" class="wp-tts-select" disabled>
                    <option value=""><?php _e('No providers enabled', 'TTS SesoLibre'); ?></option>
                </select>
            <?php else: ?>
                <select id="tts_voice_provider" name="tts_voice_provider" class="wp-tts-select">
                    <option value=""><?php _e('Use default provider', 'TTS SesoLibre'); ?></option>
                    <?php foreach ($enabled_providers as $provider_name): ?>
                        <?php $provider_config = $config->getProviderConfig($provider_name); ?>
                        <option value="<?php echo esc_attr($provider_name); ?>" 
                                <?php selected($provider, $provider_name); ?>>
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $provider_name))); ?>
                            <?php if ($provider_name === $defaults['default_provider']): ?>
                                (<?php _e('Default', 'TTS SesoLibre'); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="wp-tts-field-description">
                <?php _e('Select the TTS provider for this post', 'TTS SesoLibre'); ?>
            </p>
        </div>
    </div>

    <!-- Voice Selection -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header">
            <label for="tts_voice_id" class="wp-tts-field-label"><?php _e('Voice', 'TTS SesoLibre'); ?></label>
        </div>
        <div class="wp-tts-field-content">
            <select id="tts_voice_id" name="tts_voice_id" class="wp-tts-select">
                <option value=""><?php _e('Use default voice', 'TTS SesoLibre'); ?></option>
                <!-- Voices will be loaded via AJAX based on provider selection -->
            </select>
            <p class="wp-tts-field-description">
                <?php _e('Select the voice for text-to-speech conversion', 'TTS SesoLibre'); ?>
            </p>
        </div>
    </div>

    <!-- Audio Assets Section -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header" style="cursor: pointer;" onclick="toggleAudioAssets()">
            <label class="wp-tts-field-label">
                <span id="audio-assets-toggle" style="margin-right: 8px;">▶</span>
                <?php _e('Audio Assets', 'TTS SesoLibre'); ?>
                <small style="color: #666; font-weight: normal;"><?php _e('(Click to expand)', 'TTS SesoLibre'); ?></small>
            </label>
        </div>
        <div class="wp-tts-field-content" id="audio-assets-content" style="display: none;">
            <?php
            // Get audio assets configuration
            $audio_assets = [];
            if (class_exists('\\WP_TTS\\Utils\\TTSMetaManager')) {
                $tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post->ID);
                $audio_assets = $tts_data['audio_assets'] ?? [];
            }
            
            $asset_types = [
                'intro_audio' => __('Intro Audio', 'TTS SesoLibre'),
                'background_audio' => __('Background Music', 'TTS SesoLibre'),
                'outro_audio' => __('Outro Audio', 'TTS SesoLibre'),
                'custom_audio' => __('Custom Audio (replaces TTS)', 'TTS SesoLibre')
            ];
            
            foreach ($asset_types as $asset_key => $asset_label): 
                $asset_id = $audio_assets[$asset_key] ?? '';
                $asset_url = $asset_id ? wp_get_attachment_url($asset_id) : '';
                $asset_title = $asset_id ? get_the_title($asset_id) : '';
            ?>
            
            <div class="tts-audio-asset" style="margin-bottom: 20px; padding: 15px; border: 1px solid #e2e4e7; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0;"><?php echo esc_html($asset_label); ?></h4>
                
                <div class="tts-media-selector" data-type="<?php echo esc_attr($asset_key); ?>">
                    <input type="hidden" name="tts_<?php echo esc_attr($asset_key); ?>" value="<?php echo esc_attr($asset_id); ?>" class="tts-media-id" />
                    
                    <div class="tts-media-preview" style="<?php echo $asset_id ? '' : 'display: none;'; ?>">
                        <?php if ($asset_url): ?>
                            <audio controls style="width: 100%; margin-bottom: 10px;">
                                <source src="<?php echo esc_url($asset_url); ?>" type="audio/mpeg">
                                <?php _e('Your browser does not support the audio element.', 'TTS SesoLibre'); ?>
                            </audio>
                        <?php endif; ?>
                        <p class="tts-media-title"><?php echo esc_html($asset_title); ?></p>
                    </div>
                    
                    <div class="tts-media-buttons">
                        <button type="button" class="button tts-select-media"><?php _e('Select Audio', 'TTS SesoLibre'); ?></button>
                        <button type="button" class="button tts-remove-media" style="<?php echo $asset_id ? '' : 'display: none;'; ?>"><?php _e('Remove', 'TTS SesoLibre'); ?></button>
                    </div>
                </div>
                
                <?php if ($asset_key === 'background_audio'): ?>
                <div style="margin-top: 10px;">
                    <label><?php _e('Default Volume:', 'TTS SesoLibre'); ?></label>
                    <input type="range" name="tts_background_volume" 
                           value="<?php echo esc_attr($audio_assets['background_volume'] ?? 0.3); ?>" 
                           min="0" max="1" step="0.1" style="width: 150px; margin-left: 10px;">
                    <span class="volume-display"><?php echo esc_html($audio_assets['background_volume'] ?? 0.3); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endforeach; ?>
            
            <p class="wp-tts-field-description">
                <?php _e('Configure audio assets for enhanced playback experience:', 'TTS SesoLibre'); ?>
                <br>• <strong><?php _e('Intro Audio:', 'TTS SesoLibre'); ?></strong> <?php _e('Plays before the main TTS audio', 'TTS SesoLibre'); ?>
                <br>• <strong><?php _e('Background Music:', 'TTS SesoLibre'); ?></strong> <?php _e('Loops during main TTS audio playback', 'TTS SesoLibre'); ?>
                <br>• <strong><?php _e('Outro Audio:', 'TTS SesoLibre'); ?></strong> <?php _e('Plays after the main TTS audio', 'TTS SesoLibre'); ?>
                <br>• <strong><?php _e('Custom Audio:', 'TTS SesoLibre'); ?></strong> <?php _e('Replaces auto-generated TTS entirely', 'TTS SesoLibre'); ?>
                <br><em><?php _e('Supported formats: MP3, WAV, OGG', 'TTS SesoLibre'); ?></em>
            </p>
        </div>
    </div>

    <!-- Generation Status and Controls -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header">
            <label class="wp-tts-field-label"><?php _e('Audio Status', 'TTS SesoLibre'); ?></label>
        </div>
        <div class="wp-tts-field-content">
                    <div class="wp-tts-status-container">
                        <?php if ($audio_url): ?>
                            <div class="wp-tts-status wp-tts-status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Audio generated successfully', 'TTS de Wordpress'); ?>
                                <a href="<?php echo esc_url($audio_url); ?>" target="_blank" class="button button-small">
                                    <?php _e('Listen', 'TTS de Wordpress'); ?>
                                </a>
                            </div>
                            
                            <!-- Audio Information -->
                            <div class="wp-tts-audio-info" style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 4px; padding: 12px; margin-top: 10px; font-size: 13px;">
                                <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #1d2327;"><?php _e('Audio Details', 'TTS de Wordpress'); ?></h4>
                                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center;">
                                    <?php if ($provider): ?>
                                    <strong style="color: #646970;"><?php _e('Provider:', 'TTS de Wordpress'); ?></strong>
                                    <span style="color: #1d2327;">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $provider))); ?>
                                        <span style="background: #007cba; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: 500; margin-left: 6px;">
                                            <?php echo esc_html(strtoupper($provider)); ?>
                                        </span>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($voice_id): ?>
                                    <strong style="color: #646970;"><?php _e('Voice:', 'TTS de Wordpress'); ?></strong>
                                    <span style="color: #1d2327;"><?php echo esc_html($voice_id); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $generated_at = '';
                                    if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
                                        $tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post->ID);
                                        $generated_at = $tts_data['audio']['generated_at'];
                                    } else {
                                        // Fallback to old system
                                        $generated_at = get_post_meta($post->ID, '_tts_generated_at', true);
                                        if ($generated_at && is_numeric($generated_at)) {
                                            $generated_at = date('Y-m-d H:i:s', $generated_at);
                                        }
                                    }
                                    if ($generated_at): 
                                    ?>
                                    <strong style="color: #646970;"><?php _e('Generated:', 'TTS de Wordpress'); ?></strong>
                                    <span style="color: #1d2327;">
                                        <?php 
                                        $timestamp = strtotime($generated_at);
                                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)); 
                                        ?>
                                        <span style="color: #646970; font-size: 11px;">
                                            (<?php echo esc_html(human_time_diff($timestamp, current_time('timestamp'))); ?> <?php _e('ago', 'TTS de Wordpress'); ?>)
                                        </span>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $file_size = '';
                                    if ($audio_url) {
                                        $upload_dir = wp_upload_dir();
                                        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $audio_url);
                                        if (file_exists($file_path)) {
                                            $file_size = size_format(filesize($file_path));
                                        }
                                    }
                                    if ($file_size): 
                                    ?>
                                    <strong style="color: #646970;"><?php _e('File Size:', 'TTS de Wordpress'); ?></strong>
                                    <span style="color: #1d2327;"><?php echo esc_html($file_size); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <audio controls style="width: 100%; margin-top: 10px;">
                                <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                                <?php _e('Your browser does not support the audio element.', 'TTS SesoLibre'); ?>
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
                            <button type="button" id="tts_delete_audio" class="button button-secondary" style="color: #d63638;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Delete Audio', 'TTS de Wordpress'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="tts_generation_progress" style="display: none; margin-top: 15px;">
                        <div class="wp-tts-progress-bar">
                            <div class="wp-tts-progress-fill" style="width: 0%;"></div>
                        </div>
                        <p class="wp-tts-progress-text"><?php _e('Preparing...', 'TTS SesoLibre'); ?></p>
                    </div>
        </div>
    </div>
</div>

<style>
/* Responsive Meta Box Design */
.wp-tts-meta-box {
    max-width: 100%;
}

.wp-tts-field {
    margin-bottom: 20px;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 15px;
}

.wp-tts-field:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.wp-tts-field-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 10px;
}

.wp-tts-field-label {
    font-weight: 600;
    color: #1d2327;
    margin: 0;
    flex: 1;
    min-width: 120px;
}

.wp-tts-field-content {
    width: 100%;
}

.wp-tts-select {
    width: 100%;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background-color: #fff;
    font-size: 14px;
}

.wp-tts-field-description {
    margin: 8px 0 0 0;
    font-size: 13px;
    color: #646970;
    font-style: italic;
}

/* Toggle Switch */
.wp-tts-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    flex-shrink: 0;
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
    background-color: #007cba;
}

.wp-tts-toggle input:checked + .wp-tts-toggle-slider:before {
    transform: translateX(26px);
}

/* Conditional visibility */
.wp-tts-conditional {
    display: none;
}

.wp-tts-conditional.active {
    display: block;
}

/* Responsive breakpoints */
@media (max-width: 600px) {
    .wp-tts-field-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .wp-tts-field-label {
        margin-bottom: 5px;
    }
    
    .wp-tts-toggle {
        align-self: flex-end;
    }
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

/* Media Selector Styles */
.tts-media-selector {
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
    margin-top: 8px;
}

.tts-media-preview {
    margin-bottom: 15px;
}

.tts-media-preview audio {
    width: 100%;
    margin-bottom: 10px;
}

.tts-media-title {
    font-weight: 500;
    margin: 0;
    color: #555;
    font-size: 13px;
}

.tts-media-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tts-media-buttons .button {
    margin: 0;
    font-size: 12px;
    padding: 4px 8px;
    height: auto;
    line-height: 1.4;
}

@media (max-width: 480px) {
    .tts-media-buttons {
        flex-direction: column;
    }
    
    .tts-media-buttons .button {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle conditional fields
    function toggleConditionalFields() {
        const isEnabled = $('#tts_enabled').is(':checked');
        $('.wp-tts-conditional').toggleClass('active', isEnabled);
    }
    
    $('#tts_enabled').on('change', function() {
        toggleConditionalFields();
        
        // Auto-save TTS enabled state
        const postId = $('#post_ID').val();
        const isEnabled = $(this).is(':checked');
        
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tts_auto_save_enabled',
                    post_id: postId,
                    enabled: isEnabled ? '1' : '0',
                    nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                },
                success: function(response) {
                    console.log('TTS enabled state saved:', response);
                },
                error: function() {
                    console.log('Failed to save TTS enabled state');
                }
            });
        }
    });
    toggleConditionalFields(); // Initial state
    
    
    // Load voices when provider changes
    $('#tts_voice_provider').on('change', function() {
        const provider = $(this).val();
        const $voiceSelect = $('#tts_voice_id');
        
        // Auto-save provider selection
        const postId = $('#post_ID').val();
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tts_auto_save_provider',
                    post_id: postId,
                    provider: provider,
                    nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                },
                success: function(response) {
                    console.log('TTS provider saved:', response);
                },
                error: function() {
                    console.log('Failed to save TTS provider');
                }
            });
        }
        
        if (!provider) {
            $voiceSelect.html('<option value=""><?php _e("Use default voice", "TTS SesoLibre"); ?></option>');
            return;
        }
        
        $voiceSelect.html('<option value=""><?php _e("Loading voices...", "TTS SesoLibre"); ?></option>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_get_voices_metabox',
                provider: provider,
                nonce: '<?php echo wp_create_nonce("wp_tts_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    let options = '<option value=""><?php _e("Use default voice", "TTS de Wordpress"); ?></option>';
                    response.data.voices.forEach(function(voice) {
                        options += `<option value="${voice.id}">${voice.name} (${voice.language})</option>`;
                    });
                    $voiceSelect.html(options);
                } else {
                    $voiceSelect.html('<option value=""><?php _e("Error loading voices", "TTS SesoLibre"); ?></option>');
                }
            },
            error: function() {
                $voiceSelect.html('<option value=""><?php _e("Error loading voices", "TTS SesoLibre"); ?></option>');
            }
        });
    });
    
    // Auto-save voice selection
    $(document).on('change', '#tts_voice_id', function() {
        const postId = $('#post_ID').val();
        const voiceId = $(this).val();
        const provider = $('#tts_voice_provider').val();
        
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tts_auto_save_voice',
                    post_id: postId,
                    provider: provider,
                    voice_id: voiceId,
                    nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                },
                success: function(response) {
                    console.log('TTS voice saved:', response);
                },
                error: function() {
                    console.log('Failed to save TTS voice');
                }
            });
        }
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
    
    // Delete audio
    $('#tts_delete_audio').on('click', function() {
        if (!confirm('<?php _e("Are you sure you want to delete the generated audio? This action cannot be undone.", "TTS de Wordpress"); ?>')) {
            return;
        }
        
        const postId = $('#post_ID').val();
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update wp-tts-spin"></span> <?php _e("Deleting...", "TTS de Wordpress"); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_delete_audio',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce("wp_tts_delete_audio"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload to show updated status
                } else {
                    alert(response.data.message || '<?php _e("Delete failed", "TTS de Wordpress"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Delete failed", "TTS de Wordpress"); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Media Library Integration for Intro/Outro in metabox
    var mediaFrame;
    
    // Select media button click
    $(document).on('click', '.wp-tts-field .tts-select-media', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $container = $button.closest('.tts-media-selector');
        var type = $container.data('type');
        
        // Create media frame
        mediaFrame = wp.media({
            title: '<?php _e("Select Audio File", "TTS SesoLibre"); ?>',
            button: {
                text: '<?php _e("Use this audio", "TTS SesoLibre"); ?>'
            },
            library: {
                type: 'audio'
            },
            multiple: false
        });
        
        // Handle media selection
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            
            // Update hidden input
            $container.find('.tts-media-id').val(attachment.id);
            
            // Update preview
            var $preview = $container.find('.tts-media-preview');
            var audioHtml = '<audio controls style="width: 100%; margin-bottom: 10px;">' +
                '<source src="' + attachment.url + '" type="' + attachment.mime + '">' +
                '<?php _e("Your browser does not support the audio element.", "TTS SesoLibre"); ?>' +
                '</audio>';
            
            $preview.find('audio').remove();
            $preview.prepend(audioHtml);
            $preview.find('.tts-media-title').text(attachment.title);
            $preview.show();
            
            // Show remove button
            $container.find('.tts-remove-media').show();
            
            // Auto-save the selection
            var postId = $('#post_ID').val();
            
            if (postId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tts_auto_save_audio_asset',
                        post_id: postId,
                        asset_type: type,
                        attachment_id: attachment.id,
                        nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                    }
                });
            }
        });
        
        // Open media frame
        mediaFrame.open();
    });
    
    // Remove media button click
    $(document).on('click', '.wp-tts-field .tts-remove-media', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $container = $button.closest('.tts-media-selector');
        var type = $container.data('type');
        
        // Clear hidden input
        $container.find('.tts-media-id').val('');
        
        // Hide preview
        $container.find('.tts-media-preview').hide();
        
        // Hide remove button
        $button.hide();
        
        // Auto-save the removal
        var postId = $('#post_ID').val();
        
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tts_auto_save_audio_asset',
                    post_id: postId,
                    asset_type: type,
                    attachment_id: '',
                    nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                }
            });
        }
    });
    
    // Use default button click
    $(document).on('click', '.wp-tts-field .tts-use-default', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $container = $button.closest('.tts-media-selector');
        var defaultId = $button.data('default-id');
        var type = $container.data('type');
        
        if (defaultId) {
            // Clear the custom selection to fall back to default
            $container.find('.tts-media-id').val('');
            
            // Auto-save the change
            var postId = $('#post_ID').val();
            
            if (postId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tts_auto_save_audio_asset',
                        post_id: postId,
                        asset_type: type,
                        attachment_id: '',
                        nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                    },
                    success: function() {
                        // Reload to show default
                        location.reload();
                    }
                });
            }
        }
    });
    
    // Background volume slider update
    $('input[name="tts_background_volume"]').on('input', function() {
        const value = $(this).val();
        $(this).siblings('.volume-display').text(value);
        
        // Auto-save volume setting
        const postId = $('#post_ID').val();
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tts_auto_save_background_volume',
                    post_id: postId,
                    volume: value,
                    nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                }
            });
        }
    });
    
    // Toggle Audio Assets Section (outside jQuery)
    window.toggleAudioAssets = function() {
        const content = document.getElementById('audio-assets-content');
        const toggle = document.getElementById('audio-assets-toggle');
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            toggle.innerHTML = '▼';
        } else {
            content.style.display = 'none';
            toggle.innerHTML = '▶';
        }
    };
});
</script>
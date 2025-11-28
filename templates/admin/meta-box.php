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
            <label for="tts_enabled" class="wp-tts-field-label"><?php _e('Enable TTS', 'wp-tts-sesolibre'); ?></label>
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
            <?php _e('Enable text-to-speech conversion for this post', 'wp-tts-sesolibre'); ?>
        </p>
    </div>

    <!-- TTS Provider Selection -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header">
            <label for="tts_voice_provider" class="wp-tts-field-label"><?php _e('TTS Provider', 'wp-tts-sesolibre'); ?></label>
        </div>
        <div class="wp-tts-field-content">
            <?php if (empty($enabled_providers)): ?>
                <div class="notice notice-warning inline" style="margin: 0; padding: 8px 12px;">
                    <p style="margin: 0;">
                        <?php _e('No TTS providers are currently enabled. Please go to', 'wp-tts-sesolibre'); ?>
                        <a href="<?php echo admin_url('options-general.php?page=wp-tts-settings'); ?>" target="_blank">
                            <?php _e('TTS Settings', 'wp-tts-sesolibre'); ?>
                        </a>
                        <?php _e('to enable at least one provider.', 'wp-tts-sesolibre'); ?>
                    </p>
                </div>
                <select id="tts_voice_provider" name="tts_voice_provider" class="wp-tts-select" disabled>
                    <option value=""><?php _e('No providers enabled', 'wp-tts-sesolibre'); ?></option>
                </select>
            <?php else: ?>
                <select id="tts_voice_provider" name="tts_voice_provider" class="wp-tts-select">
                    <option value=""><?php _e('Use default provider', 'wp-tts-sesolibre'); ?></option>
                    <?php foreach ($enabled_providers as $provider_name): ?>
                        <?php $provider_config = $config->getProviderConfig($provider_name); ?>
                        <option value="<?php echo esc_attr($provider_name); ?>" 
                                <?php selected($provider, $provider_name); ?>>
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $provider_name))); ?>
                            <?php if ($provider_name === $defaults['default_provider']): ?>
                                (<?php _e('Default', 'wp-tts-sesolibre'); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="wp-tts-field-description">
                <?php _e('Select the TTS provider for this post', 'wp-tts-sesolibre'); ?>
            </p>
        </div>
    </div>

    <!-- Voice Selection -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header">
            <label for="tts_voice_id" class="wp-tts-field-label"><?php _e('Voice', 'wp-tts-sesolibre'); ?></label>
        </div>
        <div class="wp-tts-field-content">
            <select id="tts_voice_id" name="tts_voice_id" class="wp-tts-select">
                <option value=""><?php _e('Use default voice', 'wp-tts-sesolibre'); ?></option>
                <!-- Voices will be loaded via AJAX based on provider selection -->
            </select>
            <p class="wp-tts-field-description">
                <?php _e('Select the voice for text-to-speech conversion', 'wp-tts-sesolibre'); ?>
            </p>
        </div>
    </div>

    <!-- Audio Assets Section -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header" style="cursor: pointer;" onclick="toggleAudioAssets()">
            <label class="wp-tts-field-label">
                <span id="audio-assets-toggle" style="margin-right: 8px;">▶</span>
                <?php _e('Audio Assets', 'wp-tts-sesolibre'); ?>
                <small style="color: #666; font-weight: normal;"><?php _e('(Click to expand)', 'wp-tts-sesolibre'); ?></small>
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
            
            // If no post-specific audio assets, check for defaults
            $config = get_option('wp_tts_config', []);
            $default_intro = $config['audio_assets']['default_intro'] ?? '';
            $default_outro = $config['audio_assets']['default_outro'] ?? '';
            $default_background = $config['audio_assets']['default_background'] ?? '';
            
            // Merge defaults with post-specific values (post-specific takes precedence)
            $audio_assets = array_merge([
                'intro_audio' => $default_intro,
                'outro_audio' => $default_outro,
                'background_audio' => $default_background,
                'background_volume' => 0.3
            ], $audio_assets);
            
            $asset_types = [
                'intro_audio' => __('Intro Audio', 'wp-tts-sesolibre'),
                'background_audio' => __('Background Music', 'wp-tts-sesolibre'),
                'outro_audio' => __('Outro Audio', 'wp-tts-sesolibre'),
                'custom_audio' => __('Custom Audio (replaces TTS)', 'wp-tts-sesolibre')
            ];
            
            foreach ($asset_types as $asset_key => $asset_label): 
                $asset_id = $audio_assets[$asset_key] ?? '';
                $asset_url = $asset_id ? wp_get_attachment_url($asset_id) : '';
                $asset_title = $asset_id ? get_the_title($asset_id) : '';
                
                // Check if this is a default value
                $is_default = false;
                $default_id = '';
                if ($asset_key === 'intro_audio' && $default_intro) {
                    $is_default = ($asset_id === $default_intro);
                    $default_id = $default_intro;
                } elseif ($asset_key === 'outro_audio' && $default_outro) {
                    $is_default = ($asset_id === $default_outro);
                    $default_id = $default_outro;
                } elseif ($asset_key === 'background_audio' && $default_background) {
                    $is_default = ($asset_id === $default_background);
                    $default_id = $default_background;
                }
            ?>
            
            <div class="tts-audio-asset" style="margin-bottom: 20px; padding: 15px; border: 1px solid #e2e4e7; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0;">
                    <?php echo esc_html($asset_label); ?>
                    <?php if ($is_default): ?>
                        <span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: 500; margin-left: 6px;">
                            <?php _e('DEFAULT', 'wp-tts-sesolibre'); ?>
                        </span>
                    <?php endif; ?>
                </h4>
                
                <div class="tts-media-selector" data-type="<?php echo esc_attr($asset_key); ?>">
                    <input type="hidden" name="tts_<?php echo esc_attr($asset_key); ?>" value="<?php echo esc_attr($asset_id); ?>" class="tts-media-id" />
                    
                    <div class="tts-media-preview" style="<?php echo $asset_id ? '' : 'display: none;'; ?>">
                        <?php if ($asset_url): ?>
                            <audio controls style="width: 100%; margin-bottom: 10px;">
                                <source src="<?php echo esc_url($asset_url); ?>" type="audio/mpeg">
                                <?php _e('Your browser does not support the audio element.', 'wp-tts-sesolibre'); ?>
                            </audio>
                        <?php endif; ?>
                        <p class="tts-media-title"><?php echo esc_html($asset_title); ?></p>
                    </div>
                    
                    <div class="tts-media-buttons">
                        <button type="button" class="button tts-select-media"><?php _e('Select Audio', 'wp-tts-sesolibre'); ?></button>
                        <button type="button" class="button tts-remove-media" style="<?php echo $asset_id ? '' : 'display: none;'; ?>"><?php _e('Remove', 'wp-tts-sesolibre'); ?></button>
                        <?php if ($default_id && !$is_default): ?>
                            <button type="button" class="button tts-use-default" data-default-id="<?php echo esc_attr($default_id); ?>"><?php _e('Use Default', 'wp-tts-sesolibre'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($asset_key === 'background_audio'): ?>
                <div style="margin-top: 10px;">
                    <label><?php _e('Default Volume:', 'wp-tts-sesolibre'); ?></label>
                    <input type="range" name="tts_background_volume" 
                           value="<?php echo esc_attr($audio_assets['background_volume'] ?? 0.3); ?>" 
                           min="0" max="1" step="0.1" style="width: 150px; margin-left: 10px;">
                    <span class="volume-display"><?php echo esc_html($audio_assets['background_volume'] ?? 0.3); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endforeach; ?>
            
            <p class="wp-tts-field-description">
                <?php _e('Configure audio assets for enhanced playback experience:', 'wp-tts-sesolibre'); ?>
                <br>• <strong><?php _e('Intro Audio:', 'wp-tts-sesolibre'); ?></strong> <?php _e('Plays before the main TTS audio', 'wp-tts-sesolibre'); ?>
                <br>• <strong><?php _e('Background Music:', 'wp-tts-sesolibre'); ?></strong> <?php _e('Loops during main TTS audio playback', 'wp-tts-sesolibre'); ?>
                <br>• <strong><?php _e('Outro Audio:', 'wp-tts-sesolibre'); ?></strong> <?php _e('Plays after the main TTS audio', 'wp-tts-sesolibre'); ?>
                <br>• <strong><?php _e('Custom Audio:', 'wp-tts-sesolibre'); ?></strong> <?php _e('Replaces auto-generated TTS entirely', 'wp-tts-sesolibre'); ?>
                <br><em><?php _e('Supported formats: MP3, WAV, OGG', 'wp-tts-sesolibre'); ?></em>
            </p>
        </div>
    </div>

    <!-- Generation Status and Controls -->
    <div class="wp-tts-field wp-tts-conditional" data-depends="tts_enabled">
        <div class="wp-tts-field-header">
            <label class="wp-tts-field-label"><?php _e('Audio Status', 'wp-tts-sesolibre'); ?></label>
        </div>
        <div class="wp-tts-field-content">
                    <div class="wp-tts-status-container">
                        <?php if ($audio_url): ?>
                            <div class="wp-tts-status wp-tts-status-success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Audio generated successfully', 'wp-tts-sesolibre'); ?>
                                <a href="<?php echo esc_url($audio_url); ?>" target="_blank" class="button button-small">
                                    <?php _e('Listen', 'wp-tts-sesolibre'); ?>
                                </a>
                            </div>
                            
                            <!-- Audio Information -->
                            <div class="wp-tts-audio-info" style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 4px; padding: 12px; margin-top: 10px; font-size: 13px;">
                                <h4 style="margin: 0 0 8px 0; font-size: 13px; color: #1d2327;"><?php _e('Audio Details', 'wp-tts-sesolibre'); ?></h4>
                                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center;">
                                    <?php if ($provider): ?>
                                    <strong style="color: #646970;"><?php _e('Provider:', 'wp-tts-sesolibre'); ?></strong>
                                    <span style="color: #1d2327;">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $provider))); ?>
                                        <span style="background: #007cba; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; font-weight: 500; margin-left: 6px;">
                                            <?php echo esc_html(strtoupper($provider)); ?>
                                        </span>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($voice_id): ?>
                                    <strong style="color: #646970;"><?php _e('Voice:', 'wp-tts-sesolibre'); ?></strong>
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
                                    <strong style="color: #646970;"><?php _e('Generated:', 'wp-tts-sesolibre'); ?></strong>
                                    <span style="color: #1d2327;">
                                        <?php 
                                        $timestamp = strtotime($generated_at);
                                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)); 
                                        ?>
                                        <span style="color: #646970; font-size: 11px;">
                                            (<?php echo esc_html(human_time_diff($timestamp, current_time('timestamp'))); ?> <?php _e('ago', 'wp-tts-sesolibre'); ?>)
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
                                    <strong style="color: #646970;"><?php _e('File Size:', 'wp-tts-sesolibre'); ?></strong>
                                    <span style="color: #1d2327;"><?php echo esc_html($file_size); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <audio controls style="width: 100%; margin-top: 10px;">
                                <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                                <?php _e('Your browser does not support the audio element.', 'wp-tts-sesolibre'); ?>
                            </audio>
                        <?php elseif ($status === 'processing'): ?>
                            <div class="wp-tts-status wp-tts-status-processing">
                                <span class="dashicons dashicons-update wp-tts-spin"></span>
                                <?php _e('Generating audio...', 'wp-tts-sesolibre'); ?>
                            </div>
                        <?php elseif ($status === 'failed'): ?>
                            <div class="wp-tts-status wp-tts-status-error">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('Audio generation failed', 'wp-tts-sesolibre'); ?>
                            </div>
                        <?php else: ?>
                            <div class="wp-tts-status wp-tts-status-pending">
                                <span class="dashicons dashicons-clock"></span>
                                <?php _e('Audio not generated yet', 'wp-tts-sesolibre'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wp-tts-actions" style="margin-top: 15px;">
                        <button type="button" id="tts_edit_text" class="button button-secondary" style="margin-bottom: 10px;">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Edit Text Before Generate', 'wp-tts-sesolibre'); ?>
                        </button>
                        <br>
                        <button type="button" id="tts_generate_now" class="button button-primary" 
                                <?php echo $status === 'processing' ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php _e('Generate Audio Now', 'wp-tts-sesolibre'); ?>
                        </button>
                        
                        <?php if ($audio_url): ?>
                            <button type="button" id="tts_regenerate" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Regenerate', 'wp-tts-sesolibre'); ?>
                            </button>
                            <button type="button" id="tts_delete_audio" class="button button-secondary" style="color: #d63638;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Delete Audio', 'wp-tts-sesolibre'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="tts_generation_progress" style="display: none; margin-top: 15px;">
                        <div class="wp-tts-progress-bar">
                            <div class="wp-tts-progress-fill" style="width: 0%;"></div>
                        </div>
                        <p class="wp-tts-progress-text"><?php _e('Preparing...', 'wp-tts-sesolibre'); ?></p>
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

/* Text Editor Modal Styles */
.tts-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.tts-modal-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-width: 800px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.tts-modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.tts-modal-header h2 {
    margin: 0;
    font-size: 18px;
    color: #1d2327;
}

.tts-modal-close {
    font-size: 24px;
    color: #666;
    cursor: pointer;
    line-height: 1;
    padding: 5px;
    margin: -5px;
    border-radius: 4px;
    transition: all 0.2s;
}

.tts-modal-close:hover {
    background: #e5e5e5;
    color: #333;
}

.tts-modal-body {
    padding: 25px;
    flex: 1;
    overflow-y: auto;
}

.tts-editor-info {
    margin-bottom: 15px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
}

.tts-validation-message {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.tts-validation-message.tts-valid {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.tts-validation-message.tts-invalid {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.tts-editor-toolbar {
    margin-bottom: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tts-editor-toolbar .button {
    margin: 0;
    font-size: 12px;
    padding: 6px 12px;
    height: auto;
    line-height: 1.4;
}

#tts-editor-textarea {
    width: 100%;
    resize: vertical;
    font-family: Monaco, Menlo, "Ubuntu Mono", monospace;
    line-height: 1.6;
    padding: 15px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background: #fff;
    box-sizing: border-box;
}

.tts-editor-stats {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    font-size: 12px;
    color: #666;
    text-align: center;
}

.tts-modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e5e5e5;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.tts-modal-footer .button {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.tts-modal-footer .button .dashicons {
    margin-top: 1px;
}

/* Responsive modal */
@media (max-width: 768px) {
    .tts-modal-backdrop {
        padding: 10px;
    }
    
    .tts-modal-container {
        max-height: 95vh;
    }
    
    .tts-modal-header,
    .tts-modal-body,
    .tts-modal-footer {
        padding: 15px;
    }
    
    .tts-modal-footer {
        flex-direction: column;
    }
    
    .tts-modal-footer .button {
        width: 100%;
        justify-content: center;
    }
    
    .tts-editor-toolbar {
        flex-direction: column;
    }
    
    .tts-editor-toolbar .button {
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
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
                    // Debug removed
                    
                    // If enabling TTS for the first time, load default assets
                    if (isEnabled && response.success && response.data && response.data.load_defaults) {
                        loadDefaultAudioAssets();
                    }
                },
                error: function() {
                    // Debug removed
                }
            });
        }
    });
    toggleConditionalFields(); // Initial state
    
    // Function to load default audio assets
    function loadDefaultAudioAssets() {
        const postId = $('#post_ID').val();
        
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tts_load_default_assets',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce("wp_tts_auto_save"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Debug removed
                        location.reload();
                    }
                },
                error: function() {
                    // Debug removed
                }
            });
        }
    }
    
    // Initialize voices for preselected provider
    function initializeVoices() {
        const provider = $('#tts_voice_provider').val();
        const currentVoiceId = '<?php echo esc_js($voice_id); ?>';
        
        if (provider) {
            // Debug removed
            const $voiceSelect = $('#tts_voice_id');
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
                            const selected = voice.id === currentVoiceId ? ' selected' : '';
                            options += `<option value="${voice.id}"${selected}>${voice.name} (${voice.language})</option>`;
                        });
                        $voiceSelect.html(options);
                        // Debug removed
                    } else {
                        $voiceSelect.html('<option value=""><?php _e("Error loading voices", "TTS SesoLibre"); ?></option>');
                    }
                },
                error: function() {
                    $voiceSelect.html('<option value=""><?php _e("Error loading voices", "TTS SesoLibre"); ?></option>');
                }
            });
        }
    }
    
    // Initialize voices on page load
    initializeVoices();
    
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
                    // Debug removed
                },
                error: function() {
                    // Debug removed
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
                    // Debug removed
                },
                error: function() {
                    // Debug removed
                }
            });
        }
    });
    
    // Generate audio
    $('#tts_generate_now, #tts_regenerate').on('click', function() {
        // Debug removed
        const postId = $('#post_ID').val();
        
        // Debug removed
        
        if (!postId) {
            // Debug removed
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
        
        // Debug removed
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_generate_audio',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce("wp_tts_generate_audio"); ?>'
            },
            success: function(response) {
                // Debug removed
                clearInterval(progressInterval);
                $('.wp-tts-progress-fill').css('width', '100%');
                
                if (response.success) {
                    // Debug removed
                    setTimeout(function() {
                        // Debug removed
                        location.reload(); // Reload to show updated status
                    }, 1000);
                } else {
                    // Debug removed
                    alert(response.data.message || '<?php _e("Generation failed", "TTS de Wordpress"); ?>');
                    $('#tts_generation_progress').hide();
                }
            },
            error: function(xhr, status, error) {
                // Debug removed
                clearInterval(progressInterval);
                alert('<?php _e("Generation failed", "TTS de Wordpress"); ?>');
                $('#tts_generation_progress').hide();
            },
            complete: function() {
                // Debug removed
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
            // Set the default ID as the selected value
            $container.find('.tts-media-id').val(defaultId);
            
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
                        attachment_id: defaultId,
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
    
    // Text Editor Modal Functionality
    let originalPostText = '';
    
    // Open text editor modal
    $('#tts_edit_text').on('click', function() {
        const postId = $('#post_ID').val();
        
        if (!postId) {
            alert('<?php _e("Please save the post first", "wp-tts-sesolibre"); ?>');
            return;
        }
        
        // Show loading
        $(this).prop('disabled', true).html('<span class="dashicons dashicons-update wp-tts-spin"></span> <?php _e("Loading...", "wp-tts-sesolibre"); ?>');
        
        // Extract content via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_extract_post_content',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce("wp_tts_admin"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    originalPostText = response.data.text;
                    showTextEditorModal(response.data);
                } else {
                    alert(response.data.message || '<?php _e("Error extracting content", "wp-tts-sesolibre"); ?>');
                }
            },
            error: function() {
                alert('<?php _e("Error connecting to server", "wp-tts-sesolibre"); ?>');
            },
            complete: function() {
                $('#tts_edit_text').prop('disabled', false).html('<span class="dashicons dashicons-edit"></span> <?php _e("Edit Text Before Generate", "wp-tts-sesolibre"); ?>');
            }
        });
    });
    
    // Show text editor modal
    function showTextEditorModal(data) {
        // Create modal HTML
        const modalHtml = `
            <div id="tts-editor-modal" class="tts-modal-backdrop">
                <div class="tts-modal-container">
                    <div class="tts-modal-header">
                        <h2><?php _e("Edit Text for TTS Generation", "wp-tts-sesolibre"); ?></h2>
                        <span class="tts-modal-close">&times;</span>
                    </div>
                    <div class="tts-modal-body">
                        <div class="tts-editor-info">
                            <strong><?php _e("Post:", "wp-tts-sesolibre"); ?></strong> ${data.post_title}
                            <div class="tts-validation-message ${data.validation.valid ? 'tts-valid' : 'tts-invalid'}">
                                ${data.validation.valid ? '✓' : '⚠'} ${data.validation.message}
                            </div>
                        </div>
                        <div class="tts-editor-toolbar">
                            <button type="button" id="tts-clean-text" class="button button-small">
                                <span class="dashicons dashicons-admin-tools"></span> <?php _e("Clean Text", "wp-tts-sesolibre"); ?>
                            </button>
                            <button type="button" id="tts-reset-text" class="button button-small">
                                <span class="dashicons dashicons-undo"></span> <?php _e("Reset to Original", "wp-tts-sesolibre"); ?>
                            </button>
                        </div>
                        <textarea id="tts-editor-textarea" rows="15" class="large-text">${data.text}</textarea>
                        <div class="tts-editor-stats">
                            <span id="tts-char-count">${data.character_count}</span> <?php _e("characters", "wp-tts-sesolibre"); ?>
                            <span style="margin: 0 10px;">|</span>
                            <span id="tts-word-count">${data.word_count}</span> <?php _e("words", "wp-tts-sesolibre"); ?>
                            <span style="margin: 0 10px;">|</span>
                            <span id="tts-cost-estimate">$${((data.character_count / 1000000) * 15).toFixed(4)}</span> <?php _e("estimated cost", "wp-tts-sesolibre"); ?>
                        </div>
                    </div>
                    <div class="tts-modal-footer">
                        <button type="button" id="tts-save-and-generate" class="button button-primary">
                            <span class="dashicons dashicons-controls-play"></span> <?php _e("Save & Generate Audio", "wp-tts-sesolibre"); ?>
                        </button>
                        <button type="button" id="tts-save-only" class="button button-secondary">
                            <span class="dashicons dashicons-saved"></span> <?php _e("Save Only", "wp-tts-sesolibre"); ?>
                        </button>
                        <button type="button" class="button tts-modal-close"><?php _e("Cancel", "wp-tts-sesolibre"); ?></button>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal
        $('#tts-editor-modal').remove();
        
        // Add modal to body
        $('body').append(modalHtml);
        
        // Update stats on text change
        $('#tts-editor-textarea').on('input', updateEditorStats);
        
        // Initial stats update
        updateEditorStats();
    }
    
    // Update editor statistics
    function updateEditorStats() {
        const text = $('#tts-editor-textarea').val();
        const charCount = text.length;
        const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
        const costEstimate = ((charCount / 1000000) * 15).toFixed(4);
        
        $('#tts-char-count').text(charCount.toLocaleString());
        $('#tts-word-count').text(wordCount.toLocaleString());
        $('#tts-cost-estimate').text('$' + costEstimate);
    }
    
    // Close modal
    $(document).on('click', '.tts-modal-close', function() {
        $('#tts-editor-modal').remove();
    });
    
    // Clean text
    $(document).on('click', '#tts-clean-text', function() {
        let text = $('#tts-editor-textarea').val();
        text = text.replace(/\s+/g, ' '); // Multiple spaces
        text = text.replace(/\[\s*\]/g, ''); // Empty brackets
        text = text.replace(/\(\s*\)/g, ''); // Empty parentheses
        text = text.trim();
        $('#tts-editor-textarea').val(text);
        updateEditorStats();
    });
    
    // Reset text
    $(document).on('click', '#tts-reset-text', function() {
        if (originalPostText && confirm('<?php _e("Are you sure you want to reset to the original text? All changes will be lost.", "wp-tts-sesolibre"); ?>')) {
            $('#tts-editor-textarea').val(originalPostText);
            updateEditorStats();
        }
    });
    
    // Save only
    $(document).on('click', '#tts-save-only', function() {
        // Debug removed
        const postId = $('#post_ID').val();
        const text = $('#tts-editor-textarea').val().trim();
        
        // Debug removed
        // Debug removed
        
        if (!text) {
            // Debug removed
            alert('<?php _e("Text cannot be empty", "wp-tts-sesolibre"); ?>');
            return;
        }
        
        const $button = $(this);
        const originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update wp-tts-spin"></span> <?php _e("Saving...", "wp-tts-sesolibre"); ?>');
        
        // Debug removed
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_save_edited_text',
                post_id: postId,
                text: text,
                nonce: '<?php echo wp_create_nonce("wp_tts_admin"); ?>'
            },
            success: function(response) {
                // Debug removed
                if (response.success) {
                    const charCount = response.data.character_count || 0;
                    const wordCount = response.data.word_count || 0;
                    let message = '<?php _e("Text saved successfully!", "wp-tts-sesolibre"); ?>';
                    message += '\n<?php _e("Characters:", "wp-tts-sesolibre"); ?> ' + charCount;
                    message += '\n<?php _e("Words:", "wp-tts-sesolibre"); ?> ' + wordCount;
                    message += '\n\n<?php _e("You can now use the Generate Audio button to create TTS from your edited text.", "wp-tts-sesolibre"); ?>';
                    // Debug removed
                    alert(message);
                    // Debug removed
                    $('#tts-editor-modal').remove();
                } else {
                    const errorMsg = response.data?.message || response.message || '<?php _e("Error saving text", "wp-tts-sesolibre"); ?>';
                    // Debug removed
                    alert('<?php _e("Save failed:", "wp-tts-sesolibre"); ?>\n\n' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                // Debug removed
                alert('<?php _e("Error connecting to server:", "wp-tts-sesolibre"); ?> ' + error);
            },
            complete: function() {
                // Debug removed
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Save and generate
    $(document).on('click', '#tts-save-and-generate', function() {
        // Debug removed
        const postId = $('#post_ID').val();
        const text = $('#tts-editor-textarea').val().trim();
        const provider = $('#tts_voice_provider').val();
        const voice = $('#tts_voice_id').val();
        
        // Debug removed
        // Debug removed
        // Debug removed
        // Debug removed
        
        if (!text) {
            // Debug removed
            alert('<?php _e("Text cannot be empty", "wp-tts-sesolibre"); ?>');
            return;
        }
        
        const $button = $(this);
        const originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update wp-tts-spin"></span> <?php _e("Saving & Generating...", "wp-tts-sesolibre"); ?>');
        
        // Debug removed
        
        // First save the text
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tts_save_edited_text',
                post_id: postId,
                text: text,
                nonce: '<?php echo wp_create_nonce("wp_tts_admin"); ?>'
            },
            success: function(response) {
                // Debug removed
                if (response.success) {
                    // Debug removed
                    // Then generate audio
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tts_generate_from_edited',
                            post_id: postId,
                            provider: provider,
                            voice: voice,
                            nonce: '<?php echo wp_create_nonce("wp_tts_admin"); ?>'
                        },
                        success: function(genResponse) {
                            // Debug removed
                            if (genResponse.success) {
                                // Show detailed success message
                                const audioUrl = genResponse.data.audio_url || '';
                                let message = '<?php _e("Audio generated successfully!", "wp-tts-sesolibre"); ?>';
                                if (audioUrl) {
                                    message += '\n<?php _e("Audio URL:", "wp-tts-sesolibre"); ?> ' + audioUrl;
                                }
                                // Debug removed
                                alert(message);
                                
                                // Debug removed
                                $('#tts-editor-modal').remove();
                                
                                // Debug removed
                                // Force reload after short delay to ensure modal is closed
                                setTimeout(function() {
                                    // Debug removed
                                    window.location.reload();
                                }, 500);
                            } else {
                                const errorMsg = genResponse.data?.message || genResponse.message || '<?php _e("Error generating audio", "wp-tts-sesolibre"); ?>';
                                // Debug removed
                                alert('<?php _e("Generation failed:", "wp-tts-sesolibre"); ?> ' + errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            // Debug removed
                            alert('<?php _e("Error generating audio:", "wp-tts-sesolibre"); ?> ' + error);
                        },
                        complete: function() {
                            // Debug removed
                            $button.prop('disabled', false).html(originalText);
                        }
                    });
                } else {
                    const errorMsg = response.data?.message || response.message || '<?php _e("Error saving text", "wp-tts-sesolibre"); ?>';
                    // Debug removed
                    alert('<?php _e("Save failed:", "wp-tts-sesolibre"); ?>\n\n' + errorMsg);
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                // Debug removed
                alert('<?php _e("Error saving text", "wp-tts-sesolibre"); ?>');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Close modal on background click
    $(document).on('click', '.tts-modal-backdrop', function(e) {
        if (e.target === this) {
            $(this).remove();
        }
    });
});
</script>
<?php
/**
 * TTS SesoLibre Player Template
 * 
 * Template for the enhanced TTS player with intro, outro, and background music
 * 
 * @var int $post_id Current post ID
 * @var string $main_audio_url Main TTS audio URL
 * @var array $audio_assets Audio assets (intro, outro, background, custom)
 * @var array $player_config Player configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get TTS data
$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post_id);
$audio_assets = $tts_data['audio_assets'] ?? [];

// Get voice information
$voice_config = $tts_data['voice'] ?? [];
$provider = $voice_config['provider'] ?? '';
$voice_id = $voice_config['voice_id'] ?? '';

// Get audio URLs
$intro_url = '';
$background_url = '';
$outro_url = '';

if (!empty($audio_assets['intro_audio'])) {
    $intro_url = wp_get_attachment_url($audio_assets['intro_audio']);
}

if (!empty($audio_assets['background_audio'])) {
    $background_url = wp_get_attachment_url($audio_assets['background_audio']);
}

if (!empty($audio_assets['outro_audio'])) {
    $outro_url = wp_get_attachment_url($audio_assets['outro_audio']);
}

// Check if we should use custom audio instead
$use_custom_audio = !empty($audio_assets['custom_audio']);
if ($use_custom_audio) {
    $main_audio_url = wp_get_attachment_url($audio_assets['custom_audio']);
}

// Player configuration
$background_volume = $audio_assets['background_volume'] ?? 0.3;
$show_voice_volume = $player_config['show_voice_volume'] ?? true;
$show_background_volume = $player_config['show_background_volume'] ?? true;

// Don't show player if no main audio
if (empty($main_audio_url)) {
    return;
}
?>

<div class="tts-sesolibre-player" 
     data-main-audio="<?php echo esc_attr($main_audio_url); ?>"
     data-intro-audio="<?php echo esc_attr($intro_url); ?>"
     data-background-audio="<?php echo esc_attr($background_url); ?>"
     data-outro-audio="<?php echo esc_attr($outro_url); ?>"
     data-background-volume="<?php echo esc_attr($background_volume); ?>"
     data-show-voice-volume="<?php echo esc_attr($show_voice_volume ? 'true' : 'false'); ?>"
     data-show-background-volume="<?php echo esc_attr($show_background_volume ? 'true' : 'false'); ?>">
     
    <div class="tts-branding">
        SesoLibre Player
    </div>
    
    <div class="tts-controls">
        <div class="tts-main-controls">
            <button class="tts-play-pause" type="button" aria-label="<?php esc_attr_e('Play/Pause', 'TTS SesoLibre'); ?>">
                ▶
            </button>
            
            <div class="tts-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="tts-progress-bar"></div>
            </div>
            
            <div class="tts-time-display">
                <span class="tts-current-time">0:00</span> / <span class="tts-total-time">0:00</span>
            </div>
        </div>
        
        <?php if ($show_voice_volume || ($show_background_volume && !empty($background_url))): ?>
        <div class="tts-volume-controls enabled">
            <?php if ($show_voice_volume): ?>
            <div class="tts-volume-control">
                <label for="tts-voice-volume-<?php echo $post_id; ?>">
                    <?php _e('Voice', 'TTS SesoLibre'); ?>
                </label>
                <input type="range" 
                       id="tts-voice-volume-<?php echo $post_id; ?>"
                       class="tts-volume-slider tts-voice-slider" 
                       min="0" max="1" step="0.1" value="1"
                       aria-label="<?php esc_attr_e('Voice Volume', 'TTS SesoLibre'); ?>">
            </div>
            <?php endif; ?>
            
            <?php if ($show_background_volume && !empty($background_url)): ?>
            <div class="tts-volume-control">
                <label for="tts-background-volume-<?php echo $post_id; ?>">
                    <?php _e('Music', 'TTS SesoLibre'); ?>
                </label>
                <input type="range" 
                       id="tts-background-volume-<?php echo $post_id; ?>"
                       class="tts-volume-slider tts-background-slider" 
                       min="0" max="1" step="0.1" 
                       value="<?php echo esc_attr($background_volume); ?>"
                       aria-label="<?php esc_attr_e('Background Music Volume', 'TTS SesoLibre'); ?>">
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="tts-error-container"></div>
</div>

<?php if ($use_custom_audio): ?>
<p class="tts-custom-audio-notice" style="font-size: 12px; color: #666; margin-top: 10px; font-style: italic;">
    <?php _e('Playing custom audio file', 'TTS SesoLibre'); ?>
</p>
<?php endif; ?>
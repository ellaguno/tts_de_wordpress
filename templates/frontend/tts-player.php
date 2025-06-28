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

// Get human-readable provider and voice names
$provider_names = [
    'azure' => 'Azure TTS',
    'google' => 'Google Cloud TTS', 
    'polly' => 'Amazon Polly',
    'elevenlabs' => 'ElevenLabs',
    'openai' => 'OpenAI TTS'
];
$provider_display = $provider_names[$provider] ?? ucfirst($provider);

// Get post title for display
$post_title = get_the_title($post_id);

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
$show_tts_service = $player_config['show_tts_service'] ?? true;
$show_voice_name = $player_config['show_voice_name'] ?? true;
$show_download_link = $player_config['show_download_link'] ?? true;
$show_article_title = $player_config['show_article_title'] ?? true;

// Don't show player if no main audio
if (empty($main_audio_url)) {
    return;
}

// Get custom colors from ConfigurationManager
$config = new \WP_TTS\Core\ConfigurationManager();
$play_icon_color = $config->get('player.play_icon_color', '#007cba');
$pause_icon_color = $config->get('player.pause_icon_color', '#007cba');
$progress_color = $config->get('player.progress_color', '#007cba');
$background_color = $config->get('player.player_background_color', '#f8f9fa');
$text_color = $config->get('player.player_text_color', '#333333');

// Generate unique player ID
$player_id = 'tts-sesolibre-player-' . $post_id;
?>

<style>
/* SesoLibre Player Custom Colors */
#<?php echo $player_id; ?> {
    background: <?php echo esc_html($background_color); ?>;
    color: <?php echo esc_html($text_color); ?>;
}

#<?php echo $player_id; ?> .tts-play-pause {
    background: <?php echo esc_html($play_icon_color); ?>;
}

#<?php echo $player_id; ?> .tts-play-pause:hover {
    background: <?php echo esc_html($pause_icon_color); ?>;
}

#<?php echo $player_id; ?> .tts-progress-bar {
    background: <?php echo esc_html($progress_color); ?>;
}

#<?php echo $player_id; ?> .tts-speed-btn {
    background: <?php echo esc_html($progress_color); ?>;
}

#<?php echo $player_id; ?> .tts-speed-btn:hover {
    background: <?php echo esc_html($pause_icon_color); ?>;
}

#<?php echo $player_id; ?> .tts-speed-menu button.active {
    background: <?php echo esc_html($progress_color); ?>;
}

#<?php echo $player_id; ?> .tts-article-title-above {
    color: <?php echo esc_html($text_color); ?>;
}
</style>

<div class="tts-sesolibre-player" 
     id="<?php echo $player_id; ?>" 
     data-main-audio="<?php echo esc_attr($main_audio_url); ?>"
     data-intro-audio="<?php echo esc_attr($intro_url); ?>"
     data-background-audio="<?php echo esc_attr($background_url); ?>"
     data-outro-audio="<?php echo esc_attr($outro_url); ?>"
     data-background-volume="<?php echo esc_attr($background_volume); ?>"
     data-show-voice-volume="<?php echo esc_attr($show_voice_volume ? 'true' : 'false'); ?>"
     data-show-background-volume="<?php echo esc_attr($show_background_volume ? 'true' : 'false'); ?>">
     
    <div class="tts-branding">
        <?php _e('Reproductor SesoLibre', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?> 
    </div> 
    
    <div class="tts-controls">
        <?php if ($show_article_title && !empty($post_title)): ?>
        <div class="tts-article-title-above">
            <?php echo esc_html($post_title); ?>
        </div>
        <?php endif; ?>
        
        <div class="tts-main-controls">
            <button class="tts-play-pause" style="border-radius: 50%;" type="button" aria-label="<?php esc_attr_e('Reproducir/Pausar', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                ▶
            </button>
            
            <div class="tts-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="tts-progress-bar"></div>
            </div>
            
            <div class="tts-time-display">
                <span class="tts-current-time">0:00</span> / <span class="tts-total-time">0:00</span>
            </div>
            
            <?php if ($player_config['show_speed_control'] ?? true): ?>
            <div class="tts-speed-control">
                <button class="tts-speed-btn" type="button" aria-label="<?php esc_attr_e('Control de velocidad', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M13,2.05V5.08C16.39,5.57 19,8.47 19,12C19,12.9 18.82,13.75 18.5,14.54L21.12,16.07C21.68,14.83 22,13.45 22,12C22,6.82 18.05,2.55 13,2.05M12,19A7,7 0 0,1 5,12C5,8.47 7.61,5.57 11,5.08V2.05C5.94,2.55 2,6.81 2,12A10,10 0 0,0 12,22C15.3,22 18.23,20.39 20.09,17.93L17.97,16.54C16.64,18.34 14.47,19.5 12,19.5M8,8V16L16,12L8,8Z"></path>
                    </svg>
                </button>
                <div class="tts-speed-menu" style="display: none;">
                    <button data-speed="0.5">0.5x</button>
                    <button data-speed="0.75">0.75x</button>
                    <button data-speed="1" class="active">1x</button>
                    <button data-speed="1.25">1.25x</button>
                    <button data-speed="1.5">1.5x</button>
                    <button data-speed="2">2x</button>
                </div>
            </div>
            <?php endif; ?>

        
            <?php if ($show_voice_volume || ($show_background_volume && !empty($background_url))): ?>
            <div class="tts-volume-controls enabled">
                <?php if ($show_voice_volume): ?>
                <div class="tts-volume-control">
                    <label for="tts-voice-volume-<?php echo $post_id; ?>">
                        <?php _e('Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>
                    </label>
                    <input type="range" 
                           id="tts-voice-volume-<?php echo $post_id; ?>"
                           class="tts-volume-slider tts-voice-slider" 
                           min="0" max="1" step="0.1" value="1"
                           aria-label="<?php esc_attr_e('Volumen de Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                </div>
                <?php endif; ?>
                
                <?php if ($show_background_volume && !empty($background_url)): ?>
                <div class="tts-volume-control">
                    <label for="tts-background-volume-<?php echo $post_id; ?>">
                        <?php _e('Música', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>
                    </label>
                    <input type="range" 
                           id="tts-background-volume-<?php echo $post_id; ?>"
                           class="tts-volume-slider tts-background-slider" 
                           min="0" max="1" step="0.1" 
                           value="<?php echo esc_attr($background_volume); ?>"
                           aria-label="<?php esc_attr_e('Volumen de Música de Fondo', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                </div>
                <?php endif; ?>

        </div>
        <?php endif; ?>
        </div> 

    </div>
    
    <?php 
    // Show player information if any option is enabled
    if ($show_tts_service || $show_voice_name || $show_download_link || $show_article_title): ?>
    <div class="tts-info-bar">
        <?php if ($show_tts_service && !empty($provider_display)): ?>
            <span class="tts-info-item tts-service">
                <strong><?php _e('Servicio:', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?></strong> <?php echo esc_html($provider_display); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($show_voice_name && !empty($voice_id)): ?>
            <span class="tts-info-item tts-voice">
                <strong><?php _e('Voz:', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?></strong> <?php echo esc_html($voice_id); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($show_download_link): ?>
            <span class="tts-info-item tts-download">
                <a href="<?php echo esc_url($main_audio_url); ?>" download class="tts-download-link">
                    <span class="tts-headphones">🎧</span>
                    <?php _e('Descargar', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>
                </a>
            </span>
        <?php endif; ?>
        
    </div>
    <?php endif; ?>
    
    <div class="tts-error-container"></div>
</div>

<?php if ($use_custom_audio): ?>
<p class="tts-custom-audio-notice" style="font-size: 12px; color: #666; margin-top: 10px; font-style: italic;">
    <?php _e('Reproduciendo archivo de audio personalizado', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>
</p>
<?php endif; ?>
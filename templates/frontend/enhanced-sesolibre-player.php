<?php
/**
 * Enhanced TTS SesoLibre Player Template
 * 
 * Enhanced version of the SesoLibre player with featured image support and CSS customization
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

// Get post title and featured image
$post_title = get_the_title($post_id);
$featured_image_id = get_post_thumbnail_id($post_id);
$featured_image_url = '';
if ($featured_image_id) {
    $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'medium');
}

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
$show_featured_image = $player_config['show_featured_image'] ?? true;

// Get custom CSS settings from player config
$config = new \WP_TTS\Core\ConfigurationManager();
$custom_css = $config->get('player.enhanced_player_css', '');
$play_icon_color = $config->get('player.play_icon_color', '#007cba');
$pause_icon_color = $config->get('player.pause_icon_color', '#007cba');
$progress_color = $config->get('player.progress_color', '#007cba');
$background_color = $config->get('player.player_background_color', '#f8f9fa');
$text_color = $config->get('player.player_text_color', '#333333');

// Don't show player if no main audio
if (empty($main_audio_url)) {
    return;
}

// Generate unique player ID
$player_id = 'tts-enhanced-sesolibre-player-' . $post_id;
?>

<style>
/* Enhanced SesoLibre Player Custom Styles */
#<?php echo $player_id; ?> {
    --play-icon-color: <?php echo esc_html($play_icon_color); ?>;
    --pause-icon-color: <?php echo esc_html($pause_icon_color); ?>;
    --progress-color: <?php echo esc_html($progress_color); ?>;
    --background-color: <?php echo esc_html($background_color); ?>;
    --text-color: <?php echo esc_html($text_color); ?>;
}

<?php if (!empty($custom_css)): ?>
/* Custom CSS from admin settings */
<?php echo $custom_css; ?>
<?php endif; ?>
</style>

<div class="tts-enhanced-sesolibre-player" 
     id="<?php echo $player_id; ?>"
     data-main-audio="<?php echo esc_attr($main_audio_url); ?>"
     data-intro-audio="<?php echo esc_attr($intro_url); ?>"
     data-background-audio="<?php echo esc_attr($background_url); ?>"
     data-outro-audio="<?php echo esc_attr($outro_url); ?>"
     data-background-volume="<?php echo esc_attr($background_volume); ?>"
     data-show-voice-volume="<?php echo esc_attr($show_voice_volume ? 'true' : 'false'); ?>"
     data-show-background-volume="<?php echo esc_attr($show_background_volume ? 'true' : 'false'); ?>">
     
    <!-- Compact Player Layout -->
    <div class="tts-enhanced-compact-controls">
        <!-- Left: Featured Image (if available) -->
        <?php if ($show_featured_image && $featured_image_url): ?>
        <div class="tts-featured-image-compact">
            <img src="<?php echo esc_url($featured_image_url); ?>" 
                 alt="<?php echo esc_attr($post_title); ?>" 
                 loading="lazy">
        </div>
        <?php endif; ?>
        
        <!-- Center: Play button and Progress -->
        <div class="tts-main-controls-compact">
            <button class="tts-play-pause" type="button" aria-label="<?php esc_attr_e('Reproducir/Pausar', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                <svg class="play-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 5v14l11-7z"></path>
                </svg>
                <svg class="pause-icon" viewBox="0 0 24 24" fill="currentColor" style="display: none;">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path>
                </svg>
            </button>
            
            <div class="tts-progress-area">
                <?php if ($show_article_title && !empty($post_title)): ?>
                <div class="tts-article-title-above">
                    <?php echo esc_html($post_title); ?>
                </div>
                <?php endif; ?>
                
                <div class="tts-progress-container">
                    <div class="tts-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="tts-progress-bar"></div>
                        <div class="tts-progress-handle"></div>
                    </div>
                </div>
                
                <div class="tts-time-display">
                    <span class="tts-current-time">0:00</span>
                    <span class="tts-separator">/</span>
                    <span class="tts-total-time">0:00</span>
                </div>
            </div>
        </div>
        
        <!-- Right: Speed Control and Volume Controls -->
        <div class="tts-right-controls">
            <?php if ($player_config['show_speed_control'] ?? true): ?>
            <div class="tts-speed-control-compact">
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
            <div class="tts-volume-controls-compact">
                <?php if ($show_voice_volume): ?>
                <div class="tts-volume-control-compact">
                    <svg class="volume-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"></path>
                    </svg>
                    <input type="range" 
                           id="tts-voice-volume-<?php echo $post_id; ?>"
                           class="tts-volume-slider-compact tts-voice-slider" 
                           min="0" max="1" step="0.1" value="1"
                           aria-label="<?php esc_attr_e('Volumen de Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                </div>
                <?php endif; ?>
                
                <?php if ($show_background_volume && !empty($background_url)): ?>
                <div class="tts-volume-control-compact">
                    <svg class="volume-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"></path>
                    </svg>
                    <input type="range" 
                           id="tts-background-volume-<?php echo $post_id; ?>"
                           class="tts-volume-slider-compact tts-background-slider" 
                           min="0" max="1" step="0.1" 
                           value="<?php echo esc_attr($background_volume); ?>"
                           aria-label="<?php esc_attr_e('Volumen de Música de Fondo', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Compact Info Bar -->
    <?php if ($show_tts_service || $show_voice_name || $show_download_link): ?>
    <div class="tts-compact-info-bar">
        <?php if ($show_tts_service && !empty($provider_display)): ?>
            <span class="tts-info-compact"><?php echo esc_html($provider_display); ?></span>
            <?php if ($show_voice_name || $show_download_link): ?><span class="tts-info-separator">•</span><?php endif; ?>
        <?php endif; ?>
        
        <?php if ($show_voice_name && !empty($voice_id)): ?>
            <span class="tts-info-compact"><?php echo esc_html($voice_id); ?></span>
            <?php if ($show_download_link): ?><span class="tts-info-separator">•</span><?php endif; ?>
        <?php endif; ?>
        
        <?php if ($show_download_link): ?>
            <a href="<?php echo esc_url($main_audio_url); ?>" download class="tts-download-compact">
                <span class="tts-headphones">🎧</span>
                <?php _e('Descargar', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Status and Error Display -->
    <div class="tts-status-container">
        <div class="tts-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <span><?php _e('Cargando audio...', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?></span>
        </div>
        <div class="tts-error-container"></div>
    </div>
</div>

<?php if ($use_custom_audio): ?>
<p class="tts-custom-audio-notice">
    <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
    </svg>
    <?php _e('Reproduciendo archivo de audio personalizado', 'TTS-SesoLibre-v1.6.7-shortcode-docs'); ?>
</p>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const playerId = '<?php echo $player_id; ?>';
    const playerContainer = document.getElementById(playerId);
    
    if (playerContainer && !playerContainer.classList.contains('initialized')) {
        new TTSEnhancedSesoLibrePlayer(playerContainer);
        playerContainer.classList.add('initialized');
    }
});
</script>
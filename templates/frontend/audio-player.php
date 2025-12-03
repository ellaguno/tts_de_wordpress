<?php
/**
 * Frontend Audio Player Template
 * 
 * Template for displaying the TTS audio player on posts/pages
 * 
 * @var int $post_id Post ID
 * @var string $audio_url Audio file URL
 * @var string $style Player style
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local scope

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get variables if not already set
if (!isset($post_id)) {
    $post_id = get_the_ID();
}

if (!isset($audio_url)) {
    if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
        $audio_url = \WP_TTS\Utils\TTSMetaManager::getAudioUrl($post_id);
    } else {
        // Fallback to old system
        $audio_url = get_post_meta($post_id, '_tts_audio_url', true);
    }
}

if (!isset($style)) {
    $style = 'default';
}

// Don't display if no audio URL
if (empty($audio_url)) {
    return;
}

// Get post title for accessibility
$post_title = get_the_title($post_id);

// Get TTS data with fallback
if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
    $tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post_id);
    $provider = $tts_data['voice']['provider'];
    $voice_id = $tts_data['voice']['voice_id'];
    $generated_at = $tts_data['audio']['generated_at'];
} else {
    // Fallback to old system
    $provider = get_post_meta($post_id, '_tts_voice_provider', true);
    $voice_id = get_post_meta($post_id, '_tts_voice_id', true);
    $generated_at = get_post_meta($post_id, '_tts_generated_at', true);
    if ($generated_at && is_numeric($generated_at)) {
        $generated_at = gmdate('Y-m-d H:i:s', $generated_at);
    }
}

?>

<div class="wp-tts-audio-player wp-tts-style-<?php echo esc_attr($style); ?>" id="wp-tts-player-<?php echo esc_attr($post_id); ?>">

    <div class="wp-tts-player-controls">
        <span class="wp-tts-icon">🎧</span>
        <audio 
            controls 
            preload="none"
            crossorigin="anonymous"
            aria-label="<?php
				/* translators: %s: post title */
				echo esc_attr(sprintf(__('Versión en audio de: %s', 'tts-sesolibre'), $post_title)); ?>"
            class="wp-tts-audio-element">
            <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
            <p><?php esc_html_e('Tu navegador no soporta el elemento de audio.', 'tts-sesolibre'); ?></p>
        </audio>
        
        <?php if ($player_config['show_speed_control'] ?? true): ?>
        <div class="wp-tts-speed-control">
            <button class="wp-tts-speed-btn" type="button" aria-label="<?php esc_attr_e('Control de velocidad', 'tts-sesolibre'); ?>">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13,2.05V5.08C16.39,5.57 19,8.47 19,12C19,12.9 18.82,13.75 18.5,14.54L21.12,16.07C21.68,14.83 22,13.45 22,12C22,6.82 18.05,2.55 13,2.05M12,19A7,7 0 0,1 5,12C5,8.47 7.61,5.57 11,5.08V2.05C5.94,2.55 2,6.81 2,12A10,10 0 0,0 12,22C15.3,22 18.23,20.39 20.09,17.93L17.97,16.54C16.64,18.34 14.47,19.5 12,19.5M8,8V16L16,12L8,8Z"></path>
                </svg>
            </button>
            <div class="wp-tts-speed-menu" style="display: none;">
                <button data-speed="0.5">0.5x</button>
                <button data-speed="0.75">0.75x</button>
                <button data-speed="1" class="active">1x</button>
                <button data-speed="1.25">1.25x</button>
                <button data-speed="1.5">1.5x</button>
                <button data-speed="2">2x</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="wp-tts-player-meta">
        <span class="wp-tts-label"><?php esc_html_e('Escucha el artículo', 'tts-sesolibre'); ?></span>
        <?php if ($voice_id || $provider): ?>
            <span class="wp-tts-voice-info">
                <?php 
                if ($voice_id) {
                    /* translators: %s: voice name */
                    echo esc_html( sprintf( __('Voz: %s', 'tts-sesolibre'), $voice_id ) );
                    if ($provider) {
                        echo ' (' . esc_html(ucfirst(str_replace('_', ' ', $provider))) . ')';
                    }
                } else if ($provider) {
                    /* translators: %s: provider name */
                    echo esc_html( sprintf( __('Proveedor: %s', 'tts-sesolibre'), ucfirst(str_replace('_', ' ', $provider)) ) );
                }
                ?>
            </span>
        <?php endif; ?>
        <span class="wp-tts-download">
        <a href="<?php echo esc_url($audio_url); ?>" download class="wp-tts-download-link">
            <span class="wp-tts-headphones">🎧</span>
            <?php esc_html_e('Descargar', 'tts-sesolibre'); ?>
            </a>
        </span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const audioPlayer = document.getElementById('wp-tts-player-<?php echo esc_js($post_id); ?>');
    const audioElement = audioPlayer.querySelector('.wp-tts-audio-element');
    
    if (audioElement) {
        // Track audio events for analytics (optional)
        audioElement.addEventListener('play', function() {
            // You can add analytics tracking here
            // Debug removed
        });
        
        audioElement.addEventListener('ended', function() {
            // You can add analytics tracking here
            // Debug removed
        });
        
        // Add loading state
        audioElement.addEventListener('loadstart', function() {
            audioPlayer.classList.add('wp-tts-loading');
        });
        
        audioElement.addEventListener('canplay', function() {
            audioPlayer.classList.remove('wp-tts-loading');
        });
        
        // Error handling
        audioElement.addEventListener('error', function() {
            audioPlayer.classList.add('wp-tts-error');
            const errorMsg = document.createElement('p');
            errorMsg.textContent = '<?php echo esc_js(__('Error cargando el audio. Por favor intenta descargar el archivo.', 'tts-sesolibre')); ?>';
            errorMsg.style.color = '#d63638';
            errorMsg.style.fontSize = '12px';
            errorMsg.style.marginTop = '8px';
            audioElement.parentNode.appendChild(errorMsg);
        });
    }
});
</script>

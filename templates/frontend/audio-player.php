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
        $generated_at = date('Y-m-d H:i:s', $generated_at);
    }
}

?>

<div class="wp-tts-audio-player wp-tts-style-<?php echo esc_attr($style); ?>" id="wp-tts-player-<?php echo esc_attr($post_id); ?>">

    <div class="wp-tts-player-controls">
        <span class="wp-tts-icon">🎧</span>
        <audio 
            controls 
            preload="none"
            aria-label="<?php echo esc_attr(sprintf(__('Audio versión of: %s', 'TTS de Wordpress'), $post_title)); ?>"
            class="wp-tts-audio-element">
            <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
            <p><?php _e('Your browser does not support the audio element.', 'TTS de Wordpress'); ?></p>
        </audio>
    </div>
    
    <div class="wp-tts-player-meta">
        <span class="wp-tts-label"><?php _e('Escucha el artículo', 'TTS de SesoLibre'); ?></span>
        <?php if ($voice_id || $provider): ?>
            <span class="wp-tts-voice-info">
                <?php 
                if ($voice_id) {
                    echo sprintf(__('Voice: %s', 'TTS de Wordpress'), esc_html($voice_id));
                    if ($provider) {
                        echo ' (' . esc_html(ucfirst(str_replace('_', ' ', $provider))) . ')';
                    }
                } else if ($provider) {
                    echo sprintf(__('Provider: %s', 'TTS de Wordpress'), esc_html(ucfirst(str_replace('_', ' ', $provider))));
                }
                ?>
            </span>
        <?php endif; ?>
        <span class="wp-tts-download">
        <a href="<?php echo esc_url($audio_url); ?>" download class="wp-tts-download-link">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Descargar', 'TTS de Wordpress'); ?>
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
            console.log('TTS Audio started playing');
        });
        
        audioElement.addEventListener('ended', function() {
            // You can add analytics tracking here
            console.log('TTS Audio finished playing');
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
            errorMsg.textContent = '<?php echo esc_js(__('Error loading audio. Please try downloading the file.', 'TTS de Wordpress')); ?>';
            errorMsg.style.color = '#d63638';
            errorMsg.style.fontSize = '12px';
            errorMsg.style.marginTop = '8px';
            audioElement.parentNode.appendChild(errorMsg);
        });
    }
});
</script>

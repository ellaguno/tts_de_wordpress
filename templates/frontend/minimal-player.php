<?php
/**
 * Minimal TTS Player Template
 * Clean, minimalist audio player with waveform visualization
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local scope

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Use the main audio URL passed from Plugin.php
$audio_url = esc_url($main_audio_url);
$player_id = 'wp-tts-minimal-player-' . $post_id;

// Get voice information from TTS data
$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post_id);
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
$service_name = $provider_names[$provider] ?? ucfirst($provider);
$voice_name = $voice_id;

$download_link = $audio_url;

// Get custom colors from ConfigurationManager
$config = new \WP_TTS\Core\ConfigurationManager();
$play_icon_color = $config->get('player.play_icon_color', '#007cba');
$pause_icon_color = $config->get('player.pause_icon_color', '#007cba');
$progress_color = $config->get('player.progress_color', '#007cba');
$background_color = $config->get('player.player_background_color', '#f8f9fa');
$text_color = $config->get('player.player_text_color', '#333333');
?>

<style>
/* Minimal Player Custom Colors */
#<?php echo esc_attr( $player_id ); ?> {
    background: <?php echo esc_html($background_color); ?>;
    color: <?php echo esc_html($text_color); ?>;
}

#<?php echo esc_attr( $player_id ); ?> .wp-tts-minimal-play-btn {
    background: <?php echo esc_html($play_icon_color); ?>;
}

#<?php echo esc_attr( $player_id ); ?> .wp-tts-minimal-play-btn:hover {
    background: <?php echo esc_html($pause_icon_color); ?>;
}

#<?php echo esc_attr( $player_id ); ?> .progress-filled {
    background: <?php echo esc_html($progress_color); ?>;
}

#<?php echo esc_attr( $player_id ); ?> .waveform-bar.active,
#<?php echo esc_attr( $player_id ); ?> .waveform-bar.playing {
    background: <?php echo esc_html($progress_color); ?>;
}
</style>

<div class="wp-tts-minimal-player-container" id="<?php echo esc_attr( $player_id ); ?>">
    <div class="wp-tts-minimal-player">
        <!-- Play/Pause Button -->
        <button class="wp-tts-minimal-play-btn" type="button" aria-label="Play/Pause">
            <svg class="play-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5v14l11-7z"></path>
            </svg>
            <svg class="pause-icon" viewBox="0 0 24 24" fill="currentColor" style="display: none;">
                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path>
            </svg>
        </button>

        <!-- Time Display -->
        <div class="wp-tts-minimal-time">
            <span class="current-time">0:00</span>
        </div>

        <!-- Waveform/Progress Container -->
        <div class="wp-tts-minimal-progress-container">
            <div class="wp-tts-minimal-waveform">
                <!-- Waveform bars - Enhanced with 60 bars for better visualization -->
                <div class="waveform-bar" style="height: 20%"></div>
                <div class="waveform-bar" style="height: 35%"></div>
                <div class="waveform-bar" style="height: 60%"></div>
                <div class="waveform-bar" style="height: 80%"></div>
                <div class="waveform-bar" style="height: 45%"></div>
                <div class="waveform-bar" style="height: 70%"></div>
                <div class="waveform-bar" style="height: 90%"></div>
                <div class="waveform-bar" style="height: 55%"></div>
                <div class="waveform-bar" style="height: 30%"></div>
                <div class="waveform-bar" style="height: 75%"></div>
                <div class="waveform-bar" style="height: 40%"></div>
                <div class="waveform-bar" style="height: 85%"></div>
                <div class="waveform-bar" style="height: 25%"></div>
                <div class="waveform-bar" style="height: 65%"></div>
                <div class="waveform-bar" style="height: 50%"></div>
                <div class="waveform-bar" style="height: 95%"></div>
                <div class="waveform-bar" style="height: 35%"></div>
                <div class="waveform-bar" style="height: 70%"></div>
                <div class="waveform-bar" style="height: 45%"></div>
                <div class="waveform-bar" style="height: 80%"></div>
                <div class="waveform-bar" style="height: 20%"></div>
                <div class="waveform-bar" style="height: 55%"></div>
                <div class="waveform-bar" style="height: 75%"></div>
                <div class="waveform-bar" style="height: 40%"></div>
                <div class="waveform-bar" style="height: 90%"></div>
                <div class="waveform-bar" style="height: 30%"></div>
                <div class="waveform-bar" style="height: 65%"></div>
                <div class="waveform-bar" style="height: 85%"></div>
                <div class="waveform-bar" style="height: 50%"></div>
                <div class="waveform-bar" style="height: 75%"></div>
                <!-- Additional bars for better waveform representation -->
                <div class="waveform-bar" style="height: 42%"></div>
                <div class="waveform-bar" style="height: 68%"></div>
                <div class="waveform-bar" style="height: 38%"></div>
                <div class="waveform-bar" style="height: 82%"></div>
                <div class="waveform-bar" style="height: 28%"></div>
                <div class="waveform-bar" style="height: 72%"></div>
                <div class="waveform-bar" style="height: 58%"></div>
                <div class="waveform-bar" style="height: 33%"></div>
                <div class="waveform-bar" style="height: 77%"></div>
                <div class="waveform-bar" style="height: 48%"></div>
                <div class="waveform-bar" style="height: 63%"></div>
                <div class="waveform-bar" style="height: 87%"></div>
                <div class="waveform-bar" style="height: 22%"></div>
                <div class="waveform-bar" style="height: 52%"></div>
                <div class="waveform-bar" style="height: 78%"></div>
                <div class="waveform-bar" style="height: 37%"></div>
                <div class="waveform-bar" style="height: 92%"></div>
                <div class="waveform-bar" style="height: 26%"></div>
                <div class="waveform-bar" style="height: 61%"></div>
                <div class="waveform-bar" style="height: 84%"></div>
                <div class="waveform-bar" style="height: 46%"></div>
                <div class="waveform-bar" style="height: 71%"></div>
                <div class="waveform-bar" style="height: 34%"></div>
                <div class="waveform-bar" style="height: 79%"></div>
                <div class="waveform-bar" style="height: 43%"></div>
                <div class="waveform-bar" style="height: 66%"></div>
                <div class="waveform-bar" style="height: 89%"></div>
                <div class="waveform-bar" style="height: 31%"></div>
                <div class="waveform-bar" style="height: 56%"></div>
                <div class="waveform-bar" style="height: 81%"></div>
                <div class="waveform-bar" style="height: 39%"></div>
                <div class="waveform-bar" style="height: 74%"></div>
                <div class="waveform-bar" style="height: 49%"></div>
            </div>
            <div class="wp-tts-minimal-progress">
                <div class="progress-filled"></div>
            </div>
        </div>

        <!-- Duration Display -->
        <div class="wp-tts-minimal-duration">
            <span class="total-time">-0:00</span>
        </div>

        <!-- Settings Button -->
        <button class="wp-tts-minimal-settings-btn" type="button" aria-label="Settings">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"></path>
            </svg>
        </button>
    </div>

    <!-- Settings Panel -->
    <div class="wp-tts-minimal-settings-panel" style="display: none;">
        <div class="settings-content">
            <div class="setting-item">
                <label>Velocidad de reproducción:</label>
                <select class="playback-rate">
                    <option value="0.5">0.5x</option>
                    <option value="0.75">0.75x</option>
                    <option value="1" selected>1x</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2">2x</option>
                </select>
            </div>
            <?php if ($download_link): ?>
            <div class="setting-item">
                <a href="<?php echo esc_url( $download_link ); ?>" download class="download-link">
                    <span class="headphones-icon">🎧</span>
                    Descargar audio
                </a>
            </div>
            <?php endif; ?>
            <div class="setting-item">
                <div class="audio-info">
                    <div class="service-info"><?php echo esc_html( $service_name ); ?></div>
                    <div class="voice-info"><?php echo esc_html( $voice_name ); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Audio Element -->
    <audio class="wp-tts-audio" preload="none" crossorigin="anonymous">
        <source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
        <source src="<?php echo esc_url( $audio_url ); ?>" type="audio/ogg">
        Tu navegador no soporta el elemento de audio.
    </audio>

    <!-- Loading Indicator -->
    <div class="wp-tts-minimal-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <span>Cargando audio...</span>
    </div>

    <!-- Error Message -->
    <div class="wp-tts-minimal-error" style="display: none;">
        <span>Error al cargar el audio. <button class="retry-btn">Reintentar</button></span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const playerId = '<?php echo esc_attr( $player_id ); ?>';
    const playerContainer = document.getElementById(playerId);
    
    if (playerContainer && !playerContainer.classList.contains('initialized')) {
        new WPTTSMinimalPlayer(playerContainer);
        playerContainer.classList.add('initialized');
    }
});
</script>
/**
 * TTS SesoLibre Audio Player (variant of TTSPlayerCore)
 *
 * Presentation-only subclass: text play button (▶/⏸), single text phase
 * indicator. All playback/mixing/seek/speed logic lives in tts-player-core.js.
 */
class TTSSesoLibrePlayer extends TTSPlayerCore {
    constructor(playerElement) {
        super(playerElement, {
            label: 'sesolibre_player',
            selectors: {
                playBtn: '.tts-play-pause',
                progress: '.tts-progress',
                progressBar: '.tts-progress-bar',
                currentTime: '.tts-current-time',
                totalTime: '.tts-total-time',
                voiceSlider: '.tts-voice-slider',
                backgroundSlider: '.tts-background-slider',
                error: '.tts-error-container',
                speedBtn: '.tts-speed-btn',
                speedMenu: '.tts-speed-menu'
            }
        });
    }

    init() {
        this.phaseIndicator = this.container.querySelector('.tts-phase-indicator');
    }

    renderPlayState(isPlaying) {
        if (this.el.playBtn) {
            this.el.playBtn.innerHTML = isPlaying ? '⏸' : '▶';
        }
    }

    updatePhaseUI(phase) {
        if (!this.phaseIndicator) {
            return;
        }

        this.phaseIndicator.className = 'tts-phase-indicator';

        if (phase) {
            this.phaseIndicator.classList.add('active', phase);

            const phaseNames = {
                intro: 'Introducción',
                main: 'Audio Principal',
                outro: 'Cierre'
            };
            this.phaseIndicator.textContent = phaseNames[phase] || '';
        } else {
            this.phaseIndicator.textContent = '';
        }
    }

    showError(message) {
        this.hideLoading();
        if (this.el.error) {
            this.el.error.innerHTML = '<div class="tts-error-message"></div>';
            this.el.error.firstChild.textContent = message;
        } else {
            console.error('TTS Player Error:', message);
        }
    }

    hideError() {
        if (this.el.error) {
            this.el.error.innerHTML = '';
        }
    }
}

// Auto-initialization
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tts-sesolibre-player:not(.initialized)').forEach((playerElement) => {
        try {
            new TTSSesoLibrePlayer(playerElement);
            playerElement.classList.add('initialized');
        } catch (error) {
            console.error('Error initializing TTS player:', error);
        }
    });
});

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.TTSSesoLibrePlayer = TTSSesoLibrePlayer;
}

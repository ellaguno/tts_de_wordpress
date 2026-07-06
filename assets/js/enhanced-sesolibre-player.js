/**
 * Enhanced SesoLibre TTS Player (variant of TTSPlayerCore)
 *
 * Presentation-only subclass: SVG play button toggled via CSS, per-phase
 * labels, progress handle, volume percentage displays and extended keyboard
 * shortcuts. All playback/mixing/seek/speed logic lives in tts-player-core.js.
 */
class TTSEnhancedSesoLibrePlayer extends TTSPlayerCore {
    constructor(container) {
        super(container, {
            label: 'enhanced_sesolibre_player',
            selectors: {
                playBtn: '.tts-play-pause',
                progress: '.tts-progress',
                progressBar: '.tts-progress-bar',
                progressHandle: '.tts-progress-handle',
                currentTime: '.tts-current-time',
                totalTime: '.tts-total-time',
                voiceSlider: '.tts-voice-slider',
                backgroundSlider: '.tts-background-slider',
                loading: '.tts-loading',
                error: '.tts-error-container',
                speedBtn: '.tts-speed-btn',
                speedMenu: '.tts-speed-menu'
            }
        });
    }

    init() {
        this.phaseLabels = this.container.querySelectorAll('.phase-label');

        // Initial volume percentage displays
        if (this.el.voiceSlider) {
            this.updateVolumeDisplay(this.el.voiceSlider, 1);
        }
        if (this.el.backgroundSlider) {
            this.updateVolumeDisplay(this.el.backgroundSlider, this.backgroundVolume);
        }

        // Extended keyboard shortcuts when focus is inside the player
        // (Space is already handled by the core).
        this.container.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'SELECT') {
                return; // let sliders/selects keep their native arrow keys
            }
            if (e.target === this.el.progress) {
                return; // the progress slider has its own arrow handling
            }

            switch (e.code) {
                case 'ArrowLeft':
                    e.preventDefault();
                    this.seekToTime(Math.max(0, this.currentTime - 10));
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.seekToTime(Math.min(this.totalDuration, this.currentTime + 10));
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (this.el.voiceSlider) {
                        const up = Math.min(1, parseFloat(this.el.voiceSlider.value) + 0.1);
                        this.el.voiceSlider.value = up;
                        this.setVoiceVolume(up);
                    }
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (this.el.voiceSlider) {
                        const down = Math.max(0, parseFloat(this.el.voiceSlider.value) - 0.1);
                        this.el.voiceSlider.value = down;
                        this.setVoiceVolume(down);
                    }
                    break;
            }
        });
    }

    updatePhaseUI(phase) {
        this.phaseLabels.forEach((label) => label.classList.remove('active'));

        if (phase) {
            const currentLabel = this.container.querySelector(`.${phase}-phase`);
            if (currentLabel) {
                currentLabel.classList.add('active');
            }
        }
    }

    showError(message) {
        this.hideLoading();
        if (this.el.error) {
            this.el.error.textContent = message;
            this.el.error.classList.add('show');
        } else {
            console.error('TTS Player Error:', message);
        }
    }

    hideError() {
        if (this.el.error) {
            this.el.error.classList.remove('show');
        }
    }

    // Backwards-compatible public setters (used by external scripts)
    setVoiceVolumeFromAPI(volume) {
        if (this.el.voiceSlider) {
            this.el.voiceSlider.value = volume;
        }
        this.setVoiceVolume(volume);
    }

    setBackgroundVolumeFromAPI(volume) {
        if (this.el.backgroundSlider) {
            this.el.backgroundSlider.value = volume;
        }
        this.setBackgroundVolume(volume);
    }
}

// Auto-initialize players when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tts-enhanced-sesolibre-player:not(.initialized)').forEach((container) => {
        new TTSEnhancedSesoLibrePlayer(container);
        container.classList.add('initialized');
    });
});

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.TTSEnhancedSesoLibrePlayer = TTSEnhancedSesoLibrePlayer;
}

/**
 * Minimal TTS Player (variant of TTSPlayerCore)
 *
 * Presentation-only subclass: adopts the <audio> element already rendered in
 * the template (no intro/outro/background mixing), decorative waveform,
 * settings panel and retry button. All playback/seek/speed logic lives in
 * tts-player-core.js.
 */
class WPTTSMinimalPlayer extends TTSPlayerCore {
    constructor(container) {
        super(container, {
            label: 'minimal_player',
            adoptAudio: '.wp-tts-audio',
            selectors: {
                playBtn: '.wp-tts-minimal-play-btn',
                progress: '.wp-tts-minimal-progress-container',
                progressBar: '.progress-filled',
                currentTime: '.current-time',
                totalTime: '.total-time',
                loading: '.wp-tts-minimal-loading',
                error: '.wp-tts-minimal-error',
                playbackRateSelect: '.playback-rate',
                retryBtn: '.retry-btn'
            }
        });
    }

    init() {
        this.waveformBars = this.container.querySelectorAll('.waveform-bar');
        this.settingsBtn = this.container.querySelector('.wp-tts-minimal-settings-btn');
        this.settingsPanel = this.container.querySelector('.wp-tts-minimal-settings-panel');
        this.settingsOpen = false;

        // Stagger the waveform bar animations
        this.waveformBars.forEach((bar, index) => {
            bar.style.animationDelay = `${index * 0.1}s`;
        });

        // Extra loading signals from the in-DOM audio element
        if (this.audio.main) {
            this.audio.main.addEventListener('loadstart', () => this.showLoading());
            this.audio.main.addEventListener('playing', () => this.hideLoading());
        }

        // Settings panel toggle
        if (this.settingsBtn && this.settingsPanel) {
            this.settingsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleSettings();
            });

            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target)) {
                    this.closeSettings();
                }
            });
        }
    }

    onProgressRender(percentage) {
        if (!this.waveformBars.length) {
            return;
        }

        const activeIndex = Math.floor((percentage / 100) * this.waveformBars.length);

        this.waveformBars.forEach((bar, index) => {
            bar.classList.remove('active', 'playing');

            if (index < activeIndex) {
                bar.classList.add('active');
            } else if (index === activeIndex && this.isPlaying) {
                bar.classList.add('playing');
            }
        });
    }

    /**
     * The error element carries static translated text plus the retry button,
     * so unlike the core we only toggle visibility instead of replacing its
     * content (which would remove the button).
     */
    showError(message) {
        this.hideLoading();
        if (this.el.error) {
            this.el.error.style.display = 'block';
        } else {
            console.error('TTS Player Error:', message);
        }
        if (this.isPlaying) {
            this.pause();
        }
    }

    toggleSettings() {
        if (this.settingsOpen) {
            this.closeSettings();
        } else {
            this.openSettings();
        }
    }

    openSettings() {
        this.settingsPanel.style.display = 'block';
        this.settingsOpen = true;
        this.settingsBtn.style.background = '#e9ecef';
        this.settingsBtn.style.color = '#007cba';
        this.settingsBtn.setAttribute('aria-expanded', 'true');
    }

    closeSettings() {
        this.settingsPanel.style.display = 'none';
        this.settingsOpen = false;
        this.settingsBtn.style.background = '';
        this.settingsBtn.style.color = '';
        this.settingsBtn.setAttribute('aria-expanded', 'false');
    }
}

// Auto-initialize players when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.wp-tts-minimal-player-container:not(.initialized)').forEach((container) => {
        new WPTTSMinimalPlayer(container);
        container.classList.add('initialized');
    });
});

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.WPTTSMinimalPlayer = WPTTSMinimalPlayer;
}

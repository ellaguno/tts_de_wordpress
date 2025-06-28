/**
 * Minimal TTS Player JavaScript
 * Clean, minimalist audio player with waveform visualization
 */

class WPTTSMinimalPlayer {
    constructor(container) {
        this.container = container;
        this.audio = container.querySelector('.wp-tts-audio');
        this.playBtn = container.querySelector('.wp-tts-minimal-play-btn');
        this.currentTimeEl = container.querySelector('.current-time');
        this.totalTimeEl = container.querySelector('.total-time');
        this.progressContainer = container.querySelector('.wp-tts-minimal-progress-container');
        this.progressFilled = container.querySelector('.progress-filled');
        this.waveformBars = container.querySelectorAll('.waveform-bar');
        this.settingsBtn = container.querySelector('.wp-tts-minimal-settings-btn');
        this.settingsPanel = container.querySelector('.wp-tts-minimal-settings-panel');
        this.playbackRateSelect = container.querySelector('.playback-rate');
        this.loadingEl = container.querySelector('.wp-tts-minimal-loading');
        this.errorEl = container.querySelector('.wp-tts-minimal-error');
        this.retryBtn = container.querySelector('.retry-btn');

        this.isPlaying = false;
        this.isLoading = false;
        this.currentTime = 0;
        this.duration = 0;
        this.settingsOpen = false;

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupAudioEventListeners();
        this.initializeWaveform();
    }

    setupEventListeners() {
        // Play/Pause button
        this.playBtn.addEventListener('click', () => this.togglePlay());

        // Progress container click for seeking
        this.progressContainer.addEventListener('click', (e) => this.seek(e));

        // Settings button
        this.settingsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleSettings();
        });

        // Playback rate change
        if (this.playbackRateSelect) {
            this.playbackRateSelect.addEventListener('change', (e) => {
                this.audio.playbackRate = parseFloat(e.target.value);
            });
        }

        // Retry button
        if (this.retryBtn) {
            this.retryBtn.addEventListener('click', () => this.retry());
        }

        // Close settings when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.closeSettings();
            }
        });

        // Keyboard shortcuts
        this.container.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                e.preventDefault();
                this.togglePlay();
            }
        });
    }

    setupAudioEventListeners() {
        this.audio.addEventListener('loadstart', () => this.showLoading());
        this.audio.addEventListener('canplay', () => this.hideLoading());
        this.audio.addEventListener('loadedmetadata', () => this.updateDuration());
        this.audio.addEventListener('timeupdate', () => this.updateProgress());
        this.audio.addEventListener('ended', () => this.onEnded());
        this.audio.addEventListener('error', () => this.showError());
        this.audio.addEventListener('play', () => this.onPlay());
        this.audio.addEventListener('pause', () => this.onPause());
        this.audio.addEventListener('waiting', () => this.showLoading());
        this.audio.addEventListener('playing', () => this.hideLoading());
    }

    initializeWaveform() {
        // Set initial waveform state
        this.waveformBars.forEach((bar, index) => {
            bar.style.animationDelay = `${index * 0.1}s`;
        });
    }

    togglePlay() {
        if (this.isLoading) return;

        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }

    async play() {
        try {
            this.hideError();
            await this.audio.play();
        } catch (error) {
            console.error('Error playing audio:', error);
            this.showError();
        }
    }

    pause() {
        this.audio.pause();
    }

    seek(e) {
        if (!this.duration) return;

        const rect = this.progressContainer.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percentage = clickX / rect.width;
        const newTime = percentage * this.duration;

        this.audio.currentTime = Math.max(0, Math.min(newTime, this.duration));
    }

    onPlay() {
        this.isPlaying = true;
        this.playBtn.classList.add('playing');
        this.startWaveformAnimation();
        this.trackAnalytics('play');
    }

    onPause() {
        this.isPlaying = false;
        this.playBtn.classList.remove('playing');
        this.stopWaveformAnimation();
        this.trackAnalytics('pause');
    }

    onEnded() {
        this.isPlaying = false;
        this.playBtn.classList.remove('playing');
        this.stopWaveformAnimation();
        this.audio.currentTime = 0;
        this.updateProgress();
        this.trackAnalytics('ended');
    }

    updateDuration() {
        this.duration = this.audio.duration;
        this.totalTimeEl.textContent = this.formatTime(this.duration);
    }

    updateProgress() {
        this.currentTime = this.audio.currentTime;
        this.currentTimeEl.textContent = this.formatTime(this.currentTime);
        
        if (this.duration) {
            const percentage = (this.currentTime / this.duration) * 100;
            this.progressFilled.style.width = `${percentage}%`;
            this.updateWaveformProgress(percentage);
        }
    }

    updateWaveformProgress(percentage) {
        const activeIndex = Math.floor((percentage / 100) * this.waveformBars.length);
        
        this.waveformBars.forEach((bar, index) => {
            bar.classList.remove('active', 'playing');
            
            if (index < activeIndex) {
                bar.classList.add('active');
            } else if (index === activeIndex && this.isPlaying) {
                bar.classList.add('playing');
            }
        });
        
        // Generate dynamic waveform based on audio frequency data if available
        if (this.isPlaying && this.audio) {
            this.animateWaveformBars();
        }
    }

    animateWaveformBars() {
        // Create animated waveform effect during playback
        this.waveformBars.forEach((bar, index) => {
            if (bar.classList.contains('active') || bar.classList.contains('playing')) {
                const randomHeight = 20 + Math.random() * 80; // Random between 20% and 100%
                bar.style.height = `${randomHeight}%`;
                
                // Add animation delay based on position
                setTimeout(() => {
                    const newHeight = 30 + Math.random() * 60;
                    bar.style.height = `${newHeight}%`;
                }, Math.random() * 500);
            }
        });
    }

    startWaveformAnimation() {
        this.waveformBars.forEach(bar => {
            if (bar.classList.contains('active') || bar.classList.contains('playing')) {
                bar.classList.add('playing');
            }
        });
    }

    stopWaveformAnimation() {
        this.waveformBars.forEach(bar => {
            bar.classList.remove('playing');
        });
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
    }

    closeSettings() {
        this.settingsPanel.style.display = 'none';
        this.settingsOpen = false;
        this.settingsBtn.style.background = '';
        this.settingsBtn.style.color = '';
    }

    showLoading() {
        this.isLoading = true;
        this.loadingEl.style.display = 'flex';
        this.hideError();
    }

    hideLoading() {
        this.isLoading = false;
        this.loadingEl.style.display = 'none';
    }

    showError() {
        this.hideLoading();
        this.errorEl.style.display = 'block';
        this.onPause();
    }

    hideError() {
        this.errorEl.style.display = 'none';
    }

    retry() {
        this.hideError();
        this.audio.load();
        this.showLoading();
    }

    formatTime(seconds) {
        if (isNaN(seconds) || !isFinite(seconds)) {
            return '0:00';
        }

        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    trackAnalytics(action) {
        // Track usage analytics if available
        if (typeof wpTTSAnalytics !== 'undefined') {
            wpTTSAnalytics.track('minimal_player', action, {
                duration: this.duration,
                currentTime: this.currentTime,
                playbackRate: this.audio.playbackRate
            });
        }

        // Google Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'tts_minimal_player_' + action, {
                event_category: 'TTS',
                event_label: 'Minimal Player',
                value: Math.round(this.currentTime)
            });
        }
    }

    // Public API methods
    getCurrentTime() {
        return this.currentTime;
    }

    getDuration() {
        return this.duration;
    }

    setPlaybackRate(rate) {
        this.audio.playbackRate = rate;
        if (this.playbackRateSelect) {
            this.playbackRateSelect.value = rate;
        }
    }

    getPlaybackRate() {
        return this.audio.playbackRate;
    }

    isCurrentlyPlaying() {
        return this.isPlaying;
    }

    destroy() {
        // Cleanup event listeners
        this.audio.pause();
        this.audio.src = '';
        this.container.classList.remove('initialized');
    }
}

// Auto-initialize players when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const players = document.querySelectorAll('.wp-tts-minimal-player-container:not(.initialized)');
    
    players.forEach(container => {
        new WPTTSMinimalPlayer(container);
        container.classList.add('initialized');
    });
});

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.WPTTSMinimalPlayer = WPTTSMinimalPlayer;
}
/**
 * Enhanced SesoLibre TTS Player JavaScript
 * Enhanced version with featured image support and advanced controls
 */

class TTSEnhancedSesoLibrePlayer {
    constructor(container) {
        this.container = container;
        this.playBtn = container.querySelector('.tts-play-pause');
        this.progress = container.querySelector('.tts-progress');
        this.progressBar = container.querySelector('.tts-progress-bar');
        this.progressHandle = container.querySelector('.tts-progress-handle');
        this.currentTimeEl = container.querySelector('.tts-current-time');
        this.totalTimeEl = container.querySelector('.tts-total-time');
        this.voiceSlider = container.querySelector('.tts-voice-slider');
        this.backgroundSlider = container.querySelector('.tts-background-slider');
        this.phaseLabels = container.querySelectorAll('.phase-label');
        this.loadingEl = container.querySelector('.tts-loading');
        this.errorContainer = container.querySelector('.tts-error-container');
        this.volumeValues = container.querySelectorAll('.volume-value');
        this.speedBtn = container.querySelector('.tts-speed-btn');
        this.speedMenu = container.querySelector('.tts-speed-menu');

        // Audio elements
        this.mainAudio = null;
        this.introAudio = null;
        this.backgroundAudio = null;
        this.outroAudio = null;

        // Player state
        this.currentPhase = 'none'; // none, intro, main, outro
        this.isPlaying = false;
        this.isLoading = false;
        this.totalDuration = 0;
        this.currentTime = 0;
        this.introDuration = 0;
        this.mainDuration = 0;
        this.outroDuration = 0;

        // Configuration
        this.mainAudioUrl = container.dataset.mainAudio;
        this.introAudioUrl = container.dataset.introAudio;
        this.backgroundAudioUrl = container.dataset.backgroundAudio;
        this.outroAudioUrl = container.dataset.outroAudio;
        this.backgroundVolume = parseFloat(container.dataset.backgroundVolume) || 0.3;
        this.showVoiceVolume = container.dataset.showVoiceVolume === 'true';
        this.showBackgroundVolume = container.dataset.showBackgroundVolume === 'true';

        this.playbackRate = 1.0;

        this.init();
    }

    init() {
        this.createAudioElements();
        this.setupEventListeners();
        this.setupSpeedControl();
        this.loadAudioMetadata();
        this.updateVolumeDisplays();
    }

    createAudioElements() {
        // Main TTS audio
        this.mainAudio = new Audio();
        this.mainAudio.crossOrigin = 'anonymous';
        this.mainAudio.preload = 'metadata';
        this.mainAudio.src = this.mainAudioUrl;

        // Intro audio
        if (this.introAudioUrl) {
            this.introAudio = new Audio();
            this.introAudio.crossOrigin = 'anonymous';
            this.introAudio.preload = 'metadata';
            this.introAudio.src = this.introAudioUrl;
        }

        // Background music
        if (this.backgroundAudioUrl) {
            this.backgroundAudio = new Audio();
            this.backgroundAudio.crossOrigin = 'anonymous';
            this.backgroundAudio.preload = 'metadata';
            this.backgroundAudio.src = this.backgroundAudioUrl;
            this.backgroundAudio.loop = true;
            this.backgroundAudio.volume = this.backgroundVolume;
        }

        // Outro audio
        if (this.outroAudioUrl) {
            this.outroAudio = new Audio();
            this.outroAudio.crossOrigin = 'anonymous';
            this.outroAudio.preload = 'metadata';
            this.outroAudio.src = this.outroAudioUrl;
        }

        this.setupAudioEventListeners();
    }

    setupEventListeners() {
        // Play/Pause button
        this.playBtn.addEventListener('click', () => this.togglePlay());

        // Progress bar seeking
        this.progress.addEventListener('click', (e) => this.seek(e));

        // Volume controls
        if (this.voiceSlider) {
            this.voiceSlider.addEventListener('input', (e) => this.updateVoiceVolume(e.target.value));
        }

        if (this.backgroundSlider) {
            this.backgroundSlider.addEventListener('input', (e) => this.updateBackgroundVolume(e.target.value));
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.target.closest('.tts-enhanced-sesolibre-player') === this.container) {
                this.handleKeydown(e);
            }
        });
    }

    setupSpeedControl() {
        if (this.speedBtn && this.speedMenu) {
            // Toggle speed menu
            this.speedBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.speedMenu.style.display = this.speedMenu.style.display === 'none' ? 'block' : 'none';
            });

            // Handle speed selection
            this.speedMenu.querySelectorAll('button').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const speed = parseFloat(button.dataset.speed);
                    this.setPlaybackRate(speed);
                    
                    // Update active state
                    this.speedMenu.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    
                    // Hide menu
                    this.speedMenu.style.display = 'none';
                });
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                if (this.speedMenu) {
                    this.speedMenu.style.display = 'none';
                }
            });
        }
    }

    setPlaybackRate(rate) {
        this.playbackRate = rate;
        
        if (this.introAudio) this.introAudio.playbackRate = rate;
        if (this.mainAudio) this.mainAudio.playbackRate = rate;
        if (this.outroAudio) this.outroAudio.playbackRate = rate;
        
        // Background music doesn't change speed
        
        this.trackAnalytics('speed_change');
    }

    setupAudioEventListeners() {
        // Main audio events
        this.mainAudio.addEventListener('loadedmetadata', () => this.calculateTotalDuration());
        this.mainAudio.addEventListener('timeupdate', () => this.updateMainProgress());
        this.mainAudio.addEventListener('ended', () => this.onMainEnded());
        this.mainAudio.addEventListener('error', (e) => this.handleAudioError('main', e));
        this.mainAudio.addEventListener('waiting', () => this.showLoading());
        this.mainAudio.addEventListener('canplay', () => this.hideLoading());

        // Intro audio events
        if (this.introAudio) {
            this.introAudio.addEventListener('loadedmetadata', () => this.calculateTotalDuration());
            this.introAudio.addEventListener('timeupdate', () => this.updateIntroProgress());
            this.introAudio.addEventListener('ended', () => this.onIntroEnded());
            this.introAudio.addEventListener('error', () => this.showError('Error al cargar el audio de intro'));
        }

        // Background audio events
        if (this.backgroundAudio) {
            this.backgroundAudio.addEventListener('error', () => console.warn('Error al cargar música de fondo'));
        }

        // Outro audio events
        if (this.outroAudio) {
            this.outroAudio.addEventListener('loadedmetadata', () => this.calculateTotalDuration());
            this.outroAudio.addEventListener('timeupdate', () => this.updateOutroProgress());
            this.outroAudio.addEventListener('ended', () => this.onOutroEnded());
            this.outroAudio.addEventListener('error', () => this.showError('Error al cargar el audio de outro'));
        }
    }

    async loadAudioMetadata() {
        this.showLoading();
        
        try {
            // Load metadata for all audio files
            const promises = [this.mainAudio];
            
            if (this.introAudio) promises.push(this.introAudio);
            if (this.outroAudio) promises.push(this.outroAudio);

            await Promise.all(promises.map(audio => {
                return new Promise((resolve, reject) => {
                    if (audio.readyState >= 1) {
                        resolve();
                    } else {
                        const onLoad = () => {
                            audio.removeEventListener('loadedmetadata', onLoad);
                            audio.removeEventListener('error', onError);
                            resolve();
                        };
                        const onError = () => {
                            audio.removeEventListener('loadedmetadata', onLoad);
                            audio.removeEventListener('error', onError);
                            reject();
                        };
                        audio.addEventListener('loadedmetadata', onLoad);
                        audio.addEventListener('error', onError);
                        audio.load();
                    }
                });
            }));

            this.calculateTotalDuration();
            this.hideLoading();
        } catch (error) {
            this.showError('Error al cargar los metadatos del audio');
        }
    }

    calculateTotalDuration() {
        this.introDuration = this.introAudio ? this.introAudio.duration || 0 : 0;
        this.mainDuration = this.mainAudio.duration || 0;
        this.outroDuration = this.outroAudio ? this.outroAudio.duration || 0 : 0;
        
        this.totalDuration = this.introDuration + this.mainDuration + this.outroDuration;
        
        if (this.totalTimeEl && this.totalDuration) {
            this.totalTimeEl.textContent = this.formatTime(this.totalDuration);
        }
    }

    async togglePlay() {
        if (this.isLoading) return;

        if (this.isPlaying) {
            await this.pause();
        } else {
            await this.play();
        }
    }

    async play() {
        try {
            this.hideError();
            
            if (this.currentPhase === 'none') {
                // Start from the beginning
                if (this.introAudio) {
                    await this.playIntro();
                } else {
                    await this.playMain();
                }
            } else {
                // Resume current phase
                await this.resumeCurrentPhase();
            }
            
            this.isPlaying = true;
            this.updatePlayButton();
            this.trackAnalytics('play');
        } catch (error) {
            console.error('Error playing audio:', error);
            this.showError('Error al reproducir el audio');
        }
    }

    async pause() {
        this.isPlaying = false;
        
        // Pause all audio elements
        if (this.introAudio) this.introAudio.pause();
        this.mainAudio.pause();
        if (this.backgroundAudio) this.backgroundAudio.pause();
        if (this.outroAudio) this.outroAudio.pause();
        
        this.updatePlayButton();
        this.trackAnalytics('pause');
    }

    async playIntro() {
        this.currentPhase = 'intro';
        this.updatePhaseIndicators();
        await this.introAudio.play();
    }

    async playMain() {
        this.currentPhase = 'main';
        this.updatePhaseIndicators();
        
        // Start background music if available
        if (this.backgroundAudio) {
            this.backgroundAudio.currentTime = 0;
            await this.backgroundAudio.play();
        }
        
        await this.mainAudio.play();
    }

    async playOutro() {
        this.currentPhase = 'outro';
        this.updatePhaseIndicators();
        
        // Stop background music
        if (this.backgroundAudio) {
            this.backgroundAudio.pause();
        }
        
        await this.outroAudio.play();
    }

    async resumeCurrentPhase() {
        switch (this.currentPhase) {
            case 'intro':
                if (this.introAudio) await this.introAudio.play();
                break;
            case 'main':
                await this.mainAudio.play();
                if (this.backgroundAudio) await this.backgroundAudio.play();
                break;
            case 'outro':
                if (this.outroAudio) await this.outroAudio.play();
                break;
        }
    }

    async onIntroEnded() {
        if (this.isPlaying) {
            await this.playMain();
        }
    }

    async onMainEnded() {
        // Stop background music
        if (this.backgroundAudio) {
            this.backgroundAudio.pause();
        }
        
        if (this.isPlaying) {
            if (this.outroAudio) {
                await this.playOutro();
            } else {
                this.onPlaybackEnded();
            }
        }
    }

    onOutroEnded() {
        this.onPlaybackEnded();
    }

    onPlaybackEnded() {
        this.isPlaying = false;
        this.currentPhase = 'none';
        this.currentTime = 0;
        
        // Reset all audio positions
        if (this.introAudio) this.introAudio.currentTime = 0;
        this.mainAudio.currentTime = 0;
        if (this.outroAudio) this.outroAudio.currentTime = 0;
        
        this.updatePlayButton();
        this.updateProgress();
        this.updatePhaseIndicators();
        this.trackAnalytics('ended');
    }

    updateIntroProgress() {
        if (this.currentPhase === 'intro') {
            this.currentTime = this.introAudio.currentTime;
            this.updateProgress();
        }
    }

    updateMainProgress() {
        if (this.currentPhase === 'main') {
            this.currentTime = this.introDuration + this.mainAudio.currentTime;
            this.updateProgress();
        }
    }

    updateOutroProgress() {
        if (this.currentPhase === 'outro') {
            this.currentTime = this.introDuration + this.mainDuration + this.outroAudio.currentTime;
            this.updateProgress();
        }
    }

    updateProgress() {
        if (this.currentTimeEl) {
            this.currentTimeEl.textContent = this.formatTime(this.currentTime);
        }
        
        if (this.progressBar && this.totalDuration) {
            const percentage = (this.currentTime / this.totalDuration) * 100;
            this.progressBar.style.width = `${percentage}%`;
            
            if (this.progressHandle) {
                this.progressHandle.style.left = `${percentage}%`;
            }
        }
    }

    updatePhaseIndicators() {
        this.phaseLabels.forEach(label => label.classList.remove('active'));
        
        const currentLabel = this.container.querySelector(`.${this.currentPhase}-phase`);
        if (currentLabel) {
            currentLabel.classList.add('active');
        }
    }

    updatePlayButton() {
        this.playBtn.classList.toggle('playing', this.isPlaying);
    }

    seek(e) {
        if (!this.totalDuration) return;

        const rect = this.progress.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percentage = clickX / rect.width;
        const targetTime = percentage * this.totalDuration;

        this.seekToTime(targetTime);
    }

    async seekToTime(targetTime) {
        const wasPlaying = this.isPlaying;
        
        if (this.isPlaying) {
            await this.pause();
        }

        // Determine which phase the target time is in
        if (targetTime < this.introDuration) {
            // Seeking to intro
            if (this.introAudio) {
                this.currentPhase = 'intro';
                this.introAudio.currentTime = targetTime;
                this.currentTime = targetTime;
            }
        } else if (targetTime < this.introDuration + this.mainDuration) {
            // Seeking to main
            this.currentPhase = 'main';
            const mainTime = targetTime - this.introDuration;
            this.mainAudio.currentTime = mainTime;
            this.currentTime = targetTime;
        } else {
            // Seeking to outro
            if (this.outroAudio) {
                this.currentPhase = 'outro';
                const outroTime = targetTime - this.introDuration - this.mainDuration;
                this.outroAudio.currentTime = outroTime;
                this.currentTime = targetTime;
            }
        }

        this.updateProgress();
        this.updatePhaseIndicators();

        if (wasPlaying) {
            await this.play();
        }
    }

    updateVoiceVolume(value) {
        const volume = parseFloat(value);
        
        if (this.introAudio) this.introAudio.volume = volume;
        this.mainAudio.volume = volume;
        if (this.outroAudio) this.outroAudio.volume = volume;
        
        this.updateVolumeDisplay(this.voiceSlider, volume);
    }

    updateBackgroundVolume(value) {
        const volume = parseFloat(value);
        
        if (this.backgroundAudio) {
            this.backgroundAudio.volume = volume;
        }
        
        this.updateVolumeDisplay(this.backgroundSlider, volume);
    }

    updateVolumeDisplay(slider, volume) {
        const volumeValue = slider.parentElement.querySelector('.volume-value');
        if (volumeValue) {
            volumeValue.textContent = `${Math.round(volume * 100)}%`;
        }
    }

    updateVolumeDisplays() {
        if (this.voiceSlider) {
            this.updateVolumeDisplay(this.voiceSlider, 1);
        }
        if (this.backgroundSlider) {
            this.updateVolumeDisplay(this.backgroundSlider, this.backgroundVolume);
        }
    }

    handleKeydown(e) {
        switch (e.code) {
            case 'Space':
                e.preventDefault();
                this.togglePlay();
                break;
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
                if (this.voiceSlider) {
                    const newValue = Math.min(1, parseFloat(this.voiceSlider.value) + 0.1);
                    this.voiceSlider.value = newValue;
                    this.updateVoiceVolume(newValue);
                }
                break;
            case 'ArrowDown':
                e.preventDefault();
                if (this.voiceSlider) {
                    const newValue = Math.max(0, parseFloat(this.voiceSlider.value) - 0.1);
                    this.voiceSlider.value = newValue;
                    this.updateVoiceVolume(newValue);
                }
                break;
        }
    }

    showLoading() {
        this.isLoading = true;
        if (this.loadingEl) {
            this.loadingEl.style.display = 'flex';
        }
        this.hideError();
    }

    hideLoading() {
        this.isLoading = false;
        if (this.loadingEl) {
            this.loadingEl.style.display = 'none';
        }
    }

    showError(message) {
        this.hideLoading();
        if (this.errorContainer) {
            this.errorContainer.textContent = message;
            this.errorContainer.classList.add('show');
        }
    }

    hideError() {
        if (this.errorContainer) {
            this.errorContainer.classList.remove('show');
        }
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
            wpTTSAnalytics.track('enhanced_sesolibre_player', action, {
                phase: this.currentPhase,
                duration: this.totalDuration,
                currentTime: this.currentTime,
                hasIntro: !!this.introAudio,
                hasBackground: !!this.backgroundAudio,
                hasOutro: !!this.outroAudio
            });
        }

        // Google Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'tts_enhanced_sesolibre_' + action, {
                event_category: 'TTS',
                event_label: 'Enhanced SesoLibre Player',
                value: Math.round(this.currentTime)
            });
        }
    }

    // Public API methods
    getCurrentTime() {
        return this.currentTime;
    }

    getTotalDuration() {
        return this.totalDuration;
    }

    getCurrentPhase() {
        return this.currentPhase;
    }

    isCurrentlyPlaying() {
        return this.isPlaying;
    }

    setVoiceVolume(volume) {
        if (this.voiceSlider) {
            this.voiceSlider.value = volume;
            this.updateVoiceVolume(volume);
        }
    }

    setBackgroundVolume(volume) {
        if (this.backgroundSlider) {
            this.backgroundSlider.value = volume;
            this.updateBackgroundVolume(volume);
        }
    }

    destroy() {
        // Cleanup audio and event listeners
        if (this.introAudio) {
            this.introAudio.pause();
            this.introAudio.src = '';
        }
        
        this.mainAudio.pause();
        this.mainAudio.src = '';
        
        if (this.backgroundAudio) {
            this.backgroundAudio.pause();
            this.backgroundAudio.src = '';
        }
        
        if (this.outroAudio) {
            this.outroAudio.pause();
            this.outroAudio.src = '';
        }
        
        this.container.classList.remove('initialized');
    }

    handleAudioError(audioType, error) {
        console.error(`Audio error (${audioType}):`, error);
        
        const audio = audioType === 'main' ? this.mainAudio : 
                     audioType === 'intro' ? this.introAudio :
                     audioType === 'background' ? this.backgroundAudio :
                     audioType === 'outro' ? this.outroAudio : null;
        
        let errorMessage = '';
        
        if (audio && audio.error) {
            switch (audio.error.code) {
                case audio.error.MEDIA_ERR_ABORTED:
                    errorMessage = 'Carga de audio cancelada';
                    break;
                case audio.error.MEDIA_ERR_NETWORK:
                    errorMessage = 'Error de red al cargar audio';
                    break;
                case audio.error.MEDIA_ERR_DECODE:
                    errorMessage = 'Error al decodificar audio';
                    break;
                case audio.error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                    errorMessage = 'Formato de audio no soportado';
                    break;
                default:
                    errorMessage = 'Error desconocido al cargar audio';
            }
        } else {
            // Likely CORS error for external URLs
            if (audio && audio.src && (audio.src.includes('buzzsprout.com') || !audio.src.includes(window.location.hostname))) {
                errorMessage = 'Audio externo cargado (limitaciones de metadatos)';
                console.info(`External audio from ${audioType}: Metadata may be limited due to CORS policy`);
                // Don't show error for external audio, just log it
                return;
            } else {
                errorMessage = 'Error al cargar los metadatos de audio';
            }
        }
        
        if (audioType === 'main') {
            this.showError(errorMessage);
            this.pause();
        } else {
            console.warn(`${audioType} audio error: ${errorMessage}`);
        }
    }
}

// Auto-initialize players when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const players = document.querySelectorAll('.tts-enhanced-sesolibre-player:not(.initialized)');
    
    players.forEach(container => {
        new TTSEnhancedSesoLibrePlayer(container);
        container.classList.add('initialized');
    });
});

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.TTSEnhancedSesoLibrePlayer = TTSEnhancedSesoLibrePlayer;
}
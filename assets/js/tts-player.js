/**
 * TTS SesoLibre Audio Player with Dynamic Mixing
 */
class TTSSesoLibrePlayer {
    constructor(playerElement) {
        this.element = playerElement;
        this.isPlaying = false;
        this.currentPhase = 'none';
        this.audioElements = {};
        
        // Get audio URLs from data attributes
        this.audioUrls = {
            main: this.element.dataset.mainAudio || '',
            intro: this.element.dataset.introAudio || '',
            background: this.element.dataset.backgroundAudio || '',
            outro: this.element.dataset.outroAudio || ''
        };
        
        // Get configuration
        this.config = {
            backgroundVolume: parseFloat(this.element.dataset.backgroundVolume) || 0.3,
            showVoiceVolume: this.element.dataset.showVoiceVolume === 'true',
            showBackgroundVolume: this.element.dataset.showBackgroundVolume === 'true'
        };
        
        // Initialize UI elements
        this.initializeElements();
        
        // Setup audio elements
        this.setupAudioElements();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Setup speed control
        this.setupSpeedControl();
        
        // Load metadata
        this.loadAudioMetadata();
        
        // Default playback rate
        this.playbackRate = 1.0;
        
        console.log('TTS SesoLibre Player initialized:', {
            audioUrls: this.audioUrls,
            config: this.config
        });
    }
    
    initializeElements() {
        this.elements = {
            playButton: this.element.querySelector('.tts-play-pause'),
            progressBar: this.element.querySelector('.tts-progress-bar'),
            progressContainer: this.element.querySelector('.tts-progress'),
            currentTime: this.element.querySelector('.tts-current-time'),
            totalTime: this.element.querySelector('.tts-total-time'),
            voiceSlider: this.element.querySelector('.tts-voice-slider'),
            backgroundSlider: this.element.querySelector('.tts-background-slider'),
            phaseIndicator: this.element.querySelector('.tts-phase-indicator'),
            errorContainer: this.element.querySelector('.tts-error-container'),
            speedBtn: this.element.querySelector('.tts-speed-btn'),
            speedMenu: this.element.querySelector('.tts-speed-menu')
        };
    }
    
    setupAudioElements() {
        // Main audio (TTS)
        if (this.audioUrls.main) {
            this.audioElements.main = new Audio(this.audioUrls.main);
            this.audioElements.main.crossOrigin = 'anonymous';
            this.audioElements.main.preload = 'metadata';
            this.setupMainAudioEvents();
        }
        
        // Background music (looped)
        if (this.audioUrls.background) {
            this.audioElements.background = new Audio(this.audioUrls.background);
            this.audioElements.background.crossOrigin = 'anonymous';
            this.audioElements.background.loop = true;
            this.audioElements.background.volume = this.config.backgroundVolume;
            this.audioElements.background.preload = 'metadata';
        }
        
        // Intro audio
        if (this.audioUrls.intro) {
            this.audioElements.intro = new Audio(this.audioUrls.intro);
            this.audioElements.intro.crossOrigin = 'anonymous';
            this.audioElements.intro.preload = 'metadata';
        }
        
        // Outro audio
        if (this.audioUrls.outro) {
            this.audioElements.outro = new Audio(this.audioUrls.outro);
            this.audioElements.outro.crossOrigin = 'anonymous';
            this.audioElements.outro.preload = 'metadata';
        }
    }
    
    setupMainAudioEvents() {
        const mainAudio = this.audioElements.main;
        
        mainAudio.addEventListener('loadedmetadata', () => {
            this.updateTotalTime();
        });
        
        mainAudio.addEventListener('timeupdate', () => {
            this.updateProgress();
        });
        
        mainAudio.addEventListener('ended', () => {
            this.handleMainAudioEnded();
        });
        
        mainAudio.addEventListener('error', (e) => {
            this.handleAudioError('main', e);
        });
    }
    
    setupEventListeners() {
        // Play/pause button
        if (this.elements.playButton) {
            this.elements.playButton.addEventListener('click', () => {
                this.togglePlayPause();
            });
        }
        
        // Progress bar seeking
        if (this.elements.progressContainer) {
            this.elements.progressContainer.addEventListener('click', (e) => {
                this.seekToPosition(e);
            });
        }
        
        // Volume controls
        if (this.elements.voiceSlider) {
            this.elements.voiceSlider.addEventListener('input', (e) => {
                this.setVoiceVolume(parseFloat(e.target.value));
            });
        }
        
        if (this.elements.backgroundSlider) {
            this.elements.backgroundSlider.addEventListener('input', (e) => {
                this.setBackgroundVolume(parseFloat(e.target.value));
            });
        }
    }
    
    setupSpeedControl() {
        if (this.elements.speedBtn && this.elements.speedMenu) {
            // Toggle speed menu
            this.elements.speedBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isVisible = this.elements.speedMenu.style.display === 'block';
                this.elements.speedMenu.style.display = isVisible ? 'none' : 'block';
            });

            // Handle speed selection
            this.elements.speedMenu.querySelectorAll('button').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const speed = parseFloat(button.dataset.speed);
                    this.setPlaybackRate(speed);
                    
                    // Update active state
                    this.elements.speedMenu.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    
                    // Hide menu
                    this.elements.speedMenu.style.display = 'none';
                });
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                if (this.elements.speedMenu) {
                    this.elements.speedMenu.style.display = 'none';
                }
            });
        }
    }
    
    setPlaybackRate(rate) {
        this.playbackRate = rate;
        
        // Apply to all audio elements except background
        if (this.audioElements.main) this.audioElements.main.playbackRate = rate;
        if (this.audioElements.intro) this.audioElements.intro.playbackRate = rate;
        if (this.audioElements.outro) this.audioElements.outro.playbackRate = rate;
        
        // Background music keeps normal speed
    }
    
    loadAudioMetadata() {
        // Load metadata for all audio files
        Object.values(this.audioElements).forEach(audio => {
            if (audio.readyState >= 1) {
                this.updateTotalTime();
            } else {
                audio.addEventListener('loadedmetadata', () => {
                    this.updateTotalTime();
                }, { once: true });
            }
        });
    }
    
    togglePlayPause() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }
    
    play() {
        if (!this.audioElements.main) {
            this.showError('No hay audio principal disponible');
            return;
        }
        
        this.isPlaying = true;
        this.updatePlayButton();
        
        // Start with intro if available, otherwise start main
        if (this.audioUrls.intro && this.currentPhase === 'none') {
            this.startIntroPhase();
        } else {
            this.startMainPhase();
        }
    }
    
    pause() {
        this.isPlaying = false;
        this.updatePlayButton();
        
        // Pause all currently playing audio
        Object.values(this.audioElements).forEach(audio => {
            if (!audio.paused) {
                audio.pause();
            }
        });
        
        this.updatePhaseIndicator('');
    }
    
    startIntroPhase() {
        this.currentPhase = 'intro';
        this.updatePhaseIndicator('intro');
        
        if (!this.audioElements.intro) {
            this.startMainPhase();
            return;
        }
        
        this.audioElements.intro.currentTime = 0;
        this.audioElements.intro.addEventListener('ended', () => {
            this.startMainPhase();
        }, { once: true });
        
        this.audioElements.intro.play().catch(e => {
            console.error('Error playing intro:', e);
            this.startMainPhase();
        });
    }
    
    startMainPhase() {
        this.currentPhase = 'main';
        this.updatePhaseIndicator('main');
        
        // Start main audio
        this.audioElements.main.play().catch(e => {
            console.error('Error playing main audio:', e);
            this.showError('Error al reproducir el audio principal');
            this.pause();
            return;
        });
        
        // Start background music if available
        if (this.audioElements.background) {
            this.audioElements.background.currentTime = 0;
            this.audioElements.background.play().catch(e => {
                console.warn('Error playing background music:', e);
            });
        }
    }
    
    handleMainAudioEnded() {
        // Stop background music
        if (this.audioElements.background) {
            this.audioElements.background.pause();
        }
        
        // Start outro if available
        if (this.audioUrls.outro) {
            this.startOutroPhase();
        } else {
            this.handlePlaybackComplete();
        }
    }
    
    startOutroPhase() {
        this.currentPhase = 'outro';
        this.updatePhaseIndicator('outro');
        
        if (!this.audioElements.outro) {
            this.handlePlaybackComplete();
            return;
        }
        
        this.audioElements.outro.currentTime = 0;
        this.audioElements.outro.addEventListener('ended', () => {
            this.handlePlaybackComplete();
        }, { once: true });
        
        this.audioElements.outro.play().catch(e => {
            console.error('Error playing outro:', e);
            this.handlePlaybackComplete();
        });
    }
    
    handlePlaybackComplete() {
        this.isPlaying = false;
        this.currentPhase = 'none';
        this.updatePlayButton();
        this.updatePhaseIndicator('');
        
        // Reset main audio to beginning
        if (this.audioElements.main) {
            this.audioElements.main.currentTime = 0;
        }
        
        this.updateProgress();
    }
    
    seekToPosition(event) {
        if (!this.audioElements.main || this.currentPhase !== 'main') {
            return;
        }
        
        const rect = this.elements.progressContainer.getBoundingClientRect();
        const percentage = (event.clientX - rect.left) / rect.width;
        const newTime = percentage * this.audioElements.main.duration;
        
        this.audioElements.main.currentTime = Math.max(0, Math.min(newTime, this.audioElements.main.duration));
    }
    
    setVoiceVolume(volume) {
        if (this.audioElements.main) {
            this.audioElements.main.volume = Math.max(0, Math.min(1, volume));
        }
        if (this.audioElements.intro) {
            this.audioElements.intro.volume = Math.max(0, Math.min(1, volume));
        }
        if (this.audioElements.outro) {
            this.audioElements.outro.volume = Math.max(0, Math.min(1, volume));
        }
    }
    
    setBackgroundVolume(volume) {
        if (this.audioElements.background) {
            this.audioElements.background.volume = Math.max(0, Math.min(1, volume));
        }
        this.config.backgroundVolume = volume;
    }
    
    updatePlayButton() {
        if (!this.elements.playButton) return;
        
        if (this.isPlaying) {
            this.elements.playButton.innerHTML = '⏸';
            this.elements.playButton.classList.add('playing');
        } else {
            this.elements.playButton.innerHTML = '▶';
            this.elements.playButton.classList.remove('playing');
        }
    }
    
    updateProgress() {
        if (!this.audioElements.main || !this.elements.progressBar || !this.elements.currentTime) {
            return;
        }
        
        const current = this.audioElements.main.currentTime;
        const duration = this.audioElements.main.duration || 0;
        
        if (duration > 0) {
            const percentage = (current / duration) * 100;
            this.elements.progressBar.style.width = `${percentage}%`;
        }
        
        this.elements.currentTime.textContent = this.formatTime(current);
    }
    
    updateTotalTime() {
        if (!this.elements.totalTime || !this.audioElements.main) {
            return;
        }
        
        const duration = this.audioElements.main.duration || 0;
        this.elements.totalTime.textContent = this.formatTime(duration);
    }
    
    updatePhaseIndicator(phase) {
        if (!this.elements.phaseIndicator) return;
        
        this.elements.phaseIndicator.className = 'tts-phase-indicator';
        
        if (phase) {
            this.elements.phaseIndicator.classList.add('active', phase);
            
            const phaseNames = {
                intro: 'Introducción',
                main: 'Audio Principal',
                outro: 'Cierre'
            };
            
            this.elements.phaseIndicator.textContent = phaseNames[phase] || '';
        } else {
            this.elements.phaseIndicator.textContent = '';
        }
    }
    
    formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
    
    showError(message) {
        if (this.elements.errorContainer) {
            this.elements.errorContainer.innerHTML = `
                <div class="tts-error-message">
                    ${message}
                </div>
            `;
        } else {
            console.error('TTS Player Error:', message);
        }
    }
    
    handleAudioError(audioType, error) {
        console.error(`Audio error (${audioType}):`, error);
        
        const audio = this.audioElements[audioType];
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
    
    // Public methods for external control
    destroy() {
        // Clean up event listeners and audio elements
        Object.values(this.audioElements).forEach(audio => {
            audio.pause();
            audio.src = '';
        });
        
        this.audioElements = {};
        this.isPlaying = false;
    }
    
    getCurrentTime() {
        return this.audioElements.main ? this.audioElements.main.currentTime : 0;
    }
    
    getDuration() {
        return this.audioElements.main ? this.audioElements.main.duration : 0;
    }
    
    getPhase() {
        return this.currentPhase;
    }
}

// Auto-initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('Looking for TTS SesoLibre players...');
    
    const players = document.querySelectorAll('.tts-sesolibre-player');
    console.log(`Found ${players.length} TTS players`);
    
    players.forEach(playerElement => {
        try {
            new TTSSesoLibrePlayer(playerElement);
        } catch (error) {
            console.error('Error initializing TTS player:', error);
        }
    });
});

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.TTSSesoLibrePlayer = TTSSesoLibrePlayer;
}
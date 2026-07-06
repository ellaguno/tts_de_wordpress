/**
 * TTS Player Core
 *
 * Shared engine for the TTS SesoLibre player variants (sesolibre, minimal,
 * enhanced_sesolibre). Before this file existed the three variants carried
 * near-identical copies of the phase logic (intro → main+background → outro),
 * seeking, speed menu, volume, time formatting, error handling and analytics
 * (~1,600 lines total) that had already drifted apart — keyboard support and
 * retry existed in some variants only, error messages differed, etc.
 *
 * Variants extend this class and only override presentation hooks:
 *   - init()                     extra setup after core wiring
 *   - renderPlayState(isPlaying) how the play button reflects state
 *   - updatePhaseUI(phase)       phase indicator ('' | intro | main | outro)
 *   - onProgressRender(pct)      extra per-tick rendering (waveform, handle)
 *   - showError(msg)/hideError() error presentation
 *
 * The classic style keeps using the native <audio controls> enhancement in
 * audio-player.js and does not go through this engine.
 */
class TTSPlayerCore {
    /**
     * @param {HTMLElement} container Player root element.
     * @param {Object} options
     * @param {string}  options.label        Analytics label for this variant.
     * @param {Object}  options.selectors    Map of role → CSS selector, resolved
     *                                       against the container into this.el.
     *                                       Roles used by the core: playBtn,
     *                                       progress, progressBar, progressHandle,
     *                                       currentTime, totalTime, voiceSlider,
     *                                       backgroundSlider, loading, error,
     *                                       speedBtn, speedMenu, playbackRateSelect,
     *                                       retryBtn.
     * @param {string} [options.adoptAudio]  Selector of an <audio> element already
     *                                       in the DOM to use as the main track
     *                                       (minimal variant). When absent, the
     *                                       main track is created from
     *                                       data-main-audio.
     */
    constructor(container, options = {}) {
        this.container = container;
        this.label = options.label || 'tts_player';

        this.el = {};
        const selectors = options.selectors || {};
        for (const role of Object.keys(selectors)) {
            this.el[role] = selectors[role] ? container.querySelector(selectors[role]) : null;
        }

        // Track URLs come from data attributes rendered by the PHP template.
        this.urls = {
            main: container.dataset.mainAudio || '',
            intro: container.dataset.introAudio || '',
            background: container.dataset.backgroundAudio || '',
            outro: container.dataset.outroAudio || ''
        };
        this.backgroundVolume = parseFloat(container.dataset.backgroundVolume);
        if (isNaN(this.backgroundVolume)) {
            this.backgroundVolume = 0.3;
        }

        // Player state
        this.currentPhase = 'none'; // none | intro | main | outro
        this.isPlaying = false;
        this.isLoading = false;
        this.currentTime = 0;      // position on the unified timeline
        this.totalDuration = 0;    // intro + main + outro
        this.introDuration = 0;
        this.mainDuration = 0;
        this.outroDuration = 0;
        this.playbackRate = 1.0;

        this.audio = { main: null, intro: null, background: null, outro: null };
        this.createAudioElements(options.adoptAudio);

        this.bindUIEvents();
        this.bindAudioEvents();
        this.setupSpeedControl();

        this.init();
        this.loadMetadata();
    }

    /** Variant hook: extra setup. */
    init() {}

    /* ------------------------------------------------------------------ *
     * Audio element setup
     * ------------------------------------------------------------------ */

    createAudioElements(adoptAudioSelector) {
        if (adoptAudioSelector) {
            this.audio.main = this.container.querySelector(adoptAudioSelector);
        } else if (this.urls.main) {
            this.audio.main = new Audio();
            this.audio.main.crossOrigin = 'anonymous';
            this.audio.main.preload = 'metadata';
            this.audio.main.src = this.urls.main;
        }

        if (this.urls.intro) {
            this.audio.intro = new Audio();
            this.audio.intro.crossOrigin = 'anonymous';
            this.audio.intro.preload = 'metadata';
            this.audio.intro.src = this.urls.intro;
        }

        if (this.urls.background) {
            this.audio.background = new Audio();
            this.audio.background.crossOrigin = 'anonymous';
            this.audio.background.preload = 'metadata';
            this.audio.background.src = this.urls.background;
            this.audio.background.loop = true;
            this.audio.background.volume = this.backgroundVolume;
        }

        if (this.urls.outro) {
            this.audio.outro = new Audio();
            this.audio.outro.crossOrigin = 'anonymous';
            this.audio.outro.preload = 'metadata';
            this.audio.outro.src = this.urls.outro;
        }
    }

    bindAudioEvents() {
        const main = this.audio.main;
        if (!main) {
            return;
        }

        main.addEventListener('loadedmetadata', () => this.calculateDurations());
        main.addEventListener('timeupdate', () => {
            if (this.currentPhase === 'main' || !this.hasPhases()) {
                this.currentTime = this.introDuration + main.currentTime;
                this.renderProgress();
            }
        });
        main.addEventListener('ended', () => this.onMainEnded());
        main.addEventListener('error', (e) => this.handleAudioError('main', e));
        main.addEventListener('waiting', () => this.showLoading());
        main.addEventListener('canplay', () => this.hideLoading());

        if (this.audio.intro) {
            this.audio.intro.addEventListener('loadedmetadata', () => this.calculateDurations());
            this.audio.intro.addEventListener('timeupdate', () => {
                if (this.currentPhase === 'intro') {
                    this.currentTime = this.audio.intro.currentTime;
                    this.renderProgress();
                }
            });
            this.audio.intro.addEventListener('ended', () => this.onIntroEnded());
            this.audio.intro.addEventListener('error', (e) => this.handleAudioError('intro', e));
        }

        if (this.audio.background) {
            this.audio.background.addEventListener('error', (e) => this.handleAudioError('background', e));
        }

        if (this.audio.outro) {
            this.audio.outro.addEventListener('loadedmetadata', () => this.calculateDurations());
            this.audio.outro.addEventListener('timeupdate', () => {
                if (this.currentPhase === 'outro') {
                    this.currentTime = this.introDuration + this.mainDuration + this.audio.outro.currentTime;
                    this.renderProgress();
                }
            });
            this.audio.outro.addEventListener('ended', () => this.onOutroEnded());
            this.audio.outro.addEventListener('error', (e) => this.handleAudioError('outro', e));
        }
    }

    hasPhases() {
        return !!(this.audio.intro || this.audio.outro);
    }

    loadMetadata() {
        Object.values(this.audio).forEach((audio) => {
            if (audio && audio.readyState < 1 && audio.load) {
                audio.load();
            }
        });
        this.calculateDurations();
    }

    calculateDurations() {
        this.introDuration = this.audio.intro ? this.audio.intro.duration || 0 : 0;
        this.mainDuration = this.audio.main ? this.audio.main.duration || 0 : 0;
        this.outroDuration = this.audio.outro ? this.audio.outro.duration || 0 : 0;
        this.totalDuration = this.introDuration + this.mainDuration + this.outroDuration;

        if (this.el.totalTime && this.totalDuration) {
            this.el.totalTime.textContent = this.formatTime(this.totalDuration);
        }
    }

    /* ------------------------------------------------------------------ *
     * UI events
     * ------------------------------------------------------------------ */

    bindUIEvents() {
        if (this.el.playBtn) {
            this.el.playBtn.addEventListener('click', () => this.togglePlay());
        }

        if (this.el.progress) {
            this.el.progress.addEventListener('click', (e) => this.seekFromEvent(e));

            // Keyboard-operable seeking (slider semantics)
            this.el.progress.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.seekToTime(Math.max(0, this.currentTime - 10));
                } else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.seekToTime(Math.min(this.totalDuration, this.currentTime + 10));
                } else if (e.key === 'Home') {
                    e.preventDefault();
                    this.seekToTime(0);
                } else if (e.key === 'End') {
                    e.preventDefault();
                    this.seekToTime(this.totalDuration);
                }
            });
        }

        if (this.el.voiceSlider) {
            this.el.voiceSlider.addEventListener('input', (e) => this.setVoiceVolume(parseFloat(e.target.value)));
        }

        if (this.el.backgroundSlider) {
            this.el.backgroundSlider.addEventListener('input', (e) => this.setBackgroundVolume(parseFloat(e.target.value)));
        }

        if (this.el.retryBtn) {
            this.el.retryBtn.addEventListener('click', () => this.retry());
        }

        // Space toggles play/pause when focus is inside the player but NOT on
        // an interactive control (a focused button already handles Space as a
        // click; reacting here too used to double-toggle).
        this.container.addEventListener('keydown', (e) => {
            if (e.code !== 'Space') {
                return;
            }
            const tag = e.target.tagName;
            if (tag === 'BUTTON' || tag === 'SELECT' || tag === 'INPUT' || tag === 'A') {
                return;
            }
            e.preventDefault();
            this.togglePlay();
        });
    }

    setupSpeedControl() {
        // Variant A: dropdown menu of buttons with data-speed
        if (this.el.speedBtn && this.el.speedMenu) {
            this.el.speedBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isVisible = this.el.speedMenu.style.display === 'block';
                this.el.speedMenu.style.display = isVisible ? 'none' : 'block';
            });

            this.el.speedMenu.querySelectorAll('button').forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.setPlaybackRate(parseFloat(button.dataset.speed));

                    this.el.speedMenu.querySelectorAll('button').forEach((btn) => btn.classList.remove('active'));
                    button.classList.add('active');
                    this.el.speedMenu.style.display = 'none';
                });
            });

            document.addEventListener('click', () => {
                if (this.el.speedMenu) {
                    this.el.speedMenu.style.display = 'none';
                }
            });
        }

        // Variant B: <select> with rate values
        if (this.el.playbackRateSelect) {
            this.el.playbackRateSelect.addEventListener('change', (e) => {
                this.setPlaybackRate(parseFloat(e.target.value));
            });
        }
    }

    setPlaybackRate(rate) {
        if (isNaN(rate) || rate <= 0) {
            return;
        }
        this.playbackRate = rate;

        // Background music keeps normal speed.
        ['main', 'intro', 'outro'].forEach((key) => {
            if (this.audio[key]) {
                this.audio[key].playbackRate = rate;
            }
        });

        if (this.el.playbackRateSelect && this.el.playbackRateSelect.value !== String(rate)) {
            this.el.playbackRateSelect.value = String(rate);
        }

        this.trackAnalytics('speed_change');
    }

    /* ------------------------------------------------------------------ *
     * Playback / phases
     * ------------------------------------------------------------------ */

    async togglePlay() {
        if (this.isLoading) {
            return;
        }
        if (this.isPlaying) {
            this.pause();
        } else {
            await this.play();
        }
    }

    async play() {
        if (!this.audio.main) {
            this.showError('No hay audio principal disponible');
            return;
        }

        try {
            this.hideError();

            if (this.currentPhase === 'none') {
                if (this.audio.intro) {
                    await this.playIntro();
                } else {
                    await this.playMain();
                }
            } else {
                await this.resumeCurrentPhase();
            }

            this.isPlaying = true;
            this.updatePlayButton();
            this.trackAnalytics('play');
        } catch (error) {
            console.error('Error playing audio:', error);
            this.showError('Error al reproducir el audio');
            this.pause();
        }
    }

    pause() {
        this.isPlaying = false;

        Object.values(this.audio).forEach((audio) => {
            if (audio && !audio.paused) {
                audio.pause();
            }
        });

        this.updatePlayButton();
        this.updatePhaseUI(this.currentPhase === 'none' ? '' : this.currentPhase);
        this.trackAnalytics('pause');
    }

    async playIntro() {
        this.currentPhase = 'intro';
        this.updatePhaseUI('intro');
        this.audio.intro.currentTime = 0;
        await this.audio.intro.play();
    }

    async playMain() {
        this.currentPhase = 'main';
        this.updatePhaseUI('main');

        if (this.audio.background) {
            this.audio.background.currentTime = 0;
            // Background failure must never block the article audio.
            try {
                await this.audio.background.play();
            } catch (e) {
                console.warn('Error playing background music:', e);
            }
        }

        await this.audio.main.play();
    }

    async playOutro() {
        this.currentPhase = 'outro';
        this.updatePhaseUI('outro');

        if (this.audio.background) {
            this.audio.background.pause();
        }

        this.audio.outro.currentTime = 0;
        await this.audio.outro.play();
    }

    async resumeCurrentPhase() {
        switch (this.currentPhase) {
            case 'intro':
                if (this.audio.intro) {
                    await this.audio.intro.play();
                }
                break;
            case 'main':
                await this.audio.main.play();
                if (this.audio.background) {
                    try {
                        await this.audio.background.play();
                    } catch (e) {
                        console.warn('Error resuming background music:', e);
                    }
                }
                break;
            case 'outro':
                if (this.audio.outro) {
                    await this.audio.outro.play();
                }
                break;
        }
    }

    async onIntroEnded() {
        if (this.isPlaying) {
            try {
                await this.playMain();
            } catch (e) {
                this.showError('Error al reproducir el audio principal');
                this.pause();
            }
        }
    }

    async onMainEnded() {
        if (this.audio.background) {
            this.audio.background.pause();
        }

        if (this.isPlaying && this.audio.outro) {
            try {
                await this.playOutro();
            } catch (e) {
                this.onPlaybackEnded();
            }
        } else {
            this.onPlaybackEnded();
        }
    }

    onOutroEnded() {
        this.onPlaybackEnded();
    }

    onPlaybackEnded() {
        this.isPlaying = false;
        this.currentPhase = 'none';
        this.currentTime = 0;

        Object.entries(this.audio).forEach(([key, audio]) => {
            if (audio && key !== 'background') {
                audio.currentTime = 0;
            }
        });

        this.updatePlayButton();
        this.renderProgress();
        this.updatePhaseUI('');
        this.trackAnalytics('ended');
    }

    /* ------------------------------------------------------------------ *
     * Seeking (unified timeline across intro + main + outro)
     * ------------------------------------------------------------------ */

    seekFromEvent(e) {
        if (!this.totalDuration || !this.el.progress) {
            return;
        }

        const rect = this.el.progress.getBoundingClientRect();
        const percentage = (e.clientX - rect.left) / rect.width;
        this.seekToTime(percentage * this.totalDuration);
    }

    async seekToTime(targetTime) {
        if (!this.audio.main || !this.totalDuration) {
            return;
        }

        targetTime = Math.max(0, Math.min(targetTime, this.totalDuration));

        const wasPlaying = this.isPlaying;
        if (wasPlaying) {
            this.pause();
        }

        if (this.audio.intro && targetTime < this.introDuration) {
            this.currentPhase = 'intro';
            this.audio.intro.currentTime = targetTime;
        } else if (targetTime < this.introDuration + this.mainDuration || !this.audio.outro) {
            this.currentPhase = 'main';
            this.audio.main.currentTime = Math.max(0, targetTime - this.introDuration);
        } else {
            this.currentPhase = 'outro';
            this.audio.outro.currentTime = targetTime - this.introDuration - this.mainDuration;
        }

        this.currentTime = targetTime;
        this.renderProgress();
        this.updatePhaseUI(this.currentPhase);

        if (wasPlaying) {
            await this.play();
        }
    }

    /* ------------------------------------------------------------------ *
     * Volume
     * ------------------------------------------------------------------ */

    setVoiceVolume(volume) {
        volume = Math.max(0, Math.min(1, volume));

        ['main', 'intro', 'outro'].forEach((key) => {
            if (this.audio[key]) {
                this.audio[key].volume = volume;
            }
        });

        if (this.el.voiceSlider) {
            this.updateVolumeDisplay(this.el.voiceSlider, volume);
        }
    }

    setBackgroundVolume(volume) {
        volume = Math.max(0, Math.min(1, volume));
        this.backgroundVolume = volume;

        if (this.audio.background) {
            this.audio.background.volume = volume;
        }

        if (this.el.backgroundSlider) {
            this.updateVolumeDisplay(this.el.backgroundSlider, volume);
        }
    }

    updateVolumeDisplay(slider, volume) {
        const volumeValue = slider.parentElement ? slider.parentElement.querySelector('.volume-value') : null;
        if (volumeValue) {
            volumeValue.textContent = `${Math.round(volume * 100)}%`;
        }
    }

    /* ------------------------------------------------------------------ *
     * Rendering
     * ------------------------------------------------------------------ */

    renderProgress() {
        if (this.el.currentTime) {
            this.el.currentTime.textContent = this.formatTime(this.currentTime);
        }

        let percentage = 0;
        if (this.totalDuration) {
            percentage = (this.currentTime / this.totalDuration) * 100;
        }

        if (this.el.progressBar) {
            this.el.progressBar.style.width = `${percentage}%`;
        }

        if (this.el.progressHandle) {
            this.el.progressHandle.style.left = `${percentage}%`;
        }

        if (this.el.progress) {
            this.el.progress.setAttribute('aria-valuenow', Math.round(percentage));
            this.el.progress.setAttribute('aria-valuetext',
                this.formatTime(this.currentTime) + ' de ' + this.formatTime(this.totalDuration));
        }

        this.onProgressRender(percentage);
    }

    /** Variant hook: extra rendering per progress tick (waveform, etc.). */
    onProgressRender(percentage) {}

    updatePlayButton() {
        if (!this.el.playBtn) {
            return;
        }
        this.el.playBtn.classList.toggle('playing', this.isPlaying);
        this.el.playBtn.setAttribute('aria-pressed', this.isPlaying ? 'true' : 'false');
        this.el.playBtn.setAttribute('aria-label', this.isPlaying ? 'Pausar' : 'Reproducir');
        this.renderPlayState(this.isPlaying);
    }

    /** Variant hook: custom play-button presentation. */
    renderPlayState(isPlaying) {}

    /** Variant hook: phase indicator ('' | intro | main | outro). */
    updatePhaseUI(phase) {}

    /* ------------------------------------------------------------------ *
     * Loading / error states
     * ------------------------------------------------------------------ */

    showLoading() {
        this.isLoading = true;
        if (this.el.loading) {
            this.el.loading.style.display = 'flex';
        }
        this.hideError();
    }

    hideLoading() {
        this.isLoading = false;
        if (this.el.loading) {
            this.el.loading.style.display = 'none';
        }
    }

    showError(message) {
        this.hideLoading();
        if (this.el.error) {
            this.el.error.textContent = message;
            this.el.error.style.display = 'block';
        } else {
            console.error('TTS Player Error:', message);
        }
    }

    hideError() {
        if (this.el.error) {
            this.el.error.style.display = 'none';
        }
    }

    retry() {
        this.hideError();
        if (this.audio.main && this.audio.main.load) {
            this.audio.main.load();
            this.showLoading();
        }
    }

    handleAudioError(track, error) {
        console.error(`Audio error (${track}):`, error);

        const audio = this.audio[track];
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
            // External (e.g. Buzzsprout) audio may not expose metadata due to
            // CORS; that is not a playback failure, so don't alarm the visitor.
            if (audio && audio.src && !audio.src.includes(window.location.hostname)) {
                console.info(`External audio (${track}): metadata may be limited by CORS policy`);
                return;
            }
            errorMessage = 'Error al cargar los metadatos de audio';
        }

        if (track === 'main') {
            this.showError(errorMessage);
            this.pause();
        } else {
            console.warn(`${track} audio error: ${errorMessage}`);
        }
    }

    /* ------------------------------------------------------------------ *
     * Utilities / public API
     * ------------------------------------------------------------------ */

    formatTime(seconds) {
        if (isNaN(seconds) || !isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    trackAnalytics(action) {
        if (typeof wpTTSAnalytics !== 'undefined') {
            wpTTSAnalytics.track(this.label, action, {
                phase: this.currentPhase,
                duration: this.totalDuration,
                currentTime: this.currentTime,
                playbackRate: this.playbackRate
            });
        }

        if (typeof gtag !== 'undefined') {
            gtag('event', 'tts_' + this.label + '_' + action, {
                event_category: 'TTS',
                event_label: this.label,
                value: Math.round(this.currentTime)
            });
        }
    }

    getCurrentTime() {
        return this.currentTime;
    }

    getDuration() {
        return this.totalDuration;
    }

    getTotalDuration() {
        return this.totalDuration;
    }

    getCurrentPhase() {
        return this.currentPhase;
    }

    getPhase() {
        return this.currentPhase;
    }

    getPlaybackRate() {
        return this.playbackRate;
    }

    isCurrentlyPlaying() {
        return this.isPlaying;
    }

    destroy() {
        Object.values(this.audio).forEach((audio) => {
            if (audio) {
                audio.pause();
                audio.src = '';
            }
        });
        this.audio = { main: null, intro: null, background: null, outro: null };
        this.isPlaying = false;
        this.container.classList.remove('initialized');
    }
}

// Export for the variant scripts
if (typeof window !== 'undefined') {
    window.TTSPlayerCore = TTSPlayerCore;
}

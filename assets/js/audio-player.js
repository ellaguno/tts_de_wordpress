/**
 * Frontend Audio Player JavaScript
 * Handles TTS audio player functionality on the frontend
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeTTSPlayers();
    });

    /**
     * Initialize all TTS audio players on the page
     */
    function initializeTTSPlayers() {
        $('.wp-tts-audio-player').each(function() {
            var $player = $(this);
            var $audio = $player.find('.wp-tts-audio-element');
            
            if ($audio.length) {
                setupAudioPlayer($player, $audio);
            }
        });
    }

    /**
     * Setup individual audio player
     */
    function setupAudioPlayer($player, $audio) {
        var audio = $audio[0];
        var playerId = $player.attr('id');

        // Add loading state
        $audio.on('loadstart', function() {
            $player.addClass('wp-tts-loading');
        });

        // Remove loading state when ready
        $audio.on('canplay', function() {
            $player.removeClass('wp-tts-loading');
        });

        // Handle play event
        $audio.on('play', function() {
            $player.addClass('wp-tts-playing');
            
            // Pause other TTS players
            $('.wp-tts-audio-player').not($player).each(function() {
                var otherAudio = $(this).find('.wp-tts-audio-element')[0];
                if (otherAudio && !otherAudio.paused) {
                    otherAudio.pause();
                }
            });

            // Analytics/tracking (optional)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'tts_play', {
                    'event_category': 'TTS Audio',
                    'event_label': playerId || 'unknown'
                });
            }

            // Fire custom event
            $(document).trigger('wp-tts-play', {
                player: $player,
                audio: audio
            });
        });

        // Handle pause event
        $audio.on('pause', function() {
            $player.removeClass('wp-tts-playing');
            
            // Fire custom event
            $(document).trigger('wp-tts-pause', {
                player: $player,
                audio: audio
            });
        });

        // Handle ended event
        $audio.on('ended', function() {
            $player.removeClass('wp-tts-playing');
            
            // Analytics/tracking (optional)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'tts_complete', {
                    'event_category': 'TTS Audio',
                    'event_label': playerId || 'unknown'
                });
            }

            // Fire custom event
            $(document).trigger('wp-tts-ended', {
                player: $player,
                audio: audio
            });
        });

        // Handle error
        $audio.on('error', function() {
            $player.addClass('wp-tts-error');
            $player.removeClass('wp-tts-loading wp-tts-playing');
            
            // Show error message if not already shown
            if (!$player.find('.wp-tts-error-message').length) {
                var errorMsg = $('<div class="wp-tts-error-message">')
                    .text('Error loading audio. Please try downloading the file.')
                    .css({
                        'color': '#d63638',
                        'font-size': '12px',
                        'margin-top': '8px',
                        'padding': '4px 8px',
                        'background': 'rgba(214, 54, 56, 0.1)',
                        'border-radius': '4px'
                    });
                
                $player.find('.wp-tts-player-controls').after(errorMsg);
            }

            // Fire custom event
            $(document).trigger('wp-tts-error', {
                player: $player,
                audio: audio
            });
        });

        // Handle time updates for progress
        $audio.on('timeupdate', function() {
            var currentTime = audio.currentTime;
            var duration = audio.duration;
            
            if (duration > 0) {
                var progress = (currentTime / duration) * 100;
                
                // Fire custom event with progress
                $(document).trigger('wp-tts-progress', {
                    player: $player,
                    audio: audio,
                    progress: progress,
                    currentTime: currentTime,
                    duration: duration
                });
            }
        });

        // Handle volume changes
        $audio.on('volumechange', function() {
            $(document).trigger('wp-tts-volume', {
                player: $player,
                audio: audio,
                volume: audio.volume,
                muted: audio.muted
            });
        });

        // Keyboard accessibility
        $player.on('keydown', function(e) {
            // Space bar to play/pause
            if (e.which === 32 && e.target === this) {
                e.preventDefault();
                if (audio.paused) {
                    audio.play();
                } else {
                    audio.pause();
                }
            }
        });

        // Download link tracking
        $player.find('.wp-tts-download-link').on('click', function() {
            // Analytics/tracking (optional)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'tts_download', {
                    'event_category': 'TTS Audio',
                    'event_label': playerId || 'unknown'
                });
            }

            // Fire custom event
            $(document).trigger('wp-tts-download', {
                player: $player,
                url: $(this).attr('href')
            });
        });

        // Speed control functionality
        setupSpeedControl($player, $audio);
    }

    /**
     * Setup speed control for player
     */
    function setupSpeedControl($player, $audio) {
        var $speedBtn = $player.find('.wp-tts-speed-btn');
        var $speedMenu = $player.find('.wp-tts-speed-menu');
        var audio = $audio[0];

        if ($speedBtn.length && $speedMenu.length) {
            // Toggle speed menu
            $speedBtn.on('click', function(e) {
                e.stopPropagation();
                $speedMenu.toggle();
            });

            // Handle speed selection
            $speedMenu.find('button').on('click', function(e) {
                e.stopPropagation();
                var speed = parseFloat($(this).data('speed'));
                
                if (audio && !isNaN(speed)) {
                    audio.playbackRate = speed;
                    
                    // Update active state
                    $speedMenu.find('button').removeClass('active');
                    $(this).addClass('active');
                    
                    // Hide menu
                    $speedMenu.hide();
                    
                    // Fire custom event
                    $(document).trigger('wp-tts-speed-change', {
                        player: $player,
                        audio: audio,
                        speed: speed
                    });
                }
            });

            // Close menu when clicking outside
            $(document).on('click', function() {
                $speedMenu.hide();
            });
        }
    }

    /**
     * Public API for external control
     */
    window.WPTTS = {
        // Play specific player
        play: function(playerId) {
            var $player = $('#' + playerId);
            if ($player.length) {
                var audio = $player.find('.wp-tts-audio-element')[0];
                if (audio) {
                    audio.play();
                }
            }
        },

        // Pause specific player
        pause: function(playerId) {
            var $player = $('#' + playerId);
            if ($player.length) {
                var audio = $player.find('.wp-tts-audio-element')[0];
                if (audio) {
                    audio.pause();
                }
            }
        },

        // Pause all players
        pauseAll: function() {
            $('.wp-tts-audio-element').each(function() {
                if (!this.paused) {
                    this.pause();
                }
            });
        },

        // Get player status
        getStatus: function(playerId) {
            var $player = $('#' + playerId);
            if ($player.length) {
                var audio = $player.find('.wp-tts-audio-element')[0];
                if (audio) {
                    return {
                        playing: !audio.paused,
                        currentTime: audio.currentTime,
                        duration: audio.duration,
                        volume: audio.volume,
                        muted: audio.muted,
                        error: audio.error
                    };
                }
            }
            return null;
        }
    };

    // Auto-play functionality (if enabled)
    $(document).on('wp-tts-autoplay', function(e, data) {
        if (data && data.playerId) {
            window.WPTTS.play(data.playerId);
        }
    });

})(jQuery);
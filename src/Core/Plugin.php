<?php

namespace WP_TTS\Core;

use WP_TTS\Admin\AdminInterface;
use WP_TTS\Services\TTSService;
use WP_TTS\Services\CacheService;
use WP_TTS\Services\RoundRobinManager;
use WP_TTS\Utils\SecurityManager;
use WP_TTS\Utils\Logger;

/**
 * Main Plugin Class
 *
 * This class serves as the main entry point for the WordPress TTS Plugin.
 * It handles initialization, dependency injection, and coordinates all plugin components.
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Service container
	 *
	 * @var ServiceContainer
	 */
	private $container;

	/**
	 * Configuration manager
	 *
	 * @var ConfigurationManager
	 */
	private $config;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin constructor
	 */
	private function __construct() {
		$this->version = WP_TTS_PLUGIN_VERSION;
		$this->initializeContainer();
		$this->loadConfiguration();
	}

	/**
	 * Get plugin instance (Singleton pattern)
	 *
	 * @return Plugin
	 */
	public static function getInstance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the service container
	 */
	private function initializeContainer(): void {
		$this->container = new ServiceContainer();
		$this->registerServices();
	}

	/**
	 * Load plugin configuration
	 */
	private function loadConfiguration(): void {
		$this->config = new ConfigurationManager();
		$this->container->set( 'config', $this->config );
	}

	/**
	 * Register all services in the container
	 */
	private function registerServices(): void {
		// Core services
		$this->container->set(
			'logger',
			function() {
				return new Logger();
			}
		);

		$this->container->set(
			'security',
			function() {
				return new SecurityManager();
			}
		);

		$this->container->set(
			'cache',
			function() {
				return new CacheService();
			}
		);

		$this->container->set(
			'round_robin',
			function() {
				return new RoundRobinManager( $this->container->get( 'config' ) );
			}
		);

		$this->container->set(
			'tts_service',
			function() {
				return new TTSService(
					$this->container->get( 'round_robin' ),
					$this->container->get( 'cache' ),
					$this->container->get( 'logger' )
				);
			}
		);

		// Admin interface
		$this->container->set(
			'admin',
			function() {
				return new AdminInterface(
					$this->container->get( 'config' ),
					$this->container->get( 'tts_service' ),
					$this->container->get( 'security' )
				);
			}
		);
	}

	/**
	 * Run the plugin
	 */
	public function run(): void {
		// Load text domain for internationalization
		add_action( 'init', array( $this, 'loadTextDomain' ) );

		// Initialize admin interface
		if ( is_admin() ) {
			$this->container->get( 'admin' )->init();
		}

		// Initialize frontend functionality
		add_action( 'init', array( $this, 'initFrontend' ) );

		// Register WordPress hooks
		$this->registerHooks();

		// Schedule cron jobs
		$this->scheduleCronJobs();
	}

	/**
	 * Load plugin text domain for internationalization
	 *
	 * Note: This function is kept for backwards compatibility and local testing.
	 * When hosted on WordPress.org, translations are loaded automatically.
	 */
	public function loadTextDomain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Kept for backwards compatibility and local testing
		load_plugin_textdomain(
			'tts-sesolibre',
			false,
			dirname( WP_TTS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize frontend functionality
	 */
	public function initFrontend(): void {
		// Register shortcodes
		add_shortcode( 'wp_tts_player', array( $this, 'renderAudioPlayerShortcode' ) );

		// Enqueue frontend scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueFrontendAssets' ) );

		// Add audio player to content if enabled
		add_filter( 'the_content', array( $this, 'maybeAddAudioPlayer' ) );
	}

	/**
	 * Register WordPress hooks
	 */
	private function registerHooks(): void {
		// Post meta box
		add_action( 'add_meta_boxes', array( $this, 'addTTSMetaBox' ) );
		add_action( 'save_post', array( $this, 'saveTTSSettings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueMetaboxAssets' ) );

		// AJAX handlers
		add_action( 'wp_ajax_tts_generate_audio', array( $this, 'handleGenerateAudio' ) );
		add_action( 'wp_ajax_tts_validate_provider', array( $this, 'handleValidateProvider' ) );
		add_action( 'wp_ajax_tts_delete_audio', array( $this, 'handleDeleteAudio' ) );

		// Register meta-box specific AJAX handler with different action name to avoid conflicts
		add_action( 'wp_ajax_tts_get_voices_metabox', array( $this, 'handleGetVoicesForMetaBox' ) );

		// Auto-save handlers for meta box
		add_action( 'wp_ajax_tts_auto_save_enabled', array( $this, 'handleAutoSaveEnabled' ) );
		add_action( 'wp_ajax_tts_auto_save_provider', array( $this, 'handleAutoSaveProvider' ) );
		add_action( 'wp_ajax_tts_auto_save_voice', array( $this, 'handleAutoSaveVoice' ) );
		add_action( 'wp_ajax_tts_load_default_assets', array( $this, 'handleLoadDefaultAssets' ) );

		// Auto-generation on post publish
		add_action( 'transition_post_status', array( $this, 'handlePostStatusTransition' ), 10, 3 );

		// Background audio generation handler
		add_action( 'wp_tts_generate_audio_background', array( $this, 'handleBackgroundAudioGeneration' ) );

		// Auto-generation background handler
		add_action( 'wp_tts_auto_generate_audio', array( $this, 'handleAutoGenerateAudio' ) );

		// Custom hooks for extensibility
		do_action( 'wp_tts_plugin_loaded', $this );
	}

	/**
	 * Schedule cron jobs
	 */
	private function scheduleCronJobs(): void {
		// Cache cleanup
		if ( ! wp_next_scheduled( 'wp_tts_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'wp_tts_cache_cleanup' );
		}

		// Usage analytics
		if ( ! wp_next_scheduled( 'wp_tts_analytics_update' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_tts_analytics_update' );
		}

		// Register cron handlers
		add_action( 'wp_tts_cache_cleanup', array( $this->container->get( 'cache' ), 'cleanExpiredCache' ) );
	}

	/**
	 * Add TTS meta box to posts and pages
	 */
	public function addTTSMetaBox(): void {
		$post_types = apply_filters( 'wp_tts_supported_post_types', array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'wp-tts-settings',
				__( 'Configuración de Texto a Voz', 'tts-sesolibre' ),
				array( $this, 'renderTTSMetaBox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render TTS meta box
	 *
	 * @param \WP_Post $post
	 */
	public function renderTTSMetaBox( $post ): void {
		// Security nonce
		wp_nonce_field( 'wp_tts_meta_box', 'wp_tts_meta_nonce' );

		// Get current settings with fallback
		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			// Use unified system
			$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post->ID );
			
			$enabled     = (bool) $tts_data['enabled']; // Ensure boolean
			$provider    = $tts_data['voice']['provider'];
			$voice_id    = $tts_data['voice']['voice_id'];
			$custom_text = $tts_data['content']['custom_text'];
			$audio_url   = $tts_data['audio']['url'];
			$status      = $tts_data['audio']['status'];
		} else {
			// Fallback to old system
			$enabled     = (bool) get_post_meta( $post->ID, '_tts_enabled', true );
			$provider    = get_post_meta( $post->ID, '_tts_voice_provider', true );
			$voice_id    = get_post_meta( $post->ID, '_tts_voice_id', true );
			$custom_text = get_post_meta( $post->ID, '_tts_custom_text', true );
			$audio_url   = get_post_meta( $post->ID, '_tts_audio_url', true );
			$status      = get_post_meta( $post->ID, '_tts_generation_status', true );
		}

		// Apply defaults if no specific provider/voice is set
		$config = new ConfigurationManager();
		$defaults = $config->getDefaults();
		
		// If no provider is set, use default provider
		if ( empty( $provider ) && ! empty( $defaults['default_provider'] ) ) {
			$provider = $defaults['default_provider'];
		}
		
		// If no voice is set and we have a provider, use the provider's default voice
		if ( empty( $voice_id ) && ! empty( $provider ) ) {
			$provider_config = $config->getProviderConfig( $provider );
			if ( ! empty( $provider_config['default_voice'] ) ) {
				$voice_id = $provider_config['default_voice'];
			}
		}

		// Include meta box template
		include WP_TTS_PLUGIN_DIR . 'templates/admin/meta-box.php';
	}

	/**
	 * Save TTS settings
	 *
	 * @param int $post_id
	 */
	public function saveTTSSettings( $post_id ): void {
		// Verify nonce
		if ( ! isset( $_POST['wp_tts_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_tts_meta_nonce'] ) ), 'wp_tts_meta_box' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$security = $this->container->get( 'security' );

		// Save TTS settings with fallback
		$enabled = isset( $_POST['tts_enabled'] ) ? true : false;

		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			// Use unified system
			\WP_TTS\Utils\TTSMetaManager::setTTSEnabled( $post_id, $enabled );

			if ( isset( $_POST['tts_voice_provider'] ) ) {
				$provider = sanitize_text_field( wp_unslash( $_POST['tts_voice_provider'] ) );
				$voice_id = '';
				if ( isset( $_POST['tts_voice_id'] ) ) {
					$voice_id = sanitize_text_field( wp_unslash( $_POST['tts_voice_id'] ) );
				}
				\WP_TTS\Utils\TTSMetaManager::setVoiceConfig( $post_id, $provider, $voice_id );
			}

			if ( isset( $_POST['tts_custom_text'] ) ) {
				$custom_text = sanitize_textarea_field( wp_unslash( $_POST['tts_custom_text'] ) );
				$use_custom = !empty( $custom_text );
				\WP_TTS\Utils\TTSMetaManager::setCustomText( $post_id, $custom_text, $use_custom );
			}

			// Process custom audio field
			if ( isset( $_POST['tts_custom_audio'] ) ) {
				$custom_audio_id = intval( $_POST['tts_custom_audio'] );
				$current_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
				$current_data['audio_assets']['custom_audio'] = $custom_audio_id;
				\WP_TTS\Utils\TTSMetaManager::saveTTSData( $post_id, $current_data );
			}
		} else {
			// Fallback to old system
			update_post_meta( $post_id, '_tts_enabled', $enabled );

			if ( isset( $_POST['tts_voice_provider'] ) ) {
				$provider = sanitize_text_field( wp_unslash( $_POST['tts_voice_provider'] ) );
				update_post_meta( $post_id, '_tts_voice_provider', $provider );

				if ( isset( $_POST['tts_voice_id'] ) ) {
					$voice_id = sanitize_text_field( wp_unslash( $_POST['tts_voice_id'] ) );
					update_post_meta( $post_id, '_tts_voice_id', $voice_id );
				}
			}

			if ( isset( $_POST['tts_custom_text'] ) ) {
				$custom_text = sanitize_textarea_field( wp_unslash( $_POST['tts_custom_text'] ) );
				update_post_meta( $post_id, '_tts_custom_text', $custom_text );
			}

			// Process custom audio field (fallback)
			if ( isset( $_POST['tts_custom_audio'] ) ) {
				$custom_audio_id = intval( $_POST['tts_custom_audio'] );
				update_post_meta( $post_id, '_tts_custom_audio', $custom_audio_id );
			}
		}

		// Trigger audio generation if enabled and settings changed
		if ( $enabled && $this->shouldRegenerateAudio( $post_id ) ) {
			$this->scheduleAudioGeneration( $post_id );
		}

		do_action( 'wp_tts_settings_saved', $post_id, $_POST );
	}

	/**
	 * Handle AJAX audio generation request
	 */
	public function handleGenerateAudio(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_generate_audio' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed', 'tts-sesolibre' )
			], 403 );
			return;
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid post ID', 'tts-sesolibre' )
			] );
			return;
		}

		$this->container->get( 'logger' )->info( 'Starting audio generation from AJAX', [
			'post_id' => $post_id
		] );

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$result      = $tts_service->generateAudioForPost( $post_id );

			if ( $result && isset( $result->url ) && ! empty( $result->url ) ) {
				$this->container->get( 'logger' )->info( 'Audio generation successful via AJAX', [
					'post_id' => $post_id,
					'audio_url' => $result->url
				] );

				wp_send_json_success( [
					'audio_url' => $result->url,
					'duration'  => $result->duration ?? 0,
					'provider'  => $result->provider ?? '',
					'message'   => __( 'Audio generated successfully', 'tts-sesolibre' ),
				] );
			} else {
				$this->container->get( 'logger' )->error( 'Audio generation returned invalid result', [
					'post_id' => $post_id,
					'result' => $result
				] );

				wp_send_json_error( [
					'message' => __( 'Audio generation failed: Invalid result returned', 'tts-sesolibre' ),
				] );
			}
		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error( 'Audio generation failed with exception', [
				'post_id' => $post_id,
				'error'   => esc_html( $e->getMessage() ),
				'trace'   => $e->getTraceAsString()
			] );

			wp_send_json_error( [
				'message' => __( 'Audio generation failed', 'tts-sesolibre' ),
				'error'   => esc_html( $e->getMessage() ),
			] );
		}
	}



	/**
	 * Handle AJAX provider validation request
	 */
	public function handleValidateProvider(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_validate_provider' ) ||
			! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Security verification failed', 'tts-sesolibre' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$is_valid = $tts_service->validateProvider( $provider );

			wp_send_json_success(
				array(
					'valid' => $is_valid,
					'message' => $is_valid ?
						__( 'Provider configuration is valid', 'tts-sesolibre' ) :
						__( 'Provider configuration is invalid', 'tts-sesolibre' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Provider validation failed', 'tts-sesolibre' ),
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Handle get voices AJAX request for meta box
	 */
	public function handleGetVoicesForMetaBox(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_admin' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed', 'tts-sesolibre' )
			], 403 );
			return;
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$voices = $tts_service->getAvailableVoices( $provider );

			wp_send_json_success( [
				'provider' => $provider,
				'voices' => $voices,
				'count' => count($voices)
			] );

		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error( 'Failed to get voices for meta box', [
				'provider' => $provider,
				'error' => esc_html( $e->getMessage() )
			] );

			wp_send_json_error( [
				'message' => 'Failed to load voices: ' . esc_html( $e->getMessage() ),
				'provider' => $provider
			] );
		}
	}

	/**
	 * Handle AJAX delete audio request
	 */
	public function handleDeleteAudio(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_delete_audio' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Security verification failed', 'tts-sesolibre' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		try {
			// Get audio URL and TTS data
			$audio_url = '';
			$tts_data = [];

			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
				$audio_url = $tts_data['audio']['url'] ?? '';
			} else {
				$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );
			}

			if ( $audio_url ) {
				// Try to delete from storage provider first
				$deleted_from_storage = $this->deleteAudioFromStorage( $audio_url, $post_id );

				// If not deleted from storage provider, try local file
				if ( ! $deleted_from_storage ) {
					$upload_dir = wp_upload_dir();
					$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $audio_url );

					if ( file_exists( $file_path ) ) {
						wp_delete_file( $file_path );
						$this->container->get( 'logger' )->info( 'Local audio file deleted', [
							'post_id' => $post_id,
							'file_path' => $file_path
						] );
					}
				}
			}

			// Clear cache for this post
			$cache_service = $this->container->get( 'cache' );
			if ( $cache_service && ! empty( $tts_data['generation']['cache_key'] ) ) {
				$cache_service->delete( $tts_data['generation']['cache_key'] );
			}

			// Complete reset of TTS data (keeps enabled state and voice config)
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				\WP_TTS\Utils\TTSMetaManager::resetTTSData( $post_id, true );
			} else {
				// Fallback to old system - complete cleanup
				delete_post_meta( $post_id, '_tts_audio_url' );
				delete_post_meta( $post_id, '_tts_generated_at' );
				delete_post_meta( $post_id, '_tts_generation_status' );
				delete_post_meta( $post_id, '_tts_last_generated' );
			}

			$this->container->get( 'logger' )->info( 'Audio completely deleted for post', [
				'post_id' => $post_id
			] );

			wp_send_json_success(
				array(
					'message' => __( 'Audio deleted successfully', 'tts-sesolibre' ),
				)
			);
		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error(
				'Audio deletion failed',
				array(
					'post_id' => $post_id,
					'error'   => esc_html( $e->getMessage() ),
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Audio deletion failed', 'tts-sesolibre' ),
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Delete audio from storage provider
	 *
	 * @param string $audio_url Audio URL to delete
	 * @param int    $post_id   Post ID for context
	 * @return bool True if deleted from storage provider
	 */
	private function deleteAudioFromStorage( string $audio_url, int $post_id ): bool {
		// Check if this is a Buzzsprout URL
		if ( strpos( $audio_url, 'buzzsprout' ) !== false ) {
			try {
				$buzzsprout_config = $this->config->getStorageConfig( 'buzzsprout' );
				if ( ! empty( $buzzsprout_config['enabled'] ) && ! empty( $buzzsprout_config['api_token'] ) ) {
					$storage_factory = new \WP_TTS\Core\StorageProviderFactory();
					$buzzsprout = $storage_factory->create( 'buzzsprout', $buzzsprout_config );
					if ( $buzzsprout && method_exists( $buzzsprout, 'deleteAudio' ) ) {
						$result = $buzzsprout->deleteAudio( $audio_url );
						$this->container->get( 'logger' )->info( 'Audio deleted from Buzzsprout', [
							'post_id' => $post_id,
							'url' => $audio_url,
							'result' => $result
						] );
						return $result;
					}
				}
			} catch ( \Exception $e ) {
				$this->container->get( 'logger' )->error( 'Failed to delete from Buzzsprout', [
					'post_id' => $post_id,
					'error' => esc_html( $e->getMessage() )
				] );
			}
		}

		// Check if this is an S3 URL
		if ( strpos( $audio_url, 's3.amazonaws.com' ) !== false || strpos( $audio_url, 'amazonaws.com' ) !== false ) {
			try {
				$s3_config = $this->config->getStorageConfig( 's3' );
				if ( ! empty( $s3_config['enabled'] ) ) {
					// TODO: Implement S3 deletion when S3 storage provider is fully implemented
					$this->container->get( 'logger' )->info( 'S3 deletion not yet implemented', [
						'post_id' => $post_id,
						'url' => $audio_url
					] );
				}
			} catch ( \Exception $e ) {
				$this->container->get( 'logger' )->error( 'Failed to delete from S3', [
					'post_id' => $post_id,
					'error' => esc_html( $e->getMessage() )
				] );
			}
		}

		return false;
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueueFrontendAssets(): void {
		wp_enqueue_style(
			'wp-tts-frontend',
			WP_TTS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$this->version
		);

		// Enqueue player styles only when needed
		if ( is_single() || is_page() ) {
			$post_id = get_the_ID();
			
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$enabled = \WP_TTS\Utils\TTSMetaManager::isTTSEnabled( $post_id );
				$audio_url = \WP_TTS\Utils\TTSMetaManager::getAudioUrl( $post_id );
			} else {
				$enabled = get_post_meta( $post_id, '_tts_enabled', true );
				$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );
			}
			
			if ( $enabled && $audio_url ) {
				// Get current player style
				$player_style = $this->config->get('player.style', 'classic');
				
				// Always enqueue common player styles
				wp_enqueue_style(
					'wp-tts-player',
					WP_TTS_PLUGIN_URL . 'assets/css/frontend-player.css',
					array(),
					$this->version
				);
				
				// Enqueue style-specific assets based on player type
				switch ($player_style) {
					case 'tts-sesolibre':
						wp_enqueue_style(
							'wp-tts-sesolibre-player',
							WP_TTS_PLUGIN_URL . 'assets/css/tts-player.css',
							array(),
							$this->version
						);
						
						wp_enqueue_script(
							'wp-tts-sesolibre-player',
							WP_TTS_PLUGIN_URL . 'assets/js/tts-player.js',
							array( 'jquery' ),
							$this->version,
							true
						);
						break;
						
					case 'minimal':
						wp_enqueue_style(
							'wp-tts-minimal-player',
							WP_TTS_PLUGIN_URL . 'assets/css/minimal-player.css',
							array(),
							$this->version
						);
						
						wp_enqueue_script(
							'wp-tts-minimal-player',
							WP_TTS_PLUGIN_URL . 'assets/js/minimal-player.js',
							array( 'jquery' ),
							$this->version,
							true
						);
						break;
						
					case 'enhanced_sesolibre':
						wp_enqueue_style(
							'wp-tts-enhanced-sesolibre-player',
							WP_TTS_PLUGIN_URL . 'assets/css/enhanced-sesolibre-player.css',
							array(),
							$this->version
						);
						
						wp_enqueue_script(
							'wp-tts-enhanced-sesolibre-player',
							WP_TTS_PLUGIN_URL . 'assets/js/enhanced-sesolibre-player.js',
							array( 'jquery' ),
							$this->version . '-' . time(),
							true
						);
						break;
						
					case 'classic':
					default:
						// Classic player uses the common styles already loaded
						break;
				}
			}
		}

		wp_enqueue_script(
			'wp-tts-frontend',
			WP_TTS_PLUGIN_URL . 'assets/js/audio-player.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Enqueue metabox assets
	 */
	public function enqueueMetaboxAssets( $hook ): void {
		// Only load on post edit screens
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
			return;
		}
		
		// Check if this post type supports TTS
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, [ 'post', 'page' ] ) ) {
			return;
		}
		
		// Enqueue WordPress media library
		wp_enqueue_media();
		
		// Enqueue admin styles
		wp_enqueue_style(
			'wp-tts-admin',
			WP_TTS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			$this->version
		);
		
		// Enqueue admin scripts
		wp_enqueue_script(
			'wp-tts-admin',
			WP_TTS_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'media-upload', 'media-views' ],
			$this->version,
			true
		);
		
		// Enqueue TTS Player assets for preview
		wp_enqueue_style(
			'wp-tts-player',
			WP_TTS_PLUGIN_URL . 'assets/css/tts-player.css',
			[],
			$this->version
		);
		
		wp_enqueue_script(
			'wp-tts-player-admin',
			WP_TTS_PLUGIN_URL . 'assets/js/tts-player.js',
			[ 'jquery' ],
			$this->version,
			true
		);
		
		// Localize script for AJAX calls
		wp_localize_script( 'wp-tts-admin', 'wpTtsMetabox', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_tts_auto_save' ),
			'adminNonce' => wp_create_nonce( 'wp_tts_admin' ),
			'generateNonce' => wp_create_nonce( 'wp_tts_generate_audio' ),
			'deleteNonce' => wp_create_nonce( 'wp_tts_delete_audio' ),
			'mediaTitle' => __( 'Seleccionar Archivo de Audio', 'tts-sesolibre' ),
			'mediaButton' => __( 'Usar este audio', 'tts-sesolibre' ),
		] );
		
		// Add inline script for media handling - ensure media library is available
		wp_add_inline_script( 'jquery', '
			// Media library initialization check (no console output in production)
			if (typeof wp === "undefined" || !wp.media) {
				// Media library not available - will be loaded when needed
			}
		' );
	}

	/**
	 * Render audio player shortcode
	 *
	 * @param array $atts
	 * @return string
	 */
	public function renderAudioPlayerShortcode( $atts ): string {
		// Get configured player style
		$config = new \WP_TTS\Core\ConfigurationManager();
		$default_style = $config->get('player.style', 'classic');
		
		$atts = shortcode_atts(
			array(
				'post_id' => get_the_ID(),
				'style'   => $default_style, // Use configured player style
			),
			$atts
		);

		$post_id = intval( $atts['post_id'] );
		$style   = $atts['style'];
		
		// Get main audio URL
		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			$main_audio_url = \WP_TTS\Utils\TTSMetaManager::getAudioUrl( $post_id );
			$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
		} else {
			$main_audio_url = get_post_meta( $post_id, '_tts_audio_url', true );
			$tts_data = [];
		}

		if ( ! $main_audio_url ) {
			return '';
		}
		
		// Player configuration from global settings
		$player_config = [
			'show_voice_volume' => $config->get('player.show_voice_volume', true),
			'show_background_volume' => $config->get('player.show_background_volume', true),
			'show_tts_service' => $config->get('player.show_tts_service', true),
			'show_voice_name' => $config->get('player.show_voice_name', true),
			'show_download_link' => $config->get('player.show_download_link', true),
			'show_article_title' => $config->get('player.show_article_title', true),
			'show_featured_image' => $config->get('player.show_featured_image', true),
			'show_speed_control' => $config->get('player.show_speed_control', true)
		];

		// Choose template based on style
		switch ($style) {
			case 'tts-sesolibre':
				$template_file = 'templates/frontend/tts-player.php';
				break;
			case 'minimal':
				$template_file = 'templates/frontend/minimal-player.php';
				break;
			case 'enhanced_sesolibre':
				$template_file = 'templates/frontend/enhanced-sesolibre-player.php';
				break;
			case 'classic':
			default:
				$template_file = 'templates/frontend/audio-player.php';
				break;
		}

		// Make variables available to the template
		ob_start();
		include WP_TTS_PLUGIN_DIR . $template_file;
		return ob_get_clean();
	}

	/**
	 * Maybe add audio player to content
	 *
	 * @param string $content
	 * @return string
	 */
	public function maybeAddAudioPlayer( $content ): string {
		// Debug: Check if we're on the right page type
		if ( ! is_single() && ! is_page() ) {
			return $content;
		}

		// Reload configuration to ensure we have the latest settings
		$this->config = new ConfigurationManager();
		
		// Debug: Check if auto-insert is enabled globally
		$auto_insert = $this->config->get('player.auto_insert', false);

		// Debug for troubleshooting - now visible to all users when tts_debug is set
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug parameter, read-only operation
		if ( isset($_GET['tts_debug']) ) {
			$all_player_config = $this->config->get('player', []);
			$wp_option_data = get_option('wp_tts_player_settings', 'NOT_FOUND');
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug output only when tts_debug is set
			$debug_info = "<!-- TTS DEBUG: auto_insert=" . ($auto_insert ? 'true' : 'false') .
			             ", full_player_config=" . print_r($all_player_config, true) .
			             ", wp_option_data=" . print_r($wp_option_data, true) . " -->";
			// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$content = $debug_info . $content;
		}
		
		if ( ! $auto_insert ) {
			return $content;
		}

		$post_id = get_the_ID();
		
		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			$enabled   = \WP_TTS\Utils\TTSMetaManager::isTTSEnabled( $post_id );
			$audio_url = \WP_TTS\Utils\TTSMetaManager::getAudioUrl( $post_id );
		} else {
			$enabled   = get_post_meta( $post_id, '_tts_enabled', true );
			$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );
		}

		// More debug info
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug parameter, read-only operation
		if ( isset($_GET['tts_debug']) ) {
			$debug_info2 = "<!-- TTS DEBUG: post_id=$post_id, enabled=" . ($enabled ? 'true' : 'false') . ", audio_url=" . ($audio_url ? 'exists' : 'empty') . " -->";
			$content = $debug_info2 . $content;
		}

		if ( $enabled && $audio_url ) {
			// Get configured player style and position
			$player_style = $this->config->get('player.style', 'classic');
			$player_position = $this->config->get('player.position', 'before_content');
			
			// Don't auto-insert if position is set to manual
			if ( $player_position === 'manual' ) {
				return $content;
			}
			
			$player = $this->renderAudioPlayerShortcode( array( 
				'post_id' => $post_id,
				'style'   => $player_style
			) );
			
			// Debug the player output
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Debug parameter, read-only operation
			if ( isset($_GET['tts_debug']) ) {
				$debug_info3 = "<!-- TTS DEBUG: player_style=$player_style, player_position=$player_position, player_length=" . strlen($player) . " -->";
				$content = $debug_info3 . $content;
			}
			
			// Insert player based on configured position
			if ( $player_position === 'after_content' ) {
				$content = $content . $player;
			} else {
				// Default: before_content
				$content = $player . $content;
			}
		}

		return $content;
	}

	/**
	 * Check if audio should be regenerated
	 *
	 * @param int $post_id
	 * @return bool
	 */
	private function shouldRegenerateAudio( $post_id ): bool {
		$last_modified = get_post_modified_time( 'U', false, $post_id );
		
		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
			$last_generated = $tts_data['generation']['last_attempt'];
		} else {
			$last_generated = get_post_meta( $post_id, '_tts_generated_at', true );
		}

		if ( ! $last_generated ) {
			return true;
		}

		$last_generated_timestamp = strtotime( $last_generated );
		return $last_modified > $last_generated_timestamp;
	}

	/**
	 * Schedule audio generation for background processing
	 *
	 * @param int $post_id
	 */
	private function scheduleAudioGeneration( $post_id ): void {
		wp_schedule_single_event( time() + 60, 'wp_tts_generate_audio_background', array( $post_id ) );
	}

	/**
	 * Get service container
	 *
	 * @return ServiceContainer
	 */
	public function getContainer(): ServiceContainer {
		return $this->container;
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * Handle auto-save for TTS enabled state
	 */
	public function handleAutoSaveEnabled(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_auto_save' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed', 'tts-sesolibre' )
			], 403 );
			return;
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1';

		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid post ID', 'tts-sesolibre' )
			] );
			return;
		}

		try {
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				// Check if this is the first time enabling TTS
				$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
				$is_first_time = $enabled && empty( $tts_data['audio_assets'] );
				
				$result = \WP_TTS\Utils\TTSMetaManager::setTTSEnabled( $post_id, $enabled );
				
				if ( $result ) {
					wp_send_json_success( [
						'message' => __( 'Estado de TTS habilitado guardado', 'tts-sesolibre' ),
						'enabled' => $enabled,
						'load_defaults' => $is_first_time
					] );
				} else {
					wp_send_json_error( [
						'message' => __( 'Falló al guardar el estado de TTS habilitado', 'tts-sesolibre' )
					] );
				}
			} else {
				wp_send_json_error( [
					'message' => __( 'TTSMetaManager no disponible', 'tts-sesolibre' )
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Error al guardar el estado de TTS habilitado', 'tts-sesolibre' ),
				'error' => esc_html( $e->getMessage() )
			] );
		}
	}

	/**
	 * Handle auto-save for TTS provider
	 */
	public function handleAutoSaveProvider(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_auto_save' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed', 'tts-sesolibre' )
			], 403 );
			return;
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid post ID', 'tts-sesolibre' )
			] );
			return;
		}

		try {
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				// Get current voice config and update only provider
				$current_config = \WP_TTS\Utils\TTSMetaManager::getVoiceConfig( $post_id );
				$result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig( 
					$post_id, 
					$provider, 
					$current_config['voice_id'] ?? '',
					$current_config['language'] ?? 'es-MX'
				);
				
				if ( $result ) {
					wp_send_json_success( [
						'message' => __( 'Proveedor de TTS guardado', 'tts-sesolibre' ),
						'provider' => $provider
					] );
				} else {
					wp_send_json_error( [
						'message' => __( 'Falló al guardar el proveedor de TTS', 'tts-sesolibre' )
					] );
				}
			} else {
				wp_send_json_error( [
					'message' => __( 'TTSMetaManager no disponible', 'tts-sesolibre' )
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Error al guardar el proveedor de TTS', 'tts-sesolibre' ),
				'error' => esc_html( $e->getMessage() )
			] );
		}
	}

	/**
	 * Handle auto-save for TTS voice
	 */
	public function handleAutoSaveVoice(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_auto_save' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed', 'tts-sesolibre' )
			], 403 );
			return;
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$voice_id = isset( $_POST['voice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['voice_id'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid post ID', 'tts-sesolibre' )
			] );
			return;
		}

		try {
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$result = \WP_TTS\Utils\TTSMetaManager::setVoiceConfig( 
					$post_id, 
					$provider, 
					$voice_id, 
					'es-MX'
				);
				
				if ( $result ) {
					wp_send_json_success( [
						'message' => __( 'Voz de TTS guardada', 'tts-sesolibre' ),
						'provider' => $provider,
						'voice_id' => $voice_id
					] );
				} else {
					wp_send_json_error( [
						'message' => __( 'Falló al guardar la voz de TTS', 'tts-sesolibre' )
					] );
				}
			} else {
				wp_send_json_error( [
					'message' => __( 'TTSMetaManager no disponible', 'tts-sesolibre' )
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Error al guardar la voz de TTS', 'tts-sesolibre' ),
				'error' => esc_html( $e->getMessage() )
			] );
		}
	}

	/**
	 * Handle AJAX request to load default audio assets
	 */
	public function handleLoadDefaultAssets(): void {
		// Verify nonce and permissions
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_tts_auto_save' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security verification failed', 'tts-sesolibre' )
			], 403 );
			return;
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid post ID', 'tts-sesolibre' )
			] );
			return;
		}

		try {
			// Get default audio assets from configuration
			$config = get_option( 'wp_tts_config', [] );
			$default_intro = $config['audio_assets']['default_intro'] ?? '';
			$default_outro = $config['audio_assets']['default_outro'] ?? '';
			$default_background = $config['audio_assets']['default_background'] ?? '';

			// Only load defaults if they exist
			if ( $default_intro || $default_outro || $default_background ) {
				if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
					$audio_assets = [
						'intro_audio' => $default_intro,
						'outro_audio' => $default_outro,
						'background_audio' => $default_background,
						'background_volume' => 0.3
					];

					$result = \WP_TTS\Utils\TTSMetaManager::updateTTSSection( $post_id, 'audio_assets', $audio_assets );
					
					if ( $result ) {
						wp_send_json_success( [
							'message' => __( 'Recursos de audio por defecto cargados', 'tts-sesolibre' ),
							'assets' => $audio_assets
						] );
					} else {
						wp_send_json_error( [
							'message' => __( 'Falló al cargar recursos de audio por defecto', 'tts-sesolibre' )
						] );
					}
				} else {
					wp_send_json_error( [
						'message' => __( 'TTSMetaManager no disponible', 'tts-sesolibre' )
					] );
				}
			} else {
				wp_send_json_success( [
					'message' => __( 'No hay recursos de audio por defecto configurados', 'tts-sesolibre' )
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Error al cargar recursos de audio por defecto', 'tts-sesolibre' ),
				'error' => esc_html( $e->getMessage() )
			] );
		}
	}

	/**
	 * Handle post status transition for auto-generation
	 *
	 * @param string   $new_status New post status
	 * @param string   $old_status Old post status
	 * @param \WP_Post $post       Post object
	 */
	public function handlePostStatusTransition( $new_status, $old_status, $post ): void {
		$logger = $this->container->get( 'logger' );

		// Always log transitions for debugging (independent of WP_DEBUG)
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf(
			'[WP_TTS] Post transition: ID=%d, old=%s, new=%s, type=%s',
			$post->ID, $old_status, $new_status, $post->post_type
		) );

		$logger->info( 'Post status transition detected', [
			'post_id' => $post->ID,
			'old_status' => $old_status,
			'new_status' => $new_status,
			'post_type' => $post->post_type,
			'context' => [
				'is_rest' => defined( 'REST_REQUEST' ) && REST_REQUEST,
				'is_ajax' => defined( 'DOING_AJAX' ) && DOING_AJAX,
			]
		] );

		// Only proceed if transitioning to 'publish'
		if ( $new_status !== 'publish' ) {
			return;
		}

		// Check if auto-generation is configured for this post
		if ( ! $this->config->shouldAutoGenerateForPost( $post->ID ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS] Auto-generation not configured for post ' . $post->ID );
			return;
		}

		// Get auto-generate settings
		$auto_settings = $this->config->getAutoGenerateSettings();

		// If on_publish_only is true, only generate when post is first published
		if ( ! empty( $auto_settings['on_publish_only'] ) && $old_status === 'publish' ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS] Skipping - post ' . $post->ID . ' already published' );
			return;
		}

		// Check if TTS is already enabled and has audio
		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post->ID );
			$has_audio = ! empty( $tts_data['audio']['url'] );

			// If already has audio and on_publish_only is true, skip
			if ( $has_audio && ! empty( $auto_settings['on_publish_only'] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP_TTS] Skipping - post ' . $post->ID . ' already has audio' );
				return;
			}
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[WP_TTS] Triggering auto-generation for post ' . $post->ID . ' (' . $post->post_title . ')' );

		$logger->info( 'Triggering auto-generation for post', [
			'post_id' => $post->ID,
			'post_title' => $post->post_title,
			'old_status' => $old_status,
			'new_status' => $new_status
		] );

		// Store post ID in a transient to prevent duplicate processing
		$transient_key = 'wp_tts_auto_gen_' . $post->ID;
		if ( get_transient( $transient_key ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS] Auto-generation already scheduled for post ' . $post->ID );
			return;
		}
		set_transient( $transient_key, true, 300 ); // 5 minutes lock

		// Method 1: Try Action Scheduler first (more reliable for background tasks)
		if ( function_exists( 'as_schedule_single_action' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS] Using Action Scheduler for post ' . $post->ID );
			as_schedule_single_action( time(), 'wp_tts_auto_generate_audio', [ $post->ID ], 'tts-sesolibre' );
		} else {
			// Method 2: Use shutdown hook for immediate execution
			// This runs after the response is sent to the browser
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS] Using shutdown hook for post ' . $post->ID );

			// Store post ID to process in shutdown (avoid closure issues)
			$post_id = $post->ID;
			add_action( 'shutdown', function() use ( $post_id ) {
				// Verify we're not in a problematic context
				if ( connection_aborted() ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[WP_TTS] Connection aborted, skipping shutdown execution for post ' . $post_id );
					return;
				}

				// Set unlimited execution time for audio generation
				if ( function_exists( 'set_time_limit' ) ) {
					// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Needed for long audio generation process
					@set_time_limit( 300 ); // 5 minutes
				}

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP_TTS] Executing in shutdown hook for post ' . $post_id );
				$this->handleAutoGenerateAudio( $post_id );

				// Clear the transient after successful execution
				delete_transient( 'wp_tts_auto_gen_' . $post_id );
			}, 100 ); // Priority 100 to run late

			// Method 3: Also schedule wp_cron as backup (in case shutdown fails)
			if ( ! wp_next_scheduled( 'wp_tts_auto_generate_audio', [ $post->ID ] ) ) {
				wp_schedule_single_event( time() + 60, 'wp_tts_auto_generate_audio', [ $post->ID ] );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP_TTS] Scheduled wp_cron backup for post ' . $post->ID );
			}
		}
	}

	/**
	 * Handle background audio generation (existing scheduled events)
	 *
	 * @param int $post_id Post ID
	 */
	public function handleBackgroundAudioGeneration( $post_id ): void {
		$this->container->get( 'logger' )->info( 'Starting background audio generation', [
			'post_id' => $post_id
		] );

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$result = $tts_service->generateAudioForPost( $post_id );

			if ( $result && isset( $result->url ) ) {
				$this->container->get( 'logger' )->info( 'Background audio generation completed', [
					'post_id' => $post_id,
					'audio_url' => $result->url
				] );
			} else {
				$this->container->get( 'logger' )->error( 'Background audio generation failed - no result', [
					'post_id' => $post_id
				] );
			}
		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error( 'Background audio generation failed with exception', [
				'post_id' => $post_id,
				'error' => esc_html( $e->getMessage() )
			] );
		}
	}

	/**
	 * Handle auto-generate audio for posts in configured categories
	 *
	 * @param int $post_id Post ID
	 */
	public function handleAutoGenerateAudio( $post_id ): void {
		// Always log auto-generation attempts (critical for debugging)
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[WP_TTS AUTO-GEN] Starting auto-generate audio for post_id: ' . $post_id );

		$this->container->get( 'logger' )->info( 'Starting auto-generate audio', [
			'post_id' => $post_id,
			'context' => [
				'is_cron' => defined( 'DOING_CRON' ) && DOING_CRON,
				'is_ajax' => defined( 'DOING_AJAX' ) && DOING_AJAX,
				'is_rest' => defined( 'REST_REQUEST' ) && REST_REQUEST,
				'is_action_scheduler' => did_action( 'action_scheduler_run_queue' ) > 0,
			]
		] );

		try {
			// First, enable TTS for this post and set default configuration
			$this->setupPostForAutoGeneration( $post_id );

			// Then generate the audio (pass true for is_auto_generate to bypass rate limiting)
			$tts_service = $this->container->get( 'tts_service' );
			$result = $tts_service->generateAudioForPost( $post_id, true );

			if ( $result && isset( $result->url ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP_TTS AUTO-GEN] SUCCESS for post_id: ' . $post_id . ' - URL: ' . $result->url );
				$this->container->get( 'logger' )->info( 'Auto-generate audio completed', [
					'post_id' => $post_id,
					'audio_url' => $result->url,
					'provider' => $result->provider ?? 'unknown'
				] );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP_TTS AUTO-GEN] FAILED for post_id: ' . $post_id . ' - No result returned' );
				$this->container->get( 'logger' )->error( 'Auto-generate audio failed - no result', [
					'post_id' => $post_id
				] );
			}
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS AUTO-GEN] EXCEPTION for post_id: ' . $post_id . ' - ' . $e->getMessage() );
			$this->container->get( 'logger' )->error( 'Auto-generate audio failed with exception', [
				'post_id' => $post_id,
				'error' => esc_html( $e->getMessage() ),
				'trace' => $e->getTraceAsString()
			] );
		} finally {
			// Always clear the transient lock after processing (success or failure)
			delete_transient( 'wp_tts_auto_gen_' . $post_id );
		}
	}

	/**
	 * Setup a post for auto-generation with default configuration
	 *
	 * @param int $post_id Post ID
	 */
	private function setupPostForAutoGeneration( int $post_id ): void {
		// Get default settings
		$defaults = $this->config->getDefaults();
		$default_provider = $defaults['default_provider'] ?? 'google';
		$provider_config = $this->config->getProviderConfig( $default_provider );
		$default_voice = $provider_config['default_voice'] ?? '';

		// Get default audio assets
		$audio_library = $this->config->getAudioLibrary();
		$default_intro = $audio_library['default_intro'] ?? '';
		$default_outro = $audio_library['default_outro'] ?? '';
		$default_background = $audio_library['default_background'] ?? '';

		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			// Enable TTS for this post
			\WP_TTS\Utils\TTSMetaManager::setTTSEnabled( $post_id, true );

			// Set voice configuration
			\WP_TTS\Utils\TTSMetaManager::setVoiceConfig( $post_id, $default_provider, $default_voice, 'es-MX' );

			// Set audio assets if configured
			if ( $default_intro || $default_outro || $default_background ) {
				\WP_TTS\Utils\TTSMetaManager::updateTTSSection( $post_id, 'audio_assets', [
					'intro_audio' => $default_intro,
					'outro_audio' => $default_outro,
					'background_audio' => $default_background,
					'background_volume' => 0.3
				] );
			}

			$this->container->get( 'logger' )->info( 'Post setup for auto-generation', [
				'post_id' => $post_id,
				'provider' => $default_provider,
				'voice' => $default_voice,
				'has_intro' => ! empty( $default_intro ),
				'has_outro' => ! empty( $default_outro ),
				'has_background' => ! empty( $default_background )
			] );
		} else {
			// Fallback to old meta system
			update_post_meta( $post_id, '_tts_enabled', true );
			update_post_meta( $post_id, '_tts_voice_provider', $default_provider );
			update_post_meta( $post_id, '_tts_voice_id', $default_voice );

			$this->container->get( 'logger' )->info( 'Post setup for auto-generation (legacy)', [
				'post_id' => $post_id,
				'provider' => $default_provider,
				'voice' => $default_voice
			] );
		}
	}
}

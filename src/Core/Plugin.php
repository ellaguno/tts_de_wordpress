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
	 */
	public function loadTextDomain(): void {
		load_plugin_textdomain(
			'TTS de Wordpress',
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

		// AJAX handlers
		add_action( 'wp_ajax_tts_generate_audio', array( $this, 'handleGenerateAudio' ) );
		add_action( 'wp_ajax_tts_validate_provider', array( $this, 'handleValidateProvider' ) );
		add_action( 'wp_ajax_tts_delete_audio', array( $this, 'handleDeleteAudio' ) );
		
		// Register meta-box specific AJAX handler with different action name to avoid conflicts
		add_action( 'wp_ajax_tts_get_voices_metabox', array( $this, 'handleGetVoicesForMetaBox' ) );

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
				__( 'Text-to-Speech Settings', 'TTS de Wordpress' ),
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
			
			$enabled     = $tts_data['enabled'];
			$provider    = $tts_data['voice']['provider'];
			$voice_id    = $tts_data['voice']['voice_id'];
			$custom_text = $tts_data['content']['custom_text'];
			$audio_url   = $tts_data['audio']['url'];
			$status      = $tts_data['audio']['status'];
		} else {
			// Fallback to old system
			$enabled     = get_post_meta( $post->ID, '_tts_enabled', true );
			$provider    = get_post_meta( $post->ID, '_tts_voice_provider', true );
			$voice_id    = get_post_meta( $post->ID, '_tts_voice_id', true );
			$custom_text = get_post_meta( $post->ID, '_tts_custom_text', true );
			$audio_url   = get_post_meta( $post->ID, '_tts_audio_url', true );
			$status      = get_post_meta( $post->ID, '_tts_generation_status', true );
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
			! wp_verify_nonce( $_POST['wp_tts_meta_nonce'], 'wp_tts_meta_box' ) ) {
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
				$provider = $security->sanitizeInput( $_POST['tts_voice_provider'] );
				$voice_id = '';
				if ( isset( $_POST['tts_voice_id'] ) ) {
					$voice_id = $security->sanitizeInput( $_POST['tts_voice_id'] );
				}
				\WP_TTS\Utils\TTSMetaManager::setVoiceConfig( $post_id, $provider, $voice_id );
			}

			if ( isset( $_POST['tts_custom_text'] ) ) {
				$custom_text = $security->sanitizeTextForTTS( $_POST['tts_custom_text'] );
				$use_custom = !empty( $custom_text );
				\WP_TTS\Utils\TTSMetaManager::setCustomText( $post_id, $custom_text, $use_custom );
			}
		} else {
			// Fallback to old system
			update_post_meta( $post_id, '_tts_enabled', $enabled );

			if ( isset( $_POST['tts_voice_provider'] ) ) {
				$provider = $security->sanitizeInput( $_POST['tts_voice_provider'] );
				update_post_meta( $post_id, '_tts_voice_provider', $provider );
				
				if ( isset( $_POST['tts_voice_id'] ) ) {
					$voice_id = $security->sanitizeInput( $_POST['tts_voice_id'] );
					update_post_meta( $post_id, '_tts_voice_id', $voice_id );
				}
			}

			if ( isset( $_POST['tts_custom_text'] ) ) {
				$custom_text = $security->sanitizeTextForTTS( $_POST['tts_custom_text'] );
				update_post_meta( $post_id, '_tts_custom_text', $custom_text );
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
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_tts_generate_audio' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed', 'TTS de Wordpress' )
			], 403 );
			return;
		}

		$post_id = intval( $_POST['post_id'] );

		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid post ID', 'TTS de Wordpress' )
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
					'message'   => __( 'Audio generated successfully', 'TTS de Wordpress' ),
				] );
			} else {
				$this->container->get( 'logger' )->error( 'Audio generation returned invalid result', [
					'post_id' => $post_id,
					'result' => $result
				] );

				wp_send_json_error( [
					'message' => __( 'Audio generation failed: Invalid result returned', 'TTS de Wordpress' ),
				] );
			}
		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error( 'Audio generation failed with exception', [
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
				'trace'   => $e->getTraceAsString()
			] );

			wp_send_json_error( [
				'message' => __( 'Audio generation failed', 'TTS de Wordpress' ),
				'error'   => $e->getMessage(),
			] );
		}
	}



	/**
	 * Handle AJAX provider validation request
	 */
	public function handleValidateProvider(): void {
		// Verify nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_tts_validate_provider' ) ||
			! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Security check failed', 'TTS de Wordpress' ) );
		}

		$provider = sanitize_text_field( $_POST['provider'] ?? '' );

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$is_valid = $tts_service->validateProvider( $provider );

			wp_send_json_success(
				array(
					'valid' => $is_valid,
					'message' => $is_valid ?
						__( 'Provider configuration is valid', 'TTS de Wordpress' ) :
						__( 'Provider configuration is invalid', 'TTS de Wordpress' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Provider validation failed', 'TTS de Wordpress' ),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle get voices AJAX request for meta box
	 */
	public function handleGetVoicesForMetaBox(): void {
		// Verify nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_tts_admin' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed', 'TTS de Wordpress' )
			], 403 );
			return;
		}

		$provider = sanitize_text_field( $_POST['provider'] ?? '' );

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
				'error' => $e->getMessage()
			] );

			wp_send_json_error( [
				'message' => 'Failed to load voices: ' . $e->getMessage(),
				'provider' => $provider
			] );
		}
	}

	/**
	 * Handle AJAX delete audio request
	 */
	public function handleDeleteAudio(): void {
		// Verify nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_tts_delete_audio' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Security check failed', 'TTS de Wordpress' ) );
		}

		$post_id = intval( $_POST['post_id'] );

		try {
			// Get audio URL and file path with fallback
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$audio_url = \WP_TTS\Utils\TTSMetaManager::getAudioUrl( $post_id );
			} else {
				$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );
			}
			
			if ( $audio_url ) {
				// Parse file path from URL
				$upload_dir = wp_upload_dir();
				$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $audio_url );
				
				// Delete physical file if it exists
				if ( file_exists( $file_path ) ) {
					unlink( $file_path );
					$this->container->get( 'logger' )->info( 'Audio file deleted', [
						'post_id' => $post_id,
						'file_path' => $file_path
					] );
				}
			}

			// Reset audio data with fallback
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				\WP_TTS\Utils\TTSMetaManager::updateTTSSection( $post_id, 'audio', [
					'url' => '',
					'generated_at' => null,
					'status' => 'pending',
					'duration' => 0,
					'file_size' => 0
				] );
			} else {
				// Fallback to old system
				delete_post_meta( $post_id, '_tts_audio_url' );
				delete_post_meta( $post_id, '_tts_generated_at' );
				update_post_meta( $post_id, '_tts_generation_status', 'pending' );
			}

			$this->container->get( 'logger' )->info( 'Audio deleted for post', [
				'post_id' => $post_id
			] );

			wp_send_json_success(
				array(
					'message' => __( 'Audio deleted successfully', 'TTS de Wordpress' ),
				)
			);
		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error(
				'Audio deletion failed',
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Audio deletion failed', 'TTS de Wordpress' ),
					'error'   => $e->getMessage(),
				)
			);
		}
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
				wp_enqueue_style(
					'wp-tts-player',
					WP_TTS_PLUGIN_URL . 'assets/css/frontend-player.css',
					array(),
					$this->version
				);
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
	 * Render audio player shortcode
	 *
	 * @param array $atts
	 * @return string
	 */
	public function renderAudioPlayerShortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'post_id' => get_the_ID(),
				'style'   => 'default',
			),
			$atts
		);

		$post_id = intval( $atts['post_id'] );
		$style   = $atts['style'];
		
		if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
			$audio_url = \WP_TTS\Utils\TTSMetaManager::getAudioUrl( $post_id );
		} else {
			$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );
		}

		if ( ! $audio_url ) {
			return '';
		}

		// Make variables available to the template
		ob_start();
		include WP_TTS_PLUGIN_DIR . 'templates/frontend/audio-player.php';
		return ob_get_clean();
	}

	/**
	 * Maybe add audio player to content
	 *
	 * @param string $content
	 * @return string
	 */
	public function maybeAddAudioPlayer( $content ): string {
		if ( ! is_single() && ! is_page() ) {
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

		if ( $enabled && $audio_url ) {
			$player  = $this->renderAudioPlayerShortcode( array( 'post_id' => $post_id ) );
			$content = $player . $content;
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
}

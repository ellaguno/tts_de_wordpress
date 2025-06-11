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
		add_action( 'wp_ajax_tts_preview_voice', array( $this, 'handlePreviewVoice' ) );
		add_action( 'wp_ajax_tts_get_voices', array( $this, 'handleGetVoices' ) );
		add_action( 'wp_ajax_tts_validate_provider', array( $this, 'handleValidateProvider' ) );

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

		// Get current settings
		$enabled     = get_post_meta( $post->ID, '_tts_enabled', true );
		$provider    = get_post_meta( $post->ID, '_tts_voice_provider', true );
		$voice_id    = get_post_meta( $post->ID, '_tts_voice_id', true );
		$custom_text = get_post_meta( $post->ID, '_tts_custom_text', true );
		$audio_url   = get_post_meta( $post->ID, '_tts_audio_url', true );
		$status      = get_post_meta( $post->ID, '_tts_generation_status', true );

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

		// Save TTS settings
		$enabled = isset( $_POST['tts_enabled'] ) ? 1 : 0;
		update_post_meta( $post_id, '_tts_enabled', $enabled );

		if ( isset( $_POST['tts_voice_provider'] ) ) {
			$provider = $security->sanitizeInput( $_POST['tts_voice_provider'] );
			update_post_meta( $post_id, '_tts_voice_provider', $provider );
		}

		if ( isset( $_POST['tts_voice_id'] ) ) {
			$voice_id = $security->sanitizeInput( $_POST['tts_voice_id'] );
			update_post_meta( $post_id, '_tts_voice_id', $voice_id );
		}

		if ( isset( $_POST['tts_custom_text'] ) ) {
			$custom_text = $security->sanitizeTextForTTS( $_POST['tts_custom_text'] );
			update_post_meta( $post_id, '_tts_custom_text', $custom_text );
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
			wp_die( __( 'Security check failed', 'TTS de Wordpress' ) );
		}

		$post_id = intval( $_POST['post_id'] );

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$result      = $tts_service->generateAudioForPost( $post_id );

			wp_send_json_success(
				array(
					'audio_url' => $result->url,
					'duration'  => $result->duration,
					'message'   => __( 'Audio generated successfully', 'TTS de Wordpress' ),
				)
			);
		} catch ( \Exception $e ) {
			$this->container->get( 'logger' )->error(
				'Audio generation failed',
				array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Audio generation failed', 'TTS de Wordpress' ),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle AJAX voice preview request
	 */
	public function handlePreviewVoice(): void {
		// Verify nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_tts_preview_voice' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Security check failed', 'TTS de Wordpress' ) );
		}

		$provider = sanitize_text_field( $_POST['provider'] ?? '' );
		$voice = sanitize_text_field( $_POST['voice'] ?? '' );

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$preview_text = __( 'This is a voice preview sample.', 'TTS de Wordpress' );
			$result = $tts_service->generatePreview( $preview_text, $provider, $voice );

			wp_send_json_success(
				array(
					'audio_url' => $result->url,
					'message'   => __( 'Preview generated successfully', 'TTS de Wordpress' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Preview generation failed', 'TTS de Wordpress' ),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle AJAX get voices request
	 */
	public function handleGetVoices(): void {
		// Verify nonce and permissions
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_tts_get_voices' ) ||
			! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Security check failed', 'TTS de Wordpress' ) );
		}

		$provider = sanitize_text_field( $_POST['provider'] ?? '' );

		try {
			$tts_service = $this->container->get( 'tts_service' );
			$voices = $tts_service->getAvailableVoices( $provider );

			wp_send_json_success(
				array(
					'voices' => $voices,
					'message' => __( 'Voices loaded successfully', 'TTS de Wordpress' ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to load voices', 'TTS de Wordpress' ),
					'error'   => $e->getMessage(),
				)
			);
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
	 * Enqueue frontend assets
	 */
	public function enqueueFrontendAssets(): void {
		wp_enqueue_style(
			'wp-tts-frontend',
			WP_TTS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$this->version
		);

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

		$post_id   = intval( $atts['post_id'] );
		$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );

		if ( ! $audio_url ) {
			return '';
		}

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

		$post_id   = get_the_ID();
		$enabled   = get_post_meta( $post_id, '_tts_enabled', true );
		$audio_url = get_post_meta( $post_id, '_tts_audio_url', true );

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
		$last_modified  = get_post_modified_time( 'U', false, $post_id );
		$last_generated = get_post_meta( $post_id, '_tts_last_generated', true );

		return ! $last_generated || $last_modified > strtotime( $last_generated );
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

<?php
/**
 * Admin Interface class
 *
 * @package WP_TTS
 */

namespace WP_TTS\Admin;

use WP_TTS\Core\ConfigurationManager;
use WP_TTS\Services\TTSService;
use WP_TTS\Utils\SecurityManager;

/**
 * Handles WordPress admin interface
 */
class AdminInterface {
	
	/**
	 * Configuration manager
	 *
	 * @var ConfigurationManager
	 */
	private $config;
	
	/**
	 * TTS service
	 *
	 * @var TTSService
	 */
	private $tts_service;
	
	/**
	 * Security manager
	 *
	 * @var SecurityManager
	 */
	private $security;
	
	/**
	 * Constructor
	 *
	 * @param ConfigurationManager $config      Configuration manager.
	 * @param TTSService           $tts_service TTS service.
	 * @param SecurityManager      $security    Security manager.
	 */
	public function __construct( ConfigurationManager $config, TTSService $tts_service, SecurityManager $security ) {
		$this->config = $config;
		$this->tts_service = $tts_service;
		$this->security = $security;
	}
	
	/**
	 * Initialize admin interface
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'addAdminMenu' ] );
		add_action( 'admin_init', [ $this, 'registerSettings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
		add_action( 'wp_ajax_wp_tts_test_provider', [ $this, 'handleTestProvider' ] );
		add_action( 'wp_ajax_tts_get_voices', [ $this, 'handleGetVoices' ] );
		add_action( 'wp_ajax_tts_preview_voice', [ $this, 'handlePreviewVoice' ] );
		add_action( 'wp_ajax_tts_generate_custom', [ $this, 'handleGenerateCustom' ] );
		add_action( 'wp_ajax_tts_auto_save_audio_asset', [ $this, 'handleAutoSaveAudioAsset' ] );
		add_action( 'wp_ajax_tts_auto_save_background_volume', [ $this, 'handleAutoSaveBackgroundVolume' ] );
		add_action( 'wp_ajax_tts_save_player_config', [ $this, 'handleSavePlayerConfig' ] );
		add_action( 'wp_ajax_tts_extract_post_content', [ $this, 'handleExtractPostContent' ] );
		add_action( 'wp_ajax_tts_save_edited_text', [ $this, 'handleSaveEditedText' ] );
		add_action( 'wp_ajax_tts_generate_from_edited', [ $this, 'handleGenerateFromEdited' ] );
	}
	
	/**
	 * Add admin menu pages
	 */
	public function addAdminMenu(): void {
		add_options_page(
			__( 'Configuración TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			__( 'Configuración TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'manage_options',
			'wp-tts-settings',
			[ $this, 'renderSettingsPage' ]
		);
		
		add_management_page(
			__( 'Herramientas TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			__( 'Herramientas TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'manage_options',
			'wp-tts-tools',
			[ $this, 'renderToolsPage' ]
		);
	}
	
	/**
	 * Register settings
	 */
	public function registerSettings(): void {
		register_setting( 'wp_tts_settings', 'wp_tts_config', [
			'sanitize_callback' => [ $this, 'sanitizeSettings' ]
		] );
		
		// TTS Providers section
		add_settings_section(
			'wp_tts_providers',
			__( 'Proveedores TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderProvidersSection' ],
			'wp-tts-settings'
		);
		
		// Provider fields
		add_settings_field(
			'openai_api_key',
			__( 'Clave API de OpenAI', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderOpenAIKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'elevenlabs_api_key',
			__( 'Clave API de ElevenLabs', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderElevenLabsKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'google_credentials',
			__( 'Credenciales de Google Cloud', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderGoogleCredentialsField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Google default voice field
		add_settings_field(
			'google_default_voice',
			__( 'Voz Predeterminada de Google', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderGoogleDefaultVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// OpenAI default voice field
		add_settings_field(
			'openai_default_voice',
			__( 'Voz Predeterminada de OpenAI', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderOpenAIDefaultVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// ElevenLabs default voice field
		add_settings_field(
			'elevenlabs_default_voice',
			__( 'Voz Predeterminada de ElevenLabs', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderElevenLabsDefaultVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Amazon Polly fields
		add_settings_field(
			'amazon_polly_access_key',
			__( 'Clave de Acceso de Amazon Polly', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAmazonPollyAccessKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'amazon_polly_secret_key',
			__( 'Clave Secreta de Amazon Polly', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAmazonPollySecretKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'amazon_polly_region',
			__( 'Región de Amazon Polly', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAmazonPollyRegionField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'amazon_polly_voice',
			__( 'Voz Predeterminada de Amazon Polly', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAmazonPollyVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'default_provider',
			__( 'Proveedor Predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderDefaultProviderField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Storage section
		add_settings_section(
			'wp_tts_storage',
			__( 'Configuración de Almacenamiento', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderStorageSection' ],
			'wp-tts-settings'
		);
		
		// Storage fields
		add_settings_field(
			'storage_provider',
			__( 'Proveedor de Almacenamiento', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderStorageProviderField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		add_settings_field(
			'cache_duration',
			__( 'Duración de Caché (horas)', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderCacheDurationField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		add_settings_field(
			'max_cache_size',
			__( 'Tamaño Máximo de Caché (MB)', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderMaxCacheSizeField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		// Buzzsprout storage fields
		add_settings_field(
			'buzzsprout_api_token',
			__( 'Token API de Buzzsprout', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderBuzzsproutApiTokenField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		add_settings_field(
			'buzzsprout_podcast_id',
			__( 'ID de Podcast de Buzzsprout', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderBuzzsproutPodcastIdField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		// Azure TTS fields
		add_settings_field(
			'azure_tts_subscription_key',
			__( 'Clave de Suscripción de Azure TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAzureTTSSubscriptionKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'azure_tts_region',
			__( 'Región de Azure TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAzureTTSRegionField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'azure_tts_default_voice',
			__( 'Voz Predeterminada de Azure TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAzureTTSVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Audio Assets section
		add_settings_section(
			'wp_tts_audio_assets',
			__( 'Recursos de Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderAudioAssetsSection' ],
			'wp-tts-settings'
		);
		
		add_settings_field(
			'default_intro_audio',
			__( 'Audio de Introducción Predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderDefaultIntroField' ],
			'wp-tts-settings',
			'wp_tts_audio_assets'
		);
		
		add_settings_field(
			'default_outro_audio',
			__( 'Audio de Cierre Predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			[ $this, 'renderDefaultOutroField' ],
			'wp-tts-settings',
			'wp_tts_audio_assets'
		);
	}
	
	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueueAdminAssets( string $hook ): void {
		if ( ! in_array( $hook, [ 'settings_page_wp-tts-settings', 'tools_page_wp-tts-tools' ], true ) ) {
			return;
		}
		
		// Enqueue WordPress media library for audio asset management
		wp_enqueue_media();
		
		wp_enqueue_style(
			'wp-tts-admin',
			WP_TTS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WP_TTS_PLUGIN_VERSION
		);
		
		wp_enqueue_script(
			'wp-tts-admin',
			WP_TTS_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'media-upload', 'media-views' ],
			WP_TTS_PLUGIN_VERSION,
			true
		);
		
		// Enqueue TTS Player assets for preview in admin
		wp_enqueue_style(
			'wp-tts-player',
			WP_TTS_PLUGIN_URL . 'assets/css/tts-player.css',
			[],
			WP_TTS_PLUGIN_VERSION
		);
		
		wp_enqueue_script(
			'wp-tts-player',
			WP_TTS_PLUGIN_URL . 'assets/js/tts-player.js',
			[ 'jquery' ],
			WP_TTS_PLUGIN_VERSION,
			true
		);
		
		wp_localize_script( 'wp-tts-admin', 'wpTtsAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_tts_admin' ),
			'mediaTitle' => __( 'Seleccionar Archivo de Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'mediaButton' => __( 'Usar este audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
		] );
	}
	
	/**
	 * Render settings page
	 */
	public function renderSettingsPage(): void {
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos suficientes para acceder a esta página.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) );
		}
		
		$active_tab = $_GET['tab'] ?? 'defaults';
		// Use ConfigurationManager to get current settings
		$config = [
			'providers' => $this->config->get('providers', []),
			'defaults' => $this->config->get('defaults', []),
			'storage' => $this->config->get('storage', []),
			'audio_assets' => $this->config->get('audio_library', []),
			'player' => $this->config->get('player', [])
		];
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Configuración de TTS SesoLibre', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h1>';
		
		// Render tabs navigation
		$this->renderTabsNavigation( $active_tab );
		
		echo '<form method="post" action="options.php">';
		settings_fields( 'wp_tts_settings' );
		
		// Include hidden fields for all tabs except the active one to preserve data
		$this->renderHiddenFieldsForOtherTabs( $config, $active_tab );
		
		// Render active tab content
		switch ( $active_tab ) {
			case 'defaults':
				$this->renderDefaultsTab( $config );
				break;
			case 'providers':
				$this->renderProvidersTab( $config );
				break;
			case 'storage':
				$this->renderStorageTab( $config );
				break;
			case 'audio_assets':
				$this->renderAudioAssetsTab( $config );
				break;
			case 'player':
				$this->renderPlayerTab( $config );
				break;
			default:
				$this->renderDefaultsTab( $config );
		}
		
		submit_button();
		echo '</form>';
		echo '</div>';
		
		// Add CSS and JavaScript for tabs
		$this->renderTabsAssets();
	}

	/**
	 * Render tabs navigation
	 */
	private function renderTabsNavigation( string $active_tab ): void {
		$tabs = [
			'defaults' => __( 'Predeterminados', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'providers' => __( 'Proveedores TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'storage' => __( 'Almacenamiento', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'audio_assets' => __( 'Recursos de Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			'player' => __( 'Reproductor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
		];

		echo '<div class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $name ) {
			$class = ( $tab === $active_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a href="?page=wp-tts-settings&tab=' . esc_attr( $tab ) . '" class="' . esc_attr( $class ) . '">';
			echo esc_html( $name );
			echo '</a>';
		}
		echo '</div>';
	}

	/**
	 * Render Defaults tab
	 */
	private function renderDefaultsTab( array $config ): void {
		echo '<div class="tts-tab-content">';
		echo '<h2>' . esc_html__( 'Configuración Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configura el proveedor TTS predeterminado y la configuración general.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		echo '<table class="form-table">';
		
		// Default Provider
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Proveedor TTS Predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderDefaultProviderField();
		echo '<p class="description">' . esc_html__( 'Selecciona el proveedor TTS predeterminado a utilizar cuando no se elige un proveedor específico para una entrada.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Cache Settings
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Duración de Caché', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderCacheDurationField();
		echo '<p class="description">' . esc_html__( 'Cuánto tiempo mantener los archivos de audio generados en caché (en horas).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Tamaño Máximo de Caché', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderMaxCacheSizeField();
		echo '<p class="description">' . esc_html__( 'Tamaño máximo de caché en megabytes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Render TTS Providers tab
	 */
	private function renderProvidersTab( array $config ): void {
		echo '<div class="tts-tab-content">';
		echo '<h2>' . esc_html__( 'Configuración de Proveedores TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configura tus proveedores TTS con claves API y voces predeterminadas.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		// Provider cards container
		echo '<div class="tts-providers-grid">';
		
		$this->renderProviderCard( 'google', __( 'Google Cloud TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ), $config );
		$this->renderProviderCard( 'openai', __( 'OpenAI TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ), $config );
		$this->renderProviderCard( 'elevenlabs', __( 'ElevenLabs', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ), $config );
		$this->renderProviderCard( 'azure_tts', __( 'Microsoft Azure TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ), $config );
		$this->renderProviderCard( 'amazon_polly', __( 'Amazon Polly', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ), $config );
		
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Storage tab
	 */
	private function renderStorageTab( array $config ): void {
		echo '<div class="tts-tab-content">';
		echo '<h2>' . esc_html__( 'Configuración de Almacenamiento', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configura dónde y cómo se almacenan los archivos de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		echo '<div class="tts-storage-section">';
		echo '<h3>' . esc_html__( 'Proveedor de Almacenamiento', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Proveedor de Almacenamiento', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderStorageProviderField();
		echo '<p class="description">' . esc_html__( 'Elige dónde almacenar los archivos de audio generados.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Buzzsprout configuration
		echo '<div class="tts-storage-provider-config" id="buzzsprout-config" style="display: none;">';
		echo '<h3>' . esc_html__( 'Configuración de Buzzsprout', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Habilitar Buzzsprout', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutEnabledField();
		echo '<p class="description">' . esc_html__( 'Habilita el almacenamiento en Buzzsprout.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Token API', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutApiTokenField();
		echo '<p class="description">' . esc_html__( 'Tu token API de Buzzsprout para subir archivos de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'ID de Podcast', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutPodcastIdField();
		echo '<p class="description">' . esc_html__( 'Tu ID de podcast de Buzzsprout.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Auto-publicar episodios', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutAutoPublishField();
		echo '<p class="description">' . esc_html__( 'Publica automáticamente los episodios en BuzzSprout.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Mantener episodios privados', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutPrivateField();
		echo '<p class="description">' . esc_html__( 'Los episodios se mantendrán privados en lugar de públicos.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Tags por defecto', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutTagsField();
		echo '<p class="description">' . esc_html__( 'Tags que se agregarán a todos los episodios (separados por comas).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Incluir enlace al artículo', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutIncludeLinkField();
		echo '<p class="description">' . esc_html__( 'Incluye un enlace al artículo original en la descripción del episodio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Amazon S3 configuration
		echo '<div class="tts-storage-provider-config" id="s3-config" style="display: none;">';
		echo '<h3>' . esc_html__( 'Configuración de Amazon S3', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Habilitar Amazon S3', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderS3EnabledField();
		echo '<p class="description">' . esc_html__( 'Habilita el almacenamiento en Amazon S3.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Access Key', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderS3AccessKeyField();
		echo '<p class="description">' . esc_html__( 'Tu AWS Access Key ID.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Secret Key', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderS3SecretKeyField();
		echo '<p class="description">' . esc_html__( 'Tu AWS Secret Access Key.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Bucket', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderS3BucketField();
		echo '<p class="description">' . esc_html__( 'Nombre del bucket de S3.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Región', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderS3RegionField();
		echo '<p class="description">' . esc_html__( 'Región de AWS (ej: us-east-1).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Google Cloud Storage configuration
		echo '<div class="tts-storage-provider-config" id="gcs-config" style="display: none;">';
		echo '<h3>' . esc_html__( 'Configuración de Google Cloud Storage', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Habilitar Google Cloud Storage', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		echo '<p class="description" style="color: #d63638;">' . esc_html__( 'Google Cloud Storage no está implementado aún. Usa Local Storage o BuzzSprout.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render individual provider card
	 */
	private function renderProviderCard( string $provider, string $title, array $config ): void {
		$provider_config = $config['providers'][$provider] ?? [];
		$is_configured = $this->isProviderConfigured( $provider, $provider_config );
		
		echo '<div class="tts-provider-card ' . ( $is_configured ? 'configured' : 'not-configured' ) . '">';
		echo '<div class="card-header">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<span class="status-indicator">' . ( $is_configured ? '✅' : '❌' ) . '</span>';
		echo '</div>';
		
		echo '<div class="card-content">';
		
		switch ( $provider ) {
			case 'google':
				$this->renderGoogleTTSFields();
				break;
			case 'openai':
				$this->renderOpenAITTSFields();
				break;
			case 'elevenlabs':
				$this->renderElevenLabsFields();
				break;
			case 'azure_tts':
				$this->renderAzureTTSFields();
				break;
			case 'amazon_polly':
				$this->renderAmazonPollyFields();
				break;
		}
		
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Check if provider is configured
	 */
	private function isProviderConfigured( string $provider, array $provider_config ): bool {
		switch ( $provider ) {
			case 'google':
				return ! empty( $provider_config['credentials_path'] );
			case 'openai':
				return ! empty( $provider_config['api_key'] );
			case 'elevenlabs':
				return ! empty( $provider_config['api_key'] );
			case 'azure_tts':
				return ! empty( $provider_config['subscription_key'] ) && ! empty( $provider_config['region'] );
			case 'amazon_polly':
				return ! empty( $provider_config['access_key'] ) && ! empty( $provider_config['secret_key'] );
			default:
				return false;
		}
	}

	/**
	 * Render tabs CSS and JavaScript
	 */
	private function renderTabsAssets(): void {
		?>
		<style>
		.tts-tab-content {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-top: none;
			padding: 20px;
			margin-bottom: 20px;
		}
		
		.tts-providers-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
			gap: 20px;
			margin-top: 20px;
		}
		
		.tts-provider-card {
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			background: #fff;
			overflow: hidden;
		}
		
		.tts-provider-card.configured {
			border-color: #00a32a;
		}
		
		.tts-provider-card.not-configured {
			border-color: #d63638;
		}
		
		.card-header {
			background: #f6f7f7;
			padding: 15px 20px;
			border-bottom: 1px solid #c3c4c7;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		.card-header h3 {
			margin: 0;
			font-size: 16px;
		}
		
		.status-indicator {
			font-size: 18px;
		}
		
		.card-content {
			padding: 20px;
		}
		
		.card-content .form-table {
			margin: 0;
		}
		
		.card-content .form-table th {
			width: 140px;
			padding: 10px 0;
		}
		
		.card-content .form-table td {
			padding: 10px 0;
		}
		
		.tts-storage-section {
			margin-bottom: 30px;
		}
		
		.tts-storage-provider-config {
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 20px;
			background: #f9f9f9;
		}
		
		.tts-audio-assets-section {
			margin-bottom: 30px;
		}
		
		.tts-media-selector {
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			padding: 15px;
			background: #f9f9f9;
			max-width: 500px;
		}
		
		.tts-media-preview {
			margin-bottom: 15px;
		}
		
		.tts-media-preview audio {
			width: 100%;
			margin-bottom: 10px;
		}
		
		.tts-media-title {
			font-weight: 500;
			margin: 0;
			color: #555;
		}
		
		.tts-media-buttons {
			display: flex;
			gap: 10px;
		}
		
		.tts-media-buttons .button {
			margin: 0;
		}
		
		@media (max-width: 768px) {
			.tts-providers-grid {
				grid-template-columns: 1fr;
			}
			
			.tts-media-selector {
				max-width: 100%;
			}
			
			.tts-media-buttons {
				flex-direction: column;
			}
		}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Show/hide storage provider configs based on selection
			function toggleStorageConfig() {
				var provider = $('select[name="wp_tts_config[defaults][default_storage]"]').val();
				$('.tts-storage-provider-config').hide();
				if (provider === 'buzzsprout') {
					$('#buzzsprout-config').show();
				} else if (provider === 's3') {
					$('#s3-config').show();
				} else if (provider === 'gcs') {
					$('#gcs-config').show();
				}
				// Local storage doesn't need additional config
			}
			
			$('select[name="wp_tts_config[defaults][default_storage]"]').on('change', toggleStorageConfig);
			toggleStorageConfig(); // Initial state
			
			// Media Library Integration for Audio Assets
			var mediaFrame;
			
			$(document).on('click', '.tts-select-media', function(e) {
				e.preventDefault();
				
				var $button = $(this);
				var $container = $button.closest('.tts-media-selector');
				var type = $container.data('type');
				
				// Create media frame
				mediaFrame = wp.media({
					title: wpTtsAdmin.mediaTitle,
					button: {
						text: wpTtsAdmin.mediaButton
					},
					library: {
						type: 'audio'
					},
					multiple: false
				});
				
				// Handle media selection
				mediaFrame.on('select', function() {
					var attachment = mediaFrame.state().get('selection').first().toJSON();
					
					// Update hidden input
					$container.find('.tts-media-id').val(attachment.id);
					
					// Update preview
					var $preview = $container.find('.tts-media-preview');
					var audioHtml = '<audio controls style="width: 100%; margin-bottom: 10px;">' +
						'<source src="' + attachment.url + '" type="' + attachment.mime + '">' +
						'Your browser does not support the audio element.' +
						'</audio>';
					
					$preview.find('audio').remove();
					$preview.prepend(audioHtml);
					$preview.find('.tts-media-title').text(attachment.title);
					$preview.show();
					
					// Show remove button
					$container.find('.tts-remove-media').show();
				});
				
				// Open media frame
				mediaFrame.open();
			});
			
			// Remove media
			$(document).on('click', '.tts-remove-media', function(e) {
				e.preventDefault();
				
				var $button = $(this);
				var $container = $button.closest('.tts-media-selector');
				
				// Clear hidden input
				$container.find('.tts-media-id').val('');
				
				// Hide preview
				$container.find('.tts-media-preview').hide();
				
				// Hide remove button
				$button.hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Google TTS fields
	 */
	private function renderGoogleTTSFields(): void {
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Ruta de Credenciales', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderGoogleCredentialsField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Voz Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderGoogleDefaultVoiceField();
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	/**
	 * Render OpenAI TTS fields
	 */
	private function renderOpenAITTSFields(): void {
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Clave API', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderOpenAIKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Voz Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderOpenAIDefaultVoiceField();
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	/**
	 * Render ElevenLabs fields
	 */
	private function renderElevenLabsFields(): void {
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Clave API', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderElevenLabsKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Voz Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderElevenLabsDefaultVoiceField();
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	/**
	 * Render Azure TTS fields
	 */
	private function renderAzureTTSFields(): void {
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Clave de Suscripción', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAzureTTSSubscriptionKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Región', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAzureTTSRegionField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Voz Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAzureTTSVoiceField();
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	/**
	 * Render Amazon Polly fields
	 */
	private function renderAmazonPollyFields(): void {
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Clave de Acceso', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollyAccessKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Clave Secreta', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollySecretKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Región', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollyRegionField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Voz Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollyVoiceField();
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}
	
	/**
	 * Render tools page
	 */
	public function renderToolsPage(): void {
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos suficientes para acceder a esta página.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) );
		}
		
		$stats = $this->tts_service->getStats();
		$config = get_option( 'wp_tts_config', [] );
		
		// Only show providers that are actually configured and active
		$all_providers = ['google', 'openai', 'elevenlabs', 'azure_tts', 'amazon_polly'];
		$enabled_providers = [];
		
		foreach ($all_providers as $provider) {
			$is_valid = $this->tts_service->validateProvider($provider);
			error_log("[TTS Tools] Provider validation - $provider: " . ($is_valid ? 'VALID' : 'INVALID'));
			
			// Only show providers that are actually configured and working
			if ($is_valid) {
				$enabled_providers[] = $provider;
			}
		}
		
		error_log('[TTS Tools Debug] Total enabled providers found: ' . count($enabled_providers));
		error_log('[TTS Tools Debug] Enabled providers list: ' . wp_json_encode($enabled_providers));
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Herramientas TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h1>';
		
		// Show debug info if no providers are enabled
		if (empty($enabled_providers)) {
			echo '<div class="notice notice-warning"><p>';
			echo '<strong>' . esc_html__( 'Advertencia:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</strong> ';
			echo esc_html__( 'No hay proveedores TTS configurados y activos. Ve a Configuración TTS para configurar al menos un proveedor.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
			echo '</p></div>';
		}
		
		// Voice Preview Tool
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Vista Previa de Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Prueba diferentes voces y proveedores antes de usarlos en tus entradas.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="preview_provider">' . esc_html__( 'Proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<select id="preview_provider" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Selecciona un proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		foreach ($enabled_providers as $provider) {
			echo '<option value="' . esc_attr($provider) . '">' . esc_html(ucfirst($provider)) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="preview_voice">' . esc_html__( 'Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<select id="preview_voice" class="regular-text" disabled>';
		echo '<option value="">' . esc_html__( 'Selecciona primero un proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="preview_text">' . esc_html__( 'Texto de Muestra', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="preview_text" rows="3" class="large-text" placeholder="' . esc_attr__( 'Ingresa texto para previsualizar...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '">' . esc_textarea('Hola, esta es una muestra de voz para probar el sistema de texto a voz.') . '</textarea>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"></th>';
		echo '<td>';
		echo '<button type="button" class="button button-primary" id="generate_preview" disabled>';
		echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html__( 'Generar Vista Previa', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		echo '<div id="preview_result" style="margin-top: 15px; display: none;">';
		echo '<audio controls style="width: 100%;">';
		echo '<source id="preview_audio_source" src="" type="audio/mpeg">';
		echo esc_html__( 'Tu navegador no soporta el elemento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</audio>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
		echo '</div>';
		
		// Custom Text Generator
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Generador de Texto Personalizado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Genera audio desde texto personalizado con opciones de configuración detalladas.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="custom_provider">' . esc_html__( 'Proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<select id="custom_provider" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Usar proveedor predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		foreach ($enabled_providers as $provider) {
			echo '<option value="' . esc_attr($provider) . '">' . esc_html(ucfirst($provider)) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="custom_voice">' . esc_html__( 'Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<select id="custom_voice" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Usar voz predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="custom_text">' . esc_html__( 'Texto Personalizado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="custom_text" rows="8" class="large-text" placeholder="' . esc_attr__( 'Ingresa tu texto personalizado aquí...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '"></textarea>';
		echo '<div class="wp-tts-text-stats" style="margin-top: 5px; font-size: 12px; color: #666;">';
		echo '<span id="custom_character_count">0</span> ' . esc_html__( 'caracteres', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '<span style="margin: 0 10px;">|</span>';
		echo '<span id="custom_estimated_cost">$0.00</span> ' . esc_html__( 'costo estimado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"></th>';
		echo '<td>';
		echo '<button type="button" class="button button-primary" id="generate_custom">';
		echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html__( 'Generar Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		echo '<div id="custom_generation_progress" style="display: none; margin-top: 15px;">';
		echo '<div style="width: 100%; height: 20px; background-color: #f0f0f0; border-radius: 10px; overflow: hidden;">';
		echo '<div id="custom_progress_fill" style="height: 100%; background-color: #0073aa; width: 0%; transition: width 0.3s ease;"></div>';
		echo '</div>';
		echo '<p style="margin: 5px 0 0 0; font-size: 12px; color: #666;" id="custom_progress_text">' . esc_html__( 'Preparando...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		echo '<div id="custom_result" style="margin-top: 15px; display: none;">';
		echo '<audio controls style="width: 100%;">';
		echo '<source id="custom_audio_source" src="" type="audio/mpeg">';
		echo esc_html__( 'Tu navegador no soporta el elemento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</audio>';
		echo '<p style="margin-top: 10px;">';
		echo '<a id="custom_download_link" href="#" download class="button button-secondary">' . esc_html__( 'Descargar Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</a>';
		echo '</p>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
		echo '</div>';
		
		// Text Editor for TTS
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Editor de Texto TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Extrae y edita el contenido de un post antes de generar el audio TTS. Esto te permite limpiar el texto, eliminar elementos no deseados y hacer ajustes para mejorar la calidad del audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		echo '<table class="form-table">';
		
		// Post Selection
		echo '<tr>';
		echo '<th scope="row"><label for="editor_post_id">' . esc_html__( 'Seleccionar Post', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<input type="number" id="editor_post_id" min="1" placeholder="' . esc_attr__( 'ID del Post', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '" class="regular-text" />';
		echo '<button type="button" class="button" id="extract_content" style="margin-left: 10px;">';
		echo '<span class="dashicons dashicons-download"></span> ' . esc_html__( 'Extraer Contenido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		echo '<p class="description">' . esc_html__( 'Ingresa el ID del post del cual quieres extraer y editar el contenido para TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Text Editor
		echo '<tr id="editor_text_row" style="display: none;">';
		echo '<th scope="row"><label for="editor_text">' . esc_html__( 'Texto para Editar', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label></th>';
		echo '<td>';
		echo '<div class="wp-tts-editor-container">';
		echo '<div class="wp-tts-editor-toolbar">';
		echo '<button type="button" class="button button-small" id="editor_clean_text">';
		echo '<span class="dashicons dashicons-admin-tools"></span> ' . esc_html__( 'Limpiar Texto', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		echo '<button type="button" class="button button-small" id="editor_reset_text" style="margin-left: 5px;">';
		echo '<span class="dashicons dashicons-undo"></span> ' . esc_html__( 'Restaurar Original', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		echo '<span class="wp-tts-editor-info" style="margin-left: 15px; font-size: 12px; color: #666;">';
		echo '<span id="editor_post_title"></span>';
		echo '</span>';
		echo '</div>';
		echo '<textarea id="editor_text" rows="15" class="large-text code" placeholder="' . esc_attr__( 'El contenido extraído del post aparecerá aquí para editar...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '"></textarea>';
		echo '<div class="wp-tts-text-stats" style="margin-top: 5px; font-size: 12px; color: #666;">';
		echo '<span id="editor_character_count">0</span> ' . esc_html__( 'caracteres', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '<span style="margin: 0 10px;">|</span>';
		echo '<span id="editor_word_count">0</span> ' . esc_html__( 'palabras', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '<span style="margin: 0 10px;">|</span>';
		echo '<span id="editor_estimated_cost">$0.00</span> ' . esc_html__( 'costo estimado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</div>';
		echo '<div id="editor_validation_message" class="notice" style="margin-top: 10px; display: none;"></div>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		
		// Save and Generate Controls
		echo '<tr id="editor_controls_row" style="display: none;">';
		echo '<th scope="row">' . esc_html__( 'Acciones', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		echo '<div class="wp-tts-editor-actions">';
		echo '<button type="button" class="button button-secondary" id="save_edited_text">';
		echo '<span class="dashicons dashicons-saved"></span> ' . esc_html__( 'Guardar Texto Editado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		
		// Provider and Voice Selection for Editor
		echo '<div class="wp-tts-editor-generation" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
		echo '<h4>' . esc_html__( 'Generar Audio con Texto Editado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h4>';
		echo '<div class="wp-tts-inline-controls" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
		
		// Provider select
		echo '<select id="editor_provider" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Seleccionar Proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		foreach ($enabled_providers as $provider) {
			echo '<option value="' . esc_attr($provider) . '">' . esc_html(ucfirst($provider)) . '</option>';
		}
		echo '</select>';
		
		// Voice select
		echo '<select id="editor_voice" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Seleccionar Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '</select>';
		
		// Generate button
		echo '<button type="button" class="button button-primary" id="generate_from_edited">';
		echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html__( 'Generar Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</button>';
		
		echo '</div>';
		echo '</div>';
		
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		
		// Audio Result
		echo '<tr id="editor_result_row" style="display: none;">';
		echo '<th scope="row">' . esc_html__( 'Audio Generado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		echo '<div id="editor_result" class="wp-tts-audio-result">';
		echo '<audio controls style="width: 100%; margin-bottom: 10px;">';
		echo '<source id="editor_audio_source" src="" type="audio/mpeg">';
		echo esc_html__( 'Tu navegador no soporta el elemento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</audio>';
		echo '<p><a id="editor_download_link" href="#" download class="button button-secondary">';
		echo '<span class="dashicons dashicons-download"></span> ' . esc_html__( 'Descargar Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</a></p>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Service Statistics
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Estadísticas del Servicio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<pre>' . esc_html( wp_json_encode( $stats, JSON_PRETTY_PRINT ) ) . '</pre>';
		echo '</div>';
		
		echo '</div>';
		
		// Add JavaScript for tools functionality
		echo '<script>';
		echo 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";';
		echo 'jQuery(document).ready(function($) {';
		
		// Preview functionality
		echo '$(\'#preview_provider\').on(\'change\', function() {';
		echo 'const provider = $(this).val();';
		echo 'const $voiceSelect = $(\'#preview_voice\');';
		echo 'if (!provider) {';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Selecciona primero un proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\').prop(\'disabled\', true);';
		echo '$(\'#generate_preview\').prop(\'disabled\', true);';
		echo 'return;';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Cargando voces...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',' ;
		echo 'data: { action: \'tts_get_voices\', provider: provider, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'beforeSend: function() {';
		echo 'console.log("[TTS Preview] Sending AJAX request for provider:", provider);';
		echo '},';
		echo 'success: function(response) {';
		echo 'console.log("[TTS Preview] AJAX Response received:", response);';
		echo 'if (response.success && response.data && response.data.voices) {';
		echo 'console.log("[TTS Preview] Success! Found " + response.data.voices.length + " voices");';
		echo 'let options = \'<option value="">' . esc_js__( 'Usar voz predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\';';
		echo 'if (response.data.voices.length > 0) {';
		echo 'response.data.voices.forEach(function(voice) {';
		echo 'console.log("[TTS Preview] Adding voice:", voice);';
		echo 'options += `<option value="${voice.id}">${voice.name}${voice.language ? \' (\' + voice.language + \')\' : \'\'}</option>`;';
		echo '});';
		echo '} else {';
		echo 'console.log("[TTS Preview] No voices found in response");';
		echo 'options += \'<option value="">' . esc_js__( 'No hay voces disponibles', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\';';
		echo '}';
		echo '$voiceSelect.html(options).prop(\'disabled\', false);';
		echo '$(\'#generate_preview\').prop(\'disabled\', false);';
		echo '} else {';
		echo 'console.error("[TTS Preview] AJAX Error - Invalid response structure:", response);';
		echo 'if (response.data && response.data.message) {';
		echo 'console.error("[TTS Preview] Error message:", response.data.message);';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error al cargar voces', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '$(\'#generate_preview\').prop(\'disabled\', true);';
		echo '}';
		echo '},';
		echo 'error: function(xhr, status, error) {';
		echo 'console.error("[TTS Preview] AJAX Call Failed:", {xhr: xhr, status: status, error: error});';
		echo 'console.error("[TTS Preview] Response text:", xhr.responseText);';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error de conexión', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '$(\'#generate_preview\').prop(\'disabled\', true);';
		echo '}';
		echo '});';
		echo '});';
		
		// Generate preview
		echo '$(\'#generate_preview\').on(\'click\', function() {';
		echo 'const provider = $(\'#preview_provider\').val();';
		echo 'const voice = $(\'#preview_voice\').val();';
		echo 'const text = $(\'#preview_text\').val();';
		echo 'if (!text.trim()) {';
		echo 'alert(\''. esc_js__( 'Por favor ingresa algún texto para previsualizar', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const $button = $(this);';
		echo 'const originalText = $button.html();';
		echo '$button.prop(\'disabled\', true).html(\'<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> '. esc_js__( 'Generando...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',' ;
		echo 'data: { action: \'tts_preview_voice\', provider: provider, voice: voice, text: text, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'success: function(response) {';
		echo 'if (response.success) {';
		echo '$(\'#preview_audio_source\').attr(\'src\', response.data.audio_url);';
		echo '$(\'#preview_result\').show();';
		echo '$(\'#preview_result audio\')[0].load();';
		echo '} else {';
		echo 'alert(response.data.message || \''. esc_js__( 'Falla en la vista previa', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '}';
		echo '},';
		echo 'error: function() { alert(\''. esc_js__( 'Falla en la vista previa', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\'); },';
		echo 'complete: function() { $button.prop(\'disabled\', false).html(originalText); }';
		echo '});';
		echo '});';
		
		// Custom text functionality
		echo '$(\'#custom_provider\').on(\'change\', function() {';
		echo 'const provider = $(this).val();';
		echo 'const $voiceSelect = $(\'#custom_voice\');';
		echo 'if (!provider) {';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Usar voz predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo 'return;';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Cargando voces...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',' ;
		echo 'data: { action: \'tts_get_voices\', provider: provider, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'beforeSend: function() {';
		echo 'console.log("[TTS Custom] Sending AJAX request for provider:", provider);';
		echo '},';
		echo 'success: function(response) {';
		echo 'console.log("[TTS Custom] AJAX Response received:", response);';
		echo 'if (response.success && response.data && response.data.voices) {';
		echo 'console.log("[TTS Custom] Success! Found " + response.data.voices.length + " voices");';
		echo 'let options = \'<option value="">' . esc_js__( 'Usar voz predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\';';
		echo 'if (response.data.voices.length > 0) {';
		echo 'response.data.voices.forEach(function(voice) {';
		echo 'console.log("[TTS Custom] Adding voice:", voice);';
		echo 'options += `<option value="${voice.id}">${voice.name}${voice.language ? \' (\' + voice.language + \')\' : \'\'}</option>`;';
		echo '});';
		echo '} else {';
		echo 'console.log("[TTS Custom] No voices found in response");';
		echo 'options += \'<option value="">' . esc_js__( 'No hay voces disponibles', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\';';
		echo '}';
		echo '$voiceSelect.html(options).prop(\'disabled\', false);';
		echo '} else {';
		echo 'console.error("[TTS Custom] AJAX Error - Invalid response structure:", response);';
		echo 'if (response.data && response.data.message) {';
		echo 'console.error("[TTS Custom] Error message:", response.data.message);';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error al cargar voces', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '}';
		echo '},';
		echo 'error: function(xhr, status, error) {';
		echo 'console.error("[TTS Custom] AJAX Call Failed:", {xhr: xhr, status: status, error: error});';
		echo 'console.error("[TTS Custom] Response text:", xhr.responseText);';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error de conexión', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '}';
		echo '});';
		echo '});';
		
		// Character count and cost estimation
		echo '$(\'#custom_text\').on(\'input\', function() {';
		echo 'const text = $(this).val();';
		echo 'const charCount = text.length;';
		echo 'const estimatedCost = (charCount / 1000000 * 15).toFixed(4);';
		echo '$(\'#custom_character_count\').text(charCount.toLocaleString());';
		echo '$(\'#custom_estimated_cost\').text(\'$\' + estimatedCost);';
		echo '});';
		
		// Generate custom audio
		echo '$(\'#generate_custom\').on(\'click\', function() {';
		echo 'const provider = $(\'#custom_provider\').val();';
		echo 'const voice = $(\'#custom_voice\').val();';
		echo 'const text = $(\'#custom_text\').val();';
		echo 'if (!text.trim()) {';
		echo 'alert(\''. esc_js__( 'Por favor ingresa algún texto para generar', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const $button = $(this);';
		echo 'const originalText = $button.html();';
		echo '$button.prop(\'disabled\', true);';
		echo '$(\'#custom_generation_progress\').show();';
		echo 'let progress = 0;';
		echo 'const progressInterval = setInterval(function() {';
		echo 'progress += Math.random() * 20;';
		echo 'if (progress > 90) progress = 90;';
		echo '$(\'#custom_progress_fill\').css(\'width\', progress + \'%\');';
		echo '}, 500);';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',' ;
		echo 'data: { action: \'tts_generate_custom\', provider: provider, voice: voice, text: text, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'beforeSend: function() {';
		echo 'console.log("[TTS Custom Generate] Starting audio generation with:", {provider: provider, voice: voice, textLength: text.length});';
		echo '},';
		echo 'success: function(response) {';
		echo 'console.log("[TTS Custom Generate] Response received:", response);';
		echo 'clearInterval(progressInterval);';
		echo '$(\'#custom_progress_fill\').css(\'width\', \'100%\');';
		echo 'if (response.success) {';
		echo 'console.log("[TTS Custom Generate] Success! Audio URL:", response.data.audio_url);';
		echo 'setTimeout(function() {';
		echo '$(\'#custom_audio_source\').attr(\'src\', response.data.audio_url);';
		echo '$(\'#custom_download_link\').attr(\'href\', response.data.audio_url);';
		echo '$(\'#custom_result\').show();';
		echo '$(\'#custom_result audio\')[0].load();';
		echo '$(\'#custom_generation_progress\').hide();';
		echo '}, 1000);';
		echo '} else {';
		echo 'console.error("[TTS Custom Generate] Generation failed:", response);';
		echo 'const errorMsg = response.data ? response.data.message : \''. esc_js__( 'Falla en la generación', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\';';
		echo 'alert(errorMsg);';
		echo '$(\'#custom_generation_progress\').hide();';
		echo '}';
		echo '},';
		echo 'error: function(xhr, status, error) {';
		echo 'console.error("[TTS Custom Generate] AJAX Error:", {xhr: xhr, status: status, error: error});';
		echo 'console.error("[TTS Custom Generate] Response text:", xhr.responseText);';
		echo 'clearInterval(progressInterval);';
		echo 'alert(\''. esc_js__( 'Falla en la generación', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '$(\'#custom_generation_progress\').hide();';
		echo '},';
		echo 'complete: function() { $button.prop(\'disabled\', false).html(originalText); }';
		echo '});';
		echo '});';
		
		// Text Editor functionality
		echo '// Text Editor Functionality';
		echo 'let originalExtractedText = "";';
		echo 'let currentPostId = 0;';
		
		// Extract content functionality
		echo '$(\'#extract_content\').on(\'click\', function() {';
		echo 'const postId = parseInt($(\'#editor_post_id\').val());';
		echo 'if (!postId || postId < 1) {';
		echo 'alert(\''. esc_js__( 'Por favor ingresa un ID de post válido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'currentPostId = postId;';
		echo 'const $button = $(this);';
		echo 'const originalText = $button.html();';
		echo '$button.prop(\'disabled\', true).html(\'<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> '. esc_js__( 'Extrayendo...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',';
		echo 'data: { action: \'tts_extract_post_content\', post_id: postId, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'success: function(response) {';
		echo 'if (response.success) {';
		echo 'originalExtractedText = response.data.text;';
		echo '$(\'#editor_text\').val(response.data.text);';
		echo '$(\'#editor_post_title\').text(\'Post: \' + response.data.post_title);';
		echo 'updateEditorStats();';
		echo 'showValidationMessage(response.data.validation);';
		echo '$(\'#editor_text_row, #editor_controls_row\').show();';
		echo '} else {';
		echo 'alert(response.data.message || \''. esc_js__( 'Error al extraer contenido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '}';
		echo '},';
		echo 'error: function() {';
		echo 'alert(\''. esc_js__( 'Error de conexión al extraer contenido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '},';
		echo 'complete: function() {';
		echo '$button.prop(\'disabled\', false).html(originalText);';
		echo '}';
		echo '});';
		echo '});';
		
		// Text editing functionality
		echo '$(\'#editor_text\').on(\'input\', function() {';
		echo 'updateEditorStats();';
		echo '});';
		
		// Clean text functionality
		echo '$("#editor_clean_text").on("click", function() {';
		echo 'let text = $("#editor_text").val();';
		echo 'text = text.replace(/\\s+/g, " ");'; // Multiple spaces
		echo 'text = text.replace(/\\[\\s*\\]/g, "");'; // Empty brackets
		echo 'text = text.replace(/\\(\\s*\\)/g, "");'; // Empty parentheses
		echo 'text = text.trim();';
		echo '$("#editor_text").val(text);';
		echo 'updateEditorStats();';
		echo '});';
		
		// Reset text functionality
		echo '$(\'#editor_reset_text\').on(\'click\', function() {';
		echo 'if (originalExtractedText && confirm(\''. esc_js__( '¿Estás seguro de que quieres restaurar el texto original? Se perderán todas las ediciones.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\')) {';
		echo '$(\'#editor_text\').val(originalExtractedText);';
		echo 'updateEditorStats();';
		echo '}';
		echo '});';
		
		// Provider change for editor
		echo '$(\'#editor_provider\').on(\'change\', function() {';
		echo 'const provider = $(this).val();';
		echo 'const $voiceSelect = $(\'#editor_voice\');';
		echo 'if (!provider) {';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Seleccionar Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo 'return;';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Cargando voces...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo 'loadVoicesForProvider(provider, $voiceSelect);';
		echo '});';
		
		// Save edited text
		echo '$(\'#save_edited_text\').on(\'click\', function() {';
		echo 'if (!currentPostId) {';
		echo 'alert(\''. esc_js__( 'Por favor extrae primero el contenido de un post', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const text = $(\'#editor_text\').val().trim();';
		echo 'if (!text) {';
		echo 'alert(\''. esc_js__( 'El texto no puede estar vacío', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const $button = $(this);';
		echo 'const originalText = $button.html();';
		echo '$button.prop(\'disabled\', true).html(\'<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> '. esc_js__( 'Guardando...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',';
		echo 'data: { action: \'tts_save_edited_text\', post_id: currentPostId, text: text, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'success: function(response) {';
		echo 'if (response.success) {';
		echo 'alert(response.data.message);';
		echo '} else {';
		echo 'alert(response.data.message || \''. esc_js__( 'Error al guardar texto', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '}';
		echo '},';
		echo 'error: function() {';
		echo 'alert(\''. esc_js__( 'Error de conexión al guardar texto', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '},';
		echo 'complete: function() {';
		echo '$button.prop(\'disabled\', false).html(originalText);';
		echo '}';
		echo '});';
		echo '});';
		
		// Generate from edited text
		echo '$(\'#generate_from_edited\').on(\'click\', function() {';
		echo 'if (!currentPostId) {';
		echo 'alert(\''. esc_js__( 'Por favor extrae primero el contenido de un post', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const provider = $(\'#editor_provider\').val();';
		echo 'const voice = $(\'#editor_voice\').val();';
		echo 'const text = $(\'#editor_text\').val().trim();';
		echo 'if (!text) {';
		echo 'alert(\''. esc_js__( 'El texto no puede estar vacío', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'if (!provider) {';
		echo 'alert(\''. esc_js__( 'Por favor selecciona un proveedor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const $button = $(this);';
		echo 'const originalText = $button.html();';
		echo '$button.prop(\'disabled\', true).html(\'<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> '. esc_js__( 'Generando...', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',';
		echo 'data: { action: \'tts_generate_from_edited\', post_id: currentPostId, provider: provider, voice: voice, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'success: function(response) {';
		echo 'if (response.success) {';
		echo '$(\'#editor_audio_source\').attr(\'src\', response.data.audio_url);';
		echo '$(\'#editor_download_link\').attr(\'href\', response.data.audio_url);';
		echo '$(\'#editor_result_row\').show();';
		echo '$(\'#editor_result audio\')[0].load();';
		echo 'alert(response.data.message);';
		echo '} else {';
		echo 'alert(response.data.message || \''. esc_js__( 'Error al generar audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '}';
		echo '},';
		echo 'error: function() {';
		echo 'alert(\''. esc_js__( 'Error de conexión al generar audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) .'\');';
		echo '},';
		echo 'complete: function() {';
		echo '$button.prop(\'disabled\', false).html(originalText);';
		echo '}';
		echo '});';
		echo '});';
		
		// Helper functions for editor
		echo 'function updateEditorStats() {';
		echo 'const text = $(\'#editor_text\').val();';
		echo 'const charCount = text.length;';
		echo 'const wordCount = text.trim() ? text.trim().split(/\\s+/).length : 0;';
		echo 'const estimatedCost = (charCount / 1000000 * 15).toFixed(4);';
		echo '$(\'#editor_character_count\').text(charCount.toLocaleString());';
		echo '$(\'#editor_word_count\').text(wordCount.toLocaleString());';
		echo '$(\'#editor_estimated_cost\').text(\'$\' + estimatedCost);';
		echo '}';
		
		echo 'function showValidationMessage(validation) {';
		echo 'const $message = $(\'#editor_validation_message\');';
		echo 'if (validation.valid) {';
		echo '$message.removeClass(\'notice-error\').addClass(\'notice-success\').html(\'<p>✓ \' + validation.message + \'</p>\').show();';
		echo '} else {';
		echo '$message.removeClass(\'notice-success\').addClass(\'notice-error\').html(\'<p>⚠ \' + validation.message + \'</p>\').show();';
		echo '}';
		echo '}';
		
		echo 'function loadVoicesForProvider(provider, $voiceSelect) {';
		echo '$.ajax({';
		echo 'url: ajaxurl,';
		echo 'type: \'POST\',';
		echo 'data: { action: \'tts_get_voices\', provider: provider, nonce: \''. wp_create_nonce('wp_tts_admin') .'\' },';
		echo 'success: function(response) {';
		echo 'if (response.success && response.data && response.data.voices) {';
		echo 'let options = \'<option value="">' . esc_js__( 'Usar voz predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\';';
		echo 'if (response.data.voices.length > 0) {';
		echo 'response.data.voices.forEach(function(voice) {';
		echo 'options += `<option value="${voice.id}">${voice.name}${voice.language ? \' (\' + voice.language + \')\' : \'\'}</option>`;';
		echo '});';
		echo '} else {';
		echo 'options += \'<option value="">' . esc_js__( 'No hay voces disponibles', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\';';
		echo '}';
		echo '$voiceSelect.html(options).prop(\'disabled\', false);';
		echo '} else {';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error al cargar voces', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '}';
		echo '},';
		echo 'error: function() {';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error de conexión', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>\');';
		echo '}';
		echo '});';
		echo '}';
		
		echo '});';
		echo '</script>';
		
		// Add CSS
		echo '<style>';
		echo '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
		echo '.wp-tts-text-stats { margin-top: 5px; font-size: 12px; color: #666; }';
		echo '.wp-tts-editor-container { max-width: 100%; }';
		echo '.wp-tts-editor-toolbar { margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; }';
		echo '.wp-tts-editor-toolbar .button { margin-right: 5px; }';
		echo '.wp-tts-editor-info { color: #666; font-style: italic; }';
		echo '.wp-tts-editor-actions { padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; }';
		echo '.wp-tts-editor-generation { margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; }';
		echo '.wp-tts-inline-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }';
		echo '.wp-tts-inline-controls select, .wp-tts-inline-controls button { margin: 5px 0; }';
		echo '.wp-tts-audio-result { padding: 15px; background: #f0f8ff; border: 1px solid #cce7ff; border-radius: 4px; }';
		echo '#editor_text { font-family: Monaco, Menlo, "Ubuntu Mono", monospace; line-height: 1.6; }';
		echo '.notice-success { border-left-color: #46b450; background-color: #ecf7ed; }';
		echo '.notice-error { border-left-color: #dc3232; background-color: #fef7f7; }';
		echo '.notice p { margin: 10px; }';
		echo '</style>';
	}
	
	/**
	 * Render providers section
	 */
	public function renderProvidersSection(): void {
		echo '<p>' . esc_html__( 'Configura tus proveedores TTS abajo.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render storage section
	 */
	public function renderStorageSection(): void {
		echo '<p>' . esc_html__( 'Configura la configuración de almacenamiento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render OpenAI API Key field
	 */
	public function renderOpenAIKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['openai']['api_key'] ?? '';
		$enabled = $config['providers']['openai']['enabled'] ?? false;
		
		echo '<div class="tts-provider-field">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[providers][openai][enabled]" value="1" ' . checked( $enabled, true, false ) . ' class="tts-provider-toggle" data-provider="openai" />';
		echo ' ' . esc_html__( 'Habilitar OpenAI TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '<div class="tts-provider-config" style="margin-top: 10px; ' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<input type="password" name="wp_tts_config[providers][openai][api_key]" value="' . esc_attr( $value ) . '" class="regular-text" ' . ( $enabled ? '' : 'disabled' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu clave API de OpenAI para servicios TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
	
	/**
	 * Render ElevenLabs API Key field
	 */
	public function renderElevenLabsKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['elevenlabs']['api_key'] ?? '';
		$enabled = $config['providers']['elevenlabs']['enabled'] ?? false;
		
		echo '<div class="tts-provider-field">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[providers][elevenlabs][enabled]" value="1" ' . checked( $enabled, true, false ) . ' class="tts-provider-toggle" data-provider="elevenlabs" />';
		echo ' ' . esc_html__( 'Habilitar ElevenLabs TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '<div class="tts-provider-config" style="margin-top: 10px; ' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<input type="password" name="wp_tts_config[providers][elevenlabs][api_key]" value="' . esc_attr( $value ) . '" class="regular-text" ' . ( $enabled ? '' : 'disabled' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu clave API de ElevenLabs para servicios TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
	
	/**
	 * Render Google Credentials field
	 */
	public function renderGoogleCredentialsField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['google']['credentials_path'] ?? '';
		$enabled = $config['providers']['google']['enabled'] ?? false;
		
		echo '<div class="tts-provider-field">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[providers][google][enabled]" value="1" ' . checked( $enabled, true, false ) . ' class="tts-provider-toggle" data-provider="google" />';
		echo ' ' . esc_html__( 'Habilitar Google Cloud TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '<div class="tts-provider-config" style="margin-top: 10px; ' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<input type="text" name="wp_tts_config[providers][google][credentials_path]" value="' . esc_attr( $value ) . '" class="regular-text" ' . ( $enabled ? '' : 'disabled' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Ruta al archivo JSON de cuenta de servicio de Google Cloud.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
/**
	 * Render Amazon Polly Access Key field
	 */
	public function renderAmazonPollyAccessKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['amazon_polly']['access_key'] ?? '';
		$enabled = $config['providers']['amazon_polly']['enabled'] ?? false;
		
		echo '<div class="tts-provider-field">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[providers][amazon_polly][enabled]" value="1" ' . checked( $enabled, true, false ) . ' class="tts-provider-toggle" data-provider="amazon_polly" />';
		echo ' ' . esc_html__( 'Habilitar Amazon Polly TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '<div class="tts-provider-config" style="margin-top: 10px; ' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<label>' . esc_html__( 'ID de Clave de Acceso AWS:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label><br>';
		echo '<input type="password" name="wp_tts_config[providers][amazon_polly][access_key]" value="' . esc_attr( $value ) . '" class="regular-text" ' . ( $enabled ? '' : 'disabled' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu ID de Clave de Acceso AWS para Amazon Polly.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
	
	/**
	 * Render Amazon Polly Secret Key field
	 */
	public function renderAmazonPollySecretKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['amazon_polly']['secret_key'] ?? '';
		$enabled = $config['providers']['amazon_polly']['enabled'] ?? false;
		
		echo '<div class="tts-provider-config" style="' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<label>' . esc_html__( 'Clave de Acceso Secreta AWS:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label><br>';
		echo '<input type="password" name="wp_tts_config[providers][amazon_polly][secret_key]" value="' . esc_attr( $value ) . '" class="regular-text" ' . ( $enabled ? '' : 'disabled' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu Clave de Acceso Secreta AWS para Amazon Polly.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
	}
	
	/**
	 * Render Amazon Polly Region field
	 */
	public function renderAmazonPollyRegionField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['amazon_polly']['region'] ?? 'us-east-1';
		$enabled = $config['providers']['amazon_polly']['enabled'] ?? false;
		$regions = [
			'us-east-1' => 'US East (N. Virginia)',
			'us-west-2' => 'US West (Oregon)',
			'eu-west-1' => 'Europe (Ireland)',
			'ap-southeast-2' => 'Asia Pacific (Sydney)',
			'ap-northeast-1' => 'Asia Pacific (Tokyo)',
		];
		
		echo '<div class="tts-provider-config" style="' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<label>' . esc_html__( 'Región AWS:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label><br>';
		echo '<select name="wp_tts_config[providers][amazon_polly][region]" ' . ( $enabled ? '' : 'disabled' ) . '>';
		foreach ( $regions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la región AWS para Amazon Polly.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
	}
	
	/**
	 * Render Amazon Polly Voice field
	 */
	public function renderAmazonPollyVoiceField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['amazon_polly']['voice'] ?? 'Joanna';
		$voices = [
			'Joanna' => 'Joanna (Female, US English)',
			'Matthew' => 'Matthew (Male, US English)',
			'Ivy' => 'Ivy (Female Child, US English)',
			'Justin' => 'Justin (Male Child, US English)',
			'Kendra' => 'Kendra (Female, US English)',
			'Kimberly' => 'Kimberly (Female, US English)',
			'Salli' => 'Salli (Female, US English)',
			'Joey' => 'Joey (Male, US English)',
			'Conchita' => 'Conchita (Female, Spanish)',
			'Enrique' => 'Enrique (Male, Spanish)',
			'Lucia' => 'Lucia (Female, Spanish)',
			'Mia' => 'Mia (Female, Mexican Spanish)',
		];
		
		echo '<select name="wp_tts_config[providers][amazon_polly][voice]">';
		foreach ( $voices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la voz predeterminada para Amazon Polly.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render Default Provider field
	 */
	public function renderDefaultProviderField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['default_provider'] ?? 'openai';
		$providers = [
			'openai' => 'OpenAI',
			'elevenlabs' => 'ElevenLabs',
			'google' => 'Google Cloud',
			'amazon_polly' => 'Amazon Polly',
			'azure_tts' => 'Microsoft Azure TTS'
		];
		
		echo '<select name="wp_tts_config[default_provider]">';
		foreach ( $providers as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona el proveedor TTS predeterminado a usar.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render Storage Provider field
	 */
	public function renderStorageProviderField(): void {
		$current = $this->config->get( 'defaults.default_storage', 'local' );
		$providers = [
			'local' => 'Local Storage',
			's3' => 'Amazon S3',
			'gcs' => 'Google Cloud Storage',
			'buzzsprout' => 'Buzzsprout'
		];
		
		echo '<select name="wp_tts_config[defaults][default_storage]">';
		foreach ( $providers as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona dónde almacenar los archivos de audio generados.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}

	/**
	 * Render Buzzsprout Enabled field
	 */
	public function renderBuzzsproutEnabledField(): void {
		$enabled = $this->config->get( 'storage.buzzsprout.enabled', false );
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[storage][buzzsprout][enabled]" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' ' . esc_html__( 'Habilitar almacenamiento en Buzzsprout', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
	}

	/**
	 * Render Buzzsprout API Token field
	 */
	public function renderBuzzsproutApiTokenField(): void {
		$value = $this->config->get( 'storage.buzzsprout.api_token', '' );
		echo '<input type="password" name="wp_tts_config[storage][buzzsprout][api_token]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu token API de Buzzsprout para almacenamiento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render Buzzsprout Podcast ID field
	 */
	public function renderBuzzsproutPodcastIdField(): void {
		$value = $this->config->get( 'storage.buzzsprout.podcast_id', '' );
		echo '<input type="text" name="wp_tts_config[storage][buzzsprout][podcast_id]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu ID de Podcast de Buzzsprout donde se almacenarán los archivos de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}

	/**
	 * Render Buzzsprout Auto Publish field
	 */
	public function renderBuzzsproutAutoPublishField(): void {
		$enabled = $this->config->get( 'storage.buzzsprout.auto_publish', false );
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[storage][buzzsprout][auto_publish]" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' ' . esc_html__( 'Publicar episodios automáticamente', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
	}

	/**
	 * Render Buzzsprout Private field
	 */
	public function renderBuzzsproutPrivateField(): void {
		$enabled = $this->config->get( 'storage.buzzsprout.make_private', false );
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[storage][buzzsprout][make_private]" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mantener episodios como privados', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
	}

	/**
	 * Render Buzzsprout Tags field
	 */
	public function renderBuzzsproutTagsField(): void {
		$value = $this->config->get( 'storage.buzzsprout.default_tags', 'tts,generated' );
		echo '<input type="text" name="wp_tts_config[storage][buzzsprout][default_tags]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render Buzzsprout Include Link field
	 */
	public function renderBuzzsproutIncludeLinkField(): void {
		$enabled = $this->config->get( 'storage.buzzsprout.include_link', true );
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[storage][buzzsprout][include_link]" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' ' . esc_html__( 'Incluir enlace al artículo en la descripción', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
	}

	/**
	 * Render S3 Enabled field
	 */
	public function renderS3EnabledField(): void {
		$enabled = $this->config->get( 'storage.s3.enabled', false );
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[storage][s3][enabled]" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' ' . esc_html__( 'Habilitar almacenamiento en Amazon S3', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
	}

	/**
	 * Render S3 Access Key field
	 */
	public function renderS3AccessKeyField(): void {
		$value = $this->config->get( 'storage.s3.access_key', '' );
		echo '<input type="text" name="wp_tts_config[storage][s3][access_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render S3 Secret Key field
	 */
	public function renderS3SecretKeyField(): void {
		$value = $this->config->get( 'storage.s3.secret_key', '' );
		echo '<input type="password" name="wp_tts_config[storage][s3][secret_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render S3 Bucket field
	 */
	public function renderS3BucketField(): void {
		$value = $this->config->get( 'storage.s3.bucket', '' );
		echo '<input type="text" name="wp_tts_config[storage][s3][bucket]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render S3 Region field
	 */
	public function renderS3RegionField(): void {
		$value = $this->config->get( 'storage.s3.region', 'us-east-1' );
		$regions = [
			'us-east-1' => 'US East (N. Virginia)',
			'us-east-2' => 'US East (Ohio)',
			'us-west-1' => 'US West (N. California)',
			'us-west-2' => 'US West (Oregon)',
			'eu-west-1' => 'Europe (Ireland)',
			'eu-central-1' => 'Europe (Frankfurt)',
			'ap-southeast-1' => 'Asia Pacific (Singapore)',
		];
		
		echo '<select name="wp_tts_config[storage][s3][region]">';
		foreach ( $regions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
	
	/**
	 * Render Azure TTS Subscription Key field
	 */
	public function renderAzureTTSSubscriptionKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['azure_tts']['subscription_key'] ?? '';
		$enabled = $config['providers']['azure_tts']['enabled'] ?? false;
		
		echo '<div class="tts-provider-field">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[providers][azure_tts][enabled]" value="1" ' . checked( $enabled, true, false ) . ' class="tts-provider-toggle" data-provider="azure_tts" />';
		echo ' ' . esc_html__( 'Habilitar Azure TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '<div class="tts-provider-config" style="margin-top: 10px; ' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<label>' . esc_html__( 'Clave de Suscripción:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label><br>';
		echo '<input type="password" name="wp_tts_config[providers][azure_tts][subscription_key]" value="' . esc_attr( $value ) . '" class="regular-text" ' . ( $enabled ? '' : 'disabled' ) . ' />';
		echo '<p class="description">' . esc_html__( 'Ingresa tu clave de suscripción de Azure Cognitive Services para TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
	
	/**
	 * Render Azure TTS Region field
	 */
	public function renderAzureTTSRegionField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['azure_tts']['region'] ?? 'eastus';
		$enabled = $config['providers']['azure_tts']['enabled'] ?? false;
		$regions = [
			'eastus' => 'East US',
			'eastus2' => 'East US 2',
			'westus' => 'West US',
			'westus2' => 'West US 2',
			'centralus' => 'Central US',
			'northcentralus' => 'North Central US',
			'southcentralus' => 'South Central US',
			'westcentralus' => 'West Central US',
			'canadacentral' => 'Canada Central',
			'brazilsouth' => 'Brazil South',
			'northeurope' => 'North Europe',
			'westeurope' => 'West Europe',
			'uksouth' => 'UK South',
			'ukwest' => 'UK West',
			'francecentral' => 'France Central',
			'germanywestcentral' => 'Germany West Central',
			'switzerlandnorth' => 'Switzerland North',
			'norwayeast' => 'Norway East',
			'southeastasia' => 'Southeast Asia',
			'eastasia' => 'East Asia',
			'australiaeast' => 'Australia East',
			'japaneast' => 'Japan East',
			'japanwest' => 'Japan West',
			'koreacentral' => 'Korea Central',
			'southafricanorth' => 'South Africa North',
			'centralindia' => 'Central India',
			'southindia' => 'South India',
			'westindia' => 'West India',
		];
		
		echo '<div class="tts-provider-config" style="' . ( $enabled ? '' : 'opacity: 0.5;' ) . '">';
		echo '<label>' . esc_html__( 'Región de Azure:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label><br>';
		echo '<select name="wp_tts_config[providers][azure_tts][region]" ' . ( $enabled ? '' : 'disabled' ) . '>';
		foreach ( $regions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la región de Azure para servicios TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
	}
	
	/**
	 * Render Azure TTS Voice field
	 */
	public function renderAzureTTSVoiceField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['azure_tts']['default_voice'] ?? 'es-MX-DaliaNeural';
		$voices = [
			'es-MX-DaliaNeural' => 'Dalia (Mexican Spanish Female)',
			'es-MX-JorgeNeural' => 'Jorge (Mexican Spanish Male)',
			'es-MX-BeatrizNeural' => 'Beatriz (Mexican Spanish Female)',
			'es-MX-CandelaNeural' => 'Candela (Mexican Spanish Female)',
			'es-MX-CecilioNeural' => 'Cecilio (Mexican Spanish Male)',
			'es-ES-ElviraNeural' => 'Elvira (Spanish Female)',
			'es-ES-AlvaroNeural' => 'Alvaro (Spanish Male)',
			'es-ES-AbrilNeural' => 'Abril (Spanish Female)',
			'es-ES-ArnauNeural' => 'Arnau (Spanish Male)',
			'en-US-AriaNeural' => 'Aria (English US Female)',
			'en-US-DavisNeural' => 'Davis (English US Male)',
			'en-US-AmberNeural' => 'Amber (English US Female)',
			'en-US-AnaNeural' => 'Ana (English US Female)',
			'en-US-BrandonNeural' => 'Brandon (English US Male)',
			'en-GB-SoniaNeural' => 'Sonia (English UK Female)',
			'en-GB-RyanNeural' => 'Ryan (English UK Male)',
			'en-GB-LibbyNeural' => 'Libby (English UK Female)',
		];
		
		echo '<select name="wp_tts_config[providers][azure_tts][default_voice]">';
		foreach ( $voices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la voz predeterminada para Azure TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render Google Default Voice field
	 */
	public function renderGoogleDefaultVoiceField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['google']['default_voice'] ?? 'es-MX-Wavenet-A';
		$voices = [
			'es-MX-Wavenet-A' => 'Wavenet A (Mexican Spanish Female)',
			'es-MX-Wavenet-B' => 'Wavenet B (Mexican Spanish Male)',
			'es-ES-Wavenet-A' => 'Wavenet A (Spanish Female)',
			'es-ES-Wavenet-B' => 'Wavenet B (Spanish Male)',
			'es-ES-Wavenet-C' => 'Wavenet C (Spanish Female)',
			'es-ES-Wavenet-D' => 'Wavenet D (Spanish Female)',
			'en-US-Wavenet-A' => 'Wavenet A (English US Female)',
			'en-US-Wavenet-B' => 'Wavenet B (English US Male)',
			'en-US-Wavenet-C' => 'Wavenet C (English US Female)',
			'en-US-Wavenet-D' => 'Wavenet D (English US Male)',
		];
		
		echo '<select name="wp_tts_config[providers][google][default_voice]">';
		foreach ( $voices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la voz predeterminada para Google Cloud TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render OpenAI Default Voice field
	 */
	public function renderOpenAIDefaultVoiceField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['openai']['default_voice'] ?? 'alloy';
		$voices = [
			'alloy' => 'Alloy (Neutral)',
			'echo' => 'Echo (Male)',
			'fable' => 'Fable (British Male)',
			'onyx' => 'Onyx (Male)',
			'nova' => 'Nova (Female)',
			'shimmer' => 'Shimmer (Female)',
		];
		
		echo '<select name="wp_tts_config[providers][openai][default_voice]">';
		foreach ( $voices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la voz predeterminada para OpenAI TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render ElevenLabs Default Voice field
	 */
	public function renderElevenLabsDefaultVoiceField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['elevenlabs']['default_voice'] ?? 'EXAVITQu4vr4xnSDxMaL';
		$voices = [
			'EXAVITQu4vr4xnSDxMaL' => 'Bella (Spanish Female)',
			'pNInz6obpgDQGcFmaJgB' => 'Adam (Spanish Male)',
			'TxGEqnHWrfWFTfGW9XjX' => 'Josh (Spanish Male)',
			'VR6AewLTigWG4xSOukaG' => 'Arnold (Spanish Male)',
			'MF3mGyEYCl7XYWbV9V6O' => 'Elli (Spanish Female)',
			'XrExE9yKIg1WjnnlVkGX' => 'Matilda (Spanish Female)',
			'ErXwobaYiN019PkySvjV' => 'Antoni (Spanish Male)',
			'21m00Tcm4TlvDq8ikWAM' => 'Rachel (English Female)',
		];
		
		echo '<select name="wp_tts_config[providers][elevenlabs][default_voice]">';
		foreach ( $voices as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Selecciona la voz predeterminada para ElevenLabs TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render Cache Duration field
	 */
	public function renderCacheDurationField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['cache']['duration'] ?? 24;
		echo '<input type="number" name="wp_tts_config[cache][duration]" value="' . esc_attr( $value ) . '" min="1" max="8760" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Cuánto tiempo mantener archivos de audio en caché (1-8760 horas).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Render Max Cache Size field
	 */
	public function renderMaxCacheSizeField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['cache']['max_size'] ?? 100;
		echo '<input type="number" name="wp_tts_config[cache][max_size]" value="' . esc_attr( $value ) . '" min="10" max="10000" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Tamaño máximo de caché en megabytes (10-10000 MB).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}
	
	/**
	 * Handle AJAX request from TTS Tools page to generate test audio.
	 * This is different from validating a provider's connection.
	 */
	public function handleTestProvider(): void {
		// Check nonce (wpTtsAdmin.nonce is 'wp_tts_admin')
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_admin' ) ) {
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida (nonce).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'debug_info' => [
					'nonce_received' => isset($_POST['nonce']) ? 'yes' : 'no',
					'expected_action' => 'wp_tts_admin'
				]
			], 403 );
			return;
		}

		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		$text_to_generate = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		
		if ( empty( $text_to_generate ) ) {
			wp_send_json_error( [
				'message' => __( 'No se proporcionó texto para la generación TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error_details' => 'Text input was empty.'
			] );
			return;
		}

		$this->config->getLogger()->info('[AdminInterface::handleTestProvider] Received request to generate test audio.', ['text_length' => strlen($text_to_generate)]);

		try {
			// For a general test from TTS Tools, we don't specify a provider or voice in $options.
			// TTSService::generateAudio will use its internal logic (e.g., round-robin or a global default).
			$options = [
				'source' => 'tts_tools_test_button' // For logging/tracking
			];
			$result = $this->tts_service->generateAudio( $text_to_generate, $options );

			if ( $result && isset($result['success']) && $result['success'] && ! empty( $result['audio_url'] ) ) {
				$this->config->getLogger()->info('[AdminInterface::handleTestProvider] Test audio generated successfully.', ['audio_url' => $result['audio_url'], 'provider' => $result['provider'] ?? 'N/A']);
				wp_send_json_success( [
					'message'   => __( 'Audio de prueba generado exitosamente.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
					'audio_url' => $result['audio_url'],
					'provider'  => $result['provider'] ?? 'N/A',
				] );
			} else {
				$error_message = isset($result['message']) && !empty($result['message']) ? $result['message'] : __( 'Falló la generación del audio de prueba. TTSService no retornó éxito o URL de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
				$this->config->getLogger()->error('[AdminInterface::handleTestProvider] TTS generation failed or returned invalid result.', ['result_from_service' => $result]);
				
				$error_details = '';
				if ( isset($result['error_code']) && $result['error_code'] === 'NO_PROVIDERS_CONFIGURED' ) {
					$error_details = __( 'No hay proveedores TTS configurados. Por favor ve a Configuración > Configuración TTS para configurar al menos un proveedor (OpenAI, Google Cloud, ElevenLabs, Amazon Polly, o Azure TTS).', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
				} else {
					$error_details = 'Provider attempted: ' . ($result['provider'] ?? 'Unknown') . '. Check plugin logs for more details from TTSService.';
				}
				
				wp_send_json_error( [
					'message' => $error_message,
					'error_details' => $error_details,
					'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR',
					'available_providers' => $result['available_providers'] ?? null
				] );
			}
		} catch ( \Exception $e ) {
			$this->config->getLogger()->error( '[AdminInterface::handleTestProvider] Exception during test audio generation.', [ 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString() ] );
			wp_send_json_error( [
				'message' => __( 'Ocurrió una excepción durante la generación del audio de prueba.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error_details' => $e->getMessage(),
			] );
		}
	}
	
	/**
	 * Handle get voices AJAX request
	 */
	public function handleGetVoices(): void {
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_admin' ) ) {
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		$provider = isset($_POST['provider']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['provider'])) ) : '';
		
		// Add comprehensive debugging
		error_log('[TTS Tools Debug] ========== GET VOICES REQUEST ==========');
		error_log('[TTS Tools Debug] Provider requested: ' . $provider);
		error_log('[TTS Tools Debug] POST data: ' . wp_json_encode($_POST));
		error_log('[TTS Tools Debug] Nonce verification passed');
		
		// Validate provider name
		$valid_providers = ['google', 'openai', 'elevenlabs', 'azure_tts', 'amazon_polly'];
		if ( !in_array($provider, $valid_providers) ) {
			error_log('[TTS Tools Debug] Invalid provider: ' . $provider);
			wp_send_json_error( [
				'message' => 'Invalid provider: ' . $provider,
				'provider' => $provider,
				'valid_providers' => $valid_providers,
				'debug' => 'Invalid provider in AdminInterface::handleGetVoices'
			] );
			return;
		}
		
		try {
			error_log('[TTS Tools Debug] Calling tts_service->getAvailableVoices() for provider: ' . $provider);
			$voices = $this->tts_service->getAvailableVoices( $provider );
			
			error_log('[TTS Tools Debug] Voices retrieved: ' . count($voices) . ' voices');
			error_log('[TTS Tools Debug] Voices data type: ' . gettype($voices));
			error_log('[TTS Tools Debug] First voice sample: ' . wp_json_encode(array_slice($voices, 0, 2)));
			
			wp_send_json_success( [
				'provider' => $provider,
				'voices' => $voices,
				'count' => count($voices),
				'debug' => 'Success from AdminInterface::handleGetVoices',
				'timestamp' => current_time('mysql')
			] );
			
		} catch ( \Exception $e ) {
			error_log('[TTS Tools Debug] Exception in getAvailableVoices: ' . $e->getMessage());
			error_log('[TTS Tools Debug] Exception trace: ' . $e->getTraceAsString());
			
			wp_send_json_error( [
				'message' => 'Failed to load voices: ' . $e->getMessage(),
				'provider' => $provider,
				'debug' => 'Exception in AdminInterface::handleGetVoices',
				'error_details' => [
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine()
				]
			] );
		}
	}
	
	/**
	 * Handle voice preview AJAX request
	 */
	public function handlePreviewVoice(): void {
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_admin' ) ) {
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}

		$provider = isset($_POST['provider']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['provider'])) ) : '';
		$voice = isset($_POST['voice']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['voice'])) ) : '';
		$text = isset($_POST['text']) ? $this->security->sanitizeTextForTTS( sanitize_textarea_field(wp_unslash($_POST['text'])) ) : '';

		if ( empty( $text ) ) {
			$text = __( 'Esta es una muestra de vista previa de voz.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		}

		try {
			$result = $this->tts_service->generatePreview( $text, $provider, $voice );

			if ( $result && isset($result->url) && ! empty($result->url) ) {
				wp_send_json_success( [
					'audio_url' => $result->url,
					'provider' => $provider,
					'duration' => $result->duration ?? 0,
					'message' => __( 'Vista previa generada exitosamente', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				] );
			} else {
				wp_send_json_error( [
					'message' => __( 'Falló la generación de vista previa - No se retornó URL de audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Falló la generación de vista previa', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle custom audio generation
	 */
	public function handleGenerateCustom(): void {
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_admin' ) ) {
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}

		$provider = isset($_POST['provider']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['provider'])) ) : '';
		$voice = isset($_POST['voice']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['voice'])) ) : '';
		$text = isset($_POST['text']) ? $this->security->sanitizeTextForTTS( sanitize_textarea_field(wp_unslash($_POST['text'])) ) : '';

		if ( empty( $text ) ) {
			wp_send_json_error( [
				'message' => __( 'El texto es requerido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
			] );
			return;
		}

		try {
			$options = [
				'provider' => $provider,
				'voice' => $voice,
				'custom' => true,
			];

			$result = $this->tts_service->generateAudio( $text, $options );

			if ( $result && $result['success'] ) {
				wp_send_json_success( [
					'audio_url' => $result['audio_url'],
					'provider' => $result['provider'] ?? $provider,
					'message' => __( 'Audio generado exitosamente', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				] );
			} else {
				wp_send_json_error( [
					'message' => $result['message'] ?? __( 'Falló la generación', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Falló la generación', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Render Audio Assets tab
	 */
	private function renderAudioAssetsTab( array $config ): void {
		echo '<div class="tts-tab-content">';
		echo '<h2>' . esc_html__( 'Configuración de Recursos de Audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configura archivos de audio de introducción y cierre predeterminados para grabaciones TTS. Estos se añadirán antes y después del contenido principal.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		echo '<div class="tts-audio-assets-section">';
		echo '<h3>' . esc_html__( 'Recursos de Audio Predeterminados', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Audio de Introducción Predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderDefaultIntroField();
		echo '<p class="description">' . esc_html__( 'Selecciona el archivo de audio de introducción predeterminado que se añadirá al inicio de las grabaciones TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Música de Fondo Predeterminada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderDefaultBackgroundField();
		echo '<p class="description">' . esc_html__( 'Selecciona la música de fondo predeterminada que sonará durante las grabaciones TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Audio de Cierre Predeterminado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderDefaultOutroField();
		echo '<p class="description">' . esc_html__( 'Selecciona el archivo de audio de cierre predeterminado que se añadirá al final de las grabaciones TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render audio assets section description
	 */
	public function renderAudioAssetsSection(): void {
		echo '<p>' . esc_html__( 'Configura archivos de audio de introducción y cierre para tus grabaciones TTS.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
	}

	/**
	 * Render Default Intro field
	 */
	public function renderDefaultIntroField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$intro_id = $config['audio_assets']['default_intro'] ?? '';
		$intro_url = '';
		$intro_title = '';
		
		if ( $intro_id ) {
			$intro_url = wp_get_attachment_url( $intro_id );
			$intro_title = get_the_title( $intro_id );
		}
		
		echo '<div class="tts-media-selector" data-type="intro">';
		echo '<input type="hidden" name="wp_tts_config[audio_assets][default_intro]" value="' . esc_attr( $intro_id ) . '" class="tts-media-id" />';
		
		echo '<div class="tts-media-preview" style="' . ( $intro_id ? '' : 'display: none;' ) . '">';
		if ( $intro_url ) {
			echo '<audio controls style="width: 100%; margin-bottom: 10px;">';
			echo '<source src="' . esc_url( $intro_url ) . '" type="audio/mpeg">';
			echo esc_html__( 'Tu navegador no soporta el elemento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
			echo '</audio>';
		}
		echo '<p class="tts-media-title">' . esc_html( $intro_title ) . '</p>';
		echo '</div>';
		
		echo '<div class="tts-media-buttons">';
		echo '<button type="button" class="button tts-select-media">' . esc_html__( 'Seleccionar Audio de Introducción', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</button>';
		echo '<button type="button" class="button tts-remove-media" style="' . ( $intro_id ? '' : 'display: none;' ) . '">' . esc_html__( 'Remover', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Default Background field
	 */
	public function renderDefaultBackgroundField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$background_id = $config['audio_assets']['default_background'] ?? '';
		$background_volume = $config['audio_assets']['background_volume'] ?? 0.3;
		$background_url = '';
		$background_title = '';
		
		if ( $background_id ) {
			$background_url = wp_get_attachment_url( $background_id );
			$background_title = get_the_title( $background_id );
		}
		
		echo '<div class="tts-media-selector" data-type="background">';
		echo '<input type="hidden" name="wp_tts_config[audio_assets][default_background]" value="' . esc_attr( $background_id ) . '" class="tts-media-id" />';
		
		echo '<div class="tts-media-preview" style="' . ( $background_id ? '' : 'display: none;' ) . '">';
		if ( $background_url ) {
			echo '<audio controls style="width: 100%; margin-bottom: 10px;">';
			echo '<source src="' . esc_url( $background_url ) . '" type="audio/mpeg">';
			echo esc_html__( 'Tu navegador no soporta el elemento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
			echo '</audio>';
		}
		echo '<p class="tts-media-title">' . esc_html( $background_title ) . '</p>';
		echo '</div>';
		
		echo '<div class="tts-media-buttons">';
		echo '<button type="button" class="button tts-select-media">' . esc_html__( 'Seleccionar Música de Fondo', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</button>';
		echo '<button type="button" class="button tts-remove-media" style="' . ( $background_id ? '' : 'display: none;' ) . '">' . esc_html__( 'Remover', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</button>';
		echo '</div>';
		
		// Background Volume Control
		echo '<div style="margin-top: 15px;">';
		echo '<label>' . esc_html__( 'Volumen Predeterminado:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</label>';
		echo '<input type="range" name="wp_tts_config[audio_assets][background_volume]" ';
		echo 'value="' . esc_attr( $background_volume ) . '" ';
		echo 'min="0" max="1" step="0.1" style="width: 150px; margin-left: 10px;">';
		echo '<span class="volume-display">' . esc_html( $background_volume ) . '</span>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Default Outro field
	 */
	public function renderDefaultOutroField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$outro_id = $config['audio_assets']['default_outro'] ?? '';
		$outro_url = '';
		$outro_title = '';
		
		if ( $outro_id ) {
			$outro_url = wp_get_attachment_url( $outro_id );
			$outro_title = get_the_title( $outro_id );
		}
		
		echo '<div class="tts-media-selector" data-type="outro">';
		echo '<input type="hidden" name="wp_tts_config[audio_assets][default_outro]" value="' . esc_attr( $outro_id ) . '" class="tts-media-id" />';
		
		echo '<div class="tts-media-preview" style="' . ( $outro_id ? '' : 'display: none;' ) . '">';
		if ( $outro_url ) {
			echo '<audio controls style="width: 100%; margin-bottom: 10px;">';
			echo '<source src="' . esc_url( $outro_url ) . '" type="audio/mpeg">';
			echo esc_html__( 'Tu navegador no soporta el elemento de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
			echo '</audio>';
		}
		echo '<p class="tts-media-title">' . esc_html( $outro_title ) . '</p>';
		echo '</div>';
		
		echo '<div class="tts-media-buttons">';
		echo '<button type="button" class="button tts-select-media">' . esc_html__( 'Seleccionar Audio de Cierre', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</button>';
		echo '<button type="button" class="button tts-remove-media" style="' . ( $outro_id ? '' : 'display: none;' ) . '">' . esc_html__( 'Remover', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Handle auto-save for audio assets (intro/outro)
	 */
	public function handleAutoSaveAudioAsset(): void {
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_auto_save' ) ) {
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$asset_type = isset($_POST['asset_type']) ? sanitize_text_field(wp_unslash($_POST['asset_type'])) : '';
		$attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
		
		if ( ! $post_id || ! in_array($asset_type, ['intro_audio', 'outro_audio', 'background_audio', 'custom_audio']) ) {
			wp_send_json_error( [
				'message' => __( 'Parámetros inválidos.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			] );
			return;
		}
		
		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		try {
			// Get existing TTS data
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post_id);
				
				// Update audio assets
				if ( ! isset($tts_data['audio_assets']) ) {
					$tts_data['audio_assets'] = [];
				}
				
				$tts_data['audio_assets'][$asset_type] = $attachment_id ?: '';
				$tts_data['updated_at'] = current_time('mysql');
				
				// Save updated data
				\WP_TTS\Utils\TTSMetaManager::saveTTSData($post_id, $tts_data);
				
				wp_send_json_success( [
					'message' => sprintf(
						__( 'Audio %s actualizado exitosamente.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
						ucfirst($asset_type)
					),
					'asset_type' => $asset_type,
					'attachment_id' => $attachment_id
				] );
			} else {
				wp_send_json_error( [
					'message' => __( 'Administrador de Meta TTS no disponible.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Falló al guardar recurso de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
		}
	}

	/**
	 * Render hidden fields for other tabs to preserve their data
	 *
	 * @param array  $config Current configuration
	 * @param string $active_tab Currently active tab
	 */
	private function renderHiddenFieldsForOtherTabs( array $config, string $active_tab ): void {
		// Preserve data from all tabs except the active one
		$tabs_to_preserve = [];
		
		switch ( $active_tab ) {
			case 'defaults':
				$tabs_to_preserve = ['providers', 'storage', 'audio_assets', 'player'];
				break;
			case 'providers':
				$tabs_to_preserve = ['defaults', 'storage', 'audio_assets', 'player'];
				break;
			case 'storage':
				$tabs_to_preserve = ['defaults', 'providers', 'audio_assets', 'player'];
				break;
			case 'audio_assets':
				$tabs_to_preserve = ['defaults', 'providers', 'storage', 'player'];
				break;
			case 'player':
				$tabs_to_preserve = ['defaults', 'providers', 'storage', 'audio_assets'];
				break;
		}
		
		foreach ( $tabs_to_preserve as $tab ) {
			$this->renderHiddenFieldsForTab( $config, $tab );
		}
	}

	/**
	 * Render hidden fields for a specific tab
	 *
	 * @param array  $config Current configuration
	 * @param string $tab Tab name
	 */
	private function renderHiddenFieldsForTab( array $config, string $tab ): void {
		switch ( $tab ) {
			case 'defaults':
				$this->renderHiddenDefaultsFields( $config );
				break;
			case 'providers':
				$this->renderHiddenProvidersFields( $config );
				break;
			case 'storage':
				$this->renderHiddenStorageFields( $config );
				break;
			case 'audio_assets':
				$this->renderHiddenAudioAssetsFields( $config );
				break;
		}
	}

	/**
	 * Render hidden fields for defaults tab
	 */
	private function renderHiddenDefaultsFields( array $config ): void {
		$defaults = $config['defaults'] ?? [];
		
		$fields = [
			'default_provider', 'default_storage', 'auto_generate', 'voice_speed',
			'voice_pitch', 'audio_format', 'audio_quality', 'enable_ssml',
			'add_pauses', 'background_processing'
		];
		
		foreach ( $fields as $field ) {
			if ( isset( $defaults[$field] ) ) {
				$value = is_bool( $defaults[$field] ) ? ($defaults[$field] ? '1' : '0') : $defaults[$field];
				echo '<input type="hidden" name="wp_tts_config[defaults][' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '" />';
			}
		}
	}

	/**
	 * Render hidden fields for providers tab
	 */
	private function renderHiddenProvidersFields( array $config ): void {
		$providers = $config['providers'] ?? [];
		
		foreach ( $providers as $provider => $provider_config ) {
			if ( is_array( $provider_config ) ) {
				foreach ( $provider_config as $key => $value ) {
					if ( is_scalar( $value ) ) {
						echo '<input type="hidden" name="wp_tts_config[providers][' . esc_attr( $provider ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
					}
				}
			}
		}
	}

	/**
	 * Render hidden fields for storage tab
	 */
	private function renderHiddenStorageFields( array $config ): void {
		$storage = $config['storage'] ?? [];
		
		// Ensure storage is an array
		if ( ! is_array( $storage ) ) {
			$storage = [];
		}
		
		foreach ( $storage as $storage_provider => $storage_config ) {
			if ( is_array( $storage_config ) ) {
				foreach ( $storage_config as $key => $value ) {
					if ( is_scalar( $value ) ) {
						echo '<input type="hidden" name="wp_tts_config[storage][' . esc_attr( $storage_provider ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
					}
				}
			}
		}
	}

	/**
	 * Render hidden fields for audio assets tab
	 */
	private function renderHiddenAudioAssetsFields( array $config ): void {
		$audio_assets = $config['audio_assets'] ?? [];
		
		$fields = [
			'default_intro', 'default_background', 'default_outro',
			'intro_volume', 'background_volume', 'outro_volume'
		];
		
		foreach ( $fields as $field ) {
			if ( isset( $audio_assets[$field] ) ) {
				echo '<input type="hidden" name="wp_tts_config[audio_assets][' . esc_attr( $field ) . ']" value="' . esc_attr( $audio_assets[$field] ) . '" />';
			}
		}
		
		// Handle arrays for files
		$array_fields = ['intro_files', 'background_music', 'outro_files'];
		foreach ( $array_fields as $array_field ) {
			if ( isset( $audio_assets[$array_field] ) && is_array( $audio_assets[$array_field] ) ) {
				foreach ( $audio_assets[$array_field] as $key => $value ) {
					echo '<input type="hidden" name="wp_tts_config[audio_assets][' . esc_attr( $array_field ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
				}
			}
		}
	}

	/**
	 * Handle auto save background volume AJAX request
	 */
	public function handleAutoSaveBackgroundVolume(): void {
		// Security check with nonce verification
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_auto_save' ) ) {
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Get parameters
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$volume = isset($_POST['volume']) ? floatval($_POST['volume']) : 0.3;
		
		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'ID de entrada inválido.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			] );
			return;
		}
		
		// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Validate volume range
		$volume = max(0, min(1, $volume));
		
		try {
			// Get existing TTS data
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData($post_id);
				
				// Initialize audio_assets if not exists
				if ( ! isset($tts_data['audio_assets']) ) {
					$tts_data['audio_assets'] = [];
				}
				
				// Save the background volume
				$tts_data['audio_assets']['background_volume'] = $volume;
				$tts_data['updated_at'] = current_time('mysql');
				
				// Save updated data
				\WP_TTS\Utils\TTSMetaManager::saveTTSData($post_id, $tts_data);
				
				wp_send_json_success( [
					'message' => __( 'Volumen de fondo actualizado exitosamente.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
					'volume' => $volume
				] );
			} else {
				wp_send_json_error( [
					'message' => __( 'Administrador de Meta TTS no disponible.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Falló al guardar volumen de fondo.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
		}
	}

	/**
	 * Render Player tab
	 */
	private function renderPlayerTab( array $config ): void {
		echo '<div class="tts-tab-content">';
		echo '<h2>' . esc_html__( 'Configuración del Reproductor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h2>';
		echo '<p>' . esc_html__( 'Elige entre diferentes estilos de reproductor y configura el comportamiento del reproductor.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		echo '<table class="form-table">';
		
		// Player Style Selection
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Estilo del Reproductor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerStyleField( $config );
		echo '<p class="description">' . esc_html__( 'Elige el estilo del reproductor para mostrar audio TTS en tu sitio web.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Auto-insertion Settings
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Insertar Reproductor Automáticamente', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderAutoInsertField( $config );
		echo '<p class="description">' . esc_html__( 'Insertar automáticamente el reproductor TTS en entradas y páginas.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Player Position
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Posición del Reproductor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerPositionField( $config );
		echo '<p class="description">' . esc_html__( 'Dónde mostrar el reproductor al insertar automáticamente.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '<br>' . 
		     esc_html__( 'La opción manual te permite usar el shortcode:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . ' <code>[wp_tts_player]</code><br>' .
		     esc_html__( 'Parámetros opcionales:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . ' <code>[wp_tts_player post_id="123" style="sesolibre"]</code>' . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Volume Controls Section
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Controles de Volumen', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderVolumeControlsField( $config );
		echo '<p class="description">' . esc_html__( 'Configura qué controles de volumen mostrar en el reproductor SesoLibre.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Player Information Section
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Información del Reproductor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerInfoField( $config );
		echo '<p class="description">' . esc_html__( 'Configura qué información mostrar debajo de la barra de progreso en el reproductor SesoLibre.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Render player style field
	 */
	private function renderPlayerStyleField( array $config ): void {
		$player_style = $config['player']['style'] ?? 'classic';
		
		echo '<select name="wp_tts_config[player][style]" id="player_style" class="tts-player-setting" data-setting="style">';
		echo '<option value="classic"' . selected( $player_style, 'classic', false ) . '>' . esc_html__( 'Reproductor Clásico', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '<option value="sesolibre"' . selected( $player_style, 'sesolibre', false ) . '>' . esc_html__( 'Reproductor SesoLibre (con Mezcla de Audio)', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '<option value="minimal"' . selected( $player_style, 'minimal', false ) . '>' . esc_html__( 'Reproductor Minimalista', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '<option value="enhanced_sesolibre"' . selected( $player_style, 'enhanced_sesolibre', false ) . '>' . esc_html__( 'Reproductor SesoLibre Mejorado (con Imagen Destacada)', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Render auto-insert field
	 */
	private function renderAutoInsertField( array $config ): void {
		$auto_insert = $config['player']['auto_insert'] ?? false;
		
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][auto_insert]" value="1" class="tts-player-setting" data-setting="auto_insert" ' . checked( $auto_insert, true, false ) . ' />';
		echo ' ' . esc_html__( 'Insertar automáticamente reproductor TTS en entradas', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
	}

	/**
	 * Render player position field
	 */
	private function renderPlayerPositionField( array $config ): void {
		$position = $config['player']['position'] ?? 'before_content';
		
		echo '<select name="wp_tts_config[player][position]" id="player_position">';
		echo '<option value="before_content"' . selected( $position, 'before_content', false ) . '>' . esc_html__( 'Antes del Contenido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '<option value="after_content"' . selected( $position, 'after_content', false ) . '>' . esc_html__( 'Después del Contenido', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '<option value="manual"' . selected( $position, 'manual', false ) . '>' . esc_html__( 'Manual (Shortcode)', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Render volume controls field
	 */
	private function renderVolumeControlsField( array $config ): void {
		$show_voice_volume = $config['player']['show_voice_volume'] ?? true;
		$show_background_volume = $config['player']['show_background_volume'] ?? true;
		
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_voice_volume]" value="1" class="tts-player-setting" data-setting="show_voice_volume" ' . checked( $show_voice_volume, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Control de Volumen de Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		
		echo '<div>';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_background_volume]" value="1" class="tts-player-setting" data-setting="show_background_volume" ' . checked( $show_background_volume, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Control de Volumen de Música de Fondo', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
	}

	/**
	 * Render player information field
	 */
	private function renderPlayerInfoField( array $config ): void {
		$show_tts_service = $config['player']['show_tts_service'] ?? true;
		$show_voice_name = $config['player']['show_voice_name'] ?? true;
		$show_download_link = $config['player']['show_download_link'] ?? true;
		$show_article_title = $config['player']['show_article_title'] ?? true;
		
		echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
		
		// First column
		echo '<div>';
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_tts_service]" value="1" class="tts-player-setting" data-setting="show_tts_service" ' . checked( $show_tts_service, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Servicio TTS', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_voice_name]" value="1" class="tts-player-setting" data-setting="show_voice_name" ' . checked( $show_voice_name, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Nombre de Voz', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		echo '</div>';
		
		// Second column
		echo '<div>';
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_download_link]" value="1" class="tts-player-setting" data-setting="show_download_link" ' . checked( $show_download_link, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Enlace de Descarga', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_article_title]" value="1" class="tts-player-setting" data-setting="show_article_title" ' . checked( $show_article_title, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Título del Artículo', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		
		$show_featured_image = $config['player']['show_featured_image'] ?? true;
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_featured_image]" value="1" class="tts-player-setting" data-setting="show_featured_image" ' . checked( $show_featured_image, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Imagen Destacada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		
		$show_speed_control = $config['player']['show_speed_control'] ?? true;
		echo '<div style="margin-bottom: 10px;">';
		echo '<label>';
		echo '<input type="checkbox" name="wp_tts_config[player][show_speed_control]" value="1" class="tts-player-setting" data-setting="show_speed_control" ' . checked( $show_speed_control, true, false ) . ' />';
		echo ' ' . esc_html__( 'Mostrar Control de Velocidad', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
		echo '</label>';
		echo '</div>';
		echo '</div>';
		
		echo '</table>';
		
		// CSS Customization section
		echo '<div style="margin-top: 30px;">';
		echo '<h3>' . esc_html__( 'Personalización CSS del Reproductor Mejorado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<p>' . esc_html__( 'Personaliza los colores y estilos del reproductor SesoLibre mejorado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '<table class="form-table">';
		
		// Play icon color
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Color del Icono de Play', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerColorField( $config, 'play_icon_color', '#007cba' );
		echo '<p class="description">' . esc_html__( 'Color del icono de reproducir en el reproductor mejorado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Pause icon color
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Color del Icono de Pausa', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerColorField( $config, 'pause_icon_color', '#007cba' );
		echo '<p class="description">' . esc_html__( 'Color del icono de pausa en el reproductor mejorado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Progress color
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Color de la Barra de Progreso', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerColorField( $config, 'progress_color', '#007cba' );
		echo '<p class="description">' . esc_html__( 'Color de la barra de progreso y elementos activos.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Background color
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Color de Fondo del Reproductor', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerColorField( $config, 'player_background_color', '#f8f9fa' );
		echo '<p class="description">' . esc_html__( 'Color de fondo principal del reproductor.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Text color
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Color del Texto', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerColorField( $config, 'player_text_color', '#333333' );
		echo '<p class="description">' . esc_html__( 'Color del texto principal en el reproductor.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Custom CSS
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'CSS Personalizado', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th>';
		echo '<td>';
		$this->renderPlayerCustomCSSField( $config );
		echo '<p class="description">' . esc_html__( 'CSS personalizado para el reproductor mejorado. Use selectores como .tts-enhanced-sesolibre-player para personalizar estilos específicos.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Shortcode documentation section
		echo '<div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 6px;">';
		echo '<h3 style="margin-top: 0;">' . esc_html__( 'Documentación de Shortcode', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</h3>';
		echo '<p>' . esc_html__( 'Al usar la posición Manual, puedes colocar el reproductor TTS en cualquier lugar de tu contenido usando el shortcode:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		
		echo '<table class="widefat" style="margin: 15px 0;">';
		echo '<thead><tr><th>' . esc_html__( 'Shortcode', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th><th>' . esc_html__( 'Descripción', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><code>[wp_tts_player]</code></td><td>' . esc_html__( 'Reproductor básico para la entrada actual', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</td></tr>';
		echo '<tr><td><code>[wp_tts_player style="classic"]</code></td><td>' . esc_html__( 'Forzar estilo de reproductor clásico', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</td></tr>';
		echo '<tr><td><code>[wp_tts_player style="sesolibre"]</code></td><td>' . esc_html__( 'Forzar reproductor SesoLibre con mezcla de audio', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</td></tr>';
		echo '<tr><td><code>[wp_tts_player style="minimal"]</code></td><td>' . esc_html__( 'Forzar reproductor minimalista con visualización de ondas', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</td></tr>';
		echo '<tr><td><code>[wp_tts_player style="enhanced_sesolibre"]</code></td><td>' . esc_html__( 'Forzar reproductor SesoLibre mejorado con imagen destacada', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</td></tr>';
		echo '<tr><td><code>[wp_tts_player post_id="123"]</code></td><td>' . esc_html__( 'Reproductor para ID de entrada específico', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';
		
		echo '<p><strong>' . esc_html__( 'Nota:', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</strong> ' . 
		     esc_html__( 'El shortcode solo se mostrará si TTS está habilitado para la entrada especificada y se ha generado audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ) . '</p>';
		echo '</div>';
		
		echo '</div>';
	}

	/**
	 * Sanitize and save settings
	 */
	public function sanitizeSettings( $input ): array {
		// Debug: Log the incoming input structure
		error_log( 'TTS DEBUG: sanitizeSettings input structure: ' . print_r( $input, true ) );
		
		// Get current configuration
		$config = $this->config;
		
		// Process providers settings
		if ( isset( $input['providers'] ) ) {
			foreach ( $input['providers'] as $provider => $settings ) {
				// Sanitize provider settings
				$sanitized = [];
				foreach ( $settings as $key => $value ) {
					if ( in_array( $key, ['enabled'] ) ) {
						$sanitized[$key] = (bool) $value;
					} elseif ( in_array( $key, ['quota_limit', 'priority'] ) ) {
						$sanitized[$key] = (int) $value;
					} else {
						$sanitized[$key] = sanitize_text_field( $value );
					}
				}
				$config->setProviderConfig( $provider, $sanitized );
			}
		}
		
		// Process player settings
		if ( isset( $input['player'] ) ) {
			$player_settings = [];
			$player_settings['style'] = sanitize_text_field( $input['player']['style'] ?? 'classic' );
			$player_settings['auto_insert'] = isset( $input['player']['auto_insert'] ) ? true : false;
			$player_settings['position'] = sanitize_text_field( $input['player']['position'] ?? 'before_content' );
			$player_settings['show_voice_volume'] = isset( $input['player']['show_voice_volume'] ) ? true : false;
			$player_settings['show_background_volume'] = isset( $input['player']['show_background_volume'] ) ? true : false;
			$player_settings['show_tts_service'] = isset( $input['player']['show_tts_service'] ) ? true : false;
			$player_settings['show_voice_name'] = isset( $input['player']['show_voice_name'] ) ? true : false;
			$player_settings['show_download_link'] = isset( $input['player']['show_download_link'] ) ? true : false;
			$player_settings['show_article_title'] = isset( $input['player']['show_article_title'] ) ? true : false;
			$player_settings['show_featured_image'] = isset( $input['player']['show_featured_image'] ) ? true : false;
			$player_settings['show_speed_control'] = isset( $input['player']['show_speed_control'] ) ? true : false;
			
			// CSS customization settings for enhanced player
			$player_settings['play_icon_color'] = sanitize_hex_color( $input['player']['play_icon_color'] ?? '#007cba' );
			$player_settings['pause_icon_color'] = sanitize_hex_color( $input['player']['pause_icon_color'] ?? '#007cba' );
			$player_settings['progress_color'] = sanitize_hex_color( $input['player']['progress_color'] ?? '#007cba' );
			$player_settings['player_background_color'] = sanitize_hex_color( $input['player']['player_background_color'] ?? '#f8f9fa' );
			$player_settings['player_text_color'] = sanitize_hex_color( $input['player']['player_text_color'] ?? '#333333' );
			$player_settings['enhanced_player_css'] = wp_strip_all_tags( $input['player']['enhanced_player_css'] ?? '', true );
			
			// Debug: Log what we're saving
			error_log( 'TTS DEBUG: Saving player settings: ' . print_r( $player_settings, true ) );
			
			// Save player settings directly to ConfigurationManager
			$config->set( 'player', $player_settings );
			
			// Debug: Verify what was saved
			$saved_settings = $config->get( 'player' );
			error_log( 'TTS DEBUG: Settings after save: ' . print_r( $saved_settings, true ) );
		}
		
		// Process storage settings
		if ( isset( $input['storage'] ) && is_array( $input['storage'] ) ) {
			foreach ( $input['storage'] as $storage_provider => $settings ) {
				// Skip if settings is not an array (defensive programming)
				if ( ! is_array( $settings ) ) {
					error_log( "TTS DEBUG: Skipping storage provider '$storage_provider' - settings is not an array: " . print_r( $settings, true ) );
					continue;
				}
				
				error_log( "TTS DEBUG: Processing storage provider '$storage_provider' with settings: " . print_r( $settings, true ) );
				
				// Sanitize storage provider settings
				$sanitized = [];
				
				// Handle checkbox fields - ensure they're set to false if not present
				$checkbox_fields = ['enabled', 'auto_publish', 'make_private', 'include_link'];
				foreach ( $checkbox_fields as $checkbox_field ) {
					$sanitized[$checkbox_field] = isset( $settings[$checkbox_field] ) ? (bool) $settings[$checkbox_field] : false;
				}
				
				// Handle other fields
				foreach ( $settings as $key => $value ) {
					if ( in_array( $key, $checkbox_fields ) ) {
						// Already handled above
						continue;
					} elseif ( in_array( $key, ['max_file_size'] ) ) {
						$sanitized[$key] = (int) $value;
					} else {
						$sanitized[$key] = sanitize_text_field( $value );
					}
				}
				
				error_log( "TTS DEBUG: Sanitized storage settings for '$storage_provider': " . print_r( $sanitized, true ) );
				
				// Update storage configuration
				$current_storage = $config->getStorageConfig( $storage_provider );
				$updated_storage = array_merge( $current_storage, $sanitized );
				$config->set( "storage.{$storage_provider}", $updated_storage, false );
				
				error_log( "TTS DEBUG: Updated storage config for '$storage_provider': " . print_r( $updated_storage, true ) );
			}
		}
		
		// Process audio assets settings
		if ( isset( $input['audio_assets'] ) ) {
			$audio_assets = [];
			foreach ( $input['audio_assets'] as $key => $value ) {
				if ( in_array( $key, ['default_intro', 'default_background', 'default_outro'] ) ) {
					$audio_assets[$key] = (int) $value;
				} elseif ( $key === 'background_volume' ) {
					$audio_assets[$key] = (float) $value;
				} else {
					$audio_assets[$key] = sanitize_text_field( $value );
				}
			}
			$config->set( 'audio_library', array_merge( $config->get('audio_library', []), $audio_assets ) );
		}
		
		// Process default settings
		if ( isset( $input['defaults'] ) ) {
			$defaults = [];
			foreach ( $input['defaults'] as $key => $value ) {
				if ( in_array( $key, ['auto_generate', 'enable_ssml', 'add_pauses', 'background_processing'] ) ) {
					$defaults[$key] = (bool) $value;
				} elseif ( in_array( $key, ['voice_speed'] ) ) {
					$defaults[$key] = (float) $value;
				} elseif ( in_array( $key, ['voice_pitch'] ) ) {
					$defaults[$key] = (int) $value;
				} else {
					$defaults[$key] = sanitize_text_field( $value );
				}
			}
			$config->updateDefaults( $defaults );
		}
		
		// Ensure all changes are saved to database
		$config->save();
		
		// Return the input as-is (settings are saved via ConfigurationManager)
		return $input;
	}

	/**
	 * Handle save player config AJAX request
	 */
	public function handleSavePlayerConfig(): void {
		try {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wp_tts_admin' ) ) {
				wp_send_json_error( [
					'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
				return;
			}

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [
					'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
				return;
			}

			// Process player settings from POST data
			$player_settings = [];
			$player_settings['style'] = sanitize_text_field( $_POST['style'] ?? 'classic' );
			$player_settings['auto_insert'] = isset( $_POST['auto_insert'] ) && $_POST['auto_insert'] === '1';
			$player_settings['position'] = sanitize_text_field( $_POST['position'] ?? 'before_content' );
			$player_settings['show_voice_volume'] = isset( $_POST['show_voice_volume'] ) && $_POST['show_voice_volume'] === '1';
			$player_settings['show_background_volume'] = isset( $_POST['show_background_volume'] ) && $_POST['show_background_volume'] === '1';
			$player_settings['show_tts_service'] = isset( $_POST['show_tts_service'] ) && $_POST['show_tts_service'] === '1';
			$player_settings['show_voice_name'] = isset( $_POST['show_voice_name'] ) && $_POST['show_voice_name'] === '1';
			$player_settings['show_download_link'] = isset( $_POST['show_download_link'] ) && $_POST['show_download_link'] === '1';
			$player_settings['show_article_title'] = isset( $_POST['show_article_title'] ) && $_POST['show_article_title'] === '1';
			$player_settings['show_featured_image'] = isset( $_POST['show_featured_image'] ) && $_POST['show_featured_image'] === '1';
			$player_settings['show_speed_control'] = isset( $_POST['show_speed_control'] ) && $_POST['show_speed_control'] === '1';
			
			// CSS customization settings for enhanced player
			$player_settings['play_icon_color'] = sanitize_hex_color( $_POST['play_icon_color'] ?? '#007cba' );
			$player_settings['pause_icon_color'] = sanitize_hex_color( $_POST['pause_icon_color'] ?? '#007cba' );
			$player_settings['progress_color'] = sanitize_hex_color( $_POST['progress_color'] ?? '#007cba' );
			$player_settings['player_background_color'] = sanitize_hex_color( $_POST['player_background_color'] ?? '#f8f9fa' );
			$player_settings['player_text_color'] = sanitize_hex_color( $_POST['player_text_color'] ?? '#333333' );
			$player_settings['enhanced_player_css'] = wp_strip_all_tags( $_POST['enhanced_player_css'] ?? '', true );
			
			// Save directly to ConfigurationManager
			$this->config->set( 'player', $player_settings );
			
			wp_send_json_success( [
				'message' => __( 'Configuración del reproductor guardada exitosamente.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'settings' => $player_settings
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Falló al guardar la configuración del reproductor.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
		}
	}
	
	/**
	 * Handle post content extraction for editing
	 */
	public function handleExtractPostContent(): void {
		// Enhanced error logging for debugging
		error_log('TTS DEBUG: handleExtractPostContent called');
		error_log('TTS DEBUG: POST data: ' . print_r($_POST, true));
		
		if ( ! isset($_POST['nonce']) ) {
			error_log('TTS DEBUG: No nonce provided in POST data');
			wp_send_json_error( [
				'message' => __( 'Nonce no proporcionado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		$nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
		error_log('TTS DEBUG: Nonce received: ' . $nonce);
		
		// Test direct nonce verification to bypass SecurityManager
		$direct_verify = wp_verify_nonce( $nonce, 'wp_tts_admin' );
		error_log('TTS DEBUG: Direct wp_verify_nonce result: ' . ($direct_verify ? 'SUCCESS' : 'FAILED'));
		
		if ( ! $direct_verify ) {
			error_log('TTS DEBUG: Direct nonce verification failed for action: wp_tts_admin');
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		error_log('TTS DEBUG: Nonce verification passed, checking user capabilities');
		
		// Test direct capability check to bypass SecurityManager
		$can_edit = current_user_can( 'edit_posts' );
		error_log('TTS DEBUG: Direct current_user_can(edit_posts) result: ' . ($can_edit ? 'SUCCESS' : 'FAILED'));
		
		if ( ! $can_edit ) {
			error_log('TTS DEBUG: User does not have edit_posts capability');
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		error_log('TTS DEBUG: User capability check passed, proceeding with content extraction');

		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		error_log('TTS DEBUG: Post ID: ' . $post_id);
		
		if ( ! $post_id ) {
			error_log('TTS DEBUG: Invalid post ID');
			wp_send_json_error( [
				'message' => __( 'ID de post requerido.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			] );
			return;
		}

		try {
			error_log('TTS DEBUG: Starting content extraction for post ID: ' . $post_id);
			
			// Check if post exists first
			$post = get_post( $post_id );
			if ( ! $post ) {
				error_log('TTS DEBUG: Post not found with ID: ' . $post_id);
				wp_send_json_error( [
					'message' => __( 'Post no encontrado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
				return;
			}
			
			error_log('TTS DEBUG: Post found: ' . $post->post_title);
			
			// FIRST: Check if there's previously edited text
			$edited_text = get_post_meta( $post_id, '_tts_edited_text', true );
			$use_edited = get_post_meta( $post_id, '_tts_use_edited_text', true );
			$edited_timestamp = get_post_meta( $post_id, '_tts_edited_timestamp', true );
			
			error_log('TTS DEBUG: Checking for previously edited text');
			error_log('TTS DEBUG: Edited text length: ' . strlen($edited_text));
			error_log('TTS DEBUG: Use edited flag: ' . ($use_edited ? 'TRUE' : 'FALSE'));
			error_log('TTS DEBUG: Edit timestamp: ' . $edited_timestamp);
			
			if ( !empty($edited_text) && $use_edited ) {
				error_log('TTS DEBUG: Found previously edited text, returning it');
				
				// Return the previously edited text
				wp_send_json_success( [
					'text' => $edited_text,
					'validation' => [
						'valid' => strlen($edited_text) >= 10 && strlen($edited_text) <= 50000,
						'message' => 'Texto previamente editado cargado.'
					],
					'character_count' => strlen( $edited_text ),
					'word_count' => str_word_count( $edited_text ),
					'post_title' => get_the_title( $post_id ),
					'source' => 'edited',
					'edited_timestamp' => $edited_timestamp
				] );
				return;
			}
			
			error_log('TTS DEBUG: No previously edited text found, extracting original content');
			
			// If no edited text, extract original content
			$post_content = $post->post_content;
			$post_title = $post->post_title;
			
			// Try basic text extraction without TextProcessor first
			$simple_text = $post_title . '. ' . wp_strip_all_tags( $post_content );
			$simple_text = trim( preg_replace( '/\s+/', ' ', $simple_text ) );
			
			error_log('TTS DEBUG: Simple extraction successful, length: ' . strlen($simple_text));
			
			// Try to load TextProcessor for better extraction
			$textProcessorPath = plugin_dir_path( __FILE__ ) . '../Utils/TextProcessor.php';
			$extracted_text = $simple_text; // fallback
			
			if ( file_exists( $textProcessorPath ) ) {
				error_log('TTS DEBUG: TextProcessor file exists, attempting to use it');
				if ( ! class_exists( '\\WP_TTS\\Utils\\TextProcessor' ) ) {
					require_once $textProcessorPath;
				}
				
				if ( class_exists( '\\WP_TTS\\Utils\\TextProcessor' ) ) {
					error_log('TTS DEBUG: Using TextProcessor for extraction');
					$extracted_text = \WP_TTS\Utils\TextProcessor::extractPostContent( $post_id );
					error_log('TTS DEBUG: TextProcessor extraction successful, length: ' . strlen($extracted_text));
				} else {
					error_log('TTS DEBUG: TextProcessor class not available, using simple extraction');
				}
			} else {
				error_log('TTS DEBUG: TextProcessor file not found, using simple extraction');
			}
			
			// Basic validation
			$validation = [
				'valid' => strlen($extracted_text) >= 10 && strlen($extracted_text) <= 50000,
				'message' => strlen($extracted_text) >= 10 && strlen($extracted_text) <= 50000 ? 'Texto válido para TTS.' : 'Texto demasiado corto o largo.'
			];
			
			error_log('TTS DEBUG: Validation completed: ' . ($validation['valid'] ? 'VALID' : 'INVALID'));
			
			wp_send_json_success( [
				'text' => $extracted_text,
				'validation' => $validation,
				'character_count' => strlen( $extracted_text ),
				'word_count' => str_word_count( $extracted_text ),
				'post_title' => get_the_title( $post_id ),
				'source' => 'original'
			] );

		} catch ( \Exception $e ) {
			error_log('TTS DEBUG: Exception caught: ' . $e->getMessage());
			error_log('TTS DEBUG: Exception trace: ' . $e->getTraceAsString());
			wp_send_json_error( [
				'message' => __( 'Error al extraer el contenido del post.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
		} catch ( \Error $e ) {
			error_log('TTS DEBUG: Fatal error caught: ' . $e->getMessage());
			error_log('TTS DEBUG: Error trace: ' . $e->getTraceAsString());
			wp_send_json_error( [
				'message' => __( 'Error fatal al extraer el contenido del post.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
		}
	}
	
	/**
	 * Handle saving edited text temporarily
	 */
	public function handleSaveEditedText(): void {
		error_log('TTS DEBUG: handleSaveEditedText called');
		error_log('TTS DEBUG: POST data: ' . print_r($_POST, true));
		
		if ( ! isset($_POST['nonce']) ) {
			error_log('TTS DEBUG: No nonce provided in POST data');
			wp_send_json_error( [
				'message' => __( 'Nonce no proporcionado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		$nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
		error_log('TTS DEBUG: Nonce received: ' . $nonce);
		
		// Test direct nonce verification
		$direct_verify = wp_verify_nonce( $nonce, 'wp_tts_admin' );
		error_log('TTS DEBUG: Direct wp_verify_nonce result: ' . ($direct_verify ? 'SUCCESS' : 'FAILED'));
		
		if ( ! $direct_verify ) {
			error_log('TTS DEBUG: Direct nonce verification failed');
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Test direct capability check
		$can_edit = current_user_can( 'edit_posts' );
		error_log('TTS DEBUG: Direct current_user_can(edit_posts) result: ' . ($can_edit ? 'SUCCESS' : 'FAILED'));
		
		if ( ! $can_edit ) {
			error_log('TTS DEBUG: User does not have edit_posts capability');
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}

		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$edited_text = isset($_POST['text']) ? sanitize_textarea_field(wp_unslash($_POST['text'])) : '';
		
		error_log('TTS DEBUG: Post ID: ' . $post_id);
		error_log('TTS DEBUG: Text length: ' . strlen($edited_text));
		
		if ( ! $post_id ) {
			error_log('TTS DEBUG: Invalid post ID');
			wp_send_json_error( [
				'message' => __( 'ID de post requerido.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			] );
			return;
		}

		try {
			error_log('TTS DEBUG: Starting text save process');
			
			// Basic text validation without external classes
			error_log('TTS DEBUG: Step 1 - Basic text validation');
			$text_length = strlen( $edited_text );
			error_log('TTS DEBUG: Text length for validation: ' . $text_length);
			
			if ( empty( $edited_text ) ) {
				error_log('TTS DEBUG: Text is empty, sending error');
				wp_send_json_error( [
					'message' => 'El texto está vacío.'
				] );
				return;
			}
			
			if ( $text_length < 5 ) {
				error_log('TTS DEBUG: Text too short, sending error');
				wp_send_json_error( [
					'message' => 'El texto es demasiado corto (mínimo 5 caracteres).'
				] );
				return;
			}
			
			if ( $text_length > 50000 ) {
				error_log('TTS DEBUG: Text too long, sending error');
				wp_send_json_error( [
					'message' => 'El texto es demasiado largo (máximo 50,000 caracteres).'
				] );
				return;
			}
			
			error_log('TTS DEBUG: Basic validation PASSED');
			
			// Step 2: Save using simple WordPress meta functions
			error_log('TTS DEBUG: Step 2 - Saving with basic WordPress functions');
			
			error_log('TTS DEBUG: Calling update_post_meta for edited text');
			$meta_result1 = update_post_meta( $post_id, '_tts_edited_text', $edited_text );
			error_log('TTS DEBUG: update_post_meta(_tts_edited_text) result: ' . ($meta_result1 !== false ? 'SUCCESS' : 'FAILED'));
			
			error_log('TTS DEBUG: Calling update_post_meta for use edited flag');
			$meta_result2 = update_post_meta( $post_id, '_tts_use_edited_text', '1' );
			error_log('TTS DEBUG: update_post_meta(_tts_use_edited_text) result: ' . ($meta_result2 !== false ? 'SUCCESS' : 'FAILED'));
			
			// Also save timestamp for reference
			$timestamp_result = update_post_meta( $post_id, '_tts_edited_timestamp', current_time( 'mysql' ) );
			error_log('TTS DEBUG: update_post_meta(_tts_edited_timestamp) result: ' . ($timestamp_result !== false ? 'SUCCESS' : 'FAILED'));
			
			error_log('TTS DEBUG: Preparing success response');
			$response_data = [
				'message' => __( 'Texto guardado exitosamente.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'character_count' => strlen( $edited_text ),
				'word_count' => str_word_count( $edited_text )
			];
			error_log('TTS DEBUG: Response data prepared: ' . print_r($response_data, true));
			
			error_log('TTS DEBUG: Calling wp_send_json_success');
			wp_send_json_success( $response_data );
			error_log('TTS DEBUG: wp_send_json_success called successfully');

		} catch ( \Exception $e ) {
			error_log('TTS DEBUG: EXCEPTION caught: ' . $e->getMessage());
			error_log('TTS DEBUG: Exception trace: ' . $e->getTraceAsString());
			wp_send_json_error( [
				'message' => __( 'Error al guardar el texto editado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
			error_log('TTS DEBUG: Exception ERROR response sent');
		} catch ( \Throwable $t ) {
			error_log('TTS DEBUG: THROWABLE caught: ' . $t->getMessage());
			error_log('TTS DEBUG: Throwable trace: ' . $t->getTraceAsString());
			wp_send_json_error( [
				'message' => __( 'Error fatal al guardar el texto editado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $t->getMessage()
			] );
			error_log('TTS DEBUG: Throwable ERROR response sent');
		}
	}
	
	/**
	 * Handle TTS generation from edited text
	 */
	public function handleGenerateFromEdited(): void {
		error_log('TTS DEBUG: handleGenerateFromEdited called');
		error_log('TTS DEBUG: POST data: ' . print_r($_POST, true));
		
		if ( ! isset($_POST['nonce']) ) {
			error_log('TTS DEBUG: No nonce provided in POST data');
			wp_send_json_error( [
				'message' => __( 'Nonce no proporcionado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		$nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
		error_log('TTS DEBUG: Nonce received: ' . $nonce);
		
		// Test direct nonce verification
		$direct_verify = wp_verify_nonce( $nonce, 'wp_tts_admin' );
		error_log('TTS DEBUG: Direct wp_verify_nonce result: ' . ($direct_verify ? 'SUCCESS' : 'FAILED'));
		
		if ( ! $direct_verify ) {
			error_log('TTS DEBUG: Direct nonce verification failed');
			wp_send_json_error( [
				'message' => __( 'Verificación de seguridad fallida.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}
		
		// Test direct capability check
		$can_edit = current_user_can( 'edit_posts' );
		error_log('TTS DEBUG: Direct current_user_can(edit_posts) result: ' . ($can_edit ? 'SUCCESS' : 'FAILED'));
		
		if ( ! $can_edit ) {
			error_log('TTS DEBUG: User does not have edit_posts capability');
			wp_send_json_error( [
				'message' => __( 'Permisos insuficientes.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			], 403 );
			return;
		}

		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
		$voice = isset($_POST['voice']) ? sanitize_text_field(wp_unslash($_POST['voice'])) : '';
		
		error_log('TTS DEBUG: Post ID: ' . $post_id);
		error_log('TTS DEBUG: Provider: ' . $provider);
		error_log('TTS DEBUG: Voice: ' . $voice);
		
		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => __( 'ID de post requerido.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
			] );
			return;
		}

		try {
			error_log('TTS DEBUG: Starting generateFromEdited process');
			
			// Get the edited text (it should have been saved previously)
			$edited_text = '';
			
			error_log('TTS DEBUG: Step 1 - Retrieving edited text for post_id=' . $post_id);
			error_log('TTS DEBUG: Using basic meta system to retrieve edited text');
			$edited_text = get_post_meta( $post_id, '_tts_edited_text', true );
			error_log('TTS DEBUG: get_post_meta(_tts_edited_text) result: ' . strlen($edited_text) . ' characters');
			
			$use_edited = get_post_meta( $post_id, '_tts_use_edited_text', true );
			error_log('TTS DEBUG: get_post_meta(_tts_use_edited_text) result: ' . ($use_edited ? 'TRUE' : 'FALSE'));
			
			$timestamp = get_post_meta( $post_id, '_tts_edited_timestamp', true );
			error_log('TTS DEBUG: get_post_meta(_tts_edited_timestamp) result: ' . $timestamp);
			
			error_log('TTS DEBUG: Retrieved edited text length: ' . strlen($edited_text));
			
			if ( empty( $edited_text ) ) {
				error_log('TTS DEBUG: No edited text found, sending error response');
				wp_send_json_error( [
					'message' => __( 'No se encontró texto editado. Por favor guarde el texto primero.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
				return;
			}
			
			// Prepare options for TTS generation
			error_log('TTS DEBUG: Step 2 - Preparing TTS options');
			$options = [
				'provider' => $provider,
				'voice' => $voice,
				'post_id' => $post_id,
				'from_editor' => true
			];
			error_log('TTS DEBUG: TTS options: ' . print_r($options, true));
			
			// Generate audio using the TTS service
			error_log('TTS DEBUG: Step 3 - Calling TTS service generateAudio');
			$result = $this->tts_service->generateAudio( $edited_text, $options );
			error_log('TTS DEBUG: TTS service result: ' . print_r($result, true));
			
			if ( $result && $result['success'] ) {
				error_log('TTS DEBUG: TTS generation successful, sending success response');
				wp_send_json_success( [
					'audio_url' => $result['audio_url'],
					'provider' => $result['provider'] ?? $provider,
					'voice' => $result['voice'] ?? $voice,
					'message' => __( 'Audio generado exitosamente desde texto editado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
					'character_count' => strlen( $edited_text )
				] );
				error_log('TTS DEBUG: Success response sent for generation');
			} else {
				error_log('TTS DEBUG: TTS generation failed, sending error response');
				wp_send_json_error( [
					'message' => $result['message'] ?? __( 'Falló la generación de audio.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' )
				] );
				error_log('TTS DEBUG: Error response sent for generation');
			}

		} catch ( \Exception $e ) {
			error_log('TTS DEBUG: EXCEPTION in generateFromEdited: ' . $e->getMessage());
			error_log('TTS DEBUG: Exception trace: ' . $e->getTraceAsString());
			wp_send_json_error( [
				'message' => __( 'Error al generar audio desde texto editado.', 'TTS-SesoLibre-v1.6.7-shortcode-docs' ),
				'error' => $e->getMessage()
			] );
			error_log('TTS DEBUG: Exception ERROR response sent for generation');
		}
	}

	/**
	 * Render player color field
	 */
	private function renderPlayerColorField( array $config, string $field_name, string $default_value ): void {
		$value = $config['player'][$field_name] ?? $default_value;
		
		echo '<div class="tts-color-picker-container">';
		echo '<input type="color" name="wp_tts_config[player][' . esc_attr($field_name) . ']" ';
		echo 'value="' . esc_attr($value) . '" ';
		echo 'class="tts-player-color-setting" data-setting="' . esc_attr($field_name) . '" />';
		echo '<input type="text" name="wp_tts_config[player][' . esc_attr($field_name) . '_text]" ';
		echo 'value="' . esc_attr($value) . '" ';
		echo 'class="tts-color-text-input" style="margin-left: 10px; width: 100px; font-family: monospace;" ';
		echo 'placeholder="' . esc_attr($default_value) . '" />';
		echo '</div>';
		
		echo '<script>
		document.addEventListener("DOMContentLoaded", function() {
			const colorInput = document.querySelector(\'input[name="wp_tts_config[player][' . esc_js($field_name) . ']"]\');
			const textInput = document.querySelector(\'input[name="wp_tts_config[player][' . esc_js($field_name) . '_text]"]\');
			
			if (colorInput && textInput) {
				colorInput.addEventListener("input", function() {
					textInput.value = this.value;
				});
				
				textInput.addEventListener("input", function() {
					if (/^#[0-9A-F]{6}$/i.test(this.value)) {
						colorInput.value = this.value;
					}
				});
			}
		});
		</script>';
	}

	/**
	 * Render player custom CSS field
	 */
	private function renderPlayerCustomCSSField( array $config ): void {
		$custom_css = $config['player']['enhanced_player_css'] ?? '';
		
		echo '<textarea name="wp_tts_config[player][enhanced_player_css]" ';
		echo 'rows="10" cols="50" ';
		echo 'class="tts-custom-css-textarea" ';
		echo 'style="width: 100%; font-family: monospace;" ';
		echo 'placeholder="/* Ejemplo de CSS personalizado:\n.tts-enhanced-sesolibre-player {\n    border-radius: 20px;\n}\n\n.tts-enhanced-sesolibre-player .tts-play-pause {\n    background: linear-gradient(45deg, #007cba, #17a2b8);\n}\n\n.tts-enhanced-sesolibre-player .tts-article-title-above {\n    color: #2c3e50;\n    font-weight: bold;\n}\n\n/* Personalizar barra de información */\n.tts-enhanced-sesolibre-player .tts-compact-info-bar {\n    background: #f0f8ff;\n    border-top: 2px solid #007cba;\n}\n\n.tts-enhanced-sesolibre-player .tts-info-compact {\n    color: #1e3a8a;\n    font-weight: 600;\n}\n*/">';
		echo esc_textarea($custom_css);
		echo '</textarea>';
		
		echo '<div style="margin-top: 10px; padding: 10px; background: #e7f3ff; border: 1px solid #b8daff; border-radius: 4px;">';
		echo '<strong>' . esc_html__('Selectores CSS útiles:', 'TTS-SesoLibre-v1.6.7-shortcode-docs') . '</strong>';
		echo '<ul style="margin: 5px 0 0 20px; font-size: 12px;">';
		echo '<li><code>.tts-enhanced-sesolibre-player</code> - Contenedor principal</li>';
		echo '<li><code>.tts-play-pause</code> - Botón de play/pausa</li>';
		echo '<li><code>.tts-progress-bar</code> - Barra de progreso</li>';
		echo '<li><code>.tts-featured-image-compact</code> - Imagen destacada</li>';
		echo '<li><code>.tts-article-title-above</code> - Título del artículo</li>';
		echo '<li><code>.tts-compact-info-bar</code> - Barra de información (fondo)</li>';
		echo '<li><code>.tts-info-compact</code> - Elementos de información (texto)</li>';
		echo '<li><code>.tts-info-separator</code> - Separadores entre info (•)</li>';
		echo '<li><code>.tts-download-compact</code> - Enlace de descarga</li>';
		echo '<li><code>.tts-volume-slider-compact</code> - Controles de volumen</li>';
		echo '<li><code>.tts-speed-btn</code> - Botón de velocidad</li>';
		echo '<li><code>.tts-headphones</code> - Icono de audífonos</li>';
		echo '</ul>';
		echo '<p style="margin-top: 10px; font-size: 11px; color: #666;"><strong>Variables CSS:</strong> --play-icon-color, --pause-icon-color, --progress-color, --background-color, --text-color</p>';
		echo '</div>';
	}
}
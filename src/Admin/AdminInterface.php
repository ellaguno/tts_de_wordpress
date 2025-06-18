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
	}
	
	/**
	 * Add admin menu pages
	 */
	public function addAdminMenu(): void {
		add_options_page(
			__( 'TTS Settings', 'TTS de Wordpress' ),
			__( 'TTS Settings', 'TTS de Wordpress' ),
			'manage_options',
			'wp-tts-settings',
			[ $this, 'renderSettingsPage' ]
		);
		
		add_management_page(
			__( 'TTS Tools', 'TTS de Wordpress' ),
			__( 'TTS Tools', 'TTS de Wordpress' ),
			'manage_options',
			'wp-tts-tools',
			[ $this, 'renderToolsPage' ]
		);
	}
	
	/**
	 * Register settings
	 */
	public function registerSettings(): void {
		register_setting( 'wp_tts_settings', 'wp_tts_config' );
		
		// TTS Providers section
		add_settings_section(
			'wp_tts_providers',
			__( 'TTS Providers', 'TTS de Wordpress' ),
			[ $this, 'renderProvidersSection' ],
			'wp-tts-settings'
		);
		
		// Provider fields
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'TTS de Wordpress' ),
			[ $this, 'renderOpenAIKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'elevenlabs_api_key',
			__( 'ElevenLabs API Key', 'TTS de Wordpress' ),
			[ $this, 'renderElevenLabsKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'google_credentials',
			__( 'Google Cloud Credentials', 'TTS de Wordpress' ),
			[ $this, 'renderGoogleCredentialsField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Google default voice field
		add_settings_field(
			'google_default_voice',
			__( 'Default Google Voice', 'TTS de Wordpress' ),
			[ $this, 'renderGoogleDefaultVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// OpenAI default voice field
		add_settings_field(
			'openai_default_voice',
			__( 'Default OpenAI Voice', 'TTS de Wordpress' ),
			[ $this, 'renderOpenAIDefaultVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// ElevenLabs default voice field
		add_settings_field(
			'elevenlabs_default_voice',
			__( 'Default ElevenLabs Voice', 'TTS de Wordpress' ),
			[ $this, 'renderElevenLabsDefaultVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Amazon Polly fields
		add_settings_field(
			'amazon_polly_access_key',
			__( 'Amazon Polly Access Key', 'TTS de Wordpress' ),
			[ $this, 'renderAmazonPollyAccessKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'amazon_polly_secret_key',
			__( 'Amazon Polly Secret Key', 'TTS de Wordpress' ),
			[ $this, 'renderAmazonPollySecretKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'amazon_polly_region',
			__( 'Amazon Polly Region', 'TTS de Wordpress' ),
			[ $this, 'renderAmazonPollyRegionField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'amazon_polly_voice',
			__( 'Default Amazon Polly Voice', 'TTS de Wordpress' ),
			[ $this, 'renderAmazonPollyVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'default_provider',
			__( 'Default Provider', 'TTS de Wordpress' ),
			[ $this, 'renderDefaultProviderField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		// Storage section
		add_settings_section(
			'wp_tts_storage',
			__( 'Storage Settings', 'TTS de Wordpress' ),
			[ $this, 'renderStorageSection' ],
			'wp-tts-settings'
		);
		
		// Storage fields
		add_settings_field(
			'storage_provider',
			__( 'Storage Provider', 'TTS de Wordpress' ),
			[ $this, 'renderStorageProviderField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		add_settings_field(
			'cache_duration',
			__( 'Cache Duration (hours)', 'TTS de Wordpress' ),
			[ $this, 'renderCacheDurationField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		add_settings_field(
			'max_cache_size',
			__( 'Max Cache Size (MB)', 'TTS de Wordpress' ),
			[ $this, 'renderMaxCacheSizeField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		// Buzzsprout storage fields
		add_settings_field(
			'buzzsprout_api_token',
			__( 'Buzzsprout API Token', 'TTS de Wordpress' ),
			[ $this, 'renderBuzzsproutApiTokenField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		add_settings_field(
			'buzzsprout_podcast_id',
			__( 'Buzzsprout Podcast ID', 'TTS de Wordpress' ),
			[ $this, 'renderBuzzsproutPodcastIdField' ],
			'wp-tts-settings',
			'wp_tts_storage'
		);
		
		// Azure TTS fields
		add_settings_field(
			'azure_tts_subscription_key',
			__( 'Azure TTS Subscription Key', 'TTS de Wordpress' ),
			[ $this, 'renderAzureTTSSubscriptionKeyField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'azure_tts_region',
			__( 'Azure TTS Region', 'TTS de Wordpress' ),
			[ $this, 'renderAzureTTSRegionField' ],
			'wp-tts-settings',
			'wp_tts_providers'
		);
		
		add_settings_field(
			'azure_tts_default_voice',
			__( 'Default Azure TTS Voice', 'TTS de Wordpress' ),
			[ $this, 'renderAzureTTSVoiceField' ],
			'wp-tts-settings',
			'wp_tts_providers'
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
		
		wp_enqueue_style(
			'wp-tts-admin',
			WP_TTS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WP_TTS_PLUGIN_VERSION
		);
		
		wp_enqueue_script(
			'wp-tts-admin',
			WP_TTS_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WP_TTS_PLUGIN_VERSION,
			true
		);
		
		wp_localize_script( 'wp-tts-admin', 'wpTtsAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_tts_admin' ),
		] );
	}
	
	/**
	 * Render settings page
	 */
	public function renderSettingsPage(): void {
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'TTS SesoLibre' ) );
		}
		
		$active_tab = $_GET['tab'] ?? 'defaults';
		$config = get_option( 'wp_tts_config', [] );
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'TTS SesoLibre Settings', 'TTS SesoLibre' ) . '</h1>';
		
		// Render tabs navigation
		$this->renderTabsNavigation( $active_tab );
		
		echo '<form method="post" action="options.php">';
		settings_fields( 'wp_tts_settings' );
		
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
			'defaults' => __( 'Defaults', 'TTS SesoLibre' ),
			'providers' => __( 'TTS Providers', 'TTS SesoLibre' ),
			'storage' => __( 'Storage', 'TTS SesoLibre' )
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
		echo '<h2>' . esc_html__( 'Default Settings', 'TTS SesoLibre' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure the default TTS provider and general settings.', 'TTS SesoLibre' ) . '</p>';
		
		echo '<table class="form-table">';
		
		// Default Provider
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default TTS Provider', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderDefaultProviderField();
		echo '<p class="description">' . esc_html__( 'Select the default TTS provider to use when no specific provider is chosen for a post.', 'TTS SesoLibre' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		// Cache Settings
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Cache Duration', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderCacheDurationField();
		echo '<p class="description">' . esc_html__( 'How long to keep generated audio files cached (in hours).', 'TTS SesoLibre' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Max Cache Size', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderMaxCacheSizeField();
		echo '<p class="description">' . esc_html__( 'Maximum cache size in megabytes.', 'TTS SesoLibre' ) . '</p>';
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
		echo '<h2>' . esc_html__( 'TTS Providers Configuration', 'TTS SesoLibre' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure your TTS providers with API keys and default voices.', 'TTS SesoLibre' ) . '</p>';
		
		// Provider cards container
		echo '<div class="tts-providers-grid">';
		
		$this->renderProviderCard( 'google', __( 'Google Cloud TTS', 'TTS SesoLibre' ), $config );
		$this->renderProviderCard( 'openai', __( 'OpenAI TTS', 'TTS SesoLibre' ), $config );
		$this->renderProviderCard( 'elevenlabs', __( 'ElevenLabs', 'TTS SesoLibre' ), $config );
		$this->renderProviderCard( 'azure_tts', __( 'Microsoft Azure TTS', 'TTS SesoLibre' ), $config );
		$this->renderProviderCard( 'amazon_polly', __( 'Amazon Polly', 'TTS SesoLibre' ), $config );
		
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Storage tab
	 */
	private function renderStorageTab( array $config ): void {
		echo '<div class="tts-tab-content">';
		echo '<h2>' . esc_html__( 'Storage Configuration', 'TTS SesoLibre' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure where and how audio files are stored.', 'TTS SesoLibre' ) . '</p>';
		
		echo '<div class="tts-storage-section">';
		echo '<h3>' . esc_html__( 'Storage Provider', 'TTS SesoLibre' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Storage Provider', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderStorageProviderField();
		echo '<p class="description">' . esc_html__( 'Choose where to store generated audio files.', 'TTS SesoLibre' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '</table>';
		echo '</div>';
		
		// Buzzsprout configuration
		echo '<div class="tts-storage-provider-config" id="buzzsprout-config">';
		echo '<h3>' . esc_html__( 'Buzzsprout Configuration', 'TTS SesoLibre' ) . '</h3>';
		echo '<table class="form-table">';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'API Token', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutApiTokenField();
		echo '<p class="description">' . esc_html__( 'Your Buzzsprout API token for uploading audio files.', 'TTS SesoLibre' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Podcast ID', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderBuzzsproutPodcastIdField();
		echo '<p class="description">' . esc_html__( 'Your Buzzsprout podcast ID.', 'TTS SesoLibre' ) . '</p>';
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
		
		@media (max-width: 768px) {
			.tts-providers-grid {
				grid-template-columns: 1fr;
			}
		}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Show/hide storage provider configs based on selection
			function toggleStorageConfig() {
				var provider = $('select[name="wp_tts_config[storage][provider]"]').val();
				$('.tts-storage-provider-config').hide();
				if (provider === 'buzzsprout') {
					$('#buzzsprout-config').show();
				}
			}
			
			$('select[name="wp_tts_config[storage][provider]"]').on('change', toggleStorageConfig);
			toggleStorageConfig(); // Initial state
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
		echo '<th scope="row">' . esc_html__( 'Credentials Path', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderGoogleCredentialsField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default Voice', 'TTS SesoLibre' ) . '</th>';
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
		echo '<th scope="row">' . esc_html__( 'API Key', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderOpenAIKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default Voice', 'TTS SesoLibre' ) . '</th>';
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
		echo '<th scope="row">' . esc_html__( 'API Key', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderElevenLabsKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default Voice', 'TTS SesoLibre' ) . '</th>';
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
		echo '<th scope="row">' . esc_html__( 'Subscription Key', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderAzureTTSSubscriptionKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Region', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderAzureTTSRegionField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default Voice', 'TTS SesoLibre' ) . '</th>';
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
		echo '<th scope="row">' . esc_html__( 'Access Key', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollyAccessKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Secret Key', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollySecretKeyField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Region', 'TTS SesoLibre' ) . '</th>';
		echo '<td>';
		$this->renderAmazonPollyRegionField();
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default Voice', 'TTS SesoLibre' ) . '</th>';
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
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'TTS de Wordpress' ) );
		}
		
		$stats = $this->tts_service->getStats();
		$config = get_option( 'wp_tts_config', [] );
		
		// Only show providers that are actually configured, plus some for testing
		$all_providers = ['google', 'openai', 'elevenlabs', 'azure_tts', 'amazon_polly'];
		$enabled_providers = [];
		
		foreach ($all_providers as $provider) {
			$is_valid = $this->tts_service->validateProvider($provider);
			error_log("[TTS Tools] Provider validation - $provider: " . ($is_valid ? 'VALID' : 'INVALID'));
			
			// Show configured providers, or some specific ones for testing even if not configured
			if ($is_valid || in_array($provider, ['google', 'openai', 'elevenlabs', 'azure_tts'])) {
				$enabled_providers[] = $provider;
			}
		}
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'TTS Tools', 'TTS de Wordpress' ) . '</h1>';
		
		// Voice Preview Tool
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Voice Preview', 'TTS de Wordpress' ) . '</h2>';
		echo '<p>' . esc_html__( 'Test different voices and providers before using them in your posts.', 'TTS de Wordpress' ) . '</p>';
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="preview_provider">' . esc_html__( 'Provider', 'TTS de Wordpress' ) . '</label></th>';
		echo '<td>';
		echo '<select id="preview_provider" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Select a provider', 'TTS de Wordpress' ) . '</option>';
		foreach ($enabled_providers as $provider) {
			echo '<option value="' . esc_attr($provider) . '">' . esc_html(ucfirst($provider)) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="preview_voice">' . esc_html__( 'Voice', 'TTS de Wordpress' ) . '</label></th>';
		echo '<td>';
		echo '<select id="preview_voice" class="regular-text" disabled>';
		echo '<option value="">' . esc_html__( 'Select a provider first', 'TTS de Wordpress' ) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="preview_text">' . esc_html__( 'Sample Text', 'TTS de Wordpress' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="preview_text" rows="3" class="large-text" placeholder="' . esc_attr__( 'Enter text to preview...', 'TTS de Wordpress' ) . '">' . esc_textarea('Hola, esta es una muestra de voz para probar el sistema de texto a voz.') . '</textarea>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"></th>';
		echo '<td>';
		echo '<button type="button" class="button button-primary" id="generate_preview" disabled>';
		echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html__( 'Generate Preview', 'TTS de Wordpress' );
		echo '</button>';
		echo '<div id="preview_result" style="margin-top: 15px; display: none;">';
		echo '<audio controls style="width: 100%;">';
		echo '<source id="preview_audio_source" src="" type="audio/mpeg">';
		echo esc_html__( 'Your browser does not support the audio element.', 'TTS de Wordpress' );
		echo '</audio>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
		echo '</div>';
		
		// Custom Text Generator
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Custom Text Generator', 'TTS de Wordpress' ) . '</h2>';
		echo '<p>' . esc_html__( 'Generate audio from custom text with detailed configuration options.', 'TTS de Wordpress' ) . '</p>';
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="custom_provider">' . esc_html__( 'Provider', 'TTS de Wordpress' ) . '</label></th>';
		echo '<td>';
		echo '<select id="custom_provider" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Use default provider', 'TTS de Wordpress' ) . '</option>';
		foreach ($enabled_providers as $provider) {
			echo '<option value="' . esc_attr($provider) . '">' . esc_html(ucfirst($provider)) . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="custom_voice">' . esc_html__( 'Voice', 'TTS de Wordpress' ) . '</label></th>';
		echo '<td>';
		echo '<select id="custom_voice" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Use default voice', 'TTS de Wordpress' ) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="custom_text">' . esc_html__( 'Custom Text', 'TTS de Wordpress' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="custom_text" rows="8" class="large-text" placeholder="' . esc_attr__( 'Enter your custom text here...', 'TTS de Wordpress' ) . '"></textarea>';
		echo '<div class="wp-tts-text-stats" style="margin-top: 5px; font-size: 12px; color: #666;">';
		echo '<span id="custom_character_count">0</span> ' . esc_html__( 'characters', 'TTS de Wordpress' );
		echo '<span style="margin: 0 10px;">|</span>';
		echo '<span id="custom_estimated_cost">$0.00</span> ' . esc_html__( 'estimated cost', 'TTS de Wordpress' );
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"></th>';
		echo '<td>';
		echo '<button type="button" class="button button-primary" id="generate_custom">';
		echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html__( 'Generate Audio', 'TTS de Wordpress' );
		echo '</button>';
		echo '<div id="custom_generation_progress" style="display: none; margin-top: 15px;">';
		echo '<div style="width: 100%; height: 20px; background-color: #f0f0f0; border-radius: 10px; overflow: hidden;">';
		echo '<div id="custom_progress_fill" style="height: 100%; background-color: #0073aa; width: 0%; transition: width 0.3s ease;"></div>';
		echo '</div>';
		echo '<p style="margin: 5px 0 0 0; font-size: 12px; color: #666;" id="custom_progress_text">' . esc_html__( 'Preparing...', 'TTS de Wordpress' ) . '</p>';
		echo '</div>';
		echo '<div id="custom_result" style="margin-top: 15px; display: none;">';
		echo '<audio controls style="width: 100%;">';
		echo '<source id="custom_audio_source" src="" type="audio/mpeg">';
		echo esc_html__( 'Your browser does not support the audio element.', 'TTS de Wordpress' );
		echo '</audio>';
		echo '<p style="margin-top: 10px;">';
		echo '<a id="custom_download_link" href="#" download class="button button-secondary">' . esc_html__( 'Download Audio', 'TTS de Wordpress' ) . '</a>';
		echo '</p>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
		echo '</div>';
		
		// Service Statistics
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Service Statistics', 'TTS de Wordpress' ) . '</h2>';
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
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Select a provider first', 'TTS de Wordpress' ) . '</option>\').prop(\'disabled\', true);';
		echo '$(\'#generate_preview\').prop(\'disabled\', true);';
		echo 'return;';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Loading voices...', 'TTS de Wordpress' ) . '</option>\');';
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
		echo 'let options = \'<option value="">' . esc_js__( 'Use default voice', 'TTS de Wordpress' ) . '</option>\';';
		echo 'if (response.data.voices.length > 0) {';
		echo 'response.data.voices.forEach(function(voice) {';
		echo 'console.log("[TTS Preview] Adding voice:", voice);';
		echo 'options += `<option value="${voice.id}">${voice.name}${voice.language ? \' (\' + voice.language + \')\' : \'\'}</option>`;';
		echo '});';
		echo '} else {';
		echo 'console.log("[TTS Preview] No voices found in response");';
		echo 'options += \'<option value="">' . esc_js__( 'No voices available', 'TTS de Wordpress' ) . '</option>\';';
		echo '}';
		echo '$voiceSelect.html(options).prop(\'disabled\', false);';
		echo '$(\'#generate_preview\').prop(\'disabled\', false);';
		echo '} else {';
		echo 'console.error("[TTS Preview] AJAX Error - Invalid response structure:", response);';
		echo 'if (response.data && response.data.message) {';
		echo 'console.error("[TTS Preview] Error message:", response.data.message);';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error loading voices', 'TTS de Wordpress' ) . '</option>\');';
		echo '$(\'#generate_preview\').prop(\'disabled\', true);';
		echo '}';
		echo '},';
		echo 'error: function(xhr, status, error) {';
		echo 'console.error("[TTS Preview] AJAX Call Failed:", {xhr: xhr, status: status, error: error});';
		echo 'console.error("[TTS Preview] Response text:", xhr.responseText);';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Connection error', 'TTS de Wordpress' ) . '</option>\');';
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
		echo 'alert(\''. esc_js__( 'Please enter some text to preview', 'TTS de Wordpress' ) .'\');';
		echo 'return;';
		echo '}';
		echo 'const $button = $(this);';
		echo 'const originalText = $button.html();';
		echo '$button.prop(\'disabled\', true).html(\'<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> '. esc_js__( 'Generating...', 'TTS de Wordpress' ) .'\');';
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
		echo 'alert(response.data.message || \''. esc_js__( 'Preview failed', 'TTS de Wordpress' ) .'\');';
		echo '}';
		echo '},';
		echo 'error: function() { alert(\''. esc_js__( 'Preview failed', 'TTS de Wordpress' ) .'\'); },';
		echo 'complete: function() { $button.prop(\'disabled\', false).html(originalText); }';
		echo '});';
		echo '});';
		
		// Custom text functionality
		echo '$(\'#custom_provider\').on(\'change\', function() {';
		echo 'const provider = $(this).val();';
		echo 'const $voiceSelect = $(\'#custom_voice\');';
		echo 'if (!provider) {';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Use default voice', 'TTS de Wordpress' ) . '</option>\');';
		echo 'return;';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Loading voices...', 'TTS de Wordpress' ) . '</option>\');';
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
		echo 'let options = \'<option value="">' . esc_js__( 'Use default voice', 'TTS de Wordpress' ) . '</option>\';';
		echo 'if (response.data.voices.length > 0) {';
		echo 'response.data.voices.forEach(function(voice) {';
		echo 'console.log("[TTS Custom] Adding voice:", voice);';
		echo 'options += `<option value="${voice.id}">${voice.name}${voice.language ? \' (\' + voice.language + \')\' : \'\'}</option>`;';
		echo '});';
		echo '} else {';
		echo 'console.log("[TTS Custom] No voices found in response");';
		echo 'options += \'<option value="">' . esc_js__( 'No voices available', 'TTS de Wordpress' ) . '</option>\';';
		echo '}';
		echo '$voiceSelect.html(options).prop(\'disabled\', false);';
		echo '} else {';
		echo 'console.error("[TTS Custom] AJAX Error - Invalid response structure:", response);';
		echo 'if (response.data && response.data.message) {';
		echo 'console.error("[TTS Custom] Error message:", response.data.message);';
		echo '}';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Error loading voices', 'TTS de Wordpress' ) . '</option>\');';
		echo '}';
		echo '},';
		echo 'error: function(xhr, status, error) {';
		echo 'console.error("[TTS Custom] AJAX Call Failed:", {xhr: xhr, status: status, error: error});';
		echo 'console.error("[TTS Custom] Response text:", xhr.responseText);';
		echo '$voiceSelect.html(\'<option value="">' . esc_js__( 'Connection error', 'TTS de Wordpress' ) . '</option>\');';
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
		echo 'alert(\''. esc_js__( 'Please enter some text to generate', 'TTS de Wordpress' ) .'\');';
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
		echo 'const errorMsg = response.data ? response.data.message : \''. esc_js__( 'Generation failed', 'TTS de Wordpress' ) .'\';';
		echo 'alert(errorMsg);';
		echo '$(\'#custom_generation_progress\').hide();';
		echo '}';
		echo '},';
		echo 'error: function(xhr, status, error) {';
		echo 'console.error("[TTS Custom Generate] AJAX Error:", {xhr: xhr, status: status, error: error});';
		echo 'console.error("[TTS Custom Generate] Response text:", xhr.responseText);';
		echo 'clearInterval(progressInterval);';
		echo 'alert(\''. esc_js__( 'Generation failed', 'TTS de Wordpress' ) .'\');';
		echo '$(\'#custom_generation_progress\').hide();';
		echo '},';
		echo 'complete: function() { $button.prop(\'disabled\', false).html(originalText); }';
		echo '});';
		echo '});';
		
		echo '});';
		echo '</script>';
		
		// Add CSS
		echo '<style>';
		echo '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
		echo '.wp-tts-text-stats { margin-top: 5px; font-size: 12px; color: #666; }';
		echo '</style>';
	}
	
	/**
	 * Render providers section
	 */
	public function renderProvidersSection(): void {
		echo '<p>' . esc_html__( 'Configure your TTS providers below.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render storage section
	 */
	public function renderStorageSection(): void {
		echo '<p>' . esc_html__( 'Configure audio storage settings.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render OpenAI API Key field
	 */
	public function renderOpenAIKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['openai']['api_key'] ?? '';
		echo '<input type="password" name="wp_tts_config[providers][openai][api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your OpenAI API key for TTS services.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render ElevenLabs API Key field
	 */
	public function renderElevenLabsKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['elevenlabs']['api_key'] ?? '';
		echo '<input type="password" name="wp_tts_config[providers][elevenlabs][api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your ElevenLabs API key for TTS services.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Google Credentials field
	 */
	public function renderGoogleCredentialsField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['google']['credentials_path'] ?? '';
		echo '<input type="text" name="wp_tts_config[providers][google][credentials_path]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Path to Google Cloud service account JSON file.', 'TTS de Wordpress' ) . '</p>';
	}
/**
	 * Render Amazon Polly Access Key field
	 */
	public function renderAmazonPollyAccessKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['amazon_polly']['access_key'] ?? '';
		echo '<input type="password" name="wp_tts_config[providers][amazon_polly][access_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your AWS Access Key ID for Amazon Polly.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Amazon Polly Secret Key field
	 */
	public function renderAmazonPollySecretKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['amazon_polly']['secret_key'] ?? '';
		echo '<input type="password" name="wp_tts_config[providers][amazon_polly][secret_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your AWS Secret Access Key for Amazon Polly.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Amazon Polly Region field
	 */
	public function renderAmazonPollyRegionField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['amazon_polly']['region'] ?? 'us-east-1';
		$regions = [
			'us-east-1' => 'US East (N. Virginia)',
			'us-west-2' => 'US West (Oregon)',
			'eu-west-1' => 'Europe (Ireland)',
			'ap-southeast-2' => 'Asia Pacific (Sydney)',
			'ap-northeast-1' => 'Asia Pacific (Tokyo)',
		];
		
		echo '<select name="wp_tts_config[providers][amazon_polly][region]">';
		foreach ( $regions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the AWS region for Amazon Polly.', 'TTS de Wordpress' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Select the default voice for Amazon Polly.', 'TTS de Wordpress' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Select the default TTS provider to use.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Storage Provider field
	 */
	public function renderStorageProviderField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['storage']['provider'] ?? 'local';
		$providers = [
			'local' => 'Local Storage',
			's3' => 'Amazon S3',
			'gcs' => 'Google Cloud Storage',
			'buzzsprout' => 'Buzzsprout'
		];
		
		echo '<select name="wp_tts_config[storage][provider]">';
		foreach ( $providers as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select where to store generated audio files.', 'TTS de Wordpress' ) . '</p>';
	}
/**
	 * Render Buzzsprout API Token field
	 */
	public function renderBuzzsproutApiTokenField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['storage']['buzzsprout']['api_token'] ?? '';
		echo '<input type="password" name="wp_tts_config[storage][buzzsprout][api_token]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your Buzzsprout API token for audio storage.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Buzzsprout Podcast ID field
	 */
	public function renderBuzzsproutPodcastIdField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['storage']['buzzsprout']['podcast_id'] ?? '';
		echo '<input type="text" name="wp_tts_config[storage][buzzsprout][podcast_id]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your Buzzsprout Podcast ID where audio files will be stored.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Azure TTS Subscription Key field
	 */
	public function renderAzureTTSSubscriptionKeyField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['providers']['azure_tts']['subscription_key'] ?? '';
		echo '<input type="password" name="wp_tts_config[providers][azure_tts][subscription_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your Azure Cognitive Services subscription key for TTS.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Azure TTS Region field
	 */
	public function renderAzureTTSRegionField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$current = $config['providers']['azure_tts']['region'] ?? 'eastus';
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
		
		echo '<select name="wp_tts_config[providers][azure_tts][region]">';
		foreach ( $regions as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the Azure region for TTS services.', 'TTS de Wordpress' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Select the default voice for Azure TTS.', 'TTS de Wordpress' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Select the default voice for Google Cloud TTS.', 'TTS de Wordpress' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Select the default voice for OpenAI TTS.', 'TTS de Wordpress' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'Select the default voice for ElevenLabs TTS.', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Cache Duration field
	 */
	public function renderCacheDurationField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['cache']['duration'] ?? 24;
		echo '<input type="number" name="wp_tts_config[cache][duration]" value="' . esc_attr( $value ) . '" min="1" max="8760" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'How long to cache audio files (1-8760 hours).', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Render Max Cache Size field
	 */
	public function renderMaxCacheSizeField(): void {
		$config = get_option( 'wp_tts_config', [] );
		$value = $config['cache']['max_size'] ?? 100;
		echo '<input type="number" name="wp_tts_config[cache][max_size]" value="' . esc_attr( $value ) . '" min="10" max="10000" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Maximum cache size in megabytes (10-10000 MB).', 'TTS de Wordpress' ) . '</p>';
	}
	
	/**
	 * Handle AJAX request from TTS Tools page to generate test audio.
	 * This is different from validating a provider's connection.
	 */
	public function handleTestProvider(): void {
		// Check nonce (wpTtsAdmin.nonce is 'wp_tts_admin')
		if ( ! isset($_POST['nonce']) || ! $this->security->verifyNonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wp_tts_admin' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed (nonce).', 'TTS de Wordpress' ),
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
				'message' => __( 'Insufficient permissions.', 'TTS de Wordpress' )
			], 403 );
			return;
		}
		
		$text_to_generate = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		
		if ( empty( $text_to_generate ) ) {
			wp_send_json_error( [
				'message' => __( 'No text provided for TTS generation.', 'TTS de Wordpress' ),
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
					'message'   => __( 'Test audio generated successfully.', 'TTS de Wordpress' ),
					'audio_url' => $result['audio_url'],
					'provider'  => $result['provider'] ?? 'N/A',
				] );
			} else {
				$error_message = isset($result['message']) && !empty($result['message']) ? $result['message'] : __( 'Failed to generate test audio. TTSService did not return success or audio URL.', 'TTS de Wordpress' );
				$this->config->getLogger()->error('[AdminInterface::handleTestProvider] TTS generation failed or returned invalid result.', ['result_from_service' => $result]);
				
				$error_details = '';
				if ( isset($result['error_code']) && $result['error_code'] === 'NO_PROVIDERS_CONFIGURED' ) {
					$error_details = __( 'No TTS providers are configured. Please go to Settings > TTS Settings to configure at least one provider (OpenAI, Google Cloud, ElevenLabs, Amazon Polly, or Azure TTS).', 'TTS de Wordpress' );
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
				'message' => __( 'An exception occurred during test audio generation.', 'TTS de Wordpress' ),
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
				'message' => __( 'Security check failed.', 'TTS de Wordpress' )
			], 403 );
			return;
		}
		
		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'TTS de Wordpress' )
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
				'message' => __( 'Security check failed.', 'TTS de Wordpress' )
			], 403 );
			return;
		}
		
		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'TTS de Wordpress' )
			], 403 );
			return;
		}

		$provider = isset($_POST['provider']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['provider'])) ) : '';
		$voice = isset($_POST['voice']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['voice'])) ) : '';
		$text = isset($_POST['text']) ? $this->security->sanitizeTextForTTS( sanitize_textarea_field(wp_unslash($_POST['text'])) ) : '';

		if ( empty( $text ) ) {
			$text = __( 'This is a voice preview sample.', 'TTS de Wordpress' );
		}

		try {
			$result = $this->tts_service->generatePreview( $text, $provider, $voice );

			if ( $result && isset($result->url) && ! empty($result->url) ) {
				wp_send_json_success( [
					'audio_url' => $result->url,
					'provider' => $provider,
					'duration' => $result->duration ?? 0,
					'message' => __( 'Preview generated successfully', 'TTS de Wordpress' ),
				] );
			} else {
				wp_send_json_error( [
					'message' => __( 'Preview generation failed - No audio URL returned', 'TTS de Wordpress' ),
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Preview generation failed', 'TTS de Wordpress' ),
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
				'message' => __( 'Security check failed.', 'TTS de Wordpress' )
			], 403 );
			return;
		}
		
		// Check permissions
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'Insufficient permissions.', 'TTS de Wordpress' )
			], 403 );
			return;
		}

		$provider = isset($_POST['provider']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['provider'])) ) : '';
		$voice = isset($_POST['voice']) ? $this->security->sanitizeText( sanitize_text_field(wp_unslash($_POST['voice'])) ) : '';
		$text = isset($_POST['text']) ? $this->security->sanitizeTextForTTS( sanitize_textarea_field(wp_unslash($_POST['text'])) ) : '';

		if ( empty( $text ) ) {
			wp_send_json_error( [
				'message' => __( 'Text is required', 'TTS de Wordpress' ),
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
					'message' => __( 'Audio generated successfully', 'TTS de Wordpress' ),
				] );
			} else {
				wp_send_json_error( [
					'message' => $result['message'] ?? __( 'Generation failed', 'TTS de Wordpress' ),
				] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Generation failed', 'TTS de Wordpress' ),
				'error' => $e->getMessage(),
			] );
		}
	}
}
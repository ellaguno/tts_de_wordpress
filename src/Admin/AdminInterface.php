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
		add_action( 'wp_ajax_wp_tts_get_voices', [ $this, 'handleGetVoices' ] );
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
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'TTS de Wordpress' ) );
		}
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'TTS Settings', 'TTS de Wordpress' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		
		settings_fields( 'wp_tts_settings' );
		do_settings_sections( 'wp-tts-settings' );
		submit_button();
		
		echo '</form>';
		echo '</div>';
	}
	
	/**
	 * Render tools page
	 */
	public function renderToolsPage(): void {
		if ( ! $this->security->canUser( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'TTS de Wordpress' ) );
		}
		
		$stats = $this->tts_service->getStats();
		
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'TTS Tools', 'TTS de Wordpress' ) . '</h1>';
		
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Service Statistics', 'TTS de Wordpress' ) . '</h2>';
		echo '<pre>' . esc_html( wp_json_encode( $stats, JSON_PRETTY_PRINT ) ) . '</pre>';
		echo '</div>';
		
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Test TTS Generation', 'TTS de Wordpress' ) . '</h2>';
		echo '<p><textarea id="test-text" rows="4" cols="50" placeholder="' . esc_attr__( 'Enter text to test...', 'TTS de Wordpress' ) . '"></textarea></p>';
		echo '<p><button type="button" class="button button-primary" id="test-tts">' . esc_html__( 'Generate Test Audio', 'TTS de Wordpress' ) . '</button></p>';
		echo '<div id="test-result"></div>';
		echo '</div>';
		
		echo '</div>';
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
			'es-MX-Wavenet-C' => 'Wavenet C (Mexican Spanish Female)',
			'es-MX-Wavenet-D' => 'Wavenet D (Mexican Spanish Male)',
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
		$current = $config['providers']['elevenlabs']['default_voice'] ?? 'Rachel';
		$voices = [
			'Rachel' => 'Rachel (Female, American)',
			'Domi' => 'Domi (Female, American)',
			'Bella' => 'Bella (Female, American)',
			'Antoni' => 'Antoni (Male, American)',
			'Elli' => 'Elli (Female, American)',
			'Josh' => 'Josh (Male, American)',
			'Arnold' => 'Arnold (Male, American)',
			'Adam' => 'Adam (Male, American)',
			'Sam' => 'Sam (Male, American)',
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
		$voices = $this->tts_service->getAvailableVoices( $provider );
		
		wp_send_json_success( [
			'provider' => $provider,
			'voices' => $voices,
		] );
	}
}
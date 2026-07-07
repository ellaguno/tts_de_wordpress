<?php

namespace WP_TTS\Core;

/**
 * Configuration Manager
 *
 * Manages plugin configuration, settings, and provider credentials
 * with secure storage and validation.
 *
 * SINGLE SOURCE OF TRUTH: everything is persisted inside the one
 * `wp_tts_config` option — the same option the Settings API form posts and
 * that TTSService/RoundRobinManager/meta-box read directly. The plugin
 * previously kept a parallel set of 8 wp_tts_* options that drifted from
 * wp_tts_config (phantom providers, stale defaults); those legacy options
 * are now read once as a migration source and never written again.
 */
class ConfigurationManager {

	/**
	 * The single option backing all configuration
	 */
	private const OPTION_MAIN = 'wp_tts_config';

	/**
	 * Legacy option names (pre-unification), kept only as migration sources
	 */
	private const LEGACY_OPTIONS = array(
		'providers'     => 'wp_tts_providers_config',
		'storage'       => 'wp_tts_storage_config',
		'defaults'      => 'wp_tts_default_settings',
		'round_robin'   => 'wp_tts_round_robin_state',
		'cache'         => 'wp_tts_cache_settings',
		'audio_library' => 'wp_tts_audio_library',
		'analytics'     => 'wp_tts_analytics_settings',
		'player'        => 'wp_tts_player_settings',
	);

	/**
	 * Map internal section name → key inside wp_tts_config.
	 * 'audio_library' lives under the 'audio_assets' key because that is what
	 * the meta box and admin templates already read.
	 */
	private const SECTION_KEYS = array(
		'providers'     => 'providers',
		'storage'       => 'storage',
		'defaults'      => 'defaults',
		'round_robin'   => 'round_robin',
		'cache'         => 'cache',
		'audio_library' => 'audio_assets',
		'analytics'     => 'analytics',
		'player'        => 'player',
	);

	/**
	 * Default configuration values
	 *
	 * @var array
	 */
	private $defaults = array(
		'providers'     => array(
			// Provider keys MUST match the runtime identifiers used across the
			// plugin (AdminInterface, TTSService, metabox AJAX): azure_tts,
			// amazon_polly, openai, google, elevenlabs. Using azure/polly here
			// produced a "phantom" provider with no voices on fresh installs.
			'openai'       => array(
				'enabled'       => false,
				'api_key'       => '',
				'default_voice' => 'nova',
				'quota_limit'   => 500000,
				'priority'      => 1,
			),
			'azure_tts'    => array(
				'enabled'       => false,
				'api_key'       => '',
				'region'        => 'eastus',
				'default_voice' => 'es-MX-DaliaNeural',
				'quota_limit'   => 500000, // characters per month
				'priority'      => 2,
			),
			'google'       => array(
				'enabled'          => false,
				'credentials_json' => '',
				'default_voice'    => 'es-US-Neural2-A',
				'quota_limit'      => 1000000,
				'priority'         => 3,
			),
			'amazon_polly' => array(
				'enabled'       => false,
				'access_key'    => '',
				'secret_key'    => '',
				'region'        => 'us-east-1',
				'default_voice' => 'Mia',
				'quota_limit'   => 5000000,
				'priority'      => 4,
			),
			'elevenlabs'   => array(
				'enabled'       => false,
				'api_key'       => '',
				'default_voice' => '',
				'quota_limit'   => 10000,
				'priority'      => 5,
			),
		),
		'storage'       => array(
			'buzzsprout' => array(
				'enabled'      => false,
				'api_token'    => '',
				'podcast_id'   => '',
				'auto_publish' => false,
				'make_private' => false,
				'default_tags' => 'tts,generated',
				'include_link' => true,
			),
			'spotify'    => array(
				'enabled'       => false,
				'client_id'     => '',
				'client_secret' => '',
				'show_id'       => '',
			),
			's3'         => array(
				'enabled'           => false,
				'access_key'        => '',
				'secret_key'        => '',
				'bucket'            => '',
				'region'            => 'us-east-1',
				'cloudfront_domain' => '',
			),
			'local'      => array(
				'enabled'       => true,
				'upload_path'   => 'wp-content/uploads/tts-audio/',
				'max_file_size' => 50, // MB
			),
		),
		'defaults'      => array(
			'default_provider'      => 'google',
			'default_storage'       => 'local',
			'auto_generate'         => false,
			'voice_speed'           => 1.0,
			'voice_pitch'           => 0,
			'audio_format'          => 'mp3',
			'audio_quality'         => 'high',
			'enable_ssml'           => true,
			'add_pauses'            => true,
			'background_processing' => true,
		),
		'cache'         => array(
			'cache_duration'    => 86400, // 24 hours
			'max_cache_entries' => 100,
			'cleanup_interval'  => 3600, // 1 hour
			'enable_cache'      => true,
		),
		'audio_library' => array(
			'intro_files'        => array(),
			'background_music'   => array(),
			'outro_files'        => array(),
			'default_intro'      => '',
			'default_background' => '',
			'default_outro'      => '',
		),
		'analytics'     => array(
			'track_usage'    => true,
			'track_costs'    => true,
			'retention_days' => 90,
			'export_enabled' => true,
		),
		'player'        => array(
			'style'                     => 'classic',
			'auto_insert'               => false,
			'position'                  => 'before_content',
			'show_voice_volume'         => true,
			'show_background_volume'    => true,
			'show_tts_service'          => true,
			'show_voice_name'           => true,
			'show_download_link'        => true,
			'show_article_title'        => true,
		),
	);

	/**
	 * Cached configuration
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->loadConfiguration();
	}

	/**
	 * Load configuration from the single wp_tts_config option.
	 *
	 * Per section: value inside wp_tts_config wins; if absent, fall back to
	 * the legacy standalone option (one-time migration source); else class
	 * defaults. Saved values are merged over defaults so new keys added in
	 * later versions appear automatically.
	 */
	private function loadConfiguration(): void {
		$data = get_option( self::OPTION_MAIN, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$section_defaults = array(
			'providers'     => $this->defaults['providers'],
			'storage'       => $this->defaults['storage'],
			'defaults'      => $this->defaults['defaults'],
			'round_robin'   => array(
				'current_provider' => $this->defaults['defaults']['default_provider'],
				'usage_count'      => array(),
				'last_reset'       => gmdate( 'Y-m-01' ), // First day of current month
				'failed_providers' => array(),
			),
			'cache'         => $this->defaults['cache'],
			'audio_library' => $this->defaults['audio_library'],
			'analytics'     => $this->defaults['analytics'],
			'player'        => $this->defaults['player'],
		);

		$this->config = array();
		foreach ( $section_defaults as $section => $defaults ) {
			$key = self::SECTION_KEYS[ $section ];

			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$saved = $data[ $key ];
			} else {
				// Migration source: the pre-unification standalone option.
				$legacy = get_option( self::LEGACY_OPTIONS[ $section ], null );
				$saved = is_array( $legacy ) ? $legacy : array();
			}

			$this->config[ $section ] = array_replace_recursive( $defaults, $saved );
		}

		// The top-level default_provider key is what TTSService and the meta
		// box read directly; it is canonical over defaults.default_provider.
		if ( ! empty( $data['default_provider'] ) && is_string( $data['default_provider'] ) ) {
			$this->config['defaults']['default_provider'] = $data['default_provider'];
		}
	}

	/**
	 * Get configuration value
	 *
	 * @param string $key Configuration key (dot notation supported)
	 * @param mixed  $default Default value if key not found
	 * @return mixed Configuration value
	 */
	public function get( string $key, $default = null ) {
		return $this->getNestedValue( $this->config, $key, $default );
	}

	/**
	 * Set configuration value
	 *
	 * @param string $key Configuration key (dot notation supported)
	 * @param mixed  $value Value to set
	 * @param bool   $save Whether to save to database immediately
	 */
	public function set( string $key, $value, bool $save = true ): void {
		$this->setNestedValue( $this->config, $key, $value );

		if ( $save ) {
			$this->save();
		}
	}

	/**
	 * Get provider configuration
	 *
	 * @param string $provider Provider name
	 * @return array Provider configuration
	 */
	public function getProviderConfig( string $provider ): array {
		$config = $this->get( "providers.{$provider}", array() );

		// Merge with defaults
		if ( isset( $this->defaults['providers'][ $provider ] ) ) {
			$config = array_merge( $this->defaults['providers'][ $provider ], $config );
		}

		return $config;
	}

	/**
	 * Set provider configuration
	 *
	 * @param string $provider Provider name
	 * @param array  $config Provider configuration
	 */
	public function setProviderConfig( string $provider, array $config ): void {
		$this->set( "providers.{$provider}", $config );
	}

	/**
	 * Get enabled providers
	 *
	 * @return array Enabled provider names
	 */
	public function getEnabledProviders(): array {
		$providers = $this->get( 'providers', array() );
		$enabled   = array();

		foreach ( $providers as $name => $config ) {
			if ( ! empty( $config['enabled'] ) ) {
				$enabled[] = $name;
			}
		}

		return $enabled;
	}

	/**
	 * Get storage configuration
	 *
	 * @param string $storage Storage provider name
	 * @return array Storage configuration
	 */
	public function getStorageConfig( string $storage ): array {
		$config = $this->get( "storage.{$storage}", array() );

		// Merge with defaults
		if ( isset( $this->defaults['storage'][ $storage ] ) ) {
			$config = array_merge( $this->defaults['storage'][ $storage ], $config );
		}

		return $config;
	}

	/**
	 * Get enabled storage providers
	 *
	 * @return array Enabled storage provider names
	 */
	public function getEnabledStorageProviders(): array {
		$storage = $this->get( 'storage', array() );
		
		// Ensure storage is an array
		if ( ! is_array( $storage ) ) {
			\WP_TTS\Utils\Logger::debugLog( "[ConfigurationManager] getEnabledStorageProviders() storage is not array: " . gettype( $storage ) );
			return array();
		}
		
		$enabled = array();

		foreach ( $storage as $name => $config ) {
			if ( is_array( $config ) && ! empty( $config['enabled'] ) ) {
				$enabled[] = $name;
			}
		}

		return $enabled;
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings
	 */
	public function getDefaults(): array {
		$defaults = $this->get( 'defaults', $this->defaults['defaults'] );
		
		// Ensure we always return an array
		if ( ! is_array( $defaults ) ) {
			\WP_TTS\Utils\Logger::debugLog( "[ConfigurationManager] getDefaults() returned non-array: " . gettype( $defaults ) );
			return $this->defaults['defaults'];
		}
		
		return $defaults;
	}

	/**
	 * Update default settings
	 *
	 * @param array $defaults New default settings
	 */
	public function updateDefaults( array $defaults ): void {
		$current = $this->getDefaults();
		$updated = array_merge( $current, $defaults );
		$this->set( 'defaults', $updated );
	}

	/**
	 * Get round-robin state
	 *
	 * @return array Round-robin state
	 */
	public function getRoundRobinState(): array {
		return $this->get( 'round_robin', array() );
	}

	/**
	 * Update round-robin state
	 *
	 * @param array $state New round-robin state
	 */
	public function updateRoundRobinState( array $state ): void {
		$this->set( 'round_robin', $state );
	}

	/**
	 * Get cache settings
	 *
	 * @return array Cache settings
	 */
	public function getCacheSettings(): array {
		return $this->get( 'cache', $this->defaults['cache'] );
	}

	/**
	 * Get audio library configuration
	 *
	 * @return array Audio library configuration
	 */
	public function getAudioLibrary(): array {
		return $this->get( 'audio_library', $this->defaults['audio_library'] );
	}

	/**
	 * Add audio file to library
	 *
	 * @param string $type File type (intro, background, outro)
	 * @param string $filename Filename
	 * @param string $url File URL
	 */
	public function addAudioFile( string $type, string $filename, string $url ): void {
		$library = $this->getAudioLibrary();
		$key     = $type . '_files';

		if ( ! isset( $library[ $key ] ) ) {
			$library[ $key ] = array();
		}

		$library[ $key ][ $filename ] = $url;
		$this->set( 'audio_library', $library );
	}

	/**
	 * Validate configuration
	 *
	 * @param array $config Configuration to validate
	 * @return array Validation errors
	 */
	public function validateConfiguration( array $config ): array {
		$errors = array();

		// Validate providers
		if ( isset( $config['providers'] ) ) {
			foreach ( $config['providers'] as $provider => $providerConfig ) {
				$providerErrors = $this->validateProviderConfig( $provider, $providerConfig );
				if ( ! empty( $providerErrors ) ) {
					$errors[ "providers.{$provider}" ] = $providerErrors;
				}
			}
		}

		// Validate defaults
		if ( isset( $config['defaults'] ) ) {
			$defaultErrors = $this->validateDefaultSettings( $config['defaults'] );
			if ( ! empty( $defaultErrors ) ) {
				$errors['defaults'] = $defaultErrors;
			}
		}

		return $errors;
	}

	/**
	 * Validate provider configuration
	 *
	 * @param string $provider Provider name
	 * @param array  $config Provider configuration
	 * @return array Validation errors
	 */
	private function validateProviderConfig( string $provider, array $config ): array {
		$errors = array();

		switch ( $provider ) {
			case 'azure':
				if ( empty( $config['api_key'] ) ) {
					$errors[] = __( 'La clave API es requerida', 'tts-sesolibre' );
				}
				if ( empty( $config['region'] ) ) {
					$errors[] = __( 'La región es requerida', 'tts-sesolibre' );
				}
				break;

			case 'google':
				if ( empty( $config['credentials_json'] ) ) {
					$errors[] = __( 'Las credenciales de cuenta de servicio son requeridas', 'tts-sesolibre' );
				}
				break;

			case 'polly':
				if ( empty( $config['access_key'] ) || empty( $config['secret_key'] ) ) {
					$errors[] = __( 'La clave de acceso y clave secreta de AWS son requeridas', 'tts-sesolibre' );
				}
				break;

			case 'elevenlabs':
				if ( empty( $config['api_key'] ) ) {
					$errors[] = __( 'La clave API es requerida', 'tts-sesolibre' );
				}
				break;
		}

		return $errors;
	}

	/**
	 * Validate default settings
	 *
	 * @param array $defaults Default settings
	 * @return array Validation errors
	 */
	private function validateDefaultSettings( array $defaults ): array {
		$errors = array();

		if ( isset( $defaults['voice_speed'] ) ) {
			$speed = floatval( $defaults['voice_speed'] );
			if ( $speed < 0.25 || $speed > 4.0 ) {
				$errors[] = __( 'La velocidad de voz debe estar entre 0.25 y 4.0', 'tts-sesolibre' );
			}
		}

		if ( isset( $defaults['voice_pitch'] ) ) {
			$pitch = intval( $defaults['voice_pitch'] );
			if ( $pitch < -20 || $pitch > 20 ) {
				$errors[] = __( 'El tono de voz debe estar entre -20 y 20', 'tts-sesolibre' );
			}
		}

		return $errors;
	}

	/**
	 * Save configuration to database
	 */
	public function save(): void {
		update_option( self::OPTION_MAIN, $this->toOptionArray() );
	}

	/**
	 * Build the canonical wp_tts_config array from the in-memory sections,
	 * preserving any unknown top-level keys already stored in the option.
	 *
	 * Also used by the Settings API sanitize callback: returning this array
	 * makes WordPress write exactly what the manager holds, instead of the
	 * raw form input (which is how the two stores used to drift).
	 *
	 * @return array Canonical option value.
	 */
	public function toOptionArray(): array {
		$data = get_option( self::OPTION_MAIN, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		foreach ( self::SECTION_KEYS as $section => $key ) {
			$data[ $key ] = $this->config[ $section ];
		}

		// Keep the top-level mirror that runtime code reads directly.
		$data['default_provider'] = $this->config['defaults']['default_provider']
			?? ( $data['default_provider'] ?? 'google' );

		return $data;
	}

	/**
	 * Reset configuration to defaults
	 *
	 * @param bool $keepCredentials Whether to keep existing credentials
	 */
	public function reset( bool $keepCredentials = true ): void {
		if ( $keepCredentials ) {
			// Preserve existing credentials
			$currentProviders = $this->get( 'providers', array() );
			$defaultProviders = $this->defaults['providers'];

			foreach ( $defaultProviders as $provider => $config ) {
				if ( isset( $currentProviders[ $provider ] ) ) {
					// Keep existing credentials
					$credentialKeys = array( 'api_key', 'credentials_json', 'access_key', 'secret_key', 'api_token' );
					foreach ( $credentialKeys as $key ) {
						if ( isset( $currentProviders[ $provider ][ $key ] ) ) {
							$defaultProviders[ $provider ][ $key ] = $currentProviders[ $provider ][ $key ];
						}
					}
				}
			}

			$this->config['providers'] = $defaultProviders;
		} else {
			$this->config = $this->defaults;
		}

		$this->save();
	}

	/**
	 * Export configuration
	 *
	 * @param bool $includeCredentials Whether to include credentials
	 * @return array Configuration array
	 */
	public function export( bool $includeCredentials = false ): array {
		$config = $this->config;

		if ( ! $includeCredentials ) {
			// Remove sensitive data
			foreach ( $config['providers'] as $provider => &$providerConfig ) {
				$sensitiveKeys = array( 'api_key', 'credentials_json', 'access_key', 'secret_key', 'api_token', 'client_secret' );
				foreach ( $sensitiveKeys as $key ) {
					if ( isset( $providerConfig[ $key ] ) ) {
						$providerConfig[ $key ] = '[REDACTED]';
					}
				}
			}
		}

		return $config;
	}

	/**
	 * Import configuration
	 *
	 * @param array $config Configuration to import
	 * @param bool  $merge Whether to merge with existing config
	 * @return bool Success status
	 */
	public function import( array $config, bool $merge = true ): bool {
		$errors = $this->validateConfiguration( $config );

		if ( ! empty( $errors ) ) {
			return false;
		}

		if ( $merge ) {
			// array_replace_recursive, NOT array_merge_recursive: the latter
			// turns colliding scalars into arrays (api_key => ['old','new'])
			// and corrupts the stored configuration.
			$this->config = array_replace_recursive( $this->config, $config );
		} else {
			$this->config = array_merge( $this->defaults, $config );
		}

		$this->save();
		return true;
	}

	/**
	 * Get nested value from array using dot notation
	 *
	 * @param array  $array Array to search
	 * @param string $key Key in dot notation
	 * @param mixed  $default Default value
	 * @return mixed Found value or default
	 */
	private function getNestedValue( array $array, string $key, $default = null ) {
		$keys  = explode( '.', $key );
		$value = $array;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}

	/**
	 * Set nested value in array using dot notation
	 *
	 * @param array  &$array Array to modify
	 * @param string $key Key in dot notation
	 * @param mixed  $value Value to set
	 */
	private function setNestedValue( array &$array, string $key, $value ): void {
		$keys    = explode( '.', $key );
		$current = &$array;

		foreach ( $keys as $k ) {
			if ( ! isset( $current[ $k ] ) || ! is_array( $current[ $k ] ) ) {
				$current[ $k ] = array();
			}
			$current = &$current[ $k ];
		}

		$current = $value;
	}

	/**
	 * Get logger instance
	 *
	 * @return \WP_TTS\Utils\Logger Logger instance
	 */
	public function getLogger() {
		static $logger = null;
		if ( $logger === null ) {
			$logger = new \WP_TTS\Utils\Logger();
		}
		return $logger;
	}
}

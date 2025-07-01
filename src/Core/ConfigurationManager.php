<?php

namespace WP_TTS\Core;

/**
 * Configuration Manager
 *
 * Manages plugin configuration, settings, and provider credentials
 * with secure storage and validation.
 */
class ConfigurationManager {

	/**
	 * Option names for WordPress options table
	 */
	private const OPTION_PROVIDERS     = 'wp_tts_providers_config';
	private const OPTION_DEFAULTS      = 'wp_tts_default_settings';
	private const OPTION_ROUND_ROBIN   = 'wp_tts_round_robin_state';
	private const OPTION_CACHE         = 'wp_tts_cache_settings';
	private const OPTION_AUDIO_LIBRARY = 'wp_tts_audio_library';
	private const OPTION_ANALYTICS     = 'wp_tts_analytics_settings';
	private const OPTION_PLAYER        = 'wp_tts_player_settings';

	/**
	 * Default configuration values
	 *
	 * @var array
	 */
	private $defaults = array(
		'providers'     => array(
			'azure'      => array(
				'enabled'       => false,
				'api_key'       => '',
				'region'        => 'eastus',
				'default_voice' => 'es-MX-DaliaNeural',
				'quota_limit'   => 500000, // characters per month
				'priority'      => 1,
			),
			'google'     => array(
				'enabled'          => false,
				'credentials_json' => '',
				'default_voice'    => 'es-US-Neural2-A',
				'quota_limit'      => 1000000,
				'priority'         => 2,
			),
			'polly'      => array(
				'enabled'       => false,
				'access_key'    => '',
				'secret_key'    => '',
				'region'        => 'us-east-1',
				'default_voice' => 'Mia',
				'quota_limit'   => 5000000,
				'priority'      => 3,
			),
			'elevenlabs' => array(
				'enabled'       => false,
				'api_key'       => '',
				'default_voice' => '',
				'quota_limit'   => 10000,
				'priority'      => 4,
			),
		),
		'storage'       => array(
			'buzzsprout' => array(
				'enabled'      => false,
				'api_token'    => '',
				'podcast_id'   => '',
				'auto_publish' => false,
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
			'default_provider'      => 'azure',
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
	 * Load configuration from WordPress options
	 */
	private function loadConfiguration(): void {
		$this->config = array(
			'providers'     => $this->getOption( self::OPTION_PROVIDERS, $this->defaults['providers'] ),
			'defaults'      => $this->getOption( self::OPTION_DEFAULTS, $this->defaults['defaults'] ),
			'round_robin'   => $this->getOption(
				self::OPTION_ROUND_ROBIN,
				array(
					'current_provider' => $this->defaults['defaults']['default_provider'],
					'usage_count'      => array(),
					'last_reset'       => date( 'Y-m-01' ), // First day of current month
					'failed_providers' => array(),
				)
			),
			'cache'         => $this->getOption( self::OPTION_CACHE, $this->defaults['cache'] ),
			'audio_library' => $this->getOption( self::OPTION_AUDIO_LIBRARY, $this->defaults['audio_library'] ),
			'analytics'     => $this->getOption( self::OPTION_ANALYTICS, $this->defaults['analytics'] ),
			'player'        => $this->getOption( self::OPTION_PLAYER, $this->defaults['player'] ),
		);
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
		$enabled = array();

		foreach ( $storage as $name => $config ) {
			if ( ! empty( $config['enabled'] ) ) {
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
		return $this->get( 'defaults', $this->defaults['defaults'] );
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
					$errors[] = __( 'La clave API es requerida', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
				}
				if ( empty( $config['region'] ) ) {
					$errors[] = __( 'La región es requerida', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
				}
				break;

			case 'google':
				if ( empty( $config['credentials_json'] ) ) {
					$errors[] = __( 'Las credenciales de cuenta de servicio son requeridas', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
				}
				break;

			case 'polly':
				if ( empty( $config['access_key'] ) || empty( $config['secret_key'] ) ) {
					$errors[] = __( 'La clave de acceso y clave secreta de AWS son requeridas', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
				}
				break;

			case 'elevenlabs':
				if ( empty( $config['api_key'] ) ) {
					$errors[] = __( 'La clave API es requerida', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
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
				$errors[] = __( 'La velocidad de voz debe estar entre 0.25 y 4.0', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
			}
		}

		if ( isset( $defaults['voice_pitch'] ) ) {
			$pitch = intval( $defaults['voice_pitch'] );
			if ( $pitch < -20 || $pitch > 20 ) {
				$errors[] = __( 'El tono de voz debe estar entre -20 y 20', 'TTS-SesoLibre-v1.6.7-shortcode-docs' );
			}
		}

		return $errors;
	}

	/**
	 * Save configuration to database
	 */
	public function save(): void {
		update_option( self::OPTION_PROVIDERS, $this->config['providers'] );
		update_option( self::OPTION_DEFAULTS, $this->config['defaults'] );
		update_option( self::OPTION_ROUND_ROBIN, $this->config['round_robin'] );
		update_option( self::OPTION_CACHE, $this->config['cache'] );
		update_option( self::OPTION_AUDIO_LIBRARY, $this->config['audio_library'] );
		update_option( self::OPTION_ANALYTICS, $this->config['analytics'] );
		update_option( self::OPTION_PLAYER, $this->config['player'] );
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
			$this->config = array_merge_recursive( $this->config, $config );
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
	 * Get option with default value
	 *
	 * @param string $option Option name
	 * @param mixed  $default Default value
	 * @return mixed Option value
	 */
	private function getOption( string $option, $default = null ) {
		return get_option( $option, $default );
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

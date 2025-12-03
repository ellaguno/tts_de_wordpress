<?php

namespace WP_TTS\Core;

use WP_TTS\Providers\BuzzsproutStorageProvider;
use WP_TTS\Providers\LocalStorageProvider;
use WP_TTS\Providers\S3StorageProvider;
use WP_TTS\Providers\SpotifyStorageProvider;
use WP_TTS\Interfaces\SimpleStorageProviderInterface;
use WP_TTS\Exceptions\ProviderException;

/**
 * Storage Provider Factory
 *
 * Creates and manages storage provider instances based on configuration
 */
class StorageProviderFactory {

	/**
	 * Configuration Manager instance
	 *
	 * @var ConfigurationManager
	 */
	private $config_manager;

	/**
	 * Cache of provider instances
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Constructor
	 *
	 * @param ConfigurationManager $config_manager Configuration manager instance
	 */
	public function __construct( ConfigurationManager $config_manager ) {
		$this->config_manager = $config_manager;
	}

	/**
	 * Get storage provider instance
	 *
	 * @param string|null $provider_name Provider name, null for default
	 * @return StorageProviderInterface
	 * @throws ProviderException If provider is not supported or not configured
	 */
	public function getProvider( ?string $provider_name = null ): SimpleStorageProviderInterface {
		// Use default provider if none specified
		if ( $provider_name === null ) {
			$provider_name = $this->config_manager->get( 'defaults.default_storage', 'local' );
		}

		// Return cached instance if available
		if ( isset( $this->providers[ $provider_name ] ) ) {
			return $this->providers[ $provider_name ];
		}

		// Get provider configuration
		$config = $this->config_manager->getStorageConfig( $provider_name );

		// Check if provider is enabled
		if ( empty( $config['enabled'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( "Storage provider '{$provider_name}' is not enabled" );
		}

		// Create provider instance
		$provider = $this->createProvider( $provider_name, $config );

		// Cache and return
		$this->providers[ $provider_name ] = $provider;
		return $provider;
	}

	/**
	 * Get enabled storage provider
	 *
	 * Falls back to next available provider if primary is not available
	 *
	 * @return StorageProviderInterface
	 * @throws ProviderException If no storage providers are available
	 */
	public function getEnabledProvider(): SimpleStorageProviderInterface {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'StorageProviderFactory: Starting getEnabledProvider()' );
		
		$enabled_providers = $this->config_manager->getEnabledStorageProviders();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'StorageProviderFactory: Enabled providers: ' . implode(', ', $enabled_providers) );

		if ( empty( $enabled_providers ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'StorageProviderFactory: No providers enabled, falling back to local storage' );
			// Fall back to local storage if nothing is enabled
			$local_config = $this->config_manager->getStorageConfig( 'local' );
			$local_config['enabled'] = true;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'StorageProviderFactory: Local config for fallback: ' . json_encode($local_config) );
			return $this->createProvider( 'local', $local_config );
		}

		// Try to get the default storage provider first
		$default_storage = $this->config_manager->get( 'defaults.default_storage', 'local' );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "StorageProviderFactory: Default storage: $default_storage" );
		
		if ( in_array( $default_storage, $enabled_providers ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "StorageProviderFactory: Trying default provider: $default_storage" );
			try {
				$provider = $this->getProvider( $default_storage );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "StorageProviderFactory: Successfully got default provider: " . get_class($provider) );
				return $provider;
			} catch ( ProviderException $e ) {
				// Log error and continue to try other providers
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "StorageProviderFactory: Failed to get default storage provider '{$default_storage}': " . $e->getMessage() );
			}
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "StorageProviderFactory: Default storage '$default_storage' not in enabled providers: " . implode(', ', $enabled_providers) );
		}

		// Try other enabled providers
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "StorageProviderFactory: Trying other enabled providers" );
		foreach ( $enabled_providers as $provider_name ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "StorageProviderFactory: Trying provider: $provider_name" );
			try {
				$provider = $this->getProvider( $provider_name );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "StorageProviderFactory: Successfully got provider: " . get_class($provider) );
				return $provider;
			} catch ( ProviderException $e ) {
				// Log error and continue
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "StorageProviderFactory: Failed to get storage provider '{$provider_name}': " . $e->getMessage() );
			}
		}

		// Ultimate fallback: force local storage to work
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "StorageProviderFactory: All providers failed, applying ultimate fallback to local storage" );
		try {
			// Force minimal local storage configuration
			$emergency_config = [
				'enabled' => true,
				'upload_path' => 'wp-content/uploads/tts-audio/',
				'max_file_size' => 50
			];
			
			$local_provider = $this->createProvider( 'local', $emergency_config );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "StorageProviderFactory: Emergency local storage provider created successfully" );
			return $local_provider;
			
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "StorageProviderFactory: Even emergency local storage failed: " . $e->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( 'No storage providers are available, including emergency local storage: ' . $e->getMessage() );
		}
	}

	/**
	 * Create provider instance
	 *
	 * @param string $provider_name Provider name
	 * @param array  $config Provider configuration
	 * @return StorageProviderInterface
	 * @throws ProviderException If provider is not supported
	 */
	private function createProvider( string $provider_name, array $config ): SimpleStorageProviderInterface {
		switch ( $provider_name ) {
			case 'local':
				return new LocalStorageProvider( $config );

			case 'buzzsprout':
				return new BuzzsproutStorageProvider( $config );

			case 's3':
				if ( ! class_exists( 'WP_TTS\Providers\S3StorageProvider' ) ) {
					throw new ProviderException( "S3 storage provider is not available" );
				}
				return new S3StorageProvider( $config );

			case 'spotify':
				if ( ! class_exists( 'WP_TTS\Providers\SpotifyStorageProvider' ) ) {
					throw new ProviderException( "Spotify storage provider is not available" );
				}
				return new SpotifyStorageProvider( $config );

			default:
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new ProviderException( "Storage provider '{$provider_name}' is not supported" );
		}
	}

	/**
	 * Clear provider cache
	 */
	public function clearCache(): void {
		$this->providers = array();
	}

	/**
	 * Get all available storage provider names
	 *
	 * @return array Array of provider names
	 */
	public function getAvailableProviders(): array {
		return array( 'local', 'buzzsprout', 's3', 'spotify' );
	}

	/**
	 * Check if provider is supported
	 *
	 * @param string $provider_name Provider name
	 * @return bool
	 */
	public function isProviderSupported( string $provider_name ): bool {
		return in_array( $provider_name, $this->getAvailableProviders() );
	}

	/**
	 * Get provider status information
	 *
	 * @return array Provider status information
	 */
	public function getProviderStatus(): array {
		$status = array();
		$available_providers = $this->getAvailableProviders();

		foreach ( $available_providers as $provider_name ) {
			$config = $this->config_manager->getStorageConfig( $provider_name );
			
			$status[ $provider_name ] = array(
				'name'        => $provider_name,
				'enabled'     => ! empty( $config['enabled'] ),
				'configured'  => $this->isProviderConfigured( $provider_name, $config ),
				'available'   => $this->isProviderAvailable( $provider_name ),
			);
		}

		return $status;
	}

	/**
	 * Check if provider is properly configured
	 *
	 * @param string $provider_name Provider name
	 * @param array  $config Provider configuration
	 * @return bool
	 */
	private function isProviderConfigured( string $provider_name, array $config ): bool {
		switch ( $provider_name ) {
			case 'local':
				return true; // Local storage is always configured

			case 'buzzsprout':
				return ! empty( $config['api_token'] ) && ! empty( $config['podcast_id'] );

			case 's3':
				return ! empty( $config['access_key'] ) && 
				       ! empty( $config['secret_key'] ) && 
				       ! empty( $config['bucket'] );

			case 'spotify':
				return ! empty( $config['client_id'] ) && 
				       ! empty( $config['client_secret'] ) && 
				       ! empty( $config['show_id'] );

			default:
				return false;
		}
	}

	/**
	 * Check if provider class is available
	 *
	 * @param string $provider_name Provider name
	 * @return bool
	 */
	private function isProviderAvailable( string $provider_name ): bool {
		switch ( $provider_name ) {
			case 'local':
				return class_exists( 'WP_TTS\Providers\LocalStorageProvider' );

			case 'buzzsprout':
				return class_exists( 'WP_TTS\Providers\BuzzsproutStorageProvider' );

			case 's3':
				return class_exists( 'WP_TTS\Providers\S3StorageProvider' );

			case 'spotify':
				return class_exists( 'WP_TTS\Providers\SpotifyStorageProvider' );

			default:
				return false;
		}
	}
}

<?php
/**
 * Round Robin Manager for TTS providers
 *
 * @package WP_TTS
 */

namespace WP_TTS\Services;

use WP_TTS\Core\ConfigurationManager;

/**
 * Manages round-robin distribution of TTS requests
 */
class RoundRobinManager {

	/**
	 * Configuration manager instance
	 *
	 * @var ConfigurationManager
	 */
	private $config;

	/**
	 * Current provider index
	 *
	 * @var int
	 */
	private $current_index = 0;

	/**
	 * Constructor
	 *
	 * @param ConfigurationManager $config Configuration manager.
	 */
	public function __construct( ConfigurationManager $config ) {
		$this->config = $config;
		$this->current_index = get_option( 'wp_tts_round_robin_index', 0 );
	}

	/**
	 * Get next available TTS provider
	 *
	 * @return string|null Provider name or null if none available.
	 */
	public function getNextProvider(): ?string {
		$providers = $this->getActiveProviders();

		if ( empty( $providers ) ) {
			return null;
		}

		$provider = $providers[ $this->current_index ];

		// Move to next provider for next request
		$this->current_index = ( $this->current_index + 1 ) % count( $providers );
		update_option( 'wp_tts_round_robin_index', $this->current_index );

		return $provider;
	}

	/**
	 * Get all active TTS providers
	 *
	 * @return array Array of active provider names.
	 */
	public function getActiveProviders(): array {
		$all_providers = [ 'google', 'amazon_polly', 'azure_tts', 'openai', 'elevenlabs', 'aws' ];
		$active_providers = [];

		foreach ( $all_providers as $provider ) {
			if ( $this->isProviderActive( $provider ) ) {
				$active_providers[] = $provider;
			}
		}

		return $active_providers;
	}

	/**
	 * Check if a provider is active and configured
	 *
	 * @param string $provider Provider name.
	 * @return bool True if active.
	 */
	public function isProviderActive( string $provider ): bool {
		$config = get_option( 'wp_tts_config', [] );

		switch ( $provider ) {
			case 'google':
				$credentials_path = $config['providers']['google']['credentials_path'] ?? '';

				// Check for default credentials file
				if ( empty( $credentials_path ) ) {
					$upload_dir = wp_upload_dir();
					$default_path = $upload_dir['basedir'] . '/private/sesolibre-tts-13985ba22d36.json';
					if ( file_exists( $default_path ) ) {
						return true;
					}
				} else {
					// Convert relative paths to absolute paths
					if ( substr( $credentials_path, 0, 1 ) !== '/' && strpos( $credentials_path, ':' ) === false ) {
						$credentials_path = ABSPATH . $credentials_path;
					}
					if ( file_exists( $credentials_path ) ) {
						return true;
					}
				}
				return false;

			case 'openai':
				$api_key = $config['providers']['openai']['api_key'] ?? '';
				return ! empty( $api_key );

			case 'elevenlabs':
				$api_key = $config['providers']['elevenlabs']['api_key'] ?? '';
				return ! empty( $api_key );

			case 'amazon_polly':
				$access_key = $config['providers']['amazon_polly']['access_key'] ?? '';
				$secret_key = $config['providers']['amazon_polly']['secret_key'] ?? '';
				$region = $config['providers']['amazon_polly']['region'] ?? '';
				return ! empty( $access_key ) && ! empty( $secret_key ) && ! empty( $region );

			case 'azure_tts':
				$subscription_key = $config['providers']['azure_tts']['subscription_key'] ?? '';
				$region = $config['providers']['azure_tts']['region'] ?? '';
				return ! empty( $subscription_key ) && ! empty( $region );

			default:
				return false;
		}
	}

	/**
	 * Reset round-robin to first provider
	 */
	public function reset(): void {
		$this->current_index = 0;
		update_option( 'wp_tts_round_robin_index', 0 );
	}

	/**
	 * Get current provider without advancing
	 *
	 * @return string|null Current provider name.
	 */
	public function getCurrentProvider(): ?string {
		$providers = $this->getActiveProviders();

		if ( empty( $providers ) ) {
			return null;
		}

		return $providers[ $this->current_index ];
	}

	/**
	 * Get provider statistics
	 *
	 * @return array Provider usage stats.
	 */
	public function getStats(): array {
		$stats = get_option( 'wp_tts_provider_stats', [] );

		return [
			'current_index' => $this->current_index,
			'active_providers' => $this->getActiveProviders(),
			'usage_stats' => $stats,
		];
	}

	/**
	 * Record provider usage
	 *
	 * @param string $provider Provider name.
	 * @param bool   $success  Whether request was successful.
	 */
	public function recordUsage( string $provider, bool $success ): void {
		$stats = get_option( 'wp_tts_provider_stats', [] );

		if ( ! isset( $stats[ $provider ] ) ) {
			$stats[ $provider ] = [
				'total_requests' => 0,
				'successful_requests' => 0,
				'failed_requests' => 0,
			];
		}

		$stats[ $provider ]['total_requests']++;

		if ( $success ) {
			$stats[ $provider ]['successful_requests']++;
		} else {
			$stats[ $provider ]['failed_requests']++;
		}

		update_option( 'wp_tts_provider_stats', $stats );
	}
}

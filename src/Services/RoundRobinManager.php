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
		error_log( '[WP_TTS DEBUG] Getting active providers' );
		$providers = $this->getActiveProviders();
		error_log( '[WP_TTS DEBUG] Active providers: ' . wp_json_encode( $providers ) );
		
		if ( empty( $providers ) ) {
			error_log( '[WP_TTS DEBUG] No active providers found' );
			return null;
		}
		
		$provider = $providers[ $this->current_index ];
		error_log( '[WP_TTS DEBUG] Selected provider: ' . $provider );
		
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
		error_log( '[WP_TTS DEBUG] Checking active providers' );
		$all_providers = [ 'google', 'amazon_polly', 'azure_tts', 'openai', 'elevenlabs', 'aws' ];
		$active_providers = [];
		
		foreach ( $all_providers as $provider ) {
			error_log( '[WP_TTS DEBUG] Checking provider: ' . $provider );
			$is_active = $this->isProviderActive( $provider );
			error_log( '[WP_TTS DEBUG] Provider ' . $provider . ' is active: ' . ( $is_active ? 'yes' : 'no' ) );
			if ( $is_active ) {
				$active_providers[] = $provider;
			}
		}
		
		// If no providers are configured, return google as default
		if ( empty( $active_providers ) ) {
			error_log( '[WP_TTS DEBUG] No active providers, using google as default' );
			$active_providers = [ 'google' ];
		}
		
		error_log( '[WP_TTS DEBUG] Final active providers: ' . wp_json_encode( $active_providers ) );
		return $active_providers;
	}
	
	/**
	 * Check if a provider is active and configured
	 *
	 * @param string $provider Provider name.
	 * @return bool True if active.
	 */
	public function isProviderActive( string $provider ): bool {
		// All providers are now active in mock mode for testing
		return in_array( $provider, [ 'google', 'amazon_polly', 'azure_tts', 'openai', 'elevenlabs' ] );
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
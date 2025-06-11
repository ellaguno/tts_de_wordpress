<?php
/**
 * Cache Service implementation
 *
 * @package WP_TTS
 */

namespace WP_TTS\Services;

use WP_TTS\Interfaces\CacheServiceInterface;

/**
 * Basic cache service implementation
 */
class CacheService implements CacheServiceInterface {
	
	/**
	 * Cache audio URL with metadata
	 *
	 * @param string $textHash Hash of the text content.
	 * @param string $url Audio file URL.
	 * @param int    $duration Cache duration in seconds.
	 * @param array  $metadata Additional metadata.
	 */
	public function cacheAudioUrl( string $textHash, string $url, int $duration = null, array $metadata = array() ): void {
		$cache_key = 'wp_tts_audio_' . $textHash;
		$cache_data = [
			'url' => $url,
			'metadata' => $metadata,
			'timestamp' => time(),
		];
		
		$cache_duration = $duration ?? HOUR_IN_SECONDS * 24;
		wp_cache_set( $cache_key, $cache_data, 'wp_tts', $cache_duration );
	}
	
	/**
	 * Get cached audio URL (interface method)
	 *
	 * @param string $textHash Text hash.
	 * @return string|null Audio URL or null if not found.
	 */
	public function getCachedAudio( string $textHash ): ?string {
		$cache_key = 'wp_tts_audio_' . $textHash;
		$cache_data = wp_cache_get( $cache_key, 'wp_tts' );
		
		if ( false === $cache_data || ! is_array( $cache_data ) ) {
			return null;
		}
		
		return $cache_data['url'] ?? null;
	}
	
	/**
	 * Get cached audio URL (legacy method for backward compatibility)
	 *
	 * @param string $textHash Text hash.
	 * @return string|null Audio URL or null if not found.
	 */
	public function getCachedAudioUrl( string $textHash ): ?string {
		return $this->getCachedAudio( $textHash );
	}
	
	/**
	 * Generate text hash
	 *
	 * @param string $text    Text content.
	 * @param array  $options TTS options.
	 * @return string Hash string.
	 */
	public function generateTextHash( string $text, array $options = [] ): string {
		$hash_data = [
			'text' => $text,
			'options' => $options,
		];
		
		return md5( wp_json_encode( $hash_data ) );
	}
	
	/**
	 * Clean expired cache entries
	 *
	 * @return int Number of entries cleaned.
	 */
	public function cleanExpiredCache(): int {
		// WordPress cache doesn't have built-in expiration tracking
		// This is a basic implementation
		return 0;
	}
	
	/**
	 * Check if cache entry exists
	 *
	 * @param string $textHash Text hash.
	 * @return bool True if entry exists.
	 */
	public function hasCache( string $textHash ): bool {
		$cache_key = 'wp_tts_audio_' . $textHash;
		$cache_data = wp_cache_get( $cache_key, 'wp_tts' );
		return false !== $cache_data;
	}
	
	/**
	 * Remove specific cache entry (interface method)
	 *
	 * @param string $textHash Text hash.
	 * @return bool Success status.
	 */
	public function removeCache( string $textHash ): bool {
		$cache_key = 'wp_tts_audio_' . $textHash;
		return wp_cache_delete( $cache_key, 'wp_tts' );
	}
	
	/**
	 * Clear cache for specific hash (legacy method for backward compatibility)
	 *
	 * @param string $textHash Text hash.
	 * @return bool True on success.
	 */
	public function clearCache( string $textHash ): bool {
		return $this->removeCache( $textHash );
	}
	
	/**
	 * Clear all TTS cache
	 *
	 * @return bool True on success.
	 */
	public function clearAllCache(): bool {
		// WordPress doesn't have a built-in way to clear cache by group
		// This is a basic implementation
		return wp_cache_flush();
	}
	
	/**
	 * Get cache statistics
	 *
	 * @return array Cache stats.
	 */
	public function getCacheStats(): array {
		// Basic implementation - in a real scenario you'd track these
		return [
			'hits' => 0,
			'misses' => 0,
			'size' => 0,
		];
	}
	
	/**
	 * Check if cache is enabled
	 *
	 * @return bool True if enabled.
	 */
	public function isEnabled(): bool {
		return true; // Always enabled for now
	}
	
	/**
	 * Get cache metadata
	 *
	 * @param string $textHash Text hash.
	 * @return array|null Cache metadata or null if not found.
	 */
	public function getCacheMetadata( string $textHash ): ?array {
		$cache_key = 'wp_tts_audio_' . $textHash;
		$cache_data = wp_cache_get( $cache_key, 'wp_tts' );
		
		if ( false === $cache_data || ! is_array( $cache_data ) ) {
			return null;
		}
		
		return $cache_data['metadata'] ?? null;
	}
	
	/**
	 * Set cache configuration
	 *
	 * @param array $config Configuration array.
	 */
	public function setConfig( array $config ): void {
		// Store config in options or class property
		update_option( 'wp_tts_cache_config', $config );
	}
	
	/**
	 * Get cache configuration
	 *
	 * @return array Configuration array.
	 */
	public function getConfig(): array {
		return get_option( 'wp_tts_cache_config', [] );
	}
}
<?php
/**
 * Cache Service implementation
 *
 * @package WP_TTS
 */

namespace WP_TTS\Services;

use WP_TTS\Interfaces\CacheServiceInterface;

/**
 * Cache service backed by WordPress transients.
 *
 * Transients persist across requests on any install (options table or
 * external object cache), unlike wp_cache_* which is per-request without a
 * drop-in. Entries are namespaced with a version salt so clearing the TTS
 * cache never touches other plugins' data (no wp_cache_flush()).
 */
class CacheService implements CacheServiceInterface {

	/**
	 * Option name holding the cache version salt
	 */
	private const VERSION_OPTION = 'wp_tts_cache_version';

	/**
	 * Get the current cache version salt
	 *
	 * @return int Version number.
	 */
	private function getCacheVersion(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Build the transient key for a text hash
	 *
	 * @param string $textHash Text hash.
	 * @return string Transient key.
	 */
	private function buildKey( string $textHash ): string {
		return 'wp_tts_audio_v' . $this->getCacheVersion() . '_' . $textHash;
	}

	/**
	 * Cache audio URL with metadata
	 *
	 * @param string $textHash Hash of the text content.
	 * @param string $url Audio file URL.
	 * @param int    $duration Cache duration in seconds.
	 * @param array  $metadata Additional metadata.
	 */
	public function cacheAudioUrl( string $textHash, string $url, int $duration = null, array $metadata = array() ): void {
		$cache_data = [
			'url' => $url,
			'metadata' => $metadata,
			'timestamp' => time(),
		];

		$cache_duration = $duration ?? HOUR_IN_SECONDS * 24;
		set_transient( $this->buildKey( $textHash ), $cache_data, $cache_duration );
	}

	/**
	 * Get cached audio URL (interface method)
	 *
	 * @param string $textHash Text hash.
	 * @return string|null Audio URL or null if not found.
	 */
	public function getCachedAudio( string $textHash ): ?string {
		$cache_data = get_transient( $this->buildKey( $textHash ) );

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
		// Only parameters that change the generated audio belong in the key.
		// Unrelated options (post_id, etc.) would fragment the cache, and
		// omitting provider/voice would serve audio from the wrong voice.
		$hash_data = [
			'text' => $text,
			'provider' => $options['provider'] ?? '',
			'voice' => $options['voice'] ?? '',
			'model' => $options['model'] ?? ( $options['model_id'] ?? '' ),
			'speaking_rate' => $options['speaking_rate'] ?? '',
			'pitch' => $options['pitch'] ?? '',
		];

		return md5( wp_json_encode( $hash_data ) );
	}

	/**
	 * Clean expired cache entries
	 *
	 * @return int Number of entries cleaned.
	 */
	public function cleanExpiredCache(): int {
		// WordPress deletes expired transients on access; nothing to do here.
		return 0;
	}

	/**
	 * Check if cache entry exists
	 *
	 * @param string $textHash Text hash.
	 * @return bool True if entry exists.
	 */
	public function hasCache( string $textHash ): bool {
		return false !== get_transient( $this->buildKey( $textHash ) );
	}

	/**
	 * Remove specific cache entry (interface method)
	 *
	 * @param string $textHash Text hash.
	 * @return bool Success status.
	 */
	public function removeCache( string $textHash ): bool {
		return delete_transient( $this->buildKey( $textHash ) );
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
	 * Bumps the version salt so every existing entry becomes unreachable and
	 * expires naturally. Never flushes the site-wide object cache.
	 *
	 * @return bool True on success.
	 */
	public function clearAllCache(): bool {
		return update_option( self::VERSION_OPTION, $this->getCacheVersion() + 1 );
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
		$cache_data = get_transient( $this->buildKey( $textHash ) );

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

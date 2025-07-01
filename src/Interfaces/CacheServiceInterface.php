<?php

namespace WP_TTS\Interfaces;

/**
 * Cache Service Interface
 *
 * Defines the contract for caching audio files and metadata
 * with minimal local storage requirements.
 */
interface CacheServiceInterface {

	/**
	 * Get cached audio URL by text hash
	 *
	 * @param string $textHash Hash of the text content
	 * @return string|null Cached audio URL or null if not found
	 */
	public function getCachedAudio( string $textHash): ?string;

	/**
	 * Cache audio URL with metadata
	 *
	 * @param string $textHash Hash of the text content
	 * @param string $url Audio file URL
	 * @param int    $duration Cache duration in seconds
	 * @param array  $metadata Additional metadata
	 */
	public function cacheAudioUrl( string $textHash, string $url, int $duration = null, array $metadata = array()): void;

	/**
	 * Generate hash for text content and options
	 *
	 * @param string $text Text content
	 * @param array  $options Voice and synthesis options
	 * @return string Content hash
	 */
	public function generateTextHash( string $text, array $options = array()): string;

	/**
	 * Clean expired cache entries
	 *
	 * @return int Number of entries cleaned
	 */
	public function cleanExpiredCache(): int;

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	public function getCacheStats(): array;

	/**
	 * Clear all cache entries
	 *
	 * @return bool Success status
	 */
	public function clearAllCache(): bool;

	/**
	 * Check if cache entry exists
	 *
	 * @param string $textHash Text hash
	 * @return bool True if entry exists
	 */
	public function hasCache( string $textHash): bool;

	/**
	 * Get cache entry metadata
	 *
	 * @param string $textHash Text hash
	 * @return array|null Cache metadata or null if not found
	 */
	public function getCacheMetadata( string $textHash): ?array;

	/**
	 * Remove specific cache entry
	 *
	 * @param string $textHash Text hash
	 * @return bool Success status
	 */
	public function removeCache( string $textHash): bool;

	/**
	 * Set cache configuration
	 *
	 * @param array $config Cache configuration
	 */
	public function setConfig( array $config): void;

	/**
	 * Get cache configuration
	 *
	 * @return array Cache configuration
	 */
	public function getConfig(): array;
}

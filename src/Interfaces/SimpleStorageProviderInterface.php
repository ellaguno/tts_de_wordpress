<?php

namespace WP_TTS\Interfaces;

/**
 * Simple Storage Provider Interface
 *
 * Simplified interface for basic storage operations
 */
interface SimpleStorageProviderInterface {

	/**
	 * Store audio file
	 *
	 * @param string $audio_data Audio file data
	 * @param string $filename Filename
	 * @param array  $metadata Additional metadata
	 * @return array Storage result with URL and metadata
	 * @throws \Exception If storage fails
	 */
	public function store( string $audio_data, string $filename, array $metadata = array() ): array;

	/**
	 * Delete audio file
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return bool Success status
	 */
	public function delete( string $identifier ): bool;

	/**
	 * Check if file exists
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return bool
	 */
	public function exists( string $identifier ): bool;

	/**
	 * Get file information
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return array|null File information or null if not found
	 */
	public function getFileInfo( string $identifier ): ?array;

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get provider configuration
	 *
	 * @return array
	 */
	public function getConfig(): array;

	/**
	 * Test provider connection/configuration
	 *
	 * @return bool
	 */
	public function test(): bool;
}
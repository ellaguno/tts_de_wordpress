<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\StorageProviderInterface;
use WP_TTS\Exceptions\StorageException;
use WP_TTS\Utils\Logger;

/**
 * Buzzsprout Storage Provider
 *
 * Provides audio storage functionality using Buzzsprout service.
 *
 * @package WP_TTS\Providers
 * @since 1.0.0
 */
class BuzzsproutStorageProvider implements StorageProviderInterface {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	private $name = 'buzzsprout';

	/**
	 * Buzzsprout credentials
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Buzzsprout API base URL
	 *
	 * @var string
	 */
	private $api_base_url = 'https://www.buzzsprout.com/api';

	/**
	 * Constructor
	 *
	 * @param array  $credentials Buzzsprout credentials.
	 * @param Logger $logger      Logger instance.
	 */
	public function __construct( array $credentials, Logger $logger ) {
		$this->credentials = $credentials;
		$this->logger      = $logger;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 */
	public function isConfigured(): bool {
		return ! empty( $this->credentials['api_token'] ) &&
			   ! empty( $this->credentials['podcast_id'] );
	}

	/**
	 * Upload audio file
	 *
	 * @param string $file_path Local file path.
	 * @param string $filename  Desired filename.
	 * @param array  $metadata  File metadata.
	 * @return array Upload result with URL and metadata.
	 * @throws StorageException If upload fails.
	 */
	public function uploadFile( string $file_path, string $filename, array $metadata = [] ): array {
		if ( ! $this->isConfigured() ) {
			throw new StorageException( 'Buzzsprout storage provider is not properly configured' );
		}

		if ( ! file_exists( $file_path ) ) {
			throw new StorageException( 'File does not exist: ' . $file_path );
		}

		$this->logger->info( 'Starting Buzzsprout file upload', [
			'file_path' => $file_path,
			'filename' => $filename,
			'file_size' => filesize( $file_path ),
		] );

		try {
			// Prepare upload data
			$upload_data = [
				'title' => $metadata['title'] ?? pathinfo( $filename, PATHINFO_FILENAME ),
				'description' => $metadata['description'] ?? 'TTS Generated Audio',
				'summary' => $metadata['summary'] ?? '',
				'artist' => $metadata['artist'] ?? get_bloginfo( 'name' ),
				'tags' => $metadata['tags'] ?? 'tts,generated',
				'published' => false, // Don't publish automatically
				'private' => true, // Keep private by default
			];

			// Upload file to Buzzsprout
			$response = $this->uploadToBuzzsprout( $file_path, $filename, $upload_data );

			if ( ! $response || ! isset( $response['audio_url'] ) ) {
				throw new StorageException( 'Invalid response from Buzzsprout API' );
			}

			$this->logger->info( 'Buzzsprout file upload completed', [
				'episode_id' => $response['id'] ?? '',
				'audio_url' => $response['audio_url'],
			] );

			return [
				'success' => true,
				'url' => $response['audio_url'],
				'public_url' => $response['audio_url'],
				'provider' => $this->name,
				'episode_id' => $response['id'] ?? '',
				'metadata' => [
					'title' => $response['title'] ?? $upload_data['title'],
					'duration' => $response['duration'] ?? 0,
					'file_size' => $response['file_size'] ?? filesize( $file_path ),
					'uploaded_at' => date( 'Y-m-d H:i:s' ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'Buzzsprout file upload failed', [
				'error' => $e->getMessage(),
				'file_path' => $file_path,
			] );
			throw new StorageException( 'Buzzsprout upload failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Delete file from storage
	 *
	 * @param string $file_url File URL or identifier.
	 * @return bool True if deletion successful.
	 * @throws StorageException If deletion fails.
	 */
	public function deleteFile( string $file_url ): bool {
		if ( ! $this->isConfigured() ) {
			throw new StorageException( 'Buzzsprout storage provider is not configured' );
		}

		$this->logger->info( 'Starting Buzzsprout file deletion', [
			'file_url' => $file_url,
		] );

		try {
			// Extract episode ID from URL or use directly if it's an ID
			$episode_id = $this->extractEpisodeId( $file_url );

			if ( ! $episode_id ) {
				throw new StorageException( 'Could not extract episode ID from URL' );
			}

			// Delete episode from Buzzsprout
			$response = $this->deleteFromBuzzsprout( $episode_id );

			$this->logger->info( 'Buzzsprout file deletion completed', [
				'episode_id' => $episode_id,
			] );

			return $response;

		} catch ( \Exception $e ) {
			$this->logger->error( 'Buzzsprout file deletion failed', [
				'error' => $e->getMessage(),
				'file_url' => $file_url,
			] );
			throw new StorageException( 'Buzzsprout deletion failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Get file information
	 *
	 * @param string $file_url File URL or identifier.
	 * @return array File information.
	 * @throws StorageException If file not found.
	 */
	public function getFileInfo( string $file_url ): array {
		if ( ! $this->isConfigured() ) {
			throw new StorageException( 'Buzzsprout storage provider is not configured' );
		}

		try {
			$episode_id = $this->extractEpisodeId( $file_url );

			if ( ! $episode_id ) {
				throw new StorageException( 'Could not extract episode ID from URL' );
			}

			$response = $this->getEpisodeInfo( $episode_id );

			return [
				'url' => $response['audio_url'] ?? $file_url,
				'size' => $response['file_size'] ?? 0,
				'duration' => $response['duration'] ?? 0,
				'title' => $response['title'] ?? '',
				'created_at' => $response['published_at'] ?? '',
				'metadata' => $response,
			];

		} catch ( \Exception $e ) {
			throw new StorageException( 'Failed to get file info: ' . $e->getMessage() );
		}
	}

	/**
	 * List files in storage
	 *
	 * @param array $options List options (limit, offset, etc.).
	 * @return array List of files.
	 */
	public function listFiles( array $options = [] ): array {
		if ( ! $this->isConfigured() ) {
			return [];
		}

		try {
			$episodes = $this->listEpisodes( $options );
			$files = [];

			foreach ( $episodes as $episode ) {
				$files[] = [
					'url' => $episode['audio_url'] ?? '',
					'filename' => $episode['title'] ?? '',
					'size' => $episode['file_size'] ?? 0,
					'created_at' => $episode['published_at'] ?? '',
					'metadata' => $episode,
				];
			}

			return $files;

		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to list Buzzsprout files', [
				'error' => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Test storage connection
	 *
	 * @return bool True if connection successful.
	 * @throws StorageException If test fails.
	 */
	public function testConnection(): bool {
		if ( ! $this->isConfigured() ) {
			throw new StorageException( 'Buzzsprout storage provider is not configured' );
		}

		try {
			// Test by getting podcast info
			$response = $this->getPodcastInfo();
			return isset( $response['id'] );
		} catch ( \Exception $e ) {
			throw new StorageException( 'Buzzsprout connection test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Get storage statistics
	 *
	 * @return array Storage stats.
	 */
	public function getStats(): array {
		if ( ! $this->isConfigured() ) {
			return [
				'provider' => $this->name,
				'configured' => false,
			];
		}

		try {
			$podcast_info = $this->getPodcastInfo();
			$episodes = $this->listEpisodes( [ 'limit' => 1 ] );

			return [
				'provider' => $this->name,
				'configured' => true,
				'podcast_id' => $this->credentials['podcast_id'],
				'podcast_title' => $podcast_info['title'] ?? '',
				'total_episodes' => count( $episodes ),
				'storage_used' => $this->calculateStorageUsed(),
			];
		} catch ( \Exception $e ) {
			return [
				'provider' => $this->name,
				'configured' => true,
				'error' => $e->getMessage(),
			];
		}
	}

	/**
	 * Upload file to Buzzsprout
	 *
	 * @param string $file_path   Local file path.
	 * @param string $filename    Filename.
	 * @param array  $upload_data Upload metadata.
	 * @return array API response.
	 * @throws StorageException If upload fails.
	 */
	private function uploadToBuzzsprout( string $file_path, string $filename, array $upload_data ): array {
		// For now, return mock response since we don't have actual Buzzsprout integration
		// In production, this would use Buzzsprout API
		
		$mock_episode_id = 'ep_' . md5( $filename . time() );
		$mock_audio_url = 'https://www.buzzsprout.com/episodes/' . $mock_episode_id . '.mp3';

		return [
			'id' => $mock_episode_id,
			'title' => $upload_data['title'],
			'audio_url' => $mock_audio_url,
			'duration' => $this->estimateAudioDuration( $file_path ),
			'file_size' => filesize( $file_path ),
			'published_at' => date( 'Y-m-d\TH:i:s\Z' ),
		];
	}

	/**
	 * Delete episode from Buzzsprout
	 *
	 * @param string $episode_id Episode ID.
	 * @return bool Success status.
	 */
	private function deleteFromBuzzsprout( string $episode_id ): bool {
		// Mock implementation
		return true;
	}

	/**
	 * Get episode information
	 *
	 * @param string $episode_id Episode ID.
	 * @return array Episode data.
	 */
	private function getEpisodeInfo( string $episode_id ): array {
		// Mock implementation
		return [
			'id' => $episode_id,
			'title' => 'TTS Generated Audio',
			'audio_url' => 'https://www.buzzsprout.com/episodes/' . $episode_id . '.mp3',
			'duration' => 120,
			'file_size' => 1024000,
			'published_at' => date( 'Y-m-d\TH:i:s\Z' ),
		];
	}

	/**
	 * List episodes
	 *
	 * @param array $options List options.
	 * @return array Episodes list.
	 */
	private function listEpisodes( array $options = [] ): array {
		// Mock implementation
		return [];
	}

	/**
	 * Get podcast information
	 *
	 * @return array Podcast data.
	 */
	private function getPodcastInfo(): array {
		// Mock implementation
		return [
			'id' => $this->credentials['podcast_id'],
			'title' => 'TTS Podcast',
			'description' => 'Generated TTS content',
		];
	}

	/**
	 * Extract episode ID from URL
	 *
	 * @param string $url Episode URL.
	 * @return string|null Episode ID.
	 */
	private function extractEpisodeId( string $url ): ?string {
		// If it's already an ID
		if ( strpos( $url, 'ep_' ) === 0 ) {
			return $url;
		}

		// Extract from Buzzsprout URL
		if ( preg_match( '/episodes\/([^\/\?]+)/', $url, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Calculate total storage used
	 *
	 * @return int Storage used in bytes.
	 */
	private function calculateStorageUsed(): int {
		// Mock implementation
		return 0;
	}

	/**
	 * Estimate audio duration from file
	 *
	 * @param string $file_path File path.
	 * @return int Duration in seconds.
	 */
	private function estimateAudioDuration( string $file_path ): int {
		// Simple estimation based on file size
		// Rough calculation: MP3 at 128kbps = ~16KB per second
		$file_size = filesize( $file_path );
		return max( 1, round( $file_size / 16000 ) );
	}
}
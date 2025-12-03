<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\SimpleStorageProviderInterface;
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
class BuzzsproutStorageProvider implements SimpleStorageProviderInterface {

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
	 * @param array $credentials Buzzsprout credentials.
	 */
	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
		// Create logger instance if not provided
		if ( class_exists( 'WP_TTS\\Utils\\Logger' ) ) {
			$this->logger = new Logger();
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			// Fallback to error_log if Logger not available
			$this->logger = null;
		}
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
	 * Store audio file (SimpleStorageProviderInterface implementation)
	 *
	 * @param string $audio_data Audio file data
	 * @param string $filename Filename
	 * @param array  $metadata Additional metadata
	 * @return array Storage result with URL and metadata
	 * @throws \Exception If storage fails
	 */
	public function store( string $audio_data, string $filename, array $metadata = array() ): array {
		if ( ! $this->isConfigured() ) {
			throw new \Exception( 'Buzzsprout storage provider is not properly configured' );
		}

		$this->log( 'info', 'Starting Buzzsprout file store', [
			'filename' => $filename,
			'data_size' => strlen( $audio_data ),
		] );

		// Save to temporary file first
		$temp_file = $this->createTempFile( $filename );
		if ( file_put_contents( $temp_file, $audio_data ) === false ) {
			throw new \Exception( 'Failed to create temporary file for Buzzsprout upload' );
		}

		try {
			$result = $this->uploadFile( $temp_file, $filename, $metadata );
			wp_delete_file( $temp_file ); // Clean up temp file
			return $result;
		} catch ( \Exception $e ) {
			wp_delete_file( $temp_file ); // Clean up temp file on error
			throw $e;
		}
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new StorageException( 'File does not exist: ' . $file_path );
		}

		$this->log( 'info', 'Starting Buzzsprout file upload', [
			'file_path' => $file_path,
			'filename' => $filename,
			'file_size' => filesize( $file_path ),
		] );

		try {
			// Debug: Log admin configuration
			$this->log( 'info', 'BuzzSprout: Admin configuration debug', [
				'auto_publish' => $this->credentials['auto_publish'] ?? 'NOT_SET',
				'make_private' => $this->credentials['make_private'] ?? 'NOT_SET',
				'include_link' => $this->credentials['include_link'] ?? 'NOT_SET',
				'default_tags' => $this->credentials['default_tags'] ?? 'NOT_SET',
				'all_credentials' => array_keys($this->credentials)
			] );
			
			// Debug: Log metadata received
			$this->log( 'info', 'BuzzSprout: Metadata debug', [
				'post_title' => $metadata['post_title'] ?? 'NOT_SET',
				'post_url' => $metadata['post_url'] ?? 'NOT_SET',
				'featured_image_url' => $metadata['featured_image_url'] ?? 'NOT_SET',
				'post_id' => $metadata['post_id'] ?? 'NOT_SET',
				'all_metadata_keys' => array_keys($metadata)
			] );
			
			// Prepare upload data using article metadata and admin configuration
			$upload_data = [
				'title' => $this->getEpisodeTitle( $metadata, $filename ),
				'description' => $this->getEpisodeDescription( $metadata ),
				'summary' => $metadata['summary'] ?? '',
				'artist' => $metadata['artist'] ?? $this->getSiteName(),
				'tags' => $metadata['tags'] ?? $this->credentials['default_tags'] ?? 'tts,generated',
				'published' => $this->credentials['auto_publish'] ?? false,
				'private' => $this->credentials['make_private'] ?? false,
			];
			
			// Add episode artwork if available
			$artwork_url = $this->getEpisodeArtwork( $metadata );
			$this->log( 'info', 'BuzzSprout: Artwork URL debug', [
				'artwork_url' => $artwork_url,
				'will_add_to_upload' => !empty($artwork_url)
			] );
			if ( $artwork_url ) {
				$upload_data['artwork_url'] = $artwork_url;
			}
			
			// Debug: Log final upload data
			$this->log( 'info', 'BuzzSprout: Final upload data', $upload_data );

			// Upload file to Buzzsprout
			$response = $this->uploadToBuzzsprout( $file_path, $filename, $upload_data );

			if ( ! $response || ! isset( $response['audio_url'] ) ) {
				throw new StorageException( 'Invalid response from Buzzsprout API' );
			}

			$this->log( 'info', 'Buzzsprout file upload completed', [
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
					'uploaded_at' => gmdate( 'Y-m-d H:i:s' ),
				],
			];

		} catch ( \Exception $e ) {
			$this->log( 'error', 'Buzzsprout file upload failed', [
				'error' => esc_html( $e->getMessage() ),
				'file_path' => $file_path,
			] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new StorageException( 'Buzzsprout upload failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Create temporary file safely
	 *
	 * @param string $filename Base filename for temp file
	 * @return string Temporary file path
	 * @throws \Exception If temp file creation fails
	 */
	private function createTempFile( string $filename ): string {
		// Try WordPress wp_tempnam first
		if ( function_exists( 'wp_tempnam' ) ) {
			$temp_file = wp_tempnam( $filename );
			if ( $temp_file ) {
				return $temp_file;
			}
		}

		// Fallback to system temp directory
		$temp_dir = sys_get_temp_dir();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Need to check temp directory
		if ( ! $temp_dir || ! is_writable( $temp_dir ) ) {
			// Try WordPress uploads temp if system temp not available
			$upload_dir = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : null;
			if ( $upload_dir && ! empty( $upload_dir['basedir'] ) ) {
				$temp_dir = $upload_dir['basedir'] . '/tmp';
				if ( ! file_exists( $temp_dir ) ) {
					if ( function_exists( 'wp_mkdir_p' ) ) {
						wp_mkdir_p( $temp_dir );
					} else {
						// Fallback to native PHP
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Fallback when wp_mkdir_p not available
						if ( ! mkdir( $temp_dir, 0755, true ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for debugging
							throw new \Exception( 'Cannot create temporary directory: ' . esc_html( $temp_dir ) );
						}
					}
				}
			} else {
				throw new \Exception( 'No writable temporary directory available for Buzzsprout upload' );
			}
		}

		// Generate unique temp filename
		$safe_filename = function_exists( 'sanitize_file_name' ) ? sanitize_file_name( $filename ) : $this->sanitizeFilename( $filename );
		$temp_file = $temp_dir . '/' . uniqid( 'buzzsprout_', true ) . '_' . $safe_filename;
		
		// Test if we can write to the temp file
		if ( file_put_contents( $temp_file, '' ) === false ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( 'Cannot create temporary file for Buzzsprout upload: ' . $temp_file );
		}

		return $temp_file;
	}

	/**
	 * Sanitize filename for cross-platform safety
	 *
	 * @param string $filename Original filename
	 * @return string Sanitized filename
	 */
	private function sanitizeFilename( string $filename ): string {
		// Remove or replace problematic characters
		$filename = preg_replace( '/[^a-zA-Z0-9._-]/', '_', $filename );
		// Remove multiple underscores
		$filename = preg_replace( '/_+/', '_', $filename );
		// Trim underscores from start/end
		$filename = trim( $filename, '_' );
		// Ensure it's not empty
		return empty( $filename ) ? 'file' : $filename;
	}

	/**
	 * Get site name safely
	 *
	 * @return string Site name
	 */
	private function getSiteName(): string {
		if ( function_exists( 'get_bloginfo' ) ) {
			return get_bloginfo( 'name' ) ?: 'TTS Site';
		}
		return 'TTS Site';
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

		$this->log( 'info', 'Starting Buzzsprout file deletion', [
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

			$this->log( 'info', 'Buzzsprout file deletion completed', [
				'episode_id' => $episode_id,
			] );

			return $response;

		} catch ( \Exception $e ) {
			$this->log( 'error', 'Buzzsprout file deletion failed', [
				'error' => esc_html( $e->getMessage() ),
				'file_url' => $file_url,
			] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
	public function getFileInfo( string $file_url ): ?array {
		if ( ! $this->isConfigured() ) {
			return null;
		}

		try {
			$episode_id = $this->extractEpisodeId( $file_url );

			if ( ! $episode_id ) {
				return null;
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
			return null;
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
			$this->log( 'error', 'Failed to list Buzzsprout files', [
				'error' => esc_html( $e->getMessage() ),
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
				'error' => esc_html( $e->getMessage() ),
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
		$this->log( 'info', 'BuzzSprout: Starting real API upload', [
			'file_path' => $file_path,
			'filename' => $filename,
			'podcast_id' => $this->credentials['podcast_id']
		] );

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close -- cURL required for multipart file upload with CURLFile
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->api_base_url . '/' . $this->credentials['podcast_id'] . '/episodes.json' );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [
			'Authorization: Token token=' . $this->credentials['api_token']
		] );
		// Prepare POST fields
		$post_fields = [
			'title' => $upload_data['title'],
			'description' => $upload_data['description'],
			'published' => $upload_data['published'] ? 'true' : 'false',
			'private' => $upload_data['private'] ? 'true' : 'false',
			'audio_file' => new \CURLFile( $file_path, 'audio/mpeg', $filename )
		];

		// Add artwork if provided
		if ( ! empty( $upload_data['artwork_url'] ) ) {
			$post_fields['artwork_url'] = $upload_data['artwork_url'];
		}

		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 120 ); // 2 minutes timeout for upload
		curl_setopt( $ch, CURLOPT_VERBOSE, false );

		$response = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $ch );
		$upload_info = curl_getinfo( $ch );
		curl_close( $ch );
		// phpcs:enable

		$this->log( 'info', 'BuzzSprout: API response received', [
			'http_code' => $http_code,
			'upload_time' => round( $upload_info['total_time'], 2 ),
			'response_length' => strlen( $response )
		] );

		if ( ! empty( $curl_error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new StorageException( 'BuzzSprout cURL error: ' . $curl_error );
		}

		if ( $http_code === 201 ) {
			$episode_data = json_decode( $response, true );
			if ( $episode_data && isset( $episode_data['id'] ) ) {
				$this->log( 'info', 'BuzzSprout: Upload successful', [
					'episode_id' => $episode_data['id'],
					'audio_url' => $episode_data['audio_url'] ?? 'N/A'
				] );
				return $episode_data;
			} else {
				throw new StorageException( 'BuzzSprout: Invalid JSON response' );
			}
		} elseif ( $http_code === 401 ) {
			throw new StorageException( 'BuzzSprout: Invalid API token or unauthorized' );
		} elseif ( $http_code === 422 ) {
			$error_data = json_decode( $response, true );
			$error_msg = 'BuzzSprout: Validation error';
			if ( $error_data ) {
				$error_msg .= ' - ' . json_encode( $error_data );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new StorageException( $error_msg );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new StorageException( "BuzzSprout: HTTP error $http_code - $response" );
		}
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
			'published_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
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

	/**
	 * Delete audio file
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return bool Success status
	 */
	public function delete( string $identifier ): bool {
		// For now, return true (mock implementation)
		// In production, this would call Buzzsprout API to delete episode
		$this->log( 'info', 'Mock delete operation for Buzzsprout', [
			'identifier' => $identifier
		] );
		return true;
	}

	/**
	 * Check if file exists
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return bool
	 */
	public function exists( string $identifier ): bool {
		// For now, return false (mock implementation)
		// In production, this would check if episode exists in Buzzsprout
		return false;
	}


	/**
	 * Get provider configuration
	 *
	 * @return array
	 */
	public function getConfig(): array {
		return $this->credentials;
	}

	/**
	 * Test provider connection/configuration
	 *
	 * @return bool
	 */
	public function test(): bool {
		return $this->isConfigured();
	}

	/**
	 * Get episode title from article metadata
	 *
	 * @param array $metadata Article metadata
	 * @param string $filename Fallback filename
	 * @return string Episode title
	 */
	private function getEpisodeTitle( array $metadata, string $filename ): string {
		// Use article title if available
		if ( ! empty( $metadata['post_title'] ) ) {
			return $metadata['post_title'];
		}
		
		// Use custom title if provided
		if ( ! empty( $metadata['title'] ) ) {
			return $metadata['title'];
		}
		
		// Fallback to filename without extension
		return pathinfo( $filename, PATHINFO_FILENAME );
	}
	
	/**
	 * Get episode description with article URL
	 *
	 * @param array $metadata Article metadata
	 * @return string Episode description
	 */
	private function getEpisodeDescription( array $metadata ): string {
		$description = '';
		
		// Add custom description if provided (excerpt, etc.)
		if ( ! empty( $metadata['description'] ) ) {
			$description = $metadata['description'];
		}
		
		// Add article URL if available and enabled in configuration
		$include_link = $this->credentials['include_link'] ?? true;
		if ( $include_link ) {
			if ( ! empty( $metadata['post_url'] ) ) {
				if ( !empty($description) ) {
					$description .= "\n\n";
				}
				$description .= "Lee el artículo completo en: " . $metadata['post_url'];
			} elseif ( ! empty( $metadata['permalink'] ) ) {
				if ( !empty($description) ) {
					$description .= "\n\n";
				}
				$description .= "Lee el artículo completo en: " . $metadata['permalink'];
			}
		}
		
		// Add site info
		$site_name = $this->getSiteName();
		if ( $site_name !== 'TTS Site' ) {
			if ( !empty($description) ) {
				$description .= "\n\n";
			}
			$description .= "📱 Publicado en: " . $site_name;
		}
		
		// If we still have no description, add a minimal one
		if ( empty($description) ) {
			$description = "Episodio de audio generado desde " . $this->getSiteName();
		}
		
		return $description;
	}
	
	/**
	 * Get episode artwork URL from featured image
	 *
	 * @param array $metadata Article metadata
	 * @return string|null Artwork URL or null if not available
	 */
	private function getEpisodeArtwork( array $metadata ): ?string {
		$this->log( 'info', 'BuzzSprout: getEpisodeArtwork() called', [
			'metadata_keys' => array_keys($metadata)
		] );
		
		// Try different metadata keys for featured image
		$image_keys = [
			'featured_image_url',
			'thumbnail_url', 
			'post_thumbnail',
			'featured_image',
			'image_url'
		];
		
		foreach ( $image_keys as $key ) {
			$this->log( 'info', "BuzzSprout: Checking metadata key '$key'", [
				'value' => $metadata[$key] ?? 'NOT_SET',
				'is_url' => isset($metadata[$key]) ? filter_var( $metadata[$key], FILTER_VALIDATE_URL ) : false
			] );
			
			if ( ! empty( $metadata[$key] ) && filter_var( $metadata[$key], FILTER_VALIDATE_URL ) ) {
				$this->log( 'info', 'BuzzSprout: Found artwork URL from metadata', [
					'key' => $key,
					'url' => $metadata[$key]
				] );
				return $metadata[$key];
			}
		}
		
		// If we have a post ID, try to get featured image directly
		if ( ! empty( $metadata['post_id'] ) && function_exists( 'get_the_post_thumbnail_url' ) ) {
			$this->log( 'info', 'BuzzSprout: Attempting to get featured image from post ID', [
				'post_id' => $metadata['post_id']
			] );
			
			$thumbnail_url = get_the_post_thumbnail_url( $metadata['post_id'], 'large' );
			if ( $thumbnail_url ) {
				$this->log( 'info', 'BuzzSprout: Found artwork URL from post ID', [
					'post_id' => $metadata['post_id'],
					'url' => $thumbnail_url
				] );
				return $thumbnail_url;
			} else {
				$this->log( 'info', 'BuzzSprout: No featured image found for post ID', [
					'post_id' => $metadata['post_id']
				] );
			}
		}
		
		$this->log( 'info', 'BuzzSprout: No artwork URL found', [
			'post_id' => $metadata['post_id'] ?? 'NOT_SET',
			'has_get_thumbnail_function' => function_exists( 'get_the_post_thumbnail_url' )
		] );
		
		return null;
	}
	
	/**
	 * Helper method for logging with fallback
	 *
	 * @param string $level Log level (info, error, debug)
	 * @param string $message Log message
	 * @param array $context Log context
	 */
	private function log( string $level, string $message, array $context = [] ): void {
		if ( $this->logger ) {
			$this->logger->{$level}( $message, $context );
		} else {
			$context_str = empty( $context ) ? '' : ' ' . json_encode( $context );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[BuzzSprout {$level}] {$message}{$context_str}" );
		}
	}
}

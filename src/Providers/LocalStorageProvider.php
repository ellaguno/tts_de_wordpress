<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\SimpleStorageProviderInterface;
use WP_TTS\Exceptions\ProviderException;

/**
 * Local Storage Provider
 *
 * Handles local file storage in WordPress uploads directory
 */
class LocalStorageProvider implements SimpleStorageProviderInterface {

	/**
	 * Provider configuration
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Upload directory info
	 *
	 * @var array
	 */
	private $upload_dir;

	/**
	 * Constructor
	 *
	 * @param array $config Provider configuration
	 */
	public function __construct( array $config ) {
		$this->config = array_merge( array(
			'enabled'       => true,
			'upload_path'   => 'wp-content/uploads/tts-audio/',
			'max_file_size' => 50, // MB
		), $config );

		$this->upload_dir = wp_upload_dir();
	}

	/**
	 * Store audio file
	 *
	 * @param string $audio_data Audio file data
	 * @param string $filename Filename
	 * @param array  $metadata Additional metadata
	 * @return array Storage result with URL and metadata
	 * @throws ProviderException If storage fails
	 */
	public function store( string $audio_data, string $filename, array $metadata = array() ): array {
		if ( ! $this->config['enabled'] ) {
			throw new ProviderException( 'Local storage provider is not enabled' );
		}

		// Check file size
		$file_size_mb = strlen( $audio_data ) / 1024 / 1024;
		if ( $file_size_mb > $this->config['max_file_size'] ) {
			throw new ProviderException( 
				sprintf( 
					'File size (%.2f MB) exceeds maximum allowed size (%d MB)', 
					$file_size_mb, 
					$this->config['max_file_size'] 
				) 
			);
		}

		// Prepare file paths
		$relative_path = $this->getRelativePath( $filename );
		$full_path = $this->upload_dir['basedir'] . '/' . $relative_path;
		$url = $this->upload_dir['baseurl'] . '/' . $relative_path;

		// Ensure directory exists
		$directory = dirname( $full_path );
		if ( ! wp_mkdir_p( $directory ) ) {
			throw new ProviderException( "Failed to create directory: {$directory}" );
		}

		// Save audio file
		if ( file_put_contents( $full_path, $audio_data ) === false ) {
			throw new ProviderException( "Failed to save audio file: {$full_path}" );
		}

		// Get file info
		$file_size = filesize( $full_path );
		$file_type = $this->getFileType( $filename );

		return array(
			'url'          => $url,
			'local_path'   => $full_path,
			'relative_path' => $relative_path,
			'file_size'    => $file_size,
			'file_type'    => $file_type,
			'provider'     => 'local',
			'stored_at'    => current_time( 'mysql' ),
			'metadata'     => $metadata,
		);
	}

	/**
	 * Delete audio file
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return bool Success status
	 */
	public function delete( string $identifier ): bool {
		$file_path = $this->getFilePathFromIdentifier( $identifier );

		if ( file_exists( $file_path ) ) {
			return unlink( $file_path );
		}

		return true; // File doesn't exist, consider it deleted
	}

	/**
	 * Check if file exists
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return bool
	 */
	public function exists( string $identifier ): bool {
		$file_path = $this->getFilePathFromIdentifier( $identifier );
		return file_exists( $file_path );
	}

	/**
	 * Get file information
	 *
	 * @param string $identifier File identifier (URL or path)
	 * @return array|null File information or null if not found
	 */
	public function getFileInfo( string $identifier ): ?array {
		$file_path = $this->getFilePathFromIdentifier( $identifier );

		if ( ! file_exists( $file_path ) ) {
			return null;
		}

		$file_size = filesize( $file_path );
		$file_type = $this->getFileType( $file_path );
		$modified_time = filemtime( $file_path );

		return array(
			'path'         => $file_path,
			'url'          => $this->getUrlFromPath( $file_path ),
			'size'         => $file_size,
			'type'         => $file_type,
			'modified'     => $modified_time,
			'provider'     => 'local',
		);
	}


	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'local';
	}

	/**
	 * Get provider configuration
	 *
	 * @return array
	 */
	public function getConfig(): array {
		return $this->config;
	}

	/**
	 * Test provider connection/configuration
	 *
	 * @return bool
	 */
	public function test(): bool {
		// Test write permissions
		$test_file = $this->upload_dir['basedir'] . '/tts-audio/.test';
		$test_content = 'test';

		// Ensure directory exists
		wp_mkdir_p( dirname( $test_file ) );

		// Try to write test file
		if ( file_put_contents( $test_file, $test_content ) === false ) {
			return false;
		}

		// Try to read test file
		if ( file_get_contents( $test_file ) !== $test_content ) {
			return false;
		}

		// Clean up test file
		unlink( $test_file );

		return true;
	}

	/**
	 * Get relative path for file
	 *
	 * @param string $filename Original filename
	 * @return string Relative path
	 */
	private function getRelativePath( string $filename ): string {
		$base_path = 'tts-audio';
		
		// Add date-based subdirectory
		$date_path = date( 'Y/m' );
		
		return $base_path . '/' . $date_path . '/' . $filename;
	}

	/**
	 * Get file path from identifier (URL or path)
	 *
	 * @param string $identifier File identifier
	 * @return string File path
	 */
	private function getFilePathFromIdentifier( string $identifier ): string {
		// If it's already a file path
		if ( file_exists( $identifier ) ) {
			return $identifier;
		}

		// If it's a URL, convert to file path
		if ( strpos( $identifier, $this->upload_dir['baseurl'] ) === 0 ) {
			$relative_path = str_replace( $this->upload_dir['baseurl'] . '/', '', $identifier );
			return $this->upload_dir['basedir'] . '/' . $relative_path;
		}

		// Assume it's a relative path
		return $this->upload_dir['basedir'] . '/' . ltrim( $identifier, '/' );
	}

	/**
	 * Get URL from file path
	 *
	 * @param string $file_path File path
	 * @return string URL
	 */
	private function getUrlFromPath( string $file_path ): string {
		$relative_path = str_replace( $this->upload_dir['basedir'] . '/', '', $file_path );
		return $this->upload_dir['baseurl'] . '/' . $relative_path;
	}

	/**
	 * Get file type from filename
	 *
	 * @param string $filename Filename
	 * @return string File type
	 */
	private function getFileType( string $filename ): string {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		
		switch ( $extension ) {
			case 'mp3':
				return 'audio/mpeg';
			case 'wav':
				return 'audio/wav';
			case 'ogg':
				return 'audio/ogg';
			case 'm4a':
				return 'audio/mp4';
			default:
				return 'application/octet-stream';
		}
	}
}
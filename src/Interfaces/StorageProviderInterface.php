<?php

namespace WP_TTS\Interfaces;

/**
 * Storage Provider Interface
 *
 * Defines the contract that all storage providers must implement.
 * This ensures consistent behavior across different storage services.
 */
interface StorageProviderInterface {

	/**
	 * Upload audio file to storage
	 *
	 * @param string $audioData Binary audio data
	 * @param string $filename Desired filename
	 * @param array  $metadata Additional metadata
	 * @return UploadResult Upload result with URL and metadata
	 * @throws \WP_TTS\Exceptions\StorageException If upload fails
	 */
	public function uploadAudio( string $audioData, string $filename, array $metadata = array()): UploadResult;

	/**
	 * Delete audio file from storage
	 *
	 * @param string $fileUrl File URL or identifier
	 * @return bool True if deletion was successful
	 * @throws \WP_TTS\Exceptions\StorageException If deletion fails
	 */
	public function deleteAudio( string $fileUrl): bool;

	/**
	 * Get public URL for audio file
	 *
	 * @param string $filename Filename or identifier
	 * @return string Public URL to access the file
	 */
	public function getPublicUrl( string $filename): string;

	/**
	 * Validate storage credentials
	 *
	 * @param array $credentials Optional credentials to validate
	 * @return bool True if credentials are valid
	 */
	public function validateCredentials( array $credentials = array()): bool;

	/**
	 * Get storage provider name
	 *
	 * @return string Provider name
	 */
	public function getName(): string;

	/**
	 * Get storage provider display name
	 *
	 * @return string Human-readable provider name
	 */
	public function getDisplayName(): string;

	/**
	 * Get provider-specific configuration schema
	 *
	 * @return array Configuration schema for admin interface
	 */
	public function getConfigSchema(): array;

	/**
	 * Get available storage space
	 *
	 * @return int|null Available space in bytes, null if unlimited or unknown
	 */
	public function getAvailableSpace(): ?int;

	/**
	 * Get used storage space
	 *
	 * @return int|null Used space in bytes, null if unknown
	 */
	public function getUsedSpace(): ?int;

	/**
	 * Check if file exists in storage
	 *
	 * @param string $filename Filename or identifier
	 * @return bool True if file exists
	 */
	public function fileExists( string $filename): bool;

	/**
	 * Get file metadata
	 *
	 * @param string $filename Filename or identifier
	 * @return array File metadata (size, modified date, etc.)
	 */
	public function getFileMetadata( string $filename): array;

	/**
	 * List files in storage
	 *
	 * @param string $prefix Optional prefix to filter files
	 * @param int    $limit Maximum number of files to return
	 * @return array Array of file information
	 */
	public function listFiles( string $prefix = '', int $limit = 100): array;

	/**
	 * Get supported file formats
	 *
	 * @return array Array of supported formats
	 */
	public function getSupportedFormats(): array;

	/**
	 * Get maximum file size allowed
	 *
	 * @return int Maximum file size in bytes
	 */
	public function getMaxFileSize(): int;

	/**
	 * Check provider health/availability
	 *
	 * @return bool True if provider is available
	 */
	public function isHealthy(): bool;

	/**
	 * Get cost per GB for storage
	 *
	 * @return float Cost per GB in USD
	 */
	public function getCostPerGB(): float;

	/**
	 * Get bandwidth cost per GB
	 *
	 * @return float Bandwidth cost per GB in USD
	 */
	public function getBandwidthCost(): float;

	/**
	 * Generate signed URL for temporary access
	 *
	 * @param string $filename Filename or identifier
	 * @param int    $expiration Expiration time in seconds
	 * @return string Signed URL
	 */
	public function generateSignedUrl( string $filename, int $expiration = 3600): string;

	/**
	 * Batch upload multiple files
	 *
	 * @param array $files Array of [filename => audioData] pairs
	 * @param array $metadata Common metadata for all files
	 * @return array Array of UploadResult objects
	 */
	public function batchUpload( array $files, array $metadata = array()): array;

	/**
	 * Get provider-specific error messages
	 *
	 * @param string $error_code Error code from provider
	 * @return string Human-readable error message
	 */
	public function getErrorMessage( string $error_code): string;
}

/**
 * Upload Result Class
 *
 * Represents the result of a file upload operation
 */
class UploadResult {

	/**
	 * Public URL of uploaded file
	 *
	 * @var string
	 */
	private $url;

	/**
	 * File identifier/key in storage
	 *
	 * @var string
	 */
	private $fileId;

	/**
	 * Original filename
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * File size in bytes
	 *
	 * @var int
	 */
	private $size;

	/**
	 * Storage provider name
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Upload timestamp
	 *
	 * @var \DateTime
	 */
	private $timestamp;

	/**
	 * Additional metadata
	 *
	 * @var array
	 */
	private $metadata;

	/**
	 * CDN URL (if available)
	 *
	 * @var string|null
	 */
	private $cdnUrl;

	/**
	 * Constructor
	 *
	 * @param string $url Public URL
	 * @param string $fileId File identifier
	 * @param string $filename Original filename
	 * @param int    $size File size in bytes
	 * @param string $provider Provider name
	 * @param array  $metadata Additional metadata
	 */
	public function __construct(
		string $url,
		string $fileId,
		string $filename,
		int $size,
		string $provider,
		array $metadata = array()
	) {
		$this->url       = $url;
		$this->fileId    = $fileId;
		$this->filename  = $filename;
		$this->size      = $size;
		$this->provider  = $provider;
		$this->metadata  = $metadata;
		$this->timestamp = new \DateTime();
		$this->cdnUrl    = $metadata['cdn_url'] ?? null;
	}

	/**
	 * Get public URL
	 *
	 * @return string Public URL
	 */
	public function getUrl(): string {
		return $this->url;
	}

	/**
	 * Get CDN URL if available, otherwise public URL
	 *
	 * @return string Best available URL
	 */
	public function getBestUrl(): string {
		return $this->cdnUrl ?: $this->url;
	}

	/**
	 * Get file identifier
	 *
	 * @return string File identifier
	 */
	public function getFileId(): string {
		return $this->fileId;
	}

	/**
	 * Get filename
	 *
	 * @return string Filename
	 */
	public function getFilename(): string {
		return $this->filename;
	}

	/**
	 * Get file size
	 *
	 * @return int File size in bytes
	 */
	public function getSize(): int {
		return $this->size;
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function getProvider(): string {
		return $this->provider;
	}

	/**
	 * Get upload timestamp
	 *
	 * @return \DateTime Upload timestamp
	 */
	public function getTimestamp(): \DateTime {
		return $this->timestamp;
	}

	/**
	 * Get metadata
	 *
	 * @param string|null $key Specific metadata key, or null for all
	 * @return mixed Metadata value or array
	 */
	public function getMetadata( string $key = null ) {
		if ( $key === null ) {
			return $this->metadata;
		}

		return $this->metadata[ $key ] ?? null;
	}

	/**
	 * Set metadata
	 *
	 * @param string $key Metadata key
	 * @param mixed  $value Metadata value
	 */
	public function setMetadata( string $key, $value ): void {
		$this->metadata[ $key ] = $value;
	}

	/**
	 * Get CDN URL
	 *
	 * @return string|null CDN URL if available
	 */
	public function getCdnUrl(): ?string {
		return $this->cdnUrl;
	}

	/**
	 * Set CDN URL
	 *
	 * @param string $cdnUrl CDN URL
	 */
	public function setCdnUrl( string $cdnUrl ): void {
		$this->cdnUrl = $cdnUrl;
	}

	/**
	 * Check if upload was successful
	 *
	 * @return bool True if successful
	 */
	public function isSuccessful(): bool {
		return ! empty( $this->url ) && ! empty( $this->fileId );
	}

	/**
	 * Get file extension
	 *
	 * @return string File extension
	 */
	public function getExtension(): string {
		return pathinfo( $this->filename, PATHINFO_EXTENSION );
	}

	/**
	 * Get MIME type
	 *
	 * @return string MIME type
	 */
	public function getMimeType(): string {
		$extension = $this->getExtension();

		$mimeTypes = array(
			'mp3'  => 'audio/mpeg',
			'wav'  => 'audio/wav',
			'ogg'  => 'audio/ogg',
			'aac'  => 'audio/aac',
			'flac' => 'audio/flac',
		);

		return $mimeTypes[ $extension ] ?? 'audio/mpeg';
	}

	/**
	 * Get human-readable file size
	 *
	 * @return string Formatted file size
	 */
	public function getFormattedSize(): string {
		$bytes = $this->size;
		$units = array( 'B', 'KB', 'MB', 'GB' );

		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Convert to array for serialization
	 *
	 * @return array Array representation
	 */
	public function toArray(): array {
		return array(
			'url'       => $this->url,
			'file_id'   => $this->fileId,
			'filename'  => $this->filename,
			'size'      => $this->size,
			'provider'  => $this->provider,
			'timestamp' => $this->timestamp->format( 'Y-m-d H:i:s' ),
			'metadata'  => $this->metadata,
			'cdn_url'   => $this->cdnUrl,
		);
	}

	/**
	 * Create from array
	 *
	 * @param array $data Array data
	 * @return UploadResult
	 */
	public static function fromArray( array $data ): UploadResult {
		$result = new self(
			$data['url'],
			$data['file_id'],
			$data['filename'],
			$data['size'],
			$data['provider'],
			$data['metadata'] ?? array()
		);

		if ( isset( $data['timestamp'] ) ) {
			$result->timestamp = new \DateTime( $data['timestamp'] );
		}

		if ( isset( $data['cdn_url'] ) ) {
			$result->cdnUrl = $data['cdn_url'];
		}

		return $result;
	}

	/**
	 * Create error result
	 *
	 * @param string $error Error message
	 * @param string $provider Provider name
	 * @return UploadResult
	 */
	public static function createError( string $error, string $provider ): UploadResult {
		return new self( '', '', '', 0, $provider, array( 'error' => $error ) );
	}

	/**
	 * Check if this is an error result
	 *
	 * @return bool True if this represents an error
	 */
	public function isError(): bool {
		return ! empty( $this->metadata['error'] );
	}

	/**
	 * Get error message
	 *
	 * @return string|null Error message if this is an error result
	 */
	public function getError(): ?string {
		return $this->metadata['error'] ?? null;
	}
}

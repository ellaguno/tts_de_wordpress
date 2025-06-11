<?php

namespace WP_TTS\Interfaces;

/**
 * TTS Provider Interface
 *
 * Defines the contract that all TTS providers must implement.
 * This ensures consistent behavior across different TTS services.
 */
interface TTSProviderInterface {

	/**
	 * Convert text to audio
	 *
	 * @param string $text Text to convert to speech
	 * @param array  $options Voice and synthesis options
	 * @return AudioResult Audio generation result
	 * @throws \WP_TTS\Exceptions\TTSException If synthesis fails
	 */
	public function synthesize( string $text, array $options = array()): AudioResult;

	/**
	 * Get available voices for this provider
	 *
	 * @param string $language Language code (e.g., 'es-MX', 'es-ES')
	 * @return array Array of available voices
	 * @throws \WP_TTS\Exceptions\ProviderException If unable to fetch voices
	 */
	public function getAvailableVoices( string $language = 'es-MX'): array;

	/**
	 * Validate API credentials
	 *
	 * @param array $credentials Optional credentials to validate (if not using stored ones)
	 * @return bool True if credentials are valid
	 */
	public function validateCredentials( array $credentials = array()): bool;

	/**
	 * Get remaining quota for this provider
	 *
	 * @return int|null Remaining characters/requests, null if unlimited or unknown
	 */
	public function getRemainingQuota(): ?int;

	/**
	 * Get provider-specific configuration schema
	 *
	 * @return array Configuration schema for admin interface
	 */
	public function getConfigSchema(): array;

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function getName(): string;

	/**
	 * Get provider display name
	 *
	 * @return string Human-readable provider name
	 */
	public function getDisplayName(): string;

	/**
	 * Check if provider supports SSML
	 *
	 * @return bool True if SSML is supported
	 */
	public function supportsSSML(): bool;

	/**
	 * Get supported audio formats
	 *
	 * @return array Array of supported formats (e.g., ['mp3', 'wav', 'ogg'])
	 */
	public function getSupportedFormats(): array;

	/**
	 * Get cost per character for this provider
	 *
	 * @return float Cost per character in USD
	 */
	public function getCostPerCharacter(): float;

	/**
	 * Preview voice with sample text
	 *
	 * @param string $voice_id Voice identifier
	 * @param string $sample_text Sample text to synthesize
	 * @param array  $options Additional options
	 * @return AudioResult Preview audio result
	 */
	public function previewVoice( string $voice_id, string $sample_text = '', array $options = array()): AudioResult;

	/**
	 * Get voice details
	 *
	 * @param string $voice_id Voice identifier
	 * @return array Voice details (name, gender, language, etc.)
	 */
	public function getVoiceDetails( string $voice_id): array;

	/**
	 * Check provider health/availability
	 *
	 * @return bool True if provider is available
	 */
	public function isHealthy(): bool;

	/**
	 * Get provider-specific error messages
	 *
	 * @param string $error_code Error code from provider
	 * @return string Human-readable error message
	 */
	public function getErrorMessage( string $error_code): string;
}

/**
 * Audio Result Class
 *
 * Represents the result of an audio synthesis operation
 */
class AudioResult {

	/**
	 * Audio data (binary)
	 *
	 * @var string
	 */
	private $audioData;

	/**
	 * Audio format
	 *
	 * @var string
	 */
	private $format;

	/**
	 * Audio duration in seconds
	 *
	 * @var float
	 */
	private $duration;

	/**
	 * Audio file size in bytes
	 *
	 * @var int
	 */
	private $size;

	/**
	 * Provider that generated this audio
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Voice used for synthesis
	 *
	 * @var string
	 */
	private $voice;

	/**
	 * Characters processed
	 *
	 * @var int
	 */
	private $characterCount;

	/**
	 * Generation timestamp
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
	 * Constructor
	 *
	 * @param string $audioData Binary audio data
	 * @param string $format Audio format
	 * @param float  $duration Duration in seconds
	 * @param array  $metadata Additional metadata
	 */
	public function __construct( string $audioData, string $format, float $duration = 0, array $metadata = array() ) {
		$this->audioData = $audioData;
		$this->format    = $format;
		$this->duration  = $duration;
		$this->size      = strlen( $audioData );
		$this->timestamp = new \DateTime();
		$this->metadata  = $metadata;

		// Extract common metadata
		$this->provider       = $metadata['provider'] ?? '';
		$this->voice          = $metadata['voice'] ?? '';
		$this->characterCount = $metadata['character_count'] ?? 0;
	}

	/**
	 * Get audio data
	 *
	 * @return string Binary audio data
	 */
	public function getAudioData(): string {
		return $this->audioData;
	}

	/**
	 * Get audio format
	 *
	 * @return string Audio format
	 */
	public function getFormat(): string {
		return $this->format;
	}

	/**
	 * Get audio duration
	 *
	 * @return float Duration in seconds
	 */
	public function getDuration(): float {
		return $this->duration;
	}

	/**
	 * Get audio file size
	 *
	 * @return int Size in bytes
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
	 * Get voice used
	 *
	 * @return string Voice identifier
	 */
	public function getVoice(): string {
		return $this->voice;
	}

	/**
	 * Get character count
	 *
	 * @return int Number of characters processed
	 */
	public function getCharacterCount(): int {
		return $this->characterCount;
	}

	/**
	 * Get generation timestamp
	 *
	 * @return \DateTime Generation timestamp
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
	 * Get MIME type for the audio format
	 *
	 * @return string MIME type
	 */
	public function getMimeType(): string {
		$mimeTypes = array(
			'mp3'  => 'audio/mpeg',
			'wav'  => 'audio/wav',
			'ogg'  => 'audio/ogg',
			'aac'  => 'audio/aac',
			'flac' => 'audio/flac',
		);

		return $mimeTypes[ $this->format ] ?? 'audio/mpeg';
	}

	/**
	 * Save audio to file
	 *
	 * @param string $filepath File path to save to
	 * @return bool Success status
	 */
	public function saveToFile( string $filepath ): bool {
		$directory = dirname( $filepath );
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		return file_put_contents( $filepath, $this->audioData ) !== false;
	}

	/**
	 * Get audio data as base64
	 *
	 * @return string Base64 encoded audio data
	 */
	public function getBase64(): string {
		return base64_encode( $this->audioData );
	}

	/**
	 * Get data URL for embedding
	 *
	 * @return string Data URL
	 */
	public function getDataUrl(): string {
		return 'data:' . $this->getMimeType() . ';base64,' . $this->getBase64();
	}

	/**
	 * Convert to array for serialization
	 *
	 * @return array Array representation
	 */
	public function toArray(): array {
		return array(
			'format'          => $this->format,
			'duration'        => $this->duration,
			'size'            => $this->size,
			'provider'        => $this->provider,
			'voice'           => $this->voice,
			'character_count' => $this->characterCount,
			'timestamp'       => $this->timestamp->format( 'Y-m-d H:i:s' ),
			'metadata'        => $this->metadata,
		);
	}

	/**
	 * Create from array
	 *
	 * @param array  $data Array data
	 * @param string $audioData Binary audio data
	 * @return AudioResult
	 */
	public static function fromArray( array $data, string $audioData ): AudioResult {
		$result                 = new self( $audioData, $data['format'], $data['duration'], $data['metadata'] );
		$result->provider       = $data['provider'];
		$result->voice          = $data['voice'];
		$result->characterCount = $data['character_count'];

		if ( isset( $data['timestamp'] ) ) {
			$result->timestamp = new \DateTime( $data['timestamp'] );
		}

		return $result;
	}
}

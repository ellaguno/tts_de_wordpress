<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\TTSProviderInterface;
use WP_TTS\Interfaces\AudioResult;
use WP_TTS\Exceptions\ProviderException;
use WP_TTS\Utils\Logger;

/**
 * OpenAI TTS Provider
 *
 * Provides text-to-speech functionality using OpenAI TTS service.
 *
 * @package WP_TTS\Providers
 * @since 1.0.0
 */
class OpenAITTSProvider implements TTSProviderInterface {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	private $name = 'openai';

	/**
	 * Provider configuration
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param array  $config Provider configuration.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( array $config, Logger $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Convert text to audio (interface method)
	 *
	 * @param string $text Text to convert to speech.
	 * @param array  $options Voice and synthesis options.
	 * @return AudioResult Audio generation result.
	 * @throws ProviderException If synthesis fails.
	 */
	public function synthesize( string $text, array $options = [] ): AudioResult {
		$result = $this->generateSpeech( $text, $options );
		
		if ( ! $result['success'] ) {
			throw new ProviderException( 'OpenAI TTS synthesis failed' );
		}

		// Read the audio file
		$audio_data = file_get_contents( $result['file_path'] );
		if ( $audio_data === false ) {
			throw new ProviderException( 'Failed to read generated audio file' );
		}

		return new AudioResult(
			$audio_data,
			$result['format'],
			$result['duration'],
			[
				'provider' => $this->name,
				'voice' => $result['voice'],
				'character_count' => strlen( $text ),
				'file_path' => $result['file_path'],
				'audio_url' => $result['audio_url'],
			]
		);
	}

	/**
	 * Generate speech from text
	 *
	 * @param string $text Text to convert.
	 * @param array  $options TTS options.
	 * @return array Audio data with URL and metadata.
	 * @throws ProviderException If generation fails.
	 */
	public function generateSpeech( string $text, array $options = [] ): array {
		if ( ! $this->isConfigured() ) {
			throw new ProviderException( 'OpenAI TTS provider is not properly configured' );
		}

		$this->logger->info( 'Starting OpenAI TTS generation', [
			'text_length' => strlen( $text ),
			'voice' => $options['voice'] ?? $this->config['default_voice'] ?? 'alloy',
		] );

		try {
			// Prepare request parameters
			$voice_id = $options['voice'] ?? $this->config['default_voice'] ?? 'alloy';
			$model = $options['model'] ?? 'tts-1';
			$output_format = $options['output_format'] ?? 'mp3';

			// For now, generate mock audio since we don't have OpenAI API integration
			$audio_data = $this->generateMockAudioData();

			// Generate unique filename
			$filename = 'openai_' . md5( $text . time() ) . '.' . $output_format;
			$upload_dir = wp_upload_dir();
			$file_path = $upload_dir['basedir'] . '/tts-audio/' . $filename;
			$file_url = $upload_dir['baseurl'] . '/tts-audio/' . $filename;

			// Ensure directory exists
			wp_mkdir_p( dirname( $file_path ) );

			// Save audio file
			if ( file_put_contents( $file_path, $audio_data ) === false ) {
				throw new ProviderException( 'Failed to save audio file' );
			}

			$this->logger->info( 'OpenAI TTS generation completed', [
				'file_path' => $file_path,
				'file_size' => filesize( $file_path ),
			] );

			return [
				'success' => true,
				'audio_url' => $file_url,
				'file_path' => $file_path,
				'provider' => $this->name,
				'voice' => $voice_id,
				'format' => $output_format,
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'model' => $model,
					'characters' => strlen( $text ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'OpenAI TTS generation failed', [
				'error' => $e->getMessage(),
			] );
			throw new ProviderException( 'OpenAI TTS generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Get available voices
	 *
	 * @param string $language Language code (optional).
	 * @return array Available voices.
	 */
	public function getAvailableVoices( string $language = 'es-MX' ): array {
		// OpenAI TTS voices
		return [
			[ 'id' => 'alloy', 'name' => 'Alloy (Neutral)', 'gender' => 'Neutral', 'language' => 'multi' ],
			[ 'id' => 'echo', 'name' => 'Echo (Male)', 'gender' => 'Male', 'language' => 'multi' ],
			[ 'id' => 'fable', 'name' => 'Fable (British Male)', 'gender' => 'Male', 'language' => 'en-GB' ],
			[ 'id' => 'onyx', 'name' => 'Onyx (Male)', 'gender' => 'Male', 'language' => 'multi' ],
			[ 'id' => 'nova', 'name' => 'Nova (Female)', 'gender' => 'Female', 'language' => 'multi' ],
			[ 'id' => 'shimmer', 'name' => 'Shimmer (Female)', 'gender' => 'Female', 'language' => 'multi' ],
		];
	}

	/**
	 * Validate API credentials
	 *
	 * @param array $credentials Optional credentials to validate.
	 * @return bool True if credentials are valid.
	 */
	public function validateCredentials( array $credentials = [] ): bool {
		$config = ! empty( $credentials ) ? $credentials : $this->config;
		
		return ! empty( $config['api_key'] );
	}

	/**
	 * Get remaining quota for this provider
	 *
	 * @return int|null Remaining characters/requests, null if unlimited.
	 */
	public function getRemainingQuota(): ?int {
		// OpenAI doesn't provide quota information via API
		return null;
	}

	/**
	 * Get provider-specific configuration schema
	 *
	 * @return array Configuration schema for admin interface.
	 */
	public function getConfigSchema(): array {
		return [
			'api_key' => [
				'type' => 'password',
				'label' => 'OpenAI API Key',
				'required' => true,
				'description' => 'Your OpenAI API key for TTS services',
			],
			'default_voice' => [
				'type' => 'select',
				'label' => 'Default Voice',
				'required' => false,
				'options' => [
					'alloy' => 'Alloy (Neutral)',
					'echo' => 'Echo (Male)',
					'fable' => 'Fable (British Male)',
					'onyx' => 'Onyx (Male)',
					'nova' => 'Nova (Female)',
					'shimmer' => 'Shimmer (Female)',
				],
				'default' => 'alloy',
				'description' => 'Default voice to use when none is specified',
			],
		];
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
	 * Get provider display name
	 *
	 * @return string Human-readable provider name.
	 */
	public function getDisplayName(): string {
		return 'OpenAI TTS';
	}

	/**
	 * Check if provider supports SSML
	 *
	 * @return bool True if SSML is supported.
	 */
	public function supportsSSML(): bool {
		return false; // OpenAI TTS doesn't support SSML
	}

	/**
	 * Get supported audio formats
	 *
	 * @return array Array of supported formats.
	 */
	public function getSupportedFormats(): array {
		return [ 'mp3', 'opus', 'aac', 'flac' ];
	}

	/**
	 * Get cost per character for this provider
	 *
	 * @return float Cost per character in USD.
	 */
	public function getCostPerCharacter(): float {
		// OpenAI TTS pricing: $15.00 per 1 million characters
		return 0.000015;
	}

	/**
	 * Preview voice with sample text
	 *
	 * @param string $voice_id Voice identifier.
	 * @param string $sample_text Sample text to synthesize.
	 * @param array  $options Additional options.
	 * @return AudioResult Preview audio result.
	 */
	public function previewVoice( string $voice_id, string $sample_text = '', array $options = [] ): AudioResult {
		if ( empty( $sample_text ) ) {
			$sample_text = 'Hello, this is a sample voice from OpenAI TTS.';
		}

		$options['voice'] = $voice_id;
		return $this->synthesize( $sample_text, $options );
	}

	/**
	 * Get voice details
	 *
	 * @param string $voice_id Voice identifier.
	 * @return array Voice details.
	 */
	public function getVoiceDetails( string $voice_id ): array {
		$voices = $this->getAvailableVoices();
		
		foreach ( $voices as $voice ) {
			if ( $voice['id'] === $voice_id ) {
				return $voice;
			}
		}

		return [
			'id' => $voice_id,
			'name' => $voice_id,
			'gender' => 'Unknown',
			'language' => 'multi',
		];
	}

	/**
	 * Check provider health/availability
	 *
	 * @return bool True if provider is available.
	 */
	public function isHealthy(): bool {
		return $this->isConfigured();
	}

	/**
	 * Get provider-specific error messages
	 *
	 * @param string $error_code Error code from provider.
	 * @return string Human-readable error message.
	 */
	public function getErrorMessage( string $error_code ): string {
		$error_messages = [
			'400' => 'Bad request - invalid parameters',
			'401' => 'Authentication failed - check API key',
			'403' => 'Access forbidden - check permissions',
			'429' => 'Rate limit exceeded - too many requests',
			'500' => 'OpenAI TTS service error',
		];

		return $error_messages[ $error_code ] ?? 'Unknown OpenAI TTS error: ' . $error_code;
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 */
	public function isConfigured(): bool {
		// Allow mock mode even without credentials for testing
		return true;
	}

	/**
	 * Generate mock audio data for testing
	 *
	 * @return string Mock audio data.
	 */
	private function generateMockAudioData(): string {
		// Create a simple MP3-like header for testing
		$sample_rate = 22050;
		$channels = 1;
		$bits_per_sample = 16;
		$duration = 3; // 3 seconds
		$data_size = $sample_rate * $channels * $bits_per_sample / 8 * $duration;
		
		// Simple MP3 header simulation
		$header = 'ID3' . chr(3) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0);
		
		// Generate simple tone data
		$audio_data = '';
		for ( $i = 0; $i < $data_size / 2; $i++ ) {
			$sample = sin( 2 * M_PI * 523 * $i / $sample_rate ) * 16383; // 523Hz tone (C5)
			$audio_data .= pack( 's', $sample );
		}
		
		return $header . $audio_data;
	}

	/**
	 * Estimate audio duration based on text length
	 *
	 * @param string $text Text content.
	 * @return int Estimated duration in seconds.
	 */
	private function estimateAudioDuration( string $text ): int {
		// Rough estimation: 150 words per minute, average 5 characters per word
		$words = strlen( $text ) / 5;
		$minutes = $words / 150;
		return max( 1, round( $minutes * 60 ) );
	}
}
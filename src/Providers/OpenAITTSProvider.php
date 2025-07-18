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
			$this->logger->error( 'OpenAI TTS provider is not configured. API key is missing.' );
			throw new ProviderException( 'OpenAI TTS provider is not properly configured (API key missing)' );
		}
		
		$api_key = $this->config['api_key'];
		$voice_id = (!empty($options['voice'])) ? $options['voice'] : ($this->config['default_voice'] ?? 'alloy');
		
		// Validate OpenAI voice ID - only accept valid OpenAI voices
		$valid_voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer', 'ash', 'sage', 'coral'];
		if (!in_array($voice_id, $valid_voices)) {
			$this->logger->warning('Invalid OpenAI voice ID provided, using default', [
				'provided_voice' => $voice_id,
				'valid_voices' => $valid_voices,
				'using_default' => 'alloy'
			]);
			$voice_id = $this->config['default_voice'] ?? 'alloy';
		}
		
		$model = $options['model'] ?? $this->config['default_model'] ?? 'tts-1'; // Allow model override from config
		$output_format = $options['output_format'] ?? 'mp3'; // OpenAI supports mp3, opus, aac, flac
		
		// Handle OpenAI's 4096 character limit
		$max_chars = 4000; // Leave some margin
		if ( strlen( $text ) > $max_chars ) {
			$this->logger->warning( 'Text too long for OpenAI TTS, truncating', [
				'original_length' => strlen( $text ),
				'truncated_to' => $max_chars
			] );
			// Truncate at word boundary to avoid cutting words
			$text = substr( $text, 0, $max_chars );
			$last_space = strrpos( $text, ' ' );
			if ( $last_space !== false ) {
				$text = substr( $text, 0, $last_space );
			}
			$text .= '...'; // Indicate truncation
		}

		$this->logger->info( 'Starting OpenAI TTS generation', [
			'text_length' => strlen( $text ),
			'voice' => $voice_id,
			'model' => $model,
			'format' => $output_format,
		] );
		
		try {
			$api_url = 'https://api.openai.com/v1/audio/speech';
			$request_body = json_encode( [
				'model' => $model,
				'input' => $text,
				'voice' => $voice_id,
				'response_format' => $output_format,
			] );

			$this->logger->debug( 'OpenAI API Request', [ 'url' => $api_url, 'body_preview' => substr($request_body, 0, 100) . '...' ] );

			$response = wp_remote_post( $api_url, [
				'method'    => 'POST',
				'headers'   => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'      => $request_body,
				'timeout'   => 60, // Increased timeout for potentially long audio generation
			] );

			if ( is_wp_error( $response ) ) {
				$this->logger->error( 'OpenAI API request failed (wp_error)', [ 'error_message' => $response->get_error_message() ] );
				throw new ProviderException( 'OpenAI API request failed: ' . $response->get_error_message() );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( $response_code !== 200 ) {
				$error_details = json_decode( $response_body, true );
				$error_message = $error_details['error']['message'] ?? $response_body;
				$this->logger->error( 'OpenAI API returned an error', [
					'response_code' => $response_code,
					'error_message' => $error_message,
					'response_body' => $response_body,
				] );
				throw new ProviderException( "OpenAI API error ({$response_code}): {$error_message}" );
			}
			
			// At this point, $response_body contains the audio data
			$audio_data = $response_body;

			$this->logger->info( 'OpenAI TTS generation completed', [
				'audio_data_size' => strlen( $audio_data ),
				'voice_id' => $voice_id
			] );

			// Return raw audio data instead of saving to file
			// The TTSService will handle storage using the configured storage provider
			return [
				'success' => true,
				'audio_data' => $audio_data,
				'provider' => $this->name,
				'voice' => $voice_id,
				'format' => $output_format,
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'model' => $model,
					'characters' => strlen( $text ),
					'data_size' => strlen( $audio_data ),
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
		// OpenAI TTS voices (updated with new voices)
		return [
			[ 'id' => 'alloy', 'name' => 'Alloy (Neutral)', 'gender' => 'Neutral', 'language' => 'multi' ],
			[ 'id' => 'echo', 'name' => 'Echo (Male)', 'gender' => 'Male', 'language' => 'multi' ],
			[ 'id' => 'fable', 'name' => 'Fable (British Male)', 'gender' => 'Male', 'language' => 'en-GB' ],
			[ 'id' => 'onyx', 'name' => 'Onyx (Male)', 'gender' => 'Male', 'language' => 'multi' ],
			[ 'id' => 'nova', 'name' => 'Nova (Female)', 'gender' => 'Female', 'language' => 'multi' ],
			[ 'id' => 'shimmer', 'name' => 'Shimmer (Female)', 'gender' => 'Female', 'language' => 'multi' ],
			[ 'id' => 'ash', 'name' => 'Ash (Neutral)', 'gender' => 'Neutral', 'language' => 'multi' ],
			[ 'id' => 'sage', 'name' => 'Sage (Neutral)', 'gender' => 'Neutral', 'language' => 'multi' ],
			[ 'id' => 'coral', 'name' => 'Coral (Female)', 'gender' => 'Female', 'language' => 'multi' ],
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
				'required' => true, // A default voice should be selected
				'options' => [
					'alloy' => 'Alloy (Neutral)',
					'echo' => 'Echo (Male)',
					'fable' => 'Fable (British Male)',
					'onyx' => 'Onyx (Male)',
					'nova' => 'Nova (Female)',
					'shimmer' => 'Shimmer (Female)',
					'ash' => 'Ash (Neutral)',
					'sage' => 'Sage (Neutral)',
					'coral' => 'Coral (Female)',
				],
				'default' => 'alloy',
				'description' => 'Default voice to use when none is specified for a post.',
			],
			'default_model' => [
				'type' => 'select',
				'label' => 'Default Model',
				'required' => true,
				'options' => [
					'tts-1' => 'tts-1 (Optimized for real-time, lower latency)',
					'tts-1-hd' => 'tts-1-hd (Optimized for quality)',
				],
				'default' => 'tts-1',
				'description' => 'Default model to use for synthesis.',
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
		return ! empty( $this->config['api_key'] );
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
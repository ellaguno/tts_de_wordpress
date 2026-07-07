<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\TTSProviderInterface;
use WP_TTS\Interfaces\AudioResult;
use WP_TTS\Exceptions\ProviderException;
use WP_TTS\Utils\Logger;
use WP_TTS\Utils\TextChunker;

/**
 * ElevenLabs TTS Provider
 *
 * Provides text-to-speech functionality using ElevenLabs service.
 *
 * @package WP_TTS\Providers
 * @since 1.0.0
 */
class ElevenLabsProvider implements TTSProviderInterface {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	private $name = 'elevenlabs';

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
		
		if ( ! $result['success'] || empty( $result['audio_data'] ) ) {
			throw new ProviderException( 'ElevenLabs TTS synthesis failed' );
		}

		return new AudioResult(
			$result['audio_data'],
			$result['format'],
			$result['duration'],
			[
				'provider' => $this->name,
				'voice' => $result['voice'],
				'character_count' => strlen( $text ),
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
			$this->logger->error( 'ElevenLabs provider is not configured. API key is missing.' );
			throw new ProviderException( 'ElevenLabs provider is not properly configured (API key missing)' );
		}
		
		$api_key = $this->config['api_key'];
		$voice_id = (!empty($options['voice'])) ? $options['voice'] : ($this->config['default_voice'] ?? 'pNInz6obpgDQGcFmaJgB'); // Default to Adam voice if nothing else is set
		$model_id = $options['model_id'] ?? $this->config['default_model'] ?? 'eleven_multilingual_v2'; // Or 'eleven_monolingual_v1' etc.
		$output_format = 'mp3_44100_128'; // ElevenLabs specific format for mp3
		
		// Stability and Similarity Boost are common ElevenLabs parameters
		$stability = $options['stability'] ?? $this->config['stability'] ?? 0.75;
		$similarity_boost = $options['similarity_boost'] ?? $this->config['similarity_boost'] ?? 0.75;


		$this->logger->info( 'Starting ElevenLabs TTS generation', [
			'text_length' => strlen( $text ),
			'voice_id' => $voice_id,
			'model_id' => $model_id,
			'stability' => $stability,
			'similarity_boost' => $similarity_boost,
		] );
		
		try {
			// ElevenLabs limits request size: chunk long text and concatenate
			// the audio (same pattern as the other providers).
			$chunks = TextChunker::chunkText( $text, 'elevenlabs' );
			$audio_chunks = [];

			foreach ( $chunks as $index => $chunk ) {
				$audio_chunks[] = $this->requestSpeech( $chunk, $voice_id, $model_id, (float) $stability, (float) $similarity_boost, $api_key, $index + 1, count( $chunks ) );
			}

			$audio_data = implode( '', $audio_chunks );

			$this->logger->info( 'ElevenLabs TTS generation completed', [
				'audio_data_size' => strlen( $audio_data ),
				'chunks_processed' => count( $chunks ),
				'voice_id' => $voice_id,
			] );

			// Return raw audio data instead of saving to file: the TTSService
			// routes audio_data through the configured storage provider
			// (local, Buzzsprout, etc.), same as the other TTS providers.
			return [
				'success' => true,
				'audio_data' => $audio_data,
				'provider' => $this->name,
				'voice' => $voice_id,
				'format' => 'mp3',
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'model_id' => $model_id,
					'characters' => strlen( $text ),
					'chunks_processed' => count( $chunks ),
					'data_size' => strlen( $audio_data ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'ElevenLabs TTS generation failed', [
				'error' => $e->getMessage(),
			] );
			throw new ProviderException( esc_html( 'ElevenLabs TTS generation failed: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Make a single TTS API request
	 *
	 * @param string $text Text chunk (within the provider limit).
	 * @param string $voice_id Voice ID.
	 * @param string $model_id Model ID.
	 * @param float  $stability Stability setting.
	 * @param float  $similarity_boost Similarity boost setting.
	 * @param string $api_key API key.
	 * @param int    $chunk_number Chunk number (for logging).
	 * @param int    $total_chunks Total chunks (for logging).
	 * @return string Audio binary data.
	 * @throws ProviderException If the request fails.
	 */
	private function requestSpeech( string $text, string $voice_id, string $model_id, float $stability, float $similarity_boost, string $api_key, int $chunk_number = 1, int $total_chunks = 1 ): string {
		$api_url = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode( $voice_id );

		$request_body = wp_json_encode( [
			'text' => $text,
			'model_id' => $model_id,
			'voice_settings' => [
				'stability' => $stability,
				'similarity_boost' => $similarity_boost,
			],
		] );

		$response = wp_remote_post( $api_url, [
			'method'    => 'POST',
			'headers'   => [
				'Accept'        => 'audio/mpeg',
				'Content-Type'  => 'application/json',
				'xi-api-key'    => $api_key,
			],
			'body'      => $request_body,
			'timeout'   => 60, // Increased timeout
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'ElevenLabs API request failed (wp_error)', [ 'error_message' => $response->get_error_message() ] );
			throw new ProviderException( esc_html( 'ElevenLabs API request failed: ' . $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$error_details = json_decode( $response_body, true );
			$error_message = $error_details['detail']['message'] ?? $error_details['detail'] ?? $response_body;
			if ( is_array( $error_message ) ) {
				$error_message = wp_json_encode( $error_message );
			}
			$this->logger->error( 'ElevenLabs API returned an error', [
				'response_code' => $response_code,
				'error_message' => $error_message,
				'chunk' => "{$chunk_number}/{$total_chunks}",
			] );
			throw new ProviderException( esc_html( "ElevenLabs API error ({$response_code}) on chunk {$chunk_number}/{$total_chunks}: {$error_message}" ) );
		}

		return $response_body;
	}

	/**
	 * Get available voices
	 *
	 * @param string $language Language code (optional).
	 * @return array Available voices.
	 */
	public function getAvailableVoices( string $language = 'es-MX' ): array {
		// Try to get real voices from API first
		$api_voices = $this->fetchVoicesFromAPI();
		if (!empty($api_voices)) {
			return $api_voices;
		}
		
		// Fallback to common ElevenLabs voice IDs (more likely to exist)
		return [
			[ 'id' => 'pNInz6obpgDQGcFmaJgB', 'name' => 'Adam (Male, Deep)', 'gender' => 'Male', 'accent' => 'American' ],
			[ 'id' => 'EXAVITQu4vr4xnSDxMaL', 'name' => 'Bella (Female, Young)', 'gender' => 'Female', 'accent' => 'American' ],
			[ 'id' => 'VR6AewLTigWG4xSOukaG', 'name' => 'Arnold (Male, Middle-aged)', 'gender' => 'Male', 'accent' => 'American' ],
			[ 'id' => 'TxGEqnHWrfWFTfGW9XjX', 'name' => 'Josh (Male, Young)', 'gender' => 'Male', 'accent' => 'American' ],
			[ 'id' => 'rSwN5qhhs7d4JwSEc2T4', 'name' => 'Generic Voice 1', 'gender' => 'Female', 'accent' => 'American' ],
			[ 'id' => 'bIHbv24MWmeRgasZH58o', 'name' => 'Generic Voice 2', 'gender' => 'Male', 'accent' => 'American' ],
		];
	}

	/**
	 * Fetch voices from ElevenLabs API
	 *
	 * @return array Array of voices or empty array on failure.
	 */
	private function fetchVoicesFromAPI(): array {
		if (!$this->isConfigured()) {
			return [];
		}

		try {
			$api_url = 'https://api.elevenlabs.io/v1/voices';
			
			$response = wp_remote_get( $api_url, [
				'headers' => [
					'Accept' => 'application/json',
					'xi-api-key' => $this->config['api_key'],
				],
				'timeout' => 10,
			] );

			if ( is_wp_error( $response ) ) {
				$this->logger->warning( 'Failed to fetch voices from ElevenLabs API', [ 'error' => $response->get_error_message() ] );
				return [];
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				$this->logger->warning( 'ElevenLabs voices API returned non-200 status', [ 'status' => $response_code ] );
				return [];
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( !isset( $data['voices'] ) || !is_array( $data['voices'] ) ) {
				$this->logger->warning( 'Invalid voices response from ElevenLabs API' );
				return [];
			}

			$voices = [];
			foreach ( $data['voices'] as $voice ) {
				if ( isset( $voice['voice_id'], $voice['name'] ) ) {
					$voices[] = [
						'id' => $voice['voice_id'],
						'name' => $voice['name'],
						'category' => $voice['category'] ?? 'Unknown',
						'accent' => 'ElevenLabs',
					];
				}
			}

			$this->logger->info( 'Successfully fetched voices from ElevenLabs API', [ 'count' => count( $voices ) ] );
			return $voices;

		} catch ( \Exception $e ) {
			$this->logger->warning( 'Exception while fetching ElevenLabs voices', [ 'error' => $e->getMessage() ] );
			return [];
		}
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
		// ElevenLabs provides quota information, but we'd need API integration
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
				'label' => 'ElevenLabs API Key',
				'required' => true,
				'description' => 'Your ElevenLabs API key for TTS services',
			],
			'default_voice' => [
				'type' => 'select', // This should ideally be populated via API in a real scenario
				'label' => 'Default Voice ID',
				'required' => true,
				'options' => $this->getSimplifiedVoiceList(), // Dynamically populate or keep a static list
				'default' => 'pNInz6obpgDQGcFmaJgB', // Adam voice (more likely to exist)
				'description' => 'Default ElevenLabs voice ID. Find IDs via ElevenLabs documentation or API.',
			],
			'default_model' => [
				'type' => 'select',
				'label' => 'Default Model ID',
				'required' => true,
				'options' => [
					'eleven_multilingual_v2' => 'Eleven Multilingual v2 (Supports multiple languages)',
					'eleven_monolingual_v1' => 'Eleven Monolingual v1 (English only, high quality)',
					// Add other models as needed
				],
				'default' => 'eleven_multilingual_v2',
				'description' => 'Default ElevenLabs model ID.',
			],
			'stability' => [
				'type' => 'number',
				'label' => 'Default Stability (0.0 - 1.0)',
				'required' => false,
				'default' => 0.75,
				'min' => 0,
				'max' => 1,
				'step' => 0.01,
				'description' => 'Controls randomness. Lower values are more expressive but less stable.',
			],
			'similarity_boost' => [
				'type' => 'number',
				'label' => 'Default Similarity Boost (0.0 - 1.0)',
				'required' => false,
				'default' => 0.75,
				'min' => 0,
				'max' => 1,
				'step' => 0.01,
				'description' => 'Higher values make the voice more similar to the original but can introduce artifacts.',
			],
		];
	}

	private function getSimplifiedVoiceList(): array {
		$voices = $this->getAvailableVoices();
		$list = [];
		foreach ($voices as $voice) {
			$list[$voice['id']] = $voice['name'];
		}
		return $list;
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
		return 'ElevenLabs';
	}

	/**
	 * Check if provider supports SSML
	 *
	 * @return bool True if SSML is supported.
	 */
	public function supportsSSML(): bool {
		return false; // ElevenLabs doesn't support SSML
	}

	/**
	 * Get supported audio formats
	 *
	 * @return array Array of supported formats.
	 */
	public function getSupportedFormats(): array {
		return [ 'mp3', 'wav', 'ogg' ];
	}

	/**
	 * Get cost per character for this provider
	 *
	 * @return float Cost per character in USD.
	 */
	public function getCostPerCharacter(): float {
		// ElevenLabs pricing varies by plan, using approximate value
		return 0.00003; // $30 per 1 million characters (approximate)
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
			$sample_text = 'Hello, this is a sample voice from ElevenLabs.';
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
			'accent' => 'American',
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
			'422' => 'Validation error - check input parameters',
			'429' => 'Rate limit exceeded - too many requests',
			'500' => 'ElevenLabs service error',
		];

		return $error_messages[ $error_code ] ?? 'Unknown ElevenLabs error: ' . $error_code;
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
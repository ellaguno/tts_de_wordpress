<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\TTSProviderInterface;
use WP_TTS\Interfaces\AudioResult;
use WP_TTS\Exceptions\ProviderException;
use WP_TTS\Utils\Logger;

/**
 * Google Cloud TTS Provider
 *
 * Provides text-to-speech functionality using Google Cloud TTS service.
 *
 * @package WP_TTS\Providers
 * @since 1.0.0
 */
class GoogleCloudTTSProvider implements TTSProviderInterface {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	private $name = 'google';

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
			throw new ProviderException( 'Google Cloud TTS synthesis failed' );
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
			$this->logger->error( 'Google Cloud TTS provider is not configured. Credentials path is invalid or file missing.' );
			throw new ProviderException( 'Google Cloud TTS provider is not properly configured (credentials missing or invalid)' );
		}

		$credentials_path = $this->config['credentials_path'];
		// Attempt to use the uploaded file if the configured path is empty or default-looking
		if (empty($credentials_path) || strpos($credentials_path, 'google-credentials.json') !== false) {
			$upload_dir = wp_upload_dir();
			$default_path = $upload_dir['basedir'] . '/private/sesolibre-tts-13985ba22d36.json';
			if (file_exists($default_path)) {
				$credentials_path = $default_path;
				$this->logger->info('Using default credentials path for Google TTS.', ['path' => $credentials_path]);
			}
		} else {
			// Convert relative paths to absolute paths
			if ( substr( $credentials_path, 0, 1 ) !== '/' && strpos( $credentials_path, ':' ) === false ) {
				// This is a relative path, convert to absolute
				$credentials_path = ABSPATH . $credentials_path;
				$this->logger->info('Converted relative to absolute path for Google TTS.', ['path' => $credentials_path]);
			}
		}


		// Verificar diferentes rutas de clase
		$client_class = null;
		$possible_classes = [
			'\Google\Cloud\TextToSpeech\V1\TextToSpeechClient',
			'\Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient'
		];
		
		foreach ($possible_classes as $class_name) {
			if (class_exists($class_name)) {
				$client_class = $class_name;
				break;
			}
		}
		
		if (!$client_class) {
			$this->logger->error( 'Google Cloud SDK for PHP not found. Please install it via Composer.' );
			throw new ProviderException( 'Google Cloud SDK for PHP not found. Run "composer require google/cloud-text-to-speech".' );
		}
		
		$voice_id = (!empty($options['voice'])) ? $options['voice'] : ($this->config['default_voice'] ?? 'es-US-Neural2-A');

		// Validate Google voice ID - get all available voices and check if provided voice exists
		$all_voices = $this->getAvailableVoices();
		$valid_voice_ids = array_column($all_voices, 'id');

		if (!in_array($voice_id, $valid_voice_ids)) {
			// Try to find a fallback voice based on the language prefix
			$language_prefix = substr($voice_id, 0, 5); // e.g., 'es-US', 'es-MX', 'es-ES'
			$fallback_voice = null;

			// Look for a Neural2-A voice in the same language first
			foreach ($valid_voice_ids as $valid_id) {
				if (strpos($valid_id, $language_prefix) === 0 && strpos($valid_id, 'Neural2-A') !== false) {
					$fallback_voice = $valid_id;
					break;
				}
			}

			// If no Neural2-A, try any voice in the same language
			if (!$fallback_voice) {
				foreach ($valid_voice_ids as $valid_id) {
					if (strpos($valid_id, $language_prefix) === 0) {
						$fallback_voice = $valid_id;
						break;
					}
				}
			}

			// Final fallback to es-US-Neural2-A
			$fallback_voice = $fallback_voice ?? 'es-US-Neural2-A';

			$this->logger->warning('Invalid Google TTS voice ID provided, using fallback', [
				'provided_voice' => $voice_id,
				'fallback_voice' => $fallback_voice,
				'language_prefix' => $language_prefix
			]);
			$voice_id = $fallback_voice;
		}
		
		// Google voice names are like 'es-ES-Standard-A'. We need language code and name separately.
		$language_code = substr( $voice_id, 0, 5 ); // e.g., es-ES
		$voice_name = $voice_id;
		
		$output_format_enum = \Google\Cloud\TextToSpeech\V1\AudioEncoding::MP3; // Default to MP3
		$output_format_ext = 'mp3';

		// Potentially map other $options['output_format'] to Google enums if needed
		// e.g., if ($options['output_format'] === 'wav') $output_format_enum = \Google\Cloud\TextToSpeech\V1\AudioEncoding::LINEAR16;

		$speaking_rate = $options['speaking_rate'] ?? $this->config['speaking_rate'] ?? 1.0;
		$pitch = $options['pitch'] ?? $this->config['pitch'] ?? 0.0;


		$this->logger->info( 'Starting Google Cloud TTS generation', [
			'text_length' => strlen( $text ),
			'voice_name' => $voice_name,
			'language_code' => $language_code,
			'speaking_rate' => $speaking_rate,
			'pitch' => $pitch,
		] );
		
		try {
			$client = new $client_class( [
				'credentials' => $credentials_path,
			] );
			
			$synthesis_input = ( new \Google\Cloud\TextToSpeech\V1\SynthesisInput() )
				->setText( $text );
			
			$voice_selection_params = ( new \Google\Cloud\TextToSpeech\V1\VoiceSelectionParams() )
				->setLanguageCode( $language_code )
				->setName( $voice_name );
			
			$audio_config = ( new \Google\Cloud\TextToSpeech\V1\AudioConfig() )
				->setAudioEncoding( $output_format_enum )
				->setSpeakingRate( (float) $speaking_rate )
				->setPitch( (float) $pitch );
			
			$this->logger->debug( 'Google Cloud API Request details', [
				'language_code' => $language_code,
				'voice_name' => $voice_name,
				'audio_encoding' => $output_format_enum,
			]);

			// Crear el request completo para la nueva API
			$request = new \Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest();
			$request->setInput($synthesis_input);
			$request->setVoice($voice_selection_params);
			$request->setAudioConfig($audio_config);
			
			$response = $client->synthesizeSpeech( $request );
			$audio_content = $response->getAudioContent();
			$client->close();
			
			$audio_data = $audio_content;

			$this->logger->info( 'Google Cloud TTS generation completed', [
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
				'format' => $output_format_ext,
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'characters' => strlen( $text ),
					'data_size' => strlen( $audio_data ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'Google Cloud TTS generation failed', [
				'error' => esc_html( $e->getMessage() ),
			] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( 'Google Cloud TTS generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Get available voices
	 *
	 * @param string $language Language code (optional).
	 * @return array Available voices.
	 */
	public function getAvailableVoices( string $language = 'es-MX' ): array {
		// Google Cloud TTS voices - includes Standard, Wavenet, and Neural2
		$voices = [
			// Spanish (Spain) - All voice types
			'es-ES' => [
				// Standard voices
				[ 'id' => 'es-ES-Standard-A', 'name' => 'Standard A (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				[ 'id' => 'es-ES-Standard-B', 'name' => 'Standard B (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				[ 'id' => 'es-ES-Standard-C', 'name' => 'Standard C (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				[ 'id' => 'es-ES-Standard-D', 'name' => 'Standard D (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				// Wavenet voices
				[ 'id' => 'es-ES-Wavenet-B', 'name' => 'Wavenet B (Male)', 'gender' => 'Male', 'type' => 'Wavenet' ],
				[ 'id' => 'es-ES-Wavenet-C', 'name' => 'Wavenet C (Female)', 'gender' => 'Female', 'type' => 'Wavenet' ],
				[ 'id' => 'es-ES-Wavenet-D', 'name' => 'Wavenet D (Female)', 'gender' => 'Female', 'type' => 'Wavenet' ],
				// Neural2 voices
				[ 'id' => 'es-ES-Neural2-A', 'name' => 'Neural2 A (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'es-ES-Neural2-B', 'name' => 'Neural2 B (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				[ 'id' => 'es-ES-Neural2-C', 'name' => 'Neural2 C (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'es-ES-Neural2-D', 'name' => 'Neural2 D (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'es-ES-Neural2-E', 'name' => 'Neural2 E (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'es-ES-Neural2-F', 'name' => 'Neural2 F (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				// Polyglot voices
				[ 'id' => 'es-ES-Polyglot-1', 'name' => 'Polyglot 1 (Male)', 'gender' => 'Male', 'type' => 'Polyglot' ],
				// Studio voices
				[ 'id' => 'es-ES-Studio-C', 'name' => 'Studio C (Female)', 'gender' => 'Female', 'type' => 'Studio' ],
				[ 'id' => 'es-ES-Studio-F', 'name' => 'Studio F (Male)', 'gender' => 'Male', 'type' => 'Studio' ],
			],
			// Spanish (Mexico) - Neural2 and Wavenet available
			'es-MX' => [
				// Standard voices (limited for es-MX)
				[ 'id' => 'es-MX-Standard-A', 'name' => 'Standard A (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				[ 'id' => 'es-MX-Standard-B', 'name' => 'Standard B (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				[ 'id' => 'es-MX-Standard-C', 'name' => 'Standard C (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				// Wavenet voices
				[ 'id' => 'es-MX-Wavenet-A', 'name' => 'Wavenet A (Female)', 'gender' => 'Female', 'type' => 'Wavenet' ],
				[ 'id' => 'es-MX-Wavenet-B', 'name' => 'Wavenet B (Male)', 'gender' => 'Male', 'type' => 'Wavenet' ],
				[ 'id' => 'es-MX-Wavenet-C', 'name' => 'Wavenet C (Male)', 'gender' => 'Male', 'type' => 'Wavenet' ],
				// Neural2 voices
				[ 'id' => 'es-MX-Neural2-A', 'name' => 'Neural2 A (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'es-MX-Neural2-B', 'name' => 'Neural2 B (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				[ 'id' => 'es-MX-Neural2-C', 'name' => 'Neural2 C (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
			],
			// Spanish (US) - Neural2 voices
			'es-US' => [
				// Standard voices
				[ 'id' => 'es-US-Standard-A', 'name' => 'Standard A (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				[ 'id' => 'es-US-Standard-B', 'name' => 'Standard B (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				[ 'id' => 'es-US-Standard-C', 'name' => 'Standard C (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				// Wavenet voices
				[ 'id' => 'es-US-Wavenet-A', 'name' => 'Wavenet A (Female)', 'gender' => 'Female', 'type' => 'Wavenet' ],
				[ 'id' => 'es-US-Wavenet-B', 'name' => 'Wavenet B (Male)', 'gender' => 'Male', 'type' => 'Wavenet' ],
				[ 'id' => 'es-US-Wavenet-C', 'name' => 'Wavenet C (Male)', 'gender' => 'Male', 'type' => 'Wavenet' ],
				// Neural2 voices
				[ 'id' => 'es-US-Neural2-A', 'name' => 'Neural2 A (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'es-US-Neural2-B', 'name' => 'Neural2 B (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				[ 'id' => 'es-US-Neural2-C', 'name' => 'Neural2 C (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				// Studio voices
				[ 'id' => 'es-US-Studio-B', 'name' => 'Studio B (Male)', 'gender' => 'Male', 'type' => 'Studio' ],
			],
			// English (US) - All voice types
			'en-US' => [
				// Standard voices
				[ 'id' => 'en-US-Standard-A', 'name' => 'Standard A (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				[ 'id' => 'en-US-Standard-B', 'name' => 'Standard B (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				[ 'id' => 'en-US-Standard-C', 'name' => 'Standard C (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				[ 'id' => 'en-US-Standard-D', 'name' => 'Standard D (Male)', 'gender' => 'Male', 'type' => 'Standard' ],
				[ 'id' => 'en-US-Standard-E', 'name' => 'Standard E (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				[ 'id' => 'en-US-Standard-F', 'name' => 'Standard F (Female)', 'gender' => 'Female', 'type' => 'Standard' ],
				// Neural2 voices
				[ 'id' => 'en-US-Neural2-A', 'name' => 'Neural2 A (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				[ 'id' => 'en-US-Neural2-C', 'name' => 'Neural2 C (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'en-US-Neural2-D', 'name' => 'Neural2 D (Male)', 'gender' => 'Male', 'type' => 'Neural2' ],
				[ 'id' => 'en-US-Neural2-E', 'name' => 'Neural2 E (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
				[ 'id' => 'en-US-Neural2-F', 'name' => 'Neural2 F (Female)', 'gender' => 'Female', 'type' => 'Neural2' ],
			],
		];

		if ( ! empty( $language ) && isset( $voices[ $language ] ) ) {
			return $voices[ $language ];
		}

		// Return all voices if no language specified
		$all_voices = [];
		foreach ( $voices as $lang => $lang_voices ) {
			foreach ( $lang_voices as $voice ) {
				$voice['language'] = $lang;
				$all_voices[] = $voice;
			}
		}

		return $all_voices;
	}

	/**
	 * Validate API credentials
	 *
	 * @param array $credentials Optional credentials to validate.
	 * @return bool True if credentials are valid.
	 */
	public function validateCredentials( array $credentials = [] ): bool {
		$config = ! empty( $credentials ) ? $credentials : $this->config;
		
		return ! empty( $config['credentials_path'] ) && file_exists( $config['credentials_path'] );
	}

	/**
	 * Get remaining quota for this provider
	 *
	 * @return int|null Remaining characters/requests, null if unlimited.
	 */
	public function getRemainingQuota(): ?int {
		// Google Cloud TTS doesn't provide quota information via API
		return null;
	}

	/**
	 * Get provider-specific configuration schema
	 *
	 * @return array Configuration schema for admin interface.
	 */
	public function getConfigSchema(): array {
		return [
			'credentials_path' => [
				'type' => 'text',
				'label' => 'Service Account JSON Path',
				'required' => true,
				'description' => 'Path to Google Cloud service account JSON file',
			],
			'default_voice' => [
				'type' => 'select', // Should be populated via API or a more comprehensive static list
				'label' => 'Default Voice',
				'required' => true,
				'options' => $this->getSimplifiedVoiceList(),
				'default' => 'es-ES-Standard-A',
				'description' => 'Default Google Cloud TTS voice.',
			],
			'speaking_rate' => [
				'type' => 'number',
				'label' => 'Default Speaking Rate (0.25 - 4.0)',
				'required' => false,
				'default' => 1.0,
				'min' => 0.25,
				'max' => 4.0,
				'step' => 0.05,
				'description' => 'Adjusts the speed of speech. 1.0 is normal.',
			],
			'pitch' => [
				'type' => 'number',
				'label' => 'Default Pitch (-20.0 - 20.0)',
				'required' => false,
				'default' => 0.0,
				'min' => -20.0,
				'max' => 20.0,
				'step' => 0.5,
				'description' => 'Adjusts the pitch of speech. 0.0 is normal.',
			],
		];
	}

	private function getSimplifiedVoiceList(): array {
		$voices = $this->getAvailableVoices(''); // Get all voices
		$list = [];
		foreach ($voices as $voice) {
			$list[$voice['id']] = $voice['name'] . " ({$voice['language']})";
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
		return 'Google Cloud TTS';
	}

	/**
	 * Check if provider supports SSML
	 *
	 * @return bool True if SSML is supported.
	 */
	public function supportsSSML(): bool {
		return true;
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
		// Google Cloud TTS pricing: $4.00 per 1 million characters for Wavenet voices
		return 0.000004;
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
			$sample_text = 'Hola, esta es una muestra de voz de Google Cloud TTS.';
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
			'language' => 'es-MX',
			'type' => 'Wavenet',
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
			'401' => 'Authentication failed - check credentials',
			'403' => 'Access forbidden - check permissions',
			'429' => 'Rate limit exceeded - too many requests',
			'500' => 'Google Cloud TTS service error',
		];

		return $error_messages[ $error_code ] ?? 'Unknown Google Cloud TTS error: ' . $error_code;
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 */
	public function isConfigured(): bool {
		$path = $this->config['credentials_path'] ?? '';
		if (empty($path) || strpos($path, 'google-credentials.json') !== false) { // Check default if specific path is empty or looks like a placeholder
			$upload_dir = wp_upload_dir();
			$default_path = $upload_dir['basedir'] . '/private/sesolibre-tts-13985ba22d36.json';
			if (file_exists($default_path)) {
				return true;
			}
		} else {
			// Convert relative paths to absolute paths
			if ( substr( $path, 0, 1 ) !== '/' && strpos( $path, ':' ) === false ) {
				// This is a relative path, convert to absolute
				$path = ABSPATH . $path;
			}
		}
		return ! empty( $path ) && file_exists( $path );
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
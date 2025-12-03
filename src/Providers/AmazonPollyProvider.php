<?php

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\TTSProviderInterface;
use WP_TTS\Interfaces\AudioResult;
use WP_TTS\Exceptions\ProviderException;
use WP_TTS\Utils\Logger;
use WP_TTS\Utils\VoiceValidator;
use WP_TTS\Utils\TextChunker;

/**
 * Amazon Polly TTS Provider
 *
 * Provides text-to-speech functionality using Amazon Polly service.
 *
 * @package WP_TTS\Providers
 * @since 1.0.0
 */
class AmazonPollyProvider implements TTSProviderInterface {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	private $name = 'amazon_polly';

	/**
	 * AWS credentials
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
	 * Constructor
	 *
	 * @param array  $credentials AWS credentials.
	 * @param Logger $logger      Logger instance.
	 */
	public function __construct( array $credentials, Logger $logger ) {
		$this->credentials = $credentials; // These are expected to be like ['access_key' => ..., 'secret_key' => ..., 'region' => ...]
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
		return ! empty( $this->credentials['access_key'] ) &&
			   ! empty( $this->credentials['secret_key'] ) &&
			   ! empty( $this->credentials['region'] );
	}
	
	/**
	 * Generate speech from text
	 *
	 * @param string $text    Text to convert.
	 * @param array  $options TTS options.
	 * @return array Audio data with URL and metadata.
	 * @throws ProviderException If generation fails.
	 */
	public function generateSpeech( string $text, array $options = [] ): array {
		if ( ! $this->isConfigured() ) {
			$this->logger->error( 'Amazon Polly provider is not configured. Credentials missing.' );
			throw new ProviderException( 'Amazon Polly provider is not properly configured (credentials missing)' );
		}

		if ( ! class_exists( '\Aws\Polly\PollyClient' ) ) {
			$this->logger->error( 'AWS SDK for PHP (Polly) not found. Please install it via Composer.' );
			throw new ProviderException( 'AWS SDK for PHP (Polly) not found. Run "composer require aws/aws-sdk-php".' );
		}
		
		$voice_id = $options['voice'] ?? $this->credentials['default_voice'] ?? 'Lucia'; // Use valid Polly voice
		
		// Validate and fix voice
		$voice_id = VoiceValidator::fixVoice( 'polly', $voice_id );
		
		$output_format = $options['output_format'] ?? 'mp3'; // Polly supports mp3, ogg_vorbis, pcm
		$engine = $options['engine'] ?? $this->credentials['default_engine'] ?? 'standard'; // 'standard' or 'neural'
		// SampleRate is often dictated by the voice/engine, but can be specified.
		// For mp3, common rates are 22050, 16000, 8000. For neural, often higher.
		$sample_rate = $options['sample_rate'] ?? $this->credentials['default_sample_rate'] ?? '22050';

		$this->logger->info( 'Starting Amazon Polly TTS generation', [
			'text_length' => strlen( $text ),
			'voice' => $voice_id,
			'engine' => $engine,
			'output_format' => $output_format,
			'needs_chunking' => TextChunker::needsChunking( $text, 'polly' )
		] );

		// Check if text needs chunking
		if ( TextChunker::needsChunking( $text, 'polly' ) ) {
			return $this->generateChunkedSpeech( $text, $voice_id, $options );
		}
		
		try {
			$pollyClient = new \Aws\Polly\PollyClient( [
				'version'     => 'latest',
				'region'      => $this->credentials['region'],
				'credentials' => [
					'key'    => $this->credentials['access_key'],
					'secret' => $this->credentials['secret_key'],
				],
			] );

			$request_args = [
				'Text'         => $text,
				'OutputFormat' => $output_format,
				'VoiceId'      => $voice_id,
				'Engine'       => $engine,
				// 'SampleRate' => $sample_rate, // Only for PCM or if specific control needed
			];
			if ($output_format === 'pcm') { // SampleRate is required for PCM
		              $request_args['SampleRate'] = $sample_rate;
		          }


			if ( isset( $options['text_type'] ) && $options['text_type'] === 'ssml' ) {
				$request_args['TextType'] = 'ssml';
			}
			
			$this->logger->debug( 'Amazon Polly API Request arguments', $request_args);

			$result = $pollyClient->synthesizeSpeech( $request_args );
			
			$audio_stream = $result->get( 'AudioStream' );
			if ( ! $audio_stream ) {
				$this->logger->error( 'Invalid response from Amazon Polly: No AudioStream.', ['result' => $result]);
				throw new ProviderException( 'Invalid response from Amazon Polly: No AudioStream' );
			}
			$audio_data = $audio_stream->getContents();
			
			// Generate unique filename
			$filename = 'polly_' . md5( $text . $voice_id . $engine . time() ) . '.' . $output_format;
			$upload_dir = wp_upload_dir();
			$file_path = $upload_dir['basedir'] . '/tts-audio/' . $filename;
			$file_url = $upload_dir['baseurl'] . '/tts-audio/' . $filename;

			// Ensure directory exists
			wp_mkdir_p( dirname( $file_path ) );

			// Save audio file
			if ( file_put_contents( $file_path, $audio_data ) === false ) { // Use $audio_data here
				throw new ProviderException( 'Failed to save audio file' );
			}
			
			$this->logger->info( 'Amazon Polly TTS generation completed', [
				'file_path' => $file_path,
				'file_size' => filesize( $file_path ),
			] );

			return [
				'success' => true,
				'audio_data' => $audio_data,
				'provider' => $this->name,
				'voice' => $voice_id,
				'format' => $output_format,
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'engine' => $engine,
					'sample_rate' => $sample_rate,
					'characters' => strlen( $text ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'Amazon Polly TTS generation failed', [
				'error' => esc_html( $e->getMessage() ),
			] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( 'Amazon Polly generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate speech for chunked text
	 *
	 * @param string $text Text to convert (will be chunked)
	 * @param string $voice_id Voice ID
	 * @param array $options Additional options
	 * @return array Result array with combined audio
	 * @throws ProviderException If generation fails
	 */
	private function generateChunkedSpeech( string $text, string $voice_id, array $options ): array {
		$chunks = TextChunker::chunkText( $text, 'polly' );
		$audio_chunks = [];
		
		$output_format = $options['output_format'] ?? 'mp3';
		$engine = $options['engine'] ?? $this->credentials['default_engine'] ?? 'standard';
		$sample_rate = $options['sample_rate'] ?? $this->credentials['default_sample_rate'] ?? '22050';
		
		$this->logger->info( 'Amazon Polly: Generating chunked speech', [
			'total_chunks' => count( $chunks ),
			'voice_id' => $voice_id
		] );

		try {
			$pollyClient = new \Aws\Polly\PollyClient( [
				'version'     => 'latest',
				'region'      => $this->credentials['region'],
				'credentials' => [
					'key'    => $this->credentials['access_key'],
					'secret' => $this->credentials['secret_key'],
				],
			] );

			foreach ( $chunks as $index => $chunk ) {
				$this->logger->debug( "Amazon Polly: Processing chunk " . ($index + 1) . "/" . count( $chunks ), [
					'chunk_length' => strlen( $chunk )
				] );

				$request_args = [
					'Text'         => $chunk,
					'VoiceId'      => $voice_id,
					'OutputFormat' => $output_format,
					'Engine'       => $engine,
					'SampleRate'   => $sample_rate,
				];

				$result = $pollyClient->synthesizeSpeech( $request_args );
				
				if ( ! isset( $result['AudioStream'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					throw new ProviderException( "Amazon Polly: Invalid response for chunk " . ($index + 1) . ": No AudioStream" );
				}
				
				$audio_data = $result['AudioStream']->getContents();
				$audio_chunks[] = $audio_data;
			}

			// Combine all audio chunks
			$combined_audio = $this->combineAudioChunks( $audio_chunks );

			$this->logger->info( 'Amazon Polly: Chunked speech generation completed', [
				'total_chunks' => count( $chunks ),
				'combined_audio_size' => strlen( $combined_audio ),
				'voice_id' => $voice_id
			] );

			return [
				'success' => true,
				'audio_data' => $combined_audio,
				'provider' => $this->name,
				'voice' => $voice_id,
				'format' => $output_format,
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'engine' => $engine,
					'sample_rate' => $sample_rate,
					'characters' => strlen( $text ),
					'chunks_processed' => count( $chunks ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'Amazon Polly chunked TTS generation failed', [
				'error' => esc_html( $e->getMessage() ),
			] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( 'Amazon Polly chunked generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Combine multiple audio chunks into single audio
	 *
	 * @param array $audio_chunks Array of audio binary data
	 * @return string Combined audio data
	 */
	private function combineAudioChunks( array $audio_chunks ): string {
		// For MP3 files, simple concatenation works for most cases
		// For production, you might want to use FFmpeg for proper audio merging
		return implode( '', $audio_chunks );
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
			throw new ProviderException( 'Speech synthesis failed' );
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
	 * Validate API credentials
	 *
	 * @param array $credentials Optional credentials to validate.
	 * @return bool True if credentials are valid.
	 */
	public function validateCredentials( array $credentials = [] ): bool {
		$creds = ! empty( $credentials ) ? $credentials : $this->credentials;
		
		return ! empty( $creds['access_key'] ) &&
			   ! empty( $creds['secret_key'] ) &&
			   ! empty( $creds['region'] );
	}

	/**
	 * Get remaining quota for this provider
	 *
	 * @return int|null Remaining characters/requests, null if unlimited.
	 */
	public function getRemainingQuota(): ?int {
		// Amazon Polly doesn't provide quota information via API
		// Return null to indicate unlimited/unknown
		return null;
	}

	/**
	 * Get provider-specific configuration schema
	 *
	 * @return array Configuration schema for admin interface.
	 */
	public function getConfigSchema(): array {
		return [
			'access_key' => [
				'type' => 'text',
				'label' => 'AWS Access Key ID',
				'required' => true,
				'description' => 'Your AWS Access Key ID for Amazon Polly',
			],
			'secret_key' => [
				'type' => 'password',
				'label' => 'AWS Secret Access Key',
				'required' => true,
				'description' => 'Your AWS Secret Access Key for Amazon Polly',
			],
			'region' => [
				'type' => 'select',
				'label' => 'AWS Region',
				'required' => true,
				'options' => [
					'us-east-1' => 'US East (N. Virginia)',
					'us-west-2' => 'US West (Oregon)',
					'eu-west-1' => 'Europe (Ireland)',
					'ap-southeast-2' => 'Asia Pacific (Sydney)',
				],
				'default' => 'us-east-1',
				'description' => 'AWS region for Amazon Polly service',
			],
		];
	}

	/**
	 * Get provider display name
	 *
	 * @return string Human-readable provider name.
	 */
	public function getDisplayName(): string {
		return 'Amazon Polly';
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
		return [ 'mp3', 'ogg_vorbis', 'pcm' ];
	}

	/**
	 * Get cost per character for this provider
	 *
	 * @return float Cost per character in USD.
	 */
	public function getCostPerCharacter(): float {
		// Amazon Polly pricing: $4.00 per 1 million characters
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
			$sample_text = 'Hola, esta es una muestra de voz de Amazon Polly.';
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
			'language' => 'en-US',
			'engine' => 'standard',
		];
	}

	/**
	 * Check provider health/availability
	 *
	 * @return bool True if provider is available.
	 */
	public function isHealthy(): bool {
		try {
			return $this->testConnection();
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get provider-specific error messages
	 *
	 * @param string $error_code Error code from provider.
	 * @return string Human-readable error message.
	 */
	public function getErrorMessage( string $error_code ): string {
		$error_messages = [
			'InvalidParameterValue' => 'Invalid parameter value provided',
			'TextLengthExceededException' => 'Text length exceeds maximum allowed',
			'InvalidSampleRateException' => 'Invalid sample rate specified',
			'InvalidSsmlException' => 'Invalid SSML markup',
			'LexiconNotFoundException' => 'Specified lexicon not found',
			'ServiceFailureException' => 'Amazon Polly service failure',
			'SynthesisTaskNotFoundException' => 'Synthesis task not found',
			'UnsupportedPlsAlphabetException' => 'Unsupported PLS alphabet',
			'UnsupportedPlsLanguageException' => 'Unsupported PLS language',
		];

		return $error_messages[ $error_code ] ?? 'Unknown Amazon Polly error: ' . $error_code;
	}

	/**
	 * Get available voices
	 *
	 * @param string $language_code Language code (optional).
	 * @return array Available voices.
	 */
	public function getAvailableVoices( string $language_code = '' ): array {
		// Standard voices for common languages
		$voices = [
			// English (US)
			'en-US' => [
				[ 'id' => 'Joanna', 'name' => 'Joanna (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
				[ 'id' => 'Matthew', 'name' => 'Matthew (Male)', 'gender' => 'Male', 'engine' => 'standard' ],
				[ 'id' => 'Ivy', 'name' => 'Ivy (Female, Child)', 'gender' => 'Female', 'engine' => 'standard' ],
				[ 'id' => 'Justin', 'name' => 'Justin (Male, Child)', 'gender' => 'Male', 'engine' => 'standard' ],
				[ 'id' => 'Kendra', 'name' => 'Kendra (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
				[ 'id' => 'Kimberly', 'name' => 'Kimberly (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
				[ 'id' => 'Salli', 'name' => 'Salli (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
				[ 'id' => 'Joey', 'name' => 'Joey (Male)', 'gender' => 'Male', 'engine' => 'standard' ],
			],
			// Spanish (ES)
			'es-ES' => [
				[ 'id' => 'Conchita', 'name' => 'Conchita (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
				[ 'id' => 'Enrique', 'name' => 'Enrique (Male)', 'gender' => 'Male', 'engine' => 'standard' ],
				[ 'id' => 'Lucia', 'name' => 'Lucia (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
			],
			// Spanish (MX)
			'es-MX' => [
				[ 'id' => 'Mia', 'name' => 'Mia (Female)', 'gender' => 'Female', 'engine' => 'standard' ],
			],
		];

		if ( ! empty( $language_code ) && isset( $voices[ $language_code ] ) ) {
			return $voices[ $language_code ];
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
	 * Test provider connection
	 *
	 * @return bool True if connection successful.
	 * @throws ProviderException If test fails.
	 */
	public function testConnection(): bool {
		if ( ! $this->isConfigured() ) {
			throw new ProviderException( 'Amazon Polly provider is not configured' );
		}

		try {
			// Test with a simple describe voices request
			$response = $this->makePollyRequest( 'describe-voices', [] );
			return isset( $response['Voices'] );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( 'Amazon Polly connection test failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Get provider statistics
	 *
	 * @return array Provider stats.
	 */
	public function getStats(): array {
		return [
			'provider' => $this->name,
			'configured' => $this->isConfigured(),
			'region' => $this->credentials['region'] ?? '',
			'available_voices' => count( $this->getAvailableVoices() ),
		];
	}

	/**
	 * Make request to Amazon Polly API
	 *
	 * @param string $action      API action.
	 * @param array  $parameters  Request parameters.
	 * @return array API response.
	 * @throws ProviderException If request fails.
	 */
	private function makePollyRequest( string $action, array $parameters ): array {
		if ( ! $this->isConfigured() ) {
			$this->logger->error('[AmazonPollyProvider::makePollyRequest] Not configured.');
			throw new ProviderException( 'Amazon Polly provider is not configured for makePollyRequest.' );
		}
		if ( ! class_exists( '\Aws\Polly\PollyClient' ) ) {
			$this->logger->error('[AmazonPollyProvider::makePollyRequest] AWS SDK PollyClient not found.');
			throw new ProviderException( 'AWS SDK for PHP (Polly) not found for makePollyRequest.' );
		}

		$pollyClient = new \Aws\Polly\PollyClient( [
			'version'     => 'latest',
			'region'      => $this->credentials['region'],
			'credentials' => [
				'key'    => $this->credentials['access_key'],
				'secret' => $this->credentials['secret_key'],
			],
		] );

		try {
			if ( $action === 'describe-voices' ) {
				$this->logger->debug('[AmazonPollyProvider::makePollyRequest] Action: describe-voices', $parameters);
				$result = $pollyClient->describeVoices( $parameters );
				return $result->toArray(); // Convert AWS Result object to array
			}
			// synthesizeSpeech is handled directly in generateSpeech method.
			// Other Polly actions (e.g., StartSpeechSynthesisTask) could be added here if needed.

		} catch ( \Aws\Exception\AwsException $e ) {
			$this->logger->error( "[AmazonPollyProvider::makePollyRequest] AWS Polly API Exception for action {$action}", [
				'aws_error_code' => $e->getAwsErrorCode(),
				'aws_error_message' => $e->getAwsErrorMessage(),
				'message' => $e->getMessage(),
			]);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( "Amazon Polly API request for {$action} failed: " . $e->getAwsErrorMessage() );
		} catch ( \Exception $e ) {
			$this->logger->error( "[AmazonPollyProvider::makePollyRequest] Generic Exception for Polly action {$action}", [ 'message' => $e->getMessage() ]);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ProviderException( "Amazon Polly request for {$action} failed: " . $e->getMessage() );
		}
		
		$this->logger->warn('[AmazonPollyProvider::makePollyRequest] Unknown or unhandled Polly action.', ['action' => $action]);
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new ProviderException( 'Unknown or unhandled Polly action in makePollyRequest: ' . $action );
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
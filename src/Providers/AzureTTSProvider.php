<?php
/**
 * Microsoft Azure Text-to-Speech Provider
 *
 * @package WP_TTS
 */

namespace WP_TTS\Providers;

use WP_TTS\Interfaces\TTSProviderInterface;
use WP_TTS\Interfaces\AudioResult;
use WP_TTS\Exceptions\ProviderException;
use WP_TTS\Utils\Logger;

/**
 * Microsoft Azure TTS provider implementation
 */
class AzureTTSProvider implements TTSProviderInterface {

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
	 * Azure TTS endpoint base URL
	 *
	 * @var string
	 */
	private $endpoint_base;

	/**
	 * Constructor
	 *
	 * @param array  $config Provider configuration.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( array $config, Logger $logger ) {
		$this->config = $config;
		$this->logger = $logger;
		
		// Set endpoint based on region
		$region = $config['region'] ?? 'eastus';
		$this->endpoint_base = "https://{$region}.tts.speech.microsoft.com";
	}


	/**
	 * Generate speech from text
	 *
	 * @param string $text Text to convert to speech.
	 * @param array  $options Additional options including voice.
	 * @return array Result array with success status and audio URL.
	 * @throws ProviderException If generation fails.
	 */
	public function generateSpeech( string $text, array $options = [] ): array {
		if ( ! $this->isConfigured() ) {
			$this->logger->error( 'Azure TTS provider is not configured. Subscription key or region is missing.' );
			throw new ProviderException( 'Azure TTS provider is not properly configured (subscription key or region missing)' );
		}

		try {
			// Get voice from options or use default
			$voice_id = (!empty($options['voice'])) ? $options['voice'] : ($this->config['default_voice'] ?? 'es-MX-DaliaNeural');

			$this->logger->info( 'Azure TTS: Starting speech generation', [
				'text_length' => strlen( $text ),
				'voice_id' => $voice_id
			] );

			// Get access token
			$access_token = $this->getAccessToken();
			
			if ( ! $access_token ) {
				throw new ProviderException( 'Azure TTS: Failed to obtain access token' );
			}

			// Prepare SSML
			$ssml = $this->buildSSML( $text, $voice_id, $options );

			// Make TTS request
			$audio_data = $this->makeTTSRequest( $ssml, $access_token );

			if ( ! $audio_data ) {
				throw new ProviderException( 'Azure TTS: Failed to generate audio' );
			}

			// Save audio file
			$audio_url = $this->saveAudioFile( $audio_data, $voice_id );

			$this->logger->info( 'Azure TTS: Speech generation completed', [
				'audio_url' => $audio_url,
				'voice_id' => $voice_id
			] );

			return [
				'success' => true,
				'audio_url' => $audio_url,
				'file_path' => $this->getFilePathFromUrl( $audio_url ),
				'provider' => 'azure_tts',
				'voice' => $voice_id,
				'format' => 'mp3',
				'duration' => $this->estimateAudioDuration( $text ),
				'metadata' => [
					'characters' => strlen( $text ),
				],
			];

		} catch ( \Exception $e ) {
			$this->logger->error( 'Azure TTS: Speech generation failed', [
				'error' => $e->getMessage(),
				'voice_id' => $voice_id ?? 'unknown'
			] );
			
			throw new ProviderException( 'Azure TTS generation failed: ' . $e->getMessage() );
		}
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
		$voice_id = $options['voice'] ?? $this->config['default_voice'] ?? 'es-MX-DaliaNeural';
		$result = $this->generateSpeech( $text, $voice_id, $options );
		
		if ( ! $result['success'] ) {
			throw new ProviderException( 'Azure TTS synthesis failed' );
		}

		// Get audio data from URL
		$audio_data = wp_remote_get( $result['audio_url'] );
		if ( is_wp_error( $audio_data ) ) {
			throw new ProviderException( 'Failed to retrieve generated audio file' );
		}

		$audio_content = wp_remote_retrieve_body( $audio_data );

		return new AudioResult(
			$audio_content,
			'mp3',
			$result['duration'],
			[
				'provider' => 'azure_tts',
				'voice' => $voice_id,
				'character_count' => strlen( $text ),
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
		$config = ! empty( $credentials ) ? $credentials : $this->config;
		
		return ! empty( $config['subscription_key'] ) && ! empty( $config['region'] );
	}

	/**
	 * Get remaining quota for this provider
	 *
	 * @return int|null Remaining characters/requests, null if unlimited.
	 */
	public function getRemainingQuota(): ?int {
		// Azure doesn't provide quota information via API
		return null;
	}

	/**
	 * Get provider display name
	 *
	 * @return string Human-readable provider name.
	 */
	public function getDisplayName(): string {
		return 'Microsoft Azure TTS';
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
		// Azure TTS pricing: $4.00 per 1 million characters for Neural voices
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
			$sample_text = 'Hola, esta es una muestra de voz de Microsoft Azure.';
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
			'language' => 'es-MX',
		];
	}

	/**
	 * Check provider health/availability
	 *
	 * @return bool True if provider is available.
	 */
	public function isHealthy(): bool {
		return $this->testConnection();
	}

	/**
	 * Get provider-specific error messages
	 *
	 * @param string $error_code Error code from provider.
	 * @return string Human-readable error message.
	 */
	public function getErrorMessage( string $error_code ): string {
		$error_messages = [
			'401' => 'Invalid subscription key or authentication failed',
			'403' => 'Access forbidden - check your subscription',
			'429' => 'Rate limit exceeded - too many requests',
			'400' => 'Bad request - invalid parameters',
			'500' => 'Azure TTS service error',
		];

		return $error_messages[ $error_code ] ?? 'Unknown Azure TTS error: ' . $error_code;
	}

	/**
	 * Get access token from Azure
	 *
	 * @return string|null Access token or null on failure.
	 */
	private function getAccessToken(): ?string {
		$token_endpoint = "https://{$this->config['region']}.api.cognitive.microsoft.com/sts/v1.0/issueToken";
		
		$response = wp_remote_post( $token_endpoint, [
			'headers' => [
				'Ocp-Apim-Subscription-Key' => $this->config['subscription_key'],
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body' => '',
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Azure TTS: Token request failed', [
				'error' => $response->get_error_message()
			] );
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$this->logger->error( 'Azure TTS: Token request failed', [
				'response_code' => $response_code,
				'response_body' => wp_remote_retrieve_body( $response )
			] );
			return null;
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Build SSML for Azure TTS
	 *
	 * @param string $text Text content.
	 * @param string $voice_id Voice ID.
	 * @param array  $options Additional options.
	 * @return string SSML string.
	 */
	private function buildSSML( string $text, string $voice_id, array $options = [] ): string {
		// Extract language from voice ID (e.g., es-MX-DaliaNeural -> es-MX)
		$language = 'es-MX';
		if ( preg_match( '/^([a-z]{2}-[A-Z]{2})/', $voice_id, $matches ) ) {
			$language = $matches[1];
		}

		// Build SSML
		$ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="' . $language . '">';
		$ssml .= '<voice name="' . esc_attr( $voice_id ) . '">';
		
		// Add rate and pitch if specified
		$prosody_attrs = [];
		if ( ! empty( $options['rate'] ) ) {
			$prosody_attrs[] = 'rate="' . esc_attr( $options['rate'] ) . '"';
		}
		if ( ! empty( $options['pitch'] ) ) {
			$prosody_attrs[] = 'pitch="' . esc_attr( $options['pitch'] ) . '"';
		}
		
		if ( ! empty( $prosody_attrs ) ) {
			$ssml .= '<prosody ' . implode( ' ', $prosody_attrs ) . '>';
			$ssml .= esc_html( $text );
			$ssml .= '</prosody>';
		} else {
			$ssml .= esc_html( $text );
		}
		
		$ssml .= '</voice>';
		$ssml .= '</speak>';

		return $ssml;
	}

	/**
	 * Make TTS request to Azure
	 *
	 * @param string $ssml SSML content.
	 * @param string $access_token Access token.
	 * @return string|null Audio data or null on failure.
	 */
	private function makeTTSRequest( string $ssml, string $access_token ): ?string {
		$tts_endpoint = $this->endpoint_base . '/cognitiveservices/v1';
		
		$response = wp_remote_post( $tts_endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type' => 'application/ssml+xml',
				'X-Microsoft-OutputFormat' => 'audio-16khz-128kbitrate-mono-mp3',
				'User-Agent' => 'WordPress-TTS-Plugin',
			],
			'body' => $ssml,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Azure TTS: TTS request failed', [
				'error' => $response->get_error_message()
			] );
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$this->logger->error( 'Azure TTS: TTS request failed', [
				'response_code' => $response_code,
				'response_body' => wp_remote_retrieve_body( $response )
			] );
			return null;
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Save audio file to WordPress uploads
	 *
	 * @param string $audio_data Audio binary data.
	 * @param string $voice_id Voice ID for filename.
	 * @return string Audio file URL.
	 * @throws ProviderException If file save fails.
	 */
	private function saveAudioFile( string $audio_data, string $voice_id ): string {
		$upload_dir = wp_upload_dir();
		$tts_dir = $upload_dir['basedir'] . '/tts-audio';
		
		// Create directory if it doesn't exist
		if ( ! file_exists( $tts_dir ) ) {
			wp_mkdir_p( $tts_dir );
		}

		// Generate unique filename
		$hash = md5( $audio_data . $voice_id . time() );
		$filename = "azure-tts-{$voice_id}-{$hash}.mp3";
		$file_path = $tts_dir . '/' . $filename;

		// Save file
		$result = file_put_contents( $file_path, $audio_data );
		
		if ( $result === false ) {
			throw new ProviderException( 'Azure TTS: Failed to save audio file' );
		}

		// Return URL
		return $upload_dir['baseurl'] . '/tts-audio/' . $filename;
	}

	/**
	 * Get available voices
	 *
	 * @param string $language Language code (optional).
	 * @return array Available voices.
	 */
	public function getAvailableVoices( string $language = 'es-MX' ): array {
		// Return static list of popular Azure voices
		// In a real implementation, you could fetch this from Azure API
		return [
			// Spanish (Mexico)
			[ 'id' => 'es-MX-DaliaNeural', 'name' => 'Dalia (Mexican Spanish Female)', 'language' => 'es-MX' ],
			[ 'id' => 'es-MX-JorgeNeural', 'name' => 'Jorge (Mexican Spanish Male)', 'language' => 'es-MX' ],
			[ 'id' => 'es-MX-BeatrizNeural', 'name' => 'Beatriz (Mexican Spanish Female)', 'language' => 'es-MX' ],
			[ 'id' => 'es-MX-CandelaNeural', 'name' => 'Candela (Mexican Spanish Female)', 'language' => 'es-MX' ],
			[ 'id' => 'es-MX-CecilioNeural', 'name' => 'Cecilio (Mexican Spanish Male)', 'language' => 'es-MX' ],
			
			// Spanish (Spain)
			[ 'id' => 'es-ES-ElviraNeural', 'name' => 'Elvira (Spanish Female)', 'language' => 'es-ES' ],
			[ 'id' => 'es-ES-AlvaroNeural', 'name' => 'Alvaro (Spanish Male)', 'language' => 'es-ES' ],
			[ 'id' => 'es-ES-AbrilNeural', 'name' => 'Abril (Spanish Female)', 'language' => 'es-ES' ],
			[ 'id' => 'es-ES-ArnauNeural', 'name' => 'Arnau (Spanish Male)', 'language' => 'es-ES' ],
			
			// English (US)
			[ 'id' => 'en-US-AriaNeural', 'name' => 'Aria (English US Female)', 'language' => 'en-US' ],
			[ 'id' => 'en-US-DavisNeural', 'name' => 'Davis (English US Male)', 'language' => 'en-US' ],
			[ 'id' => 'en-US-AmberNeural', 'name' => 'Amber (English US Female)', 'language' => 'en-US' ],
			[ 'id' => 'en-US-AnaNeural', 'name' => 'Ana (English US Female)', 'language' => 'en-US' ],
			[ 'id' => 'en-US-BrandonNeural', 'name' => 'Brandon (English US Male)', 'language' => 'en-US' ],
			
			// English (UK)
			[ 'id' => 'en-GB-SoniaNeural', 'name' => 'Sonia (English UK Female)', 'language' => 'en-GB' ],
			[ 'id' => 'en-GB-RyanNeural', 'name' => 'Ryan (English UK Male)', 'language' => 'en-GB' ],
			[ 'id' => 'en-GB-LibbyNeural', 'name' => 'Libby (English UK Female)', 'language' => 'en-GB' ],
		];
	}

	/**
	 * Test provider connection
	 *
	 * @return bool True if connection successful.
	 */
	public function testConnection(): bool {
		try {
			$access_token = $this->getAccessToken();
			return ! empty( $access_token );
		} catch ( \Exception $e ) {
			$this->logger->error( 'Azure TTS: Connection test failed', [
				'error' => $e->getMessage()
			] );
			return false;
		}
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name.
	 */
	public function getName(): string {
		return 'Microsoft Azure TTS';
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool True if configured.
	 */
	public function isConfigured(): bool {
		return ! empty( $this->config['subscription_key'] ) && ! empty( $this->config['region'] );
	}

	/**
	 * Get file path from URL
	 *
	 * @param string $url File URL.
	 * @return string File path.
	 */
	private function getFilePathFromUrl( string $url ): string {
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
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

	/**
	 * Get provider configuration schema
	 *
	 * @return array Configuration schema.
	 */
	public function getConfigSchema(): array {
		return [
			'subscription_key' => [
				'type' => 'string',
				'required' => true,
				'label' => 'Subscription Key',
				'description' => 'Azure Cognitive Services subscription key',
			],
			'region' => [
				'type' => 'select',
				'required' => true,
				'label' => 'Region',
				'description' => 'Azure region for the service',
				'options' => [
					'eastus' => 'East US',
					'eastus2' => 'East US 2',
					'westus' => 'West US',
					'westus2' => 'West US 2',
					'centralus' => 'Central US',
					'northcentralus' => 'North Central US',
					'southcentralus' => 'South Central US',
					'westcentralus' => 'West Central US',
					'canadacentral' => 'Canada Central',
					'brazilsouth' => 'Brazil South',
					'northeurope' => 'North Europe',
					'westeurope' => 'West Europe',
					'uksouth' => 'UK South',
					'ukwest' => 'UK West',
					'francecentral' => 'France Central',
					'germanywestcentral' => 'Germany West Central',
					'switzerlandnorth' => 'Switzerland North',
					'norwayeast' => 'Norway East',
					'southeastasia' => 'Southeast Asia',
					'eastasia' => 'East Asia',
					'australiaeast' => 'Australia East',
					'japaneast' => 'Japan East',
					'japanwest' => 'Japan West',
					'koreacentral' => 'Korea Central',
					'southafricanorth' => 'South Africa North',
					'centralindia' => 'Central India',
					'southindia' => 'South India',
					'westindia' => 'West India',
				],
				'default' => 'eastus',
			],
			'default_voice' => [
				'type' => 'select',
				'required' => false,
				'label' => 'Default Voice',
				'description' => 'Default voice to use when none is specified',
				'default' => 'es-MX-DaliaNeural',
			],
		];
	}
}
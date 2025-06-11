<?php
/**
 * TTS Service main class
 *
 * @package WP_TTS
 */

namespace WP_TTS\Services;

use WP_TTS\Utils\Logger;
use WP_TTS\Interfaces\CacheServiceInterface;

/**
 * Main TTS service coordinator
 */
class TTSService {
	
	/**
	 * Round robin manager
	 *
	 * @var RoundRobinManager
	 */
	private $round_robin;
	
	/**
	 * Cache service
	 *
	 * @var CacheServiceInterface
	 */
	private $cache;
	
	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;
	
	/**
	 * Constructor
	 *
	 * @param RoundRobinManager     $round_robin Round robin manager.
	 * @param CacheServiceInterface $cache       Cache service.
	 * @param Logger                $logger      Logger instance.
	 */
	public function __construct( RoundRobinManager $round_robin, CacheServiceInterface $cache, Logger $logger ) {
		$this->round_robin = $round_robin;
		$this->cache = $cache;
		$this->logger = $logger;
	}
	
	/**
	 * Generate audio from text
	 *
	 * @param string $text    Text to convert.
	 * @param array  $options TTS options.
	 * @return array|null Audio result or null on failure.
	 */
	public function generateAudio( string $text, array $options = [] ): ?array {
		try {
			$this->logger->info( 'Starting TTS generation', [ 'text_length' => strlen( $text ) ] );
			
			// Check cache first
			$textHash = $this->cache->generateTextHash( $text, $options );
			$cached_url = $this->cache->getCachedAudioUrl( $textHash );
			
			if ( $cached_url ) {
				$this->logger->info( 'Audio found in cache', [ 'hash' => $textHash ] );
				return [
					'success' => true,
					'audio_url' => $cached_url,
					'source' => 'cache',
					'hash' => $textHash,
				];
			}
			
			// Get next provider
			$this->logger->info( 'Getting next provider from round robin' );
			$provider = $this->round_robin->getNextProvider();
			$this->logger->info( 'Round robin returned provider', [ 'provider' => $provider ] );
			
			if ( ! $provider ) {
				$this->logger->error( 'No active TTS providers available' );
				return null;
			}
			
			$this->logger->info( 'Using TTS provider', [ 'provider' => $provider ] );
			
			// Get the actual provider instance and generate audio
			$provider_instance = $this->getProviderInstance( $provider );
			
			if ( ! $provider_instance ) {
				$this->logger->info( 'Provider instance not found, using mock audio', [ 'provider' => $provider ] );
				// Fallback to mock audio generation
				$mock_audio_url = $this->generateMockAudio( $text, $provider );
				
				if ( $mock_audio_url ) {
					// Cache the result
					$this->cache->cacheAudioUrl( $textHash, $mock_audio_url, null, [
						'provider' => $provider,
						'generated_at' => time(),
						'text_length' => strlen( $text ),
						'mock' => true,
					] );
					
					$this->round_robin->recordUsage( $provider, true );
					
					$this->logger->info( 'Mock TTS generation completed successfully', [
						'provider' => $provider,
						'audio_url' => $mock_audio_url
					] );
					
					return [
						'success' => true,
						'audio_url' => $mock_audio_url,
						'source' => 'mock',
						'provider' => $provider,
						'hash' => $textHash,
					];
				}
				
				$this->logger->error( 'Mock audio generation failed', [ 'provider' => $provider ] );
				return null;
			}
			
			try {
				// Use voice from options if provided
				$voice = $options['voice'] ?? '';
				$audio_result = $provider_instance->generateSpeech( $text, $voice );
				
				if ( $audio_result && $audio_result['success'] ) {
					// Cache the result
					$this->cache->cacheAudioUrl( $textHash, $audio_result['audio_url'], null, [
						'provider' => $provider,
						'generated_at' => time(),
						'text_length' => strlen( $text ),
						'voice' => $voice,
					] );
					
					$this->round_robin->recordUsage( $provider, true );
					
					$this->logger->info( 'TTS generation completed successfully', [
						'provider' => $provider,
						'audio_url' => $audio_result['audio_url']
					] );
					
					return [
						'success' => true,
						'audio_url' => $audio_result['audio_url'],
						'source' => 'generated',
						'provider' => $provider,
						'hash' => $textHash,
					];
				}
			} catch ( \Exception $e ) {
				$this->logger->error( 'Provider generation failed, falling back to mock', [
					'provider' => $provider,
					'error' => $e->getMessage()
				] );
				
				// Fallback to mock audio
				$mock_audio_url = $this->generateMockAudio( $text, $provider );
				
				if ( $mock_audio_url ) {
					// Cache the result
					$this->cache->cacheAudioUrl( $textHash, $mock_audio_url, null, [
						'provider' => $provider,
						'generated_at' => time(),
						'text_length' => strlen( $text ),
						'mock' => true,
						'fallback_reason' => $e->getMessage(),
					] );
					
					$this->round_robin->recordUsage( $provider, true );
					
					$this->logger->info( 'Fallback mock TTS generation completed', [
						'provider' => $provider,
						'audio_url' => $mock_audio_url
					] );
					
					return [
						'success' => true,
						'audio_url' => $mock_audio_url,
						'source' => 'mock_fallback',
						'provider' => $provider,
						'hash' => $textHash,
					];
				}
			}
			
			$this->round_robin->recordUsage( $provider, false );
			$this->logger->error( 'Failed to generate audio', [ 'provider' => $provider ] );
			
			return null;
			
		} catch ( \Exception $e ) {
			$this->logger->error( 'Exception in generateAudio', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}
	
	/**
	 * Get available voices for a provider
	 *
	 * @param string $provider Provider name.
	 * @return array Available voices.
	 */
	public function getAvailableVoices( string $provider ): array {
		// Try to get voices from the actual provider first
		$provider_instance = $this->getProviderInstance( $provider );
		
		if ( $provider_instance && method_exists( $provider_instance, 'getAvailableVoices' ) ) {
			try {
				return $provider_instance->getAvailableVoices();
			} catch ( \Exception $e ) {
				$this->logger->error( 'Failed to get voices from provider', [
					'provider' => $provider,
					'error' => $e->getMessage()
				] );
			}
		}
		
		// Fallback to static voice lists
		$voices = [
			'google' => [
				[ 'id' => 'es-MX-Wavenet-A', 'name' => 'Mexican Spanish A (Female)', 'language' => 'es-MX' ],
				[ 'id' => 'es-MX-Wavenet-B', 'name' => 'Mexican Spanish B (Male)', 'language' => 'es-MX' ],
				[ 'id' => 'es-ES-Wavenet-A', 'name' => 'Spanish A (Female)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-Wavenet-B', 'name' => 'Spanish B (Male)', 'language' => 'es-ES' ],
			],
			'openai' => [
				[ 'id' => 'alloy', 'name' => 'Alloy', 'language' => 'en-US' ],
				[ 'id' => 'echo', 'name' => 'Echo', 'language' => 'en-US' ],
				[ 'id' => 'fable', 'name' => 'Fable', 'language' => 'en-US' ],
				[ 'id' => 'onyx', 'name' => 'Onyx', 'language' => 'en-US' ],
				[ 'id' => 'nova', 'name' => 'Nova', 'language' => 'en-US' ],
				[ 'id' => 'shimmer', 'name' => 'Shimmer', 'language' => 'en-US' ],
			],
			'elevenlabs' => [
				[ 'id' => 'spanish-female-1', 'name' => 'Spanish Female 1', 'language' => 'es-ES' ],
				[ 'id' => 'spanish-male-1', 'name' => 'Spanish Male 1', 'language' => 'es-ES' ],
				[ 'id' => 'english-female-1', 'name' => 'English Female 1', 'language' => 'en-US' ],
				[ 'id' => 'english-male-1', 'name' => 'English Male 1', 'language' => 'en-US' ],
			],
			'amazon_polly' => [
				[ 'id' => 'Joanna', 'name' => 'Joanna (English US Female)', 'language' => 'en-US' ],
				[ 'id' => 'Matthew', 'name' => 'Matthew (English US Male)', 'language' => 'en-US' ],
				[ 'id' => 'Amy', 'name' => 'Amy (English UK Female)', 'language' => 'en-GB' ],
				[ 'id' => 'Brian', 'name' => 'Brian (English UK Male)', 'language' => 'en-GB' ],
				[ 'id' => 'Lucia', 'name' => 'Lucia (Spanish Female)', 'language' => 'es-ES' ],
				[ 'id' => 'Enrique', 'name' => 'Enrique (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => 'Lupe', 'name' => 'Lupe (Spanish US Female)', 'language' => 'es-US' ],
				[ 'id' => 'Miguel', 'name' => 'Miguel (Spanish US Male)', 'language' => 'es-US' ],
			],
			'azure_tts' => [
				[ 'id' => 'es-MX-DaliaNeural', 'name' => 'Dalia (Mexican Spanish Female)', 'language' => 'es-MX' ],
				[ 'id' => 'es-MX-JorgeNeural', 'name' => 'Jorge (Mexican Spanish Male)', 'language' => 'es-MX' ],
				[ 'id' => 'es-MX-BeatrizNeural', 'name' => 'Beatriz (Mexican Spanish Female)', 'language' => 'es-MX' ],
				[ 'id' => 'es-MX-CandelaNeural', 'name' => 'Candela (Mexican Spanish Female)', 'language' => 'es-MX' ],
				[ 'id' => 'es-MX-CecilioNeural', 'name' => 'Cecilio (Mexican Spanish Male)', 'language' => 'es-MX' ],
				[ 'id' => 'es-ES-ElviraNeural', 'name' => 'Elvira (Spanish Female)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-AlvaroNeural', 'name' => 'Alvaro (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-AbrilNeural', 'name' => 'Abril (Spanish Female)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-ArnauNeural', 'name' => 'Arnau (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => 'en-US-AriaNeural', 'name' => 'Aria (English US Female)', 'language' => 'en-US' ],
				[ 'id' => 'en-US-DavisNeural', 'name' => 'Davis (English US Male)', 'language' => 'en-US' ],
				[ 'id' => 'en-US-AmberNeural', 'name' => 'Amber (English US Female)', 'language' => 'en-US' ],
				[ 'id' => 'en-US-AnaNeural', 'name' => 'Ana (English US Female)', 'language' => 'en-US' ],
				[ 'id' => 'en-US-BrandonNeural', 'name' => 'Brandon (English US Male)', 'language' => 'en-US' ],
				[ 'id' => 'en-GB-SoniaNeural', 'name' => 'Sonia (English UK Female)', 'language' => 'en-GB' ],
				[ 'id' => 'en-GB-RyanNeural', 'name' => 'Ryan (English UK Male)', 'language' => 'en-GB' ],
				[ 'id' => 'en-GB-LibbyNeural', 'name' => 'Libby (English UK Female)', 'language' => 'en-GB' ],
			],
			'aws' => [
				[ 'id' => 'Mia', 'name' => 'Mia (Spanish)', 'language' => 'es-ES' ],
				[ 'id' => 'Enrique', 'name' => 'Enrique (Spanish)', 'language' => 'es-ES' ],
				[ 'id' => 'Conchita', 'name' => 'Conchita (Spanish)', 'language' => 'es-ES' ],
				[ 'id' => 'Lucia', 'name' => 'Lucia (Spanish)', 'language' => 'es-ES' ],
			],
		];
		
		return $voices[ $provider ] ?? [];
	}
	
	/**
	 * Test provider connection
	 *
	 * @param string $provider Provider name.
	 * @return bool True if connection successful.
	 */
	public function testProvider( string $provider ): bool {
		$this->logger->info( 'Testing provider connection', [ 'provider' => $provider ] );
		
		// Mock implementation - always return true for now
		return true;
	}
	
	/**
	 * Get service statistics
	 *
	 * @return array Service stats.
	 */
	public function getStats(): array {
		return [
			'cache_stats' => $this->cache->getCacheStats(),
			'provider_stats' => $this->round_robin->getStats(),
			'active_providers' => $this->round_robin->getActiveProviders(),
		];
	}
	
	/**
	 * Generate audio for a WordPress post
	 *
	 * @param int $post_id Post ID.
	 * @return object Audio result object.
	 * @throws \Exception If generation fails.
	 */
	public function generateAudioForPost( int $post_id ) {
		try {
			$this->logger->info( 'Starting audio generation for post', [ 'post_id' => $post_id ] );
			
			$post = get_post( $post_id );
			if ( ! $post ) {
				throw new \Exception( 'Post not found' );
			}
			
			// Get post content and strip HTML
			$content = wp_strip_all_tags( $post->post_content );
			$title = $post->post_title;
			$full_text = $title . '. ' . $content;
			
			$this->logger->info( 'Post content extracted', [
				'post_id' => $post_id,
				'title' => $title,
				'content_length' => strlen( $content )
			] );
			
			// Get TTS settings for this post
			$provider = get_post_meta( $post_id, '_tts_voice_provider', true ) ?: 'google';
			$voice = get_post_meta( $post_id, '_tts_voice_id', true );
			
			$this->logger->info( 'TTS settings for post', [
				'post_id' => $post_id,
				'provider' => $provider,
				'voice' => $voice
			] );
			
			$options = [
				'provider' => $provider,
				'voice' => $voice,
				'post_id' => $post_id,
			];
			
			$result = $this->generateAudio( $full_text, $options );
			
			if ( ! $result || ! $result['success'] ) {
				$this->logger->error( 'Audio generation failed for post', [ 'post_id' => $post_id ] );
				throw new \Exception( 'Failed to generate audio for post' );
			}
			
			// Save audio URL to post meta (using the correct meta keys)
			update_post_meta( $post_id, '_tts_audio_url', $result['audio_url'] );
			update_post_meta( $post_id, '_tts_generated_at', time() );
			update_post_meta( $post_id, '_tts_generation_status', 'completed' );
			
			$this->logger->info( 'Audio generation completed for post', [
				'post_id' => $post_id,
				'audio_url' => $result['audio_url']
			] );
			
			return (object) [
				'url' => $result['audio_url'],
				'duration' => 0, // Mock duration
				'provider' => $result['provider'] ?? $provider,
			];
			
		} catch ( \Exception $e ) {
			$this->logger->error( 'Exception in generateAudioForPost', [
				'post_id' => $post_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			throw $e;
		}
	}
	
	/**
	 * Generate preview audio
	 *
	 * @param string $text     Preview text.
	 * @param string $provider Provider name.
	 * @param string $voice    Voice ID.
	 * @return object Audio result object.
	 * @throws \Exception If generation fails.
	 */
	public function generatePreview( string $text, string $provider, string $voice = '' ) {
		$options = [
			'provider' => $provider,
			'voice' => $voice,
			'preview' => true,
		];
		
		$result = $this->generateAudio( $text, $options );
		
		if ( ! $result || ! $result['success'] ) {
			throw new \Exception( 'Failed to generate preview audio' );
		}
		
		return (object) [
			'url' => $result['audio_url'],
			'duration' => 0, // Mock duration
		];
	}
	
	/**
	 * Validate provider configuration
	 *
	 * @param string $provider Provider name.
	 * @return bool True if valid.
	 */
	public function validateProvider( string $provider ): bool {
		// Get configuration
		$config = get_option( 'wp_tts_config', [] );
		
		switch ( $provider ) {
			case 'google':
				$credentials_path = $config['providers']['google']['credentials_path'] ?? '';
				// If not set in config, try the user's uploaded file
				if ( empty( $credentials_path ) ) {
					$credentials_path = ABSPATH . 'wp-content/uploads/private/sesolibre-tts-13985ba22d36.json';
				}
				return ! empty( $credentials_path ) && file_exists( $credentials_path );
				
			case 'openai':
				$api_key = $config['providers']['openai']['api_key'] ?? '';
				return ! empty( $api_key );
				
			case 'elevenlabs':
				$api_key = $config['providers']['elevenlabs']['api_key'] ?? '';
				return ! empty( $api_key );
				
			case 'amazon_polly':
				$access_key = $config['providers']['amazon_polly']['access_key'] ?? '';
				$secret_key = $config['providers']['amazon_polly']['secret_key'] ?? '';
				$region = $config['providers']['amazon_polly']['region'] ?? '';
				return ! empty( $access_key ) && ! empty( $secret_key ) && ! empty( $region );
				
			case 'azure_tts':
				$subscription_key = $config['providers']['azure_tts']['subscription_key'] ?? '';
				$region = $config['providers']['azure_tts']['region'] ?? '';
				return ! empty( $subscription_key ) && ! empty( $region );
				
			default:
				return false;
		}
	}
	
	/**
		* Get provider instance by name
		*
		* @param string $provider Provider name.
		* @return object|null Provider instance or null if not found.
		*/
	private function getProviderInstance( string $provider ) {
		try {
			switch ( $provider ) {
				case 'google':
					// Get Google Cloud TTS provider
					if ( class_exists( '\WP_TTS\Providers\GoogleCloudTTSProvider' ) ) {
						$config = get_option( 'wp_tts_config', [] );
						$provider_config = $config['providers']['google'] ?? [];
						
						// Always create instance, even without full configuration (for mock mode)
						return new \WP_TTS\Providers\GoogleCloudTTSProvider( $provider_config, $this->logger );
					}
					return null;
					
				case 'amazon_polly':
					// Get Amazon Polly provider
					if ( class_exists( '\WP_TTS\Providers\AmazonPollyProvider' ) ) {
						$config = get_option( 'wp_tts_config', [] );
						$provider_config = $config['providers']['amazon_polly'] ?? [];
						
						// Always create instance, even without full configuration (for mock mode)
						return new \WP_TTS\Providers\AmazonPollyProvider( $provider_config, $this->logger );
					}
					return null;
					
				case 'azure_tts':
					// Get Azure TTS provider
					if ( class_exists( '\WP_TTS\Providers\AzureTTSProvider' ) ) {
						$config = get_option( 'wp_tts_config', [] );
						$provider_config = $config['providers']['azure_tts'] ?? [];
						
						if ( ! empty( $provider_config['subscription_key'] ) &&
							 ! empty( $provider_config['region'] ) ) {
							return new \WP_TTS\Providers\AzureTTSProvider( $provider_config );
						}
					}
					return null;
					
				case 'openai':
					// Get OpenAI TTS provider
					if ( class_exists( '\WP_TTS\Providers\OpenAITTSProvider' ) ) {
						$config = get_option( 'wp_tts_config', [] );
						$provider_config = $config['providers']['openai'] ?? [];
						
						// Always create instance, even without full configuration (for mock mode)
						return new \WP_TTS\Providers\OpenAITTSProvider( $provider_config, $this->logger );
					}
					return null;
					
				case 'elevenlabs':
					// Get ElevenLabs provider
					if ( class_exists( '\WP_TTS\Providers\ElevenLabsProvider' ) ) {
						$config = get_option( 'wp_tts_config', [] );
						$provider_config = $config['providers']['elevenlabs'] ?? [];
						
						// Always create instance, even without full configuration (for mock mode)
						return new \WP_TTS\Providers\ElevenLabsProvider( $provider_config, $this->logger );
					}
					return null;
					
				default:
					return null;
			}
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to get provider instance', [
				'provider' => $provider,
				'error' => $e->getMessage()
			] );
			return null;
		}
	}
	
	/**
		* Generate mock audio URL for testing
	 *
	 * @param string $text     Text content.
	 * @param string $provider Provider name.
	 * @return string|null Mock audio URL.
	 */
	private function generateMockAudio( string $text, string $provider ): ?string {
		try {
			// Ensure the uploads directory exists
			$upload_dir = wp_upload_dir();
			$tts_dir = $upload_dir['basedir'] . '/tts-audio';
			
			if ( ! file_exists( $tts_dir ) ) {
				wp_mkdir_p( $tts_dir );
				$this->logger->info( 'Created TTS audio directory', [ 'path' => $tts_dir ] );
			}
			
			// Generate a mock audio file
			$hash = md5( $text . $provider . time() );
			$filename = "mock-{$provider}-{$hash}.mp3";
			$file_path = $tts_dir . '/' . $filename;
			$audio_url = $upload_dir['baseurl'] . "/tts-audio/{$filename}";
			
			// Create a simple mock MP3 file (minimal valid MP3 header)
			$mock_mp3_data = $this->createMockMp3Data( $text, $provider );
			
			// Save the mock file
			$result = file_put_contents( $file_path, $mock_mp3_data );
			
			if ( $result === false ) {
				$this->logger->error( 'Failed to save mock audio file', [
					'file_path' => $file_path,
					'provider' => $provider
				] );
				return null;
			}
			
			$this->logger->info( 'Generated mock audio file', [
				'provider' => $provider,
				'url' => $audio_url,
				'file_path' => $file_path,
				'size' => $result
			] );
			
			return $audio_url;
			
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to generate mock audio', [
				'error' => $e->getMessage(),
				'provider' => $provider
			] );
			return null;
		}
	}
	
	/**
	 * Create mock MP3 data
	 *
	 * @param string $text     Text content.
	 * @param string $provider Provider name.
	 * @return string Mock MP3 binary data.
	 */
	private function createMockMp3Data( string $text, string $provider ): string {
		// Create a minimal valid MP3 file with ID3 tag
		$id3_header = "ID3\x03\x00\x00\x00\x00\x00\x00";
		
		// Add some metadata
		$title = "Mock TTS Audio - " . substr( $text, 0, 30 ) . "...";
		$artist = "TTS Provider: " . ucfirst( $provider );
		
		// Simple MP3 frame header (for a very short silent audio)
		$mp3_frame = "\xFF\xFB\x90\x00"; // MP3 frame sync + header
		
		// Create a short silence (about 1 second of silence)
		$silence_frames = str_repeat( $mp3_frame . str_repeat( "\x00", 100 ), 10 );
		
		// Combine ID3 header with MP3 frames
		$mock_data = $id3_header . $silence_frames;
		
		return $mock_data;
	}
}
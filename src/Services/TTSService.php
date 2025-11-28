<?php
/**
 * TTS Service main class
 *
 * @package WP_TTS
 */

namespace WP_TTS\Services;

use WP_TTS\Utils\Logger;
use WP_TTS\Utils\TTSMetaManager;
use WP_TTS\Interfaces\CacheServiceInterface;
use WP_TTS\Core\StorageProviderFactory;
use WP_TTS\Core\ConfigurationManager;

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
	 * Storage provider factory
	 *
	 * @var StorageProviderFactory
	 */
	private $storage_factory;
	
	/**
	 * Configuration manager
	 *
	 * @var ConfigurationManager
	 */
	private $config_manager;
	
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
		$this->config_manager = new ConfigurationManager();
		$this->storage_factory = new StorageProviderFactory( $this->config_manager );
	}
	
	/**
	 * Check rate limiting for audio generation
	 *
	 * @param int $user_id User ID (0 for anonymous).
	 * @return bool|array True if allowed, array with error info if rate limited.
	 */
	private function checkRateLimit( int $user_id = 0 ): bool|array {
		$user_id = $user_id ?: get_current_user_id();
		$rate_key = 'wp_tts_rate_' . $user_id;
		$rate_data = get_transient( $rate_key );

		// Rate limit configuration
		$max_requests = apply_filters( 'wp_tts_rate_limit_max', 10 ); // 10 requests
		$time_window = apply_filters( 'wp_tts_rate_limit_window', 60 ); // per 60 seconds

		if ( false === $rate_data ) {
			// First request
			set_transient( $rate_key, [ 'count' => 1, 'start' => time() ], $time_window );
			return true;
		}

		$count = $rate_data['count'] ?? 0;
		$start = $rate_data['start'] ?? time();

		// Check if we're still in the same time window
		if ( ( time() - $start ) < $time_window ) {
			if ( $count >= $max_requests ) {
				$remaining = $time_window - ( time() - $start );
				return [
					'limited' => true,
					'message' => sprintf(
						__( 'Límite de solicitudes excedido. Por favor espera %d segundos.', 'wp-tts-sesolibre' ),
						$remaining
					),
					'retry_after' => $remaining
				];
			}
			// Increment count
			set_transient( $rate_key, [ 'count' => $count + 1, 'start' => $start ], $time_window - ( time() - $start ) );
		} else {
			// Time window expired, reset
			set_transient( $rate_key, [ 'count' => 1, 'start' => time() ], $time_window );
		}

		return true;
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
			// Check rate limiting first
			$rate_check = $this->checkRateLimit();
			if ( is_array( $rate_check ) && isset( $rate_check['limited'] ) && $rate_check['limited'] ) {
				$this->logger->warning( 'Rate limit exceeded for user', [ 'user_id' => get_current_user_id() ] );
				return [
					'success' => false,
					'message' => $rate_check['message'],
					'error_code' => 'RATE_LIMITED',
					'retry_after' => $rate_check['retry_after']
				];
			}

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
			
			$current_provider_name = null;
			$provider_instance = null;

			// 1. Try provider from $options (post settings) - HIGHEST PRIORITY
			if ( ! empty( $options['provider'] ) ) {
				$this->logger->info( '[generateAudio] Using provider from post options', [ 'provider' => $options['provider'] ] );
				$instance = $this->getProviderInstance( $options['provider'] );
				if ( $instance ) {
					$current_provider_name = $options['provider'];
					$provider_instance = $instance;
					$this->logger->info( '[generateAudio] Successfully set provider from post options', [ 'provider' => $current_provider_name ] );
				} else {
					$this->logger->warn( '[generateAudio] Provider from post options FAILED to instantiate', [ 'provider' => $options['provider'] ] );
				}
			}

			// 2. If no provider from options, use the DEFAULT provider (NO Round Robin)
			if ( ! $provider_instance ) {
				$config = get_option( 'wp_tts_config', [] );
				$default_provider = $config['default_provider'] ?? 'google';
				
				$this->logger->info( '[generateAudio] No provider specified, using default provider', [ 'default_provider' => $default_provider ] );
				$instance = $this->getProviderInstance( $default_provider );
				if ( $instance ) {
					$current_provider_name = $default_provider;
					$provider_instance = $instance;
					$this->logger->info( '[generateAudio] Successfully set default provider', [ 'provider' => $current_provider_name ] );
				} else {
					$this->logger->error( '[generateAudio] Default provider FAILED to instantiate', [ 'default_provider' => $default_provider ] );
				}
			}
			
			if ( ! $current_provider_name || ! $provider_instance ) {
				$this->logger->error( 'No TTS providers are configured and available for audio generation.' );
				return [
					'success' => false,
					'message' => __( 'No hay proveedores TTS configurados. Por favor configure al menos un proveedor en Configuración > Configuración TTS.', 'wp-tts-sesolibre' ),
					'error_code' => 'NO_PROVIDERS_CONFIGURED',
					'available_providers' => [
						'openai' => 'OpenAI TTS',
						'google' => 'Google Cloud TTS',
						'elevenlabs' => 'ElevenLabs',
						'amazon_polly' => 'Amazon Polly',
						'azure_tts' => 'Microsoft Azure TTS'
					]
				];
			}
			
			$this->logger->info( '[generateAudio] Final provider selected for speech generation', [
				'current_provider_name' => $current_provider_name,
				'provider_instance_class' => is_object($provider_instance) ? get_class($provider_instance) : 'N/A'
			] );
			
			try {
				$speech_call_options = [];
				if ( isset( $options['voice'] ) ) {
					$speech_call_options['voice'] = $options['voice'];
				}
				// Providers' generateSpeech methods should handle $speech_call_options['voice'] being empty or absent by using their defaults.
				$this->logger->debug( '[generateAudio] About to call generateSpeech method on provider', [
					'provider' => $current_provider_name,
					'speech_options' => $speech_call_options
				]);

				// Generate audio using provider (this might return raw audio data or a URL)
				$audio_result = $provider_instance->generateSpeech( $text, $speech_call_options );
				
				if ( $audio_result && isset($audio_result['success']) && $audio_result['success'] ) {
					// If provider returned raw audio data, store it using storage provider
					if ( isset($audio_result['audio_data']) && !isset($audio_result['audio_url']) ) {
						$this->logger->info( 'Provider returned raw audio data, storing with storage provider' );
						
						try {
							$this->logger->info( 'Attempting to get storage provider from factory' );
							$storage_provider = $this->storage_factory->getEnabledProvider();
							$this->logger->info( 'Storage provider obtained successfully', [
								'provider_class' => get_class($storage_provider)
							]);
							
							// Generate filename
							$hash = md5( $text . $current_provider_name . time() );
							$voice_suffix = isset($speech_call_options['voice']) ? '-' . $speech_call_options['voice'] : '';
							$filename = "{$current_provider_name}{$voice_suffix}-{$hash}.mp3";
							
							// Prepare metadata including post information
							$metadata = [
								'provider' => $current_provider_name,
								'voice' => $speech_call_options['voice'] ?? null,
								'text_length' => strlen( $text ),
								'generated_at' => current_time( 'mysql' )
							];

							// Add post metadata if available from options
							if ( isset($options['post_id']) ) {
								$post_id = $options['post_id'];
								$post = get_post( $post_id );
								
								if ( $post ) {
									$metadata['post_id'] = $post_id;
									$metadata['post_title'] = $post->post_title;
									$metadata['post_url'] = get_permalink( $post_id );
									$metadata['permalink'] = get_permalink( $post_id );
									
									// Get featured image
									$featured_image_url = get_the_post_thumbnail_url( $post_id, 'large' );
									if ( $featured_image_url ) {
										$metadata['featured_image_url'] = $featured_image_url;
									}
									
									// Add post excerpt as description if available
									if ( !empty($post->post_excerpt) ) {
										$metadata['description'] = $post->post_excerpt;
									}
									
									$this->logger->info( 'Added post metadata for storage', [
										'post_id' => $post_id,
										'post_title' => $post->post_title,
										'has_featured_image' => !empty($featured_image_url)
									]);
								}
							}

							// Store audio using storage provider
							$storage_result = $storage_provider->store( 
								$audio_result['audio_data'], 
								$filename,
								$metadata
							);
							
							$audio_result['audio_url'] = $storage_result['url'];
							$audio_result['storage_provider'] = $storage_provider->getName();
							
							$this->logger->info( 'Audio stored successfully', [
								'storage_provider' => $storage_provider->getName(),
								'audio_url' => $audio_result['audio_url']
							]);
							
						} catch ( \Exception $storage_error ) {
							$this->logger->error( 'Failed to store audio with storage provider', [
								'error' => $storage_error->getMessage(),
								'error_type' => get_class($storage_error),
								'error_trace' => $storage_error->getTraceAsString(),
								'provider' => $current_provider_name
							]);
							
							// Try fallback to local storage if primary storage fails
							$this->logger->info( 'Attempting fallback to local storage' );
							try {
								$local_storage = $this->storage_factory->getProvider( 'local' );
								$this->logger->info( 'Local storage provider obtained for fallback' );
								
								$fallback_result = $local_storage->store( 
									$audio_result['audio_data'], 
									$filename,
									$metadata
								);
								
								$audio_result['audio_url'] = $fallback_result['url'];
								$audio_result['storage_provider'] = $local_storage->getName();
								
								$this->logger->info( 'Audio stored successfully with local storage fallback', [
									'original_provider' => $storage_provider->getName(),
									'fallback_provider' => $local_storage->getName(),
									'audio_url' => $audio_result['audio_url']
								]);
								
							} catch ( \Exception $fallback_error ) {
								$this->logger->error( 'Local storage fallback also failed', [
									'original_error' => $storage_error->getMessage(),
									'fallback_error' => $fallback_error->getMessage()
								]);
								
								// Return error if both storage methods fail
								return [
									'success' => false,
									'message' => sprintf( 
										__( 'La generación TTS fue exitosa pero falló el almacenamiento principal (%s) y el de respaldo (%s)', 'wp-tts-sesolibre' ),
										$storage_error->getMessage(),
										$fallback_error->getMessage()
									),
									'error_code' => 'STORAGE_FAILED',
									'provider' => $current_provider_name
								];
							}
						}
					}
					$this->cache->cacheAudioUrl( $textHash, $audio_result['audio_url'], null, [
						'provider' => $current_provider_name,
						'generated_at' => time(),
						'text_length' => strlen( $text ),
						'voice' => $speech_call_options['voice'] ?? null, // Log the voice used or attempted
					] );
					
					// Success - no round robin tracking needed
					
					$this->logger->info( 'TTS generation completed successfully', [
						'provider' => $current_provider_name,
						'audio_url' => $audio_result['audio_url'],
					] );
					
					return [
						'success' => true,
						'audio_url' => $audio_result['audio_url'],
						'source' => 'generated',
						'provider' => $current_provider_name,
						'voice' => $speech_call_options['voice'] ?? null,
						'hash' => $textHash,
					];
				} else {
					// Handle cases where generateSpeech returns success=false or an unexpected result
					$this->logger->error( 'Provider generateSpeech did not return success', [
						'provider' => $current_provider_name,
						'result' => $audio_result,
					]);
					// Fall through to record usage as failure, then to potential mock fallback by exception or explicit call
				}
			} catch ( \Exception $e ) {
				$this->logger->error( '[generateAudio] Exception during provider generation.', [
					'provider_at_exception' => $current_provider_name,
					'error' => $e->getMessage(),
					'exception_class' => get_class($e),
					'trace' => $e->getTraceAsString()
				] );
				
				// Record failure - no round robin tracking needed
				
				return [
					'success' => false,
					'message' => sprintf( 
						__( 'La generación TTS falló con %s: %s', 'wp-tts-sesolibre' ),
						$current_provider_name,
						$e->getMessage()
					),
					'error_code' => 'PROVIDER_EXCEPTION',
					'provider' => $current_provider_name
				];
			}
			
			// If we reach here, it means generation failed 
			$this->logger->error( 'Failed to generate audio with provider', [ 'provider' => $current_provider_name ] );
			
			return [
				'success' => false,
				'message' => sprintf( 
					__( 'La generación TTS falló con el proveedor %s. Por favor verifique su configuración e intente nuevamente.', 'wp-tts-sesolibre' ),
					$current_provider_name
				),
				'error_code' => 'GENERATION_FAILED',
				'provider' => $current_provider_name
			];
			
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
		$this->logger->info( '[getAvailableVoices] Fetching voices for provider', [ 'provider' => $provider ] );
		
		// Try to get voices from the actual provider first
		$provider_instance = $this->getProviderInstance( $provider );
		
		if ( $provider_instance && method_exists( $provider_instance, 'getAvailableVoices' ) ) {
			$this->logger->info( '[getAvailableVoices] Provider instance found, trying API voices', [ 'provider' => $provider ] );
			try {
				$api_voices = $provider_instance->getAvailableVoices();
				if ( !empty($api_voices) ) {
					$this->logger->info( '[getAvailableVoices] Got API voices successfully', [ 
						'provider' => $provider, 
						'count' => count($api_voices) 
					] );
					return $api_voices;
				}
				$this->logger->info( '[getAvailableVoices] API returned empty voices, falling back to static', [ 'provider' => $provider ] );
			} catch ( \Exception $e ) {
				$this->logger->error( '[getAvailableVoices] Failed to get voices from provider API', [
					'provider' => $provider,
					'error' => $e->getMessage()
				] );
			}
		} else {
			$this->logger->info( '[getAvailableVoices] No provider instance or method, using static list', [ 
				'provider' => $provider,
				'instance_exists' => !!$provider_instance,
				'method_exists' => $provider_instance ? method_exists($provider_instance, 'getAvailableVoices') : false
			] );
		}
		
		// Fallback to static voice lists
		$voices = [
			'google' => [
				[ 'id' => 'es-MX-Wavenet-A', 'name' => 'Mexican Spanish A (Female)', 'language' => 'es-MX' ],
				[ 'id' => 'es-MX-Wavenet-B', 'name' => 'Mexican Spanish B (Male)', 'language' => 'es-MX' ],
				[ 'id' => 'es-ES-Wavenet-A', 'name' => 'Spanish A (Female)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-Wavenet-B', 'name' => 'Spanish B (Male)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-Wavenet-C', 'name' => 'Spanish C (Female)', 'language' => 'es-ES' ],
				[ 'id' => 'es-ES-Wavenet-D', 'name' => 'Spanish D (Female)', 'language' => 'es-ES' ],
			],
			'openai' => [
				[ 'id' => 'alloy', 'name' => 'Alloy (Neutral, Multilingual)', 'language' => 'es-ES' ],
				[ 'id' => 'echo', 'name' => 'Echo (Male, Multilingual)', 'language' => 'es-ES' ],
				[ 'id' => 'fable', 'name' => 'Fable (British Male, Multilingual)', 'language' => 'es-ES' ],
				[ 'id' => 'onyx', 'name' => 'Onyx (Deep Male, Multilingual)', 'language' => 'es-ES' ],
				[ 'id' => 'nova', 'name' => 'Nova (Female, Multilingual)', 'language' => 'es-ES' ],
				[ 'id' => 'shimmer', 'name' => 'Shimmer (Soft Female, Multilingual)', 'language' => 'es-ES' ],
			],
			'elevenlabs' => [
				[ 'id' => 'EXAVITQu4vr4xnSDxMaL', 'name' => 'Bella (Spanish Female)', 'language' => 'es-ES' ],
				[ 'id' => 'pNInz6obpgDQGcFmaJgB', 'name' => 'Adam (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => 'TxGEqnHWrfWFTfGW9XjX', 'name' => 'Josh (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => 'VR6AewLTigWG4xSOukaG', 'name' => 'Arnold (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => 'MF3mGyEYCl7XYWbV9V6O', 'name' => 'Elli (Spanish Female)', 'language' => 'es-ES' ],
				[ 'id' => 'XrExE9yKIg1WjnnlVkGX', 'name' => 'Matilda (Spanish Female)', 'language' => 'es-ES' ],
				[ 'id' => 'ErXwobaYiN019PkySvjV', 'name' => 'Antoni (Spanish Male)', 'language' => 'es-ES' ],
				[ 'id' => '21m00Tcm4TlvDq8ikWAM', 'name' => 'Rachel (English Female)', 'language' => 'en-US' ],
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
		
		$static_voices = $voices[ $provider ] ?? [];
		$this->logger->info( '[getAvailableVoices] Returning static voices', [ 
			'provider' => $provider, 
			'count' => count($static_voices),
			'sample' => array_slice($static_voices, 0, 2)
		] );
		
		return $static_voices;
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
		$config = get_option( 'wp_tts_config', [] );
		$default_provider = $config['default_provider'] ?? 'google';
		
		// Get list of configured providers
		$configured_providers = [];
		$all_providers = ['google', 'openai', 'elevenlabs', 'azure_tts', 'amazon_polly'];
		foreach ($all_providers as $provider) {
			if ($this->validateProvider($provider)) {
				$configured_providers[] = $provider;
			}
		}
		
		return [
			'cache_stats' => $this->cache->getCacheStats(),
			'default_provider' => $default_provider,
			'configured_providers' => $configured_providers,
			'round_robin_disabled' => true,
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
			
			// Check for custom audio first - if present, use it instead of generating TTS
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$tts_data = TTSMetaManager::getTTSData( $post_id );
				$custom_audio_id = $tts_data['audio_assets']['custom_audio'] ?? '';
				
				if ( !empty( $custom_audio_id ) ) {
					$custom_audio_url = wp_get_attachment_url( $custom_audio_id );
					if ( $custom_audio_url ) {
						// Use custom audio instead of generating TTS
						$this->logger->info( 'Using custom audio instead of TTS generation', [
							'post_id' => $post_id,
							'custom_audio_id' => $custom_audio_id,
							'custom_audio_url' => $custom_audio_url
						] );
						
						// Update audio metadata with custom audio info
						TTSMetaManager::updateTTSSection( $post_id, 'audio', [
							'url' => $custom_audio_url,
							'generated_at' => current_time( 'mysql' ),
							'status' => 'completed',
							'duration' => 0,
							'format' => 'custom'
						] );
						
						return (object) [
							'url' => $custom_audio_url,
							'duration' => 0,
							'provider' => 'custom_upload',
							'source' => 'custom_audio'
						];
					}
				}
			}

			// Get TTS settings for this post - with fallback to old system
			$provider_from_meta = '';
			$voice = '';
			$voice_config = [];
			$custom_text_config = [];
			
			try {
				if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
					// Use new unified system
					$voice_config = TTSMetaManager::getVoiceConfig( $post_id );
					$custom_text_config = TTSMetaManager::getCustomTextConfig( $post_id );
					
					$this->logger->info( 'Raw voice_config from TTSMetaManager', [
						'post_id' => $post_id,
						'voice_config' => $voice_config
					] );
					
					$provider_from_meta = $voice_config['provider'] ?? '';
					$voice = $voice_config['voice_id'] ?? '';
					
					$this->logger->info( 'Using unified metadata system', [
						'post_id' => $post_id,
						'provider' => $provider_from_meta,
						'voice' => $voice,
						'raw_voice_config' => $voice_config
					] );
				} else {
					// Fallback to old system
					$provider_from_meta = get_post_meta( $post_id, '_tts_voice_provider', true );
					$voice = get_post_meta( $post_id, '_tts_voice_id', true );
					$custom_text = get_post_meta( $post_id, '_tts_custom_text', true );
					
					$voice_config = [
						'provider' => $provider_from_meta,
						'voice_id' => $voice,
						'language' => 'es-MX'
					];
					
					$custom_text_config = [
						'custom_text' => $custom_text,
						'use_custom_text' => !empty($custom_text)
					];
					
					$this->logger->info( 'Using fallback old metadata system', [
						'post_id' => $post_id,
						'provider' => $provider_from_meta,
						'voice' => $voice
					] );
				}
				
				// Check if should use custom text instead of post content
				// Priority order: 1) Edited text, 2) Custom text, 3) Original post content
				$edited_text = '';
				$fallback_edited_text = get_post_meta( $post_id, '_tts_edited_text', true );
				$use_edited_text = get_post_meta( $post_id, '_tts_use_edited_text', true );
				
				$this->logger->info( 'DEBUG: Checking for edited text', [
					'post_id' => $post_id,
					'fallback_edited_text_length' => strlen($fallback_edited_text),
					'use_edited_text' => $use_edited_text ? 'TRUE' : 'FALSE',
					'custom_text_config' => $custom_text_config
				] );
				
				// First check for edited text (highest priority)
				if ( !empty($custom_text_config['use_custom_text']) && !empty($custom_text_config['custom_text']) ) {
					$edited_text = $custom_text_config['custom_text'];
					$this->logger->info( 'Using edited text from unified system for TTS generation', [
						'post_id' => $post_id,
						'edited_text_length' => strlen( $edited_text )
					] );
				} elseif ( !empty($fallback_edited_text) && $use_edited_text ) {
					$edited_text = $fallback_edited_text;
					$this->logger->info( 'Using edited text from fallback system for TTS generation', [
						'post_id' => $post_id,
						'edited_text_length' => strlen( $edited_text )
					] );
				} else {
					$this->logger->info( 'No edited text found, will use original content', [
						'post_id' => $post_id,
						'fallback_edited_text_empty' => empty($fallback_edited_text),
						'use_edited_text_false' => !$use_edited_text,
						'custom_text_config_empty' => empty($custom_text_config['custom_text'])
					] );
				}
				
				// Use edited text if available, otherwise use original post content
				if ( !empty($edited_text) ) {
					$full_text = $edited_text;
					$this->logger->info( 'Using edited text for TTS generation', [
						'post_id' => $post_id,
						'text_length' => strlen( $full_text ),
						'text_source' => 'edited'
					] );
				} else {
					$this->logger->info( 'Using original post content for TTS generation', [
						'post_id' => $post_id,
						'text_length' => strlen( $full_text ),
						'text_source' => 'original'
					] );
				}
				
			} catch ( \Exception $e ) {
				$this->logger->error( 'Error getting TTS settings, using fallback', [
					'post_id' => $post_id,
					'error' => $e->getMessage()
				] );
				
				// Fallback to old system
				$provider_from_meta = get_post_meta( $post_id, '_tts_voice_provider', true );
				$voice = get_post_meta( $post_id, '_tts_voice_id', true );
				
				$voice_config = [
					'provider' => $provider_from_meta,
					'voice_id' => $voice,
					'language' => 'es-MX'
				];
			}
			
			$this->logger->info( 'TTS settings for post', [
				'post_id' => $post_id,
				'provider' => $provider_from_meta,
				'voice' => $voice,
				'using_custom_text' => !empty($custom_text_config['use_custom_text'])
			] );
			
			$options = [
				'provider' => $provider_from_meta, // Pass the potentially empty provider to generateAudio
				'voice' => $voice,
				'post_id' => $post_id,
			];
			
			$result = $this->generateAudio( $full_text, $options );
			
			if ( ! $result || ! $result['success'] ) {
				$this->logger->error( 'Audio generation failed for post', [ 'post_id' => $post_id ] );
				throw new \Exception( 'Failed to generate audio for post' );
			}
			
			// Check for intro/outro and combine audio if needed
			$result = $this->processIntroOutroAudio( $post_id, $result );
			
			// Save audio URL and generation details using unified system
			try {
				$this->logger->info( 'Saving TTS metadata using unified system', [
					'post_id' => $post_id,
					'audio_url' => $result['audio_url']
				] );
				
				// Check if TTSMetaManager class exists
				if ( ! class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
					$this->logger->error( 'TTSMetaManager class not found, falling back to old system' );
					// Fallback to old system
					update_post_meta( $post_id, '_tts_audio_url', $result['audio_url'] );
					update_post_meta( $post_id, '_tts_generated_at', time() );
					update_post_meta( $post_id, '_tts_generation_status', 'completed' );
					
					if ( isset( $result['provider'] ) ) {
						update_post_meta( $post_id, '_tts_voice_provider', $result['provider'] );
					}
					if ( isset( $result['voice'] ) ) {
						update_post_meta( $post_id, '_tts_voice_id', $result['voice'] );
					}
				} else {
					$this->logger->info( 'TTSMetaManager class found, using unified system' );
					
					// Use new unified system with detailed logging
					try {
						$this->logger->info( 'Calling TTSMetaManager::setAudioInfo', [
							'post_id' => $post_id,
							'audio_url' => $result['audio_url'],
							'metadata' => [
								'status' => 'completed',
								'duration' => $result['duration'] ?? 0,
								'format' => 'mp3'
							]
						] );
						
						$audio_result = TTSMetaManager::setAudioInfo( $post_id, $result['audio_url'], [
							'status' => 'completed',
							'duration' => $result['duration'] ?? 0,
							'format' => 'mp3'
						] );
						
						$this->logger->info( 'TTSMetaManager::setAudioInfo result', [
							'result' => $audio_result
						] );
						
						// Update voice config with actual provider and voice used
						if ( isset( $result['provider'] ) ) {
							$this->logger->info( 'Calling TTSMetaManager::setVoiceConfig', [
								'post_id' => $post_id,
								'provider' => $result['provider'],
								'voice' => $result['voice'] ?? $voice,
								'language' => $voice_config['language'] ?? 'es-MX'
							] );
							
							$voice_result = TTSMetaManager::setVoiceConfig( 
								$post_id, 
								$result['provider'], 
								$result['voice'] ?? $voice,
								$voice_config['language'] ?? 'es-MX'
							);
							
							$this->logger->info( 'TTSMetaManager::setVoiceConfig result', [
								'result' => $voice_result
							] );
						}
						
						// Record successful generation attempt
						$this->logger->info( 'Calling TTSMetaManager::recordGenerationAttempt' );
						$generation_result = TTSMetaManager::recordGenerationAttempt( 
							$post_id, 
							true, 
							'', 
							0 // We'll calculate this later if needed
						);
						
						$this->logger->info( 'TTSMetaManager::recordGenerationAttempt result', [
							'result' => $generation_result
						] );
						
						// Mark content as processed (not modified)
						$this->logger->info( 'Calling TTSMetaManager::markContentModified' );
						$content_result = TTSMetaManager::markContentModified( $post_id, $content );
						
						$this->logger->info( 'TTSMetaManager::markContentModified result', [
							'result' => $content_result
						] );
						
					} catch ( \Throwable $meta_error ) {
						$this->logger->error( 'Error in TTSMetaManager operations', [
							'error' => $meta_error->getMessage(),
							'trace' => $meta_error->getTraceAsString(),
							'file' => $meta_error->getFile(),
							'line' => $meta_error->getLine()
						] );
						throw $meta_error; // Re-throw to trigger outer catch
					}
				}
				
				$this->logger->info( 'TTS metadata saved successfully', [ 'post_id' => $post_id ] );
				
			} catch ( \Exception $e ) {
				$this->logger->error( 'Error saving TTS metadata, falling back to old system', [
					'post_id' => $post_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				] );
				
				// Fallback to old system in case of any errors
				update_post_meta( $post_id, '_tts_audio_url', $result['audio_url'] );
				update_post_meta( $post_id, '_tts_generated_at', time() );
				update_post_meta( $post_id, '_tts_generation_status', 'completed' );
				
				if ( isset( $result['provider'] ) ) {
					update_post_meta( $post_id, '_tts_voice_provider', $result['provider'] );
				}
				if ( isset( $result['voice'] ) ) {
					update_post_meta( $post_id, '_tts_voice_id', $result['voice'] );
				}
			}
			
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
			
			// Record failed generation attempt (only if class exists)
			try {
				if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
					TTSMetaManager::recordGenerationAttempt( 
						$post_id, 
						false, 
						$e->getMessage()
					);
				} else {
					// Fallback: record failure using old system
					update_post_meta( $post_id, '_tts_generation_status', 'failed' );
					update_post_meta( $post_id, '_tts_last_error', $e->getMessage() );
				}
			} catch ( \Exception $meta_error ) {
				$this->logger->error( 'Failed to record generation attempt', [
					'post_id' => $post_id,
					'meta_error' => $meta_error->getMessage()
				] );
			}
			
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
		$this->logger->info( '[generatePreview] Starting preview generation', [
			'text_length' => strlen( $text ),
			'provider' => $provider,
			'voice' => $voice
		] );
		
		// If no provider specified, use default
		if ( empty( $provider ) ) {
			$config = get_option( 'wp_tts_config', [] );
			$provider = $config['default_provider'] ?? 'google';
			$this->logger->info( '[generatePreview] Using default provider', [ 'provider' => $provider ] );
		}
		
		$options = [
			'provider' => $provider,
			'voice' => $voice,
			'preview' => true,
		];
		
		$result = $this->generateAudio( $text, $options );
		
		if ( ! $result || ! $result['success'] ) {
			$this->logger->error( '[generatePreview] Preview generation failed', [ 'result' => $result ] );
			throw new \Exception( 'Failed to generate preview audio: ' . ( $result['message'] ?? 'Unknown error' ) );
		}
		
		$this->logger->info( '[generatePreview] Preview generation completed successfully', [
			'audio_url' => $result['audio_url'],
			'provider' => $result['provider'] ?? $provider
		] );
		
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
					$upload_dir = wp_upload_dir();
					$credentials_path = $upload_dir['basedir'] . '/private/sesolibre-tts-13985ba22d36.json';
				} else {
					// Convert relative paths to absolute paths
					if ( substr( $credentials_path, 0, 1 ) !== '/' && strpos( $credentials_path, ':' ) === false ) {
						// This is a relative path, convert to absolute
						$credentials_path = ABSPATH . $credentials_path;
					}
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
						
						// Always create instance, even without full configuration (for mock mode)
						return new \WP_TTS\Providers\AzureTTSProvider( $provider_config, $this->logger );
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
	
	/**
	 * Process intro/outro audio for a post
	 *
	 * @param int   $post_id Post ID
	 * @param array $result  Original TTS generation result
	 * @return array Modified result with intro/outro if applicable
	 */
	private function processIntroOutroAudio( int $post_id, array $result ): array {
		try {
			$this->logger->info( 'Processing intro/outro audio for post', [ 'post_id' => $post_id ] );
			
			// Get intro/outro/background settings
			$intro_audio_id = '';
			$outro_audio_id = '';
			$background_audio_id = '';
			
			// Get post-specific audio assets
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$tts_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
				$intro_audio_id = $tts_data['audio_assets']['intro_audio'] ?? '';
				$outro_audio_id = $tts_data['audio_assets']['outro_audio'] ?? '';
				$background_audio_id = $tts_data['audio_assets']['background_audio'] ?? '';
			}
			
			// If no post-specific audio, check for defaults
			if ( ! $intro_audio_id || ! $outro_audio_id || ! $background_audio_id ) {
				$config = get_option( 'wp_tts_config', [] );
				$default_intro = $config['audio_assets']['default_intro'] ?? '';
				$default_outro = $config['audio_assets']['default_outro'] ?? '';
				$default_background = $config['audio_assets']['default_background'] ?? '';
				
				if ( ! $intro_audio_id ) {
					$intro_audio_id = $default_intro;
				}
				if ( ! $outro_audio_id ) {
					$outro_audio_id = $default_outro;
				}
				if ( ! $background_audio_id ) {
					$background_audio_id = $default_background;
				}
			}
			
			// If no intro, outro, or background, return original result
			if ( ! $intro_audio_id && ! $outro_audio_id && ! $background_audio_id ) {
				$this->logger->info( 'No audio assets configured, returning original audio', [ 'post_id' => $post_id ] );
				return $result;
			}
			
			$this->logger->info( 'Found audio assets configuration', [
				'post_id' => $post_id,
				'intro_id' => $intro_audio_id,
				'outro_id' => $outro_audio_id,
				'background_id' => $background_audio_id
			] );
			
			// Get file paths
			$intro_path = $intro_audio_id ? $this->getAttachmentFilePath( $intro_audio_id ) : '';
			$outro_path = $outro_audio_id ? $this->getAttachmentFilePath( $outro_audio_id ) : '';
			$background_path = $background_audio_id ? $this->getAttachmentFilePath( $background_audio_id ) : '';
			$main_audio_path = $this->getAudioFilePathFromUrl( $result['audio_url'] );
			
			if ( ! $main_audio_path || ! file_exists( $main_audio_path ) ) {
				$this->logger->error( 'Main audio file not found', [
					'post_id' => $post_id,
					'audio_url' => $result['audio_url'],
					'expected_path' => $main_audio_path
				] );
				return $result;
			}
			
			// Validate intro/outro files exist
			if ( $intro_path && ! file_exists( $intro_path ) ) {
				$this->logger->warning( 'Intro audio file not found, skipping', [
					'post_id' => $post_id,
					'intro_id' => $intro_audio_id,
					'intro_path' => $intro_path
				] );
				$intro_path = '';
			}
			
			if ( $outro_path && ! file_exists( $outro_path ) ) {
				$this->logger->warning( 'Outro audio file not found, skipping', [
					'post_id' => $post_id,
					'outro_id' => $outro_audio_id,
					'outro_path' => $outro_path
				] );
				$outro_path = '';
			}
			
			if ( $background_path && ! file_exists( $background_path ) ) {
				$this->logger->warning( 'Background audio file not found, skipping', [
					'post_id' => $post_id,
					'background_id' => $background_audio_id,
					'background_path' => $background_path
				] );
				$background_path = '';
			}
			
			// NEW APPROACH: Save intro/outro info to metadata for dynamic player mixing
			// Instead of creating mixed files, we'll store the audio asset references
			// so the SesoLibre player can dynamically mix them
			
			$this->logger->info( 'Saving audio assets metadata for dynamic mixing', [
				'post_id' => $post_id,
				'intro_id' => $intro_audio_id,
				'outro_id' => $outro_audio_id,
				'background_id' => $background_audio_id,
				'intro_valid' => $intro_path ? file_exists( $intro_path ) : false,
				'outro_valid' => $outro_path ? file_exists( $outro_path ) : false,
				'background_valid' => $background_path ? file_exists( $background_path ) : false
			] );
			
			// Update TTS metadata with valid audio asset references
			if ( class_exists( '\\WP_TTS\\Utils\\TTSMetaManager' ) ) {
				$current_data = \WP_TTS\Utils\TTSMetaManager::getTTSData( $post_id );
				
				// Only save IDs for files that actually exist
				$current_data['audio_assets']['intro_audio'] = ( $intro_path && file_exists( $intro_path ) ) ? $intro_audio_id : '';
				$current_data['audio_assets']['outro_audio'] = ( $outro_path && file_exists( $outro_path ) ) ? $outro_audio_id : '';
				$current_data['audio_assets']['background_audio'] = ( $background_path && file_exists( $background_path ) ) ? $background_audio_id : '';
				
				// Save the clean main audio URL (not mixed)
				$current_data['audio']['url'] = $result['audio_url'];
				
				\WP_TTS\Utils\TTSMetaManager::saveTTSData( $post_id, $current_data );
				
				$this->logger->info( 'Updated metadata with audio asset references', [
					'post_id' => $post_id,
					'intro_saved' => $current_data['audio_assets']['intro_audio'],
					'outro_saved' => $current_data['audio_assets']['outro_audio'],
					'background_saved' => $current_data['audio_assets']['background_audio']
				] );
			}
			
			// Return the original clean audio URL - no mixing at file level
			$result['uses_dynamic_mixing'] = true;
			$result['intro_id'] = ( $intro_path && file_exists( $intro_path ) ) ? $intro_audio_id : '';
			$result['outro_id'] = ( $outro_path && file_exists( $outro_path ) ) ? $outro_audio_id : '';
			$result['background_id'] = ( $background_path && file_exists( $background_path ) ) ? $background_audio_id : '';
			
			return $result;
			
		} catch ( \Exception $e ) {
			$this->logger->error( 'Exception processing intro/outro audio', [
				'post_id' => $post_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			
			// Return original result on error
			return $result;
		}
	}
	
	/**
	 * Get file path from attachment ID
	 *
	 * @param int $attachment_id Attachment ID
	 * @return string File path or empty string if not found
	 */
	private function getAttachmentFilePath( int $attachment_id ): string {
		if ( ! $attachment_id ) {
			return '';
		}
		
		$file_path = get_attached_file( $attachment_id );
		return $file_path ?: '';
	}
	
	/**
	 * Get file path from audio URL
	 *
	 * @param string $audio_url Audio URL
	 * @return string File path or empty string if not found
	 */
	private function getAudioFilePathFromUrl( string $audio_url ): string {
		if ( ! $audio_url ) {
			return '';
		}
		
		$upload_dir = wp_upload_dir();
		$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $audio_url );
		
		return $file_path;
	}
	
	/**
	 * Concatenate audio files using PHP (fallback method)
	 *
	 * @param string $intro_path Intro audio file path
	 * @param string $main_path  Main audio file path  
	 * @param string $outro_path Outro audio file path
	 * @param int    $post_id    Post ID for filename generation
	 * @return string|null Concatenated audio URL or null on failure
	 */
	private function concatenateAudioFiles( string $intro_path, string $main_path, string $outro_path, int $post_id ): ?string {
		try {
			$this->logger->info( 'Starting audio concatenation', [
				'post_id' => $post_id,
				'intro' => $intro_path ? basename( $intro_path ) : 'none',
				'main' => basename( $main_path ),
				'outro' => $outro_path ? basename( $outro_path ) : 'none'
			] );
			
			// Create output filename
			$upload_dir = wp_upload_dir();
			$tts_dir = $upload_dir['basedir'] . '/tts-audio';
			
			// Ensure TTS directory exists
			if ( ! file_exists( $tts_dir ) ) {
				wp_mkdir_p( $tts_dir );
			}
			
			$hash = md5( $post_id . time() . 'intro_outro' );
			$output_filename = "tts-{$post_id}-with-intro-outro-{$hash}.mp3";
			$output_path = $tts_dir . '/' . $output_filename;
			$output_url = $upload_dir['baseurl'] . '/tts-audio/' . $output_filename;
			
			// Simple concatenation: just combine the binary data
			$combined_data = '';
			
			// Add intro
			if ( $intro_path && file_exists( $intro_path ) ) {
				$intro_data = file_get_contents( $intro_path );
				if ( $intro_data !== false ) {
					$combined_data .= $intro_data;
					$this->logger->info( 'Added intro audio', [
						'size' => strlen( $intro_data ),
						'file' => basename( $intro_path )
					] );
				}
			}
			
			// Add main audio
			$main_data = file_get_contents( $main_path );
			if ( $main_data !== false ) {
				$combined_data .= $main_data;
				$this->logger->info( 'Added main audio', [
					'size' => strlen( $main_data ),
					'file' => basename( $main_path )
				] );
			} else {
				$this->logger->error( 'Failed to read main audio file', [ 'path' => $main_path ] );
				return null;
			}
			
			// Add outro
			if ( $outro_path && file_exists( $outro_path ) ) {
				$outro_data = file_get_contents( $outro_path );
				if ( $outro_data !== false ) {
					$combined_data .= $outro_data;
					$this->logger->info( 'Added outro audio', [
						'size' => strlen( $outro_data ),
						'file' => basename( $outro_path )
					] );
				}
			}
			
			// Save combined file
			$result = file_put_contents( $output_path, $combined_data );
			
			if ( $result === false ) {
				$this->logger->error( 'Failed to save concatenated audio file', [ 'output_path' => $output_path ] );
				return null;
			}
			
			$this->logger->info( 'Successfully created concatenated audio file', [
				'output_path' => $output_path,
				'output_url' => $output_url,
				'total_size' => $result
			] );
			
			// Clean up original main audio file to save space
			if ( file_exists( $main_path ) ) {
				unlink( $main_path );
				$this->logger->info( 'Cleaned up original main audio file', [ 'path' => $main_path ] );
			}
			
			return $output_url;
			
		} catch ( \Exception $e ) {
			$this->logger->error( 'Exception during audio concatenation', [
				'post_id' => $post_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			return null;
		}
	}
}
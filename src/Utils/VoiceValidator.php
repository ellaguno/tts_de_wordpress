<?php

namespace WP_TTS\Utils;

/**
 * Voice Validator
 * 
 * Validates voice names for different TTS providers
 */
class VoiceValidator {

	/**
	 * Valid voices for each provider
	 */
	private static $valid_voices = [
		'azure' => [
			'es-MX-DaliaNeural',
			'es-MX-JorgeNeural', 
			'es-ES-ElviraNeural',
			'es-ES-AlvaroNeural',
			'es-AR-ElenaNeural',
			'es-AR-TomasNeural',
			'es-CO-SalomeNeural',
			'es-CO-GonzaloNeural',
			'es-CL-CatalinaNeural',
			'es-CL-LorenzoNeural',
			'es-PE-CamilaNeural',
			'es-PE-AlexNeural',
			'es-VE-PaolaNeural',
			'es-VE-SebastianNeural'
		],
		'polly' => [
			'Conchita',  // Spanish (Spain)
			'Enrique',   // Spanish (Spain) 
			'Lucia',     // Spanish (Spain)
			'Mia',       // Spanish (Mexico)
			'Miguel',    // Spanish (US)
			'Penelope',  // Spanish (US)
			'Lupe'       // Spanish (US)
		],
		'google' => [
			'es-ES-Neural2-A',
			'es-ES-Neural2-B', 
			'es-ES-Neural2-C',
			'es-ES-Neural2-D',
			'es-ES-Neural2-E',
			'es-ES-Neural2-F',
			'es-US-Neural2-A',
			'es-US-Neural2-B',
			'es-US-Neural2-C',
			'es-US-Standard-A',
			'es-US-Standard-B',
			'es-US-Standard-C'
		],
		'elevenlabs' => [
			// ElevenLabs uses custom voice IDs, these are examples
			'voice-id-1',
			'voice-id-2'
		]
	];

	/**
	 * Validate voice for provider
	 *
	 * @param string $provider Provider name
	 * @param string $voice Voice name
	 * @return bool True if valid
	 */
	public static function isValidVoice( string $provider, string $voice ): bool {
		if ( ! isset( self::$valid_voices[ $provider ] ) ) {
			return false;
		}

		return in_array( $voice, self::$valid_voices[ $provider ], true );
	}

	/**
	 * Get valid voices for provider
	 *
	 * @param string $provider Provider name
	 * @return array Valid voices
	 */
	public static function getValidVoices( string $provider ): array {
		return self::$valid_voices[ $provider ] ?? [];
	}

	/**
	 * Get default voice for provider
	 *
	 * @param string $provider Provider name
	 * @return string Default voice
	 */
	public static function getDefaultVoice( string $provider ): string {
		$voices = self::getValidVoices( $provider );
		
		switch ( $provider ) {
			case 'azure':
				return 'es-MX-DaliaNeural';
			case 'polly':
				return 'Lucia'; // Valid Polly voice
			case 'google':
				return 'es-ES-Neural2-A';
			case 'elevenlabs':
				return $voices[0] ?? '';
			default:
				return $voices[0] ?? '';
		}
	}

	/**
	 * Fix invalid voice name
	 *
	 * @param string $provider Provider name
	 * @param string $voice Voice name
	 * @return string Valid voice name
	 */
	public static function fixVoice( string $provider, string $voice ): string {
		// If voice is already valid, return it
		if ( self::isValidVoice( $provider, $voice ) ) {
			return $voice;
		}

		// Log the invalid voice
		error_log( "[VoiceValidator] Invalid voice '{$voice}' for provider '{$provider}', using default" );

		// Return default voice for provider
		return self::getDefaultVoice( $provider );
	}

	/**
	 * Validate and fix voice configuration
	 *
	 * @param string $provider Provider name
	 * @param array $config Provider configuration
	 * @return array Fixed configuration
	 */
	public static function validateProviderConfig( string $provider, array $config ): array {
		if ( isset( $config['default_voice'] ) ) {
			$config['default_voice'] = self::fixVoice( $provider, $config['default_voice'] );
		}

		if ( isset( $config['voice_id'] ) ) {
			$config['voice_id'] = self::fixVoice( $provider, $config['voice_id'] );
		}

		return $config;
	}
}
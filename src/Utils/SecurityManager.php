<?php
/**
 * Security Manager utility class
 *
 * @package WP_TTS
 */

namespace WP_TTS\Utils;

/**
 * Basic security manager implementation
 */
class SecurityManager {
	
	/**
	 * Verify nonce
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Action name.
	 * @return bool True if valid.
	 */
	public function verifyNonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action );
	}
	
	/**
	 * Check user capability
	 *
	 * @param string $capability Required capability.
	 * @return bool True if user has capability.
	 */
	public function canUser( string $capability ): bool {
		return current_user_can( $capability );
	}
	
	/**
	 * Sanitize text input
	 *
	 * @param string $input Input text.
	 * @return string Sanitized text.
	 */
	public function sanitizeText( string $input ): string {
		return sanitize_text_field( $input );
	}
	
	/**
	 * Sanitize textarea input
	 *
	 * @param string $input Input text.
	 * @return string Sanitized text.
	 */
	public function sanitizeTextarea( string $input ): string {
		return sanitize_textarea_field( $input );
	}
	
	/**
	 * Validate API key format
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if valid format.
	 */
	public function validateApiKey( string $api_key ): bool {
		// Basic validation - not empty and reasonable length
		return ! empty( $api_key ) && strlen( $api_key ) >= 10;
	}
	
	/**
	 * Sanitize general input
	 *
	 * @param string $input Input text.
	 * @return string Sanitized text.
	 */
	public function sanitizeInput( string $input ): string {
		return sanitize_text_field( $input );
	}
	
	/**
	 * Sanitize text for TTS processing
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	public function sanitizeTextForTTS( string $text ): string {
		return sanitize_textarea_field( $text );
	}
}
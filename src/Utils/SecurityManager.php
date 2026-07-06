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

	/**
	 * Prefix that marks a value as encrypted by this plugin
	 */
	private const ENC_PREFIX = '$wpttsenc$';

	/**
	 * Option keys whose values are secrets and must be encrypted at rest
	 */
	private const SECRET_KEYS = [
		'api_key',
		'api_token',
		'secret_key',
		'subscription_key',
		'client_secret',
	];

	/**
	 * Register option filters that transparently encrypt provider secrets
	 * at rest in wp_options and decrypt them on read.
	 *
	 * Hooking the option layer is a single choke point: every existing
	 * get_option()/update_option() caller keeps working unchanged, but the
	 * database only ever sees ciphertext.
	 */
	public static function registerSecretStorageFilters(): void {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return; // No OpenSSL: leave behavior unchanged rather than corrupt data.
		}

		foreach ( [ 'wp_tts_config', 'wp_tts_storage_config', 'wp_tts_providers_config' ] as $option ) {
			add_filter( "pre_update_option_{$option}", [ self::class, 'encryptSecretsDeep' ], 10, 1 );
			add_filter( "option_{$option}", [ self::class, 'decryptSecretsDeep' ], 10, 1 );
		}
	}

	/**
	 * Recursively encrypt secret fields in a config array
	 *
	 * @param mixed $value Option value.
	 * @return mixed Value with secret leaves encrypted.
	 */
	public static function encryptSecretsDeep( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::encryptSecretsDeep( $item );
			} elseif ( is_string( $item ) && '' !== $item && in_array( (string) $key, self::SECRET_KEYS, true ) ) {
				$value[ $key ] = self::encryptSecret( $item );
			}
		}

		return $value;
	}

	/**
	 * Recursively decrypt secret fields in a config array
	 *
	 * @param mixed $value Option value.
	 * @return mixed Value with secret leaves decrypted.
	 */
	public static function decryptSecretsDeep( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::decryptSecretsDeep( $item );
			} elseif ( is_string( $item ) ) {
				$value[ $key ] = self::decryptSecret( $item );
			}
		}

		return $value;
	}

	/**
	 * Encrypt a secret value (AES-256-GCM, key derived from WP salts)
	 *
	 * @param string $plaintext Secret value.
	 * @return string Prefixed ciphertext, or the original value if encryption fails.
	 */
	public static function encryptSecret( string $plaintext ): string {
		if ( 0 === strpos( $plaintext, self::ENC_PREFIX ) ) {
			return $plaintext; // Already encrypted.
		}

		$iv = random_bytes( 12 );
		$tag = '';
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', self::deriveKey(), OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext ) {
			return $plaintext;
		}

		return self::ENC_PREFIX . base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt a secret value; non-encrypted values pass through untouched
	 * (legacy plaintext config keeps working).
	 *
	 * @param string $value Stored value.
	 * @return string Decrypted secret, or '' if the ciphertext is corrupt.
	 */
	public static function decryptSecret( string $value ): string {
		if ( 0 !== strpos( $value, self::ENC_PREFIX ) ) {
			return $value;
		}

		$raw = base64_decode( substr( $value, strlen( self::ENC_PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) < 29 ) { // 12 IV + 16 tag + >=1 data
			return '';
		}

		$iv = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$ciphertext = substr( $raw, 28 );

		$plaintext = openssl_decrypt( $ciphertext, 'aes-256-gcm', self::deriveKey(), OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plaintext ? '' : $plaintext;
	}

	/**
	 * Derive a stable 32-byte key from the WordPress auth salts
	 *
	 * @return string Binary key.
	 */
	private static function deriveKey(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|wp-tts-secret-storage', true );
	}
}
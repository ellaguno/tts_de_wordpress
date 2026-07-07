<?php
/**
 * Logger utility class
 *
 * @package WP_TTS
 */

namespace WP_TTS\Utils;

/**
 * Basic logger implementation.
 *
 * This is the single place in the plugin allowed to call error_log():
 * every other file routes through these methods (or the static debugLog()
 * helper), so debug output is consistently gated behind WP_DEBUG.
 */
class Logger {

	/**
	 * Write a line to the PHP error log
	 *
	 * @param string $line Formatted log line.
	 */
	private static function write( string $line ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- centralized logging sink, gated by WP_DEBUG at the call sites.
		error_log( $line );
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function info( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::write( '[WP_TTS INFO] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function error( string $message, array $context = [] ): void {
		self::write( '[WP_TTS ERROR] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function warning( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::write( '[WP_TTS WARNING] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function debug( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::write( '[WP_TTS DEBUG] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log warning message (alias for warning)
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function warn( string $message, array $context = [] ): void {
		$this->warning( $message, $context );
	}

	/**
	 * Static debug helper for code without a Logger instance
	 *
	 * Replaces the direct error_log() calls that were scattered across the
	 * plugin. Only writes when WP_DEBUG is enabled.
	 *
	 * @param string $message Log message.
	 */
	public static function debugLog( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::write( $message );
		}
	}
}

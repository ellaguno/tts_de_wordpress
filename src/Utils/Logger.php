<?php
/**
 * Logger utility class
 *
 * @package WP_TTS
 */

namespace WP_TTS\Utils;

/**
 * Basic logger implementation
 */
class Logger {
	
	/**
	 * Log info message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function info( string $message, array $context = [] ): void {
		// Always log auto-generation related messages for debugging
		$always_log = strpos( $message, 'auto-generat' ) !== false ||
		              strpos( $message, 'Auto-generat' ) !== false ||
		              strpos( $message, 'transition' ) !== false ||
		              strpos( $message, 'Checking auto' ) !== false;

		if ( $always_log || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS INFO] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}
	
	/**
	 * Log error message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function error( string $message, array $context = [] ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[WP_TTS ERROR] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
	}
	
	/**
	 * Log warning message
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function warning( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS WARNING] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WP_TTS DEBUG] ' . $message . ( ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '' ) );
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
}

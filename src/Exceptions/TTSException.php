<?php

namespace WP_TTS\Exceptions;

/**
 * Base TTS Exception
 *
 * Base exception class for all TTS-related errors
 */
class TTSException extends \Exception {

	/**
	 * Error context data
	 *
	 * @var array
	 */
	protected $context = array();

	/**
	 * Error code mapping
	 *
	 * @var array
	 */
	protected static $errorCodes = array(
		'INVALID_TEXT'         => 1001,
		'PROVIDER_ERROR'       => 1002,
		'QUOTA_EXCEEDED'       => 1003,
		'INVALID_VOICE'        => 1004,
		'NETWORK_ERROR'        => 1005,
		'AUTHENTICATION_ERROR' => 1006,
		'RATE_LIMIT_EXCEEDED'  => 1007,
		'UNSUPPORTED_FORMAT'   => 1008,
		'TEXT_TOO_LONG'        => 1009,
		'CONFIGURATION_ERROR'  => 1010,
	);

	/**
	 * Constructor
	 *
	 * @param string          $message Error message
	 * @param string          $errorType Error type code
	 * @param array           $context Additional context data
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $message = '',
		string $errorType = 'PROVIDER_ERROR',
		array $context = array(),
		\Throwable $previous = null
	) {
		$code          = self::$errorCodes[ $errorType ] ?? 1000;
		$this->context = $context;

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Get error context
	 *
	 * @return array Error context data
	 */
	public function getContext(): array {
		return $this->context;
	}

	/**
	 * Set error context
	 *
	 * @param array $context Context data
	 */
	public function setContext( array $context ): void {
		$this->context = $context;
	}

	/**
	 * Add context data
	 *
	 * @param string $key Context key
	 * @param mixed  $value Context value
	 */
	public function addContext( string $key, $value ): void {
		$this->context[ $key ] = $value;
	}

	/**
	 * Get error type from code
	 *
	 * @return string Error type
	 */
	public function getErrorType(): string {
		$errorTypes = array_flip( self::$errorCodes );
		return $errorTypes[ $this->getCode() ] ?? 'UNKNOWN_ERROR';
	}

	/**
	 * Check if error is recoverable
	 *
	 * @return bool True if error might be recoverable
	 */
	public function isRecoverable(): bool {
		$recoverableTypes = array(
			'NETWORK_ERROR',
			'RATE_LIMIT_EXCEEDED',
			'PROVIDER_ERROR',
		);

		return in_array( $this->getErrorType(), $recoverableTypes );
	}

	/**
	 * Get user-friendly error message
	 *
	 * @return string User-friendly message
	 */
	public function getUserMessage(): string {
		$userMessages = array(
			'INVALID_TEXT'         => __( 'The text content is invalid or empty.', 'TTS de Wordpress' ),
			'PROVIDER_ERROR'       => __( 'There was an error with the TTS service. Please try again.', 'TTS de Wordpress' ),
			'QUOTA_EXCEEDED'       => __( 'The TTS service quota has been exceeded. Please try again later.', 'TTS de Wordpress' ),
			'INVALID_VOICE'        => __( 'The selected voice is not available.', 'TTS de Wordpress' ),
			'NETWORK_ERROR'        => __( 'Network connection error. Please check your internet connection.', 'TTS de Wordpress' ),
			'AUTHENTICATION_ERROR' => __( 'Authentication failed. Please check your API credentials.', 'TTS de Wordpress' ),
			'RATE_LIMIT_EXCEEDED'  => __( 'Rate limit exceeded. Please wait before trying again.', 'TTS de Wordpress' ),
			'UNSUPPORTED_FORMAT'   => __( 'The requested audio format is not supported.', 'TTS de Wordpress' ),
			'TEXT_TOO_LONG'        => __( 'The text is too long for processing.', 'TTS de Wordpress' ),
			'CONFIGURATION_ERROR'  => __( 'Plugin configuration error. Please check your settings.', 'TTS de Wordpress' ),
		);

		return $userMessages[ $this->getErrorType() ] ?? $this->getMessage();
	}

	/**
	 * Convert to array for logging
	 *
	 * @return array Exception data
	 */
	public function toArray(): array {
		return array(
			'message' => $this->getMessage(),
			'code'    => $this->getCode(),
			'type'    => $this->getErrorType(),
			'file'    => $this->getFile(),
			'line'    => $this->getLine(),
			'context' => $this->context,
			'trace'   => $this->getTraceAsString(),
		);
	}

	/**
	 * Create exception from error type
	 *
	 * @param string $errorType Error type
	 * @param string $message Custom message
	 * @param array  $context Context data
	 * @return static
	 */
	public static function create( string $errorType, string $message = '', array $context = array() ): self {
		return new static( $message, $errorType, $context );
	}
}

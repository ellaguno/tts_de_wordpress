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
			'INVALID_TEXT'         => __( 'El contenido del texto es inválido o está vacío.', 'tts-sesolibre' ),
			'PROVIDER_ERROR'       => __( 'Hubo un error con el servicio TTS. Por favor, inténtelo de nuevo.', 'tts-sesolibre' ),
			'QUOTA_EXCEEDED'       => __( 'Se ha superado la cuota del servicio TTS. Por favor, inténtelo más tarde.', 'tts-sesolibre' ),
			'INVALID_VOICE'        => __( 'La voz seleccionada no está disponible.', 'tts-sesolibre' ),
			'NETWORK_ERROR'        => __( 'Error de conexión de red. Por favor, verifique su conexión a internet.', 'tts-sesolibre' ),
			'AUTHENTICATION_ERROR' => __( 'Falló la autenticación. Por favor, verifique sus credenciales de API.', 'tts-sesolibre' ),
			'RATE_LIMIT_EXCEEDED'  => __( 'Límite de tasa superado. Por favor, espere antes de intentar de nuevo.', 'tts-sesolibre' ),
			'UNSUPPORTED_FORMAT'   => __( 'El formato de audio solicitado no es compatible.', 'tts-sesolibre' ),
			'TEXT_TOO_LONG'        => __( 'El texto es demasiado largo para procesarse.', 'tts-sesolibre' ),
			'CONFIGURATION_ERROR'  => __( 'Error de configuración del plugin. Por favor, verifique su configuración.', 'tts-sesolibre' ),
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

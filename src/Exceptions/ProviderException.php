<?php

namespace WP_TTS\Exceptions;

/**
 * Provider Exception
 *
 * Exception class for TTS provider-specific errors
 */
class ProviderException extends TTSException {

	/**
	 * Provider name
	 *
	 * @var string
	 */
	protected $provider;

	/**
	 * Provider-specific error code
	 *
	 * @var string
	 */
	protected $providerErrorCode;

	/**
	 * Constructor
	 *
	 * @param string          $message Error message
	 * @param string          $provider Provider name
	 * @param string          $providerErrorCode Provider-specific error code
	 * @param string          $errorType Error type
	 * @param array           $context Additional context
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $message = '',
		string $provider = '',
		string $providerErrorCode = '',
		string $errorType = 'PROVIDER_ERROR',
		array $context = array(),
		\Throwable $previous = null
	) {
		$this->provider          = $provider;
		$this->providerErrorCode = $providerErrorCode;

		// Add provider info to context
		$context['provider']            = $provider;
		$context['provider_error_code'] = $providerErrorCode;

		parent::__construct( $message, $errorType, $context, $previous );
	}

	/**
	 * Get provider name
	 *
	 * @return string Provider name
	 */
	public function getProvider(): string {
		return $this->provider;
	}

	/**
	 * Get provider-specific error code
	 *
	 * @return string Provider error code
	 */
	public function getProviderErrorCode(): string {
		return $this->providerErrorCode;
	}

	/**
	 * Create Azure-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode Azure error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function azure( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 'azure', $errorCode, 'PROVIDER_ERROR', $context );
	}

	/**
	 * Create Google-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode Google error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function google( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 'google', $errorCode, 'PROVIDER_ERROR', $context );
	}

	/**
	 * Create Amazon Polly-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode Polly error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function polly( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 'polly', $errorCode, 'PROVIDER_ERROR', $context );
	}

	/**
	 * Create ElevenLabs-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode ElevenLabs error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function elevenlabs( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 'elevenlabs', $errorCode, 'PROVIDER_ERROR', $context );
	}
}

<?php

namespace WP_TTS\Exceptions;

/**
 * Storage Exception
 *
 * Exception class for storage provider-specific errors
 */
class StorageException extends TTSException {

	/**
	 * Storage provider name
	 *
	 * @var string
	 */
	protected $storageProvider;

	/**
	 * Storage-specific error code
	 *
	 * @var string
	 */
	protected $storageErrorCode;

	/**
	 * File information
	 *
	 * @var array
	 */
	protected $fileInfo;

	/**
	 * Constructor
	 *
	 * @param string          $message Error message
	 * @param string          $storageProvider Storage provider name
	 * @param string          $storageErrorCode Storage-specific error code
	 * @param array           $fileInfo File information
	 * @param string          $errorType Error type
	 * @param array           $context Additional context
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $message = '',
		string $storageProvider = '',
		string $storageErrorCode = '',
		array $fileInfo = array(),
		string $errorType = 'STORAGE_ERROR',
		array $context = array(),
		\Throwable $previous = null
	) {
		$this->storageProvider  = $storageProvider;
		$this->storageErrorCode = $storageErrorCode;
		$this->fileInfo         = $fileInfo;

		// Add storage info to context
		$context['storage_provider']   = $storageProvider;
		$context['storage_error_code'] = $storageErrorCode;
		$context['file_info']          = $fileInfo;

		parent::__construct( $message, $errorType, $context, $previous );
	}

	/**
	 * Get storage provider name
	 *
	 * @return string Storage provider name
	 */
	public function getStorageProvider(): string {
		return $this->storageProvider;
	}

	/**
	 * Get storage-specific error code
	 *
	 * @return string Storage error code
	 */
	public function getStorageErrorCode(): string {
		return $this->storageErrorCode;
	}

	/**
	 * Get file information
	 *
	 * @return array File information
	 */
	public function getFileInfo(): array {
		return $this->fileInfo;
	}

	/**
	 * Create upload failure exception
	 *
	 * @param string $provider Storage provider
	 * @param string $filename Filename
	 * @param string $reason Failure reason
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function uploadFailed(
		string $provider,
		string $filename,
		string $reason = '',
		array $context = array()
	): self {
		$message = sprintf(
			__( 'Error al subir el archivo "%1$s" al almacenamiento %2$s', 'wp-tts-sesolibre' ),
			$filename,
			$provider
		);

		if ( $reason ) {
			$message .= ': ' . $reason;
		}

		return new static(
			$message,
			$provider,
			'UPLOAD_FAILED',
			array( 'filename' => $filename ),
			'UPLOAD_FAILED',
			$context
		);
	}

	/**
	 * Create delete failure exception
	 *
	 * @param string $provider Storage provider
	 * @param string $fileUrl File URL
	 * @param string $reason Failure reason
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function deleteFailed(
		string $provider,
		string $fileUrl,
		string $reason = '',
		array $context = array()
	): self {
		$message = sprintf(
			__( 'Error al eliminar el archivo "%1$s" del almacenamiento %2$s', 'wp-tts-sesolibre' ),
			$fileUrl,
			$provider
		);

		if ( $reason ) {
			$message .= ': ' . $reason;
		}

		return new static(
			$message,
			$provider,
			'DELETE_FAILED',
			array( 'file_url' => $fileUrl ),
			'DELETE_FAILED',
			$context
		);
	}

	/**
	 * Create authentication failure exception
	 *
	 * @param string $provider Storage provider
	 * @param string $reason Failure reason
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function authenticationFailed(
		string $provider,
		string $reason = '',
		array $context = array()
	): self {
		$message = sprintf(
			__( 'Falló la autenticación para el almacenamiento %s', 'wp-tts-sesolibre' ),
			$provider
		);

		if ( $reason ) {
			$message .= ': ' . $reason;
		}

		return new static(
			$message,
			$provider,
			'AUTH_FAILED',
			array(),
			'AUTHENTICATION_ERROR',
			$context
		);
	}

	/**
	 * Create quota exceeded exception
	 *
	 * @param string $provider Storage provider
	 * @param array  $quotaInfo Quota information
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function quotaExceeded(
		string $provider,
		array $quotaInfo = array(),
		array $context = array()
	): self {
		$message = sprintf(
			__( 'Cuota de almacenamiento superada para %s', 'wp-tts-sesolibre' ),
			$provider
		);

		return new static(
			$message,
			$provider,
			'QUOTA_EXCEEDED',
			$quotaInfo,
			'QUOTA_EXCEEDED',
			$context
		);
	}

	/**
	 * Create file not found exception
	 *
	 * @param string $provider Storage provider
	 * @param string $filename Filename
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function fileNotFound(
		string $provider,
		string $filename,
		array $context = array()
	): self {
		$message = sprintf(
			__( 'Archivo "%1$s" no encontrado en el almacenamiento %2$s', 'wp-tts-sesolibre' ),
			$filename,
			$provider
		);

		return new static(
			$message,
			$provider,
			'FILE_NOT_FOUND',
			array( 'filename' => $filename ),
			'FILE_NOT_FOUND',
			$context
		);
	}

	/**
	 * Create file too large exception
	 *
	 * @param string $provider Storage provider
	 * @param string $filename Filename
	 * @param int    $fileSize File size in bytes
	 * @param int    $maxSize Maximum allowed size in bytes
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function fileTooLarge(
		string $provider,
		string $filename,
		int $fileSize,
		int $maxSize,
		array $context = array()
	): self {
		$message = sprintf(
			__( 'El archivo "%1$s" es demasiado grande (%2$s). El tamaño máximo para %3$s es %4$s', 'wp-tts-sesolibre' ),
			$filename,
			size_format( $fileSize ),
			$provider,
			size_format( $maxSize )
		);

		return new static(
			$message,
			$provider,
			'FILE_TOO_LARGE',
			array(
				'filename'  => $filename,
				'file_size' => $fileSize,
				'max_size'  => $maxSize,
			),
			'FILE_TOO_LARGE',
			$context
		);
	}

	/**
	 * Create network error exception
	 *
	 * @param string $provider Storage provider
	 * @param string $operation Operation being performed
	 * @param string $reason Network error reason
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function networkError(
		string $provider,
		string $operation,
		string $reason = '',
		array $context = array()
	): self {
		$message = sprintf(
			__( 'Error de red durante la operación %1$s con el almacenamiento %2$s', 'wp-tts-sesolibre' ),
			$operation,
			$provider
		);

		if ( $reason ) {
			$message .= ': ' . $reason;
		}

		return new static(
			$message,
			$provider,
			'NETWORK_ERROR',
			array( 'operation' => $operation ),
			'NETWORK_ERROR',
			$context
		);
	}

	/**
	 * Create Buzzsprout-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode Buzzsprout error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function buzzsprout( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 'buzzsprout', $errorCode, array(), 'STORAGE_ERROR', $context );
	}

	/**
	 * Create AWS S3-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode S3 error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function s3( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 's3', $errorCode, array(), 'STORAGE_ERROR', $context );
	}

	/**
	 * Create Spotify-specific exception
	 *
	 * @param string $message Error message
	 * @param string $errorCode Spotify error code
	 * @param array  $context Additional context
	 * @return static
	 */
	public static function spotify( string $message, string $errorCode = '', array $context = array() ): self {
		return new static( $message, 'spotify', $errorCode, array(), 'STORAGE_ERROR', $context );
	}
}

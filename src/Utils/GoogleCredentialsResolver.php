<?php
/**
 * Google Cloud credentials path resolver
 *
 * @package WP_TTS
 */

namespace WP_TTS\Utils;

/**
 * Resolves the Google Cloud service-account JSON path.
 *
 * This logic used to be copy-pasted (with slight drift) in
 * GoogleCloudTTSProvider, TTSService and RoundRobinManager, including a
 * hardcoded environment-specific filename. Single implementation:
 *
 * 1. If a path is configured (and isn't the placeholder), resolve relative
 *    paths against ABSPATH and use it when the file exists.
 * 2. Otherwise look in uploads/private/: first the legacy known filename,
 *    then any *.json file.
 */
class GoogleCredentialsResolver {

	/**
	 * Legacy filename kept for existing installs
	 */
	private const LEGACY_FILENAME = 'sesolibre-tts-13985ba22d36.json';

	/**
	 * Resolve the credentials file path
	 *
	 * @param string|null $configured_path Path from configuration (may be empty).
	 * @return string|null Absolute path to an existing JSON file, or null.
	 */
	public static function resolve( ?string $configured_path ): ?string {
		$configured_path = (string) $configured_path;

		// A path containing the sample placeholder name is treated as unset.
		if ( '' !== $configured_path && strpos( $configured_path, 'google-credentials.json' ) === false ) {
			// Convert relative paths to absolute (':' guards Windows drives).
			if ( substr( $configured_path, 0, 1 ) !== '/' && strpos( $configured_path, ':' ) === false ) {
				$configured_path = ABSPATH . $configured_path;
			}

			return file_exists( $configured_path ) ? $configured_path : null;
		}

		$upload_dir = wp_upload_dir();
		$private_dir = $upload_dir['basedir'] . '/private';

		$legacy = $private_dir . '/' . self::LEGACY_FILENAME;
		if ( file_exists( $legacy ) ) {
			return $legacy;
		}

		$candidates = glob( $private_dir . '/*.json' );
		if ( ! empty( $candidates ) ) {
			return $candidates[0];
		}

		return null;
	}
}

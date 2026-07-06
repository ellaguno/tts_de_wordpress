<?php

namespace WP_TTS\Utils;

/**
 * Text Chunker
 *
 * Handles splitting long text into chunks for TTS providers with character limits.
 *
 * Limits are enforced in bytes (the strictest interpretation: Google's 5000
 * limit is bytes, others are characters), but text is always split at UTF-8
 * character boundaries and no text is ever discarded.
 */
class TextChunker {

	/**
	 * Byte limits for each provider
	 */
	private static $provider_limits = [
		'azure' => 8000,      // Azure TTS limit
		'polly' => 3000,      // Amazon Polly limit
		'google' => 4800,     // Google TTS limit is 5000 bytes; leave headroom
		'elevenlabs' => 2500, // ElevenLabs limit
		'openai' => 4096      // OpenAI TTS limit
	];

	/**
	 * Split text into chunks for provider
	 *
	 * @param string $text Text to split
	 * @param string $provider Provider name
	 * @return array Array of text chunks
	 */
	public static function chunkText( string $text, string $provider ): array {
		$limit = self::getProviderLimit( $provider );

		// If text is within limit, return as single chunk
		if ( strlen( $text ) <= $limit ) {
			return [ $text ];
		}

		$chunks = [];
		$sentences = self::splitIntoSentences( $text );
		$current_chunk = '';

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );

			if ( '' === $sentence ) {
				continue;
			}

			// If single sentence is too long, split by words
			if ( strlen( $sentence ) > $limit ) {
				// Save current chunk if not empty
				if ( '' !== $current_chunk ) {
					$chunks[] = trim( $current_chunk );
					$current_chunk = '';
				}

				// Split long sentence by words
				$word_chunks = self::splitLongSentence( $sentence, $limit );
				$chunks = array_merge( $chunks, $word_chunks );
				continue;
			}

			// Check if adding this sentence would exceed limit
			$test_chunk = $current_chunk . ( '' === $current_chunk ? '' : ' ' ) . $sentence;

			if ( strlen( $test_chunk ) > $limit ) {
				// Save current chunk and start new one
				if ( '' !== $current_chunk ) {
					$chunks[] = trim( $current_chunk );
				}
				$current_chunk = $sentence;
			} else {
				// Add sentence to current chunk
				$current_chunk = $test_chunk;
			}
		}

		// Add final chunk if not empty
		if ( '' !== $current_chunk ) {
			$chunks[] = trim( $current_chunk );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TextChunker] Split ' . strlen( $text ) . " bytes into " . count( $chunks ) . " chunks for {$provider} (limit {$limit})" );
		}

		return $chunks;
	}

	/**
	 * Split text into sentences
	 *
	 * Splits after ., ! or ? followed by whitespace, guarding common Spanish
	 * abbreviations and single-initial names. Decimals ("3.5") never match
	 * because they have no whitespace after the period. Opening ¿ ¡ stay
	 * attached to the sentence they introduce.
	 *
	 * @param string $text Text to split
	 * @return array Array of sentences
	 */
	private static function splitIntoSentences( string $text ): array {
		$pattern = '/(?<!\bSr\.)(?<!\bSra\.)(?<!\bDr\.)(?<!\bDra\.)(?<!\bLic\.)(?<!\bIng\.)(?<!\bProf\.)(?<!\bNo\.)(?<!\bNúm\.)(?<!\bpág\.)(?<!\betc\.)(?<!\b[A-ZÁÉÍÓÚÑ]\.)(?<=[.!?])\s+/u';

		$sentences = preg_split( $pattern, $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( false === $sentences ) {
			// Regex failure (e.g., invalid UTF-8) — fall back to whole text
			return [ $text ];
		}

		return $sentences;
	}

	/**
	 * Split long sentence by words, never discarding text
	 *
	 * @param string $sentence Long sentence
	 * @param int $limit Byte limit
	 * @return array Array of word chunks
	 */
	private static function splitLongSentence( string $sentence, int $limit ): array {
		$words = explode( ' ', $sentence );
		$chunks = [];
		$current_chunk = '';

		foreach ( $words as $word ) {
			$test_chunk = $current_chunk . ( '' === $current_chunk ? '' : ' ' ) . $word;

			if ( strlen( $test_chunk ) > $limit ) {
				if ( '' !== $current_chunk ) {
					$chunks[] = trim( $current_chunk );
					$current_chunk = '';
				}
				if ( strlen( $word ) > $limit ) {
					// Single word exceeds the limit: split it at UTF-8
					// character boundaries instead of truncating it.
					foreach ( self::splitWordByBytes( $word, $limit ) as $piece ) {
						$chunks[] = $piece;
					}
				} else {
					$current_chunk = $word;
				}
			} else {
				$current_chunk = $test_chunk;
			}
		}

		if ( '' !== $current_chunk ) {
			$chunks[] = trim( $current_chunk );
		}

		return $chunks;
	}

	/**
	 * Split a single over-long word into byte-limited pieces at character boundaries
	 *
	 * @param string $word Word longer than the limit
	 * @param int $limit Byte limit
	 * @return array Pieces, each within the byte limit
	 */
	private static function splitWordByBytes( string $word, int $limit ): array {
		$pieces = [];
		$buffer = '';
		$chars = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );

		if ( false === $chars ) {
			// Invalid UTF-8 — fall back to raw byte split
			return str_split( $word, $limit );
		}

		foreach ( $chars as $char ) {
			if ( strlen( $buffer . $char ) > $limit ) {
				$pieces[] = $buffer;
				$buffer = $char;
			} else {
				$buffer .= $char;
			}
		}

		if ( '' !== $buffer ) {
			$pieces[] = $buffer;
		}

		return $pieces;
	}

	/**
	 * Get byte limit for provider
	 *
	 * @param string $provider Provider name
	 * @return int Byte limit
	 */
	public static function getProviderLimit( string $provider ): int {
		return self::$provider_limits[ $provider ] ?? 3000;
	}

	/**
	 * Check if text needs chunking for provider
	 *
	 * @param string $text Text to check
	 * @param string $provider Provider name
	 * @return bool True if needs chunking
	 */
	public static function needsChunking( string $text, string $provider ): bool {
		$limit = self::getProviderLimit( $provider );
		return strlen( $text ) > $limit;
	}
}

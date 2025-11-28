<?php

namespace WP_TTS\Utils;

/**
 * Text Chunker
 * 
 * Handles splitting long text into chunks for TTS providers with character limits
 */
class TextChunker {

	/**
	 * Character limits for each provider
	 */
	private static $provider_limits = [
		'azure' => 8000,      // Azure TTS limit
		'polly' => 3000,      // Amazon Polly limit  
		'google' => 5000,     // Google TTS limit
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
		$limit = self::$provider_limits[ $provider ] ?? 3000;
		
		// If text is within limit, return as single chunk
		if ( strlen( $text ) <= $limit ) {
			return [ $text ];
		}

		$chunks = [];
		$sentences = self::splitIntoSentences( $text );
		$current_chunk = '';

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			
			if ( empty( $sentence ) ) {
				continue;
			}

			// If single sentence is too long, split by words
			if ( strlen( $sentence ) > $limit ) {
				// Save current chunk if not empty
				if ( ! empty( $current_chunk ) ) {
					$chunks[] = trim( $current_chunk );
					$current_chunk = '';
				}
				
				// Split long sentence by words
				$word_chunks = self::splitLongSentence( $sentence, $limit );
				$chunks = array_merge( $chunks, $word_chunks );
				continue;
			}

			// Check if adding this sentence would exceed limit
			$test_chunk = $current_chunk . ( empty( $current_chunk ) ? '' : ' ' ) . $sentence;
			
			if ( strlen( $test_chunk ) > $limit ) {
				// Save current chunk and start new one
				if ( ! empty( $current_chunk ) ) {
					$chunks[] = trim( $current_chunk );
				}
				$current_chunk = $sentence;
			} else {
				// Add sentence to current chunk
				$current_chunk = $test_chunk;
			}
		}

		// Add final chunk if not empty
		if ( ! empty( $current_chunk ) ) {
			$chunks[] = trim( $current_chunk );
		}

		return $chunks;
	}

	/**
	 * Split text into sentences
	 *
	 * @param string $text Text to split
	 * @return array Array of sentences
	 */
	private static function splitIntoSentences( string $text ): array {
		// Split by sentence endings, keeping the delimiter
		$sentences = preg_split( '/([.!?]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		
		$result = [];
		$current_sentence = '';
		
		foreach ( $sentences as $part ) {
			if ( preg_match( '/[.!?]+/', $part ) ) {
				// This is a delimiter, add to current sentence and save
				$current_sentence .= $part;
				$result[] = $current_sentence;
				$current_sentence = '';
			} else {
				// This is text, add to current sentence
				$current_sentence .= $part;
			}
		}
		
		// Add any remaining text as final sentence
		if ( ! empty( trim( $current_sentence ) ) ) {
			$result[] = $current_sentence;
		}
		
		return $result;
	}

	/**
	 * Split long sentence by words
	 *
	 * @param string $sentence Long sentence
	 * @param int $limit Character limit
	 * @return array Array of word chunks
	 */
	private static function splitLongSentence( string $sentence, int $limit ): array {
		$words = explode( ' ', $sentence );
		$chunks = [];
		$current_chunk = '';

		foreach ( $words as $word ) {
			$test_chunk = $current_chunk . ( empty( $current_chunk ) ? '' : ' ' ) . $word;
			
			if ( strlen( $test_chunk ) > $limit ) {
				if ( ! empty( $current_chunk ) ) {
					$chunks[] = trim( $current_chunk );
					$current_chunk = $word;
				} else {
					// Single word is too long, truncate it
					$chunks[] = substr( $word, 0, $limit - 3 ) . '...';
					$current_chunk = '';
				}
			} else {
				$current_chunk = $test_chunk;
			}
		}

		if ( ! empty( $current_chunk ) ) {
			$chunks[] = trim( $current_chunk );
		}

		return $chunks;
	}

	/**
	 * Get character limit for provider
	 *
	 * @param string $provider Provider name
	 * @return int Character limit
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
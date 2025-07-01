<?php
/**
 * Text processor for TTS content preparation
 *
 * @package WP_TTS
 */

namespace WP_TTS\Utils;

/**
 * Handles text processing and cleaning for TTS generation
 */
class TextProcessor {
	
	/**
	 * Extract and clean content from a WordPress post
	 *
	 * @param int $post_id Post ID
	 * @return string Cleaned content ready for editing
	 */
	public static function extractPostContent( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		
		// Get post content
		$content = $post->post_content;
		$title = $post->post_title;
		
		// Remove shortcodes but keep their content where possible
		$content = self::processShortcodes( $content );
		
		// Strip HTML tags but preserve basic structure
		$content = self::stripHtmlSmart( $content );
		
		// Clean up whitespace and special characters
		$content = self::cleanText( $content );
		
		// Combine title and content
		$full_text = trim( $title . '. ' . $content );
		
		return $full_text;
	}
	
	/**
	 * Process shortcodes for TTS conversion
	 *
	 * @param string $content Content with shortcodes
	 * @return string Content with shortcodes processed
	 */
	private static function processShortcodes( string $content ): string {
		// Remove or replace common shortcodes that don't work well with TTS
		$shortcode_replacements = [
			// Image captions - extract alt text
			'/\[caption[^\]]*\](.*?)\[\/caption\]/s' => '$1',
			
			// Gallery shortcodes - remove entirely
			'/\[gallery[^\]]*\]/i' => '',
			
			// Audio/Video shortcodes - replace with descriptive text
			'/\[audio[^\]]*\]/i' => '[Audio insertado]',
			'/\[video[^\]]*\]/i' => '[Video insertado]',
			
			// Embed shortcodes - replace with descriptive text
			'/\[embed[^\]]*\](.*?)\[\/embed\]/s' => '[Contenido embebido: $1]',
			
			// Code blocks - remove or replace
			'/\[code[^\]]*\](.*?)\[\/code\]/s' => '',
			
			// Contact forms - remove
			'/\[contact-form[^\]]*\]/i' => '',
			'/\[contact-form-7[^\]]*\]/i' => '',
			
			// Custom shortcodes that might exist
			'/\[tts[^\]]*\]/i' => '', // Remove TTS shortcodes to avoid recursion
		];
		
		foreach ( $shortcode_replacements as $pattern => $replacement ) {
			$content = preg_replace( $pattern, $replacement, $content );
		}
		
		// Process remaining shortcodes by executing them and getting their output
		$content = do_shortcode( $content );
		
		return $content;
	}
	
	/**
	 * Smart HTML stripping that preserves important content structure
	 *
	 * @param string $content HTML content
	 * @return string Plain text with preserved structure
	 */
	private static function stripHtmlSmart( string $content ): string {
		// Replace certain HTML elements with text equivalents
		$html_replacements = [
			// Headers - add periods for better speech
			'/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is' => '$1. ',
			
			// Paragraphs - add spacing
			'/<p[^>]*>(.*?)<\/p>/is' => '$1. ',
			
			// Lists - convert to readable format
			'/<li[^>]*>(.*?)<\/li>/is' => '- $1. ',
			'/<ul[^>]*>/i' => '',
			'/<\/ul>/i' => ' ',
			'/<ol[^>]*>/i' => '',
			'/<\/ol>/i' => ' ',
			
			// Line breaks
			'/<br[^>]*>/i' => '. ',
			
			// Emphasis - keep content but remove tags
			'/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/is' => '$2',
			'/<(em|i)[^>]*>(.*?)<\/(em|i)>/is' => '$2',
			
			// Links - keep text content only
			'/<a[^>]*>(.*?)<\/a>/is' => '$1',
			
			// Tables - simplified approach
			'/<th[^>]*>(.*?)<\/th>/is' => '$1: ',
			'/<td[^>]*>(.*?)<\/td>/is' => '$1, ',
			'/<tr[^>]*>/i' => '',
			'/<\/tr>/i' => '. ',
			
			// Divs and spans - just remove tags
			'/<div[^>]*>/i' => '',
			'/<\/div>/i' => ' ',
			'/<span[^>]*>/i' => '',
			'/<\/span>/i' => '',
		];
		
		foreach ( $html_replacements as $pattern => $replacement ) {
			$content = preg_replace( $pattern, $replacement, $content );
		}
		
		// Remove any remaining HTML tags
		$content = wp_strip_all_tags( $content );
		
		return $content;
	}
	
	/**
	 * Clean text for TTS processing
	 *
	 * @param string $text Raw text
	 * @return string Cleaned text
	 */
	private static function cleanText( string $text ): string {
		// Remove multiple spaces, tabs, newlines
		$text = preg_replace( '/\s+/', ' ', $text );
		
		// Basic HTML entity replacements
		$text = str_replace( '&amp;', ' y ', $text );
		$text = str_replace( '&nbsp;', ' ', $text );
		$text = str_replace( '&lt;', '<', $text );
		$text = str_replace( '&gt;', '>', $text );
		$text = str_replace( '&quot;', '"', $text );
		
		// Clean up multiple punctuation with simple regex
		$text = preg_replace( '/\.{2,}/', '.', $text );
		$text = preg_replace( '/\?{2,}/', '?', $text );
		$text = preg_replace( '/!{2,}/', '!', $text );
		
		// Remove empty brackets
		$text = preg_replace( '/\[\s*\]/', '', $text );
		$text = preg_replace( '/\(\s*\)/', '', $text );
		
		// Clean up spacing around punctuation
		$text = preg_replace( '/\s+([.!?])/', '$1', $text );
		$text = preg_replace( '/([.!?])\s+/', '$1 ', $text );
		
		// Clean remaining ampersands
		$text = preg_replace( '/&/', ' y ', $text );
		
		// Trim and clean up final spacing
		$text = trim( $text );
		
		// Ensure sentences end properly
		if ( ! empty( $text ) && ! in_array( substr( $text, -1 ), array( '.', '!', '?' ) ) ) {
			$text .= '.';
		}
		
		return $text;
	}
	
	/**
	 * Validate text for TTS generation
	 *
	 * @param string $text Text to validate
	 * @return array Validation result with 'valid' boolean and 'message' string
	 */
	public static function validateTextForTTS( string $text ): array {
		$text = trim( $text );
		
		if ( empty( $text ) ) {
			return [
				'valid' => false,
				'message' => 'El texto está vacío.'
			];
		}
		
		if ( strlen( $text ) < 5 ) {
			return [
				'valid' => false,
				'message' => 'El texto es demasiado corto (mínimo 5 caracteres).'
			];
		}
		
		if ( strlen( $text ) > 50000 ) {
			return [
				'valid' => false,
				'message' => 'El texto es demasiado largo (máximo 50,000 caracteres).'
			];
		}
		
		// Check for too many special characters
		$special_char_count = preg_match_all( '/[^\w\s\.,\!\?\-\(\)]/', $text );
		$text_length = strlen( $text );
		
		if ( $special_char_count > ( $text_length * 0.1 ) ) {
			return [
				'valid' => false,
				'message' => 'El texto contiene demasiados caracteres especiales.'
			];
		}
		
		return [
			'valid' => true,
			'message' => 'El texto es válido para TTS.'
		];
	}
}
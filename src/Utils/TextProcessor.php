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

		// Remove Gutenberg blocks that shouldn't be read (images, HTML blocks, etc.)
		$content = self::filterGutenbergBlocks( $content );

		// Remove shortcodes but keep their content where possible
		$content = self::processShortcodes( $content );

		// Remove elements matching user-configured CSS selectors and any
		// aria-hidden="true" elements (e.g. numbered reference links)
		$content = self::removeExcludedElements( $content );

		// Strip HTML tags but preserve basic structure
		$content = self::stripHtmlSmart( $content );
		
		// Clean up whitespace and special characters
		$content = self::cleanText( $content );
		
		// Combine title and content
		$full_text = trim( $title . '. ' . $content );
		
		return $full_text;
	}
	
	/**
	 * Filter out Gutenberg blocks that shouldn't be converted to speech
	 *
	 * Removes blocks like images, custom HTML, embeds, etc. that contain
	 * content not suitable for audio narration.
	 *
	 * @param string $content Post content with Gutenberg blocks
	 * @return string Content with unwanted blocks removed
	 */
	public static function filterGutenbergBlocks( string $content ): string {
		// List of Gutenberg block types to completely remove
		$blocks_to_remove = [
			// Media blocks
			'wp:image',
			'wp:gallery',
			'wp:audio',
			'wp:video',
			'wp:cover',
			'wp:media-text',
			'wp:file',

			// Custom HTML and code blocks
			'wp:html',
			'wp:code',
			'wp:preformatted',

			// Embed blocks
			'wp:embed',
			'wp:core-embed/youtube',
			'wp:core-embed/twitter',
			'wp:core-embed/facebook',
			'wp:core-embed/instagram',
			'wp:core-embed/vimeo',
			'wp:core-embed/soundcloud',
			'wp:core-embed/spotify',
			'wp:core-embed/flickr',
			'wp:core-embed/tiktok',

			// Widget and dynamic blocks
			'wp:shortcode',
			'wp:archives',
			'wp:calendar',
			'wp:categories',
			'wp:latest-comments',
			'wp:latest-posts',
			'wp:rss',
			'wp:search',
			'wp:tag-cloud',
			'wp:social-links',
			'wp:social-link',
			'wp:navigation',
			'wp:navigation-link',
			'wp:site-logo',
			'wp:site-title',
			'wp:site-tagline',
			'wp:login-logout',
			'wp:page-list',
			'wp:post-navigation-link',

			// Table of contents and indexes
			'wp:table-of-contents',
			'yoast-seo/table-of-contents',
			'rank-math/toc-block',

			// Forms
			'wp:form',
			'contact-form-7/contact-form-selector',
			'wpforms/form-selector',
			'formidable/simple-form',
			'gravityforms/form',

			// Separators and spacers (no content anyway)
			'wp:separator',
			'wp:spacer',

			// Buttons (not useful for audio)
			'wp:buttons',
			'wp:button',
		];

		// Remove each block type
		foreach ( $blocks_to_remove as $block_type ) {
			// Pattern for self-closing blocks: <!-- wp:image {"id":123} /-->
			$pattern_self_closing = '/<!--\s*' . preg_quote( $block_type, '/' ) . '(?:\s+\{[^}]*\})?\s*\/?-->/s';
			$content = preg_replace( $pattern_self_closing, '', $content );

			// Pattern for blocks with content: <!-- wp:html -->content<!-- /wp:html -->
			$pattern_with_content = '/<!--\s*' . preg_quote( $block_type, '/' ) . '(?:\s+\{[^}]*\})?\s*-->.*?<!--\s*\/' . preg_quote( $block_type, '/' ) . '\s*-->/s';
			$content = preg_replace( $pattern_with_content, '', $content );
		}

		// Remove image tags that might not be in Gutenberg blocks
		$content = preg_replace( '/<img[^>]*>/i', '', $content );

		// Remove figure elements containing images
		$content = preg_replace( '/<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>.*?<\/figure>/is', '', $content );

		// Remove figcaption elements (image captions)
		$content = preg_replace( '/<figcaption[^>]*>.*?<\/figcaption>/is', '', $content );

		// Remove iframe embeds
		$content = preg_replace( '/<iframe[^>]*>.*?<\/iframe>/is', '', $content );
		$content = preg_replace( '/<iframe[^>]*\/>/i', '', $content );

		// Remove object and embed tags
		$content = preg_replace( '/<object[^>]*>.*?<\/object>/is', '', $content );
		$content = preg_replace( '/<embed[^>]*>/i', '', $content );

		// Remove script and style tags
		$content = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $content );
		$content = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $content );

		// Remove noscript tags
		$content = preg_replace( '/<noscript[^>]*>.*?<\/noscript>/is', '', $content );

		// Remove SVG elements
		$content = preg_replace( '/<svg[^>]*>.*?<\/svg>/is', '', $content );

		// Remove any remaining Gutenberg block comments
		$content = preg_replace( '/<!--\s*wp:[^\s]+[^>]*-->/s', '', $content );
		$content = preg_replace( '/<!--\s*\/wp:[^\s]+\s*-->/s', '', $content );

		// Clean up URLs that might appear alone (from removed links context)
		// Keep URLs that are part of sentences, remove standalone URLs
		$content = preg_replace( '/(?<!["\'])(https?:\/\/[^\s<>"\']+)(?!["\'])/i', '', $content );

		return $content;
	}

	/**
	 * Remove HTML elements that should never reach the TTS engine
	 *
	 * Two sources of exclusion:
	 * 1. Elements with aria-hidden="true" — by definition not meant to be
	 *    read aloud (e.g. numbered reference links rendered as "1 2 3 4 5"
	 *    that otherwise get spoken as "twelve thousand three hundred...").
	 * 2. User-configured CSS selectors from Settings → Defaults
	 *    (defaults.excluded_css_selectors): comma-separated list of
	 *    .class-name or #element-id tokens (a bare token is treated as a
	 *    class name).
	 *
	 * @param string      $content   HTML content
	 * @param string|null $selectors Comma-separated selectors; null = read from plugin config
	 * @return string Content with excluded elements removed
	 */
	public static function removeExcludedElements( string $content, ?string $selectors = null ): string {
		if ( strpos( $content, '<' ) === false ) {
			return $content;
		}

		if ( $selectors === null ) {
			$config    = get_option( 'wp_tts_config', array() );
			$selectors = $config['defaults']['excluded_css_selectors'] ?? '.aicg-references';
		}

		$tokens = array_filter( array_map( 'trim', explode( ',', (string) $selectors ) ) );

		if ( class_exists( '\DOMDocument' ) ) {
			return self::removeExcludedElementsDom( $content, $tokens );
		}

		return self::removeExcludedElementsRegex( $content, $tokens );
	}

	/**
	 * DOM-based removal (robust against nested markup)
	 *
	 * @param string $content HTML content
	 * @param array  $tokens  Selector tokens (.class, #id or bare class name)
	 * @return string Filtered content
	 */
	private static function removeExcludedElementsDom( string $content, array $tokens ): string {
		$dom = new \DOMDocument();
		$previous_errors = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8"><html><body>' . $content . '</body></html>',
			LIBXML_NOWARNING | LIBXML_NOERROR
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( ! $loaded ) {
			return self::removeExcludedElementsRegex( $content, $tokens );
		}

		$xpath   = new \DOMXPath( $dom );
		$queries = array( '//*[@aria-hidden="true"]' );

		foreach ( $tokens as $token ) {
			if ( strpos( $token, '#' ) === 0 ) {
				$queries[] = sprintf( '//*[@id="%s"]', substr( $token, 1 ) );
			} else {
				$class_name = ltrim( $token, '.' );
				if ( $class_name !== '' ) {
					$queries[] = sprintf(
						'//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
						$class_name
					);
				}
			}
		}

		foreach ( $queries as $query ) {
			$nodes = $xpath->query( $query );
			if ( ! $nodes ) {
				continue;
			}
			// Iterate over a static copy: removing while iterating a live
			// DOMNodeList skips nodes
			foreach ( iterator_to_array( $nodes ) as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return $content;
		}

		$html = '';
		foreach ( $body->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Regex fallback when the DOM extension is unavailable
	 *
	 * Less robust with nested identical tags, but covers the common case of
	 * flat reference/link containers.
	 *
	 * @param string $content HTML content
	 * @param array  $tokens  Selector tokens
	 * @return string Filtered content
	 */
	private static function removeExcludedElementsRegex( string $content, array $tokens ): string {
		// aria-hidden="true" elements
		$content = preg_replace(
			'/<(\w+)[^>]*\baria-hidden=["\']true["\'][^>]*>.*?<\/\1>/is',
			'',
			$content
		);

		foreach ( $tokens as $token ) {
			if ( strpos( $token, '#' ) === 0 ) {
				$id = preg_quote( substr( $token, 1 ), '/' );
				$content = preg_replace(
					'/<(\w+)[^>]*\bid=["\']' . $id . '["\'][^>]*>.*?<\/\1>/is',
					'',
					$content
				);
			} else {
				$class_name = preg_quote( ltrim( $token, '.' ), '/' );
				if ( $class_name === '' ) {
					continue;
				}
				$content = preg_replace(
					'/<(\w+)[^>]*\bclass=["\'][^"\']*\b' . $class_name . '\b[^"\']*["\'][^>]*>.*?<\/\1>/is',
					'',
					$content
				);
			}
		}

		return $content;
	}

	/**
	 * Get list of Gutenberg block types that are filtered out
	 *
	 * Useful for admin interface to show users what blocks are skipped
	 *
	 * @return array List of block type names
	 */
	public static function getFilteredBlockTypes(): array {
		return [
			'Imágenes (wp:image, wp:gallery, wp:cover)',
			'Audio y Video (wp:audio, wp:video)',
			'HTML personalizado (wp:html)',
			'Código (wp:code, wp:preformatted)',
			'Embeds (YouTube, Twitter, Facebook, Instagram, etc.)',
			'Widgets (archivos, calendario, categorías, etc.)',
			'Navegación y enlaces sociales',
			'Formularios de contacto',
			'Separadores y espaciadores',
			'Botones',
		];
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
		$text = preg_replace( '/\s+/u', ' ', $text );
		
		// Decode ALL HTML entities (named + numeric) so accented Spanish content
		// pasted from editors (&aacute; &eacute; &ntilde; &#8217; &hellip; …) is
		// resolved to real characters instead of being read out literally
		// ("y aacute") by the residual ampersand handling below.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Normalize any decoded non-breaking spaces (U+00A0) to plain spaces.
		$text = str_replace( "\xC2\xA0", ' ', $text );
		
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
		
		// Ensure sentences end properly (mb-safe: last char may be multibyte)
		if ( ! empty( $text ) && ! in_array( mb_substr( $text, -1 ), array( '.', '!', '?' ), true ) ) {
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
		
		$char_length = mb_strlen( $text );

		if ( $char_length < 5 ) {
			return [
				'valid' => false,
				'message' => 'El texto es demasiado corto (mínimo 5 caracteres).'
			];
		}

		if ( $char_length > 50000 ) {
			return [
				'valid' => false,
				'message' => 'El texto es demasiado largo (máximo 50,000 caracteres).'
			];
		}

		// Check for too many special characters. Unicode-aware: letters (á, ñ),
		// digits, whitespace and normal Spanish punctuation (¿ ¡ « » — …) do
		// NOT count as special.
		$special_char_count = preg_match_all( '/[^\p{L}\p{N}\s\.,;:\!\?\-\(\)¿¡«»"\'—–…%$€]/u', $text );

		if ( $special_char_count > ( $char_length * 0.1 ) ) {
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
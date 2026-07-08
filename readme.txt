=== TTS SesoLibre ===
Contributors: ellaguno
Tags: text-to-speech, tts, audio, accessibility, podcast
Requires at least: 6.1
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert your posts to audio using multiple TTS providers (OpenAI, Google, Azure, Amazon Polly, ElevenLabs), with a Spanish-language focus.

== Description ==

TTS SesoLibre converts your articles to speech and embeds an audio player in your posts. It supports multiple text-to-speech providers with per-post voice selection, cost-aware provider choice, and optional podcast publishing.

Features:

* Five TTS providers: OpenAI, Google Cloud, Microsoft Azure, Amazon Polly and ElevenLabs.
* Automatic chunking of long articles with audio concatenation.
* Four front-end player styles (classic, SesoLibre, minimal, enhanced) with keyboard-accessible controls.
* Intro, outro and background-music mixing.
* Optional publishing of generated audio as Buzzsprout podcast episodes.
* Text editing before generation, with per-provider cost estimates.
* Provider API keys encrypted at rest.
* Spanish-language focus: text cleaning and sentence handling tuned for Spanish content.

Idioma: el plugin está enfocado en sitios en español; la interfaz de administración está disponible en español.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/tts-sesolibre/`, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen.
3. Go to Settings → TTS Settings to configure at least one provider (API key) and choose a player style.
4. Edit any post, enable TTS in the "Configuración de Texto a Voz" box and generate the audio.

== Frequently Asked Questions ==

= Which providers are supported? =

OpenAI TTS, Google Cloud Text-to-Speech, Microsoft Azure Speech, Amazon Polly and ElevenLabs. Each needs its own API credentials.

= Where is the audio stored? =

Locally in `wp-content/uploads/tts-audio/` by default. You can also publish episodes automatically to Buzzsprout.

= Are my API keys safe? =

Provider keys are encrypted at rest (AES-256-GCM, keyed from your WordPress salts) before being stored in the database.

== Changelog ==

= 2.0.0 =
* Auto-generation of audio for posts published in selected categories (new "Auto-Generación" settings tab).
* Gutenberg block filtering before narration: images, embeds, custom HTML, forms, buttons and standalone URLs are no longer read aloud.
* Google Cloud TTS voices are fetched dynamically from the API (full Wavenet/Neural2/Studio catalog), with static fallback.
* Fixed CORS error when loading Buzzsprout-hosted audio in the player.
* Rate limiting for manual audio generation (10 requests/min per user, filterable).
* Formatted service statistics table in Tools (providers, cache size, posts with audio).
* Fixed the Settings link on the Plugins page pointing to the wrong admin URL.

= 1.7.0 =
* Unified configuration into a single option with automatic migration.
* Consolidated the player variants into one shared engine with accessibility improvements.
* Long-text chunking for OpenAI, Google and ElevenLabs (no more silent truncation).
* Provider API keys encrypted at rest.
* Security hardening: output escaping, path traversal fixes, per-post capability checks.
* Honest generation progress and inline notices in the editor.

= 1.6.8 =
* Buzzsprout episode details and automatic publishing, configurable from settings.

== Upgrade Notice ==

= 2.0.0 =
Restores and improves features from the 1.9.x series (category auto-generation, Gutenberg filtering, Buzzsprout CORS fix) on top of the 1.7.0 refactor. Update from any prior version.

= 1.7.0 =
Major internal refactor (configuration, players, security). Settings are migrated automatically; review your provider configuration after updating.

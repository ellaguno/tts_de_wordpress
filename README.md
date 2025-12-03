=== TTS SesoLibre ===
Contributors: sesolibre
Tags: tts, text-to-speech, audio, accessibility, podcast
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An advanced Text-to-Speech (TTS) plugin for WordPress supporting multiple TTS providers and cloud storage.

== Description ==

TTS SesoLibre is a powerful Text-to-Speech plugin that converts your WordPress content to audio using multiple TTS providers.

**Key Features:**

* **Multiple TTS Providers**: Amazon Polly, Azure TTS, Google Cloud TTS, OpenAI TTS, ElevenLabs
* **Cloud Storage**: Buzzsprout integration for audio file hosting
* **Round-Robin System**: Automatic load distribution between providers
* **Smart Cache**: Caching system to optimize performance
* **Admin Interface**: Complete control panel in WordPress
* **Mock Mode**: Testing functionality without API credentials

== Installation ==

1. Download the plugin from this repository
2. Upload the ZIP file to your WordPress in `Plugins > Add New > Upload Plugin`
3. Activate the plugin from the admin panel
4. Configure TTS providers in `Settings > TTS Settings`

== Configuration ==

= Supported TTS Providers =

**Amazon Polly**
* AWS Access Key ID
* AWS Secret Access Key
* AWS Region
* Voice selection

**Azure TTS**
* Subscription Key
* Service Region
* Voice selection

**Google Cloud TTS**
* Service Account JSON
* Voice selection

**OpenAI TTS**
* API Key
* Voice model
* Voice selection

**ElevenLabs**
* API Key
* Voice selection

= Storage =

**Buzzsprout**
* API Token
* Podcast ID

== Usage ==

= Automatic Generation =
The plugin can automatically generate TTS audio files for:
* Blog posts
* Pages
* Custom content

= Shortcodes =
`[tts_audio text="Your text here"]`

= Programmatic API =
`$tts_service = wp_tts_get_service();`
`$audio_url = $tts_service->generateSpeech('Your text here');`

== Frequently Asked Questions ==

= What TTS providers are supported? =

The plugin supports Amazon Polly, Azure TTS, Google Cloud TTS, OpenAI TTS, and ElevenLabs.

= Do I need API credentials? =

Yes, you need API credentials from at least one TTS provider. However, the plugin includes a mock mode for testing without credentials.

= Can I use multiple providers? =

Yes, the plugin includes a round-robin system to distribute load between multiple configured providers.

== Changelog ==

= 1.9.0 =
* Changed plugin slug to sesolibre
* Fixed all WordPress Plugin Check errors
* Improved security and code quality

= 1.7.0 =
* Fixed Tools page and improved statistics
* Code cleanup and security improvements

= 1.6.9 =
* Code cleanup, security and improvements

= 1.0.0 =
* Initial implementation with support for 5 TTS providers
* Round-robin system
* Complete admin interface
* Mock mode for testing
* Cache system
* Buzzsprout integration

== Upgrade Notice ==

= 1.9.0 =
This version changes the plugin slug to sesolibre and fixes all WordPress Plugin Check errors. Upgrade recommended.

== Screenshots ==

1. Admin settings page
2. TTS meta box in post editor
3. Audio player on frontend

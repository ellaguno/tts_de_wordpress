<?php
/**
 * TTS Meta Manager - Unified metadata management for TTS posts
 *
 * @package WP_TTS\Utils
 */

namespace WP_TTS\Utils;

/**
 * Manages TTS metadata as a single JSON record per post
 */
class TTSMetaManager {
    
    /**
     * Meta key for unified TTS data
     */
    const META_KEY = '_tts_sesolibre';
    
    /**
     * Current version of the metadata structure
     */
    const VERSION = '1.0';
    
    /**
     * Default metadata structure
     *
     * @return array
     */
    private static function getDefaultData(): array {
        return [
            'version' => self::VERSION,
            'enabled' => false,
            'audio' => [
                'url' => '',
                'generated_at' => null,
                'status' => 'pending', // pending, generating, completed, failed
                'duration' => 0,
                'file_size' => 0,
                'format' => 'mp3'
            ],
            'voice' => [
                'provider' => '',
                'voice_id' => '',
                'language' => 'es-MX'
            ],
            'content' => [
                'custom_text' => '',
                'use_custom_text' => false,
                'last_post_hash' => '', // Hash of post content when audio was generated
                'content_modified' => false
            ],
            'generation' => [
                'last_attempt' => null,
                'attempts_count' => 0,
                'last_error' => '',
                'cache_key' => ''
            ],
            'stats' => [
                'character_count' => 0,
                'generation_time_ms' => 0,
                'cost_estimate' => 0.0
            ],
            'audio_assets' => [
                'intro_audio' => '',
                'outro_audio' => '',
                'background_audio' => '',
                'background_volume' => 0.3,
                'custom_audio' => ''
            ],
            'updated_at' => null
        ];
    }
    
    /**
     * Get TTS metadata for a post
     *
     * @param int $post_id Post ID
     * @return array TTS metadata
     */
    public static function getTTSData(int $post_id): array {
        $data = get_post_meta($post_id, self::META_KEY, true);
        
        if (empty($data) || !is_array($data)) {
            return self::getDefaultData();
        }
        
        // Merge with defaults to ensure all keys exist
        return array_replace_recursive(self::getDefaultData(), $data);
    }
    
    /**
     * Save TTS metadata for a post
     *
     * @param int $post_id Post ID
     * @param array $data TTS data to save
     * @return bool Success
     */
    public static function saveTTSData(int $post_id, array $data): bool {
        // Log the operation start
        error_log("[TTSMetaManager] Starting saveTTSData for post $post_id");
        error_log("[TTSMetaManager] Input data: " . print_r($data, true));
        
        try {
            // Ensure we have the current timestamp
            $data['updated_at'] = current_time('mysql');
            error_log("[TTSMetaManager] Added timestamp: " . $data['updated_at']);
            
            // Validate and sanitize data
            $data = self::validateAndSanitizeData($data);
            error_log("[TTSMetaManager] Data after validation: " . print_r($data, true));
            
            // Try to save
            error_log("[TTSMetaManager] Attempting update_post_meta with meta_key: " . self::META_KEY);
            $result = update_post_meta($post_id, self::META_KEY, $data);
            error_log("[TTSMetaManager] update_post_meta result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // Verify it was saved
            $saved_data = get_post_meta($post_id, self::META_KEY, true);
            if ($saved_data) {
                error_log("[TTSMetaManager] Verification: Data was saved successfully");
            } else {
                error_log("[TTSMetaManager] Verification: Data was NOT saved!");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("[TTSMetaManager] Exception in saveTTSData: " . $e->getMessage());
            error_log("[TTSMetaManager] Exception trace: " . $e->getTraceAsString());
            return false;
        } catch (\Error $e) {
            error_log("[TTSMetaManager] Fatal error in saveTTSData: " . $e->getMessage());
            error_log("[TTSMetaManager] Error trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Update specific section of TTS data
     *
     * @param int $post_id Post ID
     * @param string $section Section to update (audio, voice, content, generation, stats)
     * @param array $section_data Data for the section
     * @return bool Success
     */
    public static function updateTTSSection(int $post_id, string $section, array $section_data): bool {
        error_log("[TTSMetaManager] Starting updateTTSSection for post $post_id, section: $section");
        error_log("[TTSMetaManager] Section data: " . print_r($section_data, true));
        
        $current_data = self::getTTSData($post_id);
        error_log("[TTSMetaManager] Current data: " . print_r($current_data, true));
        
        if (!isset($current_data[$section])) {
            error_log("[TTSMetaManager] Section '$section' not found in current data structure");
            // Initialize with defaults if section doesn't exist
            $defaults = self::getDefaultData();
            if (isset($defaults[$section])) {
                $current_data[$section] = $defaults[$section];
                error_log("[TTSMetaManager] Initialized section '$section' with defaults");
            } else {
                error_log("[TTSMetaManager] Section '$section' not found in defaults either - cannot proceed");
                return false;
            }
        }
        
        $current_data[$section] = array_merge($current_data[$section], $section_data);
        error_log("[TTSMetaManager] Data after merge: " . print_r($current_data, true));
        
        $result = self::saveTTSData($post_id, $current_data);
        error_log("[TTSMetaManager] updateTTSSection save result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        return $result;
    }
    
    /**
     * Check if TTS is enabled for a post
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public static function isTTSEnabled(int $post_id): bool {
        $data = self::getTTSData($post_id);
        return (bool) $data['enabled'];
    }
    
    /**
     * Enable/disable TTS for a post
     *
     * @param int $post_id Post ID
     * @param bool $enabled Enable or disable
     * @return bool Success
     */
    public static function setTTSEnabled(int $post_id, bool $enabled): bool {
        error_log("[TTSMetaManager] setTTSEnabled called for post $post_id, enabled: " . ($enabled ? 'true' : 'false'));
        
        $data = self::getTTSData($post_id);
        error_log("[TTSMetaManager] Current data before setting enabled: " . print_r($data, true));
        
        $data['enabled'] = $enabled;
        
        $result = self::saveTTSData($post_id, $data);
        error_log("[TTSMetaManager] setTTSEnabled save result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        return $result;
    }
    
    /**
     * Get audio URL for a post
     *
     * @param int $post_id Post ID
     * @return string Audio URL or empty string
     */
    public static function getAudioUrl(int $post_id): string {
        $data = self::getTTSData($post_id);
        return $data['audio']['url'] ?? '';
    }
    
    /**
     * Set audio information
     *
     * @param int $post_id Post ID
     * @param string $url Audio URL
     * @param array $metadata Additional audio metadata
     * @return bool Success
     */
    public static function setAudioInfo(int $post_id, string $url, array $metadata = []): bool {
        $audio_data = array_merge([
            'url' => $url,
            'generated_at' => current_time('mysql'),
            'status' => 'completed'
        ], $metadata);
        
        return self::updateTTSSection($post_id, 'audio', $audio_data);
    }
    
    /**
     * Get voice configuration
     *
     * @param int $post_id Post ID
     * @return array Voice configuration
     */
    public static function getVoiceConfig(int $post_id): array {
        $data = self::getTTSData($post_id);
        return $data['voice'] ?? [];
    }
    
    /**
     * Set voice configuration
     *
     * @param int $post_id Post ID
     * @param string $provider Voice provider
     * @param string $voice_id Voice ID
     * @param string $language Language code
     * @return bool Success
     */
    public static function setVoiceConfig(int $post_id, string $provider, string $voice_id = '', string $language = 'es-MX'): bool {
        error_log("[TTSMetaManager] setVoiceConfig called for post $post_id");
        error_log("[TTSMetaManager] provider: '$provider', voice_id: '$voice_id', language: '$language'");
        
        $voice_data = [
            'provider' => $provider,
            'voice_id' => $voice_id,
            'language' => $language
        ];
        
        // Ensure we have valid data structure first
        $current_data = self::getTTSData($post_id);
        error_log("[TTSMetaManager] Current data before voice config: " . print_r($current_data, true));
        
        $result = self::updateTTSSection($post_id, 'voice', $voice_data);
        error_log("[TTSMetaManager] setVoiceConfig result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        return $result;
    }
    
    /**
     * Get custom text configuration
     *
     * @param int $post_id Post ID
     * @return array Custom text configuration
     */
    public static function getCustomTextConfig(int $post_id): array {
        $data = self::getTTSData($post_id);
        return $data['content'] ?? [];
    }
    
    /**
     * Set custom text
     *
     * @param int $post_id Post ID
     * @param string $custom_text Custom text
     * @param bool $use_custom Whether to use custom text instead of post content
     * @return bool Success
     */
    public static function setCustomText(int $post_id, string $custom_text, bool $use_custom = true): bool {
        $content_data = [
            'custom_text' => $custom_text,
            'use_custom_text' => $use_custom
        ];
        
        return self::updateTTSSection($post_id, 'content', $content_data);
    }
    
    /**
     * Mark content as modified (when post content changes)
     *
     * @param int $post_id Post ID
     * @param string $post_content Current post content
     * @return bool Success
     */
    public static function markContentModified(int $post_id, string $post_content): bool {
        $content_hash = md5($post_content);
        $data = self::getTTSData($post_id);
        
        $is_modified = $data['content']['last_post_hash'] !== $content_hash;
        
        $content_data = [
            'last_post_hash' => $content_hash,
            'content_modified' => $is_modified
        ];
        
        return self::updateTTSSection($post_id, 'content', $content_data);
    }
    
    /**
     * Record generation attempt
     *
     * @param int $post_id Post ID
     * @param bool $success Whether generation was successful
     * @param string $error_message Error message if failed
     * @param int $generation_time_ms Generation time in milliseconds
     * @return bool Success
     */
    public static function recordGenerationAttempt(int $post_id, bool $success, string $error_message = '', int $generation_time_ms = 0): bool {
        $data = self::getTTSData($post_id);
        
        // Update generation info
        $generation_data = [
            'last_attempt' => current_time('mysql'),
            'attempts_count' => ($data['generation']['attempts_count'] ?? 0) + 1,
            'last_error' => $error_message
        ];
        
        // Update stats if successful
        if ($success && $generation_time_ms > 0) {
            $stats_data = [
                'generation_time_ms' => $generation_time_ms
            ];
            self::updateTTSSection($post_id, 'stats', $stats_data);
        }
        
        // Update audio status
        $audio_status = $success ? 'completed' : 'failed';
        self::updateTTSSection($post_id, 'audio', ['status' => $audio_status]);
        
        return self::updateTTSSection($post_id, 'generation', $generation_data);
    }
    
    /**
     * Delete TTS data for a post
     *
     * @param int $post_id Post ID
     * @return bool Success
     */
    public static function deleteTTSData(int $post_id): bool {
        return delete_post_meta($post_id, self::META_KEY);
    }
    
    /**
     * Validate and sanitize TTS data
     *
     * @param array $data Raw data
     * @return array Sanitized data
     */
    private static function validateAndSanitizeData(array $data): array {
        error_log("[TTSMetaManager] Starting validateAndSanitizeData");
        error_log("[TTSMetaManager] Input data for validation: " . print_r($data, true));
        
        try {
            $defaults = self::getDefaultData();
            error_log("[TTSMetaManager] Default data structure: " . print_r($defaults, true));
            
            // Ensure all required keys exist - use array_merge, not array_merge_recursive
            $data = array_replace_recursive($defaults, $data);
            error_log("[TTSMetaManager] Data after merge with defaults: " . print_r($data, true));
            
            // Sanitize specific fields
            $data['enabled'] = (bool) ($data['enabled'] ?? false);
            $data['audio']['url'] = esc_url_raw($data['audio']['url'] ?? '');
            $data['audio']['status'] = sanitize_text_field($data['audio']['status'] ?? 'pending');
            $data['voice']['provider'] = sanitize_text_field($data['voice']['provider'] ?? '');
            $data['voice']['voice_id'] = sanitize_text_field($data['voice']['voice_id'] ?? '');
            $data['voice']['language'] = sanitize_text_field($data['voice']['language'] ?? 'es-MX');
            $data['content']['custom_text'] = sanitize_textarea_field($data['content']['custom_text'] ?? '');
            $data['content']['use_custom_text'] = (bool) ($data['content']['use_custom_text'] ?? false);
            
            // Sanitize numbers
            $data['audio']['duration'] = max(0, intval($data['audio']['duration'] ?? 0));
            $data['audio']['file_size'] = max(0, intval($data['audio']['file_size'] ?? 0));
            $data['generation']['attempts_count'] = max(0, intval($data['generation']['attempts_count'] ?? 0));
            $data['stats']['character_count'] = max(0, intval($data['stats']['character_count'] ?? 0));
            $data['stats']['generation_time_ms'] = max(0, intval($data['stats']['generation_time_ms'] ?? 0));
            $data['stats']['cost_estimate'] = max(0, floatval($data['stats']['cost_estimate'] ?? 0));
            
            error_log("[TTSMetaManager] Final sanitized data: " . print_r($data, true));
            return $data;
            
        } catch (\Exception $e) {
            error_log("[TTSMetaManager] Exception in validateAndSanitizeData: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Migrate old metadata format to new unified format
     *
     * @param int $post_id Post ID
     * @return bool Success
     */
    public static function migrateOldMetadata(int $post_id): bool {
        // Check if new format already exists
        $existing_data = get_post_meta($post_id, self::META_KEY, true);
        if (!empty($existing_data) && is_array($existing_data)) {
            return true; // Already migrated
        }
        
        // Get old metadata
        $old_data = [
            'enabled' => get_post_meta($post_id, '_tts_enabled', true),
            'audio_url' => get_post_meta($post_id, '_tts_audio_url', true),
            'voice_provider' => get_post_meta($post_id, '_tts_voice_provider', true),
            'voice_id' => get_post_meta($post_id, '_tts_voice_id', true),
            'custom_text' => get_post_meta($post_id, '_tts_custom_text', true),
            'generated_at' => get_post_meta($post_id, '_tts_generated_at', true),
            'generation_status' => get_post_meta($post_id, '_tts_generation_status', true),
            'last_generated' => get_post_meta($post_id, '_tts_last_generated', true)
        ];
        
        // Convert to new format
        $new_data = self::getDefaultData();
        
        if (!empty($old_data['enabled'])) {
            $new_data['enabled'] = (bool) $old_data['enabled'];
        }
        
        if (!empty($old_data['audio_url'])) {
            $new_data['audio']['url'] = $old_data['audio_url'];
            $new_data['audio']['status'] = !empty($old_data['generation_status']) ? $old_data['generation_status'] : 'completed';
        }
        
        if (!empty($old_data['voice_provider'])) {
            $new_data['voice']['provider'] = $old_data['voice_provider'];
        }
        
        if (!empty($old_data['voice_id'])) {
            $new_data['voice']['voice_id'] = $old_data['voice_id'];
        }
        
        if (!empty($old_data['custom_text'])) {
            $new_data['content']['custom_text'] = $old_data['custom_text'];
            $new_data['content']['use_custom_text'] = true;
        }
        
        if (!empty($old_data['generated_at'])) {
            $timestamp = is_numeric($old_data['generated_at']) ? 
                date('Y-m-d H:i:s', $old_data['generated_at']) : 
                $old_data['generated_at'];
            $new_data['audio']['generated_at'] = $timestamp;
        }
        
        if (!empty($old_data['last_generated'])) {
            $timestamp = is_numeric($old_data['last_generated']) ? 
                date('Y-m-d H:i:s', $old_data['last_generated']) : 
                $old_data['last_generated'];
            $new_data['generation']['last_attempt'] = $timestamp;
        }
        
        // Save new format
        $success = self::saveTTSData($post_id, $new_data);
        
        if ($success) {
            // Clean up old metadata
            delete_post_meta($post_id, '_tts_enabled');
            delete_post_meta($post_id, '_tts_audio_url');
            delete_post_meta($post_id, '_tts_voice_provider');
            delete_post_meta($post_id, '_tts_voice_id');
            delete_post_meta($post_id, '_tts_custom_text');
            delete_post_meta($post_id, '_tts_generated_at');
            delete_post_meta($post_id, '_tts_generation_status');
            delete_post_meta($post_id, '_tts_last_generated');
        }
        
        return $success;
    }
    
    /**
     * Get all posts with TTS data (for bulk operations)
     *
     * @param array $args Additional query args
     * @return array Post IDs
     */
    public static function getPostsWithTTS(array $args = []): array {
        $default_args = [
            'post_type' => ['post', 'page'],
            'meta_key' => self::META_KEY,
            'fields' => 'ids',
            'posts_per_page' => -1
        ];
        
        $query_args = array_merge($default_args, $args);
        $query = new \WP_Query($query_args);
        
        return $query->posts;
    }
    
    /**
     * Get TTS statistics across all posts
     *
     * @return array Statistics
     */
    public static function getTTSStatistics(): array {
        global $wpdb;
        
        $meta_key = self::META_KEY;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
        ", $meta_key));
        
        $stats = [
            'total_posts' => 0,
            'enabled_posts' => 0,
            'completed_audio' => 0,
            'total_audio_files' => 0,
            'providers_used' => [],
            'languages_used' => [],
            'total_generation_time' => 0,
            'total_cost_estimate' => 0.0
        ];
        
        foreach ($results as $result) {
            $data = json_decode($result->meta_value, true);
            if (!is_array($data)) continue;
            
            $stats['total_posts']++;
            
            if (!empty($data['enabled'])) {
                $stats['enabled_posts']++;
            }
            
            if (!empty($data['audio']['url']) && $data['audio']['status'] === 'completed') {
                $stats['completed_audio']++;
                $stats['total_audio_files']++;
            }
            
            if (!empty($data['voice']['provider'])) {
                $provider = $data['voice']['provider'];
                $stats['providers_used'][$provider] = ($stats['providers_used'][$provider] ?? 0) + 1;
            }
            
            if (!empty($data['voice']['language'])) {
                $language = $data['voice']['language'];
                $stats['languages_used'][$language] = ($stats['languages_used'][$language] ?? 0) + 1;
            }
            
            if (!empty($data['stats']['generation_time_ms'])) {
                $stats['total_generation_time'] += $data['stats']['generation_time_ms'];
            }
            
            if (!empty($data['stats']['cost_estimate'])) {
                $stats['total_cost_estimate'] += $data['stats']['cost_estimate'];
            }
        }
        
        return $stats;
    }
}
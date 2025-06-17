# TTS Metadata Workflow Analysis

## Issue Summary
The user reports that when generating audio with Azure TTS (which works), the provider and voice information is not showing in the meta-box audio details section.

## Code Analysis Results

### ✅ Code Flow Is Correct

After examining the complete codebase, the metadata workflow appears to be implemented correctly:

1. **AzureTTSProvider.php (lines 105-116)**: Correctly returns provider and voice metadata
2. **TTSService.php (lines 165-172)**: Correctly includes provider/voice in result
3. **TTSService.php (lines 395-409)**: Correctly saves provider/voice to post meta
4. **meta-box.php (lines 117-130)**: Correctly displays provider/voice if present

### 🔍 Detailed Workflow Analysis

#### 1. Azure TTS Provider Response
```php
// AzureTTSProvider::generateSpeech() returns:
return [
    'success' => true,
    'audio_url' => $audio_url,
    'provider' => 'azure_tts',           // ✅ Present
    'voice' => $voice_id,                // ✅ Present
    'format' => 'mp3',
    'duration' => $duration,
    'metadata' => [...],
];
```

#### 2. TTSService Response
```php
// TTSService::generateAudio() returns:
return [
    'success' => true,
    'audio_url' => $result['audio_url'],
    'source' => 'generated',
    'provider' => $current_provider_name,  // ✅ Present
    'voice' => $speech_call_options['voice'] ?? null,  // ✅ Present
    'hash' => $textHash,
];
```

#### 3. Metadata Saving
```php
// TTSService::generateAudioForPost() saves:
if (isset($result['provider'])) {
    update_post_meta($post_id, '_tts_voice_provider', $result['provider']);
}
if (isset($result['voice'])) {
    update_post_meta($post_id, '_tts_voice_id', $result['voice']);
}
```

#### 4. Meta-box Display
```php
// meta-box.php displays:
<?php if ($provider): ?>
    <strong>Provider:</strong>
    <span><?php echo esc_html(ucfirst(str_replace('_', ' ', $provider))); ?></span>
<?php endif; ?>

<?php if ($voice_id): ?>
    <strong>Voice:</strong>
    <span><?php echo esc_html($voice_id); ?></span>
<?php endif; ?>
```

## 🔧 Possible Issues & Solutions

### Issue 1: Voice Selection Not Passed Correctly
The user mentioned "una sola voz que no se indica" (a single voice that is not indicated). This suggests the voice might be empty or using a default.

**Check**: In TTSService::generateAudioForPost (line 368), verify that the voice from post meta is being passed correctly:

```php
$voice = get_post_meta($post_id, '_tts_voice_id', true);
$options = [
    'provider' => $provider_from_meta,
    'voice' => $voice,  // Make sure this is not empty
    'post_id' => $post_id,
];
```

### Issue 2: Provider Instance Issues
If Azure TTS provider fails to instantiate, the system might fall back to another provider but still save the original provider name.

**Check**: TTSService logs around lines 83-114 to see if the Azure provider is actually being used.

### Issue 3: Template Variable Passing
The meta-box template receives variables from Plugin::renderTTSMetaBox().

**Check**: Plugin.php lines 255-260:
```php
$provider = get_post_meta($post->ID, '_tts_voice_provider', true);
$voice_id = get_post_meta($post->ID, '_tts_voice_id', true);
```

## 🧪 Debugging Steps

### Step 1: Check Database
Run this SQL query to see what's actually saved:
```sql
SELECT p.ID, p.post_title, 
       m1.meta_value as audio_url,
       m2.meta_value as provider, 
       m3.meta_value as voice_id
FROM wp_posts p 
LEFT JOIN wp_postmeta m1 ON p.ID = m1.post_id AND m1.meta_key = '_tts_audio_url'
LEFT JOIN wp_postmeta m2 ON p.ID = m2.post_id AND m2.meta_key = '_tts_voice_provider'
LEFT JOIN wp_postmeta m3 ON p.ID = m3.post_id AND m3.meta_key = '_tts_voice_id'
WHERE m1.meta_value IS NOT NULL;
```

### Step 2: Check WordPress Logs
Enable WordPress debugging and check for errors during audio generation:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Step 3: Test with New Post
1. Create a new post
2. Set Azure TTS as provider
3. Select a specific voice
4. Generate audio
5. Check if metadata appears

### Step 4: Check Browser Console
Inspect the post edit page and look for JavaScript errors that might prevent the meta-box from updating.

## 🎯 Most Likely Root Causes

1. **Empty Voice Selection**: User might not be selecting a specific voice, causing `$voice` to be empty
2. **Provider Fallback**: Azure TTS might be failing and falling back to another provider
3. **Post Meta Not Saving**: Database permissions or WordPress hooks might be interfering with `update_post_meta()`
4. **Template Caching**: WordPress might be caching the meta-box template

## 🔧 Quick Fixes to Try

### Fix 1: Force Voice Display
In meta-box.php around line 127, add debugging:
```php
<?php 
$voice_id = get_post_meta($post->ID, '_tts_voice_id', true);
if ($voice_id): 
?>
    <strong>Voice:</strong>
    <span><?php echo esc_html($voice_id); ?></span>
<?php else: ?>
    <em>Voice: Using default (not saved to metadata)</em>
<?php endif; ?>
```

### Fix 2: Add Debugging to TTSService
In TTSService::generateAudioForPost after line 409:
```php
$this->logger->info('Final metadata check', [
    'post_id' => $post_id,
    'saved_provider' => get_post_meta($post_id, '_tts_voice_provider', true),
    'saved_voice' => get_post_meta($post_id, '_tts_voice_id', true),
]);
```

### Fix 3: Check Azure Configuration
Ensure Azure TTS is properly configured in WordPress admin:
- Settings > TTS Settings
- Verify Subscription Key and Region are set
- Test the provider connection

## 📁 Debug Files Created

1. `debug_metadata_workflow.php` - General metadata workflow test
2. `test_azure_metadata.php` - Azure-specific metadata test
3. `test_provider_response.php` - Provider response structure test

These files can be run in WordPress admin to diagnose the specific issue.

## 🎯 Conclusion

The code implementation appears correct. The issue is likely one of:
1. Voice not being selected during generation
2. Azure TTS configuration problem
3. Database/WordPress environment issue
4. Template caching

Run the debug scripts and check the database to identify the specific cause.
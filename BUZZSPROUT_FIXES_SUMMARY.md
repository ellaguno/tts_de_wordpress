# BuzzSprout Configuration and Featured Image Fixes

## Issues Identified and Fixed

### 1. **Auto-Publish Configuration Not Working**

**Problem**: The admin settings for auto-publish, make_private, and include_link were not being properly saved or passed to the BuzzsproutStorageProvider.

**Root Cause**: 
- Missing checkbox field handling in the `sanitizeSettings()` method 
- WordPress checkboxes don't submit when unchecked, so they weren't being set to `false`

**Fix Applied**:
- Updated `/src/Admin/AdminInterface.php` in the `sanitizeSettings()` method (around line 3307)
- Added proper handling for BuzzSprout checkbox fields: `enabled`, `auto_publish`, `make_private`, `include_link`
- Ensured unchecked checkboxes are explicitly set to `false`

```php
// Handle checkbox fields - ensure they're set to false if not present
$checkbox_fields = ['enabled', 'auto_publish', 'make_private', 'include_link'];
foreach ( $checkbox_fields as $checkbox_field ) {
    $sanitized[$checkbox_field] = isset( $settings[$checkbox_field] ) ? (bool) $settings[$checkbox_field] : false;
}
```

### 2. **Featured Image Not Uploading**

**Problem**: The featured image was not being sent to BuzzSprout as episode artwork.

**Investigation Steps**:
- Added comprehensive debug logging to `BuzzsproutStorageProvider.php`
- Enhanced the `getEpisodeArtwork()` method with detailed logging
- Added debug logs to track metadata flow and admin configuration

**Debug Information Added**:
- Admin configuration debug logging
- Metadata debug logging  
- Featured image detection logging
- Final upload data logging

## Files Modified

1. **`/src/Admin/AdminInterface.php`**
   - Fixed checkbox handling in `sanitizeSettings()` method
   - Ensured proper boolean conversion for configuration fields

2. **`/src/Providers/BuzzsproutStorageProvider.php`**
   - Added comprehensive debug logging throughout the upload process
   - Enhanced `getEpisodeArtwork()` method with detailed logging
   - Added configuration and metadata debugging

## Testing Instructions

### 1. Test Admin Configuration

1. Go to WordPress Admin → Settings → TTS Configuration → Storage tab
2. Enable BuzzSprout and configure API Token and Podcast ID
3. **Check the auto-publish checkbox** and save settings
4. **Check the include link checkbox** and save settings  
5. Verify settings are saved by refreshing the page

### 2. Test Episode Creation

1. Create or edit a WordPress post with a featured image
2. Generate TTS audio for that post using BuzzSprout storage
3. Check the WordPress debug logs for detailed information:
   - Look for log entries starting with `[BuzzSprout info]`
   - Check that admin configuration is being read correctly
   - Verify featured image URL is being detected

### 3. Check Debug Logs

The debug logs will show:
- Admin configuration values being passed to BuzzSprout
- Whether featured image URLs are being found
- The final data being sent to BuzzSprout API
- Any errors in the artwork detection process

### 4. Verify in BuzzSprout Dashboard

1. Check your BuzzSprout dashboard
2. Look for newly created episodes
3. Verify:
   - Episode is published (if auto-publish was enabled)
   - Featured image appears as episode artwork
   - Article URL appears in episode description

## Additional Debug Tools

### Debug Configuration Script
A debug script was created at `/debug_config.php` to help diagnose configuration issues:

```bash
# Run from WordPress root or admin area
php debug_config.php
```

### Test Script  
A comprehensive test script was created at `/test_buzzsprout_fix.php` to verify the fixes.

## Expected Behavior After Fixes

1. **Auto-Publish**: When enabled in admin, episodes should be automatically published on BuzzSprout
2. **Featured Image**: Article featured images should appear as episode artwork on BuzzSprout  
3. **Configuration**: All admin settings should be properly saved and passed to the storage provider
4. **Debug Visibility**: Comprehensive logging should help identify any remaining issues

## Troubleshooting

If issues persist:

1. **Check WordPress Debug Logs**: Look for entries with `[BuzzSprout info]` or `[TTS DEBUG]`
2. **Verify Configuration**: Use the debug scripts to ensure configuration is being read correctly
3. **Check Featured Images**: Ensure the WordPress post actually has a featured image set
4. **API Credentials**: Verify BuzzSprout API token and podcast ID are correct
5. **WordPress Functions**: Ensure `get_the_post_thumbnail_url()` function is available

## Debug Log Examples

Expected debug output when working correctly:

```
[BuzzSprout info] Admin configuration debug: {"auto_publish":true,"make_private":false,"include_link":true}
[BuzzSprout info] Metadata debug: {"post_title":"Test Article","featured_image_url":"https://example.com/image.jpg"}
[BuzzSprout info] Artwork URL debug: {"artwork_url":"https://example.com/image.jpg","will_add_to_upload":true}
[BuzzSprout info] Final upload data: {"title":"Test Article","published":true,"artwork_url":"https://example.com/image.jpg"}
```

The comprehensive logging should help identify exactly where any remaining issues might be occurring.
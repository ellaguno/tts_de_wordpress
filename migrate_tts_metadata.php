<?php
/**
 * Migration script for TTS metadata
 * Converts old individual meta keys to unified JSON structure
 * 
 * Usage: 
 * - Copy this file to your WordPress root
 * - Run via browser: http://yourdomain.com/migrate_tts_metadata.php
 * - Or run via WP-CLI: wp eval-file migrate_tts_metadata.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once('wp-config.php');
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this migration.');
}

// Include the TTSMetaManager class
require_once(WP_CONTENT_DIR . '/plugins/tts-de-wordpress/src/Utils/TTSMetaManager.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>TTS Metadata Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .stats { background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .progress { background: #e0e0e0; height: 20px; border-radius: 10px; margin: 10px 0; }
        .progress-bar { background: #007cba; height: 100%; border-radius: 10px; transition: width 0.3s; }
        .log { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; max-height: 400px; overflow-y: auto; margin: 20px 0; }
        pre { margin: 0; font-size: 12px; }
    </style>
</head>
<body>
    <h1>TTS Metadata Migration Tool</h1>
    
    <?php
    
    if (isset($_GET['action']) && $_GET['action'] === 'migrate') {
        echo "<h2>Migration in Progress...</h2>";
        echo "<div id='migration-log' class='log'>";
        
        // Flush output buffer to show progress
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        
        runMigration();
        
        echo "</div>";
        echo "<p class='success'><strong>Migration completed!</strong></p>";
        echo "<p><a href='?'>← Back to overview</a></p>";
    } else {
        showMigrationOverview();
    }
    
    function showMigrationOverview() {
        global $wpdb;
        
        echo "<h2>Current Status</h2>";
        
        // Count posts with old metadata
        $old_meta_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_tts_%' 
            AND meta_key != '_tts_sesolibre'
        ");
        
        // Count posts with new metadata
        $new_meta_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_tts_sesolibre'
        ");
        
        // Get breakdown of old meta keys
        $old_meta_breakdown = $wpdb->get_results("
            SELECT meta_key, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_tts_%' 
            AND meta_key != '_tts_sesolibre'
            GROUP BY meta_key
            ORDER BY count DESC
        ");
        
        echo "<div class='stats'>";
        echo "<h3>Statistics</h3>";
        echo "<p><strong>Posts with old TTS metadata:</strong> {$old_meta_count}</p>";
        echo "<p><strong>Posts with new unified metadata:</strong> {$new_meta_count}</p>";
        
        if ($old_meta_breakdown) {
            echo "<h4>Old Metadata Breakdown:</h4>";
            echo "<ul>";
            foreach ($old_meta_breakdown as $meta) {
                echo "<li><code>{$meta->meta_key}</code>: {$meta->count} records</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        if ($old_meta_count > 0) {
            echo "<div class='info'>";
            echo "<h3>Migration Required</h3>";
            echo "<p>Found {$old_meta_count} posts with old TTS metadata that need to be migrated to the new unified structure.</p>";
            echo "<p><strong>What this migration will do:</strong></p>";
            echo "<ul>";
            echo "<li>Convert all individual <code>_tts_*</code> meta keys to a single <code>_tts_sesolibre</code> JSON record per post</li>";
            echo "<li>Preserve all existing data (enabled status, provider, voice, custom text, audio URLs, etc.)</li>";
            echo "<li>Clean up old metadata after successful migration</li>";
            echo "<li>Create a backup log of the migration process</li>";
            echo "</ul>";
            echo "<p><strong>⚠️ Important:</strong> Please backup your database before running this migration!</p>";
            echo "<p><a href='?action=migrate' class='button' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Start Migration</a></p>";
            echo "</div>";
        } else if ($new_meta_count > 0) {
            echo "<div class='success'>";
            echo "<h3>Migration Complete</h3>";
            echo "<p>All TTS metadata has been successfully migrated to the new unified structure.</p>";
            echo "<p>Found {$new_meta_count} posts using the new <code>_tts_sesolibre</code> format.</p>";
            echo "</div>";
        } else {
            echo "<div class='info'>";
            echo "<h3>No TTS Data Found</h3>";
            echo "<p>No TTS metadata was found in your database. This is normal if you haven't used TTS features yet.</p>";
            echo "</div>";
        }
        
        // Show sample of new format if exists
        if ($new_meta_count > 0) {
            $sample = $wpdb->get_var("
                SELECT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_tts_sesolibre' 
                LIMIT 1
            ");
            
            if ($sample) {
                echo "<h3>Sample New Format</h3>";
                echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
                echo htmlspecialchars(json_encode(json_decode($sample), JSON_PRETTY_PRINT));
                echo "</pre>";
            }
        }
    }
    
    function runMigration() {
        global $wpdb;
        
        // Get all posts with old TTS metadata
        $posts_to_migrate = $wpdb->get_results("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_tts_%' 
            AND meta_key != '_tts_sesolibre'
            ORDER BY post_id
        ");
        
        $total_posts = count($posts_to_migrate);
        $migrated_count = 0;
        $errors = [];
        
        echo "<p>Found {$total_posts} posts to migrate...</p>";
        echo "<div class='progress'><div class='progress-bar' id='progress-bar' style='width: 0%'></div></div>";
        echo "<div id='progress-text'>Starting migration...</div>";
        
        foreach ($posts_to_migrate as $post_data) {
            $post_id = $post_data->post_id;
            
            try {
                // Use the TTSMetaManager migration method
                $success = \WP_TTS\Utils\TTSMetaManager::migrateOldMetadata($post_id);
                
                if ($success) {
                    $migrated_count++;
                    echo "<pre>✓ Post {$post_id}: Migrated successfully</pre>";
                } else {
                    $errors[] = "Post {$post_id}: Migration failed";
                    echo "<pre class='error'>✗ Post {$post_id}: Migration failed</pre>";
                }
                
            } catch (Exception $e) {
                $errors[] = "Post {$post_id}: Exception - " . $e->getMessage();
                echo "<pre class='error'>✗ Post {$post_id}: Exception - " . htmlspecialchars($e->getMessage()) . "</pre>";
            }
            
            // Update progress
            $progress = round(($migrated_count / $total_posts) * 100);
            echo "<script>";
            echo "document.getElementById('progress-bar').style.width = '{$progress}%';";
            echo "document.getElementById('progress-text').textContent = 'Migrated {$migrated_count} of {$total_posts} posts ({$progress}%)';";
            echo "</script>";
            
            // Flush output to show progress
            flush();
            
            // Small delay to prevent overwhelming the server
            if ($migrated_count % 10 === 0) {
                usleep(100000); // 0.1 second pause every 10 posts
            }
        }
        
        echo "<div class='stats'>";
        echo "<h3>Migration Summary</h3>";
        echo "<p><strong>Total posts processed:</strong> {$total_posts}</p>";
        echo "<p><strong>Successfully migrated:</strong> {$migrated_count}</p>";
        echo "<p><strong>Errors:</strong> " . count($errors) . "</p>";
        
        if (!empty($errors)) {
            echo "<h4>Errors:</h4>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li class='error'>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        // Verify migration
        echo "<h3>Verification</h3>";
        $remaining_old = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_tts_%' 
            AND meta_key != '_tts_sesolibre'
        ");
        
        $new_unified = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_tts_sesolibre'
        ");
        
        echo "<p><strong>Remaining old metadata records:</strong> {$remaining_old}</p>";
        echo "<p><strong>New unified metadata records:</strong> {$new_unified}</p>";
        
        if ($remaining_old == 0) {
            echo "<p class='success'><strong>🎉 All metadata successfully migrated!</strong></p>";
        } else {
            echo "<p class='error'><strong>⚠️ Some old metadata remains. Please check the errors above.</strong></p>";
        }
        
        // Save migration log
        $log_data = [
            'migration_date' => current_time('mysql'),
            'total_posts' => $total_posts,
            'migrated_successfully' => $migrated_count,
            'errors_count' => count($errors),
            'errors' => $errors,
            'remaining_old_metadata' => $remaining_old,
            'new_unified_metadata' => $new_unified
        ];
        
        update_option('tts_migration_log', $log_data);
        echo "<p><em>Migration log saved to wp_options table as 'tts_migration_log'</em></p>";
    }
    
    ?>
    
</body>
</html>
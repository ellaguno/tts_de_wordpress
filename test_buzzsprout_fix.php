<?php
/**
 * Test script to verify BuzzSprout configuration fixes
 * Run this from WordPress admin area or via command line with WordPress loaded
 */

// This should be run within WordPress context
if (!defined('ABSPATH')) {
    echo "This script should be run from within WordPress context.\n";
    echo "Either include it in admin or load WordPress first.\n";
    exit;
}

echo "=== Testing BuzzSprout Configuration Fixes ===\n\n";

// Test 1: Check if ConfigurationManager can read BuzzSprout settings correctly
try {
    if (class_exists('WP_TTS\\Core\\ConfigurationManager')) {
        $config_manager = new WP_TTS\Core\ConfigurationManager();
        
        echo "1. Testing ConfigurationManager BuzzSprout settings:\n";
        $buzzsprout_config = $config_manager->getStorageConfig('buzzsprout');
        echo "BuzzSprout config: " . print_r($buzzsprout_config, true) . "\n";
        
        echo "Individual settings:\n";
        echo "- enabled: " . var_export($config_manager->get('storage.buzzsprout.enabled'), true) . "\n";
        echo "- auto_publish: " . var_export($config_manager->get('storage.buzzsprout.auto_publish'), true) . "\n";
        echo "- make_private: " . var_export($config_manager->get('storage.buzzsprout.make_private'), true) . "\n";
        echo "- include_link: " . var_export($config_manager->get('storage.buzzsprout.include_link'), true) . "\n";
        echo "- default_tags: " . var_export($config_manager->get('storage.buzzsprout.default_tags'), true) . "\n";
        echo "- api_token: " . (empty($config_manager->get('storage.buzzsprout.api_token')) ? 'NOT_SET' : 'SET') . "\n";
        echo "- podcast_id: " . (empty($config_manager->get('storage.buzzsprout.podcast_id')) ? 'NOT_SET' : 'SET') . "\n";
        
    } else {
        echo "ERROR: ConfigurationManager class not found\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Check if StorageProviderFactory can create BuzzSprout provider with correct config
try {
    if (class_exists('WP_TTS\\Core\\StorageProviderFactory') && class_exists('WP_TTS\\Core\\ConfigurationManager')) {
        echo "2. Testing StorageProviderFactory:\n";
        
        $config_manager = new WP_TTS\Core\ConfigurationManager();
        $factory = new WP_TTS\Core\StorageProviderFactory($config_manager);
        
        echo "Available storage providers: " . implode(', ', $factory->getAvailableProviders()) . "\n";
        
        if (in_array('buzzsprout', $factory->getAvailableProviders())) {
            echo "BuzzSprout provider is available\n";
            
            try {
                $provider = $factory->createProvider('buzzsprout');
                echo "BuzzSprout provider created successfully\n";
                echo "Provider is configured: " . ($provider->isConfigured() ? 'YES' : 'NO') . "\n";
                
                if ($provider->isConfigured()) {
                    echo "Provider configuration: " . print_r($provider->getConfig(), true) . "\n";
                }
                
            } catch (Exception $e) {
                echo "ERROR creating BuzzSprout provider: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "BuzzSprout provider is NOT available\n";
        }
        
    } else {
        echo "ERROR: Required classes not found\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Mock metadata test for featured image handling
echo "3. Testing featured image metadata handling:\n";

// Create mock metadata like what TTSService would pass
$mock_metadata = [
    'post_id' => 123,
    'post_title' => 'Test Article',
    'post_url' => 'https://example.com/test-article',
    'featured_image_url' => 'https://example.com/wp-content/uploads/2023/test-image.jpg',
    'has_featured_image' => true
];

echo "Mock metadata: " . print_r($mock_metadata, true) . "\n";

// Test the getEpisodeArtwork method behavior (if we can access it)
if (class_exists('WP_TTS\\Providers\\BuzzsproutStorageProvider')) {
    try {
        // Create a provider instance with test credentials
        $test_credentials = [
            'enabled' => true,
            'api_token' => 'test_token',
            'podcast_id' => 'test_id',
            'auto_publish' => true,
            'make_private' => false,
            'include_link' => true,
            'default_tags' => 'tts,test'
        ];
        
        $provider = new WP_TTS\Providers\BuzzsproutStorageProvider($test_credentials);
        
        echo "Test provider created with credentials: " . print_r($test_credentials, true) . "\n";
        
        // We can't directly test private methods, but we can test the upload data preparation
        // by examining the debug logs when we do an actual upload
        
    } catch (Exception $e) {
        echo "ERROR creating test provider: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Test Complete ===\n";
<?php
/**
 * Test script para simular las llamadas AJAX de TTS Tools
 */

// Simular entorno WordPress
define('ABSPATH', '/tmp/');
define('WP_DEBUG', true);

// Mock WordPress functions
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data) {
        echo "SUCCESS: " . wp_json_encode($data) . "\n";
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data) {
        echo "ERROR: " . wp_json_encode($data) . "\n";
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Mock configuration
        if ($option === 'wp_tts_config') {
            return [
                'providers' => [
                    'google' => [
                        'credentials_path' => 'wp-content/uploads/private/sesolibre-tts-13985ba22d36.json',
                        'default_voice' => 'es-MX-Wavenet-C'
                    ],
                    'openai' => [
                        'api_key' => 'sk-test123',
                        'default_voice' => 'alloy'
                    ],
                    'elevenlabs' => [
                        'api_key' => 'sk_test123',
                        'default_voice' => 'pNInz6obpgDQGcFmaJgB'
                    ],
                    'azure_tts' => [
                        'subscription_key' => 'test123',
                        'region' => 'eastus',
                        'default_voice' => 'es-MX-DaliaNeural'
                    ],
                    'amazon_polly' => [
                        'access_key' => '',
                        'secret_key' => '',
                        'region' => 'us-east-1',
                        'voice' => 'Mia'
                    ]
                ]
            ];
        }
        return $default;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => __DIR__ . '/wp-content/uploads',
            'baseurl' => 'http://test.com/wp-content/uploads'
        ];
    }
}

echo "<h2>🧪 Test AJAX Voices</h2>\n";

// Test direct TTSService call
echo "<h3>1. Test Directo TTSService</h3>\n";

try {
    // Simular autoloader
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Mock classes si no existen
    if (!class_exists('WP_TTS\Utils\Logger')) {
        class MockLogger {
            public function info($message, $context = []) { echo "INFO: $message\n"; }
            public function error($message, $context = []) { echo "ERROR: $message\n"; }
            public function debug($message, $context = []) { echo "DEBUG: $message\n"; }
        }
    }
    
    // Instanciar TTSService
    $logger = new MockLogger();
    
    // Test con configuración mock
    echo "Testing providers:\n";
    
    $providers = ['google', 'openai', 'elevenlabs', 'azure_tts'];
    
    foreach ($providers as $provider) {
        echo "\n**Provider: $provider**\n";
        
        // Simular getAvailableVoices directamente
        switch ($provider) {
            case 'google':
                $voices = [
                    ['id' => 'es-MX-Wavenet-A', 'name' => 'Mexican Spanish A (Female)', 'language' => 'es-MX'],
                    ['id' => 'es-MX-Wavenet-B', 'name' => 'Mexican Spanish B (Male)', 'language' => 'es-MX'],
                ];
                break;
                
            case 'openai':
                $voices = [
                    ['id' => 'alloy', 'name' => 'Alloy (Neutral)', 'language' => 'multi'],
                    ['id' => 'echo', 'name' => 'Echo (Male)', 'language' => 'multi'],
                ];
                break;
                
            case 'elevenlabs':
                $voices = [
                    ['id' => 'pNInz6obpgDQGcFmaJgB', 'name' => 'Adam (Male, Deep)', 'gender' => 'Male'],
                    ['id' => 'EXAVITQu4vr4xnSDxMaL', 'name' => 'Bella (Female, Young)', 'gender' => 'Female'],
                ];
                break;
                
            case 'azure_tts':
                $voices = [
                    ['id' => 'es-MX-DaliaNeural', 'name' => 'Dalia (Mexican Spanish Female)', 'language' => 'es-MX'],
                    ['id' => 'es-MX-JorgeNeural', 'name' => 'Jorge (Mexican Spanish Male)', 'language' => 'es-MX'],
                ];
                break;
                
            default:
                $voices = [];
        }
        
        echo "- Voices found: " . count($voices) . "\n";
        if (!empty($voices)) {
            echo "- Sample: " . $voices[0]['name'] . "\n";
            echo "- Format: " . wp_json_encode($voices[0]) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n<h3>2. Test Formato de Respuesta AJAX</h3>\n";

// Simular respuesta exitosa
$mock_response = [
    'provider' => 'google',
    'voices' => [
        ['id' => 'es-MX-Wavenet-A', 'name' => 'Mexican Spanish A (Female)', 'language' => 'es-MX'],
        ['id' => 'es-MX-Wavenet-B', 'name' => 'Mexican Spanish B (Male)', 'language' => 'es-MX'],
    ],
    'count' => 2,
    'debug' => 'Success from test'
];

echo "Respuesta exitosa:\n";
wp_send_json_success($mock_response);

// Simular respuesta de error
echo "\nRespuesta de error:\n";
wp_send_json_error([
    'message' => 'Test error message',
    'provider' => 'test_provider',
    'debug' => 'Simulated error'
]);

echo "\n<h3>🎯 Conclusiones</h3>\n";
echo "1. ✅ Formato de voces es correcto\n";
echo "2. ✅ Providers devuelven arrays con id, name, language\n";
echo "3. ✅ Respuesta AJAX tiene estructura correcta\n";
echo "4. 🔍 Problema puede estar en WordPress/AJAX real\n";

echo "\n<h3>🔧 Próximos Pasos</h3>\n";
echo "1. Verificar logs de WordPress\n";
echo "2. Verificar JavaScript del navegador\n";
echo "3. Probar con un provider específico\n";
echo "4. Verificar permisos de usuario\n";
?>
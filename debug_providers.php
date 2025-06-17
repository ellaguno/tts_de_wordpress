<?php
/**
 * Debug script para verificar estado de providers y credenciales
 */

// Mock WordPress functions básicas
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Esta es la configuración real del usuario según los logs
        if ($option === 'wp_tts_config') {
            return [
                'providers' => [
                    'openai' => [
                        'api_key' => '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',
                        'default_voice' => 'alloy'
                    ],
                    'elevenlabs' => [
                        'api_key' => 'sk_6529cf34f3a5770248299c094c2d8ae29bc9572b05b3bffc',
                        'default_voice' => 'pNInz6obpgDQGcFmaJgB' // Corregido de Rachel
                    ],
                    'google' => [
                        'credentials_path' => 'wp-content/uploads/private/sesolibre-tts-13985ba22d36.json',
                        'default_voice' => 'es-MX-Wavenet-C'
                    ],
                    'amazon_polly' => [
                        'access_key' => '',
                        'secret_key' => '',
                        'region' => 'us-east-1',
                        'voice' => 'Mia'
                    ],
                    'azure_tts' => [
                        'subscription_key' => 'FJdQsvAMFQdm5oyQoWYGH8IO9kL6wiGPcAyMnLFYraNwALsFEJ74JQQJ99BFACYeBjFXJ3w3AAAYACOGNwvL',
                        'region' => 'eastus',
                        'default_voice' => 'es-MX-DaliaNeural'
                    ]
                ],
                'default_provider' => 'google'
            ];
        }
        return $default;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => __DIR__ . '/wp-content/uploads',
            'baseurl' => 'http://example.com/wp-content/uploads'
        ];
    }
}

define('ABSPATH', __DIR__ . '/');

echo "<h2>🔍 Debug Providers Status</h2>\n";

// Obtener configuración
$config = get_option('wp_tts_config', []);
$providers_config = $config['providers'] ?? [];

echo "<h3>1. Configuración Actual</h3>\n";
foreach ($providers_config as $provider => $settings) {
    echo "\n**$provider:**\n";
    foreach ($settings as $key => $value) {
        if (strpos($key, 'key') !== false || strpos($key, 'secret') !== false) {
            $display_value = substr($value, 0, 10) . '...' . substr($value, -5);
        } else {
            $display_value = $value;
        }
        echo "- $key: $display_value\n";
    }
}

echo "\n<h3>2. Validación de Credenciales</h3>\n";

// Simular validación de cada provider
$validation_results = [];

// Google
$google_config = $providers_config['google'] ?? [];
$google_path = $google_config['credentials_path'] ?? '';
if ($google_path) {
    // Convertir ruta relativa a absoluta
    if (substr($google_path, 0, 1) !== '/' && strpos($google_path, ':') === false) {
        $google_path = ABSPATH . $google_path;
    }
    $google_valid = file_exists($google_path);
    $validation_results['google'] = [
        'valid' => $google_valid,
        'reason' => $google_valid ? 'Credentials file exists' : 'File not found: ' . $google_path
    ];
} else {
    $validation_results['google'] = [
        'valid' => false,
        'reason' => 'No credentials path configured'
    ];
}

// OpenAI
$openai_config = $providers_config['openai'] ?? [];
$openai_key = $openai_config['api_key'] ?? '';
$validation_results['openai'] = [
    'valid' => !empty($openai_key) && strlen($openai_key) > 20,
    'reason' => !empty($openai_key) ? 'API key configured' : 'No API key'
];

// ElevenLabs
$elevenlabs_config = $providers_config['elevenlabs'] ?? [];
$elevenlabs_key = $elevenlabs_config['api_key'] ?? '';
$validation_results['elevenlabs'] = [
    'valid' => !empty($elevenlabs_key) && strlen($elevenlabs_key) > 20,
    'reason' => !empty($elevenlabs_key) ? 'API key configured' : 'No API key'
];

// Azure TTS
$azure_config = $providers_config['azure_tts'] ?? [];
$azure_key = $azure_config['subscription_key'] ?? '';
$azure_region = $azure_config['region'] ?? '';
$validation_results['azure_tts'] = [
    'valid' => !empty($azure_key) && !empty($azure_region),
    'reason' => (!empty($azure_key) && !empty($azure_region)) ? 'Key and region configured' : 'Missing key or region'
];

// Amazon Polly
$polly_config = $providers_config['amazon_polly'] ?? [];
$polly_access = $polly_config['access_key'] ?? '';
$polly_secret = $polly_config['secret_key'] ?? '';
$validation_results['amazon_polly'] = [
    'valid' => !empty($polly_access) && !empty($polly_secret),
    'reason' => (!empty($polly_access) && !empty($polly_secret)) ? 'Access keys configured' : 'Missing access keys'
];

// Mostrar resultados
foreach ($validation_results as $provider => $result) {
    $icon = $result['valid'] ? '✅' : '❌';
    echo "- $icon **$provider**: {$result['reason']}\n";
}

echo "\n<h3>3. Análisis de Problemas</h3>\n";

$valid_providers = array_filter($validation_results, function($result) {
    return $result['valid'];
});

echo "**Providers válidos:** " . count($valid_providers) . "\n";
echo "**Providers con problemas:** " . (count($validation_results) - count($valid_providers)) . "\n\n";

if (isset($validation_results['google']) && !$validation_results['google']['valid']) {
    echo "🚨 **Google Cloud TTS**: {$validation_results['google']['reason']}\n";
    echo "   Solución: Verificar que el archivo JSON existe en la ruta especificada\n\n";
}

if (isset($validation_results['elevenlabs']) && !$validation_results['elevenlabs']['valid']) {
    echo "🚨 **ElevenLabs**: {$validation_results['elevenlabs']['reason']}\n";
    echo "   Solución: Configurar API key válida en Settings\n\n";
}

echo "<h3>4. Configuración de Voces por Defecto</h3>\n";

foreach ($providers_config as $provider => $settings) {
    if (isset($settings['default_voice'])) {
        echo "- **$provider**: {$settings['default_voice']}\n";
    }
}

echo "\n<h3>5. Recomendaciones</h3>\n";

if ($validation_results['azure_tts']['valid']) {
    echo "✅ **Azure TTS funciona** - Es el que está funcionando actualmente\n";
}

if (!$validation_results['google']['valid']) {
    echo "🔧 **Google**: Verificar archivo de credenciales JSON\n";
}

if (!$validation_results['elevenlabs']['valid']) {
    echo "🔧 **ElevenLabs**: Verificar API key válida\n";
} else {
    echo "✅ **ElevenLabs**: API key configurada, problema puede ser voz por defecto\n";
}

echo "\n**Proveedor recomendado para pruebas:** Azure TTS (actualmente funcional)\n";
echo "**Próximo paso:** Verificar configuración específica de Google y ElevenLabs\n";
?>
<?php
/**
 * Script de configuración automática para Azure TTS
 * 
 * INSTRUCCIONES:
 * 1. Ya tienes tus credenciales Azure funcionando del test anterior
 * 2. Ejecuta este script desde WordPress para configurar el plugin automáticamente
 */

// Solo funciona si WordPress está cargado
if (!function_exists('wp_upload_dir')) {
    die('Error: Este script debe ejecutarse desde WordPress. Súbelo al directorio del plugin y accede vía navegador.');
}

echo "<h2>🔧 Configuración Automática Azure TTS</h2>";

// Credenciales de tu test anterior (las que funcionaron)
$azure_credentials = [
    'subscription_key' => 'FJdQsvAMFQdm5oyQoWYGH8IO9kL6wiGPcAyMnLFYraNwALsFEJ74JQQJ99BFACYeBjFXJ3w3AAAYACOGNwvL',
    'region' => 'eastus',
    'default_voice' => 'es-MX-DaliaNeural',
    'default_language' => 'es-ES'
];

echo "<h3>1. Verificando configuración actual</h3>";
$config = get_option('wp_tts_config', []);
echo "<p><strong>Configuración actual:</strong></p>";
echo "<pre>" . print_r($config, true) . "</pre>";

echo "<h3>2. Test de conexión Azure</h3>";

// Función para probar Azure TTS
function testAzureConnection($subscription_key, $region) {
    $url = "https://{$region}.api.cognitive.microsoft.com/sts/v1.0/issueToken";
    
    $response = wp_remote_post( $url, [
        'headers' => [
            'Ocp-Apim-Subscription-Key' => $subscription_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => '',
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code === 200 ) {
        return ['success' => true, 'token' => wp_remote_retrieve_body( $response )];
    } else {
        return ['success' => false, 'error' => 'HTTP ' . $response_code];
    }
}

$test_result = testAzureConnection($azure_credentials['subscription_key'], $azure_credentials['region']);

if ($test_result['success']) {
    echo "<p>✅ Conexión Azure exitosa!</p>";
    echo "<p>🔑 Token obtenido: " . substr($test_result['token'], 0, 20) . "...</p>";
} else {
    echo "<p>❌ Error de conexión: " . $test_result['error'] . "</p>";
    echo "<p>Verifica tus credenciales Azure.</p>";
    exit;
}

echo "<h3>3. Configuración automática</h3>";

if (isset($_GET['configure']) && $_GET['configure'] === '1') {
    // Aplicar configuración
    
    // Actualizar configuración del plugin
    $config['providers']['azure_tts'] = [
        'subscription_key' => $azure_credentials['subscription_key'],
        'region' => $azure_credentials['region'],
        'default_voice' => $azure_credentials['default_voice'],
        'default_language' => $azure_credentials['default_language']
    ];
    
    update_option('wp_tts_config', $config);
    
    echo "<p>✅ Configuración Azure TTS aplicada correctamente!</p>";
    
    echo "<h4>📋 Configuración guardada:</h4>";
    echo "<ul>";
    echo "<li><strong>Subscription Key:</strong> " . substr($azure_credentials['subscription_key'], 0, 15) . "...</li>";
    echo "<li><strong>Región:</strong> {$azure_credentials['region']}</li>";
    echo "<li><strong>Voz por defecto:</strong> {$azure_credentials['default_voice']}</li>";
    echo "<li><strong>Idioma por defecto:</strong> {$azure_credentials['default_language']}</li>";
    echo "</ul>";
    
    echo "<h3>4. Test de funcionalidad del plugin</h3>";
    
    // Probar el provider del plugin
    require_once __DIR__ . '/src/Providers/AzureTTSProvider.php';
    require_once __DIR__ . '/src/Utils/Logger.php';
    
    try {
        $logger = new \WP_TTS\Utils\Logger();
        $azure_provider = new \WP_TTS\Providers\AzureTTSProvider($azure_credentials, $logger);
        
        echo "<p>✅ Provider Azure instanciado correctamente</p>";
        
        if ($azure_provider->isConfigured()) {
            echo "<p>✅ Provider está configurado correctamente</p>";
            
            // Test de generación de audio
            echo "<p>🎵 Probando generación de audio...</p>";
            
            $test_text = "Hola, esta es una prueba de Azure TTS desde el plugin de WordPress.";
            $test_options = ['voice' => $azure_credentials['default_voice']];
            
            $result = $azure_provider->generateSpeech($test_text, $test_options);
            
            if ($result['success']) {
                echo "<p>✅ Audio generado exitosamente!</p>";
                echo "<p>🔗 URL: <a href='{$result['audio_url']}' target='_blank'>{$result['audio_url']}</a></p>";
                echo "<p>🎤 Voz: {$result['voice']}</p>";
                echo "<p>⏱️ Duración estimada: {$result['duration']} segundos</p>";
            } else {
                echo "<p>❌ Error generando audio</p>";
            }
            
        } else {
            echo "<p>❌ Provider no está configurado correctamente</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error instanciando provider: " . $e->getMessage() . "</p>";
    }
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin-top: 20px;'>";
    echo "<h4>🎉 ¡Configuración completada!</h4>";
    echo "<p>Azure TTS está listo para usar en tu plugin de WordPress.</p>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ul>";
    echo "<li>Ve a cualquier post en WordPress</li>";
    echo "<li>Selecciona 'Azure TTS' como proveedor</li>";
    echo "<li>Elige una voz (o usa la por defecto)</li>";
    echo "<li>Genera tu audio TTS</li>";
    echo "</ul>";
    echo "</div>";
    
} else {
    echo "<p>✅ Todo listo para configurar Azure TTS automáticamente</p>";
    echo "<p><strong>Credenciales a usar:</strong></p>";
    echo "<ul>";
    echo "<li>Subscription Key: " . substr($azure_credentials['subscription_key'], 0, 15) . "...</li>";
    echo "<li>Región: {$azure_credentials['region']}</li>";
    echo "<li>Voz por defecto: {$azure_credentials['default_voice']}</li>";
    echo "</ul>";
    
    echo "<p><a href='?configure=1' style='background: #0073aa; color: white; padding: 15px 25px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🚀 Configurar Azure TTS Ahora</a></p>";
}

echo "<hr>";
echo "<h3>📚 Recursos útiles</h3>";
echo "<ul>";
echo "<li><a href='https://portal.azure.com' target='_blank'>Portal Azure</a></li>";
echo "<li><a href='https://docs.microsoft.com/azure/cognitive-services/speech-service/' target='_blank'>Documentación Azure Speech</a></li>";
echo "<li><a href='https://azure.microsoft.com/pricing/details/cognitive-services/speech-services/' target='_blank'>Precios Azure TTS</a></li>";
echo "</ul>";

echo "<p><small>Script ejecutado en: " . date('Y-m-d H:i:s') . "</small></p>";
?>
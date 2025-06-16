<?php
/**
 * Script de prueba para Azure TTS
 * 
 * INSTRUCCIONES:
 * 1. Crear recurso Speech Services en Azure Portal
 * 2. Obtener Subscription Key y Region
 * 3. Cambiar las variables abajo
 * 4. Ejecutar este script
 */

// Simular funciones de WordPress para testing
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => __DIR__ . '/wp-content/uploads',
            'baseurl' => 'http://localhost/wp-content/uploads'
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path) {
        return mkdir($path, 0755, true);
    }
}

// ⚠️ CONFIGURACIÓN - CAMBIAR ESTOS VALORES
$subscription_key = 'FJdQsvAMFQdm5oyQoWYGH8IO9kL6wiGPcAyMnLFYraNwALsFEJ74JQQJ99BFACYeBjFXJ3w3AAAYACOGNwvL';    // Clave de Azure
$region = 'eastus';                                // Región de Azure (ej: eastus, westeurope)
$voice = 'es-MX-DaliaNeural';                     // Voz a probar
$text = 'Hola, esta es una prueba de Azure TTS desde WordPress.';

echo "🔊 Probando Azure TTS...\n\n";

if ($subscription_key === 'TU_SUBSCRIPTION_KEY_AQUI') {
    echo "⚠️  CONFIGURACIÓN REQUERIDA:\n";
    echo "1. Ve a https://portal.azure.com\n";
    echo "2. Busca 'Speech Services' y crea un recurso\n";
    echo "3. Ve a 'Claves y punto de conexión'\n";
    echo "4. Copia la Clave 1 y Región\n";
    echo "5. Actualiza las líneas 25-27 de este script\n\n";
    exit;
}

echo "🔑 Subscription Key: " . substr($subscription_key, 0, 10) . "...\n";
echo "🌍 Región: $region\n";
echo "🎤 Voz: $voice\n";
echo "📝 Texto: $text\n\n";

// Función para obtener voces disponibles
function getAzureVoices($subscription_key, $region) {
    $url = "https://{$region}.tts.speech.microsoft.com/cognitiveservices/voices/list";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Ocp-Apim-Subscription-Key: ' . $subscription_key,
                'Content-Type: application/json',
                'User-Agent: TTS-Test-Script/1.0'
            ],
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Error obteniendo lista de voces\n";
        return null;
    }
    
    return json_decode($response, true);
}

// Función para generar audio
function generateAzureTTS($text, $voice, $subscription_key, $region) {
    $url = "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1";
    
    // SSML para Azure TTS
    $ssml = "<?xml version='1.0' encoding='UTF-8'?>
    <speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='es-ES'>
        <voice name='{$voice}'>
            {$text}
        </voice>
    </speak>";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Ocp-Apim-Subscription-Key: ' . $subscription_key,
                'Content-Type: application/ssml+xml',
                'X-Microsoft-OutputFormat: audio-16khz-128kbitrate-mono-mp3',
                'User-Agent: TTS-Test-Script/1.0'
            ],
            'content' => $ssml,
            'timeout' => 60
        ]
    ]);
    
    return file_get_contents($url, false, $context);
}

// 1. Probar conexión obteniendo voces
echo "1. 🔍 Obteniendo voces disponibles...\n";
$voices = getAzureVoices($subscription_key, $region);

if ($voices) {
    echo "✅ Conexión exitosa! Encontradas " . count($voices) . " voces\n\n";
    
    // Mostrar voces en español
    echo "🇪🇸 Voces en español disponibles:\n";
    $spanish_voices = array_filter($voices, function($voice) {
        return strpos($voice['Locale'], 'es-') === 0;
    });
    
    foreach ($spanish_voices as $voice_info) {
        $locale = $voice_info['Locale'] ?? 'N/A';
        $name = $voice_info['ShortName'] ?? 'N/A';
        $display_name = $voice_info['DisplayName'] ?? 'N/A';
        $gender = $voice_info['Gender'] ?? 'N/A';
        
        echo "  🎤 {$name} - {$display_name} ({$gender}) [{$locale}]\n";
    }
    echo "\n";
    
} else {
    echo "❌ No se pudieron obtener las voces. Verifica las credenciales.\n\n";
    exit;
}

// 2. Generar audio de prueba
echo "2. 🎵 Generando audio de prueba...\n";
$audio_data = generateAzureTTS($text, $voice, $subscription_key, $region);

if ($audio_data === false) {
    echo "❌ Error generando audio\n";
    echo "Verifica que la voz '{$voice}' esté disponible.\n";
    exit;
}

// 3. Guardar archivo
$upload_dir = wp_upload_dir();
$tts_dir = $upload_dir['basedir'] . '/tts-audio';

if (!file_exists($tts_dir)) {
    wp_mkdir_p($tts_dir);
}

$filename = 'azure_test_' . date('YmdHis') . '.mp3';
$file_path = $tts_dir . '/' . $filename;

if (file_put_contents($file_path, $audio_data)) {
    $file_size = filesize($file_path);
    echo "✅ Audio generado exitosamente!\n";
    echo "📁 Archivo: {$file_path}\n";
    echo "📏 Tamaño: " . round($file_size / 1024, 2) . " KB\n\n";
    
    // 4. Configuración para WordPress
    echo "📋 CONFIGURACIÓN PARA TU PLUGIN:\n\n";
    echo "Agrega esto en la configuración de WordPress:\n\n";
    echo "```php\n";
    echo "\$config['providers']['azure_tts'] = [\n";
    echo "    'subscription_key' => '{$subscription_key}',\n";
    echo "    'region' => '{$region}',\n";
    echo "    'default_voice' => '{$voice}',\n";
    echo "    'default_language' => 'es-ES'\n";
    echo "];\n";
    echo "```\n\n";
    
    echo "🎉 ¡Azure TTS está funcionando correctamente!\n";
    
} else {
    echo "❌ Error guardando archivo de audio\n";
}

echo "\n📚 Recursos útiles:\n";
echo "- Portal Azure: https://portal.azure.com\n";
echo "- Documentación: https://docs.microsoft.com/azure/cognitive-services/speech-service/\n";
echo "- Precios: https://azure.microsoft.com/pricing/details/cognitive-services/speech-services/\n";
echo "- Voces disponibles: https://docs.microsoft.com/azure/cognitive-services/speech-service/language-support\n";
?>

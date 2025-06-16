<?php
/**
 * Test script to check available ElevenLabs voices
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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

// Configuración ElevenLabs - SUSTITUIR POR TU API KEY REAL
$elevenlabs_api_key = 'TU_API_KEY_AQUI'; // ⚠️ CAMBIAR ESTO

// Función para obtener voces disponibles de ElevenLabs
function getElevenLabsVoices($api_key) {
    $api_url = 'https://api.elevenlabs.io/v1/voices';
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'xi-api-key: ' . $api_key
            ],
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents($api_url, false, $context);
    
    if ($response === false) {
        echo "❌ Error: No se pudo conectar a la API de ElevenLabs\n";
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Error: Respuesta JSON inválida\n";
        return null;
    }
    
    return $data;
}

echo "🔍 Probando ElevenLabs API y obteniendo voces disponibles...\n\n";

if ($elevenlabs_api_key === 'TU_API_KEY_AQUI') {
    echo "⚠️  ATENCIÓN: Necesitas cambiar la API key en la línea 26 de este script\n";
    echo "   Busca tu API key en: https://elevenlabs.io/app/settings/api-keys\n\n";
} else {
    echo "🔑 Usando API key: " . substr($elevenlabs_api_key, 0, 10) . "...\n\n";
    
    $voices = getElevenLabsVoices($elevenlabs_api_key);
    
    if ($voices && isset($voices['voices'])) {
        echo "✅ Voces disponibles en tu cuenta ElevenLabs:\n\n";
        
        foreach ($voices['voices'] as $voice) {
            $voice_id = $voice['voice_id'] ?? 'N/A';
            $name = $voice['name'] ?? 'N/A';
            $category = $voice['category'] ?? 'N/A';
            $labels = isset($voice['labels']) ? implode(', ', array_keys($voice['labels'])) : 'N/A';
            
            echo "🎤 ID: {$voice_id}\n";
            echo "   Nombre: {$name}\n";
            echo "   Categoría: {$category}\n";
            echo "   Etiquetas: {$labels}\n\n";
        }
        
        // Generar configuración para el plugin
        echo "📝 Configuración sugerida para tu plugin:\n\n";
        echo "Agrega estas voces en ElevenLabsProvider::getAvailableVoices():\n\n";
        echo "```php\n";
        echo "public function getAvailableVoices( string \$language = 'es-MX' ): array {\n";
        echo "    return [\n";
        
        foreach ($voices['voices'] as $voice) {
            $voice_id = $voice['voice_id'] ?? '';
            $name = $voice['name'] ?? '';
            $category = $voice['category'] ?? 'Unknown';
            
            if (!empty($voice_id) && !empty($name)) {
                echo "        [ 'id' => '{$voice_id}', 'name' => '{$name}', 'category' => '{$category}' ],\n";
            }
        }
        
        echo "    ];\n";
        echo "}\n";
        echo "```\n\n";
        
        // Seleccionar una voz por defecto
        if (!empty($voices['voices'])) {
            $default_voice = $voices['voices'][0]['voice_id'];
            echo "💡 Voz sugerida por defecto: {$default_voice}\n";
            echo "   Cambia la línea 102 en ElevenLabsProvider.php:\n";
            echo "   \$voice_id = (!empty(\$options['voice'])) ? \$options['voice'] : (\$this->config['default_voice'] ?? '{$default_voice}');\n\n";
        }
        
    } else {
        echo "❌ Error obteniendo voces. Respuesta:\n";
        echo $response . "\n";
    }
}

echo "🔗 Recursos útiles:\n";
echo "   - Panel ElevenLabs: https://elevenlabs.io/app/voice-lab\n";
echo "   - Documentación API: https://elevenlabs.io/docs/api-reference/voices\n";
echo "   - Clones de voz: https://elevenlabs.io/app/voice-cloning\n";
?>
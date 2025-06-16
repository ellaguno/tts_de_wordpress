<?php
/**
 * Script para diagnosticar y reparar el problema de voces ElevenLabs
 * 
 * INSTRUCCIONES:
 * 1. Obtener tu API key de ElevenLabs: https://elevenlabs.io/app/settings/api-keys
 * 2. Cambiar la variable $elevenlabs_api_key abajo por tu API key real
 * 3. Ejecutar este script desde el navegador o línea de comandos
 */

// Solo funciona si WordPress está cargado
if (!function_exists('wp_upload_dir')) {
    die('Error: Este script debe ejecutarse desde WordPress. Súbelo al directorio del plugin y accede vía navegador.');
}

echo "<h2>🔧 Diagnóstico y Reparación ElevenLabs</h2>";

// CONFIGURACIÓN - CAMBIAR ESTO POR TU API KEY REAL
$elevenlabs_api_key = 'TU_API_KEY_AQUI'; // ⚠️ CAMBIAR ESTO

// 1. Verificar configuración actual
echo "<h3>1. Configuración Actual</h3>";
$config = get_option('wp_tts_config', []);
$elevenlabs_config = $config['providers']['elevenlabs'] ?? [];

echo "<p><strong>API Key configurada:</strong> ";
if (!empty($elevenlabs_config['api_key'])) {
    echo "✅ Sí (" . substr($elevenlabs_config['api_key'], 0, 10) . "...)</p>";
} else {
    echo "❌ No</p>";
}

echo "<p><strong>Voz por defecto:</strong> " . ($elevenlabs_config['default_voice'] ?? 'No configurada') . "</p>";

// 2. Test de API
echo "<h3>2. Test de Conexión API</h3>";

if ($elevenlabs_api_key === 'TU_API_KEY_AQUI') {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<strong>⚠️ Acción Requerida:</strong><br>";
    echo "1. Ve a <a href='https://elevenlabs.io/app/settings/api-keys' target='_blank'>ElevenLabs API Keys</a><br>";
    echo "2. Copia tu API key<br>";
    echo "3. Cambia la línea 17 de este script:<br>";
    echo "<code>\$elevenlabs_api_key = 'tu_api_key_real_aqui';</code><br>";
    echo "4. Recarga esta página<br>";
    echo "</div>";
} else {
    echo "<p>🔑 Probando con API key: " . substr($elevenlabs_api_key, 0, 10) . "...</p>";
    
    // Hacer petición a ElevenLabs
    $api_url = 'https://api.elevenlabs.io/v1/voices';
    
    $response = wp_remote_get( $api_url, [
        'headers' => [
            'Accept' => 'application/json',
            'xi-api-key' => $elevenlabs_api_key,
        ],
        'timeout' => 15,
    ] );
    
    if ( is_wp_error( $response ) ) {
        echo "<p>❌ Error de conexión: " . $response->get_error_message() . "</p>";
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $response_code === 200 ) {
            $data = json_decode( $response_body, true );
            
            if ( isset( $data['voices'] ) && is_array( $data['voices'] ) ) {
                echo "<p>✅ Conexión exitosa! Encontradas " . count( $data['voices'] ) . " voces</p>";
                
                echo "<h3>3. Voces Disponibles en tu Cuenta</h3>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr style='background: #f0f0f0;'><th>ID de Voz</th><th>Nombre</th><th>Categoría</th><th>Etiquetas</th></tr>";
                
                foreach ( $data['voices'] as $voice ) {
                    $voice_id = $voice['voice_id'] ?? 'N/A';
                    $name = $voice['name'] ?? 'N/A';
                    $category = $voice['category'] ?? 'N/A';
                    $labels = isset($voice['labels']) ? implode(', ', array_keys($voice['labels'])) : 'N/A';
                    
                    echo "<tr>";
                    echo "<td><code>{$voice_id}</code></td>";
                    echo "<td>{$name}</td>";
                    echo "<td>{$category}</td>";
                    echo "<td>{$labels}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // 4. Reparación automática
                echo "<h3>4. Reparación Automática</h3>";
                
                if (isset($_GET['fix']) && $_GET['fix'] === '1') {
                    // Ejecutar reparación
                    $first_voice = $data['voices'][0]['voice_id'];
                    
                    // Actualizar configuración
                    $config['providers']['elevenlabs']['api_key'] = $elevenlabs_api_key;
                    $config['providers']['elevenlabs']['default_voice'] = $first_voice;
                    update_option('wp_tts_config', $config);
                    
                    echo "<p>✅ Configuración actualizada:</p>";
                    echo "<ul>";
                    echo "<li>API Key: ✅ Configurada</li>";
                    echo "<li>Voz por defecto: <code>{$first_voice}</code></li>";
                    echo "</ul>";
                    
                    echo "<p><strong>🎉 ¡Reparación completada! Ahora puedes probar ElevenLabs desde el plugin.</strong></p>";
                    
                } else {
                    echo "<p>✅ Se puede reparar automáticamente usando la primera voz disponible:</p>";
                    echo "<p><strong>Voz sugerida:</strong> <code>" . $data['voices'][0]['voice_id'] . "</code> (" . $data['voices'][0]['name'] . ")</p>";
                    
                    echo "<p><a href='?fix=1' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>🔧 Aplicar Reparación</a></p>";
                }
                
            } else {
                echo "<p>❌ Respuesta inválida de la API</p>";
                echo "<pre>" . htmlspecialchars($response_body) . "</pre>";
            }
        } else {
            echo "<p>❌ Error API (Código {$response_code}):</p>";
            echo "<pre>" . htmlspecialchars($response_body) . "</pre>";
        }
    }
}

// 5. Estado final
echo "<h3>5. Estado Final</h3>";
$config_final = get_option('wp_tts_config', []);
$elevenlabs_final = $config_final['providers']['elevenlabs'] ?? [];

echo "<p><strong>ElevenLabs configurado:</strong> " . ((!empty($elevenlabs_final['api_key'])) ? '✅ Sí' : '❌ No') . "</p>";
echo "<p><strong>Voz por defecto:</strong> " . ($elevenlabs_final['default_voice'] ?? 'No configurada') . "</p>";

if (!empty($elevenlabs_final['api_key']) && !empty($elevenlabs_final['default_voice'])) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<strong>✅ ElevenLabs está listo para usar</strong><br>";
    echo "Puedes generar audio TTS desde cualquier post usando ElevenLabs.";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<strong>❌ ElevenLabs necesita configuración</strong><br>";
    echo "Completa los pasos de arriba para usar ElevenLabs.";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Script ejecutado en: " . date('Y-m-d H:i:s') . "</small></p>";
?>
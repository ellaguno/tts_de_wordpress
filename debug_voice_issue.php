<?php
/**
 * Script de diagnóstico para resolver problemas de voces
 * Ejecutar desde WordPress para verificar voces disponibles
 */

// Solo funciona si WordPress está cargado
if (!function_exists('wp_upload_dir')) {
    die('Error: Este script debe ejecutarse desde WordPress');
}

echo "<h2>🔍 Diagnóstico de Voces TTS</h2>";

echo "<h3>1. Configuración Actual</h3>";
$config = get_option('wp_tts_config', []);
echo "<pre>";
print_r($config);
echo "</pre>";

echo "<h3>2. Problema Identificado</h3>";
echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
echo "<p><strong>❌ Error Google:</strong> Voice 'es-MX-Wavenet-C' does not exist</p>";
echo "<p><strong>⚠️ Azure no fue usado:</strong> Round robin seleccionó Google automáticamente</p>";
echo "</div>";

echo "<h3>3. Test Google TTS Voices</h3>";
$google_creds = $config['providers']['google'] ?? [];
if (!empty($google_creds['credentials_path'])) {
    $creds_path = $google_creds['credentials_path'];
    if (substr($creds_path, 0, 1) !== '/') {
        $creds_path = ABSPATH . $creds_path;
    }
    
    if (file_exists($creds_path)) {
        echo "<p>✅ Credenciales Google encontradas: $creds_path</p>";
        
        // Test real de voces Google
        echo "<p>🔍 Probando voces disponibles en Google...</p>";
        
        // Función para obtener voces de Google
        function getGoogleVoices($creds_path) {
            try {
                // Usar el SDK de Google si está disponible
                if (class_exists('\Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient')) {
                    $client = new \Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient([
                        'credentials' => $creds_path
                    ]);
                    
                    $response = $client->listVoices();
                    $voices = [];
                    foreach ($response->getVoices() as $voice) {
                        $languageCodes = $voice->getLanguageCodes();
                        $name = $voice->getName();
                        if (count($languageCodes) > 0) {
                            $langCode = $languageCodes[0];
                            if (strpos($langCode, 'es') === 0) { // Solo voces en español
                                $voices[] = [
                                    'name' => $name,
                                    'language' => $langCode,
                                    'gender' => $voice->getSsmlGender()
                                ];
                            }
                        }
                    }
                    $client->close();
                    return $voices;
                }
            } catch (Exception $e) {
                return ['error' => $e->getMessage()];
            }
            return ['error' => 'SDK no disponible'];
        }
        
        $google_voices = getGoogleVoices($creds_path);
        
        if (isset($google_voices['error'])) {
            echo "<p>❌ Error obteniendo voces: " . $google_voices['error'] . "</p>";
        } else {
            echo "<p>✅ Voces españolas disponibles en Google (" . count($google_voices) . "):</p>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Nombre</th><th>Idioma</th><th>Género</th></tr>";
            foreach (array_slice($google_voices, 0, 10) as $voice) { // Solo primeras 10
                echo "<tr>";
                echo "<td>{$voice['name']}</td>";
                echo "<td>{$voice['language']}</td>";
                echo "<td>{$voice['gender']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p>❌ Archivo de credenciales no encontrado: $creds_path</p>";
    }
} else {
    echo "<p>❌ No hay credenciales Google configuradas</p>";
}

echo "<h3>4. Test Azure TTS Voices</h3>";
$azure_creds = $config['providers']['azure_tts'] ?? [];
if (!empty($azure_creds['subscription_key']) && !empty($azure_creds['region'])) {
    echo "<p>✅ Credenciales Azure encontradas</p>";
    
    // Test Azure
    $api_url = "https://{$azure_creds['region']}.tts.speech.microsoft.com/cognitiveservices/voices/list";
    
    $response = wp_remote_get($api_url, [
        'headers' => [
            'Ocp-Apim-Subscription-Key' => $azure_creds['subscription_key'],
            'User-Agent' => 'WordPress-TTS-Debug/1.0'
        ],
        'timeout' => 15
    ]);
    
    if (!is_wp_error($response)) {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $voices_data = json_decode(wp_remote_retrieve_body($response), true);
            $spanish_voices = array_filter($voices_data, function($voice) {
                return strpos($voice['Locale'], 'es-') === 0;
            });
            
            echo "<p>✅ Voces españolas disponibles en Azure (" . count($spanish_voices) . "):</p>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Nombre</th><th>Locale</th><th>Género</th></tr>";
            foreach (array_slice($spanish_voices, 0, 10) as $voice) { // Solo primeras 10
                echo "<tr>";
                echo "<td>{$voice['ShortName']}</td>";
                echo "<td>{$voice['DisplayName']}</td>";
                echo "<td>{$voice['Locale']}</td>";
                echo "<td>{$voice['Gender']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ Error Azure API: HTTP $response_code</p>";
        }
    } else {
        echo "<p>❌ Error conectando a Azure: " . $response->get_error_message() . "</p>";
    }
} else {
    echo "<p>❌ Credenciales Azure incompletas</p>";
}

echo "<h3>5. Soluciones Recomendadas</h3>";
echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
echo "<h4>🔧 Para usar Azure específicamente:</h4>";
echo "<ol>";
echo "<li><strong>En el editor del post:</strong> Selecciona 'Azure_tts' en el dropdown antes de generar</li>";
echo "<li><strong>O ejecuta:</strong> <a href='?fix_google=1'>Corregir voz Google por defecto</a></li>";
echo "<li><strong>O ejecuta:</strong> <a href='?set_azure_default=1'>Establecer Azure como provider por defecto</a></li>";
echo "</ol>";
echo "</div>";

// Acciones de reparación
if (isset($_GET['fix_google']) && $_GET['fix_google'] === '1') {
    echo "<h3>6. Corrigiendo voz Google...</h3>";
    $config['providers']['google']['default_voice'] = 'es-MX-Wavenet-A'; // Una voz que sí existe
    update_option('wp_tts_config', $config);
    echo "<p>✅ Voz Google actualizada a: es-MX-Wavenet-A</p>";
}

if (isset($_GET['set_azure_default']) && $_GET['set_azure_default'] === '1') {
    echo "<h3>6. Estableciendo Azure como provider por defecto...</h3>";
    $config['default_provider'] = 'azure_tts';
    update_option('wp_tts_config', $config);
    echo "<p>✅ Azure TTS establecido como provider por defecto</p>";
}

echo "<hr>";
echo "<p><strong>💡 Recomendación:</strong> Usa Azure seleccionándolo manualmente en el dropdown del post, o establécelo como provider por defecto.</p>";
echo "<p><small>Script ejecutado en: " . date('Y-m-d H:i:s') . "</small></p>";
?>
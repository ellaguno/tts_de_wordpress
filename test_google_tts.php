<?php
/**
 * Script de prueba local para Google Cloud TTS
 * 
 * Ejecutar con: php test_google_tts.php
 */

// Incluir autoloader de Composer - REQUERIDO
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die("Error: Composer autoloader no encontrado. Ejecuta 'composer install' primero.\n");
}

// Simular constantes de WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Incluir las clases necesarias
require_once __DIR__ . '/src/Utils/Logger.php';
require_once __DIR__ . '/src/Interfaces/TTSProviderInterface.php';
// AudioResult está definida en TTSProviderInterface.php
require_once __DIR__ . '/src/Exceptions/ProviderException.php';
require_once __DIR__ . '/src/Providers/GoogleCloudTTSProvider.php';

use WP_TTS\Providers\GoogleCloudTTSProvider;
use WP_TTS\Utils\Logger;
use WP_TTS\Interfaces\AudioResult;

class LocalTester {
    private $logger;
    
    public function __construct() {
        $this->logger = new Logger();
    }
    
    public function testGoogleTTS() {
        echo "=== PRUEBA LOCAL DE GOOGLE CLOUD TTS ===\n\n";
        
        // 1. Verificar credenciales
        echo "1. Verificando credenciales...\n";
        $credentials_paths = [
            __DIR__ . '/wp-content/uploads/private/sesolibre-tts-13985ba22d36.json',
            __DIR__ . '/sesolibre-tts-13985ba22d36.json',
            __DIR__ . '/google-credentials.json'
        ];
        
        $credentials_path = null;
        foreach ($credentials_paths as $path) {
            if (file_exists($path)) {
                $credentials_path = $path;
                echo "   ✓ Credenciales encontradas en: $path\n";
                break;
            }
        }
        
        if (!$credentials_path) {
            echo "   ✗ No se encontraron credenciales en las rutas esperadas:\n";
            foreach ($credentials_paths as $path) {
                echo "     - $path\n";
            }
            echo "\n   Por favor, coloca tu archivo de credenciales de Google en una de estas rutas.\n";
            return false;
        }
        
        // 2. Verificar SDK de Google
        echo "\n2. Verificando SDK de Google Cloud...\n";
        // Intentar diferentes rutas de clase
        $class_paths = [
            '\Google\Cloud\TextToSpeech\V1\TextToSpeechClient',
            '\Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient'
        ];
        
        $client_class = null;
        foreach ($class_paths as $class_path) {
            if (class_exists($class_path)) {
                $client_class = $class_path;
                break;
            }
        }
        
        if (!$client_class) {
            echo "   ✗ SDK de Google Cloud no encontrado.\n";
            echo "   Clases buscadas:\n";
            foreach ($class_paths as $class_path) {
                echo "     - $class_path: " . (class_exists($class_path) ? 'ENCONTRADA' : 'NO ENCONTRADA') . "\n";
            }
            return false;
        }
        echo "   ✓ SDK de Google Cloud disponible\n";
        
        // 3. Configurar proveedor
        echo "\n3. Configurando proveedor Google TTS...\n";
        $config = [
            'credentials_path' => $credentials_path,
            'default_voice' => 'es-MX-Wavenet-A',
            'speaking_rate' => 1.0,
            'pitch' => 0.0
        ];
        
        $provider = new GoogleCloudTTSProvider($config, $this->logger);
        
        // 4. Verificar configuración
        echo "\n4. Verificando configuración del proveedor...\n";
        if (!$provider->isConfigured()) {
            echo "   ✗ El proveedor no está configurado correctamente\n";
            return false;
        }
        echo "   ✓ Proveedor configurado correctamente\n";
        
        // 5. Crear directorio de salida
        echo "\n5. Preparando directorio de salida...\n";
        $output_dir = __DIR__ . '/test_output';
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
            echo "   ✓ Directorio creado: $output_dir\n";
        } else {
            echo "   ✓ Directorio existe: $output_dir\n";
        }
        
        // 6. Realizar prueba de síntesis
        echo "\n6. Realizando prueba de síntesis de voz...\n";
        $test_text = "Hola, esta es una prueba de Google Cloud TTS desde el plugin de WordPress. El sistema está funcionando correctamente.";
        
        try {
            echo "   Texto a sintetizar: \"$test_text\"\n";
            echo "   Generando audio...\n";
            
            // Simplemente probemos con voces estándar
            echo "   Usando voz estándar de Google...\n";
            $first_voice = 'es-ES-Standard-A';
            
            $options = [
                'voice' => $first_voice ?? 'es-ES-Standard-A',
                'output_format' => 'mp3'
            ];
            
            $result = $provider->generateSpeech($test_text, $options);
            
            if ($result && $result['success']) {
                echo "   ✓ Audio generado exitosamente!\n";
                echo "   Archivo: {$result['file_path']}\n";
                echo "   URL: {$result['audio_url']}\n";
                echo "   Proveedor: {$result['provider']}\n";
                echo "   Voz: {$result['voice']}\n";
                echo "   Tamaño: " . filesize($result['file_path']) . " bytes\n";
                
                // Copiar archivo a directorio de prueba para fácil acceso
                $test_file = $output_dir . '/google_tts_test.mp3';
                if (copy($result['file_path'], $test_file)) {
                    echo "   ✓ Copia guardada en: $test_file\n";
                }
                
                return true;
            } else {
                echo "   ✗ Falló la generación de audio\n";
                if (isset($result['error'])) {
                    echo "   Error: {$result['error']}\n";
                }
                return false;
            }
            
        } catch (Exception $e) {
            echo "   ✗ Excepción durante la síntesis:\n";
            echo "   Error: " . $e->getMessage() . "\n";
            echo "   Archivo: " . $e->getFile() . "\n";
            echo "   Línea: " . $e->getLine() . "\n";
            return false;
        }
    }
    
    public function testVoicesList() {
        echo "\n\n=== LISTADO DE VOCES DISPONIBLES ===\n";
        
        $config = [
            'credentials_path' => '',
            'default_voice' => 'es-MX-Wavenet-A'
        ];
        
        $provider = new GoogleCloudTTSProvider($config, $this->logger);
        $voices = $provider->getAvailableVoices();
        
        foreach ($voices as $voice) {
            echo sprintf("- %s: %s (%s, %s)\n", 
                $voice['id'], 
                $voice['name'], 
                $voice['gender'] ?? 'Unknown', 
                $voice['language'] ?? 'Unknown'
            );
        }
    }
}

// Función para simular wp_upload_dir()
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        $upload_dir = __DIR__ . '/wp-content/uploads';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        return [
            'basedir' => $upload_dir,
            'baseurl' => 'http://localhost/wp-content/uploads'
        ];
    }
}

// Función para simular wp_mkdir_p()
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return mkdir($target, 0755, true);
    }
}

// Función para simular wp_json_encode()
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

// Ejecutar la prueba
$tester = new LocalTester();
$success = $tester->testGoogleTTS();

if ($success) {
    echo "\n🎉 ¡PRUEBA EXITOSA! Google Cloud TTS está funcionando correctamente.\n";
    $tester->testVoicesList();
} else {
    echo "\n❌ La prueba falló. Revisa la configuración y las credenciales.\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
<?php
/**
 * Script manual para configurar credenciales en WordPress
 * Subir este archivo al directorio del plugin y ejecutar desde el navegador
 */

// Solo funciona si WordPress está cargado
if (!function_exists('wp_upload_dir')) {
    die('Error: Este script debe ejecutarse desde WordPress');
}

echo "<h2>Configuración Manual de Credenciales Google TTS</h2>";

// 1. Verificar estado actual
echo "<h3>1. Diagnóstico Actual</h3>";
$config = get_option('wp_tts_config', []);
echo "<pre>";
echo "Configuración actual:\n";
print_r($config);
echo "</pre>";

// 2. Verificar archivo de credenciales
echo "<h3>2. Verificar Credenciales</h3>";
$source_file = __DIR__ . '/sesolibre-tts-13985ba22d36.json';
$upload_dir = wp_upload_dir();
$private_dir = $upload_dir['basedir'] . '/private';
$target_file = $private_dir . '/sesolibre-tts-13985ba22d36.json';

echo "<p><strong>Archivo origen:</strong> $source_file</p>";
echo "<p><strong>Existe:</strong> " . (file_exists($source_file) ? '✅ Sí' : '❌ No') . "</p>";

echo "<p><strong>Directorio privado:</strong> $private_dir</p>";
echo "<p><strong>Existe:</strong> " . (is_dir($private_dir) ? '✅ Sí' : '❌ No') . "</p>";

echo "<p><strong>Archivo destino:</strong> $target_file</p>";
echo "<p><strong>Existe:</strong> " . (file_exists($target_file) ? '✅ Sí' : '❌ No') . "</p>";

// 3. Acción de reparación
if (isset($_GET['fix']) && $_GET['fix'] === '1') {
    echo "<h3>3. Ejecutando Reparación</h3>";
    
    // Crear directorio si no existe
    if (!is_dir($private_dir)) {
        wp_mkdir_p($private_dir);
        echo "<p>✅ Directorio privado creado</p>";
    }
    
    // Copiar archivo
    if (file_exists($source_file)) {
        if (copy($source_file, $target_file)) {
            chmod($target_file, 0600);
            echo "<p>✅ Credenciales copiadas y permisos establecidos</p>";
            
            // Crear .htaccess
            $htaccess_file = $private_dir . '/.htaccess';
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
            echo "<p>✅ Archivo .htaccess creado</p>";
            
        } else {
            echo "<p>❌ Error al copiar credenciales</p>";
        }
    } else {
        echo "<p>❌ Archivo de credenciales no encontrado en el plugin</p>";
    }
}

// 4. Prueba de proveedor
echo "<h3>4. Prueba de Proveedores</h3>";

// Instanciar RoundRobinManager para probar
require_once __DIR__ . '/src/Core/ConfigurationManager.php';
require_once __DIR__ . '/src/Services/RoundRobinManager.php';

$config_manager = new \WP_TTS\Core\ConfigurationManager();
$round_robin = new \WP_TTS\Services\RoundRobinManager($config_manager);

$providers = ['google', 'openai', 'elevenlabs', 'amazon_polly', 'azure_tts'];
foreach ($providers as $provider) {
    $is_active = $round_robin->isProviderActive($provider);
    echo "<p><strong>$provider:</strong> " . ($is_active ? '✅ Activo' : '❌ Inactivo') . "</p>";
}

// 5. Botón de reparación
if (!isset($_GET['fix'])) {
    echo "<h3>5. Reparación</h3>";
    echo "<p><a href='?fix=1' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>🔧 Ejecutar Reparación</a></p>";
}

echo "<hr>";
echo "<p><small>Script ejecutado en: " . date('Y-m-d H:i:s') . "</small></p>";
?>
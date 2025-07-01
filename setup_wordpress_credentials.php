<?php
/**
 * Script para configurar credenciales en WordPress
 * Ejecutar este script DESPUÉS de instalar el plugin en WordPress
 */

// Simular el environment de WordPress si no estamos en WordPress
if (!function_exists('wp_upload_dir')) {
    // Si estamos en desarrollo local, usar rutas locales
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

echo "=== CONFIGURACIÓN DE CREDENCIALES PARA WORDPRESS ===\n\n";

// 1. Verificar si tenemos el archivo de credenciales
$source_credentials = __DIR__ . '/sesolibre-tts-13985ba22d36.json';
if (!file_exists($source_credentials)) {
    echo "❌ Error: No se encontró el archivo de credenciales en: $source_credentials\n";
    echo "   Por favor, asegúrate de que el archivo esté en la raíz del plugin.\n";
    exit(1);
}

echo "✅ Archivo de credenciales encontrado: $source_credentials\n";

// 2. Preparar directorio de destino
$upload_dir = wp_upload_dir();
$private_dir = $upload_dir['basedir'] . '/private';
$target_credentials = $private_dir . '/sesolibre-tts-13985ba22d36.json';

echo "📁 Preparando directorio: $private_dir\n";

if (!is_dir($private_dir)) {
    if (mkdir($private_dir, 0755, true)) {
        echo "✅ Directorio creado exitosamente\n";
    } else {
        echo "❌ Error: No se pudo crear el directorio: $private_dir\n";
        exit(1);
    }
} else {
    echo "✅ Directorio ya existe\n";
}

// 3. Copiar archivo de credenciales
echo "📋 Copiando credenciales...\n";

if (copy($source_credentials, $target_credentials)) {
    echo "✅ Credenciales copiadas exitosamente a: $target_credentials\n";
    
    // Verificar permisos
    chmod($target_credentials, 0600); // Solo lectura para el propietario
    echo "🔒 Permisos de archivo establecidos (600)\n";
} else {
    echo "❌ Error: No se pudieron copiar las credenciales\n";
    exit(1);
}

// 4. Crear archivo .htaccess para proteger el directorio
$htaccess_file = $private_dir . '/.htaccess';
$htaccess_content = "# Denegar acceso a archivos de credenciales\n";
$htaccess_content .= "Order deny,allow\n";
$htaccess_content .= "Deny from all\n";

if (file_put_contents($htaccess_file, $htaccess_content)) {
    echo "🛡️  Archivo .htaccess creado para proteger credenciales\n";
} else {
    echo "⚠️  Advertencia: No se pudo crear .htaccess, el directorio podría ser accesible públicamente\n";
}

// 5. Verificar que todo funcione
echo "\n📋 Verificando configuración...\n";

if (file_exists($target_credentials) && is_readable($target_credentials)) {
    $credentials_content = file_get_contents($target_credentials);
    $credentials_data = json_decode($credentials_content, true);
    
    if ($credentials_data && isset($credentials_data['type'])) {
        echo "✅ Credenciales válidas (tipo: " . $credentials_data['type'] . ")\n";
        echo "✅ Proyecto: " . ($credentials_data['project_id'] ?? 'N/A') . "\n";
    } else {
        echo "⚠️  Advertencia: El archivo de credenciales podría no ser válido\n";
    }
} else {
    echo "❌ Error: No se puede leer el archivo de credenciales copiado\n";
    exit(1);
}

echo "\n🎉 ¡CONFIGURACIÓN COMPLETADA!\n\n";
echo "📝 Instrucciones para WordPress:\n";
echo "   1. Asegúrate de que el plugin esté activado\n";
echo "   2. Ve a Ajustes > TTS Settings en el admin de WordPress\n";
echo "   3. Configura los providers con sus respectivas API keys\n";
echo "   4. El archivo de credenciales de Google ya está en la ubicación correcta\n";
echo "\n🔧 Ruta de credenciales: $target_credentials\n";
echo "\n=== FIN DE LA CONFIGURACIÓN ===\n";
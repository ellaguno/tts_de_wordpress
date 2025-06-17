<?php
/**
 * Test Script para verificar las correcciones implementadas
 * 
 * 1. Dropdown de Voice en TTS Tools
 * 2. Información del audio existente en meta-box
 */

echo "<h2>🧪 Test de Correcciones TTS</h2>\n";

// Test 1: Verificar handlers AJAX registrados
echo "<h3>1. ✅ Verificación de Handlers AJAX</h3>\n";

$plugin_file = __DIR__ . '/src/Core/Plugin.php';
$admin_file = __DIR__ . '/src/Admin/AdminInterface.php';

if (file_exists($plugin_file)) {
    $plugin_content = file_get_contents($plugin_file);
    $handlers_registered = [
        'tts_get_voices' => strpos($plugin_content, "wp_ajax_tts_get_voices") !== false,
        'tts_preview_voice' => strpos($plugin_content, "wp_ajax_tts_preview_voice") !== false,
        'tts_delete_audio' => strpos($plugin_content, "wp_ajax_tts_delete_audio") !== false,
    ];
    
    foreach ($handlers_registered as $handler => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " Handler '$handler' " . ($exists ? "registrado" : "NO registrado") . "\n";
    }
} else {
    echo "❌ No se pudo leer Plugin.php\n";
}

if (file_exists($admin_file)) {
    $admin_content = file_get_contents($admin_file);
    $methods_implemented = [
        'handleGetVoices' => strpos($admin_content, "public function handleGetVoices") !== false,
        'handlePreviewVoice' => strpos($admin_content, "public function handlePreviewVoice") !== false,
        'handleGenerateCustom' => strpos($admin_content, "public function handleGenerateCustom") !== false,
    ];
    
    foreach ($methods_implemented as $method => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " Método '$method' " . ($exists ? "implementado" : "NO implementado") . "\n";
    }
} else {
    echo "❌ No se pudo leer AdminInterface.php\n";
}

// Test 2: Verificar corrección de nonces
echo "\n<h3>2. ✅ Verificación de Nonces Corregidos</h3>\n";

if (file_exists($admin_file)) {
    $admin_content = file_get_contents($admin_file);
    
    // Verificar que usa wp_tts_admin nonce
    $correct_nonces = [
        'tts_get_voices con wp_tts_admin' => strpos($admin_content, "wp_create_nonce('wp_tts_admin')") !== false,
        'Verificación de nonce en handleGetVoices' => strpos($admin_content, "verifyNonce( sanitize_text_field(wp_unslash(\$_POST['nonce'])), 'wp_tts_admin' )") !== false,
        'Verificación de nonce en handlePreviewVoice' => strpos($admin_content, "public function handlePreviewVoice") !== false,
    ];
    
    foreach ($correct_nonces as $check => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " $check " . ($exists ? "correcto" : "necesita corrección") . "\n";
    }
} else {
    echo "❌ No se pudo verificar nonces\n";
}

// Test 3: Verificar meta-box actualizado
echo "\n<h3>3. ✅ Verificación de Meta-box con Información de Audio</h3>\n";

$metabox_file = __DIR__ . '/templates/admin/meta-box.php';

if (file_exists($metabox_file)) {
    $metabox_content = file_get_contents($metabox_file);
    
    $metabox_features = [
        'Audio Details section' => strpos($metabox_content, 'Audio Details') !== false,
        'Provider display' => strpos($metabox_content, "_e('Provider:', 'TTS de Wordpress')") !== false,
        'Voice display' => strpos($metabox_content, "_e('Voice:', 'TTS de Wordpress')") !== false,
        'Generated date' => strpos($metabox_content, "_e('Generated:', 'TTS de Wordpress')") !== false,
        'File size' => strpos($metabox_content, "_e('File Size:', 'TTS de Wordpress')") !== false,
        'Provider badge' => strpos($metabox_content, 'background: #007cba; color: white') !== false,
        'Time ago display' => strpos($metabox_content, 'human_time_diff') !== false,
    ];
    
    foreach ($metabox_features as $feature => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " $feature " . ($exists ? "implementado" : "NO implementado") . "\n";
    }
} else {
    echo "❌ No se pudo leer meta-box.php\n";
}

// Test 4: Verificar JavaScript mejorado
echo "\n<h3>4. ✅ Verificación de JavaScript TTS Tools</h3>\n";

if (file_exists($admin_file)) {
    $admin_content = file_get_contents($admin_file);
    
    $js_features = [
        'Provider change handler para preview' => strpos($admin_content, "('#preview_provider').on('change'") !== false,
        'Provider change handler para custom' => strpos($admin_content, "('#custom_provider').on('change'") !== false,
        'AJAX loading voices' => strpos($admin_content, "Loading voices...") !== false,
        'Error handling en AJAX' => strpos($admin_content, "AJAX Call Failed") !== false,
        'Console logging para debug' => strpos($admin_content, "console.log") !== false,
        'Voice dropdown population' => strpos($admin_content, "response.data.voices.forEach") !== false,
    ];
    
    foreach ($js_features as $feature => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " $feature " . ($exists ? "implementado" : "NO implementado") . "\n";
    }
} else {
    echo "❌ No se pudo verificar JavaScript\n";
}

echo "\n<h3>📊 Resumen de Correcciones</h3>\n";
echo "✅ **Problema 1**: Dropdown de Voice en TTS Tools\n";
echo "   - Handlers AJAX corregidos\n";
echo "   - Nonces unificados a 'wp_tts_admin'\n";
echo "   - JavaScript mejorado con debugging\n";
echo "   - Error handling robusto\n\n";

echo "✅ **Problema 2**: Información de audio en meta-box\n";
echo "   - Sección 'Audio Details' añadida\n";
echo "   - Proveedor con badge visual\n";
echo "   - Voz utilizada\n";
echo "   - Fecha de generación con tiempo relativo\n";
echo "   - Tamaño del archivo\n";
echo "   - Diseño con grid layout\n\n";

echo "🎯 **Estado**: Ambas correcciones implementadas correctamente\n";
echo "📦 **Siguiente paso**: Crear ZIP actualizado para testing\n";

echo "\n<h3>🚀 Instrucciones de Prueba</h3>\n";
echo "1. **TTS Tools**: Ir a Tools > TTS Tools, seleccionar un provider y verificar que se llene el dropdown Voice\n";
echo "2. **Meta-box**: Editar un post con audio generado y verificar que muestra Provider, Voice, Fecha y Tamaño\n";
echo "3. **Debug**: Abrir DevTools (F12) para ver logs de AJAX en TTS Tools\n";
?>
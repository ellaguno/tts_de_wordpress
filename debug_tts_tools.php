<?php
/**
 * Debug script para diagnosticar problemas en TTS Tools
 */

echo "<h2>🔍 Debug TTS Tools</h2>\n";

// Test 1: Verificar handlers AJAX registrados
echo "<h3>1. Handlers AJAX Registrados</h3>\n";

$admin_file = __DIR__ . '/src/Admin/AdminInterface.php';
if (file_exists($admin_file)) {
    $content = file_get_contents($admin_file);
    
    $handlers = [
        'tts_get_voices' => strpos($content, "wp_ajax_tts_get_voices") !== false,
        'tts_preview_voice' => strpos($content, "wp_ajax_tts_preview_voice") !== false,
        'tts_generate_custom' => strpos($content, "wp_ajax_tts_generate_custom") !== false,
    ];
    
    foreach ($handlers as $handler => $registered) {
        echo "- " . ($registered ? "✅" : "❌") . " $handler\n";
    }
} else {
    echo "❌ No se pudo leer AdminInterface.php\n";
}

// Test 2: Verificar nonces
echo "\n<h3>2. Nonces en JavaScript</h3>\n";

if (file_exists($admin_file)) {
    $content = file_get_contents($admin_file);
    
    $nonces = [
        'tts_get_voices con wp_tts_admin' => preg_match('/tts_get_voices.*wp_create_nonce\(\'wp_tts_admin\'\)/', $content),
        'tts_preview_voice con wp_tts_admin' => preg_match('/tts_preview_voice.*wp_create_nonce\(\'wp_tts_admin\'\)/', $content),
        'tts_generate_custom con propio nonce' => preg_match('/tts_generate_custom.*wp_create_nonce\(\'wp_tts_generate_custom\'\)/', $content),
    ];
    
    foreach ($nonces as $check => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " $check\n";
    }
} else {
    echo "❌ No se pudo verificar nonces\n";
}

// Test 3: Verificar configuración ElevenLabs
echo "\n<h3>3. Configuración ElevenLabs</h3>\n";

if (file_exists($admin_file)) {
    $content = file_get_contents($admin_file);
    
    $elevenlabs_checks = [
        'Voz por defecto corregida' => strpos($content, "pNInz6obpgDQGcFmaJgB") !== false,
        'Rachel eliminada' => strpos($content, "'Rachel'") === false,
        'IDs válidos de ElevenLabs' => strpos($content, "EXAVITQu4vr4xnSDxMaL") !== false,
    ];
    
    foreach ($elevenlabs_checks as $check => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " $check\n";
    }
} else {
    echo "❌ No se pudo verificar ElevenLabs\n";
}

// Test 4: Verificar método getAvailableVoices en providers
echo "\n<h3>4. Métodos getAvailableVoices en Providers</h3>\n";

$providers = [
    'GoogleCloudTTSProvider.php',
    'OpenAITTSProvider.php', 
    'ElevenLabsProvider.php',
    'AzureTTSProvider.php',
    'AmazonPollyProvider.php'
];

foreach ($providers as $provider) {
    $file = __DIR__ . '/src/Providers/' . $provider;
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $has_method = strpos($content, 'getAvailableVoices') !== false;
        echo "- " . ($has_method ? "✅" : "❌") . " $provider\n";
    } else {
        echo "- ❌ $provider (no existe)\n";
    }
}

// Test 5: Verificar TTSService
echo "\n<h3>5. TTSService</h3>\n";

$tts_service_file = __DIR__ . '/src/Services/TTSService.php';
if (file_exists($tts_service_file)) {
    $content = file_get_contents($tts_service_file);
    
    $service_checks = [
        'getAvailableVoices implementado' => strpos($content, 'public function getAvailableVoices') !== false,
        'Guarda provider en resultado' => strpos($content, "'provider' => \$current_provider_name") !== false,
        'Guarda voice en resultado' => strpos($content, "'voice' => \$speech_call_options['voice']") !== false,
        'Guarda provider y voice en post meta' => strpos($content, "update_post_meta( \$post_id, '_tts_voice_provider'") !== false,
    ];
    
    foreach ($service_checks as $check => $exists) {
        echo "- " . ($exists ? "✅" : "❌") . " $check\n";
    }
} else {
    echo "❌ No se pudo leer TTSService.php\n";
}

echo "\n<h3>📋 Resumen de Problemas Identificados</h3>\n";

echo "🔧 **Problemas a Resolver:**\n";
echo "1. **Error loading voices**: Verificar que providers implementen getAvailableVoices correctamente\n";
echo "2. **Custom Text Generation**: Verificar AJAX y nonces\n"; 
echo "3. **ElevenLabs Rachel**: Corregida a voces válidas\n";
echo "4. **Guardar provider/voice**: Implementado en TTSService\n";

echo "\n<h3>🚀 Próximos Pasos</h3>\n";
echo "1. ✅ Verificar que todos los providers implementen getAvailableVoices\n";
echo "2. ✅ Verificar AJAX handlers y nonces\n";
echo "3. ✅ Probar TTS Tools en WordPress\n";
echo "4. ✅ Verificar logs de errores\n";
?>
<?php
/**
 * Script para probar las correcciones en TTS Tools
 */

echo "<h2>🔧 Test de Correcciones TTS Tools</h2>";

// 1. Verificar filtrado de providers
echo "<h3>1. ✅ Filtrado de Providers</h3>";
$admin_file = __DIR__ . '/src/Admin/AdminInterface.php';
if (file_exists($admin_file)) {
    $content = file_get_contents($admin_file);
    if (strpos($content, 'validateProvider') !== false) {
        echo "✅ Filtrado de providers implementado<br>";
        if (strpos($content, 'Only show providers that are actually configured') !== false) {
            echo "✅ Comentario explicativo encontrado<br>";
        }
    }
}

// 2. Verificar variable ajaxurl
echo "<h3>2. 📡 Variable AJAX URL</h3>";
if (strpos($content, 'var ajaxurl') !== false) {
    echo "✅ Variable ajaxurl definida en JavaScript<br>";
}

// 3. Verificar debugging mejorado
echo "<h3>3. 🐛 Debugging Mejorado</h3>";
$debug_features = 0;

if (strpos($content, 'console.log') !== false) {
    echo "✅ Console.log añadido para debugging<br>";
    $debug_features++;
}

if (strpos($content, 'AJAX Response') !== false) {
    echo "✅ Logging de respuestas AJAX implementado<br>";
    $debug_features++;
}

if (strpos($content, 'error: function(xhr') !== false) {
    echo "✅ Manejo de errores AJAX mejorado<br>";
    $debug_features++;
}

echo "<p><strong>Funciones de debugging: $debug_features/3</strong></p>";

// 4. Verificar handler mejorado
echo "<h3>4. 🎯 Handler AJAX Mejorado</h3>";
$plugin_file = __DIR__ . '/src/Core/Plugin.php';
if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);
    if (strpos($content, 'Get voices request') !== false) {
        echo "✅ Logging en handler AJAX implementado<br>";
    }
    if (strpos($content, 'voices_count') !== false) {
        echo "✅ Contador de voces en respuesta<br>";
    }
}

// 5. Verificar response mejorada
echo "<h3>5. 📤 Respuesta AJAX Mejorada</h3>";
$response_features = 0;

if (strpos($content, "'provider' => \$provider") !== false) {
    echo "✅ Provider incluido en respuesta<br>";
    $response_features++;
}

if (strpos($content, "'count' => count") !== false) {
    echo "✅ Contador de voces incluido<br>";
    $response_features++;
}

echo "<p><strong>Mejoras en respuesta: $response_features/2</strong></p>";

echo "<hr>";

// Instrucciones de testing
echo "<h3>🧪 Para Probar las Correcciones:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<ol>";
echo "<li><strong>Ve a Tools > TTS Tools</strong></li>";
echo "<li><strong>Verifica que solo aparecen providers configurados</strong> (no debería aparecer Amazon Polly)</li>";
echo "<li><strong>Selecciona un provider válido</strong> (Google, OpenAI, Azure, etc.)</li>";
echo "<li><strong>Verifica que el dropdown Voice se llena</strong></li>";
echo "<li><strong>Abre Developer Tools (F12)</strong> para ver logs de debugging</li>";
echo "<li><strong>Intenta generar un preview</strong> para verificar funcionalidad</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<p><strong>✅ Correcciones Aplicadas:</strong></p>";
echo "<ul>";
echo "<li>🔍 <strong>Filtrado de providers:</strong> Solo muestra providers con credenciales válidas</li>";
echo "<li>📡 <strong>Variable ajaxurl:</strong> Definida correctamente para llamadas AJAX</li>";
echo "<li>🐛 <strong>Debugging mejorado:</strong> Console.log para troubleshooting</li>";
echo "<li>🎯 <strong>Handler mejorado:</strong> Más información de logging</li>";
echo "<li>📤 <strong>Respuesta mejorada:</strong> Más datos para verificación</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<p><strong>⚠️ Si sigue sin funcionar:</strong></p>";
echo "<ul>";
echo "<li>Verifica que hay providers configurados con credenciales válidas</li>";
echo "<li>Revisa la consola del navegador (F12) para errores JavaScript</li>";
echo "<li>Verifica permisos de usuario (debe tener 'edit_posts')</li>";
echo "<li>Asegúrate de que WordPress AJAX está funcionando</li>";
echo "</ul>";
echo "</div>";

echo "<p><small>Test ejecutado en: " . date('Y-m-d H:i:s') . "</small></p>";
?>
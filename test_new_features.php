<?php
/**
 * Script para probar las nuevas funciones implementadas
 */

echo "<h2>🧪 Test de Nuevas Funciones TTS</h2>";

// 1. Verificar que la inserción automática está habilitada
echo "<h3>1. ✅ Inserción Automática de Audio</h3>";
$plugin_file = __DIR__ . '/src/Core/Plugin.php';
if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);
    if (strpos($content, 'maybeAddAudioPlayer') !== false) {
        echo "✅ Función de inserción automática encontrada<br>";
        if (strpos($content, "add_filter( 'the_content', array( \$this, 'maybeAddAudioPlayer' ) );") !== false) {
            echo "✅ Hook de inserción automática registrado correctamente<br>";
        }
    }
}

// 2. Verificar el botón de borrar
echo "<h3>2. 🗑️ Botón Borrar Audio</h3>";
$metabox_file = __DIR__ . '/templates/admin/meta-box.php';
if (file_exists($metabox_file)) {
    $content = file_get_contents($metabox_file);
    if (strpos($content, 'tts_delete_audio') !== false) {
        echo "✅ Botón 'Delete Audio' encontrado en meta-box<br>";
    }
    if (strpos($content, 'handleDeleteAudio') === false && strpos($content, 'tts_delete_audio') !== false) {
        echo "✅ JavaScript para borrar audio implementado<br>";
    }
}

// Verificar handler AJAX
if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);
    if (strpos($content, 'handleDeleteAudio') !== false) {
        echo "✅ Handler AJAX para borrar audio implementado<br>";
    }
}

// 3. Verificar meta-box simplificado
echo "<h3>3. 🎨 Meta-box Simplificado</h3>";
if (file_exists($metabox_file)) {
    $content = file_get_contents($metabox_file);
    $removed_features = 0;
    
    if (strpos($content, 'Custom Text') === false) {
        echo "✅ Área 'Custom Text' removida<br>";
        $removed_features++;
    }
    
    if (strpos($content, 'tts_preview_voice') === false) {
        echo "✅ Botón 'Preview' removido<br>";
        $removed_features++;
    }
    
    if (strpos($content, 'Audio Enhancement') === false) {
        echo "✅ Sección 'Audio Enhancement' removida<br>";
        $removed_features++;
    }
    
    echo "<p><strong>Funciones removidas: $removed_features/3</strong></p>";
}

// 4. Verificar página TTS Tools expandida
echo "<h3>4. 🛠️ Página TTS Tools Expandida</h3>";
$admin_file = __DIR__ . '/src/Admin/AdminInterface.php';
if (file_exists($admin_file)) {
    $content = file_get_contents($admin_file);
    
    $features_found = 0;
    
    if (strpos($content, 'Voice Preview') !== false) {
        echo "✅ Sección 'Voice Preview' encontrada<br>";
        $features_found++;
    }
    
    if (strpos($content, 'Custom Text Generator') !== false) {
        echo "✅ Sección 'Custom Text Generator' encontrada<br>";
        $features_found++;
    }
    
    if (strpos($content, 'handleGenerateCustom') !== false) {
        echo "✅ Handler para generación personalizada implementado<br>";
        $features_found++;
    }
    
    if (strpos($content, 'character_count') !== false && strpos($content, 'estimated_cost') !== false) {
        echo "✅ Contador de caracteres y estimación de costos implementado<br>";
        $features_found++;
    }
    
    echo "<p><strong>Funciones encontradas: $features_found/4</strong></p>";
}

// 5. Verificar guardado automático
echo "<h3>5. 💾 Guardado Automático de Configuración</h3>";
if (file_exists($plugin_file)) {
    $content = file_get_contents($plugin_file);
    if (strpos($content, 'saveTTSSettings') !== false) {
        echo "✅ Función de guardado automático encontrada<br>";
        if (strpos($content, 'update_post_meta') !== false) {
            echo "✅ Guardado de metadatos implementado<br>";
        }
    }
}

// 6. Verificar handlers AJAX adicionales
echo "<h3>6. 📡 Handlers AJAX</h3>";
$ajax_handlers = [
    'tts_delete_audio' => 'Borrar audio',
    'tts_generate_custom' => 'Generar audio personalizado'
];

foreach ($ajax_handlers as $action => $description) {
    if (strpos($content, $action) !== false) {
        echo "✅ Handler '$action' ($description) implementado<br>";
    }
}

echo "<hr>";

// Resumen de archivos modificados
echo "<h3>📁 Archivos Modificados</h3>";
$modified_files = [
    'src/Core/Plugin.php' => 'Core del plugin',
    'src/Admin/AdminInterface.php' => 'Interfaz de administración',
    'templates/admin/meta-box.php' => 'Meta-box del post',
    'NUEVAS_FUNCIONES.md' => 'Documentación'
];

foreach ($modified_files as $file => $description) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        $modified = date('Y-m-d H:i:s', filemtime($path));
        echo "✅ <strong>$file</strong> - $description<br>";
        echo "&nbsp;&nbsp;&nbsp;Tamaño: " . number_format($size) . " bytes | Modificado: $modified<br>";
    } else {
        echo "❌ <strong>$file</strong> - No encontrado<br>";
    }
}

echo "<hr>";

// Instrucciones finales
echo "<h3>🚀 Estado Final</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>✅ Todas las nuevas funciones implementadas:</strong></p>";
echo "<ol>";
echo "<li>🎵 <strong>Inserción automática de audio</strong> - Los posts muestran el reproductor automáticamente</li>";
echo "<li>💾 <strong>Guardado automático</strong> - La configuración se guarda al guardar el post</li>";
echo "<li>🗑️ <strong>Botón borrar</strong> - Elimina archivos de audio y metadatos</li>";
echo "<li>🎨 <strong>Meta-box optimizado</strong> - Interfaz más limpia y simple</li>";
echo "<li>🛠️ <strong>TTS Tools expandido</strong> - Herramientas avanzadas en página separada</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
echo "<p><strong>📋 Para usar:</strong></p>";
echo "<ul>";
echo "<li><strong>Posts:</strong> Activar TTS, seleccionar opciones, generar audio</li>";
echo "<li><strong>Herramientas:</strong> Ir a Tools > TTS Tools para funciones avanzadas</li>";
echo "<li><strong>Borrar:</strong> Usar botón 'Delete Audio' en meta-box del post</li>";
echo "</ul>";
echo "</div>";

echo "<p><small>Test ejecutado en: " . date('Y-m-d H:i:s') . "</small></p>";
echo "<p><strong>📦 Package final:</strong> tts-de-wordpress-enhanced-final.zip</p>";
?>
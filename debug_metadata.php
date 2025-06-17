<?php
/**
 * Script de debug para verificar el flujo de metadatos de TTS
 * 
 * Ejecutar en WordPress admin para verificar si los metadatos se están guardando correctamente
 */

// Asegurar que solo se ejecute en admin
if (!is_admin()) {
    wp_die('Este script solo puede ejecutarse desde el admin de WordPress');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar este script');
}

echo "<h2>🔍 Debug de Metadatos TTS</h2>";

// Función para verificar un post específico
function debug_post_metadata($post_id) {
    echo "<h3>📝 Post ID: $post_id</h3>";
    
    // Obtener todos los metadatos TTS
    $metadata = [
        '_tts_enabled' => get_post_meta($post_id, '_tts_enabled', true),
        '_tts_audio_url' => get_post_meta($post_id, '_tts_audio_url', true),
        '_tts_voice_provider' => get_post_meta($post_id, '_tts_voice_provider', true),
        '_tts_voice_id' => get_post_meta($post_id, '_tts_voice_id', true),
        '_tts_generated_at' => get_post_meta($post_id, '_tts_generated_at', true),
        '_tts_generation_status' => get_post_meta($post_id, '_tts_generation_status', true),
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Meta Key</th><th>Value</th><th>Status</th></tr>";
    
    foreach ($metadata as $key => $value) {
        $status = !empty($value) ? '✅ Set' : '❌ Empty';
        $display_value = empty($value) ? '<em>empty</em>' : $value;
        
        if ($key === '_tts_generated_at' && !empty($value)) {
            $display_value .= ' (' . date('Y-m-d H:i:s', $value) . ')';
        }
        
        echo "<tr>";
        echo "<td><strong>$key</strong></td>";
        echo "<td>$display_value</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar si el archivo de audio existe
    $audio_url = $metadata['_tts_audio_url'];
    if (!empty($audio_url)) {
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $audio_url);
        $file_exists = file_exists($file_path);
        echo "<p><strong>Audio File:</strong> " . ($file_exists ? '✅ Exists' : '❌ Missing') . " ($file_path)</p>";
        
        if ($file_exists) {
            $file_size = size_format(filesize($file_path));
            echo "<p><strong>File Size:</strong> $file_size</p>";
        }
    }
    
    return $metadata;
}

// Buscar posts con audio TTS
$posts_with_tts = get_posts([
    'meta_key' => '_tts_audio_url',
    'meta_compare' => '!=',
    'meta_value' => '',
    'post_type' => ['post', 'page'],
    'post_status' => 'any',
    'numberposts' => 10
]);

if (empty($posts_with_tts)) {
    echo "<p>❌ <strong>No se encontraron posts con audio TTS generado</strong></p>";
    echo "<p>💡 <strong>Sugerencias:</strong></p>";
    echo "<ul>";
    echo "<li>Genera audio para un post desde el meta-box</li>";
    echo "<li>Verifica que la generación se complete sin errores</li>";
    echo "<li>Revisa los logs de WordPress</li>";
    echo "</ul>";
} else {
    echo "<p>✅ <strong>Encontrados " . count($posts_with_tts) . " posts con audio TTS</strong></p>";
    
    foreach ($posts_with_tts as $post) {
        echo "<h3>📄 " . get_the_title($post->ID) . " (ID: {$post->ID})</h3>";
        $metadata = debug_post_metadata($post->ID);
        
        // Análisis específico
        $has_provider = !empty($metadata['_tts_voice_provider']);
        $has_voice = !empty($metadata['_tts_voice_id']);
        
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Análisis:</strong><br>";
        
        if ($has_provider && $has_voice) {
            echo "✅ <strong>Provider y Voice están guardados correctamente</strong><br>";
            echo "📊 Provider: <code>{$metadata['_tts_voice_provider']}</code><br>";
            echo "🎤 Voice: <code>{$metadata['_tts_voice_id']}</code><br>";
            echo "💡 Si no se muestran en el meta-box, puede ser un problema de template o cache";
        } elseif ($has_provider && !$has_voice) {
            echo "⚠️ <strong>Provider guardado pero Voice falta</strong><br>";
            echo "📊 Provider: <code>{$metadata['_tts_voice_provider']}</code><br>";
            echo "💡 Posible problema: Voice no se está pasando correctamente en la generación";
        } elseif (!$has_provider && $has_voice) {
            echo "⚠️ <strong>Voice guardado pero Provider falta</strong><br>";
            echo "🎤 Voice: <code>{$metadata['_tts_voice_id']}</code><br>";
            echo "💡 Posible problema: Provider no se está pasando correctamente en la generación";
        } else {
            echo "❌ <strong>Ni Provider ni Voice están guardados</strong><br>";
            echo "💡 Posible problema: Los metadatos no se están guardando en generateAudioForPost()";
        }
        echo "</div>";
        
        echo "<hr>";
    }
}

// Query SQL para verificar directamente en la base de datos
echo "<h3>🗃️ Verificación Directa en Base de Datos</h3>";
echo "<p>Ejecuta esta query en phpMyAdmin o similar para verificar los metadatos:</p>";
$table_prefix = $GLOBALS['wpdb']->prefix;
echo "<code style='background: #f0f0f0; padding: 10px; display: block; font-family: monospace;'>";
echo "SELECT p.ID, p.post_title,<br>";
echo "&nbsp;&nbsp;MAX(CASE WHEN pm.meta_key = '_tts_voice_provider' THEN pm.meta_value END) as provider,<br>";
echo "&nbsp;&nbsp;MAX(CASE WHEN pm.meta_key = '_tts_voice_id' THEN pm.meta_value END) as voice,<br>";
echo "&nbsp;&nbsp;MAX(CASE WHEN pm.meta_key = '_tts_audio_url' THEN pm.meta_value END) as audio_url<br>";
echo "FROM {$table_prefix}posts p<br>";
echo "JOIN {$table_prefix}postmeta pm ON p.ID = pm.post_id<br>";
echo "WHERE pm.meta_key IN ('_tts_voice_provider', '_tts_voice_id', '_tts_audio_url')<br>";
echo "GROUP BY p.ID, p.post_title<br>";
echo "HAVING audio_url IS NOT NULL<br>";
echo "ORDER BY p.ID DESC;";
echo "</code>";

echo "<h3>🔧 Acciones Recomendadas</h3>";
echo "<ul>";
echo "<li><strong>Si los metadatos NO están guardados:</strong> Verificar TTSService.php líneas 394-409</li>";
echo "<li><strong>Si los metadatos SÍ están guardados:</strong> Verificar meta-box.php líneas 117-130</li>";
echo "<li><strong>Regenerar audio:</strong> Eliminar audio existente y generar nuevo para probar</li>";
echo "<li><strong>Verificar logs:</strong> Revisar wp-content/debug.log para errores TTS</li>";
echo "<li><strong>Limpiar cache:</strong> Si usas cache, limpiarlo completamente</li>";
echo "</ul>";

echo "<style>";
echo "table { margin: 10px 0; }";
echo "th, td { padding: 8px; text-align: left; }";
echo "th { background-color: #f2f2f2; }";
echo "code { background: #f0f0f0; padding: 2px 4px; font-family: monospace; }";
echo "</style>";
?>
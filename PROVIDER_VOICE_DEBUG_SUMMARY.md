# Provider/Voice Configuration Debug Summary

## Problema Reportado
El usuario reportó: "por ahora lo que no está haciendo bien es 'respetar' el TTS y Voz seleccionados. Es decir siempre toma el TTS y Voces default definidas en TTS Settings"

## Análisis del Problema
Los logs muestran que los valores de provider y voice están llegando vacíos:
```
[WP_TTS INFO] Using unified metadata system
    post_id => 635
    provider => 
    voice => 
```

## Depuración Implementada

### 1. Enhanced Plugin.php Logging
**Archivo**: `src/Core/Plugin.php:315-331`
- Agregado logging detallado para `setTTSEnabled` y `setVoiceConfig`
- Verificación inmediata de datos guardados
- Tracking de resultados de operaciones

### 2. TTSMetaManager Debug Logging
**Archivo**: `src/Utils/TTSMetaManager.php:94-130`
- Logging completo del proceso `saveTTSData`
- Verificación de datos antes y después de guardar
- Error handling mejorado

### 3. TTSService Voice Config Logging
**Archivo**: `src/Services/TTSService.php:411-424`
- Logging de `voice_config` raw desde TTSMetaManager
- Tracking del flujo de datos desde metadata a opciones

## Archivos de Debug Creados

1. **debug_voice_config.php** - Test específico de configuración de voz
2. **debug_form_save.php** - Simulación del proceso de guardado del meta box

## Próximos Pasos para el Usuario

### 1. Instalar ZIP con Debug
Usar el archivo: `tts-de-wordpress-debug-final.zip` (293K)

### 2. Reproducir el Problema
1. Ir al editor de un post
2. En el meta box de TTS, seleccionar:
   - Provider: Azure TTS (o cualquier provider)
   - Voice: Una voz específica
3. Guardar el post
4. Generar audio

### 3. Revisar Logs
Buscar en `debug.log` las siguientes líneas:

#### Al Guardar (Plugin.php):
```
[Plugin] Saving TTS settings for post X
[Plugin] tts_voice_provider: [valor]
[Plugin] setVoiceConfig result: SUCCESS/FAILED
[Plugin] Verification - saved voice config: [array]
```

#### Al Generar Audio (TTSService.php):
```
[WP_TTS INFO] Raw voice_config from TTSMetaManager
[WP_TTS INFO] Using unified metadata system
```

### 4. Ejecutar Scripts de Debug (Opcional)
1. Acceder vía web a `debug_voice_config.php`
2. Acceder vía web a `debug_form_save.php`

## Posibles Causas Identificadas

1. **Meta Box Form Data**: Los datos del formulario no se están enviando correctamente
2. **TTSMetaManager Save**: Fallo en el guardado de la configuración de voz
3. **Data Retrieval**: Problema al leer la configuración guardada
4. **Override**: Los datos se guardan pero se sobrescriben por defaults

## Correcciones Pendientes

Una vez identificado el punto exacto del fallo, se implementarán las siguientes correcciones:

1. **Form Validation**: Validar que los datos del formulario se envíen correctamente
2. **Save Process**: Corregir el proceso de guardado si está fallando
3. **Data Persistence**: Asegurar que los datos persistan correctamente
4. **Fallback Logic**: Mejorar la lógica de fallback a defaults

## Información del ZIP
- **Archivo**: `tts-de-wordpress-debug-final.zip`
- **Tamaño**: 293K
- **Contenido**: Plugin completo con logging mejorado
- **Excluye**: .git, vendor, archivos de debug, tests
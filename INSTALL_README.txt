=== WordPress Text-to-Speech Plugin ===
Contributors: Eduardo Llaguno
Tags: text-to-speech, tts, audio, accessibility, spanish
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later

Convert WordPress articles to audio using multiple TTS providers with cost optimization and Spanish language focus.

== Descripción ==

Este plugin convierte automáticamente el contenido de tus posts y páginas de WordPress en audio utilizando múltiples proveedores de Text-to-Speech (TTS) como Google Cloud TTS, Amazon Polly, Azure TTS, OpenAI TTS y ElevenLabs.

== Características principales ==

* Soporte para múltiples proveedores TTS
* Optimización de costos con sistema round-robin
* Enfoque en idioma español (México y España)
* Sistema de caché inteligente
* Interfaz de administración completa
* Generación de audio en modo mock para pruebas
* Almacenamiento local y en la nube

== Instalación ==

1. Descarga el archivo wp-tts-plugin.zip
2. Ve a WordPress Admin > Plugins > Añadir nuevo
3. Haz clic en "Subir plugin"
4. Selecciona el archivo wp-tts-plugin.zip
5. Haz clic en "Instalar ahora"
6. Activa el plugin

== Configuración básica ==

1. Ve a Ajustes > TTS Settings para configurar los proveedores
2. Ve a Herramientas > TTS Tools para probar la generación de audio
3. Configura las API keys de los proveedores que desees usar
4. El plugin funcionará en modo mock si no hay proveedores configurados

== Proveedores soportados ==

* Google Cloud Text-to-Speech
* Amazon Polly
* Microsoft Azure TTS
* OpenAI TTS
* ElevenLabs

== Uso ==

1. **Modo automático**: El plugin puede agregar automáticamente reproductores de audio a tus posts
2. **Modo manual**: Usa el shortcode [wp_tts_player] en cualquier post o página
3. **Herramientas de prueba**: Ve a Herramientas > TTS Tools para generar audio de prueba

== Notas importantes ==

* El plugin incluye un sistema de archivos mock para pruebas
* Los proveedores requieren configuración de API keys para funcionar
* El sistema de caché mejora el rendimiento y reduce costos
* Compatible con posts y páginas de WordPress

== Soporte ==

Para soporte técnico y reportar errores:
* GitHub: https://github.com/ellaguno/tts_de_wordpress
* Email: [tu-email]

== Changelog ==

= 1.0.0 =
* Versión inicial del plugin
* Soporte para múltiples proveedores TTS
* Sistema de caché y optimización de costos
* Interfaz de administración completa
* Modo mock para pruebas

== Desarrollador ==

Eduardo Llaguno - https://sesolibre.com

== Licencia ==

Este plugin está licenciado bajo GPL v2 o posterior.
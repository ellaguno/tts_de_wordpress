# WordPress TTS Plugin

Un plugin avanzado de Text-to-Speech (TTS) para WordPress que soporta múltiples proveedores de TTS y almacenamiento en la nube.

## Características

- **Múltiples Proveedores TTS**: Amazon Polly, Azure TTS, Google Cloud TTS, OpenAI TTS, ElevenLabs
- **Almacenamiento en la Nube**: Buzzsprout para hosting de archivos de audio
- **Sistema Round-Robin**: Distribución automática de carga entre proveedores
- **Cache Inteligente**: Sistema de cache para optimizar rendimiento
- **Interfaz de Administración**: Panel de control completo en WordPress
- **Modo Mock**: Funcionalidad de prueba sin necesidad de credenciales API

## Instalación

1. Descarga el plugin desde este repositorio
2. Sube el archivo ZIP a tu WordPress en `Plugins > Añadir nuevo > Subir plugin`
3. Activa el plugin desde el panel de administración
4. Configura los proveedores TTS en `Configuración > TTS Settings`

## Configuración

### Proveedores TTS Soportados

#### Amazon Polly
- AWS Access Key ID
- AWS Secret Access Key
- Región AWS
- Selección de voz

#### Azure TTS
- Subscription Key
- Región del servicio
- Selección de voz

#### Google Cloud TTS
- Service Account JSON
- Selección de voz

#### OpenAI TTS
- API Key
- Modelo de voz
- Selección de voz

#### ElevenLabs
- API Key
- Selección de voz

### Almacenamiento

#### Buzzsprout
- API Token
- Podcast ID

## Uso

### Generación Automática
El plugin puede generar automáticamente archivos de audio TTS para:
- Entradas de blog
- Páginas
- Contenido personalizado

### Shortcodes
```php
[tts_audio text="Tu texto aquí"]
```

### API Programática
```php
$tts_service = wp_tts_get_service();
$audio_url = $tts_service->generateSpeech('Tu texto aquí');
```

## Arquitectura

### Estructura del Proyecto
```
src/
├── Admin/              # Interfaz de administración
├── Core/               # Núcleo del plugin
├── Exceptions/         # Manejo de excepciones
├── Interfaces/         # Interfaces del sistema
├── Providers/          # Proveedores TTS y almacenamiento
├── Services/           # Servicios principales
└── Utils/              # Utilidades
```

### Componentes Principales

- **TTSService**: Servicio principal de TTS
- **RoundRobinManager**: Gestión de proveedores
- **CacheService**: Sistema de cache
- **AdminInterface**: Panel de administración
- **SecurityManager**: Gestión de seguridad

## Desarrollo

### Requisitos
- PHP 7.4+
- WordPress 5.0+
- Composer

### Instalación para Desarrollo
```bash
git clone https://github.com/ellaguno/tts_de_wordpress.git
cd tts_de_wordpress
composer install
```

### Testing
El plugin incluye modo mock para testing sin credenciales API reales.

## Contribución

1. Fork el repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-caracteristica`)
3. Commit tus cambios (`git commit -am 'Añade nueva característica'`)
4. Push a la rama (`git push origin feature/nueva-caracteristica`)
5. Crea un Pull Request

## Licencia

Este proyecto está licenciado bajo la Licencia GPL v2 o posterior.

## Soporte

Para soporte y reportes de bugs, por favor abre un issue en este repositorio.

## Changelog

### v1.0.0
- Implementación inicial con soporte para 5 proveedores TTS
- Sistema round-robin
- Interfaz de administración completa
- Modo mock para testing
- Sistema de cache
- Integración con Buzzsprout
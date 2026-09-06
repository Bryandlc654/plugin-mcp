# MCP Connect for WordPress

**Desarrollado por [Next Boost Peru](http://nextboost.business/)**

Convierte cualquier instalación de WordPress en un **servidor MCP** (Model Context Protocol) con autenticación **OAuth 2.1**, listo para conectarse a ChatGPT, Claude, Claude Code, Cursor y otros agentes de IA.

> El código fuente se encuentra en `mcp-connect-wp/`. La documentación técnica completa está en [docs/](docs/) y el diseño en [ARCHITECTURE.md](ARCHITECTURE.md).

## Qué hace

- Publica un endpoint MCP con transporte **Streamable HTTP** (una sola POST, JSON-RPC 2.0).
- Autenticación OAuth 2.1 introducida de cero: **Dynamic Client Registration** (RFC 7591), **Authorization Code + PKCE (S256)**, tokens de refresco con rotación y detección de reutilización, revocación, `resource`/audiencia (RFC 8707).
- Descubrimiento automático: metadatos de recurso protegido (**RFC 9728**) y de servidor de autorización (**RFC 8414**) en `/.well-known/`.
- **27 herramientas MCP** sobre contenido de WordPress (posts, páginas, medios, comentarios, taxonomías, usuario y sitio).
- Permisos granulares por *scopio* (`categoria:modo`) gestionados desde el panel.
- Los agentes actúan con las capacidades de tu usuario de WordPress y solo sobre TU contenido (a menos que tu rol permita más).
- Sin API keys, sin servicios externos, sin dependencias de terceros.

## Requisitos

- WordPress 6.0+, PHP 7.4+.
- El sitio **debe** servirse por **HTTPS** (los navegadores bloquean OAuth sin él).
- Enlaces permanentes distintos de "Simple".

## Instalación

```bash
# 1. Copia la carpeta al plugin dir
cp -r mcp-connect-wp /path/to/wp-content/plugins/

# 2. Activa en el panel: Plugins → MCP Connect for WordPress
# 3. Ajustes → MCP Connect for WordPress → revisa Permisos y copia la "URL del servidor MCP"
```

## Conectar un agente

Pega la URL del servidor MCP (`https://tusitio.com/wp-json/mcp-connect-wp/v1/mcp`) como URL de servidor MCP en tu cliente.

**Claude Code** (también mostrado en el panel):

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://tusitio.com/wp-json/mcp-connect-wp/v1/mcp"
    }
  }
}
```

Flujo al conectar:

1. El cliente se registra dinámicamente (DCR).
2. Pantalla de consentimiento en tu WordPress (usuario ya autenticado).
3. Autoriza → emisión de code + tokens (PKCE, S256).
4. El agente pide `initialize`, ve `tools/list` y ejecuta `tools/call`.

## Estructura

```
mcp-connect-wp/
  mcp-connect-wp.php              Bootstrap, constantes, autoloader
  uninstall.php             Desinstalación (borrado opcional)
  admin/                    Panel: vistas, CSS, JS, pantalla de consentimiento
  includes/
    class-plugin.php        Contenedor DI
    class-install.php       Tablas, activación/desactivación
    class-mcp-router.php    Transporte: rutas REST, well-known, CORS
    class-mcp-server.php    JSON-RPC / inicialización / herramientas
    class-mcp-tools.php     Registro de herramientas
    class-oauth-server.php  Consentimiento, token, revocación
    class-oauth-client-registration.php  DCR + clientes
    class-token-store.php   Almacenamiento de tokens/códigos/clientes
    ... (crypto, settings, url, permissions, logger, etc.)
  tools/                    Definiciones de las 27 herramientas
  languages/                Pot + es_ES
tests/                      Pruebas unitarias (sin WordPress)
docs/                       Documentación técnica
```

## Documentación

| Documento | Contenido |
|---|---|
| [docs/PROTOCOL.md](docs/PROTOCOL.md) | Transporte MCP, mensajes JSON-RPC, versiones soportadas |
| [docs/OAUTH.md](docs/OAUTH.md) | Flujo OAuth 2.1 completo y endpoints |
| [docs/SECURITY.md](docs/SECURITY.md) | Modelo de amenazas y mitigaciones |
| [docs/TOOLS.md](docs/TOOLS.md) | Catálogo de herramientas y permisos |
| [MANUAL_TESTING.md](MANUAL_TESTING.md) | Guía de pruebas manuales en un WordPress real |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Diseño y decisiones |

## Probar

```bash
# Sintaxis de todos los archivos PHP
php -l $(find mcp-connect-wp -name '*.php') > /dev/null

# Pruebas unitarias (sin WordPress)
php tests/run.php
```

## Licencia

GPL-2.0-or-later. Ver [mcp-connect-wp/readme.txt](mcp-connect-wp/readme.txt).
# plugin-mcp

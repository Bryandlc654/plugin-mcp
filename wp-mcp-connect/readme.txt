=== WP MCP Connect ===
Contributors: wp-mcp-connect
Tags: mcp, ai, chatgpt, claude, cursor, oauth
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convierte tu WordPress en un servidor MCP (Model Context Protocol) con autenticación OAuth 2.1 para conectarlo a ChatGPT, Claude, Claude Code, Cursor y otros agentes de IA.

== Description ==

WP MCP Connect transforma cualquier instalación de WordPress en un servidor **MCP** (Model Context Protocol) compatible con el transporte **Streamable HTTP**, protegido con **OAuth 2.1** y **PKCE**. Copia una URL en tu agente de IA y este podrá leer y gestionar tu contenido usando solo los permisos que autorices.

**Qué hace**

* Publica un endpoint MCP (Streamable HTTP) listo para usar con ChatGPT, Claude, Claude Code, Cursor y cualquier cliente MCP con OAuth.
* Autenticación OAuth 2.1 completa: Dynamic Client Registration (RFC 7591), Authorization Code + PKCE (S256), tokens de refresco con rotación y detección de reuso, revocación.
* Descubrimiento automático: RFC 9728 (protected resource metadata) y RFC 8414 (authorization server metadata) en `/.well-known/`.
* 27 herramientas MCP: publicaciones, páginas, medios, comentarios, categorías, etiquetas, usuario y sitio.
* Sistema de permisos por scopios (`categoria:modo`) gestionado desde Ajustes → WP MCP Connect.
* Los agentes actúan únicamente con las capacidades de tu usuario de WordPress y sobre TU contenido (o global, si tu rol lo permite).
* Panel de administración: permisos, conexiones autorizadas, registro de actividad y diagnóstico.
* Sin API keys. Sin dependencias de servicios externos. Los datos nunca salen de tu servidor salvo hacia el agente que autorices.

**Requisitos**

* WordPress 6.0+.
* PHP 7.4+ (recomendado 8.0+).
* El sitio debe servirse por **HTTPS**.
* Estructura de enlaces permanentes distinta de "Simple".

**Seguridad**

* Tokens almacenados solo con hash SHA-256; los códigos son de un solo uso y expiran en 10 minutos.
* Tokens de acceso de 1 hora, tokens de refresco de 30 días con rotación.
* Validación de `redirect_uri`, verificación de PKCE y de origen (Origin) contra DNS rebinding.
* Logs sin tokens ni secretos. Endpoints OAuth con limitación de tasa.
* Los scopios destructivos (borrado) están desactivados por defecto y ocultan las herramientas destructivas del agente.

== Installation ==

1. Sube la carpeta `wp-mcp-connect` a `/wp-content/plugins/` (o instala el ZIP desde Plugins → Añadir nuevo → Subir plugin).
2. Activa el plugin desde el menú Plugins.
3. Ve a Ajustes → **WP MCP Connect**.
4. Comprueba el estado en la pestaña **Diagnóstico** (HTTPS, enlaces permanentes, tablas).
5. Revisa los **Permisos** que pueden solicitar los agentes y guárdalos.
6. Copia la **URL del servidor MCP** de la pestaña **Conectar** y pégala en tu agente de IA.

**Conectar con ChatGPT, Claude o Cursor**

Pega la URL del servidor MCP directamente en el cliente. Al conectar:

1. El cliente se registra dinámicamente (OAuth 2.1 Dynamic Client Registration).
2. Se abre la pantalla de consentimiento (con tu usuario de WordPress conectado).
3. Autoriza o cancela; se emiten el código y los tokens.
4. El agente ya puede usar las herramientas permitidas.

Para Claude Code también puedes usar la configuración que muestra la pestaña "Conectar".

Alternativamente, puede conectarse cualquier cliente MCP con OAuth 2.1 estándar usando el descubrimiento automático.

== Frequently Asked Questions ==

= ¿Necesito una API key? =
No. La autenticación es OAuth 2.1 con el propio WordPress como servidor de autorización. No hay terceros.

= ¿Qué permisos necesita el agente? =
Los que se marquen en Ajustes → WP MCP Connect → Permisos y, además, los que tu usuario de WordPress tenga. Por defecto la lectura y escritura están activas y el borrado desactivado.

= ¿Puede un agente actuar sobre contenido de otros usuarios? =
Solo si tu usuario de WordPress tiene las capacidades correspondientes (p. ej. `edit_others_posts`). En caso contrario, el agente solo verá/modificará tu propio contenido.

= ¿Funciona con https://, subdirectorios o detrás de un proxy? =
Sí. Se soportan instalaciones en la raíz, en subdirectorio (`/es/`, `/wordpress/`) y detrás de proxy reverso. Las cabeceras de proxy (`X-Forwarded-*`) se ignoran salvo que se activen explícitamente en los ajustes.

= ¿Qué pasa si desactivo el soporte de borrado? =
Las herramientas destructivas desaparecen de la lista de herramientas del agente, de modo que este ni siquiera las ve.

= ¿Dónde aparecen las conexiones? =
En Ajustes → WP MCP Connect → Conexiones autorizadas puedes ver qué clientes se han conectado, con qué usuario y con qué permisos, y revocarlas en cualquier momento.

== Screenshots ==

1. Pestaña Conectar: URL del servidor MCP y configuración de Claude Code.
2. Permisos disponibles para los agentes.
3. Conexiones autorizadas y clientes registrados.
4. Logs y diagnóstico.

== Changelog ==

= 1.0.0 =
* Primera versión: servidor MCP (Streamable HTTP) + OAuth 2.1 completo + 27 herramientas + panel de administración + i18n es_ES/en.
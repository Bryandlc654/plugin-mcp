# Protocolo MCP del plugin

Este documento describe cómo el plugin expone el Model Context Protocol.

## Transporte

- **Streamable HTTP** (según especificación MCP 2025-11-25).
- Un único `POST` JSON-RPC 2.0 por petición sobre el endpoint:

```
https://<site>/wp-json/mcp-connect/v1/mcp
```

(La raíz `https://<site>/wp-json/mcp-connect/v1` también responde como heredado, pero la URL canónica es la de `/mcp`).

- Respuestas `application/json` directas (no se usa SSE).
- `GET` al endpoint → `405` con cabecera `Allow: POST`.
- El servidor es **sin estado** (stateless): no emite ni exige `Mcp-Session-Id`.
- La cabecera `MCP-Protocol-Version` es opcional; la versión se negocia en `initialize`.

## Versiones del protocolo

Se negocia entre: `2025-03-26`, `2025-06-18`, `2025-11-25`. No se soporta `2026-07-28`.

En un `initialize`, el servidor devuelve en `serverInfo.protocolVersion` la versión solicitada si está soportada; en caso contrario (versión no soportada o ausente) se usa `2025-11-25`.

## Mensajes soportados

| Método | Campos | Descripción |
|---|---|---|
| `initialize` | `protocolVersion`, `capabilities`, `clientInfo` | Negocia versión y capacidades |
| `tools/list` | — | Lista las herramientas visibles según los scopios concedidos |
| `tools/call` | `name`, `arguments` | Ejecuta una herramienta (validación JSON Schema) |
| `ping` | — | Comprueba vida |
| `notifications/initialized` | — | Notificación, sin respuesta |

Peticiones sin `id` se tratan como notificaciones y **no reciben respuesta** (HTTP 202 vacío). Métodos desconocidos con `id` → error `-32601`.

## Errores JSON-RPC

| Código | Descripción |
|---|---|
| `-32700` | Parse error |
| `-32600` | Invalid request |
| `-32601` | Method not found |
| `-32602` | Invalid params (validación de schema fallida) |
| `-32603` | Internal error |
| `2002` | Disabled (el plugin/endpoint está desactivado) |
| `2003` | Forbidden (origen no permitido, token inválido…) |
| tool errors | Errores de la herramienta (`not_found`, `update_failed`…) |

## Autenticación

Las peticiones MCP requieren `Authorization: Bearer <access_token>`. Sin token válido:

```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer resource_metadata="...", authorization_server="...", scope="..."
```

El cliente sigue el flujo OAuth 2.1 descrito en [OAUTH.md](OAUTH.md).

## Origen (CORS)

Si llega la cabecera `Origin` no permitida, la petición se rechaza con `403` (mitigación de DNS rebinding). Los orígenes permitidos son el del propio sitio y los configurados en el panel.
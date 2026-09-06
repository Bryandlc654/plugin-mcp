# OAuth 2.1 del plugin

El plugin implementa OAuth 2.1 como servidor de autorización (AS) y como recurso protegido.

## Endpoints

| Endpoint | Ruta |
|---|---|
| MCP (recurso protegido) | `https://<site>/wp-json/wp-mcp-connect/v1/mcp` |
| Consentimiento | `https://<site>/wp-json/wp-mcp-connect/v1/oauth/authorize` |
| Token | `https://<site>/wp-json/wp-mcp-connect/v1/oauth/token` |
| Registro dinámico (DCR) | `https://<site>/wp-json/wp-mcp-connect/v1/oauth/register` |
| Revocación | `https://<site>/wp-json/wp-mcp-connect/v1/oauth/revoke` |
| Protected resource metadata | `/.well-known/oauth-protected-resource` |
| Authorization server metadata | `/.well-known/oauth-authorization-server` |
| Alias de descubrimiento (pretty) | `https://<site>/wp-json/wp-mcp-connect/v1/.well-known/*` |

El `issuer` es `https://<site>/` (misma raíz para `/.well-known/`).

## Descubrimiento

Un cliente que recibe el reto `401`:

1. Obtiene la metadata del **recurso protegido** (RFC 9728) → `authorization_servers: [issuer]`.
2. Obtiene la metadata del **servidor de autorización** (RFC 8414) desde el `issuer`.
3. Si no tiene `client_id`, hace **Dynamic Client Registration** (RFC 7591) en `registration_endpoint`.
4. Ejecuta el flujo **Authorization Code + PKCE (S256)**, indicando el recurso con `resource=<URL del MCP>` (RFC 8707).
5. Intercambia el código por *access token* + *refresh token*.
6. Llama al MCP con `Authorization: Bearer`.

## Detalles del flujo

- `response_type=code` (único permitido).
- `grant_types`: `authorization_code` y `refresh_token` (únicos permitidos).
- PKCE **obligatorio**, solo `S256`.
- `state` validado y devuelto al consentimiento y al redirect.
- `redirect_uri`:
  - `https://` o identificadores de esquema de aplicaciones como `cursor://` o `http://localhost` / `http://127.0.0.1` (loopback, cualquier puerto).
  - Normalización loopback (`localhost` ↔ `127.0.0.1`) y validación exacta contra el registro del cliente.
  - Idealmente el `redirect_uri` debe venir descrito en la metadata del cliente (`client_id` como URL) o en el `redirect_uris` del registro dinámico.
- Cookies eludidas (endpoints solo con bearer).
- En la pantalla de consentimiento se muestra quién solicita, con qué permisos y el `redirect_uri`.

## Consentimiento

- `GET /oauth/authorize` → formulario HTML con nonce; la acción `POST` vuelve al mismo endpoint.
- Requiere el usuario de WordPress ya autenticado (acceso a `wp-admin`).
- Aprobado → se genera un **código de un solo uso** (hash SHA-256) con 10 min de vida ligado a `code_challenge`, `redirect_uri`, `scope` y `resource`.
- Denegado → `error=access_denied` al `redirect_uri`.

## Token endpoint

- Autenticación del cliente: secreto compartido (`client_secret_post`/`client_secret_basic`) si el cliente tiene secreto, o `none` si es público (S256 obligatorio).
- `access_token`: 1 hora.
- `refresh_token`: 30 días, **rotación en cada uso**; detecta reutilización de un token ya rotado y **revoca toda la familia**.
- Los tokens se guardan **solo como hash SHA-256**; nunca en claro.
- Respuestas con `iss` y `Content-Type: application/json`, `Cache-Control: no-store`.

## Revocación

`POST /oauth/revoke` con `token` (exige cliente autenticado, RFC 7009).

## Parseo de bodies

`/oauth/token` y `/oauth/register` aceptan `application/x-www-form-urlencoded` y `application/json`; el body se parsea con `parse_str()`/`json_decode` tras límite de tamaño y validación de `Content-Type`. El consentimiento (`/oauth/authorize`) acepta formularios (`application/x-www-form-urlencoded`).
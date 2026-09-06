# Pruebas manuales de `mcp-connect`

Estas pruebas requieren un WordPress real (local o de desarrollo) con el plugin activo, HTTPS y enlaces permanentes activados.

## Preparación

1. Instala y activa el plugin.
2. Ajustes → MCP Connect → Diagnóstico: deben pasar HTTPS, enlaces permanentes y las 6 tablas.
3. Pestaña Permisos: deja lectura/escritura activas; deja borrado desactivado.

## 1. Descubrimiento (`/.well-known`)

```bash
curl -s https://TU-SITIO/.well-known/oauth-protected-resource
curl -s https://TU-SITIO/.well-known/oauth-authorization-server
```

Comprueba: ambos devuelven JSON válido con `authorization_servers` / `issuer`, `registration_endpoint`, `token_endpoint`, `code_challenge_methods_supported: ["S256"]`.

Si estás en un subdirectorio (`/es/`), prueba también:
`https://TU-SITIO/es/wp-json/mcp-connect/v1/.well-known/oauth-protected-resource`.

## 2. Reto 401 del MCP

```bash
curl -i -X POST https://TU-SITIO/wp-json/mcp-connect/v1/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

Debe devolver `401` con `WWW-Authenticate: Bearer resource_metadata="...", authorization_server="...", scope="..."`.

`GET` al mismo endpoint → `405` con `Allow: POST`.

## 3. Registro dinámico (DCR)

```bash
curl -s -X POST https://TU-SITIO/wp-json/mcp-connect/v1/oauth/register \
  -H 'Content-Type: application/json' \
  -d '{"client_name":"Prueba","redirect_uris":["http://localhost:9876/cb"],"token_endpoint_auth_method":"none","grant_types":["authorization_code","refresh_token"],"response_types":["code"]}'
```

Guarda `client_id` (+ `client_secret` si lo devuelve). Repite el alta dos veces rápido y comprueba que el rate limiter bloquea (429).

## 4. Flujo de consentimiento (navegador)

1. Con sesión de administrador iniciada: `GET /wp-json/mcp-connect/v1/oauth/authorize?response_type=code&client_id=<id>&redirect_uri=<encoded>&scope=posts:read&state=abc&code_challenge=<48b64>&code_challenge_method=S256&resource=<encoded MCP URL>`.
2. Debe verse la pantalla de consentimiento con nombre de cliente, usuario, permisos y redirect_uri.
3. Autoriza → redirige a `redirect_uri` con `code` y `state=abc`.
4. Cancelar → redirige con `error=access_denied`.

`code_challenge` = base64url(SHA256(verifier)). Puedes generarlo con:

```bash
php -r "echo rtrim(strtr(base64_encode(hash('sha256','verifier-secreto',true)),'+/','-_'),'=');"
```

## 5. Token

```bash
curl -s -X POST https://TU-SITIO/wp-json/mcp-connect/v1/oauth/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode grant_type=authorization_code \
  --data-urlencode code=<CODIGO> \
  --data-urlencode client_id=<id> \
  --data-urlencode redirect_uri=<REDIRECT> \
  --data-urlencode code_verifier=verifier-secreto \
  --data-urlencode resource=https://TU-SITIO/wp-json/mcp-connect/v1/mcp
```

Comprueba: `access_token`, `refresh_token`, `token_type: Bearer`, `expires_in`, `scope`.

- Repite con el mismo `code` → error `invalid_grant` (uso único).
- PKCE incorrecto → `invalid_grant`.

## 6. Llamada MCP autenticada

```bash
curl -s -X POST https://TU-SITIO/wp-json/mcp-connect/v1/mcp \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <ACCESS>' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"x","version":"1"}}}'

curl -s ... tools/list
curl -s ... -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"wp_list_posts","arguments":{"number":5}}}'
```

Verifica: herramientas destructivas ausentes; `wp_list_posts` devuelve solo posts publicados (o propios según rol).

## 7. Refresh con rotación

Intercambia el refresh por uno nuevo y reutiliza el antiguo → la familia debe quedar revocada (los `access_token` dejan de funcionar).

## 8. Revocación (RFC 7009)

```bash
curl -s -X POST .../oauth/revoke -H 'Content-Type: application/json' \
  -d '{"token":"<REFRESH>","client_id":"<id>"}'
```

Posteriormente el refresh falla. La conexión desaparece del panel (Conexiones autorizadas).

## 9. Panel de administración

- Conectar: botón "Probar conexión" → OK; copiar URL.
- Permisos: desactiva `delete` por completo y vuelve a pedir `tools/list` → las 6 herramientas de borrado desaparecen.
- Conexiones: revocar una autorización → los tokens ya no valen.
- Logs: activa nivel `all`, repite una llamada, revisa la fila (sin tokens en el log).

## 10. Escenarios de error

| Caso | Esperado |
|---|---|
| Sin bearer | `401` + WWW-Authenticate |
| Token caducado | `401` |
| Scopio no concedido | error herramienta `forbidden` en `tools/call` |
| Schema inválido (falta `id` numérico) | error `-32602` |
| Método desconocido | error `-32601` |
| `Origin` extraño en MCP | `403` |
| Registro con client_id malicioso (`https://evil.com`) | rechazado en el flujo |

## Notas

- En `localhost` sin HTTPS, el navegador rechazará la pantalla de consentimiento salvo que uses el loopback; pruebas el flujo con `curl -c/-b cookies.txt` para mantener sesión.
- El descubrimiento raíz (`/.well-known`) requiere reescrituras; si no quieres activarlas, usa las variantes bajo `wp-json/mcp-connect/v1/.well-known/`.
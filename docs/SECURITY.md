# Modelo de seguridad

El plugin asume el peor caso: un servidor de WordPress puede estar en internet, tras un proxy, con varios roles de usuario y con agentes que pueden ser maliciosos. Todas las decisiones de seguridad se enumeran en `ARCHITECTURE.md`; aquí el resumen ejecutivo.

## Principios

1. **Nada en claro que sea secreto**: tokens, códigos y secretos de clientes se almacenan solo con hash SHA-256.
2. **Mínimo privilegio**: el agente actúa como el usuario que autorizó el acceso y solo con los scopios concedidos (`categoria:modo`).
3. **Por defecto no destructivo**: el modo `delete` está desactivado; las herramientas destructivas se ocultan de `tools/list`.
4. **Validación server-side de todo input** (redirect_uri, origen, PKCE, resource, scopios, schema JSON).
5. **Auditoría sin secretos**: los logs registran eventos pero nunca tokens ni secretos.

## Mitigaciones por amenaza

| Amenaza | Mitigación |
|---|---|
| Robo de authorization code | Códigos de un solo uso + 10 min TTL + PKCE S256 exigido |
| Reemplazo/robo de token | Tokens solo en hash; listas de telón/revocación; TTL corto (1 h) |
| Reutilización de refresh token | Rotación + detección de reuso → revoca la familia completa |
| Suplantación de `redirect_uri` | Validación exacta contra registro; normalización loopback |
| Open redirect | Solo se redirige a `redirect_uri` registrado |
| DNS rebinding / CSRF de navegador | Verificación de `Origin` en el endpoint MCP; CORS cerrado |
| Ataque al token endpoint | Rate limiting por IP+endpoint; auth de cliente cuando aplica |
| Cliente malicioso (DCR) | DCR con rate limit; metadata `client_id`-URL validada y cachead |
| Escalada vía JS malicioso | Consentimiento con nonce + verificación de `Origin` + cabeceras de cookies |
| Exfiltración vía logs | Logs sin tokens/secretos; purga automática 90 días; vaciado al desactivar |
| Modificación de datos ajenos | Los tools comprueban las capacidades reales del usuario del token |

## Datos y retención

- Tokens expirados y códigos: purga automática (`prune_expired`).
- Logs: nivel configurable (`off` / `errors` / `all`), purga a 90 días, se vacían al desactivar.
- Desinstalación: borra tablas solo si `delete_on_uninstall` está marcado.

## Endpoints sensibles

- El endpoint MCP exige `Authorization: Bearer` (salvo `initialize`… también exige bearer; el reto `401` se emite si falta).
- Los endpoints OAuth admin (consentimiento/formularios) exigen usuario de `wp-admin` y nonce.
- Rutas REST de administración del plugin (`/mcp-connect-wp/v1/admin/*`) exigen `manage_options` + nonce REST.
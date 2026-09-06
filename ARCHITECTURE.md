# MCP Connect for WordPress — Architecture

Version: 1.0.0
Status: Implementation reference

This document explains the technical decisions behind the plugin. It was written
*before* the implementation, after studying the current MCP and OAuth specifications.

---

## 1. Protocol

The plugin implements the **Model Context Protocol (MCP)** over the **Streamable HTTP**
transport, as defined by the current MCP specification series
(`2025-03-26` → `2025-06-18` → `2025-11-25`).

### Transport: Streamable HTTP

- A single MCP endpoint is exposed: `https://example.com/wp-json/mcp-connect-wp/v1/mcp`
- Clients send **one JSON-RPC 2.0 message per HTTP POST**.
- The server answers either `application/json` (single JSON object) or
  `text/event-stream` (SSE). This server always answers with a single
  `application/json` object because no streaming tools are implemented.
- `GET` on the endpoint answers `405 Method Not Allowed` (per spec, a server that
  does not expose an SSE stream must answer 405 to GET).
- The server is **stateless**: no `MCP-Session-Id` is required or issued.
- The server tolerates requests with or without the `MCP-Protocol-Version` header
  (a client that omits it is treated as protocol version `2025-03-26`).
- The `Origin` header is validated on every request to prevent DNS rebinding.

### Version negotiation

`initialize` echoes a supported protocol version:

- Supported versions: `2025-03-26`, `2025-06-18`, `2025-11-25`.
- If the client requests an unsupported/newer version, the server answers with its
  latest supported version (negotiation without hard failure).

### JSON-RPC methods implemented

| Method                   | Auth required | Notes                                   |
| ------------------------ | ------------- | --------------------------------------- |
| `initialize`             | **yes**       | Version + capabilities + server info    |
| `notifications/initialized` | **yes**   | notification, no response               |
| `ping`                   | **yes**       | `{}` result                             |
| `tools/list`             | **yes**       | Filtered by granted scopes + settings   |
| `tools/call`             | **yes**       | Validates scopes + capabilities         |
| anything else            | –             | `-32601` method not found               |

Requests without an `id` (notifications) receive no JSON-RPC response (HTTP 202, empty body).

Every MCP method requires a valid bearer access token. Missing/invalid tokens return
**HTTP 401** with `WWW-Authenticate: Bearer resource_metadata="...", authorization_server="..."`
(see §3).

---

## 2. Architecture

The plugin is namespaced `MCPConnect\`. Files:

```
mcp-connect-wp/
├── mcp-connect-wp.php                     # bootstrap, constants, autoloader, hooks
├── uninstall.php                    # uninstall handler (optional data removal)
├── includes/
│   ├── class-plugin.php             # main container / wiring
│   ├── class-install.php            # activation, DB schema (dbDelta), deactivation
│   ├── class-settings.php           # options + defaults (single option array)
│   ├── class-crypto.php             # CSPRNG tokens, SHA-256 hashing
│   ├── class-url-manager.php        # absolute URLs, issuer, redirect_uri policy, origin checks
│   ├── class-permissions.php        # scopes ↔ WP capabilities mapping
│   ├── class-logger.php             # DB logs (level: off|errors|all)
│   ├── class-rate-limiter.php       # IP-based throttling (own table)
│   ├── class-schema-validator.php   # JSON Schema (draft-07 subset) validation
│   ├── class-json-rpc.php           # JSON-RPC response/error builders
│   ├── class-token-store.php        # clients, codes, tokens, authorizations (SQL)
│   ├── class-mcp-tools.php          # tool registry: list + call + filtering
│   ├── class-mcp-auth.php           # bearer token introspection → Actor
│   ├── class-mcp-server.php         # JSON-RPC dispatch (initialize/tools/*)
│   ├── class-discovery.php          # RFC 9728 + RFC 8414 metadata documents
│   ├── class-oauth-server.php       # authorize / token / revoke endpoints
│   ├── class-oauth-client-registration.php # RFC 7591 DCR + CIMD support
│   ├── class-mcp-router.php         # REST routes, well-known rewrite rules, CORS, 401
│   ├── class-rest-api.php           # health + admin REST endpoints
│   └── class-admin.php              # settings page, views, AJAX, diagnostics
├── tools/                           # MCP tool definitions (one file per domain)
│   ├── site.php
│   ├── posts.php
│   ├── pages.php
│   ├── media.php
│   ├── users.php
│   ├── comments.php
│   └── taxonomies.php
├── admin/
│   ├── class-admin-page.php         # page controller (tabs)
│   ├── views/*.php                  # templates
│   ├── css/admin.css
│   └── js/admin.js
├── languages/
└── readme.txt
```

Dependency direction is one-way: `Router → OAuth/Discovery/MCP` → `Stores/Settings`.

---

## 3. OAuth 2.1 (MCP profile)

The plugin is simultaneously:

- an **OAuth 2.1 Authorization Server** (metadata, authorize, token, revoke, DCR), and
- an **MCP resource server** (validates bearer tokens, enforces audience).

### Discovery

1. **Protected resource metadata — RFC 9728**

   - `GET /.well-known/oauth-protected-resource`
   - `GET /wp-json/mcp-connect-wp/v1/.well-known/oauth-protected-resource` (path-insertion
     fallback so it resolves on sub-directory installs and without permalinks)
   - Contains `resource` (canonical MCP endpoint) and `authorization_servers`
     (`issuer`).

2. **Authorization server metadata — RFC 8414**

   - `GET /.well-known/oauth-authorization-server`
   - `GET /wp-json/mcp-connect-wp/v1/.well-known/oauth-authorization-server`
   - Contains `issuer`, `authorization_endpoint`, `token_endpoint`,
     `registration_endpoint`, `revocation_endpoint`, `scopes_supported`,
     `response_types_supported`, `grant_types_supported`,
     `token_endpoint_auth_methods_supported`, `code_challenge_methods_supported`
     (`S256`), `authorization_response_iss_parameter_supported: true`.

3. **401 challenge (mandatory per MCP spec)**

   Every protected MCP request without a valid token answers:

   ```
   HTTP/1.1 401 Unauthorized
   WWW-Authenticate: Bearer resource_metadata="https://…/wp-json/mcp-connect-wp/v1/.well-known/oauth-protected-resource",
                     authorization_server="https://…/wp-json/mcp-connect-wp/v1/.well-known/oauth-authorization-server",
                     scope="posts:read posts:write …"
   ```

   All URLs are absolute, generated from `home_url()` / `site_url()` /
   `rest_url()` — never hard-coded and never derived from untrusted headers.

### Dynamic Client Registration — RFC 7591

- `POST /wp-json/mcp-connect-wp/v1/oauth/register`
- Validates `redirect_uris`, `grant_types`, `response_types`,
  `token_endpoint_auth_method`. Public clients (`none`) get no secret; all
  clients must use PKCE (S256). Confidential clients (`client_secret_basic|post`)
  receive a generated secret (stored SHA-256 hashed).
- Redirect URI policy: absolute, no fragment, no userinfo; allows
  `https://…`, `http://localhost|127.0.0.1|[::1]:port`, and custom app schemes
  such as `cursor://…` (with host). Loopback `localhost`/`127.0.0.1` variants are
  registered automatically.
- **Client ID Metadata Documents (CIMD)**: if a client presents an
  `https://…` URL as `client_id` (instead of registering), the server fetches the
  document (cached), validates `client_id` equality, and uses its `redirect_uris`.

### Authorization Code + PKCE flow

```
client → GET /oauth/authorize?response_type=code&client_id=…&redirect_uri=…
          &code_challenge=…&code_challenge_method=S256&state=…
         (WordPress login if needed)
         → consent screen (server-rendered, CSRF nonce)
client ← 302 redirect_uri?code=…&state=…&iss=issuer
client → POST /oauth/token  (grant_type=authorization_code, code, code_verifier,
         client_id, redirect_uri, resource)
client ← {access_token, token_type:"Bearer", expires_in, refresh_token, scope}
client → POST /wp-json/mcp-connect-wp/v1/mcp (Authorization: Bearer …)
```

Security properties:

- `code_challenge_method` **must** be `S256`; `plain` is rejected.
- Authorization codes: single use (atomic consume), 10 min expiry, bound to
  client, redirect_uri, challenge, user, scopes and resource.
- `iss` parameter returned in authorization responses (OAuth 2.1 / RFC 9207).
- Refresh tokens: rotated on use; **reuse detection** revokes the whole token
  family (RFC 6819 best practice).
- Access tokens: opaque, 1 h TTL, stored as SHA-256 (DB leaks do not leak tokens),
  bound to user, client, scopes and `resource` (RFC 8707 audience).
- Revocation endpoint (RFC 7009) + admin-driven authorization revocation.
- Rate limiting on register / authorize / token.

### Scopes ↔ permissions

Scopes are `{category}:{mode}`:

```
site:read   posts:read/write/delete   pages:read/write/delete
media:read/write/delete   comments:read/write   taxonomies:read/write/delete
users:read
```

Each scope maps to a WordPress capability category. A token only grants what the
admin enabled and what the authenticated WordPress user may do
(`current_user_can()`).

---

## 4. Security model

Priority order (per requirements): **Security > MCP compat > OAuth compat > Ease of
use > UX > Features**.

- Every cross-section of authorization is enforced: HTTP 401 → OAuth scope →
  admin setting toggle → WordPress capability.
- Destructive tools (`wp_delete_*`) are disabled by default (settings matrix,
  mode `delete` = off); they are hidden from `tools/list` when disabled.
- Capability checks (examples): `edit_posts` to create, `publish_posts` to
  publish, `edit_post`/`delete_post` meta caps per object, `upload_files` to
  upload, `moderate_comments`, `manage_categories`.
- Uploads validate MIME type, extension and size via `wp_check_filetype_and_ext`.
- Nonces + capability checks + sanitization/escaping everywhere in admin.
- Secrets are never logged; logs contain no tokens/codes/secrets/passwords.
- CSRF: consent form uses a nonce; admin actions use nonces.
- Open-redirect: redirects only to validated `redirect_uri`.
- MCP disabled ⇒ all endpoints return 403/404 (no accidental exposure).
- Proxy aware: URLs come from WordPress options, not from
  `HTTP_HOST`/`X-Forwarded-*`. An opt-in `trust_proxy_headers` setting exists but
  is off by default.

---

## 5. Database

All tables use `$wpdb->prefix`:

| table                      | purpose                                      |
| -------------------------- | -------------------------------------------- |
| `{prefix}mcp_connect_clients`       | registered OAuth clients (secrets hashed)    |
| `{prefix}mcp_connect_codes`         | one-time PKCE authorization codes            |
| `{prefix}mcp_connect_tokens`        | access + refresh tokens (SHA-256, families)  |
| `{prefix}mcp_connect_authorizations`| user↔client↔scope grants (for revoke/UI)     |
| `{prefix}mcp_connect_logs`          | audit log                                    |
| `{prefix}mcp_connect_rate`          | IP rate limiting                             |

## 6. Non-goals

- Legacy HTTP+SSE transport (deprecated) and WebSocket transport are **not** implemented.
- Editing/creating WordPress users over MCP is not exposed in v1.
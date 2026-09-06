# Herramientas MCP

El plugin expone 27 herramientas organizadas por ámbito. Cada herramienta está limitada por:

1. El **scopio** concedido en el consentimiento (`categoria:modo`).
2. El **modo** habilitado en Ajustes → MCP Connect for WordPress → Permisos (si el modo borrado está desactivado, la herramienta no aparece en `tools/list`).
3. Las **capacidades reales** del usuario de WordPress autenticado.

## Inventario

| Ámbito | Scopio | Herramientas |
|---|---|---|
| Sitio | `site:read` | `wp_get_site_info` |
| Usuario | `users:read` | `wp_get_current_user` |
| Posts | `posts:read/write/delete` | `wp_list_posts`, `wp_get_post`, `wp_create_post`, `wp_update_post`, `wp_delete_post` |
| Pages | `pages:read/write/delete` | `wp_list_pages`, `wp_get_page`, `wp_create_page`, `wp_update_page`, `wp_delete_page` |
| Media | `media:read/write/delete` | `wp_list_media`, `wp_get_media`, `wp_upload_media`, `wp_update_media`, `wp_delete_media` |
| Comments | `comments:read/write` | `wp_list_comments`, `wp_get_comment`, `wp_update_comment` |
| Categorías | `taxonomies:read/write/delete` | categorías: `wp_list_categories`, `wp_create_category`, `wp_update_category`, `wp_delete_category` |
| Etiquetas | `taxonomies:read/write/delete` | tags: `wp_list_tags`, `wp_create_tag`, `wp_update_tag`, `wp_delete_tag` |

> No existe `wp_delete_comment`: la moderación de borrado queda en manos de wp-admin. Tampoco `users:write`.

## Modelo de permisos

- **Turbo de "borrar"**: `posts:delete`, `pages:delete`, `media:delete`, `taxonomies:delete` → ocultos por defecto.
- **Capacidades**:
  - Contenido ajeno: requiere `edit_others_posts` etc. En caso contrario los tools filtran/actúan solo sobre el contenido del propio usuario.
  - Publicar: `publish_posts`. Borrar permanentemente: `delete_posts`.
  - Comentarios no aprobados / moderación: `moderate_comments`.
  - Categorías y etiquetas: `manage_categories`.
  - Medios: `upload_files` para subir; borrar: `delete_post` sobre el attachment.
  - Estado del sitio / usuario: `read` básico.
- **Datos propios**: `wp_list_posts` con status no publicado exige `edit_posts`; los listados añaden `author=current_user` si el usuario no puede editar contenido ajeno.

## Formatos

- Fechas: zona del sitio (`wp_date`).
- Imágenes: tamaños disponibles (thumbnail, medium, large, full) con URL y dimensiones.
- Comentarios: auxiliares con contenido y estado.
- Categorías/etiquetas: ID, nombre, count, slug, programa padre.
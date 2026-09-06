<?php
defined( 'ABSPATH' ) || exit;
/** @var \MCPConnect\Plugin $plugin */
$mcp_url = $plugin->url->mcp_endpoint();
$json    = wp_json_encode(
	array(
		'mcpServers' => array(
			'wordpress' => array( 'url' => $mcp_url ),
		),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
$user = wp_get_current_user();
?>
<?php if ( isset( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Ajustes guardados.', 'mcp-connect-wp' ); ?></p></div>
<?php endif; ?>

<div class="mcp-connect-wp-card mcp-connect-wp-hero">
	<div class="mcp-connect-wp-hero-text">
		<h2><?php echo esc_html__( 'Conecta tus agentes de IA a este WordPress', 'mcp-connect-wp' ); ?></h2>
		<p><?php echo esc_html__( 'Copia esta URL en ChatGPT, Claude, Claude Code, Cursor o cualquier cliente que soporte MCP con OAuth 2.1. El cliente se autenticará con tu usuario y usará solo los permisos que autorices.', 'mcp-connect-wp' ); ?></p>
	</div>
	<button type="button" class="button button-primary button-hero" id="mcp-test-button"><?php echo esc_html__( 'Probar conexión', 'mcp-connect-wp' ); ?></button>
	<span class="mcp-connect-wp-test-result" id="mcp-test-result" role="status"></span>
</div>

<div class="mcp-connect-wp-grid">
	<div class="mcp-connect-wp-card">
		<h3><?php echo esc_html__( 'URL del servidor MCP', 'mcp-connect-wp' ); ?></h3>
		<div class="mcp-connect-wp-copy" data-copy="<?php echo esc_attr( $mcp_url ); ?>">
			<code class="mcp-connect-wp-url"><?php echo esc_html( $mcp_url ); ?></code>
			<button type="button" class="button button-small" data-copy-target><?php echo esc_html__( 'Copiar', 'mcp-connect-wp' ); ?></button>
		</div>
		<p class="description">
			<?php echo esc_html__( 'Pega esta URL en el cliente MCP. No hace falta configuración adicional: el cliente se registrará y pedirá permiso al conectarse.', 'mcp-connect-wp' ); ?>
		</p>
	</div>

	<div class="mcp-connect-wp-card">
		<h3><?php echo esc_html__( 'Configuración para Claude Code', 'mcp-connect-wp' ); ?></h3>
		<pre class="mcp-connect-wp-config"><code><?php echo esc_html( $json ); ?></code></pre>
	</div>
</div>

<div class="mcp-connect-wp-card">
	<h3><?php echo esc_html__( 'Ajustes generales', 'mcp-connect-wp' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mcp_connect_save">
		<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \MCPConnect\Admin::NONCE ) ); ?>">
		<input type="hidden" name="mcp_connect[section]" value="connect">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Servidor MCP', 'mcp-connect-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mcp_connect[enabled]" value="1" <?php checked( $plugin->is_enabled() ); ?>>
						<?php echo esc_html__( 'Habilitado (responde a peticiones MCP y OAuth)', 'mcp-connect-wp' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Registro dinámico', 'mcp-connect-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mcp_connect[enable_registration]" value="1" <?php checked( $plugin->settings->is_registration_enabled() ); ?>>
						<?php echo esc_html__( 'Permitir que los clientes se registren automáticamente (Dynamic Client Registration)', 'mcp-connect-wp' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Orígenes CORS extra', 'mcp-connect-wp' ); ?></th>
				<td>
					<textarea name="mcp_connect[cors_origins]" rows="3" class="large-text code" placeholder="https://app.ejemplo.com&#10;https://widget.ejemplo.com"><?php echo esc_textarea( implode( "\n", $plugin->url->get_allowed_origins() ) ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'Un origen por línea. Solo para clientes tipo navegador que usen fetch desde otro dominio.', 'mcp-connect-wp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Cabeceras proxy', 'mcp-connect-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mcp_connect[trust_proxy_headers]" value="1" <?php checked( $plugin->settings->trust_proxy_headers() ); ?>>
						<?php echo esc_html__( 'Confiar en X-Forwarded-* (sin activar en entornos no proxy)', 'mcp-connect-wp' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Nivel de log', 'mcp-connect-wp' ); ?></th>
				<td>
					<select name="mcp_connect[log_level]">
						<option value="errors" <?php selected( $plugin->settings->log_level(), 'errors' ); ?>><?php echo esc_html__( 'Solo errores', 'mcp-connect-wp' ); ?></option>
						<option value="all" <?php selected( $plugin->settings->log_level(), 'all' ); ?>><?php echo esc_html__( 'Todo', 'mcp-connect-wp' ); ?></option>
						<option value="off" <?php selected( $plugin->settings->log_level(), 'off' ); ?>><?php echo esc_html__( 'Desactivado', 'mcp-connect-wp' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Desinstalación', 'mcp-connect-wp' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mcp_connect[delete_on_uninstall]" value="1" <?php checked( (bool) $plugin->settings->get( 'delete_on_uninstall' ) ); ?>>
						<?php echo esc_html__( 'Borrar datos al desinstalar', 'mcp-connect-wp' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Guardar ajustes', 'mcp-connect-wp' ) ); ?>
	</form>
</div>

<div class="mcp-connect-wp-card">
	<h3><?php echo esc_html__( 'Conectado como', 'mcp-connect-wp' ); ?></h3>
	<p>
		<strong><?php echo esc_html( $user->display_name ); ?></strong> &lt;<?php echo esc_html( $user->user_email ); ?>&gt;
		<br><span class="description"><?php echo esc_html__( 'Los agentes se conectarán con tu usuario de administrador y sus capacidades.', 'mcp-connect-wp' ); ?></span>
	</p>
</div>
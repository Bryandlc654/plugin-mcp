<?php
defined( 'ABSPATH' ) || exit;
/** @var \WPMCPConnect\Plugin $plugin */
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
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Ajustes guardados.', 'wp-mcp-connect' ); ?></p></div>
<?php endif; ?>

<div class="wp-mcp-connect-card wp-mcp-connect-hero">
	<div class="wp-mcp-connect-hero-text">
		<h2><?php echo esc_html__( 'Conecta tus agentes de IA a este WordPress', 'wp-mcp-connect' ); ?></h2>
		<p><?php echo esc_html__( 'Copia esta URL en ChatGPT, Claude, Claude Code, Cursor o cualquier cliente que soporte MCP con OAuth 2.1. El cliente se autenticará con tu usuario y usará solo los permisos que autorices.', 'wp-mcp-connect' ); ?></p>
	</div>
	<button type="button" class="button button-primary button-hero" id="mcp-test-button"><?php echo esc_html__( 'Probar conexión', 'wp-mcp-connect' ); ?></button>
	<span class="wp-mcp-connect-test-result" id="mcp-test-result" role="status"></span>
</div>

<div class="wp-mcp-connect-grid">
	<div class="wp-mcp-connect-card">
		<h3><?php echo esc_html__( 'URL del servidor MCP', 'wp-mcp-connect' ); ?></h3>
		<div class="wp-mcp-connect-copy" data-copy="<?php echo esc_attr( $mcp_url ); ?>">
			<code class="wp-mcp-connect-url"><?php echo esc_html( $mcp_url ); ?></code>
			<button type="button" class="button button-small" data-copy-target><?php echo esc_html__( 'Copiar', 'wp-mcp-connect' ); ?></button>
		</div>
		<p class="description">
			<?php echo esc_html__( 'Pega esta URL en el cliente MCP. No hace falta configuración adicional: el cliente se registrará y pedirá permiso al conectarse.', 'wp-mcp-connect' ); ?>
		</p>
	</div>

	<div class="wp-mcp-connect-card">
		<h3><?php echo esc_html__( 'Configuración para Claude Code', 'wp-mcp-connect' ); ?></h3>
		<pre class="wp-mcp-connect-config"><code><?php echo esc_html( $json ); ?></code></pre>
	</div>
</div>

<div class="wp-mcp-connect-card">
	<h3><?php echo esc_html__( 'Ajustes generales', 'wp-mcp-connect' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wp_mcp_connect_save">
		<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \WPMCPConnect\Admin::NONCE ) ); ?>">
		<input type="hidden" name="wp_mcp_connect[section]" value="connect">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Servidor MCP', 'wp-mcp-connect' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wp_mcp_connect[enabled]" value="1" <?php checked( $plugin->is_enabled() ); ?>>
						<?php echo esc_html__( 'Habilitado (responde a peticiones MCP y OAuth)', 'wp-mcp-connect' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Registro dinámico', 'wp-mcp-connect' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wp_mcp_connect[enable_registration]" value="1" <?php checked( $plugin->settings->is_registration_enabled() ); ?>>
						<?php echo esc_html__( 'Permitir que los clientes se registren automáticamente (Dynamic Client Registration)', 'wp-mcp-connect' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Orígenes CORS extra', 'wp-mcp-connect' ); ?></th>
				<td>
					<textarea name="wp_mcp_connect[cors_origins]" rows="3" class="large-text code" placeholder="https://app.ejemplo.com&#10;https://widget.ejemplo.com"><?php echo esc_textarea( implode( "\n", $plugin->url->get_allowed_origins() ) ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'Un origen por línea. Solo para clientes tipo navegador que usen fetch desde otro dominio.', 'wp-mcp-connect' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Cabeceras proxy', 'wp-mcp-connect' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wp_mcp_connect[trust_proxy_headers]" value="1" <?php checked( $plugin->settings->trust_proxy_headers() ); ?>>
						<?php echo esc_html__( 'Confiar en X-Forwarded-* (sin activar en entornos no proxy)', 'wp-mcp-connect' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Nivel de log', 'wp-mcp-connect' ); ?></th>
				<td>
					<select name="wp_mcp_connect[log_level]">
						<option value="errors" <?php selected( $plugin->settings->log_level(), 'errors' ); ?>><?php echo esc_html__( 'Solo errores', 'wp-mcp-connect' ); ?></option>
						<option value="all" <?php selected( $plugin->settings->log_level(), 'all' ); ?>><?php echo esc_html__( 'Todo', 'wp-mcp-connect' ); ?></option>
						<option value="off" <?php selected( $plugin->settings->log_level(), 'off' ); ?>><?php echo esc_html__( 'Desactivado', 'wp-mcp-connect' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Desinstalación', 'wp-mcp-connect' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wp_mcp_connect[delete_on_uninstall]" value="1" <?php checked( (bool) $plugin->settings->get( 'delete_on_uninstall' ) ); ?>>
						<?php echo esc_html__( 'Borrar datos al desinstalar', 'wp-mcp-connect' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Guardar ajustes', 'wp-mcp-connect' ) ); ?>
	</form>
</div>

<div class="wp-mcp-connect-card">
	<h3><?php echo esc_html__( 'Conectado como', 'wp-mcp-connect' ); ?></h3>
	<p>
		<strong><?php echo esc_html( $user->display_name ); ?></strong> &lt;<?php echo esc_html( $user->user_email ); ?>&gt;
		<br><span class="description"><?php echo esc_html__( 'Los agentes se conectarán con tu usuario de administrador y sus capacidades.', 'wp-mcp-connect' ); ?></span>
	</p>
</div>
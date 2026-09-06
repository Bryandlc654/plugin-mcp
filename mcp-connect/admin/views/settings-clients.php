<?php
defined( 'ABSPATH' ) || exit;
/** @var \MCPConnect\Plugin $plugin */
$auths  = $plugin->tokens->list_all_authorizations();
$clients = $plugin->tokens->list_clients();
$client_names = array();
foreach ( $clients as $client ) {
	$client_names[ $client->client_id ] = $client->client_name ? $client->client_name : $client->client_id;
}
$current_user_id = get_current_user_id();
?>
<div class="mcp-connect-card">
	<h3><?php echo esc_html__( 'Conexiones autorizadas', 'mcp-connect' ); ?></h3>
	<?php if ( empty( $auths ) ) : ?>
		<p><?php echo esc_html__( 'Todavía no hay ninguna autorización. Cuando un agente se conecte desde la pestaña "Conectar" y autorices el acceso, aparecerá aquí.', 'mcp-connect' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Cliente', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Usuario', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Permisos', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Concedido', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Último uso', 'mcp-connect' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $auths as $auth ) : ?>
				<?php
				$scopes = $auth->scopes ? (array) json_decode( $auth->scopes, true ) : array();
				$labels = array();
				foreach ( $scopes as $scope ) {
					if ( isset( \MCPConnect\Permissions::SCOPE_LABELS[ $scope ] ) ) {
						$labels[] = \MCPConnect\Permissions::SCOPE_LABELS[ $scope ];
					}
				}
				$name = isset( $client_names[ $auth->client_id ] ) ? $client_names[ $auth->client_id ] : $auth->client_id;
				$user = get_userdata( (int) $auth->user_id );
				$is_me = ( (int) $auth->user_id === $current_user_id );
				?>
				<tr class="<?php echo $auth->revoked ? 'mcp-connect-revoked' : ''; ?>">
					<td><strong><?php echo esc_html( $name ); ?></strong><br><code><?php echo esc_html( $auth->client_id ); ?></code><?php echo $is_me ? ' ' . esc_html__( '(tu autorización)', 'mcp-connect' ) : ''; ?></td>
					<td><?php echo $user ? esc_html( $user->display_name ) : esc_html( (string) $auth->user_id ); ?></td>
					<td><?php echo $labels ? esc_html( implode( ', ', $labels ) ) : esc_html__( 'Ninguno', 'mcp-connect' ); ?></td>
					<td><?php echo esc_html( $auth->granted_at ); ?></td>
					<td><?php echo $auth->last_used_at ? esc_html( $auth->last_used_at ) : '—'; ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Revocar esta autorización?', 'mcp-connect' ) ); ?>');">
							<input type="hidden" name="action" value="mcp_connect_revoke">
							<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \MCPConnect\Admin::NONCE ) ); ?>">
							<input type="hidden" name="auth_id" value="<?php echo esc_attr( (int) $auth->id ); ?>">
							<?php if ( $auth->revoked ) : ?>
								<button type="submit" class="button button-link button-link-delete" disabled><?php echo esc_html__( 'Revocada', 'mcp-connect' ); ?></button>
							<?php else : ?>
								<button type="submit" class="button button-link button-link-delete"><?php echo esc_html__( 'Revocar', 'mcp-connect' ); ?></button>
							<?php endif; ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="mcp-connect-card">
	<h3><?php echo esc_html__( 'Clientes registrados', 'mcp-connect' ); ?></h3>
	<?php if ( empty( $clients ) ) : ?>
		<p><?php echo esc_html__( 'No hay clientes registrados.', 'mcp-connect' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Nombre', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Client ID', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Tipo', 'mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Creado', 'mcp-connect' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $clients as $client ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $client->client_name ? $client->client_name : '—' ); ?></strong></td>
					<td><code><?php echo esc_html( $client->client_id ); ?></code></td>
					<td><?php echo $client->is_dynamic ? esc_html__( 'Dinámico', 'mcp-connect' ) : esc_html__( 'Manual', 'mcp-connect' ); ?></td>
					<td><?php echo esc_html( $client->created_at ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar este cliente y todas sus autorizaciones?', 'mcp-connect' ) ); ?>');">
							<input type="hidden" name="action" value="mcp_connect_delete_client">
							<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \MCPConnect\Admin::NONCE ) ); ?>">
							<input type="hidden" name="client_id" value="<?php echo esc_attr( $client->client_id ); ?>">
							<button type="submit" class="button button-link button-link-delete"><?php echo esc_html__( 'Eliminar', 'mcp-connect' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
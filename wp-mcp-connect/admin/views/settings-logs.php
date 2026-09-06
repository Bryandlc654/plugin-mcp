<?php
defined( 'ABSPATH' ) || exit;
/** @var \WPMCPConnect\Plugin $plugin */
$logs = $plugin->logger->fetch( 'all', 100, 0 );
$level_map = array(
	'info'  => __( 'Información', 'wp-mcp-connect' ),
	'error' => __( 'Error', 'wp-mcp-connect' ),
);
?>
<div class="wp-mcp-connect-card">
	<div class="wp-mcp-connect-card-head">
		<h3><?php echo esc_html__( 'Registro de actividad', 'wp-mcp-connect' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wp_mcp_connect_clear_logs">
			<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \WPMCPConnect\Admin::NONCE ) ); ?>">
			<button type="submit" class="button"><?php echo esc_html__( 'Vaciar logs', 'wp-mcp-connect' ); ?></button>
		</form>
	</div>

	<p class="description">
		<?php echo esc_html__( 'Los logs nunca contienen tokens ni secretos. Se conservan 90 días y se vacían automáticamente al desactivar el plugin.', 'wp-mcp-connect' ); ?>
	</p>

	<?php if ( empty( $logs ) ) : ?>
		<p><?php echo esc_html__( 'Sin actividad registrada.', 'wp-mcp-connect' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Fecha', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Nivel', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Cliente', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Usuario', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Método', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Resultado', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'Código', 'wp-mcp-connect' ); ?></th>
					<th><?php echo esc_html__( 'ms', 'wp-mcp-connect' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $logs as $row ) : ?>
				<tr class="<?php echo 'error' === $row->level ? 'wp-mcp-connect-log-error' : ''; ?>">
					<td><?php echo esc_html( $row->time ); ?></td>
					<td><?php echo esc_html( isset( $level_map[ $row->level ] ) ? $level_map[ $row->level ] : $row->level ); ?></td>
					<td><code><?php echo esc_html( $row->client_id ); ?></code></td>
					<td><?php echo esc_html( (string) $row->user_id ); ?></td>
					<td><?php echo esc_html( (string) $row->method ); ?></td>
					<td><?php echo esc_html( (string) $row->result ); ?></td>
					<td><?php echo esc_html( (string) $row->code ); ?></td>
					<td><?php echo esc_html( (string) $row->duration_ms ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
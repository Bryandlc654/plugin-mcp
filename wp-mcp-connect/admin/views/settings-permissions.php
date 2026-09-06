<?php
defined( 'ABSPATH' ) || exit;
/** @var \WPMCPConnect\Plugin $plugin */
$matrix  = (array) $plugin->settings->get( 'scopes', array() );
$modes   = array(
	'read'   => __( 'Lectura', 'wp-mcp-connect' ),
	'write'  => __( 'Escritura', 'wp-mcp-connect' ),
	'delete' => __( 'Borrado', 'wp-mcp-connect' ),
);
$cats    = array(
	'posts'       => __( 'Publicaciones', 'wp-mcp-connect' ),
	'pages'       => __( 'Páginas', 'wp-mcp-connect' ),
	'media'       => __( 'Multimedia', 'wp-mcp-connect' ),
	'comments'    => __( 'Comentarios', 'wp-mcp-connect' ),
	'taxonomies'  => __( 'Categorías y etiquetas', 'wp-mcp-connect' ),
	'users'       => __( 'Tu usuario', 'wp-mcp-connect' ),
	'site'        => __( 'Información del sitio', 'wp-mcp-connect' ),
);
?>
<?php if ( isset( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Permisos actualizados.', 'wp-mcp-connect' ); ?></p></div>
<?php endif; ?>

<div class="wp-mcp-connect-card">
	<h3><?php echo esc_html__( 'Permisos disponibles para los agentes', 'wp-mcp-connect' ); ?></h3>
	<p class="description"><?php echo esc_html__( 'Marca los permisos que los agentes de IA pueden solicitar. Las piezas destructivas (borrado) están desactivadas por defecto: se ocultan de la lista de herramientas del agente. Recuerda que además el agente solo podrá actuar sobre aquello que tu usuario de WordPress tenga capacidad de hacer (y solo sobre tu propio contenido si no tiene permisos globales).', 'wp-mcp-connect' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wp_mcp_connect_save">
		<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \WPMCPConnect\Admin::NONCE ) ); ?>">
		<input type="hidden" name="wp_mcp_connect[section]" value="permissions">

		<table class="widefat" id="wp-mcp-connect-scopes">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Área', 'wp-mcp-connect' ); ?></th>
					<?php foreach ( $modes as $mode => $label ) : ?>
						<th><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
					<th><?php echo esc_html__( 'Scope', 'wp-mcp-connect' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $cats as $cat => $cat_label ) : ?>
				<?php $row = isset( $matrix[ $cat ] ) ? $matrix[ $cat ] : array(); ?>
				<tr>
					<th scope="row"><?php echo esc_html( $cat_label ); ?></th>
					<?php foreach ( $modes as $mode => $mode_label ) : ?>
						<?php
						$scope  = $cat . ':' . $mode;
						$valid  = \WPMCPConnect\Permissions::is_valid_scope( $scope );
						$enabled = $valid && ! empty( $row[ $mode ] );
						?>
						<td class="wp-mcp-connect-scope-cell">
							<?php if ( $valid ) : ?>
								<input type="checkbox" name="wp_mcp_connect[scopes][<?php echo esc_attr( $scope ); ?>]" value="1" <?php checked( $enabled ); ?>>
							<?php else : ?>
								<span class="dashicons dashicons-lock" aria-hidden="true" title="<?php echo esc_attr__( 'No disponible', 'wp-mcp-connect' ); ?>"></span>
								<input type="hidden" name="wp_mcp_connect[scopes][<?php echo esc_attr( $scope ); ?>]" value="0">
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
					<td><code><?php echo esc_html( \WPMCPConnect\Permissions::SCOPE_LABELS[ $scope ] ?? $scope ); ?></code><br><span class="description"><?php echo esc_html( $scope ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p>
			<label>
				<input type="checkbox" id="wp-mcp-connect-check-all"> <?php echo esc_html__( 'Seleccionar todos los disponibles', 'wp-mcp-connect' ); ?>
			</label>
		</p>

		<?php submit_button( __( 'Guardar permisos', 'wp-mcp-connect' ) ); ?>
	</form>
</div>
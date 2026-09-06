<?php
defined( 'ABSPATH' ) || exit;
/** @var \MCPConnect\Plugin $plugin */
$matrix  = (array) $plugin->settings->get( 'scopes', array() );
$modes   = array(
	'read'   => __( 'Lectura', 'mcp-connect' ),
	'write'  => __( 'Escritura', 'mcp-connect' ),
	'delete' => __( 'Borrado', 'mcp-connect' ),
);
$cats    = array(
	'posts'       => __( 'Publicaciones', 'mcp-connect' ),
	'pages'       => __( 'Páginas', 'mcp-connect' ),
	'media'       => __( 'Multimedia', 'mcp-connect' ),
	'comments'    => __( 'Comentarios', 'mcp-connect' ),
	'taxonomies'  => __( 'Categorías y etiquetas', 'mcp-connect' ),
	'users'       => __( 'Tu usuario', 'mcp-connect' ),
	'site'        => __( 'Información del sitio', 'mcp-connect' ),
);
?>
<?php if ( isset( $_GET['updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Permisos actualizados.', 'mcp-connect' ); ?></p></div>
<?php endif; ?>

<div class="mcp-connect-card">
	<h3><?php echo esc_html__( 'Permisos disponibles para los agentes', 'mcp-connect' ); ?></h3>
	<p class="description"><?php echo esc_html__( 'Marca los permisos que los agentes de IA pueden solicitar. Las piezas destructivas (borrado) están desactivadas por defecto: se ocultan de la lista de herramientas del agente. Recuerda que además el agente solo podrá actuar sobre aquello que tu usuario de WordPress tenga capacidad de hacer (y solo sobre tu propio contenido si no tiene permisos globales).', 'mcp-connect' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mcp_connect_save">
		<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( \MCPConnect\Admin::NONCE ) ); ?>">
		<input type="hidden" name="mcp_connect[section]" value="permissions">

		<table class="widefat" id="mcp-connect-scopes">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Área', 'mcp-connect' ); ?></th>
					<?php foreach ( $modes as $mode => $label ) : ?>
						<th><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
					<th><?php echo esc_html__( 'Scope', 'mcp-connect' ); ?></th>
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
						$valid  = \MCPConnect\Permissions::is_valid_scope( $scope );
						$enabled = $valid && ! empty( $row[ $mode ] );
						?>
						<td class="mcp-connect-scope-cell">
							<?php if ( $valid ) : ?>
								<input type="checkbox" name="mcp_connect[scopes][<?php echo esc_attr( $scope ); ?>]" value="1" <?php checked( $enabled ); ?>>
							<?php else : ?>
								<span class="dashicons dashicons-lock" aria-hidden="true" title="<?php echo esc_attr__( 'No disponible', 'mcp-connect' ); ?>"></span>
								<input type="hidden" name="mcp_connect[scopes][<?php echo esc_attr( $scope ); ?>]" value="0">
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
					<td><code><?php echo esc_html( \MCPConnect\Permissions::SCOPE_LABELS[ $scope ] ?? $scope ); ?></code><br><span class="description"><?php echo esc_html( $scope ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p>
			<label>
				<input type="checkbox" id="mcp-connect-check-all"> <?php echo esc_html__( 'Seleccionar todos los disponibles', 'mcp-connect' ); ?>
			</label>
		</p>

		<?php submit_button( __( 'Guardar permisos', 'mcp-connect' ) ); ?>
	</form>
</div>
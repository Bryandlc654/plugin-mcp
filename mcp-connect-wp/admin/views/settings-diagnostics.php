<?php
defined( 'ABSPATH' ) || exit;
/** @var \MCPConnect\Plugin $plugin */
$checks = $plugin->admin->run_diagnostics();
$funcs  = array(
	'php'      => PHP_VERSION,
	'infra'    => PHP_OS . ' / ' . PHP_SAPI,
	'max_exec' => ini_get( 'max_execution_time' ) . 's',
	'mem'      => ini_get( 'memory_limit' ),
);
?>
<div class="mcp-connect-wp-card">
	<h3><?php echo esc_html__( 'Comprobaciones', 'mcp-connect-wp' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Comprobación', 'mcp-connect-wp' ); ?></th>
				<th><?php echo esc_html__( 'Estado', 'mcp-connect-wp' ); ?></th>
				<th><?php echo esc_html__( 'Detalle', 'mcp-connect-wp' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $checks as $check ) : ?>
			<tr>
				<td><?php echo esc_html( $check['name'] ); ?></td>
				<td>
					<?php if ( $check['ok'] ) : ?>
						<span class="mcp-connect-wp-badge mcp-connect-wp-ok"><?php echo esc_html__( 'OK', 'mcp-connect-wp' ); ?></span>
					<?php else : ?>
						<span class="mcp-connect-wp-badge mcp-connect-wp-fail"><?php echo esc_html__( '¡Revisa!', 'mcp-connect-wp' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo wp_kses_post( $check['message'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="mcp-connect-wp-card">
	<h3><?php echo esc_html__( 'Entorno', 'mcp-connect-wp' ); ?></h3>
	<ul>
		<li><strong>WordPress:</strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?> — <code><?php echo esc_html( (string) get_option( 'permalink_structure' ) ); ?></code></li>
		<li><strong>PHP:</strong> <?php echo esc_html( $funcs['php'] ); ?> — <?php echo esc_html( $funcs['infra'] ); ?></li>
		<li><strong>Home:</strong> <code><?php echo esc_html( home_url( '/' ) ); ?></code></li>
		<li><strong>Site:</strong> <code><?php echo esc_html( site_url( '/' ) ); ?></code></li>
		<li><strong>REST:</strong> <code><?php echo esc_html( get_rest_url() ); ?></code></li>
		<li><strong>MCP:</strong> <code><?php echo esc_html( $plugin->url->mcp_endpoint() ); ?></code></li>
		<li><strong>Issuer:</strong> <code><?php echo esc_html( $plugin->url->issuer() ); ?></code></li>
		<li><strong>max_execution_time:</strong> <?php echo esc_html( $funcs['max_exec'] ); ?> — <strong>memory_limit:</strong> <?php echo esc_html( $funcs['mem'] ); ?></li>
	</ul>
</div>
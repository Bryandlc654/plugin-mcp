<?php
defined( 'ABSPATH' ) || exit;
/** @var \WPMCPConnect\Plugin $plugin */
$checks = $plugin->admin->run_diagnostics();
$funcs  = array(
	'php'      => PHP_VERSION,
	'infra'    => PHP_OS . ' / ' . PHP_SAPI,
	'max_exec' => ini_get( 'max_execution_time' ) . 's',
	'mem'      => ini_get( 'memory_limit' ),
);
?>
<div class="wp-mcp-connect-card">
	<h3><?php echo esc_html__( 'Comprobaciones', 'wp-mcp-connect' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Comprobación', 'wp-mcp-connect' ); ?></th>
				<th><?php echo esc_html__( 'Estado', 'wp-mcp-connect' ); ?></th>
				<th><?php echo esc_html__( 'Detalle', 'wp-mcp-connect' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $checks as $check ) : ?>
			<tr>
				<td><?php echo esc_html( $check['name'] ); ?></td>
				<td>
					<?php if ( $check['ok'] ) : ?>
						<span class="wp-mcp-connect-badge wp-mcp-connect-ok"><?php echo esc_html__( 'OK', 'wp-mcp-connect' ); ?></span>
					<?php else : ?>
						<span class="wp-mcp-connect-badge wp-mcp-connect-fail"><?php echo esc_html__( '¡Revisa!', 'wp-mcp-connect' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo wp_kses_post( $check['message'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="wp-mcp-connect-card">
	<h3><?php echo esc_html__( 'Entorno', 'wp-mcp-connect' ); ?></h3>
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
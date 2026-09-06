<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! class_exists( '\MCPConnect\Install', false ) ) {
	require_once dirname( __FILE__ ) . '/includes/class-install.php';
}

\MCPConnect\Install::uninstall();
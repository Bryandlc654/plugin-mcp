<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\MCPConnect\Install', false ) ) {
	require_once dirname( __FILE__ ) . '/includes/class-install.php';
}

\MCPConnect\Install::uninstall();
<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WPMCPConnect\Install', false ) ) {
	require_once dirname( __FILE__ ) . '/includes/class-install.php';
}

\WPMCPConnect\Install::uninstall();
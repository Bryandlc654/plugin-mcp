<?php
/**
 * Plugin Name:       WP MCP Connect
 * Plugin URI:        https://github.com/wpmcpconnect/wp-mcp-connect
 * Description:       Convierte tu WordPress en un MCP Server (Model Context Protocol) listo para conectar con ChatGPT, Claude, Claude Code, Cursor y otros agentes de IA mediante OAuth 2.1. Copia la URL MCP, conecta, autoriza y listo.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Next Boost Peru
 * Author URI:        http://nextboost.business/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-mcp-connect
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_MCP_CONNECT_VERSION', '1.0.0' );
define( 'WP_MCP_CONNECT_FILE', __FILE__ );
define( 'WP_MCP_CONNECT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MCP_CONNECT_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_MCP_CONNECT_MIN_CAP', 'manage_options' );

require_once WP_MCP_CONNECT_DIR . 'includes/class-plugin.php';

spl_autoload_register(
	function ( $class ) {
		$prefix = 'WPMCPConnect\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );

		$dir = WP_MCP_CONNECT_DIR;
		if ( count( $parts ) ) {
			$dir .= strtolower( implode( '/', $parts ) ) . '/';
		} else {
			$dir .= 'includes/';
		}

		$file = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $name ) );
		$file = str_replace( '_', '-', $file );
		$file = sprintf( '%sclass-%s.php', $dir, $file );

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( '\WPMCPConnect\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\WPMCPConnect\Install', 'deactivate' ) );

\WPMCPConnect\Plugin::instance()->init();
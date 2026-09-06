<?php
/**
 * Test bootstrap: stubs enough WordPress API to exercise the pure logic of
 * the plugin (no database, no HTTP). Run with: php tests/run.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ . '/../' );
define( 'MCP_CONNECT_VERSION', '1.0.0' );
define( 'MCP_CONNECT_FILE', __DIR__ . '/../mcp-connect-wp/mcp-connect-wp.php' );
define( 'MCP_CONNECT_DIR', __DIR__ . '/../mcp-connect-wp/' );
define( 'MCP_CONNECT_URL', 'https://example.test/wp-content/plugins/mcp-connect-wp/' );
define( 'MCP_CONNECT_MIN_CAP', 'manage_options' );
define( 'HOUR_IN_SECONDS', 60 * 60 );
define( 'DAY_IN_SECONDS', 24 * 60 * 60 );

$GLOBALS['wp_options']  = array();
$GLOBALS['test_home']   = 'https://example.test';
$GLOBALS['test_site']   = 'https://example.test';
$GLOBALS['test_ssl']    = true;
$GLOBALS['test_perm']   = '/%postname%/';

/** Minimal WP_Error. */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}

/* ---- option / url pumps ---- */

function wp_parse_args( $args, $defaults = array() ) {
	$args = is_object( $args ) ? get_object_vars( $args ) : ( is_array( $args ) ? $args : array() );
	return array_merge( $defaults, $args );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wp_options'][ $key ] = $value;
	return true;
}

function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	if ( ! array_key_exists( $key, $GLOBALS['wp_options'] ) ) {
		$GLOBALS['wp_options'][ $key ] = $value;
		return true;
	}
	return false;
}

function delete_option( $key ) {
	unset( $GLOBALS['wp_options'][ $key ] );
	return true;
}

function home_url( $path = '' ) { return untrailingslashit( $GLOBALS['test_home'] ) . ( $path ? '/' . ltrim( $path, '/' ) : '' ); }
function get_home_url( $blog_id = null, $path = '' ) { return home_url( $path ); }
function get_site_url( $blog_id = null, $path = '' ) { return untrailingslashit( $GLOBALS['test_site'] ) . ( $path ? '/' . ltrim( $path, '/' ) : '' ); }
function is_ssl() { return (bool) $GLOBALS['test_ssl']; }
function untrailingslashit( $str ) { return rtrim( (string) $str, '/' ); }
function trailingslashit( $str ) { return rtrim( (string) $str, '/\\' ) . '/'; }
function wp_parse_url( $url ) { return parse_url( (string) $url ); }
function get_rest_url( $blog_id = null, $path = '' ) {
	$base = home_url() . '/wp-json';
	return $path ? $base . '/' . ltrim( $path, '/' ) : $base;
}
function rest_url( $path = '' ) { return get_rest_url( null, $path ); }
function get_bloginfo( $key = '' ) { return 'Test Site'; }
function get_locale() { return 'es_ES'; }
function is_admin() { return false; }

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
function sanitize_key( $key ) { return strtolower( trim( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) ) ); }
function sanitize_email( $email ) { return strtolower( trim( (string) $email ) ); }
function esc_url_raw( $url ) { return (string) $url; }
function esc_url( $url ) { return (string) $url; }
function esc_html( $str ) { return htmlspecialchars( (string) $str, ENT_QUOTES ); }
function esc_attr( $str ) { return htmlspecialchars( (string) $str, ENT_QUOTES ); }
function esc_attr_e( $text, $domain ) { echo esc_attr( $text ); }
function esc_html_e( $text, $domain ) { echo esc_html( $text ); }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_js( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_textarea( $str ) { return (string) $str; }
function __( $text, $domain = null ) { return $text; }
function _e( $text, $domain = null ) { echo $text; }
function _n( $single, $plural, $number, $domain = null ) { return $number == 1 ? $single : $plural; }
function wp_kses_post( $str ) { return (string) $str; }

function is_wp_error( $thing ) { return is_object( $thing ) && $thing instanceof WP_Error; }

function add_action( ...$args ) { $GLOBALS['test_hooks'][] = $args; return true; }
function add_filter( ...$args ) { $GLOBALS['test_filters'][] = $args; return true; }
function do_action( $tag ) {}
function apply_filters( $tag, $value ) { return $value; }
function register_activation_hook( ...$args ) { return true; }
function register_deactivation_hook( ...$args ) { return true; }
function flush_rewrite_rules( ...$args ) { return true; }
function add_rewrite_rule( ...$args ) { return true; }

function current_user_can( $cap ) { return false; }
function user_can( $user, $cap ) { return false; }
function get_current_user_id() { return 0; }
function wp_set_current_user( $id ) {}
function get_userdata( $id ) { return null; }
function wp_create_nonce( $action = -1 ) { return md5( $action . '::nonce' ); }
function wp_verify_nonce( $nonce, $action = -1 ) { return (string) $nonce === wp_create_nonce( $action ); }
function current_time( $type = 'mysql', $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_date( $format = '', $timestamp = null, $timezone = null ) { return gmdate( $format, null === $timestamp ? time() : $timestamp ); }
function update_option_multi( ...$a ) {}

function get_permalink( $post = 0 ) { return 'https://example.test/?p=' . (int) $post; }
function post_type_archive_title( $prefix = '', $display = true ) { return ''; }

$GLOBALS['test_hooks']   = array();
$GLOBALS['test_filters'] = array();

/* ---- plugin autoloader and container ---- */

spl_autoload_register(
	function ( $class ) {
		$prefix = 'MCPConnect\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$dir      = MCP_CONNECT_DIR;
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

\MCPConnect\Plugin::instance()->init();

/* ---- tiny assertion runner support ---- */

function mcp_connect_assert( $condition, $message, &$failures, $test ) {
	if ( ! $condition ) {
		$failures[] = "  [FAIL] $test: $message";
		return false;
	}
	return true;
}

function mcp_connect_run_tests( $dir ) {
	$files    = glob( $dir . '/*.test.php' );
	$failures = array();
	$pass     = 0;
	foreach ( $files as $file ) {
		require_once $file;
		$source = file_get_contents( $file );
		preg_match_all( '/function\s+(test_[a-zA-Z0-9_]+)\s*\(/', $source, $m );
		foreach ( $m[1] as $fn ) {
			$local = array();
			call_user_func_array( $fn, array( &$local ) );
			if ( $local ) {
				$failures = array_merge( $failures, $local );
			} else {
				$pass++;
			}
		}
	}
	return array( $pass, $failures );
}
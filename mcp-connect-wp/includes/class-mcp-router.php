<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP/transport wiring: REST routes, Streamable HTTP handling, OAuth
 * endpoints, well-known discovery, CORS and Origin validation.
 */
final class MCP_Router {

	/** @var Plugin */
	private $plugin;

	/** @var array|null Pending raw response to serve via rest_pre_serve_request. */
	private static $pending = null;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'parse_request', array( $this, 'serve_well_known' ) );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'rest_authentication_errors', array( $this, 'bypass_cookie_auth' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'pre_serve' ), 10, 4 );

		add_filter( 'allowed_http_origin', array( $this, 'allow_cors_origins' ) );
		add_filter( 'rest_cors_allowed_headers', array( $this, 'cors_headers' ) );
	}

	public function maybe_upgrade() {
		Install::maybe_upgrade();
	}

	/**
	 * Registers the .well-known/<kind> rewrite rules. Called from
	 * Plugin::register_core() (init priority 20) and, idempotently, via the
	 * init hook wired in the constructor.
	 */
	public function register_rewrites() {
		$this->register_rewrite_rules();
	}

	public function register_rewrite_rules() {
		add_rewrite_rule( '^\.well-known/oauth-protected-resource/?$', 'index.php?mcp_connect_wk=protected-resource', 'top' );
		add_rewrite_rule( '^\.well-known/oauth-authorization-server/?$', 'index.php?mcp_connect_wk=authorization-server', 'top' );
		add_rewrite_rule( '^\.well-known/openid-configuration/?$', 'index.php?mcp_connect_wk=authorization-server', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'mcp_connect_wk';
		return $vars;
	}

	public function serve_well_known( \WP $wp ) {
		$kind = isset( $wp->query_vars['mcp_connect_wk'] ) ? $wp->query_vars['mcp_connect_wk'] : '';
		if ( ! $kind ) {
			return;
		}

		if ( ! empty( $wp->query_vars['rest_route'] ) ) {
			return;
		}

		if ( ! $this->plugin->is_enabled() ) {
			status_header( 404 );
			nocache_headers();
			header( 'Content-Type: application/json' );
			echo wp_json_encode( array( 'error' => 'not_found' ) );
			exit;
		}

		self::emit_json( $this->well_known_document( $kind ) );
		exit;
	}

	private function well_known_document( $kind ) {
		$discovery = $this->plugin->discovery;
		if ( 'protected-resource' === $kind ) {
			return $discovery->protected_resource_document();
		}
		return $discovery->authorization_server_document();
	}

	/* ------------------------------------------------------------------ *
	 *  REST routes
	 * ------------------------------------------------------------------ */

	public function register_rest_routes() {
		$any = array( 'methods' => \WP_REST_Server::READABLE, 'callback' => array( $this, 'route_any' ), 'permission_callback' => '__return_true' );

		register_rest_route(
			'mcp-connect-wp/v1',
			'/',
			array(
				'methods'             => array( 'POST', 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'handle_mcp_endpoint' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'mcp-connect-wp/v1',
			'/mcp',
			array(
				'methods'             => array( 'POST', 'GET', 'OPTIONS' ),
				'callback'            => array( $this, 'handle_mcp_endpoint' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route( 'mcp-connect-wp/v1', '/health', array( 'methods' => 'GET', 'callback' => array( $this, 'handle_health' ), 'permission_callback' => '__return_true' ) );

		register_rest_route(
			'mcp-connect-wp/v1',
			'/oauth/authorize',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_oauth' ),
				'args'                => array( 'endpoint' => array( 'default' => 'authorize' ) ),
				'permission_callback' => '__return_true',
			)
		);
		foreach ( array( 'token', 'register', 'revoke' ) as $endpoint ) {
			register_rest_route(
				'mcp-connect-wp/v1',
				'/oauth/' . $endpoint,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_oauth' ),
					'args'                => array( 'endpoint' => array( 'default' => $endpoint ) ),
					'permission_callback' => '__return_true',
				)
			);
		}

		register_rest_route( 'mcp-connect-wp/v1', '/.well-known/oauth-protected-resource', array( 'methods' => 'GET', 'callback' => array( $this, 'route_any' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mcp-connect-wp/v1', '/.well-known/oauth-authorization-server', array( 'methods' => 'GET', 'callback' => array( $this, 'route_any' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mcp-connect-wp/v1', '/.well-known/openid-configuration', array( 'methods' => 'GET', 'callback' => array( $this, 'route_any' ), 'permission_callback' => '__return_true' ) );

		register_rest_route(
			'mcp-connect-wp/v1',
			'/admin/(?P<action>[a-z_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_admin_rest' ),
				'permission_callback' => array( $this, 'admin_rest_permission' ),
			)
		);
	}

	public function route_any( \WP_REST_Request $request ) {
		$route = $request->get_route();
		if ( strpos( $route, 'oauth-protected-resource' ) !== false ) {
			return $this->emit( $this->json_response( $this->plugin->discovery->protected_resource_document() ) );
		}
		if ( strpos( $route, 'oauth-authorization-server' ) !== false || strpos( $route, 'openid-configuration' ) !== false ) {
			return $this->emit( $this->json_response( $this->plugin->discovery->authorization_server_document() ) );
		}
		return $this->emit( $this->json_response( array( 'status' => 'ok' ) ) );
	}

	public function handle_health() {
		global $wp_version;
		$plugin = $this->plugin;
		$body   = array(
			'status'      => 'ok',
			'plugin'      => 'MCP Connect for WordPress',
			'version'     => MCP_CONNECT_VERSION,
			'mcp'         => $plugin->is_enabled(),
			'oauth'       => $plugin->is_enabled(),
			'wordpress'   => $wp_version,
			'php'         => PHP_VERSION,
			'https'       => $plugin->url->is_https(),
			'endpoint'    => $plugin->url->mcp_endpoint(),
			'issuer'      => $plugin->url->issuer(),
			'tools'       => $plugin->is_enabled() ? count( $plugin->tools->all() ) : 0,
		);
		return $this->emit( $this->json_response( $body ) );
	}

	public function handle_mcp_endpoint( \WP_REST_Request $request ) {
		$plugin = $this->plugin;

		if ( in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
			return $this->emit(
				array(
					'status'  => 405,
					'headers' => array( 'Allow' => 'POST' ),
					'body'    => JsonRpc::error( null, JsonRpc::INVALID_REQUEST, 'This MCP endpoint accepts POST requests only.' ),
					'format'  => 'json',
				)
			);
		}

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? trim( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$cors_headers = array();
		if ( '' !== $origin ) {
			if ( ! $plugin->url->is_allowed_origin( $origin ) ) {
				return $this->emit(
					array(
						'status'  => 403,
						'headers' => array(),
						'body'    => JsonRpc::error( null, JsonRpc::FORBIDDEN, 'Origin not allowed.' ),
						'format'  => 'json',
					)
				);
			}
			$cors_headers['Access-Control-Allow-Origin']  = $origin;
			$cors_headers['Access-Control-Expose-Headers'] = 'WWW-Authenticate';
		}

		if ( ! $plugin->is_enabled() ) {
			return $this->emit(
				array(
					'status'  => 403,
					'headers' => $cors_headers,
					'body'    => JsonRpc::error( null, JsonRpc::DISABLED, 'The MCP server is disabled.' ),
					'format'  => 'json',
				)
			);
		}

		$raw           = (string) file_get_contents( 'php://input' );
		$json          = json_decode( $raw, true );
		$header_ver    = isset( $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ) ) : null;

		$response = $plugin->server->handle( $json, $header_ver );

		if ( 401 === $response['status'] ) {
			$response['headers']['WWW-Authenticate'] = $plugin->auth->challenge_header();
		}
		$response['headers'] = array_merge( $response['headers'], $cors_headers, array( 'Vary' => 'Origin' ) );
		if ( in_array( $response['status'], array( 401, 403 ), true ) ) {
			$response['headers']['Cache-Control'] = 'no-store';
		}

		return $this->emit( $response );
	}

	public function handle_oauth( \WP_REST_Request $request ) {
		$plugin   = $this->plugin;
		$endpoint = $request->get_param( 'endpoint' );

		if ( ! $plugin->is_enabled() ) {
			return $this->emit( $this->json_response( array( 'error' => 'not_found' ), 404 ) );
		}

		switch ( $endpoint ) {
			case 'authorize':
				$response = $plugin->oauth->handle_authorize();
				break;
			case 'token':
				$response = $plugin->oauth->handle_token();
				break;
			case 'register':
				$response = $plugin->registration->handle_register( $plugin->oauth->parse_body() );
				break;
			case 'revoke':
				$response = $plugin->oauth->handle_revoke();
				break;
			default:
				$response = $this->json_response( array( 'error' => 'not_found' ), 404 );
		}

		if ( isset( $response['format'] ) && 'json' === $response['format'] && in_array( $response['status'], array( 401, 429 ), true ) ) {
			$response['headers']['Cache-Control'] = 'no-store';
		}

		return $this->emit( $response );
	}

	public function handle_admin_rest( \WP_REST_Request $request ) {
		$plugin = $this->plugin;
		$action = $request->get_param( 'action' );
		$nonce  = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, $plugin->admin->ajax_nonce_action() ) ) {
			return $this->emit( $this->json_response( array( 'success' => false, 'message' => __( 'Invalid nonce.', 'mcp-connect-wp' ) ), 403 ) );
		}

		if ( 'test' === $action ) {
			$checks = $plugin->admin->run_diagnostics();
			return $this->emit( $this->json_response( array( 'success' => true, 'checks' => $checks ) ) );
		}
		if ( 'revoke' === $action ) {
			$auth_id = (int) $request->get_param( 'auth_id' );
			$plugin->tokens->revoke_authorization( $auth_id );
			return $this->emit( $this->json_response( array( 'success' => true ) ) );
		}
		if ( 'clear_logs' === $action ) {
			$plugin->logger->clear();
			return $this->emit( $this->json_response( array( 'success' => true ) ) );
		}

		return $this->emit( $this->json_response( array( 'success' => false ), 400 ) );
	}

	public function admin_rest_permission() {
		return current_user_can( 'manage_options' );
	}

	/* ------------------------------------------------------------------ *
	 *  Response plumbing
	 * ------------------------------------------------------------------ */

	private function json_response( array $body, $status = 200, array $headers = array() ) {
		return array(
			'status'  => $status,
			'headers' => $headers,
			'body'    => $body,
			'format'  => 'json',
		);
	}

	/**
	 * Stores a raw-style response for rest_pre_serve_request and returns null.
	 */
	private function emit( array $response ) {
		$format = isset( $response['format'] ) ? $response['format'] : ( '' === $response['body'] ? 'empty' : 'json' );
		self::$pending = array(
			'status'  => $response['status'],
			'headers' => $response['headers'],
			'format'  => $format,
			'body'    => $response['body'],
		);
		return null;
	}

	public function pre_serve( $served, $result, \WP_REST_Request $request, $server ) {
		if ( null === self::$pending && ! $this->is_our_route() ) {
			return $served;
		}
		if ( null === self::$pending ) {
			return $served;
		}

		$pending = self::$pending;
		self::$pending = null;

		status_header( $pending['status'] );
		foreach ( $pending['headers'] as $key => $value ) {
			if ( 'Content-Type' === $key ) {
				continue;
			}
			header( $key . ': ' . $value );
		}

		$content_type = isset( $pending['headers']['Content-Type'] ) ? $pending['headers']['Content-Type'] : '';

		if ( 'json' === $pending['format'] ) {
			header( 'Content-Type: ' . ( '' !== $content_type ? $content_type : 'application/json' ) );
			echo wp_json_encode( $pending['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} elseif ( 'html' === $pending['format'] ) {
			header( 'Content-Type: ' . ( '' !== $content_type ? $content_type : 'text/html; charset=' . get_bloginfo( 'charset' ) ) );
			echo $pending['body'];
		} elseif ( 'redirect' === $pending['format'] ) {
			// Location is already emitted by the generic header loop above.
			echo '';
		} else {
			echo '';
		}

		return true;
	}

	private function is_our_route() {
		$route = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';
		if ( '' === $route ) {
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
			$route = preg_replace( '#^.*?/(wp-json/?|index.php\?rest_route=/)([^?]*).*$#', '$2', $uri );
		}
		return ( strpos( $route, 'mcp-connect-wp/v1' ) !== false );
	}

	public function bypass_cookie_auth( $result ) {
		if ( $this->is_our_route() ) {
			return true;
		}
		return $result;
	}

	/* ------------------------------------------------------------------ *
	 *  CORS
	 * ------------------------------------------------------------------ */

	public function allow_cors_origins( $origin ) {
		if ( $origin && $this->plugin->url->is_allowed_origin( $origin ) ) {
			return $origin;
		}
		return false;
	}

	public function cors_headers( $headers ) {
		$allow = 'Authorization, X-WP-Nonce, Content-Type, Content-Disposition, Content-MD5';
		if ( is_array( $headers ) ) {
			$headers['Access-Control-Allow-Headers']  = $this->merge_header_list( $headers, 'Access-Control-Allow-Headers', $allow );
			$headers['Access-Control-Expose-Headers'] = $this->merge_header_list( $headers, 'Access-Control-Expose-Headers', 'WWW-Authenticate' );
			return $headers;
		}
		if ( is_string( $headers ) && '' !== $headers ) {
			return $headers . ', ' . $allow;
		}
		return $allow;
	}

	private function merge_header_list( $headers, $key, $new ) {
		$existing = isset( $headers[ $key ] ) && is_string( $headers[ $key ] ) ? $headers[ $key ] : '';
		return ( '' !== $existing ) ? $existing . ', ' . $new : $new;
	}

	public static function emit_json( $payload ) {
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
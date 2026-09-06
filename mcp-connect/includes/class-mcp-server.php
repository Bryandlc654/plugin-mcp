<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * MCP JSON-RPC dispatch for the Streamable HTTP transport.
 */
final class MCP_Server {

	const PROTOCOL_VERSIONS = array( '2025-03-26', '2025-06-18', '2025-11-25' );

	const METHOD_INITIALIZE = 'initialize';
	const METHOD_NOTIFICATION_INITIALIZED = 'notifications/initialized';
	const METHOD_PING        = 'ping';
	const METHOD_TOOLS_LIST  = 'tools/list';
	const METHOD_TOOLS_CALL  = 'tools/call';

	/** @var MCP_Tools */
	private $tools;

	/** @var MCP_Auth */
	private $auth;

	/** @var Logger */
	private $logger;

	/** @var Url_Manager */
	private $url;

	public function __construct( MCP_Tools $tools, MCP_Auth $auth, Logger $logger, Url_Manager $url ) {
		$this->tools   = $tools;
		$this->auth    = $auth;
		$this->logger  = $logger;
		$this->url     = $url;
	}

	/**
	 * Handles a single JSON-RPC message.
	 *
	 * @return array{status:int,headers:array,body:mixed}
	 */
	public function handle( $message, $header_protocol_version = null ) {
		$start = microtime( true );

		if ( ! $message ) {
			return self::http_response( JsonRpc::error( null, JsonRpc::PARSE_ERROR, 'Parse error. Expected a JSON-RPC 2.0 request.' ), 400 );
		}
		if ( is_array( $message ) && isset( $message[0] ) && ! isset( $message['jsonrpc'] ) ) {
			return self::http_response( JsonRpc::error( null, JsonRpc::INVALID_REQUEST, 'Batch requests are not supported.' ), 400 );
		}
		if ( ! JsonRpc::is_request( $message ) ) {
			return self::http_response( JsonRpc::error( null, JsonRpc::INVALID_REQUEST, 'Invalid Request.' ), 400 );
		}

		$method = $message['method'];
		$id     = array_key_exists( 'id', $message ) ? $message['id'] : null;
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

		if ( $header_protocol_version && in_array( $header_protocol_version, self::PROTOCOL_VERSIONS, true ) === false ) {
			return self::http_response(
				JsonRpc::error( $id, JsonRpc::INVALID_REQUEST, 'Unsupported protocol version.', $header_protocol_version ),
				400
			);
		}

		$actor = $this->auth->authenticate();
		if ( ! $actor ) {
			$this->finish_log( null, $method, 'error', 'unauthorized', $start );
			return self::http_response(
				JsonRpc::error( $id, JsonRpc::UNAUTHORIZED, 'MCP authorization required.' ),
				401,
				array( 'WWW-Authenticate' => $this->auth->challenge_header() )
			);
		}

		// Notifications carry no id and MUST NOT receive a response.
		if ( null === $id ) {
			return self::http_response( '', 202 );
		}

		switch ( $method ) {
			case self::METHOD_INITIALIZE:
				return self::http_response( $this->initialize( $id, $params ), 200 );

			case self::METHOD_PING:
				return self::http_response( JsonRpc::success( $id, new \stdClass() ), 200 );

			case self::METHOD_TOOLS_LIST:
			case self::METHOD_TOOLS_CALL:
				return self::http_response(
					$this->dispatch_authenticated( $method, $id, $params, $actor, $start ),
					200
				);

			default:
				return self::http_response( JsonRpc::error( $id, JsonRpc::METHOD_NOT_FOUND, 'Method not found.' ), 404 );
		}
	}

	private function dispatch_authenticated( $method, $id, array $params, array $actor, $start ) {
		if ( self::METHOD_TOOLS_LIST === $method ) {
			$server = Plugin::instance();
			$tools  = $this->tools->list_for_scopes( $actor['scopes'] );
			$this->finish_log( $actor['client_id'], $method, 'ok', 'ok', $start, $actor['user_id'] );
			return JsonRpc::success(
				$id,
				array(
					'tools' => array_values( $tools ),
				)
			);
		}

		if ( self::METHOD_TOOLS_CALL === $method ) {
			if ( ! isset( $params['name'] ) || ! is_string( $params['name'] ) ) {
				return JsonRpc::error( $id, JsonRpc::INVALID_PARAMS, 'Missing required parameter: name.' );
			}
			$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

			$result      = $this->tools->call( $params['name'], $arguments, $actor );
			$is_error    = ! empty( $result['isError'] );
			$error_code  = isset( $result['structuredContent']['error']['code'] ) ? $result['structuredContent']['error']['code'] : ( $is_error ? 'error' : 'ok' );
			$this->finish_log( $actor['client_id'], $params['name'], $is_error ? 'error' : 'ok', $error_code, $start, $actor['user_id'] );
			return JsonRpc::success( $id, $result );
		}

		return JsonRpc::error( $id, JsonRpc::METHOD_NOT_FOUND, 'Method not found.' );
	}

	private function initialize( $id, array $params ) {
		$requested = isset( $params['protocolVersion'] ) ? $params['protocolVersion'] : '';
		$version   = $this->negotiate_version( (string) $requested );

		return JsonRpc::success(
			$id,
			array(
				'protocolVersion' => $version,
				'capabilities'    => array(
					'tools' => array(
						'listChanged' => false,
					),
				),
				'serverInfo'      => array(
					'name'    => 'MCP Connect',
					'version' => MCP_CONNECT_VERSION,
				),
				'instructions'    => sprintf(
					__( 'You are connected to the WordPress site %s via MCP Connect. Use the available wp_* tools to help the user manage their WordPress content. Respect the user\'s capabilities and always ask before destructive actions.', 'mcp-connect' ),
					get_bloginfo( 'name' )
				),
			)
		);
	}

	private function negotiate_version( $requested ) {
		if ( in_array( $requested, self::PROTOCOL_VERSIONS, true ) ) {
			return $requested;
		}
		return '2025-11-25';
	}

	private function finish_log( $client_id, $method, $result, $code, $start, $user_id = 0 ) {
		$ms = (int) ( ( microtime( true ) - $start ) * 1000 );
		if ( 'error' === $result ) {
			$this->logger->error( $client_id, $user_id, $method, $code, $ms );
		} else {
			$this->logger->info( $client_id, $user_id, $method, $result, $ms );
		}
	}

	private static function http_response( $body, $status, array $headers = array() ) {
		return array(
			'status'  => $status,
			'headers' => $headers,
			'body'    => $body,
		);
	}
}
<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Client Registration (RFC 7591) and support for URL-form client IDs
 * that point to OAuth Client ID Metadata Documents.
 */
final class OAuth_Client_Registration {

	/** @var Token_Store */
	private $tokens;

	/** @var Url_Manager */
	private $url;

	/** @var Settings */
	private $settings;

	public function __construct( Token_Store $tokens, Url_Manager $url, Settings $settings ) {
		$this->tokens   = $tokens;
		$this->url      = $url;
		$this->settings = $settings;
	}

	/**
	 * @return array{status:int, headers:array, body:array, format:string}
	 */
	public function handle_register( array $params ) {
		if ( ! $this->settings->is_registration_enabled() ) {
			return array(
				'status'  => 403,
				'headers' => array(),
				'body'    => array( 'error' => 'registration_disabled', 'error_description' => __( 'Dynamic client registration is disabled.', 'mcp-connect-wp' ) ),
				'format'  => 'json',
			);
		}

		if ( ! Rate_Limiter::allowed( $this->url->get_client_ip(), 'register', 10 ) ) {
			return $this->error( 429, 'too_many_requests', __( 'Too many registration attempts. Try again later.', 'mcp-connect-wp' ) );
		}

		$redirect_uris = isset( $params['redirect_uris'] ) ? $params['redirect_uris'] : array();
		if ( ! is_array( $redirect_uris ) ) {
			return $this->error( 400, 'invalid_redirect_uri', __( 'redirect_uris must be an array of URIs.', 'mcp-connect-wp' ) );
		}
		if ( empty( $redirect_uris ) ) {
			return $this->error( 400, 'invalid_redirect_uri', __( 'At least one redirect URI is required.', 'mcp-connect-wp' ) );
		}

		$normalized = array();
		foreach ( $redirect_uris as $uri ) {
			if ( ! $this->url->is_valid_redirect_uri( $uri ) ) {
				return $this->error( 400, 'invalid_redirect_uri', __( 'Redirect URIs must be absolute HTTPS URIs, loopback HTTP URIs or custom app schemes without fragments.', 'mcp-connect-wp' ) );
			}
			$normalized[] = $uri;
		}
		$normalized = $this->url->normalize_redirect_variants( $normalized );

		$grant_types = isset( $params['grant_types'] ) ? $params['grant_types'] : array( 'authorization_code', 'refresh_token' );
		if ( ! is_array( $grant_types ) || empty( $grant_types ) ) {
			$grant_types = array( 'authorization_code' );
		}
		foreach ( $grant_types as $grant ) {
			if ( ! in_array( $grant, array( 'authorization_code', 'refresh_token' ), true ) ) {
				return $this->error( 400, 'invalid_client_metadata', __( 'Only authorization_code and refresh_token grants are supported.', 'mcp-connect-wp' ) );
			}
		}

		$response_types = isset( $params['response_types'] ) ? $params['response_types'] : array( 'code' );
		if ( ! is_array( $response_types ) || array_diff( $response_types, array( 'code' ) ) ) {
			return $this->error( 400, 'invalid_client_metadata', __( 'Only the code response type is supported.', 'mcp-connect-wp' ) );
		}

		$auth_method = isset( $params['token_endpoint_auth_method'] ) ? $params['token_endpoint_auth_method'] : 'none';
		if ( ! in_array( $auth_method, array( 'none', 'client_secret_basic', 'client_secret_post' ), true ) ) {
			return $this->error( 400, 'invalid_client_metadata', __( 'Unsupported token endpoint authentication method.', 'mcp-connect-wp' ) );
		}

		$scope = isset( $params['scope'] ) ? (string) $params['scope'] : $this->settings->default_scope_string();
		$parsed_scopes = Permissions::parse_scopes( $scope );
		$grantable     = $this->settings->grantable_scopes();
		if ( array_diff( $parsed_scopes, $grantable ) ) {
			return $this->error( 400, 'invalid_scope', __( 'The requested scope contains scopes that are not supported.', 'mcp-connect-wp' ) );
		}
		$scope = implode( ' ', $parsed_scopes );

		$client_name = isset( $params['client_name'] ) ? sanitize_text_field( $params['client_name'] ) : '';
		$client_id   = 'mcp_' . Crypto::random_hex( 20 );

		$secret       = '';
		$secret_hash  = '';
		if ( 'none' !== $auth_method ) {
			$secret      = 'mcp_' . Crypto::random_hex( 32 );
			$secret_hash = Crypto::hash( $secret );
		}

		$inserted = $this->tokens->register_client(
			array(
				'client_id'                  => $client_id,
				'client_name'                => $client_name,
				'client_secret_hash'         => $secret_hash,
				'redirect_uris'              => $normalized,
				'grant_types'                => array_values( $grant_types ),
				'response_types'             => array_values( $response_types ),
				'token_endpoint_auth_method' => $auth_method,
				'default_scope'              => $scope,
				'is_dynamic'                 => 1,
			)
		);
		if ( ! $inserted ) {
			return $this->error( 500, 'server_error', __( 'Could not store the client registration.', 'mcp-connect-wp' ) );
		}

		$response = array(
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'client_secret_expires_at'   => 0,
			'client_name'                => $client_name,
			'redirect_uris'              => $normalized,
			'grant_types'                => array_values( $grant_types ),
			'response_types'             => array_values( $response_types ),
			'token_endpoint_auth_method' => $auth_method,
			'scope'                      => $scope,
		);
		if ( 'none' !== $auth_method ) {
			$response['client_secret'] = $secret;
		}

		return array( 'status' => 201, 'headers' => array(), 'body' => $response, 'format' => 'json' );
	}

	/**
	 * Resolves a client_id to a normalized client record.
	 * Supports registered clients and URL-based Client ID Metadata Documents.
	 *
	 * @return object|null
	 */
	public function resolve_client( $client_id ) {
		if ( ! is_string( $client_id ) || '' === $client_id || strlen( $client_id ) > 512 ) {
			return null;
		}

		$stored = $this->tokens->get_client( $client_id );
		if ( $stored ) {
			return $stored;
		}

		if ( $this->looks_like_url( $client_id ) && $this->settings->get( 'allow_client_id_metadata_docs', true ) ) {
			return $this->fetch_client_id_document( $client_id );
		}

		return null;
	}

	public function looks_like_url( $value ) {
		$parts = wp_parse_url( $value );
		return ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) && 'https' === strtolower( $parts['scheme'] ) && $this->is_public_host( $parts['host'] );
	}

	private function is_public_host( $host ) {
		$host = strtolower( rtrim( (string) $host, '.' ) );
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return true;
			}
			return false;
		}
		if ( preg_match( '/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host ) ) {
			return false;
		}
		return true;
	}

	private function fetch_client_id_document( $url ) {
		$cache_key = 'mcp_connect_cimd_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || ! $this->is_public_host( $parts['host'] ) ) {
			set_transient( $cache_key, array(), 60 );
			return null;
		}

		$response = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 0, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $response ) ) {
			set_transient( $cache_key, array(), 60 );
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			set_transient( $cache_key, array(), 60 );
			return null;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) || ( isset( $json['client_id'] ) && $json['client_id'] !== $url ) ) {
			set_transient( $cache_key, array(), 60 );
			return null;
		}

		$redirect_uris = isset( $json['redirect_uris'] ) ? $json['redirect_uris'] : array();
		if ( ! is_array( $redirect_uris ) || empty( $redirect_uris ) ) {
			set_transient( $cache_key, array(), 60 );
			return null;
		}
		foreach ( $redirect_uris as $uri ) {
			if ( ! $this->url->is_valid_redirect_uri( $uri ) ) {
				set_transient( $cache_key, array(), 60 );
				return null;
			}
		}

		$client = (object) array(
			'client_id'                   => $url,
			'client_name'                 => isset( $json['client_name'] ) ? sanitize_text_field( $json['client_name'] ) : '',
			'client_secret_hash'          => '',
			'redirect_uris'               => $this->url->normalize_redirect_variants( $redirect_uris ),
			'grant_types'                 => array( 'authorization_code', 'refresh_token' ),
			'response_types'              => array( 'code' ),
			'token_endpoint_auth_method'  => 'none',
			'default_scope'               => '',
			'is_dynamic'                  => 0,
			'is_cimd'                     => true,
		);

		set_transient( $cache_key, $client, 5 * MINUTE_IN_SECONDS );
		return $client;
	}

	public function verify_client_secret( $client, $secret ) {
		if ( empty( $client->client_secret_hash ) ) {
			return false;
		}
		return hash_equals( $client->client_secret_hash, Crypto::hash( $secret ) );
	}

	public function authenticate_client_request( $client, array $params ) {
		$method = isset( $client->token_endpoint_auth_method ) ? $client->token_endpoint_auth_method : 'none';
		if ( 'none' === $method ) {
			return true;
		}

		$secret = null;
		if ( 'client_secret_post' === $method ) {
			$secret = isset( $params['client_secret'] ) ? (string) $params['client_secret'] : null;
		} elseif ( 'client_secret_basic' === $method ) {
			$auth  = '';
			if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
				$auth = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
			}
			if ( preg_match( '/^Basic\s+(.+)$/i', trim( $auth ), $matches ) ) {
				$decoded = base64_decode( $matches[1], true );
				if ( false !== $decoded && strpos( $decoded, ':' ) !== false ) {
					list( $id, $secret_candidate ) = explode( ':', $decoded, 2 );
					if ( $id === $client->client_id ) {
						$secret = $secret_candidate;
					}
				}
			}
		}

		if ( null === $secret ) {
			return false;
		}
		return $this->verify_client_secret( $client, $secret );
	}

	private function error( $status, $error, $description ) {
		return array(
			'status'  => $status,
			'headers' => array(),
			'body'    => array( 'error' => $error, 'error_description' => $description ),
			'format'  => 'json',
		);
	}
}
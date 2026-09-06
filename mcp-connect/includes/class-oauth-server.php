<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth 2.1 authorization server: consent, token, refresh and revocation.
 */
final class OAuth_Server {

	/** @var Token_Store */
	private $tokens;

	/** @var Url_Manager */
	private $url;

	/** @var Settings */
	private $settings;

	/** @var Logger */
	private $logger;

	/** @var OAuth_Client_Registration */
	private $registration;

	/** @var Discovery */
	private $discovery;

	public function __construct( Token_Store $tokens, Url_Manager $url, Settings $settings, Logger $logger, OAuth_Client_Registration $registration, Discovery $discovery ) {
		$this->tokens       = $tokens;
		$this->url          = $url;
		$this->settings     = $settings;
		$this->logger       = $logger;
		$this->registration = $registration;
		$this->discovery    = $discovery;
	}

	/* ------------------------------------------------------------------ *
	 *  Authorization endpoint
	 * ------------------------------------------------------------------ */

	public function handle_authorize() {
		if ( ! Rate_Limiter::allowed( $this->url->get_client_ip(), 'authorize', 30 ) ) {
			return $this->html( 429, __( 'Too many attempts. Please try again later.', 'mcp-connect' ) );
		}

		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$nonce = isset( $_POST['_mcp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_mcp_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'mcp_connect_authorize' ) ) {
				return $this->html( 403, __( 'Security check failed. Please reload the page and try again.', 'mcp-connect' ) );
			}
			return $this->process_consent();
		}

		return $this->show_consent();
	}

	private function fetch_consent_input() {
		$params = array(
			'response_type'        => isset( $_GET['response_type'] ) ? sanitize_text_field( wp_unslash( $_GET['response_type'] ) ) : '',
			'client_id'            => isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '',
			'redirect_uri'         => isset( $_GET['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_uri'] ) ) : '',
			'scope'                => isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : '',
			'state'                => isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '',
			'code_challenge'       => isset( $_GET['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge'] ) ) : '',
			'code_challenge_method' => isset( $_GET['code_challenge_method'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ) ) ) : '',
			'resource'             => isset( $_GET['resource'] ) ? esc_url_raw( wp_unslash( $_GET['resource'] ) ) : '',
		);
		return $params;
	}

	/**
	 * Validates an authorization request.
	 *
	 * @return array{ok:bool, data?:array, error_page?:string, error_redirect?:string}
	 */
	private function validate_authorize_request( array $params ) {
		if ( 'code' !== $params['response_type'] ) {
			return array( 'ok' => false, 'client' => null, 'redirect_uri' => null, 'error_page' => __( 'Only the "code" response type is supported.', 'mcp-connect' ) );
		}

		$client = $this->registration->resolve_client( $params['client_id'] );
		if ( ! $client ) {
			return array( 'ok' => false, 'client' => null, 'redirect_uri' => null, 'error_page' => __( 'Unknown client. Please reconnect your AI agent.', 'mcp-connect' ) );
		}

		$redirect_uri = $params['redirect_uri'];
		if ( ! in_array( $redirect_uri, $client->redirect_uris, true ) ) {
			return array( 'ok' => false, 'client' => $client, 'redirect_uri' => null, 'error_page' => __( 'The redirect URI does not match the registered client.', 'mcp-connect' ) );
		}

		if ( '' === $params['code_challenge'] || 'S256' !== $params['code_challenge_method'] ) {
			return array( 'ok' => false, 'client' => $client, 'redirect_uri' => $redirect_uri, 'error_uri' => $redirect_uri, 'error' => 'invalid_request', 'error_description' => 'PKCE with the S256 method is required.', 'state' => $params['state'] );
		}

		$resource = $this->url->normalize_resource( $params['resource'] );
		if ( null === $resource ) {
			return array( 'ok' => false, 'client' => $client, 'redirect_uri' => $redirect_uri, 'error_uri' => $redirect_uri, 'error' => 'invalid_request', 'error_description' => 'The requested resource is not valid.', 'state' => $params['state'] );
		}

		$scopes = $this->resolve_scopes( $params['scope'] );
		if ( null === $scopes ) {
			return array( 'ok' => false, 'client' => $client, 'redirect_uri' => $redirect_uri, 'error_uri' => $redirect_uri, 'error' => 'invalid_scope', 'error_description' => 'The requested scope is not supported.', 'state' => $params['state'] );
		}

		return array(
			'ok'           => true,
			'client'       => $client,
			'redirect_uri' => $redirect_uri,
			'resource'     => $resource,
			'scopes'       => $scopes,
			'state'        => $params['state'],
			'code_challenge' => $params['code_challenge'],
			'code_challenge_method' => $params['code_challenge_method'],
		);
	}

	private function resolve_scopes( $scope_string ) {
		$scopes = Permissions::parse_scopes( $scope_string );
		if ( empty( $scopes ) ) {
			return $this->settings->grantable_scopes();
		}
		$grantable = $this->settings->grantable_scopes();
		foreach ( $scopes as $scope ) {
			if ( ! in_array( $scope, $grantable, true ) ) {
				return null;
			}
		}
		return $scopes;
	}

	private function show_consent() {
		if ( ! is_user_logged_in() ) {
			return $this->redirect( wp_login_url( $this->url->oauth_endpoint( 'authorize' ) . '?' . http_build_query( array_map( 'wp_unslash', $_GET ) ) ) );
		}

		$params            = $this->fetch_consent_input();
		$validation        = $this->validate_authorize_request( $params );
		$validation['params'] = $params;
		if ( ! $validation['ok'] ) {
			if ( isset( $validation['error_uri'] ) ) {
				return $this->redirect( $this->build_error_redirect( $validation['error_uri'], $validation['state'], $validation['error'], $validation['error_description'] ) );
			}
			return $this->html( 400, $validation['error_page'] );
		}

		$user = wp_get_current_user();
		$data = array(
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => get_bloginfo( 'url' ),
			'user_name'    => $user ? $user->display_name : '',
			'user_email'   => $user ? $user->user_email : '',
			'avatar'       => $user ? get_avatar_url( $user->ID, array( 'size' => 64 ) ) : '',
			'client_name'  => $validation['client']->client_name ? $validation['client']->client_name : ( isset( $validation['client']->is_cimd ) ? $params['client_id'] : __( 'Unnamed client', 'mcp-connect' ) ),
			'client_id'    => $params['client_id'],
			'redirect_uri' => $validation['redirect_uri'],
			'state'        => $validation['state'],
			'scope_string' => implode( ' ', $validation['scopes'] ),
			'scopes'       => array_map(
				function ( $s ) {
					return array( 'scope' => $s, 'label' => Permissions::SCOPE_LABELS[ $s ] );
				},
				$validation['scopes']
			),
			'code_challenge' => $validation['code_challenge'],
			'code_challenge_method' => $validation['code_challenge_method'],
			'resource'     => $validation['resource'],
			'cancel_url'   => $this->build_error_redirect( $validation['redirect_uri'], $validation['state'], 'access_denied', 'The user denied the access request.' ),
		);

		return array(
			'status'  => 200,
			'headers' => array( 'Content-Type' => 'text/html; charset=' . get_bloginfo( 'charset' ) ),
			'body'    => $this->render_authorize_html( $data ),
			'format'  => 'html',
		);
	}

	private function process_consent() {
		if ( ! is_user_logged_in() ) {
			return $this->redirect( wp_login_url( $this->url->oauth_endpoint( 'authorize' ) ) );
		}

		$params = array(
			'response_type'        => isset( $_POST['response_type'] ) ? sanitize_text_field( wp_unslash( $_POST['response_type'] ) ) : '',
			'client_id'            => isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			'redirect_uri'         => isset( $_POST['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_uri'] ) ) : '',
			'scope'                => isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : '',
			'state'                => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'code_challenge'       => isset( $_POST['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_POST['code_challenge'] ) ) : '',
			'code_challenge_method' => isset( $_POST['code_challenge_method'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['code_challenge_method'] ) ) ) : '',
			'resource'             => isset( $_POST['resource'] ) ? esc_url_raw( wp_unslash( $_POST['resource'] ) ) : '',
			'action'               => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
		);

		if ( 'cancel' === $params['action'] ) {
			$client       = $this->registration->resolve_client( $params['client_id'] );
			$redirect_uri = $params['redirect_uri'];
			if ( $client && in_array( $redirect_uri, $client->redirect_uris, true ) ) {
				return $this->redirect( $this->build_error_redirect( $redirect_uri, $params['state'], 'access_denied', 'The user denied the access request.' ) );
			}
			return $this->html( 400, __( 'The request could not be processed.', 'mcp-connect' ) );
		}

		$validation = $this->validate_authorize_request( $params );
		if ( ! $validation['ok'] ) {
			if ( isset( $validation['error_page'] ) ) {
				return $this->html( 400, $validation['error_page'] );
			}
			return $this->redirect( $this->build_error_redirect( $validation['error_uri'], $validation['state'], $validation['error'], $validation['error_description'] ) );
		}

		$user      = wp_get_current_user();
		$raw_code  = Crypto::random_token( 32 );
		$expires   = time() + $this->settings->code_ttl();
		$inserted  = $this->tokens->create_code(
			array(
				'code_hash'            => Crypto::hash( $raw_code ),
				'client_id'            => $params['client_id'],
				'user_id'              => $user->ID,
				'redirect_uri'         => $params['redirect_uri'],
				'scopes'               => implode( ' ', $validation['scopes'] ),
				'resource'             => $validation['resource'],
				'code_challenge'       => $params['code_challenge'],
				'code_challenge_method' => $params['code_challenge_method'],
				'expires_at'           => gmdate( 'Y-m-d H:i:s', $expires ),
			)
		);
		if ( ! $inserted ) {
			return $this->html( 500, __( 'Could not create the authorization code.', 'mcp-connect' ) );
		}

		$this->logger->info( $params['client_id'], $user->ID, 'oauth/authorize', 'approved' );

		$query = array( 'code' => $raw_code, 'iss' => $this->url->issuer() );
		if ( '' !== $params['state'] ) {
			$query['state'] = $params['state'];
		}
		return $this->redirect( add_query_arg( $query, $params['redirect_uri'] ) );
	}

	private function build_error_redirect( $redirect_uri, $state, $error, $description = '' ) {
		$query = array( 'error' => $error );
		if ( '' !== $state ) {
			$query['state'] = $state;
		}
		if ( '' !== $description ) {
			$query['error_description'] = $description;
		}
		return add_query_arg( $query, $redirect_uri );
	}

	private function render_authorize_html( array $data ) {
		ob_start();
		include MCP_CONNECT_DIR . 'admin/views/authorize.php';
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ *
	 *  Token endpoint
	 * ------------------------------------------------------------------ */

	public function handle_token() {
		if ( ! Rate_Limiter::allowed( $this->url->get_client_ip(), 'token', 30 ) ) {
			return $this->json( 429, array( 'error' => 'too_many_requests', 'error_description' => __( 'Too many attempts.', 'mcp-connect' ) ), array( 'Retry-After' => '60' ) );
		}

		$params = $this->parse_token_body();
		$grant  = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';
		$client_id = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
		$client    = '' !== $client_id ? $this->registration->resolve_client( $client_id ) : null;

		if ( ! $client ) {
			return $this->json( 401, array( 'error' => 'invalid_client', 'error_description' => 'Unknown client.' ) );
		}
		if ( ! $this->registration->authenticate_client_request( $client, $params ) ) {
			return $this->json( 401, array( 'error' => 'invalid_client', 'error_description' => 'Client authentication failed.' ) );
		}

		if ( 'authorization_code' === $grant ) {
			return $this->grant_authorization_code( $params, $client );
		}
		if ( 'refresh_token' === $grant ) {
			return $this->grant_refresh_token( $params, $client );
		}

		return $this->json( 400, array( 'error' => 'unsupported_grant_type', 'error_description' => __( 'The grant type is not supported.', 'mcp-connect' ) ) );
	}

	private function grant_authorization_code( array $params, $client ) {
		$code        = isset( $params['code'] ) ? (string) $params['code'] : '';
		$verifier    = isset( $params['code_verifier'] ) ? (string) $params['code_verifier'] : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		$resource    = isset( $params['resource'] ) ? (string) $params['resource'] : '';

		if ( '' === $code || '' === $verifier ) {
			return $this->json( 400, array( 'error' => 'invalid_request', 'error_description' => __( 'Missing code or code_verifier.', 'mcp-connect' ) ) );
		}

		$row = $this->tokens->consume_code( $code );
		if ( ! $row || $row->client_id !== $client->client_id ) {
			return $this->json( 400, array( 'error' => 'invalid_grant', 'error_description' => __( 'The authorization code is invalid or has already been used.', 'mcp-connect' ) ) );
		}
		if ( $row->redirect_uri !== $redirect_uri ) {
			return $this->json( 400, array( 'error' => 'invalid_grant', 'error_description' => __( 'The redirect URI does not match the authorization request.', 'mcp-connect' ) ) );
		}

		$normalized_resource = $this->url->normalize_resource( $resource );
		if ( null === $normalized_resource ) {
			return $this->json( 400, array( 'error' => 'invalid_request', 'error_description' => __( 'The resource is not valid.', 'mcp-connect' ) ) );
		}
		if ( $row->resource && strcasecmp( rtrim( $row->resource, '/' ), rtrim( $normalized_resource, '/' ) ) !== 0 ) {
			$this->tokens->revoke_client_user( $row->client_id, (int) $row->user_id );
			return $this->json( 400, array( 'error' => 'invalid_grant', 'error_description' => __( 'The resource does not match the authorization request.', 'mcp-connect' ) ) );
		}

		if ( ! Crypto::pkce_verify( $verifier, $row->code_challenge ) ) {
			$this->tokens->revoke_client_user( $row->client_id, (int) $row->user_id );
			return $this->json( 400, array( 'error' => 'invalid_grant', 'error_description' => __( 'Invalid PKCE code verifier.', 'mcp-connect' ) ) );
		}

		$scopes = array_filter( array_map( 'trim', preg_split( '/\s+/', $row->scopes ) ) );
		$auth_id = $this->tokens->create_authorization( $row->client_id, (int) $row->user_id, $scopes, $row->resource );
		$tokens  = $this->tokens->create_tokens( $auth_id, $row->client_id, (int) $row->user_id, $scopes, $row->resource, true );

		$this->logger->info( $row->client_id, (int) $row->user_id, 'oauth/token', 'issued' );
		$body           = $this->token_response( $tokens );
		$body['iss']    = $this->url->issuer();
		return $this->json( 200, $body );
	}

	private function grant_refresh_token( array $params, $client ) {
		$refresh  = isset( $params['refresh_token'] ) ? (string) $params['refresh_token'] : '';
		$resource = isset( $params['resource'] ) ? (string) $params['resource'] : '';
		if ( '' === $refresh ) {
			return $this->json( 400, array( 'error' => 'invalid_request', 'error_description' => __( 'Missing refresh_token.', 'mcp-connect' ) ) );
		}

		$normalized_resource = $this->url->normalize_resource( $resource );
		if ( null === $normalized_resource ) {
			return $this->json( 400, array( 'error' => 'invalid_request', 'error_description' => __( 'The resource is not valid.', 'mcp-connect' ) ) );
		}

		$changed = $this->tokens->refresh( $refresh, $client->client_id, $resource );
		if ( is_wp_error( $changed ) ) {
			if ( 'invalid_grant' === $changed->get_error_code() ) {
				return $this->json( 400, array( 'error' => 'invalid_grant', 'error_description' => $changed->get_error_message() ) );
			}
			return $this->json( 401, array( 'error' => 'invalid_client', 'error_description' => $changed->get_error_message() ) );
		}

		$body = $this->token_response( $changed );
		$body['iss'] = $this->url->issuer();
		return $this->json( 200, $body );
	}

	private function token_response( array $tokens ) {
		return array(
			'access_token'  => $tokens['access_token'],
			'token_type'    => 'Bearer',
			'expires_in'    => $tokens['expires_in'],
			'scope'         => $tokens['scope'],
			'refresh_token' => $tokens['refresh_token'],
		);
	}

	/* ------------------------------------------------------------------ *
	 *  Revocation endpoint  (RFC 7009)
	 * ------------------------------------------------------------------ */

	public function handle_revoke() {
		$params = $this->parse_token_body();
		$token  = isset( $params['token'] ) ? (string) $params['token'] : '';
		if ( '' !== $token ) {
			$this->tokens->revoke_token( $token );
			$client_id = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
			$this->logger->info( $client_id, 0, 'oauth/revoke', 'ok' );
		}
		return array( 'status' => 200, 'headers' => array(), 'body' => '', 'format' => 'empty' );
	}

	/* ------------------------------------------------------------------ *
	 *  Input / output helpers
	 * ------------------------------------------------------------------ */

	private function parse_token_body() {
		$raw = (string) file_get_contents( 'php://input' );
		$ct  = isset( $_SERVER['CONTENT_TYPE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) : '';
		if ( false !== strpos( $ct, 'application/json' ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		$params = array();
		parse_str( $raw, $params );
		return is_array( $params ) ? $params : array();
	}

	/**
	 * Parses a form-encoded or JSON request body into an associative array.
	 * Used by the DCR endpoint (oauth/register).
	 */
	public function parse_body() {
		return $this->parse_token_body();
	}

	private function json( $status, array $body, array $headers = array() ) {
		return array( 'status' => $status, 'headers' => $headers, 'body' => $body, 'format' => 'json' );
	}

	private function html( $status, $message ) {
		$charset = get_bloginfo( 'charset' );
		$body    = '<!DOCTYPE html><html lang="' . esc_attr( get_locale() ) . '"><head><meta charset="' . esc_attr( $charset ) . '"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__( 'MCP Connect', 'mcp-connect' ) . '</title>';
		$body   .= '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;color:#1d2327}main{background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.12);padding:32px;max-width:460px;width:90%;text-align:center}h1{font-size:20px;margin:0 0 8px}p{color:#50575e;line-height:1.5;margin:0}</style></head><body><main><h1>' . esc_html( $message ) . '</h1><p><a href="' . esc_url( $this->url->base() . '/wp-admin/' ) . '">' . esc_html__( 'Back to WordPress', 'mcp-connect' ) . '</a></p></main></body></html>';
		return array( 'status' => $status, 'headers' => array( 'Content-Type' => 'text/html; charset=' . $charset ), 'body' => $body, 'format' => 'html' );
	}

	private function redirect( $location ) {
		return array(
			'status'  => 302,
			'headers' => array( 'Location' => $location ),
			'body'    => '',
			'format'  => 'redirect',
		);
	}
}
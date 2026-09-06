<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Validates bearer access tokens presented to the MCP resource server and
 * enforces the RFC 8707 audience (resource).
 */
final class MCP_Auth {

	/** @var Token_Store */
	private $tokens;

	/** @var Url_Manager */
	private $url;

	public function __construct( Token_Store $tokens, Url_Manager $url ) {
		$this->tokens = $tokens;
		$this->url    = $url;
	}

	public static function get_bearer_token() {
		$auth = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		} elseif ( function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $key => $value ) {
				if ( 'authorization' === strtolower( (string) $key ) ) {
					$auth = $value;
					break;
				}
			}
		}

		if ( preg_match( '/^Bearer\s+(.+)$/i', trim( (string) $auth ), $matches ) ) {
			$token = preg_replace( '/[^A-Za-z0-9\-._~]/', '', $matches[1] );
			return '' === $token ? null : $token;
		}
		return null;
	}

	/**
	 * @return array|null Actor array or null when the token is missing/invalid.
	 */
	public function authenticate() {
		$token = self::get_bearer_token();
		if ( null === $token ) {
			return null;
		}

		$row = $this->tokens->find_token( $token, 'access' );
		if ( ! $row ) {
			return null;
		}

		$resource = $row->resource ? $row->resource : $this->url->mcp_endpoint();
		if ( null === $this->url->normalize_resource( $resource ) ) {
			return null;
		}

		if ( $this->tokens->authorization_revoked( (int) $row->auth_id ) ) {
			return null;
		}

		$this->tokens->touch_authorization( (int) $row->auth_id );

		return array(
			'token_row' => $row,
			'auth_id'   => (int) $row->auth_id,
			'user_id'   => (int) $row->user_id,
			'client_id' => $row->client_id,
			'scopes'    => array_filter( array_map( 'trim', preg_split( '/\s+/', $row->scopes ) ) ),
		);
	}

	/**
	 * Builds the WWW-Authenticate challenge header value for 401 responses.
	 */
	public function challenge_header() {
		$url   = $this->url;
		$parts = array(
			sprintf( 'Bearer resource_metadata="%s"', $url->well_known_url( 'oauth-protected-resource' ) ),
			sprintf( 'authorization_server="%s"', $url->well_known_url( 'oauth-authorization-server' ) ),
		);
		if ( Plugin::instance()->settings->get( 'scope_in_www_authenticate', true ) ) {
			$scopes = Plugin::instance()->settings->grantable_scopes();
			if ( $scopes ) {
				$parts[] = sprintf( 'scope="%s"', implode( ' ', $scopes ) );
			}
		}
		return implode( ', ', $parts );
	}
}
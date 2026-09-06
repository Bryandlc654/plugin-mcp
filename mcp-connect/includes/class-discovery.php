<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth discovery documents: RFC 9728 (protected resource metadata)
 * and RFC 8414 (authorization server metadata).
 */
final class Discovery {

	/** @var Settings */
	private $settings;

	/** @var Url_Manager */
	private $url;

	public function __construct( Settings $settings, Url_Manager $url ) {
		$this->settings = $settings;
		$this->url      = $url;
	}

	public function protected_resource_document() {
		return array(
			'resource'              => $this->url->mcp_endpoint(),
			'authorization_servers' => array( $this->url->issuer() ),
			'scopes_supported'      => $this->settings->grantable_scopes(),
			'bearer_methods_supported' => array( 'header' ),
		);
	}

	public function authorization_server_document() {
		$doc = array(
			'issuer'                                    => $this->url->issuer(),
			'authorization_endpoint'                    => $this->url->oauth_endpoint( 'authorize' ),
			'token_endpoint'                            => $this->url->oauth_endpoint( 'token' ),
			'response_types_supported'                  => array( 'code' ),
			'grant_types_supported'                     => array( 'authorization_code', 'refresh_token' ),
			'token_endpoint_auth_methods_supported'     => array( 'none', 'client_secret_basic', 'client_secret_post' ),
			'code_challenge_methods_supported'          => array( 'S256' ),
			'scopes_supported'                          => $this->settings->grantable_scopes(),
			'authorization_response_iss_parameter_supported' => true,
			'revocation_endpoint'                       => $this->url->oauth_endpoint( 'revoke' ),
		);

		if ( $this->settings->is_registration_enabled() ) {
			$doc['registration_endpoint'] = $this->url->oauth_endpoint( 'register' );
		}

		return $doc;
	}
}
<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence layer for OAuth clients, authorization codes, tokens and grants.
 */
final class Token_Store {

	public function register_client( array $data ) {
		global $wpdb;
		$created = current_time( 'mysql', true );
		$ok      = $wpdb->insert(
			Install::table( 'clients' ),
			array(
				'client_id'                 => $data['client_id'],
				'client_name'               => isset( $data['client_name'] ) ? $data['client_name'] : '',
				'client_secret_hash'        => isset( $data['client_secret_hash'] ) ? $data['client_secret_hash'] : '',
				'redirect_uris'             => isset( $data['redirect_uris'] ) ? wp_json_encode( $data['redirect_uris'] ) : null,
				'grant_types'               => isset( $data['grant_types'] ) ? wp_json_encode( $data['grant_types'] ) : null,
				'response_types'            => isset( $data['response_types'] ) ? wp_json_encode( $data['response_types'] ) : null,
				'token_endpoint_auth_method' => isset( $data['token_endpoint_auth_method'] ) ? $data['token_endpoint_auth_method'] : 'none',
				'default_scope'             => isset( $data['default_scope'] ) ? $data['default_scope'] : '',
				'is_dynamic'                => isset( $data['is_dynamic'] ) ? (int) $data['is_dynamic'] : 1,
				'created_at'                => $created,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( false === $ok && ! empty( $wpdb->last_error ) ) {
			// Likely a duplicate client_id on a re-registration; attempt upsert.
			$existing = $this->get_client( $data['client_id'] );
			if ( $existing ) {
				return true;
			}
		}
		return (bool) $ok;
	}

	public function get_client( $client_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'clients' ) . ' WHERE client_id = %s', $client_id ) );
		if ( ! $row ) {
			return null;
		}
		$row->redirect_uris = $row->redirect_uris ? (array) json_decode( $row->redirect_uris, true ) : array();
		$row->grant_types   = $row->grant_types ? (array) json_decode( $row->grant_types, true ) : array();
		$row->response_types = $row->response_types ? (array) json_decode( $row->response_types, true ) : array();
		return $row;
	}

	public function touch_client( $client_id ) {
		global $wpdb;
		$wpdb->update(
			Install::table( 'clients' ),
			array( 'last_used_at' => current_time( 'mysql', true ) ),
			array( 'client_id' => $client_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	public function client_redirect_uris( $client_id ) {
		$client = $this->get_client( $client_id );
		return $client ? $client->redirect_uris : array();
	}

	public function create_code( array $fields ) {
		global $wpdb;
		return (bool) $wpdb->insert(
			Install::table( 'codes' ),
			array(
				'code_hash'            => $fields['code_hash'],
				'client_id'            => $fields['client_id'],
				'user_id'              => $fields['user_id'],
				'redirect_uri'         => $fields['redirect_uri'],
				'scopes'               => $fields['scopes'],
				'resource'             => $fields['resource'],
				'code_challenge'       => $fields['code_challenge'],
				'code_challenge_method' => $fields['code_challenge_method'],
				'expires_at'           => $fields['expires_at'],
				'used'                 => 0,
				'created_at'           => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Atomically consume a single-use authorization code.
	 *
	 * @return object|null code row on success, null when missing/used/expired.
	 */
	public function consume_code( $code ) {
		global $wpdb;
		$hash  = Crypto::hash( $code );
		$table = Install::table( 'codes' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code_hash = %s", $hash ) );
		if ( ! $row ) {
			return null;
		}
		if ( (int) $row->used ) {
			$this->revoke_client_user( $row->client_id, (int) $row->user_id );
			return null;
		}
		if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return null;
		}

		$updated = $wpdb->update( $table, array( 'used' => 1 ), array( 'id' => $row->id, 'used' => 0 ), array( '%d' ), array( '%d', '%d' ) );
		if ( 0 === $updated || false === $updated ) {
			if ( 0 === $updated ) {
				// Lost a race against a concurrent exchange: the code is no
				// longer valid for us, treat it as reuse and revoke the family.
				$this->revoke_client_user( $row->client_id, (int) $row->user_id );
			}
			return null;
		}
		return $row;
	}

	public function create_authorization( $client_id, $user_id, $scopes, $resource ) {
		global $wpdb;
		$wpdb->insert(
			Install::table( 'authorizations' ),
			array(
				'client_id'  => $client_id,
				'user_id'    => (int) $user_id,
				'scopes'     => is_array( $scopes ) ? implode( ' ', $scopes ) : (string) $scopes,
				'resource'   => $resource,
				'granted_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function create_tokens( $auth_id, $client_id, $user_id, $scopes, $resource, $issue_refresh = true ) {
		global $wpdb;
		$family   = md5( uniqid( '', true ) . Crypto::random_hex( 8 ) );
		$access   = Crypto::random_token( 32 );
		$table    = Install::table( 'tokens' );
		$exp      = time() + Plugin::instance()->settings->access_token_ttl();
		$exp_mysql = gmdate( 'Y-m-d H:i:s', $exp );

		$wpdb->insert(
			$table,
			array(
				'token_hash' => Crypto::hash( $access ),
				'type'       => 'access',
				'family_id'  => $family,
				'auth_id'    => (int) $auth_id,
				'client_id'  => $client_id,
				'user_id'    => (int) $user_id,
				'scopes'     => implode( ' ', $scopes ),
				'resource'   => $resource,
				'expires_at' => $exp_mysql,
				'revoked'    => 0,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		$refresh = null;
		if ( $issue_refresh ) {
			$refresh         = Crypto::random_token( 32 );
			$refresh_exp_mysql = gmdate( 'Y-m-d H:i:s', time() + Plugin::instance()->settings->refresh_token_ttl() );
			$wpdb->insert(
				$table,
				array(
					'token_hash' => Crypto::hash( $refresh ),
					'type'       => 'refresh',
					'family_id'  => $family,
					'auth_id'    => (int) $auth_id,
					'client_id'  => $client_id,
					'user_id'    => (int) $user_id,
					'scopes'     => implode( ' ', $scopes ),
					'resource'   => $resource,
					'expires_at' => $refresh_exp_mysql,
					'revoked'    => 0,
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
			);
		}

		$expires_in = $exp - time();
		return array(
			'access_token'  => $access,
			'expires_in'    => $expires_in,
			'refresh_token' => $refresh,
			'scope'         => implode( ' ', $scopes ),
		);
	}

	public function find_token( $token, $type = 'access' ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Install::table( 'tokens' ) . ' WHERE token_hash = %s', Crypto::hash( $token ) )
		);
		if ( ! $row || $row->type !== $type ) {
			return null;
		}
		if ( (int) $row->revoked ) {
			return null;
		}
		if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return null;
		}
		return $row;
	}

	public function refresh( $refresh_token, $client_id, $resource = null ) {
		global $wpdb;
		$table = Install::table( 'tokens' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", Crypto::hash( $refresh_token ) ) );
		if ( ! $row || 'refresh' !== $row->type ) {
			return new \WP_Error( 'invalid_grant', 'The provided refresh token is not valid.' );
		}
		if ( $row->client_id !== $client_id ) {
			return new \WP_Error( 'invalid_grant', 'The refresh token does not belong to this client.' );
		}
		if ( (int) $row->revoked ) {
			$this->revoke_family( $row->family_id );
			return new \WP_Error( 'invalid_grant', 'The refresh token has been revoked.' );
		}
		if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return new \WP_Error( 'invalid_grant', 'The refresh token has expired.' );
		}
		if ( $this->authorization_revoked( (int) $row->auth_id ) ) {
			return new \WP_Error( 'invalid_grant', 'The authorization has been revoked.' );
		}
		if ( $resource ) {
			$stored = $row->resource ? $row->resource : Plugin::instance()->url->mcp_endpoint();
			if ( strcasecmp( rtrim( $stored, '/' ), rtrim( $resource, '/' ) ) !== 0 ) {
				return new \WP_Error( 'invalid_grant', 'The resource does not match the original grant.' );
			}
		}

		$this->revoke_family( $row->family_id );

		$scopes   = array_filter( array_map( 'trim', preg_split( '/\s+/', $row->scopes ) ) );
		$resource = $row->resource ? $row->resource : Plugin::instance()->url->mcp_endpoint();
		$tokens   = $this->create_tokens( (int) $row->auth_id, $row->client_id, (int) $row->user_id, $scopes, $resource, true );
		$this->touch_authorization( (int) $row->auth_id );

		return $tokens;
	}

	public function revoke_token( $token ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'tokens' ) . ' WHERE token_hash = %s', Crypto::hash( $token ) ) );
		if ( ! $row ) {
			return false;
		}
		if ( 'refresh' === $row->type ) {
			$this->revoke_family( $row->family_id );
			return true;
		}
		$wpdb->update(
			Install::table( 'tokens' ),
			array( 'revoked' => 1 ),
			array( 'id' => $row->id ),
			array( '%d' ),
			array( '%d' )
		);
		return true;
	}

	public function revoke_family( $family_id ) {
		global $wpdb;
		$wpdb->update(
			Install::table( 'tokens' ),
			array( 'revoked' => 1 ),
			array( 'family_id' => $family_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	public function revoke_client_user( $client_id, $user_id ) {
		global $wpdb;
		$table = Install::table( 'tokens' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revoked = 1 WHERE client_id = %s AND user_id = %d", $client_id, $user_id ) );
	}

	public function touch_authorization( $auth_id ) {
		global $wpdb;
		$wpdb->update(
			Install::table( 'authorizations' ),
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $auth_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public function authorization_revoked( $auth_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT revoked FROM ' . Install::table( 'authorizations' ) . ' WHERE id = %d', $auth_id ) );
		return ! $row || (int) $row->revoked;
	}

	public function revoke_authorization( $auth_id ) {
		global $wpdb;
		$table = Install::table( 'authorizations' );
		$arg   = Install::table( 'tokens' );
		$wpdb->update( $table, array( 'revoked' => 1 ), array( 'id' => (int) $auth_id ), array( '%d' ), array( '%d' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$arg} SET revoked = 1 WHERE auth_id = %d", (int) $auth_id ) );
	}

	public function list_authorizations_for_user( $user_id, $limit = 100, $offset = 0 ) {
		global $wpdb;
		$table = Install::table( 'authorizations' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY granted_at DESC LIMIT %d OFFSET %d", (int) $user_id, (int) $limit, (int) $offset )
		);
	}

	public function list_all_authorizations() {
		global $wpdb;
		$table = Install::table( 'authorizations' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY granted_at DESC LIMIT 500" );
	}

	public function list_clients( $limit = 200, $offset = 0 ) {
		global $wpdb;
		$table = Install::table( 'clients' );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", (int) $limit, (int) $offset )
		);
	}

	public function delete_client( $client_id ) {
		global $wpdb;
		$clients  = Install::table( 'clients' );
		$codes    = Install::table( 'codes' );
		$tokens   = Install::table( 'tokens' );
		$auths    = Install::table( 'authorizations' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$codes} WHERE client_id = %s", $client_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tokens} WHERE client_id = %s", $client_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$auths} WHERE client_id = %s", $client_id ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$clients} WHERE client_id = %s", $client_id ) );
	}

	public function get_authorization( $auth_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Install::table( 'authorizations' ) . ' WHERE id = %d', (int) $auth_id ) );
	}

	public function prune_expired() {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Install::table( 'codes' ) . ' WHERE expires_at < %s', $now ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Install::table( 'tokens' ) . " WHERE expires_at < %s AND ( type = 'access' OR revoked = 1 )", $now ) );
	}
}
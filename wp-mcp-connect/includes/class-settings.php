<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION = 'wp_mcp_connect_settings';

	const CATEGORIES = array( 'posts', 'pages', 'media', 'comments', 'taxonomies', 'users', 'site' );
	const MODES      = array( 'read', 'write', 'delete' );

	public function get( $key = null, $default = null ) {
		$option = get_option( self::OPTION, null );
		if ( ! is_array( $option ) ) {
			$option = self::defaults();
		}
		$option = wp_parse_args( $option, self::defaults() );
		if ( null === $key ) {
			return $option;
		}
		return array_key_exists( $key, $option ) ? $option[ $key ] : $default;
	}

	public function update( array $values ) {
		$current = (array) get_option( self::OPTION, array() );
		$merged  = wp_parse_args( $values, $current );
		update_option( self::OPTION, $merged, false );
	}

	public function scope_enabled( $category, $mode ) {
		$matrix = (array) $this->get( 'scopes', array() );
		return ! empty( $matrix[ $category ][ $mode ] );
	}

	public function grantable_scopes() {
		$matrix = (array) $this->get( 'scopes', array() );

		// If the matrix is missing or entirely empty/false (e.g. a partial
		// install or an accidental save), fall back to the defaults: read/write
		// on for content, delete off.
		$has_any = false;
		foreach ( self::CATEGORIES as $cat ) {
			foreach ( self::MODES as $mode ) {
				if ( isset( $matrix[ $cat ][ $mode ] ) && $matrix[ $cat ][ $mode ] ) {
					$has_any = true;
					break 2;
				}
			}
		}
		if ( ! $has_any ) {
			$matrix = self::defaults()['scopes'];
		}

		$scopes = array();
		foreach ( self::CATEGORIES as $cat ) {
			foreach ( self::MODES as $mode ) {
				if ( 'comments' === $cat && 'delete' === $mode ) {
					continue;
				}
				if ( 'users' === $cat && 'delete' === $mode ) {
					continue;
				}
				if ( 'site' === $cat && 'write' === $mode ) {
					continue;
				}
				if ( 'site' === $cat && 'delete' === $mode ) {
					continue;
				}
				if ( ! empty( $matrix[ $cat ][ $mode ] ) ) {
					$scopes[] = $cat . ':' . $mode;
				}
			}
		}
		return $scopes;
	}

	public function default_scope_string() {
		return implode( ' ', $this->grantable_scopes() );
	}

	public function log_level() {
		return $this->get( 'log_level', 'errors' );
	}

	public function is_enabled() {
		return (bool) $this->get( 'enabled', true );
	}

	public function is_registration_enabled() {
		return (bool) $this->get( 'enable_registration', true );
	}

	public function access_token_ttl() {
		return (int) $this->get( 'access_token_ttl', HOUR_IN_SECONDS );
	}

	public function refresh_token_ttl() {
		return (int) $this->get( 'refresh_token_ttl', 30 * DAY_IN_SECONDS );
	}

	public function code_ttl() {
		return (int) $this->get( 'code_ttl', 600 );
	}

	public function trust_proxy_headers() {
		return (bool) $this->get( 'trust_proxy_headers', false );
	}

	public static function defaults() {
		$scopes = array();
		foreach ( self::CATEGORIES as $cat ) {
			$scopes[ $cat ] = array(
				'read'   => true,
				'write'  => true,
				'delete' => false,
			);
		}

		return array(
			'enabled'                      => true,
			'enable_registration'          => true,
			'allow_client_id_metadata_docs' => true,
			'scopes'                       => $scopes,
			'access_token_ttl'             => HOUR_IN_SECONDS,
			'refresh_token_ttl'            => 30 * DAY_IN_SECONDS,
			'code_ttl'                     => 600,
			'log_level'                    => 'errors',
			'delete_on_uninstall'          => false,
			'trust_proxy_headers'          => false,
			'scope_in_www_authenticate'    => true,
			'cors_origins'                 => array(),
			'rate_limit'                   => true,
		);
	}
}
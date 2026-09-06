<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Central place for URL building, issuer/resource computation, redirect_uri
 * policy and origin validation. All absolute URLs derive from WordPress
 * options (home_url/site_url/rest_url); untrusted headers are never used
 * unless the administrator opts into proxy trust.
 */
class Url_Manager {

	/** @var Settings */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register() {}

	public function base() {
		return untrailingslashit( get_home_url() );
	}

	public function site_base() {
		return untrailingslashit( get_site_url() );
	}

	public function scheme() {
		return is_ssl() ? 'https' : 'http';
	}

	public function origin() {
		$parts = wp_parse_url( $this->base() );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}
		return $origin;
	}

	public function is_https() {
		return ( substr( $this->base(), 0, 8 ) === 'https://' );
	}

	public function mcp_endpoint( $trailing = false ) {
		$url = untrailingslashit( get_rest_url( null, 'mcp-connect/v1/mcp' ) );
		return $trailing ? $url . '/' : $url;
	}

	public function oauth_endpoint( $endpoint ) {
		return get_rest_url( null, 'mcp-connect/v1/oauth/' . $endpoint );
	}

	public function well_known_url( $doc ) {
		return untrailingslashit( get_rest_url( null, 'mcp-connect/v1/.well-known/' . $doc ) );
	}

	public function issuer() {
		return $this->site_base();
	}

	public function is_same_origin( $url ) {
		$needle   = strtolower( rtrim( $url, '/' ) );
		$base_lo  = strtolower( rtrim( $this->base(), '/' ) );
		return ( $needle === $base_lo );
	}

	public function normalize_resource( $resource ) {
		$canonical = $this->mcp_endpoint();
		if ( ! is_string( $resource ) || '' === $resource ) {
			return $canonical;
		}
		$r = rtrim( $resource, '/' );
		if ( strcasecmp( $r, rtrim( $canonical, '/' ) ) === 0 ) {
			return $canonical;
		}
		// Legacy bare-namespace root (mcp-connect/v1) still accepted as the resource.
		if ( strcasecmp( $r, untrailingslashit( get_rest_url( null, 'mcp-connect/v1' ) ) ) === 0 ) {
			return $canonical;
		}
		return null;
	}

	public function is_valid_redirect_uri( $uri ) {
		if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > 2048 ) {
			return false;
		}
		$parts = wp_parse_url( $uri );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		$scheme = strtolower( $parts['scheme'] );
		$host   = strtolower( $parts['host'] );

		if ( 'https' === $scheme ) {
			return ! self::is_loopback( $host ) || $this->is_valid_loopback_uri( $uri );
		}
		if ( 'http' === $scheme ) {
			return self::is_loopback_hostname( $host ) && $this->is_valid_loopback_uri( $uri );
		}
		return (bool) preg_match( '/^[a-z][a-z0-9+.\-]{1,31}$/i', $scheme ) && strlen( $uri ) <= 2048;
	}

	private static function is_loopback_hostname( $host ) {
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true );
	}

	private static function is_loopback( $host ) {
		if ( self::is_loopback_hostname( $host ) ) {
			return true;
		}
		if ( preg_match( '/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host ) ) {
			return true;
		}
		return false;
	}

	private function is_valid_loopback_uri( $uri ) {
		$parts = wp_parse_url( $uri );
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['port'] ) && ( $parts['port'] < 1 || $parts['port'] > 65535 ) ) {
			return false;
		}
		return true;
	}

	public function normalize_redirect_variants( array $uris ) {
		$expanded = array();
		foreach ( $uris as $uri ) {
			$expanded[] = $uri;
			$parts      = wp_parse_url( $uri );
			if ( ! empty( $parts['host'] ) && 'localhost' === strtolower( $parts['host'] ) ) {
				$parts['host'] = '127.0.0.1';
				$expanded[]    = $this->build_url( $parts );
			}
		}
		return array_values( array_unique( $expanded ) );
	}

	private function build_url( array $parts ) {
		$url = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}
		$url .= isset( $parts['path'] ) ? $parts['path'] : '';
		if ( ! empty( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}
		return $url;
	}

	public function get_client_ip() {
		return self::client_ip();
	}

	public static function client_ip() {
		$keys = array( 'REMOTE_ADDR' );
		if ( Plugin::instance()->settings->trust_proxy_headers() ) {
			$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		}
		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$parts = explode( ',', $value );
			$ip    = trim( $parts[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}

	public function get_allowed_origins() {
		$origins = (array) $this->settings->get( 'cors_origins', array() );
		$origins = array_filter( array_map( array( $this, 'normalize_origin' ), $origins ) );
		$site    = $this->origin();
		if ( $site ) {
			$origins[] = $site;
		}
		return array_values( array_unique( $origins ) );
	}

	public function normalize_origin( $origin ) {
		if ( ! is_string( $origin ) || '' === rtrim( $origin ) ) {
			return '';
		}
		$origin = untrailingslashit( strtolower( rtrim( $origin ) ) );
		$parts  = wp_parse_url( $origin );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		if ( 'https' !== $parts['scheme'] && 'http' !== $parts['scheme'] ) {
			return '';
		}
		$out = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$out .= ':' . $parts['port'];
		}
		return $out;
	}

	public function is_allowed_origin( $origin ) {
		$origin = strtolower( untrailingslashit( trim( (string) $origin ) ) );
		if ( '' === $origin ) {
			return false;
		}
		$allowed = array_map( 'strtolower', $this->get_allowed_origins() );
		if ( in_array( $origin, $allowed, true ) ) {
			return true;
		}
		// Local desktop clients (Claude Desktop, Cursor) may send loopback origins.
		$parts = wp_parse_url( $origin );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( in_array( $scheme, array( 'http', 'https' ), true ) && self::is_loopback( $host ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns true for origins served from localhost / 127.x (any port).
	 */
	public static function is_loopback_origin( $origin ) {
		$parts  = wp_parse_url( $origin );
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( '' === $host || '' === $scheme ) {
			return false;
		}
		return in_array( $scheme, array( 'http', 'https' ), true ) && self::is_loopback( $host );
	}
}
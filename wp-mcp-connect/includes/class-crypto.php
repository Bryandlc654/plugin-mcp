<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	public static function available() {
		return function_exists( 'random_bytes' ) && function_exists( 'hash_equals' );
	}

	public static function random_bytes( $length = 32 ) {
		return random_bytes( $length );
	}

	public static function random_hex( $length = 32 ) {
		return bin2hex( self::random_bytes( $length ) );
	}

	public static function random_token( $bytes = 32 ) {
		return rtrim( strtr( base64_encode( self::random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}

	public static function hash( $value ) {
		return hash( 'sha256', (string) $value );
	}

	public static function hash_equals( $hash, $value ) {
		return hash_equals( (string) $hash, self::hash( $value ) );
	}

	public static function base64url_decode( $input ) {
		$remainder = strlen( $input ) % 4;
		if ( $remainder ) {
			$input .= str_repeat( '=', 4 - $remainder );
		}
		$decoded = base64_decode( strtr( $input, '-_', '+/' ), true );
		return false === $decoded ? '' : $decoded;
	}

	public static function pkce_verify( $code_verifier, $code_challenge ) {
		if ( strlen( $code_verifier ) < 43 || strlen( $code_verifier ) > 128 ) {
			return false;
		}
		if ( ! preg_match( '/^[A-Za-z0-9\-\._~]+$/', $code_verifier ) ) {
			return false;
		}
		$calculated = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		return hash_equals( $calculated, (string) $code_challenge );
	}
}
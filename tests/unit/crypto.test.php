<?php

use WPMCPConnect\Crypto;

function test_crypto_generates_unique_tokens( &$f ) {
	$a = Crypto::random_token( 32 );
	$b = Crypto::random_token( 32 );
	wp_mcp_connect_assert( 43 === strlen( $a ), 'base64url token must be 43 chars for 32 bytes', $f, __FUNCTION__ );
	wp_mcp_connect_assert( $a !== $b, 'tokens must differ', $f, __FUNCTION__ );
}

function test_crypto_hash( &$f ) {
	wp_mcp_connect_assert( 64 === strlen( Crypto::hash( 'abc' ) ), 'sha256 hex length', $f, __FUNCTION__ );
	wp_mcp_connect_assert( hash( 'sha256', 'abc' ) === Crypto::hash( 'abc' ), 'matches native hash()', $f, __FUNCTION__ );
	wp_mcp_connect_assert( Crypto::hash_equals( Crypto::hash( 'secret' ), 'secret' ), 'hash_equals true', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! Crypto::hash_equals( Crypto::hash( 'secret' ), 'other' ), 'hash_equals false', $f, __FUNCTION__ );
}

function test_crypto_pkce_real( &$f ) {
	$verifier  = 'z' . str_repeat( 'Q', 63 ); // 64 chars
	$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	wp_mcp_connect_assert( Crypto::pkce_verify( $verifier, $challenge ), 'pkce_verify passes with correct S256 challenge', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! Crypto::pkce_verify( $verifier, 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' ), 'pkce_verify fails with wrong challenge', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! Crypto::pkce_verify( 'tooshort', $challenge ), 'pkce_verify rejects verifier with length < 43', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! Crypto::pkce_verify( str_repeat( 'x', 60 ) . ' ', $challenge ), 'pkce_verify rejects invalid charset', $f, __FUNCTION__ );
}

function test_crypto_base64url( &$f ) {
	$raw = random_bytes( 16 );
	$enc = rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	wp_mcp_connect_assert( Crypto::base64url_decode( $enc ) === $raw, 'base64url_decode roundtrip', $f, __FUNCTION__ );
	wp_mcp_connect_assert( '' === Crypto::base64url_decode( '!!@#$%' ), 'invalid returns empty', $f, __FUNCTION__ );
}
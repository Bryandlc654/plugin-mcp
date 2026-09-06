<?php

use MCPConnect\Settings;
use MCPConnect\Url_Manager;

function test_url_redirect_policy( &$f ) {
	$u = new Url_Manager( new Settings() );

	// Scheme-less / fragment / credentials rejected.
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'example.com/cb' ), 'scheme required', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'https://ex.com/cb#frag' ), 'fragment rejected', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'https://user:pass@ex.com/cb' ), 'credentials rejected', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_valid_redirect_uri( '' ), 'empty rejected', $f, __FUNCTION__ );

	// https anywhere (non-loopback) is fine.
	mcp_connect_assert( $u->is_valid_redirect_uri( 'https://app.consumer.dev/callback?x=1' ), 'https ok', $f, __FUNCTION__ );

	// http only for loopback.
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'http://evil.example/cb' ), 'http non-loopback rejected', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'http://localhost:9876/cb' ) === false, 'http loopback ok', $f, __FUNCTION__ );
	mcp_connect_assert( $u->is_valid_redirect_uri( 'http://localhost:9876/cb' ), 'http loopback (localhost) ok', $f, __FUNCTION__ );
	mcp_connect_assert( $u->is_valid_redirect_uri( 'http://127.0.0.1:5555/cb' ), 'http 127.0.0.1 ok', $f, __FUNCTION__ );
	// Strict policy: http only for the exact loopback hostnames.
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'http://127.8.8.8/cb' ), 'http 127.x rejected unless canonical loopback name', $f, __FUNCTION__ );
	mcp_connect_assert( $u->is_valid_redirect_uri( 'https://127.8.8.8/cb' ), 'https 127.x accepted', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'https://localhost:notaport/cb' ), 'bad port rejected', $f, __FUNCTION__ );

	// Custom application schemes are allowed (cursor:// etc).
	mcp_connect_assert( $u->is_valid_redirect_uri( 'cursor://oauth2/callback' ), 'custom scheme ok', $f, __FUNCTION__ );

	// Over-long redirected URI rejected.
	mcp_connect_assert( ! $u->is_valid_redirect_uri( 'https://x.com/' . str_repeat( 'a', 2100 ) ), 'overlong rejected', $f, __FUNCTION__ );
	// https loopback: acceptable because it can still carry the code.
	mcp_connect_assert( $u->is_valid_redirect_uri( 'https://localhost:5555/cb' ), 'https loopback ok', $f, __FUNCTION__ );
}

function test_url_loopback_variants( &$f ) {
	$u = new Url_Manager( new Settings() );
	$v = $u->normalize_redirect_variants( array( 'http://localhost:1111/cb' ) );
	mcp_connect_assert( in_array( 'http://localhost:1111/cb', $v, true ) && in_array( 'http://127.0.0.1:1111/cb', $v, true ), 'localhost expands to 127.0.0.1', $f, __FUNCTION__ );
	mcp_connect_assert( count( array_unique( $v ) ) === count( $v ), 'no dupes', $f, __FUNCTION__ );
}

function test_url_issuer_resource( &$f ) {
	$u = new Url_Manager( new Settings() );

	mcp_connect_assert( 'https://example.test' === $u->issuer(), 'issuer = site base', $f, __FUNCTION__ );
	mcp_connect_assert( 'https://example.test' === $u->origin(), 'origin computed', $f, __FUNCTION__ );
	mcp_connect_assert( $u->is_https(), 'default https true', $f, __FUNCTION__ );

	$canonical = $u->mcp_endpoint();
	mcp_connect_assert( $canonical === $u->normalize_resource( '' ), 'empty resource -> canonical', $f, __FUNCTION__ );
	mcp_connect_assert( $canonical === $u->normalize_resource( $canonical ), 'canonical accepted', $f, __FUNCTION__ );
	mcp_connect_assert( $canonical === $u->normalize_resource( $canonical . '/' ), 'trailing slash normalized', $f, __FUNCTION__ );
	mcp_connect_assert( $canonical === $u->normalize_resource( 'https://example.test/wp-json/mcp-connect-wp/v1/mcp' ), 'alias /mcp accepted', $f, __FUNCTION__ );
	mcp_connect_assert( $canonical === $u->normalize_resource( 'https://example.test/wp-json/mcp-connect-wp/v1' ), 'legacy bare root accepted', $f, __FUNCTION__ );
	mcp_connect_assert( null === $u->normalize_resource( 'https://evil.test/other' ), 'foreign resource rejected', $f, __FUNCTION__ );
}

function test_url_origins( &$f ) {
	$u = new Url_Manager( new Settings() );

	mcp_connect_assert( $u->is_allowed_origin( 'https://example.test' ), 'site origin allowed by default', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_allowed_origin( 'https://evil.test' ), 'foreign origin rejected', $f, __FUNCTION__ );
	mcp_connect_assert( ! $u->is_allowed_origin( '' ), 'empty rejected', $f, __FUNCTION__ );

	$GLOBALS['wp_options'][ Settings::OPTION ] = array( 'cors_origins' => array( 'https://App.Example.com/' ) );
	mcp_connect_assert( $u->is_allowed_origin( 'https://app.example.com' ), 'configured origin accepted & normalized', $f, __FUNCTION__ );

	$GLOBALS['test_home'] = 'http://example.test';
	$GLOBALS['test_site'] = 'http://example.test';
	mcp_connect_assert( ! $u->is_https(), 'http site -> not https', $f, __FUNCTION__ );
	$GLOBALS['test_home'] = 'https://example.test';
	$GLOBALS['test_site'] = 'https://example.test';
	$GLOBALS['test_ssl']  = true;

	unset( $GLOBALS['wp_options'][ Settings::OPTION ] );
}
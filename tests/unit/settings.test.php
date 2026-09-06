<?php

use MCPConnect\Settings;

function test_settings_defaults( &$f ) {
	$s = new Settings();
	mcp_connect_assert( $s->get( 'enabled', true ), 'enabled default true', $f, __FUNCTION__ );
	mcp_connect_assert( $s->scope_enabled( 'posts', 'read' ), 'posts:read on by default', $f, __FUNCTION__ );
	mcp_connect_assert( ! $s->scope_enabled( 'posts', 'delete' ), 'posts:delete off by default', $f, __FUNCTION__ );
	mcp_connect_assert( HOUR_IN_SECONDS === $s->access_token_ttl(), 'access ttl default', $f, __FUNCTION__ );
	mcp_connect_assert( 30 * DAY_IN_SECONDS === $s->refresh_token_ttl(), 'refresh ttl default', $f, __FUNCTION__ );
	mcp_connect_assert( 600 === $s->code_ttl(), 'code ttl default', $f, __FUNCTION__ );
	mcp_connect_assert( $s->is_registration_enabled(), 'registration default on', $f, __FUNCTION__ );
	mcp_connect_assert( ! $s->trust_proxy_headers(), 'proxy headers default off', $f, __FUNCTION__ );
}

function test_settings_grantables( &$f ) {
	$s = new Settings();
	$g = $s->grantable_scopes();
	mcp_connect_assert( in_array( 'posts:read', $g, true ), 'posts:read grantable', $f, __FUNCTION__ );
	mcp_connect_assert( ! in_array( 'posts:delete', $g, true ), 'posts:delete NOT grantable by default', $f, __FUNCTION__ );
	mcp_connect_assert( ! in_array( 'users:delete', $g, true ), 'users:delete never grantable', $f, __FUNCTION__ );
	mcp_connect_assert( ! in_array( 'comments:delete', $g, true ), 'comments:delete never grantable', $f, __FUNCTION__ );
	mcp_connect_assert( ! in_array( 'site:write', $g, true ), 'site:write never grantable', $f, __FUNCTION__ );

	// With delete enabled everywhere, delete scopes appear.
	$all = array();
	foreach ( Settings::CATEGORIES as $cat ) {
		$all[ $cat ] = array( 'read' => true, 'write' => true, 'delete' => true );
	}
	$GLOBALS['wp_options'][ Settings::OPTION ] = array( 'scopes' => $all );
	$g2 = $s->grantable_scopes();
	mcp_connect_assert( in_array( 'posts:delete', $g2, true ), 'posts:delete grantable when admin enables', $f, __FUNCTION__ );
	mcp_connect_assert( ! in_array( 'users:delete', $g2, true ), 'users:delete still impossible', $f, __FUNCTION__ );
	unset( $GLOBALS['wp_options'][ Settings::OPTION ] );
}

function test_settings_update( &$f ) {
	$s = new Settings();
	$s->update( array( 'enabled' => false, 'cors_origins' => array( 'https://x.test' ) ) );
	mcp_connect_assert( false === $s->get( 'enabled' ), 'update merged', $f, __FUNCTION__ );
	mcp_connect_assert( array( 'https://x.test' ) === $s->get( 'cors_origins' ), 'origins stored', $f, __FUNCTION__ );
	unset( $GLOBALS['wp_options'][ Settings::OPTION ] );
}
<?php

use MCPConnect\Permissions;

function test_permissions_parse( &$f ) {
	mcp_connect_assert( array() === Permissions::parse_scopes( '' ), 'empty -> none', $f, __FUNCTION__ );
	mcp_connect_assert( array() === Permissions::parse_scopes( 'bogus:scope' ), 'unknown scope dropped', $f, __FUNCTION__ );

	$got = Permissions::parse_scopes( 'posts:read site:read posts:read' );
	mcp_connect_assert( array( 'posts:read', 'site:read' ) === $got, 'parse + dedupe + order', $f, __FUNCTION__ );
}

function test_permissions_valid( &$f ) {
	mcp_connect_assert( Permissions::is_valid_scope( 'posts:write' ), 'posts:write valid', $f, __FUNCTION__ );
	mcp_connect_assert( ! Permissions::is_valid_scope( 'posts:nuke' ), 'mode nuke invalid', $f, __FUNCTION__ );
	mcp_connect_assert( ! Permissions::is_valid_scope( 'users:delete' ), 'users:delete blocked', $f, __FUNCTION__ );
	mcp_connect_assert( ! Permissions::is_valid_scope( 'comments:delete' ), 'comments:delete blocked', $f, __FUNCTION__ );
	mcp_connect_assert( Permissions::is_valid_scope( 'site:read' ), 'site:read valid', $f, __FUNCTION__ );
	mcp_connect_assert( ! Permissions::is_valid_scope( 'site:write' ), 'site:write invalid', $f, __FUNCTION__ );
}

function test_permissions_has_scope( &$f ) {
	mcp_connect_assert( Permissions::has_scope( array( 'posts:read' ), 'posts:read' ), 'has scope true', $f, __FUNCTION__ );
	mcp_connect_assert( ! Permissions::has_scope( array( 'posts:read' ), 'posts:write' ), 'has scope false', $f, __FUNCTION__ );
	mcp_connect_assert( Permissions::has_scope( array(), '' ), 'empty required == allowed', $f, __FUNCTION__ );
	mcp_connect_assert( ! Permissions::has_scope( '', 'posts:read' ), 'non-array granted -> false', $f, __FUNCTION__ );
}

function test_permissions_parts( &$f ) {
	list( $c, $m ) = Permissions::scope_parts( 'media:delete' );
	mcp_connect_assert( 'media' === $c && 'delete' === $m, 'scope parts split', $f, __FUNCTION__ );
	list( $c2, $m2 ) = Permissions::scope_parts( 'noscope' );
	mcp_connect_assert( '' === $c2 && '' === $m2, 'no colon -> empty parts', $f, __FUNCTION__ );
}
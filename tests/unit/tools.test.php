<?php

use WPMCPConnect\MCP_Tools;
use WPMCPConnect\Settings;
use WPMCPConnect\Permissions;

function test_tools_registered( &$f ) {
	$tools = new MCP_Tools();
	$tools->register_tools();
	wp_mcp_connect_assert( 27 === $tools->count(), 'expected 27 tools, got ' . $tools->count(), $f, __FUNCTION__ );

	$all = $tools->all();
	wp_mcp_connect_assert( isset( $all['wp_list_posts'] ), 'wp_list_posts present', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! isset( $all['wp_delete_comment'] ), 'no comment deletion tool', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! isset( $all['wp_create_user'] ), 'no user creation tool', $f, __FUNCTION__ );
	wp_mcp_connect_assert( isset( $all['wp_get_current_user'] ), 'current user tool present', $f, __FUNCTION__ );
	wp_mcp_connect_assert( isset( $all['wp_delete_post'] ), 'post deletion tool present (scope-gated)', $f, __FUNCTION__ );
	wp_mcp_connect_assert( isset( $all['wp_delete_category'] ), 'category deletion present', $f, __FUNCTION__ );
}

function test_tools_scoping( &$f ) {
	$tools = new MCP_Tools();
	$tools->register_tools();

	// All valid scopes granted AND delete enabled by admin -> full inventory.
	$all_matrix = array();
	foreach ( Settings::CATEGORIES as $cat ) {
		$all_matrix[ $cat ] = array( 'read' => true, 'write' => true, 'delete' => true );
	}
	$GLOBALS['wp_options'][ Settings::OPTION ] = array( 'scopes' => $all_matrix );

	$all        = $tools->list_for_scopes( array_keys( Permissions::SCOPE_LABELS ) );
	$all_names  = array_column( $all, 'name' );
	wp_mcp_connect_assert( in_array( 'wp_delete_post', $all_names, true ), 'delete tool appears when all scopes granted', $f, __FUNCTION__ );
	wp_mcp_connect_assert( count( $all ) === $tools->count(), 'all tools listed with all scopes', $f, __FUNCTION__ );

	unset( $GLOBALS['wp_options'][ Settings::OPTION ] );

	// Only read scopes.
	$read       = $tools->list_for_scopes( array( 'posts:read', 'site:read', 'users:read', 'pages:read', 'media:read', 'comments:read', 'taxonomies:read' ) );
	$read_names = array_column( $read, 'name' );
	wp_mcp_connect_assert( in_array( 'wp_list_posts', $read_names, true ), 'list visible', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! in_array( 'wp_create_post', $read_names, true ), 'create hidden without write', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! in_array( 'wp_delete_post', $read_names, true ), 'delete hidden without delete', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! in_array( 'wp_update_comment', $read_names, true ), 'comment update hidden', $f, __FUNCTION__ );

	// Admin disabled a category entirely; media write off.
	$GLOBALS['wp_options'][ Settings::OPTION ] = array(
		'scopes' => array(
			'posts'       => array( 'read' => false, 'write' => false, 'delete' => false ),
			'pages'       => array( 'read' => true, 'write' => true, 'delete' => true ),
			'media'       => array( 'read' => true, 'write' => false, 'delete' => false ),
			'comments'    => array( 'read' => true, 'write' => true, 'delete' => false ),
			'taxonomies'  => array( 'read' => true, 'write' => true, 'delete' => true ),
			'users'       => array( 'read' => true, 'write' => true, 'delete' => false ),
			'site'        => array( 'read' => true, 'write' => false, 'delete' => false ),
		),
	);
	$names = array_column( $tools->list_for_scopes( array_keys( Permissions::SCOPE_LABELS ) ), 'name' );
	wp_mcp_connect_assert( ! in_array( 'wp_list_posts', $names, true ), 'posts hidden when posts disabled', $f, __FUNCTION__ );
	wp_mcp_connect_assert( in_array( 'wp_list_pages', $names, true ), 'pages still visible', $f, __FUNCTION__ );
	wp_mcp_connect_assert( ! in_array( 'wp_upload_media', $names, true ), 'upload hidden when media write off', $f, __FUNCTION__ );
	unset( $GLOBALS['wp_options'][ Settings::OPTION ] );
}

function test_tools_call_errors( &$f ) {
	$tools = new MCP_Tools();
	$tools->register_tools();

	// Unknown tool.
	$r = $tools->call( 'wp_does_not_exist', array(), array( 'user_id' => 1, 'scopes' => array() ) );
	wp_mcp_connect_assert( ! empty( $r['isError'] ), 'unknown tool -> isError', $f, __FUNCTION__ );
	wp_mcp_connect_assert( 'method_not_found' === $r['structuredContent']['error']['code'], 'unknown tool code', $f, __FUNCTION__ );

	// Tool without the granted scope.
	$r = $tools->call( 'wp_list_posts', array(), array( 'user_id' => 1, 'scopes' => array() ) );
	wp_mcp_connect_assert( ! empty( $r['isError'] ), 'no scope -> isError', $f, __FUNCTION__ );
	wp_mcp_connect_assert( 'insufficient_scope' === $r['structuredContent']['error']['code'], 'no scope code', $f, __FUNCTION__ );
}

function test_tools_schema_rejects( &$f ) {
	$tools = new MCP_Tools();
	$tools->register_tools();

	$r = $tools->call( 'wp_get_post', array( 'id' => 'not-an-int' ), array( 'user_id' => 1, 'scopes' => array( 'posts:read' ) ) );
	wp_mcp_connect_assert( ! empty( $r['isError'] ), 'bad schema -> isError', $f, __FUNCTION__ );
	wp_mcp_connect_assert( 'invalid_arguments' === $r['structuredContent']['error']['code'], 'bad schema code', $f, __FUNCTION__ );
}
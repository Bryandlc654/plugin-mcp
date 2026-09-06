<?php

use MCPConnect\Schema_Validator;
use MCPConnect\JsonRpc;

function test_schema_basic( &$f ) {
	$v = new Schema_Validator();
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'id'     => array( 'type' => 'integer', 'minimum' => 1 ),
			'title'  => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 10 ),
			'status' => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ),
		),
		'required'             => array( 'id' ),
		'additionalProperties' => false,
	);

	$good = array( 'id' => 3, 'title' => 'hola' );
	mcp_connect_assert( true === $v->validate( $good, $schema ), 'valid object passes', $f, __FUNCTION__ );

	$bad_missing = array( 'title' => 'x' );
	$r = $v->validate( $bad_missing, $schema );
	mcp_connect_assert( is_wp_error( $r ) && 'missing_required' === $r->get_error_code(), 'missing required -> error', $f, __FUNCTION__ );

	$bad_extra = array( 'id' => 1, 'evil' => 1 );
	$r = $v->validate( $bad_extra, $schema );
	mcp_connect_assert( is_wp_error( $r ) && 'additional_properties' === $r->get_error_code(), 'additionalProperties:false enforced', $f, __FUNCTION__ );

	$bad_min = array( 'id' => 0 );
	$r = $v->validate( $bad_min, $schema );
	mcp_connect_assert( is_wp_error( $r ) && 'minimum' === $r->get_error_code(), 'minimum enforced', $f, __FUNCTION__ );

	$bad_enum = array( 'id' => 1, 'status' => 'trash' );
	$r = $v->validate( $bad_enum, $schema );
	mcp_connect_assert( is_wp_error( $r ) && 'invalid_enum' === $r->get_error_code(), 'enum enforced', $f, __FUNCTION__ );

	$bad_type = array( 'id' => 'one' );
	$r = $v->validate( $bad_type, $schema );
	mcp_connect_assert( is_wp_error( $r ) && 'invalid_type' === $r->get_error_code(), 'type enforced', $f, __FUNCTION__ );
}

function test_schema_arrays( &$f ) {
	$v = new Schema_Validator();
	$schema = array(
		'type'  => 'array',
		'items' => array( 'type' => 'string' ),
	);
	$good = array( 'a', 'b' );
	mcp_connect_assert( true === $v->validate( $good, $schema ), 'string array ok', $f, __FUNCTION__ );
	$bad = array( 'a', 7 );
	$r = $v->validate( $bad, $schema );
	mcp_connect_assert( is_wp_error( $r ), 'non-string item rejected', $f, __FUNCTION__ );
}

function test_jsonrpc_shape( &$f ) {
	$ok = JsonRpc::success( 1, array( 'tools' => array() ) );
	mcp_connect_assert( 1 === $ok['id'] && array( 'tools' => array() ) === $ok['result'] && ! isset( $ok['error'] ), 'result shape', $f, __FUNCTION__ );

	$e = JsonRpc::error( 2, -32602, 'bad params', array( 'x' => 1 ) );
	mcp_connect_assert( -32602 === $e['error']['code'] && 'bad params' === $e['error']['message'] && 2 === $e['id'], 'error shape', $f, __FUNCTION__ );
	mcp_connect_assert( ! isset( $e['result'] ), 'no result on error', $f, __FUNCTION__ );

	mcp_connect_assert( -32700 === JsonRpc::PARSE_ERROR, 'parse error code', $f, __FUNCTION__ );
	mcp_connect_assert( -32601 === JsonRpc::METHOD_NOT_FOUND, 'method not found code', $f, __FUNCTION__ );

	$n = JsonRpc::is_notification( array( 'jsonrpc' => '2.0', 'method' => 'ping' ) );
	mcp_connect_assert( $n, 'notification detected (no id)', $f, __FUNCTION__ );
	mcp_connect_assert( ! JsonRpc::is_notification( array( 'jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1 ) ), 'request has id', $f, __FUNCTION__ );
	mcp_connect_assert( JsonRpc::is_request( array( 'jsonrpc' => '2.0', 'method' => 'tools/list' ) ), 'request shape', $f, __FUNCTION__ );
	mcp_connect_assert( ! JsonRpc::is_request( array( 'foo' => 'bar' ) ), 'non request', $f, __FUNCTION__ );
}
<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

final class JsonRpc {

	const PARSE_ERROR     = -32700;
	const INVALID_REQUEST = -32600;
	const METHOD_NOT_FOUND = -32601;
	const INVALID_PARAMS  = -32602;
	const INTERNAL_ERROR  = -32603;
	const UNAUTHORIZED    = -32001;
	const FORBIDDEN       = -32003;
	const DISABLED        = -32004;

	public static function success( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	public static function error( $id, $code, $message, $data = null ) {
		$err = array(
			'code'    => $code,
			'message' => $message,
		);
		if ( null !== $data ) {
			$err['data'] = $data;
		}
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => $err,
		);
	}

	public static function is_request( $message ) {
		return is_array( $message )
			&& isset( $message['jsonrpc'] )
			&& '2.0' === $message['jsonrpc']
			&& isset( $message['method'] )
			&& is_string( $message['method'] );
	}

	public static function is_notification( $message ) {
		return self::is_request( $message ) && ! array_key_exists( 'id', $message );
	}
}
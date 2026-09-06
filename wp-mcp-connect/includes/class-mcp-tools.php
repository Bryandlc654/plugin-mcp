<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by tool handlers to produce a structured MCP "isError" result.
 */
final class Tool_Error extends \Exception {}

/**
 * Tool registry: loads definitions, filters by scope + settings, validates
 * inputs and dispatches calls.
 */
final class MCP_Tools {

	private $definitions = array();

	public function register_tools() {
		$dirs = array(
			WP_MCP_CONNECT_DIR . 'tools/site.php',
			WP_MCP_CONNECT_DIR . 'tools/posts.php',
			WP_MCP_CONNECT_DIR . 'tools/pages.php',
			WP_MCP_CONNECT_DIR . 'tools/media.php',
			WP_MCP_CONNECT_DIR . 'tools/users.php',
			WP_MCP_CONNECT_DIR . 'tools/comments.php',
			WP_MCP_CONNECT_DIR . 'tools/taxonomies.php',
		);

		foreach ( $dirs as $file ) {
			if ( file_exists( $file ) ) {
				$defs = require $file;
				if ( is_array( $defs ) ) {
					foreach ( $defs as $def ) {
						$this->register( $def );
					}
				}
			}
		}
	}

	private function register( array $def ) {
		if ( isset( $def['name'] ) && isset( $def['handler'] ) ) {
			$this->definitions[ $def['name'] ] = $def;
		}
	}

	public function get( $name ) {
		return isset( $this->definitions[ $name ] ) ? $this->definitions[ $name ] : null;
	}

	public function all() {
		return $this->definitions;
	}

	/**
	 * Returns tools available to an actor with the given granted scopes.
	 */
	public function list_for_scopes( array $granted_scopes ) {
		$settings = Plugin::instance()->settings;
		$out      = array();
		foreach ( $this->definitions as $def ) {
			$cat  = isset( $def['category'] ) ? $def['category'] : '';
			$mode = isset( $def['mode'] ) ? $def['mode'] : 'read';
			if ( ! $settings->scope_enabled( $cat, $mode ) ) {
				continue;
			}
			if ( ! Permissions::has_scope( $granted_scopes, $def['scope'] ) ) {
				continue;
			}
			$schema = $def['inputSchema'];
			if ( is_array( $schema ) && isset( $schema['properties'] ) && is_array( $schema['properties'] ) && empty( $schema['properties'] ) ) {
				// Empty property lists must serialize as {} (JSON object),
				// not [], or strict MCP clients reject the tool schema.
				$schema['properties'] = new \stdClass();
			}
			$out[] = array(
				'name'        => $def['name'],
				'description' => $def['description'],
				'inputSchema' => $schema,
			);
		}
		return $out;
	}

	/**
	 * Executes a tool call.
	 *
	 * @return array MCP result payload (content + isError).
	 */
	public function call( $name, array $arguments, array $actor ) {
		$def = $this->get( $name );
		if ( ! $def ) {
			return $this->error_result( 'method_not_found', __( 'Tool not found.', 'wp-mcp-connect' ) );
		}

		$settings = Plugin::instance()->settings;
		$cat      = isset( $def['category'] ) ? $def['category'] : '';
		$mode     = isset( $def['mode'] ) ? $def['mode'] : 'read';

		if ( ! $settings->scope_enabled( $cat, $mode ) ) {
			return $this->error_result( 'tool_disabled', __( 'This tool is disabled by the administrator.', 'wp-mcp-connect' ) );
		}
		if ( ! Permissions::has_scope( $actor['scopes'], $def['scope'] ) ) {
			return $this->error_result( 'insufficient_scope', __( 'The granted scope does not include this tool.', 'wp-mcp-connect' ) );
		}

		$validator = new Schema_Validator();
		$valid     = $validator->validate( $arguments, $def['inputSchema'] );
		if ( is_wp_error( $valid ) ) {
			$data = $valid->get_error_data();
			return $this->error_result(
				'invalid_arguments',
				sprintf( '%s %s', $valid->get_error_message(), isset( $data['path'] ) ? '(' . $data['path'] . ')' : '' )
			);
		}

		if ( ! empty( $def['permission'] ) && is_callable( $def['permission'] ) ) {
			$check = call_user_func( $def['permission'], $arguments, $actor );
			if ( is_array( $check ) && isset( $check['allowed'] ) && ! $check['allowed'] ) {
				$message = isset( $check['message'] ) ? $check['message'] : __( 'You do not have permission to perform this action.', 'wp-mcp-connect' );
				return $this->error_result( isset( $check['code'] ) ? $check['code'] : 'insufficient_permissions', $message );
			}
		}

		try {
			$snapshot = Permissions::switch_user( $actor['user_id'] );
			try {
				$result = call_user_func( $def['handler'], $arguments, $actor );
			} finally {
				Permissions::restore_user( $snapshot );
			}
		} catch ( Tool_Error $e ) {
			return $this->error_result( $e->getCode(), $e->getMessage() );
		} catch ( \Throwable $e ) {
			return $this->error_result( 'internal_error', __( 'An unexpected error occurred while running the tool.', 'wp-mcp-connect' ) );
		}

		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				),
			),
			'structuredContent' => $result,
		);
	}

	private function error_result( $code, $message ) {
		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError'           => true,
			'structuredContent' => array(
				'error' => array(
					'code'    => $code,
					'message' => $message,
				),
			),
		);
	}

	public function count() {
		return count( $this->definitions );
	}
}
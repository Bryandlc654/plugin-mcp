<?php

namespace MCPConnect;

use MCPConnect\Tool_Error;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress user MCP tools. Read-only in v1.
 */
return array(

	array(
		'name'        => 'wp_get_current_user',
		'description' => __( 'Gets information about the currently authenticated WordPress user represented by the MCP connection. Use this tool to know who is connected and what capabilities they have.', 'mcp-connect' ),
		'scope'       => 'users:read',
		'category'    => 'users',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => (object) array(),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'read' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'The account has no read access to this site.', 'mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function () {
			$user = wp_get_current_user();
			if ( ! $user || ! $user->exists() ) {
				throw new Tool_Error( 'no_user', __( 'No authenticated user found.', 'mcp-connect' ) );
			}
			return array(
				'user' => array(
					'id'          => (int) $user->ID,
					'login'       => $user->user_login,
					'display_name' => $user->display_name,
					'email'       => $user->user_email,
					'roles'       => (array) $user->roles,
					'registered'  => $user->user_registered,
					'capabilities' => Permissions::relevant_capabilities( $user->ID ),
				),
			);
		},
	),
);
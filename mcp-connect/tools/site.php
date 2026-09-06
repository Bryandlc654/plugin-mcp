<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress site info MCP tool.
 */
return array(

	array(
		'name'        => 'wp_get_site_info',
		'description' => __( 'Gets general information about the WordPress site: name, URL, description, WordPress version, language, timezone, and the connected user. Use this tool first when you need context about the site.', 'mcp-connect' ),
		'scope'       => 'site:read',
		'category'    => 'site',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'include_capabilities' => array( 'type' => 'boolean', 'default' => true, 'description' => __( 'Include the connected user\'s relevant capabilities.', 'mcp-connect' ) ),
			),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'read' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'The account has no read access to this site.', 'mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$user = wp_get_current_user();
			global $wp_version;

			$data = array(
				'site'           => array(
					'name'        => get_bloginfo( 'name' ),
					'url'         => get_bloginfo( 'url' ),
					'description' => get_bloginfo( 'description' ),
					'wordpress'   => $wp_version,
					'php'         => PHP_VERSION,
					'language'    => get_locale(),
					'timezone'    => wp_timezone_string(),
					'date_format' => get_option( 'date_format' ),
					'admin_email' => null,
				),
				'mcp_endpoint'   => Plugin::instance()->url->mcp_endpoint(),
				'current_user'   => array(
					'id'           => $user ? $user->ID : 0,
					'display_name' => $user ? $user->display_name : '',
					'roles'        => $user ? (array) $user->roles : array(),
				),
			);

			if ( ! empty( $args['include_capabilities'] ) ) {
				$data['current_user']['capabilities'] = $user ? Permissions::relevant_capabilities( $user->ID ) : array();
				$data['permissions'] = array(
					'can_read'    => $user ? $user->has_cap( 'read' ) : false,
					'can_edit_posts'     => $user ? $user->has_cap( 'edit_posts' ) : false,
					'can_publish_posts'  => $user ? $user->has_cap( 'publish_posts' ) : false,
					'can_upload'         => $user ? $user->has_cap( 'upload_files' ) : false,
					'can_moderate_comments' => $user ? $user->has_cap( 'moderate_comments' ) : false,
				);
			}

			return $data;
		},
	),
);
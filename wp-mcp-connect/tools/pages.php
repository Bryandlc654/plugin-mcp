<?php

namespace WPMCPConnect;

use WPMCPConnect\Tool_Error;
use WPMCPConnect\Tool_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress pages MCP tools (post_type = page).
 */
return array(

	array(
		'name'        => 'wp_list_pages',
		'description' => __( 'Lists WordPress pages. Use this tool when the user asks to see, list or show their pages (static pages, not blog posts).', 'wp-mcp-connect' ),
		'scope'       => 'pages:read',
		'category'    => 'pages',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'number'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10 ),
				'offset'  => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
				'status'  => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ), 'default' => 'publish' ),
				'search'  => array( 'type' => 'string' ),
				'parent'  => array( 'type' => 'integer', 'description' => __( 'Parent page ID.', 'wp-mcp-connect' ) ),
				'orderby' => array( 'type' => 'string', 'enum' => array( 'date', 'modified', 'title', 'menu_order', 'ID' ), 'default' => 'date' ),
				'order'   => array( 'type' => 'string', 'enum' => array( 'DESC', 'ASC' ), 'default' => 'DESC' ),
			),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			$status = Tool_Helpers::arg( $args, 'status', 'publish' );
			if ( ! in_array( $status, array( 'publish', 'any' ), true ) && ! Permissions::user_has( $actor['user_id'], 'edit_pages' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Non-published pages require the edit_pages capability.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$status = Tool_Helpers::arg( $args, 'status', 'publish' );
			$query  = array(
				'post_type'      => 'page',
				'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private', 'trash' ) : array( $status ),
				'posts_per_page' => (int) Tool_Helpers::arg( $args, 'number', 10 ),
				'offset'         => (int) Tool_Helpers::arg( $args, 'offset', 0 ),
				'orderby'        => Tool_Helpers::arg( $args, 'orderby', 'date' ),
				'order'          => Tool_Helpers::arg( $args, 'order', 'DESC' ),
				'no_found_rows'  => true,
			);
			if ( Tool_Helpers::arg( $args, 'search' ) ) {
				$query['s'] = sanitize_text_field( $args['search'] );
			}
			if ( Tool_Helpers::arg( $args, 'parent' ) ) {
				$query['post_parent'] = (int) $args['parent'];
			}

			$posts   = get_posts( $query );
			$results = array();
			foreach ( $posts as $page ) {
				$results[] = Tool_Helpers::post_brief( $page );
			}
			return array( 'total' => count( $results ), 'pages' => $results );
		},
	),

	array(
		'name'        => 'wp_get_page',
		'description' => __( 'Gets a single WordPress page by ID with its full content.', 'wp-mcp-connect' ),
		'scope'       => 'pages:read',
		'category'    => 'pages',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			$post = get_post( (int) $args['id'] );
			if ( $post && 'publish' !== $post->post_status && ! Permissions::user_has( $actor['user_id'], 'edit_page', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot read this unpublished page.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$post = Tool_Helpers::ensure_post_exists( (int) $args['id'] );
			return array( 'page' => Tool_Helpers::post_detailed( $post ) );
		},
	),

	array(
		'name'        => 'wp_create_page',
		'description' => __( 'Creates a new WordPress page. Use this tool when the user asks to create a new page. The page can be created as draft, pending, private or published, subject to capabilities.', 'wp-mcp-connect' ),
		'scope'       => 'pages:write',
		'category'    => 'pages',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'title'      => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
				'content'    => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ), 'default' => 'draft' ),
				'excerpt'    => array( 'type' => 'string' ),
				'slug'       => array( 'type' => 'string' ),
				'parent'     => array( 'type' => 'integer', 'description' => __( 'Parent page ID.', 'wp-mcp-connect' ) ),
				'template'   => array( 'type' => 'string', 'description' => __( 'Page template name.', 'wp-mcp-connect' ) ),
				'order'      => array( 'type' => 'integer', 'description' => __( 'Menu order.', 'wp-mcp-connect' ) ),
			),
			'required'             => array( 'title' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'edit_pages' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Creating pages requires the edit_pages capability.', 'wp-mcp-connect' ) );
			}
			$status = Tool_Helpers::arg( $args, 'status', 'draft' );
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! Permissions::user_has( $actor['user_id'], 'publish_pages' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'The authenticated WordPress user cannot publish pages.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$postarr = array(
				'post_type'    => 'page',
				'post_title'   => sanitize_text_field( $args['title'] ),
				'post_content' => Tool_Helpers::arg( $args, 'content', '' ),
				'post_status'  => Tool_Helpers::arg( $args, 'status', 'draft' ),
				'post_excerpt' => Tool_Helpers::arg( $args, 'excerpt', '' ),
				'post_name'    => Tool_Helpers::arg( $args, 'slug', '' ),
			);
			if ( isset( $args['parent'] ) ) {
				$postarr['post_parent'] = (int) $args['parent'];
			}
			if ( isset( $args['order'] ) ) {
				$postarr['menu_order'] = (int) $args['order'];
			}

			$id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $id ) ) {
				throw new Tool_Error( 'creation_failed', $id->get_error_message() );
			}
			if ( isset( $args['template'] ) ) {
				update_post_meta( $id, '_wp_page_template', sanitize_text_field( $args['template'] ) );
			}
			return array( 'created' => true, 'page' => Tool_Helpers::post_detailed( get_post( $id ) ) );
		},
	),

	array(
		'name'        => 'wp_update_page',
		'description' => __( 'Updates an existing WordPress page.', 'wp-mcp-connect' ),
		'scope'       => 'pages:write',
		'category'    => 'pages',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'       => array( 'type' => 'integer', 'minimum' => 1 ),
				'title'    => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255 ),
				'content'  => array( 'type' => 'string' ),
				'status'   => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ) ),
				'excerpt'  => array( 'type' => 'string' ),
				'slug'     => array( 'type' => 'string' ),
				'parent'   => array( 'type' => 'integer' ),
				'template' => array( 'type' => 'string' ),
				'order'    => array( 'type' => 'integer' ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'edit_page', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot edit this page.', 'wp-mcp-connect' ) );
			}
			$post = get_post( (int) $args['id'] );
			if ( $post && isset( $args['status'] ) && in_array( $args['status'], array( 'publish', 'private' ), true ) && 'publish' !== $post->post_status && 'private' !== $post->post_status && ! Permissions::user_has( $actor['user_id'], 'publish_pages' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Publishing pages requires the publish_pages capability.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$post    = Tool_Helpers::ensure_post_exists( (int) $args['id'] );
			$postarr = array( 'ID' => (int) $post->ID );
			if ( isset( $args['title'] ) ) {
				$postarr['post_title'] = sanitize_text_field( $args['title'] );
			}
			if ( isset( $args['content'] ) ) {
				$postarr['post_content'] = $args['content'];
			}
			if ( isset( $args['status'] ) ) {
				$postarr['post_status'] = $args['status'];
			}
			if ( isset( $args['excerpt'] ) ) {
				$postarr['post_excerpt'] = $args['excerpt'];
			}
			if ( isset( $args['slug'] ) ) {
				$postarr['post_name'] = sanitize_title( $args['slug'] );
			}
			if ( isset( $args['parent'] ) ) {
				$postarr['post_parent'] = (int) $args['parent'];
			}
			if ( isset( $args['order'] ) ) {
				$postarr['menu_order'] = (int) $args['order'];
			}

			$updated = wp_update_post( $postarr, true );
			if ( is_wp_error( $updated ) ) {
				throw new Tool_Error( 'update_failed', $updated->get_error_message() );
			}
			if ( isset( $args['template'] ) ) {
				update_post_meta( $updated, '_wp_page_template', sanitize_text_field( $args['template'] ) );
			}
			return array( 'updated' => true, 'page' => Tool_Helpers::post_detailed( get_post( $updated ) ) );
		},
	),

	array(
		'name'        => 'wp_delete_page',
		'description' => __( 'Deletes a WordPress page. Moves it to the trash by default or permanently deletes it when force is true. Destructive action.', 'wp-mcp-connect' ),
		'scope'       => 'pages:delete',
		'category'    => 'pages',
		'mode'        => 'delete',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'    => array( 'type' => 'integer', 'minimum' => 1 ),
				'force' => array( 'type' => 'boolean', 'default' => false ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'delete_page', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot delete this page.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$post = Tool_Helpers::ensure_post_exists( (int) $args['id'] );
			if ( ! empty( $args['force'] ) ) {
				$result = wp_delete_post( $post->ID, true );
				if ( ! $result ) {
					throw new Tool_Error( 'deletion_failed', __( 'The page could not be deleted.', 'wp-mcp-connect' ) );
				}
				return array( 'deleted' => true, 'permanent' => true, 'id' => (int) $post->ID );
			}
			wp_trash_post( $post->ID );
			return array( 'deleted' => true, 'permanent' => false, 'id' => (int) $post->ID );
		},
	),
);
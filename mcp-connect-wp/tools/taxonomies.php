<?php

namespace MCPConnect;

use MCPConnect\Tool_Error;
use MCPConnect\Tool_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress taxonomy MCP tools: categories and tags.
 */
return array(

	array(
		'name'        => 'wp_list_categories',
		'description' => __( 'Lists the site\'s post categories.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:read',
		'category'    => 'taxonomies',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'number'    => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ),
				'search'    => array( 'type' => 'string' ),
				'hide_empty' => array( 'type' => 'boolean', 'default' => true ),
			),
			'additionalProperties' => false,
		),
		'handler'     => function ( $args ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => (bool) Tool_Helpers::arg( $args, 'hide_empty', true ),
					'number'     => (int) Tool_Helpers::arg( $args, 'number', 50 ),
					'search'     => Tool_Helpers::arg( $args, 'search', '' ),
				)
			);
			if ( is_wp_error( $terms ) ) {
				throw new Tool_Error( 'list_failed', $terms->get_error_message() );
			}
			$out = array();
			foreach ( $terms as $term ) {
				$out[] = Tool_Helpers::term_summary( $term );
			}
			return array( 'total' => count( $out ), 'categories' => $out );
		},
	),

	array(
		'name'        => 'wp_create_category',
		'description' => __( 'Creates a new post category.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:write',
		'category'    => 'taxonomies',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'name'        => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'        => array( 'type' => 'string' ),
				'parent'      => array( 'type' => 'integer', 'description' => __( 'Parent category ID.', 'mcp-connect-wp' ) ),
				'description' => array( 'type' => 'string' ),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'manage_categories' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Managing categories requires the manage_categories capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$term = wp_insert_term(
				sanitize_text_field( $args['name'] ),
				'category',
				array(
					'slug'        => Tool_Helpers::arg( $args, 'slug', '' ),
					'parent'      => (int) Tool_Helpers::arg( $args, 'parent', 0 ),
					'description' => Tool_Helpers::arg( $args, 'description', '' ),
				)
			);
			if ( is_wp_error( $term ) ) {
				throw new Tool_Error( 'creation_failed', $term->get_error_message() );
			}
			return array( 'created' => true, 'category' => Tool_Helpers::term_summary( get_term( $term['term_id'], 'category' ) ) );
		},
	),

	array(
		'name'        => 'wp_update_category',
		'description' => __( 'Updates an existing category (name, slug, description, parent).', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:write',
		'category'    => 'taxonomies',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
				'name'        => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'        => array( 'type' => 'string' ),
				'parent'      => array( 'type' => 'integer' ),
				'description' => array( 'type' => 'string' ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'manage_categories' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Managing categories requires the manage_categories capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$update = array( 'name' => sanitize_text_field( $args['name'] ) );
			if ( isset( $args['slug'] ) ) {
				$update['slug'] = sanitize_title( $args['slug'] );
			}
			if ( isset( $args['parent'] ) ) {
				$update['parent'] = (int) $args['parent'];
			}
			if ( isset( $args['description'] ) ) {
				$update['description'] = $args['description'];
			}
			$result = wp_update_term( (int) $args['id'], 'category', $update );
			if ( is_wp_error( $result ) ) {
				throw new Tool_Error( 'update_failed', $result->get_error_message() );
			}
			return array( 'updated' => true, 'category' => Tool_Helpers::term_summary( get_term( (int) $args['id'], 'category' ) ) );
		},
	),

	array(
		'name'        => 'wp_delete_category',
		'description' => __( 'Deletes a category (the default "Uncategorized" category cannot be deleted). Destructive action.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:delete',
		'category'    => 'taxonomies',
		'mode'        => 'delete',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'manage_categories' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Managing categories requires the manage_categories capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$term = get_term( (int) $args['id'], 'category' );
			if ( ! $term ) {
				throw new Tool_Error( 'not_found', __( 'Category not found.', 'mcp-connect-wp' ) );
			}
			if ( 1 === (int) $term->term_id ) {
				throw new Tool_Error( 'protected', __( 'The default category cannot be deleted.', 'mcp-connect-wp' ) );
			}
			$result = wp_delete_term( (int) $args['id'], 'category' );
			if ( is_wp_error( $result ) ) {
				throw new Tool_Error( 'deletion_failed', $result->get_error_message() );
			}
			if ( ! $result ) {
				throw new Tool_Error( 'deletion_failed', __( 'The category could not be deleted.', 'mcp-connect-wp' ) );
			}
			return array( 'deleted' => true, 'id' => (int) $term->term_id );
		},
	),

	array(
		'name'        => 'wp_list_tags',
		'description' => __( 'Lists the site\'s post tags.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:read',
		'category'    => 'taxonomies',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'number' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ),
				'search' => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
		),
		'handler'     => function ( $args ) {
			$terms = get_terms(
				array(
					'taxonomy' => 'post_tag',
					'hide_empty' => false,
					'number'   => (int) Tool_Helpers::arg( $args, 'number', 50 ),
					'search'   => Tool_Helpers::arg( $args, 'search', '' ),
				)
			);
			if ( is_wp_error( $terms ) ) {
				throw new Tool_Error( 'list_failed', $terms->get_error_message() );
			}
			$out = array();
			foreach ( $terms as $term ) {
				$out[] = Tool_Helpers::term_summary( $term );
			}
			return array( 'total' => count( $out ), 'tags' => $out );
		},
	),

	array(
		'name'        => 'wp_create_tag',
		'description' => __( 'Creates a new post tag.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:write',
		'category'    => 'taxonomies',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'name'        => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
			'required'             => array( 'name' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'manage_categories' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Managing tags requires the manage_categories capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$term = wp_insert_term(
				sanitize_text_field( $args['name'] ),
				'post_tag',
				array(
					'slug'        => Tool_Helpers::arg( $args, 'slug', '' ),
					'description' => Tool_Helpers::arg( $args, 'description', '' ),
				)
			);
			if ( is_wp_error( $term ) ) {
				throw new Tool_Error( 'creation_failed', $term->get_error_message() );
			}
			return array( 'created' => true, 'tag' => Tool_Helpers::term_summary( get_term( $term['term_id'], 'post_tag' ) ) );
		},
	),

	array(
		'name'        => 'wp_update_tag',
		'description' => __( 'Updates an existing tag.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:write',
		'category'    => 'taxonomies',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
				'name'        => array( 'type' => 'string', 'minLength' => 1 ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'manage_categories' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Managing tags requires the manage_categories capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$update = array();
			if ( isset( $args['name'] ) ) {
				$update['name'] = sanitize_text_field( $args['name'] );
			}
			if ( isset( $args['slug'] ) ) {
				$update['slug'] = sanitize_title( $args['slug'] );
			}
			if ( isset( $args['description'] ) ) {
				$update['description'] = $args['description'];
			}
			$result = wp_update_term( (int) $args['id'], 'post_tag', $update );
			if ( is_wp_error( $result ) ) {
				throw new Tool_Error( 'update_failed', $result->get_error_message() );
			}
			return array( 'updated' => true, 'tag' => Tool_Helpers::term_summary( get_term( (int) $args['id'], 'post_tag' ) ) );
		},
	),

	array(
		'name'        => 'wp_delete_tag',
		'description' => __( 'Deletes a tag. Destructive action.', 'mcp-connect-wp' ),
		'scope'       => 'taxonomies:delete',
		'category'    => 'taxonomies',
		'mode'        => 'delete',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'manage_categories' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Managing tags requires the manage_categories capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$term = get_term( (int) $args['id'], 'post_tag' );
			if ( ! $term ) {
				throw new Tool_Error( 'not_found', __( 'Tag not found.', 'mcp-connect-wp' ) );
			}
			$result = wp_delete_term( (int) $args['id'], 'post_tag' );
			if ( is_wp_error( $result ) ) {
				throw new Tool_Error( 'deletion_failed', $result->get_error_message() );
			}
			if ( ! $result ) {
				throw new Tool_Error( 'deletion_failed', __( 'The tag could not be deleted.', 'mcp-connect-wp' ) );
			}
			return array( 'deleted' => true, 'id' => (int) $term->term_id );
		},
	),
);
<?php

namespace MCPConnect;

use MCPConnect\Tool_Error;
use MCPConnect\Tool_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress posts MCP tools.
 */
return array(

	array(
		'name'        => 'wp_list_posts',
		'description' => __( 'Lists WordPress posts. Use this tool when the user asks to see, list or show their latest posts, articles or blog posts. Non-published statuses require the edit_posts capability.', 'mcp-connect-wp' ),
		'scope'       => 'posts:read',
		'category'    => 'posts',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'number'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10, 'description' => __( 'Number of posts to return.', 'mcp-connect-wp' ) ),
				'offset'  => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => __( 'Pagination offset.', 'mcp-connect-wp' ) ),
				'status'  => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ), 'default' => 'publish', 'description' => __( 'Post status to filter by.', 'mcp-connect-wp' ) ),
				'search'  => array( 'type' => 'string', 'description' => __( 'Search term in the title and content.', 'mcp-connect-wp' ) ),
				'category'=> array( 'type' => 'integer', 'description' => __( 'Category ID to filter by.', 'mcp-connect-wp' ) ),
				'tag'     => array( 'type' => 'integer', 'description' => __( 'Tag ID to filter by.', 'mcp-connect-wp' ) ),
				'author'  => array( 'type' => 'integer', 'description' => __( 'Author user ID.', 'mcp-connect-wp' ) ),
				'orderby' => array( 'type' => 'string', 'enum' => array( 'date', 'modified', 'title', 'ID', 'comment_count' ), 'default' => 'date', 'description' => __( 'Field to order by.', 'mcp-connect-wp' ) ),
				'order'   => array( 'type' => 'string', 'enum' => array( 'DESC', 'ASC' ), 'default' => 'DESC', 'description' => __( 'Sort direction.', 'mcp-connect-wp' ) ),
			),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			$status = Tool_Helpers::arg( $args, 'status', 'publish' );
			if ( ! in_array( $status, array( 'publish', 'any' ), true ) && ! Permissions::user_has( $actor['user_id'], 'edit_posts' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Non-published posts require the edit_posts capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$status = Tool_Helpers::arg( $args, 'status', 'publish' );
			$query  = array(
				'post_type'      => 'post',
				'post_status'    => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ) : array( $status ),
				'posts_per_page' => (int) Tool_Helpers::arg( $args, 'number', 10 ),
				'offset'         => (int) Tool_Helpers::arg( $args, 'offset', 0 ),
				'orderby'        => Tool_Helpers::arg( $args, 'orderby', 'date' ),
				'order'          => Tool_Helpers::arg( $args, 'order', 'DESC' ),
				'no_found_rows'  => true,
			);
			if ( Tool_Helpers::arg( $args, 'search' ) ) {
				$query['s'] = sanitize_text_field( $args['search'] );
			}
			if ( Tool_Helpers::arg( $args, 'category' ) ) {
				$query['cat'] = (int) $args['category'];
			}
			if ( Tool_Helpers::arg( $args, 'tag' ) ) {
				$query['tag_id'] = (int) $args['tag'];
			}
			if ( Tool_Helpers::arg( $args, 'author' ) ) {
				$query['author'] = (int) $args['author'];
			}

			$posts   = get_posts( $query );
			$results = array();
			foreach ( $posts as $post ) {
				$results[] = Tool_Helpers::post_brief( $post );
			}

			return array(
				'total' => count( $results ),
				'posts' => $results,
			);
		},
	),

	array(
		'name'        => 'wp_get_post',
		'description' => __( 'Gets a single WordPress post by ID with its full content. Use this tool when the user references a specific post or article.', 'mcp-connect-wp' ),
		'scope'       => 'posts:read',
		'category'    => 'posts',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'integer', 'description' => __( 'Post ID.', 'mcp-connect-wp' ), 'minimum' => 1 ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			$post = get_post( (int) $args['id'] );
			if ( $post && 'publish' !== $post->post_status && ! Permissions::user_has( $actor['user_id'], 'edit_post', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot read this unpublished post.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$post = Tool_Helpers::ensure_post_exists( (int) $args['id'] );
			return array( 'post' => Tool_Helpers::post_detailed( $post ) );
		},
	),

	array(
		'name'        => 'wp_create_post',
		'description' => __( 'Creates a new WordPress post. Use this tool when the user asks to create, write or draft a new article, blog post or post. The post can be created as draft, pending, private or published, subject to the authenticated user\'s capabilities.', 'mcp-connect-wp' ),
		'scope'       => 'posts:write',
		'category'    => 'posts',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'title'         => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'description' => __( 'Post title.', 'mcp-connect-wp' ) ),
				'content'       => array( 'type' => 'string', 'description' => __( 'Post content (HTML or plain text).', 'mcp-connect-wp' ) ),
				'status'        => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ), 'default' => 'draft', 'description' => __( 'Post status.', 'mcp-connect-wp' ) ),
				'excerpt'       => array( 'type' => 'string', 'description' => __( 'Post excerpt.', 'mcp-connect-wp' ) ),
				'slug'          => array( 'type' => 'string', 'description' => __( 'URL slug.', 'mcp-connect-wp' ) ),
				'categories'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => __( 'Category IDs.', 'mcp-connect-wp' ) ),
				'tags'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Tag names or IDs.', 'mcp-connect-wp' ) ),
				'featured_media' => array( 'type' => 'integer', 'description' => __( 'Featured image attachment ID.', 'mcp-connect-wp' ) ),
				'author'        => array( 'type' => 'integer', 'description' => __( 'Author user ID (requires edit_others_posts).', 'mcp-connect-wp' ) ),
			),
			'required'             => array( 'title' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'edit_posts' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Creating posts requires the edit_posts capability.', 'mcp-connect-wp' ) );
			}
			$status = Tool_Helpers::arg( $args, 'status', 'draft' );
			if ( in_array( $status, array( 'publish', 'private' ), true ) && ! Permissions::user_has( $actor['user_id'], 'publish_posts' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'The authenticated WordPress user cannot publish posts.', 'mcp-connect-wp' ) );
			}
			if ( Tool_Helpers::arg( $args, 'author' ) && (int) $args['author'] !== (int) $actor['user_id'] && ! Permissions::user_has( $actor['user_id'], 'edit_others_posts' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Changing the author requires the edit_others_posts capability.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$postarr = array(
				'post_type'    => 'post',
				'post_title'   => sanitize_text_field( $args['title'] ),
				'post_content' => isset( $args['content'] ) ? $args['content'] : '',
				'post_status'  => Tool_Helpers::arg( $args, 'status', 'draft' ),
				'post_excerpt' => Tool_Helpers::arg( $args, 'excerpt', '' ),
				'post_name'    => Tool_Helpers::arg( $args, 'slug', '' ),
			);
			if ( isset( $args['author'] ) ) {
				$postarr['post_author'] = (int) $args['author'];
			}
			if ( isset( $args['categories'] ) ) {
				$postarr['post_category'] = array_map( 'intval', (array) $args['categories'] );
			}
			if ( isset( $args['tags'] ) ) {
				$terms = array();
				foreach ( (array) $args['tags'] as $tag ) {
					if ( is_numeric( $tag ) ) {
						$terms[] = (int) $tag;
					} else {
						$created = wp_insert_term( sanitize_text_field( $tag ), 'post_tag' );
						$terms[] = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
					}
				}
				$postarr['tax_input'] = array( 'post_tag' => $terms );
			}

			$id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $id ) ) {
				throw new Tool_Error( 'creation_failed', $id->get_error_message() );
			}
			if ( isset( $args['featured_media'] ) ) {
				set_post_thumbnail( $id, (int) $args['featured_media'] );
			}
			return array( 'created' => true, 'post' => Tool_Helpers::post_detailed( get_post( $id ) ) );
		},
	),

	array(
		'name'        => 'wp_update_post',
		'description' => __( 'Updates an existing WordPress post. Use this tool when the user asks to edit, change, update or revise a post or article.', 'mcp-connect-wp' ),
		'scope'       => 'posts:write',
		'category'    => 'posts',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'            => array( 'type' => 'integer', 'description' => __( 'Post ID.', 'mcp-connect-wp' ) ),
				'title'         => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 255, 'description' => __( 'Post title.', 'mcp-connect-wp' ) ),
				'content'       => array( 'type' => 'string', 'description' => __( 'Post content.', 'mcp-connect-wp' ) ),
				'status'        => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'publish', 'private' ), 'description' => __( 'Post status.', 'mcp-connect-wp' ) ),
				'excerpt'       => array( 'type' => 'string', 'description' => __( 'Post excerpt.', 'mcp-connect-wp' ) ),
				'slug'          => array( 'type' => 'string', 'description' => __( 'URL slug.', 'mcp-connect-wp' ) ),
				'categories'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => __( 'Category IDs.', 'mcp-connect-wp' ) ),
				'tags'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Tag names or IDs.', 'mcp-connect-wp' ) ),
				'featured_media' => array( 'type' => 'integer', 'description' => __( 'Featured image attachment ID.', 'mcp-connect-wp' ) ),
				'author'        => array( 'type' => 'integer', 'description' => __( 'Author user ID (requires edit_others_posts).', 'mcp-connect-wp' ) ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'edit_post', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot edit this post.', 'mcp-connect-wp' ) );
			}
			$post = get_post( (int) $args['id'] );
			if ( $post && isset( $args['status'] ) && in_array( $args['status'], array( 'publish', 'private' ), true ) && 'publish' !== $post->post_status && 'private' !== $post->post_status && ! Permissions::user_has( $actor['user_id'], 'publish_posts' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Publishing posts requires the publish_posts capability.', 'mcp-connect-wp' ) );
			}
			if ( Tool_Helpers::arg( $args, 'author' ) && (int) $args['author'] !== (int) $actor['user_id'] && ! Permissions::user_has( $actor['user_id'], 'edit_others_posts' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Changing the author requires the edit_others_posts capability.', 'mcp-connect-wp' ) );
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
			if ( isset( $args['author'] ) ) {
				$postarr['post_author'] = (int) $args['author'];
			}
			if ( isset( $args['categories'] ) ) {
				$postarr['post_category'] = array_map( 'intval', (array) $args['categories'] );
			}
			if ( isset( $args['tags'] ) ) {
				$terms = array();
				foreach ( (array) $args['tags'] as $tag ) {
					if ( is_numeric( $tag ) ) {
						$terms[] = (int) $tag;
					} else {
						$created = wp_insert_term( sanitize_text_field( $tag ), 'post_tag' );
						$terms[] = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
					}
				}
				$postarr['tax_input'] = array( 'post_tag' => $terms );
			}
			if ( array_key_exists( 'featured_media', $args ) ) {
				if ( $args['featured_media'] ) {
					set_post_thumbnail( $post->ID, (int) $args['featured_media'] );
				} else {
					delete_post_thumbnail( $post->ID );
				}
			}

			$updated = wp_update_post( $postarr, true );
			if ( is_wp_error( $updated ) ) {
				throw new Tool_Error( 'update_failed', $updated->get_error_message() );
			}
			return array( 'updated' => true, 'post' => Tool_Helpers::post_detailed( get_post( $updated ) ) );
		},
	),

	array(
		'name'        => 'wp_delete_post',
		'description' => __( 'Deletes a WordPress post. Moves the post to the trash by default, or permanently deletes it when force is true. This is a destructive action.', 'mcp-connect-wp' ),
		'scope'       => 'posts:delete',
		'category'    => 'posts',
		'mode'        => 'delete',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'    => array( 'type' => 'integer', 'description' => __( 'Post ID.', 'mcp-connect-wp' ) ),
				'force' => array( 'type' => 'boolean', 'default' => false, 'description' => __( 'Permanently delete instead of trashing.', 'mcp-connect-wp' ) ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'delete_post', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot delete this post.', 'mcp-connect-wp' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$post = Tool_Helpers::ensure_post_exists( (int) $args['id'] );
			if ( ! empty( $args['force'] ) ) {
				$result = wp_delete_post( $post->ID, true );
				if ( ! $result ) {
					throw new Tool_Error( 'deletion_failed', __( 'The post could not be deleted.', 'mcp-connect-wp' ) );
				}
				return array( 'deleted' => true, 'permanent' => true, 'id' => (int) $post->ID );
			}

			$trashed = wp_trash_post( $post->ID );
			if ( ! $trashed ) {
				$result = wp_delete_post( $post->ID, false );
				if ( ! $result ) {
					throw new Tool_Error( 'deletion_failed', __( 'The post could not be deleted.', 'mcp-connect-wp' ) );
				}
				return array( 'deleted' => true, 'permanent' => false, 'id' => (int) $post->ID );
			}
			return array( 'deleted' => true, 'permanent' => false, 'id' => (int) $trashed->ID, 'trashed' => true );
		},
	),
);
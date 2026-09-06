<?php

namespace WPMCPConnect;

use WPMCPConnect\Tool_Error;
use WPMCPConnect\Tool_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress comments MCP tools.
 */
return array(

	array(
		'name'        => 'wp_list_comments',
		'description' => __( 'Lists comments. Use this tool when the user asks to see comments on their site or on a specific post.', 'wp-mcp-connect' ),
		'scope'       => 'comments:read',
		'category'    => 'comments',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array( 'type' => 'integer', 'description' => __( 'Only comments for this post.', 'wp-mcp-connect' ) ),
				'number'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				'status'  => array( 'type' => 'string', 'enum' => array( 'approved', 'hold', 'spam', 'trash', 'all' ), 'default' => 'approved', 'description' => __( 'Comment status.', 'wp-mcp-connect' ) ),
				'search'  => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			$status = Tool_Helpers::arg( $args, 'status', 'approved' );
			if ( in_array( $status, array( 'hold', 'spam', 'trash' ), true ) && ! Permissions::user_has( $actor['user_id'], 'moderate_comments' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Viewing non-approved comments requires the moderate_comments capability.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$status = Tool_Helpers::arg( $args, 'status', 'approved' );
			$query  = array(
				'number' => (int) Tool_Helpers::arg( $args, 'number', 20 ),
				'status' => 'all' === $status ? 'all' : $status,
			);
			if ( Tool_Helpers::arg( $args, 'post_id' ) ) {
				$query['post_id'] = (int) $args['post_id'];
			}
			if ( Tool_Helpers::arg( $args, 'search' ) ) {
				$query['search'] = sanitize_text_field( $args['search'] );
			}

			$comments = get_comments( $query );
			$out      = array();
			foreach ( $comments as $comment ) {
				$out[] = Tool_Helpers::comment_summary( $comment );
			}
			return array( 'total' => count( $out ), 'comments' => $out );
		},
	),

	array(
		'name'        => 'wp_get_comment',
		'description' => __( 'Gets a single comment by ID.', 'wp-mcp-connect' ),
		'scope'       => 'comments:read',
		'category'    => 'comments',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'handler'     => function ( $args ) {
			$comment = get_comment( (int) $args['id'] );
			if ( ! $comment ) {
				throw new Tool_Error( 'not_found', __( 'Comment not found.', 'wp-mcp-connect' ) );
			}
			return array( 'comment' => Tool_Helpers::comment_summary( $comment ) );
		},
	),

	array(
		'name'        => 'wp_update_comment',
		'description' => __( 'Updates an existing comment: edits its content or changes its status. Changing a comment not written by you requires the moderate_comments capability.', 'wp-mcp-connect' ),
		'scope'       => 'comments:write',
		'category'    => 'comments',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'      => array( 'type' => 'integer', 'minimum' => 1 ),
				'content' => array( 'type' => 'string', 'minLength' => 1, 'description' => __( 'New comment content.', 'wp-mcp-connect' ) ),
				'status'  => array( 'type' => 'string', 'enum' => array( 'approved', 'hold', 'spam', 'trash' ), 'description' => __( 'New comment status.', 'wp-mcp-connect' ) ),
				'author'  => array( 'type' => 'string', 'description' => __( 'Author display name.', 'wp-mcp-connect' ) ),
				'author_email' => array( 'type' => 'string', 'format' => 'email', 'description' => __( 'Author email.', 'wp-mcp-connect' ) ),
				'author_url'   => array( 'type' => 'string', 'description' => __( 'Author website.', 'wp-mcp-connect' ) ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			$comment = get_comment( (int) $args['id'] );
			if ( ! $comment ) {
				return array( 'allowed' => false, 'code' => 'not_found', 'message' => __( 'Comment not found.', 'wp-mcp-connect' ) );
			}
			$is_author = (int) $comment->user_id === (int) $actor['user_id'] && (int) $comment->user_id > 0;
			if ( ! $is_author && ! Permissions::user_has( $actor['user_id'], 'moderate_comments' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Editing this comment requires the moderate_comments capability.', 'wp-mcp-connect' ) );
			}
			if ( isset( $args['status'] ) && 'hold' === $comment->comment_approved && 'approved' === $args['status'] && ! Permissions::user_has( $actor['user_id'], 'moderate_comments' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Approving comments requires the moderate_comments capability.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$comment      = get_comment( (int) $args['id'] );
			$commentdata  = array( 'comment_ID' => $comment->comment_ID );
			if ( isset( $args['content'] ) ) {
				$commentdata['comment_content'] = wp_kses_post( $args['content'] );
			}
			if ( isset( $args['status'] ) ) {
				$map = array(
					'approved' => 1,
					'hold'     => 0,
					'spam'     => 'spam',
					'trash'    => 'trash',
				);
				$commentdata['comment_approved'] = $map[ $args['status'] ];
			}
			if ( isset( $args['author'] ) ) {
				$commentdata['comment_author'] = sanitize_text_field( $args['author'] );
			}
			if ( isset( $args['author_email'] ) ) {
				$commentdata['comment_author_email'] = sanitize_email( $args['author_email'] );
			}
			if ( isset( $args['author_url'] ) ) {
				$commentdata['comment_author_url'] = esc_url_raw( $args['author_url'] );
			}

			$result = wp_update_comment( $commentdata );
			if ( 0 === $result && ! is_wp_error( $result ) ) {
				throw new Tool_Error( 'update_failed', __( 'The comment could not be updated.', 'wp-mcp-connect' ) );
			}
			if ( is_wp_error( $result ) ) {
				throw new Tool_Error( 'update_failed', $result->get_error_message() );
			}
			return array( 'updated' => true, 'comment' => Tool_Helpers::comment_summary( get_comment( (int) $args['id'] ) ) );
		},
	),
);
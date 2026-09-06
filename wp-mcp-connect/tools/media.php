<?php

namespace WPMCPConnect;

use WPMCPConnect\Tool_Error;
use WPMCPConnect\Tool_Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress media (attachments) MCP tools.
 */
return array(

	array(
		'name'        => 'wp_list_media',
		'description' => __( 'Lists media attachments on the site. Use this tool when the user asks to see their images, files or media library.', 'wp-mcp-connect' ),
		'scope'       => 'media:read',
		'category'    => 'media',
		'mode'        => 'read',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'number'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				'offset'   => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
				'search'   => array( 'type' => 'string' ),
				'mime_type' => array( 'type' => 'string', 'description' => __( 'Filter by MIME type, e.g. image/ or image/png.', 'wp-mcp-connect' ) ),
			),
			'additionalProperties' => false,
		),
		'handler'     => function ( $args ) {
			$query = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => (int) Tool_Helpers::arg( $args, 'number', 20 ),
				'offset'         => (int) Tool_Helpers::arg( $args, 'offset', 0 ),
				'no_found_rows'  => true,
			);
			if ( Tool_Helpers::arg( $args, 'search' ) ) {
				$query['s'] = sanitize_text_field( $args['search'] );
			}
			$mime = Tool_Helpers::arg( $args, 'mime_type', '' );
			if ( $mime && strpos( $mime, '/' ) ) {
				$query['post_mime_type'] = sanitize_mime_type( $mime );
			}

			$items = get_posts( $query );
			$out   = array();
			foreach ( $items as $item ) {
				$out[] = Tool_Helpers::attachment_summary( $item );
			}
			return array( 'total' => count( $out ), 'media' => $out );
		},
	),

	array(
		'name'        => 'wp_get_media',
		'description' => __( 'Gets a single media attachment by ID.', 'wp-mcp-connect' ),
		'scope'       => 'media:read',
		'category'    => 'media',
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
			if ( ! isset( $args['id'] ) || ! Permissions::user_has( $actor['user_id'], 'read_post', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You do not have permission to read this media item.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$attachment = get_post( (int) $args['id'] );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				throw new Tool_Error( 'not_found', __( 'Media item not found.', 'wp-mcp-connect' ) );
			}
			return array( 'media' => Tool_Helpers::attachment_summary( $attachment ) );
		},
	),

	array(
		'name'        => 'wp_upload_media',
		'description' => __( 'Uploads a media file to the WordPress media library. Provide the file bytes base64-encoded. The MIME type, extension and size are validated.', 'wp-mcp-connect' ),
		'scope'       => 'media:write',
		'category'    => 'media',
		'mode'        => 'write',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'filename'    => array( 'type' => 'string', 'minLength' => 1, 'description' => __( 'Filename including extension, e.g. photo.jpg.', 'wp-mcp-connect' ) ),
				'data'        => array( 'type' => 'string', 'minLength' => 1, 'description' => __( 'File content base64-encoded.', 'wp-mcp-connect' ) ),
				'mime_type'   => array( 'type' => 'string', 'description' => __( 'Expected MIME type, e.g. image/png.', 'wp-mcp-connect' ) ),
				'title'       => array( 'type' => 'string', 'description' => __( 'Attachment title.', 'wp-mcp-connect' ) ),
				'caption'     => array( 'type' => 'string' ),
				'alt'         => array( 'type' => 'string', 'description' => __( 'Alt text for images.', 'wp-mcp-connect' ) ),
				'description' => array( 'type' => 'string' ),
			),
			'required'             => array( 'filename', 'data' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'upload_files' ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'Uploading media requires the upload_files capability.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$file_name = sanitize_file_name( $args['filename'] );
			if ( ! $file_name ) {
				throw new Tool_Error( 'invalid_file', __( 'The filename could not be sanitized.', 'wp-mcp-connect' ) );
			}

			$binary = Crypto::base64url_decode( $args['data'] );
			if ( '' === $binary ) {
				throw new Tool_Error( 'invalid_file', __( 'The provided data is not valid base64.', 'wp-mcp-connect' ) );
			}

			$max_size = wp_max_upload_size();
			if ( strlen( $binary ) > $max_size ) {
				throw new Tool_Error( 'file_too_large', sprintf( __( 'File exceeds the maximum upload size of %d bytes.', 'wp-mcp-connect' ), $max_size ) );
			}

			$wp_filetype = wp_check_filetype_and_ext( $file_name, $file_name );
			$ext         = $wp_filetype['ext'];
			$type        = $wp_filetype['type'];

			$requested_type = isset( $args['mime_type'] ) ? sanitize_mime_type( $args['mime_type'] ) : '';
			if ( ! $type && $requested_type ) {
				$check = wp_check_filetype_and_ext( $file_name, $file_name, array( $requested_type => $requested_type ) );
				$type  = $check['type'];
				$ext   = $check['ext'];
			}

			if ( ! $type || ! $ext ) {
				throw new Tool_Error( 'invalid_mime', __( 'The file type is not allowed or could not be determined.', 'wp-mcp-connect' ) );
			}

			$bits = wp_upload_bits( $file_name, null, $binary );
			if ( ! empty( $bits['error'] ) ) {
				throw new Tool_Error( 'upload_failed', $bits['error'] );
			}

			$attachment = array(
				'post_mime_type' => $type,
				'post_title'     => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : preg_replace( '/\.[^.]+$/', '', $file_name ),
				'post_content'   => isset( $args['description'] ) ? $args['description'] : '',
				'post_excerpt'   => isset( $args['caption'] ) ? $args['caption'] : '',
				'post_status'    => 'inherit',
			);
			$attach_id   = wp_insert_attachment( $attachment, $bits['file'] );
			if ( ! $attach_id || is_wp_error( $attach_id ) ) {
				throw new Tool_Error( 'upload_failed', __( 'The attachment could not be created.', 'wp-mcp-connect' ) );
			}

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$metadata = wp_generate_attachment_metadata( $attach_id, $bits['file'] );
			wp_update_attachment_metadata( $attach_id, $metadata );

			if ( isset( $args['alt'] ) && strpos( $type, 'image/' ) === 0 ) {
				update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
			}

			return array( 'uploaded' => true, 'media' => Tool_Helpers::attachment_summary( get_post( $attach_id ) ) );
		},
	),

	array(
		'name'        => 'wp_delete_media',
		'description' => __( 'Deletes a media attachment. Destructive action.', 'wp-mcp-connect' ),
		'scope'       => 'media:delete',
		'category'    => 'media',
		'mode'        => 'delete',
		'inputSchema' => array(
			'type'                 => 'object',
			'properties'           => array(
				'id'    => array( 'type' => 'integer', 'minimum' => 1 ),
				'force' => array( 'type' => 'boolean', 'default' => false, 'description' => __( 'Delete the underlying file too.', 'wp-mcp-connect' ) ),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		),
		'permission'  => function ( $args, $actor ) {
			if ( ! Permissions::user_has( $actor['user_id'], 'delete_post', (int) $args['id'] ) ) {
				return array( 'allowed' => false, 'code' => 'insufficient_permissions', 'message' => __( 'You cannot delete this media item.', 'wp-mcp-connect' ) );
			}
			return array( 'allowed' => true );
		},
		'handler'     => function ( $args ) {
			$attachment = get_post( (int) $args['id'] );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				throw new Tool_Error( 'not_found', __( 'Media item not found.', 'wp-mcp-connect' ) );
			}
			$result = wp_delete_attachment( (int) $args['id'], ! empty( $args['force'] ) );
			if ( ! $result ) {
				throw new Tool_Error( 'deletion_failed', __( 'The media item could not be deleted.', 'wp-mcp-connect' ) );
			}
			return array( 'deleted' => true, 'id' => (int) $attachment->ID );
		},
	),
);
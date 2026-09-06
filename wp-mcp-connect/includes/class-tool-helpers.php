<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

final class Tool_Helpers {

	public static function post_brief( \WP_Post $post ) {
		$author_id = (int) $post->post_author;
		$author    = get_userdata( $author_id );
		return array(
			'id'     => (int) $post->ID,
			'title'  => $post->post_title,
			'type'   => $post->post_type,
			'status' => $post->post_status,
			'date'   => $post->post_date,
			'modified' => $post->post_modified,
			'slug'   => $post->post_name,
			'author' => $author ? $author->display_name : '',
			'link'   => get_permalink( $post->ID ),
			'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
		);
	}

	public static function post_detailed( \WP_Post $post ) {
		$brief = self::post_brief( $post );
		$cats  = get_the_terms( $post->ID, 'category' );
		$tags  = get_the_terms( $post->ID, 'post_tag' );
		return array_merge(
			$brief,
			array(
				'content'     => $post->post_content,
				'categories'  => $cats ? wp_list_pluck( $cats, 'name' ) : array(),
				'tags'        => $tags ? wp_list_pluck( $tags, 'name' ) : array(),
				'comment_status' => $post->comment_status,
				'password'    => '' !== $post->post_password,
			)
		);
	}

	public static function attachment_summary( \WP_Post $attachment ) {
		$meta = wp_get_attachment_metadata( $attachment->ID );
		return array(
			'id'     => (int) $attachment->ID,
			'title'  => $attachment->post_title,
			'caption' => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'alt'    => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'mime'   => $attachment->post_mime_type,
			'url'    => wp_get_attachment_url( $attachment->ID ),
			'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height' => isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'file'   => isset( $meta['file'] ) ? $meta['file'] : '',
			'size'   => isset( $meta['filesize'] ) ? (int) $meta['filesize'] : null,
			'date'   => $attachment->post_date,
		);
	}

	public static function comment_summary( \WP_Comment $comment ) {
		return array(
			'id'        => (int) $comment->comment_ID,
			'post_id'   => (int) $comment->comment_post_ID,
			'author'    => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'author_url'   => $comment->comment_author_url,
			'content'   => $comment->comment_content,
			'status'    => wp_get_comment_status( $comment->comment_ID ),
			'date'      => $comment->comment_date,
			'approved'  => '1' === $comment->comment_approved,
		);
	}

	public static function term_summary( \WP_Term $term ) {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'taxonomy'    => $term->taxonomy,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	public static function arg( array $args, $key, $default = null ) {
		return array_key_exists( $key, $args ) ? $args[ $key ] : $default;
	}

	public static function ensure_post_exists( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post ) {
			throw new Tool_Error( 'not_found', __( 'Post not found.', 'wp-mcp-connect' ) );
		}
		return $post;
	}

	public static function allowed_statuses_for_user( $user_id ) {
		$statuses = array( 'draft', 'pending' );
		if ( Permissions::user_has( $user_id, 'publish_posts' ) ) {
			$statuses = array_merge( $statuses, array( 'publish', 'private' ) );
		}
		return $statuses;
	}
}
<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Maps OAuth scopes to WordPress capabilities and offers capability helpers.
 */
final class Permissions {

	const SCOPE_LABELS = array(
		'site:read'         => 'Ver información del sitio',
		'posts:read'        => 'Leer publicaciones',
		'posts:write'       => 'Crear y editar publicaciones',
		'posts:delete'      => 'Eliminar publicaciones',
		'pages:read'        => 'Leer páginas',
		'pages:write'       => 'Crear y editar páginas',
		'pages:delete'      => 'Eliminar páginas',
		'media:read'        => 'Ver archivos multimedia',
		'media:write'       => 'Subir y editar multimedia',
		'media:delete'      => 'Eliminar multimedia',
		'comments:read'     => 'Leer comentarios',
		'comments:write'    => 'Editar comentarios',
		'taxonomies:read'   => 'Ver categorías y etiquetas',
		'taxonomies:write'  => 'Crear y editar categorías y etiquetas',
		'taxonomies:delete' => 'Eliminar categorías y etiquetas',
		'users:read'        => 'Ver tu usuario actual',
	);

	public static function is_valid_scope( $scope ) {
		return array_key_exists( $scope, self::SCOPE_LABELS );
	}

	public static function scope_parts( $scope ) {
		if ( ! is_string( $scope ) || strpos( $scope, ':' ) === false ) {
			return array( '', '' );
		}
		$parts = explode( ':', $scope, 2 );
		return array( $parts[0], $parts[1] );
	}

	public static function parse_scopes( $scope_string ) {
		if ( ! is_string( $scope_string ) || '' === trim( $scope_string ) ) {
			return array();
		}
		$scopes = array_map( 'trim', preg_split( '/\s+/', trim( $scope_string ) ) );
		$scopes = array_filter(
			$scopes,
			function ( $s ) {
				return self::is_valid_scope( $s );
			}
		);
		return array_values( array_unique( $scopes ) );
	}

	public static function has_scope( $granted_scopes, $required_scope ) {
		if ( empty( $required_scope ) ) {
			return true;
		}
		if ( ! is_array( $granted_scopes ) ) {
			return false;
		}
		return in_array( $required_scope, $granted_scopes, true );
	}

	public static function user_has( $user_id, $capability, $object_id = null ) {
		if ( ! function_exists( 'user_can' ) ) {
			return false;
		}
		if ( null === $object_id ) {
			return user_can( $user_id, $capability );
		}
		return user_can( $user_id, $capability, $object_id );
	}

	public static function wp_user_has( $capability, $object_id = null ) {
		if ( null === $object_id ) {
			return current_user_can( $capability );
		}
		return current_user_can( $capability, $object_id );
	}

	public static function switch_user( $user_id ) {
		global $current_user;
		$previous          = isset( $current_user ) ? $current_user : null;
		$old_user_id       = get_current_user_id();
		wp_set_current_user( (int) $user_id );
		return array( 'previous' => $previous, 'old_user_id' => $old_user_id );
	}

	public static function restore_user( $snapshot ) {
		if ( ! empty( $snapshot['previous'] ) && is_a( $snapshot['previous'], 'WP_User' ) ) {
			wp_set_current_user( $snapshot['previous']->ID );
		} elseif ( isset( $snapshot['old_user_id'] ) ) {
			wp_set_current_user( $snapshot['old_user_id'] );
		}
	}

	public static function relevant_capabilities( $user_id ) {
		$caps = array( 'read', 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts', 'edit_pages', 'publish_pages', 'delete_pages', 'upload_files', 'manage_categories', 'moderate_comments' );
		$out  = array();
		foreach ( $caps as $cap ) {
			if ( self::user_has( $user_id, $cap ) ) {
				$out[ $cap ] = true;
			}
		}
		return $out;
	}
}
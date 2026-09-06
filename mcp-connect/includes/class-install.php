<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

final class Install {

	const DB_VERSION = '1.0.0';
	const OPTION_DB  = 'mcp_connect_db_version';

	public static function activate() {
		self::create_tables();
		self::ensure_settings();
		update_option( 'mcp_connect_flush_rewrite', 1 );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function uninstall() {
		$settings = (array) get_option( Settings::OPTION, array() );
		if ( empty( $settings['delete_on_uninstall'] ) ) {
			return;
		}

		global $wpdb;
		foreach ( self::tables() as $table ) {
			$name = self::table( $table );
			// Identifier interpolation: %i requires WP 6.2+; support earlier versions.
			$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '``', $name ) . '`' );
		}
		delete_option( Settings::OPTION );
		delete_option( self::OPTION_DB );
		delete_option( 'mcp_connect_flush_rewrite' );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::OPTION_DB ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::OPTION_DB, self::DB_VERSION );
		}
		self::ensure_tables();
		if ( get_option( 'mcp_connect_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'mcp_connect_flush_rewrite' );
		}
	}

	/**
	 * Verifies every table exists and (re)creates any that are missing.
	 * Guards against partial installs where the db-version option was already
	 * set but a table is absent, which would otherwise fatal on insert.
	 */
	public static function ensure_tables() {
		global $wpdb;
		$existing = $wpdb->get_col( "SHOW TABLES LIKE '" . $wpdb->prefix . 'mcp_connect\_%' . "'" );
		$needed   = array_map( array( __CLASS__, 'table' ), self::tables() );
		$missing  = array_diff( $needed, $existing );
		if ( $missing ) {
			self::create_tables();
		}
	}

	public static function ensure_settings() {
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'mcp_connect_' . $name;
	}

	public static function tables() {
		return array( 'clients', 'codes', 'tokens', 'authorizations', 'logs', 'rate' );
	}

	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$queries = array();

		$queries[] = "CREATE TABLE " . self::table( 'clients' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(191) NOT NULL,
			client_name varchar(191) NOT NULL DEFAULT '',
			client_secret_hash char(64) NOT NULL DEFAULT '',
			redirect_uris longtext NULL,
			grant_types text NULL,
			response_types text NULL,
			token_endpoint_auth_method varchar(32) NOT NULL DEFAULT 'none',
			default_scope text NULL,
			is_dynamic tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			last_used_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id)
		) $charset;";

		$queries[] = "CREATE TABLE " . self::table( 'codes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code_hash char(64) NOT NULL,
			client_id varchar(191) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			redirect_uri text NOT NULL,
			scopes text NULL,
			resource varchar(255) NOT NULL DEFAULT '',
			code_challenge varchar(128) NOT NULL DEFAULT '',
			code_challenge_method varchar(16) NOT NULL DEFAULT 'S256',
			expires_at datetime NOT NULL,
			used tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code_hash (code_hash),
			KEY expires_at (expires_at)
		) $charset;";

		$queries[] = "CREATE TABLE " . self::table( 'tokens' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			type varchar(16) NOT NULL,
			family_id char(32) NOT NULL,
			auth_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id varchar(191) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			scopes text NULL,
			resource varchar(255) NOT NULL DEFAULT '',
			expires_at datetime NOT NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY client_id (client_id),
			KEY user_id (user_id),
			KEY family_id (family_id),
			KEY auth_id (auth_id),
			KEY expires_at (expires_at)
		) $charset;";

		$queries[] = "CREATE TABLE " . self::table( 'authorizations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(191) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			scopes text NULL,
			resource varchar(255) NOT NULL DEFAULT '',
			granted_at datetime NOT NULL,
			last_used_at datetime NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY user_id (user_id)
		) $charset;";

		$queries[] = "CREATE TABLE " . self::table( 'logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ts datetime NOT NULL,
			level varchar(16) NOT NULL,
			client_id varchar(191) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			method varchar(128) NOT NULL DEFAULT '',
			result varchar(16) NOT NULL DEFAULT '',
			code varchar(64) NOT NULL DEFAULT '',
			duration_ms bigint(20) NOT NULL DEFAULT 0,
			ip varchar(64) NOT NULL DEFAULT '',
			note text NULL,
			PRIMARY KEY  (id),
			KEY ts (ts),
			KEY level (level)
		) $charset;";

		$queries[] = "CREATE TABLE " . self::table( 'rate' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip varchar(64) NOT NULL,
			endpoint varchar(64) NOT NULL,
			hits bigint(20) unsigned NOT NULL DEFAULT 1,
			window_start datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ip_endpoint (ip, endpoint)
		) $charset;";

		foreach ( $queries as $query ) {
			dbDelta( $query );
		}
	}
}
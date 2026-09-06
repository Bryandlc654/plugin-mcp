<?php

namespace WPMCPConnect;

defined( 'ABSPATH' ) || exit;

final class Logger {

	public function log( $level, $client_id, $user_id, $method, $result, $code, $duration_ms, $ip, $note = '' ) {
		$configured = Plugin::instance()->settings->log_level();
		if ( 'off' === $configured ) {
			return;
		}
		if ( 'errors' === $configured && 'error' !== $level ) {
			return;
		}

		global $wpdb;
		$wpdb->insert(
			Install::table( 'logs' ),
			array(
				'ts'          => current_time( 'mysql' ),
				'level'       => $level,
				'client_id'   => substr( (string) $client_id, 0, 191 ),
				'user_id'     => (int) $user_id,
				'method'      => substr( (string) $method, 0, 128 ),
				'result'      => substr( (string) $result, 0, 16 ),
				'code'        => substr( (string) $code, 0, 64 ),
				'duration_ms' => (int) $duration_ms,
				'ip'          => substr( (string) $ip, 0, 64 ),
				'note'        => substr( (string) $note, 0, 2000 ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	public function error( $client_id, $user_id, $method, $code, $duration_ms = 0, $note = '' ) {
		$this->log( 'error', $client_id, $user_id, $method, 'error', $code, $duration_ms, Url_Manager::client_ip(), $note );
	}

	public function info( $client_id, $user_id, $method, $result = 'ok', $duration_ms = 0 ) {
		$this->log( 'info', $client_id, $user_id, $method, $result, '', $duration_ms, Url_Manager::client_ip() );
	}

	public function fetch( $level = 'all', $limit = 100, $offset = 0 ) {
		global $wpdb;
		$table = Install::table( 'logs' );
		$sql   = "SELECT * FROM {$table}";
		if ( in_array( $level, array( 'error', 'info' ), true ) ) {
			$sql .= $wpdb->prepare( ' WHERE level = %s', $level );
		}
		$sql .= $wpdb->prepare( ' ORDER BY id DESC LIMIT %d OFFSET %d', (int) $limit, (int) $offset );
		return $wpdb->get_results( $sql );
	}

	public function count( $level = 'all' ) {
		global $wpdb;
		$table = Install::table( 'logs' );
		$sql   = "SELECT COUNT(*) FROM {$table}";
		if ( in_array( $level, array( 'error', 'info' ), true ) ) {
			$sql .= $wpdb->prepare( ' WHERE level = %s', $level );
		}
		return (int) $wpdb->get_var( $sql );
	}

	public function clear() {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Install::table( 'logs' ) );
	}

	public function prune( $days = 90 ) {
		global $wpdb;
		$date = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Install::table( 'logs' ) . ' WHERE ts < %s', $date ) );
	}
}
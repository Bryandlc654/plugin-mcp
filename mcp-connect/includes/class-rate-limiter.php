<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

	const WINDOW_SECONDS = 60;

	public static function allowed( $ip, $endpoint, $max ) {
		if ( ! Plugin::instance()->settings->get( 'rate_limit', true ) ) {
			return true;
		}
		if ( '' === $ip ) {
			return true;
		}

		global $wpdb;
		$table    = Install::table( 'rate' );
		$endpoint = substr( (string) $endpoint, 0, 64 );
		$now      = time();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE ip = %s AND endpoint = %s", $ip, $endpoint )
		);

		if ( ! $row ) {
			$wpdb->insert(
				$table,
				array(
					'ip'           => $ip,
					'endpoint'     => $endpoint,
					'hits'         => 1,
					'window_start' => gmdate( 'Y-m-d H:i:s', $now ),
				),
				array( '%s', '%s', '%d', '%s' )
			);
			return true;
		}

		$window_start = strtotime( $row->window_start . ' UTC' );
		if ( $now - $window_start >= self::WINDOW_SECONDS ) {
			$wpdb->update(
				$table,
				array( 'hits' => 1, 'window_start' => gmdate( 'Y-m-d H:i:s', $now ) ),
				array( 'id' => $row->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			return true;
		}

		if ( (int) $row->hits >= $max ) {
			return false;
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1 WHERE id = %d", $row->id ) );
		return true;
	}
}
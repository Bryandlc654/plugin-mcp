<?php
/**
 * MCP Connect unit test runner.
 * Usage: php tests/run.php
 */

error_reporting( E_ALL );
require __DIR__ . '/bootstrap.php';

list( $pass, $failures ) = mcp_connect_run_tests( __DIR__ . '/unit' );

echo "\n====================\n";
echo "PASS: $pass\n";
if ( $failures ) {
	foreach ( $failures as $f ) {
		echo "$f\n";
	}
	echo "FAILURES: " . count( $failures ) . "\n";
	exit( 1 );
}
echo "ALL TESTS PASSED\n";
exit( 0 );
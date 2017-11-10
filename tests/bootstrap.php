<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_tests_funcs = $_tests_dir . '/includes/functions.php';

if ( ! file_exists( $_tests_funcs ) ) {
	echo "Could not find {$_tests_funcs}, have you run bin/install-wp-tests.sh ?";
	exit( 1 );
}

require_once $_tests_funcs;

tests_add_filter( 'muplugins_loaded', function() {
	require_once dirname( __DIR__ ) . '/revisions-digest.php';
} );

require_once $_tests_dir . '/includes/bootstrap.php';
require_once dirname( __FILE__ ) . '/includes/testcase.php';

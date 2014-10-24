<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once( $_tests_dir . '/includes/functions.php' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

function _manually_load_plugin() {
	require( dirname( __FILE__ ) . '/../curator.php' );

	require_once( dirname( __FILE__ ) . '/includes/functions.php' );
}
//tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require( $_tests_dir . '/includes/bootstrap.php' );
require_once( dirname( __FILE__ ) . '/includes/class-cur-test-base.php' );
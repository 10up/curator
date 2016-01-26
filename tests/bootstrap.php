<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once( $_tests_dir . '/includes/functions.php' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

function _manually_load_plugin() {
	
	add_filter( 'cur_modules', '_enable_curator_modules' );
				
	require( dirname( __FILE__ ) . '/../curator.php' );

	require_once( dirname( __FILE__ ) . '/includes/functions.php' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
 
/**
 * Enable curation, featuring, and pinning of items in the curation plugin
 *
 * @param $modules
 */
function _enable_curator_modules ( $modules ) {
	$modules['curator']['enabled']           = true;
	$modules['featurer']['enabled']          = true;
	$modules['featurer']['sizes']['enabled'] = true;
	$modules['pinner']['enabled']            = true;
	
	return $modules;
}

require( $_tests_dir . '/includes/bootstrap.php' );
require_once( dirname( __FILE__ ) . '/includes/class-cur-term-factory.php' );
require_once( dirname( __FILE__ ) . '/includes/class-cur-test-base.php' );
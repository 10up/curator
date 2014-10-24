<?php

class CUR_Test_Base extends WP_UnitTestCase {

	/**
	 * Prevents weird MySQLi error.
	 *
	 * @since 0.2.1
	 */
	public function __construct() {
		self::$ignore_files = true;
	}

	/**
	 * Helps us keep track of actions that have fired
	 *
	 * @var array
	 * @since 0.2.1
	 */
	protected $fired_actions = array();

	/**
	 * Helps us keep track of applied filters
	 *
	 * @var array
	 * @since 0.2.1
	 */
	protected $applied_filters = array();

	/**
	 * Setup a post type for testing
	 *
	 * @since 0.2.1
	 */
	public function setup_test_post_type() {
		$args = array(
			'public' => true,
			'taxonomies' => array( 'post_tag', 'category' ),
		);

		register_post_type( 'cur_test', $args );
	}
}
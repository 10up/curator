<?php

class CURTestSingleSite extends CUR_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 0.2.1
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.2.1
	 */
	public function tearDown() {
		parent::tearDown();

		// @todo delete all posts, curated post, etc
		$this->fired_actions = array();
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.2.1
	 */
	public function testPostSync() {
		$this->assertTrue( true );
	}
}
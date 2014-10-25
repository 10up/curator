<?php

class CURTestSingleSite extends CUR_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 0.2.1
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$this->setup_test_post_type();
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
	 * Test a simple post curation
	 *
	 * @since 0.2.1
	 */
	public function testPostCuration() {
		$post_id = cur_create_post();

		$post = get_post( $post_id );

		// Ensure we're able to create a post
		$this->assertTrue( ! empty( $post_id ) );
		$this->assertTrue( null !== $post );

		// Curate the post
		$curated_post = cur_curate_post( $post_id, $post );

		// We should get an int value back of the curated post ID if we were successful
		$this->assertTrue( false !== $curated_post && is_int( $curated_post ));

		// Check to ensure that the posts meta is stored
		$this->assertEquals( $curated_post, get_post_meta( $post_id, '_curator_related_id', true ) );
		$this->assertEquals( $post_id, get_post_meta( $curated_post, '_curator_related_id', true ) );

		// Check that the posts know they're related to each other
		$this->assertEquals( $post_id, cur_get_related_id( $curated_post ) );
		$this->assertEquals( $curated_post, cur_get_related_id( $post_id ) );

		// Test term association
		$curate_term = cur_get_module_term( 'curator' );
		$associated_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );
		$this->assertTrue( in_array( $curate_term, $associated_terms ) );
	}
}
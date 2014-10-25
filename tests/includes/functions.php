<?php

// Add our post types to include in the curator plugin
add_filter( 'cur_set_post_types', 'cur_test_setup_post_types' );

function cur_test_setup_post_types( $post_types ) {
	$curator_post_types = array(
		'cur_test',
		'post',
	);

	return $curator_post_types;
}

/**
 * Create a WP post
 *
 * @param array $post_args
 * @param array $post_meta
 * @param int $site_id
 * @since 0.9
 * @return int|WP_Error
 */
function cur_create_post( $post_args = array(), $post_meta = array(), $site_id = null ) {
	if ( $site_id != null ) {
		switch_to_blog( $site_id );
	}

	$post_types = cur_get_post_types();
	$post_type_values = array_values( $post_types );

	$args = wp_parse_args( $post_args, array(
		'post_type' => $post_type_values[0],
		'post_status' => 'publish',
		'post_title' => 'Test Post ' . time(),
	) );

	$post_id = wp_insert_post( $args );

	// Quit if we have a WP_Error object
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	if ( ! empty( $post_meta ) ) {
		foreach ( $post_meta as $key => $value ) {
			// No need for sanitization here
			update_post_meta( $post_id, $key, $value );
		}
	}

	if ( $site_id != null ) {
		restore_current_blog();
	}

	return $post_id;
}
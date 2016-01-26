<?php

/**
 * Add our post types to include for testing
 *
 * @param $post_types
 *
 * @return array
 */
function cur_test_setup_post_types( $post_types ) {
	$curator_post_types = array(
		'cur_test',
		'post',
	);

	return $curator_post_types;
}
add_filter( 'cur_set_post_types', 'cur_test_setup_post_types' );

/**
 * Enable all modules for testing
 *
 * @param $modules
 *
 * @return mixed
 */
function cur_test_setup_modules( $modules ) {
	// Enable curation, featuring, and pinning of items in the curation plugin
	$modules['curator']['enabled'] = true;
	$modules['featurer']['enabled'] = true;
	$modules['pinner']['enabled'] = true;

	return $modules;
}
add_filter( 'cur_modules', 'cur_test_setup_modules' );

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

/**
 * Add Terms to a post object
 *
 * @param int    $post_id       The post Id to add the term to.
 * @param array  $args          Term arguments array.
 */
function cur_add_test_term( $post_id, $taxonomy, $args ) {
	
	$default_args = array(
	                'term_name' => 'Test term',
					'term_slug' => 'test_term',
					);
					
	$args = array_merge( $default_args, array_intersect_key( $args, $default_args ) );
	
	extract( $args, EXTR_OVERWRITE );
	
	$factory = new Cur_Term_Factory( $taxonomy );
	$term = get_term_by('slug', $term_slug, $taxonomy );
		
	if ( ! $term ) {
		$term_id = $factory->create( array( 'name' => $term_name, 'slug'=>$term_slug ) );
	}else {
		$term_id = $term->term_id;
	}
	

	$factory->add_post_terms( $post_id, array( $term_id ), $taxonomy ); 	
}
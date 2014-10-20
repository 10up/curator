<?php

class CUR_Curator extends CUR_Singleton {

	/**
	 * Slug of post meta to store ID of related post
	 *
	 * @var string
	 */
	private $curated_meta_slug = '_curator_related_id';

	/**
	 * Our default post status when we create a new curated item
	 *
	 * @var string
	 * @since 0.1.0
	 */
	private static $default_post_status = 'publish';

	/**
	 * Default enabled portions of the module
	 *
	 * @var array
	 */
	private static $modules = array();

	/**
	 * Which post types should the Curator be allowed to include?
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private static $post_types = array();

	/**
	 * Setup actions/filters
	 *
	 * @since 0.1.0
	 */
	public function setup() {

		self::$modules = array(
			'curator'  => array(
				'slug'    => 'cur-curated-item',
				'enabled' => true,
				'label'   => __( 'Curate Item', 'cur' ),
			),
			'featurer' => array(
				'slug'    => 'cur-featured-item',
				'enabled' => false,
				'label'   => __( 'Feature Item', 'cur' ),
			),
			'pinner'   => array(
				'slug'    => 'cur-pinned-item',
				'enabled' => false,
				'label'   => __( 'Pin Item', 'cur' ),
			),
		);

		// Set the filters to fire during `wp_loaded`
		add_action( 'wp_loaded', array( $this, 'filter_settings' ) );
	}

	/**
	 * Allow custom configuration using filters
	 *
	 * @uses cur_settings
	 * @uses cur_set_post_types
	 * @uses cur_set_create_post_status
	 */
	public function filter_settings() {

		// Allow modification of different modules. Curation, Featuring and Pinning of items
		self::$modules = apply_filters( 'cur_modules', self::$modules );

		// Which post types should we curate?
		self::$post_types = apply_filters( 'cur_set_post_types', self::$post_types );

		// Allow configuration of the default curator creation status of a post (Default is 'publish')
		self::$default_post_status = apply_filters( 'cur_set_create_post_status', self::$default_post_status );
	}

	/**
	 * Get the post types curator is enabled for
	 *
	 * @return array
	 * @since 0.1.0
	 */
	public function get_post_types() {
		return self::$post_types;
	}

	/**
	 * Getter for retrieving the settings
	 *
	 * @return array
	 */
	public function get_modules() {
		return self::$modules;
	}

	private function curate_post( $post_id, $post ) {

		// Create a post and add in as meta the original post's ID
		// @todo Get top ordered posts and place on top (via menu_order)
		$args = array(
			'post_type'      => cur_get_cpt_slug(),
			'posts_per_page' => 1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$top_curated_items = new WP_Query( $args );
		if ( $top_curated_items->have_posts() ) {
			$top_item_position = $top_curated_items->posts[0]->menu_order;
			if ( $top_item_position > 0 ) {
				$top_item_position -= 1;
			}
		} else {
			$top_item_position = 0;
		}

		$new_post_args = array(
			'post_title'     => $post->post_title,
			'post_type'      => cur_get_cpt_slug(),
			'post_status'    => self::$default_post_status,
			'menu_order'     => $top_item_position,
			'comment_status' => 'closed',
		);

		$inserted_cur_post = wp_insert_post( $new_post_args );

		if ( $inserted_cur_post && ! is_wp_error( $inserted_cur_post ) ) {

			// Add our related post ID to the curator post meta
			update_post_meta( $inserted_cur_post, $this->curated_meta_slug, $post_id );

			// Add our curator post id to the original post
			update_post_meta( $post_id, $this->curated_meta_slug, $inserted_cur_post );

			wp_add_object_terms( $post_id, cur_get_module_term( 'curator' ), cur_get_tax_slug() );

			return $inserted_cur_post;
		}

		return false;
	}

	public function set_item_modules( $post_id, $post, $modules, $set_modules ) {

		// Get a simple array of already associated terms in the format of: array( (int) $term_id => (string) $slug ) )
		$associated_terms = $prev_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );

		foreach ( $set_modules as $module => $action ) {
			$term = get_term_by( 'slug', cur_get_module_term( $module ), cur_get_tax_slug() );

			if ( false === $term || is_wp_error( $term ) ) {
				continue;
			}

			if ( 'add' === $action ) {
				$associated_terms[ $term->term_id ] = $term->slug;
			} else if ( 'remove' === $action ) {
				unset( $associated_terms[ $term->term_id ] );
			}
		}

		// If there's been a change, overwrite all old terms with our new list
		if ( $associated_terms !== $prev_terms ) {
			wp_set_object_terms( $curated_post, array_keys( $associated_terms ), cur_get_tax_slug() );
		}
	}

	/**
	 * Get the related post (works both ways, cur-curator to other post type or vice versa)
	 *
	 * @param $post_id
	 *
	 * @return mixed
	 */
	public function get_related_id( $post_id ) {
		return get_post_meta( $post_id, $this->curated_meta_slug, true );
	}

	/**
	 * Utility method to check if a module is enabled or not
	 *
	 * @param $module
	 *
	 * @return bool
	 */
	public function is_module_enabled( $module ) {
		$is_enabled = false;

		$modules = $this->get_modules();

		if ( ! empty( $modules[ $module ] ) && true === $modules[ $module ] ) {
			$is_enabled = true;
		}

		return $is_enabled;
	}

	public function get_module_term( $module ) {
		$modules = $this->get_modules();
		$term = false;

		if ( ! empty( $modules[ $module ]['slug'] ) && true === $modules[ $module ]['enabled'] ) {
			$term = $modules[ $module ]['slug'];
		}

		return $term;
	}

	/**
	 * Remove curation status from item
	 *
	 * @param $post_id
	 */
	public function uncurate_item( $post_id ) {

		// Remove item module
		$curate_term = get_term_by( 'slug', cur_get_module_term( 'curator' ), cur_get_tax_slug() );

		$curated_id = get_post_meta( $post_id, $this->curated_meta_slug, true );

		// Unset the curation term of the main post
		wp_remove_object_terms( $post_id, $curate_term->term_id, cur_get_tax_slug() );

		// Remove the associated meta of the curated post ID
		delete_post_meta( $post_id, $this->curated_meta_slug );

		// Finally, delete the curation post entirely
		wp_delete_post( $curated_id, true );
	}
}

CUR_Curator::factory()->setup();

/**
 * Accessor functions
 */

function cur_get_post_types() {
	return CUR_Curator::factory()->get_post_types();
}

function cur_get_related_id( $post_id ) {
	return CUR_Curator::factory()->get_related_id( $post_id );
}

function cur_get_modules() {
	return CUR_Curator::factory()->get_modules();
}

function cur_is_module_enabled( $module ) {
	return CUR_Curator::factory()->is_module_enabled( $module );
}

function cur_get_module_term( $module ) {
	return CUR_Curator::factory()->get_module_term( $module );
}

function cur_set_item_modules( $post_id, $post, $modules, $set_modules ) {
	return CUR_Curator::factory()->set_item_modules( $post_id, $post, $modules, $set_modules );
}

function cur_uncurate_item( $post_id ) {
	return CUR_Curator::factory()->uncurate_item( $post_id );
}
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
				'option'  => 'curator-pinned-items',
				'enabled' => false,
				'label'   => __( 'Pin Item', 'cur' ),
			),
		);

		// Set the filters to fire during `wp_loaded`
		add_action( 'wp_loaded', array( $this, 'filter_settings' ) );

		// Replace the Curator query items with their original items
		add_filter( 'the_posts', array( $this, 'filter_the_posts' ), 10, 2 );
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

	/**
	 * Getter for retrieving the option name for the pinner
	 *
	 * @return mixed
	 */
	public function get_pinner_option_slug() {
		return self::$modules['pinner']['option'];
	}

	/**
	 * Curates a post
	 *
	 * @param $post_id
	 * @param $post
	 *
	 * @return bool|int|WP_Error
	 */
	public function curate_post( $post_id, $post ) {

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

		$curated_post = wp_insert_post( $new_post_args );

		if ( $curated_post && ! is_wp_error( $curated_post ) ) {

			// Add our related post ID to the curator post meta
			update_post_meta( $curated_post, $this->curated_meta_slug, $post_id );

			// Add our curator post id to the original post
			update_post_meta( $post_id, $this->curated_meta_slug, $curated_post );

			wp_add_object_terms( $curated_post, cur_get_module_term( 'curator' ), cur_get_tax_slug() );

			return $curated_post;
		}

		return false;
	}

	/**
	 * Sets the modules for each item
	 *
	 * @param $post_id
	 * @param $post
	 * @param $modules
	 * @param $set_modules
	 * @param $curated_post
	 */
	public function set_item_modules( $post_id, $post, $modules, $set_modules, $curated_post ) {

		// Get a simple array of already associated terms in the format of: array( (int) $term_id => (string) $slug ) )
		$associated_terms = $prev_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );

		foreach ( $set_modules as $module => $action ) {
			$term = get_term_by( 'slug', cur_get_module_term( $module ), cur_get_tax_slug() );

			if ( false === $term || is_wp_error( $term ) ) {
				continue;
			}

			if ( 'add' === $action ) {
				$associated_terms[ $term->term_id ] = $term->slug;

				// If pinner module, add to pinner array
				if ( 'pinner' === $module && cur_is_module_enabled( 'pinner' ) ) {
					$pinned_items = get_option( cur_get_pinner_option_slug() );
					if ( empty( $pinned_items ) ) {
						$pinned_items = array();
					}

					array_unshift( $pinned_items, $curated_post );

					// Update the pinned items with our new item in front
					update_option( cur_get_pinner_option_slug(), $pinned_items );
				}
			} else if ( 'remove' === $action ) {
				unset( $associated_terms[ $term->term_id ] );

				// If pinner module, remove from pinned items array
				if ( 'pinner' === $module && cur_is_module_enabled( 'pinner' ) ) {
					$pinned_items = get_option( cur_get_pinner_option_slug() );
					if ( empty( $pinned_items ) ) {
						continue;
					}

					// Find our item's current position
					$position = array_search( (int) $curated_post, $pinned_items );

					// Remove this item from the pinned items array
					unset( $pinned_items[ $position ] );

					// Update the pinned items array
					update_option( cur_get_pinner_option_slug(), $pinned_items );
				}
			}
		}

		// If there's been a change, overwrite all old terms with our new list
		if ( $associated_terms !== $prev_terms ) {

			// Set terms to curated post object
			wp_set_object_terms( $curated_post, array_keys( $associated_terms ), cur_get_tax_slug() );

			// Pinner module requires us to do something special. We're going to store the pinned items into the options table
			// As a key->value of curatedCPT->originCPT ids.

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
		$id = intval( get_post_meta( $post_id, $this->curated_meta_slug, true ) );

		if ( is_int( $id ) && 0 !== $id ) {
			return $id;
		} else {
			return false;
		}
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

		if ( ! empty( $modules[ $module ] ) && true === $modules[ $module ]['enabled'] ) {
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

		$curated_id = cur_get_related_id( $post_id );

		// Unset the curation term of the main post
		wp_remove_object_terms( $post_id, $curate_term->term_id, cur_get_tax_slug() );

		// Remove the associated meta of the curated post ID
		delete_post_meta( $post_id, $this->curated_meta_slug );

		// Finally, delete the curation post entirely
		wp_delete_post( $curated_id, true );
	}

	/**
	 * Makes the loop experience seamless by replacing all curator items with their original posts.
	 *
	 * Tada!
	 *
	 * @param $posts
	 * @param $query
	 *
	 * @return mixed
	 * @since 0.1.0
	 */
	public function filter_the_posts( $posts, $query ) {

		// Ensure that we are only filtering for curator queries
		if ( ! empty( $query->query['post_type'] )
		     && ! is_array( $query->query['post_type'] )
		     && cur_get_cpt_slug() === $query->query['post_type']
		) {

			// Replace the posts we found with their origins
			if ( ! empty( $posts ) ) {

				// Do for each post that was found
				foreach ( $posts as $key => $post ) {
					$posts[ $key ] = get_post( cur_get_related_id( $post->ID ) );
				}
			}
		}

		return $posts;
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

function cur_set_item_modules( $post_id, $post, $modules, $set_modules, $curated_post ) {
	return CUR_Curator::factory()->set_item_modules( $post_id, $post, $modules, $set_modules, $curated_post );
}

function cur_uncurate_item( $post_id ) {
	return CUR_Curator::factory()->uncurate_item( $post_id );
}

function cur_curate_post( $post_id, $post ) {
	return CUR_Curator::factory()->curate_post( $post_id, $post );
}

function cur_get_pinner_option_slug() {
	return CUR_Curator::factory()->get_pinner_option_slug();
}
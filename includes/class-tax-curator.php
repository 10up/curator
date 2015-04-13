<?php

/**
 * Class CUR_Tax_Curator
 *
 * All taxonomies must be registered somewhere.
 * Might as well be here
 */
class CUR_Tax_Curator extends CUR_Singleton {

	/**
	 * Slug of taxonomy
	 *
	 * @var string
	 * @since 0.1.0
	 */
	public $tax_slug = 'cur-tax-curator';

	/**
	 * Build it
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_taxonomies' ) );
	}

	/**
	 * Registers taxonomies for "cur" post_type
	 * @since 0.1.0
	 */
	public function register_post_taxonomies() {
		$labels = array(
			'name'                       => __( 'Curator Tax', 'cur' ),
			'singular_name'              => __( 'Curator Tax', 'cur' ),
			'search_items'               => __( 'Search Curator Tax', 'cur' ),
			'popular_items'              => __( 'Popular Curator Tax', 'cur' ),
			'all_items'                  => __( 'All Curator Tax', 'cur' ),
			'parent_item'                => __( 'Parent Curator Tax', 'cur' ),
			'parent_item_colon'          => __( 'Parent Curator Tax:', 'cur' ),
			'edit_item'                  => __( 'Edit Curator Tax', 'cur' ),
			'update_item'                => __( 'Update Curator Tax', 'cur' ),
			'add_new_item'               => __( 'Add New Curator Tax', 'cur' ),
			'new_item_name'              => __( 'New Curator Tax', 'cur' ),
			'separate_items_with_commas' => __( 'Separate Curator Tax with commas', 'cur' ),
			'add_or_remove_items'        => __( 'Add or remove Curator Tax', 'cur' ),
			'choose_from_most_used'      => __( 'Choose from the most used Curator Tax', 'cur' ),
			'menu_name'                  => __( 'Curator Tax', 'cur' ),
		);

		$args = array(
			'labels'            => $labels,
			'public'            => false,
			'show_in_nav_menus' => false,
			'show_ui'           => false,
			'show_tagcloud'     => false,
			'show_admin_column' => false,
			'hierarchical'      => false,
			'rewrite'           => false,
			'query_var'         => false,
		);

		register_taxonomy( $this->tax_slug, cur_get_cpt_slug(), $args );
	}

	/**
	 * Sets the default terms for us to use
	 * Fires on plugin activation
	 * @since 0.1.0
	 */
	public function setup_default_terms() {

		$modules = cur_get_modules();

		// Check for all enabled modules, add or delete terms as necessary
		$terms = get_terms( $this->tax_slug, array( 'hide_empty' => false ) );

		foreach ( $modules as $module => $module_info ) {

			// For enabled modules check to see if the term exists, if it doesn't add it
			if ( ! empty( $module_info['enabled'] ) && true === $module_info['enabled'] ) {

				// Check to see if we have this term already
				$module_term = get_term_by( 'slug', $module_info['slug'], $this->tax_slug );

				// If we don't have it then let's add it
				if ( false === $module_term && ! is_wp_error( $module_term ) ) {
					wp_insert_term( $module_info['slug'], $this->tax_slug );
				}
			}
		}
	}
}

CUR_Tax_Curator::factory();

/**
 * Accessor Functions
 */

function cur_get_tax_slug() {
	return CUR_Tax_Curator::factory()->tax_slug;
}

function cur_setup_default_terms() {
	return CUR_Tax_Curator::factory()->setup_default_terms();
}
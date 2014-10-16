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
	 */
	public $tax_slug = 'cur-tax-curator';

	/**
	 * Curate term slug
	 *
	 * @var string
	 */
	public $term_curate = 'cur-curate-item';

	/**
	 * Feature term slug
	 *
	 * @var string
	 */
	public $term_feature = 'cur-feature-item';

	/**
	 * Pin term slug
	 *
	 * @var string
	 */
	public $term_pin = 'cur-pin-item';

	/**
	 * Build it
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_taxonomies' ) );
		add_action( 'init', array( $this, 'set_default_term' ), 900 );
	}

	/**
	 * Registers taxonomies for "cur" post_type
	 */
	public function register_post_taxonomies() {
		$labels = array(
			'name'                       => __( 'Curator Tax', 'fpb' ),
			'singular_name'              => __( 'Curator Tax', 'fpb' ),
			'search_items'               => __( 'Search Curator Tax', 'fpb' ),
			'popular_items'              => __( 'Popular Curator Tax', 'fpb' ),
			'all_items'                  => __( 'All Curator Tax', 'fpb' ),
			'parent_item'                => __( 'Parent Curator Tax', 'fpb' ),
			'parent_item_colon'          => __( 'Parent Curator Tax:', 'fpb' ),
			'edit_item'                  => __( 'Edit Curator Tax', 'fpb' ),
			'update_item'                => __( 'Update Curator Tax', 'fpb' ),
			'add_new_item'               => __( 'Add New Curator Tax', 'fpb' ),
			'new_item_name'              => __( 'New Curator Tax', 'fpb' ),
			'separate_items_with_commas' => __( 'Separate Curator Tax with commas', 'fpb' ),
			'add_or_remove_items'        => __( 'Add or remove Curator Tax', 'fpb' ),
			'choose_from_most_used'      => __( 'Choose from the most used Curator Tax', 'fpb' ),
			'menu_name'                  => __( 'Curator Tax', 'fpb' ),
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
	 *
	 * @todo move this to plugin activate
	 */
	public function set_default_term() {
		$terms = get_terms( $this->tax_slug, array( 'hide_empty' => false ) );

		if ( empty( $terms ) ) {
			// $terms is empty, let's add our default term
			wp_insert_term( 'curate-item', $this->tax_slug );
		}
	}
}

CUR_Tax_Curator::factory();

function cur_get_tax_slug() {
	return CUR_Tax_Curator::factory()->tax_slug;
}
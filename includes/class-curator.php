<?php

class CUR_Curator extends CUR_Singleton {

	/**
	 * Our default post status when we create a new curated item
	 *
	 * @var string
	 * @since 0.1.0
	 */
	public $default_post_status = 'publish';

	/**
	 * Which post types should the Curator be allowed to include?
	 *
	 * @var array
	 * @since 0.1.0
	 */
	public static $post_types = array();

	/**
	 * Setup actions/filters
	 *
	 * @since 0.1.0
	 */
	public function setup() {

		// Allow configuration of the default curator creation status of a post (Default is 'publish')
		$this->default_post_status = apply_filters( 'cur_set_create_post_status', $this->default_post_status );

		add_action( 'wp_loaded', array( $this, 'set_post_types' ) );
	}

	/**
	 * Here's where we allow custom configuration of the post types to allow curation for
	 *
	 * @since 0.1.0
	 * @uses cur_set_post_types
	 */
	public function set_post_types() {
		self::$post_types = apply_filters( 'cur_set_post_types', array() );
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
	 * Our curate term slug
	 *
	 * @return mixed
	 * @since 0.1.0
	 */
	public function get_curate_term() {
		return get_term_by( 'slug', 'curate-item', cur_get_tax_slug() );
	}

	/**
	 * This item should be curated
	 *
	 * @param $post_id
	 * @param $post
	 * @return bool
	 * @since 0.1.0
	 */
	public function create_curated_item( $post_id, $post ) {
		$curate_term = cur_get_curate_term();

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
			'post_title' => $post->post_title,
			'post_type' => cur_get_cpt_slug(),
			'post_status' => $this->default_post_status,
			'menu_order' => $top_item_position,
			'comment_status' => 'closed',
		);

		$inserted_cur_post = wp_insert_post( $new_post_args );

		if ( $inserted_cur_post && ! is_wp_error( $inserted_cur_post ) ) {

			// Set the term in the original post item
			wp_set_object_terms( $post_id, $curate_term->term_id, cur_get_tax_slug() );

			// Add our related post ID to the curator post meta
			update_post_meta( $inserted_cur_post, '_curator_related_id', $post_id );

			// Add our curator post id to the original post
			update_post_meta( $post_id, '_curator_related_id', $inserted_cur_post );

			return true;
		}

		return false;
	}

	/**
	 * Item is no longer curated, remove it
	 *
	 * @param $post_id
	 * @param $post
	 * @since 0.1.0
	 */
	public function remove_curated_item( $post_id ) {
		$curate_term = cur_get_curate_term();

		$curated_id = get_post_meta( $post_id, '_curator_related_id', true );

		// Unset the curation term of the main post
		wp_remove_object_terms( $post_id, $curate_term->term_id, cur_get_tax_slug() );

		// Remove the associated meta of the curated post ID
		delete_post_meta( $post_id, '_curator_related_id' );

		// Finally, delete the curation post entirely
		wp_delete_post( $curated_id, true );
	}

	public function get_related_id( $post_id ) {
		return get_post_meta( $post_id, '_curator_related_id', true );
	}
}

CUR_Curator::factory()->setup();

/**
 * Accessor functions
 */

function cur_get_post_types() {
	return CUR_Curator::factory()->get_post_types();
}

function cur_get_curate_term() {
	return CUR_Curator::factory()->get_curate_term();
}

function cur_remove_curated_item( $post_id ) {
	return CUR_Curator::factory()->remove_curated_item( $post_id );
}

function cur_create_curated_item( $post_id, $post ) {
	return CUR_Curator::factory()->create_curated_item( $post_id, $post );
}

function cur_get_related_id( $post_id )  {
	return CUR_Curator::factory()->get_related_id( $post_id );
}
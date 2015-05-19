<?php

class CUR_Curator extends CUR_Singleton {

	/**
	 * Slug of post meta to store ID of related post
	 *
	 * @var string
	 * @since 0.1.0
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
	 * @since 0.1.0
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
				'sizes'   => array(
					'enabled' => false,
					'sizes'   => array(
						'2x2' => true,
						'2x1' => true,
						'1x2' => false,
					),
				),
			),
			'pinner'   => array(
				'slug'      => 'cur-pinned-item',
				'option'    => 'curator-pinned-items',
				'max_items' => 3,
				'enabled'   => false,
				'label'     => __( 'Pin Item', 'cur' ),
			),
		);

		// Set the filters to fire during `wp_loaded`
		add_action( 'wp_loaded', array( $this, 'filter_settings' ) );

		// Inject sticky posts if pinner is enabled
		add_filter( 'the_posts', array( $this, 'filter_sticky_posts' ), 20, 2 );

		// Replace the Curator query items with their original items
		add_filter( 'the_posts', array( $this, 'filter_the_posts' ), 900, 2 );

		// Display featurer sizes
		add_action( 'cur_module_featurer_control', array( $this, 'featurer_size_display' ) );
	}

	/**
	 * Allow custom configuration using filters
	 *
	 * @uses cur_settings
	 * @uses cur_set_post_types
	 * @uses cur_set_create_post_status
	 * @since 0.1.0
	 */
	public function filter_settings() {

		// Allow modification of different modules. Curation, Featuring and Pinning of items
		self::$modules = apply_filters( 'cur_modules', self::$modules );

		// Which post types should we curate?
		self::$post_types = apply_filters( 'cur_set_post_types', self::$post_types );

		// Allow configuration of the default curator creation status of a post (Default is 'publish')
		self::$default_post_status = apply_filters( 'cur_set_create_post_status', self::$default_post_status );

		// Allow manual override on limit of pinned items. Default is 3
		self::$modules['pinner']['max_items'] = apply_filters( 'cur_pinned_items', self::$modules['pinner']['max_items'] );

		// Featurer sizes are disabled by default
		self::$modules['featurer']['sizes']['enabled'] = apply_filters( 'cur_featurer_size_status', self::$modules['featurer']['sizes']['enabled'] );

		// Featurer size controls
		self::$modules['featurer']['sizes']['sizes'] = apply_filters( 'cur_featurer_sizes', self::$modules['featurer']['sizes']['sizes'] );
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
	 * @since 0.1.0
	 */
	public function get_modules() {
		return self::$modules;
	}

	/**
	 * Getter for retrieving the option name for the pinner
	 *
	 * @return mixed
	 * @since 0.1.0
	 */
	public function get_pinner_option_slug() {
		return self::$modules['pinner']['option'];
	}

	/**
	 * Getter for retrieving max pinnable items
	 *
	 * @return mixed
	 * @since 0.1.0
	 */
	public function get_pinner_max_items() {
		return self::$modules['pinner']['max_items'];
	}

	/**
	 * Getter for retrieving the featurer sizes
	 *
	 * @return mixed
	 */
	public function get_featurer_sizes() {
		$sizes = false;

		if ( cur_is_module_enabled('featurer') ) {
			if ( true === self::$modules['featurer']['sizes']['enabled'] ) {
				$sizes = self::$modules['featurer']['sizes']['sizes'];
			}
		}

		return $sizes;
	}

	/**
	 * Curates a post
	 *
	 * @param $post_id
	 * @param $post
	 *
	 * @return bool|int|WP_Error
	 * @since 0.1.0
	 */
	public function curate_post( $post_id, $post ) {

		// Create a post and add in as meta the original post's ID
		// @todo Get top ordered posts and place on top (via menu_order)
		$args = array(
			'post_type'      => cur_get_cpt_slug(),
			'posts_per_page' => 1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'no_found_rows'  => true,
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

			do_action( 'cur_curate_item', $post_id );

			return $curated_post;
		}

		return false;
	}

	/**
	 * Sets the modules for each item
	 *
	 * @param $set_modules
	 * @param $curated_post
	 * @since 0.2.0
	 */
	public function set_item_modules( $set_modules, $curated_post ) {

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

			// Set terms to curated post object
			wp_set_object_terms( $curated_post, array_keys( $associated_terms ), cur_get_tax_slug() );
		}
	}

	/**
	 * Pin item
	 * Add curated post id to pinned item array in options table
	 * If array gets larger than max # of items allowable, unpin oldest items
	 *
	 * @param $curated_id
	 * @since 0.2.0
	 */
	public function pin_item( $curated_id ) {

		// Ensure our pinner module is enabled
		if ( true !== cur_is_module_enabled( 'pinner' ) ) {
			return;
		}

		$max_items = cur_get_pinner_max_items();
		$option_slug = cur_get_pinner_option_slug();

		$pinned_items = get_option( $option_slug );
		if ( empty( $pinned_items ) ) {
			$pinned_items = array();
		}

		// Add our new item onto the top of the pinned stack
		array_unshift( $pinned_items, $curated_id );

		// If we're over the max items allotted, unpin the overage items
		if ( count( $pinned_items ) > $max_items ) {
			$unpin_items = array_splice( $pinned_items, $max_items, 1 );
			foreach ( (array) $unpin_items as $unpin_item ) {
				$this->unpin_item( $unpin_item, $pinned_items );
			}
		}

		// Update the pinned items with our new item in front
		update_option( $option_slug, $pinned_items );
	}

	/**
	 * Unpins item
	 * Removes curated post ID from option array
	 * Unassociates the pinner term from the curated post
	 *
	 * @param $curated_id
	 * @since 0.2.0
	 */
	public function unpin_item( $curated_id, $pinned_items = null ) {
		// Ensure our pinner module is enabled
		if ( true !== cur_is_module_enabled( 'pinner' ) ) {
			return;
		}

		if ( null === $pinned_items ) {
			$pinned_items = get_option( cur_get_pinner_option_slug() );
		}

		if ( empty( $pinned_items ) ) {
			$pinned_items = array();
		}

		// Find our item's current position
		$position = array_search( (int) $curated_id, $pinned_items );

		// Remove this item from the pinned items array
		if ( false !== $position ) {
			unset( $pinned_items[ $position ] );

			// Update the pinned items array
			update_option( cur_get_pinner_option_slug(), $pinned_items );
		}

		// Unassociate pinner term from curate post
		wp_remove_object_terms( $curated_id, cur_get_module_term( 'pinner' ), cur_get_tax_slug() );
	}

	/**
	 * Get the related post (works both ways, cur-curator to other post type or vice versa)
	 *
	 * @param $post_id
	 *
	 * @return mixed
	 * @since 0.2.0
	 */
	public function get_related_id( $post_id ) {
		$post_id = intval( $post_id );
		$related_id = intval( get_post_meta( $post_id, $this->curated_meta_slug, true ) );

		if ( is_int( $related_id ) && 0 !== $related_id ) {
			$self_id = intval( get_post_meta( $related_id, $this->curated_meta_slug, true ) );

			// Something got unsynced, there's no related post that links back to this. Cleaning up
			if ( "" === $self_id ) {
				delete_post_meta( $post_id, $this->curated_meta_slug, true );
			} else if ( $self_id === $post_id ) {
				return $related_id;
			}
		}

		return false;
	}

	/**
	 * Check if a post is curated
	 *
	 * @param int $post
	 *
	 * @return bool
	 */
	public function is_curated( $post = 0 ) {
		$post = get_post( $post );

		$curated = false;

		// Ensure that we are able to properly get the related ID, if we are then it's curated
		$curated_post = cur_get_related_id( $post->ID );
		if ( is_int( $curated_post ) ) {
			$curated = true;
		}

		return $curated;
	}

	/**
	 * Returns only the curator post type ID. Will determine what post type you're passing.
	 *
	 * @param $post_id
	 *
	 * @return mixed
	 * @since 0.2.0
	 */
	public function get_curated_post( $post_id = 0 ) {
		$post = get_post( $post_id );
		$post_id = $post->ID;

		// Ensure we have a legitimate post
		if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
			return;
		}

		// check to see if curator post type, if not then get the curator post type
		if ( cur_get_cpt_slug() === get_post_type( $post_id ) ) {
			return $post_id;
		} else {
			return $this->get_related_id( $post_id );
		}
	}

	/**
	 * Return the original post's ID, regardless of what post_id is passed
	 *
	 * @param $post_id
	 *
	 * @return mixed
	 */
	public function get_original_post( $post_id ) {
		$post = get_post( $post_id );
		$post_id = $post->ID;

		// Ensure we have a legitimate post
		if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
			return;
		}

		// See what post we're dealing with, get the original
		if ( cur_get_cpt_slug() === get_post_type( $post_id ) ) {
			return $this->get_related_id( $post_id );
		} else {
			return $post_id;
		}
	}

	/**
	 * Utility method to check if a module is enabled or not
	 *
	 * @param $module
	 *
	 * @return bool
	 * @since 0.2.0
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
		$term    = false;

		if ( ! empty( $modules[ $module ]['slug'] ) && true === $modules[ $module ]['enabled'] ) {
			$term = $modules[ $module ]['slug'];
		}

		return $term;
	}

	/**
	 * Remove curation status from item
	 *
	 * @param $post_id
	 * @return WP_Post | array | bool
	 * @since 0.2.0
	 */
	public function uncurate_item( $post_id ) {

		// Get original item
		$original_id = cur_get_original_post( $post_id );

		// Get curated item
		$curated_id = cur_get_curated_post( $post_id );

		// Unset all the things from the original post
		if ( ! empty( $original_id ) && is_int( $original_id ) ) {

			// Remove item module
			$curate_term = get_term_by( 'slug', cur_get_module_term( 'curator' ), cur_get_tax_slug() );

			// Unset the curation term of the main post
			wp_remove_object_terms( $original_id, $curate_term->term_id, cur_get_tax_slug() );

			// Remove the associated meta of the curated post ID
			delete_post_meta( $original_id, $this->curated_meta_slug );
		}

		do_action( 'cur_uncurate_item', $post_id );

		// Delete the curation post entirely
		return wp_delete_post( $curated_id, true );
	}

	/**
	 * Using WP_Query sticky post logic here for our custom post type.
	 * Takes sticky posts and moves them to the top of the query
	 *
	 * @param $posts
	 * @param $query
	 *
	 * @return mixed
	 * @since 0.2.0
	 */
	public function filter_sticky_posts( $posts, $query ) {

		// Ensure that we are only filtering for curator queries
		if ( ! empty( $query->query['post_type'] )
		     && ! is_array( $query->query['post_type'] )
		     && cur_get_cpt_slug() === $query->query['post_type']
		) {
			$q    = $query->query_vars;
			$page = 1;

			// Paging
			if ( empty( $q['nopaging'] ) && ! $query->is_singular ) {
				$page = absint( $q['paged'] );
				if ( ! $page ) {
					$page = 1;
				}
			}

			// Put sticky posts at the top of the posts array
			$sticky_posts = get_option( cur_get_pinner_option_slug() );
			if ( $page <= 1 && is_array( $sticky_posts ) && ! empty( $sticky_posts ) && ! $q['ignore_sticky_posts'] ) {
				$num_posts     = count( $posts );
				$sticky_offset = 0;
				// Loop over posts and relocate stickies to the front.
				for ( $i = 0; $i < $num_posts; $i ++ ) {
					if ( in_array( $posts[ $i ]->ID, $sticky_posts ) ) {
						$sticky_post = $posts[ $i ];
						// Remove sticky from current position
						array_splice( $posts, $i, 1 );
						// Move to front, after other stickies
						array_splice( $posts, $sticky_offset, 0, array( $sticky_post ) );
						// Increment the sticky offset. The next sticky will be placed at this offset.
						$sticky_offset ++;
						// Remove post from sticky posts array
						$offset = array_search( $sticky_post->ID, $sticky_posts );
						unset( $sticky_posts[ $offset ] );
					}
				}

				// If any posts have been excluded specifically, Ignore those that are sticky.
				if ( ! empty( $sticky_posts ) && ! empty( $q['post__not_in'] ) ) {
					$sticky_posts = array_diff( $sticky_posts, $q['post__not_in'] );
				}

				// Fetch sticky posts that weren't in the query results
				if ( ! empty( $sticky_posts ) ) {
					$stickies = get_posts( array(
						'post__in'    => $sticky_posts,
						'post_type'   => cur_get_cpt_slug(),
						'post_status' => 'publish',
						'nopaging'    => true
					) );

					foreach ( $stickies as $sticky_post ) {
						array_splice( $posts, $sticky_offset, 0, array( $sticky_post ) );
						$sticky_offset ++;
					}
				}
			}
		}

		return $posts;
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
		if ( ! is_admin()
		     && ! empty( $query->query['post_type'] )
		     && ! is_array( $query->query['post_type'] )
		     && cur_get_cpt_slug() === $query->query['post_type']
		) {

			// Replace the posts we found with their origins
			if ( ! empty( $posts ) ) {

				// Do for each post that was found
				foreach ( $posts as $key => $post ) {
					$posts[ $key ] = get_post( apply_filters( 'cur_curated_post_id', cur_get_related_id( $post->ID ) ) );
				}
			}
		}

		return $posts;
	}

	/**
	 * Determine if a post is featured. Will get the current post in the loop unless a
	 * specific post ID is passed.
	 *
	 * @param int $post
	 * @return bool
	 */
	public function is_featured( $post = 0 ) {
		$post = get_post( $post );

		// get the curated post if we don't already have it
		$curated_post = cur_get_curated_post( $post->ID );

		// If we can't find a curated post or if it's not an int returned, abort
		if ( false === $curated_post || ! is_int( $curated_post ) ) {
			return false;
		}

		// Get our module information
		$modules = cur_get_modules();

		// Ensure that our featurer module is enabled
		if ( empty( $modules['featurer'] ) ) {
			return false;
		}

		// Writing out $modules['featurer'] gets a bit long, let's tidy it up
		$featurer = $modules['featurer'];

		// Ensure that our module information is correct and contains 'enabled' and 'slug' items
		if ( empty( $featurer['enabled'] ) || true !== $featurer['enabled'] || empty( $featurer['slug'] ) ) {
			return false;
		}

		// Get our feature term information
		$featurer_term = get_term_by( 'slug', $featurer['slug'], cur_get_tax_slug() );

		// Get terms associated with curated post
		$associated_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );

		// Check to see if the curated post has the feature term associated with it - if so, then this is featured!
		if ( ! empty( $associated_terms[ $featurer_term->term_id ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Display the featurer radio buttons to choose how prominent an item should be
	 *
	 * @return void
	 */
	public function featurer_size_display() {

		// If featurer isn't enabled, abort
		if ( true !== cur_is_module_enabled( 'featurer' ) ) {
			return;
		}

		// If post isn't featured then we shouldn't show this yet
		if ( ! cur_is_featured() ) {
			return;
		}

		// If our size submodule isn't enabled, abort
		if ( true !== self::$modules['featurer']['sizes']['enabled'] ) {
			return;
		}

		// Get our sizes
		$sizes = $this->get_featurer_sizes();

		// Ensure our sizes array matches expectations (not empty && an array)
		if ( empty( $sizes ) || ! is_array( $sizes ) ) {
			return;
		}

		// Do we already have a selected size?
		$curated_post = cur_get_curated_post();
		$current_size = get_post_meta( $curated_post, 'cur_featured_size', true );

		// Set default size
		if ( empty( $current_size ) ) {
			$current_size = '2x2';
		}
		?>
		<div class="featurer-sizes">
			<?php
			foreach ( (array) $sizes as $size_key => $size_enabled ) :
				if ( true === $size_enabled ) : ?>
					<label>
						<?php
						printf( '<input type="radio" name="cur-featurer-size" value="%s" %s>',
							esc_attr( $size_key ),
							checked( $size_key, $current_size, false ) );
						?>
						<img src="<?php esc_attr_e( CUR_URL . 'images/featurer-grid-' . $size_key . '.png' ); ?>">
					</label>
				<?php
				endif;
			endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get the featured size of the curated item
	 *
	 * @param int $post_id
	 *
	 * @return mixed
	 */
	public function get_featured_size( $post_id = 0 ) {
		$post = get_post( $post_id );

		// get the curated post if we don't already have it
		$curated_post = cur_get_curated_post( $post->ID );

		if ( true !== cur_is_featured( $curated_post ) ) {
			return;
		}

		return get_post_meta( $curated_post, 'cur_featured_size', true );
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

function cur_is_curated( $post = 0 ) {
	return CUR_Curator::factory()->is_curated( $post );
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

function cur_set_item_modules( $set_modules, $curated_post ) {
	return CUR_Curator::factory()->set_item_modules( $set_modules, $curated_post );
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

function cur_pin_item( $curated_id ) {
	return CUR_Curator::factory()->pin_item( $curated_id );
}

function cur_unpin_item( $curated_id ) {
	return CUR_Curator::factory()->unpin_item( $curated_id );
}

function cur_get_pinner_max_items() {
	return CUR_Curator::factory()->get_pinner_max_items();
}

function cur_get_curated_post( $post_id = 0 ) {
	return CUR_Curator::factory()->get_curated_post( $post_id );
}

function cur_get_original_post( $post_id ) {
	return CUR_Curator::factory()->get_original_post( $post_id );
}

function cur_is_featured( $post_id = 0 ) {
	return CUR_Curator::factory()->is_featured( $post_id );
}

function cur_get_featurer_sizes() {
	return CUR_Curator::factory()->get_featurer_sizes();
}

function cur_get_featured_size( $post_id = 0 ) {
	return CUR_Curator::factory()->get_featured_size( $post_id );
}

<?php

/**
 * Post type for Curator
 *
 * Class CUR_CPT_Curator
 */
class CUR_CPT_Curator extends CUR_Singleton {

	/**
	 * Internal use slug of post type
	 *
	 * @var string
	 */
	public $cpt_slug = 'cur-curator';

	/**
	 * External URL facing slug for rewrites
	 *
	 * @var string
	 */
	public $cpt_url_slug = 'curator';

	/**
	 * Build it
	 *
	 * @uses add_action()
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Save terms/meta on save_post
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		// Uncurate items on unpublish
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );

		add_action( 'admin_menu', array( $this, 'remove_add_new_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );

		add_action( 'trashed_post', array( $this, 'trashed_post' ), 200 );

		// Modify the edit post link to go directly to the original item
		add_filter( 'get_edit_post_link', array( $this, 'filter_edit_post_link' ), 10, 3 );

		add_action( 'admin_head', array( $this, 'admin_head' ) );
	}

	/**
	 * Add custom columns to the edit screen
	 *
	 * @since 0.2.0
	 */
	public function admin_head() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( 'edit-cur-curator' !== $screen->id ) {
			return;
		}

		// Add custom columns to show the origin post type and featured status
		add_filter( 'manage_' . $this->cpt_slug . '_posts_columns', array( $this, 'manage_columns' ) );

		// Display our custom columns (need to add for each post type, as these posts are actually the original post
		add_action( 'manage_pages_custom_column', array( $this, 'display_custom_columns' ), 10, 2 );
		add_action( 'manage_posts_custom_column', array( $this, 'display_custom_columns' ), 10, 2 );

		$post_types = cur_get_post_types();
		if ( ! empty( $post_types ) ) {
			foreach ( (array) $post_types as $type ) {
				if ( 'post' !== $type || 'page' !== $type ) {
					add_action( 'manage_' . $type . '_posts_columns', array( $this, 'display_custom_columns' ), 10, 2 );
				}
			}
		}
	}

	/**
	 * Disable the trash for this post type, as it just confuses things
	 *
	 * @param $post_id
	 */
	public function trashed_post( $post_id ) {
		if ( cur_get_cpt_slug() === get_post_type( $post_id ) || in_array( get_post_type( $post_id ), cur_get_post_types() ) ) {

			// Get the ID of the main item, and then use the same removal/disconnect method
			cur_uncurate_item( $post_id );
		}
	}

	/**
	 * Displays the checkbox for the post translated meta
	 *
	 * @access  public
	 * @since   0.1
	 * @uses    get_post_meta, _e
	 * @return  void
	 */
	public function post_submitbox_misc_actions() {
		global $post;

		// Only show curator if this post is published
		if ( ! empty( $post->post_status ) && 'publish' !== $post->post_status ) {
			return;
		}

		// Is the curator enabled for this post type?
		if ( ! in_array( get_post_type( $post ), cur_get_post_types() ) ) {
			return;
		}

		$modules = cur_get_modules();

		$curated_post = cur_get_curated_post( $post->ID );

		if ( false !== $curated_post ) {
			$associated_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );
		}

		foreach ( $modules as $module => $module_info ) {
			if ( ! empty( $module_info['enabled'] ) && true === $module_info['enabled'] && ! empty( $module_info['slug'] ) ) {

				// Get the term object information
				$term = get_term_by( 'slug', $module_info['slug'], cur_get_tax_slug() );

				// Only show the other modules if this item is curated
				if ( 'curator' === $module || false !== $curated_post ) {
					$checked = false;
					if ( ! empty( $associated_terms[ $term->term_id ] ) ) {
						$checked = true;
					}
					?>
					<div class="misc-pub-section">
						<input type="checkbox" id="<?php esc_attr_e( $module_info['slug'] ); ?>" name="<?php esc_attr_e( $module_info['slug'] ); ?>" <?php checked( true, $checked ); ?> value="on" />
						<?php
						printf( '<label for="%s">%s</label>', esc_attr( $module_info['slug'] ), esc_html( $module_info['label'] ) );

						do_action( 'cur_module_' . $module . '_control' );

						if ( 'curator' === $module ) {
							wp_nonce_field( 'cur_curate_item', 'cur_curate_item_nonce' );
						} ?>
					</div>
				<?php
				}
			}
		}
	}

	/**
	 * Set whether the post should be curated or not
	 *
	 * @param $post_id
	 */
	public function save_post( $post_id, $post ) {
		// Is the curator enabled for this post type?
		if ( ! in_array( $post->post_type, cur_get_post_types() ) ) {
			return;
		}

		// Hah! Nice try - nope, no curating unpublished items
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// If autosave, our form has not been submitted, don't do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user's permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check nonce set
		if ( ! isset( $_POST['cur_curate_item_nonce'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['cur_curate_item_nonce'], 'cur_curate_item' ) ) {
			return;
		}

		$modules = cur_get_modules();

		/**
		 * Curate/Uncurate item logic
		 * Run before anything else
		 */
		if ( ! empty( $modules['curator'] ) && $modules['curator']['enabled'] && true === $modules['curator']['enabled'] ) {
			$curated_post = cur_get_curated_post( $post->ID );
			$curate_term = cur_get_module_term( 'curator' );

			// This post is not curated
			if ( false === $curated_post ) {

				// This post is not curated; we wish to curate it
				if ( isset( $_POST[ $curate_term ] ) && 'on' === $_POST[ $curate_term ] ) {
					$curated_post = cur_curate_post( $post_id, $post );
				}
			}

			// This post is curated
			else {

				// This post is curated; we don't want to uncurate it. Grab the curated post id
				if ( isset( $_POST[ $curate_term ] ) && 'on' === $_POST[ $curate_term ] ) {
					$curated_post = cur_get_related_id( $post_id );
				}

				// This post is curated and we want to uncurate it
				else {
					cur_uncurate_item( $post_id );
				}
			}

			// Only run other modules if this post has been curated
			if ( false !== $curated_post ) {
				$associated_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );

				/**
				 * Run through and set/unset our other modules
				 */
				foreach ( $modules as $module => $module_info ) {
					if ( ! empty( $module_info['enabled'] ) && true === $module_info['enabled'] && ! empty( $module_info['slug'] ) ) {

						// Skip the curator module, already handled that logic above
						if ( 'curator' === $module ) {
							continue;
						}

						// Get term object
						$term = get_term_by( 'slug', $module_info['slug'], cur_get_tax_slug() );

						if ( ! empty( $term->slug ) ) {
							$term_slug = $term->slug;

							// See if term is currently associated with post
							if ( ! empty( $associated_terms[ $term->term_id ] ) && $module_info['slug'] === $term_slug ) {

								// Post associated with term; no change
								if ( isset( $_POST[ $term_slug ] ) && 'on' === $_POST[ $term_slug ] ) {

									// Featurer is enabled, allow for custom sizes
									if ( ! empty( $_POST['cur-featurer-size'] ) ) {
										if ( 'featurer' === $module && cur_is_module_enabled( 'featurer' ) ) {

											// Ensure custom sizes exist
											$sizes = cur_get_featurer_sizes();
											if ( ! empty( $sizes ) ) {
												update_post_meta( $curated_post, 'cur_featured_size', sanitize_text_field( $_POST['cur-featurer-size'] ) );
											}
										}
									}

									continue;
								} // Post associated with term; remove term association
								else if ( ! isset( $_POST[ $term_slug ] ) ) {
									$set_modules[ $module ] = 'remove';

									// If pinner module, remove from pinned items array
									if ( 'pinner' === $module && cur_is_module_enabled( 'pinner' ) ) {
										cur_unpin_item( $curated_post );
									}

									// Ensure custom sizes exist
									$sizes = cur_get_featurer_sizes();
									if ( 'featurer' === $module && cur_is_module_enabled( 'featurer' ) ) {
										delete_post_meta( $curated_post, 'cur_featured_size' );
									}
								}
							} // Post not associated with term
							else {

								// Post not associated with term; add term association
								if ( isset( $_POST[ $term_slug ] ) && 'on' === $_POST[ $term_slug ] ) {
									$set_modules[ $module ] = 'add';

									// If pinner module, add to pinner array
									if ( 'pinner' === $module && cur_is_module_enabled( 'pinner' ) ) {
										cur_pin_item( $curated_post );
									}
								}
							}
						}
					}
				}

				// We have a change to make
				if ( ! empty( $set_modules ) ) {
					cur_set_item_modules( $set_modules, $curated_post );
				}
			}
		}
	}

	/**
	 * Uncurate posts if they get unpublished
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( $old_status === 'publish' && $new_status !== 'publish' ) {
			$curated_post = cur_get_curated_post( $post->ID );

			if ( false !== $curated_post && is_int( $curated_post ) && $post->ID !== $curated_post ) {

				// Transitioning an original post, let's uncurate it
				cur_uncurate_item( $post->ID );
			}
		}
	}

	/**
	 * Remove the 'Add New' submenu item
	 *
	 * @since 0.2.0
	 */
	public function remove_add_new_menu() {
		remove_submenu_page( 'edit.php?post_type=' . $this->cpt_slug, 'post-new.php?post_type=' . $this->cpt_slug );
	}

	/**
	 * Enqueue our styles and scripts for admin usage
	 *
	 * @since 0.2.0
	 */
	public function admin_enqueue() {
		$postfix = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_style( 'curator_admin', CUR_URL . '/assets/css/curator_admin.css' );
	}

	/**
	 * Registration
	 *
	 * @uses register_post_type()
	 * @since 0.1.0
	 */
	public function register_post_type() {
		$menu_icon = apply_filters( 'cur_menu_icon', 'dashicons-schedule' );

		$labels = array(
			'name'               => _x( 'Curator', 'curator post type general name', 'cur' ),
			'singular_name'      => _x( 'Curated Items', 'curator post type singular name', 'cur' ),
			'menu_name'          => _x( 'Curator', 'admin menu', 'cur' ),
			'name_admin_bar'     => _x( 'Curator', 'add new on admin bar', 'cur' ),
			'add_new'            => __( 'Add New', 'add new', 'cur' ),
			'add_new_item'       => __( 'Add New Curated Item', 'cur' ),
			'new_item'           => __( 'New Curated Item', 'cur' ),
			'edit_item'          => __( 'Edit Curated Item', 'cur' ),
			'view_item'          => __( 'View Curated Item', 'cur' ),
			'all_items'          => __( 'All Curator', 'cur' ),
			'search_items'       => __( 'Search Curated Items', 'cur' ),
			'parent_item_colon'  => __( 'Parent Curated Item:', 'cur' ),
			'not_found'          => __( 'No Curated Items found.', 'cur' ),
			'not_found_in_trash' => __( 'No Curated Items found in Trash.', 'cur' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => false,
			'query_var'           => true,
			'menu_icon'           => $menu_icon,
			'capability_type'     => 'post',
			'has_archive'         => true,
			'hierarchical'        => true,
			'menu_position'       => 3,
			'rewrite'             => array( 'slug' => $this->cpt_url_slug ),
			'exclude_from_search' => true,
			'supports'            => array(
				'title',
			),
		);

		register_post_type( $this->cpt_slug, $args );
	}

	/**
	 * Modify the curator post edit link to point to the original post
	 *
	 * @param $edit_link
	 * @param $post_id
	 * @param $context
	 *
	 * @return mixed
	 * @since 0.1.0
	 */
	public function filter_edit_post_link( $edit_link, $post_id, $context ) {

		if ( $this->cpt_slug === get_post_type( $post_id ) ) {

			// We found a curator post type, let's get it's related post ID
			$related_post_id = cur_get_related_id( $post_id );

			// Replace the Curated post type ID with the original post ID
			$edit_link = str_replace( $post_id, $related_post_id, $edit_link );
		}

		return $edit_link;
	}

	/**
	 * Add custom columns to curator post type.
	 * Featured Column (shows which posts should be weighted with more prominence)
	 * Post Type (Shows origin post type)
	 *
	 * @param $columns
	 *
	 * @return array
	 *
	 * @since 0.2.0
	 */
	public function manage_columns( $columns ) {
		$new_columns = array(
			'post_type' => __( 'Post Type', 'cur' ),
		);

		if ( cur_is_module_enabled( 'pinner' ) ) {
			$new_columns['pinned'] = __( 'Pinned', 'cur' );
		}

		if ( cur_is_module_enabled( 'featurer' ) ) {
			$new_columns['featured'] = __( 'Featured', 'cur' );
		}

		$count = 0;
		if ( ! empty( $columns['cb'] ) ) {
			$count++;
		}
		if ( ! empty( $columns['title'] ) ) {
			$count++;
		}

		// Insert our columns directly after the title - all other columns should be forced after these columns
		$columns = array_slice( $columns, 0, $count, true ) +
		           $new_columns +
		           array_slice( $columns, $count, count( $columns ) - $count, true );

		return $columns;
	}

	/**
	 * Display for our custom columns
	 *
	 * @param $column
	 * @param $post_id
	 *
	 * @since 0.2.0
	 */
	public function display_custom_columns( $column, $post_id ) {
		$modules = cur_get_modules();

		$curated_post = cur_get_curated_post( $post_id );

		switch( $column ) {
			case 'pinned':
				$associated_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );

				$term = get_term_by( 'slug', $modules['pinner']['slug'], cur_get_tax_slug() );

				if ( ! empty( $associated_terms[ $term->term_id ] ) ) {
					$pinned = true;
				} else {
					$pinned = false;
				}

				if ( true === $pinned ) {
					echo '<div class="wp-menu-image dashicons-before dashicons-admin-post cur-curator-featured-item"><br></div>';
				}

				break;
			case 'featured':
				$associated_terms = wp_list_pluck( wp_get_object_terms( $curated_post, cur_get_tax_slug() ), 'slug', 'term_id' );

				$term = get_term_by( 'slug', $modules['featurer']['slug'], cur_get_tax_slug() );

				if ( ! empty( $associated_terms[ $term->term_id ] ) ) {
					$featured = true;
				} else {
					$featured = false;
				}

				if ( true === $featured ) {
					echo '<div class="wp-menu-image dashicons-before dashicons-star-filled cur-curator-featured-item"><br></div>';
				}

				break;
			case 'post_type';
				$post_type = get_post_type( cur_get_related_id( $curated_post ) );
				$post_type_obj = get_post_type_object( $post_type );

				echo esc_html( $post_type_obj->labels->singular_name );

				break;
		}
	}
}

CUR_CPT_Curator::factory();

/**
 * Accessor Functions
 */

function cur_get_cpt_slug() {
	return CUR_CPT_Curator::factory()->cpt_slug;
}
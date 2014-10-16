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

		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		add_action( 'admin_menu', array( $this, 'remove_add_new_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );

		add_action( 'trashed_post', array( $this, 'trashed_post' ), 200 );

		/**
		 * @todo using bostonmagazine's featured item curator as a model
		 *
		 * Workflow:
		 * - Have a radio select box that allows user to choose the post type they wish to use
		 * - Perform query to grab the most recent(all?) items for that post type
		 * - Display items in chronological order in select dropdown
		 * - User selects one item from dropdown
		 * - Store selection as showcase item to be related/tied to that item
		 * - Think about performance of front end display? - Should definitely be cached, regenerated any time a new curated item is saved
		 *
		 * Thoughts:
		 * - use hidden (kind of) post type (don't allow for manual creation of new item)
		 * - have checkbox on main post type publish box that allows for creation of new item
		 * - showcase admin archive listing page allows for sorting
		 * - create as separate plugin
		 *
		 * @todo add link/info on curated item edit screen from main item (possibly display meta/images/etc)
		 */
	}

	/**
	 * Disable the trash for this post type, as it just confuses things
	 *
	 * @param $post_id
	 */
	public function trashed_post( $post_id ) {
		if ( cur_get_cpt_slug() === get_post_type( $post_id ) ) {

			// Get the ID of the main item, and then use the same removal/disconnect method
			cur_remove_curated_item( cur_get_related_id( $post_id ) );
		} else if ( in_array( get_post_type( $post_id ), cur_get_post_types() ) ) {

			// Deleting the main item, let's remove the attached curated item
			cur_remove_curated_item( $post_id );
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

		// Is the curator enabled for this post type?
		if ( ! in_array( get_post_type( $post ), cur_get_post_types() ) ) {
			return;
		}

		$is_curated = has_term( 'curate-item', cur_get_tax_slug() );
		?>
		<div class="misc-pub-section curtime misc-pub-section">
			<input type="checkbox" id="curate-item" name="curate_item" <?php checked( true, $is_curated ); ?> value="on" />
			<?php
			printf( '<label for="curate-item">%s</label>', __( 'Curate item', 'cur' ) );

			if ( true === $is_curated ) {
				printf( ' <a href="%s" target="_blank">%s</a>',
					get_edit_post_link( cur_get_related_id( $post->ID ) ),
					__( 'Edit', 'cur' ) );
			}

			wp_nonce_field( 'cur_curate_item', 'cur_curate_item_nonce' ); ?>
		</div>
	<?php
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

		// Check nonce set
		if ( ! isset( $_POST['cur_curate_item_nonce'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['cur_curate_item_nonce'], 'cur_curate_item' ) ) {
			return;
		}

		// If autosave, our form has not been submitted, don't do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user's permissions
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		// is it already curated?
		$curate_term = cur_get_curate_term();
		$is_curated = has_term( $curate_term->term_id, cur_get_tax_slug() );

		// Already curated
		if ( ! empty( $is_curated ) && ! is_wp_error( $is_curated ) ) {

			// No change
			if ( isset( $_POST['curate_item'] ) ) {
				return;
			} else if ( empty( $_POST['curate_item'] ) ) {

				// Remove curation for this item!
				cur_remove_curated_item( $post_id );
			}
		} else {

			// Not currently curated
			if ( isset( $_POST['curate_item'] ) && 'on' === $_POST['curate_item'] ) {

				// Start curation for this item!
				cur_create_curated_item( $post_id, $post );
			}
		}
	}

	/**
	 * Remove the 'Add New' submenu item
	 */
	public function remove_add_new_menu() {
		remove_submenu_page( 'edit.php?post_type=' . $this->cpt_slug, 'post-new.php?post_type=' . $this->cpt_slug );
	}

	/**
	 * Enqueue our styles and scripts for admin usage
	 */
	public function admin_enqueue() {
		$postfix = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? '' : '.min';

		//		wp_enqueue_script(
		//			'curator_admin',
		//			CUR_URL . "/assets/js/curator_admin{$postfix}.js",
		//			array( 'jquery' ),
		//			CUR_VERSIO3N
		//		);

		wp_enqueue_style( 'curator_admin', CUR_URL . '/assets/css/curator_admin.css' );
	}

	/**
	 * Registration
	 *
	 * @uses register_post_type()
	 */
	public function register_post_type() {
		$menu_icon = apply_filters( 'cur_menu_icon', 'dashicons-schedule' );

		$labels = array(
			'name'               => _x( 'Curator', 'curator post type general name', 'fpb' ),
			'singular_name'      => _x( 'Curated Items', 'curator post type singular name', 'fpb' ),
			'menu_name'          => _x( 'Curator', 'admin menu', 'fpb' ),
			'name_admin_bar'     => _x( 'Curator', 'add new on admin bar', 'fpb' ),
			'add_new'            => __( 'Add New', 'add new', 'fpb' ),
			'add_new_item'       => __( 'Add New Curated Item', 'fpb' ),
			'new_item'           => __( 'New Curated Item', 'fpb' ),
			'edit_item'          => __( 'Edit Curated Item', 'fpb' ),
			'view_item'          => __( 'View Curated Item', 'fpb' ),
			'all_items'          => __( 'All Curator', 'fpb' ),
			'search_items'       => __( 'Search Curated Items', 'fpb' ),
			'parent_item_colon'  => __( 'Parent Curated Item:', 'fpb' ),
			'not_found'          => __( 'No Curated Items found.', 'fpb' ),
			'not_found_in_trash' => __( 'No Curated Items found in Trash.', 'fpb' ),
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
			'menu_position'       => 50,
			'rewrite'             => array( 'slug' => $this->cpt_url_slug ),
			'exclude_from_search' => true,
			'supports'            => array(
				'title',
			),
		);

		register_post_type( $this->cpt_slug, $args );
	}
}

CUR_CPT_Curator::factory();

function cur_get_cpt_slug() {
	return CUR_CPT_Curator::factory()->cpt_slug;
}
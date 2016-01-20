<?php
/**
 * Plugin Name: Curator
 * Plugin URI:  http://github.com/AaronHolbrook/curator
 * Description: Select specific posts from across multiple post types to combine together and control the ordering.
 * Version:     0.2.2
 * Author:      Aaron Holbrook, Gustave Gerhardt, 10up
 * Author URI:  http://10up.com
 * License:     MIT
 * Text Domain: cur
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 Aaron Holbrook, Gustave Gerhardt, 10up (email : info@10up.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Built using grunt-wp-plugin
 * Copyright (c) 2013 10up, LLC
 * https://github.com/10up/grunt-wp-plugin
 */

// Useful global constants
define( 'CUR_VERSION', '0.2.2' );
define( 'CUR_URL',     plugin_dir_url( __FILE__ ) );
define( 'CUR_PATH',    dirname( __FILE__ ) . '/' );

/**
 * Include classes
 */
require_once( CUR_PATH . 'includes/class-singleton.php' );
require_once( CUR_PATH . 'includes/class-curator.php' );
require_once( CUR_PATH . 'includes/class-cpt-curator.php' );
require_once( CUR_PATH . 'includes/class-tax-curator.php' );

/**
 * Default initialization for the plugin:
 * - Registers the default textdomain.
 */
function cur_init() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'cur' );
	load_textdomain( 'cur', WP_LANG_DIR . '/cur/cur-' . $locale . '.mo' );
	load_plugin_textdomain( 'cur', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'wp_loaded', 'cur_setup_default_terms', 900 );

/**
 * Determine whether to add an admin notice when simple page ordering plugin is missing.
 */
function cur_maybe_add_spop_notice() {

	/**
	 * Filter to allow developers not to show the missing page ordering notice at all.
	 *
	 * Passing a false value to the filter will short-circuit showing the missing page ordering plugin notice.
	 *
	 * @param bool $show_spop_notice Value set for the option.
	 */
	$show_spop_notice = apply_filters( 'cur_show_missing_spop_notification', true );

	if ( false === $show_spop_notice ) {
		return;
	}
	
	// Return if Simple Page Ordering plugin is active.
	if ( is_plugin_active( 'simple-page-ordering/simple-page-ordering.php' ) ) {
		return;
	}

	if ( ! empty( $_GET['dismiss_spop_msg'] ) && '1' === $_GET['dismiss_spop_msg'] ) {
		update_option( 'dismiss-spop-msg', true );
	}
	
	if ( ! get_option( 'dismiss-spop-msg', false ) ) {
		add_action( 'admin_notices', 'cur_missing_simple_ordering_notice' );
	}

}
add_action( 'admin_init', 'cur_maybe_add_spop_notice' );

/**
 * Call back function to add notice if simple ordering plugin is not installed.
 */
function cur_missing_simple_ordering_notice() {

	$is_installed = get_plugins( '/simple-page-ordering' );

	$activate_msg = sprintf(
		__( 'The %s plugin is not active!', 'cur' ),
		'<b> Simple Page Ordering </b>'
	);

	$install_msg = sprintf(
		__( 'For best use, please install and activate the %s plugin.', 'cur' ),
		'<b><a href ="http://10up.com/plugins/simple-page-ordering-wordpress/">Simple Page Ordering</a></b>'
	);


	$allowed_html = array(
		'a' => array(
			'href'  => array(),
			'title' => array(),
		),
		'b' => array(),
	);
	?>
	<div class="error notice is-dismissible">
		<p>
			<b>Curator: </b><?php echo ( $is_installed ) ? wp_kses( $activate_msg, $allowed_html ) : wp_kses( $install_msg, $allowed_html ); ?>
			&nbsp;&nbsp;<a href="<?php echo add_query_arg( array( 'dismiss_spop_msg'=>1 ) ) ?>"><?php esc_html_e( 'Don\'t show this again.', 'cur' ) ?></a>
		</p>
	</div>
<?php
}

/**
 * Activate the plugin
 */
function cur_activate() {
	// First load the init scripts in case any rewrite functionality is being loaded
	cur_init();

	flush_rewrite_rules();

	add_action( 'init', 'cur_setup_default_terms', 900 );
}
register_activation_hook( __FILE__, 'cur_activate' );

/**
 * Deactivate the plugin
 * Uninstall routines should be in uninstall.php
 */
function cur_deactivate() {
	delete_option( 'dismiss-spop-msg' );
}
register_deactivation_hook( __FILE__, 'cur_deactivate' );

// Wireup actions
add_action( 'init', 'cur_init' );
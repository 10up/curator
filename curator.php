<?php
/**
 * Plugin Name: Curator
 * Plugin URI:  http://github.com/AaronHolbrook/curator
 * Description: Select specific posts from across multiple post types to combine together and control the ordering.
 * Version:     0.2.1
 * Author:      Aaron Holbrook, Gustave Gerhardt, 10up
 * Author URI:  http://10up.com
 * License:     GPLv2+
 * Text Domain: cur
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 Aaron Holbrook, Gustave Gerhardt, 10up (email : info@10up.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using grunt-wp-plugin
 * Copyright (c) 2013 10up, LLC
 * https://github.com/10up/grunt-wp-plugin
 */

// Useful global constants
define( 'CUR_VERSION', '0.2.0' );
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

}
register_deactivation_hook( __FILE__, 'cur_deactivate' );

// Wireup actions
add_action( 'init', 'cur_init' );
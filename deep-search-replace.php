<?php
/**
 * Plugin Name:       Deep Search & Replace
 * Plugin URI:        https://wordpress.org/plugins/deep-search-replace/
 * Description:       Searches ALL database tables for a URL/text and replaces it. Finds links hidden in serialized data, post meta, options, widgets, and more.
 * Version:           1.0.0
 * Author:            devshagor
 * Author URI:        https://profiles.wordpress.org/shagors/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       deep-search-replace
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Domain Path:       /languages
 *
 * @package Deep_Search_Replace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'DSR_VERSION', '1.0.0' );
define( 'DSR_PLUGIN_FILE', __FILE__ );
define( 'DSR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin activation.
 */
function deep_search_replace_activate() {
	add_option( 'deep_search_replace_do_activation_redirect', true );
}
register_activation_hook( __FILE__, 'deep_search_replace_activate' );

/**
 * Load admin-only functionality.
 *
 * All plugin classes, styles, and hooks are loaded exclusively in the admin.
 * Nothing is loaded on the frontend — zero performance impact.
 */
if ( is_admin() ) {
	require_once DSR_PLUGIN_DIR . 'includes/class-dsr-replacer.php';
	require_once DSR_PLUGIN_DIR . 'includes/class-dsr-backup.php';
	require_once DSR_PLUGIN_DIR . 'includes/class-dsr-admin.php';

	DSR_Admin::init();

	/**
	 * Add Settings link on the plugins list page.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	function deep_search_replace_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'tools.php?page=deep-search-replace' ) ) . '">'
			. esc_html__( 'Settings', 'deep-search-replace' ) . '</a>';
		$links[]       = $settings_link;
		return $links;
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'deep_search_replace_action_links' );
}

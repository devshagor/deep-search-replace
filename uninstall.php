<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Deep_Search_Replace
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'deep_search_replace_do_activation_redirect' );

<?php
/**
 * Admin page registration and hooks.
 *
 * @package Deep_Search_Replace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu, enqueues styles, and renders the page.
 */
class DSR_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_activation_redirect' ) );
		add_action( 'admin_init', array( 'DSR_Backup', 'handle_download' ) );
	}

	/**
	 * Register the admin menu item under Tools.
	 */
	public static function register_menu() {
		$hook = add_management_page(
			__( 'Deep Search & Replace', 'deep-search-replace' ),
			__( 'Deep Search & Replace', 'deep-search-replace' ),
			'manage_options',
			'deep-search-replace',
			array( __CLASS__, 'render_page' )
		);

		if ( $hook ) {
			add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) use ( $hook ) {
				if ( $hook_suffix !== $hook ) {
					return;
				}
				wp_enqueue_style(
					'dsr-admin',
					DSR_PLUGIN_URL . 'admin/css/admin-style.css',
					array(),
					DSR_VERSION
				);
			} );
		}
	}

	/**
	 * Handle the activation redirect.
	 */
	public static function handle_activation_redirect() {
		if ( ! get_option( 'deep_search_replace_do_activation_redirect', false ) ) {
			return;
		}

		delete_option( 'deep_search_replace_do_activation_redirect' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a core WordPress query parameter checked during plugin activation, not a form submission.
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'tools.php?page=deep-search-replace' ) );
		exit;
	}

	/**
	 * Render the admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'deep-search-replace' ) );
		}

		$search  = '';
		$replace = '';
		$action  = '';
		$results = null;

		if ( isset( $_POST['dsr_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsr_nonce'] ) ), 'dsr_action' ) ) {
			$search  = isset( $_POST['dsr_search'] ) ? sanitize_text_field( wp_unslash( $_POST['dsr_search'] ) ) : '';
			$replace = isset( $_POST['dsr_replace'] ) ? sanitize_text_field( wp_unslash( $_POST['dsr_replace'] ) ) : '';
			$action  = isset( $_POST['dsr_action'] ) ? sanitize_text_field( wp_unslash( $_POST['dsr_action'] ) ) : '';

			if ( ! empty( $search ) && in_array( $action, array( 'search', 'replace' ), true ) ) {
				$results = DSR_Replacer::process( $search, $replace, $action );
			}
		}

		include DSR_PLUGIN_DIR . 'admin/views/admin-page.php';
	}
}

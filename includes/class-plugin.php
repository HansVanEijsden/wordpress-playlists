<?php
/**
 * Plugin
 *
 * Main bootstrap: loads the text domain, runs dependency checks and
 * instantiates the plugin's components.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->boot();
	}

	private function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		new Settings();
		new Rest_Controller();
		new Gettext_Filters();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'wordpress-playlists', false, dirname( WORDPRESS_PLAYLISTS_BASENAME ) . '/languages' );
	}

	/**
	 * Surface dependency and configuration problems to admins.
	 */
	public function admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! is_plugin_active( 'radio-station/radio-station.php' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'WordPress Playlists depends on the Radio Station plugin; install and activate it to create playlists.', 'wordpress-playlists' ) . '</p></div>';
		}

		$missing = Settings::stations_missing_keys();
		if ( ! empty( $missing ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: comma separated station names */
					__( 'WordPress Playlists: enter an API key for %s on the settings page before tracks can be accepted.', 'wordpress-playlists' ),
					implode( ', ', $missing )
				)
			) . '</p></div>';
		}
	}
}

<?php
/**
 * Routes
 *
 * Month pages, the artist index and its letter pages, the custom templates,
 * the legacy redirects and the deferred rewrite flush.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Routes {

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rules' ), 12 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'parse_query', array( $this, 'parse_query' ) );
		add_filter( 'pre_handle_404', array( $this, 'pre_handle_404' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_filter( 'redirect_canonical', array( $this, 'redirect_canonical' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirects' ), 1 );
		add_action( 'save_post_page', array( $this, 'forget_pages' ) );
		add_action( 'deleted_post', array( $this, 'forget_pages' ) );
		add_action( 'pre_get_posts', array( $this, 'artist_query' ) );
	}

	/**
	 * Use the configured page size for the artist archive's main query, so the
	 * theme and our own query agree on pagination and never 404 a valid page.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public function artist_query( $query ): void {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_tax( Taxonomy::NAME ) ) {
			$query->set( 'posts_per_page', Context::per_page() );
		}
	}

	/**
	 * Register the custom rewrite rules.
	 */
	public function add_rules(): void {
		if ( ! Context::display_enabled() ) {
			return;
		}

		$hub_id = Context::hub_page_id();

		if ( $hub_id ) {
			$hub_path = trim( (string) get_page_uri( $hub_id ), '/' );

			if ( '' !== $hub_path ) {
				add_rewrite_rule(
					'^' . preg_quote( $hub_path, '#' ) . '/([0-9]{4})/([0-9]{2})/?$',
					'index.php?wppl_month=$matches[1]-$matches[2]',
					'top'
				);
			}
		}

		$index_base = Context::artists_index_base();

		if ( '' !== $index_base && ! get_page_by_path( $index_base ) ) {
			add_rewrite_rule(
				'^' . preg_quote( $index_base, '#' ) . '/?$',
				'index.php?wppl_artists=1',
				'top'
			);

			add_rewrite_rule(
				'^' . preg_quote( $index_base, '#' ) . '/([a-z]|0-9)/?$',
				'index.php?wppl_letter=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Register the public query variables.
	 *
	 * @param array $vars Existing variables.
	 * @return array
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'wppl_month';
		$vars[] = 'wppl_artists';
		$vars[] = 'wppl_letter';

		return $vars;
	}

	/**
	 * Keep custom routes out of the "home" and 404 states.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public function parse_query( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'wppl_month' ) || $query->get( 'wppl_artists' ) || $query->get( 'wppl_letter' ) ) {
			$query->is_home = false;
			$query->is_404  = false;
			$query->is_page = false;
		}
	}

	/**
	 * Prevent WordPress from turning our routes into a 404.
	 *
	 * @param bool      $preempt Whether to short-circuit.
	 * @param \WP_Query $query   Query object.
	 * @return bool
	 */
	public function pre_handle_404( $preempt, $query ): bool {
		if ( $query instanceof \WP_Query && $query->is_main_query() && ( $query->get( 'wppl_month' ) || $query->get( 'wppl_artists' ) || $query->get( 'wppl_letter' ) ) ) {
			return true;
		}

		return (bool) $preempt;
	}

	/**
	 * WordPress should not canonicalise our custom routes to the home page.
	 *
	 * @param string|false $redirect_url Suggested redirect.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function redirect_canonical( $redirect_url, $requested_url ) {
		if ( get_query_var( 'wppl_month' ) || get_query_var( 'wppl_artists' ) || get_query_var( 'wppl_letter' ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Load the plugin templates for our routes and for artist archives.
	 *
	 * @param string $template Theme template.
	 * @return string
	 */
	public function template_include( $template ): string {
		if ( ! Context::display_enabled() ) {
			return (string) $template;
		}

		$month = (string) get_query_var( 'wppl_month' );

		if ( '' !== $month ) {
			$parts = Month_View::parse( $month );

			if ( null === $parts || null === Context::month_entry( $parts['year'], $parts['month'] ) ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();

				$not_found = (string) get_query_template( '404' );

				return '' !== $not_found ? $not_found : (string) $template;
			}

			Assets::enqueue();

			return WORDPRESS_PLAYLISTS_DIR . 'templates/month.php';
		}

		if ( get_query_var( 'wppl_artists' ) || get_query_var( 'wppl_letter' ) ) {
			Assets::enqueue();

			return WORDPRESS_PLAYLISTS_DIR . 'templates/artists-index.php';
		}

		if ( is_tax( Taxonomy::NAME ) ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$plays = (int) get_term_meta( $term->term_id, Artist_Index::META_PLAY_COUNT, true );

				if ( $plays < 1 ) {
					global $wp_query;
					$wp_query->set_404();
					status_header( 404 );
					nocache_headers();

					$not_found = (string) get_query_template( '404' );

					return '' !== $not_found ? $not_found : (string) $template;
				}
			}

			Assets::enqueue();

			return WORDPRESS_PLAYLISTS_DIR . 'templates/taxonomy-playlist_artist.php';
		}

		return (string) $template;
	}

	/**
	 * Legacy redirects.
	 */
	public function redirects(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! Context::display_enabled() ) {
			return;
		}

		$hub_url = Context::hub_url();

		if ( is_post_type_archive( 'playlist' ) ) {
			wp_safe_redirect( $hub_url, 301, 'WordPress Playlists' );
			exit;
		}

		$hub_id = Context::hub_page_id();

		if ( $hub_id && is_page( $hub_id ) && isset( $_GET['offset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( $hub_url, 301, 'WordPress Playlists' );
			exit;
		}

		$request_path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		$hub_path     = $hub_id ? trim( (string) get_page_uri( $hub_id ), '/' ) : '';

		foreach ( Context::legacy_hub_paths() as $legacy_path ) {
			if ( '' !== $request_path && $legacy_path !== $hub_path && $request_path === $legacy_path ) {
				wp_safe_redirect( $hub_url, 301, 'WordPress Playlists' );
				exit;
			}
		}

		if ( ! empty( Context::setting( 'clean_playlist_urls', false ) ) ) {
			$this->clean_playlist_redirect( $hub_url );
		}
	}

	/**
	 * Redirect old playlist URLs that contain the site's front path.
	 *
	 * @param string $hub_url Hub URL.
	 */
	private function clean_playlist_redirect( string $hub_url ): void {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path    = (string) wp_parse_url( $request, PHP_URL_PATH );

		if ( '' === $path ) {
			return;
		}

		$structure = (string) get_option( 'permalink_structure', '' );
		$front     = '';

		if ( preg_match( '#^/([^/%]+)/#', $structure, $matches ) ) {
			$front = preg_quote( $matches[1], '#' );
		}

		if ( '' !== $front && preg_match( '#^/' . $front . '/playlists/?$#', $path ) ) {
			wp_safe_redirect( $hub_url, 301, 'WordPress Playlists' );
			exit;
		}

		if ( '' !== $front && preg_match( '#^/' . $front . '/playlist/(.+?)/?$#', $path, $matches ) ) {
			$target = home_url( user_trailingslashit( 'playlist/' . $matches[1] ) );
			wp_safe_redirect( $target, 301, 'WordPress Playlists' );
			exit;
		}
	}

	/**
	 * Flush rewrite rules once after an upgrade or a settings change.
	 */
	public function maybe_flush(): void {
		$stored  = (string) get_option( 'wordpress_playlists_version', '' );
		$changed = (string) get_option( 'wordpress_playlists_flush_rewrite', '' );

		if ( WORDPRESS_PLAYLISTS_VERSION !== $stored ) {
			$changed = '1';
			update_option( 'wordpress_playlists_version', WORDPRESS_PLAYLISTS_VERSION, false );
		}

		if ( '' === $changed ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( 'wordpress_playlists_flush_rewrite' );
	}

	/**
	 * Forget cached page detections when a page is saved or deleted.
	 */
	public function forget_pages(): void {
		Context::forget_hub_page();
		Context::forget_archive_page();
	}
}

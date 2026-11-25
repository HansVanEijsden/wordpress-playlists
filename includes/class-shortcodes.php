<?php
/**
 * Shortcodes
 *
 * Registers the hub and artist-card shortcodes and enqueues the display
 * assets as soon as one of them is present in the requested post.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	/** Hub shortcode tag. */
	public const HUB = 'wordpress_playlists';

	/** Artist card shortcode tag. */
	public const ARTIST = 'wordpress_playlists_artist';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
	}

	/**
	 * Register the shortcodes.
	 */
	public function register(): void {
		$hub = new Hub_View();

		add_shortcode( self::HUB, array( $hub, 'shortcode' ) );

		$artist = new Artist_View();

		add_shortcode( self::ARTIST, array( $artist, 'embed_shortcode' ) );
	}

	/**
	 * Enqueue the stylesheet and script on pages that need them.
	 */
	public function maybe_enqueue(): void {
		if ( ! Context::display_enabled() ) {
			return;
		}

		if ( is_singular( 'playlist' ) || is_tax( Taxonomy::NAME ) ) {
			Assets::enqueue();
			return;
		}

		if ( get_query_var( 'wppl_month' ) || get_query_var( 'wppl_artists' ) || get_query_var( 'wppl_letter' ) ) {
			Assets::enqueue();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( has_shortcode( (string) $post->post_content, self::HUB ) || has_shortcode( (string) $post->post_content, self::ARTIST ) ) {
			Assets::enqueue();
		}
	}
}

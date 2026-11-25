<?php
/**
 * Assets
 *
 * Enqueues the display stylesheet and script, and inline accent custom
 * properties derived from the station colour.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Assets {

	/** Stylesheet handle. */
	public const STYLE = 'wordpress-playlists-display';

	/** Script handle. */
	public const SCRIPT = 'wordpress-playlists-display';

	/** Whether the assets were enqueued already. */
	private static bool $enqueued = false;

	/**
	 * Enqueue the display assets.
	 */
	public static function enqueue(): void {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;

		$css_path = WORDPRESS_PLAYLISTS_DIR . 'assets/css/playlist-display.css';
		$js_path  = WORDPRESS_PLAYLISTS_DIR . 'assets/js/playlist-display.js';

		$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WORDPRESS_PLAYLISTS_VERSION;
		$js_version  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WORDPRESS_PLAYLISTS_VERSION;

		wp_enqueue_style( self::STYLE, WORDPRESS_PLAYLISTS_URL . 'assets/css/playlist-display.css', array(), $css_version );
		wp_enqueue_script( self::SCRIPT, WORDPRESS_PLAYLISTS_URL . 'assets/js/playlist-display.js', array(), $js_version, true );

		$palette = Palette::derive( Context::accent() );

		$inline = sprintf(
			'.wppl{--wppl-accent:%1$s;--wppl-accent-ink:%2$s;--wppl-on-accent:%3$s;--wppl-accent-soft:%4$s;--wppl-accent-soft-strong:%5$s;}',
			$palette['accent'],
			$palette['ink'],
			$palette['on'],
			$palette['soft'],
			$palette['soft-strong']
		);

		wp_add_inline_style( self::STYLE, $inline );

		wp_localize_script(
			self::SCRIPT,
			'WordPressPlaylistsDisplay',
			array(
				'timeZone'    => self::timezone(),
				'copyLabel'   => __( 'Copy link', 'wordpress-playlists' ),
				'copiedLabel' => __( 'Link copied', 'wordpress-playlists' ),
				'copyError'   => __( 'Could not copy the link', 'wordpress-playlists' ),
				'nowLabel'    => __( 'Now', 'wordpress-playlists' ),
				'playerError' => __( 'This broadcast could not be played.', 'wordpress-playlists' ),
			)
		);
	}

	/**
	 * Enqueue the term-field script on the artist taxonomy screens.
	 */
	public static function enqueue_term_fields(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return;
		}

		$is_artist_screen = in_array( $screen->id, array( 'edit-' . Taxonomy::NAME, Taxonomy::NAME ), true )
			|| ( 'term' === $screen->id && isset( $screen->taxonomy ) && Taxonomy::NAME === $screen->taxonomy );

		if ( ! $is_artist_screen ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'wordpress-playlists-term-fields',
			WORDPRESS_PLAYLISTS_URL . 'assets/js/term-fields.js',
			array( 'media-editor' ),
			WORDPRESS_PLAYLISTS_VERSION,
			true
		);

		wp_localize_script(
			'wordpress-playlists-term-fields',
			'WordPressPlaylistsTermFields',
			array(
				'select'  => __( 'Select image', 'wordpress-playlists' ),
				'use'     => __( 'Use this image', 'wordpress-playlists' ),
				'remove'  => __( 'Remove image', 'wordpress-playlists' ),
				'missing' => __( 'Choose an image, or leave empty for a monogram.', 'wordpress-playlists' ),
			)
		);
	}

	/**
	 * Station timezone as an IANA name where possible.
	 */
	private static function timezone(): string {
		if ( function_exists( 'wp_timezone_string' ) ) {
			$timezone = (string) wp_timezone_string();

			if ( '' !== $timezone && ! preg_match( '/^[+-]/', $timezone ) ) {
				return $timezone;
			}
		}

		return 'UTC';
	}
}

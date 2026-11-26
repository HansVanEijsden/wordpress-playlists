<?php
/**
 * Theme shells for the standalone artist routes.
 *
 * The artist pages are not WordPress pages; they still need the same content
 * container, shadow and widget area as the rest of the site. This adapter
 * reproduces the wrapper of the two themes used by the stations and falls back
 * to the plugin's own continuous white surface for other themes.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Theme_Shell {

	/**
	 * Register the body-class correction for GeneratePress.
	 */
	public static function register(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ), 99 );
	}

	/**
	 * Tell GeneratePress that these routes have a sidebar.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public static function body_class( array $classes ): array {
		if ( ! self::is_plugin_route() ) {
			return $classes;
		}

		$classes[] = 'wppl-artist-surface';

		if ( 'generatepress' !== get_template() ) {
			return $classes;
		}

		$classes = array_values( array_diff( $classes, array( 'no-sidebar', 'full-width-content' ) ) );
		$classes[] = 'right-sidebar';

		return $classes;
	}

	/**
	 * Whether the current request is one of the artist surfaces.
	 */
	public static function is_plugin_route(): bool {
		if ( is_tax( Taxonomy::NAME ) ) {
			return true;
		}

		return (bool) get_query_var( 'wppl_artists' ) || (bool) get_query_var( 'wppl_letter' );
	}

	/**
	 * Wrap main and rail content in the active theme's layout.
	 *
	 * @param string $main  Main column HTML.
	 * @param string $rail  Sidebar HTML.
	 */
	public static function render( string $main, string $rail = '' ): string {
		switch ( get_template() ) {
			case 'Newspaper':
				return self::newspaper( $main, $rail );

			case 'generatepress':
				return self::generatepress( $main, $rail );
		}

		return self::fallback( $main, $rail );
	}

	/**
	 * Newspaper page shell.
	 *
	 * @param string $main Main column HTML.
	 * @param string $rail Sidebar HTML.
	 */
	private static function newspaper( string $main, string $rail ): string {
		$html  = '<div class="td-main-content-wrap td-container-wrap"><div class="td-container"><div class="td-pb-row">';
		$html .= '<div class="td-pb-span8 td-main-content"><div class="td-ss-main-content">' . $main . '</div></div>';

		if ( '' !== $rail ) {
			$html .= '<div class="td-pb-span4 td-main-sidebar"><div class="td-ss-main-sidebar">' . $rail . '</div></div>';
		}

		return $html . '</div></div></div>';
	}

	/**
	 * GeneratePress page shell.
	 *
	 * @param string $main Main column HTML.
	 * @param string $rail Sidebar HTML.
	 */
	private static function generatepress( string $main, string $rail ): string {
		$html  = '<div id="primary" class="content-area"><main id="main" class="site-main">' . $main . '</main></div>';

		if ( '' !== $rail ) {
			$html .= '<div id="right-sidebar" class="widget-area sidebar is-right-sidebar"><div class="inside-right-sidebar">' . $rail . '</div></div>';
		}

		return $html;
	}

	/**
	 * Continuous white fallback shell.
	 *
	 * @param string $main Main column HTML.
	 * @param string $rail Sidebar HTML.
	 */
	private static function fallback( string $main, string $rail ): string {
		$html  = '<main id="wppl-primary" class="wppl-main wppl-main--rail"><div class="wppl-rail-layout"><div class="wppl-rail-main">' . $main . '</div>';

		if ( '' !== $rail ) {
			$html .= '<aside class="wppl-rail-aside widget-area">' . $rail . '</aside>';
		}

		return $html . '</div></main>';
	}
}

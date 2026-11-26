<?php
/**
 * Components
 *
 * Shared, pure HTML fragments for the playlist, hub, month and artist views.
 * No database access, so the preview harness renders the real markup.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Components {

	/**
	 * Breadcrumb trail.
	 *
	 * @param array<int,array{label:string,url?:string}> $items Trail, last item is the current page.
	 */
	public static function breadcrumb( array $items ): string {
		if ( empty( $items ) ) {
			return '';
		}

		$html = '<nav class="wppl-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'wordpress-playlists' ) . '"><ol class="wppl-crumbs-list">';

		$last = count( $items ) - 1;

		foreach ( array_values( $items ) as $index => $item ) {
			$label = (string) ( $item['label'] ?? '' );
			$url   = (string) ( $item['url'] ?? '' );

			$html .= '<li class="wppl-crumbs-item">';

			if ( $index !== $last && '' !== $url ) {
				$html .= '<a class="wppl-crumbs-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			} else {
				$html .= '<span aria-current="page">' . esc_html( $label ) . '</span>';
			}

			$html .= '</li>';
		}

		return $html . '</ol></nav>';
	}

	/**
	 * Playlist row used by the hub and month views.
	 *
	 * @param array $playlist Playlist model.
	 */
	public static function playlist_row( array $playlist ): string {
		$time  = (string) ( $playlist['span'] ?? '' );
		$title = (string) ( $playlist['show']['name'] ?? $playlist['title'] ?? '' );
		$count = (int) ( $playlist['count'] ?? 0 );
		$url   = (string) ( $playlist['url'] ?? '' );
		$tease = (string) ( $playlist['teaser'] ?? '' );

		$meta = array();

		if ( $count > 0 ) {
			$meta[] = Renderer::count_label(
				$count,
				/* translators: %s: number of tracks. */
				'%s track',
				/* translators: %s: number of tracks. */
				'%s tracks'
			);
		}

		if ( '' !== $tease ) {
			$meta[] = $tease;
		}

		$html  = '<li class="wppl-row">';
		$html .= '<a class="wppl-row-link" href="' . esc_url( $url ) . '">';

		if ( '' !== $time ) {
			$parts = explode( ' – ', $time, 2 );

			$html .= '<span class="wppl-row-time"><span class="wppl-row-start">' . esc_html( $parts[0] ) . '</span>';

			if ( isset( $parts[1] ) ) {
				$html .= '<span class="wppl-row-end"> – ' . esc_html( $parts[1] ) . '</span>';
			}

			$html .= '</span>';
		}

		$html .= '<span class="wppl-row-body">';
		$html .= '<span class="wppl-row-title">' . esc_html( $title ) . '</span>';

		if ( ! empty( $meta ) ) {
			$html .= '<span class="wppl-row-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
		}

		$html .= '</span>';
		$html .= '<span class="wppl-row-go" aria-hidden="true">' . self::icon( 'arrow-right' ) . '</span>';
		$html .= '</a></li>';

		return $html;
	}

	/**
	 * Day heading for grouped playlists.
	 *
	 * @param string $weekday Localised weekday.
	 * @param string $date    Localised date.
	 * @param int    $count   Number of playlists.
	 * @param string $id      Anchor ID.
	 */
	public static function day_heading( string $weekday, string $date, int $count, string $id = '' ): string {
		$html  = '<h3 class="wppl-day"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
		$html .= '<span class="wppl-day-weekday">' . esc_html( $weekday ) . '</span>';
		$html .= '<span class="wppl-day-date">' . esc_html( $date ) . '</span>';

		if ( $count > 0 ) {
			$html .= '<span class="wppl-day-count">' . esc_html( Renderer::count_label( $count, '%s broadcast', '%s broadcasts' ) ) . '</span>';
		}

		return $html . '</h3>';
	}

	/**
	 * Section heading with a hairline.
	 *
	 * @param string $title Heading text.
	 * @param string $link  Optional "all" URL.
	 * @param string $label Optional link label.
	 */
	public static function section_head( string $title, string $link = '', string $label = '' ): string {
		$html  = '<div class="wppl-section-head">';
		$html .= '<div class="wppl-section-text">';
		$html .= '<h2 class="wppl-section-title">' . esc_html( $title ) . '</h2>';
		$html .= '</div>';

		if ( '' !== $link && '' !== $label ) {
			$html .= '<a class="wppl-section-link" href="' . esc_url( $link ) . '">' . esc_html( $label ) . self::icon( 'arrow-right' ) . '</a>';
		}

		return $html . '</div>';
	}

	/**
	 * Artist avatar: editorial photo when available, otherwise a monogram.
	 *
	 * @param array  $artist Artist model.
	 * @param string $size   "sm", "md" or "lg".
	 */
	public static function avatar( array $artist, string $size = 'md' ): string {
		$name  = (string) ( $artist['name'] ?? '' );
		$image = (string) ( $artist['image'] ?? '' );
		$class = 'wppl-avatar wppl-avatar--' . sanitize_html_class( $size );

		if ( '' !== $image ) {
			return '<span class="' . esc_attr( $class ) . '"><img src="' . esc_url( $image ) . '" alt="" width="96" height="96" loading="lazy" decoding="async" /></span>';
		}

		return '<span class="' . esc_attr( $class ) . '" aria-hidden="true"><span class="wppl-mono">' . esc_html( Renderer::initials( $name ) ) . '</span></span>';
	}

	/**
	 * Small status badge.
	 *
	 * @param string $label    Text.
	 * @param string $modifier Style modifier.
	 */
	public static function badge( string $label, string $modifier = 'neutral' ): string {
		return '<span class="wppl-badge wppl-badge--' . sanitize_html_class( $modifier ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Empty state panel.
	 *
	 * @param string $title       Heading.
	 * @param string $body        Body text.
	 * @param string $action_url  Optional link.
	 * @param string $action_text Optional link label.
	 */
	public static function empty_state( string $title, string $body = '', string $action_url = '', string $action_text = '' ): string {
		$html  = '<div class="wppl-empty">';
		$html .= '<p class="wppl-empty-title">' . esc_html( $title ) . '</p>';

		if ( '' !== $body ) {
			$html .= '<p class="wppl-empty-body">' . esc_html( $body ) . '</p>';
		}

		if ( '' !== $action_url && '' !== $action_text ) {
			$html .= '<a class="wppl-button" href="' . esc_url( $action_url ) . '">' . esc_html( $action_text ) . '</a>';
		}

		return $html . '</div>';
	}

	/**
	 * Inline icon. Decorative by default; labels live next to it in text.
	 *
	 * @param string $name Icon name.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'arrow-right' => '<path d="M4 10h12M11 5l5 5-5 5" />',
			'arrow-left'  => '<path d="M16 10H4M9 5l-5 5 5 5" />',
			'play'        => '<path d="M6 4l10 6-10 6z" />',
			'search'      => '<circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5L18 18" />',
			'copy'        => '<rect x="7" y="7" width="10" height="10" rx="2" /><path d="M13 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />',
			'check'       => '<path d="M4 10.5l4 4L16 6" />',
			'clock'       => '<circle cx="10" cy="10" r="7" /><path d="M10 6v4l3 2" />',
		);

		$path = $paths[ $name ] ?? $paths['arrow-right'];

		return '<svg class="wppl-icon" viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * Render the first active theme sidebar, if the theme has one.
	 *
	 * @return string
	 */
	public static function theme_sidebar(): string {
		if ( ! function_exists( 'is_active_sidebar' ) || ! function_exists( 'dynamic_sidebar' ) ) {
			return '';
		}

		$candidates = array( 'sidebar-1', 'sidebar-2', 'td-default', 'sidebar', 'primary-sidebar', 'right-sidebar', 'sidebar-right' );

		if ( function_exists( 'apply_filters' ) ) {
			$candidates = (array) apply_filters( 'wordpress_playlists_sidebar_ids', $candidates );
		}

		foreach ( array_unique( array_filter( array_map( 'strval', $candidates ) ) ) as $sidebar_id ) {
			if ( ! is_active_sidebar( $sidebar_id ) ) {
				continue;
			}

			ob_start();
			dynamic_sidebar( $sidebar_id );

			return (string) ob_get_clean();
		}

		return '';
	}

	/**
	 * Previous/next pagination with a count, never a wall of page numbers.
	 *
	 * @param int    $current Current page.
	 * @param int    $total   Total pages.
	 * @param string $base    URL base containing %#% for the page number.
	 * @param string $summary Human-readable range, e.g. "Broadcasts 1–12 of 232".
	 */
	public static function pagination( int $current, int $total, string $base, string $summary = '' ): string {
		if ( $total <= 1 ) {
			return '';
		}

		$current = max( 1, min( $total, $current ) );

		$page_url = static function ( int $page ) use ( $base ): string {
			return 1 === $page ? str_replace( 'page/%#%/', '', $base ) : str_replace( '%#%', (string) $page, $base );
		};

		$html  = '<nav class="wppl-pager" aria-label="' . esc_attr__( 'Pagination', 'wordpress-playlists' ) . '">';

		if ( '' !== $summary ) {
			$html .= '<p class="wppl-pager-summary">' . esc_html( $summary ) . '</p>';
		}

		$html .= '<div class="wppl-pager-steps">';

		if ( $current > 1 ) {
			$html .= '<a class="wppl-pager-step" href="' . esc_url( $page_url( $current - 1 ) ) . '" rel="prev">' . self::icon( 'arrow-left' ) . '<span>' . esc_html__( 'Newer broadcasts', 'wordpress-playlists' ) . '</span></a>';
		} else {
			$html .= '<span class="wppl-pager-step is-disabled">' . self::icon( 'arrow-left' ) . '<span>' . esc_html__( 'Newer broadcasts', 'wordpress-playlists' ) . '</span></span>';
		}

		if ( $current < $total ) {
			$html .= '<a class="wppl-pager-step" href="' . esc_url( $page_url( $current + 1 ) ) . '" rel="next"><span>' . esc_html__( 'Older broadcasts', 'wordpress-playlists' ) . '</span>' . self::icon( 'arrow-right' ) . '</a>';
		} else {
			$html .= '<span class="wppl-pager-step is-disabled"><span>' . esc_html__( 'Older broadcasts', 'wordpress-playlists' ) . '</span>' . self::icon( 'arrow-right' ) . '</span>';
		}

		return $html . '</div></nav>';
	}

	/**
	 * Copy-link button. Hidden until JavaScript enhances it, so the page never
	 * shows a dead control when scripting is unavailable.
	 *
	 * @param string $url Page URL.
	 */
	public static function copy_link( string $url ): string {
		return '<button type="button" class="wppl-copy" data-wppl-copy="' . esc_url( $url ) . '" hidden>' . self::icon( 'copy' ) . '<span data-wppl-copy-label>' . esc_html__( 'Copy link', 'wordpress-playlists' ) . '</span><span class="wppl-sr" data-wppl-copy-status aria-live="polite"></span></button>';
	}
}

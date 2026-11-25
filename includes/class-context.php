<?php
/**
 * Context
 *
 * Resolves the display configuration into URLs and small cached structures
 * (months, artist letters). Keeps route, view and SEO code free of repeated
 * option and page lookups.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Context {

	/** Static settings cache for the current request. */
	private static ?array $settings = null;

	/** Static hub page cache. */
	private static ?int $hub_page = null;

	/** Static archive page cache. */
	private static ?int $archive_page = null;

	/**
	 * Merged settings.
	 */
	public static function settings(): array {
		if ( null === self::$settings ) {
			self::$settings = Settings::get();
		}

		return self::$settings;
	}

	/**
	 * Forget the per-request settings cache.
	 */
	public static function flush(): void {
		self::$settings    = null;
		self::$hub_page    = null;
		self::$archive_page = null;
	}

	/**
	 * One setting with a default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function setting( string $key, $default = null ) {
		$settings = self::settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Whether the designed front end is enabled on this site.
	 */
	public static function display_enabled(): bool {
		return ! empty( self::setting( 'display_enabled', true ) );
	}

	/**
	 * The configured accent colour.
	 */
	public static function accent(): string {
		return Palette::sanitize( (string) self::setting( 'accent_color', Palette::DEFAULT_ACCENT ) );
	}

	/**
	 * Rewrite base for a single artist.
	 */
	public static function artist_base(): string {
		$base = sanitize_title( (string) self::setting( 'artist_base', 'artiest' ) );

		return '' === $base ? 'artiest' : $base;
	}

	/**
	 * Rewrite base for the artist index and its letters.
	 */
	public static function artists_index_base(): string {
		$base = sanitize_title( (string) self::setting( 'artists_index_base', 'artiesten' ) );

		if ( '' === $base ) {
			$base = 'artiesten';
		}

		if ( $base === self::artist_base() ) {
			$base .= '-overzicht';
		}

		return $base;
	}

	/**
	 * Old hub paths that redirect to the current hub.
	 *
	 * @return array<int,string>
	 */
	public static function legacy_hub_paths(): array {
		$raw   = (string) self::setting( 'legacy_hub_paths', '' );
		$paths = array();

		foreach ( preg_split( '/[\s,]+/', $raw ) ?: array() as $path ) {
			$path = strtolower( trim( $path, "/ \t\n\r\0\x0B" ) );
			$path = preg_replace( '#[^a-z0-9\-/]#', '', $path );

			if ( '' !== $path ) {
				$paths[] = $path;
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Playlists shown per page on an artist archive.
	 */
	public static function per_page(): int {
		$per_page = (int) self::setting( 'artist_playlists_per_page', 12 );

		return max( 1, min( 50, $per_page ) );
	}

	/**
	 * The page that hosts the hub shortcode.
	 */
	public static function hub_page_id(): int {
		if ( null !== self::$hub_page ) {
			return self::$hub_page;
		}

		$configured = absint( self::setting( 'hub_page_id', 0 ) );

		if ( $configured && 'publish' === get_post_status( $configured ) ) {
			self::$hub_page = $configured;
			return self::$hub_page;
		}

		$detected = absint( get_option( 'wordpress_playlists_hub_page', 0 ) );

		if ( $detected && 'publish' === get_post_status( $detected ) && has_shortcode( (string) get_post_field( 'post_content', $detected ), 'wordpress_playlists' ) ) {
			self::$hub_page = $detected;
			return self::$hub_page;
		}

		global $wpdb;

		$candidates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'page'
				   AND post_status = 'publish'
				   AND post_content LIKE %s
				 ORDER BY ID ASC
				 LIMIT 20",
				'%' . $wpdb->esc_like( '[wordpress_playlists' ) . '%'
			)
		);

		foreach ( (array) $candidates as $candidate ) {
			$candidate = (int) $candidate;

			if ( has_shortcode( (string) get_post_field( 'post_content', $candidate ), 'wordpress_playlists' ) ) {
				update_option( 'wordpress_playlists_hub_page', $candidate, false );
				self::$hub_page = $candidate;
				return self::$hub_page;
			}
		}

		self::$hub_page = 0;

		return 0;
	}

	/**
	 * Forget the detected hub page (after a page is saved or deleted).
	 */
	public static function forget_hub_page(): void {
		delete_option( 'wordpress_playlists_hub_page' );
		self::$hub_page = null;
	}

	/**
	 * Hub page URL, or the site home as a last resort.
	 */
	public static function hub_url(): string {
		$page_id = self::hub_page_id();

		if ( $page_id ) {
			$url = get_permalink( $page_id );

			if ( $url ) {
				return (string) $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Month page URL.
	 */
	public static function month_url( int $year, int $month ): string {
		if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 || ! self::hub_page_id() ) {
			return '';
		}

		return self::hub_url() . sprintf( '%04d/%02d/', $year, $month );
	}

	/**
	 * Month page URL for the broadcast date of a playlist.
	 */
	public static function month_url_for_post( int $post_id ): string {
		$date = (string) get_post_field( 'post_date', $post_id );

		if ( ! preg_match( '/^(\d{4})-(\d{2})/', $date, $matches ) ) {
			return '';
		}

		return self::month_url( (int) $matches[1], (int) $matches[2] );
	}

	/**
	 * Artists index URL, optionally for one letter.
	 */
	public static function artists_index_url( string $letter = '' ): string {
		$base = self::artists_index_base();
		$path = $base . '/';

		if ( '' !== $letter ) {
			$letter = strtolower( preg_replace( '/[^a-z0-9\-]/', '', $letter ) );

			if ( '' !== $letter ) {
				$path .= $letter . '/';
			}
		}

		return home_url( '/' . $path );
	}

	/**
	 * Artist archive URL.
	 */
	public static function artist_url( int $term_id ): string {
		$url = get_term_link( $term_id, Taxonomy::NAME );

		return is_wp_error( $url ) ? '' : (string) $url;
	}

	/**
	 * The page hosting the Broadcast Archive shortcode, if any.
	 */
	public static function archive_page_id(): int {
		if ( null !== self::$archive_page ) {
			return self::$archive_page;
		}

		$configured = absint( self::setting( 'archive_page_id', 0 ) );

		if ( $configured && 'publish' === get_post_status( $configured ) ) {
			self::$archive_page = $configured;
			return self::$archive_page;
		}

		$detected = absint( get_option( 'wordpress_playlists_archive_page', 0 ) );

		if ( $detected && 'publish' === get_post_status( $detected ) ) {
			self::$archive_page = $detected;
			return self::$archive_page;
		}

		global $wpdb;

		$candidates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'page'
				   AND post_status = 'publish'
				   AND post_content LIKE %s
				 ORDER BY ID ASC
				 LIMIT 20",
				'%' . $wpdb->esc_like( '[broadcast_archive' ) . '%'
			)
		);

		foreach ( (array) $candidates as $candidate ) {
			$content = (string) get_post_field( 'post_content', (int) $candidate );

			if ( has_shortcode( $content, 'broadcast_archive' ) ) {
				update_option( 'wordpress_playlists_archive_page', (int) $candidate, false );
				self::$archive_page = (int) $candidate;
				return self::$archive_page;
			}
		}

		self::$archive_page = 0;

		return 0;
	}

	/**
	 * Archive page URL.
	 */
	public static function archive_url(): string {
		$page_id = self::archive_page_id();

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? (string) $url : '';
	}

	/**
	 * Forget the detected archive page.
	 */
	public static function forget_archive_page(): void {
		delete_option( 'wordpress_playlists_archive_page' );
		self::$archive_page = null;
	}

	/**
	 * Months that contain published playlists, newest first.
	 *
	 * @return array<int,array{year:int,month:int,count:int,label:string,url:string}>
	 */
	public static function months(): array {
		$months = Cache::remember_raw(
			'playlist_months',
			15 * MINUTE_IN_SECONDS,
			static function (): array {
				global $wpdb;

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT YEAR(post_date) AS y, MONTH(post_date) AS m, COUNT(*) AS c
						 FROM {$wpdb->posts}
						 WHERE post_type = %s AND post_status = 'publish'
						 GROUP BY y, m
						 ORDER BY y DESC, m DESC",
						'playlist'
					)
				);

				$months = array();

				foreach ( (array) $rows as $row ) {
					$year  = (int) $row->y;
					$month = (int) $row->m;

					$months[] = array(
						'year'  => $year,
						'month' => $month,
						'count' => (int) $row->c,
						'label' => date_i18n( 'F Y', mktime( 12, 0, 0, $month, 1, $year ) ),
						'url'   => Context::month_url( $year, $month ),
					);
				}

				return $months;
			}
		);

		return is_array( $months ) ? $months : array();
	}

	/**
	 * Existing month entry for a year/month pair, or null.
	 *
	 * @return array{year:int,month:int,count:int,label:string,url:string}|null
	 */
	public static function month_entry( int $year, int $month ): ?array {
		foreach ( self::months() as $entry ) {
			if ( (int) $entry['year'] === $year && (int) $entry['month'] === $month ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * The month before and after a given month, when they contain playlists.
	 *
	 * @return array{prev:array|null,next:array|null}
	 */
	public static function month_neighbours( int $year, int $month ): array {
		$months  = self::months();
		$current = sprintf( '%04d-%02d', $year, $month );
		$prev    = null;
		$next    = null;

		foreach ( $months as $entry ) {
			$key = sprintf( '%04d-%02d', (int) $entry['year'], (int) $entry['month'] );

			if ( $key < $current ) {
				$prev = $entry;
			} elseif ( $key > $current && null === $next ) {
				$next = $entry;
			}
		}

		return array(
			'prev' => $prev,
			'next' => $next,
		);
	}

	/**
	 * Artists grouped by their normalised first character.
	 *
	 * @return array<string,array<int,array{id:int,name:string,plays:int,url:string,local:bool}>>
	 */
	public static function artist_letters(): array {
		$groups = Cache::remember_raw(
			'artist_letters',
			HOUR_IN_SECONDS,
			static function (): array {
				$terms = get_terms(
					array(
						'taxonomy'   => Taxonomy::NAME,
						'hide_empty' => true,
						'number'     => 0,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);

				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					return array();
				}

				update_termmeta_cache( wp_list_pluck( $terms, 'term_id' ) );

				$groups = array();

				foreach ( $terms as $term ) {
					$plays = (int) get_term_meta( $term->term_id, Artist_Index::META_PLAY_COUNT, true );

					if ( $plays < 1 ) {
						continue;
					}

					$letter = self::letter_for( (string) $term->name );
					$url    = get_term_link( $term );

					$groups[ $letter ][] = array(
						'id'    => (int) $term->term_id,
						'name'  => (string) $term->name,
						'plays' => $plays,
						'url'   => is_wp_error( $url ) ? '' : (string) $url,
						'local' => (bool) get_term_meta( $term->term_id, Taxonomy::META_LOCAL, true ),
					);
				}

				ksort( $groups );

				return $groups;
			}
		);

		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * The letter bucket for a name.
	 */
	public static function letter_for( string $name ): string {
		$name = remove_accents( $name );
		$name = preg_replace( '/^\s+/u', '', $name );

		if ( '' === $name ) {
			return '0-9';
		}

		$first = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 );
		$first = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first, 'UTF-8' ) : strtoupper( $first );

		if ( preg_match( '/^[A-Z]$/', $first ) ) {
			return strtolower( $first );
		}

		return '0-9';
	}

	/**
	 * Local artists, most-played first.
	 *
	 * @param int $limit Maximum number of artists.
	 * @return array<int,array{id:int,name:string,plays:int,url:string,image:string,bio:string}>
	 */
	public static function local_artists( int $limit = 6 ): array {
		$limit = max( 1, min( 24, $limit ) );
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => true,
				'number'     => 200,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'meta_query' => array(
					array(
						'key'     => Taxonomy::META_LOCAL,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		update_termmeta_cache( wp_list_pluck( $terms, 'term_id' ) );

		$artists = array();

		foreach ( $terms as $term ) {
			$plays = (int) get_term_meta( $term->term_id, Artist_Index::META_PLAY_COUNT, true );

			if ( $plays < 1 ) {
				continue;
			}

			$url     = get_term_link( $term );
			$image_id = (int) get_term_meta( $term->term_id, Taxonomy::META_IMAGE, true );
			$image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';

			$artists[] = array(
				'id'    => (int) $term->term_id,
				'name'  => (string) $term->name,
				'plays' => $plays,
				'url'   => is_wp_error( $url ) ? '' : (string) $url,
				'image' => $image,
				'bio'   => (string) get_term_meta( $term->term_id, Taxonomy::META_BIO, true ),
			);
		}

		usort(
			$artists,
			static function ( $a, $b ) {
				return $b['plays'] <=> $a['plays'];
			}
		);

		return array_slice( $artists, 0, $limit );
	}

	/**
	 * Total counts for the artist index.
	 *
	 * @return array{artists:int,local:int,plays:int}
	 */
	public static function artist_totals(): array {
		$groups = self::artist_letters();
		$total  = 0;
		$local  = 0;
		$plays  = 0;

		foreach ( $groups as $artists ) {
			foreach ( $artists as $artist ) {
				$total++;
				$plays += (int) $artist['plays'];

				if ( ! empty( $artist['local'] ) ) {
					$local++;
				}
			}
		}

		return array(
			'artists' => $total,
			'local'   => $local,
			'plays'   => $plays,
		);
	}
}

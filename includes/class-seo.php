<?php
/**
 * SEO
 *
 * Titles, descriptions, canonicals, robots, structured data, breadcrumbs and
 * a month sitemap. Integrated with Yoast when it is active, with a plain
 * WordPress fallback otherwise.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class SEO {

	/** Cached request context. */
	private static ?array $context = null;

	/** Whether the context was resolved. */
	private static bool $resolved = false;

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_filter( 'wpseo_title', array( $this, 'filter_title' ) );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_description' ) );
		add_filter( 'wpseo_canonical', array( $this, 'filter_canonical' ) );
		add_filter( 'wpseo_robots', array( $this, 'filter_robots' ) );
		add_filter( 'wpseo_opengraph_title', array( $this, 'filter_og_title' ) );
		add_filter( 'wpseo_opengraph_description', array( $this, 'filter_description' ) );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'filter_description' ) );
		add_filter( 'wpseo_twitter_description', array( $this, 'filter_description' ) );
		add_filter( 'wpseo_opengraph_image', array( $this, 'filter_og_image' ) );
		add_filter( 'wpseo_twitter_image', array( $this, 'filter_og_image' ) );
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'filter_breadcrumbs' ), 99 );
		add_filter( 'wpseo_schema_graph', array( $this, 'filter_schema' ), 10, 2 );
		add_filter( 'wpseo_sitemap_index_links', array( $this, 'filter_sitemap_index' ) );
		add_filter( 'wpseo_sitemap_exclude_taxonomy', array( $this, 'filter_sitemap_taxonomy' ), 10, 2 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ) );
		add_action( 'wp_head', array( $this, 'print_head' ), 1 );
		add_action( 'template_redirect', array( $this, 'sitemap' ), 0 );
	}

	/**
	 * Resolve the current presentation context.
	 *
	 * @return array|null
	 */
	private static function context(): ?array {
		if ( self::$resolved ) {
			return self::$context;
		}

		self::$resolved = true;
		self::$context  = null;

		if ( ! Context::display_enabled() ) {
			return null;
		}

		if ( is_singular( 'playlist' ) ) {
			$post = get_queried_object();

			if ( $post instanceof \WP_Post ) {
				self::$context = array(
					'type' => 'playlist',
					'post' => $post,
				);

				return self::$context;
			}
		}

		if ( is_tax( Taxonomy::NAME ) ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				self::$context = array(
					'type' => 'artist',
					'term' => $term,
				);

				return self::$context;
			}
		}

		$is_index  = (bool) get_query_var( 'wppl_artists' );
		$letter    = (string) get_query_var( 'wppl_letter' );
		$query     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $is_index || '' !== $letter ) {
			if ( '' !== $query ) {
				self::$context = array(
					'type'  => 'search',
					'query' => $query,
				);

				return self::$context;
			}

			if ( '' !== $letter ) {
				self::$context = array(
					'type'   => 'letter',
					'letter' => strtolower( $letter ),
				);

				return self::$context;
			}

			self::$context = array( 'type' => 'index' );

			return self::$context;
		}

		$month = (string) get_query_var( 'wppl_month' );

		if ( '' !== $month ) {
			$parts = Month_View::parse( $month );

			if ( null !== $parts ) {
				self::$context = array(
					'type'  => 'month',
					'year'  => (int) $parts['year'],
					'month' => (int) $parts['month'],
				);

				return self::$context;
			}
		}

		return null;
	}

	/**
	 * Page title for the current context.
	 */
	public static function title(): string {
		$context = self::context();

		if ( null === $context ) {
			return '';
		}

		switch ( $context['type'] ) {
			case 'playlist':
				$post = $context['post'];
				$show = Playlist_Model::show( $post );

				if ( '' === (string) $show['name'] ) {
					/* translators: %s: broadcast date. */
					return sprintf( __( 'Playlist %s', 'wordpress-playlists' ), mysql2date( 'j F Y', $post->post_date ) );
				}

				/* translators: 1: broadcast date, 2: programme name. */
				return sprintf( __( 'Playlist %1$s – %2$s', 'wordpress-playlists' ), mysql2date( 'j F Y', $post->post_date ), $show['name'] );

			case 'artist':
				$term  = $context['term'];
				$plays = (int) get_term_meta( $term->term_id, Artist_Index::META_PLAY_COUNT, true );

				/* translators: 1: artist name, 2: number of plays. */
				return sprintf( __( '%1$s: %2$s plays', 'wordpress-playlists' ), $term->name, number_format_i18n( $plays ) );

			case 'month':
				$label = date_i18n( 'F Y', mktime( 12, 0, 0, (int) $context['month'], 1, (int) $context['year'] ) );

				/* translators: %s: month and year. */
				return sprintf( __( 'Played tracks %s', 'wordpress-playlists' ), $label );

			case 'index':
				return __( 'Artists – everyone heard on this station', 'wordpress-playlists' );

			case 'letter':
				$label = '0-9' === $context['letter'] ? '0–9' : strtoupper( (string) $context['letter'] );

				/* translators: %s: letter. */
				return sprintf( __( 'Artists starting with %s', 'wordpress-playlists' ), $label );

			case 'search':
				/* translators: %s: search query. */
				return sprintf( __( 'Search results for “%s”', 'wordpress-playlists' ), (string) $context['query'] );
		}

		return '';
	}

	/**
	 * Meta description for the current context.
	 */
	public static function description(): string {
		$context = self::context();

		if ( null === $context ) {
			return '';
		}

		switch ( $context['type'] ) {
			case 'playlist':
				$post    = $context['post'];
				$summary = get_post_meta( $post->ID, Artist_Index::META_SUMMARY, true );

				if ( ! is_array( $summary ) ) {
					$tracks  = get_post_meta( $post->ID, 'playlist', true );
					$summary = Artist_Index::build_summary( is_array( $tracks ) ? $tracks : array() );
				}

				$show = Playlist_Model::show( $post );
				$list = array();

				foreach ( (array) ( $summary['preview'] ?? array() ) as $preview ) {
					$artist = trim( (string) ( $preview['artist'] ?? '' ) );
					$title  = trim( (string) ( $preview['title'] ?? '' ) );

					if ( '' !== $artist ) {
						$list[] = '' !== $title ? $artist . ' – ' . $title : $artist;
					}
				}

				return Renderer::excerpt(
					sprintf(
						/* translators: 1: track count, 2: programme, 3: date, 4: examples. */
						__( 'All %1$s tracks from the broadcast of %3$s on %2$s: %4$s.', 'wordpress-playlists' ),
						number_format_i18n( (int) ( $summary['count'] ?? 0 ) ),
						$show['name'],
						mysql2date( 'j F Y', $post->post_date ),
						implode( ', ', $list )
					)
				);

			case 'artist':
				$term  = $context['term'];
				$plays = (int) get_term_meta( $term->term_id, Artist_Index::META_PLAY_COUNT, true );
				$tracks = Artist_Index::tracks_for_term( (int) $term->term_id );
				$last   = (string) get_term_meta( $term->term_id, Artist_Index::META_LAST_PLAYED, true );

				return Renderer::excerpt(
					sprintf(
						/* translators: 1: artist, 2: plays, 3: number of tracks, 4: last date. */
						__( '%1$s has been played %2$s times on this station: %3$s tracks, last heard on %4$s.', 'wordpress-playlists' ),
						$term->name,
						number_format_i18n( $plays ),
						number_format_i18n( count( $tracks ) ),
						'' !== $last ? mysql2date( 'j F Y', $last ) : __( 'unknown', 'wordpress-playlists' )
					)
				);

			case 'month':
				$label = date_i18n( 'F Y', mktime( 12, 0, 0, (int) $context['month'], 1, (int) $context['year'] ) );
				$entry = Context::month_entry( (int) $context['year'], (int) $context['month'] );

				return Renderer::excerpt(
					sprintf(
						/* translators: 1: number of broadcasts, 2: month. */
						__( 'All %1$s broadcasts from %2$s, grouped per day and programme, with every played track.', 'wordpress-playlists' ),
						number_format_i18n( (int) ( $entry['count'] ?? 0 ) ),
						$label
					)
				);

			case 'index':
				return __( 'Every artist heard on this station, from A to Z, with play counts and links to the broadcasts they appeared in.', 'wordpress-playlists' );

			case 'letter':
				$label = '0-9' === $context['letter'] ? '0–9' : strtoupper( (string) $context['letter'] );

				/* translators: %s: letter. */
				return sprintf( __( 'Artists starting with %s, with play counts and links to every broadcast.', 'wordpress-playlists' ), $label );

			case 'search':
				/* translators: %s: search query. */
				return sprintf( __( 'Search results for “%s”: artists and tracks heard on this station.', 'wordpress-playlists' ), (string) $context['query'] );
		}

		return '';
	}

	/**
	 * Yoast title filter.
	 *
	 * @param string $title Current title.
	 */
	public function filter_title( $title ): string {
		$context = self::context();

		if ( null === $context || $this->has_custom_title( $context ) ) {
			return (string) $title;
		}

		$title = self::title();

		if ( '' === $title ) {
			return (string) $title;
		}

		$separator = (string) apply_filters( 'document_title_separator', '–' );
		$site      = (string) get_bloginfo( 'name' );

		return '' !== $site ? $title . ' ' . $separator . ' ' . $site : $title;
	}

	/**
	 * Open Graph title without the site suffix.
	 *
	 * @param string $title Current title.
	 */
	public function filter_og_title( $title ): string {
		$context = self::context();

		if ( null === $context || $this->has_custom_title( $context ) ) {
			return (string) $title;
		}

		$own = self::title();

		return '' !== $own ? $own : (string) $title;
	}

	/**
	 * Yoast description filter.
	 *
	 * @param string $description Current description.
	 */
	public function filter_description( $description ): string {
		$original = (string) $description;
		$context  = self::context();

		if ( null === $context || $this->has_custom_description( $context ) ) {
			return $original;
		}

		$description = self::description();

		return '' !== $description ? $description : $original;
	}

	/**
	 * Use the artist image as the social image.
	 *
	 * Yoast uses the site logo (or nothing) on a taxonomy archive. The artist
	 * image is the meaningful image for a shared artist page, so use it unless
	 * an editor set a Yoast social image explicitly.
	 *
	 * @param string $image Current image URL.
	 */
	public function filter_og_image( $image ): string {
		$context = self::context();

		if ( null === $context || 'artist' !== $context['type'] ) {
			return (string) $image;
		}

		$term = $context['term'];

		foreach ( array( 'wpseo_opengraph-image-id', 'wpseo_opengraph-image' ) as $key ) {
			if ( '' !== trim( (string) get_term_meta( $term->term_id, $key, true ) ) ) {
				return (string) $image;
			}
		}

		$id  = (int) get_term_meta( $term->term_id, Taxonomy::META_IMAGE, true );
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'large' ) : '';

		return '' !== $url ? $url : (string) $image;
	}

	/**
	 * Yoast canonical filter.
	 *
	 * @param string $canonical Current canonical.
	 */
	public function filter_canonical( $canonical ): string {
		$context = self::context();

		if ( null === $context ) {
			return (string) $canonical;
		}

		switch ( $context['type'] ) {
			case 'index':
				return Context::artists_index_url();

			case 'letter':
				return Context::artists_index_url( (string) $context['letter'] );

			case 'month':
				return Context::month_url( (int) $context['year'], (int) $context['month'] );

			case 'search':
				return Context::artists_index_url();
		}

		return (string) $canonical;
	}

	/**
	 * Yoast robots filter.
	 *
	 * @param string $robots Current robots value.
	 */
	public function filter_robots( $robots ): string {
		$context = self::context();

		if ( null === $context ) {
			return (string) $robots;
		}

		if ( 'search' === $context['type'] ) {
			return 'noindex, follow';
		}

		// Respect an explicit editorial noindex on the playlist or artist.
		if ( 'playlist' === $context['type'] ) {
			$noindex = (string) get_post_meta( $context['post']->ID, '_yoast_wpseo_meta-robots-noindex', true );

			if ( '1' === $noindex ) {
				return (string) $robots;
			}
		}

		if ( 'artist' === $context['type'] ) {
			$noindex = (string) get_term_meta( $context['term']->term_id, 'wpseo_noindex', true );

			if ( 'noindex' === $noindex ) {
				return (string) $robots;
			}
		}

		return 'index, follow';
	}

	/**
	 * Yoast breadcrumb links.
	 *
	 * @param array $crumbs Current crumbs.
	 * @return array
	 */
	public function filter_breadcrumbs( $crumbs ): array {
		$context = self::context();

		if ( null === $context ) {
			return (array) $crumbs;
		}

		$crumbs = array(
			array(
				'text' => __( 'Home', 'wordpress-playlists' ),
				'url'  => home_url( '/' ),
			),
			array(
				'text' => __( 'Playlists', 'wordpress-playlists' ),
				'url'  => Context::hub_url(),
			),
		);

		if ( in_array( $context['type'], array( 'artist', 'index', 'letter', 'search' ), true ) ) {
			$crumbs[] = array(
				'text' => __( 'Artists', 'wordpress-playlists' ),
				'url'  => Context::artists_index_url(),
			);
		}

		switch ( $context['type'] ) {
			case 'playlist':
				$crumbs[] = array( 'text' => (string) $context['post']->post_title );
				break;

			case 'artist':
				$crumbs[] = array( 'text' => (string) $context['term']->name );
				break;

			case 'month':
				$crumbs[] = array(
					'text' => date_i18n( 'F Y', mktime( 12, 0, 0, (int) $context['month'], 1, (int) $context['year'] ) ),
				);
				break;

			case 'letter':
				$crumbs[] = array( 'text' => '0-9' === $context['letter'] ? '0–9' : strtoupper( (string) $context['letter'] ) );
				break;

			case 'search':
				$crumbs[] = array( 'text' => __( 'Search', 'wordpress-playlists' ) );
				break;
		}

		return $crumbs;
	}

	/**
	 * Add structured data to Yoast's graph.
	 *
	 * @param array $graph   Graph nodes.
	 * @param mixed $context Schema context.
	 * @return array
	 */
	public function filter_schema( $graph, $context = null ): array {
		$graph = is_array( $graph ) ? $graph : array();
		$nodes = $this->schema_nodes();

		return array_merge( $graph, $nodes );
	}

	/**
	 * Structured data nodes for the current context.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function schema_nodes(): array {
		$context = self::context();

		if ( null === $context || 'search' === $context['type'] ) {
			return array();
		}

		switch ( $context['type'] ) {
			case 'playlist':
				return array( $this->playlist_schema( $context['post'] ) );

			case 'artist':
				return $this->artist_schema( $context['term'] );

			case 'month':
				return array( $this->month_schema( (int) $context['year'], (int) $context['month'] ) );
		}

		return array();
	}

	/**
	 * MusicPlaylist node.
	 *
	 * @param \WP_Post $post Playlist post.
	 * @return array<string,mixed>
	 */
	private function playlist_schema( \WP_Post $post ): array {
		$url     = (string) get_permalink( $post );
		$show    = Playlist_Model::show( $post );
		$tracks  = array();
		$total   = 0;
		$entries = get_post_meta( $post->ID, 'playlist', true );

		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) || ! Artist_Index::is_played( $entry ) ) {
					continue;
				}

				$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );
				$title  = trim( (string) ( $entry['playlist_entry_song'] ?? '' ) );

				if ( '' === $artist || '' === $title ) {
					continue;
				}

				$total++;

				$artist_node = array(
					'@type' => 'MusicGroup',
					'name'  => $artist,
				);

				$term_id = Artist_Index::find_term_id( $artist );

				if ( $term_id ) {
					$artist_url = Context::artist_url( $term_id );

					if ( '' !== $artist_url ) {
						$artist_node['@id'] = $artist_url . '#artist';
						$artist_node['url'] = $artist_url;
					}
				}

				$recording = array(
					'@type'    => 'MusicRecording',
					'name'     => $title,
					'byArtist' => $artist_node,
				);

				$duration = Renderer::duration_iso( $entry['playlist_entry_minutes'] ?? '', $entry['playlist_entry_seconds'] ?? '' );

				if ( '' !== $duration ) {
					$recording['duration'] = $duration;
				}

				$tracks[] = $recording;

				if ( count( $tracks ) >= 100 ) {
					break;
				}
			}
		}

		$node = array(
			'@type'         => 'MusicPlaylist',
			'@id'           => $url . '#musicplaylist',
			'url'           => $url,
			'name'          => wp_strip_all_tags( (string) $post->post_title ),
			'datePublished' => $this->iso_date( (string) $post->post_date ),
			'numTracks'     => $total,
		);

		if ( ! empty( $tracks ) ) {
			$node['track'] = $tracks;
		}

		if ( '' !== $show['name'] ) {
			$node['partOfSeries'] = array(
				'@type' => 'RadioSeries',
				'name'  => $show['name'],
			);

			if ( '' !== $show['url'] ) {
				$node['partOfSeries']['url'] = $show['url'];
			}
		}

		return $node;
	}

	/**
	 * MusicGroup and track list nodes.
	 *
	 * @param \WP_Term $term Artist term.
	 * @return array<int,array<string,mixed>>
	 */
	private function artist_schema( \WP_Term $term ): array {
		$url   = Context::artist_url( (int) $term->term_id );
		$image = (int) get_term_meta( $term->term_id, Taxonomy::META_IMAGE, true );
		$bio   = (string) get_term_meta( $term->term_id, Taxonomy::META_BIO, true );

		$artist = array(
			'@type' => 'MusicGroup',
			'@id'   => $url . '#artist',
			'name'  => wp_strip_all_tags( (string) $term->name ),
			'url'   => $url,
		);

		if ( $image ) {
			$image_url = wp_get_attachment_image_url( $image, 'large' );

			if ( $image_url ) {
				$artist['image'] = $image_url;
			}
		}

		if ( '' !== $bio ) {
			$artist['description'] = Renderer::excerpt( wp_strip_all_tags( $bio ), 300 );
		}

		$same_as = array();

		foreach ( array( Taxonomy::META_WEBSITE, Taxonomy::META_INSTAGRAM, Taxonomy::META_SPOTIFY, Taxonomy::META_YOUTUBE ) as $key ) {
			$link = trim( (string) get_term_meta( $term->term_id, $key, true ) );

			if ( '' !== $link && preg_match( '#^https?://#i', $link ) ) {
				$same_as[] = $link;
			}
		}

		if ( ! empty( $same_as ) ) {
			$artist['sameAs'] = $same_as;
		}

		$tracks       = Artist_Index::tracks_for_term( (int) $term->term_id );
		$total_tracks = count( $tracks );

		uasort(
			$tracks,
			static function ( $a, $b ) {
				return (int) $b['plays'] <=> (int) $a['plays'];
			}
		);

		$items = array();
		$position = 0;

		foreach ( $tracks as $track ) {
			$position++;

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'item'     => array(
					'@type'    => 'MusicRecording',
					'name'     => wp_strip_all_tags( (string) $track['title'] ),
					'byArtist' => array(
						'@id' => $url . '#artist',
					),
				),
			);

			if ( $position >= 50 ) {
				break;
			}
		}

		$list = array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#tracks',
			'name'            => sprintf( /* translators: %s: artist name. */ __( 'Tracks played by %s', 'wordpress-playlists' ), $term->name ),
			'numberOfItems'   => $total_tracks,
			'itemListElement' => $items,
		);

		return array( $artist, $list );
	}

	/**
	 * Month ItemList node.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return array<string,mixed>
	 */
	private function month_schema( int $year, int $month ): array {
		$url  = Context::month_url( $year, $month );
		$from = sprintf( '%04d-%02d-01', $year, $month );
		$to   = gmdate( 'Y-m-t', strtotime( $from ) );
		$ids        = Playlist_Model::ids_between( $from, $to );
		$total      = count( $ids );
		$items      = array();
		$position   = 0;

		foreach ( $ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$position++;

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'url'      => (string) get_permalink( $post ),
				'name'     => wp_strip_all_tags( (string) $post->post_title ),
			);

			if ( $position >= 200 ) {
				break;
			}
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#playlists',
			'name'            => sprintf( /* translators: %s: month and year. */ __( 'Broadcasts from %s', 'wordpress-playlists' ), date_i18n( 'F Y', mktime( 12, 0, 0, $month, 1, $year ) ) ),
			'numberOfItems'   => $total,
			'itemListElement' => $items,
		);
	}

	/**
	 * Document title parts fallback.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_document_title( $parts ): array {
		$parts = is_array( $parts ) ? $parts : array();
		$title = self::title();

		if ( '' !== $title ) {
			$parts['title'] = $title;
		}

		return $parts;
	}

	/**
	 * Add the month and artist index sitemap to Yoast's index.
	 *
	 * @param array $links Sitemap index links.
	 * @return array
	 */
	public function filter_sitemap_index( $links ): array {
		$links = is_array( $links ) ? $links : array();

		if ( empty( Context::months() ) ) {
			return $links;
		}

		$links[] = array(
			'loc'     => home_url( '/wppl-maanden-sitemap.xml' ),
			'lastmod' => gmdate( 'c' ),
		);

		return $links;
	}

	/**
	 * Never exclude our taxonomy from the sitemap.
	 *
	 * @param bool   $exclude  Whether to exclude.
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	public function filter_sitemap_taxonomy( $exclude, $taxonomy ): bool {
		if ( Taxonomy::NAME === $taxonomy ) {
			return false;
		}

		return (bool) $exclude;
	}

	/**
	 * Output the extra sitemap.
	 */
	public function sitemap(): void {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path    = (string) wp_parse_url( $request, PHP_URL_PATH );

		if ( '/wppl-maanden-sitemap.xml' !== $path ) {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );

		$urls = array();

		foreach ( Context::months() as $month ) {
			$urls[] = (string) $month['url'];
		}

		$urls[] = Context::artists_index_url();
		$urls[] = Context::hub_url();

		foreach ( array_keys( Context::artist_letters() ) as $letter ) {
			$urls[] = Context::artists_index_url( (string) $letter );
		}

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( array_unique( $urls ) as $url ) {
			if ( '' === $url ) {
				continue;
			}

			echo "\t<url><loc>" . esc_url( $url ) . "</loc></url>\n";
		}

		echo '</urlset>' . "\n";
		exit;
	}

	/**
	 * Print meta tags and JSON-LD when Yoast is not active.
	 */
	public function print_head(): void {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		$context = self::context();

		if ( null === $context ) {
			return;
		}

		$description = self::description();

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		$canonical = $this->current_url();

		if ( '' !== $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		if ( 'search' === $context['type'] ) {
			echo '<meta name="robots" content="noindex, follow" />' . "\n";
		}

		$nodes = $this->schema_nodes();

		if ( empty( $nodes ) ) {
			return;
		}

		$graph = array_merge(
			array(
				array(
					'@type' => 'WebPage',
					'@id'   => $this->current_url() . '#webpage',
					'url'   => $this->current_url(),
					'name'  => wp_strip_all_tags( (string) wp_get_document_title() ),
				),
			),
			$nodes
		);

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		);

		if ( false === $json ) {
			return;
		}

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with HEX flags.
	}

	/**
	 * Current request URL without query arguments.
	 */
	private function current_url(): string {
		$context = self::context();

		if ( null === $context ) {
			return home_url( '/' );
		}

		switch ( $context['type'] ) {
			case 'playlist':
				return (string) get_permalink( $context['post'] );

			case 'artist':
				return Context::artist_url( (int) $context['term']->term_id );

			case 'month':
				return Context::month_url( (int) $context['year'], (int) $context['month'] );

			case 'index':
			case 'search':
				return Context::artists_index_url();

			case 'letter':
				return Context::artists_index_url( (string) $context['letter'] );
		}

		return home_url( '/' );
	}

	/**
	 * ISO 8601 timestamp for a stored local MySQL date.
	 *
	 * @param string $mysql MySQL datetime.
	 */
	private function iso_date( string $mysql ): string {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql, wp_timezone() );

		return $date ? $date->format( 'c' ) : '';
	}

	/**
	 * Whether the editor set a custom Yoast title.
	 *
	 * @param array $context Presentation context.
	 */
	private function has_custom_title( array $context ): bool {
		if ( 'playlist' === $context['type'] ) {
			return '' !== trim( (string) get_post_meta( $context['post']->ID, '_yoast_wpseo_title', true ) );
		}

		if ( 'artist' === $context['type'] ) {
			return '' !== trim( (string) get_term_meta( $context['term']->term_id, 'wpseo_title', true ) );
		}

		return false;
	}

	/**
	 * Whether the editor set a custom Yoast description.
	 *
	 * @param array $context Presentation context.
	 */
	private function has_custom_description( array $context ): bool {
		if ( 'playlist' === $context['type'] ) {
			return '' !== trim( (string) get_post_meta( $context['post']->ID, '_yoast_wpseo_metadesc', true ) );
		}

		if ( 'artist' === $context['type'] ) {
			return '' !== trim( (string) get_term_meta( $context['term']->term_id, 'wpseo_desc', true ) );
		}

		return false;
	}
}

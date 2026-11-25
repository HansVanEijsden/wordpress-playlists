<?php
/**
 * Artist Index
 *
 * Keeps the artist taxonomy, the per-artist play counters and the per-playlist
 * summaries in sync with the Radio Station playlist meta. The playlist meta
 * stays the single source of truth; everything here is derived and can be
 * rebuilt from it with the WP-CLI command.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Artist_Index {

	/** Artwork / playcount taxonomy. */
	public const TAXONOMY = Taxonomy::NAME;

	/** Stable, normalised artist key. */
	public const META_KEY = '_wppl_key';

	/** Number of plays for the artist. */
	public const META_PLAY_COUNT = '_wppl_play_count';

	/** Per-artist track summary: track key => array( title, plays ). */
	public const META_TRACKS = '_wppl_tracks';

	/** First broadcast date the artist was heard on. */
	public const META_FIRST_PLAYED = '_wppl_first_played';

	/** Most recent broadcast date the artist was heard on. */
	public const META_LAST_PLAYED = '_wppl_last_played';

	/** Per-playlist display summary. */
	public const META_SUMMARY = '_wppl_summary';

	/** Per-playlist index used to diff incremental updates. */
	public const META_INDEX = '_wppl_index';

	/** Option holding the last successful rebuild timestamp. */
	public const BUILT_OPTION = 'wordpress_playlists_index_built';

	/** In-request cache of artist key => term ID. */
	private static array $terms = array();

	/** Playlists whose save_post callback should be skipped (our own writes). */
	private static array $skip_save = array();

	/** Whether a rebuild is running. */
	private static bool $rebuilding = false;

	/** Whether term creation is suppressed (dry run). */
	private static bool $read_only = false;

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'save_post_playlist', array( __CLASS__, 'on_save' ), 99, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	/**
	 * Normalise a string into a stable identity key.
	 */
	public static function normalize_key( string $value ): string {
		$value = wp_strip_all_tags( $value );

		if ( function_exists( 'remove_accents' ) ) {
			$value = remove_accents( $value );
		}

		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
		$value = preg_replace( '/[\s\x{00A0}]+/u', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * Normalise a track title within an artist.
	 */
	public static function track_key( string $title ): string {
		return self::normalize_key( $title );
	}

	/**
	 * Whether a playlist entry counts as played. Legacy entries may omit the
	 * status field entirely.
	 */
	public static function is_played( array $entry ): bool {
		$status = (string) ( $entry['playlist_entry_status'] ?? 'played' );

		return '' === $status || 'played' === $status;
	}

	/**
	 * Find the artist term for a name without creating one.
	 */
	public static function find_term_id( string $name ): int {
		$key = self::normalize_key( $name );

		if ( '' === $key ) {
			return 0;
		}

		if ( isset( self::$terms[ $key ] ) ) {
			return (int) self::$terms[ $key ];
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => self::META_KEY,
						'value'   => $key,
						'compare' => '=',
					),
				),
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			self::$terms[ $key ] = (int) $terms[0];

			return (int) $terms[0];
		}

		return 0;
	}

	/**
	 * Resolve an artist term, creating it when it does not exist yet.
	 */
	public static function term_id_for_name( string $name, bool $create = true ): int {
		$name = trim( $name );
		$key  = self::normalize_key( $name );

		if ( '' === $key ) {
			return 0;
		}

		$existing = self::find_term_id( $name );

		if ( $existing || ! $create || self::$read_only ) {
			return $existing;
		}

		$slug = sanitize_title( $name );

		if ( '' === $slug ) {
			$slug = 'artist';
		}

		if ( term_exists( $slug, self::TAXONOMY ) ) {
			$term = get_term_by( 'slug', $slug, self::TAXONOMY );

			if ( $term instanceof \WP_Term ) {
				$term_key = (string) get_term_meta( $term->term_id, self::META_KEY, true );

				if ( $term_key === $key ) {
					self::$terms[ $key ] = (int) $term->term_id;

					return (int) $term->term_id;
				}
			}

			$slug .= '-' . substr( md5( $key ), 0, 5 );

			$suffix = 2;

			while ( term_exists( $slug, self::TAXONOMY ) && $suffix <= 5 ) {
				$slug = sanitize_title( $name ) . '-' . substr( md5( $key ), 0, 5 ) . '-' . $suffix;
				$suffix++;
			}
		}

		$inserted = wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );

		if ( is_wp_error( $inserted ) ) {
			$term = get_term_by( 'slug', $slug, self::TAXONOMY );

			if ( ! $term instanceof \WP_Term ) {
				Logger::log( (string) Context::setting( 'station_name', '' ), 'Could not create artist term for "' . $name . '": ' . $inserted->get_error_message() );

				return 0;
			}

			$term_id = (int) $term->term_id;
		} else {
			$term_id = (int) $inserted['term_id'];
		}

		update_term_meta( $term_id, self::META_KEY, $key );

		if ( '' === (string) get_term_meta( $term_id, self::META_PLAY_COUNT, true ) ) {
			update_term_meta( $term_id, self::META_PLAY_COUNT, 0 );
		}

		self::$terms[ $key ] = $term_id;

		Cache::forget( 'artist_letters' );

		/**
		 * Fires when a new artist term is created from playlist data.
		 *
		 * @param int    $term_id Term ID.
		 * @param string $name    Artist name.
		 */
		do_action( 'wordpress_playlists_artist_created', $term_id, $name );

		return $term_id;
	}

	/**
	 * Preload a batch of artist terms in one query.
	 *
	 * @param array<int,string> $names Artist names.
	 */
	public static function preload( array $names ): void {
		$keys = array();

		foreach ( $names as $name ) {
			$key = self::normalize_key( (string) $name );

			if ( '' !== $key && ! isset( self::$terms[ $key ] ) ) {
				$keys[ $key ] = $key;
			}
		}

		if ( empty( $keys ) ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 0,
				'fields'     => 'all',
				'meta_query' => array(
					array(
						'key'     => self::META_KEY,
						'value'   => array_values( $keys ),
						'compare' => 'IN',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$key = (string) get_term_meta( $term->term_id, self::META_KEY, true );

			if ( '' !== $key ) {
				self::$terms[ $key ] = (int) $term->term_id;
			}
		}
	}

	/**
	 * Ensure an artist is assigned to a playlist.
	 */
	private static function ensure_assigned( int $playlist_id, int $term_id ): void {
		if ( $term_id <= 0 ) {
			return;
		}

		$assigned = wp_get_object_terms( $playlist_id, self::TAXONOMY, array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $assigned ) && in_array( $term_id, array_map( 'intval', $assigned ), true ) ) {
			return;
		}

		wp_set_object_terms( $playlist_id, array( $term_id ), self::TAXONOMY, true );
	}

	/**
	 * Record one appended track.
	 *
	 * @param int   $playlist_id Playlist post ID.
	 * @param array $track       Playlist entry.
	 * @param array $all_tracks  Full playlist meta after the append.
	 */
	public static function record_track( int $playlist_id, array $track, array $all_tracks = array() ): void {
		$artist = trim( (string) ( $track['playlist_entry_artist'] ?? '' ) );
		$title  = trim( (string) ( $track['playlist_entry_song'] ?? '' ) );

		if ( '' === $artist || '' === $title ) {
			return;
		}

		if ( ! self::is_played( $track ) ) {
			return;
		}

		$term_id = self::term_id_for_name( $artist );

		if ( ! $term_id ) {
			return;
		}

		self::ensure_assigned( $playlist_id, $term_id );

		$track_key = self::track_key( $title );

		$count = (int) get_term_meta( $term_id, self::META_PLAY_COUNT, true );
		update_term_meta( $term_id, self::META_PLAY_COUNT, $count + 1 );

		$tracks = get_term_meta( $term_id, self::META_TRACKS, true );
		$tracks = is_array( $tracks ) ? $tracks : array();

		if ( isset( $tracks[ $track_key ] ) && is_array( $tracks[ $track_key ] ) ) {
			$tracks[ $track_key ]['plays'] = (int) ( $tracks[ $track_key ]['plays'] ?? 0 ) + 1;
			$tracks[ $track_key ]['title'] = $title;
		} else {
			$tracks[ $track_key ] = array(
				'title' => $title,
				'plays' => 1,
			);
		}

		update_term_meta( $term_id, self::META_TRACKS, $tracks );

		$post_date = (string) get_post_field( 'post_date', $playlist_id );

		$first = (string) get_term_meta( $term_id, self::META_FIRST_PLAYED, true );
		if ( '' === $first || ( '' !== $post_date && $post_date < $first ) ) {
			update_term_meta( $term_id, self::META_FIRST_PLAYED, $post_date );
		}

		$last = (string) get_term_meta( $term_id, self::META_LAST_PLAYED, true );
		if ( '' === $last || ( '' !== $post_date && $post_date > $last ) ) {
			update_term_meta( $term_id, self::META_LAST_PLAYED, $post_date );
		}

		$index = get_post_meta( $playlist_id, self::META_INDEX, true );
		$index = is_array( $index ) ? $index : array();

		if ( ! isset( $index[ $term_id ] ) || ! is_array( $index[ $term_id ] ) ) {
			$index[ $term_id ] = array();
		}

		$index[ $term_id ][ $track_key ] = (int) ( $index[ $term_id ][ $track_key ] ?? 0 ) + 1;
		update_post_meta( $playlist_id, self::META_INDEX, $index );

		if ( ! empty( $all_tracks ) ) {
			update_post_meta( $playlist_id, self::META_SUMMARY, self::build_summary( $all_tracks ) );
		}

		self::$skip_save[ $playlist_id ] = true;

		Cache::bump();

		/**
		 * Fires after a track has been added to the derived artist index.
		 *
		 * @param int $playlist_id Playlist post ID.
		 * @param int $term_id     Artist term ID.
		 * @param array $track     Playlist entry.
		 */
		do_action( 'wordpress_playlists_track_indexed', $playlist_id, $term_id, $track );

		Cache::purge_after_play( $playlist_id, $term_id );
	}

	/**
	 * Build the display summary for a playlist.
	 *
	 * @param array $tracks Playlist meta array.
	 * @return array{count:int,first:string,last:string,preview:array,artists:int}
	 */
	public static function build_summary( array $tracks ): array {
		$count   = 0;
		$first   = '';
		$last    = '';
		$preview = array();
		$artists = array();

		foreach ( $tracks as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( ! self::is_played( $entry ) ) {
				continue;
			}

			$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );
			$title  = trim( (string) ( $entry['playlist_entry_song'] ?? '' ) );

			if ( '' === $artist && '' === $title ) {
				continue;
			}

			$count++;

			$time = Renderer::time( (string) ( $entry['playlist_entry_comments'] ?? '' ) );

			if ( '' !== $time ) {
				if ( '' === $first || $time < $first ) {
					$first = $time;
				}

				if ( '' === $last || $time > $last ) {
					$last = $time;
				}
			}

			if ( count( $preview ) < 3 && '' !== $artist ) {
				$preview[] = array(
					'artist' => $artist,
					'title'  => $title,
				);
			}

			if ( '' !== $artist ) {
				$artists[ self::normalize_key( $artist ) ] = true;
			}
		}

		return array(
			'count'   => $count,
			'first'   => $first,
			'last'    => $last,
			'preview' => $preview,
			'artists' => count( $artists ),
		);
	}

	/**
	 * Reconcile one playlist after an editorial save.
	 *
	 * @param int $post_id Playlist post ID.
	 */
	public static function resync_playlist( int $post_id ): void {
		if ( self::$rebuilding ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'playlist' !== $post->post_type ) {
			return;
		}

		$tracks = get_post_meta( $post_id, 'playlist', true );
		$tracks = is_array( $tracks ) ? $tracks : array();

		self::preload( self::artist_names_from_tracks( $tracks ) );

		$new_index = array();
		$titles    = array();

		foreach ( $tracks as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( ! self::is_played( $entry ) ) {
				continue;
			}

			$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );
			$title  = trim( (string) ( $entry['playlist_entry_song'] ?? '' ) );

			if ( '' === $artist || '' === $title ) {
				continue;
			}

			$term_id = self::term_id_for_name( $artist );

			if ( ! $term_id ) {
				continue;
			}

			$track_key = self::track_key( $title );

			$new_index[ $term_id ][ $track_key ] = (int) ( $new_index[ $term_id ][ $track_key ] ?? 0 ) + 1;
			$titles[ $term_id ][ $track_key ]    = $title;
		}

		$old_index = get_post_meta( $post_id, self::META_INDEX, true );
		$old_index = is_array( $old_index ) ? $old_index : array();

		$term_ids = array_unique(
			array_merge( array_map( 'intval', array_keys( $old_index ) ), array_map( 'intval', array_keys( $new_index ) ) )
		);

		$desired = array_map( 'intval', array_keys( $new_index ) );

		foreach ( $term_ids as $term_id ) {
			$old = isset( $old_index[ $term_id ] ) && is_array( $old_index[ $term_id ] ) ? $old_index[ $term_id ] : array();
			$new = isset( $new_index[ $term_id ] ) && is_array( $new_index[ $term_id ] ) ? $new_index[ $term_id ] : array();

			$track_meta = get_term_meta( $term_id, self::META_TRACKS, true );
			$track_meta = is_array( $track_meta ) ? $track_meta : array();

			$count = (int) get_term_meta( $term_id, self::META_PLAY_COUNT, true );

			$keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );

			foreach ( $keys as $track_key ) {
				$delta = (int) ( $new[ $track_key ] ?? 0 ) - (int) ( $old[ $track_key ] ?? 0 );

				if ( 0 === $delta ) {
					continue;
				}

				$count += $delta;

				$plays = (int) ( $track_meta[ $track_key ]['plays'] ?? 0 ) + $delta;

				if ( $plays <= 0 ) {
					unset( $track_meta[ $track_key ] );
				} else {
					if ( ! isset( $track_meta[ $track_key ] ) || ! is_array( $track_meta[ $track_key ] ) ) {
						$track_meta[ $track_key ] = array();
					}

					$track_meta[ $track_key ]['plays'] = $plays;
					$track_meta[ $track_key ]['title'] = (string) ( $titles[ $term_id ][ $track_key ] ?? ( $track_meta[ $track_key ]['title'] ?? '' ) );
				}
			}

			update_term_meta( $term_id, self::META_PLAY_COUNT, max( 0, $count ) );
			update_term_meta( $term_id, self::META_TRACKS, $track_meta );
		}

		if ( ! empty( $desired ) ) {
			$post_date = (string) get_post_field( 'post_date', $post_id );

			foreach ( $desired as $term_id ) {
				$first = (string) get_term_meta( $term_id, self::META_FIRST_PLAYED, true );

				if ( '' === $first || ( '' !== $post_date && $post_date < $first ) ) {
					update_term_meta( $term_id, self::META_FIRST_PLAYED, $post_date );
				}

				$last = (string) get_term_meta( $term_id, self::META_LAST_PLAYED, true );

				if ( '' === $last || ( '' !== $post_date && $post_date > $last ) ) {
					update_term_meta( $term_id, self::META_LAST_PLAYED, $post_date );
				}
			}
		}

		$assigned = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
		$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );

		sort( $assigned );
		sort( $desired );

		if ( $assigned !== $desired ) {
			wp_set_object_terms( $post_id, $desired, self::TAXONOMY, false );
		}

		update_post_meta( $post_id, self::META_INDEX, $new_index );
		update_post_meta( $post_id, self::META_SUMMARY, self::build_summary( $tracks ) );

		Cache::bump();
	}

	/**
	 * Save callback. Skips our own writes, revisions and autosaves.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function on_save( $post_id, $post, $update ): void {
		$post_id = (int) $post_id;

		if ( ! empty( self::$skip_save[ $post_id ] ) ) {
			unset( self::$skip_save[ $post_id ] );
			return;
		}

		if ( ! $post instanceof \WP_Post || 'playlist' !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		self::resync_playlist( $post_id );
	}

	/**
	 * Subtract a deleted playlist from the counters.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete( $post_id ): void {
		if ( 'playlist' !== get_post_type( $post_id ) ) {
			return;
		}

		self::subtract_playlist( (int) $post_id );
	}

	/**
	 * Remove a playlist's contribution from the index.
	 *
	 * @param int $post_id Playlist post ID.
	 */
	public static function subtract_playlist( int $post_id ): void {
		$index = get_post_meta( $post_id, self::META_INDEX, true );

		if ( ! is_array( $index ) || empty( $index ) ) {
			return;
		}

		foreach ( $index as $term_id => $track_counts ) {
			$term_id = (int) $term_id;

			if ( ! $term_id || ! is_array( $track_counts ) ) {
				continue;
			}

			$count = (int) get_term_meta( $term_id, self::META_PLAY_COUNT, true );
			$count = max( 0, $count - array_sum( array_map( 'intval', $track_counts ) ) );
			update_term_meta( $term_id, self::META_PLAY_COUNT, $count );

			$tracks = get_term_meta( $term_id, self::META_TRACKS, true );
			$tracks = is_array( $tracks ) ? $tracks : array();

			foreach ( $track_counts as $track_key => $plays ) {
				if ( ! isset( $tracks[ $track_key ] ) ) {
					continue;
				}

				$remaining = (int) ( $tracks[ $track_key ]['plays'] ?? 0 ) - (int) $plays;

				if ( $remaining <= 0 ) {
					unset( $tracks[ $track_key ] );
				} else {
					$tracks[ $track_key ]['plays'] = $remaining;
				}
			}

			update_term_meta( $term_id, self::META_TRACKS, $tracks );
		}

		Cache::bump();
	}

	/**
	 * All artist names in a playlist's meta array.
	 *
	 * @param array $tracks Playlist meta.
	 * @return array<int,string>
	 */
	public static function artist_names_from_tracks( array $tracks ): array {
		$names = array();

		foreach ( $tracks as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );

			if ( '' !== $artist ) {
				$names[] = $artist;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Read the per-artist track summary.
	 *
	 * @return array<string,array{title:string,plays:int}>
	 */
	public static function tracks_for_term( int $term_id ): array {
		$tracks = get_term_meta( $term_id, self::META_TRACKS, true );

		if ( ! is_array( $tracks ) ) {
			return array();
		}

		$clean = array();

		foreach ( $tracks as $key => $track ) {
			if ( ! is_array( $track ) ) {
				continue;
			}

			$clean[ (string) $key ] = array(
				'title' => (string) ( $track['title'] ?? '' ),
				'plays' => (int) ( $track['plays'] ?? 0 ),
			);
		}

		return $clean;
	}

	/**
	 * Rebuild every derived structure from the playlist meta.
	 *
	 * @param bool $dry_run Only report differences.
	 * @return array<string,mixed>
	 */
	public static function rebuild( bool $dry_run = false ): array {
		self::$rebuilding = true;
		self::$read_only  = $dry_run;
		self::$terms      = array();

		$post_ids = get_posts(
			array(
				'post_type'        => 'playlist',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		$existing_terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 0,
			)
		);

		if ( ! is_wp_error( $existing_terms ) ) {
			update_termmeta_cache( wp_list_pluck( $existing_terms, 'term_id' ) );

			foreach ( $existing_terms as $term ) {
				$key = (string) get_term_meta( $term->term_id, self::META_KEY, true );

				if ( '' !== $key ) {
					self::$terms[ $key ] = (int) $term->term_id;
				}
			}
		}

		$aggregate = array();
		$indexes   = array();
		$summaries = array();
		$names     = array();
		$missing   = array();

		$processed = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$tracks  = get_post_meta( $post_id, 'playlist', true );
			$tracks  = is_array( $tracks ) ? $tracks : array();

			self::preload( self::artist_names_from_tracks( $tracks ) );

			$index = array();
			$dates = (string) get_post_field( 'post_date', $post_id );

			foreach ( $tracks as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				if ( ! self::is_played( $entry ) ) {
					continue;
				}

				$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );
				$title  = trim( (string) ( $entry['playlist_entry_song'] ?? '' ) );

				if ( '' === $artist || '' === $title ) {
					continue;
				}

				$term_id = self::term_id_for_name( $artist );

				if ( ! $term_id ) {
					$missing[] = $artist;
					continue;
				}

				$track_key = self::track_key( $title );

				$index[ $term_id ][ $track_key ] = (int) ( $index[ $term_id ][ $track_key ] ?? 0 ) + 1;

				if ( ! isset( $aggregate[ $term_id ] ) ) {
					$aggregate[ $term_id ] = array(
						'plays'  => 0,
						'tracks' => array(),
						'first'  => '',
						'last'   => '',
					);
				}

				$aggregate[ $term_id ]['plays']++;

				if ( ! isset( $aggregate[ $term_id ]['tracks'][ $track_key ] ) ) {
					$aggregate[ $term_id ]['tracks'][ $track_key ] = array(
						'title' => $title,
						'plays' => 0,
					);
				}

				$aggregate[ $term_id ]['tracks'][ $track_key ]['plays']++;
				$aggregate[ $term_id ]['tracks'][ $track_key ]['title'] = $title;

				if ( '' !== $dates ) {
					if ( '' === $aggregate[ $term_id ]['first'] || $dates < $aggregate[ $term_id ]['first'] ) {
						$aggregate[ $term_id ]['first'] = $dates;
					}

					if ( '' === $aggregate[ $term_id ]['last'] || $dates > $aggregate[ $term_id ]['last'] ) {
						$aggregate[ $term_id ]['last'] = $dates;
					}
				}
			}

			$indexes[ $post_id ]   = $index;
			$summaries[ $post_id ] = self::build_summary( $tracks );

			$processed++;

			if ( 0 === $processed % 500 ) {
				do_action( 'wordpress_playlists_rebuild_progress', $processed, count( $post_ids ) );
			}
		}

		$report = self::compare_rebuild( $post_ids, $indexes, $summaries, $aggregate, $dry_run );

		$report['playlists'] = count( $post_ids );
		$report['terms']     = count( $aggregate );
		$report['plays']     = array_sum( wp_list_pluck( $aggregate, 'plays' ) );
		$report['missing']   = array_values( array_unique( $missing ) );
		$report['dry_run']   = $dry_run;

		if ( $dry_run ) {
			self::$rebuilding = false;
			self::$read_only  = false;

			return $report;
		}

		wp_defer_term_counting( true );

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			$stored_index = get_post_meta( $post_id, self::META_INDEX, true );

			if ( $stored_index != $indexes[ $post_id ] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Key order is irrelevant.
				update_post_meta( $post_id, self::META_INDEX, $indexes[ $post_id ] );
			}

			$stored_summary = get_post_meta( $post_id, self::META_SUMMARY, true );

			if ( $stored_summary !== $summaries[ $post_id ] ) {
				update_post_meta( $post_id, self::META_SUMMARY, $summaries[ $post_id ] );
			}

			$desired  = array_map( 'intval', array_keys( $indexes[ $post_id ] ) );
			$assigned = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
			$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );

			sort( $assigned );
			sort( $desired );

			if ( $assigned !== $desired ) {
				wp_set_object_terms( $post_id, $desired, self::TAXONOMY, false );
				$report['reassigned']++;
			}
		}

		wp_defer_term_counting( false );

		$shipped = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 0,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $shipped ) ) {
			$shipped = array();
		}

		update_termmeta_cache( $shipped );

		foreach ( $shipped as $term_id ) {
			$term_id = (int) $term_id;
			$data    = $aggregate[ $term_id ] ?? array(
				'plays'  => 0,
				'tracks' => array(),
				'first'  => '',
				'last'   => '',
			);

			update_term_meta( $term_id, self::META_PLAY_COUNT, (int) $data['plays'] );
			update_term_meta( $term_id, self::META_TRACKS, $data['tracks'] );
			update_term_meta( $term_id, self::META_FIRST_PLAYED, (string) $data['first'] );
			update_term_meta( $term_id, self::META_LAST_PLAYED, (string) $data['last'] );
		}

		foreach ( array_chunk( $shipped, 500 ) as $chunk ) {
			wp_update_term_count_now( $chunk, self::TAXONOMY );
		}

		update_option( self::BUILT_OPTION, time(), false );

		Cache::forget( 'artist_letters' );
		Cache::forget( 'playlist_months' );
		Cache::bump();

		self::$rebuilding = false;
		self::$read_only  = false;
		self::$terms      = array();

		return $report;
	}

	/**
	 * Compare stored derived data with freshly computed data.
	 *
	 * @param array $post_ids  Playlist IDs.
	 * @param array $indexes   Computed indexes.
	 * @param array $summaries Computed summaries.
	 * @param array $aggregate Computed term aggregates.
	 * @param bool  $dry_run   Whether this is a dry run.
	 * @return array<string,mixed>
	 */
	private static function compare_rebuild( array $post_ids, array $indexes, array $summaries, array $aggregate, bool $dry_run ): array {
		$report = array(
			'index_mismatch' => 0,
			'summary_mismatch' => 0,
			'term_mismatch'  => 0,
			'term_missing'   => 0,
			'reassigned'     => 0,
			'examples'       => array(),
		);

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( get_post_meta( $post_id, self::META_INDEX, true ) != $indexes[ $post_id ] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				$report['index_mismatch']++;
			}

			if ( get_post_meta( $post_id, self::META_SUMMARY, true ) !== $summaries[ $post_id ] ) {
				$report['summary_mismatch']++;
			}
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 0,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			update_termmeta_cache( wp_list_pluck( $terms, 'term_id' ) );

			foreach ( $terms as $term ) {
				$term_id = (int) $term->term_id;
				$stored  = (int) get_term_meta( $term_id, self::META_PLAY_COUNT, true );
				$wanted  = (int) ( $aggregate[ $term_id ]['plays'] ?? 0 );

				if ( $stored !== $wanted ) {
					$report['term_mismatch']++;

					if ( count( $report['examples'] ) < 20 ) {
						$report['examples'][] = array(
							'term'   => $term->name,
							'stored' => $stored,
							'wanted' => $wanted,
						);
					}
				}
			}
		}

		if ( ! $dry_run ) {
			$report['term_missing'] = 0;
		}

		return $report;
	}

	/**
	 * Recompute one artist from its playlists.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string,mixed>|null
	 */
	public static function recount_term( int $term_id ): ?array {
		$term = get_term( $term_id, self::TAXONOMY );

		if ( ! $term instanceof \WP_Term ) {
			return null;
		}

		$key = (string) get_term_meta( $term_id, self::META_KEY, true );

		if ( '' === $key ) {
			return null;
		}

		$post_ids = get_posts(
			array(
				'post_type'        => 'playlist',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'tax_query'        => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		$plays  = 0;
		$tracks = array();
		$first  = '';
		$last   = '';

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$meta    = get_post_meta( $post_id, 'playlist', true );

			if ( ! is_array( $meta ) ) {
				continue;
			}

			$post_date = (string) get_post_field( 'post_date', $post_id );

			foreach ( $meta as $entry ) {
				if ( ! is_array( $entry ) || ! self::is_played( $entry ) ) {
					continue;
				}

				if ( self::normalize_key( (string) ( $entry['playlist_entry_artist'] ?? '' ) ) !== $key ) {
					continue;
				}

				$title = trim( (string) ( $entry['playlist_entry_song'] ?? '' ) );

				if ( '' === $title ) {
					continue;
				}

				$track_key = self::track_key( $title );

				$plays++;

				if ( ! isset( $tracks[ $track_key ] ) ) {
					$tracks[ $track_key ] = array(
						'title' => $title,
						'plays' => 0,
					);
				}

				$tracks[ $track_key ]['plays']++;

				if ( '' !== $post_date ) {
					if ( '' === $first || $post_date < $first ) {
						$first = $post_date;
					}

					if ( '' === $last || $post_date > $last ) {
						$last = $post_date;
					}
				}
			}
		}

		update_term_meta( $term_id, self::META_PLAY_COUNT, $plays );
		update_term_meta( $term_id, self::META_TRACKS, $tracks );
		update_term_meta( $term_id, self::META_FIRST_PLAYED, $first );
		update_term_meta( $term_id, self::META_LAST_PLAYED, $last );

		Cache::forget( 'artist_letters' );
		Cache::bump();

		return array(
			'term'     => $term->name,
			'plays'    => $plays,
			'tracks'   => count( $tracks ),
			'first'    => $first,
			'last'     => $last,
			'playlists' => count( $post_ids ),
		);
	}

	/**
	 * Index status for the admin page and the CLI.
	 *
	 * @return array<string,int|string>
	 */
	public static function status(): array {
		global $wpdb;

		$playlists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'playlist'
			)
		);

		$with_summary = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_SUMMARY
			)
		);

		$unassigned = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				 WHERE p.post_type = %s
				   AND p.post_status = 'publish'
				   AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tr.object_id = p.ID AND tt.taxonomy = %s
				   )",
				'playlist',
				self::TAXONOMY
			)
		);

		$terms = wp_count_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$plays = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(tm.meta_value AS UNSIGNED)), 0)
				 FROM {$wpdb->termmeta} tm
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
				 WHERE tm.meta_key = %s AND tt.taxonomy = %s",
				self::META_PLAY_COUNT,
				self::TAXONOMY
			)
		);

		return array(
			'playlists'    => $playlists,
			'with_summary' => $with_summary,
			'unassigned'   => $unassigned,
			'terms'        => is_wp_error( $terms ) ? 0 : (int) $terms,
			'plays'        => $plays,
			'built'        => (int) get_option( self::BUILT_OPTION, 0 ),
		);
	}
}

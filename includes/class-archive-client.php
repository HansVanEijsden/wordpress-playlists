<?php
/**
 * Archive Client
 *
 * Matches a playlist to the broadcasts in the Broadcast Archive plugin and
 * exposes a small payload the shared player can start. Optional: when the
 * archive plugin or its REST route is absent, nothing is rendered.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Archive_Client {

	/** Transient key for the episode list. */
	private const CACHE_KEY = 'archive_episodes';

	/** Static availability probe. */
	private static ?bool $available = null;

	/**
	 * Whether the archive is reachable and returns episodes.
	 */
	public static function is_available(): bool {
		if ( null !== self::$available ) {
			return self::$available;
		}

		if ( ! class_exists( '\BroadcastArchive\Plugin' ) || ! function_exists( 'rest_do_request' ) ) {
			self::$available = false;

			return false;
		}

		self::$available = ! empty( self::episodes() );

		return self::$available;
	}

	/**
	 * All archive episodes, cached for ten minutes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function episodes(): array {
		$episodes = Cache::remember_raw(
			self::CACHE_KEY,
			10 * MINUTE_IN_SECONDS,
			static function (): array {
				if ( ! function_exists( 'rest_do_request' ) || ! class_exists( '\WP_REST_Request' ) ) {
					return array();
				}

				$request  = new \WP_REST_Request( 'GET', '/broadcast-archive/v1/episodes' );
				$response = rest_do_request( $request );

				if ( is_wp_error( $response ) || ! is_object( $response ) || $response->is_error() ) {
					return array();
				}

				$data  = $response->get_data();
				$items = is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

				$clean = array();

				foreach ( $items as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$audio = isset( $item['audio'] ) ? esc_url_raw( (string) $item['audio'] ) : '';

					if ( '' === $audio || ! preg_match( '#^https?://#i', $audio ) ) {
						continue;
					}

					$clean[] = array(
						'id'          => (string) ( $item['id'] ?? '' ),
						'title'       => (string) ( $item['title'] ?? '' ),
						'show'        => (string) ( $item['show'] ?? '' ),
						'presenter'   => (string) ( $item['presenter'] ?? '' ),
						'start'       => (string) ( $item['start'] ?? '' ),
						'end'         => (string) ( $item['end'] ?? '' ),
						'date'        => (string) ( $item['date'] ?? '' ),
						'time'        => (string) ( $item['time'] ?? '' ),
						'duration'    => (int) ( $item['duration'] ?? 0 ),
						'description' => (string) ( $item['description'] ?? '' ),
						'audio'       => $audio,
						'image'       => isset( $item['image'] ) ? esc_url_raw( (string) $item['image'] ) : '',
						'ts'          => (int) ( $item['ts'] ?? 0 ),
					);
				}

				return $clean;
			}
		);

		return is_array( $episodes ) ? $episodes : array();
	}

	/**
	 * Matching broadcasts for a playlist.
	 *
	 * @param int $playlist_id Playlist post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_playlist( int $playlist_id ): array {
		if ( ! Context::display_enabled() || ! self::is_available() ) {
			return array();
		}

		$post = get_post( $playlist_id );

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$date = mysql2date( 'Y-m-d', $post->post_date );
		$show = self::show_name( $post );

		if ( '' === $show ) {
			return array();
		}

		$summary = get_post_meta( $playlist_id, Artist_Index::META_SUMMARY, true );
		$first   = '';
		$last    = '';

		if ( is_array( $summary ) ) {
			$first = Renderer::time( (string) ( $summary['first'] ?? '' ) );
			$last  = Renderer::time( (string) ( $summary['last'] ?? '' ) );
		}

		$span_start = Renderer::minutes( $first );
		$span_end   = Renderer::minutes( $last );

		$matches = array();

		foreach ( self::episodes() as $episode ) {
			if ( $date !== (string) $episode['date'] ) {
				continue;
			}

			if ( ! self::same_show( $show, (string) $episode['show'] ) ) {
				continue;
			}

			if ( null !== $span_start && null !== $span_end ) {
				$episode_start = self::minutes_from_iso( (string) $episode['start'] );
				$episode_end   = self::minutes_from_iso( (string) $episode['end'] );

				if ( null !== $episode_start && null !== $episode_end ) {
					$overlaps = $episode_start < $span_end + 1 && $episode_end > $span_start;

					if ( ! $overlaps ) {
						continue;
					}
				}
			}

			$episode['label'] = '' !== (string) $episode['time'] ? (string) $episode['time'] : self::label_from_iso( (string) $episode['start'], (string) $episode['end'] );

			$matches[] = $episode;
		}

		usort(
			$matches,
			static function ( $a, $b ) {
				return (int) $a['ts'] <=> (int) $b['ts'];
			}
		);

		return array_slice( $matches, 0, 8 );
	}

	/**
	 * Show name for a playlist, falling back to the title suffix.
	 *
	 * @param \WP_Post $post Playlist post.
	 */
	public static function show_name( \WP_Post $post ): string {
		$show_id = absint( get_post_meta( $post->ID, 'playlist_show_id', true ) );
		$show    = $show_id ? get_post( $show_id ) : null;

		if ( $show instanceof \WP_Post && 'show' === $show->post_type && 'publish' === $show->post_status ) {
			return (string) $show->post_title;
		}

		$parts = explode( ' - ', (string) $post->post_title, 2 );

		return isset( $parts[1] ) ? trim( $parts[1] ) : '';
	}

	/**
	 * Compare two show names loosely.
	 */
	private static function same_show( string $a, string $b ): bool {
		$a = Artist_Index::normalize_key( $a );
		$b = Artist_Index::normalize_key( $b );

		if ( '' === $a || '' === $b ) {
			return false;
		}

		if ( $a === $b ) {
			return true;
		}

		return false !== strpos( $a, $b ) || false !== strpos( $b, $a );
	}

	/**
	 * Minutes since midnight for an ISO timestamp, in site time.
	 */
	private static function minutes_from_iso( string $iso ): ?int {
		if ( '' === $iso ) {
			return null;
		}

		$timestamp = strtotime( $iso );

		if ( false === $timestamp ) {
			return null;
		}

		$local = wp_date( 'H:i', $timestamp );

		return Renderer::minutes( $local );
	}

	/**
	 * Fallback time label from ISO start/end.
	 */
	private static function label_from_iso( string $start, string $end ): string {
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );

		if ( false === $start_ts || false === $end_ts ) {
			return '';
		}

		return wp_date( 'H:i', $start_ts ) . ' – ' . wp_date( 'H:i', $end_ts );
	}
}

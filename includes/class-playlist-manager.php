<?php
/**
 * Playlist Manager
 *
 * Finds or creates today's playlist post for the current show and appends
 * tracks to its "playlist" post meta, following the Radio Station plugin's
 * data contract exactly.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Playlist_Manager {

	/**
	 * Minimum track length in seconds; shorter tracks are rejected.
	 */
	private int $min_duration_seconds = 120;

	private Station $station;
	private Metadata_Client $metadata_client;

	public function __construct( Station $station ) {
		$this->station         = $station;
		$this->metadata_client = new Metadata_Client( $station );
	}

	public function min_duration_seconds(): int {
		return $this->min_duration_seconds;
	}

	/**
	 * Validate a duration string. Empty and unparseable values are accepted
	 * (with a warning logged); only durations shorter than the configured
	 * minimum are rejected.
	 */
	public function is_duration_valid( string $duration ): bool {
		if ( '' === $duration ) {
			return true;
		}

		$total_seconds = $this->duration_to_seconds( $duration );
		if ( false === $total_seconds ) {
			Logger::log( $this->station->name(), 'Could not parse duration, accepting track: ' . $duration );
			return true;
		}

		return $total_seconds >= $this->min_duration_seconds;
	}

	/**
	 * Add a track to today's playlist for the current show.
	 *
	 * @param string $artist   Artist name.
	 * @param string $title    Track title.
	 * @param string $album    Album name (optional).
	 * @param string $duration Duration in MM:SS format (optional).
	 * @return array{playlist_id:int,track:array}|\WP_Error
	 */
	public function add_track( string $artist, string $title, string $album = '', string $duration = '' ) {
		$show = $this->metadata_client->get_current_show();
		if ( ! $show ) {
			return new \WP_Error( 'no_show', __( 'No current show found at this time.', 'wordpress-playlists' ), array( 'status' => 404 ) );
		}

		$playlist_id = $this->get_todays_playlist( $show['name'] );

		if ( ! $playlist_id ) {
			$playlist_id = $this->create_playlist( (int) $show['id'], $show['name'], $show['avatar_id'] );
			if ( is_wp_error( $playlist_id ) ) {
				return $playlist_id;
			}

			Logger::log( $this->station->name(), sprintf( 'Created playlist %d for show "%s"', $playlist_id, $show['name'] ) );
		}

		$added = $this->append_track( $playlist_id, $artist, $title, $album, $duration );
		if ( ! $added ) {
			return new \WP_Error( 'add_failed', __( 'Could not add the track to the playlist.', 'wordpress-playlists' ), array( 'status' => 500 ) );
		}

		Logger::log( $this->station->name(), sprintf( 'Added "%s" - "%s" to playlist %d', $artist, $title, $playlist_id ) );

		return array(
			'playlist_id' => $playlist_id,
			'track'       => array(
				'artist'   => $artist,
				'title'    => $title,
				'duration' => $duration,
			),
		);
	}

	/**
	 * Find today's playlist for a show.
	 *
	 * Playlists are titled "<date_i18n('j F Y')> - <show name>". The lookup is
	 * a direct post-title query on the day's date prefix rather than a WP_Query
	 * fuzzy "s" search, so it stays correct even when another plugin - e.g. a
	 * fulltext-search mu-plugin - rewrites WordPress search clauses. The show
	 * is matched by its name inside the title (the Radio Station title
	 * contract), not by the playlist_show_id meta, because a metadata hub's
	 * show ID may not match the value Radio Station stored.
	 *
	 * @param string $show_name Show name from the metadata API.
	 * @return int|false
	 */
	private function get_todays_playlist( string $show_name ) {
		global $wpdb;

		$today_date = date_i18n( 'j F Y' );
		$show_name  = trim( (string) $show_name );
		$prefix     = $wpdb->esc_like( $today_date ) . '%';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title
				 FROM {$wpdb->posts}
				 WHERE post_type = 'playlist'
				   AND post_status = 'publish'
				   AND post_title LIKE %s
				 ORDER BY post_date DESC
				 LIMIT 20",
				$prefix
			)
		);

		if ( empty( $rows ) ) {
			return false;
		}

		// Prefer the newest playlist whose title also contains this show's
		// name; fall back to the newest playlist matching today's date.
		$fallback = 0;
		foreach ( $rows as $row ) {
			if ( 0 === $fallback ) {
				$fallback = (int) $row->ID;
			}
			if ( '' !== $show_name && false !== stripos( (string) $row->post_title, $show_name ) ) {
				return (int) $row->ID;
			}
		}

		return $fallback;
	}

	/**
	 * Create a playlist post for a show and attach the correct featured image.
	 *
	 * @return int|\WP_Error
	 */
	private function create_playlist( int $show_id, string $show_name, $avatar_id = null ) {
		$playlist_title = sprintf( '%s - %s', date_i18n( 'j F Y' ), $show_name );

		$playlist_id = wp_insert_post(
			array(
				'post_title'    => $playlist_title,
				'post_type'     => 'playlist',
				'post_status'   => 'publish',
				'post_author'   => $this->station->post_author(),
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		if ( is_wp_error( $playlist_id ) ) {
			Logger::log( $this->station->name(), 'Failed to create playlist: ' . $playlist_id->get_error_message() );
			return $playlist_id;
		}

		update_post_meta( $playlist_id, 'playlist_show_id', $show_id );
		update_post_meta( $playlist_id, 'playlist', array() );

		// Featured images are never downloaded - only existing attachment IDs
		// are used (the show avatar or the station's configured fallback).
		$featured_image_id = $this->get_featured_image_id( $avatar_id );
		if ( $featured_image_id && $this->attachment_exists( $featured_image_id ) ) {
			set_post_thumbnail( $playlist_id, $featured_image_id );
			Logger::log( $this->station->name(), sprintf( 'Set featured image %d on playlist %d', $featured_image_id, $playlist_id ) );
		} else {
			Logger::log(
				$this->station->name(),
				sprintf( 'No usable featured image (avatar %s, fallback %d) for playlist %d', $avatar_id ? '#' . (int) $avatar_id : 'none', $this->station->fallback_image_id(), $playlist_id )
			);
		}

		return $playlist_id;
	}

	/**
	 * Append a track entry to the playlist's 1-based "playlist" meta array.
	 */
	private function append_track( int $playlist_id, string $artist, string $title, string $album, string $duration ): bool {
		$tracks = get_post_meta( $playlist_id, 'playlist', true );
		if ( ! is_array( $tracks ) ) {
			$tracks = array();
		}

		list( $minutes, $seconds ) = $this->parse_duration( $duration );

		$new_key                = count( $tracks ) + 1;
		$tracks[ $new_key ]     = array(
			'playlist_entry_artist'   => $artist,
			'playlist_entry_song'     => $title,
			'playlist_entry_album'    => $album,
			'playlist_entry_label'    => '',
			'playlist_entry_minutes'  => $minutes,
			'playlist_entry_seconds'  => $seconds,
			'playlist_entry_comments' => current_time( 'H:i' ),
			'playlist_entry_status'   => 'played',
		);

		if ( false === update_post_meta( $playlist_id, 'playlist', $tracks ) ) {
			Logger::log( $this->station->name(), sprintf( 'Failed to update playlist meta for playlist %d', $playlist_id ) );
			return false;
		}

		// Touch the post so ordering and feeds stay current.
		wp_update_post(
			array(
				'ID'                => $playlist_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);

		return true;
	}

	/**
	 * Parse a duration string ("MM:SS" or "MM:SS.mmm") into minutes/seconds.
	 *
	 * @return array{0:string,1:string}
	 */
	private function parse_duration( string $duration ): array {
		if ( '' === $duration ) {
			return array( '', '00' );
		}

		$duration = (string) preg_replace( '/\.[0-9]+$/', '', $duration ); // Strip milliseconds.
		$parts    = explode( ':', $duration );

		if ( 2 === count( $parts ) ) {
			$minutes = max( 0, min( 99, (int) $parts[0] ) );
			$seconds = max( 0, min( 59, (int) $parts[1] ) );

			return array( (string) $minutes, str_pad( (string) $seconds, 2, '0', STR_PAD_LEFT ) );
		}

		return array( '', '00' );
	}

	/**
	 * Convert a duration string to total seconds, or false if unparseable.
	 *
	 * @return int|false
	 */
	private function duration_to_seconds( string $duration ) {
		if ( '' === $duration ) {
			return false;
		}

		$duration = (string) preg_replace( '/\.[0-9]+$/', '', $duration );
		$parts    = explode( ':', $duration );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		return ( (int) $parts[0] * 60 ) + (int) $parts[1];
	}

	/**
	 * Pick the featured image: the show's avatar if it exists as an
	 * attachment, otherwise the station's configured fallback.
	 *
	 * @return int
	 */
	private function get_featured_image_id( $avatar_id = null ): int {
		if ( $avatar_id && $this->attachment_exists( (int) $avatar_id ) ) {
			return (int) $avatar_id;
		}

		return $this->station->fallback_image_id();
	}

	private function attachment_exists( int $attachment_id ): bool {
		$attachment = get_post( $attachment_id );
		return $attachment && 'attachment' === $attachment->post_type;
	}
}

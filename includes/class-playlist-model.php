<?php
/**
 * Playlist Model
 *
 * Builds the small, cache-friendly playlist card used by the hub and month
 * views. Reads the summary meta written by the artist index; falls back to the
 * full playlist meta on installs that have not been rebuilt yet.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Playlist_Model {

	/**
	 * Show data for a playlist.
	 *
	 * @param \WP_Post $post Playlist post.
	 * @return array{name:string,url:string,image:string,image_alt:string,initials:string,playlists_url:string}
	 */
	public static function show( \WP_Post $post ): array {
		$show_id = absint( get_post_meta( $post->ID, 'playlist_show_id', true ) );
		$show    = $show_id ? get_post( $show_id ) : null;
		$name    = '';
		$url     = '';

		if ( $show instanceof \WP_Post && 'show' === $show->post_type && 'publish' === $show->post_status ) {
			$name = (string) $show->post_title;
			$url  = (string) get_permalink( $show );
		} else {
			$parts = explode( ' - ', (string) $post->post_title, 2 );

			if ( isset( $parts[1] ) && '' !== trim( $parts[1] ) ) {
				$name = trim( $parts[1] );
			}
		}

		$image     = '';
		$image_alt = '';
		$thumb_id  = (int) get_post_thumbnail_id( $post );

		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'medium' );

			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$image     = (string) $src[0];
				$image_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );

				if ( '' === $image_alt ) {
					$image_alt = $name;
				}
			}
		}

		return array(
			'name'          => $name,
			'url'           => $url,
			'image'         => $image,
			'image_alt'     => $image_alt,
			'initials'      => Renderer::initials( $name ),
			'playlists_url' => '' !== $url ? $url . '#show-playlists' : '',
		);
	}

	/**
	 * Card model for one playlist.
	 *
	 * @param \WP_Post $post Playlist post.
	 * @return array<string,mixed>
	 */
	public static function summary( \WP_Post $post ): array {
		$summary = get_post_meta( $post->ID, Artist_Index::META_SUMMARY, true );

		if ( ! is_array( $summary ) ) {
			$tracks  = get_post_meta( $post->ID, 'playlist', true );
			$summary = Artist_Index::build_summary( is_array( $tracks ) ? $tracks : array() );
		}

		$teaser_parts = array();

		foreach ( (array) ( $summary['preview'] ?? array() ) as $preview ) {
			$artist = trim( (string) ( $preview['artist'] ?? '' ) );
			$title  = trim( (string) ( $preview['title'] ?? '' ) );

			if ( '' === $artist && '' === $title ) {
				continue;
			}

			$teaser_parts[] = '' !== $title ? $artist . ' – ' . $title : $artist;
		}

		return array(
			'id'         => (int) $post->ID,
			'url'        => (string) get_permalink( $post ),
			'title'      => (string) $post->post_title,
			'date'       => mysql2date( 'Y-m-d', $post->post_date ),
			'date_short' => mysql2date( 'j F Y', $post->post_date ),
			'weekday'    => mysql2date( 'l', $post->post_date ),
			'span'       => Renderer::span( (string) ( $summary['first'] ?? '' ), (string) ( $summary['last'] ?? '' ) ),
			'count'      => (int) ( $summary['count'] ?? 0 ),
			'artists'    => (int) ( $summary['artists'] ?? 0 ),
			'teaser'     => implode( ' · ', $teaser_parts ),
			'show'       => self::show( $post ),
		);
	}

	/**
	 * Group card models per broadcast day, newest day first.
	 *
	 * @param array<int,int> $post_ids Playlist IDs.
	 * @return array<int,array{date:string,weekday:string,date_label:string,id:string,items:array<int,array<string,mixed>>}>
	 */
	public static function grouped( array $post_ids ): array {
		$groups = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$card = self::summary( $post );
			$date = (string) $card['date'];

			if ( ! isset( $groups[ $date ] ) ) {
				$groups[ $date ] = array(
					'date'       => $date,
					'weekday'    => mysql2date( 'l', $post->post_date ),
					'date_label' => mysql2date( 'j F Y', $post->post_date ),
					'id'         => 'day-' . str_replace( '-', '', $date ),
					'items'      => array(),
				);
			}

			$groups[ $date ]['items'][] = $card;
		}

		krsort( $groups );

		return array_values( $groups );
	}

	/**
	 * Published playlist IDs for a date range.
	 *
	 * @param string $from_date Y-m-d, inclusive.
	 * @param string $to_date   Y-m-d, inclusive.
	 * @return array<int,int>
	 */
	public static function ids_between( string $from_date, string $to_date ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => 'playlist',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'orderby'          => 'date',
					'order'            => 'DESC',
					'no_found_rows'    => true,
					'update_post_meta_cache' => true,
					'suppress_filters' => true,
					'date_query'       => array(
						array(
							'column'    => 'post_date',
							'after'     => $from_date . ' 00:00:00',
							'before'    => $to_date . ' 23:59:59',
							'inclusive' => true,
						),
					),
				)
			)
		);
	}
}

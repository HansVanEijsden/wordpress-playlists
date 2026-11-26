<?php
/**
 * Archive links
 *
 * Enriches the Broadcast Archive's public payload with artist-page URLs so the
 * archive can turn every artist name in a playlist into a link. This is an
 * optional integration: the archive exposes the `broadcast_archive_payload`
 * filter, and this class only hooks it when both plugins are active.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Archive_Links {

	/** Maximum number of names in one term query. */
	private const CHUNK_SIZE = 300;

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_filter( 'broadcast_archive_payload', array( $this, 'enrich' ) );
	}

	/**
	 * Add artist URLs and the local flag to every playlist track in the payload.
	 *
	 * @param array<string,mixed> $payload Archive payload.
	 * @return array<string,mixed>
	 */
	public function enrich( $payload ) {
		if ( ! is_array( $payload ) || empty( $payload['items'] ) || ! Context::display_enabled() ) {
			return $payload;
		}

		$names = $this->collect_names( $payload );

		if ( empty( $names ) ) {
			return $payload;
		}

		foreach ( array_chunk( array_values( $names ), self::CHUNK_SIZE ) as $chunk ) {
			Artist_Index::preload( $chunk );
		}

		$term_ids = array();

		foreach ( $names as $name ) {
			$term_id = Artist_Index::find_term_id( $name );

			if ( $term_id ) {
				$term_ids[ $name ] = $term_id;
			}
		}

		if ( empty( $term_ids ) ) {
			return $payload;
		}

		// One query for every term's local flag.
		update_termmeta_cache( array_values( $term_ids ) );

		$links = array();

		foreach ( $term_ids as $name => $term_id ) {
			$url = Context::artist_url( $term_id );

			if ( '' === $url ) {
				continue;
			}

			$url = esc_url_raw( $url, array( 'http', 'https' ) );

			if ( '' === $url ) {
				continue;
			}

			$links[ $name ] = array(
				'url'   => $url,
				'local' => (bool) get_term_meta( $term_id, Taxonomy::META_LOCAL, true ),
			);
		}

		if ( empty( $links ) ) {
			return $payload;
		}

		foreach ( $payload['items'] as &$item ) {
			if ( empty( $item['playlist'] ) || ! is_array( $item['playlist'] ) ) {
				continue;
			}

			foreach ( $item['playlist'] as &$track ) {
				if ( ! is_array( $track ) ) {
					continue;
				}

				$name = trim( (string) ( $track['artist'] ?? '' ) );

				if ( isset( $links[ $name ] ) ) {
					$track['artist_url']   = $links[ $name ]['url'];
					$track['artist_local'] = $links[ $name ]['local'];
				}
			}

			unset( $track );
		}

		unset( $item );

		return $payload;
	}

	/**
	 * Unique artist names in the payload.
	 *
	 * @param array<string,mixed> $payload Archive payload.
	 * @return array<string,string>
	 */
	private function collect_names( array $payload ): array {
		$names = array();

		foreach ( $payload['items'] as $item ) {
			if ( empty( $item['playlist'] ) || ! is_array( $item['playlist'] ) ) {
				continue;
			}

			foreach ( $item['playlist'] as $track ) {
				if ( ! is_array( $track ) ) {
					continue;
				}

				$name = trim( (string) ( $track['artist'] ?? '' ) );

				if ( '' !== $name ) {
					$names[ $name ] = $name;
				}
			}
		}

		return $names;
	}
}

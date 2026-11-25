<?php
/**
 * Search
 *
 * Finds artists by name and tracks by title. Track search uses the per-artist
 * track summary in term meta with a prepared LIKE query, never WP_Query's
 * search, because a fulltext search plugin on these sites rewrites it.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Search {

	/**
	 * Run a search.
	 *
	 * @param string $query Raw query.
	 * @param int    $limit Maximum number of artists.
	 * @return array{query:string,groups:array<int,array<string,mixed>>}
	 */
	public static function run( string $query, int $limit = 40 ): array {
		$query = trim( sanitize_text_field( $query ) );

		if ( mb_strlen( $query ) < 2 ) {
			return array(
				'query'  => $query,
				'groups' => array(),
			);
		}

		$cached = Cache::remember(
			'search:' . $query . ':' . $limit,
			30 * MINUTE_IN_SECONDS,
			static function () use ( $query, $limit ): array {
				return self::build( $query, $limit );
			}
		);

		return is_array( $cached ) ? $cached : array(
			'query'  => $query,
			'groups' => array(),
		);
	}

	/**
	 * Build the result groups.
	 *
	 * @param string $query Query.
	 * @param int    $limit Maximum artists.
	 * @return array{query:string,groups:array<int,array<string,mixed>>}
	 */
	private static function build( string $query, int $limit ): array {
		$normalized = Artist_Index::normalize_key( $query );
		$tokens     = array_values(
			array_filter(
				preg_split( '/\s+/u', $normalized ) ?: array(),
				static function ( $token ) {
					return mb_strlen( (string) $token ) >= 2;
				}
			)
		);

		$groups = array();

		// Artist names.
		$terms = get_terms(
			array(
				'taxonomy'       => Taxonomy::NAME,
				'hide_empty'     => true,
				'number'         => $limit,
				'search'         => $query,
				'search_columns' => array( 'name' ),
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$groups[ (int) $term->term_id ] = array(
					'id'      => (int) $term->term_id,
					'name'    => (string) $term->name,
					'url'     => '',
					'plays'   => 0,
					'local'   => false,
					'matched' => array(),
					'exact'   => Artist_Index::normalize_key( (string) $term->name ) === $normalized,
				);
			}
		}

		// Track titles in the per-artist summary.
		if ( ! empty( $tokens ) && count( $groups ) < $limit ) {
			global $wpdb;

			$params   = array( Artist_Index::META_TRACKS );
			$clauses  = array();

			foreach ( $tokens as $token ) {
				$clauses[]  = 'meta_value LIKE %s';
				$params[]   = '%' . $wpdb->esc_like( (string) $token ) . '%';
			}

			$sql = "SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s AND " . implode( ' AND ', $clauses ) . ' LIMIT 400';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built with placeholders and prepared below.
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

			foreach ( (array) $rows as $row ) {
				$term_id = (int) $row->term_id;

				if ( isset( $groups[ $term_id ] ) && count( $groups[ $term_id ]['matched'] ) >= 3 ) {
					continue;
				}

				$tracks = maybe_unserialize( $row->meta_value );

				if ( ! is_array( $tracks ) ) {
					continue;
				}

				$matched = array();

				foreach ( $tracks as $track_key => $track ) {
					$title = (string) ( $track['title'] ?? '' );
					$key   = (string) $track_key;

					$haystack = $key . ' ' . Artist_Index::normalize_key( $title );

					$all = true;

					foreach ( $tokens as $token ) {
						if ( false === strpos( $haystack, (string) $token ) ) {
							$all = false;
							break;
						}
					}

					if ( $all && '' !== $title ) {
						$matched[] = $title;
					}
				}

				if ( empty( $matched ) ) {
					continue;
				}

				if ( ! isset( $groups[ $term_id ] ) ) {
					$groups[ $term_id ] = array(
						'id'      => $term_id,
						'name'    => '',
						'url'     => '',
						'plays'   => 0,
						'local'   => false,
						'matched' => array(),
						'exact'   => false,
					);
				}

				$groups[ $term_id ]['matched'] = array_slice( array_values( array_unique( array_merge( $groups[ $term_id ]['matched'], $matched ) ) ), 0, 3 );
			}
		}

		if ( empty( $groups ) ) {
			return array(
				'query'  => $query,
				'groups' => array(),
			);
		}

		$ids = array_keys( $groups );

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'include'    => $ids,
				'number'     => 0,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			update_termmeta_cache( $ids );

			foreach ( $terms as $term ) {
				$term_id = (int) $term->term_id;

				if ( ! isset( $groups[ $term_id ] ) ) {
					continue;
				}

				$url = get_term_link( $term );

				$groups[ $term_id ]['name']  = (string) $term->name;
				$groups[ $term_id ]['url']   = is_wp_error( $url ) ? '' : (string) $url;
				$groups[ $term_id ]['plays'] = (int) get_term_meta( $term_id, Artist_Index::META_PLAY_COUNT, true );
				$groups[ $term_id ]['local'] = (bool) get_term_meta( $term_id, Taxonomy::META_LOCAL, true );
			}
		}

		$groups = array_values(
			array_filter(
				$groups,
				static function ( $group ) {
					return '' !== (string) $group['name'] && (int) $group['plays'] > 0;
				}
			)
		);

		usort(
			$groups,
			static function ( $a, $b ) {
				if ( $a['exact'] !== $b['exact'] ) {
					return $a['exact'] ? -1 : 1;
				}

				if ( empty( $a['matched'] ) !== empty( $b['matched'] ) ) {
					return empty( $a['matched'] ) ? 1 : -1;
				}

				return (int) $b['plays'] <=> (int) $a['plays'];
			}
		);

		return array(
			'query'  => $query,
			'groups' => array_slice( $groups, 0, $limit ),
		);
	}
}

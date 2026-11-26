<?php
/**
 * Hub View
 *
 * The [wordpress_playlists] shortcode: search, today, the past week, the most
 * played artists, local artists and a month index. No numeric pagination; the
 * month pages carry the archive.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Hub_View {

	/**
	 * Shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function shortcode( $atts = array() ): string {
		if ( ! Context::display_enabled() ) {
			return '';
		}

		Assets::enqueue();

		$atts = shortcode_atts(
			array(
				'week'   => 7,
				'top'    => 8,
				'local'  => Context::LOCAL_LIMIT,
				'months' => 24,
			),
			$atts,
			'wordpress_playlists'
		);

		$data = $this->data(
			array(
				'week'   => max( 1, min( 31, (int) $atts['week'] ) ),
				'top'    => max( 1, min( 20, (int) $atts['top'] ) ),
				'local'  => max( 0, min( Context::LOCAL_LIMIT, (int) $atts['local'] ) ),
				'months' => max( 0, min( 120, (int) $atts['months'] ) ),
			)
		);

		return $this->render( $data );
	}

	/**
	 * Build the hub model.
	 *
	 * @param array $args Section options.
	 * @return array<string,mixed>
	 */
	public function data( array $args = array() ): array {
		$week   = (int) ( $args['week'] ?? 7 );
		$top    = (int) ( $args['top'] ?? 8 );
		$local  = (int) ( $args['local'] ?? Context::LOCAL_LIMIT );
		$months = (int) ( $args['months'] ?? 24 );

		$today = current_time( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( $today . ' -' . max( 1, $week - 1 ) . ' days' ) );

		$post_ids = Playlist_Model::ids_between( $from, $today );
		$groups   = Playlist_Model::grouped( $post_ids );

		$today_groups = array();
		$week_groups  = array();

		foreach ( $groups as $group ) {
			if ( $group['date'] === $today ) {
				$today_groups[] = $group;
			} else {
				$week_groups[] = $group;
			}
		}

		$local_artists = array();

		if ( $local > 0 && ! empty( Context::setting( 'local_artists_section', true ) ) ) {
			$local_artists = Context::local_artists( $local );
		}

		$month_entries = Context::months();

		if ( $months > 0 ) {
			$month_entries = array_slice( $month_entries, 0, $months );
		}

		return array(
			'today'        => $today_groups,
			'week'         => $week_groups,
			'top'          => $this->top_artists( $post_ids, $top ),
			'local'        => $local_artists,
			'months'       => $month_entries,
			'today_label'  => mysql2date( 'l j F Y', $today . ' 00:00:00' ),
			'today_count'  => array_sum( array_map( 'count', wp_list_pluck( $today_groups, 'items' ) ) ),
			'week_count'   => array_sum( array_map( 'count', wp_list_pluck( $week_groups, 'items' ) ) ),
			'total'        => array_sum( array_map( 'intval', wp_list_pluck( Context::months(), 'count' ) ) ),
			'search_url'   => Context::artists_index_url(),
			'artists_url'  => Context::artists_index_url(),
			'hub_url'      => Context::hub_url(),
		);
	}

	/**
	 * Most-played artists in the selected window.
	 *
	 * @param array<int,int> $post_ids Playlist IDs.
	 * @param int            $limit    Number of artists.
	 * @return array<int,array{name:string,plays:int,url:string}>
	 */
	private function top_artists( array $post_ids, int $limit ): array {
		if ( $limit <= 0 || empty( $post_ids ) ) {
			return array();
		}

		$artists = Cache::remember(
			'hub_top_' . md5( implode( ',', $post_ids ) ) . '_' . $limit,
			10 * MINUTE_IN_SECONDS,
			static function () use ( $post_ids ): array {
				$counts = array();
				$names  = array();

				foreach ( $post_ids as $post_id ) {
					$tracks = get_post_meta( (int) $post_id, 'playlist', true );

					if ( ! is_array( $tracks ) ) {
						continue;
					}

					foreach ( $tracks as $entry ) {
						if ( ! is_array( $entry ) || ! Artist_Index::is_played( $entry ) ) {
							continue;
						}

						$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );

						if ( '' === $artist ) {
							continue;
						}

						$key = Artist_Index::normalize_key( $artist );

						$counts[ $key ] = (int) ( $counts[ $key ] ?? 0 ) + 1;
						$names[ $key ]  = $artist;
					}
				}

				arsort( $counts );

				$result = array();

				foreach ( $counts as $key => $count ) {
					$result[] = array(
						'key'   => $key,
						'name'  => $names[ $key ],
						'plays' => (int) $count,
					);
				}

				return $result;
			}
		);

		$artists = is_array( $artists ) ? $artists : array();
		$artists = array_slice( $artists, 0, $limit );

		Artist_Index::preload( wp_list_pluck( $artists, 'name' ) );

		foreach ( $artists as $index => $artist ) {
			$term_id = Artist_Index::find_term_id( (string) $artist['name'] );

			$artists[ $index ]['url'] = $term_id ? Context::artist_url( $term_id ) : '';
		}

		return $artists;
	}

	/**
	 * Render the hub.
	 *
	 * @param array $data Hub model.
	 */
	public function render( array $data ): string {
		$html  = '<div class="wppl wppl-hub">';
		$html .= $this->render_search( $data );

		if ( empty( $data['today'] ) && empty( $data['week'] ) ) {
			$html .= Components::empty_state(
				__( 'No playlists yet', 'wordpress-playlists' ),
				__( 'The first broadcast will appear here as soon as tracks are reported.', 'wordpress-playlists' )
			);
		}

		if ( ! empty( $data['today'] ) ) {
			$html .= '<section class="wppl-section" aria-labelledby="wppl-today-title">';
			$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-today-title">' . esc_html( sprintf( /* translators: %s: date. */ __( 'Today, %s', 'wordpress-playlists' ), (string) $data['today_label'] ) ) . '</h2></div></div>';

			foreach ( $data['today'] as $group ) {
				$html .= '<ul class="wppl-rows">';

				foreach ( $group['items'] as $item ) {
					$html .= Components::playlist_row( $item );
				}

				$html .= '</ul>';
			}

			$html .= '</section>';
		}

		if ( ! empty( $data['week'] ) ) {
			$html .= '<section class="wppl-section" aria-labelledby="wppl-week-title">';
			$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-week-title">' . esc_html__( 'Recent broadcasts', 'wordpress-playlists' ) . '</h2></div></div>';

			foreach ( $data['week'] as $group ) {
				$html .= Components::day_heading( (string) $group['weekday'], (string) $group['date_label'], count( $group['items'] ), (string) $group['id'] );
				$html .= '<ul class="wppl-rows">';

				foreach ( $group['items'] as $item ) {
					$html .= Components::playlist_row( $item );
				}

				$html .= '</ul>';
			}

			$html .= '</section>';
		}

		if ( ! empty( $data['top'] ) ) {
			$html .= $this->render_top( $data['top'] );
		}

		if ( ! empty( $data['local'] ) ) {
			$html .= $this->render_local( $data['local'] );
		}

		if ( ! empty( $data['months'] ) ) {
			$html .= $this->render_months( $data );
		}

		$html .= '<footer class="wppl-hub-foot">';
		$html .= '<a class="wppl-textlink" href="' . esc_url( (string) $data['artists_url'] ) . '">' . esc_html__( 'All artists A–Z', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a>';
		$html .= '</footer>';

		return $html . '</div>';
	}

	/**
	 * Search form.
	 *
	 * @param array $data Hub model.
	 */
	private function render_search( array $data ): string {
		$html  = '<section class="wppl-hub-search" aria-labelledby="wppl-search-title">';
		$html .= '<h2 class="wppl-search-title" id="wppl-search-title">' . esc_html__( 'Find a track or an artist', 'wordpress-playlists' ) . '</h2>';
		$html .= '<form class="wppl-search" role="search" action="' . esc_url( (string) $data['search_url'] ) . '" method="get">';
		$html .= '<label class="wppl-sr" for="wppl-hub-q">' . esc_html__( 'Search an artist or a track', 'wordpress-playlists' ) . '</label>';
		$html .= '<input class="wppl-search-input" id="wppl-hub-q" type="search" name="q" placeholder="' . esc_attr__( 'Artist or track title', 'wordpress-playlists' ) . '" autocomplete="off" />';
		$html .= '<button class="wppl-search-button" type="submit">' . Components::icon( 'search' ) . '<span>' . esc_html__( 'Search', 'wordpress-playlists' ) . '</span></button>';
		$html .= '</form>';
		$html .= '<p class="wppl-search-hint"><a class="wppl-textlink" href="' . esc_url( (string) $data['artists_url'] ) . '">' . esc_html__( 'Browse all artists', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a></p>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * Most played artists.
	 *
	 * @param array $artists Artist rows.
	 */
	private function render_top( array $artists ): string {
		$html  = '<section class="wppl-section wppl-top" aria-labelledby="wppl-top-title">';
		$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-top-title">' . esc_html__( 'Most played this week', 'wordpress-playlists' ) . '</h2></div></div>';
		$html .= '<ol class="wppl-top-list">';

		$rank = 0;

		foreach ( $artists as $artist ) {
			$rank++;

			$html .= '<li class="wppl-top-item">';
			$html .= '<span class="wppl-top-rank" aria-hidden="true">' . esc_html( str_pad( (string) $rank, 2, '0', STR_PAD_LEFT ) ) . '</span>';

			if ( ! empty( $artist['url'] ) ) {
				$html .= '<a class="wppl-top-name" href="' . esc_url( (string) $artist['url'] ) . '">' . esc_html( (string) $artist['name'] ) . '</a>';
			} else {
				$html .= '<span class="wppl-top-name">' . esc_html( (string) $artist['name'] ) . '</span>';
			}

			$html .= '<span class="wppl-top-plays">' . esc_html( Renderer::count_label( (int) $artist['plays'], '%s play', '%s plays' ) ) . '</span>';
			$html .= '</li>';
		}

		return $html . '</ol></section>';
	}

	/**
	 * Local artists.
	 *
	 * @param array $artists Local artist rows.
	 */
	private function render_local( array $artists ): string {
		$html  = '<section class="wppl-section wppl-local" aria-labelledby="wppl-local-title">';
		$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-local-title">' . esc_html__( 'Artists from the region', 'wordpress-playlists' ) . '</h2></div></div>';
		$html .= '<ul class="wppl-local-list">';

		foreach ( $artists as $artist ) {
			$html .= '<li class="wppl-local-item">';
			$html .= '<a class="wppl-local-link" href="' . esc_url( (string) ( $artist['url'] ?? Context::artists_index_url() ) ) . '">';
			$html .= Components::avatar( $artist, 'md' );
			$html .= '<span class="wppl-local-body"><span class="wppl-local-name">' . esc_html( (string) $artist['name'] ) . '</span>';
			$html .= '<span class="wppl-local-meta">' . esc_html( Renderer::count_label( (int) $artist['plays'], '%s play', '%s plays' ) ) . '</span></span>';
			$html .= '</a></li>';
		}

		return $html . '</ul></section>';
	}

	/**
	 * Month navigation.
	 *
	 * @param array $data Hub model.
	 */
	private function render_months( array $data ): string {
		$html  = '<section class="wppl-section wppl-months" aria-labelledby="wppl-months-title">';
		$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-months-title">' . esc_html__( 'Browse by month', 'wordpress-playlists' ) . '</h2></div></div>';
		$html .= '<ul class="wppl-month-list">';

		foreach ( $data['months'] as $month ) {
			if ( '' === (string) $month['url'] ) {
				continue;
			}

			$html .= '<li class="wppl-month-item"><a class="wppl-month-link" href="' . esc_url( (string) $month['url'] ) . '"><span class="wppl-month-label">' . esc_html( (string) $month['label'] ) . '</span><span class="wppl-month-count">' . esc_html( Renderer::number( (int) $month['count'] ) ) . '</span></a></li>';
		}

		return $html . '</ul></section>';
	}
}

<?php
/**
 * Artist View
 *
 * The canonical artist page (all airplay for one artist), the A–Z index, its
 * letter pages, search results and the embeddable artist card.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Artist_View {

	/**
	 * Render the current artist archive route.
	 */
	public function render_current_archive(): void {
		$term = get_queried_object();

		if ( ! $term instanceof \WP_Term || Taxonomy::NAME !== $term->taxonomy ) {
			$this->not_found();
			return;
		}

		$data = $this->archive_data( $term );

		echo $this->render_archive( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes internally.
	}

	/**
	 * Render the artist index, a letter page or search results.
	 */
	public function render_current_index(): void {
		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $query ) {
			echo $this->render_search( Search::run( $query ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$letter = (string) get_query_var( 'wppl_letter' );

		if ( '' !== $letter ) {
			$data = $this->letter_data( $letter );

			if ( null === $data ) {
				$this->not_found();
				return;
			}

			echo $this->render_letter( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo $this->render_index( $this->index_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build an artist archive model.
	 *
	 * @param \WP_Term $term Artist term.
	 * @return array<string,mixed>
	 */
	public function archive_data( \WP_Term $term ): array {
		$term_id = (int) $term->term_id;
		$key     = (string) get_term_meta( $term_id, Artist_Index::META_KEY, true );
		$plays   = (int) get_term_meta( $term_id, Artist_Index::META_PLAY_COUNT, true );
		$first   = (string) get_term_meta( $term_id, Artist_Index::META_FIRST_PLAYED, true );
		$last    = (string) get_term_meta( $term_id, Artist_Index::META_LAST_PLAYED, true );
		$local   = (bool) get_term_meta( $term_id, Taxonomy::META_LOCAL, true );
		$image_id = (int) get_term_meta( $term_id, Taxonomy::META_IMAGE, true );
		$image   = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$bio     = (string) get_term_meta( $term_id, Taxonomy::META_BIO, true );

		$tracks = Artist_Index::tracks_for_term( $term_id );

		uasort(
			$tracks,
			static function ( $a, $b ) {
				if ( (int) $a['plays'] === (int) $b['plays'] ) {
					return strcasecmp( (string) $a['title'], (string) $b['title'] );
				}

				return (int) $b['plays'] <=> (int) $a['plays'];
			}
		);

		$used   = array();
		$models = array();

		foreach ( $tracks as $track_key => $track ) {
			$title  = (string) $track['title'];
			$anchor = 'track-' . ( '' !== sanitize_title( $title ) ? sanitize_title( $title ) : (string) $track_key );
			$base   = $anchor;
			$index  = 2;

			while ( isset( $used[ $anchor ] ) ) {
				$anchor = $base . '-' . $index;
				$index++;
			}

			$used[ $anchor ] = true;

			$models[] = array(
				'anchor' => $anchor,
				'title'  => $title,
				'plays'  => (int) $track['plays'],
			);
		}

		$per_page = Context::per_page();
		$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		$query = new \WP_Query(
			array(
				'post_type'              => 'playlist',
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => Taxonomy::NAME,
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
					),
				),
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$card  = Playlist_Model::summary( $post );
			$plays_list = array();
			$meta  = get_post_meta( $post->ID, 'playlist', true );

			if ( is_array( $meta ) ) {
				foreach ( $meta as $entry ) {
					if ( ! is_array( $entry ) || ! Artist_Index::is_played( $entry ) ) {
						continue;
					}

					if ( Artist_Index::normalize_key( (string) ( $entry['playlist_entry_artist'] ?? '' ) ) !== $key ) {
						continue;
					}

					$plays_list[] = array(
						'time'  => Renderer::time( (string) ( $entry['playlist_entry_comments'] ?? '' ) ),
						'title' => (string) ( $entry['playlist_entry_song'] ?? '' ),
					);
				}
			}

			$items[] = array(
				'card'  => $card,
				'plays' => $plays_list,
			);
		}

		$total_pages = (int) $query->max_num_pages;
		$found_posts = (int) $query->found_posts;
		$link        = get_term_link( $term );
		$first_item  = $found_posts > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
		$last_item   = min( $found_posts, $paged * $per_page );
		$summary     = $found_posts > 0
			? sprintf(
				/* translators: 1: first item, 2: last item, 3: total broadcasts. */
				__( 'Broadcasts %1$s–%2$s of %3$s', 'wordpress-playlists' ),
				number_format_i18n( $first_item ),
				number_format_i18n( $last_item ),
				number_format_i18n( $found_posts )
			)
			: '';

		$data = array(
			'term'        => $term,
			'id'          => $term_id,
			'name'        => (string) $term->name,
			'url'         => is_wp_error( $link ) ? '' : (string) $link,
			'key'         => $key,
			'plays'       => $plays,
			'tracks'      => $models,
			'track_count' => count( $models ),
			'playlists'   => (int) $term->count,
			'first'       => $this->date_label( $first ),
			'last'        => $this->date_label( $last ),
			'local'       => $local,
			'image'       => $image,
			'initials'    => Renderer::initials( (string) $term->name ),
			'bio'         => '' !== $bio ? wpautop( wp_kses_post( $bio ) ) : '',
			'socials'     => $this->socials( $term_id ),
			'items'       => $items,
			'paged'       => $paged,
			'total_pages' => $total_pages,
			'pager'       => Components::pagination( $paged, $total_pages, trailingslashit( is_wp_error( $link ) ? Context::artists_index_url() : (string) $link ) . 'page/%#%/', $summary ),
			'hub_url'     => Context::hub_url(),
			'artists_url' => Context::artists_index_url(),
		);

		wp_reset_postdata();

		return $data;
	}

	/**
	 * Render one artist page.
	 *
	 * @param array $data Artist model.
	 */
	public function render_archive( array $data ): string {
		return '<div class="wppl wppl-page wppl-artist-page"><div class="wppl-artist-layout"><div class="wppl-artist-main">' . $this->render_archive_main( $data ) . '</div><aside class="wppl-artist-rail">' . $this->render_archive_rail( $data ) . '</aside></div></div>';
	}

	/**
	 * Main artist content: breadcrumb, hero, tracks and broadcast list.
	 *
	 * @param array<string,mixed> $data Artist model.
	 */
	public function render_archive_main( array $data ): string {
		$crumbs = array(
			array(
				'label' => __( 'Home', 'wordpress-playlists' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Playlists', 'wordpress-playlists' ),
				'url'   => (string) $data['hub_url'],
			),
			array(
				'label' => __( 'Artists', 'wordpress-playlists' ),
				'url'   => (string) $data['artists_url'],
			),
			array(
				'label' => (string) $data['name'],
			),
		);

		$html  = Components::breadcrumb( $crumbs );
		$html .= '<header class="wppl-artist-hero">';
		$html .= Components::avatar(
			array(
				'name'  => (string) $data['name'],
				'image' => (string) $data['image'],
			),
			'lg'
		);
		$html .= '<div class="wppl-artist-hero-body">';
		$html .= '<h1 class="wppl-artist-name">' . esc_html( (string) $data['name'] ) . '</h1>';

		$meta = array();

		if ( (int) $data['plays'] > 0 ) {
			$meta[] = Renderer::count_label( (int) $data['plays'], '%s play', '%s plays' );
		}

		if ( (int) $data['track_count'] > 0 ) {
			$meta[] = Renderer::count_label( (int) $data['track_count'], '%s track', '%s tracks' );
		}

		if ( (int) $data['playlists'] > 0 ) {
			$meta[] = Renderer::count_label( (int) $data['playlists'], 'in %s broadcast', 'in %s broadcasts' );
		}

		$html .= '<p class="wppl-artist-meta">';

		if ( ! empty( $data['local'] ) ) {
			$html .= Components::badge( __( 'Local', 'wordpress-playlists' ), 'local' );
		}

		if ( ! empty( $meta ) ) {
			$html .= '<span>' . esc_html( implode( ' · ', $meta ) ) . '</span>';
		}

		if ( '' !== (string) $data['last'] ) {
			$html .= '<span class="wppl-artist-last">' . esc_html__( 'Last played', 'wordpress-playlists' ) . ' <span class="wppl-nowrap">' . esc_html( (string) $data['last'] ) . '</span></span>';
		}

		$html .= '</p>';

		$html .= '<div class="wppl-artist-actions">';
		$html .= Components::copy_link( (string) $data['url'] );
		$html .= '</div>';

		$html .= '</div></header>';
		$html .= $this->render_tracks( $data );
		$html .= $this->render_plays( $data );

		return $html;
	}

	/**
	 * Artist rail: station facts first, then editorial info, then theme widgets.
	 *
	 * @param array<string,mixed> $data Artist model.
	 */
	public function render_archive_rail( array $data ): string {
		$html = '<div class="wppl wppl-theme-rail">';

		$html .= '<section class="wppl-rail-card wppl-rail-facts">';
		$html .= '<h2 class="wppl-subhead">' . esc_html__( 'On this station', 'wordpress-playlists' ) . '</h2>';
		$html .= '<dl class="wppl-facts">';

		if ( '' !== (string) $data['first'] ) {
			$html .= '<dt>' . esc_html__( 'First played', 'wordpress-playlists' ) . '</dt><dd>' . esc_html( (string) $data['first'] ) . '</dd>';
		}

		if ( '' !== (string) $data['last'] ) {
			$html .= '<dt>' . esc_html__( 'Last played', 'wordpress-playlists' ) . '</dt><dd>' . esc_html( (string) $data['last'] ) . '</dd>';
		}

		$html .= '<dt>' . esc_html__( 'Total plays', 'wordpress-playlists' ) . '</dt><dd>' . esc_html( Renderer::number( (int) $data['plays'] ) ) . '</dd>';
		$html .= '</dl>';
		$html .= '<p class="wppl-aside-links"><a class="wppl-textlink" href="' . esc_url( (string) $data['hub_url'] ) . '">' . esc_html__( 'All playlists', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a></p>';
		$html .= '</section>';

		if ( '' !== (string) $data['bio'] || ! empty( $data['socials'] ) ) {
			$html .= '<section class="wppl-rail-card wppl-rail-about">';

			if ( '' !== (string) $data['bio'] ) {
				$html .= '<h2 class="wppl-subhead">' . esc_html( sprintf( /* translators: %s: artist name. */ __( 'About %s', 'wordpress-playlists' ), (string) $data['name'] ) ) . '</h2>';
				$html .= '<div class="wppl-prose">' . wp_kses_post( (string) $data['bio'] ) . '</div>';
			}

			if ( ! empty( $data['socials'] ) ) {
				$html .= '<h2 class="wppl-subhead' . ( '' !== (string) $data['bio'] ? ' wppl-subhead--spaced' : '' ) . '">' . esc_html__( 'Listen and follow', 'wordpress-playlists' ) . '</h2>';
				$html .= '<ul class="wppl-social-list">';

				foreach ( $data['socials'] as $social ) {
					$html .= '<li><a class="wppl-textlink" href="' . esc_url( (string) $social['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $social['label'] ) . Components::icon( 'arrow-right' ) . '</a></li>';
				}

				$html .= '</ul>';
			}

			$html .= '</section>';
		}

		$sidebar = Components::theme_sidebar();

		if ( '' !== $sidebar ) {
			$html .= '<div class="wppl-rail-widgets">' . $sidebar . '</div>';
		}

		return $html . '</div>';
	}

	/**
	 * Distinct tracks block.
	 *
	 * @param array $data Artist model.
	 */
	private function render_tracks( array $data ): string {
		$html = '<section class="wppl-artist-tracks" aria-labelledby="wppl-artist-tracks-title">';
		$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-artist-tracks-title">' . esc_html__( 'Tracks by this artist', 'wordpress-playlists' ) . '</h2></div></div>';

		if ( empty( $data['tracks'] ) ) {
			return $html . Components::empty_state( __( 'No tracks indexed yet', 'wordpress-playlists' ) ) . '</section>';
		}

		$html .= '<ul class="wppl-track-list">';

		foreach ( $data['tracks'] as $track ) {
			$html .= '<li class="wppl-track-item" id="' . esc_attr( (string) $track['anchor'] ) . '">';
			$html .= '<span class="wppl-track-item-title">' . esc_html( (string) $track['title'] ) . '</span>';
			$html .= '<span class="wppl-track-item-plays">' . esc_html( Renderer::count_label( (int) $track['plays'], '%s play', '%s plays' ) ) . '</span>';
			$html .= '</li>';
		}

		return $html . '</ul></section>';
	}

	/**
	 * Paginated chronological plays.
	 *
	 * @param array $data Artist model.
	 */
	private function render_plays( array $data ): string {
		$html  = '<section class="wppl-artist-plays" aria-labelledby="wppl-artist-plays-title">';
		$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-artist-plays-title">' . esc_html__( 'Recently played', 'wordpress-playlists' ) . '</h2></div></div>';

		if ( empty( $data['items'] ) ) {
			return $html . Components::empty_state( __( 'This artist has not been played yet', 'wordpress-playlists' ) ) . '</section>';
		}

		$html .= '<ol class="wppl-plays">';

		foreach ( $data['items'] as $item ) {
			$card = (array) $item['card'];

			$html .= '<li class="wppl-play">';

			if ( ! empty( $card['url'] ) ) {
				$html .= '<a class="wppl-play-date" href="' . esc_url( (string) $card['url'] ) . '">' . esc_html( (string) $card['date_short'] ) . '</a>';
			} else {
				$html .= '<span class="wppl-play-date">' . esc_html( (string) $card['date_short'] ) . '</span>';
			}

			$html .= '<span class="wppl-play-body">';

			if ( ! empty( $card['show']['name'] ) ) {
				$html .= '<span class="wppl-play-show">' . esc_html( (string) $card['show']['name'] ) . '</span>';
			}

			$html .= '<span class="wppl-play-tracks">';

			$parts = array();

			foreach ( (array) $item['plays'] as $play ) {
				$text = trim( (string) ( $play['time'] ?? '' ) . ' ' . (string) ( $play['title'] ?? '' ) );
				$parts[] = '<span class="wppl-play-track"><time datetime="' . esc_attr( (string) ( $play['time'] ?? '' ) ) . '">' . esc_html( (string) ( $play['time'] ?? '' ) ) . '</time> ' . esc_html( (string) ( $play['title'] ?? '' ) ) . '</span>';
			}

			$html .= implode( '<span class="wppl-sep" aria-hidden="true">·</span>', $parts );
			$html .= '</span></span>';

			if ( ! empty( $card['url'] ) ) {
				$html .= '<a class="wppl-play-link" href="' . esc_url( (string) $card['url'] ) . '">' . esc_html__( 'View playlist', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a>';
			}

			$html .= '</li>';
		}

		$html .= '</ol>';

		if ( '' !== (string) $data['pager'] ) {
			$html .= (string) $data['pager'];
		}

		return $html . '</section>';
	}

	/**
	 * Index model.
	 *
	 * @return array<string,mixed>
	 */
	public function index_data(): array {
		$groups = Context::artist_letters();
		$counts = array();

		foreach ( $groups as $letter => $artists ) {
			$counts[ $letter ] = count( $artists );
		}

		$local = ! empty( Context::setting( 'local_artists_section', true ) ) ? Context::local_artists( 6 ) : array();

		return array(
			'groups'     => $groups,
			'counts'     => $counts,
			'total'      => array_sum( $counts ),
			'local'      => $local,
			'totals'     => Context::artist_totals(),
			'url'        => Context::artists_index_url(),
			'hub_url'    => Context::hub_url(),
		);
	}

	/**
	 * Render the artist index.
	 *
	 * @param array $data Index model.
	 */
	public function render_index( array $data ): string {
		$crumbs = array(
			array(
				'label' => __( 'Home', 'wordpress-playlists' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Playlists', 'wordpress-playlists' ),
				'url'   => (string) $data['hub_url'],
			),
			array(
				'label' => __( 'Artists', 'wordpress-playlists' ),
			),
		);

		$html  = '<div class="wppl wppl-page wppl-artists">';
		$html .= Components::breadcrumb( $crumbs );
		$html .= '<header class="wppl-page-head">';
		$html .= '<h1 class="wppl-page-title">' . esc_html__( 'Artists', 'wordpress-playlists' ) . '</h1>';
		$html .= '<p class="wppl-page-meta">' . esc_html( Renderer::count_label( (int) $data['total'], '%s artist', '%s artists' ) ) . '</p>';
		$html .= '</header>';

		$html .= $this->search_form( (string) $data['url'], '' );

		if ( ! empty( $data['local'] ) ) {
			$html .= '<section class="wppl-section" aria-labelledby="wppl-index-local-title">';
			$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-index-local-title">' . esc_html__( 'Artists from the region', 'wordpress-playlists' ) . '</h2></div></div>';
			$html .= '<ul class="wppl-local-list">';

			foreach ( $data['local'] as $artist ) {
				$html .= '<li class="wppl-local-item"><a class="wppl-local-link" href="' . esc_url( (string) $artist['url'] ) . '">' . Components::avatar( $artist, 'md' ) . '<span class="wppl-local-body"><span class="wppl-local-name">' . esc_html( (string) $artist['name'] ) . '</span><span class="wppl-local-meta">' . esc_html( Renderer::count_label( (int) $artist['plays'], '%s play', '%s plays' ) ) . '</span></span></a></li>';
			}

			$html .= '</ul></section>';
		}

		$html .= '<section class="wppl-section" aria-labelledby="wppl-index-letters-title">';
		$html .= '<div class="wppl-section-head"><div class="wppl-section-text"><h2 class="wppl-section-title" id="wppl-index-letters-title">' . esc_html__( 'Browse by first letter', 'wordpress-playlists' ) . '</h2></div></div>';
		$html .= '<ul class="wppl-letters">';

		foreach ( $this->ordered_letters( $data['counts'] ) as $letter ) {
			$count = (int) $data['counts'][ $letter ];

			$html .= '<li><a class="wppl-letter" href="' . esc_url( Context::artists_index_url( $letter ) ) . '"><span class="wppl-letter-char">' . esc_html( '0-9' === $letter ? '0–9' : strtoupper( $letter ) ) . '</span><span class="wppl-letter-count">' . esc_html( Renderer::number( $count ) ) . '</span></a></li>';
		}

		$html .= '</ul></section>';
		$html .= '<footer class="wppl-page-foot"><a class="wppl-textlink" href="' . esc_url( (string) $data['hub_url'] ) . '">' . esc_html__( 'Back to all playlists', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a></footer>';

		return $html . '</div>';
	}

	/**
	 * Letter page model, or null for an unknown letter.
	 *
	 * @param string $letter Letter bucket.
	 * @return array<string,mixed>|null
	 */
	public function letter_data( string $letter ): ?array {
		$letter = strtolower( $letter );
		$groups = Context::artist_letters();

		if ( ! isset( $groups[ $letter ] ) ) {
			return null;
		}

		$letters = $this->ordered_letters( array_keys( $groups ) );
		$index   = array_search( $letter, $letters, true );
		$items   = $groups[ $letter ];

		usort(
			$items,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return array(
			'letter'     => $letter,
			'label'      => '0-9' === $letter ? '0–9' : strtoupper( $letter ),
			'items'      => $items,
			'count'      => count( $items ),
			'prev'       => false !== $index && isset( $letters[ $index - 1 ] ) ? $letters[ $index - 1 ] : '',
			'next'       => false !== $index && isset( $letters[ $index + 1 ] ) ? $letters[ $index + 1 ] : '',
			'all'        => $letters,
			'counts'     => array_map( 'count', $groups ),
			'url'        => Context::artists_index_url( $letter ),
			'artists_url' => Context::artists_index_url(),
			'hub_url'    => Context::hub_url(),
		);
	}

	/**
	 * Render a letter page.
	 *
	 * @param array $data Letter model.
	 */
	public function render_letter( array $data ): string {
		$crumbs = array(
			array(
				'label' => __( 'Home', 'wordpress-playlists' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Playlists', 'wordpress-playlists' ),
				'url'   => (string) $data['hub_url'],
			),
			array(
				'label' => __( 'Artists', 'wordpress-playlists' ),
				'url'   => (string) $data['artists_url'],
			),
			array(
				'label' => (string) $data['label'],
			),
		);

		$html  = '<div class="wppl wppl-page wppl-artists">';
		$html .= Components::breadcrumb( $crumbs );
		$html .= '<header class="wppl-page-head">';
		$html .= '<h1 class="wppl-page-title">' . esc_html( sprintf( /* translators: %s: letter. */ __( 'Artists starting with %s', 'wordpress-playlists' ), (string) $data['label'] ) ) . '</h1>';
		$html .= '<p class="wppl-page-meta">' . esc_html( Renderer::count_label( (int) $data['count'], '%s artist', '%s artists' ) ) . '</p>';
		$html .= '</header>';
		$html .= $this->letter_nav( $data );
		$html .= '<ul class="wppl-artist-list">';

		foreach ( $data['items'] as $artist ) {
			$html .= '<li class="wppl-artist-list-item"><a class="wppl-artist-list-link" href="' . esc_url( (string) $artist['url'] ) . '"><span class="wppl-artist-list-name">' . esc_html( (string) $artist['name'] ) . '</span>';

			if ( ! empty( $artist['local'] ) ) {
				$html .= Components::badge( __( 'Local', 'wordpress-playlists' ), 'local' );
			}

			$html .= '<span class="wppl-artist-list-plays">' . esc_html( Renderer::count_label( (int) $artist['plays'], '%s play', '%s plays' ) ) . '</span></a></li>';
		}

		$html .= '</ul>';
		$html .= $this->letter_nav( $data, true );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render search results.
	 *
	 * @param array $data Search model.
	 */
	public function render_search( array $data ): string {
		$query = (string) ( $data['query'] ?? '' );

		$html  = '<div class="wppl wppl-page wppl-artists">';
		$html .= Components::breadcrumb(
			array(
				array(
					'label' => __( 'Home', 'wordpress-playlists' ),
					'url'   => home_url( '/' ),
				),
				array(
					'label' => __( 'Playlists', 'wordpress-playlists' ),
					'url'   => Context::hub_url(),
				),
				array(
					'label' => __( 'Artists', 'wordpress-playlists' ),
					'url'   => Context::artists_index_url(),
				),
				array(
					'label' => __( 'Search', 'wordpress-playlists' ),
				),
			)
		);

		$html .= '<header class="wppl-page-head">';
		$html .= '<h1 class="wppl-page-title">' . esc_html__( 'Search results', 'wordpress-playlists' ) . '</h1>';

		if ( '' !== $query ) {
			$html .= '<p class="wppl-page-meta">' . esc_html( sprintf( /* translators: %s: search query. */ __( 'For “%s”', 'wordpress-playlists' ), $query ) ) . '</p>';
		}

		$html .= '</header>';
		$html .= $this->search_form( Context::artists_index_url(), $query );

		if ( empty( $data['groups'] ) ) {
			$html .= Components::empty_state(
				__( 'No artist or track found', 'wordpress-playlists' ),
				__( 'Try a shorter search, or browse the artist index.', 'wordpress-playlists' ),
				Context::artists_index_url(),
				__( 'Browse all artists', 'wordpress-playlists' )
			);

			return $html . '</div>';
		}

		$html .= '<p class="wppl-search-count" aria-live="polite">' . esc_html( Renderer::count_label( count( $data['groups'] ), '%s result', '%s results' ) ) . '</p>';
		$html .= '<ul class="wppl-search-results">';

		foreach ( $data['groups'] as $group ) {
			$html .= '<li class="wppl-search-result">';
			$html .= '<a class="wppl-search-result-link" href="' . esc_url( (string) $group['url'] ) . '"><span class="wppl-search-result-name">' . esc_html( (string) $group['name'] ) . '</span>';

			if ( ! empty( $group['local'] ) ) {
				$html .= Components::badge( __( 'Local', 'wordpress-playlists' ), 'local' );
			}

			$html .= '<span class="wppl-search-result-plays">' . esc_html( Renderer::count_label( (int) $group['plays'], '%s play', '%s plays' ) ) . '</span></a>';

			if ( ! empty( $group['matched'] ) ) {
				$html .= '<p class="wppl-search-result-tracks">' . esc_html( implode( ' · ', (array) $group['matched'] ) ) . '</p>';
			}

			$html .= '</li>';
		}

		$html .= '</ul></div>';

		return $html;
	}

	/**
	 * Embeddable artist card.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function embed_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'name'   => '',
				'tracks' => 3,
			),
			$atts,
			'wordpress_playlists_artist'
		);

		$term = null;

		if ( (int) $atts['id'] > 0 ) {
			$term = get_term( (int) $atts['id'], Taxonomy::NAME );
		} elseif ( '' !== (string) $atts['name'] ) {
			$term_id = Artist_Index::find_term_id( (string) $atts['name'] );

			if ( $term_id ) {
				$term = get_term( $term_id, Taxonomy::NAME );
			}
		}

		if ( ! $term instanceof \WP_Term ) {
			return '<!-- wordpress_playlists_artist: artist not found -->';
		}

		Assets::enqueue();

		$image_id = (int) get_term_meta( $term->term_id, Taxonomy::META_IMAGE, true );
		$image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		$link     = get_term_link( $term );
		$url      = is_wp_error( $link ) ? '' : (string) $link;
		$tracks   = Artist_Index::tracks_for_term( (int) $term->term_id );

		uasort(
			$tracks,
			static function ( $a, $b ) {
				return (int) $b['plays'] <=> (int) $a['plays'];
			}
		);

		$data = array(
			'name'   => (string) $term->name,
			'url'    => $url,
			'plays'  => (int) get_term_meta( $term->term_id, Artist_Index::META_PLAY_COUNT, true ),
			'local'  => (bool) get_term_meta( $term->term_id, Taxonomy::META_LOCAL, true ),
			'image'  => $image,
			'tracks' => array_slice( $tracks, 0, max( 0, min( 6, (int) $atts['tracks'] ) ) ),
		);

		return $this->render_embed( $data );
	}

	/**
	 * Render the embeddable artist card from a plain model.
	 *
	 * @param array $data Artist card model.
	 */
	public function render_embed( array $data ): string {
		$name   = (string) ( $data['name'] ?? '' );
		$url    = (string) ( $data['url'] ?? '' );
		$plays  = (int) ( $data['plays'] ?? 0 );
		$local  = ! empty( $data['local'] );
		$image  = (string) ( $data['image'] ?? '' );
		$tracks = (array) ( $data['tracks'] ?? array() );

		$html  = '<aside class="wppl wppl-embed" aria-label="' . esc_attr( sprintf( /* translators: %s: artist name. */ __( 'Artist card: %s', 'wordpress-playlists' ), $name ) ) . '">';
		$html .= '<a class="wppl-embed-head" href="' . esc_url( $url ) . '">';
		$html .= Components::avatar(
			array(
				'name'  => $name,
				'image' => $image,
			),
			'md'
		);
		$html .= '<span class="wppl-embed-title"><span class="wppl-embed-name">' . esc_html( $name ) . '</span>';

		if ( $local ) {
			$html .= Components::badge( __( 'Local', 'wordpress-playlists' ), 'local' );
		}

		$html .= '</span></a>';

		$html .= '<p class="wppl-embed-meta">' . esc_html( Renderer::count_label( $plays, '%s play', '%s plays' ) ) . '</p>';

		if ( ! empty( $tracks ) ) {
			$html .= '<ul class="wppl-embed-tracks">';

			foreach ( $tracks as $track ) {
				$html .= '<li><span>' . esc_html( (string) ( $track['title'] ?? '' ) ) . '</span><span class="wppl-embed-plays">' . esc_html( Renderer::number( (int) ( $track['plays'] ?? 0 ) ) ) . '<span class="wppl-sr"> ' . esc_html__( 'plays', 'wordpress-playlists' ) . '</span></span></li>';
			}

			$html .= '</ul>';
		}

		$html .= '<a class="wppl-textlink" href="' . esc_url( $url ) . '">' . esc_html__( 'All playlists and details', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a>';
		$html .= '</aside>';

		return $html;
	}

	/**
	 * Search form.
	 *
	 * @param string $action Form action.
	 * @param string $value  Current query.
	 */
	private function search_form( string $action, string $value ): string {
		$html  = '<form class="wppl-search" role="search" action="' . esc_url( $action ) . '" method="get">';
		$html .= '<label class="wppl-sr" for="wppl-artist-q">' . esc_html__( 'Search an artist or a track', 'wordpress-playlists' ) . '</label>';
		$html .= '<input class="wppl-search-input" id="wppl-artist-q" type="search" name="q" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'Artist or track title', 'wordpress-playlists' ) . '" autocomplete="off" />';
		$html .= '<button class="wppl-search-button" type="submit">' . Components::icon( 'search' ) . '<span>' . esc_html__( 'Search', 'wordpress-playlists' ) . '</span></button>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * A–Z navigation for letter pages.
	 *
	 * @param array $data Letter model.
	 * @param bool  $bottom Whether this is the bottom navigation.
	 */
	private function letter_nav( array $data, bool $bottom = false ): string {
		$html  = '<nav class="wppl-letters-nav' . ( $bottom ? ' wppl-letters-nav--bottom' : '' ) . '" aria-label="' . esc_attr__( 'Letters', 'wordpress-playlists' ) . '">';
		$html .= '<ul class="wppl-letters">';

		foreach ( $data['all'] as $letter ) {
			$is_current = $letter === $data['letter'];
			$html      .= '<li><a class="wppl-letter' . ( $is_current ? ' is-current' : '' ) . '" href="' . esc_url( Context::artists_index_url( $letter ) ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( '0-9' === $letter ? '0–9' : strtoupper( $letter ) ) . '</a></li>';
		}

		return $html . '</ul></nav>';
	}

	/**
	 * Letter buckets in display order.
	 *
	 * @param array $counts Counts keyed by letter.
	 * @return array<int,string>
	 */
	private function ordered_letters( array $counts ): array {
		$letters = array();

		foreach ( array_keys( $counts ) as $letter ) {
			if ( '0-9' !== $letter ) {
				$letters[] = (string) $letter;
			}
		}

		sort( $letters );

		if ( isset( $counts['0-9'] ) ) {
			$letters[] = '0-9';
		}

		return $letters;
	}

	/**
	 * Social links for an artist.
	 *
	 * @param int $term_id Term ID.
	 * @return array<int,array{label:string,url:string}>
	 */
	private function socials( int $term_id ): array {
		$map = array(
			Taxonomy::META_WEBSITE   => __( 'Website', 'wordpress-playlists' ),
			Taxonomy::META_INSTAGRAM => __( 'Instagram', 'wordpress-playlists' ),
			Taxonomy::META_SPOTIFY   => __( 'Spotify', 'wordpress-playlists' ),
			Taxonomy::META_YOUTUBE   => __( 'YouTube', 'wordpress-playlists' ),
		);

		$links = array();

		foreach ( $map as $key => $label ) {
			$url = trim( (string) get_term_meta( $term_id, $key, true ) );

			if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
				$links[] = array(
					'label' => $label,
					'url'   => $url,
				);
			}
		}

		return $links;
	}

	/**
	 * Format a stored date.
	 *
	 * @param string $value MySQL date or empty.
	 */
	private function date_label( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		return mysql2date( 'j F Y', $value );
	}

	/**
	 * Render a 404 response.
	 */
	private function not_found(): void {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		echo '<div class="wppl wppl-page"><header class="wppl-page-head"><h1 class="wppl-page-title">' . esc_html__( 'This page does not exist', 'wordpress-playlists' ) . '</h1></header><p class="wppl-page-meta"><a class="wppl-textlink" href="' . esc_url( Context::artists_index_url() ) . '">' . esc_html__( 'Browse all artists', 'wordpress-playlists' ) . '</a></p></div>';
	}
}

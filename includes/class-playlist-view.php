<?php
/**
 * Playlist View
 *
 * Replaces Radio Station's plain playlist table with the airplay sheet. The
 * theme keeps its own page shell, title, breadcrumb and sidebar; only the
 * article content is replaced.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Playlist_View {

	/** Post IDs rendered in this request (themes sometimes call the_content twice). */
	private static array $rendered = array();

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_take_over' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Add a scoping class to playlist pages.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( array $classes ): array {
		if ( is_singular( 'playlist' ) && Context::display_enabled() ) {
			$classes[] = 'wppl-playlist-page';
		}

		return $classes;
	}

	/**
	 * Swap Radio Station's content template for ours.
	 */
	public function maybe_take_over(): void {
		if ( ! is_singular( 'playlist' ) || ! Context::display_enabled() ) {
			return;
		}

		remove_filter( 'the_content', 'radio_station_playlist_content_template', 11 );
		remove_filter( 'the_excerpt', 'radio_station_playlist_content_template_excerpt', 11 );

		add_filter( 'the_content', array( $this, 'filter_content' ), 11 );

		Assets::enqueue();
	}

	/**
	 * Content filter callback.
	 *
	 * @param string $content Post content.
	 */
	public function filter_content( $content ): string {
		$post_id = (int) get_the_ID();

		if ( ! $post_id || 'playlist' !== get_post_type( $post_id ) ) {
			return (string) $content;
		}

		// The queried object is the authoritative singular target. Some page
		// builders (Newspaper Cloud Templates among them) call the_content()
		// outside the main loop, so is_main_query() is not a safe condition.
		if ( ! is_singular( 'playlist' ) || (int) get_queried_object_id() !== $post_id ) {
			return (string) $content;
		}

		if ( isset( self::$rendered[ $post_id ] ) ) {
			return (string) $content;
		}

		self::$rendered[ $post_id ] = true;

		$data                  = $this->data( $post_id );
		$data['content_html']  = (string) $content;

		return $this->render( $data );
	}

	/**
	 * Build the playlist model.
	 *
	 * @param int $post_id Playlist post ID.
	 * @return array<string,mixed>
	 */
	public function data( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$entries = get_post_meta( $post_id, 'playlist', true );
		$entries = is_array( $entries ) ? $entries : array();

		$summary = get_post_meta( $post_id, Artist_Index::META_SUMMARY, true );

		if ( ! is_array( $summary ) ) {
			$summary = Artist_Index::build_summary( $entries );
		}

		$played = array();

		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && Artist_Index::is_played( $entry ) ) {
				$played[] = $entry;
			}
		}

		$names = array();

		foreach ( $played as $entry ) {
			$names[] = (string) ( $entry['playlist_entry_artist'] ?? '' );
		}

		Artist_Index::preload( $names );

		$terms = array();

		foreach ( $names as $name ) {
			$name = trim( $name );

			if ( '' === $name || isset( $terms[ $name ] ) ) {
				continue;
			}

			$term_id = Artist_Index::find_term_id( $name );

			$terms[ $name ] = array(
				'id'    => $term_id,
				'url'   => $term_id ? Context::artist_url( $term_id ) : '',
				'local' => $term_id ? (bool) get_term_meta( $term_id, Taxonomy::META_LOCAL, true ) : false,
			);
		}

		$tracks = array();
		$times  = array();

		foreach ( $played as $entry ) {
			$artist = trim( (string) ( $entry['playlist_entry_artist'] ?? '' ) );
			$title  = trim( (string) ( $entry['playlist_entry_song'] ?? '' ) );
			$time   = Renderer::time( (string) ( $entry['playlist_entry_comments'] ?? '' ) );

			if ( '' === $artist && '' === $title ) {
				continue;
			}

			$terms_for_artist = $terms[ $artist ] ?? array(
				'url'   => '',
				'local' => false,
			);

			$duration_minutes = max( 0, (int) ( $entry['playlist_entry_minutes'] ?? 0 ) );
			$duration_seconds = max( 0, min( 59, (int) ( $entry['playlist_entry_seconds'] ?? 0 ) ) );

			$tracks[] = array(
				'time'         => $time,
				'hour'         => '' !== $time ? substr( $time, 0, 2 ) : '',
				'artist'       => $artist,
				'artist_url'   => (string) ( $terms_for_artist['url'] ?? '' ),
				'artist_local' => ! empty( $terms_for_artist['local'] ),
				'title'        => $title,
				'duration'         => Renderer::duration( $entry['playlist_entry_minutes'] ?? '', $entry['playlist_entry_seconds'] ?? '' ),
				'duration_iso'     => Renderer::duration_iso( $entry['playlist_entry_minutes'] ?? '', $entry['playlist_entry_seconds'] ?? '' ),
				'duration_seconds' => ( $duration_minutes * 60 ) + $duration_seconds,
			);

			if ( '' !== $time ) {
				$times[] = $time;
			}
		}

		$summary = array_merge(
			array(
				'count'   => count( $tracks ),
				'first'   => '',
				'last'    => '',
				'preview' => array(),
				'artists' => 0,
			),
			$summary
		);

		if ( '' === (string) $summary['first'] && ! empty( $times ) ) {
			$summary['first'] = min( $times );
		}

		if ( '' === (string) $summary['last'] && ! empty( $times ) ) {
			$summary['last'] = max( $times );
		}

		$show = $this->show_data( $post );

		$is_today = mysql2date( 'Y-m-d', $post->post_date ) === current_time( 'Y-m-d' );

		$data = array(
			'id'          => $post_id,
			'url'         => (string) get_permalink( $post ),
			'date'        => mysql2date( 'Y-m-d', $post->post_date ),
			'date_iso'    => $this->iso_datetime( $post ),
			'date_label'  => mysql2date( 'l j F Y', $post->post_date ),
			'date_short'  => mysql2date( 'j F Y', $post->post_date ),
			'weekday'     => mysql2date( 'l', $post->post_date ),
			'title'       => get_the_title( $post ),
			'show'        => $show,
			'count'       => (int) $summary['count'],
			'span'        => Renderer::span( (string) $summary['first'], (string) $summary['last'] ),
			'tracks'      => $tracks,
			'hours'       => Renderer::hours( $times ),
			'bands'       => count( $times ) >= 24 && count( Renderer::hours( $times ) ) >= 2,
			'is_today'    => $is_today,
			'hub_url'     => Context::hub_url(),
			'episodes'    => Archive_Client::for_playlist( $post_id ),
			'archive_url' => Context::archive_url(),
			'prev'        => $this->adjacent( $post, $show, 'before' ),
			'next'        => $this->adjacent( $post, $show, 'after' ),
			'content_html' => '',
		);

		$data['show']['playlists_url'] = $this->show_playlists_url( $show );

		return $data;
	}

	/**
	 * Show data for the plate.
	 *
	 * @param \WP_Post $post Playlist post.
	 * @return array{name:string,url:string,image:string,image_alt:string,initials:string}
	 */
	private function show_data( \WP_Post $post ): array {
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
			'name'     => $name,
			'url'      => $url,
			'image'    => $image,
			'image_alt' => $image_alt,
			'initials' => Renderer::initials( $name ),
		);
	}

	/**
	 * URL of the show page's playlist section.
	 *
	 * @param array $show Show model.
	 */
	private function show_playlists_url( array $show ): string {
		if ( empty( $show['url'] ) ) {
			return '';
		}

		return $show['url'] . '#show-playlists';
	}

	/**
	 * Previous or next playlist of the same show.
	 *
	 * @param \WP_Post $post      Current playlist.
	 * @param array    $show      Show model.
	 * @param string   $direction "before" or "after".
	 * @return array{url:string,label:string}|null
	 */
	private function adjacent( \WP_Post $post, array $show, string $direction ): ?array {
		$show_id = absint( get_post_meta( $post->ID, 'playlist_show_id', true ) );

		if ( ! $show_id || 'show' !== get_post_type( $show_id ) ) {
			return null;
		}

		$args = array(
			'post_type'        => 'playlist',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'post__not_in'     => array( $post->ID ),
			'meta_key'         => 'playlist_show_id',
			'meta_value'       => $show_id,
			'orderby'          => 'date',
			'order'            => 'before' === $direction ? 'DESC' : 'ASC',
			'date_query'       => array(
				array(
					'column'    => 'post_date',
					'before'    => 'before' === $direction ? $post->post_date : null,
					'after'     => 'after' === $direction ? $post->post_date : null,
					'inclusive' => false,
				),
			),
			'suppress_filters' => true,
		);

		$args['date_query'][0] = array_filter( $args['date_query'][0], static function ( $value ) {
			return null !== $value;
		} );

		$ids = get_posts( $args );

		if ( empty( $ids ) ) {
			return null;
		}

		$neighbour = get_post( (int) $ids[0] );

		if ( ! $neighbour instanceof \WP_Post ) {
			return null;
		}

		return array(
			'url'   => (string) get_permalink( $neighbour ),
			'label' => mysql2date( 'j F Y', $neighbour->post_date ),
			'title' => (string) $neighbour->post_title,
		);
	}

	/**
	 * ISO 8601 timestamp for the broadcast date.
	 *
	 * @param \WP_Post $post Playlist post.
	 */
	private function iso_datetime( \WP_Post $post ): string {
		$timezone = wp_timezone();
		$date     = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $post->post_date, $timezone );

		if ( ! $date ) {
			return '';
		}

		return $date->format( 'c' );
	}

	/**
	 * Render the component.
	 *
	 * @param array $data Playlist model.
	 */
	public function render( array $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		$tracks = (array) ( $data['tracks'] ?? array() );
		$count  = (int) ( $data['count'] ?? count( $tracks ) );

		$html  = '<div class="wppl wppl-playlist" data-wppl-sheet data-wppl-date="' . esc_attr( (string) ( $data['date'] ?? '' ) ) . '">';
		$html .= $this->render_plate( $data );

		if ( '' !== trim( (string) ( $data['content_html'] ?? '' ) ) ) {
			$html .= '<div class="wppl-prose">' . wp_kses_post( (string) $data['content_html'] ) . '</div>';
		}

		if ( ! empty( $data['bands'] ) && ! empty( $data['hours'] ) ) {
			$html .= $this->render_hour_nav( $data );
		}

		if ( empty( $tracks ) ) {
			$html .= Components::empty_state(
				__( 'No tracks in this broadcast yet', 'wordpress-playlists' ),
				__( 'Tracks appear here as soon as the metadata hub reports them.', 'wordpress-playlists' )
			);
		} else {
			$html .= $this->render_sheet( $data );
		}

		if ( ! empty( $data['episodes'] ) && ! empty( $data['archive_url'] ) ) {
			$html .= $this->render_episodes( $data );
		}

		$html .= $this->render_footer( $data );

		return $html . '</div>';
	}

	/**
	 * Show plate: the only dark object on the page.
	 *
	 * @param array $data Playlist model.
	 */
	private function render_plate( array $data ): string {
		$show    = (array) ( $data['show'] ?? array() );
		$name    = (string) ( $show['name'] ?? '' );
		$url     = (string) ( $show['url'] ?? '' );
		$image   = (string) ( $show['image'] ?? '' );
		$count   = (int) ( $data['count'] ?? 0 );
		$span    = (string) ( $data['span'] ?? '' );

		$html  = '<section class="wppl-plate">';
		$html .= '<div class="wppl-plate-art">';

		if ( '' !== $image ) {
			$html .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( (string) ( $show['image_alt'] ?? '' ) ) . '" width="96" height="96" decoding="async" loading="eager" />';
		} else {
			$html .= '<span class="wppl-plate-mono" aria-hidden="true">' . esc_html( (string) ( $show['initials'] ?? Renderer::initials( $name ) ) ) . '</span>';
		}

		$html .= '</div><div class="wppl-plate-body">';

		if ( '' !== $name ) {
			if ( '' !== $url ) {
				$html .= '<h2 class="wppl-plate-title"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></h2>';
			} else {
				$html .= '<h2 class="wppl-plate-title">' . esc_html( $name ) . '</h2>';
			}
		}

		$meta = array();

		if ( '' !== (string) ( $data['date_short'] ?? '' ) ) {
			$meta[] = '<time datetime="' . esc_attr( (string) ( $data['date_iso'] ?? '' ) ) . '">' . esc_html( (string) $data['date_short'] ) . '</time>';
		}

		if ( '' !== $span ) {
			$meta[] = '<span class="wppl-plate-span">' . esc_html( $span ) . '</span>';
		}

		if ( $count > 0 ) {
			$meta[] = '<span class="wppl-plate-count">' . esc_html( Renderer::count_label( $count, '%s track', '%s tracks' ) ) . '</span>';
		}

		if ( ! empty( $meta ) ) {
			$html .= '<p class="wppl-plate-meta">' . implode( '', $meta ) . '</p>';
		}

		if ( ! empty( $show['playlists_url'] ) ) {
			$html .= '<a class="wppl-plate-link" href="' . esc_url( (string) $show['playlists_url'] ) . '">' . esc_html__( 'All playlists for this programme', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a>';
		}

		$html .= '</div>';

		return $html . '</section>';
	}

	/**
	 * Hour jump links for long broadcasts.
	 *
	 * @param array $data Playlist model.
	 */
	private function render_hour_nav( array $data ): string {
		$html  = '<nav class="wppl-hours" aria-label="' . esc_attr__( 'Jump to an hour', 'wordpress-playlists' ) . '">';
		$html .= '<span class="wppl-hours-label">' . esc_html__( 'Hour', 'wordpress-playlists' ) . '</span><ul class="wppl-hours-list">';

		foreach ( (array) $data['hours'] as $hour ) {
			$count_label = Renderer::count_label( (int) $hour['count'], '%s track', '%s tracks' );
			$aria_label  = sprintf( /* translators: 1: hour, 2: number of tracks. */ __( 'Hour %1$s, %2$s', 'wordpress-playlists' ), $hour['hour'], $count_label );

			$html .= '<li><a class="wppl-hour-chip" href="#' . esc_attr( 'hour-' . $hour['hour'] ) . '" aria-label="' . esc_attr( $aria_label ) . '">' . esc_html( $hour['hour'] ) . '<span class="wppl-hour-chip-count" aria-hidden="true">' . esc_html( (string) (int) $hour['count'] ) . '</span></a></li>';
		}

		return $html . '</ul></nav>';
	}

	/**
	 * The track table.
	 *
	 * @param array $data Playlist model.
	 */
	private function render_sheet( array $data ): string {
		$bands  = ! empty( $data['bands'] );
		$tracks = (array) $data['tracks'];
		$count  = (int) $data['count'];

		$html  = '<div class="wppl-sheet">';
		$html .= '<table class="wppl-sheet-table">';
		$html .= '<caption class="wppl-sr">' . esc_html( sprintf( /* translators: 1: number of tracks, 2: show name, 3: date. */ __( 'Track list: %1$s tracks from %2$s on %3$s', 'wordpress-playlists' ), (string) $count, (string) ( $data['show']['name'] ?? '' ), (string) ( $data['date_label'] ?? '' ) ) ) . '</caption>';
		$html .= '<thead><tr>';
		$html .= '<th scope="col" class="wppl-col-time">' . esc_html__( 'Time', 'wordpress-playlists' ) . '</th>';
		$html .= '<th scope="col" class="wppl-col-track">' . esc_html__( 'Artist and track', 'wordpress-playlists' ) . '</th>';
		$html .= '<th scope="col" class="wppl-col-duration">' . esc_html__( 'Length', 'wordpress-playlists' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		$current_hour = '';
		$hour_counts  = array();

		foreach ( $tracks as $candidate ) {
			$candidate_hour = (string) ( $candidate['hour'] ?? '' );

			if ( '' !== $candidate_hour ) {
				$hour_counts[ $candidate_hour ] = (int) ( $hour_counts[ $candidate_hour ] ?? 0 ) + 1;
			}
		}

		foreach ( $tracks as $track ) {
			$hour = (string) ( $track['hour'] ?? '' );

			if ( $bands && '' !== $hour && $hour !== $current_hour ) {
				$current_hour = $hour;
				$hour_count   = (int) ( $hour_counts[ $hour ] ?? 0 );

				$html .= '<tr class="wppl-hour-row" id="hour-' . esc_attr( $hour ) . '">';
				$html .= '<th colspan="3" scope="rowgroup">';
				$html .= '<span class="wppl-hour-number">' . esc_html( $hour ) . '</span>';
				$html .= '<span class="wppl-hour-word">' . esc_html__( 'hour', 'wordpress-playlists' ) . '</span>';
				$html .= '<span class="wppl-hour-total">' . esc_html( Renderer::count_label( $hour_count, '%s track', '%s tracks' ) ) . '</span>';
				$html .= '</th></tr>';
			}

			$time     = (string) ( $track['time'] ?? '' );
			$artist   = (string) ( $track['artist'] ?? '' );
			$artist_url = (string) ( $track['artist_url'] ?? '' );
			$title    = (string) ( $track['title'] ?? '' );
			$duration = (string) ( $track['duration'] ?? '' );

			$html .= '<tr class="wppl-track" data-wppl-time="' . esc_attr( $time ) . '" data-wppl-duration="' . esc_attr( (string) (int) ( $track['duration_seconds'] ?? 0 ) ) . '">';
			$html .= '<td class="wppl-track-time">' . ( '' !== $time ? '<time datetime="' . esc_attr( $time ) . '">' . esc_html( $time ) . '</time>' : '' ) . '</td>';
			$html .= '<td class="wppl-track-main">';

			if ( '' !== $artist ) {
				$local = ! empty( $track['artist_local'] ) ? ' ' . Components::badge( __( 'Local', 'wordpress-playlists' ), 'local' ) : '';

				if ( '' !== $artist_url ) {
					$html .= '<a class="wppl-artist" href="' . esc_url( $artist_url ) . '">' . esc_html( $artist ) . '</a>';
				} else {
					$html .= '<span class="wppl-artist">' . esc_html( $artist ) . '</span>';
				}

				$html .= $local;

				if ( '' !== $title ) {
					$html .= '<span class="wppl-sep wppl-sep--track" aria-hidden="true">—</span>';
				}
			}

			$html .= '<span class="wppl-title">' . esc_html( $title ) . '</span>';
			$html .= '</td>';
			$html .= '<td class="wppl-track-duration">' . ( '' !== $duration ? '<span>' . esc_html( $duration ) . '</span>' : '' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';

		return $html;
	}

	/**
	 * Listen-back section.
	 *
	 * @param array $data Playlist model.
	 */
	private function render_episodes( array $data ): string {
		$archive_url = (string) ( $data['archive_url'] ?? '' );
		$episodes    = (array) $data['episodes'];

		$html  = '<section class="wppl-listen" aria-labelledby="wppl-listen-title">';
		$html .= '<h2 class="wppl-subhead" id="wppl-listen-title">' . esc_html__( 'Listen back', 'wordpress-playlists' ) . '</h2>';
		$html .= '<p class="wppl-listen-note">' . esc_html__( 'This broadcast is available in the archive.', 'wordpress-playlists' ) . '</p>';
		$html .= '<ul class="wppl-episodes">';

		foreach ( $episodes as $episode ) {
			$label  = (string) ( $episode['label'] ?? '' );
			$show   = (string) ( $episode['show'] ?? '' );
			$payload = wp_json_encode(
				array(
					'audio'       => (string) ( $episode['audio'] ?? '' ),
					'title'       => (string) ( $episode['title'] ?? '' ),
					'show'        => $show,
					'presenter'   => (string) ( $episode['presenter'] ?? '' ),
					'duration'    => (int) ( $episode['duration'] ?? 0 ),
					'image'       => (string) ( $episode['image'] ?? '' ),
					'description' => (string) ( $episode['description'] ?? '' ),
				)
			);

			$html .= '<li class="wppl-episode">';
			$html .= '<a class="wppl-episode-link" href="' . esc_url( $archive_url ) . '" data-wppl-episode="' . esc_attr( (string) $payload ) . '">';
			$html .= '<span class="wppl-episode-icon" aria-hidden="true">' . Components::icon( 'play' ) . '</span>';
			$html .= '<span class="wppl-episode-body">';
			$html .= '<span class="wppl-episode-label">' . esc_html( $label ) . '</span>';

			if ( '' !== $show ) {
				$html .= '<span class="wppl-episode-show">' . esc_html( $show ) . '</span>';
			}

			$html .= '</span></a></li>';
		}

		$html .= '</ul>';

		if ( '' !== $archive_url ) {
			$html .= '<p class="wppl-listen-more"><a class="wppl-textlink" href="' . esc_url( $archive_url ) . '">' . esc_html__( 'Open the broadcast archive', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a></p>';
		}

		return $html . '</section>';
	}

	/**
	 * Adjacent navigation and footer links.
	 *
	 * @param array $data Playlist model.
	 */
	private function render_footer( array $data ): string {
		$show = (array) ( $data['show'] ?? array() );

		$html = '<footer class="wppl-playlist-foot">';

		if ( ! empty( $data['prev'] ) || ! empty( $data['next'] ) ) {
			$html .= '<nav class="wppl-adjacent" aria-label="' . esc_attr__( 'Previous and next broadcast', 'wordpress-playlists' ) . '">';

			foreach ( array( 'prev' => 'arrow-left', 'next' => 'arrow-right' ) as $key => $icon ) {
				if ( empty( $data[ $key ] ) ) {
					continue;
				}

				$item  = (array) $data[ $key ];
				$label = 'prev' === $key ? __( 'Previous broadcast', 'wordpress-playlists' ) : __( 'Next broadcast', 'wordpress-playlists' );

				$html .= '<a class="wppl-adjacent-link wppl-adjacent-link--' . esc_attr( $key ) . '" href="' . esc_url( (string) ( $item['url'] ?? '' ) ) . '" rel="' . ( 'prev' === $key ? 'prev' : 'next' ) . '">';
				$html .= ( 'prev' === $key ? Components::icon( $icon ) : '' );
				$html .= '<span class="wppl-adjacent-text"><span class="wppl-adjacent-kicker">' . esc_html( $label ) . '</span><span class="wppl-adjacent-date">' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</span></span>';
				$html .= ( 'next' === $key ? Components::icon( $icon ) : '' );
				$html .= '</a>';
			}

			$html .= '</nav>';
		}

		$html .= '<div class="wppl-foot-links">';

		if ( ! empty( $show['playlists_url'] ) ) {
			$html .= '<a class="wppl-textlink" href="' . esc_url( (string) $show['playlists_url'] ) . '">' . esc_html( sprintf( /* translators: %s: show name. */ __( 'All playlists for %s', 'wordpress-playlists' ), (string) ( $show['name'] ?? '' ) ) ) . '</a>';
		}

		$html .= '<a class="wppl-textlink" href="' . esc_url( (string) ( $data['hub_url'] ?? '' ) ) . '">' . esc_html__( 'All playlists', 'wordpress-playlists' ) . '</a>';
		$html .= Components::copy_link( (string) ( $data['url'] ?? '' ) );
		$html .= '</div>';

		return $html . '</footer>';
	}
}

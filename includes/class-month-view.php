<?php
/**
 * Month View
 *
 * One page per month with every playlist of that month, grouped per day. This
 * replaces the numeric pagination of the old shortcode with a crawlable,
 * stable archive.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Month_View {

	/**
	 * Parse a "YYYY-MM" query value.
	 *
	 * @param string $value Query value.
	 * @return array{year:int,month:int}|null
	 */
	public static function parse( string $value ): ?array {
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $value, $matches ) ) {
			return null;
		}

		$year  = (int) $matches[1];
		$month = (int) $matches[2];

		if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 ) {
			return null;
		}

		return array(
			'year'  => $year,
			'month' => $month,
		);
	}

	/**
	 * Build the month model, or null when the month has no playlists.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return array<string,mixed>|null
	 */
	public function data( int $year, int $month ): ?array {
		$entry = Context::month_entry( $year, $month );

		if ( null === $entry ) {
			return null;
		}

		$from = sprintf( '%04d-%02d-01', $year, $month );
		$to   = gmdate( 'Y-m-t', strtotime( $from ) );

		$ids    = Playlist_Model::ids_between( $from, $to );
		$groups = Playlist_Model::grouped( $ids );

		$tracks = 0;

		foreach ( $groups as $group ) {
			foreach ( $group['items'] as $item ) {
				$tracks += (int) $item['count'];
			}
		}

		$neighbours = Context::month_neighbours( $year, $month );

		return array(
			'year'       => $year,
			'month'      => $month,
			'label'      => (string) $entry['label'],
			'url'        => (string) $entry['url'],
			'groups'     => $groups,
			'playlists'  => count( $ids ),
			'tracks'     => $tracks,
			'prev'       => $neighbours['prev'],
			'next'       => $neighbours['next'],
			'months'     => Context::months(),
			'hub_url'    => Context::hub_url(),
			'artists_url' => Context::artists_index_url(),
		);
	}

	/**
	 * Render the month page.
	 *
	 * @param array $data Month model.
	 */
	public function render( array $data ): string {
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
				'label' => (string) $data['label'],
			),
		);

		$html  = '<div class="wppl wppl-page wppl-month">';
		$html .= Components::breadcrumb( $crumbs );
		$html .= '<header class="wppl-page-head">';
		$html .= '<h1 class="wppl-page-title">' . esc_html__( 'Played tracks', 'wordpress-playlists' ) . ' <span class="wppl-page-title-soft">' . esc_html( (string) $data['label'] ) . '</span></h1>';

		$meta = array();

		if ( (int) $data['playlists'] > 0 ) {
			$meta[] = Renderer::count_label( (int) $data['playlists'], '%s broadcast', '%s broadcasts' );
		}

		if ( (int) $data['tracks'] > 0 ) {
			$meta[] = Renderer::count_label( (int) $data['tracks'], '%s track', '%s tracks' );
		}

		if ( ! empty( $meta ) ) {
			$html .= '<p class="wppl-page-meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}

		$html .= '</header>';

		$html .= $this->render_nav( $data );

		foreach ( $data['groups'] as $group ) {
			$html .= '<section class="wppl-day-group" aria-labelledby="' . esc_attr( (string) $group['id'] ) . '">';
			$html .= Components::day_heading( (string) $group['weekday'], (string) $group['date_label'], count( $group['items'] ), (string) $group['id'] );
			$html .= '<ul class="wppl-rows">';

			foreach ( $group['items'] as $item ) {
				$html .= Components::playlist_row( $item );
			}

			$html .= '</ul></section>';
		}

		$html .= $this->render_nav( $data, true );
		$html .= '<footer class="wppl-page-foot"><a class="wppl-textlink" href="' . esc_url( (string) $data['artists_url'] ) . '">' . esc_html__( 'All artists A–Z', 'wordpress-playlists' ) . Components::icon( 'arrow-right' ) . '</a></footer>';

		return $html . '</div>';
	}

	/**
	 * Month navigation with all months in a disclosure.
	 *
	 * @param array $data  Month model.
	 * @param bool  $after Whether this is the bottom navigation.
	 */
	private function render_nav( array $data, bool $after = false ): string {
		$html  = '<nav class="wppl-month-nav' . ( $after ? ' wppl-month-nav--after' : '' ) . '" aria-label="' . esc_attr__( 'Months', 'wordpress-playlists' ) . '">';

		if ( ! empty( $data['prev'] ) ) {
			$html .= '<a class="wppl-month-step" href="' . esc_url( (string) $data['prev']['url'] ) . '" rel="prev">' . Components::icon( 'arrow-left' ) . '<span>' . esc_html( (string) $data['prev']['label'] ) . '</span></a>';
		} else {
			$html .= '<span class="wppl-month-step is-disabled">' . esc_html__( 'First month', 'wordpress-playlists' ) . '</span>';
		}

		$html .= '<details class="wppl-month-all"><summary>' . esc_html__( 'All months', 'wordpress-playlists' ) . '</summary><ul class="wppl-month-list">';

		foreach ( $data['months'] as $month ) {
			$html .= '<li class="wppl-month-item"><a class="wppl-month-link' . ( (int) $month['year'] === (int) $data['year'] && (int) $month['month'] === (int) $data['month'] ? ' is-current' : '' ) . '" href="' . esc_url( (string) $month['url'] ) . '"><span class="wppl-month-label">' . esc_html( (string) $month['label'] ) . '</span><span class="wppl-month-count">' . esc_html( Renderer::number( (int) $month['count'] ) ) . '</span></a></li>';
		}

		$html .= '</ul></details>';

		if ( ! empty( $data['next'] ) ) {
			$html .= '<a class="wppl-month-step wppl-month-step--next" href="' . esc_url( (string) $data['next']['url'] ) . '" rel="next"><span>' . esc_html( (string) $data['next']['label'] ) . '</span>' . Components::icon( 'arrow-right' ) . '</a>';
		} else {
			$html .= '<span class="wppl-month-step is-disabled">' . esc_html__( 'Latest month', 'wordpress-playlists' ) . '</span>';
		}

		return $html . '</nav>';
	}

	/**
	 * Render the current month route.
	 */
	public function render_current(): void {
		$value = (string) get_query_var( 'wppl_month' );
		$parts = self::parse( $value );

		if ( null === $parts ) {
			return;
		}

		$data = $this->data( $parts['year'], $parts['month'] );

		if ( null === $data ) {
			return;
		}

		echo $this->render( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes internally.
	}
}

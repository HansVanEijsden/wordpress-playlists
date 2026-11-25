<?php
/**
 * Realistic models for the preview harness. Links point back into the preview
 * so the surfaces can be explored; artwork falls back to the station's own
 * programme images or a monogram.
 *
 * All data here is illustrative test data, not a record of real broadcasts.
 * The preview shell labels it as such.
 *
 * @package WordPressPlaylists
 */

use WordPressPlaylists\Components;
use WordPressPlaylists\Renderer;

/**
 * Dutch month names.
 */
function preview_months(): array {
	return array( 1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' );
}

/**
 * Dutch weekday names.
 */
function preview_weekdays(): array {
	return array( 'Sunday' => 'zondag', 'Monday' => 'maandag', 'Tuesday' => 'dinsdag', 'Wednesday' => 'woensdag', 'Thursday' => 'donderdag', 'Friday' => 'vrijdag', 'Saturday' => 'zaterdag' );
}

/**
 * Format a timestamp as a Dutch long date.
 */
function preview_date_label( ?int $timestamp = null ): string {
	$timestamp = $timestamp ?? time();
	$months    = preview_months();
	$weekdays  = preview_weekdays();

	return $weekdays[ date( 'l', $timestamp ) ] . ' ' . date( 'j', $timestamp ) . ' ' . $months[ (int) date( 'n', $timestamp ) ] . ' ' . date( 'Y', $timestamp );
}

/**
 * Format a timestamp as a Dutch short date.
 */
function preview_date_short( ?int $timestamp = null ): string {
	$timestamp = $timestamp ?? time();
	$months    = preview_months();

	return date( 'j', $timestamp ) . ' ' . $months[ (int) date( 'n', $timestamp ) ] . ' ' . date( 'Y', $timestamp );
}

/**
 * Show model.
 */
function preview_show( string $name, string $image = '' ): array {
	return array(
		'name'          => $name,
		'url'           => '?view=show',
		'image'         => $image,
		'image_alt'     => $name,
		'initials'      => Renderer::initials( $name ),
		'playlists_url' => '#show-playlists',
	);
}

/**
 * Playlist card model for the hub and month views.
 */
function preview_card( string $show, string $date, string $span, int $count, string $teaser, string $slug ): array {
	return array(
		'id'         => abs( crc32( $slug ) ),
		'url'        => '?view=playlist',
		'title'      => $date . ' – ' . $show,
		'date'       => date( 'Y-m-d' ),
		'date_short' => $date,
		'weekday'    => 'woensdag',
		'span'       => $span,
		'count'      => $count,
		'artists'    => min( 8, $count ),
		'teaser'     => $teaser,
		'show'       => preview_show( $show ),
	);
}

/**
 * Build track rows around the current time so the now marker is exercised.
 *
 * @param int $count Number of tracks.
 */
function preview_track_times( int $count ): array {
	$times = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$minutes = ( 19 * 60 ) + 5 + ( $i * 4 );
		$times[] = sprintf( '%02d:%02d', (int) floor( $minutes / 60 ), $minutes % 60 );
	}

	return $times;
}

/**
 * Normal playlist model.
 */
function preview_playlist(): array {
	$entries = array(
		array( 'Douwe Bob', 'Born Again', '3:42', false ),
		array( 'Salland Sound Collective', 'Sweet Lil\' Baby', '2:58', true ),
		array( 'Fleetwood Mac', 'Sara', '4:51', false ),
		array( 'Son Mieux', 'Tonight', '3:11', false ),
		array( 'Air', 'All I Need', '4:02', false ),
		array( 'Diesel', 'Sausolito Summernight', '3:29', false ),
		array( 'Bow Wow Wow', 'The Man Mountain', '3:37', false ),
		array( 'Aswad', 'Next To You', '3:44', false ),
	);

	$times  = preview_track_times( count( $entries ) );
	$tracks = array();

	foreach ( $entries as $index => $entry ) {
		$tracks[] = array(
			'time'             => $times[ $index ],
			'hour'             => substr( $times[ $index ], 0, 2 ),
			'artist'           => $entry[0],
			'artist_url'       => '?view=artist',
			'artist_local'     => $entry[3],
			'title'            => $entry[1],
			'duration'         => $entry[2],
			'duration_iso'     => 'PT3M42S',
			'duration_seconds' => 222,
		);
	}

	$span = $times[0] . ' – ' . $times[ count( $times ) - 1 ];

	return array(
		'id'           => 212964,
		'url'          => '?view=playlist',
		'date'         => '2026-11-25',
		'date_iso'     => '2026-11-25T19:31:00+01:00',
		'date_label'   => 'woensdag 25 november 2026',
		'date_short'   => '25 november 2026',
		'weekday'      => 'woensdag',
		'title'        => '25 november 2026 – Sallands Café',
		'show'         => preview_show( 'Sallands Café', 'https://www.salland1.nl/wp-content/uploads/2026/02/Salland1-Programmering-Sallands-Cafe-150x150.jpg' ),
		'count'        => count( $tracks ),
		'span'         => $span,
		'tracks'       => $tracks,
		'hours'        => Renderer::hours( $times ),
		'bands'        => false,
		'is_today'     => true,
		'hub_url'      => '?view=hub',
		'episodes'     => array(
			array(
				'label'       => '19:00 – 20:00',
				'show'        => 'Sallands Café',
				'audio'       => 'https://archive.salland1.nl/wednesday_19-20.m4a',
				'title'       => 'Sallands Café - 25 November 2026 (19:00 - 20:00)',
				'presenter'   => 'Hans',
				'duration'    => 3612,
				'image'       => '',
				'description' => '',
			),
		),
		'archive_url'  => '#archief',
		'prev'         => array( 'url' => '?view=playlist', 'label' => '18 november 2026' ),
		'next'         => null,
		'content_html' => '',
	);
}

/**
 * Long Non Stop broadcast with 385 tracks.
 */
function preview_long_playlist(): array {
	$artists = array( 'Bruno Mars', 'Alex Warren', 'Douwe Bob', 'Taylor Swift', 'Son Mieux', 'Teddy Swims', 'P!nk', 'ABBA', 'Di-Rect', 'U2', 'Salland Sound Collective', 'Rondé', 'Snelle', 'Racoon', 'Davina Michelle' );
	$titles  = array( 'On My Soul', 'Passenger', 'Born Again', 'The Fate of Ophelia', 'Tonight', 'Lose Control', 'Trustfall', 'Gimme! Gimme! Gimme!', 'Through The Barricades', 'One', 'Sweet Lil\' Baby', 'Harder, Better', 'Zomer', 'Oceaan', 'Kwik, Staal en Beton' );

	$tracks = array();
	$times  = array();
	$total  = 385;

	for ( $i = 0; $i < $total; $i++ ) {
		$minutes = (int) floor( ( $i * 1439 ) / $total );
		$hour    = (int) floor( $minutes / 60 );
		$minute  = $minutes % 60;
		$time    = sprintf( '%02d:%02d', $hour, $minute );
		$artist  = $artists[ $i % count( $artists ) ];

		$tracks[] = array(
			'time'             => $time,
			'hour'             => sprintf( '%02d', $hour ),
			'artist'           => $artist,
			'artist_url'       => '?view=artist',
			'artist_local'     => 'Salland Sound Collective' === $artist,
			'title'            => $titles[ $i % count( $titles ) ],
			'duration'         => sprintf( '%d:%02d', 2 + ( $i % 3 ), 10 + ( $i % 49 ) ),
			'duration_iso'     => 'PT3M10S',
			'duration_seconds' => 190,
		);

		$times[] = $time;
	}

	return array(
		'id'           => 212980,
		'url'          => '?view=long',
		'date'         => '2026-11-25',
		'date_iso'     => '2026-11-25T19:31:00+01:00',
		'date_label'   => 'woensdag 25 november 2026',
		'date_short'   => '25 november 2026',
		'weekday'      => 'woensdag',
		'title'        => '25 november 2026 – Non Stop',
		'show'         => preview_show( 'Non Stop' ),
		'count'        => $total,
		'span'         => '00:00 – 23:59',
		'tracks'       => $tracks,
		'hours'        => Renderer::hours( $times ),
		'bands'        => true,
		'is_today'     => true,
		'hub_url'      => '?view=hub',
		'episodes'     => array(
			array( 'label' => '00:00 – 01:00', 'show' => 'Non Stop', 'audio' => 'https://archive.salland1.nl/wednesday_00-01.m4a', 'title' => 'Non Stop - 25 November 2026 (00:00 - 01:00)', 'presenter' => '', 'duration' => 3612, 'image' => '', 'description' => '' ),
			array( 'label' => '01:00 – 02:00', 'show' => 'Non Stop', 'audio' => 'https://archive.salland1.nl/wednesday_01-02.m4a', 'title' => 'Non Stop - 25 November 2026 (01:00 - 02:00)', 'presenter' => '', 'duration' => 3612, 'image' => '', 'description' => '' ),
		),
		'archive_url'  => '#archief',
		'prev'         => array( 'url' => '?view=long', 'label' => '24 november 2026' ),
		'next'         => array( 'url' => '?view=long', 'label' => '26 november 2026' ),
		'content_html' => '',
	);
}

/**
 * Artist model.
 */
function preview_artist( string $which = 'douwe' ): array {
	if ( 'local' === $which ) {
		$name   = 'Salland Sound Collective';
		$plays  = 7;
		$tracks = array(
			array( 'anchor' => 'track-sweet-lil-baby', 'title' => 'Sweet Lil\' Baby', 'plays' => 7 ),
		);
		$local       = true;
		$bio         = 'Een fictief collectief uit de Sallandse klei: vier muzikanten die liedjes maken over het land, de rivier en de mensen die er wonen. Deze biografie is testdata.';
		$items       = 2;
		$playlists   = 2;
		$total_pages = 1;
	} else {
		$name   = 'Douwe Bob';
		$plays  = 471;
		$tracks = array(
			array( 'anchor' => 'track-nothing-at-all', 'title' => 'Nothing At All', 'plays' => 48 ),
			array( 'anchor' => 'track-born-again', 'title' => 'Born Again', 'plays' => 44 ),
			array( 'anchor' => 'track-sweet-sunshine', 'title' => 'Sweet Sunshine', 'plays' => 41 ),
			array( 'anchor' => 'track-i-got-you', 'title' => 'I Got You', 'plays' => 39 ),
			array( 'anchor' => 'track-hold-me', 'title' => 'Hold Me', 'plays' => 35 ),
			array( 'anchor' => 'track-multicolor-angel', 'title' => 'Multicolor Angel', 'plays' => 33 ),
			array( 'anchor' => 'track-slow-down', 'title' => 'Slow Down', 'plays' => 31 ),
			array( 'anchor' => 'track-when-the-sun-goes-down', 'title' => 'When The Sun Goes Down', 'plays' => 28 ),
			array( 'anchor' => 'track-water', 'title' => 'Water', 'plays' => 24 ),
			array( 'anchor' => 'track-calm-after-the-storm', 'title' => 'Calm After The Storm', 'plays' => 22 ),
			array( 'anchor' => 'track-shine', 'title' => 'Shine', 'plays' => 19 ),
			array( 'anchor' => 'track-lucky', 'title' => 'Lucky', 'plays' => 17 ),
		);
		$local       = false;
		$bio         = 'Zanger en liedjesschrijver. Deze preview toont de biografie- en linksectie met testdata.';
		$items       = 3;
		$playlists   = 232;
		$total_pages = 20;
	}

	if ( $local ) {
		$items_data = array(
			array(
				'card'  => preview_card( 'Sallands Café', '25 november 2026', '19:05 – 19:33', 8, '', 'local-25' ),
				'plays' => array(
					array( 'time' => '19:09', 'title' => 'Sweet Lil\' Baby' ),
				),
			),
			array(
				'card'  => preview_card( 'Goedemorgen Salland', '18 november 2026', '09:02 – 11:00', 18, '', 'local-18' ),
				'plays' => array(
					array( 'time' => '09:14', 'title' => 'Sweet Lil\' Baby' ),
				),
			),
		);
	} else {
		$items_data = array(
			array(
				'card'  => preview_card( 'Sallands Café', '25 november 2026', '19:05 – 19:33', 8, '', 'douwe-25' ),
				'plays' => array(
					array( 'time' => '19:05', 'title' => 'Born Again' ),
				),
			),
			array(
				'card'  => preview_card( 'Goedemorgen Salland', '18 november 2026', '09:02 – 11:00', 18, '', 'douwe-18' ),
				'plays' => array(
					array( 'time' => '09:12', 'title' => 'Nothing At All' ),
					array( 'time' => '09:27', 'title' => 'Born Again' ),
				),
			),
			array(
				'card'  => preview_card( 'Avond aan de IJssel', '11 november 2026', '19:04 – 20:00', 12, '', 'douwe-11' ),
				'plays' => array(
					array( 'time' => '19:24', 'title' => 'Sweet Sunshine' ),
				),
			),
		);
	}

	$summary = $total_pages > 1
		? sprintf( 'Uitzendingen %s–%s van %s', number_format_i18n( 1 ), number_format_i18n( $items ), number_format_i18n( $playlists ) )
		: '';

	return array(
		'id'          => 123,
		'name'        => $name,
		'url'         => '?view=' . ( $local ? 'local' : 'artist' ),
		'plays'       => $plays,
		'tracks'      => $tracks,
		'track_count' => count( $tracks ),
		'playlists'   => $playlists,
		'first'       => '26 maart 2026',
		'last'        => '25 november 2026',
		'local'       => $local,
		'image'       => '',
		'bio'         => '<p>' . $bio . '</p>',
		'socials'     => array(
			array( 'label' => 'Website', 'url' => '#' ),
			array( 'label' => 'Instagram', 'url' => '#' ),
			array( 'label' => 'Spotify', 'url' => '#' ),
		),
		'items'       => $items_data,
		'paged'       => 1,
		'total_pages' => $total_pages,
		'pager'       => Components::pagination( 1, $total_pages, '?view=artist&page=%#%', $summary ),
		'hub_url'     => '?view=hub',
		'artists_url' => '?view=index',
	);
}

/**
 * Hub model.
 */
function preview_hub(): array {
	$today = preview_date_short();
	$today_items = array(
		preview_card( 'Sallands Café', $today, '22:41 – 23:31', 8, 'Douwe Bob – Born Again · Fleetwood Mac – Sara', 'cafe' ),
		preview_card( 'Thuiskomen in Salland', $today, '21:03 – 22:00', 12, 'Son Mieux – Tonight · Racoon – Oceaan', 'thuis' ),
		preview_card( 'Goedemiddag Salland', $today, '15:02 – 17:00', 26, 'P!nk – Trustfall · ABBA – Gimme! Gimme!', 'middag' ),
		preview_card( 'Club Sandwich', $today, '13:01 – 14:00', 11, 'Di-Rect – Through The Barricades', 'club' ),
		preview_card( 'Goedemorgen Salland', $today, '09:00 – 12:00', 31, 'Taylor Swift – The Fate of Ophelia', 'morgen' ),
		preview_card( 'Non Stop', $today, '00:00 – 23:59', 385, 'De hele dag non-stop muziek', 'nonstop' ),
	);

	$yesterday_label = preview_date_short( strtotime( '-1 day' ) );
	$earlier_label   = preview_date_short( strtotime( '-2 days' ) );

	$yesterday = array(
		'date'       => date( 'Y-m-d', strtotime( '-1 day' ) ),
		'weekday'    => preview_weekdays()[ date( 'l', strtotime( '-1 day' ) ) ],
		'date_label' => $yesterday_label,
		'id'         => 'day-yesterday',
		'items'      => array(
			preview_card( 'Avond aan de IJssel', $yesterday_label, '19:04 – 20:00', 9, 'Alex Warren – Passenger', 'aai' ),
			preview_card( 'Pascal\'s Avonduren', $yesterday_label, '20:02 – 22:00', 18, 'Bruno Mars – On My Soul', 'pascal' ),
			preview_card( 'Salland Wordt Wakker', $yesterday_label, '06:00 – 09:00', 28, 'Teddy Swims – Lose Control', 'wakker' ),
		),
	);

	$earlier = array(
		'date'       => date( 'Y-m-d', strtotime( '-2 days' ) ),
		'weekday'    => preview_weekdays()[ date( 'l', strtotime( '-2 days' ) ) ],
		'date_label' => $earlier_label,
		'id'         => 'day-earlier',
		'items'      => array(
			preview_card( 'De Nachtuil', $earlier_label, '23:00 – 23:59', 10, 'Rondé – Harder, Better', 'nachtuil' ),
			preview_card( 'Goedemiddag Salland', $earlier_label, '15:02 – 17:00', 25, 'Snelle – Zomer', 'middag2' ),
		),
	);

	$months = array();
	$labels = preview_months();

	foreach ( array( array( (int) date( 'Y' ), (int) date( 'n' ), 204 ), array( 2026, 10, 252 ), array( 2026, 9, 250 ), array( 2026, 8, 251 ), array( 2026, 7, 270 ), array( 2026, 6, 256 ), array( 2026, 5, 268 ), array( 2026, 4, 258 ), array( 2026, 3, 38 ) ) as $month ) {
		$months[] = array(
			'year'  => $month[0],
			'month' => $month[1],
			'count' => $month[2],
			'label' => $labels[ $month[1] ] . ' ' . $month[0],
			'url'   => '?view=month',
		);
	}

	return array(
		'today'       => array(
			array(
				'date'       => date( 'Y-m-d' ),
				'weekday'    => preview_weekdays()[ date( 'l' ) ],
				'date_label' => preview_date_short(),
				'id'         => 'day-today',
				'items'      => $today_items,
			),
		),
		'week'        => array( $yesterday, $earlier ),
		'top'         => array(
			array( 'name' => 'Alex Warren', 'plays' => 18, 'url' => '?view=artist' ),
			array( 'name' => 'Bruno Mars', 'plays' => 17, 'url' => '?view=artist' ),
			array( 'name' => 'Douwe Bob', 'plays' => 15, 'url' => '?view=artist' ),
			array( 'name' => 'Taylor Swift', 'plays' => 14, 'url' => '?view=artist' ),
			array( 'name' => 'Son Mieux', 'plays' => 13, 'url' => '?view=artist' ),
			array( 'name' => 'Rondé', 'plays' => 11, 'url' => '?view=artist' ),
			array( 'name' => 'Davina Michelle', 'plays' => 10, 'url' => '?view=artist' ),
			array( 'name' => 'Salland Sound Collective', 'plays' => 6, 'url' => '?view=local' ),
		),
		'local'       => array(
			array( 'name' => 'Salland Sound Collective', 'plays' => 7, 'url' => '?view=local', 'image' => '', 'bio' => '', 'id' => 1 ),
			array( 'name' => 'Bastiaan Baas', 'plays' => 5, 'url' => '?view=local', 'image' => '', 'bio' => '', 'id' => 2 ),
			array( 'name' => 'De Ijsselbruggers', 'plays' => 3, 'url' => '?view=local', 'image' => '', 'bio' => '', 'id' => 3 ),
		),
		'months'      => $months,
		'today_label' => preview_date_label(),
		'today_count' => 6,
		'week_count'  => 5,
		'total'       => 2047,
		'search_url'  => '?view=index',
		'artists_url' => '?view=index',
		'hub_url'     => '?view=hub',
	);
}

/**
 * Month model with a realistic number of days.
 */
function preview_month(): array {
	$groups = array();

	for ( $day = 0; $day < 12; $day++ ) {
		$timestamp = strtotime( '-' . $day . ' days' );
		$label     = preview_date_short( $timestamp );
		$items     = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$shows = array( 'Sallands Café', 'Avond aan de IJssel', 'Goedemiddag Salland' );

			$items[] = preview_card( $shows[ $i ], $label, ( 19 + $i ) . ':04 – ' . ( 20 + $i ) . ':00', 9 + ( $i * 4 ), 'Douwe Bob – Born Again', 'month-' . $day . '-' . $i );
		}

		$groups[] = array(
			'date'       => date( 'Y-m-d', $timestamp ),
			'weekday'    => preview_weekdays()[ date( 'l', $timestamp ) ],
			'date_label' => $label,
			'id'         => 'day-' . date( 'Ymd', $timestamp ),
			'items'      => $items,
		);
	}

	$hub    = preview_hub();
	$months = $hub['months'];

	return array(
		'year'        => (int) date( 'Y' ),
		'month'       => (int) date( 'n' ),
		'label'       => preview_months()[ (int) date( 'n' ) ] . ' ' . date( 'Y' ),
		'url'         => '?view=month',
		'groups'      => $groups,
		'playlists'   => 204,
		'tracks'      => 7900,
		'prev'        => $months[1],
		'next'        => null,
		'months'      => $months,
		'hub_url'     => '?view=hub',
		'artists_url' => '?view=index',
	);
}

/**
 * Artist index model.
 */
function preview_index(): array {
	$groups = array(
		'a' => array( array( 'name' => 'ABBA', 'plays' => 359, 'url' => '?view=artist', 'local' => false, 'id' => 1 ), array( 'name' => 'Air', 'plays' => 122, 'url' => '?view=artist', 'local' => false, 'id' => 2 ), array( 'name' => 'Alex Warren', 'plays' => 608, 'url' => '?view=artist', 'local' => false, 'id' => 3 ), array( 'name' => 'Aswad', 'plays' => 74, 'url' => '?view=artist', 'local' => false, 'id' => 4 ) ),
		'b' => array( array( 'name' => 'Bastiaan Baas', 'plays' => 5, 'url' => '?view=local', 'local' => true, 'id' => 5 ), array( 'name' => 'Bruno Mars', 'plays' => 634, 'url' => '?view=artist', 'local' => false, 'id' => 6 ) ),
		'd' => array( array( 'name' => 'Davina Michelle', 'plays' => 402, 'url' => '?view=artist', 'local' => false, 'id' => 7 ), array( 'name' => 'Di-Rect', 'plays' => 353, 'url' => '?view=artist', 'local' => false, 'id' => 8 ), array( 'name' => 'Douwe Bob', 'plays' => 471, 'url' => '?view=artist', 'local' => false, 'id' => 9 ) ),
		's' => array( array( 'name' => 'Salland Sound Collective', 'plays' => 7, 'url' => '?view=local', 'local' => true, 'id' => 10 ), array( 'name' => 'Son Mieux', 'plays' => 423, 'url' => '?view=artist', 'local' => false, 'id' => 11 ) ),
	);

	$counts = array();

	foreach ( range( 'a', 'z' ) as $letter ) {
		$counts[ $letter ] = 80 + ( ord( $letter ) % 90 );
	}

	$counts['0-9'] = 12;

	$local = array(
		array( 'name' => 'Salland Sound Collective', 'plays' => 7, 'url' => '?view=local', 'image' => '', 'bio' => '', 'id' => 1 ),
		array( 'name' => 'Bastiaan Baas', 'plays' => 5, 'url' => '?view=local', 'image' => '', 'bio' => '', 'id' => 2 ),
		array( 'name' => 'De Ijsselbruggers', 'plays' => 3, 'url' => '?view=local', 'image' => '', 'bio' => '', 'id' => 3 ),
	);

	return array(
		'groups'  => $groups,
		'counts'  => $counts,
		'total'   => 3180,
		'local'   => $local,
		'totals'  => array( 'artists' => 3180, 'local' => 3, 'plays' => 79092 ),
		'url'     => '?view=index',
		'hub_url' => '?view=hub',
	);
}

/**
 * Letter page model with a realistic count.
 */
function preview_letter(): array {
	$names = array(
		'Davina Michelle', 'De Bankzitters', 'De Dijk', 'De Jeugd van Tegenwoordig', 'De Staat',
		'Di-Rect', 'Diesel', 'Dizzee Rascal', 'Doe Maar', 'Dotan', 'Douwe Bob', 'Dua Lipa',
		'Duncan Laurence', 'Dusty Springfield', 'DVBBS', 'Dylan', 'Danny Vera', 'Daniël Lohues',
		'De Poema\'s', 'De Kast', 'De Heideroosjes', 'Dick Dale', 'Donna Summer', 'Dolly Parton',
	);

	$items = array();

	foreach ( $names as $index => $name ) {
		$items[] = array(
			'name'  => $name,
			'plays' => 300 - ( $index * 9 ),
			'url'   => '?view=artist',
			'local' => false,
			'id'    => 100 + $index,
		);
	}

	$index  = preview_index();
	$letter = 'd';

	return array(
		'letter'      => $letter,
		'label'       => 'D',
		'items'       => $items,
		'count'       => count( $items ),
		'prev'        => 'b',
		'next'        => 's',
		'all'         => array_merge( range( 'a', 'z' ), array( '0-9' ) ),
		'counts'      => $index['counts'],
		'url'         => '?view=letter',
		'artists_url' => '?view=index',
		'hub_url'     => '?view=hub',
	);
}

/**
 * Search results model, in production sort order.
 */
function preview_search(): array {
	return array(
		'query'  => 'sweet',
		'groups' => array(
			array( 'id' => 9, 'name' => 'Douwe Bob', 'url' => '?view=artist', 'plays' => 471, 'local' => false, 'matched' => array( 'Sweet Sunshine' ) ),
			array( 'id' => 12, 'name' => 'The Sweet', 'url' => '?view=artist', 'plays' => 14, 'local' => false, 'matched' => array( 'Ballroom Blitz' ) ),
			array( 'id' => 10, 'name' => 'Salland Sound Collective', 'url' => '?view=local', 'plays' => 7, 'local' => true, 'matched' => array( 'Sweet Lil\' Baby' ) ),
		),
	);
}

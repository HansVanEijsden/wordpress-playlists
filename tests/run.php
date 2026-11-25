<?php
/**
 * Dependency-free test runner for the parts that do not need WordPress.
 *
 * Run from the repository root:
 *
 *     php tests/run.php
 *
 * The preview harness supplies the small WordPress surface the views use, so
 * these tests exercise the real renderer classes, not copies of them.
 *
 * @package WordPressPlaylists
 */

declare( strict_types=1 );

error_reporting( E_ALL );

set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ): void {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

require_once __DIR__ . '/../preview/bootstrap.php';
require_once __DIR__ . '/../preview/fixtures.php';
use WordPressPlaylists\Artist_Index;
use WordPressPlaylists\Components;
use WordPressPlaylists\Month_View;
use WordPressPlaylists\Palette;
use WordPressPlaylists\Renderer;

$GLOBALS['tests_run']    = 0;
$GLOBALS['tests_failed'] = 0;

function test( string $name, callable $callback ): void {
	$GLOBALS['tests_run']++;

	try {
		$callback();
		echo "  ok   " . $name . "\n";
	} catch ( Throwable $error ) {
		$GLOBALS['tests_failed']++;
		echo "  FAIL " . $name . "\n";
		echo "       " . $error->getMessage() . "\n";
	}
}

function assert_same( $expected, $actual, string $message = '' ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			( '' !== $message ? $message . ': ' : '' ) . 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true )
		);
	}
}

function assert_true( $value, string $message = '' ): void {
	if ( ! $value ) {
		throw new RuntimeException( '' !== $message ? $message : 'expected a truthy value' );
	}
}

function assert_contains( string $needle, string $haystack, string $message = '' ): void {
	if ( false === strpos( $haystack, $needle ) ) {
		throw new RuntimeException( ( '' !== $message ? $message . ': ' : '' ) . 'missing "' . $needle . '"' );
	}
}

function assert_not_contains( string $needle, string $haystack, string $message = '' ): void {
	if ( false !== strpos( $haystack, $needle ) ) {
		throw new RuntimeException( ( '' !== $message ? $message . ': ' : '' ) . 'unexpected "' . $needle . '"' );
	}
}

echo "Loading\n";

test(
	'every plugin class file loads in dependency order',
	static function (): void {
		$files = array(
			'class-logger.php',
			'class-palette.php',
			'class-renderer.php',
			'class-components.php',
			'class-cache.php',
			'class-station.php',
			'class-settings.php',
			'class-taxonomy.php',
			'class-artist-index.php',
			'class-context.php',
			'class-metadata-client.php',
			'class-playlist-manager.php',
			'class-playlist-model.php',
			'class-archive-client.php',
			'class-playlist-view.php',
			'class-hub-view.php',
			'class-month-view.php',
			'class-search.php',
			'class-artist-view.php',
			'class-assets.php',
			'class-routes.php',
			'class-seo.php',
			'class-shortcodes.php',
			'class-term-fields.php',
			'class-cli.php',
			'class-rest-controller.php',
			'class-gettext.php',
			'class-plugin.php',
		);

		foreach ( $files as $file ) {
			$path = WORDPRESS_PLAYLISTS_DIR . 'includes/' . $file;

			if ( ! file_exists( $path ) ) {
				throw new RuntimeException( 'missing ' . $file );
			}

			require_once $path;
		}

		assert_true( class_exists( 'WordPressPlaylists\\Plugin' ) );
		assert_true( class_exists( 'WordPressPlaylists\\Artist_Index' ) );
	}
);

echo "Palette\n";

test(
	'hex colours are sanitised',
	static function (): void {
		assert_same( '#aabbcc', Palette::sanitize( '#ABC' ) );
		assert_same( '#e50526', Palette::sanitize( '#E50526' ) );
		assert_same( Palette::DEFAULT_ACCENT, Palette::sanitize( 'red' ) );
	}
);

test(
	'accent companions clear AA on white and on the tint',
	static function (): void {
		foreach ( array( '#e50526', '#283593', '#ffff00', '#000000', '#00a651' ) as $accent ) {
			$palette = Palette::derive( $accent );
			$ink     = Palette::hex_to_rgb( $palette['ink'] );
			$white   = Palette::hex_to_rgb( Palette::WHITE );

			assert_true( Palette::contrast( $ink, $white ) >= 4.5, 'ink on white for ' . $accent );

			$tint = Palette::tint( Palette::hex_to_rgb( $accent ), 0.09 );
			assert_true( Palette::contrast( $ink, $tint ) >= 4.5, 'ink on the tint for ' . $accent );
		}
	}
);

test(
	'text on a filled accent picks the legible colour',
	static function (): void {
		assert_same( Palette::WHITE, Palette::on_accent( '#e50526' ) );
		assert_same( Palette::INK, Palette::on_accent( '#ffff00' ) );
	}
);

echo "Renderer\n";

test(
	'time helpers normalise and reject invalid values',
	static function (): void {
		assert_same( '09:05', Renderer::time( '9:05' ) );
		assert_same( '', Renderer::time( '24:00' ) );
		assert_same( '', Renderer::time( 'kwart over' ) );
		assert_same( 1164, Renderer::minutes( '19:24' ) );
		assert_same( null, Renderer::minutes( '' ) );
		assert_same( '19:05', Renderer::span( '19:05', '19:05' ) );
		assert_same( '19:05 – 19:51', Renderer::span( '19:05', '19:51' ) );
	}
);

test(
	'durations have a display and an ISO form',
	static function (): void {
		assert_same( '3:07', Renderer::duration( '3', '7' ) );
		assert_same( '', Renderer::duration( '', '' ) );
		assert_same( 'PT3M7S', Renderer::duration_iso( 3, 7 ) );
		assert_same( '', Renderer::duration_iso( 0, 0 ) );
	}
);

test(
	'monograms and plurals',
	static function (): void {
		assert_same( 'DB', Renderer::initials( 'Douwe Bob' ) );
		assert_same( 'AB', Renderer::initials( 'ABBA' ) );
		assert_same( '1 nummer', Renderer::count_label( 1, '%s track', '%s tracks' ) );
		assert_same( '5 nummers', Renderer::count_label( 5, '%s track', '%s tracks' ) );
	}
);

test(
	'hours are grouped in order with counts',
	static function (): void {
		$hours = Renderer::hours( array( '19:05', '19:31', '20:02' ) );

		assert_same( 2, count( $hours ) );
		assert_same( '19', $hours[0]['hour'] );
		assert_same( 2, $hours[0]['count'] );
		assert_same( 'hour-19', $hours[0]['key'] );
	}
);

test(
	'excerpts cut on a word boundary',
	static function (): void {
		$excerpt = Renderer::excerpt( 'Een lange zin over muziek en radio uit Salland.', 20 );

		assert_true( strlen( $excerpt ) <= 24 );
		assert_true( '…' === substr( $excerpt, -3 ) || '.' === substr( $excerpt, -1 ) );
	}
);

echo "Month route\n";

test(
	'month values parse strictly',
	static function (): void {
		assert_same( array( 'year' => 2026, 'month' => 11 ), Month_View::parse( '2026-11' ) );
		assert_same( null, Month_View::parse( '2026-13' ) );
		assert_same( null, Month_View::parse( '2026-1' ) );
		assert_same( null, Month_View::parse( 'november' ) );
	}
);

echo "Artist index\n";

test(
	'artist and track keys fold case, accents and spacing',
	static function (): void {
		assert_same( 'salland sound collective', Artist_Index::normalize_key( '  Salland   Sound Collective ' ) );
		assert_same( 'sallands cafe', Artist_Index::normalize_key( 'Sallands Café' ) );
		assert_same( 'sweet lil baby', Artist_Index::track_key( "Sweet Lil' Baby" ) );
	}
);

test(
	'played entries include legacy values but not queued ones',
	static function (): void {
		assert_true( Artist_Index::is_played( array( 'playlist_entry_song' => 'x' ) ) );
		assert_true( Artist_Index::is_played( array( 'playlist_entry_status' => 'played' ) ) );
		assert_true( ! Artist_Index::is_played( array( 'playlist_entry_status' => 'queued' ) ) );
	}
);

echo "Components\n";

test(
	'output is escaped',
	static function (): void {
		$row = Components::playlist_row(
			array(
				'url'    => '/x',
				'span'   => '19:05 – 19:51',
				'count'  => 1,
				'teaser' => '<script>alert(1)</script>',
				'show'   => array( 'name' => '<b>Sallands Café</b>' ),
			)
		);

		assert_not_contains( '<script>', $row );
		assert_not_contains( '<b>Sallands Café</b>', $row );
		assert_contains( '&lt;script&gt;', $row );
	}
);

test(
	'row times split into a start and end span',
	static function (): void {
		$row = Components::playlist_row(
			array(
				'url'   => '/x',
				'span'  => '19:05 – 19:51',
				'count' => 1,
				'show'  => array( 'name' => 'Show' ),
			)
		);

		assert_contains( 'wppl-row-start">19:05', $row );
		assert_contains( 'wppl-row-end"> – 19:51', $row );
	}
);

test(
	'the copy control is hidden until JavaScript enhances it',
	static function (): void {
		$copy = Components::copy_link( '/playlist/x/' );

		assert_contains( 'data-wppl-copy="/playlist/x/" hidden', $copy );
	}
);

test(
	'pagination steps between broadcasts and shows a count',
	static function (): void {
		$pager = Components::pagination( 2, 20, '/artiest/x/page/%#%/', 'Uitzendingen 13–24 van 232' );

		assert_contains( 'Uitzendingen 13–24 van 232', $pager );
		assert_contains( 'rel="prev"', $pager );
		assert_contains( 'rel="next"', $pager );
		assert_contains( '/artiest/x/', $pager );
		assert_contains( '/artiest/x/page/3/', $pager );
		assert_contains( 'Nieuwere uitzendingen', $pager );
		assert_contains( 'Oudere uitzendingen', $pager );
		assert_not_contains( 'aria-current="page"', $pager );
	}
);

echo "Views\n";

test(
	'a normal playlist renders its plate, sheet and episodes',
	static function (): void {
		$html = ( new WordPressPlaylists\Playlist_View() )->render( preview_playlist() );

		assert_same( 8, substr_count( $html, 'class="wppl-track"' ) );
		assert_contains( 'data-wppl-sheet', $html );
		assert_contains( 'data-wppl-date="2026-11-25"', $html );
		assert_contains( 'data-wppl-duration="222"', $html );
		assert_contains( 'wppl-track', $html );
		assert_contains( 'wppl-badge--local', $html );
		assert_contains( '<caption', $html );
		assert_contains( 'data-wppl-episode', $html );
		assert_not_contains( 'wppl-hour-row', $html );
	}
);

test(
	'a long broadcast renders bands, hours and every track',
	static function (): void {
		$html = ( new WordPressPlaylists\Playlist_View() )->render( preview_long_playlist() );

		assert_same( 385, substr_count( $html, 'data-wppl-time=' ) );
		assert_same( 24, substr_count( $html, 'wppl-hour-row' ) );
		assert_same( 24, substr_count( $html, 'class="wppl-hour-chip"' ) );
		assert_contains( 'id="hour-23"', $html );
		assert_contains( 'aria-label="Uur 00, 17 nummers"', $html );
	}
);

test(
	'the empty playlist has a calm empty state',
	static function (): void {
		$playlist           = preview_playlist();
		$playlist['tracks'] = array();
		$playlist['hours']  = array();
		$playlist['count']  = 0;
		$playlist['span']   = '';
		$playlist['bands']  = false;

		$html = ( new WordPressPlaylists\Playlist_View() )->render( $playlist );

		assert_contains( 'Nog geen nummers in deze uitzending', $html );
		assert_not_contains( 'wppl-sheet-table', $html );
	}
);

test(
	'the hub shows search, today, week, artists, locals and months',
	static function (): void {
		$html = ( new WordPressPlaylists\Hub_View() )->render( preview_hub() );

		assert_contains( 'wppl-hub-search', $html );
		assert_contains( 'Vandaag,', $html );
		assert_contains( 'Recente uitzendingen', $html );
		assert_contains( 'Meest gedraaid deze week', $html );
		assert_contains( 'Artiesten uit de regio', $html );
		assert_contains( 'Blader per maand', $html );
		assert_contains( 'wppl-month-link', $html );
		assert_not_contains( 'offset=', $html );
	}
);

test(
	'the month page groups per day and navigates months',
	static function (): void {
		$html = ( new WordPressPlaylists\Month_View() )->render( preview_month() );

		assert_contains( 'Afgespeelde nummers', $html );
		assert_same( 36, substr_count( $html, 'class="wppl-row"' ) );
		assert_contains( 'Alle maanden', $html );
		assert_contains( 'november 2026', $html );
	}
);

test(
	'the artist page renders tracks, plays and pagination',
	static function (): void {
		$html = ( new WordPressPlaylists\Artist_View() )->render_archive( preview_artist() );

		assert_contains( '<h1', $html );
		assert_contains( 'Douwe Bob', $html );
		assert_contains( 'Nummers van deze artiest', $html );
		assert_contains( 'wppl-play', $html );
		assert_contains( 'wppl-pager', $html );
		assert_contains( 'Uitzendingen 1–3 van 232', $html );
		assert_contains( 'Oudere uitzendingen', $html );
	}
);

test(
	'the local artist is marked and keeps its biography',
	static function (): void {
		$html = ( new WordPressPlaylists\Artist_View() )->render_archive( preview_artist( 'local' ) );

		assert_contains( 'wppl-badge--local', $html );
		assert_contains( 'Sallandse klei', $html );
		assert_contains( 'Sweet Lil&#039; Baby', $html );
	}
);

test(
	'the artist index lists letters and local artists',
	static function (): void {
		$html = ( new WordPressPlaylists\Artist_View() )->render_index( preview_index() );

		assert_contains( '3.180 artiesten', $html );
		assert_contains( 'Artiesten uit de regio', $html );
		assert_contains( 'Blader op eerste letter', $html );
		assert_contains( 'Zoek een artiest of een nummer', $html );
	}
);

test(
	'search results show the matched track titles',
	static function (): void {
		$html = ( new WordPressPlaylists\Artist_View() )->render_search( preview_search() );

		assert_contains( 'Zoekresultaten', $html );
		assert_contains( 'Salland Sound Collective', $html );
		assert_contains( 'Sweet Lil&#039; Baby', $html );
		assert_contains( 'aria-live="polite"', $html );
	}
);

test(
	'the embed card has an accessible play count',
	static function (): void {
		$artist = preview_artist();
		$html   = ( new WordPressPlaylists\Artist_View() )->render_embed(
			array(
				'name'   => $artist['name'],
				'url'    => '/artiest/douwe-bob/',
				'plays'  => 471,
				'local'  => false,
				'image'  => '',
				'tracks' => array_slice( $artist['tracks'], 0, 3 ),
			)
		);

		assert_contains( 'wppl-embed', $html );
		assert_contains( '471 keer gedraaid', $html );
		assert_contains( 'keer gedraaid</span></span>', $html );
	}
);

echo "\n" . $GLOBALS['tests_run'] . ' tests, ' . $GLOBALS['tests_failed'] . " failed.\n";

exit( $GLOBALS['tests_failed'] > 0 ? 1 : 0 );

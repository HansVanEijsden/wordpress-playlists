<?php
/**
 * Move tracks between two of today's playlists (maintenance tool, CLI only).
 *
 * Recovery tool for tracks that were appended to the wrong show's playlist
 * (e.g. before a fix was deployed, so a brand-new show's tracks went into the
 * previous show's playlist). Moves every entry whose start time
 * (playlist_entry_comments, "H:i") is >= the cutoff from the source show's
 * playlist into the target show's playlist, preserving chronological order.
 *
 * USAGE (on the server, from the plugin directory):
 *
 *   # Show what would be moved (no changes):
 *   php tools/move-tracks-between-playlists.php "Sander's Late Night Classics" "Avond aan de IJssel" "23:00"
 *
 *   # Actually move:
 *   php tools/move-tracks-between-playlists.php "Sander's Late Night Classics" "Avond aan de IJssel" "23:00" --commit
 *
 * SAFETY:
 *   - CLI only: refuses to run when reached over HTTP.
 *   - Dry-run by default; pass --commit to make changes.
 *   - Only entries with a start time >= "HH:MM" are moved; entries without a
 *     start time are never touched.
 *   - Delete this file from the server after use if you prefer.
 *
 * @package WordPressPlaylists
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	exit( 'This tool is CLI only.' );
}

// Same CLI bootstrap hardening as merge-duplicate-playlists.php: some hardened
// hosts strip FS_CHMOD_* constants or rely on FS_METHOD, and plugins that
// write files during load (e.g. the Bluesky logger) fatal otherwise.
if ( ! defined( 'WP_USE_THEMES' ) ) {
	define( 'WP_USE_THEMES', false ); // CLI: never load theme templates.
}
if ( ! defined( 'FS_CHMOD_DIR' ) ) {
	define( 'FS_CHMOD_DIR', 0755 );
}
if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}
if ( ! defined( 'FS_METHOD' ) ) {
	define( 'FS_METHOD', 'direct' );
}

$root = __DIR__;
for ( $i = 0; $i < 8; $i++ ) {
	$root = dirname( $root );
	if ( file_exists( $root . '/wp-load.php' ) ) {
		require $root . '/wp-load.php';
		break;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Could not locate wp-load.php. Run this from inside the plugin directory.\n" );
	exit( 1 );
}

$args       = array_slice( $argv, 1 );
$commit     = in_array( '--commit', $args, true );
$positional = array();
foreach ( $args as $arg ) {
	if ( '--commit' === $arg ) {
		continue;
	}
	$positional[] = $arg;
}

if ( count( $positional ) < 3 ) {
	fwrite( STDERR, 'Usage: php ' . basename( __FILE__ ) . " \"<from show>\" \"<to show>\" \"HH:MM\" [--commit]\n" );
	exit( 1 );
}

$from_show = trim( $positional[0] );
$to_show   = trim( $positional[1] );
$cutoff    = $positional[2];

if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $cutoff ) ) {
	fwrite( STDERR, "Cutoff must be HH:MM (24h), e.g. 23:00.\n" );
	exit( 1 );
}

echo "Moving tracks with start time >= {$cutoff} from \"{$from_show}\" to \"{$to_show}\".\n";
echo $commit ? "Mode: COMMIT (changes will be applied)\n" : "Mode: dry-run (pass --commit to apply)\n";

/**
 * Find today's playlist whose title contains the given show name.
 *
 * @param string $show_name Show name to match in the playlist title.
 * @return int|false
 */
function wpp_find_todays_playlist( string $show_name ) {
	global $wpdb;
	$today_date = date_i18n( 'j F Y' );
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

	foreach ( $rows as $row ) {
		if ( false !== stripos( (string) $row->post_title, $show_name ) ) {
			return (int) $row->ID;
		}
	}
	return false;
}

$from_id = wpp_find_todays_playlist( $from_show );
$to_id   = wpp_find_todays_playlist( $to_show );

if ( ! $from_id ) {
	fwrite( STDERR, "Could not find today's playlist for \"{$from_show}\".\n" );
	exit( 1 );
}
if ( ! $to_id ) {
	fwrite( STDERR, "Could not find today's playlist for \"{$to_show}\".\n" );
	exit( 1 );
}
if ( $from_id === $to_id ) {
	fwrite( STDERR, "Source and target are the same playlist.\n" );
	exit( 1 );
}

echo "Source playlist: #{$from_id}\n";
echo "Target playlist: #{$to_id}\n";

$from_tracks = get_post_meta( $from_id, 'playlist', true );
$to_tracks   = get_post_meta( $to_id, 'playlist', true );

if ( ! is_array( $from_tracks ) ) {
	$from_tracks = array();
}
if ( ! is_array( $to_tracks ) ) {
	$to_tracks = array();
}

$keep   = array();
$moving = array();
foreach ( $from_tracks as $key => $track ) {
	$start = isset( $track['playlist_entry_comments'] ) ? (string) $track['playlist_entry_comments'] : '';
	if ( '' !== $start && $start >= $cutoff ) {
		$moving[ $key ] = $track;
		$artist         = isset( $track['playlist_entry_artist'] ) ? $track['playlist_entry_artist'] : '?';
		$song           = isset( $track['playlist_entry_song'] ) ? $track['playlist_entry_song'] : '?';
		echo "  move: {$start} {$artist} - {$song}\n";
	} else {
		$keep[ $key ] = $track;
	}
}

echo 'Found ' . count( $moving ) . " track(s) to move, " . count( $keep ) . " remain in source.\n";

if ( empty( $moving ) ) {
	echo "Nothing to move.\n";
	exit( 0 );
}

if ( ! $commit ) {
	echo "Dry-run only - no changes were made. Re-run with --commit to apply.\n";
	exit( 0 );
}

// Merge the moved tracks into the target in chronological order. Moved tracks
// came earlier in real time than the target's entries at the same minute, so
// give them priority on ties.
$all = array();
$i   = 0;
foreach ( $moving as $track ) {
	$all[] = array(
		'track'    => $track,
		'start'    => isset( $track['playlist_entry_comments'] ) ? (string) $track['playlist_entry_comments'] : '',
		'priority' => 0, // moved first on ties
		'order'    => $i++,
	);
}
foreach ( $to_tracks as $track ) {
	$all[] = array(
		'track'    => $track,
		'start'    => isset( $track['playlist_entry_comments'] ) ? (string) $track['playlist_entry_comments'] : '',
		'priority' => 1,
		'order'    => $i++,
	);
}

usort(
	$all,
	function ( $a, $b ) {
		$cmp = strcmp( $a['start'], $b['start'] );
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		$cmp = $a['priority'] <=> $b['priority'];
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		return $a['order'] <=> $b['order'];
	}
);

$merged = array();
$n      = 1;
foreach ( $all as $entry ) {
	$merged[ $n++ ] = $entry['track'];
}

// Rebuild the source (1-based) without the moved tracks.
$kept = array();
$n    = 1;
foreach ( $keep as $track ) {
	$kept[ $n++ ] = $track;
}

update_post_meta( $from_id, 'playlist', $kept );
update_post_meta( $to_id, 'playlist', $merged );

echo 'Moved ' . count( $moving ) . " track(s): source #{$from_id} now has " . count( $kept ) . ', target #' . $to_id . ' now has ' . count( $merged ) . ".\n";

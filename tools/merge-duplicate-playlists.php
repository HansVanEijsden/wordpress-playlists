<?php
/**
 * Merge duplicate daily playlists (maintenance tool, CLI only).
 *
 * After a lookup bug created one playlist per track - all titled with the
 * same day and show - this tool consolidates all playlists for a given date
 * back into a single playlist per show, preserving every track.
 *
 * USAGE (on the server, from the plugin directory):
 *
 *   # Show what would be merged (no changes):
 *   php tools/merge-duplicate-playlists.php
 *   php tools/merge-duplicate-playlists.php "3 augustus 2026"
 *
 *   # Actually merge:
 *   php tools/merge-duplicate-playlists.php "3 augustus 2026" --commit
 *
 * SAFETY:
 *   - CLI only: refuses to run when reached over HTTP.
 *   - Dry-run by default; pass --commit to make changes.
 *   - The "kept" playlist is the one holding the most tracks; duplicate
 *     playlists are permanently deleted (wp_delete_post( ..., true )).
 *   - Delete this file from the server after use if you prefer.
 *
 * @package WordPressPlaylists
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	exit( 'This tool is CLI only.' );
}

// Bootstrap WordPress by finding wp-load.php above this plugin.
//
// Some hardened hosts strip the FS_CHMOD_* constants from
// wp-includes/default-constants.php (a minor info-leak vector). Plugins that
// reference them - e.g. simple-auto-poster-for-bluesky - then fatal during
// bootstrap, so predefine the standard safe values for CLI runs. WordPress's
// own definitions are guarded with defined(), so this cannot conflict.
if ( ! defined( 'WP_USE_THEMES' ) ) {
	define( 'WP_USE_THEMES', false ); // CLI: never load theme templates.
}
if ( ! defined( 'FS_CHMOD_DIR' ) ) {
	define( 'FS_CHMOD_DIR', 0755 );
}
if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}
// Force the direct filesystem method. Without this, an unset FS_METHOD can
// fall back to ftpsockets, whose put_contents() needs wp_tempnam() /
// wp_generate_password() - admin functions that are not loaded during a CLI
// bootstrap. The direct method writes with file_put_contents() instead, so
// plugins that write files during load (e.g. the Bluesky logger) won't fatal.
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

$args   = array_slice( $argv, 1 );
$commit = in_array( '--commit', $args, true );
$date   = '';

foreach ( $args as $arg ) {
	if ( '--commit' === $arg ) {
		continue;
	}
	$date = $arg;
	break;
}

if ( '' === $date ) {
	$date = date_i18n( 'j F Y' );
}

echo 'Merging duplicate playlists for date: ' . $date . "\n";
echo $commit ? "Mode: COMMIT (changes will be applied)\n" : "Mode: dry-run (pass --commit to apply)\n";

global $wpdb;

$prefix = $wpdb->esc_like( $date ) . '%';
$posts  = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_title, post_date
		 FROM {$wpdb->posts}
		 WHERE post_type = 'playlist'
		   AND post_status = 'publish'
		   AND post_title LIKE %s
		 ORDER BY post_date ASC",
		$prefix
	)
);

if ( empty( $posts ) ) {
	echo "No playlists found for that date.\n";
	exit( 0 );
}

// Group by show name (the part of the title after the date prefix).
$groups = array();
foreach ( $posts as $post ) {
	$title            = (string) $post->post_title;
	$rest             = trim( str_ireplace( $date, '', $title ), " \t\n\r\0\x0B-–—" );
	$groups[ $rest ][] = $post;
}

$total_groups  = 0;
$total_removed = 0;

foreach ( $groups as $show => $members ) {
	if ( count( $members ) < 2 ) {
		continue;
	}
	$total_groups++;

	// Pick the member holding the most tracks as the survivor.
	$keep       = $members[0];
	$keep_count = 0;
	foreach ( $members as $member ) {
		$tracks = get_post_meta( $member->ID, 'playlist', true );
		$count  = is_array( $tracks ) ? count( $tracks ) : 0;
		if ( $count > $keep_count ) {
			$keep       = $member;
			$keep_count = $count;
		}
	}

	echo "\nShow: \"{$show}\" (" . count( $members ) . " playlists, keeping #{$keep->ID} with {$keep_count} track(s))\n";
	foreach ( $members as $member ) {
		if ( $member->ID === $keep->ID ) {
			continue;
		}
		$tracks = get_post_meta( $member->ID, 'playlist', true );
		$count  = is_array( $tracks ) ? count( $tracks ) : 0;
		echo "  - #{$member->ID} \"{$member->post_title}\" ({$member->post_date}) - {$count} track(s)\n";
	}

	if ( ! $commit ) {
		continue;
	}

	// Gather tracks from every member in creation order.
	$sources = $members;
	usort(
		$sources,
		function ( $a, $b ) {
			return strtotime( $a->post_date ) <=> strtotime( $b->post_date );
		}
	);

	$all_tracks = array();
	foreach ( $sources as $source ) {
		$tracks = get_post_meta( $source->ID, 'playlist', true );
		if ( ! is_array( $tracks ) ) {
			continue;
		}
		foreach ( $tracks as $track ) {
			$all_tracks[] = $track;
		}
	}

	// Order tracks by their start time (playlist_entry_comments = H:i),
	// falling back to their original order for equal/empty start times.
	$index = 0;
	foreach ( $all_tracks as &$track ) {
		$track['_sort_start'] = isset( $track['playlist_entry_comments'] ) ? (string) $track['playlist_entry_comments'] : '';
		$track['_sort_index'] = $index++;
	}
	unset( $track );

	usort(
		$all_tracks,
		function ( $a, $b ) {
			$cmp = strcmp( $a['_sort_start'], $b['_sort_start'] );
			if ( 0 !== $cmp ) {
				return $cmp;
			}
			return $a['_sort_index'] <=> $b['_sort_index'];
		}
	);

	// Rebuild the 1-based Radio Station playlist meta array.
	$merged = array();
	$n      = 1;
	foreach ( $all_tracks as $track ) {
		unset( $track['_sort_start'], $track['_sort_index'] );
		$merged[ $n++ ] = $track;
	}

	update_post_meta( $keep->ID, 'playlist', $merged );

	// Give the survivor a featured image if it has none.
	if ( ! has_post_thumbnail( $keep->ID ) ) {
		foreach ( $members as $member ) {
			if ( $member->ID !== $keep->ID && has_post_thumbnail( $member->ID ) ) {
				set_post_thumbnail( $keep->ID, get_post_thumbnail_id( $member->ID ) );
				break;
			}
		}
	}

	// Permanently delete the duplicates.
	foreach ( $members as $member ) {
		if ( $member->ID !== $keep->ID ) {
			wp_delete_post( $member->ID, true );
			$total_removed++;
			echo "  - deleted #{$member->ID}\n";
		}
	}

	echo '  - merged ' . count( $all_tracks ) . " track(s) into #{$keep->ID}\n";
}

echo "\nDone. Groups with duplicates: {$total_groups}, playlists removed: {$total_removed}.\n";
if ( ! $commit ) {
	echo "Dry-run only - no changes were made. Re-run with --commit to apply.\n";
}

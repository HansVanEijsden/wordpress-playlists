<?php
/**
 * WP-CLI commands
 *
 * Maintenance commands for the derived artist index. Register only when
 * WP-CLI is present.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class CLI {

	/**
	 * Register the command namespace.
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command( 'wppl', __CLASS__ );
	}

	/**
	 * Rebuild the artist index from the playlist meta.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report differences without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wppl rebuild --dry-run
	 *     wp wppl rebuild
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function rebuild( $args, $assoc_args ): void {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		\WP_CLI::log( $dry_run ? 'Rebuilding the artist index (dry run)…' : 'Rebuilding the artist index…' );

		add_action(
			'wordpress_playlists_rebuild_progress',
			static function ( $done, $total ) {
				\WP_CLI::log( sprintf( '  %d / %d playlists', $done, $total ) );
			},
			10,
			2
		);

		$start  = microtime( true );
		$report = Artist_Index::rebuild( $dry_run );

		$items = array(
			array(
				'playlists'  => (int) $report['playlists'],
				'plays'      => (int) $report['plays'],
				'artists'    => (int) $report['terms'],
				'index diff' => (int) $report['index_mismatch'],
				'summary diff' => (int) $report['summary_mismatch'],
				'count diff' => (int) $report['term_mismatch'],
			),
		);

		\WP_CLI\Utils\format_items( 'table', $items, array( 'playlists', 'plays', 'artists', 'index diff', 'summary diff', 'count diff' ) );

		if ( ! empty( $report['examples'] ) ) {
			\WP_CLI::log( 'Artists whose stored count differs:' );

			foreach ( $report['examples'] as $example ) {
				\WP_CLI::log( sprintf( '  %s: stored %d, expected %d', $example['term'], $example['stored'], $example['wanted'] ) );
			}
		}

		if ( ! empty( $report['missing'] ) ) {
			\WP_CLI::warning( sprintf( '%d artist names could not be indexed.', count( $report['missing'] ) ) );
		}

		\WP_CLI::log( sprintf( 'Finished in %.1fs.', microtime( true ) - $start ) );

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run only; nothing was written.' );
			return;
		}

		Cache::purge_aggregates();

		\WP_CLI::success( 'Artist index rebuilt.' );
	}

	/**
	 * Show the state of the derived index.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wppl status
	 */
	public function status(): void {
		$status = Artist_Index::status();

		$built = (int) $status['built'];

		$items = array(
			array(
				'playlists'           => (int) $status['playlists'],
				'with summary'        => (int) $status['with_summary'],
				'without an artist'   => (int) $status['unassigned'],
				'artists'             => (int) $status['terms'],
				'plays'               => (int) $status['plays'],
				'last rebuild'        => $built ? wp_date( 'Y-m-d H:i', $built ) : 'never',
			),
		);

		\WP_CLI\Utils\format_items( 'table', $items, array_keys( $items[0] ) );

		if ( ! $built ) {
			\WP_CLI::warning( 'The index has never been rebuilt. Run: wp wppl rebuild' );
		}
	}

	/**
	 * Recompute one artist from its playlists.
	 *
	 * ## OPTIONS
	 *
	 * <artist>
	 * : Term ID or term slug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wppl recount douwe-bob
	 *
	 * @param array $args Positional arguments.
	 */
	public function recount( $args ): void {
		$artist = isset( $args[0] ) ? (string) $args[0] : '';

		if ( '' === $artist ) {
			\WP_CLI::error( 'Provide an artist term ID or slug.' );
		}

		$term = ctype_digit( $artist ) ? get_term( (int) $artist, Taxonomy::NAME ) : get_term_by( 'slug', $artist, Taxonomy::NAME );

		if ( ! $term instanceof \WP_Term ) {
			\WP_CLI::error( 'Artist not found.' );
		}

		$report = Artist_Index::recount_term( (int) $term->term_id );

		if ( null === $report ) {
			\WP_CLI::error( 'This artist has no index key; rebuild the index first.' );
		}

		\WP_CLI::success(
			sprintf(
				'%s: %d plays, %d tracks across %d playlists (first %s, last %s).',
				$report['term'],
				$report['plays'],
				$report['tracks'],
				$report['playlists'],
				$report['first'] ? $report['first'] : '—',
				$report['last'] ? $report['last'] : '—'
			)
		);
	}
}

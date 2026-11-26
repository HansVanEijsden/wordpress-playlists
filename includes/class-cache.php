<?php
/**
 * Cache
 *
 * Cache versioning, snapshots and full-page cache purging. The display layer
 * renders server-side for SEO, so everything that changes when a track is
 * added must either be busted or explicitly purged.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Cache {

	/** Option holding the current cache version. */
	public const VERSION_OPTION = 'wordpress_playlists_cache_version';

	/**
	 * Current cache version. Bumped whenever stored derived data changes.
	 */
	public static function version(): string {
		$version = (string) get_option( self::VERSION_OPTION, '1' );

		return '' === $version ? '1' : $version;
	}

	/**
	 * Bump the version, invalidating every versioned snapshot.
	 */
	public static function bump(): void {
		update_option( self::VERSION_OPTION, (string) microtime( true ), false );
	}

	/**
	 * Remember a value in a transient keyed by the current version.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Lifetime in seconds.
	 * @param callable $callback Producer.
	 * @return mixed
	 */
	public static function remember( string $key, int $ttl, callable $callback ) {
		$transient = 'wppl_' . md5( $key . '|' . self::version() );
		$cached    = get_transient( $transient );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();

		if ( null !== $value && false !== $value ) {
			set_transient( $transient, $value, $ttl );
		}

		return $value;
	}

	/**
	 * Remember a value under its own key, without the version applied. Used
	 * for slow structures that are invalidated explicitly.
	 *
	 * @param string   $key      Cache key.
	 * @param int      $ttl      Lifetime in seconds.
	 * @param callable $callback Producer.
	 * @return mixed
	 */
	public static function remember_raw( string $key, int $ttl, callable $callback ) {
		$transient = 'wppl_raw_' . md5( $key );
		$cached    = get_transient( $transient );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();

		if ( null !== $value && false !== $value ) {
			set_transient( $transient, $value, $ttl );
		}

		return $value;
	}

	/**
	 * Forget one raw snapshot.
	 */
	public static function forget( string $key ): void {
		delete_transient( 'wppl_raw_' . md5( $key ) );
	}

	/**
	 * Purge the pages a newly recorded track affects.
	 *
	 * The playlist itself is purged by the site's cache integration when the
	 * post is touched, but artist, hub and month pages are not derived posts,
	 * so they are purged explicitly.
	 *
	 * @param int $playlist_id Playlist post ID.
	 * @param int $term_id     Artist term ID, when known.
	 */
	public static function purge_after_play( int $playlist_id, int $term_id = 0 ): void {
		if ( ! Context::display_enabled() ) {
			return;
		}

		$urls = array();

		$playlist_url = get_permalink( $playlist_id );
		if ( $playlist_url ) {
			$urls[] = $playlist_url;
		}

		if ( $term_id ) {
			$term_url = get_term_link( $term_id, Artist_Index::TAXONOMY );
			if ( ! is_wp_error( $term_url ) ) {
				$urls[] = $term_url;
			}
		}

		$hub_url = Context::hub_url();
		if ( '' !== $hub_url ) {
			$urls[] = $hub_url;
		}

		$month_url = Context::month_url_for_post( $playlist_id );
		if ( '' !== $month_url ) {
			$urls[] = $month_url;
		}

		$urls = array_merge( $urls, self::extra_urls() );

		self::purge_urls( $urls );
	}

	/**
	 * Purge the aggregate display pages (hub, month index, artist index).
	 * Used after a rebuild or an editorial change.
	 */
	public static function purge_aggregates(): void {
		$urls = array( Context::hub_url(), Context::artists_index_url() );

		foreach ( Context::months() as $month ) {
			$urls[] = Context::month_url( (int) $month['year'], (int) $month['month'] );
		}

		$urls = array_merge( $urls, self::extra_urls() );

		self::purge_urls( $urls );
	}

	/**
	 * Extra URLs registered by companion plugins (for example a radio hub that
	 * renders the local-artist list).
	 *
	 * @return array<int,string>
	 */
	private static function extra_urls(): array {
		/**
		 * Filter extra URLs to purge when playlist data changes.
		 *
		 * @param array<int,string> $urls Absolute URLs.
		 */
		$urls = (array) apply_filters( 'wordpress_playlists_extra_purge_urls', array() );

		return array_values( array_filter( array_map( 'strval', $urls ) ) );
	}

	/**
	 * Purge the given URLs plus any companion-plugin URLs.
	 *
	 * Editorial term saves and rebuilds call this so a page that renders
	 * playlist data (for example a radio hub with a local-artist section) is
	 * purged together with the playlist pages.
	 *
	 * @param array<int,string> $urls Absolute URLs.
	 */
	public static function purge_urls_with_extra( array $urls ): void {
		self::purge_urls( array_merge( $urls, self::extra_urls() ) );
	}

	/**
	 * Send purge requests through the cache integrations we can detect.
	 *
	 * @param array<int,string> $urls Absolute URLs.
	 */
	public static function purge_urls( array $urls ): void {
		$urls = array_values( array_unique( array_filter( array_map( 'strval', $urls ) ) ) );

		if ( empty( $urls ) ) {
			return;
		}

		foreach ( $urls as $url ) {
			self::purge_url( $url );
		}
	}

	/**
	 * Purge a single URL.
	 */
	private static function purge_url( string $url ): void {
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return;
		}

		/** This action lets a site purge its own full-page cache. */
		do_action( 'wordpress_playlists_purge_url', $url );

		if ( isset( $GLOBALS['nginx_purger'] ) && is_object( $GLOBALS['nginx_purger'] ) && method_exists( $GLOBALS['nginx_purger'], 'purge_url' ) ) {
			$GLOBALS['nginx_purger']->purge_url( $url );
		}

		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $url );
		}

		// LiteSpeed Cache listens on this hook when it is active.
		do_action( 'litespeed_purge_url', $url );
	}
}

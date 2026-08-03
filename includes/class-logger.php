<?php
/**
 * Logger
 *
 * Centralized error_log helper. Every diagnostic message is prefixed so that
 * site logs can be filtered consistently across all stations.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Logger {

	const PREFIX = '[WordPress Playlists]';

	/**
	 * Write a diagnostic message to the PHP error log.
	 *
	 * @param string $station_name Station name used in the log prefix.
	 * @param string $message      Log message. Never pass API keys or secrets here.
	 */
	public static function log( string $station_name, string $message ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::PREFIX . '[' . $station_name . '] ' . $message );
	}
}

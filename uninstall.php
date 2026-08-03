<?php
/**
 * Uninstall
 *
 * Removes plugin data when the plugin is deleted from WordPress.
 *
 * SAFETY: playlists are valuable Radio Station content. This file ONLY ever
 * removes this plugin's own settings option by default. Playlist posts are
 * deleted only when the site administrator has explicitly enabled
 * "Delete all playlist posts when the plugin is uninstalled" on the settings
 * page (disabled by default). Only the "playlist" post type is ever targeted.
 *
 * @package WordPressPlaylists
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'WORDPRESS_PLAYLISTS_OPTION' ) ) {
	define( 'WORDPRESS_PLAYLISTS_OPTION', 'wordpress_playlists_settings' );
}

/**
 * Remove this plugin's settings option for the current site.
 */
function wordpress_playlists_delete_option() {
	delete_option( WORDPRESS_PLAYLISTS_OPTION );
}

/**
 * Delete all playlist posts - but ONLY when the administrator explicitly
 * enabled playlist deletion. Never touches any other post type.
 */
function wordpress_playlists_delete_playlists() {
	$settings = get_option( WORDPRESS_PLAYLISTS_OPTION, array() );

	if ( empty( $settings['delete_playlists_on_uninstall'] ) ) {
		return;
	}

	if ( ! post_type_exists( 'playlist' ) ) {
		return;
	}

	// Delete in small batches; the loop stops as soon as nothing is left.
	$max_batches = 1000; // Hard safety cap against an infinite loop.
	$processed   = 0;

	for ( $batch = 0; $batch < $max_batches; $batch++ ) {
		$playlists = get_posts(
			array(
				'post_type'      => 'playlist',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
			)
		);

		if ( empty( $playlists ) ) {
			break;
		}

		foreach ( $playlists as $playlist_id ) {
			wp_delete_post( (int) $playlist_id, true );
			$processed++;
		}
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '[WordPress Playlists] Deleted ' . $processed . ' playlist posts on uninstall (explicitly enabled).' );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		wordpress_playlists_delete_playlists();
		wordpress_playlists_delete_option();
		restore_current_blog();
	}
} else {
	wordpress_playlists_delete_playlists();
	wordpress_playlists_delete_option();
}

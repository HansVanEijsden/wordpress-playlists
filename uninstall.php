<?php
/**
 * Uninstall
 *
 * Removes all plugin data when the plugin is deleted from WordPress.
 * No API keys, playlists or options are left behind.
 *
 * @package WordPressPlaylists
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// On multisite, clean up every site that used the plugin.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( WORDPRESS_PLAYLISTS_OPTION );
		restore_current_blog();
	}
} else {
	delete_option( WORDPRESS_PLAYLISTS_OPTION );
}

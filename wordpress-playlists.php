<?php
/**
 * Plugin Name:       WordPress Playlists
 * Plugin URI:        https://github.com/HansVanEijsden/wordpress-playlists
 * Description:       Automatic daily radio playlists for the Radio Station plugin. Each station receives track data over its own REST API endpoint and appends the track to the correct daily playlist for the current show. Multi-station and fully configurable in the WordPress admin.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Hans van Eijsden
 * Author URI:        https://www.hansvaneijsden.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordpress-playlists
 * Domain Path:       /languages
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

define( 'WORDPRESS_PLAYLISTS_VERSION', '1.0.0' );
define( 'WORDPRESS_PLAYLISTS_FILE', __FILE__ );
define( 'WORDPRESS_PLAYLISTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORDPRESS_PLAYLISTS_URL', plugin_dir_url( __FILE__ ) );
define( 'WORDPRESS_PLAYLISTS_BASENAME', plugin_basename( __FILE__ ) );
define( 'WORDPRESS_PLAYLISTS_OPTION', 'wordpress_playlists_settings' );

require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-logger.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-station.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-settings.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-metadata-client.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-playlist-manager.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-rest-controller.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-gettext.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-plugin.php';

\WordPressPlaylists\Plugin::instance();

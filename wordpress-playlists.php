<?php
/**
 * Plugin Name:       Radio Station Playlists
 * Plugin URI:        https://github.com/HansVanEijsden/wordpress-playlists
 * Description:       Automatic daily radio playlists for the Radio Station plugin, with a designed public presentation: an airplay hub, month archive, playlist pages and a canonical page per artist. A metadata hub posts each played track to the plugin's REST endpoint, which appends it to the correct daily playlist for the current show.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  radio-station
 * Author:            Hans van Eijsden Consultancy
 * Author URI:        https://www.hansvaneijsden.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordpress-playlists
 * Domain Path:       /languages
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

define( 'WORDPRESS_PLAYLISTS_VERSION', '1.2.1' );
define( 'WORDPRESS_PLAYLISTS_FILE', __FILE__ );
define( 'WORDPRESS_PLAYLISTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORDPRESS_PLAYLISTS_URL', plugin_dir_url( __FILE__ ) );
define( 'WORDPRESS_PLAYLISTS_BASENAME', plugin_basename( __FILE__ ) );
define( 'WORDPRESS_PLAYLISTS_OPTION', 'wordpress_playlists_settings' );

require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-logger.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-palette.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-renderer.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-components.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-cache.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-station.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-settings.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-taxonomy.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-artist-index.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-context.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-metadata-client.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-playlist-manager.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-playlist-model.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-archive-client.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-archive-links.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-playlist-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-hub-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-month-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-search.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-artist-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-theme-shell.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-assets.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-routes.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-seo.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-shortcodes.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-term-fields.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-cli.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-rest-controller.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-gettext.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( '\WordPressPlaylists\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\WordPressPlaylists\Plugin', 'deactivate' ) );

\WordPressPlaylists\Plugin::instance();

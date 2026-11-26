<?php
/**
 * Artist index, letter page and search results template.
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

get_header();

$view = new \WordPressPlaylists\Artist_View();

ob_start();
$view->render_current_index();
$main = (string) ob_get_clean();

$sidebar = \WordPressPlaylists\Components::theme_sidebar();
$rail    = '';

if ( '' !== $sidebar ) {
	$rail = '<div class="wppl wppl-theme-rail"><div class="wppl-rail-widgets">' . $sidebar . '</div></div>';
}

echo \WordPressPlaylists\Theme_Shell::render( $main, $rail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer and theme widgets escape internally.

get_footer();

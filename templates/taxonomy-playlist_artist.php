<?php
/**
 * Artist archive template.
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

get_header();

$view = new \WordPressPlaylists\Artist_View();
$term = get_queried_object();

if ( $term instanceof \WP_Term && \WordPressPlaylists\Taxonomy::NAME === $term->taxonomy ) {
	$data = $view->archive_data( $term );
	$main = '<div class="wppl wppl-page wppl-artist-page">' . $view->render_archive_main( $data ) . '</div>';
	$rail = $view->render_archive_rail( $data );

	echo \WordPressPlaylists\Theme_Shell::render( $main, $rail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer and theme widgets escape internally.
} else {
	$view->render_current_archive();
}

get_footer();

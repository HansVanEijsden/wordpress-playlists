<?php
/**
 * Artist index, letter page and search results template.
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sidebar = \WordPressPlaylists\Components::theme_sidebar();
?>
<main id="wppl-primary" class="wppl-main wppl-main--rail">
	<?php if ( '' !== $sidebar ) : ?>
		<div class="wppl-rail-layout">
			<div class="wppl-rail-main">
				<?php ( new \WordPressPlaylists\Artist_View() )->render_current_index(); ?>
			</div>
			<aside class="wppl-rail-aside widget-area" aria-label="<?php echo esc_attr__( 'Sidebar', 'wordpress-playlists' ); ?>">
				<?php echo $sidebar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Theme widget markup. ?>
			</aside>
		</div>
	<?php else : ?>
		<?php ( new \WordPressPlaylists\Artist_View() )->render_current_index(); ?>
	<?php endif; ?>
</main>
<?php
get_footer();

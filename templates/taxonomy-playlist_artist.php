<?php
/**
 * Artist archive template.
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="wppl-primary" class="wppl-main">
	<?php ( new \WordPressPlaylists\Artist_View() )->render_current_archive(); ?>
</main>
<?php
get_footer();

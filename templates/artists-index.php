<?php
/**
 * Artist index, letter page and search results template.
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="wppl-main">
	<?php ( new \WordPressPlaylists\Artist_View() )->render_current_index(); ?>
</main>
<?php
get_footer();

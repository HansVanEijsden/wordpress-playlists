<?php
/**
 * Month template.
 *
 * @package WordPressPlaylists
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="wppl-main">
	<?php ( new \WordPressPlaylists\Month_View() )->render_current(); ?>
</main>
<?php
get_footer();

<?php
/**
 * Preview harness for the public playlist surfaces.
 *
 * Run with: php -S 127.0.0.1:8099 -t preview preview/index.php
 * Then open: http://127.0.0.1:8099/?view=playlist
 *
 * Development only; not part of the WordPress plugin distribution.
 *
 * @package WordPressPlaylists
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures.php';

use WordPressPlaylists\Artist_View;
use WordPressPlaylists\Components;
use WordPressPlaylists\Hub_View;
use WordPressPlaylists\Month_View;
use WordPressPlaylists\Palette;
use WordPressPlaylists\Playlist_View;

$site  = isset( $_GET['site'] ) && '1z' === $_GET['site'] ? '1z' : 's1';
$view  = isset( $_GET['view'] ) ? preg_replace( '/[^a-z]/', '', (string) $_GET['view'] ) : 'playlist';
$chrome = ! isset( $_GET['chrome'] ) || '0' !== $_GET['chrome'];
$now    = isset( $_GET['now'] ) ? preg_replace( '/[^0-9:]/', '', (string) $_GET['now'] ) : '19:31';
$width = isset( $_GET['width'] ) && 'wide' === $_GET['width'] ? 'wide' : 'normal';

$accent  = '1z' === $site ? '#283593' : '#e50526';
$palette = Palette::derive( $accent );

$views = array(
	'playlist' => 'Playlist',
	'long'     => 'Non Stop (385)',
	'hub'      => 'Hub',
	'month'    => 'Maand',
	'artist'   => 'Artiest',
	'local'    => 'Lokale artiest',
	'index'    => 'Artiestenindex',
	'letter'   => 'Letter',
	'search'   => 'Zoeken',
	'embed'    => 'Embed-kaart',
	'empty'    => 'Lege staat',
	'searchempty' => 'Leeg zoekresultaat',
);

if ( ! isset( $views[ $view ] ) ) {
	$view = 'playlist';
}

$site_name = '1z' === $site ? '1Zwolle' : 'Salland1';
$is_article = in_array( $view, array( 'playlist', 'long', 'hub', 'empty' ), true );

$render = static function () use ( $view, $site ): string {
	switch ( $view ) {
		case 'playlist':
			$data = preview_playlist();

			if ( '1z' === $site ) {
				$data['episodes']    = array();
				$data['archive_url'] = '';
			}

			return ( new Playlist_View() )->render( $data );

		case 'long':
			$data = preview_long_playlist();

			if ( '1z' === $site ) {
				$data['episodes']    = array();
				$data['archive_url'] = '';
			}

			return ( new Playlist_View() )->render( $data );

		case 'hub':
			return ( new Hub_View() )->render( preview_hub() );

		case 'month':
			return ( new Month_View() )->render( preview_month() );

		case 'artist':
			return ( new Artist_View() )->render_archive( preview_artist() );

		case 'local':
			return ( new Artist_View() )->render_archive( preview_artist( 'local' ) );

		case 'index':
			return ( new Artist_View() )->render_index( preview_index() );

		case 'letter':
			return ( new Artist_View() )->render_letter( preview_letter() );

		case 'search':
			return ( new Artist_View() )->render_search( preview_search() );

		case 'embed':
			$artist = preview_artist();

			return '<div style="max-width:640px;">' . ( new Artist_View() )->render_embed(
				array(
					'name'   => $artist['name'],
					'url'    => $artist['url'],
					'plays'  => $artist['plays'],
					'local'  => $artist['local'],
					'image'  => '',
					'tracks' => array_slice( $artist['tracks'], 0, 3 ),
				)
			) . '</div>';

		case 'empty':
			$empty          = preview_playlist();
			$empty['tracks'] = array();
			$empty['hours']  = array();
			$empty['count']  = 0;
			$empty['span']   = '';
			$empty['bands']  = false;

			if ( '1z' === $site ) {
				$empty['episodes']    = array();
				$empty['archive_url'] = '';
			}

			return ( new Playlist_View() )->render( $empty );

		case 'searchempty':
			return ( new Artist_View() )->render_search( array( 'query' => 'onvindbaar', 'groups' => array() ) );
	}

	return '';
};

$demo_title = array(
	'playlist' => '25 november 2026 – Sallands Café',
	'long'     => '25 november 2026 – Non Stop',
	'hub'      => 'Afgespeelde Nummers',
	'empty'    => '25 november 2026 – Sallands Café',
	'artist'   => 'Artiest',
	'local'    => 'Artiest',
	'month'    => 'Maand',
	'index'    => 'Artiesten',
	'letter'   => 'Letter',
	'search'   => 'Zoeken',
	'embed'    => 'Embed-kaart',
	'searchempty' => 'Leeg zoekresultaat',
);

$content_width = 'wide' === $width ? '920px' : ( '1z' === $site ? '660px' : '700px' );

?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Preview — <?php echo esc_html( $views[ $view ] ); ?> — <?php echo esc_html( $site_name ); ?></title>
<?php if ( '1z' === $site ) : ?>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800&display=swap" />
<?php endif; ?>
<link rel="stylesheet" href="../assets/css/playlist-display.css?v=<?php echo (int) filemtime( dirname( __DIR__ ) . '/assets/css/playlist-display.css' ); ?>" />
<style>
	:root { --preview-accent: <?php echo esc_html( $accent ); ?>; }
	html, body { margin: 0; padding: 0; background: #fff; }
	body.site-s1 { font-family: Verdana, Geneva, sans-serif; color: #1f2430; }
	body.site-1z { font-family: "Mulish", "Segoe UI", system-ui, sans-serif; color: #20242c; }
	body { font-size: 15px; line-height: 1.5; }
	a { color: inherit; }
	.pv-chrome { background: #111318; color: #fff; font: 12px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; }
	.pv-chrome .pv-inner { max-width: 1180px; margin: 0 auto; padding: 8px 16px; display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; }
	.pv-chrome a { color: #c9cfdb; text-decoration: none; padding: 2px 7px; border: 1px solid #2c313c; border-radius: 999px; }
	.pv-chrome a:hover, .pv-chrome a.is-on { color: #fff; border-color: #6b7486; background: #1b1f28; }
	.pv-chrome .pv-tag { color: #8b93a3; }
	.pv-sitepage { min-height: 100vh; }
	.pv-header { border-bottom: 1px solid #e5e7eb; background: #fff; }
	.pv-header .pv-inner { max-width: 1180px; margin: 0 auto; padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
	.pv-logo { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; }
	.site-s1 .pv-logo { color: #e50526; }
	.site-1z .pv-logo { color: #283593; }
	.pv-nav { display: flex; gap: 16px; font-size: 13px; color: #4b5563; }
	.pv-shell { max-width: 1180px; margin: 0 auto; padding: 26px 16px 70px; display: grid; grid-template-columns: minmax(0, <?php echo esc_html( $content_width ); ?>) 300px; gap: 44px; align-items: start; }
	.pv-shell.is-full { grid-template-columns: minmax(0, 1fr); }
	.pv-content { min-width: 0; }
	.pv-side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 16px; }
	.pv-ad { border: 1px solid #eceef2; background: #f8f9fb; height: 150px; display: grid; place-items: center; font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase; color: #9aa2ad; }
	.pv-player { border: 1px solid #eceef2; border-radius: 14px; padding: 14px; font-size: 12px; color: #6b7280; }
	.pv-demo-title { margin: 0 0 14px; font-size: 28px; line-height: 1.2; letter-spacing: -0.02em; }
	.site-1z .pv-demo-title { font-weight: 800; }
	.pv-demo-intro { margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #4b5563; max-width: 62ch; }
	.pv-crumbs { font-size: 12px; color: #8a93a0; margin-bottom: 10px; }
	.pv-synthetic { font-size: 11px; letter-spacing: 0.04em; text-transform: uppercase; color: #8a93a0; margin-top: 22px; }
	@media (max-width: 900px) {
		.pv-shell { grid-template-columns: minmax(0, 1fr); }
		.pv-side { display: none; }
	}
	@media (max-width: 760px) {
		.pv-nav { display: none; }
		.pv-header .pv-inner { padding: 12px 16px; }
	}
</style>
<style>
	.wppl {
		--wppl-accent: <?php echo esc_html( $palette['accent'] ); ?>;
		--wppl-accent-ink: <?php echo esc_html( $palette['ink'] ); ?>;
		--wppl-on-accent: <?php echo esc_html( $palette['on'] ); ?>;
		--wppl-accent-soft: <?php echo esc_html( $palette['soft'] ); ?>;
		--wppl-accent-soft-strong: <?php echo esc_html( $palette['soft-strong'] ); ?>;
	}
</style>
</head>
<body class="site-<?php echo esc_attr( $site ); ?>">
<?php if ( $chrome ) : ?>
<div class="pv-chrome">
	<div class="pv-inner">
		<span class="pv-tag">preview</span>
		<?php foreach ( $views as $key => $label ) : ?>
			<a class="<?php echo $key === $view ? 'is-on' : ''; ?>" href="?view=<?php echo esc_attr( $key ); ?>&site=<?php echo esc_attr( $site ); ?>&width=<?php echo esc_attr( $width ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		<span style="flex:1"></span>
		<a class="<?php echo 's1' === $site ? 'is-on' : ''; ?>" href="?view=<?php echo esc_attr( $view ); ?>&site=s1&width=<?php echo esc_attr( $width ); ?>">Salland1</a>
		<a class="<?php echo '1z' === $site ? 'is-on' : ''; ?>" href="?view=<?php echo esc_attr( $view ); ?>&site=1z&width=<?php echo esc_attr( $width ); ?>">1Zwolle</a>
		<a href="?view=<?php echo esc_attr( $view ); ?>&site=<?php echo esc_attr( $site ); ?>&width=<?php echo 'wide' === $width ? 'normal' : 'wide'; ?>"><?php echo 'wide' === $width ? 'normale breedte' : 'brede kolom'; ?></a>
		<a href="?view=<?php echo esc_attr( $view ); ?>&site=<?php echo esc_attr( $site ); ?>&chrome=0">schoon</a>
	</div>
</div>
<?php endif; ?>

<div class="pv-sitepage">
	<header class="pv-header">
		<div class="pv-inner">
			<div class="pv-logo"><?php echo esc_html( $site_name ); ?></div>
			<nav class="pv-nav" aria-label="Demo navigatie">
				<span>Nieuws</span><span>Radio</span><span>Programmering</span><span>Afspeellijsten</span><span>Contact</span>
			</nav>
		</div>
	</header>

	<main class="pv-shell <?php echo $is_article ? '' : 'is-full'; ?>">
		<div class="pv-content">
			<?php if ( in_array( $view, array( 'playlist', 'long', 'empty', 'hub' ), true ) ) : ?>
				<p class="pv-crumbs">Home › <?php echo 'hub' === $view ? 'Afgespeelde nummers' : '25 november 2026 – Sallands Café'; ?></p>
				<h1 class="pv-demo-title"><?php echo esc_html( $demo_title[ $view ] ); ?></h1>
				<?php if ( 'hub' === $view ) : ?>
					<p class="pv-demo-intro">Hieronder vind je de afspeellijsten, overzichtelijk gerangschikt in chronologische volgorde. Zo kun je eenvoudig terugzoeken wat er op een bepaald moment werd gedraaid.</p>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- views escape internally.
			echo $render();
			?>

			<p class="pv-synthetic">Voorbeeldweergave met testdata — geen echte uitzendingen of aantallen.</p>
		</div>

		<?php if ( $is_article ) : ?>
			<aside class="pv-side" aria-label="Demo zijbalk">
				<div class="pv-player">
					<strong style="display:block;color:#374151;margin-bottom:6px;">Nu op <?php echo esc_html( $site_name ); ?></strong>
					Zijbalk en advertenties blijven van het thema. De plugin raakt ze niet aan.
				</div>
				<div class="pv-ad">reclame</div>
				<div class="pv-ad">reclame</div>
			</aside>
		<?php endif; ?>
	</main>
</div>

<script>
window.WordPressPlaylistsDisplay = {
	timeZone: 'Europe/Amsterdam',
	nowOverride: '<?php echo esc_js( $now ); ?>',
	nowDate: '2026-11-25',
	nowLabel: 'Nu',
	copyLabel: 'Kopieer link',
	copiedLabel: 'Link gekopieerd',
	copyError: 'Kon de link niet kopiëren'
};
</script>
<script src="../assets/js/playlist-display.js?v=<?php echo (int) filemtime( dirname( __DIR__ ) . '/assets/js/playlist-display.js' ); ?>"></script>
</body>
</html>

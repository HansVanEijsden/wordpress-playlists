<?php
/**
 * Preview harness bootstrap: the minimum WordPress surface the public views
 * use, so the real renderer classes can be exercised without a database.
 *
 * Development only. Not shipped inside the WordPress plugin directory.
 *
 * @package WordPressPlaylists
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'WORDPRESS_PLAYLISTS_VERSION', '1.1.0-preview' );
define( 'WORDPRESS_PLAYLISTS_DIR', dirname( __DIR__ ) . '/' );
define( 'WORDPRESS_PLAYLISTS_URL', '../' );
define( 'WORDPRESS_PLAYLISTS_OPTION', 'wordpress_playlists_settings' );

date_default_timezone_set( 'Europe/Amsterdam' );
define( 'WORDPRESS_PLAYLISTS_BASENAME', 'wordpress-playlists.php' );

require_once __DIR__ . '/translate.php';

function preview_translate( string $text ): string {
	global $preview_translations;

	return $preview_translations[ $text ] ?? $text;
}

function __( $text, $domain = null ) {
	return preview_translate( (string) $text );
}

function _x( $text, $context = null, $domain = null ) {
	return preview_translate( (string) $text );
}

function _n( $single, $plural, $number, $domain = null ) {
	return preview_translate( (int) $number === 1 ? (string) $single : (string) $plural );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr( $text ) {
	return esc_html( $text );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_textarea( $text ) {
	return esc_html( $text );
}

function esc_js( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_html__( $text, $domain = null ) {
	return esc_html( preview_translate( (string) $text ) );
}

function esc_attr__( $text, $domain = null ) {
	return esc_attr( preview_translate( (string) $text ) );
}

function esc_html_e( $text, $domain = null ) {
	echo esc_html( preview_translate( (string) $text ) );
}

function esc_attr_e( $text, $domain = null ) {
	echo esc_attr( preview_translate( (string) $text ) );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals, ',', '.' );
}

function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}

function remove_accents( $text ) {
	$map = array(
		'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
		'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
		'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
		'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
		'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
		'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
		'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
		'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
		'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
		'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
		'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
		'Ç' => 'C', 'Ñ' => 'N',
	);

	return strtr( (string) $text, $map );
}

function sanitize_title( $title ) {
	$title = remove_accents( (string) $title );
	$title = strtolower( $title );
	$title = preg_replace( '/[^a-z0-9]+/', '-', $title );

	return trim( (string) $title, '-' );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function wp_kses_post( $content ) {
	return (string) $content;
}

function wpautop( $text ) {
	$paragraphs = preg_split( '/\R{2,}/', trim( (string) $text ) ) ?: array();

	return implode( '', array_map( static function ( $paragraph ) {
		return '<p>' . str_replace( "\n", '<br />', $paragraph ) . '</p>';
	}, $paragraphs ) );
}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, (int) $flags );
}

function home_url( $path = '' ) {
	return '/' . ltrim( (string) $path, '/' );
}

function add_action( ...$args ) {
	return true;
}

function add_filter( ...$args ) {
	return true;
}

function remove_filter( ...$args ) {
	return true;
}

function do_action( ...$args ) {
	return true;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function is_active_sidebar( $id ) {
	return 'sidebar-1' === $id;
}

function dynamic_sidebar( $id ) {
	if ( 'sidebar-1' !== $id ) {
		return false;
	}

	echo '<div class="widget"><h2 class="widget-title">Meest gelezen</h2><ul><li><a href="#">Van strandromance tot diamanten huwelijk in Zwolle</a></li><li><a href="#">Overleden persoon aangetroffen in auto bij tankstation</a></li><li><a href="#">Zwolse Bärbel Brouwer zoekt Indische stadsgenoten</a></li></ul></div>';
	echo '<div class="widget"><h2 class="widget-title">Adverteren</h2><p>Bereik de inwoners van Zwolle met een banner op deze pagina.</p></div>';

	return true;
}

function add_shortcode( ...$args ) {
	return true;
}

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();

	foreach ( (array) $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}

	return $out;
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_page_by_path( $path, $output = null, $post_type = 'page' ) {
	return null;
}

function has_shortcode( $content, $tag ) {
	return false !== strpos( (string) $content, '[' . $tag );
}

function update_option( $option, $value, $autoload = null ) {
	return true;
}

function get_permalink( $post = null ) {
	return '#';
}

function get_post( $post = null ) {
	return null;
}

function get_term( $term = null, $taxonomy = '' ) {
	return null;
}

function is_tax( $taxonomy = '', $term = '' ) {
	return false;
}

function is_singular( $post_types = '' ) {
	return false;
}

function get_option( $name, $default = false ) {
	if ( WORDPRESS_PLAYLISTS_OPTION === $name ) {
		return array();
	}

	return $default;
}

function get_post_status( $post_id ) {
	return false;
}

function get_post_field( $field, $post_id ) {
	return '';
}

function get_query_var( $var ) {
	return '';
}

function current_time( $type ) {
	return date( (string) $type );
}

function date_i18n( $format, $timestamp = null ) {
	return date( (string) $format, $timestamp ?? time() );
}

function get_bloginfo( $show ) {
	return 'Salland1';
}

function get_terms( $args = array() ) {
	return array();
}

function get_term_meta( $term_id, $key = '', $single = false ) {
	return '';
}

function update_termmeta_cache( $ids ) {
	return array();
}

function wp_list_pluck( $list, $field ) {
	return array_column( (array) $list, $field );
}

function get_term_link( $term ) {
	return '/artiest/voorbeeld/';
}

function is_wp_error( $thing ) {
	return false;
}

final class Preview_WPDB {
	public $posts = 'wp_posts';
	public $termmeta = 'wp_termmeta';
	public $term_relationships = 'wp_term_relationships';
	public $term_taxonomy = 'wp_term_taxonomy';

	public function get_col( $query ) {
		return array();
	}

	public function esc_like( $text ) {
		return $text;
	}

	public function prepare( $query, ...$args ) {
		return $query;
	}
}

$wpdb = new Preview_WPDB();

require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-palette.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-renderer.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-components.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-station.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-settings.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-taxonomy.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-context.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-playlist-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-hub-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-month-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-artist-view.php';
require_once WORDPRESS_PLAYLISTS_DIR . 'includes/class-archive-links.php';

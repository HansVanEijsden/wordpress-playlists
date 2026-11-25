<?php
/**
 * Taxonomy
 *
 * Registers the playlist_artist taxonomy and the editorial term meta fields.
 * The taxonomy is the artist's identity: its archive URL, its editable name,
 * biography and local-artist flag. Play counts live in term meta.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Taxonomy {

	/** Taxonomy name. */
	public const NAME = 'playlist_artist';

	/** Editorial: artist photo attachment ID. */
	public const META_IMAGE = '_wppl_image_id';

	/** Editorial: short biography (safe HTML). */
	public const META_BIO = '_wppl_bio';

	/** Editorial: local-artist flag. */
	public const META_LOCAL = '_wppl_local';

	/** Editorial: website URL. */
	public const META_WEBSITE = '_wppl_website';

	/** Editorial: Instagram URL. */
	public const META_INSTAGRAM = '_wppl_instagram';

	/** Editorial: Spotify URL. */
	public const META_SPOTIFY = '_wppl_spotify';

	/** Editorial: YouTube URL. */
	public const META_YOUTUBE = '_wppl_youtube';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 11 );
		add_action( 'init', array( $this, 'register_meta' ), 12 );
		add_filter( 'register_post_type_args', array( $this, 'playlist_post_type_args' ), 10, 2 );
	}

	/**
	 * Remove the site's front path from playlist permalinks when the setting is
	 * enabled (for a site whose playlist URLs currently include /nieuws/…).
	 *
	 * @param array  $args      Post type arguments.
	 * @param string $post_type Post type name.
	 * @return array
	 */
	public function playlist_post_type_args( $args, $post_type ): array {
		if ( 'playlist' !== $post_type || ! is_array( $args ) || empty( Context::setting( 'clean_playlist_urls', false ) ) ) {
			return is_array( $args ) ? $args : array();
		}

		if ( isset( $args['rewrite'] ) && is_array( $args['rewrite'] ) ) {
			$args['rewrite']['with_front'] = false;
			$args['rewrite']['slug']       = 'playlist';
		}

		return $args;
	}

	/**
	 * Register the taxonomy.
	 */
	public function register(): void {
		$base = Context::artist_base();

		$labels = array(
			'name'                       => _x( 'Artists', 'taxonomy general name', 'wordpress-playlists' ),
			'singular_name'              => _x( 'Artist', 'taxonomy singular name', 'wordpress-playlists' ),
			'search_items'               => __( 'Search artists', 'wordpress-playlists' ),
			'all_items'                  => __( 'All artists', 'wordpress-playlists' ),
			'edit_item'                  => __( 'Edit artist', 'wordpress-playlists' ),
			'update_item'                => __( 'Update artist', 'wordpress-playlists' ),
			'add_new_item'               => __( 'Add new artist', 'wordpress-playlists' ),
			'new_item_name'              => __( 'New artist name', 'wordpress-playlists' ),
			'menu_name'                  => __( 'Artists', 'wordpress-playlists' ),
			'not_found'                  => __( 'No artists found.', 'wordpress-playlists' ),
			'no_terms'                   => __( 'No artists', 'wordpress-playlists' ),
			'items_list_navigation'      => __( 'Artists list navigation', 'wordpress-playlists' ),
			'items_list'                 => __( 'Artists list', 'wordpress-playlists' ),
			'back_to_items'              => __( 'Back to artists', 'wordpress-playlists' ),
			'item_link'                  => _x( 'Artist Link', 'navigation link block title', 'wordpress-playlists' ),
			'item_link_description'      => _x( 'A link to an artist.', 'navigation link block description', 'wordpress-playlists' ),
		);

		register_taxonomy(
			self::NAME,
			array( 'playlist' ),
			array(
				'labels'            => $labels,
				'public'            => true,
				'publicly_queryable' => true,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_rest'      => true,
				'show_tagcloud'     => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => $base,
					'with_front' => false,
					'feeds'      => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Register the editorial term meta with sanitisation.
	 */
	public function register_meta(): void {
		$meta = array(
			self::META_IMAGE     => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			self::META_BIO       => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			self::META_LOCAL     => array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_bool' ),
			),
			self::META_WEBSITE   => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			self::META_INSTAGRAM => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			self::META_SPOTIFY   => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			self::META_YOUTUBE   => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
		);

		foreach ( $meta as $key => $args ) {
			register_term_meta(
				self::NAME,
				$key,
				array(
					'type'              => $args['type'],
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $args['sanitize_callback'],
					'auth_callback'     => array( $this, 'auth' ),
				)
			);
		}
	}

	/**
	 * Only editors who may manage categories may read or write artist meta.
	 *
	 * @return bool
	 */
	public function auth(): bool {
		return current_user_can( 'manage_categories' );
	}

	/**
	 * Boolean sanitiser for register_term_meta.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_bool( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Whether the taxonomy is registered for playlists.
	 */
	public static function is_available(): bool {
		return taxonomy_exists( self::NAME ) && post_type_exists( 'playlist' );
	}
}

<?php
/**
 * Term Fields
 *
 * Editorial fields on the artist taxonomy: photo, biography, links and the
 * local-artist flag. Also adds two admin columns.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Term_Fields {

	/** Nonce action. */
	private const NONCE = 'wordpress_playlists_artist_meta';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( Taxonomy::NAME . '_add_form_fields', array( $this, 'add_fields' ) );
		add_action( Taxonomy::NAME . '_edit_form_fields', array( $this, 'edit_fields' ) );
		add_action( 'created_' . Taxonomy::NAME, array( $this, 'save' ) );
		add_action( 'edited_' . Taxonomy::NAME, array( $this, 'save' ) );
		add_filter( 'manage_edit-' . Taxonomy::NAME . '_columns', array( $this, 'columns' ) );
		add_filter( 'manage_' . Taxonomy::NAME . '_custom_column', array( $this, 'column_content' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( 'WordPressPlaylists\\Assets', 'enqueue_term_fields' ) );
	}

	/**
	 * Fields on the "add artist" form.
	 */
	public function add_fields(): void {
		wp_nonce_field( self::NONCE, '_wppl_artist_nonce' );
		?>
		<div class="form-field">
			<label for="wppl-artist-image"><?php esc_html_e( 'Photo', 'wordpress-playlists' ); ?></label>
			<input type="hidden" id="wppl-artist-image" name="wppl_artist_image" value="0" />
			<div class="wppl-term-image-preview" data-wppl-preview></div>
			<p>
				<button type="button" class="button" data-wppl-media-select><?php esc_html_e( 'Select image', 'wordpress-playlists' ); ?></button>
				<button type="button" class="button-link" data-wppl-media-remove hidden><?php esc_html_e( 'Remove image', 'wordpress-playlists' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Used on the artist page and the local-artists section. Without a photo the artist gets a monogram.', 'wordpress-playlists' ); ?></p>
		</div>
		<div class="form-field">
			<label for="wppl-artist-bio"><?php esc_html_e( 'Biography', 'wordpress-playlists' ); ?></label>
			<textarea id="wppl-artist-bio" name="wppl_artist_bio" rows="5"></textarea>
			<p class="description"><?php esc_html_e( 'A short biography shown on the artist page and in the structured data. Leave empty to omit it.', 'wordpress-playlists' ); ?></p>
		</div>
		<div class="form-field">
			<label for="wppl-artist-website"><?php esc_html_e( 'Website', 'wordpress-playlists' ); ?></label>
			<input type="url" id="wppl-artist-website" name="wppl_artist_website" value="" placeholder="https://" />
		</div>
		<div class="form-field">
			<label for="wppl-artist-instagram"><?php esc_html_e( 'Instagram', 'wordpress-playlists' ); ?></label>
			<input type="url" id="wppl-artist-instagram" name="wppl_artist_instagram" value="" placeholder="https://" />
		</div>
		<div class="form-field">
			<label for="wppl-artist-spotify"><?php esc_html_e( 'Spotify', 'wordpress-playlists' ); ?></label>
			<input type="url" id="wppl-artist-spotify" name="wppl_artist_spotify" value="" placeholder="https://" />
		</div>
		<div class="form-field">
			<label for="wppl-artist-youtube"><?php esc_html_e( 'YouTube', 'wordpress-playlists' ); ?></label>
			<input type="url" id="wppl-artist-youtube" name="wppl_artist_youtube" value="" placeholder="https://" />
		</div>
		<div class="form-field">
			<label>
				<input type="checkbox" name="wppl_artist_local" value="1" />
				<?php esc_html_e( 'Local artist', 'wordpress-playlists' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Local artists are highlighted and can appear in the local-artists section on the hub and the artist index.', 'wordpress-playlists' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Fields on the "edit artist" form.
	 *
	 * @param \WP_Term $term Term being edited.
	 */
	public function edit_fields( $term ): void {
		$term_id  = (int) $term->term_id;
		$image_id = (int) get_term_meta( $term_id, Taxonomy::META_IMAGE, true );
		$image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$bio      = (string) get_term_meta( $term_id, Taxonomy::META_BIO, true );
		$local    = (bool) get_term_meta( $term_id, Taxonomy::META_LOCAL, true );

		wp_nonce_field( self::NONCE, '_wppl_artist_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="wppl-artist-image"><?php esc_html_e( 'Photo', 'wordpress-playlists' ); ?></label></th>
			<td>
				<input type="hidden" id="wppl-artist-image" name="wppl_artist_image" value="<?php echo esc_attr( (string) $image_id ); ?>" />
				<div class="wppl-term-image-preview" data-wppl-preview><?php if ( '' !== $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" style="max-width:120px;height:auto;border-radius:8px;" /><?php endif; ?></div>
				<p>
					<button type="button" class="button" data-wppl-media-select><?php esc_html_e( 'Select image', 'wordpress-playlists' ); ?></button>
					<button type="button" class="button-link" data-wppl-media-remove <?php echo '' === $image ? 'hidden' : ''; ?>><?php esc_html_e( 'Remove image', 'wordpress-playlists' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Used on the artist page and the local-artists section. Without a photo the artist gets a monogram.', 'wordpress-playlists' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wppl-artist-bio"><?php esc_html_e( 'Biography', 'wordpress-playlists' ); ?></label></th>
			<td>
				<textarea id="wppl-artist-bio" name="wppl_artist_bio" rows="6" class="large-text"><?php echo esc_textarea( $bio ); ?></textarea>
				<p class="description"><?php esc_html_e( 'A short biography shown on the artist page and in the structured data. Leave empty to omit it.', 'wordpress-playlists' ); ?></p>
			</td>
		</tr>
		<?php
		$links = array(
			'website'   => array( 'label' => __( 'Website', 'wordpress-playlists' ), 'meta' => Taxonomy::META_WEBSITE ),
			'instagram' => array( 'label' => __( 'Instagram', 'wordpress-playlists' ), 'meta' => Taxonomy::META_INSTAGRAM ),
			'spotify'   => array( 'label' => __( 'Spotify', 'wordpress-playlists' ), 'meta' => Taxonomy::META_SPOTIFY ),
			'youtube'   => array( 'label' => __( 'YouTube', 'wordpress-playlists' ), 'meta' => Taxonomy::META_YOUTUBE ),
		);

		foreach ( $links as $key => $link ) :
			?>
			<tr class="form-field">
				<th scope="row"><label for="wppl-artist-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $link['label'] ); ?></label></th>
				<td>
					<input type="url" id="wppl-artist-<?php echo esc_attr( $key ); ?>" name="wppl_artist_<?php echo esc_attr( $key ); ?>" class="regular-text code" value="<?php echo esc_attr( (string) get_term_meta( $term_id, $link['meta'], true ) ); ?>" placeholder="https://" />
				</td>
			</tr>
			<?php
		endforeach;
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Local artist', 'wordpress-playlists' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="wppl_artist_local" value="1" <?php checked( $local ); ?> />
					<?php esc_html_e( 'Highlight this artist as local talent', 'wordpress-playlists' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Local artists are highlighted and can appear in the local-artists section on the hub and the artist index.', 'wordpress-playlists' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the editorial fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save( $term_id ): void {
		$term_id = (int) $term_id;

		if ( ! isset( $_POST['_wppl_artist_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wppl_artist_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$image = isset( $_POST['wppl_artist_image'] ) ? absint( $_POST['wppl_artist_image'] ) : 0;
		update_term_meta( $term_id, Taxonomy::META_IMAGE, $image );

		$bio = isset( $_POST['wppl_artist_bio'] ) ? wp_kses_post( wp_unslash( (string) $_POST['wppl_artist_bio'] ) ) : '';
		update_term_meta( $term_id, Taxonomy::META_BIO, $bio );

		$links = array(
			'website'   => Taxonomy::META_WEBSITE,
			'instagram' => Taxonomy::META_INSTAGRAM,
			'spotify'   => Taxonomy::META_SPOTIFY,
			'youtube'   => Taxonomy::META_YOUTUBE,
		);

		foreach ( $links as $key => $meta_key ) {
			$value = isset( $_POST[ 'wppl_artist_' . $key ] ) ? esc_url_raw( wp_unslash( (string) $_POST[ 'wppl_artist_' . $key ] ) ) : '';
			update_term_meta( $term_id, $meta_key, $value );
		}

		$local = ! empty( $_POST['wppl_artist_local'] );
		update_term_meta( $term_id, Taxonomy::META_LOCAL, $local ? 1 : 0 );

		Cache::bump();
		Cache::forget( 'artist_letters' );

		$urls   = array();
		$artist = Context::artist_url( $term_id );
		$term   = get_term( $term_id, Taxonomy::NAME );

		if ( '' !== $artist ) {
			$urls[] = $artist;
		}

		$urls[] = Context::artists_index_url();
		$urls[] = Context::hub_url();

		if ( $term instanceof \WP_Term ) {
			$letter = Context::letter_for( (string) $term->name );
			$urls[] = Context::artists_index_url( $letter );
		}

		Cache::purge_urls( array_values( array_unique( array_filter( $urls ) ) ) );
	}

	/**
	 * Add the admin columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();

		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'name' === $key ) {
				$new['wppl_local'] = __( 'Local', 'wordpress-playlists' );
				$new['wppl_plays'] = __( 'Plays', 'wordpress-playlists' );
			}
		}

		return $new;
	}

	/**
	 * Render a custom column.
	 *
	 * @param string $content Column content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 */
	public function column_content( $content, $column, $term_id ): string {
		if ( 'wppl_local' === $column ) {
			return (bool) get_term_meta( (int) $term_id, Taxonomy::META_LOCAL, true )
				? esc_html__( 'Yes', 'wordpress-playlists' )
				: '—';
		}

		if ( 'wppl_plays' === $column ) {
			$plays = (int) get_term_meta( (int) $term_id, Artist_Index::META_PLAY_COUNT, true );
			$url   = Context::artist_url( (int) $term_id );

			if ( '' !== $url ) {
				return '<a href="' . esc_url( $url ) . '">' . esc_html( number_format_i18n( $plays ) ) . '</a>';
			}

			return esc_html( number_format_i18n( $plays ) );
		}

		return (string) $content;
	}
}

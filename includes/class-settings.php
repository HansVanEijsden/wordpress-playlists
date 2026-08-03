<?php
/**
 * Settings
 *
 * Stores station configuration in a single option and renders the admin page
 * under Settings > WordPress Playlists. No secrets are ever hardcoded or
 * displayed; API keys are entered (and never shown) here.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Settings {

	/**
	 * Default stations. API keys are intentionally empty; they must be
	 * configured per site in the admin.
	 */
	public static function defaults(): array {
		return array(
			'stations' => array(
				'1zwolle'  => array(
					'id'                => '1zwolle',
					'name'              => '1Zwolle',
					'enabled'           => true,
					'rest_namespace'    => '1zwolle/v1',
					'api_endpoint'      => 'https://1zwolle.nl/wp-json/metadata/v1/current',
					'api_key'           => '',
					'fallback_image_id' => 195562,
					'post_author'       => 1,
				),
				'salland1' => array(
					'id'                => 'salland1',
					'name'              => 'Salland1',
					'enabled'           => true,
					'rest_namespace'    => 'salland1/v1',
					'api_endpoint'      => 'https://www.salland1.nl/wp-json/metadata/v1/current',
					'api_key'           => '',
					'fallback_image_id' => 196806,
					'post_author'       => 70,
				),
			),
		);
	}

	/**
	 * Get the full settings array, merged over the defaults so that newly
	 * added configuration keys appear even on existing installs.
	 */
	public static function get(): array {
		$saved    = get_option( WORDPRESS_PLAYLISTS_OPTION, array() );
		$defaults = self::defaults();

		if ( empty( $saved['stations'] ) || ! is_array( $saved['stations'] ) ) {
			return $defaults;
		}

		foreach ( $saved['stations'] as $id => $data ) {
			if ( isset( $defaults['stations'][ $id ] ) ) {
				$saved['stations'][ $id ] = array_merge(
					$defaults['stations'][ $id ],
					is_array( $data ) ? $data : array()
				);
			}
		}

		return $saved;
	}

	/**
	 * All stations as Station value objects, keyed by station id.
	 *
	 * @return Station[]
	 */
	public static function stations(): array {
		$stations = array();

		foreach ( ( self::get()['stations'] ?? array() ) as $data ) {
			$station = new Station( $data );
			$stations[ $station->id() ] = $station;
		}

		return $stations;
	}

	/**
	 * Stations that are enabled and fully configured (including an API key).
	 * Only these get a REST route registered.
	 *
	 * @return Station[]
	 */
	public static function enabled_stations(): array {
		return array_filter(
			self::stations(),
			static function ( Station $station ): bool {
				return $station->is_ready();
			}
		);
	}

	/**
	 * Names of stations that are enabled but still missing an API key.
	 *
	 * @return string[]
	 */
	public static function stations_missing_keys(): array {
		$missing = array();

		foreach ( self::stations() as $station ) {
			if ( $station->enabled() && '' === $station->api_key() ) {
				$missing[] = $station->name();
			}
		}

		return $missing;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu(): void {
		add_options_page(
			esc_html__( 'WordPress Playlists', 'wordpress-playlists' ),
			esc_html__( 'WordPress Playlists', 'wordpress-playlists' ),
			'manage_options',
			'wordpress-playlists',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'wordpress_playlists',
			WORDPRESS_PLAYLISTS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize the whole settings array before it is persisted.
	 *
	 * @param mixed $input Raw submitted settings.
	 * @return array
	 */
	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		$clean = array( 'stations' => array() );

		$stations = ( isset( $input['stations'] ) && is_array( $input['stations'] ) ) ? $input['stations'] : array();

		foreach ( $stations as $id => $data ) {
			$station_id = sanitize_key( (string) $id );

			if ( '' === $station_id || ! is_array( $data ) ) {
				continue;
			}

			// Deleting a station is explicit: a checkbox must be ticked.
			if ( ! empty( $data['delete'] ) ) {
				continue;
			}

			$clean['stations'][ $station_id ] = array(
				'id'                => $station_id,
				'name'              => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
				'enabled'           => ! empty( $data['enabled'] ),
				'rest_namespace'    => $this->sanitize_namespace( (string) ( $data['rest_namespace'] ?? '' ) ),
				'api_endpoint'      => esc_url_raw( (string) ( $data['api_endpoint'] ?? '' ) ),
				'api_key'           => $this->sanitize_api_key( $station_id, $data['api_key'] ?? '' ),
				'fallback_image_id' => absint( $data['fallback_image_id'] ?? 0 ),
				'post_author'       => absint( $data['post_author'] ?? 0 ),
			);
		}

		// Add a brand new station if a slug was provided on the settings page.
		$new_slug = sanitize_key( (string) ( $input['new_station_slug'] ?? '' ) );
		if ( '' !== $new_slug && ! isset( $clean['stations'][ $new_slug ] ) ) {
			$clean['stations'][ $new_slug ] = array(
				'id'                => $new_slug,
				'name'              => ucfirst( $new_slug ),
				'enabled'           => false,
				'rest_namespace'    => $new_slug . '/v1',
				'api_endpoint'      => '',
				'api_key'           => '',
				'fallback_image_id' => 0,
				'post_author'       => 0,
			);
		}

		return $clean;
	}

	/**
	 * Normalize a REST namespace (trim slashes, strip protocols).
	 */
	private function sanitize_namespace( string $value ): string {
		$value = sanitize_text_field( $value );
		$value = preg_replace( '#^https?://[^/]+/#i', '', $value );
		return trim( $value, '/' );
	}

	/**
	 * Never display or echo the key. An empty submission keeps the currently
	 * stored key, so saving other fields cannot wipe the secret.
	 */
	private function sanitize_api_key( string $station_id, $submitted ): string {
		$submitted = is_string( $submitted ) ? trim( $submitted ) : '';

		if ( '' === $submitted ) {
			$current = self::get();
			return (string) ( $current['stations'][ $station_id ]['api_key'] ?? '' );
		}

		if ( strlen( $submitted ) < 8 ) {
			add_settings_error(
				WORDPRESS_PLAYLISTS_OPTION,
				'wordpress_playlists_short_key',
				sprintf(
					/* translators: %s: station id */
					__( 'The API key for "%s" must be at least 8 characters long. The previous key was kept.', 'wordpress-playlists' ),
					$station_id
				),
				'error'
			);

			$current = self::get();
			return (string) ( $current['stations'][ $station_id ]['api_key'] ?? '' );
		}

		return $submitted;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stations = self::get()['stations'] ?? array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WordPress Playlists', 'wordpress-playlists' ); ?></h1>
			<p>
				<?php esc_html_e( 'Configure one station per radio channel. A metadata hub POSTs each played track to the station\'s REST endpoint; the plugin finds or creates today\'s playlist for the current show and appends the track to it.', 'wordpress-playlists' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wordpress_playlists' ); ?>

				<?php foreach ( $stations as $id => $station ) { $this->render_station( $id, $station ); } ?>

				<h2><?php esc_html_e( 'Add a station', 'wordpress-playlists' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wordpress-playlists-new-slug"><?php esc_html_e( 'New station slug', 'wordpress-playlists' ); ?></label></th>
						<td>
							<input type="text" id="wordpress-playlists-new-slug" class="regular-text" name="wordpress_playlists_settings[new_station_slug]" value="" placeholder="e.g. rtv1ijsseldelta" />
							<p class="description">
								<?php esc_html_e( 'Enter a unique slug (lowercase letters, numbers and dashes) and save to add a new station. It is created disabled with an empty API key.', 'wordpress-playlists' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the settings rows for a single station.
	 *
	 * @param string $id      Station id (array key).
	 * @param array  $station Merged station settings.
	 */
	private function render_station( string $id, array $station ): void {
		$name = sanitize_text_field( (string) ( $station['name'] ?? '' ) );
		?>
		<h2><?php echo esc_html( $name ? $name : $id ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable station', 'wordpress-playlists' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][enabled]" value="1" <?php checked( ! empty( $station['enabled'] ) ); ?> />
						<?php esc_html_e( 'Register this station\'s REST endpoint', 'wordpress-playlists' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-name-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Station name', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="text" id="wp-playlists-name-<?php echo esc_attr( $id ); ?>" class="regular-text" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-ns-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'REST namespace', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="text" id="wp-playlists-ns-<?php echo esc_attr( $id ); ?>" class="regular-text code" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][rest_namespace]" value="<?php echo esc_attr( (string) ( $station['rest_namespace'] ?? '' ) ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Track endpoint:', 'wordpress-playlists' ); ?>
						<code><?php echo esc_html( rest_url( ( (string) ( $station['rest_namespace'] ?? '' ) ) . '/playlist/track' ) ); ?></code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-endpoint-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Metadata API endpoint', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="url" id="wp-playlists-endpoint-<?php echo esc_attr( $id ); ?>" class="regular-text code" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][api_endpoint]" value="<?php echo esc_attr( (string) ( $station['api_endpoint'] ?? '' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Returns the current show; response shape broadcast.current_show.show.{id,name,slug,avatar_id}.', 'wordpress-playlists' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-key-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'API key', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="password" id="wp-playlists-key-<?php echo esc_attr( $id ); ?>" class="regular-text code" autocomplete="new-password" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][api_key]" value="" placeholder="••••••••••••••" />
					<p class="description">
						<?php esc_html_e( 'Accepted via the X-API-Key header, an Authorization: Bearer header, or a ?secret= query parameter. Leave blank to keep the current key. The key is never displayed.', 'wordpress-playlists' ); ?>
						<?php
						if ( '' !== (string) ( $station['api_key'] ?? '' ) ) {
							esc_html_e( 'A key is currently configured.', 'wordpress-playlists' );
						} else {
							esc_html_e( 'No key configured yet — tracks will be rejected until one is saved.', 'wordpress-playlists' );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-fallback-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Fallback image ID', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="number" id="wp-playlists-fallback-<?php echo esc_attr( $id ); ?>" min="0" step="1" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][fallback_image_id]" value="<?php echo esc_attr( (string) absint( $station['fallback_image_id'] ?? 0 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Attachment ID used for the playlist featured image when the current show has no avatar.', 'wordpress-playlists' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-author-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Post author ID', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="number" id="wp-playlists-author-<?php echo esc_attr( $id ); ?>" min="0" step="1" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][post_author]" value="<?php echo esc_attr( (string) absint( $station['post_author'] ?? 0 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'User ID assigned to created playlist posts.', 'wordpress-playlists' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete station', 'wordpress-playlists' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wordpress_playlists_settings[stations][<?php echo esc_attr( $id ); ?>][delete]" value="1" />
						<?php esc_html_e( 'Delete this station when saving', 'wordpress-playlists' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}
}

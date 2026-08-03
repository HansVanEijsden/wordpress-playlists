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
	 * Plugin defaults. No stations are pre-configured; the site administrator
	 * adds them on the settings page so the plugin ships without any
	 * site-specific data.
	 */
	public static function defaults(): array {
		return array(
			'enabled'                       => true,
			'station_name'                  => '',
			'rest_namespace'                => 'playlists/v1',
			'api_endpoint'                  => '',
			'api_key'                       => '',
			'fallback_image_id'             => 0,
			'post_author'                   => 0,
			'comments_label'                => 'Start Time',
			'delete_playlists_on_uninstall' => false,
		);
	}

	/**
	 * Get the full settings array, merged over the defaults so that newly
	 * added configuration keys appear even on existing installs.
	 */
	public static function get(): array {
		$saved    = get_option( WORDPRESS_PLAYLISTS_OPTION, array() );
		$defaults = self::defaults();

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// Top-level merge so new keys appear on existing installs.
		return array_merge( $defaults, $saved );
	}

	/**
	 * The single station for this site, built from the settings.
	 */
	public static function station(): Station {
		return new Station( self::get() );
	}

	/**
	 * Whether the station is enabled but missing its API key (used for an
	 * admin notice).
	 */
	public static function missing_api_key(): bool {
		$station = self::station();
		return $station->enabled() && '' === $station->api_key();
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Load the media library + settings JS only on this plugin's settings page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'settings_page_wordpress-playlists' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'wordpress-playlists-settings',
			WORDPRESS_PLAYLISTS_URL . 'assets/js/settings.js',
			array( 'jquery' ),
			WORDPRESS_PLAYLISTS_VERSION,
			true
		);
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

		return array(
			'enabled'                       => ! empty( $input['enabled'] ),
			'station_name'                  => sanitize_text_field( (string) ( $input['station_name'] ?? '' ) ),
			'rest_namespace'                => $this->sanitize_namespace( (string) ( $input['rest_namespace'] ?? '' ) ),
			'api_endpoint'                  => esc_url_raw( (string) ( $input['api_endpoint'] ?? '' ) ),
			'api_key'                       => $this->sanitize_api_key( $input['api_key'] ?? '' ),
			'fallback_image_id'             => absint( $input['fallback_image_id'] ?? 0 ),
			'post_author'                   => absint( $input['post_author'] ?? 0 ),
			'comments_label'                => trim( sanitize_text_field( (string) ( $input['comments_label'] ?? '' ) ) ),
			'delete_playlists_on_uninstall' => ! empty( $input['delete_playlists_on_uninstall'] ),
		);
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
	private function sanitize_api_key( $submitted ): string {
		$submitted = is_string( $submitted ) ? trim( $submitted ) : '';

		if ( '' === $submitted ) {
			$current = self::get();
			return (string) ( $current['api_key'] ?? '' );
		}

		if ( strlen( $submitted ) < 8 ) {
			add_settings_error(
				WORDPRESS_PLAYLISTS_OPTION,
				'wordpress_playlists_short_key',
				__( 'The API key must be at least 8 characters long. The previous key was kept.', 'wordpress-playlists' ),
				'error'
			);

			$current = self::get();
			return (string) ( $current['api_key'] ?? '' );
		}

		return $submitted;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WordPress Playlists', 'wordpress-playlists' ); ?></h1>
			<p>
				<?php esc_html_e( 'Each site has one radio station. A metadata hub POSTs each played track to this station\'s REST endpoint; the plugin finds or creates today\'s playlist for the current show and appends the track to it.', 'wordpress-playlists' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wordpress_playlists' ); ?>

				<?php $this->render_station_fields( $settings ); ?>
				<?php $this->render_general_settings( $settings ); ?>

				<?php submit_button(); ?>
			</form>

			<?php $this->render_hub_config( $settings ); ?>
		</div>
		<?php
	}

	/**
	 * Render the station configuration rows.
	 *
	 * @param array $settings Merged settings.
	 */
	private function render_station_fields( array $settings ): void {
		?>
		<h2><?php esc_html_e( 'Station', 'wordpress-playlists' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable station', 'wordpress-playlists' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wordpress_playlists_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
						<?php esc_html_e( 'Register the station\'s REST endpoint', 'wordpress-playlists' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-name"><?php esc_html_e( 'Station name', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="text" id="wp-playlists-name" class="regular-text" name="wordpress_playlists_settings[station_name]" value="<?php echo esc_attr( (string) ( $settings['station_name'] ?? '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-ns"><?php esc_html_e( 'REST namespace', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="text" id="wp-playlists-ns" class="regular-text code" name="wordpress_playlists_settings[rest_namespace]" value="<?php echo esc_attr( (string) ( $settings['rest_namespace'] ?? '' ) ); ?>" />
					<p class="description">
						<?php esc_html_e( 'The path segment of the track endpoint between /wp-json/ and /playlist/track. Set it to the namespace your metadata hub POSTs to.', 'wordpress-playlists' ); ?>
						<br />
						<?php esc_html_e( 'Track endpoint:', 'wordpress-playlists' ); ?>
						<code><?php echo esc_html( rest_url( ( (string) ( $settings['rest_namespace'] ?? '' ) ) . '/playlist/track' ) ); ?></code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-endpoint"><?php esc_html_e( 'Metadata API endpoint', 'wordpress-playlists' ); ?></label></th>
				<td>
					<?php $default_endpoint = trailingslashit( site_url() ) . 'wp-json/metadata/v1/current'; ?>
					<input type="url" id="wp-playlists-endpoint" class="regular-text code" name="wordpress_playlists_settings[api_endpoint]" value="<?php echo esc_attr( (string) ( $settings['api_endpoint'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $default_endpoint ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Returns the current show; response shape broadcast.current_show.show.{id,name,slug,avatar_id}.', 'wordpress-playlists' ); ?>
						<br />
						<?php esc_html_e( 'Leave blank to auto-detect:', 'wordpress-playlists' ); ?>
						<code><?php echo esc_html( $default_endpoint ); ?></code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wp-playlists-key"><?php esc_html_e( 'API key', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="password" id="wp-playlists-key" class="regular-text code" autocomplete="new-password" name="wordpress_playlists_settings[api_key]" value="" placeholder="••••••••••••••" />
					<p class="description">
						<?php esc_html_e( 'Accepted via the X-API-Key header, an Authorization: Bearer header, or a ?secret= query parameter (the metadata hub uses a bearer token). Leave blank to keep the current key. The key is never displayed.', 'wordpress-playlists' ); ?>
						<?php
						if ( '' !== (string) ( $settings['api_key'] ?? '' ) ) {
							esc_html_e( 'A key is currently configured.', 'wordpress-playlists' );
						} else {
							esc_html_e( 'No key configured yet — tracks will be rejected until one is saved.', 'wordpress-playlists' );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fallback image', 'wordpress-playlists' ); ?></th>
				<td>
					<?php $fallback_id = absint( $settings['fallback_image_id'] ?? 0 ); ?>
					<input type="hidden" id="wp-playlists-fallback" name="wordpress_playlists_settings[fallback_image_id]" value="<?php echo esc_attr( (string) $fallback_id ); ?>" />
					<div id="wp-playlists-fallback-preview">
						<?php
						if ( $fallback_id ) {
							$fallback_url = wp_get_attachment_image_url( $fallback_id, 'thumbnail' );
							if ( $fallback_url ) {
								echo '<img src="' . esc_url( $fallback_url ) . '" alt="" style="max-width:120px;height:auto;border:1px solid #ccd0d4;border-radius:4px;" />';
							}
						}
						?>
					</div>
					<p>
						<button type="button" class="button" id="wp-playlists-fallback-select"><?php esc_html_e( 'Select image', 'wordpress-playlists' ); ?></button>
						<button type="button" class="button" id="wp-playlists-fallback-remove" style="<?php echo $fallback_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'wordpress-playlists' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Image used for the playlist featured image when the current show has no avatar. Choose it from the media library; the attachment ID is stored.', 'wordpress-playlists' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post author', 'wordpress-playlists' ); ?></th>
				<td>
					<?php
					wp_dropdown_users(
						array(
							'name'              => 'wordpress_playlists_settings[post_author]',
							'id'                => 'wp-playlists-author',
							'selected'          => absint( $settings['post_author'] ?? 0 ),
							'show_option_none'  => __( 'Select an author', 'wordpress-playlists' ),
							'option_none_value' => '0',
							'role__not_in'      => array( 'subscriber' ),
							'show'              => 'display_name_with_login',
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'User assigned to created playlist posts.', 'wordpress-playlists' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the plugin-wide settings (comment column label, uninstall
	 * behavior).
	 */
	private function render_general_settings( array $settings ): void {
		?>
		<h2><?php esc_html_e( 'General', 'wordpress-playlists' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wp-playlists-comments-label"><?php esc_html_e( 'Playlist comment column label', 'wordpress-playlists' ); ?></label></th>
				<td>
					<input type="text" id="wp-playlists-comments-label" class="regular-text" name="wordpress_playlists_settings[comments_label]" value="<?php echo esc_attr( (string) ( $settings['comments_label'] ?? '' ) ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Radio Station\'s playlist entries have a "Comments" field, which this plugin uses to store each track\'s start time. Use this setting to rename that column\'s header (default "Start Time"). Leave empty to keep Radio Station\'s wording.', 'wordpress-playlists' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall behavior', 'wordpress-playlists' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="wordpress_playlists_settings[delete_playlists_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_playlists_on_uninstall'] ) ); ?> />
						<?php esc_html_e( 'Delete all playlist posts when the plugin is uninstalled', 'wordpress-playlists' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'WARNING: playlists are valuable content created by the Radio Station plugin. When enabled, uninstalling this plugin permanently deletes every playlist post - this cannot be undone. It is disabled by default and should stay disabled unless you are certain. Uninstalling only ever removes this plugin\'s own settings option otherwise.', 'wordpress-playlists' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render a ready-to-paste output block for the metadata hub's config.json.
	 * The API key itself is never displayed; the hub must use the same value
	 * as the API key setting above.
	 *
	 * @param array $settings Merged settings.
	 */
	private function render_hub_config( array $settings ): void {
		$namespace = (string) ( $settings['rest_namespace'] ?? '' );
		$url       = rest_url( $namespace . '/playlist/track' );

		$output = array(
			'type'     => 'url',
			'name'     => 'radio-playlist-website',
			'inputs'   => array( 'radio-playout-main' ),
			'settings' => array(
				'delay'          => 10,
				'url'            => $url,
				'method'         => 'POST',
				'bearerToken'    => '<same value as the API key setting above>',
				'payloadMapping' => array(
					'artist'   => '{{.artist}}',
					'title'    => '{{.title}}',
					'duration' => '{{.duration}}',
				),
			),
		);

		$json = wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		?>
		<h2><?php esc_html_e( 'Metadata hub configuration', 'wordpress-playlists' ); ?></h2>
		<p>
			<?php esc_html_e( 'Paste this output block into the metadata hub\'s config.json (adapt name, inputs and delay to your hub). The bearerToken must be the same value as the API key configured above; it is never displayed here.', 'wordpress-playlists' ); ?>
		</p>
		<div id="wp-playlists-hub-config">
			<pre><code><?php echo esc_html( (string) $json ); ?></code></pre>
			<button type="button" class="button" id="wp-playlists-copy-hub-config"><?php esc_html_e( 'Copy', 'wordpress-playlists' ); ?></button>
		</div>
		<?php
	}
}

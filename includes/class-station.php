<?php
/**
 * Station
 *
 * Immutable value object for this site's station configuration, built from the
 * settings option. Keeps the admin-configured values typed and sanitized so
 * the rest of the plugin can trust them. Each site has exactly one station.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Station {

	private bool $enabled;
	private string $name;
	private string $rest_namespace;
	private string $api_endpoint;
	private string $api_key;
	private int $fallback_image_id;
	private int $post_author;

	/**
	 * @param array $data Raw settings from the options table.
	 */
	public function __construct( array $data ) {
		$this->enabled           = ! empty( $data['enabled'] );
		$this->name              = sanitize_text_field( (string) ( $data['station_name'] ?? '' ) );
		$this->rest_namespace    = sanitize_text_field( (string) ( $data['rest_namespace'] ?? '' ) );
		$this->api_endpoint      = $this->resolve_api_endpoint( $data );
		$this->api_key           = (string) ( $data['api_key'] ?? '' );
		$this->fallback_image_id = absint( $data['fallback_image_id'] ?? 0 );
		$this->post_author       = absint( $data['post_author'] ?? 0 );
	}

	public function enabled(): bool {
		return $this->enabled;
	}

	public function name(): string {
		return $this->name;
	}

	public function rest_namespace(): string {
		return $this->rest_namespace;
	}

	public function api_endpoint(): string {
		return $this->api_endpoint;
	}

	public function api_key(): string {
		return $this->api_key;
	}

	public function fallback_image_id(): int {
		return $this->fallback_image_id;
	}

	public function post_author(): int {
		return $this->post_author;
	}

	/**
	 * Whether the station is fully configured. The REST endpoint is only
	 * registered when this returns true.
	 */
	public function is_ready(): bool {
		return $this->enabled
			&& '' !== $this->name
			&& '' !== $this->rest_namespace
			&& '' !== $this->api_endpoint
			&& '' !== $this->api_key;
	}

	/**
	 * Resolve the metadata API endpoint. An explicitly configured URL wins;
	 * otherwise the plugin auto-detects the conventional
	 * "/wp-json/metadata/v1/current" endpoint on this site, so the field can
	 * be left blank on setups that follow that convention.
	 *
	 * @param array $data Raw settings from the options table.
	 */
	private function resolve_api_endpoint( array $data ): string {
		$configured = esc_url_raw( (string) ( $data['api_endpoint'] ?? '' ) );

		if ( '' !== $configured ) {
			return $configured;
		}

		return trailingslashit( site_url() ) . 'wp-json/metadata/v1/current';
	}
}

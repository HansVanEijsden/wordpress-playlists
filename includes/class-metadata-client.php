<?php
/**
 * Metadata Client
 *
 * Fetches the currently broadcasting show from a station's metadata API.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Metadata_Client {

	private Station $station;

	public function __construct( Station $station ) {
		$this->station = $station;
	}

	/**
	 * Fetch the current show.
	 *
	 * @return array{id:int,name:string,slug:string,avatar_id:int|null}|false
	 *         Show data, or false on any failure (network, status, JSON,
	 *         or missing current_show).
	 */
	public function get_current_show() {
		$response = wp_remote_get(
			$this->station->api_endpoint(),
			array(
				'timeout' => 30,
				'headers' => array(
					'User-Agent' => 'WordPress-Playlists/' . WORDPRESS_PLAYLISTS_VERSION,
					'Accept'     => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::log( $this->station->name(), 'Metadata API request failed: ' . $response->get_error_message() );
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			Logger::log( $this->station->name(), 'Metadata API returned HTTP ' . $status );
			return false;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $body ) ) {
			Logger::log( $this->station->name(), 'Metadata API returned invalid JSON' );
			return false;
		}

		if ( empty( $body['broadcast']['current_show']['show'] ) ) {
			Logger::log( $this->station->name(), 'Metadata API response contains no current_show' );
			return false;
		}

		$show = $body['broadcast']['current_show']['show'];

		return array(
			'id'        => (int) ( $show['id'] ?? 0 ),
			'name'      => sanitize_text_field( (string) ( $show['name'] ?? '' ) ),
			'slug'      => sanitize_title( (string) ( $show['slug'] ?? '' ) ),
			'avatar_id' => ! empty( $show['avatar_id'] ) ? (int) $show['avatar_id'] : null,
		);
	}
}

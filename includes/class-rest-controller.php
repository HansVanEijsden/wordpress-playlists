<?php
/**
 * REST Controller
 *
 * Registers a per-station "playlist/track" endpoint and authenticates every
 * request against that station's admin-configured API key. The API key lives
 * only in the options table - never in source code.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Rest_Controller {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register a track endpoint for every fully configured station.
	 * Route shape: /wp-json/<namespace>/playlist/track (POST).
	 */
	public function register_routes(): void {
		foreach ( Settings::enabled_stations() as $station ) {
			$namespace = $station->rest_namespace();
			if ( '' === $namespace ) {
				continue;
			}

			register_rest_route(
				$namespace,
				'/playlist/track',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => function ( \WP_REST_Request $request ) use ( $station ) {
						return $this->handle_track_request( $request, $station );
					},
					'permission_callback' => function ( \WP_REST_Request $request ) use ( $station ) {
						return $this->check_permission( $request, $station );
					},
					'args'                => $this->route_args(),
				)
			);
		}
	}

	/**
	 * Route argument definitions (validation + sanitization).
	 */
	private function route_args(): array {
		return array(
			'artist'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'title'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'duration' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'album'    => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'songID'   => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Authenticate a request. Accepted methods:
	 *  - X-API-Key header
	 *  - Authorization: Bearer <key>
	 *  - ?secret=<key> query parameter
	 */
	public function check_permission( \WP_REST_Request $request, Station $station ): bool {
		$key = $station->api_key();
		if ( '' === $key ) {
			Logger::log( $station->name(), 'Rejected request: no API key configured yet.' );
			return false;
		}

		$header_key  = (string) $request->get_header( 'X-API-Key' );
		$auth_header = (string) $request->get_header( 'Authorization' );
		$bearer      = preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ? trim( $matches[1] ) : '';
		$secret      = (string) $request->get_param( 'secret' );

		if (
			$this->secure_compare( $key, $header_key )
			|| $this->secure_compare( $key, $bearer )
			|| $this->secure_compare( $key, $secret )
		) {
			return true;
		}

		Logger::log( $station->name(), sprintf( 'Rejected unauthenticated track request from %s', $this->client_ip() ) );
		return false;
	}

	/**
	 * Constant-time string comparison.
	 */
	private function secure_compare( string $known, string $user ): bool {
		if ( '' === $known || '' === $user ) {
			return false;
		}

		return hash_equals( $known, $user );
	}

	/**
	 * Handle an authenticated track POST for one station.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_track_request( \WP_REST_Request $request, Station $station ) {
		$artist   = (string) $request->get_param( 'artist' );
		$title    = (string) $request->get_param( 'title' );
		$duration = (string) $request->get_param( 'duration' );
		$album    = (string) $request->get_param( 'album' );

		Logger::log( $station->name(), sprintf( 'Track received - Artist: %s, Title: %s, Duration: %s', $artist, $title, '' !== $duration ? $duration : 'unknown' ) );

		if ( '' === trim( $artist ) || '' === trim( $title ) ) {
			return new \WP_Error( 'missing_fields', __( 'Both artist and title are required.', 'wordpress-playlists' ), array( 'status' => 400 ) );
		}

		if ( ! post_type_exists( 'playlist' ) ) {
			Logger::log( $station->name(), 'Radio Station plugin is not active; rejecting track.' );
			return new \WP_Error( 'radio_station_inactive', __( 'The Radio Station plugin is not active.', 'wordpress-playlists' ), array( 'status' => 500 ) );
		}

		$manager = new Playlist_Manager( $station );

		if ( ! $manager->is_duration_valid( $duration ) ) {
			return new \WP_Error(
				'duration_too_short',
				sprintf(
					/* translators: %d: number of minutes */
					__( 'Track duration is shorter than %d minutes and will be skipped.', 'wordpress-playlists' ),
					(int) ( $manager->min_duration_seconds() / 60 )
				),
				array(
					'status'   => 422,
					'duration' => $duration,
				)
			);
		}

		$result = $manager->add_track( $artist, $title, $album, $duration );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Track successfully added to the playlist.', 'wordpress-playlists' ),
				'data'    => array(
					'playlist_id' => $result['playlist_id'],
					'track'       => $result['track'],
				),
			),
			200
		);
	}

	private function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	}
}

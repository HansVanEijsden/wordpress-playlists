<?php
/**
 * Gettext Filters
 *
 * The Radio Station plugin labels the playlist entry comment column
 * "Comments". The stations display it as "Starttijd" (Dutch for "start time")
 * because the value holds the track start time (HH:MM). This is a site-facing
 * label on the Dutch stations and must remain Dutch.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Gettext_Filters {

	public function __construct() {
		add_filter( 'gettext', array( $this, 'comments_to_starttijd' ), 20, 3 );
	}

	/**
	 * Rename the Radio Station "Comments"/"Comment" labels to "Starttijd".
	 *
	 * @param string $translated Translated text.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public function comments_to_starttijd( string $translated, string $text, string $domain ): string {
		if ( 'radio-station' === $domain && ( 'Comments' === $text || 'Comment' === $text ) ) {
			return 'Starttijd';
		}

		return $translated;
	}
}

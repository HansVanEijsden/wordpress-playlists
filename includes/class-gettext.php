<?php
/**
 * Gettext Filters
 *
 * Radio Station's playlist entries have a "Comments" field, which this plugin
 * uses to store each track's start time (HH:MM). The column header shown on
 * the site is admin-configurable (default "Start Time"); the rename is
 * skipped when the configured label is empty.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Gettext_Filters {

	public function __construct() {
		add_filter( 'gettext', array( $this, 'comments_column_label' ), 20, 3 );
	}

	/**
	 * Rename Radio Station's "Comments"/"Comment" labels to the configured
	 * column label.
	 *
	 * @param string $translated Translated text.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public function comments_column_label( string $translated, string $text, string $domain ): string {
		if ( 'radio-station' !== $domain || ( 'Comments' !== $text && 'Comment' !== $text ) ) {
			return $translated;
		}

		$label = self::configured_label();
		return '' !== $label ? $label : $translated;
	}

	/**
	 * The admin-configured label for the comment/start-time column.
	 */
	private static function configured_label(): string {
		$settings = Settings::get();
		$label    = isset( $settings['comments_label'] ) ? trim( sanitize_text_field( (string) $settings['comments_label'] ) ) : '';

		return $label;
	}
}


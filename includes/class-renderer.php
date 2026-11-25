<?php
/**
 * Renderer
 *
 * Small, pure formatting helpers shared by the views. Nothing here queries
 * WordPress, so the preview harness can render the real views from fixtures.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/**
	 * Normalise a clock time to H:i, or return an empty string.
	 */
	public static function time( string $time ): string {
		$time = trim( $time );

		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $matches ) ) {
			$hour = (int) $matches[1];

			if ( $hour > 23 ) {
				return '';
			}

			return sprintf( '%02d:%s', $hour, $matches[2] );
		}

		return '';
	}

	/**
	 * Minutes since midnight for a clock time, or null.
	 */
	public static function minutes( string $time ): ?int {
		$time = self::time( $time );

		if ( '' === $time ) {
			return null;
		}

		list( $hour, $minute ) = array_map( 'intval', explode( ':', $time ) );

		return ( $hour * 60 ) + $minute;
	}

	/**
	 * A readable time span, e.g. "19:05 – 19:51", or one time.
	 */
	public static function span( string $first, string $last ): string {
		$first = self::time( $first );
		$last  = self::time( $last );

		if ( '' === $first && '' === $last ) {
			return '';
		}

		if ( '' === $last || $first === $last ) {
			return $first;
		}

		return $first . ' – ' . $last;
	}

	/**
	 * Duration label from the stored minutes/seconds, e.g. "3:07".
	 */
	public static function duration( $minutes, $seconds ): string {
		$minutes = trim( (string) $minutes );
		$seconds = trim( (string) $seconds );

		if ( '' === $minutes && '' === $seconds ) {
			return '';
		}

		$minutes = max( 0, (int) $minutes );
		$seconds = max( 0, min( 59, (int) $seconds ) );

		return sprintf( '%d:%02d', $minutes, $seconds );
	}

	/**
	 * ISO 8601 duration, e.g. "PT3M7S".
	 */
	public static function duration_iso( $minutes, $seconds ): string {
		$minutes = max( 0, (int) $minutes );
		$seconds = max( 0, min( 59, (int) $seconds ) );

		if ( 0 === $minutes && 0 === $seconds ) {
			return '';
		}

		return 'PT' . $minutes . 'M' . $seconds . 'S';
	}

	/**
	 * Two-letter monogram for an artist without artwork.
	 */
	public static function initials( string $name, int $length = 2 ): string {
		$name = trim( preg_replace( '/\s+/u', ' ', $name ) );

		if ( '' === $name ) {
			return '?';
		}

		$words = preg_split( '/\s+/u', $name ) ?: array( $name );
		$parts = array();

		foreach ( $words as $word ) {
			$word = preg_replace( '/[^\p{L}\p{N}]+/u', '', $word );

			if ( '' !== $word ) {
				$parts[] = $word;
			}

			if ( count( $parts ) >= $length ) {
				break;
			}
		}

		if ( empty( $parts ) ) {
			return '?';
		}

		$initials = '';
		foreach ( $parts as $part ) {
			$letter    = function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1, 'UTF-8' ) : substr( $part, 0, 1 );
			$initials .= function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter, 'UTF-8' ) : strtoupper( $letter );
		}

		if ( 1 === count( $parts ) ) {
			$initials = function_exists( 'mb_substr' ) ? mb_substr( $parts[0], 0, $length, 'UTF-8' ) : substr( $parts[0], 0, $length );
			$initials = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials, 'UTF-8' ) : strtoupper( $initials );
		}

		return $initials;
	}

	/**
	 * Localised number.
	 */
	public static function number( $value ): string {
		return number_format_i18n( (int) $value );
	}

	/**
	 * "5 nummers" / "1 nummer" through the WordPress plural API.
	 */
	public static function count_label( int $count, string $single, string $plural ): string {
		return sprintf( _n( $single, $plural, $count, 'wordpress-playlists' ), self::number( $count ) );
	}

	/**
	 * Group times into broadcast hours, preserving order.
	 *
	 * @param array<int,string> $times H:i values.
	 * @return array<int,array{hour:string,key:string,count:int}>
	 */
	public static function hours( array $times ): array {
		$hours = array();

		foreach ( $times as $time ) {
			$time = self::time( $time );

			if ( '' === $time ) {
				continue;
			}

			$hour = substr( $time, 0, 2 );

			if ( ! isset( $hours[ $hour ] ) ) {
				$hours[ $hour ] = array(
					'hour'  => $hour,
					'key'   => 'hour-' . $hour,
					'count' => 0,
				);
			}

			$hours[ $hour ]['count']++;
		}

		return array_values( $hours );
	}

	/**
	 * Trim a description to a maximum length without cutting a word in half.
	 */
	public static function excerpt( string $text, int $length = 155 ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$cut  = mb_substr( $text, 0, $length - 1 );
		$last = strrpos( $cut, ' ' );

		if ( false !== $last ) {
			$cut = mb_substr( $cut, 0, $last );
		}

		return rtrim( $cut, " \t\n\r\0\x0B,.;:" ) . '…';
	}

	/**
	 * Build a stable anchor id from a label.
	 */
	public static function anchor( string $label ): string {
		$anchor = sanitize_title( remove_accents( $label ) );

		return '' === $anchor ? 'item' : $anchor;
	}

	/**
	 * Sanitised list of CSS classes.
	 *
	 * @param array<int,string> $classes Classes.
	 */
	public static function classes( array $classes ): string {
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}
}

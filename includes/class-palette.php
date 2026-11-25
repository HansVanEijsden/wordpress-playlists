<?php
/**
 * Palette
 *
 * Derives accessible companions for the single station accent colour. Pure
 * maths, no WordPress calls, so the preview harness and the unit tests can
 * exercise it without booting WordPress.
 *
 * @package WordPressPlaylists
 */

namespace WordPressPlaylists;

defined( 'ABSPATH' ) || exit;

final class Palette {

	/** Near-black used for text on a light or mid-tone accent. */
	public const INK = '#14161c';

	/** White. */
	public const WHITE = '#ffffff';

	/** Default accent, matching the other radio surfaces on these sites. */
	public const DEFAULT_ACCENT = '#e50526';

	/** Per-request memo of derived colours. */
	private static array $derived = array();

	/**
	 * Validate a hex colour and expand shorthand. Falls back when invalid.
	 *
	 * @param string $color    Candidate colour.
	 * @param string $fallback Colour used when the candidate is not a hex value.
	 */
	public static function sanitize( string $color, string $fallback = self::DEFAULT_ACCENT ): string {
		$color = strtolower( trim( $color ) );

		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color ) ) {
			if ( 4 === strlen( $color ) ) {
				$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
			}

			return $color;
		}

		return $fallback;
	}

	/**
	 * Hex to an RGB triplet.
	 *
	 * @return array{0:int,1:int,2:int}
	 */
	public static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( self::sanitize( $hex ), '#' );

		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * RGB triplet to hex.
	 *
	 * @param array{0:int,1:int,2:int} $rgb Colour.
	 */
	public static function rgb_to_hex( array $rgb ): string {
		return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) $rgb[0] ) ), max( 0, min( 255, (int) $rgb[1] ) ), max( 0, min( 255, (int) $rgb[2] ) ) );
	}

	/**
	 * Relative luminance per WCAG 2.x.
	 *
	 * @param array{0:int,1:int,2:int} $rgb Colour.
	 */
	public static function luminance( array $rgb ): float {
		$channels = array();
		foreach ( $rgb as $channel ) {
			$value      = $channel / 255;
			$channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}

	/**
	 * Contrast ratio between two RGB colours.
	 *
	 * @param array{0:int,1:int,2:int} $a First colour.
	 * @param array{0:int,1:int,2:int} $b Second colour.
	 */
	public static function contrast( array $a, array $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		$hi = max( $la, $lb );
		$lo = min( $la, $lb );

		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	/**
	 * Mix a colour towards white.
	 *
	 * @param array{0:int,1:int,2:int} $rgb   Colour.
	 * @param float                    $alpha Share of the colour, 0..1.
	 * @return array{0:int,1:int,2:int}
	 */
	public static function tint( array $rgb, float $alpha ): array {
		$alpha = max( 0.0, min( 1.0, $alpha ) );

		return array(
			(int) round( $rgb[0] * $alpha + 255 * ( 1 - $alpha ) ),
			(int) round( $rgb[1] * $alpha + 255 * ( 1 - $alpha ) ),
			(int) round( $rgb[2] * $alpha + 255 * ( 1 - $alpha ) ),
		);
	}

	/**
	 * Text colour for a filled accent surface.
	 */
	public static function on_accent( string $accent ): string {
		$accent = self::sanitize( $accent );
		$rgb    = self::hex_to_rgb( $accent );

		$white = self::contrast( $rgb, self::hex_to_rgb( self::WHITE ) );
		$ink   = self::contrast( $rgb, self::hex_to_rgb( self::INK ) );

		if ( $white >= 4.5 ) {
			return self::WHITE;
		}

		if ( $ink >= 4.5 ) {
			return self::INK;
		}

		return $white >= $ink ? self::WHITE : self::INK;
	}

	/**
	 * Accent for text and links: the supplied colour darkened until it clears
	 * AA on white and on the strongest accent tint.
	 */
	public static function accent_ink( string $accent ): string {
		$accent = self::sanitize( $accent );
		$base   = self::hex_to_rgb( $accent );
		$tint   = self::tint( $base, 0.09 );
		$white  = self::hex_to_rgb( self::WHITE );
		$rgb    = $base;

		for ( $i = 0; $i < 80; $i++ ) {
			if ( self::contrast( $rgb, $white ) >= 4.55 && self::contrast( $rgb, $tint ) >= 4.55 ) {
				break;
			}

			$rgb = array(
				(int) floor( $rgb[0] * 0.94 ),
				(int) floor( $rgb[1] * 0.94 ),
				(int) floor( $rgb[2] * 0.94 ),
			);
		}

		return self::rgb_to_hex( $rgb );
	}

	/**
	 * Every companion colour the front end needs.
	 *
	 * @return array{accent:string,ink:string,on:string,soft:string,soft-strong:string}
	 */
	public static function derive( string $accent ): array {
		$accent = self::sanitize( $accent );

		if ( isset( self::$derived[ $accent ] ) ) {
			return self::$derived[ $accent ];
		}

		$rgb = self::hex_to_rgb( $accent );

		self::$derived[ $accent ] = array(
			'accent'      => $accent,
			'ink'         => self::accent_ink( $accent ),
			'on'          => self::on_accent( $accent ),
			'soft'        => sprintf( 'rgba(%d, %d, %d, 0.05)', $rgb[0], $rgb[1], $rgb[2] ),
			'soft-strong' => sprintf( 'rgba(%d, %d, %d, 0.09)', $rgb[0], $rgb[1], $rgb[2] ),
		);

		return self::$derived[ $accent ];
	}
}

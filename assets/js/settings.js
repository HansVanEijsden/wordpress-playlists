/**
 * WordPress Playlists - settings page helpers.
 *
 * Media picker for the fallback image and a copy button for the metadata hub
 * config block. Loaded only on the Settings > WordPress Playlists page.
 */
( function ( $ ) {
	'use strict';

	var fallbackFrame = null;

	// Fallback image: open the WP media picker.
	$( '#wp-playlists-fallback-select' ).on( 'click', function ( event ) {
		event.preventDefault();

		if ( fallbackFrame ) {
			fallbackFrame.open();
			return;
		}

		fallbackFrame = wp.media( {
			title: 'Select fallback image',
			button: { text: 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );

		fallbackFrame.on( 'select', function () {
			var attachment = fallbackFrame.state().get( 'selection' ).first().toJSON();
			var url = attachment.sizes && attachment.sizes.thumbnail
				? attachment.sizes.thumbnail.url
				: attachment.url;

			$( '#wp-playlists-fallback' ).val( attachment.id );
			$( '#wp-playlists-fallback-preview' ).html(
				'<img src="' + url + '" alt="" style="max-width:120px;height:auto;border:1px solid #ccd0d4;border-radius:4px;" />'
			);
			$( '#wp-playlists-fallback-remove' ).show();
		} );

		fallbackFrame.open();
	} );

	// Remove the selected fallback image.
	$( '#wp-playlists-fallback-remove' ).on( 'click', function ( event ) {
		event.preventDefault();
		$( '#wp-playlists-fallback' ).val( '' );
		$( '#wp-playlists-fallback-preview' ).empty();
		$( this ).hide();
	} );

	// Copy the metadata hub config JSON.
	$( '#wp-playlists-copy-hub-config' ).on( 'click', function () {
		var text = $( '#wp-playlists-hub-config pre' ).text();

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () {
				$( '#wp-playlists-copy-hub-config' ).text( 'Copied!' );
				setTimeout( function () {
					$( '#wp-playlists-copy-hub-config' ).text( 'Copy' );
				}, 1500 );
			} );
			return;
		}

		// Fallback for older browsers.
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
	} );
} )( jQuery );

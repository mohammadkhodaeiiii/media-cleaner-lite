/**
 * Media Cleaner Lite - general admin behaviour.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-mcl-confirm]' ).forEach( function ( element ) {
			element.addEventListener( 'click', function ( event ) {
				var message = element.getAttribute( 'data-mcl-confirm' );
				if ( message && ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
}() );

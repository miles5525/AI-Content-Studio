/**
 * AI Content Studio admin entry point.
 *
 * Provides confirmation for destructive settings actions.
 */
( function () {
	'use strict';

	document.addEventListener( 'submit', function ( event ) {
		var message = event.target.getAttribute( 'data-aics-confirm' );

		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;

		if ( ! form.classList.contains( 'aics-generate-ideas-form' ) ) {
			return;
		}

		var button = form.querySelector( '[type="submit"]' );

		if ( ! button || button.disabled ) {
			event.preventDefault();
			return;
		}

		button.disabled = true;
		button.value = form.getAttribute( 'data-aics-generating-label' );
	} );
}() );

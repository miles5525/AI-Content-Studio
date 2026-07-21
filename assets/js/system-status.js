/** System Status clipboard helper. */
( function () {
	'use strict';
	var button = document.querySelector( '[data-aics-copy-diagnostics]' );
	var textarea = document.getElementById( 'aics-diagnostic-text' );
	var status = document.querySelector( '[data-aics-copy-status]' );
	if ( ! button || ! textarea || ! status ) { return; }
	button.addEventListener( 'click', function () {
		var copied = function () { status.textContent = button.getAttribute( 'data-copied-label' ); };
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( textarea.value ).then( copied, function () { textarea.focus(); textarea.select(); status.textContent = button.getAttribute( 'data-copy-label' ); } );
			return;
		}
		textarea.focus(); textarea.select();
		try { if ( document.execCommand( 'copy' ) ) { copied(); } } catch ( error ) { status.textContent = button.getAttribute( 'data-copy-label' ); }
	} );
}() );

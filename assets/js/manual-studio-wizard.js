/** Manual Studio single-page wizard. */
( function () {
	'use strict';
	var root = document.getElementById( 'aics-manual-wizard' );
	if ( ! root ) { return; }
	var sequence = 0;
	var controller = null;
	var longTimer = null;
	var messages = {
		generate_ideas: 'Generating content ideas…', regenerate_ideas: 'Generating a new set of content ideas…', generate_article: 'Writing your article…',
		create_draft: 'Creating the WordPress draft…', generate_image: 'Creating your featured image…',
		generate_seo: 'Preparing SEO data…', apply_seo: 'Applying SEO to the WordPress post…',
		render: 'Restoring your saved step…', navigate: 'Opening the selected step…'
	};

	function skeleton( operation ) {
		var card = root.querySelector( '.aics-wizard-card' );
		if ( ! card ) { return; }
		card.setAttribute( 'aria-busy', 'true' );
		var kind = operation.indexOf( 'image' ) > -1 ? 'image' : operation.indexOf( 'seo' ) > -1 ? 'seo' : operation.indexOf( 'article' ) > -1 || 'create_draft' === operation ? 'article' : 'ideas';
		var blocks = 'ideas' === kind ? 5 : 7;
		var html = '<div class="aics-skeleton" data-kind="' + kind + '"><p class="aics-skeleton-label">' + ( messages[ operation ] || 'Processing your request…' ) + '</p>';
		if ( 'image' === kind ) { html += '<span class="aics-skeleton-image"></span>'; }
		for ( var i = 0; i < blocks; i++ ) { html += '<span class="aics-skeleton-line aics-skeleton-line--' + ( i % 3 ) + '"></span>'; }
		card.innerHTML = html + '</div>';
		longTimer = window.setTimeout( function () { var label = root.querySelector( '.aics-skeleton-label' ); if ( label ) { label.textContent = 'This can take a little longer. Please keep this tab open.'; } }, 9000 );
	}

	function announce( message, type ) {
		var notice = root.querySelector( '.aics-wizard-notice' );
		var live = root.querySelector( '.aics-wizard-live' );
		if ( notice ) { notice.className = 'aics-wizard-notice is-' + type; notice.textContent = message; }
		if ( live ) { live.textContent = message; }
	}
	function alignResetAction() {
		var toolbarReset = root.querySelector( '.aics-wizard-toolbar [data-aics-reset]' );
		var footer = root.querySelector( '.aics-wizard-card .aics-wizard-actions' );
		if ( ! toolbarReset || ! footer ) { return; }
		if ( footer.querySelector( '[data-aics-reset]' ) ) { toolbarReset.remove(); return; }
		footer.insertBefore( toolbarReset, footer.firstChild );
	}

	function request( operation, formData, showSkeleton ) {
		sequence += 1; var requestId = sequence;
		if ( controller ) { controller.abort(); }
		controller = new AbortController();
		var data = formData || new FormData();
		data.set( 'action', root.dataset.action ); data.set( 'nonce', root.dataset.nonce ); data.set( 'operation', operation );
		var currentCard = root.querySelector( '.aics-wizard-card' ); if ( currentCard ) { currentCard.setAttribute( 'aria-busy', 'true' ); }
		root.querySelectorAll( 'button, input[type="submit"]' ).forEach( function ( control ) { control.disabled = true; } );
		if ( showSkeleton ) { skeleton( operation ); }
		return fetch( root.dataset.endpoint, { method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal } ).then( function ( response ) { return response.json(); } ).then( function ( result ) {
			if ( requestId !== sequence ) { return; }
			window.clearTimeout( longTimer );
			if ( ! result.success ) { throw new Error( result.data && result.data.message ? result.data.message : 'Something went wrong while processing this step. Your existing work has been preserved.' ); }
			root.innerHTML = result.data.html;
			var heading = root.querySelector( '.aics-wizard-card h2' ); if ( heading ) { heading.focus(); }
			if ( result.data.notice && result.data.notice.message ) { announce( result.data.notice.message, 'success' ); }
			var url = new URL( window.location.href ); url.searchParams.set( 'wizard_step', result.data.step ); window.history.pushState( { step: result.data.step }, '', url );
			initializePreview();
			alignResetAction();
		} ).catch( function ( error ) {
			window.clearTimeout( longTimer ); if ( 'AbortError' === error.name ) { return; }
			if ( 'render' === operation ) { announce( error.message, 'error' ); return; }
			request( 'render', new FormData(), false ).then( function () { announce( error.message, 'error' ); } );
		} );
	}

	root.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( '.aics-wizard-form' ); if ( ! form ) { return; }
		event.preventDefault(); if ( ! form.reportValidity() ) { return; }
		var submitter = event.submitter; var operation = submitter && submitter.dataset.aicsOperation ? submitter.dataset.aicsOperation : form.dataset.operation;
		request( operation, new FormData( form ), [ 'generate_ideas','generate_article','create_draft','generate_image','generate_seo','apply_seo' ].indexOf( operation ) > -1 );
	} );
	root.addEventListener( 'click', function ( event ) {
		var step = event.target.closest( '[data-aics-step]' ); if ( step ) { var d = new FormData(); d.set( 'step', step.dataset.aicsStep ); request( 'navigate', d, false ); return; }
		var refreshPrompt = event.target.closest( '[data-aics-refresh-image-prompt]' ); if ( refreshPrompt ) { var prompt = root.querySelector( '[name="featured_image_prompt"]' ); if ( prompt && prompt.value !== prompt.defaultValue && ! window.confirm( 'Replace your edited prompt with a fresh prompt built from the current article?' ) ) { return; } request( 'refresh_image_prompt', new FormData(), false ); return; }
		var action = event.target.closest( '[data-aics-operation]' ); if ( action && ! action.form ) { request( action.dataset.aicsOperation, new FormData(), true ); return; }
		var regenerate = event.target.closest( '[data-aics-regenerate="ideas"]' ); if ( regenerate && window.confirm( 'Regenerate ideas? The current idea set will be replaced. Existing persistent articles and posts will remain unchanged.' ) ) { request( 'regenerate_ideas', new FormData(), true ); return; }
		var reset = event.target.closest( '[data-aics-reset]' ); if ( reset && window.confirm( 'Start new content? Existing persistent articles and WordPress posts will be preserved.' ) ) { request( 'reset', new FormData(), false ); }
	} );
	window.addEventListener( 'popstate', function () { var d=new FormData();d.set('step',new URL(window.location.href).searchParams.get('wizard_step')||'context');request('navigate',d,false); } );
	function initializePreview(){var frame=root.querySelector('.aics-preview-frame');if(frame){frame.addEventListener('load',function(){var loading=root.querySelector('.aics-preview-loading');if(loading){loading.hidden=true;}});}}
	initializePreview();
	alignResetAction();
}() );

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

		if ( event.defaultPrevented || ! form.classList.contains( 'aics-create-wordpress-draft-form' ) ) {
			return;
		}

		var button = form.querySelector( '[type="submit"]' );

		if ( ! button || button.disabled ) {
			event.preventDefault();
			return;
		}

		button.disabled = true;
		button.value = form.getAttribute( 'data-aics-creating-label' );
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;

		if ( event.defaultPrevented || ( ! form.classList.contains( 'aics-generate-ideas-form' ) && ! form.classList.contains( 'aics-generate-article-form' ) ) ) {
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

	function initializeDraftWarning() {
		var form = document.querySelector( '[data-aics-draft-form]' );

		if ( ! form ) {
			return;
		}

		var title = form.querySelector( '[name="article_title"]' );
		var excerpt = form.querySelector( '[name="article_excerpt"]' );
		var editorTextarea = form.querySelector( '[name="article_content"]' );

		if ( ! title || ! excerpt || ! editorTextarea ) {
			return;
		}
		var initialTitle = title.value;
		var initialExcerpt = excerpt.value;
		var initialContent = editorTextarea.value;
		var allowLeave = false;

		function getEditorContent() {
			if ( window.tinymce ) {
				var editor = window.tinymce.get( 'aics_article_content_editor' );

				if ( editor ) {
					return editor.getContent();
				}
			}

			return editorTextarea.value;
		}

		function hasUnsavedChanges() {
			return title.value !== initialTitle || excerpt.value !== initialExcerpt || getEditorContent() !== initialContent;
		}

		if ( window.tinymce ) {
			window.tinymce.on( 'AddEditor', function ( event ) {
				if ( 'aics_article_content_editor' === event.editor.id ) {
					event.editor.on( 'init', function () {
						initialContent = event.editor.getContent();
					} );
				}
			} );
		}

		form.addEventListener( 'submit', function () {
			allowLeave = true;
		} );

		document.addEventListener( 'submit', function ( event ) {
			if ( event.target.classList.contains( 'aics-regenerate-article-form' ) && ! event.defaultPrevented ) {
				allowLeave = true;
			}
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( allowLeave || ! hasUnsavedChanges() ) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
		} );
	}

	function initializeAutomationForm() {
		var form = document.querySelector( '.aics-automation-form' );

		if ( ! form ) {
			return;
		}

		var mode = form.querySelector( '[name="mode"]' );
		var frequency = form.querySelector( '[name="schedule_settings[frequency]"]' );
		var publishingMode = form.querySelector( '[name="publishing_settings[publishing_mode]"]' );
		var approvalHeading = form.querySelector( '#aics-approval-heading' );
		var publishingDayInput = form.querySelector( '[name="schedule_settings[days_of_week][]"]' );
		var approvalSection = approvalHeading ? approvalHeading.closest( '.aics-section' ) : null;
		var publishingDays = publishingDayInput ? publishingDayInput.closest( '.aics-field' ) : null;
		var monthlyDay = form.querySelector( '.aics-monthly-day-field' );
		var warning = form.querySelector( '.aics-publish-warning' );
		var imageSource = form.querySelector( '[name="featured_image_settings[settings_source]"]' );
		var imageOverrides = form.querySelector( '.aics-featured-image-overrides' );
		var globalImageSummary = form.querySelector( '.aics-global-image-summary' );

		function refreshConditionalFields() {
			if ( approvalSection && mode ) { approvalSection.hidden = 'approval' !== mode.value; }
			if ( publishingDays && frequency ) { publishingDays.hidden = 'weekly' !== frequency.value; }
			if ( monthlyDay && frequency ) { monthlyDay.hidden = 'monthly' !== frequency.value; }
			if ( warning && publishingMode ) { warning.hidden = 'publish' !== publishingMode.value; }
			if ( imageSource && imageOverrides ) {
				imageOverrides.hidden = 'override' !== imageSource.value;
				globalImageSummary.hidden = 'global' !== imageSource.value;
			}
		}

		if ( mode ) { mode.addEventListener( 'change', refreshConditionalFields ); }
		if ( frequency ) { frequency.addEventListener( 'change', refreshConditionalFields ); }
		if ( publishingMode ) { publishingMode.addEventListener( 'change', refreshConditionalFields ); }
		if ( imageSource ) { imageSource.addEventListener( 'change', refreshConditionalFields ); }
		refreshConditionalFields();

		form.addEventListener( 'submit', function () {
			var button = form.querySelector( '[type="submit"]' );

			if ( button ) {
				button.disabled = true;
			}
		} );
	}

	function initializeRunStatusBanner() {
		var config = window.aicsRunStatus;
		var header = document.querySelector( '.aics-admin-wrap > .aics-page-header' );
		var banner = null;
		var requestInFlight = false;
		var publishedQueue = [];
		var queuedPublishedPosts = {};
		var publishedModal = null;

		if ( ! config || ! header ) {
			return;
		}

		function hideBanner() {
			if ( banner ) {
				banner.remove();
				banner = null;
			}
		}

		function renderBanner( status ) {
			if ( ! config.showBanner || ! status || ! status.visible ) {
				hideBanner();
				return;
			}

			if ( ! banner ) {
				banner = document.createElement( 'section' );
				banner.className = 'aics-run-status-banner';
				banner.setAttribute( 'aria-live', 'polite' );
				header.insertAdjacentElement( 'afterend', banner );
			}

			banner.className = 'aics-run-status-banner is-' + status.tone;
			banner.replaceChildren();

			var indicator = document.createElement( 'span' );
			indicator.className = 'aics-run-status-indicator';
			indicator.setAttribute( 'aria-hidden', 'true' );
			banner.appendChild( indicator );

			var copy = document.createElement( 'div' );
			copy.className = 'aics-run-status-copy';
			var title = document.createElement( 'strong' );
			title.className = 'aics-run-status-title';
			title.textContent = status.title;
			var description = document.createElement( 'p' );
			description.textContent = status.description;
			copy.appendChild( title );
			copy.appendChild( description );
			if ( status.animated ) {
				var skeleton = document.createElement( 'div' );
				skeleton.className = 'aics-run-status-skeleton';
				skeleton.setAttribute( 'aria-hidden', 'true' );
				var line = document.createElement( 'span' );
				line.className = 'aics-skeleton-line';
				var shortLine = document.createElement( 'span' );
				shortLine.className = 'aics-skeleton-line aics-skeleton-line--1';
				skeleton.appendChild( line );
				skeleton.appendChild( shortLine );
				copy.appendChild( skeleton );
			}
			banner.appendChild( copy );

			if ( status.action_url && status.action_label ) {
				var action = document.createElement( 'a' );
				action.className = 'aics-run-status-action';
				action.href = status.action_url;
				action.textContent = status.action_label;
				banner.appendChild( action );
			}
		}

		function closePublishedModal() {
			if ( ! publishedModal ) {
				return;
			}
			publishedModal.remove();
			publishedModal = null;
			showNextPublishedPost();
		}

		function showNextPublishedPost() {
			if ( publishedModal || ! publishedQueue.length ) {
				return;
			}
			if ( document.querySelector( '.aics-activation-modal:not([hidden])' ) ) {
				window.setTimeout( showNextPublishedPost, 500 );
				return;
			}

			var published = publishedQueue.shift();
			publishedModal = document.createElement( 'div' );
			publishedModal.className = 'aics-activation-modal';
			publishedModal.setAttribute( 'data-aics-published-modal', '' );
			var backdrop = document.createElement( 'div' );
			backdrop.className = 'aics-activation-modal__backdrop';
			var dialog = document.createElement( 'div' );
			dialog.className = 'aics-activation-modal__dialog';
			dialog.setAttribute( 'role', 'dialog' );
			dialog.setAttribute( 'aria-modal', 'true' );
			dialog.setAttribute( 'aria-labelledby', 'aics-published-modal-title' );
			var closeIcon = document.createElement( 'button' );
			closeIcon.type = 'button';
			closeIcon.className = 'aics-activation-modal__close';
			closeIcon.setAttribute( 'aria-label', 'Close publication confirmation' );
			closeIcon.textContent = '×';
			var icon = document.createElement( 'div' );
			icon.className = 'aics-activation-modal__icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.textContent = '✓';
			var title = document.createElement( 'h2' );
			title.id = 'aics-published-modal-title';
			title.textContent = 'Post published successfully';
			var message = document.createElement( 'p' );
			message.textContent = 'AI Content Studio automatically published “' + published.title + '”.';
			var actions = document.createElement( 'div' );
			actions.className = 'aics-activation-modal__actions';
			var view = document.createElement( 'a' );
			view.className = 'button button-primary aics-button-primary';
			view.href = published.url;
			view.textContent = 'View Post';
			var close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'button';
			close.textContent = 'Close';
			actions.appendChild( view );
			actions.appendChild( close );
			dialog.appendChild( closeIcon );
			dialog.appendChild( icon );
			dialog.appendChild( title );
			dialog.appendChild( message );
			dialog.appendChild( actions );
			publishedModal.appendChild( backdrop );
			publishedModal.appendChild( dialog );
			document.body.appendChild( publishedModal );
			backdrop.addEventListener( 'click', closePublishedModal );
			closeIcon.addEventListener( 'click', closePublishedModal );
			close.addEventListener( 'click', closePublishedModal );
			view.addEventListener( 'click', closePublishedModal );
			view.focus();
		}

		function queuePublishedPosts( posts ) {
			if ( ! Array.isArray( posts ) ) {
				return;
			}
			posts.forEach( function ( post ) {
				if ( post && post.key && post.url && ! queuedPublishedPosts[ post.key ] ) {
					queuedPublishedPosts[ post.key ] = true;
					publishedQueue.push( post );
				}
			} );
			showNextPublishedPost();
		}

		function poll() {
			if ( requestInFlight || document.hidden ) {
				return;
			}

			requestInFlight = true;
			var body = new URLSearchParams( { action: config.action, nonce: config.nonce } );
			window.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) { return response.ok ? response.json() : null; } )
				.then( function ( response ) {
					if ( response && response.success ) {
						renderBanner( response.data );
						queuePublishedPosts( response.data.published_posts );
					}
				} )
				.catch( function () {} )
				.finally( function () { requestInFlight = false; } );
		}

		poll();
		window.setInterval( poll, Number( config.pollInterval ) || 10000 );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && publishedModal ) {
				closePublishedModal();
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initializeDraftWarning();
			initializeAutomationForm();
			initializeRunStatusBanner();
		} );
	} else {
		initializeDraftWarning();
		initializeAutomationForm();
		initializeRunStatusBanner();
	}
}() );

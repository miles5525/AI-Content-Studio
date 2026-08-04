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
		var approvalSection = form.querySelector( '#aics-approval-heading' ).closest( '.aics-section' );
		var publishingDays = form.querySelector( '[name="schedule_settings[days_of_week][]"]' ).closest( '.aics-field' );
		var monthlyDay = form.querySelector( '.aics-monthly-day-field' );
		var warning = form.querySelector( '.aics-publish-warning' );
		var imageSource = form.querySelector( '[name="featured_image_settings[settings_source]"]' );
		var imageOverrides = form.querySelector( '.aics-featured-image-overrides' );
		var globalImageSummary = form.querySelector( '.aics-global-image-summary' );

		function refreshConditionalFields() {
			approvalSection.hidden = 'approval' !== mode.value;
			publishingDays.hidden = 'weekly' !== frequency.value;
			monthlyDay.hidden = 'monthly' !== frequency.value;
			warning.hidden = 'publish' !== publishingMode.value;
			if ( imageSource && imageOverrides ) {
				imageOverrides.hidden = 'override' !== imageSource.value;
				globalImageSummary.hidden = 'global' !== imageSource.value;
			}
		}

		mode.addEventListener( 'change', refreshConditionalFields );
		frequency.addEventListener( 'change', refreshConditionalFields );
		publishingMode.addEventListener( 'change', refreshConditionalFields );
		if ( imageSource ) { imageSource.addEventListener( 'change', refreshConditionalFields ); }
		refreshConditionalFields();

		form.addEventListener( 'submit', function () {
			var button = form.querySelector( '[type="submit"]' );

			if ( button ) {
				button.disabled = true;
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initializeDraftWarning();
			initializeAutomationForm();
		} );
	} else {
		initializeDraftWarning();
		initializeAutomationForm();
	}
}() );

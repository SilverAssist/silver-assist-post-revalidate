/**
 * Admin Debug Logs JavaScript
 *
 * Handles accordion functionality and AJAX clear logs operation.
 *
 * @package RevalidatePosts
 * @since 1.2.0
 * @version 1.2.0
 */

(function ($) {
	'use strict';

	/**
	 * Initialize debug logs functionality
	 */
	function initDebugLogs() {
		// Accordion toggle functionality.
		$( '.sa-log-header' ).on(
			'click',
			function () {
				$( this ).next( '.sa-log-content' ).toggleClass( 'active' );
			}
		);

		// Clear logs button with AJAX.
		$( '#sa-clear-logs-btn' ).on( 'click', handleClearLogs );
	}

	/**
	 * Handle clear logs button click
	 *
	 * @param {Event} e - Click event.
	 */
	function handleClearLogs(e) {
		e.preventDefault();

		// Confirmation dialog.
		if ( ! confirm( silverAssistDebugLogs.confirmMessage )) {
			return;
		}

		const button       = $( this );
		const originalText = button.text();

		// Disable button and show loading state.
		button.prop( 'disabled', true ).text( silverAssistDebugLogs.clearingText );

		// Send AJAX request.
		$.ajax(
			{
				url: silverAssistDebugLogs.ajaxUrl,
				type: 'POST',
				data: {
					action: 'silver_assist_clear_logs',
					nonce: silverAssistDebugLogs.nonce
				},
				success: function (response) {
					if (response.success) {
						// Reload page on success.
						location.reload();
					} else {
						// Show error message.
						alert( response.data || silverAssistDebugLogs.errorMessage );
						button.prop( 'disabled', false ).text( originalText );
					}
				},
				error: function () {
					// Show error message on AJAX failure.
					alert( silverAssistDebugLogs.errorMessage );
					button.prop( 'disabled', false ).text( originalText );
				}
			}
		);
	}

	// Initialize when document is ready.
	$( document ).ready( initDebugLogs );

})( jQuery );

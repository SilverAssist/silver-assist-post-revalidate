/**
 * Check Updates Button Handler
 *
 * Handles AJAX request for manual version checking via the Settings Hub dashboard.
 *
 * @package RevalidatePosts
 * @since 1.2.1
 */

(function ($) {
	'use strict';

	/**
	 * Handle check updates button click
	 *
	 * @param {string} buttonId The button element ID or selector
	 * @param {string} nonce    The security nonce
	 */
	window.silverAssistCheckUpdates = function (buttonId, nonce) {
		// Try to find button by ID first, then by the inline script's button reference.
		var button = document.getElementById( buttonId );

		// If not found by ID, try to find it by data attribute or other means.
		if ( ! button) {
			// Look for the button that triggered this script (event.target).
			var scripts = document.getElementsByTagName( 'script' );
			for ( var i = scripts.length - 1; i >= 0; i-- ) {
				if ( scripts[i].textContent.indexOf( 'silverAssistCheckUpdates' ) !== -1 ) {
					// Find the nearest button element before this script.
					var sibling = scripts[i].previousElementSibling;
					while ( sibling ) {
						if ( sibling.tagName === 'BUTTON' || sibling.tagName === 'A' ) {
							button = sibling;
							break;
						}
						sibling = sibling.previousElementSibling;
					}
					break;
				}
			}
		}

		if ( ! button) {
			console.error( 'Check updates button not found:', buttonId );
			return;
		}

		// Disable button and show loading state.
		button.disabled    = true;
		button.textContent = silverAssistCheckUpdatesData.checkingText;

		// Make AJAX request.
		$.ajax(
			{
				url: silverAssistCheckUpdatesData.ajaxUrl,
				type: 'POST',
				data: {
					action: silverAssistCheckUpdatesData.ajaxAction,
					nonce: nonce
				},
				success: function (response) {
					button.disabled = false;

					if (response.success) {
						if (response.data.update_available) {
							// Update available - change button style and redirect.
							button.textContent = silverAssistCheckUpdatesData.updateAvailableText;
							button.classList.remove( 'button-secondary' );
							button.classList.add( 'button-primary' );

							// Redirect to plugins update page.
							setTimeout(
								function () {
									window.location.href = silverAssistCheckUpdatesData.updatesPageUrl;
								},
								500
							);
						} else {
							// No update available.
							button.textContent = silverAssistCheckUpdatesData.upToDateText;

							// Reset button text after 3 seconds.
							setTimeout(
								function () {
									button.textContent = silverAssistCheckUpdatesData.checkUpdatesText;
								},
								3000
							);
						}
					} else {
						// Error response from server.
						button.textContent = silverAssistCheckUpdatesData.errorText;
						alert( silverAssistCheckUpdatesData.errorCheckingMessage );

						// Reset button text after 3 seconds.
						setTimeout(
							function () {
								button.textContent = silverAssistCheckUpdatesData.checkUpdatesText;
							},
							3000
						);
					}
				},
				error: function () {
					// Network error.
					button.disabled    = false;
					button.textContent = silverAssistCheckUpdatesData.errorText;
					alert( silverAssistCheckUpdatesData.networkErrorMessage );

					// Reset button text after 3 seconds.
					setTimeout(
						function () {
							button.textContent = silverAssistCheckUpdatesData.checkUpdatesText;
						},
						3000
					);
				}
			}
		);
	};

})( jQuery );

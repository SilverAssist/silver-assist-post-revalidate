/**
 * Update Check Script
 *
 * Handles AJAX update checking for Silver Assist Post Revalidate plugin updates.
 * Integrates with wp-github-updater package for manual version checking.
 *
 * @package RevalidatePosts
 * @since   1.2.1
 * @version 1.2.1
 * @author  Silver Assist <support@silverassist.io>
 */

(($) => {
	"use strict";

	/**
	 * Check for plugin updates via AJAX
	 *
	 * This function is called by the Settings Hub action button.
	 * It sends an AJAX request to check for updates and handles the response.
	 *
	 * @since 1.2.1
	 * @returns {void}
	 */
	window.silverAssistCheckUpdates = function () {
		// Get localized data.
		const { ajaxurl, nonce, updateUrl, strings = {} } =
			window.silverAssistCheckUpdatesData || {};

		// Validate configuration.
		if ( ! ajaxurl || ! nonce ) {
			console.error(
				"Silver Assist Post Revalidate: Update check configuration missing"
			);
			return;
		}

		// Send AJAX request.
		$.ajax(
			{
				url: ajaxurl,
				type: "POST",
				data: {
					action: "silver_assist_revalidate_check_version",
					nonce: nonce,
				},
				success: function ( response ) {
					if ( response.success ) {
						if ( response.data.update_available ) {
							// Update available - redirect to Updates page.
							const message =
								strings.updateAvailable ||
								"Update available! Redirecting to Updates page...";
							alert( message );
							window.location.href = updateUrl;
						} else {
							// No update available.
							const message =
								strings.upToDate || "You're up to date!";
							alert( message );
						}
					} else {
						// Error response from server.
						const message =
							response.data.message ||
							strings.checkError ||
							"Error checking updates. Please try again.";
						alert( message );
					}
				},
				error: function () {
					// Connection error.
					const message =
						strings.connectError ||
						"Error connecting to update server.";
					alert( message );
				},
			}
		);
	};

})( jQuery );
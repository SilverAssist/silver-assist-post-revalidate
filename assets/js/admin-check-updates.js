/**
 * Update Check Script
 *
 * Handles AJAX update checking for Silver Assist Post Revalidate plugin updates.
 * Integrates with wp-github-updater package for manual version checking.
 *
 * @package RevalidatePosts
 * @since   1.2.1
 * @version 1.3.1
 * @author  Silver Assist <support@silverassist.io>
 */

(($ => {
	"use strict";

	/**
	 * Show WordPress admin notice
	 *
	 * @since 1.3.1
	 * @param {string} message - The message to display
	 * @param {string} type - Notice type: 'success', 'error', 'warning', 'info'
	 * @returns {void}
	 */
	const showAdminNotice = (message, type = "info") => {
		const noticeClass = `notice notice-${type} is-dismissible`;
		const noticeHtml = `
			<div class="${noticeClass}" style="margin: 15px 0;">
				<p><strong>Silver Assist Post Revalidate:</strong> ${message}</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
		`;

		// Insert notice at the top of the page (after h1).
		const $notice = $(noticeHtml);
		$("h1").first().after($notice);

		// Make dismiss button work.
		$notice.find(".notice-dismiss").on("click", function() {
			$notice.fadeOut(300, function() {
				$(this).remove();
			});
		});

		// Auto-dismiss after 5 seconds for success/info messages.
		if (type === "success" || type === "info") {
			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	/**
	 * Check for plugin updates via AJAX
	 *
	 * This function is called by the Settings Hub action button.
	 * It sends an AJAX request to check for updates and handles the response.
	 *
	 * @since 1.2.1
	 * @returns {void}
	 */
	window.silverAssistRevalidateCheckUpdates = function () {
		// Get localized data.
		const { ajaxurl, nonce, updateUrl, strings = {} } =
			window.silverAssistRevalidateCheckUpdatesData || {};

		// Validate configuration.
		if ( ! ajaxurl || ! nonce ) {
			console.error(
				"Silver Assist Post Revalidate: Update check configuration missing"
			);
			showAdminNotice("Update check configuration error. Please contact support.", "error");
			return;
		}

		// Show checking notice.
		showAdminNotice(strings.checking || "Checking for updates...", "info");

		// Send AJAX request.
		$.ajax({
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
						showAdminNotice(message, "success");
						
						// Redirect after 2 seconds.
						setTimeout(() => {
							window.location.href = updateUrl;
						}, 2000);
					} else {
						// No update available.
						const message =
							strings.upToDate || "You're up to date!";
						showAdminNotice(message, "success");
					}
				} else {
					// Error response from server.
					const errorMessage =
						response.data?.message ||
						strings.checkError ||
						"Error checking updates. Please try again.";
					showAdminNotice(errorMessage, "error");
				}
			},
			error: function () {
				// Connection error.
				const message =
					strings.connectError ||
					"Error connecting to update server.";
				showAdminNotice(message, "error");
			},
		});
	};

}))(jQuery);
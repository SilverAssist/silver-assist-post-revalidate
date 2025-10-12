/**
 * Manual Revalidation Script
 *
 * Handles AJAX revalidation from post editor meta box.
 *
 * @package RevalidatePosts
 * @since   1.4.0
 * @version 1.4.0
 * @author  Silver Assist <support@silverassist.io>
 */

(($ => {
	"use strict";

	/**
	 * Handle manual revalidation button click
	 *
	 * @since 1.4.0
	 * @returns {void}
	 */
	const handleRevalidateClick = () => {
		const $button  = $( '#silver-assist-revalidate-button' );
		const $message = $( '#silver-assist-revalidate-message' );
		const postId   = $button.data( 'post-id' );
		const nonce    = $( '#revalidate_post_nonce' ).val();

		// Get localized strings.
		const { ajaxurl, strings = {} } = window.silverAssistManualRevalidate || {};

		// Validate configuration.
		if ( ! ajaxurl || ! nonce ) {
			console.error( 'Silver Assist Post Revalidate: Configuration missing' );
			return;
		}

		// Disable button and show loading state.
		$button.prop( 'disabled', true ).text( strings.revalidating || 'Revalidating...' );
		$message.removeClass( 'notice notice-success notice-error' ).html( '' );

		// Send AJAX request.
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'silver_assist_manual_revalidate',
				post_id: postId,
				_wpnonce: nonce,
			},
			success: ( response ) => {
				if ( response.success ) {
					// Show success message.
					$message
						.addClass( 'notice notice-success' )
						.html( `<p>${response.data.message || strings.success || 'Success!'}</p>` );
				} else {
					// Show error message.
					$message
						.addClass( 'notice notice-error' )
						.html( `<p>${response.data.message || strings.error || 'Error occurred.'}</p>` );
				}

				// Re-enable button.
				$button.prop( 'disabled', false ).text( $button.data( 'original-text' ) );
			},
			error: () => {
				// Show connection error.
				$message
					.addClass( 'notice notice-error' )
					.html( `<p>${strings.error || 'Error connecting to server.'}</p>` );

				// Re-enable button.
				$button.prop( 'disabled', false ).text( $button.data( 'original-text' ) );
			},
		});
	};

	/**
	 * Initialize when DOM is ready
	 *
	 * @since 1.4.0
	 * @returns {void}
	 */
	$( document ).ready( () => {
		const $button = $( '#silver-assist-revalidate-button' );

		if ( $button.length ) {
			// Store original button text.
			$button.data( 'original-text', $button.text() );

			// Attach click handler.
			$button.on( 'click', handleRevalidateClick );
		}
	});

}))(jQuery);

/**
 * Revisions digest JavaScript
 *
 * Handle revisions digest interactions in the Dashboard.
 *
 * @package revisions-digest
 * @since   0.1.0
 * @author  adamsilverstein
 */

( function ( $, window ) {

	$( document ).on(
		'click',
		'.revisions-digest-widget .activity-block .handlediv',
		function ( el ) {
			$( el.currentTarget )
				.parents( '.activity-block' )
				.toggleClass( 'closed' );
		}
	);
}( jQuery, window ) );
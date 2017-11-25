( function ( $, window ) {

	$(document).on(
		'click',
		'.revisions-digest-widget .activity-block .handlediv',
		function( el ) {
			$( el.currentTarget )
				.parents( '.activity-block' )
				.toggleClass( 'closed' );
		}
	);
} ( jQuery, window ));


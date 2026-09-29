/* Portrait photo picker on the Memories → People screens. */
( function ( $ ) {
	var frame;

	$( document ).on( 'click', '.fmem-portrait-choose', function ( e ) {
		e.preventDefault();
		var $picker = $( this ).closest( '.fmem-portrait-picker' );

		frame = wp.media( {
			title: fmemPortrait.title,
			button: { text: fmemPortrait.button },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var photo = frame.state().get( 'selection' ).first().toJSON();
			var src = photo.sizes && photo.sizes.thumbnail ? photo.sizes.thumbnail.url : photo.url;
			$picker.find( '.fmem-portrait-id' ).val( photo.id );
			$picker.find( '.fmem-portrait-preview' ).html( $( '<img>' ).attr( { src: src, alt: '' } ) );
			$picker.find( '.fmem-portrait-remove' ).prop( 'hidden', false );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.fmem-portrait-remove', function ( e ) {
		e.preventDefault();
		var $picker = $( this ).closest( '.fmem-portrait-picker' );
		$picker.find( '.fmem-portrait-id' ).val( '' );
		$picker.find( '.fmem-portrait-preview' ).empty();
		$( this ).prop( 'hidden', true );
	} );

	// The "Add Person" form stays on screen after saving, so clear the picker.
	$( document ).ajaxSuccess( function ( event, xhr, settings ) {
		if ( settings.data && typeof settings.data === 'string' && settings.data.indexOf( 'action=add-tag' ) !== -1 ) {
			$( '#addtag .fmem-portrait-remove' ).trigger( 'click' );
		}
	} );
} )( jQuery );

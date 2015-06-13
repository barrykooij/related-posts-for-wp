jQuery( function ( $ ) {
    var rp4wp_is_submitting = false;
    $( '#rp4wp-settings-form' ).submit( function () {
        if ( rp4wp_is_submitting ) {
            return false;
        }
        rp4wp_is_submitting = true;
        $( this ).find( '#submit' ).attr( 'disabled', 'disabled' ).val( 'Saving ...' );
        return true;
    } );


    $( '.nav-tab-wrapper a' ).click( function () {
        $( '.rp4wp-settings-section' ).hide();
        $( '.nav-tab-active' ).removeClass( 'nav-tab-active' );
        $( $( this ).attr( 'href' ) ).show();
        $( this ).addClass( 'nav-tab-active' );
        return false;
    } );

    $( '.nav-tab-wrapper a:first' ).click();


} );
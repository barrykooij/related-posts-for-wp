jQuery( function ( $ ) {
    var rp4wp_is_submitting = false;
    $( '#rp4wp-settings-form' ).submit( function () {
        if ( rp4wp_is_submitting ) {
            return false;
        }
        rp4wp_is_submitting = true;
        $( this ).find( '#submit' ).attr( 'disabled', 'disabled' ).val( 'Saving ...' );
        return true;
    } )

} );
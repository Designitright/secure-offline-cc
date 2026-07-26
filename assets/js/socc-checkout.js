( function() {
    'use strict';

    function formatCardNumber( input ) {
        var v = input.value.replace( /\D/g, '' ).substring( 0, 16 );
        var parts = [];
        for ( var i = 0; i < v.length; i += 4 ) {
            parts.push( v.substring( i, i + 4 ) );
        }
        input.value = parts.join( ' ' );
    }

    function formatExpiry( input ) {
        var v = input.value.replace( /\D/g, '' ).substring( 0, 4 );
        if ( v.length >= 2 ) {
            input.value = v.substring( 0, 2 ) + ' / ' + v.substring( 2 );
        } else {
            input.value = v;
        }
    }

    function formatCVC( input ) {
        input.value = input.value.replace( /\D/g, '' ).substring( 0, 4 );
    }

    function bindFields() {
        var cardNum = document.getElementById( 'socc-card-number' );
        var expiry  = document.getElementById( 'socc-card-expiry' );
        var cvc     = document.getElementById( 'socc-card-cvc' );

        if ( cardNum ) {
            cardNum.addEventListener( 'input', function() { formatCardNumber( this ); } );
        }
        if ( expiry ) {
            expiry.addEventListener( 'input', function() { formatExpiry( this ); } );
        }
        if ( cvc ) {
            cvc.addEventListener( 'input', function() { formatCVC( this ); } );
        }
    }

    // Bind on load and after WC AJAX (checkout updates)
    document.addEventListener( 'DOMContentLoaded', bindFields );
    document.body && document.body.addEventListener( 'updated_checkout', bindFields );
    jQuery && jQuery( document.body ).on( 'updated_checkout payment_method_selected', bindFields );

} )();

( function( wc, wp ) {
    var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
    var getSetting            = wc.wcSettings.getSetting;
    var decodeEntities        = wp.htmlEntities.decodeEntities;
    var createElement         = wp.element.createElement;
    var RawHTML               = wp.element.RawHTML;

    var settings = getSetting( 'socc_data', {} );

    var label = decodeEntities( settings.title ) || 'Credit Card (Offline)';

    var Content = function() {
        return createElement( RawHTML, null, decodeEntities( settings.description || '' ) );
    };

    registerPaymentMethod( {
        name:    'socc',
        label:   label,
        content: createElement( Content ),
        edit:    createElement( Content ),
        canMakePayment: function() { return true; },
        ariaLabel: label,
        supports: {
            features: settings.supports || [ 'products' ],
        },
    } );

} )( window.wc, window.wp );

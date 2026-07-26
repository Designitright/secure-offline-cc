( function( wc, wp ) {
    'use strict';

    var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
    var getSetting            = wc.wcSettings.getSetting;
    var decodeEntities        = wp.htmlEntities.decodeEntities;
    var createElement         = wp.element.createElement;
    var Fragment              = wp.element.Fragment;
    var useState              = wp.element.useState;
    var useEffect             = wp.element.useEffect;
    var RawHTML               = wp.element.RawHTML;

    var settings = getSetting( 'socc_data', {} );

    var label = decodeEntities( settings.title ) || 'Credit Card (Offline)';
    var showCardholder = !! settings.cardholderField;

    function formatCardNumber( v ) {
        v = v.replace( /\D/g, '' ).substring( 0, 16 );
        var parts = [];
        for ( var i = 0; i < v.length; i += 4 ) {
            parts.push( v.substring( i, i + 4 ) );
        }
        return parts.join( ' ' );
    }

    function formatExpiry( v ) {
        v = v.replace( /\D/g, '' ).substring( 0, 4 );
        if ( v.length >= 3 ) {
            return v.substring( 0, 2 ) + ' / ' + v.substring( 2 );
        } else if ( v.length >= 2 ) {
            return v.substring( 0, 2 ) + ' / ';
        }
        return v;
    }

    function formatCVC( v ) {
        return v.replace( /\D/g, '' ).substring( 0, 4 );
    }

    function Content( props ) {
        var eventRegistration = props.eventRegistration || {};
        var onPaymentSetup    = eventRegistration.onPaymentSetup;
        var emitResponse      = props.emitResponse || {};

        var cardNumber  = useState( '' );
        var expiry      = useState( '' );
        var cvc         = useState( '' );
        var holder      = useState( '' );

        useEffect( function () {
            if ( typeof onPaymentSetup !== 'function' ) {
                return;
            }

            var unsubscribe = onPaymentSetup( function () {
                var rawNumber = cardNumber[0].replace( /\s/g, '' );
                var rawExpiry = expiry[0].replace( /\s/g, '' );
                var rawCvc    = cvc[0];

                if ( ! rawNumber || ! rawExpiry || ! rawCvc ) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please fill in all card fields.',
                    };
                }

                if ( showCardholder && ! holder[0] ) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please enter the cardholder name.',
                    };
                }

                var paymentMethodData = {
                    'socc-card-number': rawNumber,
                    'socc-card-expiry': rawExpiry,
                    'socc-card-cvc':    rawCvc,
                };

                if ( showCardholder ) {
                    paymentMethodData.socc_holder = holder[0];
                }

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: { paymentMethodData: paymentMethodData },
                };
            } );

            return unsubscribe;
        }, [ cardNumber[0], expiry[0], cvc[0], holder[0], onPaymentSetup, emitResponse ] );

        var fieldStyle = {
            marginBottom: '12px',
        };

        var labelStyle = {
            display: 'block',
            fontSize: '14px',
            fontWeight: '600',
            marginBottom: '4px',
        };

        var inputStyle = {
            width: '100%',
            padding: '8px 12px',
            fontSize: '16px',
            border: '1px solid #ddd',
            borderRadius: '4px',
            boxSizing: 'border-box',
        };

        return createElement( Fragment, null,
            settings.description
                ? createElement( RawHTML, null, decodeEntities( settings.description ) )
                : null,
            createElement( 'div', { style: fieldStyle },
                createElement( 'label', { htmlFor: 'socc-block-card-number', style: labelStyle },
                    'Card Number ', createElement( 'span', { style: { color: '#e2401c' } }, '*' ) ),
                createElement( 'input', {
                    id: 'socc-block-card-number',
                    type: 'tel',
                    maxLength: 19,
                    autoComplete: 'cc-number',
                    placeholder: '\u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022',
                    value: cardNumber[0],
                    onChange: function( e ) { cardNumber[1]( formatCardNumber( e.target.value ) ); },
                    style: inputStyle,
                } )
            ),
            createElement( 'div', { style: { display: 'flex', gap: '12px', marginBottom: '12px' } },
                createElement( 'div', { style: { flex: '1' } },
                    createElement( 'label', { htmlFor: 'socc-block-card-expiry', style: labelStyle },
                        'Expiry (MM/YY) ', createElement( 'span', { style: { color: '#e2401c' } }, '*' ) ),
                    createElement( 'input', {
                        id: 'socc-block-card-expiry',
                        type: 'tel',
                        maxLength: 7,
                        autoComplete: 'cc-exp',
                        placeholder: 'MM / YY',
                        value: expiry[0],
                        onChange: function( e ) { expiry[1]( formatExpiry( e.target.value ) ); },
                        style: inputStyle,
                    } )
                ),
                createElement( 'div', { style: { flex: '1' } },
                    createElement( 'label', { htmlFor: 'socc-block-card-cvc', style: labelStyle },
                        'CVV ', createElement( 'span', { style: { color: '#e2401c' } }, '*' ) ),
                    createElement( 'input', {
                        id: 'socc-block-card-cvc',
                        type: 'tel',
                        maxLength: 4,
                        autoComplete: 'off',
                        placeholder: 'CVV',
                        value: cvc[0],
                        onChange: function( e ) { cvc[1]( formatCVC( e.target.value ) ); },
                        style: inputStyle,
                    } )
                )
            ),
            showCardholder
                ? createElement( 'div', { style: fieldStyle },
                    createElement( 'label', { htmlFor: 'socc-block-holder', style: labelStyle },
                        'Cardholder Name ', createElement( 'span', { style: { color: '#e2401c' } }, '*' ) ),
                    createElement( 'input', {
                        id: 'socc-block-holder',
                        type: 'text',
                        autoComplete: 'cc-name',
                        placeholder: 'Name on card',
                        value: holder[0],
                        onChange: function( e ) { holder[1]( e.target.value ); },
                        style: inputStyle,
                    } )
                )
                : null
        );
    }

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

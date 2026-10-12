import { __ } from '@wordpress/i18n';
import { FormTokenField, TextControl } from '@wordpress/components';
import { SUGGESTIONS, cleanCodes } from './currencies';

/**
 * Sidebar fields shared by both blocks: target currency + currencies to show.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.target        Target currency code.
 * @param {string[]} props.symbols       Selected currency codes.
 * @param {Function} props.setAttributes Block attribute setter.
 * @return {Element} Controls.
 */
export default function CurrencyControls( { target, symbols, setAttributes } ) {
	return (
		<>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Target currency', 'nauman-currency-rates' ) }
				help={ __(
					'3-letter code. Values are shown in this currency.',
					'nauman-currency-rates'
				) }
				value={ target }
				maxLength={ 3 }
				onChange={ ( value ) =>
					setAttributes( {
						target: value.replace( /[^a-z]/gi, '' ).toUpperCase(),
					} )
				}
			/>
			<FormTokenField
				__experimentalExpandOnFocus
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Currencies to show', 'nauman-currency-rates' ) }
				value={ symbols }
				suggestions={ SUGGESTIONS }
				maxLength={ 10 }
				onChange={ ( tokens ) =>
					setAttributes( { symbols: cleanCodes( tokens ) } )
				}
			/>
		</>
	);
}

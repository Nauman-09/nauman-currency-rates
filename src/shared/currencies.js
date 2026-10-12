/**
 * Currency list used by the editor controls.
 * Names are only for display; the API decides which codes are supported.
 */
export const CURRENCY_NAMES = {
	USD: 'US Dollar',
	EUR: 'Euro',
	GBP: 'British Pound',
	AED: 'UAE Dirham',
	SAR: 'Saudi Riyal',
	CAD: 'Canadian Dollar',
	AUD: 'Australian Dollar',
	JPY: 'Japanese Yen',
	CNY: 'Chinese Yuan',
	TRY: 'Turkish Lira',
	QAR: 'Qatari Riyal',
	KWD: 'Kuwaiti Dinar',
};

export const SUGGESTIONS = Object.keys( CURRENCY_NAMES );

/**
 * Turn raw token-field input into clean, unique 3-letter codes (max 10).
 *
 * @param {string[]} tokens Raw tokens typed or picked by the user.
 * @return {string[]} Clean currency codes.
 */
export function cleanCodes( tokens ) {
	const out = [];
	( tokens || [] ).forEach( ( token ) => {
		const code = String( token )
			.replace( /[^a-z]/gi, '' )
			.toUpperCase();
		if ( code.length === 3 && ! out.includes( code ) ) {
			out.push( code );
		}
	} );
	return out.slice( 0, 10 );
}

/**
 * Format a number with 2 decimals, e.g. 28150 -> "28,150.00".
 *
 * @param {number} value Number to format.
 * @return {string} Formatted number.
 */
export function formatMoney( value ) {
	return value.toLocaleString( 'en-US', {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	} );
}

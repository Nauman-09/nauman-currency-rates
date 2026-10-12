import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useEffect, useState } from '@wordpress/element';

/**
 * Load "value of 1 unit in the target currency" from the plugin's REST route.
 * The route reads the same cached data the front end uses.
 *
 * @param {string}   target  Target currency code, e.g. PKR.
 * @param {string[]} symbols Currency codes to show.
 * @return {{status: string, data: ?Object, error: string}} Request state.
 */
export default function useRates( target, symbols ) {
	const [ state, setState ] = useState( {
		status: 'loading',
		data: null,
		error: '',
	} );
	const key = `${ target }|${ symbols.join( ',' ) }`;

	useEffect( () => {
		let cancelled = false;
		setState( ( prev ) => ( { ...prev, status: 'loading', error: '' } ) );

		// Wait a moment so typing a code does not fire a request per letter.
		const timer = setTimeout( () => {
			apiFetch( {
				path: addQueryArgs( '/ncr/v1/rates', {
					target,
					symbols: symbols.join( ',' ),
				} ),
			} )
				.then( ( data ) => {
					if ( ! cancelled ) {
						setState( { status: 'ready', data, error: '' } );
					}
				} )
				.catch( ( err ) => {
					if ( ! cancelled ) {
						setState( {
							status: 'error',
							data: null,
							error: err && err.message ? err.message : 'Request failed',
						} );
					}
				} );
		}, 400 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );

	return state;
}

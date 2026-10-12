/**
 * Smoke tests: both editor components render real data and react to input.
 */
import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import CardEdit from '../rates-card/edit';
import ConverterEdit from '../converter/edit';
import { cleanCodes, formatMoney } from '../shared/currencies';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( { className: 'wp-block' } ),
	InspectorControls: ( { children } ) => <div className="inspector">{ children }</div>,
} ) );

const REPLY = {
	target: 'PKR',
	date: '2026-10-10',
	rates: { USD: 277.01, EUR: 333.33, AED: 75.4 },
};

// The real component library needs a full WordPress runtime; plain stand-ins
// are enough to test this plugin's own logic.
jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => <div>{ children }</div>,
	TextControl: ( { label } ) => <label>{ label }</label>,
	FormTokenField: ( { label } ) => <label>{ label }</label>,
	__experimentalNumberControl: ( { label } ) => <label>{ label }</label>,
	Spinner: () => <span className="spinner" />,
	Notice: ( { children } ) => <div className="notice">{ children }</div>,
} ) );

global.IS_REACT_ACT_ENVIRONMENT = true;

async function mount( element ) {
	const host = document.createElement( 'div' );
	document.body.appendChild( host );
	const root = createRoot( host );
	await act( async () => {
		root.render( element );
	} );
	// let the 400ms debounce + the mocked request resolve
	await act( async () => {
		await new Promise( ( r ) => setTimeout( r, 500 ) );
	} );
	return host;
}

beforeEach( () => {
	apiFetch.mockReset();
	apiFetch.mockResolvedValue( REPLY );
} );

test( 'cleanCodes tidies token input', () => {
	expect( cleanCodes( [ 'usd', 'USD', 'e-u-r', 'toolong', 'ab' ] ) ).toEqual( [ 'USD', 'EUR' ] );
	expect( formatMoney( 28150 ) ).toBe( '28,150.00' );
} );

test( 'rates card shows rows from the REST reply and skips unknown codes', async () => {
	const host = await mount(
		<CardEdit
			attributes={ { target: 'PKR', symbols: [ 'USD', 'EUR', 'XXX' ], title: "Today's rates", note: '' } }
			setAttributes={ () => {} }
		/>
	);
	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain( '/ncr/v1/rates?' );
	expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain( 'target=PKR' );
	const rows = host.querySelectorAll( '.ncr-card__row' );
	expect( rows.length ).toBe( 2 );
	expect( host.textContent ).toContain( '277.01' );
	expect( host.textContent ).toContain( 'per 1 unit in PKR' );
	expect( host.textContent ).toContain( 'Updated 2026-10-10' );
} );

test( 'rates card shows an error notice when the request fails', async () => {
	apiFetch.mockReset();
	apiFetch.mockRejectedValue( new Error( 'Service down' ) );
	const host = await mount(
		<CardEdit
			attributes={ { target: 'PKR', symbols: [ 'USD' ], title: 'T', note: '' } }
			setAttributes={ () => {} }
		/>
	);
	expect( host.textContent ).toContain( 'Service down' );
} );

test( 'calculator multiplies, switches currency and rejects bad input', async () => {
	const host = await mount(
		<ConverterEdit
			attributes={ { target: 'PKR', symbols: [ 'USD', 'EUR' ], amount: 100, label: 'Amount' } }
			setAttributes={ () => {} }
		/>
	);
	expect( host.querySelector( '.ncr-conv__total' ).textContent ).toBe( 'PKR 27,701.00' );

	const chips = host.querySelectorAll( '.ncr-conv__chip' );
	await act( async () => {
		chips[ 1 ].click();
	} );
	expect( host.querySelector( '.ncr-conv__total' ).textContent ).toBe( 'PKR 33,333.00' );
	expect( host.querySelector( '.ncr-conv__chip.is-active' ).textContent ).toBe( 'EUR' );

	const input = host.querySelector( '.ncr-conv__input' );
	const setter = Object.getOwnPropertyDescriptor( window.HTMLInputElement.prototype, 'value' ).set;
	await act( async () => {
		setter.call( input, 'abc' );
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
	expect( host.querySelector( '.ncr-conv__total' ).textContent ).toBe( '—' );
} );

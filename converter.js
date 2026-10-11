( function () {
	'use strict';

	function init( root ) {
		var rates;
		try {
			rates = JSON.parse( root.getAttribute( 'data-rates' ) || '{}' );
		} catch ( e ) {
			return;
		}
		var target = root.getAttribute( 'data-target' ) || '';
		var chips = root.querySelectorAll( '.ncr-conv__chip' );
		var input = root.querySelector( '.ncr-conv__input' );
		var sub = root.querySelector( '.ncr-conv__sub' );
		var total = root.querySelector( '.ncr-conv__total' );
		var code = chips.length ? chips[ 0 ].getAttribute( 'data-code' ) : '';

		function fmt( n ) {
			return n.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
		}

		function render() {
			var raw = String( input.value ).replace( /,/g, '' ).trim();
			var amount = parseFloat( raw );
			if ( ! isFinite( amount ) || amount < 0 || amount > 1e9 || ! rates[ code ] ) {
				sub.textContent = 'Enter an amount';
				total.textContent = '—';
				return;
			}
			sub.textContent = fmt( amount ) + ' ' + code + ' is about';
			total.textContent = target + ' ' + fmt( amount * rates[ code ] );
		}

		function select( chip ) {
			code = chip.getAttribute( 'data-code' );
			chips.forEach( function ( c ) {
				var on = c === chip;
				c.classList.toggle( 'is-active', on );
				c.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			} );
			render();
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				select( chip );
			} );
		} );
		input.addEventListener( 'input', render );
		render();
	}

	function ready() {
		document.querySelectorAll( '.ncr-conv[data-rates]' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
}() );

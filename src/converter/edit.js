import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	__experimentalNumberControl as NumberControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import CurrencyControls from '../shared/controls';
import useRates from '../shared/useRates';
import { formatMoney } from '../shared/currencies';

export default function Edit( { attributes, setAttributes } ) {
	const { target, symbols, amount, label } = attributes;
	const blockProps = useBlockProps();
	const validTarget = target.length === 3;
	const { status, data, error } = useRates(
		validTarget ? target : 'PKR',
		symbols
	);

	// Preview-only state: lets you try the calculator inside the editor.
	const [ picked, setPicked ] = useState( null );
	const [ typed, setTyped ] = useState( null );

	const codes = data ? symbols.filter( ( c ) => data.rates[ c ] ) : [];
	const active = picked && codes.includes( picked ) ? picked : codes[ 0 ];
	const shown = typed !== null ? typed : String( amount );
	const parsed = parseFloat( String( shown ).replace( /,/g, '' ) );
	const ok = active && isFinite( parsed ) && parsed >= 0;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Rates', 'nauman-currency-rates' ) }>
					<CurrencyControls
						target={ target }
						symbols={ symbols }
						setAttributes={ setAttributes }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Calculator', 'nauman-currency-rates' ) }
					initialOpen={ false }
				>
					<NumberControl
						__next40pxDefaultSize
						label={ __( 'Starting amount', 'nauman-currency-rates' ) }
						value={ amount }
						min={ 1 }
						onChange={ ( value ) => {
							const n = parseFloat( value );
							setAttributes( { amount: n > 0 ? n : 100 } );
							setTyped( null );
						} }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Amount label', 'nauman-currency-rates' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! validTarget && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Enter a 3-letter target currency.',
							'nauman-currency-rates'
						) }
					</Notice>
				) }
				{ status === 'error' && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<div className="ncr-conv ncr-conv--editor">
					<p className="ncr-conv__label">
						{ __( 'Currency', 'nauman-currency-rates' ) }
					</p>
					<div className="ncr-conv__chips" role="radiogroup">
						{ status === 'loading' && <Spinner /> }
						{ codes.map( ( code ) => (
							<button
								type="button"
								key={ code }
								role="radio"
								aria-checked={ code === active }
								className={
									'ncr-conv__chip' +
									( code === active ? ' is-active' : '' )
								}
								onClick={ () => setPicked( code ) }
							>
								{ code }
							</button>
						) ) }
					</div>
					<label
						className="ncr-conv__label"
						htmlFor="ncr-editor-amount"
					>
						{ label }
					</label>
					<input
						id="ncr-editor-amount"
						className="ncr-conv__input"
						type="text"
						inputMode="decimal"
						value={ shown }
						onChange={ ( e ) => setTyped( e.target.value ) }
					/>
					<div className="ncr-conv__result" aria-live="polite">
						<p className="ncr-conv__sub">
							{ ok
								? sprintf(
										/* translators: 1: amount, 2: currency code. */
										__( '%1$s %2$s is about', 'nauman-currency-rates' ),
										formatMoney( parsed ),
										active
								  )
								: __( 'Enter an amount', 'nauman-currency-rates' ) }
						</p>
						<p className="ncr-conv__total">
							{ ok
								? `${ target } ${ formatMoney( parsed * data.rates[ active ] ) }`
								: '—' }
						</p>
					</div>
					{ data && data.date && (
						<p className="ncr-conv__foot">
							{ sprintf(
								/* translators: %s: date of the rates. */
								__(
									'Indicative rate · Updated %s · Rates by ExchangeRate-API',
									'nauman-currency-rates'
								),
								data.date
							) }
						</p>
					) }
				</div>
			</div>
		</>
	);
}

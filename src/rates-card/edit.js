import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Spinner, Notice } from '@wordpress/components';
import CurrencyControls from '../shared/controls';
import useRates from '../shared/useRates';
import { CURRENCY_NAMES, formatMoney } from '../shared/currencies';

export default function Edit( { attributes, setAttributes } ) {
	const { target, symbols, title, note } = attributes;
	const blockProps = useBlockProps();
	const validTarget = target.length === 3;
	const { status, data, error } = useRates(
		validTarget ? target : 'PKR',
		symbols
	);

	let footer = note;
	if ( ! footer && data && data.date ) {
		footer = sprintf(
			/* translators: %s: date of the rates. */
			__( 'Updated %s · Rates by ExchangeRate-API', 'nauman-currency-rates' ),
			data.date
		);
	}

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
					title={ __( 'Text', 'nauman-currency-rates' ) }
					initialOpen={ false }
				>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Title', 'nauman-currency-rates' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Footer note', 'nauman-currency-rates' ) }
						help={ __(
							'Leave empty to show the update date and credit.',
							'nauman-currency-rates'
						) }
						value={ note }
						onChange={ ( value ) => setAttributes( { note: value } ) }
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
				<div className="ncr-card ncr-card--editor">
					<div className="ncr-card__head">
						<h3 className="ncr-card__title">{ title }</h3>
						<span className="ncr-card__pill">
							{ sprintf(
								/* translators: %s: target currency code. */
								__( 'per 1 unit in %s', 'nauman-currency-rates' ),
								target || '---'
							) }
						</span>
					</div>
					{ status === 'loading' && <Spinner /> }
					{ status === 'ready' && data && (
						<ul className="ncr-card__list">
							{ symbols
								.filter( ( code ) => data.rates[ code ] )
								.map( ( code ) => (
									<li className="ncr-card__row" key={ code }>
										<span className="ncr-card__code">{ code }</span>
										<span className="ncr-card__name">
											{ CURRENCY_NAMES[ code ] || code }
										</span>
										<span className="ncr-card__value">
											{ formatMoney( data.rates[ code ] ) }
										</span>
									</li>
								) ) }
						</ul>
					) }
					{ footer && <p className="ncr-card__foot">{ footer }</p> }
				</div>
			</div>
		</>
	);
}

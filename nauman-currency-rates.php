<?php
/**
 * Plugin Name:       Nauman Currency Rates
 * Plugin URI:        https://syednauman.com
 * Description:       Shows live currency exchange rates with a shortcode. Fetches data from the free open.er-api.com feed (supports PKR, AED, SAR), caches it, and includes a settings page.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Syed Nauman
 * Author URI:        https://syednauman.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nauman-currency-rates
 */

// Stop direct access to this file.
defined( 'ABSPATH' ) || exit;

define( 'NCR_VERSION', '1.2.0' );
define( 'NCR_OPTION', 'ncr_settings' );
define( 'NCR_API_URL', 'https://open.er-api.com/v6/latest' );

/* -------------------------------------------------------------------------
 * Settings helpers
 * ---------------------------------------------------------------------- */

/**
 * Default plugin settings.
 *
 * @return array
 */
function ncr_defaults() {
	return array(
		'base'          => 'USD',
		'symbols'       => 'PKR,EUR,GBP',
		'cache_minutes' => 60,
	);
}

/**
 * Saved settings merged with defaults.
 *
 * @return array
 */
function ncr_get_settings() {
	return wp_parse_args( get_option( NCR_OPTION, array() ), ncr_defaults() );
}

/**
 * Clean one 3-letter currency code. Returns '' if invalid.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function ncr_sanitize_code( $value ) {
	$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );
	return 3 === strlen( $value ) ? $value : '';
}

/**
 * Clean a comma-separated list of currency codes (max 10).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function ncr_sanitize_codes( $value ) {
	$codes = array();
	foreach ( explode( ',', (string) $value ) as $part ) {
		$code = ncr_sanitize_code( trim( $part ) );
		if ( '' !== $code ) {
			$codes[] = $code;
		}
	}
	$codes = array_slice( array_values( array_unique( $codes ) ), 0, 10 );
	return implode( ',', $codes );
}

/**
 * Sanitize the whole settings array before it is saved.
 *
 * @param mixed $input Raw form input.
 * @return array
 */
function ncr_sanitize_settings( $input ) {
	$defaults = ncr_defaults();
	$input    = is_array( $input ) ? $input : array();

	$base    = isset( $input['base'] ) ? ncr_sanitize_code( $input['base'] ) : '';
	$symbols = isset( $input['symbols'] ) ? ncr_sanitize_codes( $input['symbols'] ) : '';
	$minutes = isset( $input['cache_minutes'] ) ? absint( $input['cache_minutes'] ) : 0;

	return array(
		'base'          => '' !== $base ? $base : $defaults['base'],
		'symbols'       => '' !== $symbols ? $symbols : $defaults['symbols'],
		'cache_minutes' => min( 1440, max( 5, $minutes > 0 ? $minutes : $defaults['cache_minutes'] ) ),
	);
}

/* -------------------------------------------------------------------------
 * API request with caching
 * ---------------------------------------------------------------------- */

/**
 * Return the last good copy of the data if we have one, otherwise the error.
 *
 * @param string   $stale_key Transient key holding the last good data.
 * @param WP_Error $error     The error to return if nothing is stored.
 * @return array|WP_Error
 */
function ncr_fallback( $stale_key, $error ) {
	$stale = get_transient( $stale_key );
	return ( false !== $stale ) ? $stale : $error;
}

/**
 * Get exchange rates from open.er-api.com (cached). The API returns every
 * currency for one base, so we keep only the requested symbols.
 *
 * @param string $base          Base currency code, e.g. USD.
 * @param string $symbols       Comma-separated target codes, e.g. PKR,EUR.
 * @param int    $cache_minutes How long to cache a good response.
 * @return array|WP_Error       array( 'base', 'date', 'rates' ) or WP_Error.
 */
function ncr_fetch_rates( $base, $symbols, $cache_minutes ) {
	$key       = md5( $base . '|' . $symbols );
	$cache_key = 'ncr_' . $key;
	$stale_key = 'ncr_stale_' . $key;
	$fail_key  = 'ncr_fail_' . $key;

	// 1. Fresh cached copy.
	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	// 2. If the API failed a moment ago, do not hammer it on every page load.
	if ( get_transient( $fail_key ) ) {
		return ncr_fallback(
			$stale_key,
			new WP_Error( 'ncr_backoff', __( 'The rates service is temporarily unavailable.', 'nauman-currency-rates' ) )
		);
	}

	// 3. Ask the API.
	$url      = trailingslashit( NCR_API_URL ) . rawurlencode( $base );
	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 8,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	$error = null;
	$data  = null;

	if ( is_wp_error( $response ) ) {
		$error = $response;
	} elseif ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		$error = new WP_Error(
			'ncr_http',
			sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The rates service returned HTTP %d.', 'nauman-currency-rates' ),
				(int) wp_remote_retrieve_response_code( $response )
			)
		);
	} else {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && isset( $body['result'] ) && 'success' !== $body['result'] ) {
			$body = null;
		}
		if ( is_array( $body ) && isset( $body['rates'] ) && is_array( $body['rates'] ) ) {
			$rates = array();
			foreach ( explode( ',', $symbols ) as $code ) {
				if ( isset( $body['rates'][ $code ] ) && is_numeric( $body['rates'][ $code ] ) && (float) $body['rates'][ $code ] > 0 ) {
					$rates[ $code ] = (float) $body['rates'][ $code ];
				}
			}
			if ( ! empty( $rates ) ) {
				$stamp = isset( $body['time_last_update_unix'] ) ? (int) $body['time_last_update_unix'] : 0;
				$data  = array(
					'base'  => isset( $body['base_code'] ) ? sanitize_text_field( (string) $body['base_code'] ) : $base,
					'date'  => $stamp > 0 ? gmdate( 'Y-m-d', $stamp ) : '',
					'rates' => $rates,
				);
			}
		}
		if ( null === $data ) {
			$error = new WP_Error( 'ncr_bad_data', __( 'The rates service sent an unexpected response or does not support these currencies.', 'nauman-currency-rates' ) );
		}
	}

	if ( null !== $data ) {
		set_transient( $cache_key, $data, (int) $cache_minutes * MINUTE_IN_SECONDS );
		set_transient( $stale_key, $data, DAY_IN_SECONDS );
		return $data;
	}

	// 4. It failed: remember that for 2 minutes and fall back to old data.
	set_transient( $fail_key, 1, 2 * MINUTE_IN_SECONDS );
	return ncr_fallback( $stale_key, $error );
}

/* -------------------------------------------------------------------------
 * Front end: shortcode and stylesheet
 * ---------------------------------------------------------------------- */

/**
 * Register the stylesheet (it is only loaded when the shortcode is used).
 */
function ncr_register_assets() {
	wp_register_style( 'ncr-style', plugin_dir_url( __FILE__ ) . 'assets/style.css', array(), NCR_VERSION );
	wp_register_script( 'ncr-converter', plugin_dir_url( __FILE__ ) . 'assets/converter.js', array(), NCR_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ncr_register_assets' );

/**
 * [ncr_rates base="USD" symbols="PKR,EUR,GBP" amount="1"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ncr_shortcode( $atts ) {
	$settings = ncr_get_settings();
	$atts     = shortcode_atts(
		array(
			'base'    => $settings['base'],
			'symbols' => $settings['symbols'],
			'amount'  => 1,
		),
		$atts,
		'ncr_rates'
	);

	$base    = ncr_sanitize_code( $atts['base'] );
	$symbols = ncr_sanitize_codes( $atts['symbols'] );
	$base    = '' !== $base ? $base : $settings['base'];
	$symbols = '' !== $symbols ? $symbols : $settings['symbols'];
	$amount  = is_numeric( $atts['amount'] ) ? (float) $atts['amount'] : 1.0;
	if ( $amount <= 0 || $amount > 1000000000 ) {
		$amount = 1.0;
	}

	wp_enqueue_style( 'ncr-style' );

	$data = ncr_fetch_rates( $base, $symbols, (int) $settings['cache_minutes'] );

	if ( is_wp_error( $data ) ) {
		$message = __( 'Exchange rates are unavailable right now.', 'nauman-currency-rates' );
		// Only admins see the technical reason.
		if ( current_user_can( 'manage_options' ) ) {
			$message .= ' ' . $data->get_error_message();
		}
		return '<div class="ncr-rates ncr-rates--error"><p>' . esc_html( $message ) . '</p></div>';
	}

	$decimals = ( floor( $amount ) === $amount ) ? 0 : 2;

	$html  = '<div class="ncr-rates">';
	$html .= '<p class="ncr-rates__title">' . sprintf(
		/* translators: 1: amount, 2: currency code. */
		esc_html__( '%1$s %2$s equals', 'nauman-currency-rates' ),
		esc_html( number_format_i18n( $amount, $decimals ) ),
		esc_html( $data['base'] )
	) . '</p>';
	$html .= '<ul class="ncr-rates__list">';
	foreach ( $data['rates'] as $code => $rate ) {
		$html .= '<li class="ncr-rates__item">';
		$html .= '<span class="ncr-rates__code">' . esc_html( $code ) . '</span>';
		$html .= '<span class="ncr-rates__value">' . esc_html( number_format_i18n( $rate * $amount, 4 ) ) . '</span>';
		$html .= '</li>';
	}
	$html .= '</ul>';
	if ( '' !== $data['date'] ) {
		$html .= '<p class="ncr-rates__meta">' . sprintf(
			/* translators: %s: date of the rates. */
			esc_html__( 'Updated %s · Rates by ExchangeRate-API', 'nauman-currency-rates' ),
			esc_html( $data['date'] )
		) . '</p>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'ncr_rates', 'ncr_shortcode' );

/**
 * Currency names for the card (fallback is the code itself).
 *
 * @return array
 */
function ncr_currency_names() {
	return array(
		'USD' => __( 'US Dollar', 'nauman-currency-rates' ),
		'EUR' => __( 'Euro', 'nauman-currency-rates' ),
		'GBP' => __( 'British Pound', 'nauman-currency-rates' ),
		'AED' => __( 'UAE Dirham', 'nauman-currency-rates' ),
		'SAR' => __( 'Saudi Riyal', 'nauman-currency-rates' ),
		'CAD' => __( 'Canadian Dollar', 'nauman-currency-rates' ),
		'AUD' => __( 'Australian Dollar', 'nauman-currency-rates' ),
		'JPY' => __( 'Japanese Yen', 'nauman-currency-rates' ),
		'CNY' => __( 'Chinese Yuan', 'nauman-currency-rates' ),
		'TRY' => __( 'Turkish Lira', 'nauman-currency-rates' ),
		'QAR' => __( 'Qatari Riyal', 'nauman-currency-rates' ),
		'KWD' => __( 'Kuwaiti Dinar', 'nauman-currency-rates' ),
	);
}

/**
 * [ncr_card target="PKR" symbols="USD,EUR,GBP,AED,SAR" title="Today's rates" note=""]
 *
 * Shows what 1 unit of each currency is worth in the target currency,
 * styled as the "Today's rates" hero card.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ncr_card_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'target'  => 'PKR',
			'symbols' => 'USD,EUR,GBP,AED,SAR',
			'title'   => __( "Today's rates", 'nauman-currency-rates' ),
			'note'    => '',
		),
		$atts,
		'ncr_card'
	);

	$settings = ncr_get_settings();
	$target   = ncr_sanitize_code( $atts['target'] );
	$target   = '' !== $target ? $target : 'PKR';
	$symbols  = ncr_sanitize_codes( $atts['symbols'] );
	$symbols  = '' !== $symbols ? $symbols : 'USD,EUR,GBP,AED,SAR';

	wp_enqueue_style( 'ncr-style' );

	// With the target as base, rates[X] = how many X you get for 1 target.
	$data = ncr_fetch_rates( $target, $symbols, (int) $settings['cache_minutes'] );

	if ( is_wp_error( $data ) ) {
		$message = __( 'Exchange rates are unavailable right now.', 'nauman-currency-rates' );
		if ( current_user_can( 'manage_options' ) ) {
			$message .= ' ' . $data->get_error_message();
		}
		return '<div class="ncr-card ncr-card--error"><p>' . esc_html( $message ) . '</p></div>';
	}

	$names = ncr_currency_names();

	$html  = '<div class="ncr-card">';
	$html .= '<div class="ncr-card__head">';
	$html .= '<h3 class="ncr-card__title">' . esc_html( $atts['title'] ) . '</h3>';
	$html .= '<span class="ncr-card__pill">' . sprintf(
		/* translators: %s: target currency code. */
		esc_html__( 'per 1 unit in %s', 'nauman-currency-rates' ),
		esc_html( $target )
	) . '</span>';
	$html .= '</div>';
	$html .= '<ul class="ncr-card__list">';
	foreach ( explode( ',', $symbols ) as $code ) {
		if ( ! isset( $data['rates'][ $code ] ) ) {
			continue;
		}
		$per_unit = 1 / $data['rates'][ $code ];
		$name     = isset( $names[ $code ] ) ? $names[ $code ] : $code;
		$html    .= '<li class="ncr-card__row">';
		$html    .= '<span class="ncr-card__code">' . esc_html( $code ) . '</span>';
		$html    .= '<span class="ncr-card__name">' . esc_html( $name ) . '</span>';
		$html    .= '<span class="ncr-card__value">' . esc_html( number_format_i18n( $per_unit, 2 ) ) . '</span>';
		$html    .= '</li>';
	}
	$html .= '</ul>';

	$foot = '' !== trim( $atts['note'] ) ? $atts['note'] : '';
	if ( '' === $foot && '' !== $data['date'] ) {
		$foot = sprintf(
			/* translators: %s: date of the rates. */
			__( 'Updated %s · Rates by ExchangeRate-API', 'nauman-currency-rates' ),
			$data['date']
		);
	}
	if ( '' !== $foot ) {
		$html .= '<p class="ncr-card__foot">' . esc_html( $foot ) . '</p>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'ncr_card', 'ncr_card_shortcode' );

/**
 * [ncr_converter target="PKR" symbols="USD,EUR,GBP,AED,SAR" amount="100"]
 *
 * Interactive calculator: pick a currency, type an amount, see the value in
 * the target currency. Rates come from the same cached server-side data, so
 * visitors' browsers never call the API.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function ncr_converter_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'target'  => 'PKR',
			'symbols' => 'USD,EUR,GBP,AED,SAR',
			'amount'  => 100,
			'label'   => __( 'Amount you want to exchange', 'nauman-currency-rates' ),
		),
		$atts,
		'ncr_converter'
	);

	$settings = ncr_get_settings();
	$target   = ncr_sanitize_code( $atts['target'] );
	$target   = '' !== $target ? $target : 'PKR';
	$symbols  = ncr_sanitize_codes( $atts['symbols'] );
	$symbols  = '' !== $symbols ? $symbols : 'USD,EUR,GBP,AED,SAR';
	$amount   = is_numeric( $atts['amount'] ) && (float) $atts['amount'] > 0 ? (float) $atts['amount'] : 100.0;

	wp_enqueue_style( 'ncr-style' );
	wp_enqueue_script( 'ncr-converter' );

	$data = ncr_fetch_rates( $target, $symbols, (int) $settings['cache_minutes'] );

	if ( is_wp_error( $data ) ) {
		$message = __( 'The currency calculator is unavailable right now.', 'nauman-currency-rates' );
		if ( current_user_can( 'manage_options' ) ) {
			$message .= ' ' . $data->get_error_message();
		}
		return '<div class="ncr-conv ncr-conv--error"><p>' . esc_html( $message ) . '</p></div>';
	}

	// Value of 1 unit of each currency in the target currency.
	$per_unit = array();
	foreach ( explode( ',', $symbols ) as $code ) {
		if ( isset( $data['rates'][ $code ] ) ) {
			$per_unit[ $code ] = 1 / $data['rates'][ $code ];
		}
	}
	$codes = array_keys( $per_unit );
	$id    = 'ncr-' . wp_unique_id();

	$html  = '<div class="ncr-conv" data-target="' . esc_attr( $target ) . '" data-rates="' . esc_attr( wp_json_encode( $per_unit ) ) . '">';
	$html .= '<p class="ncr-conv__label" id="' . esc_attr( $id ) . '-cur">' . esc_html__( 'Currency', 'nauman-currency-rates' ) . '</p>';
	$html .= '<div class="ncr-conv__chips" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-cur">';
	foreach ( $codes as $i => $code ) {
		$on    = 0 === $i;
		$html .= '<button type="button" class="ncr-conv__chip' . ( $on ? ' is-active' : '' ) . '" role="radio" aria-checked="' . ( $on ? 'true' : 'false' ) . '" data-code="' . esc_attr( $code ) . '">' . esc_html( $code ) . '</button>';
	}
	$html .= '</div>';
	$html .= '<label class="ncr-conv__label" for="' . esc_attr( $id ) . '-amt">' . esc_html( $atts['label'] ) . '</label>';
	$html .= '<input class="ncr-conv__input" id="' . esc_attr( $id ) . '-amt" type="text" inputmode="decimal" autocomplete="off" value="' . esc_attr( (string) $amount ) . '" />';
	$html .= '<div class="ncr-conv__result" aria-live="polite">';
	$html .= '<p class="ncr-conv__sub"></p>';
	$html .= '<p class="ncr-conv__total"></p>';
	$html .= '</div>';
	if ( '' !== $data['date'] ) {
		$html .= '<p class="ncr-conv__foot">' . sprintf(
			/* translators: %s: date of the rates. */
			esc_html__( 'Indicative rate · Updated %s · Rates by ExchangeRate-API', 'nauman-currency-rates' ),
			esc_html( $data['date'] )
		) . '</p>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'ncr_converter', 'ncr_converter_shortcode' );

/* -------------------------------------------------------------------------
 * Admin: settings page
 * ---------------------------------------------------------------------- */

/**
 * Add Settings -> Currency Rates.
 */
function ncr_add_menu() {
	add_options_page(
		__( 'Currency Rates', 'nauman-currency-rates' ),
		__( 'Currency Rates', 'nauman-currency-rates' ),
		'manage_options',
		'ncr-settings',
		'ncr_render_settings_page'
	);
}
add_action( 'admin_menu', 'ncr_add_menu' );

/**
 * Register the setting and its fields (WordPress Settings API).
 */
function ncr_register_settings() {
	register_setting(
		'ncr_settings_group',
		NCR_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ncr_sanitize_settings',
			'default'           => ncr_defaults(),
		)
	);

	add_settings_section( 'ncr_main', '', '__return_false', 'ncr-settings' );

	add_settings_field( 'ncr_base', __( 'Base currency', 'nauman-currency-rates' ), 'ncr_field_base', 'ncr-settings', 'ncr_main' );
	add_settings_field( 'ncr_symbols', __( 'Currencies to show', 'nauman-currency-rates' ), 'ncr_field_symbols', 'ncr-settings', 'ncr_main' );
	add_settings_field( 'ncr_cache', __( 'Cache time (minutes)', 'nauman-currency-rates' ), 'ncr_field_cache', 'ncr-settings', 'ncr_main' );
}
add_action( 'admin_init', 'ncr_register_settings' );

/**
 * Field: base currency.
 */
function ncr_field_base() {
	$settings = ncr_get_settings();
	printf(
		'<input type="text" class="small-text" maxlength="3" name="%1$s[base]" value="%2$s" /> <p class="description">%3$s</p>',
		esc_attr( NCR_OPTION ),
		esc_attr( $settings['base'] ),
		esc_html__( 'A 3-letter code, for example USD.', 'nauman-currency-rates' )
	);
}

/**
 * Field: target currencies.
 */
function ncr_field_symbols() {
	$settings = ncr_get_settings();
	printf(
		'<input type="text" class="regular-text" name="%1$s[symbols]" value="%2$s" /> <p class="description">%3$s</p>',
		esc_attr( NCR_OPTION ),
		esc_attr( $settings['symbols'] ),
		esc_html__( 'Comma-separated codes, up to 10. Example: PKR,EUR,GBP', 'nauman-currency-rates' )
	);
}

/**
 * Field: cache time.
 */
function ncr_field_cache() {
	$settings = ncr_get_settings();
	printf(
		'<input type="number" class="small-text" min="5" max="1440" name="%1$s[cache_minutes]" value="%2$d" /> <p class="description">%3$s</p>',
		esc_attr( NCR_OPTION ),
		(int) $settings['cache_minutes'],
		esc_html__( 'Between 5 and 1440. Rates are stored for this long before asking the API again.', 'nauman-currency-rates' )
	);
}

/**
 * Draw the settings page.
 */
function ncr_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'ncr_settings_group' );
			do_settings_sections( 'ncr-settings' );
			submit_button();
			?>
		</form>
		<hr />
		<h2><?php esc_html_e( 'How to use', 'nauman-currency-rates' ); ?></h2>
		<p><?php esc_html_e( 'Add this shortcode to any page or post:', 'nauman-currency-rates' ); ?></p>
		<p><code>[ncr_rates]</code></p>
		<p><?php esc_html_e( 'Override the settings for one place:', 'nauman-currency-rates' ); ?></p>
		<p><code>[ncr_rates base="EUR" symbols="USD,PKR" amount="100"]</code></p>
			<p><?php esc_html_e( 'Hero card (what 1 unit of each currency is worth in PKR):', 'nauman-currency-rates' ); ?></p>
			<p><?php esc_html_e( 'Calculator (pick a currency, type an amount):', 'nauman-currency-rates' ); ?></p>
			<p><code>[ncr_converter]</code></p>
			<p><code>[ncr_card]</code> &nbsp; <code>[ncr_card target="PKR" symbols="USD,EUR,GBP,AED,SAR"]</code></p>
	</div>
	<?php
}

/**
 * Add a "Settings" link on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function ncr_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=ncr-settings' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'nauman-currency-rates' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ncr_action_links' );

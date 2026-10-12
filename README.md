# Nauman Currency Rates

A small WordPress plugin that shows live currency exchange rates anywhere on your site with **Gutenberg blocks** or shortcodes. It calls the free [ExchangeRate-API open endpoint](https://www.exchangerate-api.com/docs/free) (no API key needed; covers PKR, AED, SAR and ~160 others), caches the result, and has a settings page.

Built as a learning and portfolio project to demonstrate external API integration, the WordPress Settings API, caching with transients, secure PHP, and block development (React, `block.json`, server-side rendering, a REST route).

<img width="1603" height="4896" alt="meridianexchange syednauman com_" src="https://github.com/user-attachments/assets/68cbb426-3bdb-4491-8e0f-20269d4c0c1b" />
Live demo: [meridianexchange.syednauman.com](https://meridianexchange.syednauman.com)


## Features

- **Two Gutenberg blocks** (Widgets category): *Currency Rates Card* and *Currency Calculator*, with a settings sidebar and a live preview in the editor
- `[ncr_rates]` shortcode with per-use overrides (base currency, currencies, amount)
- Settings page under **Settings → Currency Rates**
- Caching with the Transients API (5 to 1440 minutes)
- Graceful failure handling: timeout, bad HTTP status, invalid JSON
- Short back-off after a failed request, so a down API does not slow every page load
- Falls back to the last good rates (up to 24 hours old) when the API is unavailable
- Output escaped, input sanitized, settings page restricted to `manage_options`
- Stylesheet loads only on pages that use the shortcode
- Cleans up its settings and cached data when deleted

## Install

1. Download this repository as a ZIP (the folder inside must be named `nauman-currency-rates`).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, upload the ZIP and activate.
3. Go to **Settings → Currency Rates** to choose your defaults.

## Blocks

In the block editor, click **+** and search for **Currency Rates Card** or **Currency Calculator**.

| Block | Sidebar settings |
|---|---|
| Currency Rates Card | Target currency, currencies to show, title, footer note |
| Currency Calculator | Target currency, currencies to show, starting amount, amount label |

Both blocks are rendered on the server (PHP), so the post only stores the block's settings and rates stay cached. The calculator adds one small script (no dependencies). The editor preview uses a REST route, `GET /wp-json/ncr/v1/rates`, which reads the same cached data and is limited to users who can edit posts.

## Usage (shortcodes)

Add the shortcode to any page, post, or text widget:

```
[ncr_rates]
```

Override the settings for one place:

```
[ncr_rates base="EUR" symbols="USD,PKR,GBP" amount="100"]
```

| Attribute | Default | Description |
|---|---|---|
| `base` | Settings value (USD) | 3-letter base currency code |
| `symbols` | Settings value (PKR,EUR,GBP) | Comma-separated target codes, up to 10 |
| `amount` | `1` | Amount of the base currency to convert |

### Calculator

```
[ncr_converter]
[ncr_converter target="PKR" symbols="USD,EUR,GBP,AED,SAR" amount="100"]
```

Currency chips plus an amount box; the result updates as you type. Rates are baked into the page from the cached server-side data, so the visitor's browser never calls the API.

### Hero card

```
[ncr_card]
[ncr_card target="PKR" symbols="USD,EUR,GBP,AED,SAR" title="Today's rates" note="Sample figures"]
```

Shows what 1 unit of each currency is worth in the `target` currency (default PKR), styled as a white rounded card. `note` replaces the footer line; leave it out to show the update date and attribution.

## How it works

1. The block or shortcode asks `ncr_fetch_rates()` for data.
2. A fresh cached copy is returned if there is one.
3. Otherwise, the plugin calls `wp_remote_get()` with an 8-second timeout and validates the JSON.
4. Good data is cached for the chosen time and also kept for 24 hours as a fallback copy.
5. If the request fails, the plugin remembers the failure for 2 minutes and serves the fallback copy if it has one. Visitors only see a generic message; administrators also see the technical reason.

## Security notes

- All output is escaped (`esc_html`, `esc_attr`).
- Shortcode attributes and settings are sanitized; currency codes must be exactly 3 letters.
- The settings page checks `current_user_can( 'manage_options' )` and uses the Settings API, which handles nonces.
- The API response is validated before it is cached or displayed.

## Requirements

- WordPress 6.5 or newer
- PHP 7.4 or newer

## Development

The editor code is in `src/` and compiles to `build/` (which is committed, so the plugin works without Node).

```
npm install
npm run build   # production build
npm start       # watch mode
npm test        # component tests (jest)
```

```
nauman-currency-rates.php   plugin, shortcodes, REST route, block registration
src/rates-card/             block.json, index.js, edit.js
src/converter/              block.json, index.js, edit.js
src/shared/                 REST hook, controls, currency helpers
build/                      compiled blocks (generated)
assets/                     front-end CSS and calculator script
```

## Credits

Exchange rate data: [Rates By Exchange Rate API](https://www.exchangerate-api.com). Attribution is required by their free-plan terms; the plugin prints it under the rates.

## License

GPL-2.0-or-later

## Author

Syed Nauman, [syednauman.com](https://syednauman.com)

# Changelog — Secure Offline CC for WooCommerce

All notable changes to this project are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.1.0] — 2026-07-22

### Added
- AES-256-GCM encrypted storage for all card data (card number, expiry, CVV, cardholder name)
- Admin order meta box: Card Details panel on WooCommerce order edit screen
- "View Card Details" button — AJAX-powered decrypt with 60-second auto-clear modal
- "Purge Card Data" button — permanently deletes all encrypted card meta
- Audit log database table (`socc_audit_log`) — logs every view and purge with user ID, username, IP address, and timestamp
- Last 5 audit log entries displayed in the order meta box
- Notification-only email — merchant receives zero card data in email; only a secure link to the admin order
- `wp-config.php` key helper — generates a cryptographically secure 64-character hex key on the settings page
- Auto-purge via WP Cron — configurable number of days before automatic card data deletion
- WooCommerce HPOS (High Performance Order Storage) compatibility declaration
- GCM authentication tag verification — failed auth tags are rejected and logged
- `uninstall.php` — cleans up all plugin options and the audit log database table on deletion

### Changed
- Notification email now contains zero card data (was previously full card details)
- Encryption switched from no encryption (v2.0.0) to AES-256-GCM
- Plugin slug renamed to `secure-offline-cc` (generic, non-branded)
- All text domain references updated to `secure-offline-cc`
- All database/option prefixes updated to `socc_`

### Removed
- External `ccvs.php` card validation library (replaced with native PHP Luhn check)
- License server / updater system (no more nag notices)
- Full card data storage in order meta as plaintext

### Security
- Card data now encrypted at rest with AES-256-GCM before any database write
- Encryption key stored in `wp-config.php` — never in the database
- All AJAX endpoints require valid nonce + `manage_woocommerce` capability
- Auto-clear on admin modal prevents card data lingering on screen

---

## [2.0.0] — 2026-07-15

### Added
- Complete rewrite for WordPress 7.0+ and WooCommerce 10.x+
- PHP 8.0+ strict compatibility throughout
- Native Luhn algorithm card number validation (no external library)
- Card type auto-detection (Visa, MasterCard, Amex, Discover, JCB, Diners, Maestro)
- HPOS compatibility declaration
- Modern `wc_get_order()` usage
- Proper `get_option()` / `update_meta_data()` WooCommerce patterns

### Removed
- External `ccvs.php` card validation library
- License server / updater system
- `WP_PLUGIN_URL` constant (deprecated) — replaced with `plugin_dir_url()`
- Support for WC < 7.0 and PHP < 8.0

---

## [1.7.9] — Legacy (WP Lab)

Original plugin by WP Lab. Last tested with WooCommerce 3.3.3 and WordPress 4.9.4. No longer maintained.

## [2.1.1] - 2026-07-26
### Fixed
- Renamed include files from `class-dir-cc-*.php` to `class-socc-*.php` to match main plugin require chain
- Replaced broken/placeholder PNG icon with proper SVG credit card icons (Visa, MC, Discover, Amex)

## [2.1.2] - 2026-07-26
### Added
- Integrated Plugin Update Checker (PUC v5) for automatic updates from GitHub releases
- Plugin now checks GitHub repo for new versions and prompts update in WP Admin

## [2.1.3] - 2026-07-26
### Fixed
- Declared full WooCommerce block checkout compatibility (resolves admin notice)
### Added
- SOCC_Blocks class and socc-blocks.js for block-based checkout registration
- Gateway now renders correctly in WooCommerce block checkout

## [2.1.4] - 2026-07-26
### Changed
- Internal version bump for PUC auto-update test

## [2.1.5] - 2026-07-26
### Fixed
- Replaced `$this->form()` with explicit socc-prefixed card input fields
- Field names now match validate_fields() and process_payment() expectations
- Card number, expiry, CVC all render correctly at classic checkout
### Added
- socc-checkout.js: auto-formats card number (groups of 4), expiry (MM / YY), CVC
- enqueue_checkout_scripts() method loads JS only on checkout page

## [2.1.6] - 2026-07-26
### Fixed
- Block checkout: rewrote Content as real React component with useState/useEffect
- Block checkout now renders controlled inputs for card number, expiry, CVC
- Block checkout now wires onPaymentSetup to emit paymentMethodData with socc-card-number, socc-card-expiry, socc-card-cvc, socc_holder
- Added cardholderField to get_payment_method_data() so JS knows whether to render name field
### Root Cause
- v2.1.5 only fixed classic checkout payment_fields() — block checkout uses socc-blocks.js which only rendered RawHTML of description, no inputs at all

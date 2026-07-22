=== Secure Offline CC for WooCommerce ===
Contributors: designitright
Tags: woocommerce, payment gateway, credit card, offline, encrypted
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.1.0
WC requires at least: 7.0.0
WC tested up to: 10.9.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A secure, modern WooCommerce offline credit card gateway with AES-256-GCM encrypted storage, audit logging, and admin-only card retrieval.

== Description ==

**Secure Offline CC for WooCommerce** is a modernized replacement for legacy offline credit card processing plugins. It allows WooCommerce stores to collect credit card details at checkout, store them encrypted in the database, and process them manually through a physical or virtual terminal.

This plugin is designed for merchants who cannot use real-time payment processors (high-risk industries, technical limitations, or fallback processing) and need a secure, auditable way to handle card data.

= Key Features =

* **AES-256-GCM encryption** — the same encryption standard used by financial institutions
* **Zero card data in emails** — notification emails contain zero sensitive data; only a link to the admin order screen
* **Admin-only decryption** — card details are only viewable by users with `manage_woocommerce` capability
* **60-second auto-clear** — decrypted card data disappears from the screen automatically
* **One-click purge** — permanently delete card data after processing
* **Full audit log** — every view and purge is logged with user, timestamp, and IP address
* **Auto-purge via cron** — automatically purges card data after a configurable number of days
* **HPOS compatible** — fully compatible with WooCommerce High Performance Order Storage
* **PHP 8.0+ strict** — built for modern PHP, no deprecated functions
* **No license server** — no phoning home, no nag notices, no external dependencies

= How It Works =

1. Customer enters card details at checkout
2. Card data is encrypted server-side with AES-256-GCM before touching the database
3. A notification-only email alerts you a new order is ready to process
4. You log into WP Admin → WooCommerce → Orders → open the order
5. Click **View Card Details** to decrypt and display the card for 60 seconds
6. Key the card into your terminal, then click **Purge Card Data**
7. The encrypted data is permanently deleted — nothing remains on the server

= Security Architecture =

* Encryption key stored in `wp-config.php` — never in the database
* AES-256-GCM with random IV per transaction — authenticated encryption prevents tampering
* GCM auth tag verification on every decrypt — failed tags are logged and rejected
* Only last 4 digits stored in plaintext (PCI DSS permitted truncation)
* All AJAX endpoints protected by nonce + capability check
* Admin screen auto-clears after 60 seconds
* Audit trail of every access event

= Important Notice =

**This plugin does not make your store PCI DSS compliant.** It is designed to minimize your PCI scope by using strong encryption and minimizing card data exposure. If you process significant card volume, consult a Qualified Security Assessor (QSA). For the most secure solution, use a fully-hosted gateway (Stripe, Authorize.net, Square) so card data never touches your server at all.

= Configuration =

After activation, add your encryption key to `wp-config.php`:

`define('SOCC_ENCRYPTION_KEY', 'your-64-character-hex-key-here');`

A unique key is generated for you in WooCommerce → Settings → Payments → Secure Offline CC.

== Installation ==

1. Upload the `secure-offline-cc` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. Go to **WooCommerce → Settings → Payments → Secure Offline CC**
4. Copy the generated encryption key and add it to `wp-config.php`
5. Enter your notification email address
6. Enable the gateway and save

== Frequently Asked Questions ==

= Is this PCI DSS compliant? =

This plugin uses AES-256-GCM encryption — the same standard used in banking — to minimize your PCI scope. However, any merchant storing card data bears PCI DSS obligations. For full compliance, consult a Qualified Security Assessor. If possible, use a real-time processor (Stripe, Authorize.net) so card data never reaches your server.

= Where is the encryption key stored? =

The key is stored in your `wp-config.php` file — outside the database. This means a database breach alone cannot decrypt your card data. Both the database AND `wp-config.php` would need to be compromised simultaneously.

= What happens if I lose my encryption key? =

Encrypted card data cannot be recovered without the original key. Set the key immediately after installation and never change it while encrypted orders exist. Back up your `wp-config.php` securely.

= Can multiple admins view card details? =

Yes — any user with the `manage_woocommerce` capability can view and purge card details. Every access is logged to the audit table with username, timestamp, and IP address.

= How do I process the card? =

After clicking **View Card Details**, manually enter the card number, expiry, and CVV into your physical card terminal, virtual terminal, or payment processor's dashboard. After processing, click **Purge Card Data** to permanently delete the encrypted data.

= What is the auto-purge feature? =

You can configure the plugin to automatically purge encrypted card data after a set number of days (default: 30). This runs via WP Cron. Cards purged automatically are logged in the audit table.

= Does this work with WooCommerce Subscriptions? =

The gateway declares subscription support, but manual offline processing of recurring subscription charges requires manual intervention for each renewal. This plugin is best suited for one-time orders.

= Is this compatible with WooCommerce HPOS? =

Yes. The plugin declares full compatibility with WooCommerce High Performance Order Storage (custom order tables).

== Screenshots ==

1. Checkout payment form — standard WooCommerce credit card fields
2. Admin order screen — Card Details meta box showing card type, last 4, and stored date
3. Decrypted card modal — 60-second countdown auto-clear
4. Audit log — access history per order

== Changelog ==

= 2.1.0 =
* Added AES-256-GCM encrypted storage for card data
* Added admin order meta box with View Card Details and Purge Card Data buttons
* Added 60-second auto-clear modal for decrypted card display
* Added audit log table (socc_audit_log) tracking all view and purge events
* Added notification-only email — zero card data in transit
* Added wp-config.php encryption key helper with one-click key generation
* Added auto-purge via WP Cron (configurable days)
* Added HPOS compatibility declaration
* Removed external ccvs.php library — replaced with native Luhn check
* Removed license/updater system

= 2.0.0 =
* Complete rewrite for WP 7.0+ and WooCommerce 10.x+
* PHP 8.0+ strict compatibility
* Modern WooCommerce gateway API patterns
* Built-in Luhn validation

== Upgrade Notice ==

= 2.1.0 =
Major security upgrade. After updating, go to WooCommerce → Settings → Payments → Secure Offline CC and add the generated SOCC_ENCRYPTION_KEY to your wp-config.php before processing any new orders.

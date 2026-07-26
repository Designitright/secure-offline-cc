<?php
/**
 * Plugin Name: Secure Offline CC for WooCommerce
 * Plugin URI: https://design-it-right.com
 * Description: A modernized WooCommerce payment gateway for processing credit cards offline with secure GCM encryption and audit logs.
 * Version: 2.1.4
 * Author: Design It Right / Josh AI
 * Author URI: https://design-it-right.com
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0.0
 * WC tested up to: 10.9.4
 * Text Domain: secure-offline-cc
 * Domain Path: /languages
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

// Define constants
define( 'SOCC_VERSION', '2.1.4' );
define( 'SOCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOCC_URL', plugin_dir_url( __FILE__ ) );
// ── Auto-update via GitHub releases ───────────────────────────────────────────
$socc_puc_file = plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $socc_puc_file ) ) {
    require_once $socc_puc_file;
    $soccUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/Designitright/secure-offline-cc/',
        __FILE__,
        'secure-offline-cc'
    );
    $soccUpdateChecker->setBranch( 'main' );
    $soccUpdateChecker->getVcsApi()->enableReleaseAssets();
}
// ── End auto-update ───────────────────────────────────────────────────────────


/**
 * Declare WooCommerce HPOS compatibility
 */
add_action( 'before_woocommerce_init', 'socc_declare_hpos_compatibility' );
function socc_declare_hpos_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

/**
 * Load translation text domain
 */
add_action( 'plugins_loaded', 'socc_load_textdomain' );
function socc_load_textdomain() {
	load_plugin_textdomain( 'secure-offline-cc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Initialize the plugin after WooCommerce loads
 */
add_action( 'plugins_loaded', 'socc_init', 10 );

function socc_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	// Include core class files
	require_once SOCC_PATH . 'includes/class-socc-crypto.php';
	require_once SOCC_PATH . 'includes/class-socc-audit.php';
	require_once SOCC_PATH . 'includes/class-socc-admin.php';
	require_once SOCC_PATH . 'includes/class-socc-gateway.php';

	// Register payment gateway
	add_filter( 'woocommerce_payment_gateways', 'socc_register_gateway' );
}

function socc_register_gateway( $gateways ) {
	$gateways[] = 'WC_Secure_Offline_CC';
	return $gateways;
}
/**
 * Register Blocks integration
 */
add_action( 'woocommerce_blocks_loaded', 'socc_register_blocks_integration' );
function socc_register_blocks_integration() {
	if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
		return;
	}
	require_once SOCC_PATH . 'includes/class-socc-blocks.php';
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( $registry ) {
			$registry->register( new SOCC_Blocks() );
		}
	);
}


/**
 * Activation Hook
 */
register_activation_hook( __FILE__, 'socc_activate' );
function socc_activate() {
	// Include Audit log class to create table
	require_once SOCC_PATH . 'includes/class-socc-audit.php';
	SOCC_Audit::create_table();

	// Schedule Daily Cron Purge
	if ( ! wp_next_scheduled( 'socc_daily_purge_event' ) ) {
		wp_schedule_event( time(), 'daily', 'socc_daily_purge_event' );
	}
}

/**
 * Deactivation Hook
 */
register_deactivation_hook( __FILE__, 'socc_deactivate' );
function socc_deactivate() {
	wp_clear_scheduled_hook( 'socc_daily_purge_event' );
}

/**
 * Handle Daily Cron Purge
 */
add_action( 'socc_daily_purge_event', 'socc_do_daily_purge' );
function socc_do_daily_purge() {
	$gateway_settings = get_option( 'woocommerce_socc_settings', [] );
	$purge_days       = isset( $gateway_settings['purge_days'] ) ? (int) $gateway_settings['purge_days'] : 30;

	if ( $purge_days <= 0 ) {
		return;
	}

	$cutoff_time = time() - ( $purge_days * DAY_IN_SECONDS );
	$cutoff_date = gmdate( 'Y-m-d H:i:s', $cutoff_time );

	// Fetch orders to purge
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( [
			'limit'          => -1,
			'payment_method' => 'socc',
			'meta_key'       => '_socc_stored_at',
			'meta_value'     => $cutoff_date,
			'meta_compare'   => '<',
		] );

		require_once SOCC_PATH . 'includes/class-socc-audit.php';

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();

			$order->delete_meta_data( '_socc_encrypted' );
			$order->delete_meta_data( '_socc_iv' );
			$order->delete_meta_data( '_socc_tag' );
			$order->delete_meta_data( '_socc_stored_at' );
			$order->delete_meta_data( '_socc_last4' );
			$order->delete_meta_data( '_socc_type' );

			$order->update_meta_data( '_socc_purged_at', current_time( 'mysql' ) );
			$order->save();

			SOCC_Audit::log(
				$order_id,
				0,
				'system_cron',
				'purged',
				'127.0.0.1'
			);
		}
	}
}

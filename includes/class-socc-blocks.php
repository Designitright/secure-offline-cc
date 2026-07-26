<?php
/**
 * WooCommerce Blocks integration for Secure Offline CC
 * Registers the gateway with the block-based checkout.
 *
 * @package SecureOfflineCC
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * SOCC_Blocks class
 */
final class SOCC_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id
	 *
	 * @var string
	 */
	protected $name = 'socc';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_socc_settings', [] );
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'socc-blocks',
			SOCC_URL . 'assets/js/socc-blocks.js',
			[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ],
			SOCC_VERSION,
			true
		);
		return [ 'socc-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'          => $this->get_setting( 'title', __( 'Credit Card (Offline)', 'secure-offline-cc' ) ),
			'description'    => $this->get_setting( 'description', '' ),
			'supports'       => $this->get_supported_features(),
			'cardholderField' => 'yes' === $this->get_setting( 'cardholder_field', 'no' ),
		];
	}
}

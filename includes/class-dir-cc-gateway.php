<?php
/**
 * Class WC_Secure_Offline_CC
 * WooCommerce Payment Gateway Subclass.
 */

defined( 'ABSPATH' ) || exit;

class WC_Secure_Offline_CC extends WC_Payment_Gateway_CC {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'socc';
		$this->method_title       = __( 'Offline Credit Card (DiR)', 'secure-offline-cc' );
		$this->method_description = __( 'Accept credit card details at checkout and store them using AES-256-GCM. Card data is decrypted on-screen for manual processing and auto-purged securely.', 'secure-offline-cc' );
		$this->has_fields         = true;
		$this->icon               = SOCC_URL . 'assets/images/cards-visa-mc-discover-amex.png';

		$this->supports = [
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
		];

		$this->init_form_fields();
		$this->init_settings();

		// Load settings values
		$this->title            = $this->get_option( 'title', __( 'Credit Card', 'secure-offline-cc' ) );
		$this->description      = $this->get_option( 'description', __( 'Pay securely with your credit card.', 'secure-offline-cc' ) );
		$this->enabled          = $this->get_option( 'enabled', 'no' );
		$this->testmode         = $this->get_option( 'testmode', 'no' );
		$this->email_address    = $this->get_option( 'email_address', '' );
		$this->new_order_status = $this->get_option( 'new_order_status', 'on-hold' );
		$this->cardholder_field = 'yes' === $this->get_option( 'cardholder_field', 'no' );
		$this->purge_days       = (int) $this->get_option( 'purge_days', '30' );
		$this->disable_checksum = 'yes' === $this->get_option( 'disable_checksum', 'no' );

		// Admin hooks
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		}

		// Frontend hooks
		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
		add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 2 );
		add_action( 'woocommerce_email_after_order_table', [ $this, 'email_admin_details' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Init setting fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => __( 'Enable/Disable', 'secure-offline-cc' ),
				'label'   => __( 'Enable Secure Offline CC for WooCommerce', 'secure-offline-cc' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'title' => [
				'title'       => __( 'Title', 'secure-offline-cc' ),
				'type'        => 'text',
				'description' => __( 'The payment method title shown to customers at checkout.', 'secure-offline-cc' ),
				'default'     => __( 'Credit Card', 'secure-offline-cc' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Description', 'secure-offline-cc' ),
				'type'        => 'textarea',
				'description' => __( 'Description shown to the customer at checkout.', 'secure-offline-cc' ),
				'default'     => __( 'Pay securely with your credit card.', 'secure-offline-cc' ),
				'desc_tip'    => true,
			],
			'email_address' => [
				'title'       => __( 'Notification Email', 'secure-offline-cc' ),
				'type'        => 'text',
				'description' => __( 'Email address(es) to receive storage notifications. Separate multiple addresses with a comma.', 'secure-offline-cc' ),
				'default'     => get_option( 'admin_email' ),
				'desc_tip'    => true,
			],
			'new_order_status' => [
				'title'       => __( 'New Order Status', 'secure-offline-cc' ),
				'type'        => 'select',
				'description' => __( 'Status assigned to new orders placed with this gateway. On-Hold is recommended.', 'secure-offline-cc' ),
				'default'     => 'on-hold',
				'desc_tip'    => true,
				'options'     => [
					'on-hold'    => __( 'On Hold', 'secure-offline-cc' ),
					'processing' => __( 'Processing', 'secure-offline-cc' ),
					'pending'    => __( 'Pending Payment', 'secure-offline-cc' ),
				],
			],
			'cardholder_field' => [
				'title'       => __( 'Cardholder Name', 'secure-offline-cc' ),
				'label'       => __( 'Request cardholder name at checkout', 'secure-offline-cc' ),
				'type'        => 'checkbox',
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'purge_days' => [
				'title'             => __( 'Auto-Purge Card Data (Days)', 'secure-offline-cc' ),
				'type'              => 'number',
				'description'       => __( 'Number of days to keep encrypted card details before automatically purging them via cron. Enter 0 to disable.', 'secure-offline-cc' ),
				'default'           => '30',
				'desc_tip'          => true,
				'custom_attributes' => [
					'min'  => 0,
					'step' => 1,
				],
			],
			'disable_checksum' => [
				'title'   => __( 'Disable Luhn Check', 'secure-offline-cc' ),
				'label'   => __( 'Disable card number checksum (Luhn algorithm) validation', 'secure-offline-cc' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'desc_tip' => true,
			],
			'testmode' => [
				'title'    => __( 'Test Mode', 'secure-offline-cc' ),
				'label'    => __( 'Enable test mode', 'secure-offline-cc' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => true,
			],
		];
	}

	/**
	 * Override admin options layout to add wp-config helper notice.
	 */
	public function admin_options() {
		if ( ! defined( 'SOCC_ENCRYPTION_KEY' ) ) {
			try {
				$generated_key = bin2hex( random_bytes( 32 ) );
			} catch ( \Exception $e ) {
				$generated_key = bin2hex( openssl_random_pseudo_bytes( 32 ) );
			}
			?>
			<div class="notice notice-warning inline" style="margin-bottom: 20px; padding: 15px; border-left-color: #ffb900;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Security Alert: Encryption Key Not Defined', 'secure-offline-cc' ); ?></h3>
				<p><?php esc_html_e( 'To secure card details using AES-256-GCM, you must define a unique encryption key in your wp-config.php file. If not defined, a fallback key derived from SECURE_AUTH_KEY is used, which is less secure.', 'secure-offline-cc' ); ?></p>
				<p><strong><?php esc_html_e( 'Recommended Action:', 'secure-offline-cc' ); ?></strong></p>
				<p><?php esc_html_e( 'Copy the line below and add it to your <code>wp-config.php</code> file, BEFORE the line that says <code>/* That\'s all, stop editing! Happy publishing. */</code>:', 'secure-offline-cc' ); ?></p>
				<pre style="background: #f0f0f0; padding: 10px; border: 1px solid #ccc; overflow-x: auto; user-select: all; font-family: monospace;">define( 'SOCC_ENCRYPTION_KEY', '<?php echo esc_html( $generated_key ); ?>' );</pre>
				<p style="color: #d63638; font-weight: bold;"><?php esc_html_e( 'Warning: Keep this key safe. If you lose or change this key, any currently stored credit card data will become undecryptable!', 'secure-offline-cc' ); ?></p>
			</div>
			<?php
		}

		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Render payment fields.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p>' . esc_html( $this->description ) . '</p>';
		}

		$this->form();

		if ( $this->cardholder_field ) {
			echo '<p class="form-row form-row-wide">
				<label for="socc_holder">' . esc_html__( 'Cardholder Name', 'secure-offline-cc' ) . ' <span class="required">*</span></label>
				<input type="text" class="input-text" id="socc_holder" name="socc_holder" autocomplete="cc-name" placeholder="' . esc_attr__( 'Name on card', 'secure-offline-cc' ) . '" />
			</p>';
		}
	}

	/**
	 * Validate payment fields on checkout.
	 */
	public function validate_fields(): bool {
		$errors = [];

		$card_number = isset( $_POST['socc-card-number'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['socc-card-number'] ) ) ) : '';
		$expiry      = isset( $_POST['socc-card-expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['socc-card-expiry'] ) ) : '';
		$cvc         = isset( $_POST['socc-card-cvc'] ) ? sanitize_text_field( wp_unslash( $_POST['socc-card-cvc'] ) ) : '';

		if ( empty( $card_number ) ) {
			$errors[] = __( 'Please enter your card number.', 'secure-offline-cc' );
		} elseif ( ! $this->disable_checksum && ! $this->luhn_check( $card_number ) ) {
			$errors[] = __( 'The card number you entered is invalid.', 'secure-offline-cc' );
		}

		if ( empty( $expiry ) ) {
			$errors[] = __( 'Please enter your card expiry date.', 'secure-offline-cc' );
		} else {
			$expiry_parts = array_map( 'trim', explode( '/', $expiry ) );
			if ( 2 !== count( $expiry_parts ) ) {
				$errors[] = __( 'Please enter a valid expiry date (MM/YY).', 'secure-offline-cc' );
			} else {
				$exp_month = (int) $expiry_parts[0];
				$exp_year  = (int) ( 2 === strlen( $expiry_parts[1] ) ? '20' . $expiry_parts[1] : $expiry_parts[1] );
				if ( $exp_month < 1 || $exp_month > 12 || $exp_year < (int) date( 'Y' ) ||
					( $exp_year === (int) date( 'Y' ) && $exp_month < (int) date( 'm' ) ) ) {
					$errors[] = __( 'Your card has expired or the expiry date is invalid.', 'secure-offline-cc' );
				}
			}
		}

		if ( empty( $cvc ) || ! preg_match( '/^\d{3,4}$/', $cvc ) ) {
			$errors[] = __( 'Please enter a valid card security code (CVV/CVC).', 'secure-offline-cc' );
		}

		if ( $this->cardholder_field ) {
			$holder = isset( $_POST['socc_holder'] ) ? sanitize_text_field( wp_unslash( $_POST['socc_holder'] ) ) : '';
			if ( empty( $holder ) ) {
				$errors[] = __( 'Please enter the cardholder name.', 'secure-offline-cc' );
			}
		}

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				wc_add_notice( $error, 'error' );
			}
			return false;
		}

		return true;
	}

	/**
	 * Process payment.
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		$card_number = isset( $_POST['socc-card-number'] ) ? preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['socc-card-number'] ) ) ) : '';
		$expiry      = isset( $_POST['socc-card-expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['socc-card-expiry'] ) ) : '';
		$cvc         = isset( $_POST['socc-card-cvc'] ) ? sanitize_text_field( wp_unslash( $_POST['socc-card-cvc'] ) ) : '';
		$holder      = $this->cardholder_field && isset( $_POST['socc_holder'] ) ? sanitize_text_field( wp_unslash( $_POST['socc_holder'] ) ) : '';
		$card_type   = $this->detect_card_type( $card_number );

		$payload = wp_json_encode( [
			'number' => $card_number,
			'expiry' => $expiry,
			'cvv'    => $cvc,
			'holder' => $holder,
		] );

		$encrypted_data = SOCC_Crypto::encrypt( $payload );

		if ( false !== $encrypted_data ) {
			$order->update_meta_data( '_socc_encrypted', $encrypted_data['ciphertext'] );
			$order->update_meta_data( '_socc_iv', $encrypted_data['iv'] );
			$order->update_meta_data( '_socc_tag', $encrypted_data['tag'] );
			$order->update_meta_data( '_socc_last4', substr( $card_number, -4 ) );
			$order->update_meta_data( '_socc_type', $card_type );
			$order->update_meta_data( '_socc_stored_at', current_time( 'mysql' ) );
			$order->delete_meta_data( '_socc_purged_at' );
			$order->save();
		} else {
			error_log( 'Secure Offline CC - Encryption failed during checkout for order ID ' . $order_id );
			wc_add_notice( __( 'An error occurred while securing your payment details. Please try again.', 'secure-offline-cc' ), 'error' );
			return [
				'result' => 'failure',
			];
		}

		$this->send_notification_email( $order );

		$order->update_status(
			$this->new_order_status,
			__( 'Offline credit card payment details stored securely — pending manual processing.', 'secure-offline-cc' )
		);

		wc_reduce_stock_levels( $order_id );

		if ( function_exists( 'WC' ) && isset( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Send notification email to merchant.
	 */
	private function send_notification_email( $order ): void {
		$to = ! empty( $this->email_address ) ? $this->email_address : get_option( 'admin_email' );
		if ( empty( $to ) ) {
			return;
		}

		$order_number = $order->get_order_number();
		$order_url    = $order->get_edit_order_url();

		$subject = sprintf( __( 'New Order %s — Card on File', 'secure-offline-cc' ), $order_number );

		$mailer = function_exists( 'WC' ) ? WC()->mailer() : null;
		if ( $mailer ) {
			$email_content = '<p>' . sprintf(
				__( 'New order #%1$s received. Card on file. Log in to view: %2$s', 'secure-offline-cc' ),
				'<strong>' . esc_html( $order_number ) . '</strong>',
				'<a href="' . esc_url( $order_url ) . '">' . esc_html__( 'View Order Details', 'secure-offline-cc' ) . '</a>'
			) . '</p>';

			$html    = $mailer->wrap_message( $subject, $email_content );
			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

			$emails = array_map( 'trim', explode( ',', $to ) );
			foreach ( $emails as $email ) {
				wp_mail( $email, $subject, $html, $headers );
			}
		} else {
			$plain_text = sprintf(
				__( "New order #%1\$s received. Card on file. Log in to view: %2\$s", 'secure-offline-cc' ),
				$order_number,
				$order_url
			);
			$emails = array_map( 'trim', explode( ',', $to ) );
			foreach ( $emails as $email ) {
				wp_mail( $email, $subject, $plain_text );
			}
		}
	}

	/**
	 * Detect card type.
	 */
	private function detect_card_type( string $number ): string {
		$patterns = [
			'Visa'             => '/^4/',
			'MasterCard'       => '/^5[1-5]|^2[2-7]/',
			'American Express' => '/^3[47]/',
			'Discover'         => '/^6(?:011|5)/',
			'JCB'              => '/^35/',
			'Diners Club'      => '/^3(?:0[0-5]|[68])/',
			'Maestro'          => '/^(?:50|6[0-9])/',
		];
		foreach ( $patterns as $type => $pattern ) {
			if ( preg_match( $pattern, $number ) ) {
				return $type;
			}
		}
		return 'Unknown';
	}

	/**
	 * Luhn check.
	 */
	private function luhn_check( string $number ): bool {
		$sum    = 0;
		$alt    = false;
		$digits = str_split( strrev( $number ) );
		foreach ( $digits as $digit ) {
			$n = (int) $digit;
			if ( $alt ) {
				$n *= 2;
				if ( $n > 9 ) {
					$n -= 9;
				}
			}
			$sum += $n;
			$alt  = ! $alt;
		}
		return 0 === ( $sum % 10 );
	}

	/**
	 * Thank you message.
	 */
	public function thankyou_page(): void {
		echo '<p>' . esc_html__( 'Thank you for your order. We will process your payment and confirm your order shortly.', 'secure-offline-cc' ) . '</p>';
	}

	/**
	 * Email instructions for customer.
	 */
	public function email_instructions( $order, bool $sent_to_admin ): void {
		if ( ! $sent_to_admin && $order->get_payment_method() === $this->id && $order->has_status( $this->new_order_status ) ) {
			echo '<p>' . esc_html__( 'Your order is being processed. We will confirm once your payment has been verified.', 'secure-offline-cc' ) . '</p>';
		}
	}

	/**
	 * Email details for admin.
	 */
	public function email_admin_details( $order, bool $sent_to_admin ): void {
		if ( $sent_to_admin && $order->get_payment_method() === $this->id ) {
			$last4 = $order->get_meta( '_socc_last4' );
			$type  = $order->get_meta( '_socc_type' );
			if ( $last4 ) {
				echo '<p><strong>' . esc_html__( 'Card details on file:', 'secure-offline-cc' ) . '</strong> ' . esc_html( $type ) . ' ending in ' . esc_html( $last4 ) . '</p>';
			}
		}
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices(): void {
		if ( 'yes' !== $this->enabled ) {
			return;
		}
		if ( empty( $this->email_address ) ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Secure Offline CC: Please set a notification email address in the gateway settings.', 'secure-offline-cc' ) .
				'</p></div>';
		}
		if ( 'yes' === $this->testmode ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'Secure Offline CC: Test mode is active. Disable before going live.', 'secure-offline-cc' ) .
				'</p></div>';
		}
	}
}

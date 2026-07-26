<?php
/**
 * Class SOCC_Admin
 * Handles admin meta boxes, asset enqueuing, and AJAX endpoints.
 */

defined( 'ABSPATH' ) || exit;

class SOCC_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_boxes' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );

		// AJAX handlers
		add_action( 'wp_ajax_socc_view_details', [ self::class, 'ajax_view_details' ] );
		add_action( 'wp_ajax_socc_purge_details', [ self::class, 'ajax_purge_details' ] );
	}

	/**
	 * Register Card Details meta box for WooCommerce Orders.
	 */
	public static function register_meta_boxes(): void {
		$screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];
		foreach ( $screens as $screen ) {
			add_meta_box(
				'socc_card_details',
				__( 'Card Details (DiR)', 'secure-offline-cc' ),
				[ self::class, 'render_meta_box' ],
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Enqueue styles and scripts on order screens.
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public static function enqueue_admin_assets( $hook_suffix ): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( 'shop_order' === $screen->post_type || 'woocommerce_page_wc-orders' === $screen->id ) {
			wp_enqueue_style(
				'socc-admin-order-css',
				SOCC_URL . 'assets/css/admin-order.css',
				[],
				SOCC_VERSION
			);

			wp_enqueue_script(
				'socc-admin-order-js',
				SOCC_URL . 'assets/js/admin-order.js',
				[ 'jquery' ],
				SOCC_VERSION,
				true
			);

			wp_localize_script(
				'socc-admin-order-js',
				'socc_params',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'socc_admin_action' ),
					'strings'  => [
						'confirm_purge' => __( 'Are you sure you want to permanently purge this card data? This action cannot be undone.', 'secure-offline-cc' ),
						'purged_label'  => __( 'Card data purged', 'secure-offline-cc' ),
						'decrypt_error' => __( 'Failed to decrypt card details.', 'secure-offline-cc' ),
						'purge_error'   => __( 'Failed to purge card data.', 'secure-offline-cc' ),
						'timer_prefix'  => __( 'Clearing in ', 'secure-offline-cc' ),
						'seconds'       => __( 's', 'secure-offline-cc' ),
					],
				]
			);
		}
	}

	/**
	 * Render the Card Details meta box content.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post or Order object.
	 */
	public static function render_meta_box( $post_or_order ): void {
		$order = ( $post_or_order instanceof \WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order ) {
			return;
		}

		$order_id = $order->get_id();

		$ciphertext = $order->get_meta( '_socc_encrypted' );
		$last4      = $order->get_meta( '_socc_last4' );
		$type       = $order->get_meta( '_socc_type' );
		$stored_at  = $order->get_meta( '_socc_stored_at' );
		$purged_at  = $order->get_meta( '_socc_purged_at' );

		echo '<div class="socc-meta-box-content" data-order-id="' . esc_attr( $order_id ) . '">';

		if ( ! empty( $ciphertext ) ) {
			$stored_date = ! empty( $stored_at ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $stored_at ) ) : __( 'Unknown', 'secure-offline-cc' );
			?>
			<table class="socc-details-table" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
				<tr>
					<td style="padding: 5px 0; font-weight: bold;"><?php esc_html_e( 'Card Type:', 'secure-offline-cc' ); ?></td>
					<td style="padding: 5px 0;"><?php echo esc_html( $type ? $type : __( 'Unknown', 'secure-offline-cc' ) ); ?></td>
				</tr>
				<tr>
					<td style="padding: 5px 0; font-weight: bold;"><?php esc_html_e( 'Last 4 Digits:', 'secure-offline-cc' ); ?></td>
					<td style="padding: 5px 0;">xxxx xxxx xxxx <?php echo esc_html( $last4 ); ?></td>
				</tr>
				<tr>
					<td style="padding: 5px 0; font-weight: bold;"><?php esc_html_e( 'Stored At:', 'secure-offline-cc' ); ?></td>
					<td style="padding: 5px 0;"><?php echo esc_html( $stored_date ); ?></td>
				</tr>
			</table>

			<div class="socc-actions" style="margin-bottom: 15px;">
				<button type="button" class="button button-primary socc-view-btn" id="socc-view-details"><?php esc_html_e( 'View Card Details', 'secure-offline-cc' ); ?></button>
				<button type="button" class="button socc-purge-btn" id="socc-purge-details" style="color: #b32d2e; border-color: #b32d2e;"><?php esc_html_e( 'Purge Card Data', 'secure-offline-cc' ); ?></button>
			</div>
			<?php
		} else {
			if ( ! empty( $purged_at ) ) {
				$purged_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $purged_at ) );
				echo '<p class="socc-purged-status" style="color: #b32d2e; font-weight: bold;">' . sprintf( esc_html__( 'Card data purged on %s', 'secure-offline-cc' ), esc_html( $purged_date ) ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'No card details on file for this order.', 'secure-offline-cc' ) . '</p>';
			}
		}

		// Audit logs
		$logs = SOCC_Audit::get_logs( $order_id, 5 );
		?>
		<hr style="border: 0; border-top: 1px solid #ccc; margin: 15px 0;" />
		<h4><?php esc_html_e( 'Recent Audit Log (DiR)', 'secure-offline-cc' ); ?></h4>
		<?php if ( ! empty( $logs ) ) : ?>
			<ul class="socc-audit-list" style="padding-left: 15px; margin: 0; font-size: 11px; line-height: 1.4;">
				<?php
				foreach ( $logs as $log ) :
					$log_date     = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) );
					$action_label = ( 'viewed' === $log->action ) ? __( 'Viewed', 'secure-offline-cc' ) : ( ( 'purged' === $log->action ) ? __( 'Purged', 'secure-offline-cc' ) : esc_html( $log->action ) );
					?>
					<li style="margin-bottom: 8px;">
						<strong><?php echo esc_html( $log_date ); ?></strong> - <?php echo esc_html( $action_label ); ?><br />
						<span style="color: #666;">
							<?php echo sprintf( esc_html__( 'By %1$s (ID: %2$d) from IP %3$s', 'secure-offline-cc' ), esc_html( $log->username ), (int) $log->user_id, esc_html( $log->ip_address ) ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p style="font-size: 11px; color: #666; font-style: italic;"><?php esc_html_e( 'No access history logged.', 'secure-offline-cc' ); ?></p>
		<?php endif; ?>

		<!-- Modal Container -->
		<div id="socc-modal" class="socc-modal-overlay" style="display:none;">
			<div class="socc-modal-content">
				<span class="socc-modal-close">&times;</span>
				<h3><?php esc_html_e( 'Decrypted Card Details', 'secure-offline-cc' ); ?></h3>
				<div id="socc-modal-data"></div>
				<div class="socc-modal-timer" style="margin-top: 15px; font-weight: bold; color: #b32d2e;">
					<span id="socc-countdown-num">60</span> <?php esc_html_e( 'seconds remaining before auto-clear.', 'secure-offline-cc' ); ?>
				</div>
			</div>
		</div>
		<?php
		echo '</div>';
	}

	/**
	 * AJAX handler for viewing (decrypting) card details.
	 */
	public static function ajax_view_details(): void {
		check_ajax_referer( 'socc_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'You do not have permission to view card details.', 'secure-offline-cc' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( ! $order_id ) {
			wp_send_json_error( __( 'Invalid Order ID.', 'secure-offline-cc' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Order not found.', 'secure-offline-cc' ) );
		}

		$ciphertext = $order->get_meta( '_socc_encrypted' );
		$iv         = $order->get_meta( '_socc_iv' );
		$tag        = $order->get_meta( '_socc_tag' );

		$user     = wp_get_current_user();
		$user_id  = $user->ID;
		$username = $user->user_login;
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : '0.0.0.0';
		if ( ! $ip ) {
			$ip = '0.0.0.0';
		}

		if ( empty( $ciphertext ) || empty( $iv ) || empty( $tag ) ) {
			wp_send_json_error( __( 'Card details are empty or have already been purged.', 'secure-offline-cc' ) );
		}

		// Decrypt
		$decrypted = SOCC_Crypto::decrypt( $ciphertext, $iv, $tag );

		if ( false === $decrypted ) {
			// Decrypt failed (GCM authentication failed or bad key)
			SOCC_Audit::log( $order_id, $user_id, $username, 'view_failed', $ip );
			wp_send_json_error( __( 'Decryption failed. The auth tag is invalid or the encryption key is incorrect.', 'secure-offline-cc' ) );
		}

		// Decrypt success! Log it first
		SOCC_Audit::log( $order_id, $user_id, $username, 'viewed', $ip );

		$card_data = json_decode( $decrypted, true );
		if ( ! is_array( $card_data ) ) {
			wp_send_json_error( __( 'Failed to decode decrypted card data JSON.', 'secure-offline-cc' ) );
		}

		wp_send_json_success( [
			'number' => $card_data['number'] ?? '',
			'expiry' => $card_data['expiry'] ?? '',
			'cvv'    => $card_data['cvv'] ?? '',
			'holder' => $card_data['holder'] ?? '',
		] );
	}

	/**
	 * AJAX handler for purging card details.
	 */
	public static function ajax_purge_details(): void {
		check_ajax_referer( 'socc_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'You do not have permission to purge card details.', 'secure-offline-cc' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		if ( ! $order_id ) {
			wp_send_json_error( __( 'Invalid Order ID.', 'secure-offline-cc' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Order not found.', 'secure-offline-cc' ) );
		}

		$user     = wp_get_current_user();
		$user_id  = $user->ID;
		$username = $user->user_login;
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : '0.0.0.0';
		if ( ! $ip ) {
			$ip = '0.0.0.0';
		}

		// Log the purge
		SOCC_Audit::log( $order_id, $user_id, $username, 'purged', $ip );

		// Delete meta keys
		$order->delete_meta_data( '_socc_encrypted' );
		$order->delete_meta_data( '_socc_iv' );
		$order->delete_meta_data( '_socc_tag' );
		$order->delete_meta_data( '_socc_stored_at' );
		$order->delete_meta_data( '_socc_last4' );
		$order->delete_meta_data( '_socc_type' );

		$purged_at_time = current_time( 'mysql' );
		$order->update_meta_data( '_socc_purged_at', $purged_at_time );
		$order->save();

		$purged_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $purged_at_time ) );

		wp_send_json_success( [
			'message' => sprintf( __( 'Card data purged on %s', 'secure-offline-cc' ), $purged_date ),
		] );
	}
}

// Initialize Admin
SOCC_Admin::init();

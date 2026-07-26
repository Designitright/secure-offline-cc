<?php
/**
 * Class SOCC_Audit
 * Handles DB audit table creation, logging, and retrieval.
 */

defined( 'ABSPATH' ) || exit;

class SOCC_Audit {

	/**
	 * Create custom audit log table on activation.
	 */
	public static function create_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'socc_audit_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			username varchar(100) NOT NULL,
			action varchar(50) NOT NULL,
			ip_address varchar(100) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert new log entry.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $user_id User ID.
	 * @param string $username Username.
	 * @param string $action 'viewed' or 'purged'.
	 * @param string $ip_address User's IP address.
	 * @return int|false Number of rows inserted or false.
	 */
	public static function log( int $order_id, int $user_id, string $username, string $action, string $ip_address ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'socc_audit_log';
		return $wpdb->insert(
			$table_name,
			[
				'order_id'   => $order_id,
				'user_id'    => $user_id,
				'username'   => $username,
				'action'     => $action,
				'ip_address' => $ip_address,
				'created_at' => current_time( 'mysql' ),
			],
			[
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s'
			]
		);
	}

	/**
	 * Retrieve last N audit logs for an order.
	 *
	 * @param int $order_id Order ID.
	 * @param int $limit Maximum number of results.
	 * @return array Array of log objects.
	 */
	public static function get_logs( int $order_id, int $limit = 5 ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'socc_audit_log';
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id, user_id, username, action, ip_address, created_at FROM $table_name WHERE order_id = %d ORDER BY created_at DESC LIMIT %d",
				$order_id,
				$limit
			)
		);
		return is_array( $results ) ? $results : [];
	}
}

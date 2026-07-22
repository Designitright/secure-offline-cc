<?php
/**
 * Class SOCC_Crypto
 * Handles secure AES-256-GCM encryption and decryption.
 */

defined( 'ABSPATH' ) || exit;

class SOCC_Crypto {

	/**
	 * Get the 32-byte encryption key.
	 *
	 * @return string Binary key.
	 */
	public static function get_encryption_key(): string {
		if ( defined( 'SOCC_ENCRYPTION_KEY' ) && ! empty( SOCC_ENCRYPTION_KEY ) ) {
			if ( 64 === strlen( SOCC_ENCRYPTION_KEY ) && ctype_xdigit( SOCC_ENCRYPTION_KEY ) ) {
				return (string) hex2bin( SOCC_ENCRYPTION_KEY );
			}
			return hash( 'sha256', SOCC_ENCRYPTION_KEY, true );
		}

		// Fallback key derived from SECURE_AUTH_KEY + site URL hash
		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'socc_secure_fallback';
		$url  = get_site_url();
		return hash( 'sha256', $salt . $url, true );
	}

	/**
	 * Encrypt plaintext using AES-256-GCM.
	 *
	 * @param string $plaintext Data to encrypt.
	 * @return array|false Ciphertext, IV, and tag, or false on failure.
	 */
	public static function encrypt( string $plaintext ) {
		$key    = self::get_encryption_key();
		$iv_len = openssl_cipher_iv_length( 'aes-256-gcm' );
		if ( ! $iv_len ) {
			return false;
		}

		try {
			$iv = random_bytes( $iv_len );
		} catch ( \Exception $e ) {
			$iv = openssl_random_pseudo_bytes( $iv_len );
		}

		$tag       = '';
		$encrypted = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $encrypted ) {
			return false;
		}

		return [
			'ciphertext' => base64_encode( $encrypted ),
			'iv'         => base64_encode( $iv ),
			'tag'        => base64_encode( $tag ),
		];
	}

	/**
	 * Decrypt data using AES-256-GCM.
	 *
	 * @param string $ciphertext Base64 encoded ciphertext.
	 * @param string $iv Base64 encoded IV.
	 * @param string $tag Base64 encoded tag.
	 * @return string|false Decrypted plaintext, or false on failure/verification failure.
	 */
	public static function decrypt( string $ciphertext, string $iv, string $tag ) {
		$key         = self::get_encryption_key();
		$encrypted   = base64_decode( $ciphertext );
		$iv_decoded  = base64_decode( $iv );
		$tag_decoded = base64_decode( $tag );

		$decrypted = openssl_decrypt( $encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv_decoded, $tag_decoded );

		return $decrypted;
	}
}

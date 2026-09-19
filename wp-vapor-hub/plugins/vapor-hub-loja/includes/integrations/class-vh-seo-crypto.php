<?php
/**
 * Criptografia simétrica para segredos SEO (tokens OAuth, API keys).
 *
 * Usa AES-256-CBC com chave derivada dos salts do WordPress. Segredos nunca
 * trafegam em texto plano pela REST — apenas indicadores booleanos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_SEO_Crypto {

	/**
	 * Criptografa uma string.
	 */
	public static function criptografar( string $texto ): string {
		if ( '' === $texto ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $texto ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$chave = self::chave();
		$iv    = random_bytes( 16 );
		$enc   = openssl_encrypt( $texto, 'AES-256-CBC', $chave, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return '';
		}

		return base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Descriptografa uma string previamente criptografada.
	 */
	public static function descriptografar( string $payload ): string {
		if ( '' === $payload ) {
			return '';
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			$dec = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return is_string( $dec ) ? $dec : '';
		}

		$raw = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! is_string( $raw ) || strlen( $raw ) < 17 ) {
			return '';
		}

		$iv  = substr( $raw, 0, 16 );
		$enc = substr( $raw, 16 );
		$dec = openssl_decrypt( $enc, 'AES-256-CBC', self::chave(), OPENSSL_RAW_DATA, $iv );

		return is_string( $dec ) ? $dec : '';
	}

	/**
	 * Deriva chave binária a partir dos salts do wp-config.
	 */
	private static function chave(): string {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'pa' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'seo' );
		return hash( 'sha256', $material, true );
	}
}

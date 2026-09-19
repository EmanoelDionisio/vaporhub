<?php
/**
 * Datas e horários — grava UTC, exibe no fuso do WordPress.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_DateTime {

	/**
	 * Timestamp MySQL UTC para persistência (logs, meta, filas).
	 */
	public static function agora_utc(): string {
		return current_time( 'mysql', true );
	}

	/**
	 * Data local (Y-m-d) conforme timezone do WordPress.
	 */
	public static function hoje_local(): string {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Data local N dias atrás (Y-m-d).
	 */
	public static function dias_atras_local( int $dias ): string {
		$ts = time() - ( max( 0, $dias ) * DAY_IN_SECONDS );
		return wp_date( 'Y-m-d', $ts );
	}

	/**
	 * Primeiro dia do mês corrente no fuso local.
	 */
	public static function inicio_mes_local(): string {
		return wp_date( 'Y-m-01' );
	}

	/**
	 * Converte valor persistido (UTC MySQL, ISO 8601 ou legado local) em timestamp Unix.
	 *
	 * @return int|false
	 */
	public static function para_timestamp( string $valor ) {
		$valor = trim( $valor );
		if ( '' === $valor ) {
			return false;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T/', $valor ) ) {
			$ts = strtotime( $valor );
			if ( false !== $ts ) {
				return $ts;
			}
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor ) ) {
			$ts = strtotime( $valor . ' UTC' );
			if ( false !== $ts ) {
				return $ts;
			}
		}

		return strtotime( $valor );
	}

	/**
	 * Formata para exibição no fuso configurado no WordPress.
	 */
	public static function formatar( string $valor, string $formato = 'd/m/Y H:i:s' ): string {
		if ( '' === trim( $valor ) ) {
			return '—';
		}

		$ts = self::para_timestamp( $valor );
		if ( false === $ts ) {
			return $valor;
		}

		return wp_date( $formato, $ts );
	}

	/**
	 * Atalho para listas (sem segundos).
	 */
	public static function formatar_curto( string $valor ): string {
		return self::formatar( $valor, 'd/m/Y H:i' );
	}
}

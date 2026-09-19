<?php
/**
 * PageSpeed Insights — Core Web Vitals (cache 24h, sob demanda).
 *
 * Usa API key simples (sem OAuth). Não impacta o site público — roda só no painel.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_SEO_PageSpeed {

	private const CACHE_KEY = 'vh_seo_pagespeed';
	private const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Métricas mobile da URL informada (padrão: home).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function analisar( ?string $url = null, bool $forcar = false ) {
		$api_key = VH_SEO_Google::pagespeed_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'vh_seo_psi_key',
				__( 'Informe a chave da API PageSpeed Insights e salve antes de analisar.', 'vapor-hub-loja' )
			);
		}

		$url = $url ? esc_url_raw( $url ) : home_url( '/' );
		$cache_key = self::CACHE_KEY . '_' . md5( $url );

		if ( ! $forcar ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$endpoint = add_query_arg(
			[
				'url'      => $url,
				'key'      => $api_key,
				'strategy' => 'mobile',
				'category' => 'performance',
			],
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
		);

		$resp = wp_remote_get( $endpoint, [ 'timeout' => 30 ] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code >= 400 || ! is_array( $body ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: __( 'Erro ao consultar PageSpeed Insights.', 'vapor-hub-loja' );
			return new WP_Error( 'vh_seo_psi', $msg );
		}

		$lh    = $body['lighthouseResult'] ?? [];
		$audit = $lh['audits'] ?? [];
		$cats  = $lh['categories'] ?? [];

		$dados = [
			'url'        => $url,
			'strategy'   => 'mobile',
			'score'      => isset( $cats['performance']['score'] ) ? (int) round( $cats['performance']['score'] * 100 ) : null,
			'lcp_ms'     => self::metrica_audit( $audit, 'largest-contentful-paint' ),
			'cls'        => self::metrica_audit( $audit, 'cumulative-layout-shift' ),
			'inp_ms'     => self::metrica_audit( $audit, 'interaction-to-next-paint' )
				?? self::metrica_audit( $audit, 'experimental-interaction-to-next-paint' ),
			'fcp_ms'     => self::metrica_audit( $audit, 'first-contentful-paint' ),
			'tbt_ms'     => self::metrica_audit( $audit, 'total-blocking-time' ),
			'atualizado' => gmdate( 'c' ),
		];

		set_transient( $cache_key, $dados, self::CACHE_TTL );
		return $dados;
	}

	/**
	 * @param array<string, mixed> $audits
	 */
	private static function metrica_audit( array $audits, string $id ): ?float {
		if ( empty( $audits[ $id ]['numericValue'] ) ) {
			return null;
		}
		return round( (float) $audits[ $id ]['numericValue'], 2 );
	}
}

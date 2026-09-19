<?php
/**
 * Google Analytics 4 — resumo via Data API (cache 1h).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_SEO_Analytics {

	private const CACHE_KEY = 'vh_seo_ga_resumo';
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Sessões e usuários (últimos 28 dias).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function resumo() {
		if ( ! VH_SEO_Google::conectado() ) {
			return new WP_Error( 'vh_seo_ga_off', __( 'Conecte ao Google para ver dados do Analytics.', 'vapor-hub-loja' ) );
		}

		$seo        = VH_Settings::obter( 'vh_seo', VH_Settings::seo_padrao() );
		$property_id = preg_replace( '/[^0-9]/', '', (string) ( $seo['ga4_property_id'] ?? '' ) );
		if ( '' === $property_id ) {
			return new WP_Error(
				'vh_seo_ga_property',
				__( 'Informe o ID numérico da propriedade GA4 (Admin → Detalhes da propriedade).', 'vapor-hub-loja' )
			);
		}

		$cache_key = self::CACHE_KEY . '_' . $property_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$body = VH_SEO_Google::requisicao(
			'https://analyticsdata.googleapis.com/v1beta/properties/' . $property_id . ':runReport',
			[
				'method'  => 'POST',
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode(
					[
						'dateRanges' => [
							[ 'startDate' => '28daysAgo', 'endDate' => 'today' ],
						],
						'metrics'    => [
							[ 'name' => 'sessions' ],
							[ 'name' => 'totalUsers' ],
							[ 'name' => 'screenPageViews' ],
						],
					]
				),
			]
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$vals = [];
		foreach ( $body['rows'][0]['metricValues'] ?? [] as $i => $mv ) {
			$nome = $body['metricHeaders'][ $i ]['name'] ?? (string) $i;
			$vals[ $nome ] = (int) ( $mv['value'] ?? 0 );
		}

		$dados = [
			'property_id' => $property_id,
			'sessoes'     => $vals['sessions'] ?? 0,
			'usuarios'    => $vals['totalUsers'] ?? 0,
			'pageviews'   => $vals['screenPageViews'] ?? 0,
			'periodo'     => __( 'Últimos 28 dias', 'vapor-hub-loja' ),
			'atualizado'  => gmdate( 'c' ),
		];

		set_transient( $cache_key, $dados, self::CACHE_TTL );
		return $dados;
	}
}

<?php
/**
 * Google Search Console — resumo de performance orgânica (cache 1h).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_SEO_SearchConsole {

	private const CACHE_KEY = 'vh_seo_gsc_resumo';
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Cliques, impressões, CTR e posição média (últimos 28 dias).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function resumo() {
		if ( ! VH_SEO_Google::conectado() ) {
			return new WP_Error( 'vh_seo_gsc_off', __( 'Conecte ao Google para ver dados do Search Console.', 'vapor-hub-loja' ) );
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$site = self::resolver_site();
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$fim   = gmdate( 'Y-m-d' );
		$inicio = gmdate( 'Y-m-d', strtotime( '-28 days' ) );

		$body = VH_SEO_Google::requisicao(
			'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query',
			[
				'method' => 'POST',
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode(
					[
						'startDate'  => $inicio,
						'endDate'    => $fim,
						'dimensions' => [],
						'rowLimit'   => 1,
					]
				),
			]
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$row = $body['rows'][0] ?? [];
		$dados = [
			'site'        => $site,
			'periodo'     => $inicio . ' — ' . $fim,
			'cliques'     => (int) round( $row['clicks'] ?? 0 ),
			'impressoes'  => (int) round( $row['impressions'] ?? 0 ),
			'ctr'         => round( (float) ( $row['ctr'] ?? 0 ) * 100, 2 ),
			'posicao'     => round( (float) ( $row['position'] ?? 0 ), 1 ),
			'atualizado'  => gmdate( 'c' ),
		];

		set_transient( self::CACHE_KEY, $dados, self::CACHE_TTL );
		return $dados;
	}

	/**
	 * Encontra a propriedade GSC que corresponde a este site.
	 *
	 * @return string|WP_Error URL da propriedade no formato exigido pela API.
	 */
	private static function resolver_site() {
		$lista = VH_SEO_Google::requisicao( 'https://www.googleapis.com/webmasters/v3/sites' );
		if ( is_wp_error( $lista ) ) {
			return $lista;
		}

		$entries = $lista['siteEntry'] ?? [];
		if ( empty( $entries ) ) {
			return new WP_Error(
				'vh_seo_gsc_vazio',
				__( 'Nenhuma propriedade encontrada no Search Console para esta conta.', 'vapor-hub-loja' )
			);
		}

		$home     = trailingslashit( home_url( '/' ) );
		$host     = wp_parse_url( $home, PHP_URL_HOST );
		$candidatos = [
			$home,
			untrailingslashit( $home ),
			'sc-domain:' . $host,
		];

		foreach ( $entries as $entry ) {
			$url = (string) ( $entry['siteUrl'] ?? '' );
			if ( in_array( $url, $candidatos, true ) ) {
				return $url;
			}
		}

		/* Fallback: primeira propriedade com permissão de leitura. */
		foreach ( $entries as $entry ) {
			$perm = $entry['permissionLevel'] ?? '';
			if ( in_array( $perm, [ 'siteOwner', 'siteFullUser', 'siteRestrictedUser' ], true ) ) {
				return (string) $entry['siteUrl'];
			}
		}

		return new WP_Error(
			'vh_seo_gsc_match',
			__( 'Este domínio não foi encontrado nas propriedades do Search Console. Adicione-o lá primeiro.', 'vapor-hub-loja' )
		);
	}
}

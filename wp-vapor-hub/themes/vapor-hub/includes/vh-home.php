<?php
/**
 * Página inicial: textos do painel e as duas listas de produto.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Textos e interruptores da home, já com o padrão quando nada foi gravado.
 *
 * @return array<string,string>
 */
function vh_home_config(): array {
	$padrao = class_exists( 'VH_Settings' ) ? VH_Settings::home_padrao() : array();
	$salvo  = get_option( 'vh_home', array() );
	if ( ! is_array( $salvo ) ) {
		$salvo = array();
	}

	$config = wp_parse_args( $salvo, $padrao );
	foreach ( array( 'destaques_mostrar', 'categorias_mostrar', 'oferta_mostrar', 'vendidos_mostrar', 'chamada_mostrar' ) as $chave ) {
		$config[ $chave ] = ! empty( $config[ $chave ] ) && '0' !== (string) $config[ $chave ] ? '1' : '0';
	}

	return $config;
}

function vh_home_bloco_ligado( array $config, string $chave ): bool {
	return isset( $config[ $chave ] ) && '1' === (string) $config[ $chave ];
}

/**
 * Até 8 produtos marcados como destaque e visíveis no catálogo.
 *
 * @return int[]
 */
function vh_home_ids_destaque(): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$ids = wc_get_products(
		array(
			'status'     => 'publish',
			'limit'      => 8,
			'featured'   => true,
			'visibility' => 'catalog',
			'return'     => 'ids',
		)
	);

	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

/**
 * Até 8 produtos que já venderam, do mais vendido para o menos.
 *
 * @return int[]
 */
function vh_home_ids_vendidos(): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$ids = wc_get_products(
		array(
			'status'     => 'publish',
			'limit'      => 8,
			'orderby'    => 'popularity',
			'order'      => 'DESC',
			'visibility' => 'catalog',
			'return'     => 'ids',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'total_sales',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

/**
 * Desenha uma fileira. Sem produtos, não sai nada.
 *
 * @param int[] $ids
 */
function vh_home_render_fileira( array $ids, string $rotulo, string $titulo, string $ancora ): void {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids ) {
		return;
	}

	get_template_part(
		'template-parts/content',
		'home-fileira',
		array(
			'ids'    => $ids,
			'rotulo' => $rotulo,
			'titulo' => $titulo,
			'ancora' => $ancora,
		)
	);
}

/**
 * Destino de uma faixa. Sem link, abre a loja; a de ofertas abre já em promoção.
 */
function vh_home_url_bloco( array $config, string $chave, bool $promocao = false ): string {
	$link = isset( $config[ $chave ] ) ? trim( (string) $config[ $chave ] ) : '';
	if ( '' !== $link ) {
		return $link;
	}

	$loja = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	$loja = is_string( $loja ) && '' !== $loja ? $loja : home_url( '/' );

	return $promocao ? add_query_arg( 'vh_promocao', '1', $loja ) : $loja;
}

/**
 * Destino da faixa de ofertas.
 */
function vh_home_url_oferta( array $config ): string {
	return vh_home_url_bloco( $config, 'oferta_link', true );
}

<?php
/**
 * Loop de produtos da loja (compartilhado entre página e AJAX).
 *
 * @package VaporHub
 *
 * @var WC_Product[] $produtos
 * @var int          $colunas
 */

defined( 'ABSPATH' ) || exit;

$produtos       = isset( $args['produtos'] ) && is_array( $args['produtos'] ) ? $args['produtos'] : array();
$colunas        = isset( $args['colunas'] ) ? absint( $args['colunas'] ) : 3;
$max_paginas    = isset( $args['max_paginas'] ) ? (int) $args['max_paginas'] : 1;
$pagina_atual   = isset( $args['pagina_atual'] ) ? (int) $args['pagina_atual'] : 1;
$paginacao_base = isset( $args['paginacao_base'] ) ? (string) $args['paginacao_base'] : '';

if ( empty( $produtos ) ) {
	do_action( 'woocommerce_no_products_found' );
	return;
}

woocommerce_product_loop_start();

$GLOBALS['woocommerce_loop']['columns'] = $colunas;

foreach ( $produtos as $produto ) {
	if ( ! $produto instanceof WC_Product ) {
		continue;
	}

	$post_object = get_post( $produto->get_id() );
	if ( ! $post_object ) {
		continue;
	}

	$GLOBALS['post'] = $post_object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $GLOBALS['post'] );
	do_action( 'woocommerce_shop_loop' );
	wc_get_template_part( 'content', 'product' );
}

wp_reset_postdata();

woocommerce_product_loop_end();

if ( ! empty( $max_paginas ) && $max_paginas > 1 && ! empty( $pagina_atual ) ) {
	$base_pag = ! empty( $paginacao_base ) ? $paginacao_base : remove_query_arg( 'paged' );
	echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $base_pag ) ),
			'format'    => '',
			'current'   => max( 1, (int) $pagina_atual ),
			'total'     => (int) $max_paginas,
			'type'      => 'list',
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		)
	);
}

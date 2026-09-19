<?php
/**
 * Gera variações de amostra para o pod variável do CSV (SKU VH-POD-001).
 *
 * Uso:
 *   wp eval-file wp-vapor-hub/setup/gerar-variacoes.php
 *
 * CSV é só teste. Catálogo de produção: Tiny → loja.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Execute via WP-CLI.' );
}

$sku_pai = 'VH-POD-001';
$pai_id  = (int) wc_get_product_id_by_sku( $sku_pai );
if ( $pai_id <= 0 ) {
	echo "SKU {$sku_pai} não encontrado. Importe setup/produtos-importacao.csv antes.\n";
	exit( 0 );
}

$product = wc_get_product( $pai_id );
if ( ! $product || ! $product->is_type( 'variable' ) ) {
	echo "Produto {$pai_id} ignorado (nao variavel).\n";
	exit( 0 );
}

$existing = get_children(
	[
		'post_parent' => $pai_id,
		'post_type'   => 'product_variation',
		'post_status' => [ 'publish', 'private' ],
		'fields'      => 'ids',
	]
);
if ( ! empty( $existing ) ) {
	echo "Produto {$pai_id} ja possui " . count( $existing ) . " variacoes.\n";
	exit( 0 );
}

$attrs = [
	'pa_sabor'          => [ 'manga-ice', 'morango', 'uva-ice' ],
	'pa_teor_nicotina' => [ '20mg', '50mg' ],
];

$parent_price = (float) $product->get_regular_price();
if ( $parent_price <= 0 ) {
	$parent_price = 89.9;
}

$count = 0;
foreach ( $attrs['pa_sabor'] as $sabor ) {
	foreach ( $attrs['pa_teor_nicotina'] as $nicotina ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $pai_id );
		$variation->set_status( 'publish' );
		$variation->set_attributes(
			[
				'pa_sabor'          => $sabor,
				'pa_teor_nicotina'  => $nicotina,
			]
		);
		$variation->set_regular_price( (string) $parent_price );
		$variation->set_price( (string) $parent_price );
		$variation->set_manage_stock( false );
		$variation->save();
		++$count;
	}
}

WC_Product_Variable::sync( $pai_id );
wc_delete_product_transients( $pai_id );
echo "Produto {$pai_id}: {$count} variacoes de amostra criadas.\n";
echo "Finalizado.\n";

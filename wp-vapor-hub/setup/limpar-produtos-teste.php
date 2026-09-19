<?php
/**
 * Limpeza de teste: mantém apenas produto #27 e variações; zera fila/logs Tiny.
 * Uso remoto: wp eval-file tmp/vapor-hub-setup/limpar-produtos-teste.php
 *
 * @package VaporHubLoja
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

set_time_limit( 600 );

global $wpdb;

$keep_parent = 27;
$keep_ids    = [ $keep_parent ];
$produto     = wc_get_product( $keep_parent );

if ( ! $produto instanceof WC_Product ) {
	WP_CLI::error( 'Produto #27 não encontrado.' );
}

if ( $produto instanceof WC_Product_Variable ) {
	$keep_ids = array_merge( $keep_ids, array_map( 'intval', $produto->get_children() ) );
}

$keep_map = array_fill_keys( $keep_ids, true );

$fila = (int) $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'vh_tiny_fila' );
$logs = (int) $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'vh_tiny_log' );

if ( class_exists( 'VH_Tiny' ) ) {
	$dados = VH_Tiny::obter();
	$dados['permitir_recebimento'] = false;
	$dados['sinc_auto']            = false;
	$dados['ultimo_erro']          = '';
	update_option( VH_Tiny::OPTION, $dados, false );
}

$deleted = 0;
$ids     = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status != 'trash'"
);

foreach ( $ids as $id ) {
	$id = (int) $id;
	if ( isset( $keep_map[ $id ] ) ) {
		continue;
	}
	if ( wp_delete_post( $id, true ) ) {
		++$deleted;
	}
}

$trash_deleted = 0;
$trash_ids     = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status = 'trash'"
);
foreach ( $trash_ids as $tid ) {
	if ( wp_delete_post( (int) $tid, true ) ) {
		++$trash_deleted;
	}
}

if ( class_exists( 'VH_Tiny_Map' ) ) {
	foreach ( $keep_ids as $kid ) {
		delete_post_meta( $kid, VH_Tiny_Map::META_TINY_ID );
		delete_post_meta( $kid, VH_Tiny_Map::META_SYNC_HASH );
		delete_post_meta( $kid, VH_Tiny_Map::META_LAST_SYNC );
		delete_post_meta( $kid, VH_Tiny_Map::META_GUARD );
	}
	delete_post_meta( $keep_parent, VH_Tiny_Map::META_VAR_REMOVIDAS );
}

if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
	wc_update_product_lookup_tables();
}

$restantes = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status != 'trash'"
);

WP_CLI::success(
	sprintf(
		'Limpeza concluída: %d produtos removidos, %d lixo removido, %d itens mantidos (#27 + vars). Fila: %d removidos. Logs: %d removidos. Restantes: %d.',
		$deleted,
		$trash_deleted,
		count( $keep_ids ),
		$fila,
		$logs,
		$restantes
	)
);

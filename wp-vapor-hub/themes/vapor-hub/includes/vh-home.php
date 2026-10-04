<?php
/**
 * Home nativa — vitrines por categoria (referência POD & VAPE, sem slides).
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trilhos da home: slug Woo + textos. Sem produtos na categoria, o trilho some.
 *
 * @return array<int,array<string,string>>
 */
function vh_home_vitrines(): array {
	$vitrines = array(
		array(
			'slug'      => 'pod-descartavel',
			'kicker'    => __( 'POD descartável', 'vapor-hub' ),
			'titulo'    => __( 'Praticidade e sabor', 'vapor-hub' ),
			'descricao' => __( 'Pods originais, faixas de puffs e sabores para o dia a dia — da primeira puxada até o fim da carga.', 'vapor-hub' ),
		),
		array(
			'slug'      => 'e-liquidos',
			'kicker'    => __( 'e-Líquidos', 'vapor-hub' ),
			'titulo'    => __( 'Sabores para o seu vape', 'vapor-hub' ),
			'descricao' => __( 'Free base e nic salt: frutados, ice, mentolados, doces e atabacados das marcas que você já procura.', 'vapor-hub' ),
		),
		array(
			'slug'      => 'pod-recarregavel',
			'kicker'    => __( 'POD recarregável', 'vapor-hub' ),
			'titulo'    => __( 'Aparelhos e performance', 'vapor-hub' ),
			'descricao' => __( 'Kits e sistemas recarregáveis para quem quer mais controle, reposição e duração.', 'vapor-hub' ),
		),
	);

	/**
	 * @param array<int,array<string,string>> $vitrines
	 */
	return apply_filters( 'vh_home_vitrines', $vitrines );
}

/**
 * @param string $slug Slug de product_cat.
 */
function vh_home_categoria_tem_produtos( string $slug ): bool {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return false;
	}
	$termo = get_term_by( 'slug', $slug, 'product_cat' );
	return ( $termo instanceof WP_Term ) && (int) $termo->count > 0;
}

/**
 * @return WP_Term|null
 */
function vh_home_termo_vitrine( string $slug ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return null;
	}
	$termo = get_term_by( 'slug', $slug, 'product_cat' );
	return ( $termo instanceof WP_Term ) ? $termo : null;
}

function vh_home_tem_vitrine_preenchida(): bool {
	foreach ( vh_home_vitrines() as $vitrine ) {
		if ( ! empty( $vitrine['slug'] ) && vh_home_categoria_tem_produtos( (string) $vitrine['slug'] ) ) {
			return true;
		}
	}
	return false;
}

function vh_home_tem_produtos_recentes(): bool {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}
	$q = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	return $q->have_posts();
}

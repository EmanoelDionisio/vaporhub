<?php
/**
 * Prepara o wp-env para os testes E2E.
 *
 * Rodar com:
 *   npx wp-env run cli wp eval-file wp-content/vh-e2e/setup.php
 * (o npm script `e2e:preparar` já faz isso pelo caminho montado).
 *
 * Deixa a loja no estado auditado em produção: WooCommerce em pt-BR, kg/cm,
 * pagamento na entrega ligado, frete determinístico por peso, integração Tiny
 * “conectada” contra o ERP mockado e uma categoria já vinculada.
 *
 * @package VaporHubLoja\Tests\E2E
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit( 1 );
}

WP_CLI::line( 'Preparando ambiente E2E…' );

/* ── Loja ───────────────────────────────────────── */
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'blog_public', 0 );

update_option( 'woocommerce_store_address', 'Rua das Palmeiras' );
update_option( 'woocommerce_store_city', 'Porto Alegre' );
update_option( 'woocommerce_default_country', 'BR:RS' );
update_option( 'woocommerce_store_postcode', '90000-000' );
update_option( 'woocommerce_currency', 'BRL' );
update_option( 'woocommerce_weight_unit', 'kg' );
update_option( 'woocommerce_dimension_unit', 'cm' );
update_option( 'woocommerce_manage_stock', 'yes' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_enable_coupons', 'no' );
update_option( 'woocommerce_allowed_countries', 'specific' );
update_option( 'woocommerce_specific_allowed_countries', [ 'BR' ] );
update_option( 'woocommerce_ship_to_countries', 'specific' );
update_option( 'woocommerce_specific_ship_to_countries', [ 'BR' ] );
/* “Em breve” do WooCommerce cobre a loja com um banner e engole os cliques. */
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_private_link', 'no' );
update_option( 'woocommerce_cart_redirect_after_add', 'no' );
update_option( 'woocommerce_enable_ajax_add_to_cart', 'no' );
update_option( 'woocommerce_terms_page_id', '' );
update_option( 'woocommerce_cod_settings', [
	'enabled'     => 'yes',
	'title'       => 'Pagamento na entrega',
	'description' => 'Pague ao receber.',
] );

if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
}
if ( class_exists( 'WC_Install' ) && method_exists( 'WC_Install', 'create_pages' ) ) {
	WC_Install::create_pages();
}

/*
 * Páginas em shortcode com os slugs de produção. O WooCommerce novo cria
 * carrinho e checkout em blocos; o site roda o clássico, e o teste tem de
 * exercitar o mesmo checkout que o cliente vê.
 */
$paginas = [
	[
		'slug'    => 'loja',
		'titulo'  => 'Loja',
		'conteudo' => '',
		'opcao'   => 'woocommerce_shop_page_id',
	],
	[
		'slug'    => 'carrinho',
		'titulo'  => 'Carrinho',
		'conteudo' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
		'opcao'   => 'woocommerce_cart_page_id',
	],
	[
		'slug'    => 'finalizar-compra',
		'titulo'  => 'Finalizar compra',
		'conteudo' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
		'opcao'   => 'woocommerce_checkout_page_id',
	],
	[
		'slug'    => 'minha-conta',
		'titulo'  => 'Minha conta',
		'conteudo' => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->',
		'opcao'   => 'woocommerce_myaccount_page_id',
	],
];

foreach ( $paginas as $pagina ) {
	$existente = get_page_by_path( $pagina['slug'] );
	$dados     = [
		'post_title'   => $pagina['titulo'],
		'post_name'    => $pagina['slug'],
		'post_content' => $pagina['conteudo'],
		'post_status'  => 'publish',
		'post_type'    => 'page',
	];

	if ( $existente instanceof WP_Post ) {
		$dados['ID'] = $existente->ID;
		$id_pagina   = (int) wp_update_post( $dados );
	} else {
		$id_pagina = (int) wp_insert_post( $dados );
	}

	if ( $id_pagina > 0 ) {
		update_option( $pagina['opcao'], $id_pagina );
	}
}
WP_CLI::line( '· páginas de loja, carrinho e checkout em shortcode' );

/* ── Frete determinístico na zona padrão ────────── */
if ( class_exists( 'WC_Shipping_Zones' ) ) {
	$zona   = new WC_Shipping_Zone( 0 );
	$existe = false;
	foreach ( $zona->get_shipping_methods() as $metodo ) {
		if ( 'vh_e2e_peso' === $metodo->id ) {
			$existe = true;
			break;
		}
	}
	if ( ! $existe ) {
		$zona->add_shipping_method( 'vh_e2e_peso' );
		$zona->save();
		WP_CLI::line( '· frete por peso adicionado à zona padrão' );
	}
}

/* ── Permissões do painel Minha Loja ────────────── */
if ( class_exists( 'VH_Roles' ) ) {
	VH_Roles::criar();
	$admin = get_user_by( 'login', 'admin' );
	if ( $admin instanceof WP_User ) {
		$admin->add_cap( VH_Roles::CAP );
	}
}

/* ── Tabelas da fila e do log ───────────────────── */
if ( class_exists( 'VH_Tiny_Module' ) ) {
	VH_Tiny_Module::instalar();
}

/* ── Integração Tiny apontando para o ERP mockado ─ */
if ( class_exists( 'VH_Tiny' ) && class_exists( 'VH_SEO_Crypto' ) ) {
	update_option(
		VH_Tiny::OPTION,
		[
			'ativo'                 => true,
			'modo'                  => 'v3',
			/* Pedido só entra na fila com a sincronização automática ligada. */
			'sinc_auto'             => true,
			'permitir_envio'        => true,
			'receber_catalogo'      => false,
			'receber_estoque_preco' => false,
			'client_id'             => 'client-e2e',
			'client_secret'         => VH_SEO_Crypto::criptografar( 'segredo-e2e' ),
			'access_token'          => VH_SEO_Crypto::criptografar( 'token-e2e' ),
			'refresh_token'         => VH_SEO_Crypto::criptografar( 'refresh-e2e' ),
			'expires_at'            => time() + 3600,
			'connected_at'          => gmdate( 'c' ),
			'conta_nome'            => 'VAPOR HUB COMERCIO LTDA',
			'conta_cnpj'            => '12345678000199',
			'loja_identificador'    => 'piloto.example',
			'categoria_raiz'        => 'Loja Vapor Hub',
			'categoria_raiz_id'     => 0,
			'sku_prefixo'           => 'VH-',
		],
		false
	);
	VH_Tiny::garantir_webhook_token();
	WP_CLI::line( '· integração Tiny conectada ao ERP mockado' );
}

/* ── Categoria já vinculada, como no piloto ─────── */
$categoria = term_exists( 'POD Descartável', 'product_cat' );
if ( ! $categoria ) {
	$categoria = wp_insert_term( 'POD Descartável', 'product_cat' );
}
$categoria_id = (int) ( is_array( $categoria ) ? $categoria['term_id'] : $categoria );

if ( $categoria_id > 0 && class_exists( 'VH_Tiny_Category_Sync' ) ) {
	$vinculo = get_term_meta( $categoria_id, VH_Tiny_Map::META_TINY_CAT_ID, true );
	if ( ! $vinculo ) {
		$resultado = VH_Tiny_Category_Sync::empurrar_categoria( $categoria_id );
		if ( is_wp_error( $resultado ) ) {
			WP_CLI::warning( 'categoria não vinculada: ' . $resultado->get_error_message() );
		} else {
			WP_CLI::line( '· categoria “POD Descartável” criada no ERP mockado sob a raiz' );
		}
	}
}

/* “Sem categoria” fica marcada como ignorar, como no piloto. */
$padrao = (int) get_option( 'default_product_cat', 0 );
if ( $padrao > 0 && class_exists( 'VH_Tiny_Map' ) ) {
	update_term_meta( $padrao, VH_Tiny_Map::META_TINY_CAT_IGNORAR, 1 );
}

flush_rewrite_rules();

WP_CLI::success( 'Ambiente E2E pronto.' );

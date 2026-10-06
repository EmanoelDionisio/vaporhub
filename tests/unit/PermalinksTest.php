<?php
/**
 * Caminho público do produto: categoria principal, slug e modos do painel.
 *
 * @package VaporHubLoja\Tests
 */

final class PermalinksTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testCaminhoUsaORamoMaisProfundo(): void {
		$raiz  = VH_Test_Env::categoria( 'Pod Descartavel' );
		$folha = VH_Test_Env::categoria( 'Ate 10 000 puffs', 0, $raiz );
		$outro = VH_Test_Env::categoria( 'Acessorios' );

		$produto = new WC_Product();
		$produto->set_name( 'Nome do produto' );
		$produto->set_slug( 'nome-do-produto' );
		$produto->set_category_ids( [ $raiz, $outro, $folha ] );

		self::assertSame(
			'pod-descartavel/ate-10-000-puffs/nome-do-produto',
			VH_Permalinks::caminho_produto( $produto )
		);
		self::assertSame(
			'https://piloto.example/pod-descartavel/ate-10-000-puffs/nome-do-produto/',
			VH_Permalinks::url_publica( $produto )
		);
	}

	public function testCategoriaPrincipalVenceORamoMaisProfundo(): void {
		$raiz  = VH_Test_Env::categoria( 'Pod Descartavel' );
		$folha = VH_Test_Env::categoria( 'Ate 10 000 puffs', 0, $raiz );
		$outro = VH_Test_Env::categoria( 'Acessorios' );

		$produto = new WC_Product();
		$produto->set_slug( 'nome-do-produto' );
		$produto->set_category_ids( [ $folha, $outro ] );
		$produto->update_meta_data( VH_Permalinks::META_PRINCIPAL, $outro );

		self::assertSame( 'acessorios/nome-do-produto', VH_Permalinks::caminho_produto( $produto ) );
	}

	public function testSemCategoriaNaoMontaCaminho(): void {
		$solta   = VH_Test_Env::categoria( 'Uncategorized' );
		$produto = new WC_Product();
		$produto->set_slug( 'avulso' );
		$produto->set_category_ids( [ $solta ] );

		self::assertSame( '', VH_Permalinks::caminho_produto( $produto ) );
	}

	public function testSlugComBarraERecusado(): void {
		$erro = VH_Permalinks::salvar(
			[
				'modo'           => 'personalizada',
				'base_produto'   => 'foo/bar',
				'base_categoria' => 'product-category',
				'base_tag'       => 'product-tag',
				'base_atributo'  => '',
				'base_marca'     => 'marca',
			]
		);

		self::assertTrue( is_wp_error( $erro ) );
		self::assertSame( '', (string) get_option( VH_Permalinks::OPTION_MODO, '' ) );
	}

	public function testCaminhoGravaABaseAntigaENaoEngoleMinhaLoja(): void {
		$ok = VH_Permalinks::salvar(
			[
				'modo'           => 'caminho',
				'base_produto'   => 'product',
				'base_categoria' => 'product-category',
				'base_tag'       => 'product-tag',
				'base_atributo'  => '',
				'base_marca'     => 'marca',
			]
		);

		self::assertTrue( true === $ok );
		$wc = get_option( 'woocommerce_permalinks', [] );
		self::assertTrue( is_array( $wc ) );
		self::assertSame( 'product', $wc['product_base'] );
		self::assertSame( 'product-category', $wc['category_base'] );
		self::assertSame( 'marca', get_option( 'woocommerce_brand_permalink' ) );
		self::assertSame( 'caminho', get_option( VH_Permalinks::OPTION_MODO_CATEGORIA ) );
		self::assertSame( '/%postname%/', get_option( 'permalink_structure' ) );

		$regras = VH_Permalinks::regras( [ 'minha-loja/?$' => 'index.php?vh_secao=dashboard' ] );
		$chaves = array_keys( $regras );
		self::assertSame( 'minha-loja/?$', $chaves[0] );
		self::assertSame( '(.+)/([^/]+)/?$', $chaves[1] );

		$pedido = VH_Permalinks::resolver_pedido(
			[
				'post_type'                 => 'product',
				'name'                      => 'seo',
				VH_Permalinks::QV_CAMINHO   => 'minha-loja',
			]
		);
		self::assertSame( '404', $pedido['error'] );
		self::assertTrue( ! isset( $pedido['post_type'] ) );
	}

	public function testModoDaLojaNaoCriaARegraDoCaminho(): void {
		update_option( VH_Permalinks::OPTION_MODO, 'personalizada', false );

		$regras = VH_Permalinks::regras( [ 'pagina/?$' => 'index.php?pagename=pagina' ] );

		self::assertTrue( ! isset( $regras['(.+)/([^/]+)/?$'] ) );
		self::assertSame( 'pagina/?$', array_key_first( $regras ) );
	}

	public function testCategoriaPublicaATrilhaSemPrefixo(): void {
		$raiz = VH_Test_Env::categoria( 'E Liquidos' );
		$folha = VH_Test_Env::categoria( 'Nicsalt', 0, $raiz );

		$termo = get_term( $folha, 'product_cat' );
		self::assertInstanceOf( WP_Term::class, $termo );
		self::assertSame( 'e-liquidos/nicsalt', VH_Permalinks::caminho_categoria( $termo ) );
		self::assertSame( 'https://piloto.example/e-liquidos/nicsalt/', VH_Permalinks::url_categoria( $termo ) );
		$achada = VH_Permalinks::categoria_por_caminho( 'e-liquidos/nicsalt' );
		self::assertInstanceOf( WP_Term::class, $achada );
		self::assertSame( $folha, $achada->term_id );

		$_SERVER['REQUEST_URI'] = '/e-liquidos/nicsalt/';
		$pedido = VH_Permalinks::resolver_pedido(
			[
				'post_type'               => 'product',
				'name'                    => 'nicsalt',
				VH_Permalinks::QV_CAMINHO => 'e-liquidos',
			]
		);
		unset( $_SERVER['REQUEST_URI'] );

		self::assertSame( 'nicsalt', $pedido['product_cat'] );
		self::assertTrue( ! isset( $pedido['post_type'] ) );
	}

	public function testCategoriaNaBaseNaoTrocaOLink(): void {
		update_option( VH_Permalinks::OPTION_MODO_CATEGORIA, 'base', false );
		$raiz  = VH_Test_Env::categoria( 'E Liquidos' );
		$termo = get_term( $raiz, 'product_cat' );

		self::assertSame(
			'https://piloto.example/product-category/e-liquidos/',
			VH_Permalinks::filtrar_link_categoria( 'https://piloto.example/product-category/e-liquidos/', $termo, 'product_cat' )
		);
	}
}

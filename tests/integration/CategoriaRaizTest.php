<?php
/**
 * Fase 5 — a árvore da loja mora sob uma raiz no ERP.
 *
 * A conta do Tiny é compartilhada com outras operações: as categorias da loja
 * precisam nascer dentro de “Loja Vapor Hub” para não se misturarem com as
 * de fora. E “Sem categoria”, que o WooCommerce cria sozinho, não é categoria
 * de verdade — não pode virar cadastro no ERP nem travar o envio do produto.
 *
 * @package VaporHubLoja\Tests
 */

final class CategoriaRaizTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	/**
	 * Categorias criadas no ERP falso, na ordem em que o POST saiu.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function categorias_criadas(): array {
		$saida = [];
		foreach ( VH_Fake_HTTP::requisicoes( 'POST', 'categorias' ) as $req ) {
			$saida[] = (array) $req['body'];
		}
		return $saida;
	}

	public function testPrimeiraCategoriaCriaARaizEPenduraNela(): void {
		$term_id = VH_Test_Env::categoria( 'EVA' );

		self::assertTrue( true === VH_Tiny_Category_Sync::empurrar_categoria( $term_id ) );

		$criadas = $this->categorias_criadas();
		self::assertCount( 2, $criadas, 'Raiz e categoria.' );
		self::assertSame( 'Loja Vapor Hub', $criadas[0]['descricao'] );
		self::assertSame( 'EVA', $criadas[1]['descricao'] );
		self::assertTrue( (int) $criadas[1]['idCategoriaPai'] > 0, 'A categoria da loja entra dentro da raiz.' );
	}

	public function testSegundaCategoriaReaproveitaARaizJaCriada(): void {
		VH_Tiny_Category_Sync::empurrar_categoria( VH_Test_Env::categoria( 'EVA' ) );
		VH_Tiny_Category_Sync::empurrar_categoria( VH_Test_Env::categoria( 'Chumbadas' ) );

		$criadas = $this->categorias_criadas();
		$raizes  = array_filter( $criadas, static fn( array $c ): bool => 'Loja Vapor Hub' === ( $c['descricao'] ?? '' ) );

		self::assertCount( 1, $raizes, 'A raiz é criada uma única vez.' );
		self::assertSame( $criadas[1]['idCategoriaPai'], $criadas[2]['idCategoriaPai'] );
	}

	public function testRaizQueJaExisteNoErpEAdotadaEmVezDeDuplicada(): void {
		$raiz_id = VH_Fake_Tiny_ERP::seed_categoria( 'Loja Vapor Hub' );

		VH_Tiny_Category_Sync::empurrar_categoria( VH_Test_Env::categoria( 'EVA' ) );

		$criadas = $this->categorias_criadas();
		self::assertCount( 1, $criadas, 'Só a categoria nova.' );
		self::assertSame( $raiz_id, (int) $criadas[0]['idCategoriaPai'] );
	}

	public function testRaizVaziaNaConfiguracaoMantemCategoriaNoTopo(): void {
		VH_Test_Env::atualizar_config( [ 'categoria_raiz' => '' ] );

		VH_Tiny_Category_Sync::empurrar_categoria( VH_Test_Env::categoria( 'EVA' ) );

		$criadas = $this->categorias_criadas();
		self::assertCount( 1, $criadas );
		self::assertArrayNotHasKey( 'idCategoriaPai', $criadas[0] );
	}

	public function testSubcategoriaContinuaPenduradaNoPaiDaLoja(): void {
		$pai   = VH_Test_Env::categoria( 'EVA' );
		$filha = VH_Test_Env::categoria( 'EVA Grande', 0, $pai );

		VH_Tiny_Category_Sync::empurrar_categoria( $filha );

		$pai_tiny = (int) get_term_meta( $pai, VH_Tiny_Map::META_TINY_CAT_ID, true );
		$criadas  = $this->categorias_criadas();

		self::assertSame( 'EVA Grande', $criadas[2]['descricao'] );
		self::assertSame( $pai_tiny, (int) $criadas[2]['idCategoriaPai'] );
	}

	public function testSemCategoriaEIgnoradaEmVezDeIrAoErp(): void {
		$term_id = VH_Test_Env::categoria( 'Sem categoria' );
		update_option( 'default_product_cat', $term_id, false );

		self::assertTrue( true === VH_Tiny_Category_Sync::empurrar_categoria( $term_id ) );

		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'categorias' ) );
		self::assertTrue( (bool) get_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR, true ) );
	}

	public function testProdutoEmSemCategoriaContinuaSendoEnviado(): void {
		$sem_cat = VH_Test_Env::categoria( 'Sem categoria' );
		update_option( 'default_product_cat', $sem_cat, false );
		VH_Tiny_Category_Sync::empurrar_categoria( $sem_cat );

		$produto = VH_Test_Env::produto_simples();
		$produto->set_category_ids( [ $sem_cat ] );
		$produto->save();

		self::assertTrue( true === VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() ) );
		self::assertArrayNotHasKey( 'categoria', VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' ) );
	}

	public function testCategoriaSemVinculoAindaTravaOEnvioDoProduto(): void {
		$cat     = VH_Test_Env::categoria( 'EVA' );
		$produto = VH_Test_Env::produto_simples();
		$produto->set_category_ids( [ $cat ] );
		$produto->save();

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertInstanceOf( WP_Error::class, $resultado );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'produtos' ) );
	}
}

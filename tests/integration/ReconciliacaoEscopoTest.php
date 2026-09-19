<?php
/**
 * Fase 1 — a reconciliação passa a olhar o vínculo local, não o catálogo do ERP.
 *
 * Cenário real da conta Tiny compartilhada: 1.790 produtos no Tiny, todos em lixeira
 * (`situacao E`), de operações que não são desta loja. A reconciliação antiga
 * paginava esse catálogo inteiro e enfileirava um `produto_pull` por item.
 *
 * @package VaporHubLoja\Tests
 */

final class ReconciliacaoEscopoTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testCatalogoDaLixeiraNaoGeraNenhumPullMesmoComRecebimentoLigado(): void {
		VH_Fake_Tiny_ERP::seed_lixeira( 1790 );
		VH_Test_Env::atualizar_config(
			[
				'receber_catalogo'      => true,
				'receber_estoque_preco' => true,
			]
		);

		VH_Tiny_Sync_Service::reconciliar();

		self::assertSame( 0, VH_Test_Env::contar_jobs( 'produto_pull' ), 'Nenhum item da lixeira do ERP pode virar job.' );
	}

	public function testReconciliacaoVarreApenasProdutosLocaisJaVinculados(): void {
		$vinculado = VH_Test_Env::produto_simples( [ 'sku' => 'VH-VINC-1' ] );
		$solto     = VH_Test_Env::produto_simples( [ 'sku' => 'VH-SEM-VINC' ] );

		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'VH-VINC-1' ] );
		update_post_meta( $vinculado->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Fake_Tiny_ERP::seed_lixeira( 50 );
		VH_Test_Env::atualizar_config( [ 'receber_estoque_preco' => true ] );

		VH_Tiny_Sync_Service::reconciliar();

		$pulls = VH_Test_Env::jobs( 'produto_pull' );
		self::assertCount( 1, $pulls, 'Um pull por produto local vinculado, nada mais.' );
		self::assertSame( 'tiny_' . $tiny_id, $pulls[0]['referencia'] );

		$listagens = array_filter(
			VH_Fake_HTTP::requisicoes( 'GET', 'produtos' ),
			static fn( array $req ): bool => 'produtos' === $req['endpoint']
		);
		self::assertCount( 0, $listagens, 'Não pagina o catálogo do ERP.' );
		self::assertSame( 2, VH_Test_Env::contar_jobs( 'produto_push' ), 'Os dois produtos locais continuam sendo enviados.' );
		unset( $solto );
	}

	public function testVinculoLocalApontandoParaItemExcluidoNoErpNaoGeraPull(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'VH-VINC-1' ] );
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'VH-VINC-1', 'situacao' => 'E' ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Test_Env::atualizar_config( [ 'receber_estoque_preco' => true ] );

		VH_Tiny_Sync_Service::reconciliar();

		self::assertSame( 0, VH_Test_Env::contar_jobs( 'produto_pull' ), 'Item excluído no ERP não volta para a loja.' );
	}

	public function testPullDeItemExcluidoAbortaSemEscreverNaLoja(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'VH-VINC-1', 'preco_regular' => '49.90' ] );
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'      => 'VH-VINC-1',
				'situacao' => 'E',
				'precos'   => [ 'preco' => 9.9 ],
			]
		);
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );
		VH_Test_Env::atualizar_config( [ 'receber_estoque_preco' => true ] );

		$resultado = VH_Tiny_Sync_Service::puxar_produto( $tiny_id );

		self::assertTrue( is_wp_error( $resultado ) );
		self::assertSame( 'vh_tiny_situacao_excluida', $resultado->get_error_code() );
		self::assertSame( '49.90', wc_get_product( $produto->get_id() )->get_regular_price(), 'Preço local intacto.' );
	}

	public function testPullDeProdutoSemParLocalNaoCriaProdutoNaLoja(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'ALHEIO-99', 'descricao' => 'Produto de outra operação' ] );
		VH_Test_Env::atualizar_config( [ 'receber_catalogo' => true, 'receber_estoque_preco' => true ] );

		$resultado = VH_Tiny_Sync_Service::puxar_produto( $tiny_id );

		self::assertTrue( is_wp_error( $resultado ) );
		self::assertSame( 'vh_tiny_sku_fora_do_recorte', $resultado->get_error_code() );
		self::assertSame( 0, wc_get_product_id_by_sku( 'ALHEIO-99' ), 'Nada de produto órfão na vitrine.' );
	}

	public function testPullDeProdutoSimplesComRecorteCriaNaLoja(): void {
		$term_id  = VH_Test_Env::categoria( 'POD Descartável' );
		$tiny_cat = VH_Fake_Tiny_ERP::seed_categoria( 'POD Descartável' );
		update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, $tiny_cat );

		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'        => 'VH-IMP-001',
				'descricao'  => 'Pod importado',
				'categoria'  => [ 'id' => $tiny_cat ],
				'precos'     => [ 'preco' => 79.9 ],
				'situacao'   => 'A',
			]
		);
		VH_Test_Env::atualizar_config( [ 'receber_catalogo' => true, 'receber_estoque_preco' => true ] );

		$resultado = VH_Tiny_Sync_Service::puxar_produto( $tiny_id );

		self::assertTrue( true === $resultado );
		$wc_id = wc_get_product_id_by_sku( 'VH-IMP-001' );
		self::assertGreaterThan( 0, $wc_id );
		self::assertSame( $tiny_id, (int) get_post_meta( $wc_id, VH_Tiny_Map::META_TINY_ID, true ) );
	}

	public function testPullDeVariavelSemParNaoCriaPai(): void {
		$term_id  = VH_Test_Env::categoria( 'POD Descartável' );
		$tiny_cat = VH_Fake_Tiny_ERP::seed_categoria( 'POD Descartável' );
		update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, $tiny_cat );

		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'       => 'VH-VAR-IMP',
				'descricao' => 'Pod 12 sabores',
				'categoria' => [ 'id' => $tiny_cat ],
				'variacoes' => [ [ 'sku' => 'VH-VAR-IMP-MANGA' ] ],
			]
		);
		VH_Test_Env::atualizar_config( [ 'receber_catalogo' => true ] );

		$resultado = VH_Tiny_Sync_Service::puxar_produto( $tiny_id );

		self::assertTrue( is_wp_error( $resultado ) );
		self::assertSame( 'vh_tiny_variavel_pendente', $resultado->get_error_code() );
		self::assertSame( 0, wc_get_product_id_by_sku( 'VH-VAR-IMP' ) );
	}

	public function testWebhookDeIdDesconhecidoNaoGeraJob(): void {
		VH_Test_Env::atualizar_config( [ 'receber_estoque_preco' => true ] );

		VH_Tiny_Sync_Service::processar_webhook(
			[ 'tipo' => 'estoque.alterado', 'idProduto' => 987654, 'codigo' => 'ALHEIO-99' ]
		);

		self::assertSame( 0, VH_Test_Env::contar_jobs(), 'Evento de produto que não é nosso é descartado.' );
	}

	public function testWebhookDeSkuConhecidoGeraPullDeEstoque(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'VH-POD-001' ] );
		VH_Test_Env::atualizar_config( [ 'receber_estoque_preco' => true ] );

		VH_Tiny_Sync_Service::processar_webhook(
			[ 'tipo' => 'estoque.alterado', 'idProduto' => 555, 'codigo' => 'VH-POD-001' ]
		);

		$jobs = VH_Test_Env::jobs( 'estoque_pull' );
		self::assertCount( 1, $jobs );
		self::assertSame( 'wc_' . $produto->get_id(), $jobs[0]['referencia'] );
	}

	public function testWebhookDeVinculoConhecidoPorTinyIdGeraPull(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'VH-POD-001' ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, 555 );
		VH_Test_Env::atualizar_config( [ 'receber_catalogo' => true ] );

		VH_Tiny_Sync_Service::processar_webhook( [ 'tipo' => 'produto.alterado', 'id' => 555 ] );

		$jobs = VH_Test_Env::jobs( 'produto_pull' );
		self::assertCount( 1, $jobs );
		self::assertSame( 'tiny_555', $jobs[0]['referencia'] );
	}
}

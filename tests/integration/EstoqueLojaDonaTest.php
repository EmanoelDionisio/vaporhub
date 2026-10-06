<?php
/**
 * Fase 4 — a loja é dona do saldo.
 *
 * Uma venda no site muda o estoque sem passar pelo formulário: sem listener no
 * WooCommerce o ERP fica com o saldo velho. Aqui provamos que a venda enfileira
 * push de saldo, que saldo igual não vira lançamento repetido e que o caminho
 * inverso (ERP mandando saldo) segue fechado.
 *
 * @package VaporHubLoja\Tests
 */

final class EstoqueLojaDonaTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset( [ 'sinc_auto' => true ] );
		VH_Tiny_Module::init();
	}

	/**
	 * Produto simples da loja já vinculado a um cadastro no ERP.
	 *
	 * @return array{produto:WC_Product, tiny_id:int}
	 */
	private function produto_vinculado( int $saldo_erp = 10 ): array {
		$produto = VH_Test_Env::produto_simples();
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'     => $produto->get_sku(),
				'estoque' => [
					'controlar' => true,
					'saldo'     => $saldo_erp,
				],
			]
		);
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		return [
			'produto' => $produto,
			'tiny_id' => $tiny_id,
		];
	}

	public function testVendaQueBaixaSaldoEnfileiraPushDeEstoque(): void {
		$cenario = $this->produto_vinculado();

		wc_update_product_stock( $cenario['produto'], 3, 'decrease' );

		$jobs = VH_Test_Env::jobs( 'estoque_push' );
		self::assertCount( 1, $jobs );
		$payload = json_decode( (string) $jobs[0]['payload'], true );
		self::assertSame( $cenario['produto']->get_id(), (int) $payload['produto_id'] );
	}

	public function testPushDeEstoqueLancaBalancoComOSaldoDaLoja(): void {
		$cenario = $this->produto_vinculado();

		wc_update_product_stock( $cenario['produto'], 3, 'decrease' );
		VH_Tiny_Queue::processar_lote();

		$corpo = VH_Fake_HTTP::ultimo_corpo( 'POST', 'estoque/' . $cenario['tiny_id'] );
		self::assertSame( 'B', $corpo['tipo'] );
		self::assertSame( 7.0, (float) $corpo['quantidade'] );
	}

	public function testSaldoJaIgualNoErpNaoGeraLancamento(): void {
		$cenario = $this->produto_vinculado( 10 );

		/* Saldo da loja continua 10: o job roda, confere e sai fora. */
		VH_Tiny_Queue::enfileirar( 'estoque_push', 'estoque_' . $cenario['produto']->get_id(), [ 'produto_id' => $cenario['produto']->get_id() ] );
		VH_Tiny_Queue::processar_lote();

		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'estoque/' ) );
	}

	public function testPushDeEstoqueNaoTocaNoCadastroDoProduto(): void {
		$cenario = $this->produto_vinculado();

		wc_update_product_stock( $cenario['produto'], 1, 'decrease' );
		VH_Tiny_Queue::processar_lote();

		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'produtos' ), 'Saldo não recria produto.' );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'PUT', 'produtos/' ), 'Saldo não reescreve cadastro.' );
	}

	public function testVariacaoEmpurraSaldoNoIdDaPropriaVariacao(): void {
		$pai     = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );
		$filhos  = $pai->get_children();
		$var_id  = (int) $filhos[0];
		$tiny_pai = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => $pai->get_sku(), 'tipo' => 'V' ] );
		$tiny_var = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'     => wc_get_product( $var_id )->get_sku(),
				'estoque' => [
					'controlar' => true,
					'saldo'     => 5,
				],
			]
		);
		update_post_meta( $pai->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_pai );
		update_post_meta( $var_id, VH_Tiny_Map::META_TINY_ID, $tiny_var );

		wc_update_product_stock( wc_get_product( $var_id ), 2, 'decrease' );
		VH_Tiny_Queue::processar_lote();

		self::assertSame( 3.0, (float) VH_Fake_HTTP::ultimo_corpo( 'POST', 'estoque/' . $tiny_var )['quantidade'] );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'estoque/' . $tiny_pai ), 'Saldo do pai variável não existe.' );
	}

	public function testProdutoSemVinculoNoErpVaiParaEnvioCompletoEmVezDeSaldo(): void {
		$produto = VH_Test_Env::produto_simples();
		wc_update_product_stock( $produto, 2, 'decrease' );

		$resultado = VH_Tiny_Sync_Service::empurrar_estoque( $produto->get_id() );

		self::assertTrue( true === $resultado );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'estoque/' ) );
		self::assertSame( 0, VH_Test_Env::contar_jobs( 'produto_push' ), 'Sem vínculo, a loja não cria cadastro no hub.' );
	}

	public function testProdutoEmGuardaDeImportacaoNaoGeraPushDeSaldo(): void {
		$cenario = $this->produto_vinculado();
		update_post_meta( $cenario['produto']->get_id(), VH_Tiny_Map::META_GUARD, time() );

		wc_update_product_stock( $cenario['produto'], 3, 'decrease' );

		self::assertSame( 0, VH_Test_Env::contar_jobs( 'estoque_push' ) );
	}

	public function testEnvioDesligadoSeguraOJobSemChamarOErp(): void {
		$cenario = $this->produto_vinculado();
		VH_Tiny_Queue::enfileirar( 'estoque_push', 'estoque_' . $cenario['produto']->get_id(), [ 'produto_id' => $cenario['produto']->get_id() ] );
		VH_Test_Env::atualizar_config( [ 'permitir_envio' => false ] );

		VH_Tiny_Queue::processar_lote();

		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'estoque/' ) );
		self::assertSame( 1, VH_Test_Env::contar_jobs( 'estoque_push' ), 'Job volta para a fila em vez de queimar tentativas.' );
	}

	public function testSaldoDoErpNaoEntraNaLojaComRecebimentoDesligado(): void {
		$cenario = $this->produto_vinculado( 999 );
		VH_Tiny_Queue::enfileirar( 'estoque_pull', 'estoque_pull_' . $cenario['produto']->get_id(), [ 'produto_id' => $cenario['produto']->get_id() ] );

		VH_Tiny_Queue::processar_lote();

		self::assertSame( 10, wc_get_product( $cenario['produto']->get_id() )->get_stock_quantity() );
	}

	public function testEstoquePorVariacaoPersisteAoSalvarOProduto(): void {
		$pai    = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );
		$filhos = $pai->get_children();

		VH_Products_Service::atualizar(
			$pai->get_id(),
			[
				'variacoes' => [
					(string) $filhos[0] => [ 'estoque' => 7 ],
					(string) $filhos[1] => [ 'estoque' => 0 ],
				],
			]
		);

		self::assertSame( 7, wc_get_product( (int) $filhos[0] )->get_stock_quantity() );
		self::assertSame( 0, wc_get_product( (int) $filhos[1] )->get_stock_quantity() );
		self::assertTrue( wc_get_product( (int) $filhos[0] )->get_manage_stock() );
	}

	public function testSaldoDeVariacaoDeOutroProdutoEIgnorado(): void {
		$pai   = VH_Test_Env::produto_variavel( [ 'azul' ] );
		$outro = VH_Test_Env::produto_variavel( [ 'azul' ], [ 'nome' => 'Camiseta Outra', 'sku' => 'VH-OUTRA' ] );
		$alheia = (int) $outro->get_children()[0];

		VH_Products_Service::atualizar(
			$pai->get_id(),
			[ 'variacoes' => [ (string) $alheia => [ 'estoque' => 99 ] ] ]
		);

		self::assertSame( 5, wc_get_product( $alheia )->get_stock_quantity(), 'Tabela de um produto não edita variação de outro.' );
	}

	public function testSaldoDaVariacaoVaiNoPayloadDaVariacaoCorreta(): void {
		$pai    = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );
		$filhos = $pai->get_children();

		VH_Products_Service::atualizar(
			$pai->get_id(),
			[ 'variacoes' => [ (string) $filhos[1] => [ 'estoque' => 4 ] ] ]
		);
		VH_Tiny_Sync_Service::empurrar_produto( $pai->get_id() );

		$corpo     = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );
		$por_sku   = [];
		foreach ( (array) ( $corpo['variacoes'] ?? [] ) as $var ) {
			$por_sku[ (string) $var['sku'] ] = $var;
		}
		$sku_alvo = wc_get_product( (int) $filhos[1] )->get_sku();

		self::assertSame( 4.0, (float) $por_sku[ $sku_alvo ]['estoque']['inicial'] );
	}
}

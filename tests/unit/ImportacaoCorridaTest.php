<?php
/**
 * A importação percorre a lista sem pular o que ainda não gravou
 * e acumula o progresso que a barra lê.
 *
 * @package VaporHubLoja\Tests
 */

final class ImportacaoCorridaTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testLoteGravaUmEGuardaOProximoNoCursor(): void {
		$this->principal( 'COM-A', 4 );
		$this->principal( 'COM-B', 2 );

		$primeiro = VH_Tiny_Importacao::lote( 1 );

		self::assertTrue( is_array( $primeiro ) );
		self::assertSame( 1, (int) $primeiro['gravados'] );
		self::assertSame( 1, (int) $primeiro['offset'] );
		self::assertFalse( (bool) $primeiro['concluida'] );
		self::assertSame( 1, (int) $primeiro['corrida']['gravados'] );
		self::assertSame( 'andamento', $primeiro['corrida']['status'] );
		self::assertGreaterThan( 0, wc_get_product_id_by_sku( 'COM-A' ) );
		self::assertSame( 0, wc_get_product_id_by_sku( 'COM-B' ) );

		$segundo = VH_Tiny_Importacao::lote( 1 );

		self::assertSame( 1, (int) $segundo['gravados'] );
		self::assertTrue( (bool) $segundo['concluida'] );
		self::assertSame( 2, (int) $segundo['corrida']['gravados'] );
		self::assertSame( 100, (int) $segundo['corrida']['percentual'] );
		self::assertSame( 'concluida', $segundo['corrida']['status'] );
		self::assertSame( [], $segundo['corrida']['erros'] );
		self::assertGreaterThan( 0, wc_get_product_id_by_sku( 'COM-B' ) );
	}

	public function testSemEstoqueNaoEntraNaListaDeErro(): void {
		$this->principal( 'SEM-CORRIDA', 0 );
		$this->principal( 'COM-CORRIDA', 3 );

		$primeiro = VH_Tiny_Importacao::lote( 1 );
		$segundo  = VH_Tiny_Importacao::lote( 1 );

		self::assertSame( 1, (int) $primeiro['sem_estoque'] );
		self::assertSame( 1, (int) $segundo['gravados'] );
		self::assertSame( 1, (int) $segundo['corrida']['sem_estoque'] );
		self::assertSame( 1, (int) $segundo['corrida']['gravados'] );
		self::assertSame( [], $segundo['corrida']['erros'] );
		self::assertSame( 0, wc_get_product_id_by_sku( 'SEM-CORRIDA' ) );
		self::assertGreaterThan( 0, wc_get_product_id_by_sku( 'COM-CORRIDA' ) );
	}

	public function testLimiteDoTinyNaoPulaOProduto(): void {
		$this->principal( 'DEPOIS-DO-LIMITE', 4 );
		set_transient( 'vh_tiny_api_proxima', time() + 25, 60 );

		$passo = VH_Tiny_Importacao::lote( 1 );

		self::assertTrue( is_array( $passo ) );
		self::assertGreaterThan( 20, (int) $passo['aguardar'] );
		self::assertSame( 0, (int) $passo['offset'] );
		self::assertSame( 0, (int) $passo['gravados'] );
		self::assertSame( [], $passo['corrida']['erros'] );
		self::assertSame( 0, wc_get_product_id_by_sku( 'DEPOIS-DO-LIMITE' ) );
		delete_transient( 'vh_tiny_api_proxima' );
	}

	public function testLoteParaDepoisDeTresConsultasDeDetalhe(): void {
		$this->principal( 'ZERO-1', 0 );
		$this->principal( 'ZERO-2', 0 );
		$this->principal( 'ZERO-3', 0 );
		$this->principal( 'ZERO-4', 0 );
		$this->principal( 'COM-FIM', 5 );

		$passo = VH_Tiny_Importacao::lote( 1 );

		self::assertSame( 0, (int) $passo['gravados'] );
		self::assertSame( 1, (int) $passo['sem_estoque'] );
		self::assertSame( 1, (int) $passo['offset'] );
		self::assertFalse( (bool) $passo['concluida'] );
		self::assertSame( 0, wc_get_product_id_by_sku( 'COM-FIM' ) );
	}

	public function testEstoqueSemPrecoFicaForaDoRecorte(): void {
		$resultado = VH_Tiny_Importacao::gravar(
			[
				'sku'       => 'SEM-PRECO-VAR',
				'nome'      => 'Pod sem preço',
				'situacao'  => 'A',
				'estoque'   => 0,
				'variacoes' => [
					[
						'sku'           => 'SEM-PRECO-VAR-1',
						'estoque'       => 5,
						'preco_regular' => '',
					],
				],
			],
			99001
		);

		self::assertTrue( is_wp_error( $resultado ) );
		self::assertSame( 'vh_tiny_sem_preco', $resultado->get_error_code() );
		self::assertSame( 0, wc_get_product_id_by_sku( 'SEM-PRECO-VAR' ) );
	}

	public function testSemPrecoEntraQuandoOFiltroEstaDesligado(): void {
		VH_Tiny::salvar_config( [ 'import_exigir_preco' => false ] );

		$resultado = VH_Tiny_Importacao::gravar(
			[
				'sku'       => 'SEM-PRECO-LIVRE',
				'nome'      => 'Pod sem preço liberado',
				'situacao'  => 'A',
				'estoque'   => 0,
				'variacoes' => [
					[
						'sku'           => 'SEM-PRECO-LIVRE-1',
						'estoque'       => 5,
						'preco_regular' => '',
					],
				],
			],
			99002
		);

		self::assertTrue( true === $resultado );
		$id = wc_get_product_id_by_sku( 'SEM-PRECO-LIVRE' );
		self::assertGreaterThan( 0, $id );
		unset( VH_Fake_WP::$produtos[ $id ], VH_Fake_WP::$produtos[ wc_get_product_id_by_sku( 'SEM-PRECO-LIVRE-1' ) ] );
		VH_Tiny::salvar_config( [ 'import_exigir_preco' => true ] );
	}

	public function testSemEstoqueEntraQuandoOFiltroEstaDesligado(): void {
		VH_Tiny::salvar_config( [ 'import_exigir_estoque' => false ] );

		$resultado = VH_Tiny_Importacao::gravar(
			[
				'sku'           => 'SEM-ESTOQUE-LIVRE',
				'nome'          => 'Pod sem estoque liberado',
				'situacao'      => 'A',
				'estoque'       => 0,
				'preco_regular' => '19.90',
			],
			99003
		);

		self::assertTrue( true === $resultado );
		$id = wc_get_product_id_by_sku( 'SEM-ESTOQUE-LIVRE' );
		self::assertGreaterThan( 0, $id );
		unset( VH_Fake_WP::$produtos[ $id ] );
		VH_Tiny::salvar_config( [ 'import_exigir_estoque' => true ] );
	}

	private function principal( string $sku, int $saldo ): void {
		VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'          => $sku,
				'descricao'    => $sku,
				'tipo'         => 'S',
				'tipoVariacao' => 'N',
				'situacao'     => 'A',
				'estoque'      => [
					'controlar'  => true,
					'saldo'      => $saldo,
					'quantidade' => $saldo,
				],
			]
		);
	}
}

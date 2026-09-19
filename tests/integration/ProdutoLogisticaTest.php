<?php
/**
 * Fase 2 — o painel passa a cadastrar logística também no personalizável.
 *
 * `VH_Products_Service::aplicar()` só gravava peso dentro do bloco de produto
 * simples, então produto variável não tinha onde informar peso e caixa.
 *
 * @package VaporHubLoja\Tests
 */

final class ProdutoLogisticaTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testProdutoVariavelPersisteMedidasNoPai(): void {
		$pai = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );

		VH_Products_Service::atualizar(
			$pai->get_id(),
			[
				'peso'        => '0,280',
				'largura'     => '12',
				'altura'      => '8',
				'comprimento' => '15',
				'volumes'     => '2',
				'embalagem_tipo' => '2',
			]
		);

		$salvo = wc_get_product( $pai->get_id() );
		self::assertSame( 0.28, (float) $salvo->get_weight(), 'Aceita vírgula do formulário.' );
		self::assertSame( '12', $salvo->get_width() );
		self::assertSame( '8', $salvo->get_height() );
		self::assertSame( '15', $salvo->get_length() );
		self::assertSame( 2, (int) get_post_meta( $pai->get_id(), VH_Products_Service::META_VOLUMES, true ) );
		self::assertSame( 2, (int) get_post_meta( $pai->get_id(), VH_Products_Service::META_EMBALAGEM, true ) );
	}

	public function testProdutoSimplesContinuaPersistindoMedidas(): void {
		$produto = VH_Test_Env::produto_simples();

		VH_Products_Service::atualizar(
			$produto->get_id(),
			[
				'peso'        => '1.5',
				'largura'     => '20',
				'altura'      => '10',
				'comprimento' => '30',
			]
		);

		$salvo = wc_get_product( $produto->get_id() );
		self::assertSame( 1.5, (float) $salvo->get_weight() );
		self::assertSame( '20', $salvo->get_width() );
		self::assertSame( '10', $salvo->get_height() );
		self::assertSame( '30', $salvo->get_length() );
	}

	public function testSalvarSemTocarNaLogisticaNaoApagaOQueJaExiste(): void {
		$produto = VH_Test_Env::produto_simples( [ 'peso' => '0.280', 'largura' => '12' ] );

		VH_Products_Service::atualizar( $produto->get_id(), [ 'nome' => 'Pod renomeado' ] );

		$salvo = wc_get_product( $produto->get_id() );
		self::assertSame( 'Pod renomeado', $salvo->get_name() );
		self::assertSame( '0.280', $salvo->get_weight() );
		self::assertSame( '12', $salvo->get_width() );
	}

	public function testVolumeZeradoLimpaAMetaEmVezDeGravarZero(): void {
		$produto = VH_Test_Env::produto_simples();
		update_post_meta( $produto->get_id(), VH_Products_Service::META_VOLUMES, 3 );

		VH_Products_Service::atualizar( $produto->get_id(), [ 'volumes' => '' ] );

		self::assertSame( '', (string) get_post_meta( $produto->get_id(), VH_Products_Service::META_VOLUMES, true ) );
	}

	public function testDetalheDoProdutoDevolveLogisticaParaOFormulario(): void {
		$pai = VH_Test_Env::produto_variavel( [ 'azul' ] );
		VH_Products_Service::atualizar(
			$pai->get_id(),
			[ 'peso' => '0.280', 'largura' => '12', 'altura' => '8', 'comprimento' => '15', 'volumes' => '2', 'embalagem_tipo' => '3' ]
		);

		$detalhe = VH_Products_Service::obter( $pai->get_id() );

		self::assertSame( 0.28, (float) $detalhe['peso'] );
		self::assertSame( '12', $detalhe['largura'] );
		self::assertSame( '8', $detalhe['altura'] );
		self::assertSame( '15', $detalhe['comprimento'] );
		self::assertSame( 2, $detalhe['volumes'] );
		self::assertSame( 3, $detalhe['embalagem_tipo'] );
	}
}

<?php
/**
 * Verifica a própria rede de teste: kernel, ERP falso e cliente real conversando.
 *
 * @package VaporHubLoja\Tests
 */

final class HarnessTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testClasseDoPluginCarregada(): void {
		self::assertTrue( class_exists( 'VH_Tiny' ) );
		self::assertTrue( class_exists( 'VH_Tiny_Driver_V3' ) );
		self::assertTrue( class_exists( 'VH_Tiny_Sync_Service' ) );
	}

	public function testConfiguracaoDeTesteDeixaIntegracaoConectada(): void {
		self::assertSame( 'v3', VH_Tiny::modo() );
		self::assertTrue( VH_Tiny::driver()->conectado() );
		self::assertTrue( VH_Tiny::pode_enviar() );
		self::assertFalse( VH_Tiny::pode_receber() );
	}

	public function testRequisicaoRealChegaAoErpFalsoSemRede(): void {
		$resp = VH_Tiny_Client::requisicao( 'GET', 'info' );

		self::assertFalse( is_wp_error( $resp ) );
		self::assertSame( VH_Fake_Tiny_ERP::CNPJ_LOJA, $resp['cpfCnpj'] );
		self::assertSame( 1, VH_Fake_HTTP::contar( 'GET', 'info' ) );
	}

	public function testErroDaApiViraWpErrorComStatus(): void {
		VH_Fake_Tiny_ERP::$falhas_forcadas['GET produtos/999'] = [
			'status' => 500,
			'body'   => [ 'message' => 'Erro interno' ],
		];

		$resp = VH_Tiny_Client::requisicao( 'GET', 'produtos/999' );

		self::assertTrue( is_wp_error( $resp ) );
		self::assertSame( 500, $resp->get_error_data()['status'] );
	}

	public function testFilaEmMemoriaRegistraJob(): void {
		$id = VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_10', [ 'produto_id' => 10 ] );

		self::assertGreaterThan( 0, $id );
		self::assertSame( 1, VH_Test_Env::contar_jobs( 'produto_push' ) );

		/* Dedupe: o mesmo job pendente não entra duas vezes. */
		VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_10', [ 'produto_id' => 10 ] );
		self::assertSame( 1, VH_Test_Env::contar_jobs( 'produto_push' ) );
	}

	public function testProdutoSimplesDeTesteExisteNoKernelWoo(): void {
		$produto = VH_Test_Env::produto_simples();

		self::assertSame( 'VH-POD-001', $produto->get_sku() );
		self::assertSame( $produto->get_id(), wc_get_product_id_by_sku( 'VH-POD-001' ) );
		self::assertTrue( wc_get_product( $produto->get_id() ) instanceof WC_Product_Simple );
	}
}

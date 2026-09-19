<?php
/**
 * Fase 3 — identidade fiscal sai da loja: NCM, GTIN, unidade e marca.
 *
 * Sem esses campos o produto nasce incompleto no ERP e trava emissão de nota.
 * Campo vazio tem de ser omitido: enviar string vazia sobrescreve o que já
 * estiver preenchido no cadastro do Tiny.
 *
 * @package VaporHubLoja\Tests
 */

final class FiscalProdutoTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	private function produto_fiscal( array $metas ): WC_Product {
		$produto = VH_Test_Env::produto_simples();
		foreach ( $metas as $chave => $valor ) {
			update_post_meta( $produto->get_id(), $chave, $valor );
		}
		return wc_get_product( $produto->get_id() );
	}

	public function testCanonicoTrazBlocoFiscal(): void {
		$produto = $this->produto_fiscal(
			[
				VH_Products_Service::META_NCM      => '95079900',
				VH_Products_Service::META_GTIN     => '7898765432109',
				VH_Products_Service::META_UNIDADE  => 'UN',
				VH_Products_Service::META_MARCA    => 'Vapor Hub',
			]
		);

		$fiscal = VH_Tiny_Map::wc_para_canonico( $produto )['fiscal'] ?? [];

		self::assertSame( '95079900', $fiscal['ncm'] );
		self::assertSame( '7898765432109', $fiscal['gtin'] );
		self::assertSame( 'UN', $fiscal['unidade'] );
		self::assertSame( 'Vapor Hub', $fiscal['marca'] );
		self::assertArrayNotHasKey( 'marca_id', $fiscal, 'Sem vínculo de marca no ERP não há id.' );
	}

	public function testProdutoSemDadoFiscalNaoTrazBloco(): void {
		$canonico = VH_Tiny_Map::wc_para_canonico( VH_Test_Env::produto_simples() );

		self::assertArrayNotHasKey( 'fiscal', $canonico );
	}

	public function testPayloadV3LevaNcmGtinEUnidade(): void {
		$produto = $this->produto_fiscal(
			[
				VH_Products_Service::META_NCM     => '95079900',
				VH_Products_Service::META_GTIN    => '7898765432109',
				VH_Products_Service::META_UNIDADE => 'UN',
			]
		);

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );
		$corpo = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );

		self::assertSame( '95079900', $corpo['ncm'] );
		self::assertSame( '7898765432109', $corpo['gtin'] );
		self::assertSame( 'UN', $corpo['unidade'] );
	}

	public function testCampoFiscalVazioNaoVaiNoPayload(): void {
		$produto = $this->produto_fiscal( [ VH_Products_Service::META_NCM => '95079900' ] );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );
		$corpo = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );

		self::assertSame( '95079900', $corpo['ncm'] );
		self::assertArrayNotHasKey( 'gtin', $corpo );
		self::assertArrayNotHasKey( 'unidade', $corpo );
		self::assertArrayNotHasKey( 'marca', $corpo );
	}

	public function testMarcaSoVaiQuandoExisteIdNoErp(): void {
		$produto = $this->produto_fiscal(
			[
				VH_Products_Service::META_MARCA    => 'Vapor Hub',
				VH_Products_Service::META_MARCA_ID => 4321,
			]
		);

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );
		$corpo = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );

		self::assertSame( 4321, (int) $corpo['marca']['id'] );
	}

	public function testMarcaSemIdNaoDerrubaOEnvioDoProduto(): void {
		$produto = $this->produto_fiscal( [ VH_Products_Service::META_MARCA => 'Vapor Hub' ] );

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertTrue( true === $resultado, 'Módulo de marcas bloqueado na conta não pode travar o cadastro.' );
		self::assertArrayNotHasKey( 'marca', VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' ) );
	}

	public function testRespostaDoErpDevolveFiscalNoCanonico(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'      => 'VH-POD-001',
				'ncm'      => '95079900',
				'gtin'     => '7898765432109',
				'unidade'  => 'UN',
				'marca'    => [ 'id' => 4321, 'nome' => 'Vapor Hub' ],
			]
		);

		$canonico = VH_Tiny::driver()->obter_produto( $tiny_id );

		self::assertSame( '95079900', $canonico['fiscal']['ncm'] );
		self::assertSame( 'UN', $canonico['fiscal']['unidade'] );
		self::assertSame( 4321, $canonico['fiscal']['marca_id'] );
	}

	public function testHashPassaASentirMudancaFiscal(): void {
		$produto = VH_Test_Env::produto_simples();
		$antes   = VH_Tiny_Map::hash_produto_wc( $produto );

		update_post_meta( $produto->get_id(), VH_Products_Service::META_NCM, '95079900' );

		self::assertTrue(
			$antes !== VH_Tiny_Map::hash_produto_wc( wc_get_product( $produto->get_id() ) ),
			'NCM novo precisa gerar novo envio.'
		);
	}

	public function testMapeamentoNormalizaDadoFiscalSujoDoFormulario(): void {
		$produto = VH_Test_Env::produto_simples();

		VH_Products_Service::atualizar(
			$produto->get_id(),
			[
				'ncm'      => '9507.99.00',
				'gtin'     => ' 7898765432109 ',
				'unidade'  => 'un',
				'marca'    => '  Vapor Hub  ',
			]
		);

		self::assertSame( '95079900', get_post_meta( $produto->get_id(), VH_Products_Service::META_NCM, true ) );
		self::assertSame( '7898765432109', get_post_meta( $produto->get_id(), VH_Products_Service::META_GTIN, true ) );
		self::assertSame( 'UN', get_post_meta( $produto->get_id(), VH_Products_Service::META_UNIDADE, true ) );
		self::assertSame( 'Vapor Hub', get_post_meta( $produto->get_id(), VH_Products_Service::META_MARCA, true ) );
	}
}

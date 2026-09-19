<?php
/**
 * Fase 2 — logística sai da loja completa: peso, medidas, volumes e embalagem.
 *
 * Hoje o payload leva só `pesoBruto`/`pesoLiquido`, e o cadastro nasce sem
 * dimensão no ERP. Frete dos Correios e expedição dependem desses campos.
 *
 * @package VaporHubLoja\Tests
 */

final class EnvioDimensoesTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function canonico_com_medidas(): array {
		$produto = VH_Test_Env::produto_simples(
			[
				'peso'        => '0.280',
				'largura'     => '12',
				'altura'      => '8',
				'comprimento' => '15',
			]
		);
		update_post_meta( $produto->get_id(), VH_Products_Service::META_VOLUMES, 2 );
		update_post_meta( $produto->get_id(), VH_Products_Service::META_EMBALAGEM, 2 );

		return VH_Tiny_Map::wc_para_canonico( wc_get_product( $produto->get_id() ) );
	}

	public function testCanonicoTrazBlocoEnvioCompleto(): void {
		$envio = $this->canonico_com_medidas()['envio'] ?? [];

		self::assertSame( 0.28, $envio['peso_bruto'] );
		self::assertSame( 0.28, $envio['peso_liquido'], 'Sem campo próprio, líquido acompanha o bruto.' );
		self::assertSame( 12.0, $envio['largura'] );
		self::assertSame( 8.0, $envio['altura'] );
		self::assertSame( 15.0, $envio['comprimento'] );
		self::assertSame( 2, $envio['volumes'] );
		self::assertSame( 2, $envio['embalagem_tipo'] );
	}

	public function testProdutoSemMedidasNaoTrazBlocoEnvio(): void {
		$produto  = VH_Test_Env::produto_simples();
		$canonico = VH_Tiny_Map::wc_para_canonico( $produto );

		self::assertArrayNotHasKey( 'envio', $canonico, 'Produto sem logística não inventa bloco.' );
	}

	public function testPayloadV3LevaDimensoesCompletas(): void {
		$produto = VH_Test_Env::produto_simples(
			[
				'peso'        => '0.280',
				'largura'     => '12',
				'altura'      => '8',
				'comprimento' => '15',
			]
		);
		update_post_meta( $produto->get_id(), VH_Products_Service::META_VOLUMES, 2 );
		update_post_meta( $produto->get_id(), VH_Products_Service::META_EMBALAGEM, 2 );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		$dim = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' )['dimensoes'] ?? [];

		/* Casts porque o payload passa por JSON: 12.0 volta como int 12. */
		self::assertSame( 0.28, (float) $dim['pesoBruto'] );
		self::assertSame( 0.28, (float) $dim['pesoLiquido'] );
		self::assertSame( 12.0, (float) $dim['largura'] );
		self::assertSame( 8.0, (float) $dim['altura'] );
		self::assertSame( 15.0, (float) $dim['comprimento'] );
		self::assertSame( 2, (int) $dim['quantidadeVolumes'] );
		self::assertSame( 2, (int) $dim['embalagem']['tipo'] );
	}

	public function testMedidasVaziasNaoEnviamDimensoesZerado(): void {
		$produto = VH_Test_Env::produto_simples();

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertArrayNotHasKey( 'dimensoes', VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' ) );
	}

	public function testSoOPesoPreenchidoEnviaApenasOsPesos(): void {
		$produto = VH_Test_Env::produto_simples( [ 'peso' => '0.5' ] );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		$dim = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' )['dimensoes'] ?? [];

		self::assertSame( [ 'pesoBruto', 'pesoLiquido' ], array_keys( $dim ) );
	}

	public function testRespostaDoErpVoltaComMedidasNoCanonico(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'       => 'VH-POD-001',
				'dimensoes' => [
					'largura'           => 12,
					'altura'            => 8,
					'comprimento'       => 15,
					'pesoBruto'         => 0.28,
					'pesoLiquido'       => 0.28,
					'quantidadeVolumes' => 2,
					'embalagem'         => [ 'tipo' => 2 ],
				],
			]
		);

		$canonico = VH_Tiny::driver()->obter_produto( $tiny_id );

		self::assertSame( 12.0, $canonico['envio']['largura'] );
		self::assertSame( 15.0, $canonico['envio']['comprimento'] );
		self::assertSame( 2, $canonico['envio']['volumes'] );
		self::assertSame( 0.28, $canonico['peso'], 'Campo legado continua existindo.' );
	}

	public function testDimensaoZeradaNoErpNaoEntraNoCanonico(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'       => 'VH-POD-001',
				'dimensoes' => [
					'largura'     => 0,
					'altura'      => 0,
					'comprimento' => 0,
					'pesoBruto'   => 0,
				],
			]
		);

		$canonico = VH_Tiny::driver()->obter_produto( $tiny_id );

		self::assertArrayNotHasKey( 'envio', $canonico, 'ERP zerado não tem o que ensinar à loja.' );
	}

	public function testHashPassaASentirMudancaDeMedida(): void {
		$produto = VH_Test_Env::produto_simples( [ 'peso' => '0.280' ] );
		$antes   = VH_Tiny_Map::hash_produto_wc( $produto );

		$produto->set_width( '12' );
		$produto->save();

		self::assertTrue(
			$antes !== VH_Tiny_Map::hash_produto_wc( wc_get_product( $produto->get_id() ) ),
			'Mudança de largura tem de gerar novo envio (mudança intencional na baseline).'
		);
	}
}

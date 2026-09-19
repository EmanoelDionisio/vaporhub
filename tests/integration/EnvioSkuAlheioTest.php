<?php
/**
 * Fase 1 — envio não adota cadastro alheio por coincidência de código.
 *
 * `buscar_produto_por_sku()` casa com qualquer produto da conta. Só o SKU com o
 * prefixo da loja autoriza transformar esse casamento em UPDATE.
 *
 * @package VaporHubLoja\Tests
 */

final class EnvioSkuAlheioTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testSkuComPrefixoDaLojaAdotaOCadastroExistenteNoErp(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'VH-POD-001' ] );
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'VH-POD-001', 'descricao' => 'Pod já cadastrado' ] );

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertTrue( true === $resultado, 'Envio deveria concluir.' );
		self::assertSame( $tiny_id, (int) get_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, true ) );
		self::assertGreaterThan( 0, VH_Fake_HTTP::contar( 'PUT', 'produtos/' . $tiny_id ), 'Atualiza o cadastro que já é da loja.' );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'produtos' ), 'Não cria duplicado.' );
	}

	public function testSkuSemPrefixoDaLojaCriaCadastroNovoEmVezDeEscreverNoAlheio(): void {
		$produto  = VH_Test_Env::produto_simples( [ 'sku' => 'ISCA-001' ] );
		$alheio_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'ISCA-001', 'descricao' => 'Produto de outra operação' ] );

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertTrue( true === $resultado, 'Envio deveria concluir criando cadastro novo.' );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'PUT', 'produtos/' . $alheio_id ), 'Nada de PUT em cadastro alheio.' );
		self::assertSame( 1, VH_Fake_HTTP::contar( 'POST', 'produtos' ), 'Cria cadastro próprio.' );

		$novo_id = (int) get_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, true );
		self::assertGreaterThan( 0, $novo_id );
		self::assertTrue( $novo_id !== $alheio_id, 'O vínculo não pode apontar para o produto de outra operação.' );
		self::assertSame( 'Produto de outra operação', VH_Fake_Tiny_ERP::$produtos[ $alheio_id ]['descricao'], 'Cadastro alheio intacto.' );
	}

	public function testTravaDesligadaVoltaAoCasamentoPorSkuPuro(): void {
		VH_Test_Env::atualizar_config( [ 'sku_prefixo' => '' ] );

		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'ISCA-001' ] );
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'ISCA-001' ] );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertSame( $tiny_id, (int) get_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, true ) );
		self::assertGreaterThan( 0, VH_Fake_HTTP::contar( 'PUT', 'produtos/' . $tiny_id ) );
	}

	public function testVinculoJaGravadoContinuaValendoMesmoSemPrefixo(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'ISCA-001' ] );
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'ISCA-001' ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertGreaterThan( 0, VH_Fake_HTTP::contar( 'PUT', 'produtos/' . $tiny_id ), 'Vínculo explícito é decisão do lojista.' );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'produtos' ) );
	}
}

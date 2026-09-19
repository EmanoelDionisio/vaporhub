<?php
/**
 * Fase 1 — o SKU da loja tem assinatura própria.
 *
 * A conta do Tiny é compartilhada. Sem um prefixo que diga “isto é da loja”,
 * qualquer código igual do outro lado vira casamento por SKU e a loja passa a
 * escrever em cadastro alheio.
 *
 * @package VaporHubLoja\Tests
 */

final class SkuPrefixoTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testPrefixoPadraoEhODaLoja(): void {
		self::assertSame( 'VH-', VH_Tiny_SKU::prefixo() );
	}

	public function testPrefixoConfiguradoEhNormalizadoComHifen(): void {
		VH_Tiny::salvar_config( [ 'ativo' => true, 'sku_prefixo' => 'loja vapor' ] );

		self::assertSame( 'LOJAVAPOR-', VH_Tiny_SKU::prefixo() );
	}

	public function testAplicarPrefixoNaoDuplicaOQueJaTem(): void {
		self::assertSame( 'VH-POD', VH_Tiny_SKU::aplicar_prefixo( 'POD' ) );
		self::assertSame( 'VH-POD', VH_Tiny_SKU::aplicar_prefixo( 'VH-POD' ) );
		self::assertSame( 'vh-pod', VH_Tiny_SKU::aplicar_prefixo( 'vh-pod' ), 'Reconhece o prefixo sem diferenciar caixa.' );
	}

	public function testSkuDaLojaSoReconheceOQueTemOPrefixo(): void {
		self::assertTrue( VH_Tiny_SKU::da_loja( 'VH-POD-001' ) );
		self::assertTrue( VH_Tiny_SKU::da_loja( 'vh-pod-001' ) );
		self::assertFalse( VH_Tiny_SKU::da_loja( 'POD-001' ) );
		self::assertFalse( VH_Tiny_SKU::da_loja( '' ) );
	}

	public function testPrefixoVazioDesligaATravaEAceitaQualquerSku(): void {
		VH_Tiny::salvar_config( [ 'ativo' => true, 'sku_prefixo' => '' ] );

		self::assertSame( '', VH_Tiny_SKU::prefixo() );
		self::assertTrue( VH_Tiny_SKU::da_loja( 'POD-001' ) );
		self::assertSame( 'POD-001', VH_Tiny_SKU::aplicar_prefixo( 'POD-001' ) );
	}

	public function testSkuGeradoNoPaiNasceComPrefixo(): void {
		$pai = VH_Test_Env::produto_variavel( [ 'azul' ], [ 'sku' => '' ] );

		$sku = VH_Tiny_SKU::garantir_sku_pai( $pai );

		self::assertSame( 'VH-CAMISETA-TESTE', $sku );
		self::assertSame( $sku, wc_get_product( $pai->get_id() )->get_sku() );
	}

	public function testColisaoDeSkuGeradoRecebeSufixo(): void {
		VH_Test_Env::produto_simples( [ 'sku' => 'VH-CAMISETA-TESTE' ] );
		$pai = VH_Test_Env::produto_variavel( [ 'azul' ], [ 'sku' => '' ] );

		self::assertSame( 'VH-CAMISETA-TESTE-2', VH_Tiny_SKU::garantir_sku_pai( $pai ) );
	}

	public function testSkuDeVariacaoHerdaOPrefixoDoPaiUmaVezSo(): void {
		$pai = VH_Test_Env::produto_variavel( [ 'azul' ], [ 'sku' => 'VH-VAR' ] );

		$sku = VH_Tiny_SKU::gerar_variacao( $pai, [ 'pa_cor' => 'verde' ] );

		self::assertSame( 'VH-VAR-VERDE', $sku );
		self::assertSame( 1, substr_count( strtoupper( $sku ), 'VH-' ) );
	}
}

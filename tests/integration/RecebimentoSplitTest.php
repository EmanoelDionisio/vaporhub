<?php
/**
 * Fase 1 — recebimento deixa de ser um interruptor só.
 *
 * Antes, ligar “receber do Tiny” abria catálogo e estoque juntos: com 1.790
 * itens na lixeira do ERP, isso significa lixo na vitrine. Agora são dois
 * interruptores independentes, ambos desligados por padrão.
 *
 * @package VaporHubLoja\Tests
 */

final class RecebimentoSplitTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testAmbosOsInterruptoresNascemDesligados(): void {
		$padrao = VH_Tiny::padrao();

		self::assertFalse( $padrao['receber_catalogo'] );
		self::assertFalse( $padrao['receber_estoque_preco'] );
	}

	public function testConfiguracaoAntigaDeRecebimentoLigadoViraOsDoisNovos(): void {
		update_option(
			VH_Tiny::OPTION,
			[
				'ativo'                => true,
				'modo'                 => 'v3',
				'permitir_recebimento' => true,
			],
			false
		);

		self::assertTrue( VH_Tiny::pode_receber_catalogo() );
		self::assertTrue( VH_Tiny::pode_receber_estoque_preco() );
	}

	public function testSalvarCredenciaisNaoLigaRecebimentoPorPadrao(): void {
		update_option( VH_Tiny::OPTION, [ 'ativo' => true, 'modo' => 'v3' ], false );

		VH_Tiny_Client::salvar_credenciais( 'client-novo', 'segredo-novo' );

		self::assertFalse( VH_Tiny::pode_receber_catalogo(), 'Salvar credenciais não pode abrir a entrada de cadastro.' );
		self::assertFalse( VH_Tiny::pode_receber_estoque_preco(), 'Nem a de estoque e preço.' );
	}

	public function testConfiguracaoAntigaDesligadaContinuaDesligada(): void {
		update_option(
			VH_Tiny::OPTION,
			[
				'ativo'                => true,
				'modo'                 => 'v3',
				'permitir_recebimento' => false,
			],
			false
		);

		self::assertFalse( VH_Tiny::pode_receber_catalogo() );
		self::assertFalse( VH_Tiny::pode_receber_estoque_preco() );
		self::assertFalse( VH_Tiny::pode_receber() );
	}

	public function testInterruptoresSaoIndependentes(): void {
		VH_Test_Env::atualizar_config(
			[
				'receber_catalogo'      => false,
				'receber_estoque_preco' => true,
			]
		);

		self::assertFalse( VH_Tiny::pode_receber_catalogo() );
		self::assertTrue( VH_Tiny::pode_receber_estoque_preco() );
		self::assertTrue( VH_Tiny::pode_receber(), 'pode_receber() responde “alguma direção de entrada está ligada”.' );
	}

	public function testSalvarConfigGravaOsDoisInterruptores(): void {
		VH_Tiny::salvar_config(
			[
				'ativo'                 => true,
				'receber_catalogo'      => true,
				'receber_estoque_preco' => false,
			]
		);

		$dados = VH_Tiny::obter();
		self::assertTrue( $dados['receber_catalogo'] );
		self::assertFalse( $dados['receber_estoque_preco'] );
	}

	public function testEstoquePullExigeOInterruptorDeEstoqueEPreco(): void {
		$produto = VH_Test_Env::produto_simples();
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, 4321 );
		VH_Tiny_Queue::enfileirar( 'estoque_pull', 'wc_' . $produto->get_id(), [ 'produto_id' => $produto->get_id() ] );

		/* Só catálogo ligado não autoriza puxar saldo. */
		VH_Test_Env::atualizar_config( [ 'receber_catalogo' => true, 'receber_estoque_preco' => false ] );
		VH_Tiny_Queue::processar_lote();

		self::assertSame( 'pending', VH_Test_Env::jobs( 'estoque_pull' )[0]['status'] );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'GET', 'estoque/' ) );
	}

	public function testStatusDoPainelExpoeOsDoisInterruptores(): void {
		VH_Test_Env::atualizar_config( [ 'receber_catalogo' => true, 'receber_estoque_preco' => false ] );

		$status = VH_Tiny::status_publico();

		self::assertTrue( $status['receber_catalogo'] );
		self::assertFalse( $status['receber_estoque_preco'] );
	}
}

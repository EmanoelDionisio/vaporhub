<?php
/**
 * Gravação parcial da configuração do Tiny.
 *
 * Serviços internos gravam uma chave só — a raiz de categoria depois de criada,
 * o mapa de atributos. Antes disso derrubava `ativo` e `sinc_auto`, porque a
 * gravação lia esses dois de um array que nunca os trouxe: a integração se
 * desligava no meio de uma sincronização e ninguém entendia por quê.
 *
 * @package VaporHubLoja\Tests
 */

final class ConfigParcialTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
		VH_Test_Env::configurar_tiny();
	}

	public function testGravarSoARaizNaoDesligaAIntegracao(): void {
		VH_Tiny::salvar_config(
			[
				'ativo'     => true,
				'sinc_auto' => true,
			]
		);

		VH_Tiny::salvar_config( [ 'categoria_raiz_id' => 4242 ] );

		$dados = VH_Tiny::obter();

		$this->assertTrue( (bool) $dados['ativo'], 'integração segue ativa' );
		$this->assertTrue( (bool) $dados['sinc_auto'], 'sincronização automática segue ligada' );
		$this->assertSame( 4242, (int) $dados['categoria_raiz_id'] );
	}

	public function testGravarSoOMapaDeAtributosNaoDesligaAIntegracao(): void {
		VH_Tiny::salvar_config(
			[
				'ativo'     => true,
				'sinc_auto' => true,
			]
		);

		VH_Tiny::salvar_config( [ 'map_atributos' => [ 'vh_cor' => 'Cor' ] ] );

		$dados = VH_Tiny::obter();

		$this->assertTrue( (bool) $dados['ativo'] );
		$this->assertSame( [ 'vh_cor' => 'Cor' ], $dados['map_atributos'] );
	}

	public function testTelaDeConfiguracaoContinuaPodendoDesligar(): void {
		VH_Tiny::salvar_config( [ 'ativo' => true, 'sinc_auto' => true ] );
		VH_Tiny::salvar_config( [ 'ativo' => false, 'sinc_auto' => false ] );

		$dados = VH_Tiny::obter();

		$this->assertFalse( (bool) $dados['ativo'], 'quem manda a chave explícita desliga' );
		$this->assertFalse( (bool) $dados['sinc_auto'] );
	}

	public function testGravacaoParcialNoClientTambemPreservaOsInterruptores(): void {
		VH_Tiny_Client::salvar_config(
			[
				'ativo'     => true,
				'sinc_auto' => true,
			]
		);

		VH_Tiny_Client::salvar_config( [ 'permitir_envio' => false ] );

		$dados = VH_Tiny_Client::obter();

		$this->assertTrue( (bool) $dados['ativo'] );
		$this->assertTrue( (bool) $dados['sinc_auto'] );
		$this->assertFalse( (bool) $dados['permitir_envio'] );
	}
}

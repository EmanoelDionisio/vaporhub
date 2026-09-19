<?php
/**
 * Fase 1 — a sincronização é de um CNPJ só: o da loja piloto.
 *
 * Trocar a conta no OAuth (ou apontar o mesmo client para outra empresa) tem de
 * derrubar a conexão em vez de começar a espelhar o catálogo de outra empresa.
 *
 * @package VaporHubLoja\Tests
 */

final class ContaTravadaTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	/**
	 * Simula o retorno do OAuth: state válido no transient + código de autorização.
	 */
	private function conectar( string $state = 'estado-de-teste' ): mixed {
		set_transient( 'vh_tiny_oauth_' . $state, 1, 600 );
		return VH_Tiny_Client::processar_callback( 'codigo-de-teste', $state );
	}

	public function testPrimeiraConexaoGravaCnpjDaConta(): void {
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => '', 'access_token' => '', 'refresh_token' => '' ] );

		self::assertTrue( true === $this->conectar() );

		$dados = VH_Tiny::obter();
		self::assertSame( VH_Fake_Tiny_ERP::CNPJ_LOJA, $dados['conta_cnpj'] );
		self::assertSame( 'VAPOR HUB COMERCIO LTDA', $dados['conta_nome'] );
	}

	public function testReconexaoComOMesmoCnpjSegueConectada(): void {
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );

		self::assertTrue( true === $this->conectar() );
		self::assertTrue( VH_Tiny_Client::conectado() );
		self::assertSame( '', VH_Tiny::obter()['ultimo_erro'] );
	}

	public function testReconexaoComOutroCnpjBloqueiaESeDesconecta(): void {
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );
		VH_Fake_Tiny_ERP::definir_cnpj( VH_Fake_Tiny_ERP::CNPJ_OUTRA );

		$resultado = $this->conectar();

		self::assertTrue( is_wp_error( $resultado ) );
		self::assertSame( 'vh_tiny_conta_diferente', $resultado->get_error_code() );
		self::assertFalse( VH_Tiny_Client::conectado(), 'Conta diferente derruba a conexão.' );
		self::assertNotEmpty( VH_Tiny::obter()['ultimo_erro'] );
		self::assertSame( VH_Fake_Tiny_ERP::CNPJ_LOJA, VH_Tiny::obter()['conta_cnpj'], 'A trava não é sobrescrita pela conta intrusa.' );
	}

	public function testVerificarContaAprovaOCnpjTravado(): void {
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );

		self::assertTrue( VH_Tiny_Client::verificar_conta() );
		self::assertTrue( VH_Tiny_Client::conectado() );
	}

	public function testVerificarContaDerrubaConexaoQuandoOCnpjMuda(): void {
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );
		VH_Fake_Tiny_ERP::definir_cnpj( VH_Fake_Tiny_ERP::CNPJ_OUTRA );

		self::assertFalse( VH_Tiny_Client::verificar_conta() );
		self::assertFalse( VH_Tiny_Client::conectado() );
		self::assertTrue( VH_Tiny_Log::contem( 'CNPJ', VH_Tiny_Log::NIVEL_ERROR ) );
	}

	public function testContaBloqueadaImpedeEnvioDeProduto(): void {
		$produto = VH_Test_Env::produto_simples();
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );
		VH_Fake_Tiny_ERP::definir_cnpj( VH_Fake_Tiny_ERP::CNPJ_OUTRA );
		VH_Tiny_Client::verificar_conta();

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertTrue( is_wp_error( $resultado ) );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'produtos' ) );
	}

	public function testReconciliacaoNaoRodaComContaBloqueada(): void {
		VH_Test_Env::produto_simples();
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );
		VH_Fake_Tiny_ERP::definir_cnpj( VH_Fake_Tiny_ERP::CNPJ_OUTRA );

		VH_Tiny_Sync_Service::reconciliar();

		self::assertSame( 0, VH_Test_Env::contar_jobs(), 'Conta errada do outro lado: nada entra na fila.' );
	}

	public function testStatusDoPainelMostraCnpjESituacaoDaTrava(): void {
		VH_Test_Env::configurar_tiny( [ 'conta_cnpj' => VH_Fake_Tiny_ERP::CNPJ_LOJA ] );

		$status = VH_Tiny::status_publico();

		self::assertSame( VH_Fake_Tiny_ERP::CNPJ_LOJA, $status['conta_cnpj'] );
		self::assertFalse( $status['conta_bloqueada'] );

		VH_Fake_Tiny_ERP::definir_cnpj( VH_Fake_Tiny_ERP::CNPJ_OUTRA );
		VH_Tiny_Client::verificar_conta();

		self::assertTrue( VH_Tiny::status_publico()['conta_bloqueada'] );
	}
}

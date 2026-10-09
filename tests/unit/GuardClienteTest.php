<?php
/**
 * A cerca manda o cliente para a conta e só encerra a sessão na segunda sonda.
 *
 * @package VaporHubLoja\Tests
 */

final class GuardClienteTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testClienteNaoIncluiEquipe(): void {
		self::assertTrue( VH_Guard::eh_cliente( true, false, false ) );
		self::assertFalse( VH_Guard::eh_cliente( true, true, false ) );
		self::assertFalse( VH_Guard::eh_cliente( true, false, true ) );
		self::assertFalse( VH_Guard::eh_cliente( false, false, false ) );
	}

	public function testSuperficieAdministrativa(): void {
		self::assertTrue( VH_Guard::eh_superficie( [
			'logado' => true,
			'uri'    => 'https://piloto.example/minha-loja/produtos/',
		] ) );
		self::assertTrue( VH_Guard::eh_superficie( [
			'logado' => true,
			'uri'    => 'https://piloto.example/minha-loja/entrar/',
		] ) );
		self::assertTrue( VH_Guard::eh_superficie( [
			'logado' => true,
			'script' => 'profile.php',
			'uri'    => 'https://piloto.example/wp-admin/profile.php',
		] ) );
		self::assertTrue( VH_Guard::eh_superficie( [
			'logado' => true,
			'script' => 'wp-login.php',
		] ) );
		self::assertFalse( VH_Guard::eh_superficie( [
			'logado' => true,
			'script' => 'admin-ajax.php',
			'uri'    => 'https://piloto.example/wp-admin/admin-ajax.php',
		] ) );
		self::assertFalse( VH_Guard::eh_superficie( [
			'logado' => true,
			'script' => 'admin-post.php',
			'uri'    => 'https://piloto.example/wp-admin/admin-post.php',
		] ) );
		self::assertFalse( VH_Guard::eh_superficie( [
			'logado' => true,
			'script' => 'wp-login.php',
			'acao'   => 'lostpassword',
		] ) );
		self::assertFalse( VH_Guard::eh_superficie( [
			'logado'  => false,
			'destino' => 'https://piloto.example/minha-conta/',
		] ) );
	}

	public function testLoginDaContaNaoContaEDestinoAdminConta(): void {
		self::assertFalse( VH_Guard::deve_sondar_login( true, '', 'https://piloto.example/minha-conta/' ) );
		self::assertFalse( VH_Guard::deve_sondar_login( false, '', 'https://piloto.example/wp-admin/' ) );
		self::assertTrue( VH_Guard::deve_sondar_login( true, 'https://piloto.example/wp-admin/', '' ) );
		self::assertTrue( VH_Guard::deve_sondar_login( true, '', 'https://piloto.example/minha-loja/' ) );
		self::assertFalse( VH_Guard::destino_e_admin( 'https://piloto.example/wp-login.php?action=lostpassword' ) );
	}

	public function testSegundaSondaEncerraASessao(): void {
		self::assertSame( 900, VH_Guard::JANELA );
		self::assertSame( 'redirecionar', VH_Guard::avaliar_sonda( 0 ) );
		self::assertSame( 'sair', VH_Guard::avaliar_sonda( 1 ) );
		self::assertSame( 'redirecionar', VH_Guard::resposta_sonda( 42 ) );
		self::assertSame( 'sair', VH_Guard::resposta_sonda( 42 ) );
		$conta = VH_Guard::url_conta();
		self::assertStringContainsString( '/minha-conta/', $conta );
		self::assertStringNotContainsString( 'vh_area', $conta );
		self::assertStringNotContainsString( 'wp-login.php', $conta );
	}

	public function testSenhaFicaNaConta(): void {
		$perdida = VH_Guard::url_senha( 'lostpassword' );
		self::assertStringContainsString( '/minha-conta/lost-password/', $perdida );
		self::assertStringNotContainsString( 'wp-login.php', $perdida );

		$redefinir = VH_Guard::url_senha( 'rp', 'Ab12', 'cliente.cerca', 8 );
		self::assertStringContainsString( '/minha-conta/lost-password/', $redefinir );
		self::assertStringContainsString( 'key=Ab12', $redefinir );
		self::assertStringContainsString( 'login=cliente.cerca', $redefinir );
		self::assertStringNotContainsString( 'wp-login.php', $redefinir );

		$mensagem = VH_Guard::trocar_link_senha(
			'Redefina em https://piloto.example/wp-login.php?action=rp&key=Ab12&login=cliente.cerca',
			'Ab12',
			'cliente.cerca'
		);
		self::assertStringNotContainsString( 'wp-login.php', $mensagem );
		self::assertStringContainsString( '/minha-conta/lost-password/', $mensagem );

		self::assertFalse( VH_Guard::deve_sair_do_login_wordpress( true, true ) );
		self::assertTrue( VH_Guard::deve_sair_do_login_wordpress( true, false ) );
		self::assertTrue( VH_Guard::deve_sair_do_login_wordpress( false, false ) );
		self::assertStringContainsString( 'erro=senha', VH_Guard::url_erro_senha() );
		self::assertStringNotContainsString( 'equipe', VH_Guard::url_erro_senha() );
	}

	public function testPortaDoWordpressFechaSemAConstante(): void {
		self::assertFalse( VH_Guard::porta_wordpress_aberta( null ) );
		self::assertFalse( VH_Guard::porta_wordpress_aberta( false ) );
		self::assertTrue( VH_Guard::porta_wordpress_aberta( true ) );
		self::assertFalse( VH_Guard::login_wordpress_liberado() );

		self::assertTrue( VH_Guard::deve_ocultar_login( false, false, '', 'GET' ) );
		self::assertTrue( VH_Guard::deve_ocultar_login( false, false, 'postpass', 'GET' ) );
		self::assertFalse( VH_Guard::deve_ocultar_login( true, false, '', 'GET' ) );
		self::assertFalse( VH_Guard::deve_ocultar_login( false, true, '', 'GET' ) );
		self::assertFalse( VH_Guard::deve_ocultar_login( false, false, 'lostpassword', 'GET' ) );
		self::assertFalse( VH_Guard::deve_ocultar_login( false, false, 'rp', 'GET' ) );
		self::assertFalse( VH_Guard::deve_ocultar_login( false, false, 'postpass', 'POST' ) );
		self::assertTrue( VH_Guard::deve_esconder_usuarios( false ) );
		self::assertFalse( VH_Guard::deve_esconder_usuarios( true ) );
	}

	public function testAutorEErroDeLogin(): void {
		self::assertTrue( VH_Guard::pedido_enumera_autor( true, '', '' ) );
		self::assertTrue( VH_Guard::pedido_enumera_autor( false, '1', '' ) );
		self::assertTrue( VH_Guard::pedido_enumera_autor( false, '', 'admin' ) );
		self::assertFalse( VH_Guard::pedido_enumera_autor( false, '0', '' ) );
		self::assertFalse( VH_Guard::pedido_enumera_autor( false, '', '' ) );

		$generico = VH_Guard::erro_login_generico( '<strong>Erro</strong>: o nome de usuário não está cadastrado neste site.' );
		self::assertSame( 'Usuário ou senha incorretos.', $generico );
		$portal = 'Gestores da loja devem acessar pelo portal: https://piloto.example/minha-loja/entrar/';
		self::assertSame( $portal, VH_Guard::erro_login_generico( $portal ) );
		self::assertSame( 'Cookies bloqueados.', VH_Guard::erro_login_generico( 'Cookies bloqueados.' ) );
	}
}

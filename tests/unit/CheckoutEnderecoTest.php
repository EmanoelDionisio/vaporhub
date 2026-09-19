<?php
/**
 * Número e bairro no endereço do checkout.
 *
 * O pedido só leva bairro e número ao ERP se a loja tiver pedido esses dados na
 * compra. Estes testes guardam a existência dos campos e das chaves de meta que
 * o mapeamento canônico lê depois.
 *
 * @package VaporHubLoja\Tests
 */

final class CheckoutEnderecoTest extends VH_Test_Case {

	public function testCampoDeNumeroEBairroEntramNoEnderecoPadrao(): void {
		$campos = VH_Checkout_Endereco::campos_endereco( self::campos_woo() );

		$this->assertTrue( isset( $campos['number'] ), 'campo de número existe' );
		$this->assertTrue( isset( $campos['neighborhood'] ), 'campo de bairro existe' );
		$this->assertTrue( (bool) $campos['number']['required'], 'número é obrigatório' );
		$this->assertTrue( (bool) $campos['neighborhood']['required'], 'bairro é obrigatório' );
	}

	public function testNumeroFicaLogoDepoisDoLogradouro(): void {
		$campos = VH_Checkout_Endereco::campos_endereco( self::campos_woo() );

		$this->assertTrue(
			$campos['number']['priority'] > $campos['address_1']['priority'],
			'número vem depois do logradouro'
		);
		$this->assertTrue(
			$campos['neighborhood']['priority'] > $campos['number']['priority'],
			'bairro vem depois do número'
		);
	}

	public function testFormatoBrasileiroImprimeNumeroEBairro(): void {
		$formatos = VH_Checkout_Endereco::formato_br( [ 'BR' => "{name}\n{address_1}" ] );

		$this->assertStringContainsString( '{number}', $formatos['BR'] );
		$this->assertStringContainsString( '{neighborhood}', $formatos['BR'] );
	}

	public function testEnderecoFormatadoBuscaAsMetasQueOErpUsa(): void {
		$pedido = new WC_Order( [ 'id' => 9001 ] );
		$pedido->update_meta_data( '_billing_number', '123' );
		$pedido->update_meta_data( '_billing_neighborhood', 'Centro' );

		$endereco = VH_Checkout_Endereco::formatar_cobranca( [ 'address_1' => 'Rua das Palmeiras' ], $pedido );

		$this->assertSame( '123', $endereco['number'] );
		$this->assertSame( 'Centro', $endereco['neighborhood'] );
	}

	public function testSubstituicaoNaoDeixaPlaceholderSobrandoQuandoFalta(): void {
		$substituicoes = VH_Checkout_Endereco::substituicoes( [], [ 'number' => '' ] );

		$this->assertSame( '', $substituicoes['{number}'] );
		$this->assertSame( '', $substituicoes['{neighborhood}'] );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function campos_woo(): array {
		return [
			'address_1' => [
				'label'    => 'Endereço',
				'required' => true,
				'priority' => 50,
			],
			'address_2' => [
				'label'    => 'Complemento',
				'required' => false,
				'priority' => 60,
			],
			'city'      => [
				'label'    => 'Cidade',
				'required' => true,
				'priority' => 70,
			],
		];
	}
}

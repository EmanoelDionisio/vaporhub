<?php
/**
 * Fase 6 — o pedido que sai da loja chega inteiro no ERP.
 *
 * O que faltava: valor do frete, forma de pagamento e bairro/número separados.
 * A API v3 ainda exige `idContato`, então o cliente precisa existir em Contatos
 * antes do pedido — e não pode virar cadastro duplicado a cada compra.
 *
 * @package VaporHubLoja\Tests
 */

final class PedidoCompletoTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	/**
	 * Produto já vinculado ao ERP, como fica depois do primeiro envio.
	 */
	private function produto_vinculado( string $sku = 'VH-POD-001' ): WC_Product_Simple {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => $sku ] );
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => $sku ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		return $produto;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function corpo_do_pedido(): array {
		return (array) VH_Fake_HTTP::ultimo_corpo( 'POST', 'pedidos' );
	}

	public function testCanonicoTrazFreteFormaDePagamentoBairroENumero(): void {
		$pedido = VH_Test_Env::pedido(
			[ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 2, 'total' => 99.8 ] ],
			[
				'endereco'  => 'Rua das Palmeiras',
				'frete'     => 24.5,
				'pagamento_titulo' => 'Pix',
			]
		);
		$pedido->update_meta_data( '_billing_number', '123' );
		$pedido->update_meta_data( '_billing_neighborhood', 'Centro' );

		$canonico = VH_Tiny_Map::pedido_para_canonico( $pedido );

		self::assertSame( 24.5, $canonico['frete']['valor'] );
		self::assertSame( 'PAC', $canonico['frete']['servico'] );
		self::assertSame( 'Pix', $canonico['pagamento']['metodo'] );
		self::assertSame( 'pix', $canonico['pagamento']['codigo'] );
		self::assertSame( '123', $canonico['entrega']['numero'] );
		self::assertSame( 'Centro', $canonico['entrega']['bairro'] );
		self::assertSame( 'Rua das Palmeiras', $canonico['entrega']['endereco'] );
	}

	public function testNumeroNoFimDoLogradouroViraCampoSeparado(): void {
		$pedido = VH_Test_Env::pedido(
			[ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 1, 'total' => 49.9 ] ],
			[ 'endereco' => 'Avenida Beira Rio, 1580' ]
		);

		$canonico = VH_Tiny_Map::pedido_para_canonico( $pedido );

		self::assertSame( 'Avenida Beira Rio', $canonico['entrega']['endereco'] );
		self::assertSame( '1580', $canonico['entrega']['numero'] );
	}

	public function testPedidoSemFreteNaoInventaCampo(): void {
		$pedido = VH_Test_Env::pedido(
			[ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 1, 'total' => 49.9 ] ],
			[
				'frete'        => 0.0,
				'metodo_envio' => '',
				'total'        => 49.9,
			]
		);

		$canonico = VH_Tiny_Map::pedido_para_canonico( $pedido );
		self::assertArrayNotHasKey( 'frete', $canonico );

		self::assertFalse( is_wp_error( VH_Tiny::driver()->criar_pedido( $canonico ) ) );

		$body = $this->corpo_do_pedido();
		self::assertArrayNotHasKey( 'valorFrete', $body );
	}

	public function testPayloadV3LevaFreteEnderecoSeparadoEPagamento(): void {
		$pedido = VH_Test_Env::pedido(
			[ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 2, 'total' => 99.8 ] ],
			[
				'endereco' => 'Rua das Palmeiras',
				'frete'    => 24.5,
				'total'    => 124.3,
			]
		);
		$pedido->update_meta_data( '_billing_number', '123' );
		$pedido->update_meta_data( '_billing_neighborhood', 'Centro' );
		$pedido->update_meta_data( '_billing_cpf', '529.982.247-25' );

		$resposta = VH_Tiny::driver()->criar_pedido( VH_Tiny_Map::pedido_para_canonico( $pedido ) );
		self::assertFalse( is_wp_error( $resposta ) );

		$body = $this->corpo_do_pedido();

		self::assertSame( 24.5, (float) $body['valorFrete'] );
		self::assertSame( 'Rua das Palmeiras', $body['enderecoEntrega']['endereco'] );
		self::assertSame( '123', $body['enderecoEntrega']['enderecoNro'] );
		self::assertSame( 'Centro', $body['enderecoEntrega']['bairro'] );
		self::assertSame( 'Porto Alegre', $body['enderecoEntrega']['municipio'] );
		self::assertSame( 'Pix', $body['pagamento']['parcelas'][0]['observacoes'] );
		self::assertSame( 124.3, (float) $body['pagamento']['parcelas'][0]['valor'] );
	}

	public function testNumeracaoDaLojaVaiNoBlocoEcommerce(): void {
		$pedido = VH_Test_Env::pedido(
			[ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 1, 'total' => 49.9 ] ],
			[ 'numero' => '1042' ]
		);

		VH_Tiny::driver()->criar_pedido( VH_Tiny_Map::pedido_para_canonico( $pedido ) );

		$body = $this->corpo_do_pedido();
		self::assertSame( 'piloto.example-1042', $body['ecommerce']['numeroPedidoEcommerce'] );
		self::assertSame( 'piloto.example-1042', $body['numeroOrdemCompra'] );
	}

	public function testItemVaiComIdDoProdutoNoErp(): void {
		$produto = $this->produto_vinculado();
		$tiny_id = (int) get_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, true );

		$pedido = VH_Test_Env::pedido( [ [ 'produto' => $produto, 'quantidade' => 3, 'total' => 149.7 ] ] );

		VH_Tiny::driver()->criar_pedido( VH_Tiny_Map::pedido_para_canonico( $pedido ) );

		$body = $this->corpo_do_pedido();
		self::assertSame( $tiny_id, (int) $body['itens'][0]['produto']['id'] );
		self::assertSame( 3.0, (float) $body['itens'][0]['quantidade'] );
		self::assertSame( 49.9, (float) $body['itens'][0]['valorUnitario'] );
	}

	public function testClienteNovoVirandoContatoAntesDoPedido(): void {
		$pedido = VH_Test_Env::pedido( [ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 1, 'total' => 49.9 ] ] );
		$pedido->update_meta_data( '_billing_cpf', '529.982.247-25' );

		VH_Tiny::driver()->criar_pedido( VH_Tiny_Map::pedido_para_canonico( $pedido ) );

		$contato = VH_Fake_HTTP::ultimo_corpo( 'POST', 'contatos' );
		self::assertSame( 'Cliente Teste', $contato['nome'] );
		self::assertSame( '52998224725', $contato['cpfCnpj'] );
		self::assertSame( 'F', $contato['tipoPessoa'] );

		$body = $this->corpo_do_pedido();
		self::assertTrue( (int) $body['idContato'] > 0, 'O pedido nasce amarrado ao contato.' );
	}

	public function testClienteQueJaExisteEmContatosNaoEDuplicado(): void {
		$contato_id = VH_Fake_Tiny_ERP::seed_contato(
			[
				'nome'    => 'Cliente Antigo',
				'cpfCnpj' => '52998224725',
			]
		);

		$pedido = VH_Test_Env::pedido( [ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 1, 'total' => 49.9 ] ] );
		$pedido->update_meta_data( '_billing_cpf', '529.982.247-25' );

		VH_Tiny::driver()->criar_pedido( VH_Tiny_Map::pedido_para_canonico( $pedido ) );

		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'contatos' ), 'Contato existente é reaproveitado.' );
		self::assertSame( $contato_id, (int) $this->corpo_do_pedido()['idContato'] );
	}

	public function testContatoResolvidoUmaVezFicaGravadoNoPedido(): void {
		$produto = $this->produto_vinculado();
		$pedido  = VH_Test_Env::pedido( [ [ 'produto' => $produto, 'quantidade' => 1, 'total' => 49.9 ] ] );
		$pedido->update_meta_data( '_billing_cpf', '529.982.247-25' );

		self::assertFalse( is_wp_error( VH_Tiny_Sync_Service::empurrar_pedido( $pedido->get_id() ) ) );

		$contato_id = (int) $pedido->get_meta( VH_Tiny_Map::META_TINY_CONTATO );
		self::assertTrue( $contato_id > 0 );
		self::assertTrue( (int) $pedido->get_meta( VH_Tiny_Map::META_TINY_PEDIDO ) > 0 );

		$buscas_antes = VH_Fake_HTTP::contar( 'GET', 'contatos' );

		/* Segundo envio do mesmo pedido não reabre a busca de contato. */
		$pedido->update_meta_data( VH_Tiny_Map::META_TINY_PEDIDO, '' );
		VH_Tiny_Sync_Service::empurrar_pedido( $pedido->get_id() );

		self::assertSame( $buscas_antes, VH_Fake_HTTP::contar( 'GET', 'contatos' ) );
		self::assertSame( $contato_id, (int) $this->corpo_do_pedido()['idContato'] );
	}

	public function testItemSemVinculoNoErpSeguraOPedidoEmVezDeMandarQuebrado(): void {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => 'VH-SEM-VINCULO' ] );
		$pedido  = VH_Test_Env::pedido( [ [ 'produto' => $produto, 'quantidade' => 1, 'total' => 49.9 ] ] );

		$resposta = VH_Tiny_Sync_Service::empurrar_pedido( $pedido->get_id() );

		self::assertTrue( is_wp_error( $resposta ) );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'pedidos' ) );
		self::assertSame( '', (string) $pedido->get_meta( VH_Tiny_Map::META_TINY_PEDIDO ) );
	}

	public function testEnderecoDeEntregaPrefereODaEntregaQuandoPreenchido(): void {
		$pedido = VH_Test_Env::pedido(
			[ [ 'produto' => $this->produto_vinculado(), 'quantidade' => 1, 'total' => 49.9 ] ],
			[
				'entrega_endereco' => 'Estrada do Lago, 45',
				'entrega_cidade'   => 'Viamão',
				'entrega_uf'       => 'RS',
				'entrega_cep'      => '94400000',
			]
		);
		$pedido->update_meta_data( '_shipping_neighborhood', 'Águas Claras' );

		$canonico = VH_Tiny_Map::pedido_para_canonico( $pedido );

		self::assertSame( 'Estrada do Lago', $canonico['entrega']['endereco'] );
		self::assertSame( '45', $canonico['entrega']['numero'] );
		self::assertSame( 'Águas Claras', $canonico['entrega']['bairro'] );
		self::assertSame( 'Viamão', $canonico['entrega']['cidade'] );
	}

	public function testDescontoSoVaiQuandoExiste(): void {
		$produto = $this->produto_vinculado();

		$sem = VH_Tiny_Map::pedido_para_canonico(
			VH_Test_Env::pedido( [ [ 'produto' => $produto, 'quantidade' => 1, 'total' => 49.9 ] ] )
		);
		self::assertArrayNotHasKey( 'desconto', $sem );

		$com = VH_Tiny_Map::pedido_para_canonico(
			VH_Test_Env::pedido(
				[ [ 'produto' => $produto, 'quantidade' => 1, 'total' => 49.9 ] ],
				[ 'desconto' => 10.0 ]
			)
		);
		self::assertSame( 10.0, $com['desconto'] );

		VH_Tiny::driver()->criar_pedido( $com );
		self::assertSame( 10.0, (float) $this->corpo_do_pedido()['valorDesconto'] );
	}
}

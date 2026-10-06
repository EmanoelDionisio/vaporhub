<?php
/**
 * Caracterização do payload v3 no código atual (3.9.15).
 *
 * Descreve o que HOJE sai para o Tiny. Serve de rede: qualquer fase seguinte que
 * mude essas formas sem intenção aparece como teste vermelho aqui.
 *
 * @package VaporHubLoja\Tests
 */

final class BaselinePayloadV3Test extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testProdutoSimplesEnviaDescricaoSkuPrecoEEstoque(): void {
		$produto = VH_Test_Env::produto_simples();

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );
		self::assertTrue( true === $resultado, 'Envio deveria concluir; erro: ' . ( is_wp_error( $resultado ) ? $resultado->get_error_message() : '' ) );

		$body = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );

		self::assertSame( 'Pod Descartavel Teste', $body['descricao'] );
		self::assertSame( 'S', $body['tipo'] );
		self::assertSame( 'A', $body['situacao'] );
		self::assertSame( 'VH-POD-001', $body['sku'] );
		self::assertSame( 49.9, (float) $body['precos']['preco'] );
		self::assertTrue( $body['estoque']['controlar'] );
		self::assertSame( 10.0, (float) $body['estoque']['inicial'] );
	}

	public function testProdutoNaoPublicadoViraSituacaoInativa(): void {
		$produto = VH_Test_Env::produto_simples( [ 'status' => 'draft' ] );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertSame( 'I', VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' )['situacao'] );
	}

	public function testImagemVaiComoAnexoInternoParaOTinyImportar(): void {
		$produto = VH_Test_Env::produto_simples();
		$produto->set_image_id( 4242 );
		$produto->save();

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		$body = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );

		self::assertCount( 1, $body['anexos'] );
		self::assertFalse( $body['anexos'][0]['externo'], 'externo=false faz o Tiny importar a imagem em vez de só apontar para ela.' );
		self::assertStringContainsString( 'img-4242', $body['anexos'][0]['url'] );
	}

	public function testProdutoVariavelEnviaTipoVGradeESemPrecoNoPai(): void {
		$pai = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $pai->get_id() );
		self::assertTrue( true === $resultado, 'Envio deveria concluir; erro: ' . ( is_wp_error( $resultado ) ? $resultado->get_error_message() : '' ) );

		$body = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' );

		self::assertSame( 'V', $body['tipo'] );
		self::assertSame( [ 'Cor' ], $body['grade'], 'A grade do pai carrega os rótulos dos eixos.' );
		self::assertArrayNotHasKey( 'precos', $body, 'Preço mora na variação, não no pai.' );
		self::assertArrayNotHasKey( 'estoque', $body, 'Estoque mora na variação, não no pai.' );
		self::assertCount( 2, $body['variacoes'] );
		self::assertSame( 'VH-VAR-AZUL', $body['variacoes'][0]['sku'] );
		self::assertSame( 'Cor', $body['variacoes'][0]['grade'][0]['chave'] );
		self::assertSame( 89.9, $body['variacoes'][0]['precos']['preco'] );
	}

	public function testPesoSaiComoPesoBrutoELiquidoIguais(): void {
		$produto = VH_Test_Env::produto_simples( [ 'peso' => '0.280' ] );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		$dimensoes = VH_Fake_HTTP::ultimo_corpo( 'POST', 'produtos' )['dimensoes'];

		self::assertSame( 0.28, $dimensoes['pesoBruto'] );
		self::assertSame( 0.28, $dimensoes['pesoLiquido'] );
	}

	public function testPrecoEImagemVemDoCadastroTiny(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'       => 'VIDRO-1',
				'descricao' => 'Tubo de vidro',
				'preco'     => 0,
				'precos'    => [
					'preco'            => 29.9,
					'precoPromocional' => 0,
				],
				'anexos'    => [
					[ 'url' => 'https://anexos.tiny.com.br/erp/tubo.jpg' ],
				],
				'variacoes' => [
					[
						'id'      => 9001,
						'codigo'  => 'VIDRO-1-AZUL',
						'preco'   => 0,
						'precos'  => [
							'preco'            => 359,
							'precoPromocional' => 269.9,
						],
						'grade'   => [
							[ 'chave' => 'Cor', 'valor' => 'BLUE' ],
						],
						'estoque' => [
							'controlar'  => true,
							'quantidade' => 22,
						],
					],
				],
			]
		);

		$canonico = VH_Tiny::driver()->obter_produto( $tiny_id );

		self::assertSame( '29.9', $canonico['preco_regular'] );
		self::assertArrayNotHasKey( 'preco_promo', $canonico );
		self::assertSame( [ 'https://anexos.tiny.com.br/erp/tubo.jpg' ], $canonico['imagens'] );
		self::assertSame( '359', $canonico['variacoes'][0]['preco_regular'] );
		self::assertSame( '269.9', $canonico['variacoes'][0]['preco_promo'] );
	}

	public function testSituacaoExcluidaChegaNoCanonicoComoNaoPublicado(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'       => 'LIXO-1',
				'descricao' => 'Item na lixeira',
				'situacao'  => 'E',
			]
		);

		$canonico = VH_Tiny::driver()->obter_produto( $tiny_id );

		self::assertSame( 'E', $canonico['situacao'] );
		self::assertFalse( $canonico['publicado'] );
	}

	public function testWebhookClassificaEstoqueProdutoEDesconhecido(): void {
		$driver = VH_Tiny::driver();

		$estoque = $driver->normalizar_webhook( [ 'tipo' => 'estoque.alterado', 'idProduto' => 55, 'codigo' => 'VH-1' ] );
		self::assertSame( 'estoque', $estoque['tipo'] );
		self::assertSame( 55, $estoque['tiny_id'] );
		self::assertSame( 'VH-1', $estoque['sku'] );

		$produto = $driver->normalizar_webhook( [ 'tipo' => 'produto.alterado', 'id' => 77 ] );
		self::assertSame( 'produto', $produto['tipo'] );
		self::assertSame( 77, $produto['tiny_id'] );

		/* sanitize_key remove o ponto: “nota.emitida” chega como “notaemitida”. */
		$outro = $driver->normalizar_webhook( [ 'tipo' => 'nota.emitida' ] );
		self::assertSame( 'notaemitida', $outro['tipo'] );
		self::assertSame( 0, $outro['tiny_id'] );

		$vazio = $driver->normalizar_webhook( [] );
		self::assertSame( 'desconhecido', $vazio['tipo'] );
	}

	/**
	 * Expectativa alterada de propósito na Fase 6 (v3.14.0).
	 *
	 * A baseline afirmava `numeroPedidoEcommerce` na raiz e item por `codigo`,
	 * como o código fazia. O schema `CriarPedidoModelRequest` da v3 põe o número
	 * da loja em `ecommerce.numeroPedidoEcommerce` e exige `itens[].produto.id`:
	 * o formato antigo era aceito na chamada e ignorado no cadastro. O que a
	 * baseline protege segue igual — a numeração continua prefixada pela loja.
	 */
	public function testPedidoRecebeNumeracaoPrefixadaPelaLoja(): void {
		$resposta = VH_Tiny::driver()->criar_pedido(
			[
				'numero'      => '1042',
				'origem_loja' => 'piloto.example',
				'data'        => '2026-09-17',
				'cliente'     => [ 'nome' => 'Cliente Teste', 'email' => 'cliente@exemplo.com' ],
				'entrega'     => [ 'cep' => '01001000' ],
				'itens'       => [
					[ 'id_tiny' => 4321, 'sku' => 'VH-POD-001', 'nome' => 'Pod', 'quantidade' => 2, 'valor_unitario' => 49.9 ],
				],
				'total'       => 99.8,
			]
		);

		self::assertFalse( is_wp_error( $resposta ) );

		$body = VH_Fake_HTTP::ultimo_corpo( 'POST', 'pedidos' );

		self::assertSame( 'piloto.example-1042', $body['ecommerce']['numeroPedidoEcommerce'] );
		self::assertSame( 4321, (int) $body['itens'][0]['produto']['id'] );
		self::assertSame( 2.0, (float) $body['itens'][0]['quantidade'] );
	}

	public function testBalancoNaoRepeteLancamentoQuandoSaldoJaBate(): void {
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'     => 'VH-POD-001',
				'estoque' => [ 'controlar' => true, 'saldo' => 7 ],
			]
		);

		$canonico = [
			'tipo'             => 'simples',
			'gerencia_estoque' => true,
			'estoque'          => 7,
			'preco_regular'    => '49.90',
		];

		self::assertTrue( true === VH_Tiny::driver()->sincronizar_estoque( $tiny_id, $canonico ) );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'estoque/' ), 'Saldo igual no ERP não deve gerar lançamento.' );

		$canonico['estoque'] = 4;
		self::assertTrue( true === VH_Tiny::driver()->sincronizar_estoque( $tiny_id, $canonico ) );
		self::assertSame( 1, VH_Fake_HTTP::contar( 'POST', 'estoque/' ), 'Saldo diferente gera um lançamento de balanço.' );
		self::assertSame( 'B', VH_Fake_HTTP::ultimo_corpo( 'POST', 'estoque/' )['tipo'] );
		self::assertSame( 4.0, (float) VH_Fake_HTTP::ultimo_corpo( 'POST', 'estoque/' )['quantidade'] );
	}
}

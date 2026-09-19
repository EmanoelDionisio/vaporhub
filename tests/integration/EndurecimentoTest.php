<?php
/**
 * Fase 7 — as duas travas que evitam estrago irreversível.
 *
 * A primeira é a mais importante da suíte: sincronizar imagem NÃO pode deletar
 * o cadastro no ERP. O fluxo antigo apagava o produto para recriá-lo com anexo
 * interno; se a recriação falhasse, o produto desaparecia do Tiny.
 *
 * A segunda é o webhook: token em header com hash guardado, sem deixar de
 * aceitar a URL antiga enquanto o painel do ERP não é atualizado.
 *
 * @package VaporHubLoja\Tests
 */

final class EndurecimentoTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	/**
	 * DELETEs de produto (não de variação nem de anexo) que saíram na rodada.
	 */
	private function deletes_de_produto(): int {
		$total = 0;
		foreach ( VH_Fake_HTTP::requisicoes( 'DELETE' ) as $req ) {
			if ( preg_match( '#^produtos/\d+$#', (string) $req['endpoint'] ) ) {
				++$total;
			}
		}
		return $total;
	}

	private function produto_com_imagem( string $sku = 'VH-POD-001' ): WC_Product_Simple {
		$produto = VH_Test_Env::produto_simples( [ 'sku' => $sku ] );
		$produto->set_image_id( 4242 );
		$produto->save();

		return $produto;
	}

	public function testImagemEmProdutoJaExistenteNaoDisparaDeleteDoProduto(): void {
		$produto = $this->produto_com_imagem();
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'    => 'VH-POD-001',
				'anexos' => [ [ 'url' => 'https://piloto.example/img-antiga.jpg', 'externo' => true ] ],
			]
		);
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertTrue( true === $resultado, 'Envio deveria concluir; erro: ' . ( is_wp_error( $resultado ) ? $resultado->get_error_message() : '' ) );
		self::assertSame( 0, $this->deletes_de_produto(), 'Nenhum DELETE de produto por causa de imagem.' );
		self::assertSame( $tiny_id, (int) get_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, true ), 'O vínculo continua o mesmo.' );
	}

	public function testImagemVaiPeloEndpointDeAnexosComExternoFalso(): void {
		$produto = $this->produto_com_imagem();
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'VH-POD-001' ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		$corpo = VH_Fake_HTTP::ultimo_corpo( 'PUT', 'produtos/' . $tiny_id . '/anexos' );

		self::assertCount( 1, $corpo );
		self::assertFalse( $corpo[0]['externo'], 'externo=false faz o Tiny hospedar a imagem.' );
		self::assertStringContainsString( 'img-4242', (string) $corpo[0]['url'] );
	}

	public function testImagemJaImportadaNoErpNaoEReenviada(): void {
		$produto = $this->produto_com_imagem();
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'    => 'VH-POD-001',
				'anexos' => [ [ 'id' => 9, 'url' => 'https://tiny-anexos.s3.amazonaws.com/ja-importada.jpg', 'externo' => false ] ],
			]
		);
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertSame( 0, VH_Fake_HTTP::contar( 'PUT', 'produtos/' . $tiny_id . '/anexos' ) );
		self::assertSame( 0, $this->deletes_de_produto() );
	}

	public function testFalhaNoEnvioDaImagemNaoDerrubaOProdutoNemOVinculo(): void {
		$produto = $this->produto_com_imagem();
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'VH-POD-001' ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Fake_Tiny_ERP::$falhas_forcadas[ 'PUT produtos/' . $tiny_id . '/anexos' ] = [
			'status' => 500,
			'body'   => [ 'message' => 'Falha ao importar anexo.' ],
		];

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $produto->get_id() );

		self::assertTrue( true === $resultado, 'Imagem é acessório: falha nela não derruba o envio.' );
		self::assertSame( 0, $this->deletes_de_produto() );
		self::assertSame( $tiny_id, (int) get_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, true ) );
		self::assertTrue( VH_Tiny_Log::contem( 'imagem' ) || VH_Tiny_Log::contem( 'Imagem' ), 'A falha da imagem fica registrada.' );
	}

	/**
	 * Produto no ERP com grade de dois eixos — incompatível com a loja, que tem um.
	 */
	private function seed_grade_de_dois_eixos( string $sku ): int {
		return VH_Fake_Tiny_ERP::seed_produto(
			[
				'sku'       => $sku,
				'tipo'      => 'V',
				'variacoes' => [
					[
						'id'     => VH_Fake_Tiny_ERP::novo_id(),
						'codigo' => $sku . '-AZUL-M',
						'grade'  => [
							[ 'descricao' => 'Cor', 'valor' => 'azul' ],
							[ 'descricao' => 'Tamanho', 'valor' => 'M' ],
						],
					],
				],
			]
		);
	}

	public function testRecriacaoPorGradeTrocaOVinculoSemDeixarRastroPendente(): void {
		$pai     = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );
		$tiny_id = $this->seed_grade_de_dois_eixos( (string) $pai->get_sku() );
		update_post_meta( $pai->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $pai->get_id() );
		self::assertTrue( true === $resultado, 'Envio deveria concluir; erro: ' . ( is_wp_error( $resultado ) ? $resultado->get_error_message() : '' ) );

		$novo = (int) get_post_meta( $pai->get_id(), VH_Tiny_Map::META_TINY_ID, true );
		self::assertTrue( $novo > 0 && $novo !== $tiny_id, 'O produto passa a apontar para o cadastro novo.' );
		self::assertSame( '', (string) get_post_meta( $pai->get_id(), VH_Tiny_Map::META_TINY_ID_ANTERIOR, true ), 'Recriação concluída não deixa marca de destrutivo pendente.' );
		self::assertTrue( VH_Tiny_Log::contem( 'Recriando cadastro' ), 'A recriação por grade fica registrada.' );
	}

	public function testFalhaNaRecriacaoPorGradeNaoDeixaOProdutoOrfaoSemRastro(): void {
		$pai     = VH_Test_Env::produto_variavel( [ 'azul', 'verde' ] );
		$tiny_id = $this->seed_grade_de_dois_eixos( (string) $pai->get_sku() );
		update_post_meta( $pai->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		VH_Fake_Tiny_ERP::$falhas_forcadas['POST produtos'] = [
			'status' => 500,
			'body'   => [ 'message' => 'Indisponível.' ],
		];

		$resultado = VH_Tiny_Sync_Service::empurrar_produto( $pai->get_id() );

		self::assertTrue( is_wp_error( $resultado ), 'O job volta com erro para a fila tentar de novo.' );
		self::assertSame( $tiny_id, (int) get_post_meta( $pai->get_id(), VH_Tiny_Map::META_TINY_ID_ANTERIOR, true ) );
		self::assertTrue( VH_Tiny_Log::contem( 'órfão', VH_Tiny_Log::NIVEL_ERROR ), 'O órfão no ERP é gritado no log.' );
	}

	public function testWebhookAceitaTokenNoHeader(): void {
		$token = VH_Tiny::rotacionar_webhook_token();

		$resposta = ( new VH_REST_Tiny() )->webhook(
			new WP_REST_Request(
				[],
				[ 'X-PA-Tiny-Token' => $token ],
				(string) wp_json_encode( [ 'id' => 'evt-1', 'tipo' => 'produto.alterado', 'id_produto' => 999 ] )
			)
		);

		self::assertFalse( is_wp_error( $resposta ) );
		self::assertSame( 200, $resposta->get_status() );
	}

	public function testWebhookComTokenInvalidoERecusadoNoHeaderENaUrl(): void {
		VH_Tiny::rotacionar_webhook_token();
		$rest = new VH_REST_Tiny();

		$pelo_header = $rest->webhook(
			new WP_REST_Request( [], [ 'X-PA-Tiny-Token' => 'token-errado' ], (string) wp_json_encode( [ 'id' => 'evt-2' ] ) )
		);
		self::assertTrue( is_wp_error( $pelo_header ) );
		self::assertSame( 'vh_tiny_webhook_token', $pelo_header->get_error_code() );

		$pela_url = $rest->webhook(
			new WP_REST_Request( [ 'token' => 'token-errado' ], [], (string) wp_json_encode( [ 'id' => 'evt-3' ] ) )
		);
		self::assertTrue( is_wp_error( $pela_url ) );
		self::assertSame( 'vh_tiny_webhook_token', $pela_url->get_error_code() );
	}

	public function testTokenFicaGuardadoComoHashNaOpcao(): void {
		$token = VH_Tiny::rotacionar_webhook_token();
		$dados = get_option( VH_Tiny::OPTION, [] );

		self::assertSame( hash( 'sha256', $token ), (string) $dados['webhook_token_hash'] );
		self::assertStringNotContainsString( $token, (string) $dados['webhook_token_hash'] );
		self::assertStringNotContainsString( $token, (string) ( $dados['webhook_token_cifrado'] ?? '' ), 'O token só volta descriptografado.' );
		self::assertSame( $token, VH_Tiny::webhook_token() );
	}

	public function testUrlLegadaContinuaValendoComTokenEmTextoPuro(): void {
		/* Instalação antiga: a opção guardava o token puro no campo “hash”. */
		VH_Test_Env::atualizar_config( [ 'webhook_token_hash' => 'token-antigo-em-texto-puro' ] );

		$resposta = ( new VH_REST_Tiny() )->webhook(
			new WP_REST_Request(
				[ 'token' => 'token-antigo-em-texto-puro' ],
				[],
				(string) wp_json_encode( [ 'id' => 'evt-4', 'tipo' => 'estoque.alterado', 'idProduto' => 1 ] )
			)
		);

		self::assertFalse( is_wp_error( $resposta ) );

		$dados = get_option( VH_Tiny::OPTION, [] );
		self::assertSame(
			hash( 'sha256', 'token-antigo-em-texto-puro' ),
			(string) $dados['webhook_token_hash'],
			'O primeiro webhook aceito migra o armazenamento para hash.'
		);
	}

	public function testRotacaoInvalidaOTokenAnterior(): void {
		$antigo = VH_Tiny::rotacionar_webhook_token();
		$novo   = VH_Tiny::rotacionar_webhook_token();

		self::assertTrue( $antigo !== $novo );

		$rest = new VH_REST_Tiny();

		$com_antigo = $rest->webhook(
			new WP_REST_Request( [], [ 'X-PA-Tiny-Token' => $antigo ], (string) wp_json_encode( [ 'id' => 'evt-5' ] ) )
		);
		self::assertTrue( is_wp_error( $com_antigo ) );

		$com_novo = $rest->webhook(
			new WP_REST_Request( [], [ 'X-PA-Tiny-Token' => $novo ], (string) wp_json_encode( [ 'id' => 'evt-6' ] ) )
		);
		self::assertFalse( is_wp_error( $com_novo ) );
	}

	public function testWebhookApenasEnfileiraSemProcessarNaHora(): void {
		VH_Test_Env::atualizar_config( [ 'receber_estoque_preco' => true ] );

		$produto = VH_Test_Env::produto_simples();
		$tiny_id = VH_Fake_Tiny_ERP::seed_produto( [ 'sku' => 'VH-POD-001' ] );
		update_post_meta( $produto->get_id(), VH_Tiny_Map::META_TINY_ID, $tiny_id );

		$token       = VH_Tiny::rotacionar_webhook_token();
		$http_antes  = VH_Fake_HTTP::contar( 'GET', 'produtos/' . $tiny_id );

		( new VH_REST_Tiny() )->webhook(
			new WP_REST_Request(
				[],
				[ 'X-PA-Tiny-Token' => $token ],
				(string) wp_json_encode( [ 'id' => 'evt-7', 'tipo' => 'estoque.alterado', 'idProduto' => $tiny_id, 'codigo' => 'VH-POD-001' ] )
			)
		);

		self::assertSame( 1, VH_Test_Env::contar_jobs( 'estoque_pull' ), 'O evento entra na fila.' );
		self::assertSame( $http_antes, VH_Fake_HTTP::contar( 'GET', 'produtos/' . $tiny_id ), 'A requisição do webhook não puxa o ERP inline.' );
	}
}

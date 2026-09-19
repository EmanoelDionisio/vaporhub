<?php
/**
 * Estado inicial de cada teste: kernel limpo, ERP falso vazio e Tiny conectado.
 *
 * @package VaporHubLoja\Tests
 */

final class VH_Test_Env {

	public const TABELA_FILA = 'wp_vh_tiny_fila';

	public static VH_Fake_WPDB $wpdb;

	/**
	 * Zera tudo e deixa a integração no estado de produção auditado hoje:
	 * ativa, v3, envio ligado, recebimento desligado.
	 *
	 * @param array<string, mixed> $config Sobrescreve chaves da configuração Tiny.
	 */
	public static function reset( array $config = [] ): void {
		global $wpdb;

		VH_Fake_WP::reset();
		VH_Fake_HTTP::reset();
		VH_Fake_Tiny_ERP::reset();
		VH_Tiny_Log::reset();

		if ( ! isset( self::$wpdb ) ) {
			self::$wpdb = new VH_Fake_WPDB();
		}
		self::$wpdb->reset();
		$wpdb = self::$wpdb;

		self::configurar_tiny( $config );
	}

	/**
	 * @param array<string, mixed> $config
	 */
	public static function configurar_tiny( array $config = [] ): void {
		$padrao = [
			'ativo'                => true,
			'modo'                 => 'v3',
			'sinc_auto'            => false,
			'permitir_envio'       => true,
			'permitir_recebimento' => false,
			'client_id'            => 'client-de-teste',
			'client_secret'        => VH_SEO_Crypto::criptografar( 'segredo-de-teste' ),
			'access_token'         => VH_SEO_Crypto::criptografar( 'token-de-teste' ),
			'refresh_token'        => VH_SEO_Crypto::criptografar( 'refresh-de-teste' ),
			'expires_at'           => time() + 3600,
			'connected_at'         => gmdate( 'c' ),
			'conta_nome'           => 'VAPOR HUB COMERCIO LTDA',
			'webhook_token_hash'   => 'token-webhook-de-teste',
			'loja_identificador'   => 'piloto.example',
			'ultimo_erro'          => '',
		];

		update_option( VH_Tiny::OPTION, array_merge( $padrao, $config ), false );
	}

	/**
	 * @param array<string, mixed> $config
	 */
	public static function atualizar_config( array $config ): void {
		$atual = get_option( VH_Tiny::OPTION, [] );
		update_option( VH_Tiny::OPTION, array_merge( is_array( $atual ) ? $atual : [], $config ), false );
	}

	/**
	 * Jobs na fila, opcionalmente filtrados por tipo.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function jobs( string $tipo = '' ): array {
		$linhas = self::$wpdb->linhas( self::TABELA_FILA );
		if ( '' === $tipo ) {
			return $linhas;
		}
		return array_values(
			array_filter(
				$linhas,
				static fn( array $linha ): bool => ( $linha['tipo'] ?? '' ) === $tipo
			)
		);
	}

	public static function contar_jobs( string $tipo = '' ): int {
		return count( self::jobs( $tipo ) );
	}

	/**
	 * Produto simples pronto para envio (com SKU, preço e estoque).
	 *
	 * @param array<string, mixed> $atributos
	 */
	public static function produto_simples( array $atributos = [] ): WC_Product_Simple {
		$dados = array_merge(
			[
				'nome'             => 'Pod Descartavel Teste',
				'sku'              => 'VH-POD-001',
				'preco_regular'    => '49.90',
				'estoque'          => 10,
				'gerencia_estoque' => true,
				'descricao'        => 'Produto de teste.',
				'status'           => 'publish',
			],
			$atributos
		);

		$produto = new WC_Product_Simple();
		$produto->set_name( (string) $dados['nome'] );
		$produto->set_status( (string) $dados['status'] );
		$produto->set_description( (string) $dados['descricao'] );
		$produto->set_sku( (string) $dados['sku'] );
		$produto->set_regular_price( (string) $dados['preco_regular'] );
		$produto->set_manage_stock( (bool) $dados['gerencia_estoque'] );
		$produto->set_stock_quantity( $dados['estoque'] );

		if ( isset( $dados['peso'] ) ) {
			$produto->set_weight( (string) $dados['peso'] );
		}
		if ( isset( $dados['largura'] ) ) {
			$produto->set_width( (string) $dados['largura'] );
		}
		if ( isset( $dados['altura'] ) ) {
			$produto->set_height( (string) $dados['altura'] );
		}
		if ( isset( $dados['comprimento'] ) ) {
			$produto->set_length( (string) $dados['comprimento'] );
		}

		$produto->save();
		return $produto;
	}

	/**
	 * Produto variável com uma variação por opção informada.
	 *
	 * @param array<int, string>   $opcoes
	 * @param array<string, mixed> $atributos
	 */
	public static function produto_variavel( array $opcoes = [ 'azul', 'verde' ], array $atributos = [] ): WC_Product_Variable {
		$dados = array_merge(
			[
				'nome'          => 'Camiseta Teste',
				'sku'           => 'VH-VAR',
				'taxonomy'      => 'pa_cor',
				'preco_regular' => '89.90',
				'estoque'       => 5,
				'status'        => 'publish',
			],
			$atributos
		);

		$pai = new WC_Product_Variable();
		$pai->set_name( (string) $dados['nome'] );
		$pai->set_status( (string) $dados['status'] );
		$pai->set_sku( (string) $dados['sku'] );

		$atributo = new WC_Product_Attribute();
		$atributo->set_name( (string) $dados['taxonomy'] );
		$atributo->set_options( $opcoes );
		$atributo->set_variation( true );
		$pai->set_attributes( [ (string) $dados['taxonomy'] => $atributo ] );
		$pai->save();

		$filhos = [];
		foreach ( $opcoes as $opcao ) {
			$variacao = new WC_Product_Variation();
			$variacao->set_parent_id( $pai->get_id() );
			$variacao->set_attributes( [ (string) $dados['taxonomy'] => (string) $opcao ] );
			$variacao->set_sku( (string) $dados['sku'] . '-' . strtoupper( (string) $opcao ) );
			$variacao->set_regular_price( (string) $dados['preco_regular'] );
			$variacao->set_manage_stock( true );
			$variacao->set_stock_quantity( $dados['estoque'] );
			$variacao->save();
			$filhos[] = $variacao->get_id();
		}

		$pai->set_children( $filhos );
		$pai->save();

		return $pai;
	}

	/**
	 * Pedido WC com um item por produto informado.
	 *
	 * @param array<int, array{produto:WC_Product, quantidade?:int, total?:float}> $itens
	 * @param array<string, mixed>                                                $campos
	 */
	public static function pedido( array $itens = [], array $campos = [] ): WC_Order {
		$pedido = new WC_Order( $campos );

		foreach ( $itens as $linha ) {
			$produto    = $linha['produto'];
			$quantidade = (int) ( $linha['quantidade'] ?? 1 );
			$total      = (float) ( $linha['total'] ?? ( (float) $produto->get_regular_price() * $quantidade ) );
			$pedido->adicionar_item( $produto, $quantidade, $total );
		}

		$pedido->save();
		return $pedido;
	}

	/**
	 * Categoria local, opcionalmente já vinculada a uma categoria do Tiny.
	 */
	public static function categoria( string $nome, int $tiny_id = 0, int $pai = 0 ): int {
		$term_id = VH_Fake_WP::novo_id();

		VH_Fake_WP::$term_meta[ $term_id ]['__nome'] = $nome;
		if ( $tiny_id > 0 ) {
			update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, $tiny_id );
		}
		if ( $pai > 0 ) {
			VH_Fake_WP::$term_meta[ $term_id ]['__pai'] = $pai;
		}

		return $term_id;
	}
}

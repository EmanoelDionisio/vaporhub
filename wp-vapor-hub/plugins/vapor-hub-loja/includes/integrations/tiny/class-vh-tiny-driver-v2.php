<?php
/**
 * Driver Tiny ERP API v2 (Token).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_Driver_V2 implements VH_Tiny_Driver_Interface {

	public function versao(): string {
		return 'v2';
	}

	public function ativo(): bool {
		$dados = VH_Tiny::obter();
		return VH_Tiny_Client_V2::conectado() && ! empty( $dados['ativo'] );
	}

	public function conectado(): bool {
		return VH_Tiny_Client_V2::conectado();
	}

	public function credenciais_configuradas(): bool {
		return VH_Tiny_Client_V2::credenciais_configuradas();
	}

	public function status_driver(): array {
		$dados = VH_Tiny::obter();
		return [
			'versao_api'     => 'v2',
			'credenciais_ok' => self::credenciais_configuradas(),
			'conectado'      => self::conectado(),
			'v2_conectado'   => ! empty( $dados['v2_conectado'] ),
			'v2_conta'       => (string) ( $dados['v2_conta'] ?? '' ),
			'client_id'      => '',
			'redirect_uri'   => '',
			'conta_nome'     => (string) ( $dados['v2_conta'] ?? '' ),
			'connected_at'   => '',
		];
	}

	public function desconectar(): void {
		VH_Tiny_Client_V2::desconectar();
	}

	public function buscar_produto_por_sku( string $sku ) {
		$resp = VH_Tiny_Client_V2::requisicao(
			'produtos.pesquisa.php',
			[
				'pesquisa' => $sku,
				'pagina'   => 1,
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		foreach ( self::iterar_produtos( $resp ) as $prod ) {
			$canon = self::resposta_para_canonico( $prod );
			if ( 0 === strcasecmp( (string) ( $canon['sku'] ?? '' ), $sku ) ) {
				return $canon;
			}
		}
		return null;
	}

	public function obter_produto( int $tiny_id ) {
		$resp = VH_Tiny_Client_V2::requisicao( 'produto.obter.php', [ 'id' => $tiny_id ] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$prod = self::extrair_produto_retorno( $resp );
		if ( ! $prod ) {
			return new WP_Error( 'vh_tiny_v2_produto', __( 'Produto não encontrado no Tiny.', 'vapor-hub-loja' ) );
		}
		$canon = self::resposta_para_canonico( $prod );

		$est = VH_Tiny_Client_V2::requisicao( 'produto.obter.estoque.php', [ 'id' => $tiny_id ] );
		if ( ! is_wp_error( $est ) ) {
			$est_dados = $est['produto'] ?? $est;
			if ( is_array( $est_dados ) ) {
				$canon['estoque'] = self::extrair_estoque_valor( $est_dados );
			}
		}

		return $canon;
	}

	public function criar_produto( array $canonico, bool $somente_pai = false ) {
		unset( $somente_pai );
		$payload = self::canonico_para_v2( $canonico );
		$resp    = VH_Tiny_Client_V2::requisicao(
			'produto.incluir.php',
			[ 'produto' => wp_json_encode( $payload ) ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$id = self::extrair_id_registro( $resp );
		if ( $id <= 0 && ! empty( $resp['registros'][0]['registro']['id'] ) ) {
			$id = absint( $resp['registros'][0]['registro']['id'] );
		}

		return [
			'id_tiny' => $id,
			'sku'     => (string) ( $canonico['sku'] ?? '' ),
		];
	}

	public function excluir_produto( int $tiny_id ) {
		if ( $tiny_id <= 0 ) {
			return true;
		}

		$resp = VH_Tiny_Client_V2::requisicao(
			'produto.excluir.php',
			[ 'id' => $tiny_id ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	public function atualizar_produto( int $tiny_id, array $canonico ) {
		$payload = self::canonico_para_v2( $canonico );
		$resp    = VH_Tiny_Client_V2::requisicao(
			'produto.alterar.php',
			[
				'id'      => $tiny_id,
				'produto' => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	public function sincronizar_imagens( int $tiny_id, array $canonico ) {
		if ( empty( $canonico['imagens'] ) || ! is_array( $canonico['imagens'] ) ) {
			return true;
		}

		$anexos = [];
		foreach ( $canonico['imagens'] as $url ) {
			if ( is_string( $url ) && '' !== trim( $url ) ) {
				$anexos[] = [ 'anexo' => esc_url_raw( $url ) ];
			}
		}
		if ( ! $anexos ) {
			return true;
		}

		$payload = [
			'codigo' => (string) ( $canonico['sku'] ?? '' ),
			'nome'   => (string) ( $canonico['nome'] ?? '' ),
			'anexos' => $anexos,
		];

		$resp = VH_Tiny_Client_V2::requisicao(
			'produto.alterar.php',
			[
				'id'      => $tiny_id,
				'produto' => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	/**
	 * Ativa controle de estoque e alinha saldo (produto simples).
	 *
	 * @return true|WP_Error
	 */
	public function sincronizar_estoque( int $tiny_id, array $canonico ) {
		if ( 'variavel' === ( $canonico['tipo'] ?? '' ) ) {
			return true;
		}

		$payload = [
			'codigo' => (string) ( $canonico['sku'] ?? '' ),
			'nome'   => (string) ( $canonico['nome'] ?? '' ),
		];

		if ( array_key_exists( 'gerencia_estoque', $canonico ) ) {
			$payload['controle_estoque'] = ! empty( $canonico['gerencia_estoque'] ) ? 'S' : 'N';
		}

		if ( isset( $canonico['estoque'] ) && is_numeric( $canonico['estoque'] ) ) {
			$payload['estoque_atual'] = (int) $canonico['estoque'];
		}

		$resp = VH_Tiny_Client_V2::requisicao(
			'produto.alterar.php',
			[
				'id'      => $tiny_id,
				'produto' => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		return true;
	}

	/**
	 * Saldo puro na v2: a API antiga só tem `produto.alterar`, então o caminho é
	 * o mesmo do cadastro, com o payload reduzido a código, nome e saldo.
	 *
	 * @param array<string, mixed> $item
	 * @return true|WP_Error
	 */
	public function sincronizar_saldo( int $tiny_id, array $item ) {
		if ( $tiny_id <= 0 ) {
			return new WP_Error( 'vh_tiny_sem_vinculo', __( 'Item sem vínculo no Tiny.', 'vapor-hub-loja' ) );
		}
		$item['tipo'] = 'simples';
		return $this->sincronizar_estoque( $tiny_id, $item );
	}

	public function sincronizar_variacoes( int $tiny_id_pai, array $canonico ) {
		$variacoes = $canonico['variacoes'] ?? [];
		if ( ! is_array( $variacoes ) || ! $variacoes ) {
			return true;
		}

		/*
		 * As variações já são enviadas no payload de criar/atualizar produto
		 * (classe_produto=V). Aqui apenas re-buscamos o pai e mapeamos o
		 * id_tiny de cada variação pelo SKU, evitando uma segunda gravação.
		 */
		$tiny_por_sku = [];
		$resp         = VH_Tiny_Client_V2::requisicao( 'produto.obter.php', [ 'id' => $tiny_id_pai ] );
		if ( ! is_wp_error( $resp ) ) {
			$prod = self::extrair_produto_retorno( $resp );
			if ( $prod ) {
				foreach ( self::extrair_variacoes_resposta( $prod ) as $var_tiny ) {
					$sku_t = strtoupper( (string) ( $var_tiny['sku'] ?? '' ) );
					if ( '' !== $sku_t ) {
						$tiny_por_sku[ $sku_t ] = (int) ( $var_tiny['tiny_id'] ?? 0 );
					}
				}
			}
		}

		$mapa = [];
		foreach ( $variacoes as $var ) {
			if ( ! is_array( $var ) ) {
				continue;
			}
			$sku = strtoupper( (string) ( $var['sku'] ?? '' ) );
			$tid = (int) ( $var['tiny_id'] ?? 0 );
			if ( $tid <= 0 && isset( $tiny_por_sku[ $sku ] ) ) {
				$tid = $tiny_por_sku[ $sku ];
			}
			$mapa[] = [
				'variacao_id' => (int) ( $var['variacao_id'] ?? 0 ),
				'tiny_id'     => $tid,
				'sku'         => (string) ( $var['sku'] ?? '' ),
			];
		}

		return [ 'variacoes' => $mapa ];
	}

	public function remover_variacao( int $tiny_id_pai, int $tiny_id_variacao ) {
		if ( $tiny_id_variacao <= 0 ) {
			return true;
		}
		$resp = VH_Tiny_Client_V2::requisicao(
			'produto.alterar.php',
			[
				'id'      => $tiny_id_pai,
				'produto' => wp_json_encode(
					[
						'classe_produto' => 'V',
						'variacoes'      => [
							[
								'variacao' => [
									'id'       => $tiny_id_variacao,
									'situacao' => 'I',
								],
							],
						],
					]
				),
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	public function buscar_categoria( string $caminho ) {
		$caminho = trim( $caminho );
		if ( '' === $caminho ) {
			return null;
		}
		$mapa = self::mavh_categorias_v2();
		if ( is_wp_error( $mapa ) ) {
			return $mapa;
		}
		foreach ( $mapa as $id => $dados ) {
			if ( 0 === strcasecmp( (string) ( $dados['caminho'] ?? '' ), $caminho ) ) {
				return [
					'id_tiny' => (int) $id,
					'nome'    => (string) ( $dados['nome'] ?? '' ),
					'caminho' => (string) ( $dados['caminho'] ?? '' ),
				];
			}
		}
		return null;
	}

	public function criar_categoria( array $canonico ) {
		$payload = [
			'descricao' => (string) ( $canonico['nome'] ?? '' ),
		];
		if ( ! empty( $canonico['pai_id_tiny'] ) ) {
			$payload['id_categoria_pai'] = (int) $canonico['pai_id_tiny'];
		}
		$resp = VH_Tiny_Client_V2::requisicao(
			'categoria.incluir.php',
			[ 'categoria' => wp_json_encode( $payload ) ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$id = self::extrair_id_registro( $resp );
		return [ 'id_tiny' => $id ];
	}

	public function atualizar_categoria( int $tiny_id, array $canonico ) {
		$payload = [
			'descricao' => (string) ( $canonico['nome'] ?? '' ),
		];
		if ( ! empty( $canonico['pai_id_tiny'] ) ) {
			$payload['id_categoria_pai'] = (int) $canonico['pai_id_tiny'];
		}
		$resp = VH_Tiny_Client_V2::requisicao(
			'categoria.alterar.php',
			[
				'id'        => $tiny_id,
				'categoria' => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	public function listar_categorias() {
		$mapa = self::mavh_categorias_v2();
		if ( is_wp_error( $mapa ) ) {
			return $mapa;
		}

		$saida = [];
		foreach ( $mapa as $id => $dados ) {
			$saida[] = [
				'id_tiny' => (int) $id,
				'nome'    => (string) ( $dados['nome'] ?? '' ),
				'caminho' => (string) ( $dados['caminho'] ?? '' ),
			];
		}

		usort(
			$saida,
			static fn( $a, $b ) => strcasecmp( (string) $a['caminho'], (string) $b['caminho'] )
		);

		return $saida;
	}

	public function listar_produtos( int $pagina, int $por_pagina ) {
		unset( $por_pagina );
		$resp = VH_Tiny_Client_V2::requisicao(
			'produtos.pesquisa.php',
			[
				'pesquisa' => ' ',
				'pagina'   => max( 1, $pagina ),
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$itens = [];
		foreach ( self::iterar_produtos( $resp ) as $prod ) {
			$itens[] = self::resposta_para_canonico( $prod );
		}

		$total = (int) ( $resp['numero_paginas'] ?? 1 ) * count( $itens );
		if ( isset( $resp['numero_paginas'] ) ) {
			$total = (int) $resp['numero_paginas'] * max( 1, count( $itens ) );
		}

		return [
			'itens'  => $itens,
			'total'  => $total,
			'pagina' => $pagina,
		];
	}

	public function obter_estoque( int $tiny_id ) {
		$resp = VH_Tiny_Client_V2::requisicao( 'produto.obter.estoque.php', [ 'id' => $tiny_id ] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$est = $resp['produto'] ?? $resp;
		return [
			'id_tiny' => $tiny_id,
			'estoque' => is_array( $est ) ? self::extrair_estoque_valor( $est ) : null,
		];
	}

	public function criar_pedido( array $canonico ) {
		$payload = self::pedido_canonico_para_v2( $canonico );
		$resp    = VH_Tiny_Client_V2::requisicao(
			'pedido.incluir.php',
			[ 'pedido' => wp_json_encode( $payload ) ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$id = self::extrair_id_registro( $resp );
		return [ 'id_tiny_pedido' => $id ];
	}

	public function listar_atualizacoes_estoque( string $desde ) {
		$params = [];
		if ( '' !== $desde ) {
			$params['dataAlteracao'] = gmdate( 'd/m/Y H:i:s', strtotime( $desde ) );
		}
		$resp = VH_Tiny_Client_V2::requisicao( 'lista.atualizacoes.estoque.php', $params );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$saida = [];
		$lista = $resp['produtos'] ?? $resp['itens'] ?? [];
		if ( ! is_array( $lista ) ) {
			return [];
		}

		foreach ( $lista as $item ) {
			$prod = $item['produto'] ?? $item;
			if ( ! is_array( $prod ) ) {
				continue;
			}
			$saida[] = [
				'tiny_id' => VH_Tiny_Map::extrair_tiny_id( $prod ),
				'sku'     => VH_Tiny_Map::extrair_sku( $prod ),
				'estoque' => self::extrair_estoque_valor( $prod ),
			];
		}
		return $saida;
	}

	public function normalizar_webhook( array $payload ): array {
		$tipo = sanitize_key( (string) ( $payload['tipo'] ?? '' ) );
		$dados = is_array( $payload['dados'] ?? null ) ? $payload['dados'] : $payload;

		$tiny_id = absint( $dados['idProduto'] ?? $dados['id'] ?? $payload['idProduto'] ?? 0 );
		$sku     = sanitize_text_field( (string) ( $dados['sku'] ?? $dados['codigo'] ?? $payload['codigo'] ?? '' ) );

		if ( 'estoque' === $tipo || str_contains( $tipo, 'estoque' ) ) {
			return [ 'tipo' => 'estoque', 'tiny_id' => $tiny_id, 'sku' => $sku ];
		}
		if ( 'produto' === $tipo || str_contains( $tipo, 'produto' ) ) {
			return [ 'tipo' => 'produto', 'tiny_id' => $tiny_id, 'sku' => $sku ];
		}
		return [ 'tipo' => $tipo ?: 'desconhecido', 'tiny_id' => $tiny_id, 'sku' => $sku ];
	}

	/**
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function canonico_para_v2( array $canonico ): array {
		$is_variavel = 'variavel' === ( $canonico['tipo'] ?? '' );

		$produto = [
			'codigo'                 => (string) ( $canonico['sku'] ?? '' ),
			'nome'                   => (string) ( $canonico['nome'] ?? '' ),
			'descricao_complementar' => (string) ( $canonico['descricao'] ?? '' ),
			'unidade'                => 'UN',
			'tipo'                   => 'P',
			'classe_produto'         => $is_variavel ? 'V' : 'S',
			'origem'                 => '0',
			'situacao'               => ! empty( $canonico['publicado'] ) ? 'A' : 'I',
		];

		if ( ! $is_variavel ) {
			if ( isset( $canonico['preco_regular'] ) && '' !== (string) $canonico['preco_regular'] ) {
				$produto['preco'] = (float) $canonico['preco_regular'];
			}
			if ( array_key_exists( 'gerencia_estoque', $canonico ) ) {
				$produto['controle_estoque'] = ! empty( $canonico['gerencia_estoque'] ) ? 'S' : 'N';
			}
			if ( isset( $canonico['estoque'] ) && is_numeric( $canonico['estoque'] ) ) {
				$produto['estoque_atual'] = (int) $canonico['estoque'];
			}
		}

		$envio = is_array( $canonico['envio'] ?? null ) ? $canonico['envio'] : [];
		$peso  = (float) ( $envio['peso_bruto'] ?? $canonico['peso'] ?? 0 );
		if ( $peso > 0 ) {
			$produto['peso_bruto']   = $peso;
			$produto['peso_liquido'] = (float) ( $envio['peso_liquido'] ?? $peso );
		}

		/* Medida vazia não é medida zero: campo ausente preserva o cadastro do ERP. */
		foreach ( [ 'largura' => 'largura_produto', 'altura' => 'altura_produto', 'comprimento' => 'comprimento_produto' ] as $chave => $campo_v2 ) {
			$valor = (float) ( $envio[ $chave ] ?? 0 );
			if ( $valor > 0 ) {
				$produto[ $campo_v2 ] = $valor;
			}
		}

		if ( ! empty( $canonico['descricao_curta'] ) ) {
			$produto['obs'] = (string) $canonico['descricao_curta'];
		}

		if ( ! empty( $canonico['categorias'] ) && is_array( $canonico['categorias'] ) ) {
			$caminhos = [];
			$ids      = [];
			foreach ( $canonico['categorias'] as $cat ) {
				if ( ! is_array( $cat ) ) {
					continue;
				}
				if ( ! empty( $cat['id_tiny'] ) ) {
					$ids[] = (int) $cat['id_tiny'];
				}
				if ( ! empty( $cat['caminho'] ) ) {
					$caminhos[] = (string) $cat['caminho'];
				} elseif ( ! empty( $cat['nome'] ) ) {
					$caminhos[] = (string) $cat['nome'];
				}
			}
			if ( $ids ) {
				$produto['id_categoria'] = $ids[0];
			}
			if ( $caminhos ) {
				$produto['categoria'] = implode( ', ', $caminhos );
			}
		}

		if ( ! empty( $canonico['imagens'] ) && is_array( $canonico['imagens'] ) ) {
			$anexos = [];
			foreach ( $canonico['imagens'] as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$anexos[] = [ 'anexo' => esc_url_raw( $url ) ];
				}
			}
			if ( $anexos ) {
				$produto['anexos'] = $anexos;
			}
		}

		if ( $is_variavel && ! empty( $canonico['variacoes'] ) && is_array( $canonico['variacoes'] ) ) {
			$vars = [];
			foreach ( $canonico['variacoes'] as $var ) {
				if ( ! is_array( $var ) ) {
					continue;
				}
				$grade = [];
				foreach ( (array) ( $var['grade'] ?? [] ) as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$grade[] = [
						'descricao' => (string) ( $item['chave'] ?? '' ),
						'valor'     => (string) ( $item['valor'] ?? '' ),
					];
				}
				$linha = [
					'codigo' => (string) ( $var['sku'] ?? '' ),
					'grade'  => $grade,
				];
				if ( isset( $var['preco_regular'] ) && '' !== (string) $var['preco_regular'] ) {
					$linha['preco'] = (float) $var['preco_regular'];
				}
				if ( isset( $var['estoque'] ) && is_numeric( $var['estoque'] ) ) {
					$linha['estoque_atual'] = (int) $var['estoque'];
				}
				$vars[] = [ 'variacao' => $linha ];
			}
			if ( $vars ) {
				$produto['variacoes'] = $vars;
			}
		}

		return array_filter(
			$produto,
			static fn( $v ) => null !== $v && '' !== $v && [] !== $v
		);
	}

	/**
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function pedido_canonico_para_v2( array $canonico ): array {
		$itens = [];
		foreach ( (array) ( $canonico['itens'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$linha = [
				'item' => [
					'codigo'        => (string) ( $item['sku'] ?? '' ),
					'descricao'     => (string) ( $item['nome'] ?? '' ),
					'quantidade'    => (float) ( $item['quantidade'] ?? 1 ),
					'valor_unitario'=> (float) ( $item['valor_unitario'] ?? 0 ),
				],
			];
			if ( ! empty( $item['id_tiny'] ) ) {
				$linha['item']['id_produto'] = (int) $item['id_tiny'];
			}
			$itens[] = $linha;
		}

		$cliente = is_array( $canonico['cliente'] ?? null ) ? $canonico['cliente'] : [];
		$entrega = is_array( $canonico['entrega'] ?? null ) ? $canonico['entrega'] : [];

		$numero = (string) ( $canonico['numero'] ?? '' );
		$origem = (string) ( $canonico['origem_loja'] ?? '' );
		if ( '' !== $origem && '' !== $numero && ! str_starts_with( $numero, $origem . '-' ) ) {
			$numero = $origem . '-' . $numero;
		}

		$frete     = is_array( $canonico['frete'] ?? null ) ? $canonico['frete'] : [];
		$pagamento = is_array( $canonico['pagamento'] ?? null ) ? $canonico['pagamento'] : [];

		$payload = [
			'numero_pedido_ecommerce' => $numero,
			'data_pedido'             => (string) ( $canonico['data'] ?? gmdate( 'd/m/Y' ) ),
			'cliente'                 => [
				'nome'     => (string) ( $cliente['nome'] ?? '' ),
				'email'    => (string) ( $cliente['email'] ?? '' ),
				'fone'     => (string) ( $cliente['telefone'] ?? '' ),
				'cpf_cnpj' => (string) ( $cliente['cpf_cnpj'] ?? '' ),
			],
			'endereco_entrega' => array_filter(
				[
					'endereco'    => (string) ( $entrega['endereco'] ?? '' ),
					'numero'      => (string) ( $entrega['numero'] ?? '' ),
					'complemento' => (string) ( $entrega['complemento'] ?? '' ),
					'bairro'      => (string) ( $entrega['bairro'] ?? '' ),
					'cidade'      => (string) ( $entrega['cidade'] ?? '' ),
					'uf'          => (string) ( $entrega['uf'] ?? '' ),
					'cep'         => (string) ( $entrega['cep'] ?? '' ),
				],
				static fn( $v ) => '' !== $v
			),
			'itens'       => $itens,
			'valor_total' => (float) ( $canonico['total'] ?? 0 ),
			'obs'         => (string) ( $canonico['observacoes'] ?? '' ),
		];

		if ( ( (float) ( $frete['valor'] ?? 0 ) ) > 0 ) {
			$payload['valor_frete'] = (float) $frete['valor'];
		}
		if ( ( (float) ( $canonico['desconto'] ?? 0 ) ) > 0 ) {
			$payload['valor_desconto'] = (float) $canonico['desconto'];
		}
		$metodo = trim( (string) ( $pagamento['metodo'] ?? '' ) );
		if ( '' !== $metodo ) {
			$payload['forma_pagamento'] = $metodo;
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $resp
	 * @return array<int, array<string, mixed>>
	 */
	private static function iterar_produtos( array $resp ): array {
		$lista = $resp['produtos'] ?? [];
		if ( ! is_array( $lista ) ) {
			return [];
		}
		$saida = [];
		foreach ( $lista as $item ) {
			if ( isset( $item['produto'] ) && is_array( $item['produto'] ) ) {
				$saida[] = $item['produto'];
			} elseif ( is_array( $item ) ) {
				$saida[] = $item;
			}
		}
		return $saida;
	}

	/**
	 * @param array<string, mixed> $resp
	 * @return array<string, mixed>|null
	 */
	private static function extrair_produto_retorno( array $resp ): ?array {
		if ( isset( $resp['produto'] ) && is_array( $resp['produto'] ) ) {
			return $resp['produto'];
		}
		$lista = self::iterar_produtos( $resp );
		return $lista[0] ?? null;
	}

	/**
	 * @param array<string, mixed> $resp
	 */
	private static function extrair_id_registro( array $resp ): int {
		if ( ! empty( $resp['registros'] ) && is_array( $resp['registros'] ) ) {
			$reg = $resp['registros'][0]['registro'] ?? $resp['registros'][0] ?? [];
			if ( is_array( $reg ) && ! empty( $reg['id'] ) ) {
				return absint( $reg['id'] );
			}
		}
		if ( ! empty( $resp['id'] ) ) {
			return absint( $resp['id'] );
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function resposta_para_canonico( array $raw ): array {
		$preco = null;
		foreach ( [ 'preco', 'preco_venda', 'precoVenda' ] as $chave ) {
			if ( isset( $raw[ $chave ] ) && '' !== (string) $raw[ $chave ] ) {
				$preco = wc_format_decimal( (string) $raw[ $chave ] );
				break;
			}
		}
		$promo = null;
		foreach ( [ 'preco_promocional', 'precoPromocional' ] as $chave ) {
			if ( isset( $raw[ $chave ] ) && '' !== (string) $raw[ $chave ] ) {
				$promo = wc_format_decimal( (string) $raw[ $chave ] );
				break;
			}
		}

		$tipo = ( isset( $raw['classe_produto'] ) && 'V' === (string) $raw['classe_produto'] ) ? 'variavel' : 'simples';

		$gerencia = null;
		if ( isset( $raw['controle_estoque'] ) ) {
			$gerencia = in_array( strtoupper( (string) $raw['controle_estoque'] ), [ 'S', 'SIM', '1', 'TRUE' ], true );
		}

		$canon = array_filter(
			[
				'tipo'             => $tipo,
				'id_tiny'          => VH_Tiny_Map::extrair_tiny_id( $raw ),
				'sku'              => VH_Tiny_Map::extrair_sku( $raw ),
				'nome'             => (string) ( $raw['nome'] ?? '' ),
				'descricao'        => (string) ( $raw['descricao_complementar'] ?? $raw['descricaoComplementar'] ?? '' ),
				'descricao_curta'  => (string) ( $raw['obs'] ?? '' ),
				'peso'             => (float) ( $raw['peso_bruto'] ?? $raw['pesoBruto'] ?? 0 ),
				'situacao'         => strtoupper( (string) ( $raw['situacao'] ?? 'A' ) ),
				'publicado'        => ! isset( $raw['situacao'] ) || ! in_array( strtoupper( (string) $raw['situacao'] ), [ 'I', 'E' ], true ),
				'preco_regular'    => $preco,
				'preco_promo'      => $promo,
				'gerencia_estoque' => $gerencia,
				'estoque'          => self::extrair_estoque_valor( $raw ),
			],
			static fn( $v ) => null !== $v
		);

		$vars = self::extrair_variacoes_resposta( $raw );
		if ( $vars ) {
			$canon['variacoes'] = $vars;
		}

		$cats = VH_Tiny_Map::categorias_de_resposta_tiny( $raw );
		if ( $cats ) {
			$canon['categorias'] = $cats;
		}

		return $canon;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<int, array<string, mixed>>
	 */
	private static function extrair_variacoes_resposta( array $raw ): array {
		$lista = $raw['variacoes'] ?? [];
		if ( ! is_array( $lista ) ) {
			return [];
		}
		$saida = [];
		foreach ( $lista as $item ) {
			$var = $item['variacao'] ?? $item;
			if ( ! is_array( $var ) ) {
				continue;
			}
			$grade = [];
			$grade_raw = $var['grade'] ?? [];
			if ( is_array( $grade_raw ) ) {
				foreach ( $grade_raw as $g ) {
					if ( is_array( $g ) ) {
						$grade[] = [
							'chave' => (string) ( $g['descricao'] ?? $g['nome'] ?? '' ),
							'valor' => (string) ( $g['valor'] ?? '' ),
						];
					}
				}
			}
			$saida[] = array_filter(
				[
					'tiny_id'       => VH_Tiny_Map::extrair_tiny_id( $var ),
					'sku'           => VH_Tiny_Map::extrair_sku( $var ),
					'grade'         => $grade,
					'preco_regular' => isset( $var['preco'] ) ? wc_format_decimal( (string) $var['preco'] ) : null,
					'estoque'       => self::extrair_estoque_valor( $var ),
				],
				static fn( $v ) => null !== $v && '' !== $v && [] !== $v
			);
		}
		return $saida;
	}

	/**
	 * @return array<int, array{caminho:string,nome:string}>|WP_Error
	 */
	private static function mavh_categorias_v2() {
		static $cache = null;
		if ( is_array( $cache ) ) {
			return $cache;
		}

		$resp = VH_Tiny_Client_V2::requisicao( 'categorias.lista.php', [] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$flat = [];
		$lista = $resp['categorias'] ?? $resp['retorno'] ?? [];
		if ( is_array( $lista ) ) {
			foreach ( $lista as $item ) {
				$cat = $item['categoria'] ?? $item;
				if ( ! is_array( $cat ) ) {
					continue;
				}
				$id = absint( $cat['id'] ?? 0 );
				if ( $id <= 0 ) {
					continue;
				}
				$flat[ $id ] = [
					'nome'    => (string) ( $cat['descricao'] ?? $cat['nome'] ?? '' ),
					'pai_id'  => absint( $cat['id_categoria_pai'] ?? $cat['idCategoriaPai'] ?? 0 ),
				];
			}
		}

		$mapa = [];
		$montar = static function ( int $id ) use ( &$montar, $flat ): string {
			if ( ! isset( $flat[ $id ] ) ) {
				return '';
			}
			$nome = $flat[ $id ]['nome'];
			$pai  = (int) $flat[ $id ]['pai_id'];
			if ( $pai > 0 && isset( $flat[ $pai ] ) ) {
				return $montar( $pai ) . ' >> ' . $nome;
			}
			return $nome;
		};

		foreach ( $flat as $id => $dados ) {
			$mapa[ $id ] = [
				'nome'    => $dados['nome'],
				'caminho' => $montar( (int) $id ),
			];
		}

		$cache = $mapa;
		return $mapa;
	}

	/**
	 * @param array<string, mixed> $fonte
	 */
	private static function extrair_estoque_valor( array $fonte ): ?int {
		foreach ( [ 'saldo', 'estoque', 'quantidade', 'saldoFisico' ] as $chave ) {
			if ( isset( $fonte[ $chave ] ) && is_numeric( $fonte[ $chave ] ) ) {
				return (int) $fonte[ $chave ];
			}
		}
		return null;
	}
}

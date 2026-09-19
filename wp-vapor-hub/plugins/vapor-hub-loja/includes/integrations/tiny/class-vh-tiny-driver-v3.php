<?php
/**
 * Driver Tiny ERP API v3 (OAuth) — envolve VH_Tiny_Client.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_Driver_V3 implements VH_Tiny_Driver_Interface {

	public function versao(): string {
		return 'v3';
	}

	public function ativo(): bool {
		return VH_Tiny_Client::ativo();
	}

	public function conectado(): bool {
		return VH_Tiny_Client::conectado();
	}

	public function credenciais_configuradas(): bool {
		return VH_Tiny_Client::credenciais_configuradas();
	}

	public function status_driver(): array {
		$conectado = self::conectado();
		$dados     = wp_parse_args( VH_Tiny_Client::obter(), VH_Tiny_Client::padrao() );

		return [
			'versao_api'       => 'v3',
			'credenciais_ok'   => self::credenciais_configuradas(),
			'conectado'        => $conectado,
			'client_id'        => (string) ( $dados['client_id'] ?? '' ),
			'redirect_uri'     => VH_Tiny_Client::redirect_uri(),
			'conta_nome'       => (string) ( $dados['conta_nome'] ?? '' ),
			'conta_cnpj'       => VH_Tiny_Client::normalizar_cnpj( (string) ( $dados['conta_cnpj'] ?? '' ) ),
			'conta_cnpj_fmt'   => VH_Tiny_Client::formatar_cnpj( (string) ( $dados['conta_cnpj'] ?? '' ) ),
			'conta_bloqueada'  => ! empty( $dados['conta_bloqueada'] ),
			'connected_at'     => (string) ( $dados['connected_at'] ?? '' ),
			'v2_conectado'     => false,
			'v2_conta'         => '',
		];
	}

	public function desconectar(): void {
		VH_Tiny_Client::desconectar();
	}

	public function buscar_produto_por_sku( string $sku ) {
		$sku = trim( $sku );
		if ( '' === $sku ) {
			return null;
		}

		$resp = VH_Tiny_Client::requisicao(
			'GET',
			'produtos',
			[ 'query' => [ 'codigo' => $sku, 'limit' => 50 ] ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$itens = $resp['itens'] ?? $resp['items'] ?? [];
		if ( ! is_array( $itens ) ) {
			return null;
		}

		/*
		 * Segurança: o filtro do Tiny pode retornar resultados aproximados.
		 * Só aceitamos correspondência se o SKU bater EXATAMENTE, evitando
		 * vincular/atualizar o produto errado no ERP.
		 */
		foreach ( $itens as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$situacao = strtoupper( (string) ( $item['situacao'] ?? '' ) );
			if ( 'E' === $situacao ) {
				continue;
			}
			$canon    = self::resposta_para_canonico( $item );
			$sku_item = trim( (string) ( $canon['sku'] ?? '' ) );
			if ( '' !== $sku_item && 0 === strcasecmp( $sku_item, $sku ) ) {
				return $canon;
			}
		}

		return null;
	}

	public function obter_produto( int $tiny_id ) {
		$resp = VH_Tiny_Client::requisicao( 'GET', 'produtos/' . $tiny_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$prod = self::extrair_produto( $resp );
		$canon = self::resposta_para_canonico( $prod );

		if ( 'variavel' === ( $canon['tipo'] ?? '' ) && empty( $canon['variacoes'] ) ) {
			$vars_resp = VH_Tiny_Client::requisicao( 'GET', 'produtos/' . $tiny_id . '/variacoes' );
			if ( ! is_wp_error( $vars_resp ) ) {
				$itens = $vars_resp['itens'] ?? $vars_resp['items'] ?? $vars_resp['variacoes'] ?? [];
				if ( is_array( $itens ) && $itens ) {
					$prod['variacoes'] = $itens;
					$canon = self::resposta_para_canonico( $prod );
				}
			}
		}

		return $canon;
	}

	public function criar_produto( array $canonico, bool $somente_pai = false ) {
		$is_variavel = 'variavel' === ( $canonico['tipo'] ?? '' );
		$body        = self::canonico_para_v3( $canonico, 'completo', false, false, true );

		if ( $is_variavel ) {
			$variacoes = [];
			$fonte     = (array) ( $canonico['variacoes'] ?? [] );
			if ( $somente_pai ) {
				/*
				 * Tiny exige ao menos 1 variação no POST de produto tipo V.
				 * O restante é sincronizado depois via sincronizar_variacoes().
				 */
				$primeira = reset( $fonte );
				if ( is_array( $primeira ) ) {
					$variacoes[] = self::variacao_canonico_para_v3( $primeira );
				}
			} else {
				foreach ( $fonte as $var ) {
					if ( is_array( $var ) ) {
						$variacoes[] = self::variacao_canonico_para_v3( $var );
					}
				}
			}
			if ( ! $variacoes ) {
				return new WP_Error(
					'vh_tiny_sem_variacoes',
					__( 'Produto variável sem variações para enviar ao Tiny.', 'vapor-hub-loja' )
				);
			}
			$body['variacoes'] = $variacoes;
		}

		$resp = VH_Tiny_Client::requisicao(
			'POST',
			'produtos',
			[ 'body' => $body ]
		);
		if ( is_wp_error( $resp ) ) {
			self::registrar_debug_payload( 'POST produtos', $body, $resp );
			return $resp;
		}

		$prod    = self::extrair_produto( $resp );
		$tiny_id = VH_Tiny_Map::extrair_tiny_id( $prod );

		return [
			'id_tiny' => $tiny_id,
			'sku'     => VH_Tiny_Map::extrair_sku( $prod ),
		];
	}

	public function excluir_produto( int $tiny_id ) {
		if ( $tiny_id <= 0 ) {
			return true;
		}

		$resp = VH_Tiny_Client::requisicao(
			'DELETE',
			'produtos/' . $tiny_id,
			[ 'body' => [ 'excluirAnexosVinculados' => true ] ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	public function atualizar_produto( int $tiny_id, array $canonico ) {
		$omitir_grade = 'variavel' === ( $canonico['tipo'] ?? '' );
		$body         = self::canonico_para_v3( $canonico, 'completo', $omitir_grade, true );
		$resp         = VH_Tiny_Client::requisicao(
			'PUT',
			'produtos/' . $tiny_id,
			[ 'body' => $body ]
		);
		if ( is_wp_error( $resp ) ) {
			self::registrar_debug_payload( 'PUT produtos/' . $tiny_id, $body, $resp );
			return $resp;
		}
		return true;
	}

	/**
	 * Importa imagens da loja para o cadastro visual do Tiny (anexos internos).
	 *
	 * Vai pelo endpoint dedicado `produtos/{id}/anexos`, que aceita `externo=false`
	 * em cadastro já existente. O fluxo antigo excluía o produto para recriá-lo
	 * com anexo interno — e uma falha na recriação apagava o cadastro do ERP.
	 * Aqui nunca há DELETE de produto.
	 *
	 * @return true|WP_Error
	 */
	public function sincronizar_imagens( int $tiny_id, array $canonico ) {
		if ( empty( $canonico['imagens'] ) || ! is_array( $canonico['imagens'] ) ) {
			return true;
		}

		if ( self::produto_tem_imagem_importada( $tiny_id ) ) {
			return true;
		}

		$anexos = [];
		foreach ( $canonico['imagens'] as $url ) {
			$url = is_array( $url ) ? (string) ( $url['url'] ?? '' ) : (string) $url;
			if ( '' === $url ) {
				continue;
			}
			$anexos[] = [
				'url'     => $url,
				'externo' => false,
			];
		}

		if ( ! $anexos ) {
			return true;
		}

		$resp = VH_Tiny_Client::requisicao(
			'PUT',
			'produtos/' . $tiny_id . '/anexos',
			[ 'body' => $anexos ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		return true;
	}

	/**
	 * Imagem hospedada no Tiny (externo=false), não apenas URL externa.
	 */
	private static function produto_tem_imagem_importada( int $tiny_id ): bool {
		$resp = VH_Tiny_Client::requisicao( 'GET', 'produtos/' . $tiny_id . '/anexos' );
		if ( is_wp_error( $resp ) ) {
			return false;
		}

		$anexos = $resp;
		if ( isset( $resp['anexos'] ) && is_array( $resp['anexos'] ) ) {
			$anexos = $resp['anexos'];
		}
		if ( ! is_array( $anexos ) ) {
			return false;
		}

		foreach ( $anexos as $anexo ) {
			if ( ! is_array( $anexo ) ) {
				continue;
			}
			if ( ! empty( $anexo['externo'] ) ) {
				continue;
			}
			$url = (string) ( $anexo['url'] ?? '' );
			if ( '' !== $url && ( str_contains( $url, 'tiny-anexos' ) || str_contains( $url, 'tiny.com.br' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ativa "Controlar estoque" e alinha saldo (produto simples).
	 *
	 * @return true|WP_Error
	 */
	public function sincronizar_estoque( int $tiny_id, array $canonico ) {
		if ( 'variavel' === ( $canonico['tipo'] ?? '' ) ) {
			return true;
		}

		return self::sincronizar_estoque_item( $tiny_id, $canonico );
	}

	/**
	 * Saldo puro: só o lançamento de balanço, sem PUT no cadastro.
	 *
	 * É o caminho de uma venda no site — mexer no cadastro aqui reescreveria
	 * campos que o lojista não tocou.
	 *
	 * @param array<string, mixed> $item
	 * @return true|WP_Error
	 */
	public function sincronizar_saldo( int $tiny_id, array $item ) {
		if ( $tiny_id <= 0 ) {
			return new WP_Error( 'vh_tiny_sem_vinculo', __( 'Item sem vínculo no Tiny.', 'vapor-hub-loja' ) );
		}
		if ( ! self::canonico_gerencia_estoque( $item ) || ! isset( $item['estoque'] ) || ! is_numeric( $item['estoque'] ) ) {
			return true;
		}

		return self::lancar_estoque_balanco(
			$tiny_id,
			(float) $item['estoque'],
			(float) ( $item['preco_regular'] ?? 0 )
		);
	}

	/**
	 * @param array<string, mixed> $fonte Item canônico (produto ou variação).
	 * @return true|WP_Error
	 */
	private static function sincronizar_estoque_item( int $tiny_id, array $fonte ) {
		$gerencia = self::canonico_gerencia_estoque( $fonte );

		$body = array_filter(
			[
				'descricao' => (string) ( $fonte['nome'] ?? $fonte['sku'] ?? '' ),
				'sku'       => (string) ( $fonte['sku'] ?? '' ),
				'estoque'   => [ 'controlar' => $gerencia ],
			]
		);

		$resp = VH_Tiny_Client::requisicao(
			'PUT',
			'produtos/' . $tiny_id,
			[ 'body' => $body ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		if ( ! $gerencia || ! isset( $fonte['estoque'] ) || ! is_numeric( $fonte['estoque'] ) ) {
			return true;
		}

		return self::lancar_estoque_balanco(
			$tiny_id,
			(float) $fonte['estoque'],
			(float) ( $fonte['preco_regular'] ?? 0 )
		);
	}

	/**
	 * @return true|WP_Error
	 */
	private static function lancar_estoque_balanco( int $tiny_id, float $quantidade, float $preco_unitario ) {
		$atual = VH_Tiny_Client::requisicao( 'GET', 'estoque/' . $tiny_id );
		if ( ! is_wp_error( $atual ) ) {
			$saldo = self::extrair_estoque_valor( $atual );
			if ( null !== $saldo && (float) $saldo === $quantidade ) {
				return true;
			}
		}

		$preco = $preco_unitario > 0 ? $preco_unitario : 0.01;

		$resp = VH_Tiny_Client::requisicao(
			'POST',
			'estoque/' . $tiny_id,
			[
				'body' => [
					'tipo'          => 'B',
					'quantidade'    => $quantidade,
					'precoUnitario' => $preco,
				],
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $fonte
	 */
	private static function canonico_gerencia_estoque( array $fonte ): bool {
		if ( array_key_exists( 'gerencia_estoque', $fonte ) ) {
			return (bool) $fonte['gerencia_estoque'];
		}

		return isset( $fonte['estoque'] ) && is_numeric( $fonte['estoque'] );
	}

	/**
	 * @param array<string, mixed> $fonte
	 * @return array<string, mixed>
	 */
	private static function montar_estoque_payload_v3( array $fonte, bool $criacao ): array {
		$gerencia = self::canonico_gerencia_estoque( $fonte );

		if ( ! $gerencia ) {
			return [ 'controlar' => false ];
		}

		$estoque = [ 'controlar' => true ];
		if ( $criacao && isset( $fonte['estoque'] ) && is_numeric( $fonte['estoque'] ) ) {
			$estoque['inicial'] = (float) $fonte['estoque'];
		}

		return $estoque;
	}

	public function sincronizar_variacoes( int $tiny_id_pai, array $canonico ) {
		$variacoes = $canonico['variacoes'] ?? [];
		if ( ! is_array( $variacoes ) || ! $variacoes ) {
			return true;
		}

		$mavh_sku_tiny = $this->mavh_variacoes_por_sku( $tiny_id_pai );

		$mapa = [];
		foreach ( $variacoes as $var ) {
			if ( ! is_array( $var ) ) {
				continue;
			}
			$body        = self::variacao_canonico_para_v3( $var );
			$sku_local   = strtolower( trim( (string) ( $var['sku'] ?? '' ) ) );
			$tiny_var_id = (int) ( $var['tiny_id'] ?? 0 );

			if ( $tiny_var_id <= 0 && '' !== $sku_local && isset( $mavh_sku_tiny[ $sku_local ] ) ) {
				$tiny_var_id = (int) $mavh_sku_tiny[ $sku_local ];
			}

			if ( $tiny_var_id > 0 ) {
				$resp = VH_Tiny_Client::requisicao(
					'PUT',
					'produtos/' . $tiny_id_pai . '/variacoes/' . $tiny_var_id,
					[ 'body' => $body ]
				);
			} else {
				$resp = VH_Tiny_Client::requisicao(
					'POST',
					'produtos/' . $tiny_id_pai . '/variacoes',
					[ 'body' => $body ]
				);
				if ( ! is_wp_error( $resp ) ) {
					$tiny_var_id = VH_Tiny_Map::extrair_tiny_id( self::extrair_produto( $resp ) );
				} elseif ( '' !== $sku_local ) {
					$mavh_sku_tiny = $this->mavh_variacoes_por_sku( $tiny_id_pai, true );
					if ( isset( $mavh_sku_tiny[ $sku_local ] ) ) {
						$tiny_var_id = (int) $mavh_sku_tiny[ $sku_local ];
						$resp        = VH_Tiny_Client::requisicao(
							'PUT',
							'produtos/' . $tiny_id_pai . '/variacoes/' . $tiny_var_id,
							[ 'body' => $body ]
						);
					}
				}
			}

			if ( is_wp_error( $resp ) ) {
				return $resp;
			}

			if ( $tiny_var_id > 0 ) {
				$var_fonte = array_merge(
					$var,
					[
						'nome' => (string) ( $var['sku'] ?? '' ),
					]
				);
				$est_resp  = self::sincronizar_estoque_item( $tiny_var_id, $var_fonte );
				if ( is_wp_error( $est_resp ) ) {
					return $est_resp;
				}
			}

			$mapa[] = [
				'variacao_id' => (int) ( $var['variacao_id'] ?? 0 ),
				'tiny_id'     => $tiny_var_id,
				'sku'         => (string) ( $var['sku'] ?? '' ),
			];
		}

		return [ 'variacoes' => $mapa ];
	}

	/**
	 * @return array<string, int> sku lowercase => tiny_id
	 */
	private function mavh_variacoes_por_sku( int $tiny_id_pai, bool $forcar = false ): array {
		static $cache = [];

		if ( ! $forcar && isset( $cache[ $tiny_id_pai ] ) ) {
			return $cache[ $tiny_id_pai ];
		}

		$mapa   = [];
		$remoto = $this->obter_produto( $tiny_id_pai );
		if ( ! is_wp_error( $remoto ) && ! empty( $remoto['variacoes'] ) && is_array( $remoto['variacoes'] ) ) {
			foreach ( $remoto['variacoes'] as $rv ) {
				if ( ! is_array( $rv ) ) {
					continue;
				}
				$sku = strtolower( trim( (string) ( $rv['sku'] ?? '' ) ) );
				$id  = (int) ( $rv['tiny_id'] ?? 0 );
				if ( '' !== $sku && $id > 0 ) {
					$mapa[ $sku ] = $id;
				}
			}
		}

		$cache[ $tiny_id_pai ] = $mapa;
		return $mapa;
	}

	public function remover_variacao( int $tiny_id_pai, int $tiny_id_variacao ) {
		if ( $tiny_id_variacao <= 0 ) {
			return true;
		}
		$resp = VH_Tiny_Client::requisicao(
			'DELETE',
			'produtos/' . $tiny_id_pai . '/variacoes/' . $tiny_id_variacao
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

		$resp = VH_Tiny_Client::requisicao( 'GET', 'categorias/todas' );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$raizes = is_array( $resp ) ? $resp : ( $resp['itens'] ?? $resp['items'] ?? [] );
		if ( ! is_array( $raizes ) ) {
			return null;
		}

		$alvo = self::normalizar_caminho_categoria( $caminho );

		foreach ( self::achatar_categorias_v3( $raizes ) as $item ) {
			if ( self::caminhos_categoria_coincidem( $alvo, $item['caminho'] ) || self::caminhos_categoria_coincidem( $alvo, $item['nome'] ) ) {
				return [
					'id_tiny' => VH_Tiny_Map::extrair_tiny_id( $item['raw'] ),
					'nome'    => $item['nome'],
					'caminho' => $item['caminho'],
				];
			}
		}

		return null;
	}

	public function criar_categoria( array $canonico ) {
		$body = [
			'descricao' => (string) ( $canonico['nome'] ?? '' ),
		];
		if ( ! empty( $canonico['pai_id_tiny'] ) ) {
			$body['idCategoriaPai'] = (int) $canonico['pai_id_tiny'];
		}
		$resp = VH_Tiny_Client::requisicao( 'POST', 'categorias', [ 'body' => $body ] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return [ 'id_tiny' => VH_Tiny_Map::extrair_tiny_id( $resp ) ];
	}

	public function atualizar_categoria( int $tiny_id, array $canonico ) {
		$body = [
			'descricao' => (string) ( $canonico['nome'] ?? '' ),
		];
		if ( ! empty( $canonico['pai_id_tiny'] ) ) {
			$body['idCategoriaPai'] = (int) $canonico['pai_id_tiny'];
		}
		$resp = VH_Tiny_Client::requisicao( 'PUT', 'categorias/' . $tiny_id, [ 'body' => $body ] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		return true;
	}

	public function listar_categorias() {
		$resp = VH_Tiny_Client::requisicao( 'GET', 'categorias/todas' );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$raizes = is_array( $resp ) ? $resp : ( $resp['itens'] ?? $resp['items'] ?? [] );
		if ( ! is_array( $raizes ) ) {
			return [];
		}

		$saida = [];
		foreach ( self::achatar_categorias_v3( $raizes ) as $item ) {
			$id = VH_Tiny_Map::extrair_tiny_id( $item['raw'] );
			if ( $id <= 0 ) {
				continue;
			}
			$saida[] = [
				'id_tiny' => $id,
				'nome'    => $item['nome'],
				'caminho' => $item['caminho'],
			];
		}

		usort(
			$saida,
			static fn( $a, $b ) => strcasecmp( (string) $a['caminho'], (string) $b['caminho'] )
		);

		return $saida;
	}

	public function listar_produtos( int $pagina, int $por_pagina ) {
		$por_pagina = max( 1, min( 100, $por_pagina ) );
		$offset     = max( 0, ( $pagina - 1 ) * $por_pagina );
		$resp       = VH_Tiny_Client::requisicao(
			'GET',
			'produtos',
			[
				'query' => [
					'limit'  => $por_pagina,
					'offset' => $offset,
				],
			]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$itens_raw = $resp['itens'] ?? $resp['items'] ?? [];
		$itens     = [];
		if ( is_array( $itens_raw ) ) {
			foreach ( $itens_raw as $item ) {
				if ( is_array( $item ) ) {
					$itens[] = self::resposta_para_canonico( $item );
				}
			}
		}

		$total = (int) ( $resp['paginacao']['total'] ?? $resp['total'] ?? count( $itens ) );

		return [
			'itens'  => $itens,
			'total'  => $total,
			'pagina' => $pagina,
		];
	}

	public function obter_estoque( int $tiny_id ) {
		$resp = VH_Tiny_Client::requisicao( 'GET', 'estoque/' . $tiny_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$est = $resp;
		if ( isset( $resp['estoque'] ) && is_array( $resp['estoque'] ) ) {
			$est = $resp['estoque'];
		}

		$gerencia = false;
		$prod     = VH_Tiny_Client::requisicao( 'GET', 'produtos/' . $tiny_id );
		if ( ! is_wp_error( $prod ) && ! empty( $prod['estoque'] ) && is_array( $prod['estoque'] ) ) {
			$gerencia = ! empty( $prod['estoque']['controlar'] );
		}

		return [
			'id_tiny'          => $tiny_id,
			'estoque'          => self::extrair_estoque_valor( $est ),
			'gerencia_estoque' => $gerencia,
		];
	}

	public function criar_pedido( array $canonico ) {
		$contato = self::resolver_contato_v3( $canonico );
		if ( is_wp_error( $contato ) ) {
			return $contato;
		}

		$resp = VH_Tiny_Client::requisicao(
			'POST',
			'pedidos',
			[ 'body' => self::pedido_canonico_para_v3( $canonico, $contato ) ]
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$id = 0;
		foreach ( [ 'id', 'idPedido', 'id_pedido' ] as $chave ) {
			if ( ! empty( $resp[ $chave ] ) ) {
				$id = absint( $resp[ $chave ] );
				break;
			}
		}
		return [
			'id_tiny_pedido' => $id,
			'id_contato'     => $contato,
		];
	}

	public function listar_atualizacoes_estoque( string $desde ) {
		unset( $desde );
		return [];
	}

	public function normalizar_webhook( array $payload ): array {
		$tipo    = sanitize_key( (string) ( $payload['tipo'] ?? $payload['event'] ?? $payload['evento'] ?? '' ) );
		$tiny_id = absint( $payload['idProduto'] ?? $payload['id_produto'] ?? $payload['id'] ?? 0 );
		$sku     = sanitize_text_field( (string) ( $payload['codigo'] ?? $payload['sku'] ?? '' ) );

		if ( str_contains( $tipo, 'estoque' ) || str_contains( $tipo, 'stock' ) ) {
			return [ 'tipo' => 'estoque', 'tiny_id' => $tiny_id, 'sku' => $sku ];
		}
		if ( str_contains( $tipo, 'produto' ) || str_contains( $tipo, 'product' ) || $tiny_id > 0 ) {
			return [ 'tipo' => 'produto', 'tiny_id' => $tiny_id, 'sku' => $sku ];
		}
		return [ 'tipo' => $tipo ?: 'desconhecido', 'tiny_id' => $tiny_id, 'sku' => $sku ];
	}

	/**
	 * @param array<string, mixed> $canonico
	 * @param string               $modo criar_pai|complemento_pai|completo
	 * @return array<string, mixed>
	 */
	private static function canonico_para_v3( array $canonico, string $modo = 'completo', bool $omitir_grade = false, bool $omitir_anexos = false, bool $criacao = false ): array {
		$is_variavel = 'variavel' === ( $canonico['tipo'] ?? '' );
		$nome        = (string) ( $canonico['nome'] ?? '' );

		$payload = [
			'descricao' => $nome,
			'tipo'      => $is_variavel ? 'V' : 'S',
			'situacao'  => ! empty( $canonico['publicado'] ) ? 'A' : 'I',
		];

		if ( '' !== (string) ( $canonico['sku'] ?? '' ) ) {
			$payload['sku'] = (string) $canonico['sku'];
		}

		if ( '' !== (string) ( $canonico['descricao'] ?? '' ) ) {
			$payload['descricaoComplementar'] = (string) $canonico['descricao'];
		}
		if ( '' !== (string) ( $canonico['descricao_curta'] ?? '' ) ) {
			$payload['observacoes'] = (string) $canonico['descricao_curta'];
		}

		$dimensoes = self::dimensoes_payload_v3( $canonico );
		if ( $dimensoes ) {
			$payload['dimensoes'] = $dimensoes;
		}

		$payload += self::fiscal_payload_v3( $canonico );

		if ( ! $is_variavel ) {
			if ( isset( $canonico['preco_regular'] ) && '' !== (string) $canonico['preco_regular'] ) {
				$payload['precos'] = [ 'preco' => (float) $canonico['preco_regular'] ];
				if ( ! empty( $canonico['preco_promo'] ) ) {
					$payload['precos']['precoPromocional'] = (float) $canonico['preco_promo'];
				}
			}
			$payload['estoque'] = self::montar_estoque_payload_v3( $canonico, $criacao );
		}

		if ( ! empty( $canonico['categorias'] ) && is_array( $canonico['categorias'] ) ) {
			foreach ( $canonico['categorias'] as $cat ) {
				if ( ! is_array( $cat ) ) {
					continue;
				}
				$id_tiny = (int) ( $cat['id_tiny'] ?? 0 );
				if ( $id_tiny > 0 ) {
					$payload['categoria'] = [ 'id' => $id_tiny ];
					break;
				}
			}
		}

		if ( ! $omitir_anexos && ! empty( $canonico['imagens'] ) && is_array( $canonico['imagens'] ) ) {
			$anexos = self::montar_anexos_imagem_v3( $canonico['imagens'] );
			if ( $anexos ) {
				$payload['anexos'] = $anexos;
			}
		}

		if ( $is_variavel && ! $omitir_grade && ! empty( $canonico['grade_atributos'] ) ) {
			$grade = [];
			foreach ( (array) $canonico['grade_atributos'] as $eixo ) {
				if ( ! is_array( $eixo ) ) {
					continue;
				}
				$rotulo = class_exists( 'VH_Tiny_Mapping_Service' )
					? VH_Tiny_Mapping_Service::rotulo_atributo(
						(string) ( $eixo['taxonomy'] ?? '' ),
						(string) ( $eixo['chave'] ?? '' )
					)
					: (string) ( $eixo['chave'] ?? '' );
				if ( '' !== $rotulo ) {
					$grade[] = $rotulo;
				}
			}
			if ( $grade ) {
				$payload['grade'] = $grade;
			}
		}

		return array_filter(
			$payload,
			static fn( $v ) => null !== $v && '' !== $v && [] !== $v
		);
	}

	/**
	 * Bloco `dimensoes` do Tiny a partir do canônico.
	 *
	 * Só sai o que está preenchido: zero no payload sobrescreveria a medida boa
	 * que já estiver no cadastro do ERP. Aceita o campo legado `peso` para
	 * produto que ainda não passou pelo formulário novo.
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function dimensoes_payload_v3( array $canonico ): array {
		$envio = is_array( $canonico['envio'] ?? null ) ? $canonico['envio'] : [];
		$peso  = (float) ( $envio['peso_bruto'] ?? $canonico['peso'] ?? 0 );

		$dimensoes = array_filter(
			[
				'largura'           => (float) ( $envio['largura'] ?? 0 ),
				'altura'            => (float) ( $envio['altura'] ?? 0 ),
				'comprimento'       => (float) ( $envio['comprimento'] ?? 0 ),
				'pesoBruto'         => $peso,
				'pesoLiquido'       => (float) ( $envio['peso_liquido'] ?? $peso ),
				'quantidadeVolumes' => (int) ( $envio['volumes'] ?? 0 ),
			],
			static fn( $v ) => $v > 0
		);

		$embalagem = (int) ( $envio['embalagem_tipo'] ?? 0 );
		if ( $dimensoes && $embalagem > 0 ) {
			$dimensoes['embalagem'] = [ 'tipo' => $embalagem ];
		}

		return $dimensoes;
	}

	/**
	 * NCM, GTIN, unidade e marca do payload de produto.
	 *
	 * A marca só vai como referência por id: a API Tiny devolve 403 no
	 * cadastro de marcas, e mandar marca por nome derrubaria o produto inteiro.
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function fiscal_payload_v3( array $canonico ): array {
		$fiscal = is_array( $canonico['fiscal'] ?? null ) ? $canonico['fiscal'] : [];

		$payload = array_filter(
			[
				'ncm'     => (string) ( $fiscal['ncm'] ?? '' ),
				'gtin'    => (string) ( $fiscal['gtin'] ?? '' ),
				'unidade' => (string) ( $fiscal['unidade'] ?? '' ),
			],
			static fn( string $v ): bool => '' !== $v
		);

		$marca_id = (int) ( $fiscal['marca_id'] ?? 0 );
		if ( $marca_id > 0 ) {
			$payload['marca'] = [ 'id' => $marca_id ];
		}

		return $payload;
	}

	/**
	 * Identidade fiscal do cadastro do Tiny em formato canônico.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function fiscal_resposta_v3( array $raw ): array {
		$marca    = is_array( $raw['marca'] ?? null ) ? $raw['marca'] : [];
		$marca_id = (int) ( $marca['id'] ?? 0 );

		return array_filter(
			[
				'ncm'      => (string) ( $raw['ncm'] ?? '' ),
				'gtin'     => (string) ( $raw['gtin'] ?? $raw['gtinEmbalagem'] ?? '' ),
				'unidade'  => strtoupper( (string) ( $raw['unidade'] ?? '' ) ),
				'marca'    => (string) ( $marca['nome'] ?? ( is_string( $raw['marca'] ?? null ) ? $raw['marca'] : '' ) ),
				'marca_id' => $marca_id > 0 ? $marca_id : null,
			],
			static fn( $v ) => null !== $v && '' !== $v
		);
	}

	/**
	 * Medidas do cadastro do Tiny em formato canônico (zero = não informado).
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function envio_resposta_v3( array $raw ): array {
		$dim = is_array( $raw['dimensoes'] ?? null ) ? $raw['dimensoes'] : [];
		if ( ! $dim ) {
			return [];
		}

		$envio = array_filter(
			[
				'peso_bruto'     => (float) ( $dim['pesoBruto'] ?? 0 ),
				'peso_liquido'   => (float) ( $dim['pesoLiquido'] ?? $dim['pesoBruto'] ?? 0 ),
				'largura'        => (float) ( $dim['largura'] ?? 0 ),
				'altura'         => (float) ( $dim['altura'] ?? 0 ),
				'comprimento'    => (float) ( $dim['comprimento'] ?? 0 ),
				'volumes'        => (int) ( $dim['quantidadeVolumes'] ?? 0 ),
				'embalagem_tipo' => (int) ( $dim['embalagem']['tipo'] ?? 0 ),
			],
			static fn( $v ) => $v > 0
		);

		return $envio;
	}

	/**
	 * Anexos de imagem: externo=false faz o Tiny importar a URL e exibir no cadastro.
	 *
	 * @param array<int, mixed> $urls
	 * @return array<int, array{url:string,externo:bool}>
	 */
	private static function montar_anexos_imagem_v3( array $urls ): array {
		$anexos = [];
		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || '' === trim( $url ) ) {
				continue;
			}
			$anexos[] = [
				'url'     => esc_url_raw( $url ),
				'externo' => false,
			];
		}
		return $anexos;
	}

	/**
	 * @param array<string, mixed> $var
	 * @return array<string, mixed>
	 */
	private static function variacao_canonico_para_v3( array $var ): array {
		$grade = [];
		foreach ( (array) ( $var['grade'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$chave = (string) ( $item['chave'] ?? '' );
			if ( class_exists( 'VH_Tiny_Mapping_Service' ) && ! empty( $item['taxonomy'] ) ) {
				$chave = VH_Tiny_Mapping_Service::rotulo_atributo( (string) $item['taxonomy'], $chave );
			}
			$grade[] = [
				'chave' => $chave,
				'valor' => (string) ( $item['valor'] ?? '' ),
			];
		}

		$body = [
			'sku'   => (string) ( $var['sku'] ?? '' ),
			'grade' => $grade,
		];

		if ( isset( $var['preco_regular'] ) && '' !== (string) $var['preco_regular'] ) {
			$body['precos'] = [ 'preco' => (float) $var['preco_regular'] ];
		}
		if ( self::canonico_gerencia_estoque( $var ) && isset( $var['estoque'] ) && is_numeric( $var['estoque'] ) ) {
			$body['estoque'] = [ 'inicial' => (float) $var['estoque'] ];
		}

		return array_filter(
			$body,
			static fn( $v ) => null !== $v && '' !== $v && [] !== $v
		);
	}

	/**
	 * Pedido no formato `CriarPedidoModelRequest`: item por `produto.id`, número
	 * da loja em `ecommerce`, frete/desconto em `valor*` e endereço com
	 * `enderecoNro`, `bairro` e `municipio` separados.
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function pedido_canonico_para_v3( array $canonico, int $id_contato = 0 ): array {
		$itens = [];
		foreach ( (array) ( $canonico['itens'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['id_tiny'] ) ) {
				continue;
			}
			$itens[] = array_filter(
				[
					'produto'       => [ 'id' => (int) $item['id_tiny'] ],
					'quantidade'    => (float) ( $item['quantidade'] ?? 1 ),
					'valorUnitario' => (float) ( $item['valor_unitario'] ?? 0 ),
					'infoAdicional' => (string) ( $item['nome'] ?? '' ),
				],
				static fn( $v ) => null !== $v && '' !== $v && [] !== $v
			);
		}

		$entrega = is_array( $canonico['entrega'] ?? null ) ? $canonico['entrega'] : [];
		$frete   = is_array( $canonico['frete'] ?? null ) ? $canonico['frete'] : [];

		$numero = (string) ( $canonico['numero'] ?? '' );
		$origem = (string) ( $canonico['origem_loja'] ?? '' );
		if ( '' !== $origem && '' !== $numero && ! str_starts_with( $numero, $origem . '-' ) ) {
			$numero = $origem . '-' . $numero;
		}

		$body = [
			'numeroOrdemCompra' => $numero,
			'data'              => (string) ( $canonico['data'] ?? gmdate( 'Y-m-d' ) ),
			'ecommerce'         => array_filter( [ 'numeroPedidoEcommerce' => $numero ] ),
			'idContato'         => $id_contato > 0 ? $id_contato : null,
			'enderecoEntrega'   => array_filter(
				[
					'nomeDestinatario' => (string) ( $entrega['nome'] ?? '' ),
					'endereco'         => (string) ( $entrega['endereco'] ?? '' ),
					'enderecoNro'      => (string) ( $entrega['numero'] ?? '' ),
					'complemento'      => (string) ( $entrega['complemento'] ?? '' ),
					'bairro'           => (string) ( $entrega['bairro'] ?? '' ),
					'municipio'        => (string) ( $entrega['cidade'] ?? '' ),
					'uf'               => (string) ( $entrega['uf'] ?? '' ),
					'cep'              => (string) ( $entrega['cep'] ?? '' ),
					'fone'             => (string) ( $entrega['telefone'] ?? '' ),
				],
				static fn( $v ) => '' !== $v
			),
			'itens'             => $itens,
			'valorFrete'        => ( (float) ( $frete['valor'] ?? 0 ) ) > 0 ? (float) $frete['valor'] : null,
			'valorDesconto'     => ( (float) ( $canonico['desconto'] ?? 0 ) ) > 0 ? (float) $canonico['desconto'] : null,
			'pagamento'         => self::pagamento_payload_v3( $canonico ),
			'observacoes'       => self::observacoes_pedido_v3( $canonico ),
		];

		return array_filter(
			$body,
			static fn( $v ) => null !== $v && '' !== $v && [] !== $v
		);
	}

	/**
	 * A v3 aceita forma de recebimento e meio de pagamento só por id do cadastro
	 * do ERP, que a loja não tem mapeado. Enquanto isso, a forma escolhida no
	 * checkout viaja na observação da parcela — visível no pedido, sem inventar id.
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function pagamento_payload_v3( array $canonico ): array {
		$pagamento = is_array( $canonico['pagamento'] ?? null ) ? $canonico['pagamento'] : [];
		$metodo    = trim( (string) ( $pagamento['metodo'] ?? $pagamento['codigo'] ?? '' ) );
		$total     = (float) ( $canonico['total'] ?? 0 );

		if ( '' === $metodo && $total <= 0 ) {
			return [];
		}

		$parcela = array_filter(
			[
				'dias'        => 0,
				'valor'       => $total > 0 ? $total : null,
				'observacoes' => $metodo,
			],
			static fn( $v ) => null !== $v && '' !== $v
		);

		return [ 'parcelas' => [ $parcela ] ];
	}

	/**
	 * @param array<string, mixed> $canonico
	 */
	private static function observacoes_pedido_v3( array $canonico ): string {
		$linhas = [ (string) ( $canonico['observacoes'] ?? '' ) ];

		$pagamento = is_array( $canonico['pagamento'] ?? null ) ? $canonico['pagamento'] : [];
		$metodo    = trim( (string) ( $pagamento['metodo'] ?? '' ) );
		if ( '' !== $metodo ) {
			/* translators: %s: payment method title */
			$linhas[] = sprintf( __( 'Pagamento: %s', 'vapor-hub-loja' ), $metodo );
		}

		$frete   = is_array( $canonico['frete'] ?? null ) ? $canonico['frete'] : [];
		$servico = trim( (string) ( $frete['servico'] ?? '' ) );
		if ( '' !== $servico ) {
			/* translators: %s: shipping method title */
			$linhas[] = sprintf( __( 'Frete: %s', 'vapor-hub-loja' ), $servico );
		}

		return trim( implode( ' | ', array_filter( $linhas ) ) );
	}

	/**
	 * Contato do pedido no Tiny: reaproveita o vínculo já gravado, senão procura
	 * por CPF/CNPJ (ou nome) e só cria quando não existe.
	 *
	 * @param array<string, mixed> $canonico
	 * @return int|WP_Error
	 */
	private static function resolver_contato_v3( array $canonico ) {
		$cliente = is_array( $canonico['cliente'] ?? null ) ? $canonico['cliente'] : [];

		$vinculado = absint( $cliente['id_tiny'] ?? 0 );
		if ( $vinculado > 0 ) {
			return $vinculado;
		}

		$documento = preg_replace( '/\D+/', '', (string) ( $cliente['cpf_cnpj'] ?? '' ) ) ?? '';
		$nome      = trim( (string) ( $cliente['nome'] ?? '' ) );

		if ( '' === $documento && '' === $nome ) {
			return new WP_Error(
				'vh_tiny_contato',
				__( 'Pedido sem nome nem CPF/CNPJ: o Tiny exige contato para criar o pedido.', 'vapor-hub-loja' )
			);
		}

		$busca = '' !== $documento ? [ 'cpfCnpj' => $documento ] : [ 'nome' => $nome ];
		$resp  = VH_Tiny_Client::requisicao( 'GET', 'contatos', [ 'query' => $busca ] );

		if ( ! is_wp_error( $resp ) ) {
			foreach ( (array) ( $resp['itens'] ?? [] ) as $item ) {
				$achado = absint( is_array( $item ) ? ( $item['id'] ?? 0 ) : 0 );
				if ( $achado > 0 ) {
					return $achado;
				}
			}
		}

		$criado = VH_Tiny_Client::requisicao(
			'POST',
			'contatos',
			[ 'body' => self::contato_payload_v3( $cliente, $canonico ) ]
		);
		if ( is_wp_error( $criado ) ) {
			return $criado;
		}

		$id = absint( $criado['id'] ?? 0 );
		if ( $id <= 0 ) {
			return new WP_Error( 'vh_tiny_contato', __( 'O Tiny não devolveu o id do contato criado.', 'vapor-hub-loja' ) );
		}

		return $id;
	}

	/**
	 * @param array<string, mixed> $cliente
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	private static function contato_payload_v3( array $cliente, array $canonico ): array {
		$entrega   = is_array( $canonico['entrega'] ?? null ) ? $canonico['entrega'] : [];
		$documento = preg_replace( '/\D+/', '', (string) ( $cliente['cpf_cnpj'] ?? '' ) ) ?? '';

		$endereco = array_filter(
			[
				'endereco'  => (string) ( $entrega['endereco'] ?? '' ),
				'numero'    => (string) ( $entrega['numero'] ?? '' ),
				'bairro'    => (string) ( $entrega['bairro'] ?? '' ),
				'municipio' => (string) ( $entrega['cidade'] ?? '' ),
				'cep'       => (string) ( $entrega['cep'] ?? '' ),
				'uf'        => (string) ( $entrega['uf'] ?? '' ),
			],
			static fn( $v ) => '' !== $v
		);

		return array_filter(
			[
				'nome'        => (string) ( $cliente['nome'] ?? '' ),
				'tipoPessoa'  => strlen( $documento ) > 11 ? 'J' : 'F',
				'cpfCnpj'     => $documento,
				'email'       => (string) ( $cliente['email'] ?? '' ),
				'celular'     => (string) ( $cliente['telefone'] ?? '' ),
				'situacao'    => 'B',
				'endereco'    => $endereco,
			],
			static fn( $v ) => null !== $v && '' !== $v && [] !== $v
		);
	}

	/**
	 * @param array<string, mixed> $resp
	 * @return array<string, mixed>
	 */
	private static function extrair_produto( array $resp ): array {
		if ( isset( $resp['produto'] ) && is_array( $resp['produto'] ) ) {
			return $resp['produto'];
		}
		return $resp;
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private static function resposta_para_canonico( array $raw ): array {
		$preco = null;
		foreach ( [ 'preco', 'precoVenda', 'valor' ] as $chave ) {
			if ( isset( $raw[ $chave ] ) && '' !== (string) $raw[ $chave ] ) {
				$preco = wc_format_decimal( (string) $raw[ $chave ] );
				break;
			}
		}
		$promo = null;
		foreach ( [ 'precoPromocional', 'preco_promocional' ] as $chave ) {
			if ( isset( $raw[ $chave ] ) && '' !== (string) $raw[ $chave ] ) {
				$promo = wc_format_decimal( (string) $raw[ $chave ] );
				break;
			}
		}

		$tipo = 'simples';
		if ( ! empty( $raw['variacoes'] ) || ( isset( $raw['tipoVariacao'] ) && 'P' === (string) $raw['tipoVariacao'] ) ) {
			$tipo = 'variavel';
		}

		$est_dados = self::extrair_estoque_do_produto_v3( $raw );

		$canon = array_filter(
			[
				'tipo'             => $tipo,
				'id_tiny'          => VH_Tiny_Map::extrair_tiny_id( $raw ),
				'sku'              => VH_Tiny_Map::extrair_sku( $raw ),
				'nome'             => (string) ( $raw['descricao'] ?? $raw['nome'] ?? '' ),
				'descricao'        => (string) ( $raw['descricaoComplementar'] ?? $raw['descricao'] ?? '' ),
				'descricao_curta'  => (string) ( $raw['observacoes'] ?? $raw['obs'] ?? $raw['descricao_curta'] ?? '' ),
				'peso'             => (float) ( $raw['dimensoes']['pesoBruto'] ?? $raw['pesoBruto'] ?? $raw['peso'] ?? 0 ),
				'envio'            => self::envio_resposta_v3( $raw ) ?: null,
				'fiscal'           => self::fiscal_resposta_v3( $raw ) ?: null,
				'situacao'         => strtoupper( (string) ( $raw['situacao'] ?? 'A' ) ),
				'publicado'        => ! isset( $raw['situacao'] ) || ! in_array( strtoupper( (string) $raw['situacao'] ), [ 'I', 'E' ], true ),
				'preco_regular'    => $preco,
				'preco_promo'      => $promo,
				'gerencia_estoque' => $est_dados['gerencia_estoque'],
				'estoque'          => $est_dados['estoque'],
			],
			static fn( $v ) => null !== $v
		);

		$vars = self::extrair_variacoes_resposta_v3( $raw );
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
	private static function extrair_variacoes_resposta_v3( array $raw ): array {
		$lista = $raw['variacoes'] ?? [];
		if ( ! is_array( $lista ) ) {
			return [];
		}
		$saida = [];
		foreach ( $lista as $var ) {
			if ( ! is_array( $var ) ) {
				continue;
			}
			$grade = [];
			foreach ( (array) ( $var['grade'] ?? [] ) as $g ) {
				if ( is_array( $g ) ) {
					$grade[] = [
						'chave' => (string) ( $g['descricao'] ?? $g['nome'] ?? '' ),
						'valor' => (string) ( $g['valor'] ?? '' ),
					];
				}
			}
			$var_est = self::extrair_estoque_do_produto_v3( $var );
			$saida[] = array_filter(
				[
					'tiny_id'          => VH_Tiny_Map::extrair_tiny_id( $var ),
					'sku'              => VH_Tiny_Map::extrair_sku( $var ),
					'grade'            => $grade,
					'preco_regular'    => isset( $var['preco'] ) ? wc_format_decimal( (string) $var['preco'] ) : null,
					'gerencia_estoque' => $var_est['gerencia_estoque'],
					'estoque'          => $var_est['estoque'],
				],
				static fn( $v ) => null !== $v && '' !== $v && [] !== $v
			);
		}
		return $saida;
	}

	/**
	 * @param array<int, array<string, mixed>> $nos
	 * @return array<int, array{raw: array<string, mixed>, nome: string, caminho: string}>
	 */
	private static function achatar_categorias_v3( array $nos, string $prefixo = '' ): array {
		$saida = [];

		foreach ( $nos as $no ) {
			if ( ! is_array( $no ) ) {
				continue;
			}

			$nome    = (string) ( $no['descricao'] ?? $no['nome'] ?? '' );
			$caminho = '' !== $prefixo ? $prefixo . ' >> ' . $nome : $nome;

			$saida[] = [
				'raw'     => $no,
				'nome'    => $nome,
				'caminho' => $caminho,
			];

			$filhas = $no['filhas'] ?? [];
			if ( is_array( $filhas ) && $filhas ) {
				$saida = array_merge( $saida, self::achatar_categorias_v3( $filhas, $caminho ) );
			}
		}

		return $saida;
	}

	private static function normalizar_caminho_categoria( string $caminho ): string {
		$caminho = trim( str_replace( [ ' › ', ' > ', ' / ' ], ' >> ', $caminho ) );
		return preg_replace( '/\s*>>\s*/', ' >> ', $caminho ) ?? $caminho;
	}

	private static function caminhos_categoria_coincidem( string $a, string $b ): bool {
		return 0 === strcasecmp( self::normalizar_caminho_categoria( $a ), self::normalizar_caminho_categoria( $b ) );
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array{gerencia_estoque:bool,estoque:?int}
	 */
	private static function extrair_estoque_do_produto_v3( array $raw ): array {
		$bloco = is_array( $raw['estoque'] ?? null ) ? $raw['estoque'] : $raw;

		return [
			'gerencia_estoque' => ! empty( $bloco['controlar'] ),
			'estoque'          => self::extrair_estoque_valor( $bloco ),
		];
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
		if ( ! empty( $fonte['depositos'] ) && is_array( $fonte['depositos'] ) ) {
			$total = 0;
			foreach ( $fonte['depositos'] as $dep ) {
				if ( is_array( $dep ) && isset( $dep['saldo'] ) ) {
					$total += (int) $dep['saldo'];
				}
			}
			return $total;
		}
		return null;
	}

	/**
	 * Log temporário de payload/resposta em erros 5xx (diagnóstico).
	 *
	 * @param array<string, mixed> $body
	 */
	private static function registrar_debug_payload( string $endpoint, array $body, WP_Error $erro ): void {
		$status = (int) ( $erro->get_error_data()['status'] ?? 0 );
		if ( $status < 400 ) {
			return;
		}

		$resumo = wp_json_encode(
			[
				'endpoint' => $endpoint,
				'payload'  => $body,
				'erro'     => $erro->get_error_message(),
			],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( is_string( $resumo ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[PA Tiny] ' . $resumo );
			}
			VH_Tiny::registrar_erro(
				sprintf(
					/* translators: 1: endpoint, 2: JSON payload snippet */
					__( 'Falha Tiny (%1$s). Payload: %2$s', 'vapor-hub-loja' ),
					$endpoint,
					substr( $resumo, 0, 900 )
				)
			);
		}
	}
}

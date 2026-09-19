<?php
/**
 * Tiny ERP em memória, com as rotas v3 que o driver realmente chama.
 *
 * As respostas seguem as formas observadas na conta Tiny compartilhada (GET info com
 * `cpfCnpj`, GET produtos com `itens`/`paginacao.total`, GET estoque com
 * `depositos[].empresa`, categorias/todas como árvore com `filhas`). Permite
 * montar cenários — inclusive a lixeira de 1.790 itens `situacao E`.
 *
 * @package VaporHubLoja\Tests
 */

final class VH_Fake_Tiny_ERP {

	public const CNPJ_LOJA  = '12345678000199';
	public const CNPJ_OUTRA = '98765432000111';

	/** @var array<string, mixed> */
	public static array $info = [];

	/** @var array<int, array<string, mixed>> */
	public static array $produtos = [];

	/** @var array<int, array<int, array<string, mixed>>> */
	public static array $variacoes = [];

	/**
	 * Índice variação → produto pai. O Tiny aceita `produtos/{id}` com id de
	 * variação, e o driver depende disso ao sincronizar estoque por variação.
	 *
	 * @var array<int, int>
	 */
	public static array $variacao_pai = [];

	/** @var array<int, int> */
	public static array $estoque = [];

	/** @var array<int, array<string, mixed>> */
	public static array $categorias = [];

	/** @var array<int, array<string, mixed>> */
	public static array $pedidos = [];

	/** @var array<int, array<string, mixed>> */
	public static array $contatos = [];

	/** @var array<string, array{status:int, body:array<string, mixed>}> */
	public static array $falhas_forcadas = [];

	private static int $proximo_id = 1000;

	public static function reset(): void {
		self::$info = [
			'razaoSocial'  => 'VAPOR HUB COMERCIO LTDA',
			'nome'         => 'VAPOR HUB COMERCIO LTDA',
			'nomeFantasia' => 'Loja Piloto',
			'cpfCnpj'      => self::CNPJ_LOJA,
		];
		self::$produtos        = [];
		self::$variacoes       = [];
		self::$variacao_pai    = [];
		self::$estoque         = [];
		self::$categorias      = [];
		self::$pedidos         = [];
		self::$contatos        = [];
		self::$falhas_forcadas = [];
		/*
		 * O contador NÃO volta a zero entre testes de propósito: o driver mantém
		 * cache estático de variações por id do pai, e ids reutilizados fariam um
		 * teste ver o cache do anterior.
		 */
	}

	public static function novo_id(): int {
		return ++self::$proximo_id;
	}

	public static function definir_cnpj( string $cnpj ): void {
		self::$info['cpfCnpj'] = $cnpj;
	}

	/**
	 * Cadastra um produto no ERP falso.
	 *
	 * @param array<string, mixed> $dados
	 */
	public static function seed_produto( array $dados ): int {
		$id = (int) ( $dados['id'] ?? self::novo_id() );

		self::$produtos[ $id ] = array_merge(
			[
				'id'        => $id,
				'sku'       => '',
				'descricao' => 'Produto ' . $id,
				'tipo'      => 'S',
				'situacao'  => 'A',
				'precos'    => [ 'preco' => 10.0 ],
				'estoque'   => [
					'controlar'    => true,
					'saldo'        => 0,
					'quantidade'   => 0,
				],
			],
			$dados
		);
		self::$produtos[ $id ]['id'] = $id;

		if ( isset( $dados['sku'] ) ) {
			self::$produtos[ $id ]['codigo'] = (string) $dados['sku'];
			unset( self::$produtos[ $id ]['sku'] );
		}

		self::$estoque[ $id ] = (int) ( self::$produtos[ $id ]['estoque']['saldo'] ?? 0 );

		return $id;
	}

	/**
	 * Cenário da conta real: catálogo cheio de itens na lixeira.
	 */
	public static function seed_lixeira( int $quantidade ): void {
		for ( $i = 1; $i <= $quantidade; $i++ ) {
			self::seed_produto(
				[
					'sku'       => 'LIXO-' . $i,
					'descricao' => 'Item antigo ' . $i,
					'situacao'  => 'E',
				]
			);
		}
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public static function seed_categoria( string $nome, int $pai = 0, array $dados = [] ): int {
		$id = (int) ( $dados['id'] ?? self::novo_id() );

		self::$categorias[ $id ] = array_merge(
			[
				'id'             => $id,
				'descricao'      => $nome,
				'idCategoriaPai' => $pai,
			],
			$dados
		);

		return $id;
	}

	/**
	 * Roteador chamado pelo transporte falso.
	 *
	 * @param array<string, mixed>      $query
	 * @param array<string, mixed>|null $body
	 * @return array{status:int, body:array<string, mixed>|array<int, mixed>}
	 */
	public static function responder( string $metodo, string $endpoint, array $query, ?array $body, string $url = '' ): array {
		unset( $url );

		$chave = $metodo . ' ' . $endpoint;
		if ( isset( self::$falhas_forcadas[ $chave ] ) ) {
			$falha = self::$falhas_forcadas[ $chave ];
			unset( self::$falhas_forcadas[ $chave ] );
			return $falha;
		}

		if ( str_contains( $endpoint, 'openid-connect/token' ) ) {
			return [
				'status' => 200,
				'body'   => [
					'access_token'  => 'token-de-teste',
					'refresh_token' => 'refresh-de-teste',
					'expires_in'    => 3600,
				],
			];
		}

		if ( 'info' === $endpoint && 'GET' === $metodo ) {
			return [
				'status' => 200,
				'body'   => self::$info,
			];
		}

		if ( preg_match( '#^produtos/(\d+)/anexos$#', $endpoint, $m ) ) {
			return self::rota_anexos( $metodo, (int) $m[1], $body );
		}

		if ( preg_match( '#^produtos/(\d+)/variacoes/(\d+)$#', $endpoint, $m ) ) {
			return self::rota_variacao( $metodo, (int) $m[1], (int) $m[2], $body );
		}

		if ( preg_match( '#^produtos/(\d+)/variacoes$#', $endpoint, $m ) ) {
			return self::rota_variacoes( $metodo, (int) $m[1], $body );
		}

		if ( preg_match( '#^produtos/(\d+)$#', $endpoint, $m ) ) {
			return self::rota_produto( $metodo, (int) $m[1], $body );
		}

		if ( 'produtos' === $endpoint ) {
			return 'POST' === $metodo ? self::criar_produto( $body ?? [] ) : self::listar_produtos( $query );
		}

		if ( preg_match( '#^estoque/(\d+)$#', $endpoint, $m ) ) {
			return self::rota_estoque( $metodo, (int) $m[1], $body );
		}

		if ( 'categorias/todas' === $endpoint && 'GET' === $metodo ) {
			return [
				'status' => 200,
				'body'   => self::arvore_categorias(),
			];
		}

		if ( 'categorias' === $endpoint && 'POST' === $metodo ) {
			$id = self::seed_categoria(
				(string) ( $body['descricao'] ?? '' ),
				(int) ( $body['idCategoriaPai'] ?? 0 )
			);
			return [
				'status' => 201,
				'body'   => [ 'id' => $id ],
			];
		}

		if ( preg_match( '#^categorias/(\d+)$#', $endpoint, $m ) && 'PUT' === $metodo ) {
			$id = (int) $m[1];
			if ( isset( self::$categorias[ $id ] ) ) {
				self::$categorias[ $id ]['descricao'] = (string) ( $body['descricao'] ?? self::$categorias[ $id ]['descricao'] );
			}
			return [
				'status' => 200,
				'body'   => [ 'id' => $id ],
			];
		}

		if ( 'contatos' === $endpoint ) {
			return 'POST' === $metodo ? self::criar_contato( $body ?? [] ) : self::listar_contatos( $query );
		}

		if ( 'pedidos' === $endpoint && 'POST' === $metodo ) {
			$id                   = self::novo_id();
			self::$pedidos[ $id ] = $body ?? [];
			return [
				'status' => 201,
				'body'   => [
					'id'           => $id,
					'numeroPedido' => $id,
				],
			];
		}

		return [
			'status' => 404,
			'body'   => [ 'message' => 'Rota não mapeada no ERP falso: ' . $chave ],
		];
	}

	/**
	 * Cadastra um contato no ERP falso.
	 *
	 * @param array<string, mixed> $dados
	 */
	public static function seed_contato( array $dados ): int {
		$id = (int) ( $dados['id'] ?? self::novo_id() );

		self::$contatos[ $id ] = array_merge(
			[
				'id'      => $id,
				'nome'    => 'Contato ' . $id,
				'cpfCnpj' => '',
				'email'   => '',
			],
			$dados
		);
		self::$contatos[ $id ]['id'] = $id;

		return $id;
	}

	/**
	 * `GET contatos` filtra por `cpfCnpj` ou `nome`, como na API v3.
	 *
	 * @param array<string, mixed> $query
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function listar_contatos( array $query ): array {
		$cpf_cnpj = preg_replace( '/\D+/', '', (string) ( $query['cpfCnpj'] ?? '' ) ) ?? '';
		$nome     = trim( (string) ( $query['nome'] ?? '' ) );

		$itens = array_values( self::$contatos );

		if ( '' !== $cpf_cnpj ) {
			$itens = array_values(
				array_filter(
					$itens,
					static fn( array $c ): bool => ( preg_replace( '/\D+/', '', (string) ( $c['cpfCnpj'] ?? '' ) ) ?? '' ) === $cpf_cnpj
				)
			);
		} elseif ( '' !== $nome ) {
			$itens = array_values(
				array_filter(
					$itens,
					static fn( array $c ): bool => 0 === strcasecmp( trim( (string) ( $c['nome'] ?? '' ) ), $nome )
				)
			);
		} else {
			$itens = [];
		}

		return [
			'status' => 200,
			'body'   => [
				'itens'     => $itens,
				'paginacao' => [ 'total' => count( $itens ) ],
			],
		];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function criar_contato( array $body ): array {
		$id = self::seed_contato( $body );

		return [
			'status' => 201,
			'body'   => self::$contatos[ $id ],
		];
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function listar_produtos( array $query ): array {
		$codigo = isset( $query['codigo'] ) ? (string) $query['codigo'] : '';
		$limite = max( 1, (int) ( $query['limit'] ?? 50 ) );
		$offset = max( 0, (int) ( $query['offset'] ?? 0 ) );

		$itens = array_values( self::$produtos );
		if ( '' !== $codigo ) {
			$itens = array_values(
				array_filter(
					$itens,
					static fn( array $p ): bool => 0 === strcasecmp( (string) ( $p['codigo'] ?? '' ), $codigo )
				)
			);
		}

		$total = count( $itens );

		return [
			'status' => 200,
			'body'   => [
				'itens'     => array_slice( $itens, $offset, $limite ),
				'paginacao' => [
					'total'  => $total,
					'limit'  => $limite,
					'offset' => $offset,
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function criar_produto( array $body ): array {
		$id = self::novo_id();

		self::$produtos[ $id ] = array_merge( $body, [ 'id' => $id ] );
		if ( isset( $body['sku'] ) ) {
			self::$produtos[ $id ]['codigo'] = (string) $body['sku'];
		}
		self::$estoque[ $id ] = (int) ( $body['estoque']['inicial'] ?? $body['estoque']['quantidade'] ?? 0 );

		/* Produto tipo V nasce com as variações do próprio POST, como no Tiny. */
		foreach ( (array) ( $body['variacoes'] ?? [] ) as $variacao ) {
			if ( ! is_array( $variacao ) ) {
				continue;
			}
			$var_id                          = self::novo_id();
			self::$variacoes[ $id ][ $var_id ] = array_merge(
				$variacao,
				[
					'id'     => $var_id,
					'codigo' => (string) ( $variacao['sku'] ?? '' ),
				]
			);
			self::$variacao_pai[ $var_id ]   = $id;
			self::$estoque[ $var_id ]        = (int) ( $variacao['estoque']['inicial'] ?? 0 );
		}
		unset( self::$produtos[ $id ]['variacoes'] );

		return [
			'status' => 201,
			'body'   => self::$produtos[ $id ],
		];
	}

	/**
	 * @return array{loja:string, id:int}|null
	 */
	private static function localizar_item( int $id ): ?array {
		if ( isset( self::$produtos[ $id ] ) ) {
			return [
				'loja' => 'produto',
				'id'   => $id,
			];
		}
		if ( isset( self::$variacao_pai[ $id ] ) ) {
			return [
				'loja' => 'variacao',
				'id'   => self::$variacao_pai[ $id ],
			];
		}
		return null;
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function rota_produto( string $metodo, int $id, ?array $body ): array {
		$item = self::localizar_item( $id );

		if ( 'GET' === $metodo ) {
			if ( null === $item ) {
				return [
					'status' => 404,
					'body'   => [ 'message' => 'Produto não encontrado.' ],
				];
			}
			if ( 'variacao' === $item['loja'] ) {
				return [
					'status' => 200,
					'body'   => self::$variacoes[ $item['id'] ][ $id ],
				];
			}
			$produto = self::$produtos[ $id ];
			if ( isset( self::$variacoes[ $id ] ) ) {
				$produto['variacoes'] = array_values( self::$variacoes[ $id ] );
			}
			return [
				'status' => 200,
				'body'   => $produto,
			];
		}

		if ( 'PUT' === $metodo ) {
			if ( null === $item ) {
				return [
					'status' => 404,
					'body'   => [ 'message' => 'Produto não encontrado.' ],
				];
			}
			if ( 'variacao' === $item['loja'] ) {
				self::$variacoes[ $item['id'] ][ $id ] = array_merge(
					self::$variacoes[ $item['id'] ][ $id ],
					$body ?? [],
					[ 'id' => $id ]
				);
				return [
					'status' => 200,
					'body'   => self::$variacoes[ $item['id'] ][ $id ],
				];
			}
			self::$produtos[ $id ] = array_merge( self::$produtos[ $id ], $body ?? [], [ 'id' => $id ] );
			return [
				'status' => 200,
				'body'   => self::$produtos[ $id ],
			];
		}

		if ( 'DELETE' === $metodo ) {
			foreach ( array_keys( self::$variacoes[ $id ] ?? [] ) as $var_id ) {
				unset( self::$variacao_pai[ $var_id ], self::$estoque[ $var_id ] );
			}
			unset( self::$produtos[ $id ], self::$variacoes[ $id ], self::$estoque[ $id ] );
			return [
				'status' => 200,
				'body'   => [],
			];
		}

		return [
			'status' => 405,
			'body'   => [ 'message' => 'Método não permitido.' ],
		];
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function rota_variacoes( string $metodo, int $pai, ?array $body ): array {
		if ( 'GET' === $metodo ) {
			return [
				'status' => 200,
				'body'   => [ 'itens' => array_values( self::$variacoes[ $pai ] ?? [] ) ],
			];
		}

		if ( 'POST' === $metodo ) {
			$id                             = self::novo_id();
			self::$variacoes[ $pai ][ $id ] = array_merge(
				$body ?? [],
				[
					'id'     => $id,
					'codigo' => (string) ( $body['sku'] ?? '' ),
				]
			);
			self::$variacao_pai[ $id ]      = $pai;
			self::$estoque[ $id ]           = (int) ( $body['estoque']['inicial'] ?? 0 );
			return [
				'status' => 201,
				'body'   => self::$variacoes[ $pai ][ $id ],
			];
		}

		return [
			'status' => 405,
			'body'   => [ 'message' => 'Método não permitido.' ],
		];
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function rota_variacao( string $metodo, int $pai, int $id, ?array $body ): array {
		if ( 'PUT' === $metodo ) {
			self::$variacoes[ $pai ][ $id ] = array_merge( self::$variacoes[ $pai ][ $id ] ?? [], $body ?? [], [ 'id' => $id ] );
			return [
				'status' => 200,
				'body'   => self::$variacoes[ $pai ][ $id ],
			];
		}

		if ( 'DELETE' === $metodo ) {
			unset( self::$variacoes[ $pai ][ $id ] );
			return [
				'status' => 200,
				'body'   => [],
			];
		}

		return [
			'status' => 405,
			'body'   => [ 'message' => 'Método não permitido.' ],
		];
	}

	/**
	 * `produtos/{id}/anexos`: o caminho que importa imagem sem tocar no cadastro.
	 * `externo=false` no pedido vira anexo hospedado no Tiny na resposta.
	 *
	 * @param array<int, array<string, mixed>>|array<string, mixed>|null $body
	 * @return array{status:int, body:array<string, mixed>|array<int, mixed>}
	 */
	private static function rota_anexos( string $metodo, int $id, ?array $body ): array {
		if ( ! isset( self::$produtos[ $id ] ) ) {
			return [
				'status' => 404,
				'body'   => [ 'message' => 'Produto não encontrado.' ],
			];
		}

		$atuais = (array) ( self::$produtos[ $id ]['anexos'] ?? [] );

		if ( 'GET' === $metodo ) {
			return [
				'status' => 200,
				'body'   => array_values( $atuais ),
			];
		}

		if ( 'PUT' === $metodo || 'POST' === $metodo ) {
			$novos = [];
			foreach ( (array) $body as $anexo ) {
				if ( ! is_array( $anexo ) || empty( $anexo['url'] ) ) {
					continue;
				}
				$externo = ! empty( $anexo['externo'] );
				$novos[] = [
					'id'      => self::novo_id(),
					'url'     => $externo
						? (string) $anexo['url']
						: 'https://tiny-anexos.s3.amazonaws.com/' . md5( (string) $anexo['url'] ) . '.jpg',
					'externo' => $externo,
				];
			}

			self::$produtos[ $id ]['anexos'] = 'PUT' === $metodo ? $novos : array_merge( $atuais, $novos );

			return [
				'status' => 200,
				'body'   => array_values( self::$produtos[ $id ]['anexos'] ),
			];
		}

		if ( 'DELETE' === $metodo ) {
			$alvo = absint( $body['id'] ?? 0 );
			self::$produtos[ $id ]['anexos'] = array_values(
				array_filter(
					$atuais,
					static fn( array $a ): bool => absint( $a['id'] ?? 0 ) !== $alvo
				)
			);
			return [
				'status' => 204,
				'body'   => [],
			];
		}

		return [
			'status' => 405,
			'body'   => [ 'message' => 'Método não permitido.' ],
		];
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array{status:int, body:array<string, mixed>}
	 */
	private static function rota_estoque( string $metodo, int $id, ?array $body ): array {
		if ( 'GET' === $metodo ) {
			$saldo = self::$estoque[ $id ] ?? 0;
			return [
				'status' => 200,
				'body'   => [
					'saldo'     => $saldo,
					'depositos' => [
						[
							'empresa' => 'vaporhub',
							'saldo'   => $saldo,
						],
					],
				],
			];
		}

		if ( 'POST' === $metodo ) {
			$quantidade = (float) ( $body['quantidade'] ?? 0 );
			$tipo       = (string) ( $body['tipo'] ?? 'B' );

			self::$estoque[ $id ] = 'B' === $tipo
				? (int) $quantidade
				: (int) ( ( self::$estoque[ $id ] ?? 0 ) + $quantidade );

			return [
				'status' => 200,
				'body'   => [ 'saldo' => self::$estoque[ $id ] ],
			];
		}

		return [
			'status' => 405,
			'body'   => [ 'message' => 'Método não permitido.' ],
		];
	}

	/**
	 * Árvore no formato de `GET categorias/todas`: raízes com `filhas`.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function arvore_categorias(): array {
		$por_pai = [];
		foreach ( self::$categorias as $cat ) {
			$por_pai[ (int) ( $cat['idCategoriaPai'] ?? 0 ) ][] = $cat;
		}

		$montar = static function ( int $pai ) use ( &$montar, $por_pai ): array {
			$saida = [];
			foreach ( $por_pai[ $pai ] ?? [] as $cat ) {
				$no = [
					'id'        => (int) $cat['id'],
					'descricao' => (string) $cat['descricao'],
				];
				$filhas = $montar( (int) $cat['id'] );
				if ( $filhas ) {
					$no['filhas'] = $filhas;
				}
				$saida[] = $no;
			}
			return $saida;
		};

		return $montar( 0 );
	}
}

<?php
/**
 * Plugin Name: PA Tiny ERP Mock (E2E)
 * Description: Intercepta as chamadas ao Tiny e responde como o ERP, guardando o que recebeu. Só carrega quando VH_E2E está definido.
 *
 * O objetivo é o mesmo do ERP falso da suíte PHP: o código sob teste é o real,
 * só a rede é substituída. Aqui vale para WordPress e WooCommerce de verdade,
 * dentro do wp-env, para que o Playwright possa conferir o que o “ERP” recebeu.
 *
 * @package VaporHubLoja\Tests\E2E
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'VH_E2E' ) || ! VH_E2E ) {
	return;
}

final class VH_E2E_Tiny_Mock {

	public const OPTION_REQUISICOES = 'vh_e2e_requisicoes';
	public const OPTION_PRODUTOS    = 'vh_e2e_erp_produtos';
	public const OPTION_PEDIDOS     = 'vh_e2e_erp_pedidos';
	public const OPTION_CONTATOS    = 'vh_e2e_erp_contatos';
	public const OPTION_CATEGORIAS  = 'vh_e2e_erp_categorias';
	public const OPTION_SEQUENCIA   = 'vh_e2e_erp_sequencia';

	public const CNPJ  = '12345678000199';
	public const TOKEN = 'vh-e2e';

	public static function init(): void {
		add_filter( 'pre_http_request', [ self::class, 'interceptar' ], 10, 3 );
		add_action( 'rest_api_init', [ self::class, 'rotas' ] );
	}

	/**
	 * @param mixed  $pre
	 * @param array<string, mixed> $args
	 * @return mixed
	 */
	public static function interceptar( $pre, $args, $url ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! in_array( $host, [ 'api.tiny.com.br', 'accounts.tiny.com.br' ], true ) ) {
			return $pre;
		}

		$metodo   = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$caminho  = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		$endpoint = ltrim( str_replace( '/public-api/v3', '', $caminho ), '/' );
		$query    = [];
		parse_str( (string) wp_parse_url( (string) $url, PHP_URL_QUERY ), $query );

		$corpo = null;
		if ( isset( $args['body'] ) && is_string( $args['body'] ) ) {
			$corpo = json_decode( $args['body'], true );
			if ( ! is_array( $corpo ) ) {
				$corpo = [ 'raw' => $args['body'] ];
			}
		} elseif ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$corpo = $args['body'];
		}

		$registro = [
			'metodo'   => $metodo,
			'endpoint' => $endpoint,
			'query'    => $query,
			'body'     => $corpo,
			'quando'   => gmdate( 'c' ),
		];

		$resposta = self::responder( $metodo, $endpoint, $query, is_array( $corpo ) ? $corpo : [] );

		$lista = get_option( self::OPTION_REQUISICOES, [] );
		if ( ! is_array( $lista ) ) {
			$lista = [];
		}
		$registro['status'] = (int) $resposta['status'];
		$lista[]            = $registro;
		update_option( self::OPTION_REQUISICOES, $lista, false );

		return [
			'headers'  => [],
			'body'     => (string) wp_json_encode( $resposta['body'] ),
			'response' => [
				'code'    => (int) $resposta['status'],
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $body
	 * @return array{status:int, body:mixed}
	 */
	private static function responder( string $metodo, string $endpoint, array $query, array $body ): array {
		if ( str_contains( $endpoint, 'openid-connect/token' ) ) {
			return [
				'status' => 200,
				'body'   => [
					'access_token'  => 'token-e2e',
					'refresh_token' => 'refresh-e2e',
					'expires_in'    => 3600,
				],
			];
		}

		if ( 'info' === $endpoint ) {
			return [
				'status' => 200,
				'body'   => [
					'nome'         => 'VAPOR HUB COMERCIO LTDA',
					'razaoSocial'  => 'VAPOR HUB COMERCIO LTDA',
					'nomeFantasia' => 'Loja Piloto',
					'cpfCnpj'      => self::CNPJ,
				],
			];
		}

		if ( 'produtos' === $endpoint && 'POST' === $metodo ) {
			$id       = self::proximo_id();
			$produtos = self::produtos();

			$produtos[ $id ]         = array_merge( $body, [ 'id' => $id ] );
			$produtos[ $id ]['codigo'] = (string) ( $body['sku'] ?? $body['codigo'] ?? '' );
			self::salvar( self::OPTION_PRODUTOS, $produtos );

			return [
				'status' => 201,
				'body'   => $produtos[ $id ],
			];
		}

		if ( 'produtos' === $endpoint ) {
			$codigo   = (string) ( $query['codigo'] ?? '' );
			$produtos = array_values( self::produtos() );
			if ( '' !== $codigo ) {
				$produtos = array_values(
					array_filter(
						$produtos,
						static fn( array $p ): bool => 0 === strcasecmp( (string) ( $p['codigo'] ?? '' ), $codigo )
					)
				);
			}
			return [
				'status' => 200,
				'body'   => [
					'itens'     => $produtos,
					'paginacao' => [ 'total' => count( $produtos ) ],
				],
			];
		}

		if ( preg_match( '#^produtos/(\d+)/anexos$#', $endpoint, $m ) ) {
			$id       = (int) $m[1];
			$produtos = self::produtos();
			if ( ! isset( $produtos[ $id ] ) ) {
				return [ 'status' => 404, 'body' => [ 'message' => 'Produto não encontrado.' ] ];
			}

			if ( 'GET' === $metodo ) {
				return [ 'status' => 200, 'body' => array_values( (array) ( $produtos[ $id ]['anexos'] ?? [] ) ) ];
			}

			$anexos = [];
			foreach ( $body as $anexo ) {
				if ( ! is_array( $anexo ) || empty( $anexo['url'] ) ) {
					continue;
				}
				$anexos[] = [
					'id'      => self::proximo_id(),
					'url'     => empty( $anexo['externo'] )
						? 'https://tiny-anexos.s3.amazonaws.com/' . md5( (string) $anexo['url'] ) . '.jpg'
						: (string) $anexo['url'],
					'externo' => ! empty( $anexo['externo'] ),
				];
			}
			$produtos[ $id ]['anexos'] = $anexos;
			self::salvar( self::OPTION_PRODUTOS, $produtos );

			return [ 'status' => 200, 'body' => $anexos ];
		}

		if ( preg_match( '#^produtos/(\d+)/variacoes(?:/(\d+))?$#', $endpoint, $m ) ) {
			$pai      = (int) $m[1];
			$produtos = self::produtos();
			$vars     = (array) ( $produtos[ $pai ]['variacoes'] ?? [] );

			if ( 'GET' === $metodo ) {
				return [ 'status' => 200, 'body' => [ 'itens' => array_values( $vars ) ] ];
			}

			if ( 'POST' === $metodo ) {
				$id     = self::proximo_id();
				$vars[] = array_merge( $body, [ 'id' => $id, 'codigo' => (string) ( $body['sku'] ?? '' ) ] );
				$produtos[ $pai ]['variacoes'] = $vars;
				self::salvar( self::OPTION_PRODUTOS, $produtos );
				return [ 'status' => 201, 'body' => end( $vars ) ];
			}

			return [ 'status' => 200, 'body' => [] ];
		}

		if ( preg_match( '#^produtos/(\d+)$#', $endpoint, $m ) ) {
			$id       = (int) $m[1];
			$produtos = self::produtos();

			if ( ! isset( $produtos[ $id ] ) ) {
				return [ 'status' => 404, 'body' => [ 'message' => 'Produto não encontrado.' ] ];
			}
			if ( 'PUT' === $metodo ) {
				$produtos[ $id ] = array_merge( $produtos[ $id ], $body, [ 'id' => $id ] );
				self::salvar( self::OPTION_PRODUTOS, $produtos );
			}
			if ( 'DELETE' === $metodo ) {
				unset( $produtos[ $id ] );
				self::salvar( self::OPTION_PRODUTOS, $produtos );
				return [ 'status' => 200, 'body' => [] ];
			}

			return [ 'status' => 200, 'body' => $produtos[ $id ] ?? [] ];
		}

		if ( preg_match( '#^estoque/(\d+)$#', $endpoint, $m ) ) {
			$id       = (int) $m[1];
			$produtos = self::produtos();
			$saldo    = (float) ( $produtos[ $id ]['saldo_e2e'] ?? 0 );

			if ( 'POST' === $metodo ) {
				$saldo = 'B' === (string) ( $body['tipo'] ?? 'B' )
					? (float) ( $body['quantidade'] ?? 0 )
					: $saldo + (float) ( $body['quantidade'] ?? 0 );
				$produtos[ $id ]['saldo_e2e'] = $saldo;
				self::salvar( self::OPTION_PRODUTOS, $produtos );
			}

			return [
				'status' => 200,
				'body'   => [
					'saldo'     => $saldo,
					'depositos' => [ [ 'empresa' => 'vaporhub', 'saldo' => $saldo ] ],
				],
			];
		}

		if ( 'categorias/todas' === $endpoint ) {
			return [ 'status' => 200, 'body' => array_values( self::lista( self::OPTION_CATEGORIAS ) ) ];
		}

		if ( preg_match( '#^categorias/(\d+)$#', $endpoint, $m ) ) {
			$id         = (int) $m[1];
			$categorias = self::lista( self::OPTION_CATEGORIAS );

			if ( ! isset( $categorias[ $id ] ) ) {
				return [ 'status' => 404, 'body' => [ 'message' => 'Categoria não encontrada.' ] ];
			}
			if ( 'PUT' === $metodo ) {
				$categorias[ $id ] = array_merge( $categorias[ $id ], $body, [ 'id' => $id ] );
				self::salvar( self::OPTION_CATEGORIAS, $categorias );
			}

			return [ 'status' => 200, 'body' => $categorias[ $id ] ];
		}

		if ( 'categorias' === $endpoint && 'POST' === $metodo ) {
			$id         = self::proximo_id();
			$categorias = self::lista( self::OPTION_CATEGORIAS );

			$categorias[ $id ] = [
				'id'             => $id,
				'descricao'      => (string) ( $body['descricao'] ?? '' ),
				'idCategoriaPai' => (int) ( $body['idCategoriaPai'] ?? 0 ),
			];
			self::salvar( self::OPTION_CATEGORIAS, $categorias );

			return [ 'status' => 201, 'body' => [ 'id' => $id ] ];
		}

		if ( 'contatos' === $endpoint && 'POST' === $metodo ) {
			$id       = self::proximo_id();
			$contatos = self::lista( self::OPTION_CONTATOS );

			$contatos[ $id ] = array_merge( $body, [ 'id' => $id ] );
			self::salvar( self::OPTION_CONTATOS, $contatos );

			return [ 'status' => 201, 'body' => $contatos[ $id ] ];
		}

		if ( 'contatos' === $endpoint ) {
			$documento = preg_replace( '/\D+/', '', (string) ( $query['cpfCnpj'] ?? '' ) ) ?? '';
			$nome      = trim( (string) ( $query['nome'] ?? $query['pesquisa'] ?? '' ) );
			$achados   = [];
			foreach ( self::lista( self::OPTION_CONTATOS ) as $contato ) {
				$doc = preg_replace( '/\D+/', '', (string) ( $contato['cpfCnpj'] ?? '' ) ) ?? '';
				if ( '' !== $documento && $doc === $documento ) {
					$achados[] = $contato;
					continue;
				}
				if ( '' === $documento && '' !== $nome && 0 === strcasecmp( (string) ( $contato['nome'] ?? '' ), $nome ) ) {
					$achados[] = $contato;
				}
			}
			return [
				'status' => 200,
				'body'   => [ 'itens' => $achados, 'paginacao' => [ 'total' => count( $achados ) ] ],
			];
		}

		if ( 'pedidos' === $endpoint && 'POST' === $metodo ) {
			$id      = self::proximo_id();
			$pedidos = self::lista( self::OPTION_PEDIDOS );

			$pedidos[ $id ] = array_merge( $body, [ 'id' => $id ] );
			self::salvar( self::OPTION_PEDIDOS, $pedidos );

			return [ 'status' => 201, 'body' => [ 'id' => $id, 'numeroPedido' => $id ] ];
		}

		return [ 'status' => 404, 'body' => [ 'message' => 'Rota não mapeada no mock: ' . $metodo . ' ' . $endpoint ] ];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function produtos(): array {
		return self::lista( self::OPTION_PRODUTOS );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function lista( string $opcao ): array {
		$dados = get_option( $opcao, [] );
		return is_array( $dados ) ? $dados : [];
	}

	/**
	 * @param array<int, array<string, mixed>> $dados
	 */
	private static function salvar( string $opcao, array $dados ): void {
		update_option( $opcao, $dados, false );
	}

	private static function proximo_id(): int {
		$id = (int) get_option( self::OPTION_SEQUENCIA, 1000 ) + 1;
		update_option( self::OPTION_SEQUENCIA, $id, false );
		return $id;
	}

	public static function rotas(): void {
		$permissao = static function ( WP_REST_Request $request ): bool {
			return hash_equals( self::TOKEN, (string) $request->get_header( 'X-VH-E2E-Token' ) );
		};

		register_rest_route(
			'vh-e2e/v1',
			'/erp',
			[
				'methods'             => 'GET',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					return new WP_REST_Response(
						[
							'requisicoes' => self::lista( self::OPTION_REQUISICOES ),
							'produtos'    => array_values( self::produtos() ),
							'pedidos'     => array_values( self::lista( self::OPTION_PEDIDOS ) ),
							'contatos'    => array_values( self::lista( self::OPTION_CONTATOS ) ),
							'categorias'  => array_values( self::lista( self::OPTION_CATEGORIAS ) ),
						]
					);
				},
			]
		);

		/*
		 * Zerar só o ERP deixaria a loja apontando para cadastros que não existem
		 * mais. O reset limpa os dois lados: ERP, vínculos locais e fila.
		 */
		register_rest_route(
			'vh-e2e/v1',
			'/reset',
			[
				'methods'             => 'POST',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					global $wpdb;

					foreach (
						[
							self::OPTION_REQUISICOES,
							self::OPTION_PRODUTOS,
							self::OPTION_PEDIDOS,
							self::OPTION_CONTATOS,
							self::OPTION_CATEGORIAS,
						] as $opcao
					) {
						update_option( $opcao, [], false );
					}

					$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_pa\_tiny\_%'" );
					$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\_pa\_tiny\_cat\_id'" );
					$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}vh_tiny_fila" );

					if ( class_exists( 'VH_Tiny' ) ) {
						VH_Tiny::salvar_config( [ 'categoria_raiz_id' => 0 ] );
					}

					return new WP_REST_Response( [ 'ok' => true ] );
				},
			]
		);

		/* Entre testes basta esquecer o que o ERP ouviu, sem apagar cadastro. */
		register_rest_route(
			'vh-e2e/v1',
			'/log',
			[
				'methods'             => 'DELETE',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					update_option( self::OPTION_REQUISICOES, [], false );
					return new WP_REST_Response( [ 'ok' => true ] );
				},
			]
		);

		register_rest_route(
			'vh-e2e/v1',
			'/fila',
			[
				'methods'             => 'POST',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					if ( class_exists( 'VH_Tiny_Queue' ) ) {
						VH_Tiny_Queue::processar_lote();
					}
					return new WP_REST_Response(
						[
							'fila' => class_exists( 'VH_Tiny_Queue' ) ? VH_Tiny_Queue::contagem_por_status() : [],
						]
					);
				},
			]
		);

		/* Espelho do lado da loja: o Playwright confere o que ficou gravado. */
		register_rest_route(
			'vh-e2e/v1',
			'/produto',
			[
				'methods'             => 'GET',
				'permission_callback' => $permissao,
				'callback'            => static function ( WP_REST_Request $request ) {
					$sku = (string) $request->get_param( 'sku' );
					$id  = (int) wc_get_product_id_by_sku( $sku );
					if ( $id < 1 ) {
						return new WP_REST_Response( [ 'encontrado' => false ], 404 );
					}

					$produto = wc_get_product( $id );

					return new WP_REST_Response(
						[
							'encontrado'   => true,
							'id'           => $id,
							'permalink'    => get_permalink( $id ),
							'tipo'         => $produto ? $produto->get_type() : '',
							'preco'        => $produto ? (float) $produto->get_regular_price() : 0,
							'estoque'      => $produto ? $produto->get_stock_quantity() : null,
							'peso'         => $produto ? (string) $produto->get_weight() : '',
							'largura'      => $produto ? (string) $produto->get_width() : '',
							'altura'       => $produto ? (string) $produto->get_height() : '',
							'comprimento'  => $produto ? (string) $produto->get_length() : '',
							'tiny_id'      => (int) get_post_meta( $id, '_vh_tiny_id', true ),
							'tiny_hash'    => (string) get_post_meta( $id, '_vh_tiny_sync_hash', true ),
							'ncm'          => (string) get_post_meta( $id, '_vh_ncm', true ),
							'gtin'         => (string) get_post_meta( $id, '_vh_gtin', true ),
							'unidade'      => (string) get_post_meta( $id, '_vh_unidade', true ),
							'volumes'      => (string) get_post_meta( $id, '_vh_volumes', true ),
						]
					);
				},
			]
		);

		/* O token do webhook nasce cifrado; o teste precisa dele em texto. */
		register_rest_route(
			'vh-e2e/v1',
			'/token-webhook',
			[
				'methods'             => 'GET',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					return new WP_REST_Response(
						[
							'token' => class_exists( 'VH_Tiny' ) ? (string) VH_Tiny::webhook_token() : '',
						]
					);
				},
			]
		);

		register_rest_route(
			'vh-e2e/v1',
			'/erros-php',
			[
				'methods'             => 'GET',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					$arquivo = WP_CONTENT_DIR . '/debug.log';
					$linhas  = [];
					if ( file_exists( $arquivo ) ) {
						$conteudo = (string) file_get_contents( $arquivo );
						foreach ( explode( "\n", $conteudo ) as $linha ) {
							if ( preg_match( '/(Fatal error|Parse error|Uncaught|PHP Warning|PHP Notice|Deprecated)/', $linha ) ) {
								$linhas[] = $linha;
							}
						}
					}
					return new WP_REST_Response( [ 'linhas' => $linhas ] );
				},
			]
		);

		register_rest_route(
			'vh-e2e/v1',
			'/erros-php',
			[
				'methods'             => 'DELETE',
				'permission_callback' => $permissao,
				'callback'            => static function (): WP_REST_Response {
					$arquivo = WP_CONTENT_DIR . '/debug.log';
					if ( file_exists( $arquivo ) ) {
						file_put_contents( $arquivo, '' );
					}
					return new WP_REST_Response( [ 'ok' => true ] );
				},
			]
		);
	}
}

VH_E2E_Tiny_Mock::init();

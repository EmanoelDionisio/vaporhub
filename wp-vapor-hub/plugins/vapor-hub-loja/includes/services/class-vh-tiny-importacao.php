<?php
/**
 * Importação inicial Tiny → loja: principais ativos com estoque, em lote.
 *
 * Não cria um cliente HTTP novo. Não reescreve SKU. Não devolve texto ao hub.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_Importacao {

	public const META_IMPORTADO = '_vh_tiny_importado';
	public const META_CRIADA    = '_vh_tiny_criada';
	public const OPTION_OFFSET  = 'vh_tiny_import_offset';
	public const OPTION_PREVIA  = 'vh_tiny_import_previa';
	public const OPTION_CORRIDA = 'vh_tiny_import_corrida';
	private const LOCK           = 'vh_tiny_import_lock';
	private const ARVORE         = 'vh_tiny_arvore_import';

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function raizes() {
		$mapa = self::mapa_categorias();
		if ( is_wp_error( $mapa ) ) {
			return $mapa;
		}
		$raizes = [];
		foreach ( $mapa as $id => $item ) {
			if ( ! str_contains( (string) $item['caminho'], '>>' ) ) {
				$raizes[] = [
					'id'   => (int) $id,
					'nome' => (string) $item['nome'],
				];
			}
		}
		return $raizes;
	}

	/**
	 * Conta o recorte sem gravar produto. Retomável pelo próprio cursor.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function previa( bool $reiniciar = false ) {
		if ( $reiniciar ) {
			update_option( self::OPTION_PREVIA, [ 'offset' => 0, 'com_estoque' => 0, 'sem_estoque' => 0, 'fora' => 0, 'concluida' => false ], false );
		}
		$estado = get_option( self::OPTION_PREVIA, [] );
		$estado = is_array( $estado ) ? $estado : [];
		$offset = (int) ( $estado['offset'] ?? 0 );

		$trava = self::com_trava(
			static function () use ( $offset, $estado ) {
				return self::percorrer( $offset, 0, true );
			}
		);
		if ( is_wp_error( $trava ) ) {
			return $trava;
		}

		$estado['offset']      = (int) $trava['offset'];
		$estado['com_estoque'] = (int) ( $estado['com_estoque'] ?? 0 ) + (int) $trava['com_estoque'];
		$estado['sem_estoque'] = (int) ( $estado['sem_estoque'] ?? 0 ) + (int) $trava['sem_estoque'];
		$estado['fora']        = (int) ( $estado['fora'] ?? 0 ) + (int) $trava['fora'];
		$estado['concluida']   = ! empty( $trava['concluida'] );
		$estado['total']       = (int) $trava['total'];
		update_option( self::OPTION_PREVIA, $estado, false );
		return $estado;
	}

	/**
	 * Grava um lote curto e avança o cursor.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function lote( int $gravar = 5 ) {
		$gravar = max( 1, min( 10, $gravar ) );
		$offset = (int) get_option( self::OPTION_OFFSET, 0 );
		if ( 0 === $offset ) {
			self::abrir_corrida();
		}
		$passo  = self::com_trava(
			static function () use ( $offset, $gravar ) {
				return self::percorrer( $offset, $gravar, false );
			}
		);
		if ( is_wp_error( $passo ) ) {
			return $passo;
		}
		update_option( self::OPTION_OFFSET, (int) $passo['offset'], false );
		if ( ! empty( $passo['concluida'] ) ) {
			update_option( self::OPTION_OFFSET, 0, false );
		}
		$passo['corrida'] = self::somar_corrida( $passo );
		return $passo;
	}

	/**
	 * Progresso acumulado da importação em curso. A tela só lê.
	 *
	 * @return array<string, mixed>
	 */
	public static function corrida(): array {
		$dados = get_option( self::OPTION_CORRIDA, [] );
		$dados = is_array( $dados ) ? $dados : [];
		$erros = [];
		foreach ( (array) ( $dados['erros'] ?? [] ) as $erro ) {
			if ( is_array( $erro ) ) {
				$erros[] = [
					'tiny_id'  => (int) ( $erro['tiny_id'] ?? 0 ),
					'codigo'   => (string) ( $erro['codigo'] ?? '' ),
					'mensagem' => (string) ( $erro['mensagem'] ?? '' ),
				];
			}
		}

		return [
			'status'      => (string) ( $dados['status'] ?? 'ociosa' ),
			'offset'      => (int) ( $dados['offset'] ?? 0 ),
			'total'       => (int) ( $dados['total'] ?? 0 ),
			'percentual'  => (float) ( $dados['percentual'] ?? 0 ),
			'gravados'    => (int) ( $dados['gravados'] ?? 0 ),
			'sem_estoque' => (int) ( $dados['sem_estoque'] ?? 0 ),
			'fora'        => (int) ( $dados['fora'] ?? 0 ),
			'pulados'     => (int) ( $dados['pulados'] ?? 0 ),
			'concluida'   => ! empty( $dados['concluida'] ),
			'erros'       => $erros,
		];
	}

	/**
	 * Cria um produto a partir do canônico já lido. Idempotente pelo id do Tiny.
	 *
	 * @param array<string, mixed> $canonico
	 * @return true|WP_Error
	 */
	public static function gravar( array $canonico, int $tiny_id ) {
		if ( $tiny_id <= 0 ) {
			return new WP_Error( 'vh_tiny_sem_id', __( 'ID Tiny não informado.', 'vapor-hub-loja' ) );
		}
		if ( self::ja_importado( $tiny_id ) ) {
			return true;
		}
		if ( self::quantidade( $canonico ) <= 0 ) {
			return new WP_Error( 'vh_tiny_sem_estoque', __( 'Produto sem estoque: fora do primeiro recorte.', 'vapor-hub-loja' ) );
		}
		$situacao = strtoupper( (string) ( $canonico['situacao'] ?? 'A' ) );
		if ( '' !== $situacao && 'A' !== $situacao ) {
			return new WP_Error( 'vh_tiny_situacao_inativa', __( 'Produto inativo no Tiny.', 'vapor-hub-loja' ) );
		}
		if ( ! self::marca_permitida( $canonico ) ) {
			return new WP_Error( 'vh_tiny_marca_fora', __( 'Marca fora do filtro desta loja.', 'vapor-hub-loja' ) );
		}

		$mapa = self::mapa_categorias();
		if ( is_wp_error( $mapa ) ) {
			$mapa = [];
		}
		$cat_tiny = self::categoria_tiny_id( $canonico );
		if ( $cat_tiny > 0 && ! self::categoria_permitida( $cat_tiny, $mapa ) ) {
			return new WP_Error( 'vh_tiny_categoria_fora', __( 'Categoria fora do filtro desta loja.', 'vapor-hub-loja' ) );
		}

		return VH_Tiny_Sync_Service::sem_push(
			static function () use ( $canonico, $tiny_id, $mapa, $cat_tiny ) {
				return self::criar_produto( $canonico, $tiny_id, $mapa, $cat_tiny );
			}
		);
	}

	/**
	 * Apaga só o que esta importação criou. OAuth e travas ficam.
	 *
	 * @return array<string, int>|WP_Error
	 */
	public static function zerar() {
		return self::com_trava(
			static function () {
				$produtos = 0;
				$ids      = get_posts(
					[
						'post_type'      => [ 'product', 'product_variation' ],
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => self::META_IMPORTADO,
						'meta_value'     => '1',
					]
				);
				foreach ( (array) $ids as $id ) {
					$produto = wc_get_product( (int) $id );
					if ( $produto instanceof WC_Product && ! $produto->is_type( 'variation' ) ) {
						$produto->delete( true );
						++$produtos;
					}
				}
				foreach ( (array) $ids as $id ) {
					$resto = wc_get_product( (int) $id );
					if ( $resto instanceof WC_Product ) {
						$resto->delete( true );
					}
				}

				$categorias = self::apagar_termos_criados( 'product_cat' );
				$atributos  = 0;
				if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
					foreach ( wc_get_attribute_taxonomies() as $tax ) {
						$nome = 'pa_' . $tax->attribute_name;
						$atributos += self::apagar_termos_criados( $nome );
					}
				}

				global $wpdb;
				$fila = VH_Tiny_Queue::nome_tabela();
				$wpdb->query( "DELETE FROM {$fila}" );

				update_option( self::OPTION_OFFSET, 0, false );
				delete_option( self::OPTION_PREVIA );
				delete_option( self::OPTION_CORRIDA );
				delete_transient( self::ARVORE );

				return [
					'produtos'   => $produtos,
					'categorias' => $categorias,
					'atributos'  => $atributos,
				];
			}
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function percorrer( int $offset, int $gravar, bool $so_contar ) {
		$driver = VH_Tiny::driver();
		if ( ! $driver->conectado() ) {
			return new WP_Error( 'vh_tiny_desconectado', __( 'Tiny ERP não conectado.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$com_estoque = 0;
		$sem_estoque = 0;
		$fora        = 0;
		$gravados    = 0;
		$pulados     = 0;
		$vistos      = 0;
		$total       = 0;
		$concluida   = false;
		$ultimo_erro = null;
		$erros       = [];
		$detalhes    = 0;
		$teto_lista    = $so_contar ? 40 : 25;
		$teto_detalhes = $so_contar ? 8 : 1;

		while ( $vistos < $teto_lista && ( $so_contar || $gravados < $gravar ) ) {
			$resp = VH_Tiny_Client::requisicao(
				'GET',
				'produtos',
				[ 'query' => [ 'limit' => 20, 'offset' => $offset, 'situacao' => 'A' ] ]
			);
			if ( is_wp_error( $resp ) ) {
				if ( self::e_limite( $resp ) ) {
					return self::passo_pausa( $offset, $total, $resp, compact( 'com_estoque', 'sem_estoque', 'fora', 'gravados', 'pulados' ) );
				}
				return $resp;
			}
			$itens = $resp['itens'] ?? [];
			if ( ! is_array( $itens ) || ! $itens ) {
				$concluida = true;
				break;
			}
			$total = (int) ( $resp['paginacao']['total'] ?? $total );

			$consumidos = 0;
			$parar      = false;
			foreach ( $itens as $item ) {
				++$consumidos;
				++$vistos;
				if ( ! is_array( $item ) || ! self::e_principal( $item ) ) {
					if ( $vistos >= $teto_lista ) {
						$parar = true;
						break;
					}
					continue;
				}
				$id = (int) ( $item['id'] ?? 0 );
				if ( $id <= 0 || self::ja_importado( $id ) ) {
					if ( self::ja_importado( $id ) ) {
						++$pulados;
					}
					if ( $vistos >= $teto_lista ) {
						$parar = true;
						break;
					}
					continue;
				}
				if ( $detalhes >= $teto_detalhes ) {
					--$consumidos;
					--$vistos;
					$parar = true;
					break;
				}
				++$detalhes;
				if ( ! defined( 'VH_TEST' ) ) {
					usleep( 200000 );
				}
				$canonico = $driver->obter_produto( $id );
				if ( is_wp_error( $canonico ) && self::e_limite( $canonico ) ) {
					--$consumidos;
					--$vistos;
					return self::passo_pausa(
						$offset + $consumidos,
						$total,
						$canonico,
						compact( 'com_estoque', 'sem_estoque', 'fora', 'gravados', 'pulados' )
					);
				}
				if ( is_wp_error( $canonico ) || ! is_array( $canonico ) ) {
					++$fora;
					if ( is_wp_error( $canonico ) ) {
						$ultimo_erro = self::registrar_erro_passo( $erros, $id, $canonico );
					}
					if ( $vistos >= $teto_lista ) {
						$parar = true;
						break;
					}
					continue;
				}
				if ( self::quantidade( $canonico ) <= 0 ) {
					++$sem_estoque;
					if ( $vistos >= $teto_lista ) {
						$parar = true;
						break;
					}
					continue;
				}
				++$com_estoque;
				if ( $so_contar ) {
					continue;
				}
				$resultado = self::gravar( $canonico, $id );
				if ( is_wp_error( $resultado ) ) {
					++$fora;
					if ( ! self::e_recorte( $resultado ) ) {
						$ultimo_erro = self::registrar_erro_passo( $erros, $id, $resultado );
					}
					if ( $vistos >= $teto_lista ) {
						$parar = true;
						break;
					}
					continue;
				}
				++$gravados;
				if ( ( ! $so_contar && $gravados >= $gravar ) || $vistos >= $teto_lista ) {
					$parar = true;
					break;
				}
			}

			$offset        += $consumidos;
			$pagina_inteira = $consumidos >= count( $itens );
			if ( $pagina_inteira && ( count( $itens ) < 20 || ( $total > 0 && $offset >= $total ) ) ) {
				$concluida = true;
				break;
			}
			if ( $parar || ( ! $so_contar && $gravados >= $gravar ) ) {
				break;
			}
		}

		return [
			'offset'      => $offset,
			'total'       => $total,
			'concluida'   => $concluida,
			'com_estoque' => $com_estoque,
			'sem_estoque' => $sem_estoque,
			'fora'        => $fora,
			'gravados'    => $gravados,
			'pulados'     => $pulados,
			'ultimo_erro' => $ultimo_erro,
			'erros'       => $erros,
		];
	}

	/**
	 * @param array{com_estoque?:int,sem_estoque?:int,fora?:int,gravados?:int,pulados?:int} $parcial
	 * @return array<string, mixed>
	 */
	private static function passo_pausa( int $offset, int $total, WP_Error $erro, array $parcial = [] ): array {
		return [
			'offset'      => $offset,
			'total'       => $total,
			'concluida'   => false,
			'com_estoque' => (int) ( $parcial['com_estoque'] ?? 0 ),
			'sem_estoque' => (int) ( $parcial['sem_estoque'] ?? 0 ),
			'fora'        => (int) ( $parcial['fora'] ?? 0 ),
			'gravados'    => (int) ( $parcial['gravados'] ?? 0 ),
			'pulados'     => (int) ( $parcial['pulados'] ?? 0 ),
			'ultimo_erro' => null,
			'erros'       => [],
			'aguardar'    => self::espera_de( $erro ),
		];
	}

	private static function e_limite( WP_Error $erro ): bool {
		if ( 'vh_tiny_limite' === $erro->get_error_code() ) {
			return true;
		}
		$dados = $erro->get_error_data();
		return is_array( $dados ) && 429 === (int) ( $dados['status'] ?? 0 );
	}

	private static function espera_de( WP_Error $erro ): int {
		$dados = $erro->get_error_data();
		$seg   = is_array( $dados ) ? (int) ( $dados['retry_after'] ?? 0 ) : 0;
		if ( $seg <= 0 ) {
			$seg = 20;
		}
		return min( 60, max( 5, $seg ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function abrir_corrida(): array {
		$corrida = [
			'status'      => 'andamento',
			'offset'      => 0,
			'total'       => 0,
			'percentual'  => 0,
			'gravados'    => 0,
			'sem_estoque' => 0,
			'fora'        => 0,
			'pulados'     => 0,
			'concluida'   => false,
			'erros'       => [],
		];
		update_option( self::OPTION_CORRIDA, $corrida, false );
		return $corrida;
	}

	/**
	 * @param array<string, mixed> $passo
	 * @return array<string, mixed>
	 */
	private static function somar_corrida( array $passo ): array {
		$corrida = self::corrida();
		if ( 'andamento' !== $corrida['status'] ) {
			$corrida = self::abrir_corrida();
		}
		$corrida['erros'] = array_values(
			array_filter(
				$corrida['erros'],
				static fn( array $erro ): bool => 'vh_tiny_limite' !== ( $erro['codigo'] ?? '' ) && ! str_contains( (string) ( $erro['mensagem'] ?? '' ), 'HTTP 429' )
			)
		);
		$corrida['gravados']    += (int) ( $passo['gravados'] ?? 0 );
		$corrida['sem_estoque'] += (int) ( $passo['sem_estoque'] ?? 0 );
		$corrida['fora']        += (int) ( $passo['fora'] ?? 0 );
		$corrida['pulados']     += (int) ( $passo['pulados'] ?? 0 );
		$corrida['offset']       = (int) ( $passo['offset'] ?? 0 );
		$corrida['total']        = (int) ( $passo['total'] ?? $corrida['total'] );
		$corrida['concluida']    = ! empty( $passo['concluida'] );
		$corrida['status']       = $corrida['concluida'] ? 'concluida' : 'andamento';
		$corrida['percentual']   = $corrida['concluida']
			? 100
			: ( $corrida['total'] > 0 ? round( ( $corrida['offset'] / $corrida['total'] ) * 100, 1 ) : 0 );

		foreach ( (array) ( $passo['erros'] ?? [] ) as $erro ) {
			if ( ! is_array( $erro ) ) {
				continue;
			}
			$corrida['erros'][] = [
				'tiny_id'  => (int) ( $erro['tiny_id'] ?? 0 ),
				'codigo'   => (string) ( $erro['codigo'] ?? '' ),
				'mensagem' => (string) ( $erro['mensagem'] ?? '' ),
			];
		}
		if ( count( $corrida['erros'] ) > 15 ) {
			$corrida['erros'] = array_slice( $corrida['erros'], -15 );
		}

		update_option( self::OPTION_CORRIDA, $corrida, false );
		return self::corrida();
	}

	/**
	 * @param array<int, array{codigo:string,mensagem:string,tiny_id:int}> $erros
	 * @return array{codigo:string,mensagem:string,tiny_id:int}
	 */
	private static function registrar_erro_passo( array &$erros, int $tiny_id, WP_Error $erro ): array {
		$falha = self::anotar_falha( $tiny_id, $erro );
		if ( count( $erros ) < 15 ) {
			$erros[] = $falha;
		}
		return $falha;
	}

	/**
	 * @param array<string, mixed> $canonico
	 * @param array<int, array{nome:string, caminho:string}> $mapa
	 * @return true|WP_Error
	 */
	private static function criar_produto( array $canonico, int $tiny_id, array $mapa, int $cat_tiny ) {
		$sku  = trim( (string) ( $canonico['sku'] ?? '' ) );
		$nome = trim( (string) ( $canonico['nome'] ?? '' ) );
		if ( '' === $sku || '' === $nome ) {
			return new WP_Error( 'vh_tiny_sem_sku', __( 'Produto Tiny sem SKU ou nome.', 'vapor-hub-loja' ) );
		}
		if ( wc_get_product_id_by_sku( $sku ) > 0 ) {
			return new WP_Error( 'vh_tiny_sku_duplicado', __( 'Já existe produto na loja com este SKU.', 'vapor-hub-loja' ) );
		}

		$term_id = $cat_tiny > 0 ? self::garantir_categoria( $cat_tiny, $mapa ) : 0;
		$eh_variavel = ! empty( $canonico['variacoes'] ) || 'variavel' === ( $canonico['tipo'] ?? '' );
		$produto = $eh_variavel ? new WC_Product_Variable() : new WC_Product_Simple();
		$produto->set_name( $nome );
		$produto->set_sku( $sku );
		$produto->set_status( 'publish' );
		$descricao = trim( (string) ( $canonico['descricao'] ?? '' ) );
		if ( '' !== $descricao && $descricao !== $nome ) {
			$produto->set_description( $descricao );
		}
		$curta = trim( (string) ( $canonico['descricao_curta'] ?? '' ) );
		if ( '' !== $curta && $curta !== $nome ) {
			$produto->set_short_description( $curta );
		}
		if ( $term_id > 0 ) {
			$produto->set_category_ids( [ $term_id ] );
		}

		$variacoes = [];
		if ( $eh_variavel ) {
			$variacoes = self::preparar_grade( $produto, (array) $canonico['variacoes'] );
		} else {
			self::preencher_preco( $produto, $canonico );
			$produto->set_manage_stock( true );
			$produto->set_stock_quantity( max( 0, (int) ( $canonico['estoque'] ?? 0 ) ) );
		}

		$id = $produto->save();
		if ( ! $id ) {
			return new WP_Error( 'vh_produto_erro', __( 'Não foi possível salvar o produto.', 'vapor-hub-loja' ) );
		}
		update_post_meta( $id, VH_Tiny_Map::META_TINY_ID, $tiny_id );
		update_post_meta( $id, self::META_IMPORTADO, '1' );

		foreach ( $variacoes as $var ) {
			self::criar_variacao( $id, $var );
		}
		if ( $eh_variavel ) {
			WC_Product_Variable::sync( $id );
		}
		self::marcar_promocao_na_busca( $id );
		self::anexar_imagens( $id, (array) ( $canonico['imagens'] ?? [] ) );

		return true;
	}

	/**
	 * @param array<int, array<string, mixed>> $variacoes
	 * @return array<int, array<string, mixed>>
	 */
	private static function preparar_grade( WC_Product_Variable $produto, array $variacoes ): array {
		$eixos = [];
		$prontas = [];
		foreach ( $variacoes as $var ) {
			if ( ! is_array( $var ) ) {
				continue;
			}
			$sku = trim( (string) ( $var['sku'] ?? '' ) );
			if ( '' === $sku ) {
				continue;
			}
			$attrs = [];
			foreach ( (array) ( $var['grade'] ?? [] ) as $eixo ) {
				if ( ! is_array( $eixo ) ) {
					continue;
				}
				$chave = trim( (string) ( $eixo['chave'] ?? '' ) );
				$valor = trim( (string) ( $eixo['valor'] ?? '' ) );
				if ( '' === $chave || '' === $valor ) {
					continue;
				}
				$tax = self::taxonomy_grade( $chave );
				$slug = self::termo_grade( $tax, $valor );
				$attrs[ $tax ] = $slug;
				$eixos[ $tax ][ $slug ] = true;
			}
			$var['attrs'] = $attrs;
			$prontas[] = $var;
		}

		$atributos = [];
		foreach ( $eixos as $tax => $slugs ) {
			$termo_ids = [];
			foreach ( array_keys( $slugs ) as $slug ) {
				$termo = get_term_by( 'slug', $slug, $tax );
				if ( $termo instanceof WP_Term ) {
					$termo_ids[] = (int) $termo->term_id;
				}
			}
		$attr = new WC_Product_Attribute();
		if ( function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
			$attr->set_id( (int) wc_attribute_taxonomy_id_by_name( substr( $tax, 3 ) ) );
		}
		$attr->set_name( $tax );
			$attr->set_options( $termo_ids );
			$attr->set_visible( true );
			$attr->set_variation( true );
			$atributos[] = $attr;
		}
		$produto->set_attributes( $atributos );
		return $prontas;
	}

	/**
	 * @param array<string, mixed> $var
	 */
	private static function criar_variacao( int $pai_id, array $var ): void {
		$sku = trim( (string) ( $var['sku'] ?? '' ) );
		if ( '' === $sku || wc_get_product_id_by_sku( $sku ) > 0 ) {
			return;
		}
		$variacao = new WC_Product_Variation();
		$variacao->set_parent_id( $pai_id );
		$variacao->set_sku( $sku );
		$variacao->set_attributes( (array) ( $var['attrs'] ?? [] ) );
		$variacao->set_status( 'publish' );
		self::preencher_preco( $variacao, $var );
		$variacao->set_manage_stock( true );
		$variacao->set_stock_quantity( max( 0, (int) ( $var['estoque'] ?? 0 ) ) );
		$vid = $variacao->save();
		if ( $vid ) {
			$tiny = (int) ( $var['tiny_id'] ?? 0 );
			if ( $tiny > 0 ) {
				update_post_meta( $vid, VH_Tiny_Map::META_TINY_ID, $tiny );
			}
			update_post_meta( $vid, self::META_IMPORTADO, '1' );
		}
	}

	/**
	 * @param array<int, array{nome:string, caminho:string}> $mapa
	 */
	private static function garantir_categoria( int $tiny_id, array $mapa ): int {
		$existente = self::termo_por_tiny_cat( $tiny_id );
		if ( $existente > 0 ) {
			return $existente;
		}
		$caminho = (string) ( $mapa[ $tiny_id ]['caminho'] ?? '' );
		$partes  = '' !== $caminho ? array_map( 'trim', explode( '>>', $caminho ) ) : [ (string) ( $mapa[ $tiny_id ]['nome'] ?? ( 'Tiny ' . $tiny_id ) ) ];
		$pai     = 0;
		$ultimo  = 0;
		$acumulo = '';
		foreach ( $partes as $nome ) {
			if ( '' === $nome ) {
				continue;
			}
			$acumulo = '' === $acumulo ? $nome : $acumulo . ' >> ' . $nome;
			$id_tiny_parte = $tiny_id;
			foreach ( $mapa as $id => $item ) {
				if ( (string) $item['caminho'] === $acumulo ) {
					$id_tiny_parte = (int) $id;
					break;
				}
			}
			$termo = self::termo_por_tiny_cat( $id_tiny_parte );
			if ( $termo <= 0 ) {
				$criado = wp_insert_term( $nome, 'product_cat', [ 'parent' => $pai ] );
				if ( is_wp_error( $criado ) && isset( $criado->error_data['term_exists'] ) ) {
					$termo = (int) $criado->error_data['term_exists'];
				} elseif ( ! is_wp_error( $criado ) ) {
					$termo = (int) $criado['term_id'];
					update_term_meta( $termo, self::META_CRIADA, '1' );
				}
				if ( $termo > 0 ) {
					update_term_meta( $termo, VH_Tiny_Map::META_TINY_CAT_ID, $id_tiny_parte );
				}
			}
			$pai    = $termo;
			$ultimo = $termo;
		}
		return $ultimo;
	}

	private static function termo_por_tiny_cat( int $tiny_id ): int {
		$termos = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => [
					[
						'key'   => VH_Tiny_Map::META_TINY_CAT_ID,
						'value' => $tiny_id,
					],
				],
			]
		);
		if ( is_array( $termos ) && isset( $termos[0] ) && $termos[0] instanceof WP_Term ) {
			return (int) $termos[0]->term_id;
		}
		return 0;
	}

	private static function taxonomy_grade( string $rotulo ): string {
		$slug = substr( sanitize_title( $rotulo ), 0, 28 );
		$tax  = 'pa_' . $slug;
		if ( taxonomy_exists( $tax ) ) {
			return $tax;
		}
		if ( function_exists( 'wc_create_attribute' ) && function_exists( 'wc_attribute_taxonomy_id_by_name' ) && ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
			wc_create_attribute(
				[
					'name'         => $rotulo,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				]
			);
			delete_transient( 'wc_attribute_taxonomies' );
		}
		if ( function_exists( 'register_taxonomy' ) ) {
			register_taxonomy(
				$tax,
				[ 'product' ],
				[
					'hierarchical' => false,
					'label'        => $rotulo,
					'public'       => false,
					'show_ui'      => false,
				]
			);
		}
		return $tax;
	}

	private static function termo_grade( string $tax, string $valor ): string {
		$slug = sanitize_title( $valor );
		$termo = get_term_by( 'slug', $slug, $tax );
		if ( $termo instanceof WP_Term ) {
			return $termo->slug;
		}
		$criado = wp_insert_term( $valor, $tax, [ 'slug' => $slug ] );
		if ( is_wp_error( $criado ) ) {
			return $slug;
		}
		update_term_meta( (int) $criado['term_id'], self::META_CRIADA, '1' );
		$termo = get_term( (int) $criado['term_id'], $tax );
		return $termo instanceof WP_Term ? $termo->slug : $slug;
	}

	private static function apagar_termos_criados( string $taxonomy ): int {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$termos = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_key'   => self::META_CRIADA,
				'meta_value' => '1',
			]
		);
		if ( ! is_array( $termos ) ) {
			return 0;
		}
		usort(
			$termos,
			static function ( $a, $b ) {
				return (int) $b->parent <=> (int) $a->parent;
			}
		);
		$n = 0;
		foreach ( $termos as $termo ) {
			if ( $termo instanceof WP_Term && wp_delete_term( $termo->term_id, $taxonomy ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function e_principal( array $item ): bool {
		$tv = strtoupper( (string) ( $item['tipoVariacao'] ?? '' ) );
		if ( in_array( $tv, [ 'P', 'N' ], true ) ) {
			return true;
		}
		if ( 'V' === $tv ) {
			return false;
		}
		return 'V' === strtoupper( (string) ( $item['tipo'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $canonico
	 */
	private static function quantidade( array $canonico ): int {
		$qtd = max( 0, (int) ( $canonico['estoque'] ?? 0 ) );
		foreach ( (array) ( $canonico['variacoes'] ?? [] ) as $var ) {
			if ( is_array( $var ) ) {
				$qtd += max( 0, (int) ( $var['estoque'] ?? 0 ) );
			}
		}
		return $qtd;
	}

	/**
	 * @param array<string, mixed> $canonico
	 */
	private static function categoria_tiny_id( array $canonico ): int {
		foreach ( (array) ( $canonico['categorias'] ?? [] ) as $cat ) {
			if ( is_array( $cat ) && (int) ( $cat['id_tiny'] ?? 0 ) > 0 ) {
				return (int) $cat['id_tiny'];
			}
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $canonico
	 */
	private static function marca_permitida( array $canonico ): bool {
		$permitidas = (array) ( VH_Tiny::obter()['import_marcas'] ?? [] );
		if ( ! $permitidas ) {
			return true;
		}
		$marca = (string) ( $canonico['marca'] ?? '' );
		foreach ( $permitidas as $item ) {
			if ( 0 === strcasecmp( (string) $item, $marca ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<int, array{nome:string, caminho:string}> $mapa
	 */
	private static function categoria_permitida( int $tiny_id, array $mapa ): bool {
		$permitidas = array_map( 'intval', (array) ( VH_Tiny::obter()['import_raizes'] ?? [] ) );
		if ( ! $permitidas || ! isset( $mapa[ $tiny_id ] ) ) {
			return true;
		}
		$caminho = (string) $mapa[ $tiny_id ]['caminho'];
		foreach ( $permitidas as $id ) {
			$raiz = (string) ( $mapa[ $id ]['caminho'] ?? '' );
			if ( '' !== $raiz && ( $caminho === $raiz || str_starts_with( $caminho, $raiz . ' >> ' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<int, array{nome:string, caminho:string}>|WP_Error
	 */
	private static function mapa_categorias() {
		$cache = get_transient( self::ARVORE );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$lista = VH_Tiny::driver()->listar_categorias();
		if ( is_wp_error( $lista ) ) {
			return $lista;
		}
		$mapa = [];
		foreach ( (array) $lista as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = (int) ( $item['id_tiny'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$mapa[ $id ] = [
				'nome'    => (string) ( $item['nome'] ?? '' ),
				'caminho' => (string) ( $item['caminho'] ?? '' ),
			];
		}
		set_transient( self::ARVORE, $mapa, 30 * MINUTE_IN_SECONDS );
		return $mapa;
	}

	/**
	 * Recorte esperado não é falha: estoque, situação, marca ou categoria fora do filtro.
	 */
	private static function e_recorte( WP_Error $erro ): bool {
		return in_array(
			$erro->get_error_code(),
			[ 'vh_tiny_sem_estoque', 'vh_tiny_situacao_inativa', 'vh_tiny_marca_fora', 'vh_tiny_categoria_fora' ],
			true
		);
	}

	/**
	 * @return array{codigo:string,mensagem:string,tiny_id:int}
	 */
	private static function anotar_falha( int $tiny_id, WP_Error $erro ): array {
		$mensagem = $erro->get_error_message();
		if ( class_exists( 'VH_Tiny_Log' ) ) {
			VH_Tiny_Log::warning(
				VH_Tiny_Log::ORIGEM_SYNC,
				$mensagem,
				'tiny_' . $tiny_id,
				[ 'codigo' => $erro->get_error_code() ]
			);
		}
		return [
			'codigo'   => $erro->get_error_code(),
			'mensagem' => $mensagem,
			'tiny_id'  => $tiny_id,
		];
	}

	private static function ja_importado( int $tiny_id ): bool {
		global $wpdb;
		$achou = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				VH_Tiny_Map::META_TINY_ID,
				(string) $tiny_id
			)
		);
		return $achou > 0;
	}

	/**
	 * Preenche preço só quando o Tiny trouxe um valor maior que zero.
	 * Não apaga um preço que a loja já tenha.
	 *
	 * @param array<string, mixed> $fonte
	 */
	private static function preencher_preco( WC_Product $produto, array $fonte ): bool {
		$regular = (string) ( $fonte['preco_regular'] ?? '' );
		if ( '' === $regular || (float) $regular <= 0 ) {
			return false;
		}
		$mudou = false;
		if ( (float) $produto->get_regular_price() <= 0 ) {
			$produto->set_regular_price( wc_format_decimal( $regular ) );
			$mudou = true;
		}
		$promo = (string) ( $fonte['preco_promo'] ?? '' );
		$base  = (float) $produto->get_regular_price();
		if ( '' !== $promo && (float) $promo > 0 && $base > 0 && (float) $promo < $base && '' === (string) $produto->get_sale_price() ) {
			$produto->set_sale_price( wc_format_decimal( $promo ) );
			$mudou = true;
		}
		return $mudou;
	}

	/**
	 * Baixa as fotos do Tiny só quando o produto ainda não tem imagem.
	 * Uma falha de download não desfaz o produto.
	 *
	 * @param array<int, mixed> $urls
	 */
	private static function anexar_imagens( int $product_id, array $urls ): bool {
		$produto = wc_get_product( $product_id );
		if ( ! $produto || $produto->get_image_id() ) {
			return false;
		}
		$validas = [];
		foreach ( $urls as $url ) {
			$url = trim( (string) $url );
			if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
				$validas[] = $url;
			}
		}
		$validas = array_slice( array_values( array_unique( $validas ) ), 0, 6 );
		if ( ! $validas ) {
			return false;
		}
		if ( ! function_exists( 'media_sideload_image' ) && ! file_exists( ABSPATH . 'wp-admin/includes/media.php' ) ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$ids = [];
		foreach ( $validas as $url ) {
			$anexo = media_sideload_image( $url, $product_id, null, 'id' );
			if ( ! is_wp_error( $anexo ) && (int) $anexo > 0 ) {
				$ids[] = (int) $anexo;
			}
		}
		if ( ! $ids ) {
			return false;
		}
		$produto = wc_get_product( $product_id );
		if ( ! $produto ) {
			return false;
		}
		$produto->set_image_id( $ids[0] );
		if ( count( $ids ) > 1 ) {
			$produto->set_gallery_image_ids( array_slice( $ids, 1 ) );
		}
		$produto->save();
		return true;
	}

	/**
	 * Completa preço e foto dos produtos já importados, sem recriar o catálogo.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function completar_precos_imagens() {
		$driver = VH_Tiny::driver();
		if ( ! $driver->conectado() ) {
			return new WP_Error( 'vh_tiny_desconectado', __( 'Tiny ERP não conectado.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$ids = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => [ 'publish', 'private', 'draft' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => VH_Tiny_Map::META_TINY_ID,
			]
		);

		$resumo = [
			'vistos'  => 0,
			'precos'  => 0,
			'imagens' => 0,
			'falhas'  => [],
		];

		foreach ( $ids as $id ) {
			$id      = (int) $id;
			$produto = wc_get_product( $id );
			if ( ! $produto || $produto->get_parent_id() > 0 ) {
				continue;
			}
			$tiny = (int) get_post_meta( $id, VH_Tiny_Map::META_TINY_ID, true );
			if ( $tiny <= 0 ) {
				continue;
			}
			++$resumo['vistos'];
			$canonico = $driver->obter_produto( $tiny );
			if ( is_wp_error( $canonico ) || ! is_array( $canonico ) ) {
				$resumo['falhas'][] = $tiny;
				continue;
			}
			if ( self::aplicar_precos_existente( $produto, $canonico ) ) {
				++$resumo['precos'];
			}
			if ( self::anexar_imagens( $id, (array) ( $canonico['imagens'] ?? [] ) ) ) {
				++$resumo['imagens'];
			}
		}

		return $resumo;
	}

	/**
	 * A vitrine filtra promoção pela coluna onsale. O sync de variação atualiza
	 * o preço, mas não essa coluna.
	 */
	private static function marcar_promocao_na_busca( int $product_id ): void {
		if ( $product_id > 0 && function_exists( 'wc_update_product_lookup_tables_column' ) ) {
			wc_update_product_lookup_tables_column( $product_id, 'onsale' );
		}
	}

	/**
	 * @param array<string, mixed> $canonico
	 */
	private static function aplicar_precos_existente( WC_Product $produto, array $canonico ): bool {
		if ( $produto->is_type( 'simple' ) ) {
			if ( ! self::preencher_preco( $produto, $canonico ) ) {
				return false;
			}
			$produto->save();
			self::marcar_promocao_na_busca( $produto->get_id() );
			return true;
		}
		if ( ! $produto->is_type( 'variable' ) ) {
			return false;
		}

		$por_sku  = [];
		$por_tiny = [];
		foreach ( (array) ( $canonico['variacoes'] ?? [] ) as $var ) {
			if ( ! is_array( $var ) ) {
				continue;
			}
			$sku = trim( (string) ( $var['sku'] ?? '' ) );
			if ( '' !== $sku ) {
				$por_sku[ $sku ] = $var;
			}
			$tiny_var = (int) ( $var['tiny_id'] ?? 0 );
			if ( $tiny_var > 0 ) {
				$por_tiny[ $tiny_var ] = $var;
			}
		}

		$mudou = false;
		foreach ( $produto->get_children() as $child_id ) {
			$child = wc_get_product( (int) $child_id );
			if ( ! $child ) {
				continue;
			}
			$var = $por_sku[ $child->get_sku() ] ?? null;
			if ( ! is_array( $var ) ) {
				$tiny_var = (int) get_post_meta( (int) $child_id, VH_Tiny_Map::META_TINY_ID, true );
				$var      = $por_tiny[ $tiny_var ] ?? null;
			}
			if ( ! is_array( $var ) || ! self::preencher_preco( $child, $var ) ) {
				continue;
			}
			$child->save();
			$mudou = true;
		}
		if ( $mudou ) {
			WC_Product_Variable::sync( $produto->get_id() );
			self::marcar_promocao_na_busca( $produto->get_id() );
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $produto->get_id() );
			}
		}
		return $mudou;
	}

	/**
	 * @return mixed|WP_Error
	 */
	private static function com_trava( callable $callback ) {
		if ( get_transient( self::LOCK ) ) {
			return new WP_Error( 'vh_tiny_import_lock', __( 'Já existe uma importação em andamento.', 'vapor-hub-loja' ), [ 'status' => 409 ] );
		}
		set_transient( self::LOCK, 1, 20 );
		try {
			return $callback();
		} finally {
			delete_transient( self::LOCK );
		}
	}
}

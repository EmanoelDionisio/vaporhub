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
		return $passo;
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
		$teto_lista  = $so_contar ? 40 : 25;

		while ( $vistos < $teto_lista && ( $so_contar || $gravados < $gravar ) ) {
			$resp = VH_Tiny_Client::requisicao(
				'GET',
				'produtos',
				[ 'query' => [ 'limit' => 20, 'offset' => $offset, 'situacao' => 'A' ] ]
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$itens = $resp['itens'] ?? [];
			if ( ! is_array( $itens ) || ! $itens ) {
				$concluida = true;
				break;
			}
			$total = (int) ( $resp['paginacao']['total'] ?? $total );

			foreach ( $itens as $item ) {
				++$vistos;
				if ( ! is_array( $item ) || ! self::e_principal( $item ) ) {
					continue;
				}
				$id = (int) ( $item['id'] ?? 0 );
				if ( $id <= 0 ) {
					continue;
				}
				if ( self::ja_importado( $id ) ) {
					++$pulados;
					continue;
				}
				if ( ! defined( 'VH_TEST' ) ) {
					usleep( 200000 );
				}
				$canonico = $driver->obter_produto( $id );
				if ( is_wp_error( $canonico ) || ! is_array( $canonico ) ) {
					++$fora;
					if ( is_wp_error( $canonico ) ) {
						$ultimo_erro = self::anotar_falha( $id, $canonico );
					}
					continue;
				}
				if ( self::quantidade( $canonico ) <= 0 ) {
					++$sem_estoque;
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
						$ultimo_erro = self::anotar_falha( $id, $resultado );
					}
					continue;
				}
				++$gravados;
				if ( $gravados >= $gravar ) {
					break;
				}
			}

			$offset += count( $itens );
			if ( count( $itens ) < 20 || ( $total > 0 && $offset >= $total ) ) {
				$concluida = true;
				break;
			}
			if ( ! $so_contar && $gravados >= $gravar ) {
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
		];
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
			$produto->set_regular_price( wc_format_decimal( (string) ( $canonico['preco_regular'] ?? '0' ) ) );
			if ( ! empty( $canonico['preco_promo'] ) ) {
				$produto->set_sale_price( wc_format_decimal( (string) $canonico['preco_promo'] ) );
			}
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
		if ( isset( $var['preco_regular'] ) && '' !== (string) $var['preco_regular'] ) {
			$variacao->set_regular_price( wc_format_decimal( (string) $var['preco_regular'] ) );
		}
		if ( ! empty( $var['preco_promo'] ) ) {
			$variacao->set_sale_price( wc_format_decimal( (string) $var['preco_promo'] ) );
		}
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
	 * @return mixed|WP_Error
	 */
	private static function com_trava( callable $callback ) {
		if ( get_transient( self::LOCK ) ) {
			return new WP_Error( 'vh_tiny_import_lock', __( 'Já existe uma importação em andamento.', 'vapor-hub-loja' ), [ 'status' => 409 ] );
		}
		set_transient( self::LOCK, 1, 2 * MINUTE_IN_SECONDS );
		try {
			return $callback();
		} finally {
			delete_transient( self::LOCK );
		}
	}
}

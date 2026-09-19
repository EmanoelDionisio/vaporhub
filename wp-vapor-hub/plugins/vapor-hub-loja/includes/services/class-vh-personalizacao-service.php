<?php
/**
 * Personalização por produto — configuração, materialização WooCommerce e migração legado.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Personalizacao_Service {

	public const META_CONFIG   = '_vh_personalizacao';
	public const META_MIGRADO  = '_vh_personalizacao_migrado_em';
	public const META_COR      = '_vh_cor_hex';
	public const META_PRECO    = '_vh_preco_acrescimo';
	public const META_IMAGEM   = '_vh_imagem_id';

	public const VERSAO        = 1;
	public const MAX_DIMENSOES = 8;
	public const MAX_OPCOES    = 30;
	public const MAX_COMBINACOES_AVISO = 200;

	/** @var string[] */
	public const TIPOS = [ 'cor', 'imagem', 'texto' ];

	public static function init(): void {
		add_action( 'before_delete_post', [ __CLASS__, 'hook_limpar_produto' ], 10, 2 );
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public static function hook_limpar_produto( int $post_id, WP_Post $post ): void {
		if ( 'product' !== $post->post_type ) {
			return;
		}
		self::limpar_produto( $post_id );
	}

	/**
	 * @return array{versao:int,preco_base:string,dimensoes:array<int,array<string,mixed>>}
	 */
	public static function config_vazia(): array {
		return [
			'versao'     => self::VERSAO,
			'preco_base' => '',
			'dimensoes'  => [],
		];
	}

	/**
	 * @return array{versao:int,preco_base:string,dimensoes:array<int,array<string,mixed>>}
	 */
	public static function obter_config( int $product_id ): array {
		$raw = get_post_meta( $product_id, self::META_CONFIG, true );
		if ( is_array( $raw ) && ! empty( $raw['dimensoes'] ) ) {
			return self::enriquecer_config( $product_id, $raw );
		}

		$produto = wc_get_product( $product_id );
		if ( $produto instanceof WC_Product_Variable ) {
			$legado = self::montar_config_legado( $produto );
			if ( ! empty( $legado['dimensoes'] ) ) {
				return self::enriquecer_config( $product_id, $legado );
			}
		}

		return self::config_vazia();
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array{versao:int,preco_base:string,dimensoes:array<int,array<string,mixed>>}
	 */
	private static function enriquecer_config( int $product_id, array $config ): array {
		$config = wp_parse_args(
			$config,
			self::config_vazia()
		);
		$config['versao'] = self::VERSAO;

		foreach ( $config['dimensoes'] as $i => $dim ) {
			if ( ! is_array( $dim ) ) {
				continue;
			}
			$opcoes = [];
			foreach ( (array) ( $dim['opcoes'] ?? [] ) as $op ) {
				if ( ! is_array( $op ) ) {
					continue;
				}
				$img_id = absint( $op['imagem_id'] ?? 0 );
				$op['imagem_id']  = $img_id;
				$op['imagem_url'] = $img_id ? ( wp_get_attachment_image_url( $img_id, 'thumbnail' ) ?: '' ) : '';
				$opcoes[]           = $op;
			}
			$config['dimensoes'][ $i ]['opcoes'] = $opcoes;
			if ( empty( $config['dimensoes'][ $i ]['id'] ) ) {
				$config['dimensoes'][ $i ]['id'] = self::gerar_id( 'dim' );
			}

			$tipo_atual = (string) ( $config['dimensoes'][ $i ]['tipo'] ?? 'texto' );
			$taxonomy   = (string) ( $config['dimensoes'][ $i ]['taxonomy'] ?? '' );
			if ( 'texto' === $tipo_atual && '' !== $taxonomy ) {
				$inferido = self::inferir_tipo_legado( $taxonomy );
				if ( 'texto' !== $inferido ) {
					$config['dimensoes'][ $i ]['tipo'] = $inferido;
				}
			}
		}

		return $config;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array{versao:int,preco_base:string,dimensoes:array<int,array<string,mixed>>}|WP_Error
	 */
	public static function validar_config( array $config ) {
		$preco_base = isset( $config['preco_base'] ) ? trim( str_replace( ',', '.', (string) $config['preco_base'] ) ) : '';
		if ( '' !== $preco_base && ( ! is_numeric( $preco_base ) || (float) $preco_base < 0 ) ) {
			return new WP_Error( 'vh_pers_preco_base', __( 'Preço base inválido.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$dimensoes = $config['dimensoes'] ?? [];
		if ( ! is_array( $dimensoes ) ) {
			return new WP_Error( 'vh_pers_dimensoes', __( 'Formato de personalização inválido.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		if ( count( $dimensoes ) > self::MAX_DIMENSOES ) {
			return new WP_Error(
				'vh_pers_max_dim',
				sprintf(
					/* translators: %d: max dimensions */
					__( 'Máximo de %d dimensões de personalização por produto.', 'vapor-hub-loja' ),
					self::MAX_DIMENSOES
				),
				[ 'status' => 400 ]
			);
		}

		$limpas   = [];
		$ordem    = 0;
		$combinacoes = 1;

		foreach ( $dimensoes as $dim ) {
			if ( ! is_array( $dim ) ) {
				continue;
			}

			$nome = sanitize_text_field( (string) ( $dim['nome'] ?? '' ) );
			if ( '' === $nome ) {
				return new WP_Error( 'vh_pers_dim_sem_nome', __( 'Informe o nome de cada dimensão de personalização.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
			}

			$tipo = sanitize_key( (string) ( $dim['tipo'] ?? 'texto' ) );
			if ( ! in_array( $tipo, self::TIPOS, true ) ) {
				$tipo = 'texto';
			}

			$dim_id = sanitize_key( (string) ( $dim['id'] ?? '' ) );
			if ( '' === $dim_id ) {
				$dim_id = self::gerar_id( 'dim' );
			}

			$opcoes_raw = $dim['opcoes'] ?? [];
			if ( ! is_array( $opcoes_raw ) || ! $opcoes_raw ) {
				return new WP_Error(
					'vh_pers_sem_opcoes',
					sprintf(
						/* translators: %s: dimension name */
						__( 'A dimensão “%s” precisa de ao menos uma opção.', 'vapor-hub-loja' ),
						$nome
					),
					[ 'status' => 400 ]
				);
			}

			if ( count( $opcoes_raw ) > self::MAX_OPCOES ) {
				return new WP_Error(
					'vh_pers_max_opcoes',
					sprintf(
						/* translators: 1: dimension name, 2: max options */
						__( 'A dimensão “%1$s” excede o máximo de %2$d opções.', 'vapor-hub-loja' ),
						$nome,
						self::MAX_OPCOES
					),
					[ 'status' => 400 ]
				);
			}

			$opcoes_limpas = [];
			$slugs_usados  = [];

			foreach ( $opcoes_raw as $op ) {
				if ( ! is_array( $op ) ) {
					continue;
				}
				$op_nome = sanitize_text_field( (string) ( $op['nome'] ?? '' ) );
				if ( '' === $op_nome ) {
					continue;
				}

				$slug = isset( $op['slug'] ) && '' !== trim( (string) $op['slug'] )
					? sanitize_title( (string) $op['slug'] )
					: sanitize_title( $op_nome );
				if ( '' === $slug ) {
					$slug = sanitize_title( $op_nome . '-' . wp_generate_password( 4, false ) );
				}

				$base_slug = $slug;
				$n         = 2;
				while ( isset( $slugs_usados[ $slug ] ) ) {
					$slug = $base_slug . '-' . $n;
					++$n;
				}
				$slugs_usados[ $slug ] = true;

				$op_id = sanitize_key( (string) ( $op['id'] ?? '' ) );
				if ( '' === $op_id ) {
					$op_id = self::gerar_id( 'op' );
				}

				$preco = self::sanitizar_acrescimo( (string) ( $op['preco_acrescimo'] ?? '' ) );

				$item = [
					'id'              => $op_id,
					'nome'            => $op_nome,
					'slug'            => $slug,
					'preco_acrescimo' => $preco,
					'cor'             => '',
					'imagem_id'       => 0,
				];

				if ( 'cor' === $tipo ) {
					$hex = sanitize_hex_color( (string) ( $op['cor'] ?? '' ) );
					$item['cor'] = $hex ?: '#c0c0c0';
				}

				if ( 'imagem' === $tipo ) {
					$item['imagem_id'] = absint( $op['imagem_id'] ?? 0 );
				}

				$opcoes_limpas[] = $item;
			}

			if ( ! $opcoes_limpas ) {
				return new WP_Error(
					'vh_pers_sem_opcoes',
					sprintf(
						/* translators: %s: dimension name */
						__( 'A dimensão “%s” precisa de ao menos uma opção válida.', 'vapor-hub-loja' ),
						$nome
					),
					[ 'status' => 400 ]
				);
			}

			$combinacoes *= count( $opcoes_limpas );

			$limpas[] = [
				'id'       => $dim_id,
				'nome'     => $nome,
				'tipo'     => $tipo,
				'ordem'    => isset( $dim['ordem'] ) ? (int) $dim['ordem'] : $ordem,
				'taxonomy' => sanitize_key( (string) ( $dim['taxonomy'] ?? '' ) ),
				'opcoes'   => $opcoes_limpas,
			];
			++$ordem;
		}

		usort(
			$limpas,
			static function ( $a, $b ) {
				return (int) $a['ordem'] <=> (int) $b['ordem'];
			}
		);

		return [
			'versao'              => self::VERSAO,
			'preco_base'          => '' !== $preco_base ? wc_format_decimal( $preco_base ) : '',
			'dimensoes'           => $limpas,
			'total_combinacoes'   => $combinacoes,
			'aviso_combinacoes'   => $combinacoes > self::MAX_COMBINACOES_AVISO,
		];
	}

	/**
	 * Persiste config, materializa atributos WC e retorna seleção para variações.
	 *
	 * @param array<string,mixed> $config
	 * @return array{selecao:array<string,string[]>,preco_base:string,config:array<string,mixed>}|WP_Error
	 */
	public static function salvar( WC_Product_Variable $produto, array $config ) {
		$validado = self::validar_config( $config );
		if ( is_wp_error( $validado ) ) {
			return $validado;
		}

		$product_id = $produto->get_id();
		if ( $product_id <= 0 ) {
			return new WP_Error( 'vh_pers_sem_id', __( 'Salve o produto antes de configurar personalização.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$anterior   = get_post_meta( $product_id, self::META_CONFIG, true );
		$era_legado = ! is_array( $anterior ) || empty( $anterior['dimensoes'] );

		if ( $era_legado && self::produto_tem_attrs_legados( $produto ) ) {
			update_post_meta( $product_id, self::META_MIGRADO, VH_DateTime::agora_utc() );
		}

		$selecao    = [];
		$atributos  = [];
		$posicao    = 0;
		$tax_usadas = [];

		foreach ( $validado['dimensoes'] as $dim ) {
			$taxonomy = self::resolver_taxonomia( $product_id, $dim );
			$dim['taxonomy'] = $taxonomy;
			$tax_usadas[]    = $taxonomy;

			$attr_id = self::garantir_atributo_wc( $taxonomy, (string) $dim['nome'] );
			if ( is_wp_error( $attr_id ) ) {
				return $attr_id;
			}

			self::registrar_taxonomia( $taxonomy );

			$slugs    = [];
			$term_ids = [];

			foreach ( $dim['opcoes'] as $op ) {
				$term_id = self::sincronizar_termo( $taxonomy, $op, (string) $dim['tipo'] );
				if ( is_wp_error( $term_id ) ) {
					return $term_id;
				}
				$slugs[]    = (string) $op['slug'];
				$term_ids[] = $term_id;
			}

			self::podar_termos( $taxonomy, $term_ids );

			$selecao[ $taxonomy ] = $slugs;

			$obj = new WC_Product_Attribute();
			$obj->set_id( $attr_id );
			$obj->set_name( $taxonomy );
			$obj->set_options( $term_ids );
			$obj->set_position( $posicao++ );
			$obj->set_visible( true );
			$obj->set_variation( true );
			$atributos[] = $obj;
		}

		$produto->set_attributes( $atributos );
		$produto->save();

		self::podar_taxonomias_produto( $product_id, $tax_usadas );

		$validado['dimensoes'] = array_map(
			static function ( $dim ) use ( $product_id ) {
				$dim['taxonomy'] = self::resolver_taxonomia( $product_id, $dim );
				return $dim;
			},
			$validado['dimensoes']
		);

		update_post_meta( $product_id, self::META_CONFIG, $validado, false );

		$preco_base = (string) $validado['preco_base'];
		if ( '' !== $preco_base ) {
			update_post_meta( $product_id, '_vh_preco_base', $preco_base );
		} else {
			delete_post_meta( $product_id, '_vh_preco_base' );
		}

		return [
			'selecao'     => $selecao,
			'preco_base'  => $preco_base,
			'config'      => $validado,
		];
	}

	/**
	 * @return array<int,array{taxonomy:string,id:int,nome:string,tipo:string,termos:array<int,array<string,mixed>>}>
	 */
	public static function listar_taxonomias_sistema(): array {
		$lista  = [];
		$vistos = [];

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $lista;
		}

		foreach ( wc_get_attribute_taxonomies() as $tax_obj ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax_obj->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$vistos[ $taxonomy ] = true;
			$lista[]             = self::formatar_taxonomia_catalogo( $tax_obj, $taxonomy );
		}

		// Taxonomias vh_p* ainda não registradas no cache WC.
		global $wpdb;
		$like = $wpdb->esc_like( 'vh_p' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT attribute_name, attribute_label, attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name LIKE %s",
				$like
			)
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$taxonomy = wc_attribute_taxonomy_name( $row->attribute_name );
				if ( isset( $vistos[ $taxonomy ] ) ) {
					continue;
				}
				$vistos[ $taxonomy ] = true;
				$lista[]             = self::formatar_taxonomia_catalogo( $row, $taxonomy );
			}
		}

		return $lista;
	}

	/**
	 * @return array<string,string> taxonomy => tipo
	 */
	public static function tipos_por_taxonomia( int $product_id ): array {
		$raw = get_post_meta( $product_id, self::META_CONFIG, true );
		if ( ! is_array( $raw ) || empty( $raw['dimensoes'] ) ) {
			return [];
		}

		$mapa = [];
		foreach ( $raw['dimensoes'] as $dim ) {
			if ( ! is_array( $dim ) || empty( $dim['taxonomy'] ) ) {
				continue;
			}
			$mapa[ (string) $dim['taxonomy'] ] = sanitize_key( (string) ( $dim['tipo'] ?? 'texto' ) );
		}
		return $mapa;
	}

	public static function imagem_termo( \WP_Term $termo ): array {
		$id = absint( get_term_meta( $termo->term_id, self::META_IMAGEM, true ) );
		return [
			'id'  => $id,
			'url' => $id ? ( wp_get_attachment_image_url( $id, 'thumbnail' ) ?: '' ) : '',
		];
	}

	public static function limpar_produto( int $product_id ): void {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		$prefixo = 'p' . $product_id . '_';

		foreach ( wc_get_attribute_taxonomies() as $tax_obj ) {
			$name = (string) $tax_obj->attribute_name;
			if ( 0 !== strpos( $name, $prefixo ) ) {
				continue;
			}
			if ( function_exists( 'wc_delete_attribute' ) ) {
				wc_delete_attribute( (int) $tax_obj->attribute_id );
			}
		}

		delete_post_meta( $product_id, self::META_CONFIG );
		delete_post_meta( $product_id, self::META_MIGRADO );
	}

	/**
	 * @return array{versao:int,preco_base:string,dimensoes:array<int,array<string,mixed>>}
	 */
	public static function montar_config_legado( WC_Product_Variable $produto ): array {
		$config = self::config_vazia();
		$meta   = get_post_meta( $produto->get_id(), '_vh_preco_base', true );
		if ( '' !== (string) $meta ) {
			$config['preco_base'] = wc_format_decimal( (string) $meta );
		}

		$ordem = 0;
		foreach ( $produto->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute || ! $attr->get_variation() || ! $attr->is_taxonomy() ) {
				continue;
			}

			$taxonomy = $attr->get_name();
			$tipo     = self::inferir_tipo_legado( $taxonomy );
			$opcoes   = [];

			foreach ( $attr->get_options() as $term_id ) {
				$termo = get_term( (int) $term_id, $taxonomy );
				if ( ! $termo instanceof WP_Term ) {
					continue;
				}

				$img = self::imagem_termo( $termo );
				$opcoes[] = [
					'id'              => 'op_' . $termo->term_id,
					'nome'            => $termo->name,
					'slug'            => $termo->slug,
					'preco_acrescimo' => class_exists( 'VH_Products_Service' ) ? VH_Products_Service::preco_acrescimo_termo( $termo ) : '',
					'cor'             => ( 'cor' === $tipo && class_exists( 'VH_Products_Service' ) ) ? VH_Products_Service::cor_termo( $termo ) : '',
					'imagem_id'       => $img['id'],
					'imagem_url'      => $img['url'],
				];
			}

			if ( ! $opcoes ) {
				continue;
			}

			$config['dimensoes'][] = [
				'id'       => self::gerar_id( 'dim' ),
				'nome'     => wc_attribute_label( $taxonomy ),
				'tipo'     => 'modelo' === $tipo ? 'imagem' : $tipo,
				'ordem'    => $ordem++,
				'taxonomy' => $taxonomy,
				'opcoes'   => $opcoes,
			];
		}

		return $config;
	}

	/**
	 * @param object $tax_obj
	 * @return array{taxonomy:string,id:int,nome:string,tipo:string,termos:array<int,array<string,mixed>>}
	 */
	private static function formatar_taxonomia_catalogo( $tax_obj, string $taxonomy ): array {
		$termos = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		if ( is_wp_error( $termos ) ) {
			$termos = [];
		}

		$tipo = self::inferir_tipo_legado( $taxonomy );
		$itens = [];
		foreach ( $termos as $termo ) {
			$img = self::imagem_termo( $termo );
			$itens[] = [
				'id'              => (int) $termo->term_id,
				'nome'            => $termo->name,
				'slug'            => $termo->slug,
				'cor'             => ( 'cor' === $tipo && class_exists( 'VH_Products_Service' ) ) ? VH_Products_Service::cor_termo( $termo ) : '',
				'preco_acrescimo' => class_exists( 'VH_Products_Service' ) ? VH_Products_Service::preco_acrescimo_termo( $termo ) : '',
				'imagem_id'       => $img['id'],
				'imagem_url'      => $img['url'],
			];
		}

		return [
			'taxonomy' => $taxonomy,
			'id'       => (int) ( $tax_obj->attribute_id ?? 0 ),
			'nome'     => (string) ( $tax_obj->attribute_label ?? wc_attribute_label( $taxonomy ) ),
			'tipo'     => $tipo,
			'termos'   => $itens,
		];
	}

	private static function produto_tem_attrs_legados( WC_Product_Variable $produto ): bool {
		foreach ( $produto->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute || ! $attr->is_taxonomy() ) {
				continue;
			}
			$taxonomy = $attr->get_name();
			if ( ! self::eh_taxonomia_produto( $produto->get_id(), $taxonomy ) ) {
				return true;
			}
		}
		return false;
	}

	public static function eh_taxonomia_produto( int $product_id, string $taxonomy ): bool {
		$name = str_replace( 'pa_', '', $taxonomy );
		return 0 === strpos( $name, 'p' . $product_id . '_' );
	}

	/**
	 * @param array<string,mixed> $dim
	 */
	private static function resolver_taxonomia( int $product_id, array $dim ): string {
		$existente = sanitize_key( (string) ( $dim['taxonomy'] ?? '' ) );
		if ( '' !== $existente && taxonomy_exists( $existente ) ) {
			if ( self::eh_taxonomia_produto( $product_id, $existente ) ) {
				return $existente;
			}
			if ( self::produto_usa_taxonomia( $product_id, $existente ) ) {
				return $existente;
			}
			if ( self::taxonomia_reutilizavel( $existente ) ) {
				return $existente;
			}
		}

		$dim_id = sanitize_key( (string) ( $dim['id'] ?? '' ) );
		return wc_attribute_taxonomy_name( self::slug_atributo( $product_id, $dim_id ) );
	}

	/**
	 * Atributo global Woo (vh_*) elegível para reutilização entre produtos.
	 */
	public static function taxonomia_reutilizavel( string $taxonomy ): bool {
		if ( ! str_starts_with( $taxonomy, 'pa_' ) || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return false;
		}

		foreach ( wc_get_attribute_taxonomies() as $tax_obj ) {
			if ( wc_attribute_taxonomy_name( $tax_obj->attribute_name ) === $taxonomy ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Taxonomia de atributo global já vinculada ao produto (legado).
	 */
	private static function produto_usa_taxonomia( int $product_id, string $taxonomy ): bool {
		$produto = wc_get_product( $product_id );
		if ( ! $produto instanceof WC_Product_Variable ) {
			return false;
		}

		foreach ( $produto->get_attributes() as $attr ) {
			if ( $attr instanceof WC_Product_Attribute && $attr->is_taxonomy() && $attr->get_name() === $taxonomy ) {
				return true;
			}
		}

		return false;
	}

	public static function slug_atributo( int $product_id, string $dim_id ): string {
		$hash = substr( md5( $dim_id ), 0, 8 );
		$slug = 'p' . $product_id . '_' . $hash;
		return substr( $slug, 0, 28 );
	}

	/**
	 * @return int|WP_Error
	 */
	private static function garantir_atributo_wc( string $taxonomy, string $label ) {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return new WP_Error( 'vh_wc_indisponivel', __( 'WooCommerce indisponível.', 'vapor-hub-loja' ), [ 'status' => 500 ] );
		}

		foreach ( wc_get_attribute_taxonomies() as $tax_obj ) {
			if ( wc_attribute_taxonomy_name( $tax_obj->attribute_name ) === $taxonomy ) {
				if ( function_exists( 'wc_update_attribute' ) && $label !== $tax_obj->attribute_label ) {
					wc_update_attribute( (int) $tax_obj->attribute_id, [ 'name' => $label ] );
				}
				return (int) $tax_obj->attribute_id;
			}
		}

		$slug = substr( str_replace( 'pa_', '', $taxonomy ), 0, 28 );

		$id = wc_create_attribute(
			[
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			]
		);

		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'vh_pers_attr', $id->get_error_message(), [ 'status' => 400 ] );
		}

		self::limpar_cache_atributos();
		self::registrar_taxonomia( $taxonomy );

		return (int) $id;
	}

	/**
	 * @param array<string,mixed> $op
	 * @return int|WP_Error
	 */
	private static function sincronizar_termo( string $taxonomy, array $op, string $tipo ) {
		$slug = sanitize_title( (string) ( $op['slug'] ?? '' ) );
		$nome = sanitize_text_field( (string) ( $op['nome'] ?? '' ) );

		$termo = get_term_by( 'slug', $slug, $taxonomy );
		if ( $termo instanceof WP_Term ) {
			wp_update_term( (int) $termo->term_id, $taxonomy, [ 'name' => $nome, 'slug' => $slug ] );
			$term_id = (int) $termo->term_id;
		} else {
			$res = wp_insert_term( $nome, $taxonomy, [ 'slug' => $slug ] );
			if ( is_wp_error( $res ) ) {
				return new WP_Error( 'vh_pers_termo', $res->get_error_message(), [ 'status' => 400 ] );
			}
			$term_id = (int) $res['term_id'];
		}

		if ( 'cor' === $tipo ) {
			$hex = sanitize_hex_color( (string) ( $op['cor'] ?? '' ) );
			if ( $hex ) {
				update_term_meta( $term_id, self::META_COR, $hex );
			}
		}

		if ( 'imagem' === $tipo ) {
			$img_id = absint( $op['imagem_id'] ?? 0 );
			if ( $img_id > 0 ) {
				update_term_meta( $term_id, self::META_IMAGEM, $img_id );
			} else {
				delete_term_meta( $term_id, self::META_IMAGEM );
			}
		}

		$preco = self::sanitizar_acrescimo( (string) ( $op['preco_acrescimo'] ?? '' ) );
		if ( '' === $preco ) {
			delete_term_meta( $term_id, self::META_PRECO );
		} else {
			update_term_meta( $term_id, self::META_PRECO, $preco );
		}

		return $term_id;
	}

	/**
	 * @param int[] $manter_ids
	 */
	private static function podar_termos( string $taxonomy, array $manter_ids ): void {
		$manter = array_flip( array_map( 'intval', $manter_ids ) );
		$termos = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
		if ( is_wp_error( $termos ) ) {
		 return;
		}
		foreach ( $termos as $termo ) {
			if ( ! isset( $manter[ (int) $termo->term_id ] ) && (int) $termo->count <= 0 ) {
				wp_delete_term( (int) $termo->term_id, $taxonomy );
			}
		}
	}

	/**
	 * @param string[] $manter_taxonomias
	 */
	private static function podar_taxonomias_produto( int $product_id, array $manter_taxonomias ): void {
		if ( ! function_exists( 'wc_delete_attribute' ) ) {
			return;
		}

		$manter = array_flip( $manter_taxonomias );
		$base   = 'p' . $product_id . '_';

		foreach ( wc_get_attribute_taxonomies() as $tax_obj ) {
			$name = (string) $tax_obj->attribute_name;
			if ( 0 !== strpos( $name, $base ) ) {
				continue;
			}
			$taxonomy = wc_attribute_taxonomy_name( $name );
			if ( ! isset( $manter[ $taxonomy ] ) ) {
				wc_delete_attribute( (int) $tax_obj->attribute_id );
			}
		}
	}

	private static function registrar_taxonomia( string $taxonomy ): void {
		if ( taxonomy_exists( $taxonomy ) ) {
			return;
		}
		if ( class_exists( 'WC_Post_Types' ) ) {
			self::limpar_cache_atributos();
			WC_Post_Types::register_taxonomies();
		}
	}

	private static function limpar_cache_atributos(): void {
		delete_transient( 'wc_attribute_taxonomies' );
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		}
	}

	private static function inferir_tipo_legado( string $taxonomy ): string {
		if ( false !== strpos( $taxonomy, 'cor' ) ) {
			return 'cor';
		}
		if ( false !== strpos( $taxonomy, 'modelo' ) || false !== strpos( $taxonomy, 'formato' ) ) {
			return 'imagem';
		}
		return 'texto';
	}

	private static function sanitizar_acrescimo( string $valor ): string {
		$valor = trim( str_replace( ',', '.', $valor ) );
		if ( '' === $valor || ! is_numeric( $valor ) ) {
			return '';
		}
		$num = (float) wc_format_decimal( $valor );
		return $num <= 0 ? '' : wc_format_decimal( (string) $num );
	}

	private static function gerar_id( string $prefixo ): string {
		return $prefixo . '_' . substr( wp_generate_password( 8, false ), 0, 8 );
	}

	/**
	 * Enfileira o editor JS (wp-admin e portal /minha-loja).
	 */
	public static function enqueue_editor_assets(): void {
		if ( 'produtos' !== VH_Router::secao_atual() ) {
			return;
		}

		$acao = VH_Router::acao_atual( 'produtos' );
		if ( ! in_array( $acao, [ 'novo', 'editar' ], true ) ) {
			return;
		}

		$prod_id = VH_Router::id_atual();
		$config  = $prod_id > 0 ? self::obter_config( $prod_id ) : self::config_vazia();

		wp_enqueue_script(
			'vh-produto-personalizacao-js',
			VH_LOJA_ASSETS . 'js/produto-personalizacao.js',
			[ 'vh-app-js' ],
			VH_LOJA_VERSION,
			true
		);
		wp_localize_script(
			'vh-produto-personalizacao-js',
			'paProdutoPers',
			[
				'config'         => $config,
				'catalogo'       => self::listar_taxonomias_sistema(),
				'maxDimensoes'   => self::MAX_DIMENSOES,
				'maxOpcoes'      => self::MAX_OPCOES,
				'maxCombinacoes' => self::MAX_COMBINACOES_AVISO,
				'i18n'           => self::i18n_editor(),
			]
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function i18n_editor(): array {
		return [
			'addDim'            => __( 'Adicionar opção de personalização', 'vapor-hub-loja' ),
			'addOpcao'          => __( 'Adicionar opção', 'vapor-hub-loja' ),
			'removerDim'        => __( 'Remover', 'vapor-hub-loja' ),
			'removerDimAria'    => __( 'Remover personalização', 'vapor-hub-loja' ),
			'removerOpcao'      => __( 'Remover opção', 'vapor-hub-loja' ),
			'confirmRemoverDim' => __( 'Remover esta opção de personalização e todas as escolhas?', 'vapor-hub-loja' ),
			'confirmRemoverDimTitulo' => __( 'Remover opção de personalização?', 'vapor-hub-loja' ),
			'confirmRemoverDimBtn'  => __( 'Remover', 'vapor-hub-loja' ),
			'cancelar'              => __( 'Cancelar', 'vapor-hub-loja' ),
			'dimNome'           => __( 'Nome (ex.: Cor do EVA, Lado, Tamanho)', 'vapor-hub-loja' ),
			'dimLabel'          => __( 'Personalização', 'vapor-hub-loja' ),
			'tipoLabel'         => __( 'Exibição na loja', 'vapor-hub-loja' ),
			'opcaoNome'         => __( 'Nome da opção', 'vapor-hub-loja' ),
			'tipoCor'           => __( 'Cores (swatches)', 'vapor-hub-loja' ),
			'tipoImagem'        => __( 'Cards com imagem', 'vapor-hub-loja' ),
			'tipoTexto'         => __( 'Botões de texto', 'vapor-hub-loja' ),
			'colOpcao'          => __( 'Opção', 'vapor-hub-loja' ),
			'colCor'            => __( 'Cor', 'vapor-hub-loja' ),
			'colImagem'         => __( 'Imagem', 'vapor-hub-loja' ),
			'colPreco'          => __( 'Acréscimo', 'vapor-hub-loja' ),
			'selecionarImagem'  => __( 'Selecionar imagem', 'vapor-hub-loja' ),
			'usarImagem'        => __( 'Usar imagem', 'vapor-hub-loja' ),
			'corSwatch'         => __( 'Cor exibida na loja', 'vapor-hub-loja' ),
			'vazio'             => __( 'Nenhuma opção de personalização ainda. Adicione cor, formato, tamanho ou outra.', 'vapor-hub-loja' ),
			'combinacoes'       => __( '%d combinações', 'vapor-hub-loja' ),
			'combinacoesAviso'  => __( 'muitas combinações — considere reduzir opções', 'vapor-hub-loja' ),
			'maxDim'            => __( 'Limite de opções de personalização atingido.', 'vapor-hub-loja' ),
			'maxOpc'            => __( 'Limite de opções atingido.', 'vapor-hub-loja' ),
			'dimSemNome'        => __( 'Personalização sem nome', 'vapor-hub-loja' ),
			'dimSingular'       => __( '1 opção de personalização', 'vapor-hub-loja' ),
			'dimensoesPlural'   => __( '%d opções de personalização', 'vapor-hub-loja' ),
			'opcaoSingular'     => __( '1 opção', 'vapor-hub-loja' ),
			'opcoesPlural'      => __( '%d opções', 'vapor-hub-loja' ),
			'arrastar'          => __( 'Arrastar para reordenar', 'vapor-hub-loja' ),
			'arrastarDica'      => __( 'Arraste pelo ícone ☰ para reordenar', 'vapor-hub-loja' ),
			'reusoBusca'        => __( 'Buscar personalização existente', 'vapor-hub-loja' ),
			'reusoPlaceholder'  => __( 'Ex.: Tamanho do EVA, Cor do EVA…', 'vapor-hub-loja' ),
			'reusoVazio'        => __( 'Nenhuma personalização encontrada.', 'vapor-hub-loja' ),
			'reusoJaAdicionada' => __( 'Esta personalização já está neste produto.', 'vapor-hub-loja' ),
			'reusoCompartilhada'=> __( 'Compartilhada', 'vapor-hub-loja' ),
			'reusoNota'         => __( 'Usada em outros produtos — alterações nas opções valem para todos que a utilizam.', 'vapor-hub-loja' ),
			'addDimNova'        => __( 'Criar personalização nova', 'vapor-hub-loja' ),
			'erroMidia'         => __( 'Biblioteca de mídia indisponível.', 'vapor-hub-loja' ),
		];
	}
}

<?php
/**
 * Mapeamento WooCommerce ↔ Tiny ERP (formato canônico neutro).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Tiny_Map {

	public const META_TINY_ID      = '_vh_tiny_id';
	public const META_TINY_ID_ANTERIOR = '_vh_tiny_id_anterior';
	public const META_SYNC_HASH    = '_vh_tiny_sync_hash';
	public const META_LAST_SYNC    = '_vh_tiny_last_sync';
	public const META_GUARD        = '_vh_tiny_sync_guard';
	public const META_TINY_PEDIDO  = '_vh_tiny_pedido_id';
	public const META_TINY_CONTATO = '_vh_tiny_contato_id';
	public const META_TINY_CAT_ID  = '_vh_tiny_cat_id';
	public const META_TINY_CAT_IGNORAR = '_vh_tiny_cat_ignorar';
	public const META_VAR_REMOVIDAS = '_vh_tiny_var_removidas';

	/**
	 * Conteúdo do produto WC em formato canônico (neutro).
	 *
	 * @return array<string, mixed>
	 */
	public static function wc_para_canonico( WC_Product $produto ): array {
		$categorias = self::categorias_wc( $produto );
		$imagens    = self::imagens_wc( $produto );

		$base = [
			'tipo'            => $produto->is_type( 'variable' ) ? 'variavel' : 'simples',
			'sku'             => $produto->get_sku(),
			'nome'            => $produto->get_name(),
			'descricao'       => wp_strip_all_tags( $produto->get_description() ),
			'descricao_curta' => wp_strip_all_tags( $produto->get_short_description() ),
			'peso'            => (float) $produto->get_weight(),
			'envio'           => self::envio_wc( $produto ),
			'fiscal'          => self::fiscal_wc( $produto ),
			'publicado'       => 'publish' === $produto->get_status(),
			'categorias'      => $categorias,
			'imagens'         => $imagens,
			'produto_id'      => $produto->get_id(),
		];

		if ( $produto->is_type( 'variable' ) && $produto instanceof WC_Product_Variable ) {
			$base['grade_atributos'] = self::grade_atributos_wc( $produto );
			$base['variacoes']       = self::variacoes_wc( $produto );
		} elseif ( $produto->is_type( 'simple' ) ) {
			$base['preco_regular']    = $produto->get_regular_price();
			$base['preco_promo']      = $produto->get_sale_price();
			$base['gerencia_estoque'] = $produto->get_manage_stock();
			if ( $produto->get_manage_stock() ) {
				$base['estoque'] = $produto->get_stock_quantity();
			}
		}

		return array_filter(
			$base,
			static fn( $v ) => null !== $v && '' !== $v && [] !== $v
		);
	}

	/**
	 * Bloco logístico do produto pai: peso, medidas, volumes e embalagem.
	 *
	 * Só entra o que está preenchido. Zero aqui significa “não informado”, e
	 * mandar zero ao ERP apagaria dado bom do cadastro de lá.
	 *
	 * @return array<string, mixed>
	 */
	public static function envio_wc( WC_Product $produto ): array {
		$peso = (float) $produto->get_weight();

		return array_filter(
			[
				'peso_bruto'     => $peso,
				'peso_liquido'   => $peso,
				'largura'        => (float) $produto->get_width(),
				'altura'         => (float) $produto->get_height(),
				'comprimento'    => (float) $produto->get_length(),
				'volumes'        => (int) get_post_meta( $produto->get_id(), VH_Products_Service::META_VOLUMES, true ),
				'embalagem_tipo' => (int) get_post_meta( $produto->get_id(), VH_Products_Service::META_EMBALAGEM, true ),
			],
			static fn( $v ) => $v > 0
		);
	}

	/**
	 * Identidade fiscal do produto pai: NCM, GTIN, unidade e marca.
	 *
	 * @return array<string, mixed>
	 */
	public static function fiscal_wc( WC_Product $produto ): array {
		$id       = $produto->get_id();
		$marca_id = (int) get_post_meta( $id, VH_Products_Service::META_MARCA_ID, true );

		return array_filter(
			[
				'ncm'      => (string) get_post_meta( $id, VH_Products_Service::META_NCM, true ),
				'gtin'     => (string) get_post_meta( $id, VH_Products_Service::META_GTIN, true ),
				'unidade'  => (string) get_post_meta( $id, VH_Products_Service::META_UNIDADE, true ),
				'marca'    => (string) get_post_meta( $id, VH_Products_Service::META_MARCA, true ),
				'marca_id' => $marca_id > 0 ? $marca_id : null,
			],
			static fn( $v ) => null !== $v && '' !== $v
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function categorias_wc( WC_Product $produto ): array {
		$categorias = [];
		foreach ( $produto->get_category_ids() as $term_id ) {
			if ( get_term_meta( (int) $term_id, self::META_TINY_CAT_IGNORAR, true ) ) {
				continue;
			}
			$termo = get_term( $term_id, 'product_cat' );
			if ( ! $termo instanceof WP_Term ) {
				continue;
			}
			$tiny_id      = (int) get_term_meta( $term_id, self::META_TINY_CAT_ID, true );
			$categorias[] = array_filter(
				[
					'id_tiny' => $tiny_id > 0 ? $tiny_id : null,
					'nome'    => $termo->name,
					'caminho' => VH_Categories_Service::caminho_hierarquico( $termo ),
					'term_id' => (int) $term_id,
				]
			);
		}
		return $categorias;
	}

	/**
	 * @return array<int, string>
	 */
	private static function imagens_wc( WC_Product $produto ): array {
		$imagens = [];
		$ids     = array_filter( array_merge( [ $produto->get_image_id() ], $produto->get_gallery_image_ids() ) );
		foreach ( $ids as $attachment_id ) {
			$url = self::url_imagem_para_tiny( (int) $attachment_id );
			if ( $url ) {
				$imagens[] = $url;
			}
		}
		return $imagens;
	}

	/**
	 * URL pública que o Tiny consegue importar (JPEG/PNG; WebP convertido sob demanda).
	 */
	public static function url_imagem_para_tiny( int $attachment_id ): ?string {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return null;
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		if ( 'image/webp' !== $mime ) {
			return esc_url_raw( $url );
		}

		$arquivo = get_attached_file( $attachment_id );
		if ( ! $arquivo || ! is_readable( $arquivo ) ) {
			return esc_url_raw( $url );
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return esc_url_raw( $url );
		}

		$dest_dir = trailingslashit( $upload_dir['basedir'] ) . 'vh-tiny-sync';
		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return esc_url_raw( $url );
		}

		$hash      = md5( $attachment_id . '|' . (string) filemtime( $arquivo ) );
		$dest_file = $dest_dir . '/img-' . $hash . '.jpg';
		$dest_url  = trailingslashit( $upload_dir['baseurl'] ) . 'vh-tiny-sync/img-' . $hash . '.jpg';

		if ( ! file_exists( $dest_file ) ) {
			$editor = wp_get_image_editor( $arquivo );
			if ( is_wp_error( $editor ) ) {
				return esc_url_raw( $url );
			}
			$editor->set_quality( 90 );
			$saved = $editor->save( $dest_file, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return esc_url_raw( $url );
			}
		}

		return esc_url_raw( $dest_url );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function grade_atributos_wc( WC_Product_Variable $produto ): array {
		$saida = [];
		foreach ( $produto->get_attributes() as $attr ) {
			if ( ! $attr instanceof WC_Product_Attribute || ! $attr->get_variation() ) {
				continue;
			}
			$taxonomy = $attr->get_name();
			$chave    = class_exists( 'VH_Tiny_Mapping_Service' )
				? VH_Tiny_Mapping_Service::rotulo_atributo( $taxonomy, wc_attribute_label( $taxonomy ) )
				: wc_attribute_label( $taxonomy );
			$opcoes   = [];
			foreach ( $attr->get_options() as $term_id ) {
				$termo = get_term( (int) $term_id, $taxonomy );
				if ( $termo instanceof WP_Term ) {
					$opcoes[] = [
						'slug' => $termo->slug,
						'nome' => $termo->name,
					];
				}
			}
			$saida[] = [
				'chave'    => $chave,
				'taxonomy' => $taxonomy,
				'opcoes'   => $opcoes,
			];
		}
		return $saida;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function variacoes_wc( WC_Product_Variable $produto ): array {
		$variacoes = [];
		foreach ( $produto->get_children() as $variacao_id ) {
			$var = wc_get_product( (int) $variacao_id );
			if ( ! $var instanceof WC_Product_Variation ) {
				continue;
			}

			$atributos = $var->get_attributes();
			$grade       = [];
			foreach ( $atributos as $taxonomy => $slug ) {
				if ( '' === (string) $slug ) {
					continue;
				}
				$termo = get_term_by( 'slug', (string) $slug, (string) $taxonomy );
				$chave = class_exists( 'VH_Tiny_Mapping_Service' )
					? VH_Tiny_Mapping_Service::rotulo_atributo( (string) $taxonomy, wc_attribute_label( (string) $taxonomy ) )
					: wc_attribute_label( (string) $taxonomy );
				$grade[] = [
					'chave'    => $chave,
					'taxonomy' => (string) $taxonomy,
					'valor'    => $termo instanceof WP_Term ? $termo->name : (string) $slug,
					'slug'     => (string) $slug,
				];
			}

			$sku = $var->get_sku();

			$variacoes[] = array_filter(
				[
					'variacao_id'      => (int) $variacao_id,
					'tiny_id'          => (int) get_post_meta( $variacao_id, self::META_TINY_ID, true ) ?: null,
					'sku'              => $sku,
					'grade'            => $grade,
					'preco_regular'    => $var->get_regular_price(),
					'preco_promo'      => $var->get_sale_price(),
					'gerencia_estoque' => $var->get_manage_stock(),
					'estoque'          => $var->get_manage_stock() ? $var->get_stock_quantity() : null,
				],
				static fn( $v ) => null !== $v && '' !== $v && [] !== $v
			);
		}
		return $variacoes;
	}

	/**
	 * Hash de conteúdo para decidir re-push (inclui variações).
	 */
	public static function hash_produto_wc( WC_Product $produto ): string {
		$canonico = self::wc_para_canonico( $produto );
		return self::hash_conteudo_loja(
			[
				'tipo'            => $canonico['tipo'] ?? 'simples',
				'nome'            => $canonico['nome'] ?? '',
				'descricao'       => $canonico['descricao'] ?? '',
				'descricao_curta' => $canonico['descricao_curta'] ?? '',
				'imagem_id'       => $produto->get_image_id(),
				'galeria_ids'     => $produto->get_gallery_image_ids(),
				'categorias'      => $canonico['categorias'] ?? [],
				'peso'             => $canonico['peso'] ?? '',
				'envio'            => $canonico['envio'] ?? [],
				'fiscal'           => $canonico['fiscal'] ?? [],
				'preco_regular'    => $canonico['preco_regular'] ?? ( $produto->is_type( 'simple' ) ? $produto->get_regular_price() : '' ),
				'preco_promo'      => $canonico['preco_promo'] ?? ( $produto->is_type( 'simple' ) ? $produto->get_sale_price() : '' ),
				'gerencia_estoque' => $canonico['gerencia_estoque'] ?? false,
				'estoque'          => $canonico['estoque'] ?? '',
				'variacoes'       => $canonico['variacoes'] ?? [],
				'grade_atributos' => $canonico['grade_atributos'] ?? [],
			]
		);
	}

	/**
	 * @deprecated Use wc_para_canonico().
	 * @return array<string, mixed>
	 */
	public static function wc_para_tiny_conteudo( WC_Product $produto ): array {
		return self::wc_para_canonico( $produto );
	}

	/**
	 * Campos financeiros/estoque que o Tiny manda na loja (formato canônico).
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	/**
	 * Dados para criar produto simples na loja a partir do formato canônico Tiny.
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>
	 */
	public static function canonico_para_wc_criar( array $canonico, int $tiny_id ): array {
		$sku = trim( (string) ( $canonico['sku'] ?? '' ) );
		if ( '' === $sku ) {
			$sku = 'TINY-' . $tiny_id;
		}

		$dados = [
			'nome'            => (string) ( $canonico['nome'] ?? __( 'Produto Tiny', 'vapor-hub-loja' ) ),
			'sku'             => $sku,
			'tipo'            => 'simple',
			'status'          => ! empty( $canonico['publicado'] ) ? 'publish' : 'draft',
			'descricao'       => (string) ( $canonico['descricao'] ?? '' ),
			'descricao_curta' => (string) ( $canonico['descricao_curta'] ?? '' ),
			'gerencia_estoque' => true,
		];

		if ( isset( $canonico['preco_regular'] ) && '' !== (string) $canonico['preco_regular'] ) {
			$dados['preco_regular'] = wc_format_decimal( (string) $canonico['preco_regular'] );
		}
		if ( isset( $canonico['preco_promo'] ) && '' !== (string) $canonico['preco_promo'] ) {
			$dados['preco_promo'] = wc_format_decimal( (string) $canonico['preco_promo'] );
		}
		if ( isset( $canonico['estoque'] ) && is_numeric( $canonico['estoque'] ) ) {
			$dados['estoque'] = (int) $canonico['estoque'];
		}
		if ( isset( $canonico['peso'] ) && (float) $canonico['peso'] > 0 ) {
			$dados['peso'] = wc_format_decimal( (string) $canonico['peso'] );
		}

		$categorias = self::categorias_tiny_para_wc_ids( $canonico );
		if ( $categorias ) {
			$dados['categorias'] = $categorias;
		}

		return $dados;
	}

	/**
	 * Resolve IDs de categorias WC já vinculadas ao Tiny.
	 *
	 * @param array<string, mixed> $canonico
	 * @return int[]
	 */
	public static function categorias_tiny_para_wc_ids( array $canonico ): array {
		$ids = [];
		foreach ( (array) ( $canonico['categorias'] ?? [] ) as $cat ) {
			if ( ! is_array( $cat ) ) {
				continue;
			}
			$tiny_id = (int) ( $cat['id_tiny'] ?? $cat['id'] ?? 0 );
			if ( $tiny_id <= 0 ) {
				continue;
			}
			$termos = get_terms(
				[
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'number'     => 1,
					'meta_query' => [
						[
							'key'   => self::META_TINY_CAT_ID,
							'value' => $tiny_id,
						],
					],
				]
			);
			if ( is_array( $termos ) && isset( $termos[0] ) && $termos[0] instanceof WP_Term ) {
				$ids[] = (int) $termos[0]->term_id;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Categoria(s) do payload Tiny em formato canônico (id_tiny).
	 *
	 * @param array<string, mixed> $raw
	 * @return array<int, array{id_tiny:int}>
	 */
	public static function categorias_de_resposta_tiny( array $raw ): array {
		$ids = [];
		if ( isset( $raw['categoria'] ) && is_array( $raw['categoria'] ) ) {
			$ids[] = (int) ( $raw['categoria']['id'] ?? $raw['categoria']['idCategoria'] ?? 0 );
		}
		$ids[] = (int) ( $raw['idCategoria'] ?? $raw['id_categoria'] ?? 0 );
		$saida = [];
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$saida[] = [ 'id_tiny' => (int) $id ];
		}
		return $saida;
	}

	public static function tiny_para_wc_financeiro( array $canonico, WC_Product $produto ): array {
		unset( $produto );
		$dados = [];

		if ( isset( $canonico['preco_regular'] ) && '' !== (string) $canonico['preco_regular'] ) {
			$dados['preco_regular'] = wc_format_decimal( (string) $canonico['preco_regular'] );
		}

		if ( isset( $canonico['preco_promo'] ) && '' !== (string) $canonico['preco_promo'] ) {
			$dados['preco_promo'] = wc_format_decimal( (string) $canonico['preco_promo'] );
		}

		if ( array_key_exists( 'gerencia_estoque', $canonico ) ) {
			$dados['gerencia_estoque'] = ! empty( $canonico['gerencia_estoque'] );
		}

		if ( isset( $canonico['estoque'] ) && is_numeric( $canonico['estoque'] ) ) {
			$dados['gerencia_estoque'] = $dados['gerencia_estoque'] ?? true;
			$dados['estoque']          = (int) $canonico['estoque'];
		}

		return $dados;
	}

	/**
	 * @param array<string, mixed> $estoque
	 * @return array<string, mixed>
	 */
	public static function tiny_estoque_para_wc( array $estoque ): array {
		$gerencia = ! empty( $estoque['gerencia_estoque'] );
		$qtd      = self::extrair_estoque_de_array( $estoque );

		if ( ! $gerencia && null === $qtd ) {
			return [ 'gerencia_estoque' => false ];
		}

		$saida = [ 'gerencia_estoque' => $gerencia ];
		if ( null !== $qtd ) {
			$saida['estoque'] = $qtd;
		}
		return $saida;
	}

	/**
	 * Pedido WC em formato canônico.
	 *
	 * @return array<string, mixed>
	 */
	public static function pedido_para_canonico( WC_Order $pedido ): array {
		$itens = [];
		foreach ( $pedido->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$produto = $item->get_product();
			$sku     = $produto ? $produto->get_sku() : '';
			$tiny_id = 0;
			if ( $produto ) {
				$tiny_id = (int) get_post_meta( $produto->get_id(), self::META_TINY_ID, true );
				if ( $tiny_id <= 0 && $produto->is_type( 'variation' ) ) {
					$tiny_id = (int) get_post_meta( $produto->get_id(), self::META_TINY_ID, true );
				}
			}
			$itens[] = array_filter(
				[
					'id_tiny'        => $tiny_id > 0 ? $tiny_id : null,
					'sku'            => $sku ?: null,
					'nome'           => $item->get_name(),
					'quantidade'     => (float) $item->get_quantity(),
					'valor_unitario' => (float) $item->get_total() / max( 1, $item->get_quantity() ),
				]
			);
		}

		$origem   = class_exists( 'VH_Tiny' ) ? VH_Tiny::loja_identificador() : '';
		$para_ele = self::entrega_pedido( $pedido );
		$frete    = (float) $pedido->get_shipping_total();
		$desconto = (float) $pedido->get_discount_total();

		$canonico = [
			'numero'       => (string) $pedido->get_order_number(),
			'origem_loja'  => $origem,
			'data'         => $pedido->get_date_created() ? $pedido->get_date_created()->date( 'Y-m-d' ) : gmdate( 'Y-m-d' ),
			'cliente'      => [
				'nome'     => trim( $pedido->get_billing_first_name() . ' ' . $pedido->get_billing_last_name() ),
				'email'    => $pedido->get_billing_email(),
				'telefone' => $pedido->get_billing_phone(),
				'cpf_cnpj' => (string) $pedido->get_meta( '_billing_cpf' ),
				'id_tiny'  => (int) $pedido->get_meta( self::META_TINY_CONTATO ),
			],
			'entrega'      => $para_ele,
			'itens'        => $itens,
			'total'        => (float) $pedido->get_total(),
			'pagamento'    => [
				'metodo' => (string) $pedido->get_payment_method_title(),
				'codigo' => (string) $pedido->get_payment_method(),
			],
			'observacoes'  => sprintf(
				/* translators: %s: order ID */
				__( 'Pedido WooCommerce #%s', 'vapor-hub-loja' ),
				$pedido->get_order_number()
			),
		];

		if ( $frete > 0 ) {
			$canonico['frete'] = [
				'valor'   => $frete,
				'servico' => (string) $pedido->get_shipping_method(),
			];
		}
		if ( $desconto > 0 ) {
			$canonico['desconto'] = $desconto;
		}

		return $canonico;
	}

	/**
	 * Endereço de entrega com bairro e número em campos próprios.
	 *
	 * O Tiny guarda logradouro e número separados; o WooCommerce, junto. Quando
	 * há plugin brasileiro (metas `_shipping_number`/`_billing_number`), usa o
	 * campo dele. Sem isso, tenta o número no fim do logradouro.
	 *
	 * @return array<string, string>
	 */
	private static function entrega_pedido( WC_Order $pedido ): array {
		$entrega  = (bool) $pedido->get_shipping_address_1();
		$endereco = $entrega ? $pedido->get_shipping_address_1() : $pedido->get_billing_address_1();

		$numero = trim( (string) $pedido->get_meta( $entrega ? '_shipping_number' : '_billing_number' ) );
		if ( '' === $numero ) {
			$numero = trim( (string) $pedido->get_meta( $entrega ? '_billing_number' : '_shipping_number' ) );
		}
		if ( '' === $numero ) {
			[ $endereco, $numero ] = self::separar_numero( (string) $endereco );
		}

		$bairro = trim( (string) $pedido->get_meta( $entrega ? '_shipping_neighborhood' : '_billing_neighborhood' ) );
		if ( '' === $bairro ) {
			$bairro = trim( (string) $pedido->get_meta( $entrega ? '_billing_neighborhood' : '_shipping_neighborhood' ) );
		}

		$nome = trim( $pedido->get_shipping_first_name() . ' ' . $pedido->get_shipping_last_name() );

		return [
			'nome'        => $nome ?: trim( $pedido->get_billing_first_name() . ' ' . $pedido->get_billing_last_name() ),
			'endereco'    => trim( (string) $endereco ),
			'numero'      => $numero,
			'bairro'      => $bairro,
			'complemento' => (string) ( $pedido->get_shipping_address_2() ?: $pedido->get_billing_address_2() ),
			'cidade'      => (string) ( $pedido->get_shipping_city() ?: $pedido->get_billing_city() ),
			'uf'          => (string) ( $pedido->get_shipping_state() ?: $pedido->get_billing_state() ),
			'cep'         => (string) ( $pedido->get_shipping_postcode() ?: $pedido->get_billing_postcode() ),
			'telefone'    => (string) $pedido->get_billing_phone(),
		];
	}

	/**
	 * “Rua das Palmeiras, 123” → logradouro e número. Sem número reconhecível, o
	 * logradouro volta intacto.
	 *
	 * @return array{0:string, 1:string}
	 */
	private static function separar_numero( string $endereco ): array {
		$endereco = trim( $endereco );

		if ( preg_match( '/^(.+?)[,\s]+(\d+[A-Za-z]?)$/u', $endereco, $m ) ) {
			return [ trim( rtrim( $m[1], ' ,-' ) ), $m[2] ];
		}

		return [ $endereco, '' ];
	}

	/**
	 * @deprecated Use pedido_para_canonico().
	 * @return array<string, mixed>
	 */
	public static function pedido_para_tiny( WC_Order $pedido ): array {
		return self::pedido_para_canonico( $pedido );
	}

	/**
	 * @param array<string, mixed> $tiny
	 */
	public static function extrair_tiny_id( array $tiny ): int {
		foreach ( [ 'id', 'idProduto', 'id_produto', 'idVariacao', 'id_variacao', 'idCategoria', 'id_categoria' ] as $chave ) {
			if ( ! empty( $tiny[ $chave ] ) ) {
				return absint( $tiny[ $chave ] );
			}
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $tiny
	 */
	public static function extrair_sku( array $tiny ): string {
		foreach ( [ 'codigo', 'sku', 'codigoProduto' ] as $chave ) {
			if ( ! empty( $tiny[ $chave ] ) ) {
				return wc_clean( (string) $tiny[ $chave ] );
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public static function hash_conteudo_loja( array $dados ): string {
		$variacoes_sig = '';
		if ( ! empty( $dados['variacoes'] ) && is_array( $dados['variacoes'] ) ) {
			$partes = [];
			foreach ( $dados['variacoes'] as $var ) {
				if ( ! is_array( $var ) ) {
					continue;
				}
				$partes[] = implode(
					':',
					[
						(string) ( $var['sku'] ?? '' ),
						(string) ( $var['preco_regular'] ?? '' ),
						(string) ( $var['preco_promo'] ?? '' ),
						(string) ( $var['estoque'] ?? '' ),
						wp_json_encode( $var['grade'] ?? [] ),
					]
				);
			}
			sort( $partes );
			$variacoes_sig = implode( '|', $partes );
		}

		$relevante = [
			'tipo'             => (string) ( $dados['tipo'] ?? 'simples' ),
			'nome'             => (string) ( $dados['nome'] ?? '' ),
			'descricao'        => (string) ( $dados['descricao'] ?? '' ),
			'descricao_curta'  => (string) ( $dados['descricao_curta'] ?? '' ),
			'imagem_id'        => (string) ( $dados['imagem_id'] ?? '' ),
			'galeria_ids'      => is_array( $dados['galeria_ids'] ?? null ) ? implode( ',', $dados['galeria_ids'] ) : (string) ( $dados['galeria_ids'] ?? '' ),
			'categorias'       => isset( $dados['categorias'] ) ? wp_json_encode( $dados['categorias'] ) : '',
			'peso'             => (string) ( $dados['peso'] ?? '' ),
			/* Medida nova tem de gerar novo envio: o frete e a expedição dependem dela. */
			'envio'            => isset( $dados['envio'] ) ? wp_json_encode( $dados['envio'] ) : '',
			/* NCM/GTIN/unidade tardios são o caso comum; sem isso o ERP nunca recebe. */
			'fiscal'           => isset( $dados['fiscal'] ) ? wp_json_encode( $dados['fiscal'] ) : '',
			'preco_regular'    => (string) ( $dados['preco_regular'] ?? '' ),
			'preco_promo'      => (string) ( $dados['preco_promo'] ?? '' ),
			'gerencia_estoque' => ! empty( $dados['gerencia_estoque'] ) ? '1' : '0',
			'estoque'          => isset( $dados['estoque'] ) && '' !== (string) $dados['estoque'] ? (string) $dados['estoque'] : '',
			'variacoes_sig'    => $variacoes_sig,
			'grade'            => isset( $dados['grade_atributos'] ) ? wp_json_encode( $dados['grade_atributos'] ) : '',
		];
		return md5( wp_json_encode( $relevante ) );
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public static function hash_financeiro_tiny( array $dados ): string {
		$relevante = [
			'preco_regular' => (string) ( $dados['preco_regular'] ?? '' ),
			'preco_promo'   => (string) ( $dados['preco_promo'] ?? '' ),
			'estoque'       => (string) ( $dados['estoque'] ?? '' ),
		];
		return md5( wp_json_encode( $relevante ) );
	}

	/**
	 * @param array<string, mixed> $fonte
	 */
	private static function extrair_estoque_de_array( array $fonte ): ?int {
		if ( isset( $fonte['estoque'] ) && is_numeric( $fonte['estoque'] ) ) {
			return (int) $fonte['estoque'];
		}
		foreach ( [ 'saldo', 'quantidade', 'saldoFisico' ] as $chave ) {
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
}

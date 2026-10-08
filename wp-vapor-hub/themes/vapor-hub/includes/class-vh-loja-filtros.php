<?php
/**
 * Filtros da loja — preço, destaques, estoque, AJAX e URLs compartilháveis.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filtros GET da vitrine WooCommerce (somente leitura / consulta).
 */
final class VH_Loja_Filtros {

	/** Parâmetro GET — produtos exclusivos da marca. */
	public const PARAM_EXCLUSIVOS = 'vh_exclusivos';

	/** Parâmetro GET — produtos em promoção (onsale). */
	public const PARAM_PROMOCAO = 'vh_promocao';

	/** Parâmetro GET — lançamentos recentes. */
	public const PARAM_NOVIDADES = 'vh_novidades';

	/** Parâmetro GET — apenas em estoque. */
	public const PARAM_ESTOQUE = 'vh_estoque';

	/** Parâmetro GET — produtos variáveis (com opções). */
	public const PARAM_VARIAVEL = 'vh_variavel';

	/** Dias para considerar produto como novidade (alinhado ao card da vitrine). */
	private const DIAS_NOVO = 30;

	/** Namespace REST público do tema. */
	public const REST_NS = 'vh-tema/v1';

	/** Teto padrão do slider quando o catálogo ainda não tem preços indexados. */
	private const PRECO_TETO_PADRAO = 300;

	/** Requisições AJAX por IP por minuto. */
	private const RATE_LIMIT = 60;

	/** Cache de resposta AJAX (segundos). */
	private const CACHE_RESPOSTA = 120;

	/** Estado usado dentro do filtro posts_clauses. */
	private static $clausula_estado = null;

	/**
	 * Registra hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_product_query', array( __CLASS__, 'aplicar_query' ), 20 );
		add_action( 'save_post_product', array( __CLASS__, 'limpar_cache_ids' ) );
		add_action( 'deleted_post', array( __CLASS__, 'limpar_cache_ids' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'limpar_cache_ids_termos' ), 10, 4 );
		add_action( 'rest_api_init', array( __CLASS__, 'registrar_rest' ) );
	}

	/**
	 * Endpoint REST para atualização dinâmica da grade.
	 */
	public static function registrar_rest(): void {
		register_rest_route(
			self::REST_NS,
			'/loja/produtos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_produtos' ),
				'permission_callback' => '__return_true',
				'args'                => self::schema_rest(),
			)
		);
	}

	/**
	 * Schema sanitizado dos parâmetros REST.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function schema_rest(): array {
		return array(
			self::PARAM_EXCLUSIVOS => self::schema_param_flag(),
			self::PARAM_PROMOCAO   => self::schema_param_flag(),
			self::PARAM_NOVIDADES  => self::schema_param_flag(),
			self::PARAM_ESTOQUE    => self::schema_param_flag(),
			self::PARAM_VARIAVEL   => self::schema_param_flag(),
			'min_price'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'max_price'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby'              => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'paged'                => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			),
			'categoria'            => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
		);
	}

	/**
	 * Resposta REST com HTML parcial da vitrine.
	 *
	 * @param WP_REST_Request $request Requisição.
	 */
	public static function rest_produtos( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::rate_limit_ok() ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Muitas requisições. Aguarde um momento.', 'vapor-hub' ) ),
				429
			);
		}

		$params = array(
			self::PARAM_EXCLUSIVOS => $request->get_param( self::PARAM_EXCLUSIVOS ),
			self::PARAM_PROMOCAO   => $request->get_param( self::PARAM_PROMOCAO ),
			self::PARAM_NOVIDADES  => $request->get_param( self::PARAM_NOVIDADES ),
			self::PARAM_ESTOQUE    => $request->get_param( self::PARAM_ESTOQUE ),
			self::PARAM_VARIAVEL   => $request->get_param( self::PARAM_VARIAVEL ),
			'min_price'            => $request->get_param( 'min_price' ),
			'max_price'            => $request->get_param( 'max_price' ),
			'orderby'              => $request->get_param( 'orderby' ),
			'paged'                => $request->get_param( 'paged' ),
			'categoria'            => $request->get_param( 'categoria' ),
		);

		$cache_key = 'vh_loja_lista_' . md5( wp_json_encode( $params ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$resultado = self::consultar_produtos( $params );
		$payload   = array(
			'html'       => $resultado['html'],
			'chips_html' => $resultado['chips_html'],
			'found'      => (int) $resultado['found'],
			'url'        => $resultado['url'],
		);

		set_transient( $cache_key, $payload, self::CACHE_RESPOSTA );

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Estado atual dos filtros (sanitizado).
	 *
	 * @param array<string, mixed>|null $params Parâmetros explícitos (REST/AJAX).
	 * @param int|null                  $term_id ID da categoria para teto de preço.
	 * @return array{
	 *   exclusivos: bool,
	 *   promocao: bool,
	 *   novidades: bool,
	 *   estoque: bool,
	 *   variavel: bool,
	 *   preco_min: float,
	 *   preco_max: float,
	 *   preco_teto: int,
	 *   tem_filtros: bool,
	 *   orderby: string,
	 *   categoria: string,
	 *   paged: int
	 * }
	 */
	public static function estado( ?array $params = null, ?int $term_id = null ): array {
		if ( null === $params ) {
			static $estado_pagina = null;
			if ( null !== $estado_pagina ) {
				return $estado_pagina;
			}

			if ( is_product_category() ) {
				$termo = get_queried_object();
				if ( $termo instanceof WP_Term ) {
					$term_id = (int) $termo->term_id;
				}
			}

			$params        = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$estado_pagina = self::estado_de_params( $params, $term_id );
			return $estado_pagina;
		}

		if ( null === $term_id && ! empty( $params['categoria'] ) ) {
			$term_id = self::term_id_por_slug( (string) $params['categoria'] );
		}

		return self::estado_de_params( $params, $term_id );
	}

	/**
	 * Monta estado a partir de parâmetros brutos.
	 *
	 * @param array<string, mixed> $params  Parâmetros.
	 * @param int|null             $term_id Categoria (teto de preço).
	 */
	private static function estado_de_params( array $params, ?int $term_id = null ): array {
		$preco_teto = self::preco_teto( $term_id );
		$exclusivos = self::param_flag( $params, self::PARAM_EXCLUSIVOS );
		$promocao   = self::param_flag( $params, self::PARAM_PROMOCAO );
		$novidades  = self::param_flag( $params, self::PARAM_NOVIDADES );
		$estoque    = self::param_flag( $params, self::PARAM_ESTOQUE );
		$variavel   = self::param_flag( $params, self::PARAM_VARIAVEL );

		$preco_min = self::preco_de_param( $params, 'min_price', $preco_teto, 0.0 );
		$preco_max = self::preco_de_param( $params, 'max_price', $preco_teto, (float) $preco_teto );

		if ( $preco_max > 0 && $preco_max < $preco_teto && $preco_min > $preco_max ) {
			$preco_min = $preco_max;
		}

		$tem_preco = ( $preco_min > 0 ) || ( $preco_max > 0 && $preco_max < $preco_teto );

		$orderby = '';
		if ( ! empty( $params['orderby'] ) ) {
			$orderby = sanitize_text_field( (string) $params['orderby'] );
			if ( ! self::orderby_permitido( $orderby ) ) {
				$orderby = '';
			}
		}

		$categoria = ! empty( $params['categoria'] ) ? sanitize_title( (string) $params['categoria'] ) : '';
		if ( '' === $categoria && is_product_category() ) {
			$termo = get_queried_object();
			if ( $termo instanceof WP_Term ) {
				$categoria = $termo->slug;
			}
		}

		$paged = isset( $params['paged'] ) ? max( 1, min( 100, absint( $params['paged'] ) ) ) : 1;

		return array(
			'exclusivos'  => $exclusivos,
			'promocao'    => $promocao,
			'novidades'   => $novidades,
			'estoque'     => $estoque,
			'variavel'    => $variavel,
			'preco_min'   => $preco_min,
			'preco_max'   => $preco_max,
			'preco_teto'  => $preco_teto,
			'tem_filtros' => $exclusivos || $promocao || $novidades || $estoque || $variavel || $tem_preco,
			'orderby'     => $orderby,
			'categoria'   => $categoria,
			'paged'       => $paged,
		);
	}

	/**
	 * Consulta produtos e renderiza HTML (REST e testes).
	 *
	 * @param array<string, mixed> $params Parâmetros.
	 * @return array{html: string, chips_html: string, found: int, url: string}
	 */
	public static function consultar_produtos( array $params ): array {
		$estado  = self::estado( $params );
		$por_pag = self::produtos_por_pagina();

		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $por_pag,
			'paged'               => max( 1, (int) $estado['paged'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'fields'              => 'ids',
		);

		/* Visibilidade de catálogo — esconde produtos "exclude-from-catalog". */
		$tax_query = array( 'relation' => 'AND' );
		$visiveis  = function_exists( 'wc_get_product_visibility_term_ids' ) ? wc_get_product_visibility_term_ids() : array();

		if ( ! empty( $visiveis['exclude-from-catalog'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( (int) $visiveis['exclude-from-catalog'] ),
				'operator' => 'NOT IN',
			);
		}

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! empty( $visiveis['outofstock'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( (int) $visiveis['outofstock'] ),
				'operator' => 'NOT IN',
			);
		}

		if ( ! empty( $estado['categoria'] ) ) {
			$term = get_term_by( 'slug', $estado['categoria'], 'product_cat' );
			if ( $term instanceof WP_Term ) {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( $term->slug ),
				);
			}
		}

		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		if ( $estado['variavel'] ) {
			$args['tax_query']   = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array( 'relation' => 'AND' );
			$args['tax_query'][] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => array( 'variable' ),
			);
		}

		if ( $estado['novidades'] ) {
			$args['date_query'] = array(
				array(
					'after'     => gmdate( 'Y-m-d H:i:s', time() - ( self::DIAS_NOVO * DAY_IN_SECONDS ) ),
					'inclusive' => true,
					'column'    => 'post_date_gmt',
				),
			);
		}

		if ( $estado['exclusivos'] ) {
			$ids              = self::ids_produtos_exclusivos();
			$args['post__in'] = ! empty( $ids ) ? $ids : array( 0 );
		}

		if ( self::estado_usa_lookup( $estado ) ) {
			self::$clausula_estado = $estado;
			add_filter( 'posts_clauses', array( __CLASS__, 'filtrar_clausulas' ), 10, 2 );
		}

		$args['vh_loja_filtros_query'] = true;

		$query = new WP_Query( $args );

		remove_filter( 'posts_clauses', array( __CLASS__, 'filtrar_clausulas' ), 10 );
		self::$clausula_estado = null;

		$produtos = array();
		foreach ( $query->posts as $produto_id ) {
			$produto = wc_get_product( (int) $produto_id );
			if ( $produto instanceof WC_Product ) {
				$produtos[] = $produto;
			}
		}

		$total        = (int) $query->found_posts;
		$max_paginas  = (int) $query->max_num_pages;
		$pagina_atual = $estado['paged'];

		ob_start();
		get_template_part(
			'template-parts/loja/loop-produtos',
			null,
			array(
				'produtos'        => $produtos,
				'colunas'         => 3,
				'max_paginas'     => $max_paginas,
				'pagina_atual'    => $pagina_atual,
				'paginacao_base'  => self::url_com_estado( array_merge( $estado, array( 'paged' => 1 ) ) ),
			)
		);
		$html = (string) ob_get_clean();

		ob_start();
		get_template_part(
			'template-parts/loja/chips-filtros',
			null,
			array( 'filtros' => $estado )
		);
		$chips_html = trim( (string) ob_get_clean() );

		return array(
			'html'       => $html,
			'chips_html' => $chips_html,
			'found'      => $total,
			'url'        => self::url_com_estado( $estado ),
		);
	}

	/**
	 * Renderiza chips para a página inicial.
	 */
	public static function renderizar_chips(): void {
		get_template_part(
			'template-parts/loja/chips-filtros',
			null,
			array( 'filtros' => self::estado() )
		);
	}

	/**
	 * URL base do contexto (loja ou taxonomia atual).
	 */
	public static function url_base(): string {
		if ( is_product_category() || is_product_tag() ) {
			$termo = get_queried_object();
			if ( $termo instanceof WP_Term ) {
				$link = get_term_link( $termo );
				if ( ! is_wp_error( $link ) ) {
					return $link;
				}
			}
		}

		return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	/**
	 * Monta URL preservando filtros ativos (e overrides opcionais).
	 *
	 * @param string               $url       URL de destino.
	 * @param array<string, mixed> $overrides Chaves: exclusivos, preco_min, preco_max, limpar.
	 */
	public static function url_com_filtros( string $url, array $overrides = array() ): string {
		if ( ! empty( $overrides['limpar'] ) ) {
			return remove_query_arg( self::params_url(), $url );
		}

		$estado = self::estado();

		$exclusivos = array_key_exists( 'exclusivos', $overrides )
			? (bool) $overrides['exclusivos']
			: $estado['exclusivos'];

		$preco_min = array_key_exists( 'preco_min', $overrides )
			? (float) $overrides['preco_min']
			: $estado['preco_min'];

		$preco_max = array_key_exists( 'preco_max', $overrides )
			? (float) $overrides['preco_max']
			: $estado['preco_max'];

		return self::url_com_estado(
			array_merge(
				$estado,
				array(
					'exclusivos' => $exclusivos,
					'preco_min'  => $preco_min,
					'preco_max'  => $preco_max,
				)
			),
			$url
		);
	}

	/**
	 * URL com estado de filtros aplicado.
	 *
	 * @param array<string, mixed> $estado Estado.
	 * @param string|null          $base   URL base opcional.
	 */
	public static function url_com_estado( array $estado, ?string $base = null ): string {
		$url = $base ?? self::url_do_contexto( $estado );

		$args = array();

		if ( ! empty( $estado['exclusivos'] ) ) {
			$args[ self::PARAM_EXCLUSIVOS ] = '1';
		}

		if ( ! empty( $estado['promocao'] ) ) {
			$args[ self::PARAM_PROMOCAO ] = '1';
		}

		if ( ! empty( $estado['novidades'] ) ) {
			$args[ self::PARAM_NOVIDADES ] = '1';
		}

		if ( ! empty( $estado['estoque'] ) ) {
			$args[ self::PARAM_ESTOQUE ] = '1';
		}

		if ( ! empty( $estado['variavel'] ) ) {
			$args[ self::PARAM_VARIAVEL ] = '1';
		}

		if ( ! empty( $estado['preco_min'] ) && $estado['preco_min'] > 0 ) {
			$args['min_price'] = self::formatar_preco_url( (float) $estado['preco_min'] );
		}

		if (
			! empty( $estado['preco_max'] )
			&& $estado['preco_max'] > 0
			&& $estado['preco_max'] < (int) $estado['preco_teto']
		) {
			$args['max_price'] = self::formatar_preco_url( (float) $estado['preco_max'] );
		}

		if ( ! empty( $estado['orderby'] ) && 'menu_order' !== $estado['orderby'] ) {
			$args['orderby'] = $estado['orderby'];
		}

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		$pagina = ! empty( $estado['paged'] ) ? (int) $estado['paged'] : 1;

		return self::url_na_pagina( $url, $pagina );
	}

	/**
	 * Endereço da categoria atual, no caminho público, ou da loja quando não há categoria.
	 *
	 * @param array<string, mixed> $estado Estado.
	 */
	private static function url_do_contexto( array $estado ): string {
		if ( ! empty( $estado['categoria'] ) ) {
			$termo = get_term_by( 'slug', sanitize_title( (string) $estado['categoria'] ), 'product_cat' );
			if ( $termo instanceof WP_Term ) {
				$link = get_term_link( $termo );
				if ( ! is_wp_error( $link ) && is_string( $link ) && '' !== $link ) {
					return $link;
				}
			}
		}

		return self::url_base();
	}

	/**
	 * Base do paginate_links com o mesmo /page/N/ da vitrine.
	 */
	public static function base_paginacao( string $url ): string {
		$limpa  = self::url_na_pagina( $url, 1 );
		$partes = wp_parse_url( $limpa );
		if ( ! is_array( $partes ) ) {
			return $limpa;
		}

		$path = trailingslashit( (string) ( $partes['path'] ?? '/' ) ) . 'page/%#%/';

		return self::remontar_url( $partes, $path );
	}

	/**
	 * Tira da paginação parâmetros internos que o pedido AJAX carrega.
	 */
	public static function limpar_link_paginacao( string $link ): string {
		$link  = remove_query_arg( array( 'categoria', 'paged' ), $link );
		$query = wp_parse_url( $link, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			$args = array();
			parse_str( $query, $args );
			if ( isset( $args['orderby'] ) && 'menu_order' === $args['orderby'] ) {
				$link = remove_query_arg( 'orderby', $link );
			}
		}

		return $link;
	}

	/**
	 * Coloca a página no caminho (/page/2/) e tira ?paged=, que o WordPress devolve para o caminho.
	 */
	private static function url_na_pagina( string $url, int $pagina ): string {
		$url    = remove_query_arg( 'paged', $url );
		$partes = wp_parse_url( $url );
		if ( ! is_array( $partes ) ) {
			return $url;
		}

		$path = (string) ( $partes['path'] ?? '/' );
		$path = (string) preg_replace( '#/page/[0-9]+/?$#', '/', $path );
		if ( $pagina > 1 ) {
			$path = trailingslashit( $path ) . 'page/' . $pagina . '/';
		}

		return self::remontar_url( $partes, $path );
	}

	/**
	 * @param array<string, mixed> $partes
	 */
	private static function remontar_url( array $partes, string $path ): string {
		$url = '';
		if ( ! empty( $partes['scheme'] ) && ! empty( $partes['host'] ) ) {
			$url .= $partes['scheme'] . '://' . $partes['host'];
			if ( ! empty( $partes['port'] ) ) {
				$url .= ':' . $partes['port'];
			}
		}
		$url .= '' !== $path ? $path : '/';
		if ( ! empty( $partes['query'] ) ) {
			$url .= '?' . $partes['query'];
		}
		if ( ! empty( $partes['fragment'] ) ) {
			$url .= '#' . $partes['fragment'];
		}

		return $url;
	}

	/**
	 * Teto de preço do catálogo (contexto da categoria).
	 *
	 * @param int|null $term_id ID da categoria.
	 */
	public static function preco_teto( ?int $term_id = null ): int {
		global $wpdb;

		if ( ! isset( $wpdb->wc_product_meta_lookup ) ) {
			return self::PRECO_TETO_PADRAO;
		}

		if ( null === $term_id && is_product_category() ) {
			$termo = get_queried_object();
			if ( $termo instanceof WP_Term ) {
				$term_id = (int) $termo->term_id;
			}
		}

		$lookup = $wpdb->wc_product_meta_lookup;

		if ( $term_id ) {
			$sql = $wpdb->prepare(
				"SELECT MAX(lookup.max_price)
				FROM {$lookup} lookup
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = lookup.product_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					AND tt.taxonomy = 'product_cat'
					AND tt.term_id = %d
				WHERE lookup.max_price > 0",
				$term_id
			);
		} else {
			$sql = "SELECT MAX(max_price) FROM {$lookup} WHERE max_price > 0";
		}

		$max = (float) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $max <= 0 ) {
			return self::PRECO_TETO_PADRAO;
		}

		return max( 50, (int) ceil( $max ) );
	}

	/**
	 * Aplica filtros exclusivos na query principal da loja.
	 *
	 * @param WP_Query $query Query WooCommerce.
	 */
	public static function aplicar_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$taxonomias_produto = get_object_taxonomies( 'product' );
		$eh_vitrine         = $query->is_post_type_archive( 'product' )
			|| ( ! empty( $taxonomias_produto ) && $query->is_tax( $taxonomias_produto ) );

		if ( ! $eh_vitrine ) {
			return;
		}

		self::aplicar_estado_na_query( $query, self::estado() );
	}

	/**
	 * Aplica estado de filtros em uma WP_Query (loja principal ou REST).
	 *
	 * @param WP_Query              $query  Query alvo.
	 * @param array<string, mixed>  $estado Estado sanitizado.
	 */
	private static function aplicar_estado_na_query( WP_Query $query, array $estado ): void {
		if ( $estado['exclusivos'] ) {
			$ids = self::ids_produtos_exclusivos();
			$query->set( 'post__in', ! empty( $ids ) ? $ids : array( 0 ) );
		}

		if ( $estado['novidades'] ) {
			$query->set(
				'date_query',
				array(
					array(
						'after'     => gmdate( 'Y-m-d H:i:s', time() - ( self::DIAS_NOVO * DAY_IN_SECONDS ) ),
						'inclusive' => true,
						'column'    => 'post_date_gmt',
					),
				)
			);
		}

		if ( $estado['variavel'] ) {
			$tax_query   = (array) $query->get( 'tax_query' );
			$tax_query[] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => array( 'variable' ),
			);
			$query->set( 'tax_query', $tax_query );
		}

		if ( self::estado_usa_lookup( $estado ) ) {
			self::$clausula_estado = $estado;
			add_filter( 'posts_clauses', array( __CLASS__, 'filtrar_clausulas' ), 10, 2 );
		}
	}

	/**
	 * Invalida cache de IDs exclusivos.
	 *
	 * @param int $post_id ID do post.
	 */
	public static function limpar_cache_ids( int $post_id ): void {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		delete_transient( 'vh_loja_ids_exclusivos' );
	}

	/**
	 * Invalida cache quando termos de produto mudam.
	 *
	 * @param int    $object_id ID do objeto.
	 * @param array  $terms     Termos.
	 * @param array  $tt_ids    IDs de taxonomia.
	 * @param string $taxonomy  Taxonomia.
	 */
	public static function limpar_cache_ids_termos( $object_id, $terms, $tt_ids, $taxonomy ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( 'product' !== get_post_type( (int) $object_id ) ) {
			return;
		}
		if ( in_array( $taxonomy, array( 'product_brand', 'product_cat' ), true ) ) {
			delete_transient( 'vh_loja_ids_exclusivos' );
		}
	}

	/**
	 * Produtos por página na vitrine.
	 */
	public static function produtos_por_pagina(): int {
		$rows = max( 1, (int) get_option( 'woocommerce_catalog_rows', 4 ) );
		$cols = max( 1, (int) get_option( 'woocommerce_catalog_columns', 4 ) );

		return (int) apply_filters( 'vh_loja_produtos_por_pagina', max( 12, $rows * $cols ) );
	}

	/**
	 * IDs publicados marcados como exclusivos (meta ou marca) — consulta única + cache.
	 *
	 * @return int[]
	 */
	private static function ids_produtos_exclusivos(): array {
		$cache = get_transient( 'vh_loja_ids_exclusivos' );
		if ( is_array( $cache ) ) {
			return array_map( 'absint', $cache );
		}

		global $wpdb;

		$ids = array();

		$sql_meta = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value = %s",
			'_vh_exclusivo',
			'sim'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ids = array_merge( $ids, array_map( 'absint', (array) $wpdb->get_col( $sql_meta ) ) );

		if ( taxonomy_exists( 'product_brand' ) ) {
			$marca = get_term_by( 'name', 'Vapor Hub', 'product_brand' );
			if ( ! $marca ) {
				$marca = get_term_by( 'slug', 'vapor-hub', 'product_brand' );
			}

			if ( $marca instanceof WP_Term ) {
				$sql_marca = $wpdb->prepare(
					"SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE p.post_type = 'product'
						AND p.post_status = 'publish'
						AND tt.taxonomy = %s
						AND tt.term_id = %d",
					'product_brand',
					(int) $marca->term_id
				);

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$ids = array_merge( $ids, array_map( 'absint', (array) $wpdb->get_col( $sql_marca ) ) );
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		set_transient( 'vh_loja_ids_exclusivos', $ids, HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Rate limit por IP para o endpoint REST.
	 */
	private static function rate_limit_ok(): bool {
		$ip = '';
		if ( class_exists( 'WC_Geolocation' ) ) {
			$ip = (string) WC_Geolocation::get_ip_address();
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$key   = 'vh_loja_rl_' . md5( '' !== $ip ? $ip : 'unknown' );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Sanitiza preço de um array de parâmetros.
	 *
	 * @param array<string, mixed> $params     Parâmetros.
	 * @param string               $chave      Chave min_price|max_price.
	 * @param int                  $preco_teto Teto.
	 * @param float                $padrao     Valor padrão.
	 */
	private static function preco_de_param( array $params, string $chave, int $preco_teto, float $padrao ): float {
		if ( ! isset( $params[ $chave ] ) || '' === (string) $params[ $chave ] ) {
			return $padrao;
		}

		$valor = (float) wc_format_decimal( sanitize_text_field( (string) $params[ $chave ] ), wc_get_price_decimals() );
		return max( 0.0, min( $valor, (float) $preco_teto ) );
	}

	/**
	 * Resolve term_id de categoria por slug.
	 */
	private static function term_id_por_slug( string $slug ): ?int {
		if ( '' === $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, 'product_cat' );
		return $term instanceof WP_Term ? (int) $term->term_id : null;
	}

	/**
	 * Filtro posts_clauses: aplica preço e ordenação via tabela de lookup.
	 *
	 * @param array<string, string> $clauses Cláusulas SQL.
	 * @param WP_Query               $query   Query.
	 * @return array<string, string>
	 */
	public static function filtrar_clausulas( $clauses, $query ): array {
		global $wpdb;

		$estado = self::$clausula_estado;

		if ( null === $estado || ! isset( $wpdb->wc_product_meta_lookup ) ) {
			return $clauses;
		}

		$post_type = $query->get( 'post_type' );
		if ( 'product' !== $post_type && ! ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
			return $clauses;
		}

		if ( ! $query->is_main_query() && ! $query->get( 'vh_loja_filtros_query' ) ) {
			return $clauses;
		}

		$lookup = $wpdb->wc_product_meta_lookup;

		if ( false === strpos( $clauses['join'], 'vh_price_lookup' ) ) {
			$clauses['join'] .= " INNER JOIN {$lookup} vh_price_lookup ON {$wpdb->posts}.ID = vh_price_lookup.product_id ";
		}

		$min     = $estado['preco_min'] > 0 ? (float) $estado['preco_min'] : 0.0;
		$tem_max = $estado['preco_max'] > 0 && $estado['preco_max'] < (int) $estado['preco_teto'];

		if ( $min > 0 || $tem_max ) {
			$max = $tem_max ? (float) $estado['preco_max'] : PHP_INT_MAX;
			/* Condição de sobreposição idêntica ao filtro nativo do WooCommerce. */
			$clauses['where'] .= $wpdb->prepare(
				' AND NOT ( %f < vh_price_lookup.min_price OR %f > vh_price_lookup.max_price ) ',
				$max,
				$min
			);
		}

		if ( ! empty( $estado['promocao'] ) ) {
			$clauses['where'] .= ' AND vh_price_lookup.onsale = 1 ';
		}

		if ( ! empty( $estado['estoque'] ) ) {
			$clauses['where'] .= " AND vh_price_lookup.stock_status = 'instock' ";
		}

		switch ( $estado['orderby'] ) {
			case 'price':
				$clauses['orderby'] = " vh_price_lookup.min_price ASC, {$wpdb->posts}.ID ASC ";
				break;
			case 'price-desc':
				$clauses['orderby'] = " vh_price_lookup.max_price DESC, {$wpdb->posts}.ID DESC ";
				break;
			case 'popularity':
				$clauses['orderby'] = " vh_price_lookup.total_sales DESC, {$wpdb->posts}.ID ASC ";
				break;
			case 'rating':
				$clauses['orderby'] = " vh_price_lookup.average_rating DESC, vh_price_lookup.rating_count DESC, {$wpdb->posts}.ID ASC ";
				break;
			case 'date':
				$clauses['orderby'] = " {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC ";
				break;
			default:
				$clauses['orderby'] = " {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_title ASC ";
				break;
		}

		$clauses['groupby'] = "{$wpdb->posts}.ID";

		remove_filter( 'posts_clauses', array( __CLASS__, 'filtrar_clausulas' ), 10 );
		self::$clausula_estado = null;

		return $clauses;
	}

	/**
	 * Parâmetros GET/REST removíveis ao limpar filtros.
	 *
	 * @return string[]
	 */
	public static function params_url(): array {
		return array(
			self::PARAM_EXCLUSIVOS,
			self::PARAM_PROMOCAO,
			self::PARAM_NOVIDADES,
			self::PARAM_ESTOQUE,
			self::PARAM_VARIAVEL,
			'min_price',
			'max_price',
			'paged',
		);
	}

	/**
	 * Indica se o estado exige JOIN na tabela de lookup do WooCommerce.
	 *
	 * @param array<string, mixed> $estado Estado sanitizado.
	 */
	private static function estado_usa_lookup( array $estado ): bool {
		$tem_preco = ( $estado['preco_min'] > 0 )
			|| ( $estado['preco_max'] > 0 && $estado['preco_max'] < (int) $estado['preco_teto'] );

		if ( $tem_preco || ! empty( $estado['promocao'] ) || ! empty( $estado['estoque'] ) ) {
			return true;
		}

		$orderby = ! empty( $estado['orderby'] ) ? (string) $estado['orderby'] : '';

		return in_array( $orderby, array( 'price', 'price-desc', 'popularity', 'rating' ), true );
	}

	/**
	 * Schema REST para parâmetros booleanos (?flag=1).
	 *
	 * @return array<string, mixed>
	 */
	private static function schema_param_flag(): array {
		return array(
			'type'              => 'string',
			'sanitize_callback' => static function ( $valor ) {
				return '1' === (string) $valor ? '1' : '';
			},
		);
	}

	/**
	 * Lê parâmetro booleano sanitizado (?flag=1).
	 *
	 * @param array<string, mixed> $params Parâmetros.
	 * @param string               $chave  Nome do parâmetro.
	 */
	private static function param_flag( array $params, string $chave ): bool {
		return isset( $params[ $chave ] ) && '1' === sanitize_text_field( (string) $params[ $chave ] );
	}

	/**
	 * Formata preço para URL (sem separadores inválidos).
	 */
	private static function formatar_preco_url( float $valor ): string {
		return wc_format_decimal( $valor, wc_get_price_decimals(), true );
	}

	/**
	 * Whitelist de orderby do WooCommerce.
	 */
	private static function orderby_permitido( string $orderby ): bool {
		$permitidos = array(
			'menu_order',
			'popularity',
			'rating',
			'date',
			'price',
			'price-desc',
		);

		return in_array( $orderby, $permitidos, true );
	}
}

if ( class_exists( 'WooCommerce' ) ) {
	VH_Loja_Filtros::init();
}

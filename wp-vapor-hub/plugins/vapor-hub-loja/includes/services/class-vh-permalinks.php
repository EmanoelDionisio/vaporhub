<?php
/**
 * Endereço público da loja.
 *
 * Uma função só monta o caminho da categoria e do produto. O painel grava as
 * mesmas opções que Configurações → Links permanentes, e acrescenta o modo
 * caminho completo (pai, filho e produto), que o WooCommerce não oferece.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Permalinks {

	public const META_PRINCIPAL = '_vh_cat_principal';

	public const OPTION_MODO = 'vh_permalink_modo';

	public const OPTION_MODO_CATEGORIA = 'vh_permalink_modo_categoria';

	public const OPTION_BASE_PRODUTO = 'vh_permalink_base_produto';

	public const QV_CAMINHO = 'vh_caminho';

	/**
	 * @return string[]
	 */
	public static function modos(): array {
		return [ 'padrao', 'loja', 'loja_categoria', 'personalizada', 'caminho' ];
	}

	public static function init(): void {
		add_filter( 'query_vars', [ __CLASS__, 'query_vars' ] );
		add_filter( 'rewrite_rules_array', [ __CLASS__, 'regras' ], 99 );
		add_filter( 'post_type_link', [ __CLASS__, 'filtrar_link' ], 20, 2 );
		add_filter( 'term_link', [ __CLASS__, 'filtrar_link_categoria' ], 20, 3 );
		add_filter( 'request', [ __CLASS__, 'resolver_pedido' ] );
		add_filter( 'woocommerce_get_breadcrumb', [ __CLASS__, 'filtrar_breadcrumb' ], 20 );
		add_action( 'template_redirect', [ __CLASS__, 'redirecionar_legado' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'redirecionar_categoria_legada' ], 1 );
	}

	public static function modo(): string {
		$modo = (string) get_option( self::OPTION_MODO, '' );
		return in_array( $modo, self::modos(), true ) ? $modo : 'caminho';
	}

	/**
	 * `caminho` publica a categoria na mesma trilha do breadcrumb.
	 * `base` mantém o prefixo do WooCommerce (product-category ou o slug do painel).
	 */
	public static function modo_categoria(): string {
		$modo = (string) get_option( self::OPTION_MODO_CATEGORIA, '' );
		return in_array( $modo, [ 'caminho', 'base' ], true ) ? $modo : 'caminho';
	}

	/**
	 * Estado exibido e gravado pelo painel.
	 *
	 * @return array{modo:string,modo_categoria:string,base_produto:string,base_categoria:string,base_tag:string,base_atributo:string,base_marca:string}
	 */
	public static function estado(): array {
		$wc           = get_option( 'woocommerce_permalinks', [] );
		$wc           = is_array( $wc ) ? $wc : [];
		$base_produto = (string) get_option( self::OPTION_BASE_PRODUTO, '' );
		if ( '' === $base_produto ) {
			$gravada      = trim( (string) ( $wc['product_base'] ?? 'product' ), '/' );
			$base_produto = ( '' === $gravada || str_contains( $gravada, '%' ) ) ? 'product' : $gravada;
		}

		$marca = (string) get_option( 'woocommerce_brand_permalink', '' );

		return [
			'modo'           => self::modo(),
			'modo_categoria' => self::modo_categoria(),
			'base_produto'   => $base_produto,
			'base_categoria' => '' !== (string) ( $wc['category_base'] ?? '' ) ? (string) $wc['category_base'] : 'product-category',
			'base_tag'       => '' !== (string) ( $wc['tag_base'] ?? '' ) ? (string) $wc['tag_base'] : 'product-tag',
			'base_atributo'  => (string) ( $wc['attribute_base'] ?? '' ),
			'base_marca'     => '' !== $marca ? $marca : 'marca',
		];
	}

	/**
	 * @param array<string, mixed> $entrada
	 */
	public static function salvar( array $entrada ): true|WP_Error {
		$modo = sanitize_key( (string) ( $entrada['modo'] ?? '' ) );
		if ( ! in_array( $modo, self::modos(), true ) ) {
			return new WP_Error( 'vh_permalink_modo', __( 'Escolha um modo de endereço.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$modo_categoria = sanitize_key( (string) ( $entrada['modo_categoria'] ?? 'caminho' ) );
		if ( ! in_array( $modo_categoria, [ 'caminho', 'base' ], true ) ) {
			return new WP_Error( 'vh_permalink_modo_categoria', __( 'Escolha o endereço da categoria.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$base_produto   = self::segmento( (string) ( $entrada['base_produto'] ?? 'product' ), true );
		$base_categoria = self::segmento( (string) ( $entrada['base_categoria'] ?? 'product-category' ), true );
		$base_tag       = self::segmento( (string) ( $entrada['base_tag'] ?? 'product-tag' ), true );
		$base_atributo  = self::segmento( (string) ( $entrada['base_atributo'] ?? '' ), false );
		$base_marca     = self::segmento( (string) ( $entrada['base_marca'] ?? 'marca' ), true );
		foreach ( [ $base_produto, $base_categoria, $base_tag, $base_atributo, $base_marca ] as $trecho ) {
			if ( is_wp_error( $trecho ) ) {
				return $trecho;
			}
		}

		$aplicada = self::base_do_modo( $modo, $base_produto );
		if ( is_wp_error( $aplicada ) ) {
			return $aplicada;
		}

		$wc = get_option( 'woocommerce_permalinks', [] );
		$wc = is_array( $wc ) ? $wc : [];
		$wc['product_base']           = $aplicada;
		$wc['category_base']          = $base_categoria;
		$wc['tag_base']               = $base_tag;
		$wc['attribute_base']         = $base_atributo;
		$wc['use_verbose_page_rules'] = in_array( $modo, [ 'loja', 'loja_categoria' ], true );

		update_option( 'woocommerce_permalinks', $wc );
		update_option( 'woocommerce_brand_permalink', $base_marca );
		update_option( self::OPTION_MODO, $modo, false );
		update_option( self::OPTION_MODO_CATEGORIA, $modo_categoria, false );
		update_option( self::OPTION_BASE_PRODUTO, $base_produto, false );

		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		return true;
	}

	/**
	 * Cadeia do raiz até a folha, inclusive.
	 *
	 * @return WP_Term[]
	 */
	public static function cadeia_categoria( WP_Term $termo ): array {
		$pilha  = [];
		$visto  = [];
		$atual  = $termo;
		$passos = 0;

		while ( $atual instanceof WP_Term && $passos < 12 ) {
			if ( isset( $visto[ $atual->term_id ] ) ) {
				break;
			}
			$visto[ $atual->term_id ] = true;
			$pilha[]                  = $atual;
			if ( (int) $atual->parent <= 0 ) {
				break;
			}
			$pai = get_term( (int) $atual->parent, 'product_cat' );
			if ( ! $pai instanceof WP_Term || is_wp_error( $pai ) ) {
				break;
			}
			$atual = $pai;
			++$passos;
		}

		return array_reverse( $pilha );
	}

	public static function caminho_categoria( WP_Term $termo ): string {
		$slugs = [];
		foreach ( self::cadeia_categoria( $termo ) as $item ) {
			if ( '' !== $item->slug ) {
				$slugs[] = $item->slug;
			}
		}
		return implode( '/', $slugs );
	}

	public static function categoria_principal( WC_Product $produto ): ?WP_Term {
		$ids = array_values( array_filter( array_map( 'absint', $produto->get_category_ids() ) ) );
		if ( ! $ids ) {
			return null;
		}

		$meta = (int) $produto->get_meta( self::META_PRINCIPAL );
		if ( $meta && in_array( $meta, $ids, true ) ) {
			$escolhida = get_term( $meta, 'product_cat' );
			if ( $escolhida instanceof WP_Term && ! is_wp_error( $escolhida ) && ! self::e_sem_categoria( $escolhida ) ) {
				return $escolhida;
			}
		}

		$melhor       = null;
		$profundidade = -1;
		foreach ( $ids as $id ) {
			$termo = get_term( $id, 'product_cat' );
			if ( ! $termo instanceof WP_Term || is_wp_error( $termo ) || self::e_sem_categoria( $termo ) ) {
				continue;
			}
			$prof = count( self::cadeia_categoria( $termo ) );
			if ( $prof > $profundidade ) {
				$profundidade = $prof;
				$melhor       = $termo;
			}
		}

		return $melhor;
	}

	public static function caminho_produto( WC_Product $produto ): string {
		$categoria = self::categoria_principal( $produto );
		$slug      = $produto->get_slug();
		if ( ! $categoria || '' === $slug ) {
			return '';
		}
		$base = self::caminho_categoria( $categoria );
		return '' === $base ? '' : $base . '/' . $slug;
	}

	public static function url_publica( WC_Product $produto ): string {
		$caminho = self::caminho_produto( $produto );
		if ( '' === $caminho ) {
			return '';
		}
		return self::url_do_caminho( $caminho );
	}

	public static function url_categoria( WP_Term $termo ): string {
		$caminho = self::caminho_categoria( $termo );
		return '' === $caminho ? '' : self::url_do_caminho( $caminho );
	}

	/**
	 * @param mixed $termo
	 */
	public static function filtrar_link_categoria( string $url, $termo, string $taxonomia ): string {
		if ( 'caminho' !== self::modo_categoria() || 'product_cat' !== $taxonomia || ! $termo instanceof WP_Term ) {
			return $url;
		}
		$nova = self::url_categoria( $termo );
		return '' !== $nova ? $nova : $url;
	}

	public static function categoria_por_caminho( string $caminho ): ?WP_Term {
		$partes = array_values( array_filter( explode( '/', trim( $caminho, '/' ) ), static fn( $parte ) => '' !== $parte ) );
		if ( ! $partes || count( $partes ) > 8 || ! function_exists( 'get_terms' ) ) {
			return null;
		}

		$pai   = 0;
		$atual = null;
		foreach ( $partes as $parte ) {
			$slug = sanitize_title( rawurldecode( $parte ) );
			if ( '' === $slug ) {
				return null;
			}
			$atual = self::termo_filho( $slug, $pai );
			if ( ! $atual ) {
				return null;
			}
			$pai = (int) $atual->term_id;
		}

		return $atual;
	}

	/**
	 * @param mixed $post
	 */
	public static function filtrar_link( string $permalink, $post ): string {
		if ( 'caminho' !== self::modo() || ! is_object( $post ) || 'product' !== ( $post->post_type ?? '' ) ) {
			return $permalink;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $permalink;
		}
		$produto = wc_get_product( $post );
		if ( ! $produto instanceof WC_Product ) {
			return $permalink;
		}
		$url = self::url_publica( $produto );
		return '' !== $url ? $url : $permalink;
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QV_CAMINHO;
		return $vars;
	}

	/**
	 * A regra fica por último: página, post e /minha-loja/ já casaram antes.
	 *
	 * @param array<string, string> $regras
	 * @return array<string, string>
	 */
	public static function regras( array $regras ): array {
		$chave = '(.+)/([^/]+)/?$';
		unset( $regras[ $chave ] );
		if ( 'caminho' !== self::modo() ) {
			return $regras;
		}
		$regras[ $chave ] = 'index.php?post_type=product&name=$matches[2]&' . self::QV_CAMINHO . '=$matches[1]';
		return $regras;
	}

	/**
	 * @param array<string, mixed> $query_vars
	 * @return array<string, mixed>
	 */
	public static function resolver_pedido( array $query_vars ): array {
		if ( 'caminho' === self::modo_categoria() ) {
			$categoria = self::resolver_categoria( $query_vars );
			if ( null !== $categoria ) {
				return $categoria;
			}
		}

		if ( 'caminho' !== self::modo() ) {
			return $query_vars;
		}
		$caminho = isset( $query_vars[ self::QV_CAMINHO ] ) ? trim( (string) $query_vars[ self::QV_CAMINHO ], '/' ) : '';
		$slug    = isset( $query_vars['name'] ) ? (string) $query_vars['name'] : '';
		if ( '' === $caminho || '' === $slug || 'product' !== ( $query_vars['post_type'] ?? '' ) ) {
			return $query_vars;
		}

		$primeiro   = explode( '/', $caminho )[0];
		$reservados = [ 'minha-loja', 'wp-json', 'wp-admin', 'wp-content', 'wp-includes', 'product-category', 'product-tag', 'product' ];
		if ( in_array( $primeiro, $reservados, true ) ) {
			return self::soltar_produto( $query_vars );
		}

		$produto = self::produto_por_slug( $slug );
		if ( ! $produto || self::caminho_categoria_do_produto( $produto ) !== $caminho ) {
			return self::soltar_produto( $query_vars );
		}

		$query_vars['p']         = $produto->get_id();
		$query_vars['post_type'] = 'product';
		unset( $query_vars['name'], $query_vars[ self::QV_CAMINHO ] );
		return $query_vars;
	}

	public static function redirecionar_legado(): void {
		if ( 'caminho' !== self::modo() ) {
			return;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! function_exists( 'wp_safe_redirect' ) || ! function_exists( 'get_permalink' ) ) {
			return;
		}

		$pedido = self::caminho_pedido();
		$base   = self::base_legada();
		if ( '' === $pedido || '' === $base || ! preg_match( '#^' . preg_quote( $base, '#' ) . '/([^/]+)$#', $pedido, $casou ) ) {
			return;
		}

		$produto = self::produto_por_slug( rawurldecode( $casou[1] ) );
		if ( ! $produto ) {
			return;
		}
		$destino = get_permalink( $produto->get_id() );
		if ( ! is_string( $destino ) || '' === $destino ) {
			return;
		}
		$novo = trim( (string) parse_url( $destino, PHP_URL_PATH ), '/' );
		$home = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && ( $novo === $home || str_starts_with( $novo, $home . '/' ) ) ) {
			$novo = trim( substr( $novo, strlen( $home ) ), '/' );
		}
		if ( $novo === $pedido ) {
			return;
		}
		wp_safe_redirect( $destino, 301 );
		exit;
	}

	/**
	 * O arquivo antigo product-category/pai/filho passa a responder no caminho da trilha.
	 */
	public static function redirecionar_categoria_legada(): void {
		if ( 'caminho' !== self::modo_categoria() ) {
			return;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return;
		}
		if ( ! function_exists( 'get_queried_object' ) || ! function_exists( 'get_term_link' ) || ! function_exists( 'wp_safe_redirect' ) ) {
			return;
		}
		$termo = get_queried_object();
		if ( ! $termo instanceof WP_Term ) {
			return;
		}
		$destino = get_term_link( $termo );
		if ( ! is_string( $destino ) || '' === $destino ) {
			return;
		}
		if ( self::mesmo_caminho( self::caminho_pedido(), $destino ) ) {
			return;
		}
		wp_safe_redirect( $destino, 301 );
		exit;
	}

	/**
	 * Trilha visível do produto: início, loja, cada ancestral e o produto.
	 *
	 * @param array<int, array{0:string,1:string}> $crumbs
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function filtrar_breadcrumb( array $crumbs ): array {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $crumbs;
		}
		if ( ! function_exists( 'get_queried_object_id' ) || ! function_exists( 'wc_get_product' ) ) {
			return $crumbs;
		}
		$produto = wc_get_product( get_queried_object_id() );
		if ( ! $produto instanceof WC_Product ) {
			return $crumbs;
		}
		$categoria = self::categoria_principal( $produto );
		if ( ! $categoria ) {
			return $crumbs;
		}

		$loja = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$novo = [
			[ __( 'Início', 'vapor-hub-loja' ), home_url( '/' ) ],
			[ __( 'Loja', 'vapor-hub-loja' ), is_string( $loja ) ? $loja : home_url( '/' ) ],
		];
		foreach ( self::cadeia_categoria( $categoria ) as $termo ) {
			$link   = get_term_link( $termo );
			$novo[] = [ $termo->name, is_wp_error( $link ) ? '' : (string) $link ];
		}
		$novo[] = [ $produto->get_name(), '' ];
		return $novo;
	}

	private static function caminho_categoria_do_produto( WC_Product $produto ): string {
		$categoria = self::categoria_principal( $produto );
		return $categoria ? self::caminho_categoria( $categoria ) : '';
	}

	private static function produto_por_slug( string $slug ): ?WC_Product {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || ! function_exists( 'get_posts' ) || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$ids = get_posts(
			[
				'name'             => $slug,
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);
		if ( ! is_array( $ids ) || ! isset( $ids[0] ) ) {
			return null;
		}
		$produto = wc_get_product( (int) $ids[0] );
		return $produto instanceof WC_Product ? $produto : null;
	}

	/**
	 * @param array<string, mixed> $query_vars
	 * @return array<string, mixed>
	 */
	private static function soltar_produto( array $query_vars ): array {
		unset( $query_vars['post_type'], $query_vars['name'], $query_vars['p'], $query_vars[ self::QV_CAMINHO ] );
		$query_vars['error'] = '404';
		return $query_vars;
	}

	/**
	 * @param array<string, mixed> $query_vars
	 * @return array<string, mixed>|null
	 */
	private static function resolver_categoria( array $query_vars ): ?array {
		$bruto = self::caminho_pedido();
		if ( '' === $bruto ) {
			$prefixo = isset( $query_vars[ self::QV_CAMINHO ] ) ? trim( (string) $query_vars[ self::QV_CAMINHO ], '/' ) : '';
			$folha   = isset( $query_vars['name'] ) ? trim( (string) $query_vars['name'], '/' ) : '';
			if ( '' !== $prefixo && '' !== $folha && 'product' === ( $query_vars['post_type'] ?? '' ) ) {
				$bruto = $prefixo . '/' . $folha;
			}
		}
		if ( '' === $bruto || self::reservado( $bruto ) ) {
			return null;
		}

		$pagina = 0;
		if ( preg_match( '#^(.+)/page/([0-9]+)$#', $bruto, $casou ) ) {
			$bruto  = $casou[1];
			$pagina = (int) $casou[2];
		}

		$termo = self::categoria_por_caminho( $bruto );
		if ( ! $termo || self::caminho_e_conteudo( $bruto ) ) {
			return null;
		}

		unset(
			$query_vars['name'],
			$query_vars['pagename'],
			$query_vars['page'],
			$query_vars['post_type'],
			$query_vars['p'],
			$query_vars['attachment'],
			$query_vars['error'],
			$query_vars[ self::QV_CAMINHO ]
		);
		$query_vars['product_cat'] = $termo->slug;
		if ( $pagina > 1 ) {
			$query_vars['paged'] = $pagina;
		}
		return $query_vars;
	}

	private static function termo_filho( string $slug, int $pai ): ?WP_Term {
		$termos = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'slug'       => $slug,
				'parent'     => $pai,
			]
		);
		if ( ! is_array( $termos ) ) {
			return null;
		}
		foreach ( $termos as $termo ) {
			if ( $termo instanceof WP_Term && $termo->slug === $slug && (int) $termo->parent === $pai && ! self::e_sem_categoria( $termo ) ) {
				return $termo;
			}
		}
		return null;
	}

	private static function reservado( string $caminho ): bool {
		$primeiro   = explode( '/', $caminho )[0];
		$reservados = [ 'minha-loja', 'wp-json', 'wp-admin', 'wp-content', 'wp-includes', 'product-category', 'product-tag', 'product' ];
		return in_array( $primeiro, $reservados, true );
	}

	private static function caminho_e_conteudo( string $caminho ): bool {
		if ( function_exists( 'get_page_by_path' ) ) {
			$pagina = get_page_by_path( $caminho );
			if ( is_object( $pagina ) && 'publish' === ( $pagina->post_status ?? '' ) ) {
				return true;
			}
		}
		if ( str_contains( $caminho, '/' ) || ! function_exists( 'get_posts' ) ) {
			return false;
		}
		$posts = get_posts(
			[
				'name'             => $caminho,
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);
		return is_array( $posts ) && isset( $posts[0] );
	}

	private static function url_do_caminho( string $caminho ): string {
		$url = home_url( '/' . trim( $caminho, '/' ) . '/' );
		return function_exists( 'user_trailingslashit' ) ? user_trailingslashit( $url ) : $url;
	}

	private static function mesmo_caminho( string $pedido, string $destino ): bool {
		$novo = trim( (string) parse_url( $destino, PHP_URL_PATH ), '/' );
		$home = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && ( $novo === $home || str_starts_with( $novo, $home . '/' ) ) ) {
			$novo = trim( substr( $novo, strlen( $home ) ), '/' );
		}
		return $novo === trim( $pedido, '/' );
	}

	private static function caminho_pedido(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = trim( (string) parse_url( $uri, PHP_URL_PATH ), '/' );
		$home = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && ( $path === $home || str_starts_with( $path, $home . '/' ) ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		return $path;
	}

	private static function base_legada(): string {
		$wc   = get_option( 'woocommerce_permalinks', [] );
		$base = is_array( $wc ) ? trim( (string) ( $wc['product_base'] ?? '' ), '/' ) : '';
		if ( '' === $base || str_contains( $base, '%' ) ) {
			return 'product';
		}
		return $base;
	}

	private static function e_sem_categoria( WP_Term $termo ): bool {
		return in_array( $termo->slug, [ 'uncategorized', 'sem-categoria' ], true );
	}

	private static function segmento( string $valor, bool $obrigatorio ): string|WP_Error {
		$valor = trim( trim( $valor ), '/' );
		if ( '' === $valor ) {
			if ( $obrigatorio ) {
				return new WP_Error( 'vh_permalink_slug', __( 'Informe um slug, sem barra no meio.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
			}
			return '';
		}
		if ( str_contains( $valor, '/' ) ) {
			return new WP_Error( 'vh_permalink_slug', __( 'O slug não pode ter barra no meio.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}
		$limpo = sanitize_title( $valor );
		if ( '' === $limpo ) {
			return new WP_Error( 'vh_permalink_slug', __( 'Slug inválido.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}
		return $limpo;
	}

	private static function base_do_modo( string $modo, string $base_produto ): string|WP_Error {
		if ( 'personalizada' === $modo ) {
			return $base_produto;
		}
		if ( 'caminho' === $modo || 'padrao' === $modo ) {
			return 'product';
		}

		$loja = self::slug_da_loja();
		if ( is_wp_error( $loja ) ) {
			return $loja;
		}
		return 'loja_categoria' === $modo ? $loja . '/%product_cat%' : $loja;
	}

	private static function slug_da_loja(): string|WP_Error {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return new WP_Error( 'vh_permalink_loja', __( 'WooCommerce indisponível.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}
		$id  = (int) wc_get_page_id( 'shop' );
		$uri = '';
		if ( $id > 0 && function_exists( 'get_page_uri' ) ) {
			$uri = (string) get_page_uri( $id );
		} elseif ( $id > 0 && function_exists( 'get_post' ) ) {
			$pagina = get_post( $id );
			$uri    = is_object( $pagina ) ? (string) $pagina->post_name : '';
		}
		$uri = trim( $uri, '/' );
		if ( '' === $uri ) {
			return new WP_Error( 'vh_permalink_loja', __( 'Defina a página da loja antes de usar a base da loja.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}
		return $uri;
	}
}

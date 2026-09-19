<?php
/**
 * Kernel mínimo de WordPress + WooCommerce para rodar o código do plugin fora do WP.
 *
 * Só implementa o que o plugin realmente usa nos caminhos sob teste: opções,
 * post meta, hooks, transients e os objetos de produto do WooCommerce. Não é um
 * WordPress de verdade — a validação final continua sendo WP-CLI na VPS.
 *
 * @package VaporHubLoja\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
}

/*
 * Polyfills de mbstring: o PHP da máquina de desenvolvimento pode não ter a
 * extensão, e o plugin usa mb_substr para truncar mensagens.
 */
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( string $texto, int $inicio, ?int $tamanho = null, ?string $encoding = null ): string {
		unset( $encoding );
		return null === $tamanho ? substr( $texto, $inicio ) : substr( $texto, $inicio, $tamanho );
	}
}

if ( ! function_exists( 'mb_strlen' ) ) {
	function mb_strlen( string $texto, ?string $encoding = null ): int {
		unset( $encoding );
		return strlen( $texto );
	}
}

if ( ! function_exists( 'mb_strtolower' ) ) {
	function mb_strtolower( string $texto, ?string $encoding = null ): string {
		unset( $encoding );
		return strtolower( $texto );
	}
}

/**
 * Estado global do kernel: opções, metas, produtos, hooks.
 */
final class VH_Fake_WP {

	/** @var array<string, mixed> */
	public static array $opcoes = [];

	/** @var array<int, array<string, mixed>> */
	public static array $meta = [];

	/** @var array<int, array<string, mixed>> */
	public static array $term_meta = [];

	/** @var array<int, WC_Product> */
	public static array $produtos = [];

	/** @var array<int, WC_Order> */
	public static array $pedidos = [];

	/** @var array<string, mixed> */
	public static array $transients = [];

	/** @var array<string, array<int, callable>> */
	public static array $hooks = [];

	/** @var array<int, array{hook:string, args:array<int, mixed>}> */
	public static array $acoes_disparadas = [];

	/** @var array<int, string> */
	public static array $erros_registrados = [];

	public static int $proximo_id = 100;

	public static function reset(): void {
		self::$opcoes           = [];
		self::$meta             = [];
		self::$term_meta        = [];
		self::$produtos         = [];
		self::$pedidos          = [];
		self::$transients       = [];
		self::$hooks            = [];
		self::$acoes_disparadas = [];
		self::$erros_registrados = [];
		self::$proximo_id       = 100;
	}

	public static function novo_id(): int {
		return ++self::$proximo_id;
	}

	public static function registrar_produto( WC_Product $produto ): void {
		self::$produtos[ $produto->get_id() ] = $produto;
	}
}

/* -------------------------------------------------------------------------- */
/* Erros                                                                      */
/* -------------------------------------------------------------------------- */

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		/** @var array<string, string[]> */
		private array $errors = [];

		/** @var array<string, mixed> */
		private array $error_data = [];

		public function __construct( string $code = '', string $message = '', mixed $data = null ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
				if ( null !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			$codigos = array_keys( $this->errors );
			return $codigos ? (string) $codigos[0] : '';
		}

		public function get_error_message( string $code = '' ): string {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_messages(): array {
			$saida = [];
			foreach ( $this->errors as $mensagens ) {
				$saida = array_merge( $saida, $mensagens );
			}
			return $saida;
		}

		public function get_error_data( string $code = '' ): mixed {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->error_data[ $code ] ?? null;
		}

		public function has_errors(): bool {
			return (bool) $this->errors;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $coisa ): bool {
		return $coisa instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id  = 0;
		public string $name  = '';
		public string $slug  = '';
		public int $parent   = 0;
		public string $description = '';
		public string $taxonomy = 'product_cat';

		public function __construct( int $term_id = 0, string $name = '', string $slug = '' ) {
			$this->term_id = $term_id;
			$this->name    = $name;
			$this->slug    = $slug;
		}
	}
}

/* -------------------------------------------------------------------------- */
/* Sanitização e helpers                                                      */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( '__' ) ) {
	function __( string $texto, string $dominio = 'default' ): string {
		unset( $dominio );
		return $texto;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $texto, string $dominio = 'default' ): string {
		return __( $texto, $dominio );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $valor ): int {
		return abs( (int) $valor );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( mixed $valor ): string {
		$valor = (string) $valor;
		$valor = strip_tags( $valor );
		$valor = preg_replace( '/[\r\n\t]+/', ' ', $valor ) ?? '';
		return trim( preg_replace( '/\s{2,}/', ' ', $valor ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( mixed $valor ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $valor ) ) ?? '';
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( mixed $valor ): string {
		$valor = strtolower( (string) $valor );
		$valor = preg_replace( '/[^a-z0-9]+/', '-', $valor ) ?? '';
		return trim( $valor, '-' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( mixed $texto, bool $remover_quebras = true ): string {
		$texto = strip_tags( (string) $texto );
		return $remover_quebras ? trim( preg_replace( '/[\r\n\t ]+/', ' ', $texto ) ?? '' ) : $texto;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( mixed $texto ): string {
		return (string) $texto;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( mixed $url ): string {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( mixed $valor ): string {
		return htmlspecialchars( (string) $valor, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( mixed $valor ): string {
		return htmlspecialchars( (string) $valor, ENT_QUOTES );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $dados, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $dados, $flags, $depth );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( mixed $args, array $padrao = [] ): array {
		$args = is_array( $args ) ? $args : [];
		return array_merge( $padrao, $args );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $tamanho = 12, bool $especiais = true, bool $extra = false ): string {
		unset( $especiais, $extra );
		return substr( str_repeat( bin2hex( random_bytes( 8 ) ), 4 ), 0, $tamanho );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( mixed $valor ): mixed {
		return is_string( $valor ) ? stripslashes( $valor ) : $valor;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $tipo = 'mysql', bool $gmt = false ): string {
		unset( $gmt );
		return 'timestamp' === $tipo ? (string) time() : gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $caminho = '' ): string {
		return 'https://piloto.example' . $caminho;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $caminho = '' ): string {
		return 'https://piloto.example/wp-json/' . ltrim( $caminho, '/' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $componente = -1 ): mixed {
		return parse_url( $url, $componente );
	}
}

/* -------------------------------------------------------------------------- */
/* Opções, metas e transients                                                 */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $nome, mixed $padrao = false ): mixed {
		return array_key_exists( $nome, VH_Fake_WP::$opcoes ) ? VH_Fake_WP::$opcoes[ $nome ] : $padrao;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $nome, mixed $valor, mixed $autoload = null ): bool {
		unset( $autoload );
		VH_Fake_WP::$opcoes[ $nome ] = $valor;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $nome ): bool {
		unset( VH_Fake_WP::$opcoes[ $nome ] );
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $chave = '', bool $unico = false ): mixed {
		$metas = VH_Fake_WP::$meta[ $post_id ] ?? [];
		if ( '' === $chave ) {
			return $metas;
		}
		$valor = $metas[ $chave ] ?? '';
		return $unico ? $valor : ( '' === $valor ? [] : [ $valor ] );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $chave, mixed $valor, mixed $anterior = '' ): bool {
		unset( $anterior );
		VH_Fake_WP::$meta[ $post_id ][ $chave ] = $valor;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $chave, mixed $valor = '' ): bool {
		unset( $valor, VH_Fake_WP::$meta[ $post_id ][ $chave ] );
		return true;
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( int $term_id, string $chave = '', bool $unico = false ): mixed {
		$metas = VH_Fake_WP::$term_meta[ $term_id ] ?? [];
		if ( '' === $chave ) {
			return $metas;
		}
		$valor = $metas[ $chave ] ?? '';
		return $unico ? $valor : ( '' === $valor ? [] : [ $valor ] );
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta( int $term_id, string $chave, mixed $valor ): bool {
		VH_Fake_WP::$term_meta[ $term_id ][ $chave ] = $valor;
		return true;
	}
}

if ( ! function_exists( 'delete_term_meta' ) ) {
	function delete_term_meta( int $term_id, string $chave ): bool {
		unset( VH_Fake_WP::$term_meta[ $term_id ][ $chave ] );
		return true;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( mixed $term_id, string $taxonomy = '' ): mixed {
		unset( $taxonomy );
		$term_id = (int) $term_id;
		if ( ! isset( VH_Fake_WP::$term_meta[ $term_id ]['__nome'] ) ) {
			return null;
		}
		$termo         = new WP_Term( $term_id, (string) VH_Fake_WP::$term_meta[ $term_id ]['__nome'] );
		$termo->slug   = sanitize_title( $termo->name );
		$termo->parent = (int) ( VH_Fake_WP::$term_meta[ $term_id ]['__pai'] ?? 0 );
		return $termo;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = [] ): array {
		$meta_query = $args['meta_query'] ?? [];
		$number     = (int) ( $args['number'] ?? 0 );
		$saida      = [];

		foreach ( VH_Fake_WP::$term_meta as $term_id => $metas ) {
			if ( ! isset( $metas['__nome'] ) ) {
				continue;
			}
			if ( is_array( $meta_query ) && $meta_query ) {
				$ok = true;
				foreach ( $meta_query as $clause ) {
					if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
						continue;
					}
					$esperado = (string) ( $clause['value'] ?? '' );
					$atual    = (string) ( $metas[ $clause['key'] ] ?? '' );
					if ( $esperado !== $atual ) {
						$ok = false;
						break;
					}
				}
				if ( ! $ok ) {
					continue;
				}
			}
			$termo = get_term( (int) $term_id, (string) ( $args['taxonomy'] ?? '' ) );
			if ( $termo instanceof WP_Term ) {
				$saida[] = $termo;
			}
			if ( $number > 0 && count( $saida ) >= $number ) {
				break;
			}
		}

		return $saida;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $chave ): mixed {
		return VH_Fake_WP::$transients[ $chave ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $chave, mixed $valor, int $expira = 0 ): bool {
		unset( $expira );
		VH_Fake_WP::$transients[ $chave ] = $valor;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $chave ): bool {
		unset( VH_Fake_WP::$transients[ $chave ] );
		return true;
	}
}

/* -------------------------------------------------------------------------- */
/* Hooks                                                                      */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $prioridade = 10, int $args = 1 ): bool {
		unset( $prioridade, $args );
		VH_Fake_WP::$hooks[ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $prioridade = 10, int $args = 1 ): bool {
		return add_action( $hook, $callback, $prioridade, $args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {
		VH_Fake_WP::$acoes_disparadas[] = [
			'hook' => $hook,
			'args' => $args,
		];
		foreach ( VH_Fake_WP::$hooks[ $hook ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $valor, mixed ...$args ): mixed {
		foreach ( VH_Fake_WP::$hooks[ $hook ] ?? [] as $callback ) {
			$valor = $callback( $valor, ...$args );
		}
		return $valor;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	function has_action( string $hook, mixed $callback = false ): bool {
		unset( $callback );
		return ! empty( VH_Fake_WP::$hooks[ $hook ] );
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( string $hook ): int {
		$total = 0;
		foreach ( VH_Fake_WP::$acoes_disparadas as $acao ) {
			if ( $acao['hook'] === $hook ) {
				++$total;
			}
		}
		return $total;
	}
}

/* -------------------------------------------------------------------------- */
/* WooCommerce — helpers                                                      */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'wc_clean' ) ) {
	function wc_clean( mixed $valor ): string {
		return sanitize_text_field( $valor );
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( mixed $valor, mixed $casas = false, bool $remover_zeros = false ): string {
		if ( '' === $valor || null === $valor ) {
			return '';
		}
		$numero = (float) str_replace( ',', '.', (string) $valor );
		$texto  = false === $casas ? (string) $numero : number_format( $numero, (int) $casas, '.', '' );
		return $remover_zeros ? rtrim( rtrim( $texto, '0' ), '.' ) : $texto;
	}
}

if ( ! class_exists( 'WC_Data_Exception' ) ) {
	class WC_Data_Exception extends Exception {}
}

/**
 * Produto WooCommerce em memória, com os getters/setters usados pelo plugin.
 */
if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {

		protected int $id;
		protected string $tipo             = 'simple';
		protected string $nome             = '';
		protected string $slug             = '';
		protected string $status           = 'publish';
		protected string $descricao        = '';
		protected string $descricao_curta  = '';
		protected string $sku              = '';
		protected string $preco_regular    = '';
		protected string $preco_promo      = '';
		protected bool $gerencia_estoque   = false;
		protected ?int $estoque            = null;
		protected string $peso             = '';
		protected string $comprimento      = '';
		protected string $largura          = '';
		protected string $altura           = '';
		protected int $imagem_id           = 0;
		/** @var int[] */
		protected array $galeria           = [];
		/** @var int[] */
		protected array $categorias        = [];
		/** @var int[] */
		protected array $tags              = [];
		public int $salvamentos            = 0;

		public function __construct( int $id = 0 ) {
			$this->id = $id > 0 ? $id : VH_Fake_WP::novo_id();
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_type(): string {
			return $this->tipo;
		}

		public function is_type( mixed $tipo ): bool {
			return is_array( $tipo ) ? in_array( $this->tipo, $tipo, true ) : $this->tipo === $tipo;
		}

		public function get_name(): string {
			return $this->nome;
		}

		public function set_name( string $nome ): void {
			$this->nome = $nome;
		}

		public function get_slug(): string {
			return '' !== $this->slug ? $this->slug : sanitize_title( $this->nome );
		}

		public function set_slug( string $slug ): void {
			$this->slug = $slug;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function set_status( string $status ): void {
			$this->status = $status;
		}

		public function get_description(): string {
			return $this->descricao;
		}

		public function set_description( string $texto ): void {
			$this->descricao = $texto;
		}

		public function get_short_description(): string {
			return $this->descricao_curta;
		}

		public function set_short_description( string $texto ): void {
			$this->descricao_curta = $texto;
		}

		public function get_sku(): string {
			return $this->sku;
		}

		public function set_sku( string $sku ): void {
			if ( '' !== $sku ) {
				$existente = wc_get_product_id_by_sku( $sku );
				if ( $existente > 0 && $existente !== $this->id ) {
					throw new WC_Data_Exception( 'sku_duplicado' );
				}
			}
			$this->sku = $sku;
		}

		public function get_regular_price( string $contexto = 'view' ): string {
			unset( $contexto );
			return $this->preco_regular;
		}

		public function set_regular_price( mixed $preco ): void {
			$this->preco_regular = (string) $preco;
		}

		public function get_sale_price( string $contexto = 'view' ): string {
			unset( $contexto );
			return $this->preco_promo;
		}

		public function set_sale_price( mixed $preco ): void {
			$this->preco_promo = (string) $preco;
		}

		public function get_price(): string {
			return '' !== $this->preco_promo ? $this->preco_promo : $this->preco_regular;
		}

		public function get_manage_stock(): bool {
			return $this->gerencia_estoque;
		}

		public function set_manage_stock( bool $gerencia ): void {
			$this->gerencia_estoque = $gerencia;
		}

		public function get_stock_quantity(): ?int {
			return $this->estoque;
		}

		public function set_stock_quantity( mixed $qtd ): void {
			$this->estoque = null === $qtd || '' === $qtd ? null : (int) $qtd;
		}

		public function is_in_stock(): bool {
			return ! $this->gerencia_estoque || (int) $this->estoque > 0;
		}

		public function get_weight(): string {
			return $this->peso;
		}

		public function set_weight( mixed $peso ): void {
			$this->peso = (string) $peso;
		}

		public function get_length(): string {
			return $this->comprimento;
		}

		public function set_length( mixed $valor ): void {
			$this->comprimento = (string) $valor;
		}

		public function get_width(): string {
			return $this->largura;
		}

		public function set_width( mixed $valor ): void {
			$this->largura = (string) $valor;
		}

		public function get_height(): string {
			return $this->altura;
		}

		public function set_height( mixed $valor ): void {
			$this->altura = (string) $valor;
		}

		public function get_image_id(): int {
			return $this->imagem_id;
		}

		public function set_image_id( mixed $id ): void {
			$this->imagem_id = (int) $id;
		}

		/** @return int[] */
		public function get_gallery_image_ids(): array {
			return $this->galeria;
		}

		/** @param int[] $ids */
		public function set_gallery_image_ids( array $ids ): void {
			$this->galeria = array_values( array_map( 'intval', $ids ) );
		}

		/** @return int[] */
		public function get_category_ids(): array {
			return $this->categorias;
		}

		/** @param int[] $ids */
		public function set_category_ids( array $ids ): void {
			$this->categorias = array_values( array_map( 'intval', $ids ) );
		}

		/** @return int[] */
		public function get_tag_ids(): array {
			return $this->tags;
		}

		/** @param int[] $ids */
		public function set_tag_ids( array $ids ): void {
			$this->tags = array_values( array_map( 'intval', $ids ) );
		}

		public function get_meta( string $chave, bool $unico = true ): mixed {
			return get_post_meta( $this->id, $chave, $unico );
		}

		public function update_meta_data( string $chave, mixed $valor ): void {
			update_post_meta( $this->id, $chave, $valor );
		}

		public function delete_meta_data( string $chave ): void {
			delete_post_meta( $this->id, $chave );
		}

		public function get_attributes(): array {
			return [];
		}

		public function save(): int {
			++$this->salvamentos;
			VH_Fake_WP::registrar_produto( $this );
			return $this->id;
		}
	}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	class WC_Product_Simple extends WC_Product {
		protected string $tipo = 'simple';
	}
}

if ( ! class_exists( 'WC_Product_Attribute' ) ) {
	class WC_Product_Attribute {

		private string $nome = '';
		/** @var array<int|string, mixed> */
		private array $opcoes = [];
		private bool $variacao = true;

		public function set_name( string $nome ): void {
			$this->nome = $nome;
		}

		public function get_name(): string {
			return $this->nome;
		}

		/** @param array<int|string, mixed> $opcoes */
		public function set_options( array $opcoes ): void {
			$this->opcoes = $opcoes;
		}

		/** @return array<int|string, mixed> */
		public function get_options(): array {
			return $this->opcoes;
		}

		public function set_variation( bool $variacao ): void {
			$this->variacao = $variacao;
		}

		public function get_variation(): bool {
			return $this->variacao;
		}

		public function is_taxonomy(): bool {
			return str_starts_with( $this->nome, 'pa_' );
		}
	}
}

if ( ! class_exists( 'WC_Product_Variable' ) ) {
	class WC_Product_Variable extends WC_Product {

		protected string $tipo = 'variable';

		/** @var array<string, WC_Product_Attribute> */
		private array $atributos = [];

		/** @var int[] */
		private array $filhos = [];

		/** @param array<string, WC_Product_Attribute> $atributos */
		public function set_attributes( array $atributos ): void {
			$this->atributos = $atributos;
		}

		/** @return array<string, WC_Product_Attribute> */
		public function get_attributes(): array {
			return $this->atributos;
		}

		/** @return int[] */
		public function get_children(): array {
			return $this->filhos;
		}

		/** @param int[] $ids */
		public function set_children( array $ids ): void {
			$this->filhos = array_values( array_map( 'intval', $ids ) );
		}

		public static function sync( int $produto_id ): void {
			unset( $produto_id );
		}
	}
}

if ( ! class_exists( 'WC_Product_Variation' ) ) {
	class WC_Product_Variation extends WC_Product {

		protected string $tipo = 'variation';

		private int $pai_id = 0;

		/** @var array<string, string> */
		private array $atributos_variacao = [];

		public function set_parent_id( int $id ): void {
			$this->pai_id = $id;
		}

		public function get_parent_id(): int {
			return $this->pai_id;
		}

		/** @param array<string, string> $atributos */
		public function set_attributes( array $atributos ): void {
			$this->atributos_variacao = $atributos;
		}

		/** @return array<string, string> */
		public function get_attributes(): array {
			return $this->atributos_variacao;
		}
	}
}

/**
 * Item de pedido, só com o que o mapeamento canônico lê.
 */
if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	class WC_Order_Item_Product {

		private WC_Product $produto;
		private int $quantidade;
		private float $total;

		public function __construct( WC_Product $produto, int $quantidade = 1, float $total = 0.0 ) {
			$this->produto    = $produto;
			$this->quantidade = $quantidade;
			$this->total      = $total;
		}

		public function get_product(): WC_Product {
			return $this->produto;
		}

		public function get_name(): string {
			return $this->produto->get_name();
		}

		public function get_quantity(): int {
			return $this->quantidade;
		}

		public function get_total(): float {
			return $this->total;
		}
	}
}

if ( ! class_exists( 'VH_Fake_Data' ) ) {
	/**
	 * Data de criação do pedido com a API que o mapeamento usa (`->date()`).
	 */
	class VH_Fake_Data {

		private string $data;

		public function __construct( string $data ) {
			$this->data = $data;
		}

		public function date( string $formato ): string {
			return gmdate( $formato, strtotime( $this->data ) ?: time() );
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {

		private int $id;
		/** @var array<int, WC_Order_Item_Product> */
		private array $itens = [];
		/** @var array<string, mixed> */
		private array $campos = [];
		/** @var array<string, mixed> */
		private array $metas = [];

		/**
		 * @param array<string, mixed> $campos
		 */
		public function __construct( array $campos = [], int $id = 0 ) {
			$this->id     = $id > 0 ? $id : VH_Fake_WP::novo_id();
			$this->campos = array_merge(
				[
					'numero'            => '1042',
					'data'              => '2026-09-17',
					'nome'              => 'Cliente',
					'sobrenome'         => 'Teste',
					'email'             => 'cliente@exemplo.com',
					'telefone'          => '51999990000',
					'endereco'          => 'Rua das Palmeiras, 123',
					'complemento'       => 'Sala 4',
					'cidade'            => 'Porto Alegre',
					'uf'                => 'RS',
					'cep'               => '90000000',
					'total'             => 99.8,
					'frete'             => 24.5,
					'desconto'          => 0.0,
					'pagamento'         => 'pix',
					'pagamento_titulo'  => 'Pix',
					'metodo_envio'      => 'PAC',
				],
				$campos
			);
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_order_number(): string {
			return (string) $this->campos['numero'];
		}

		public function get_date_created(): VH_Fake_Data {
			return new VH_Fake_Data( (string) $this->campos['data'] );
		}

		public function adicionar_item( WC_Product $produto, int $quantidade, float $total ): void {
			$this->itens[] = new WC_Order_Item_Product( $produto, $quantidade, $total );
		}

		/** @return array<int, WC_Order_Item_Product> */
		public function get_items(): array {
			return $this->itens;
		}

		public function get_billing_first_name(): string {
			return (string) $this->campos['nome'];
		}

		public function get_billing_last_name(): string {
			return (string) $this->campos['sobrenome'];
		}

		public function get_billing_email(): string {
			return (string) $this->campos['email'];
		}

		public function get_billing_phone(): string {
			return (string) $this->campos['telefone'];
		}

		public function get_billing_address_1(): string {
			return (string) $this->campos['endereco'];
		}

		public function get_billing_address_2(): string {
			return (string) $this->campos['complemento'];
		}

		public function get_billing_city(): string {
			return (string) $this->campos['cidade'];
		}

		public function get_billing_state(): string {
			return (string) $this->campos['uf'];
		}

		public function get_billing_postcode(): string {
			return (string) $this->campos['cep'];
		}

		public function get_shipping_first_name(): string {
			return (string) ( $this->campos['entrega_nome'] ?? '' );
		}

		public function get_shipping_last_name(): string {
			return (string) ( $this->campos['entrega_sobrenome'] ?? '' );
		}

		public function get_shipping_address_1(): string {
			return (string) ( $this->campos['entrega_endereco'] ?? '' );
		}

		public function get_shipping_address_2(): string {
			return (string) ( $this->campos['entrega_complemento'] ?? '' );
		}

		public function get_shipping_city(): string {
			return (string) ( $this->campos['entrega_cidade'] ?? '' );
		}

		public function get_shipping_state(): string {
			return (string) ( $this->campos['entrega_uf'] ?? '' );
		}

		public function get_shipping_postcode(): string {
			return (string) ( $this->campos['entrega_cep'] ?? '' );
		}

		public function get_total(): string {
			return (string) $this->campos['total'];
		}

		public function get_shipping_total(): string {
			return (string) $this->campos['frete'];
		}

		public function get_discount_total(): string {
			return (string) $this->campos['desconto'];
		}

		public function get_payment_method(): string {
			return (string) $this->campos['pagamento'];
		}

		public function get_payment_method_title(): string {
			return (string) $this->campos['pagamento_titulo'];
		}

		public function get_shipping_method(): string {
			return (string) $this->campos['metodo_envio'];
		}

		public function get_meta( string $chave, bool $unico = true ): mixed {
			unset( $unico );
			return $this->metas[ $chave ] ?? '';
		}

		public function update_meta_data( string $chave, mixed $valor ): void {
			$this->metas[ $chave ] = $valor;
		}

		public function save(): int {
			VH_Fake_WP::$pedidos[ $this->id ] = $this;
			return $this->id;
		}
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( mixed $id = 0 ): mixed {
		if ( $id instanceof WC_Order ) {
			return $id;
		}
		return VH_Fake_WP::$pedidos[ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( mixed $id = 0 ): mixed {
		if ( $id instanceof WC_Product ) {
			return $id;
		}
		return VH_Fake_WP::$produtos[ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	function wc_get_product_id_by_sku( string $sku ): int {
		if ( '' === $sku ) {
			return 0;
		}
		foreach ( VH_Fake_WP::$produtos as $id => $produto ) {
			if ( 0 === strcasecmp( $produto->get_sku(), $sku ) ) {
				return (int) $id;
			}
		}
		return 0;
	}
}

if ( ! function_exists( 'wc_get_products' ) ) {
	function wc_get_products( array $args = [] ): array {
		$tipos   = (array) ( $args['type'] ?? [ 'simple', 'variable' ] );
		$status  = (array) ( $args['status'] ?? [ 'publish', 'draft' ] );
		$limite  = (int) ( $args['limit'] ?? 10 );
		$pagina  = max( 1, (int) ( $args['page'] ?? 1 ) );
		$retorno = (string) ( $args['return'] ?? 'objects' );

		$encontrados = [];
		foreach ( VH_Fake_WP::$produtos as $produto ) {
			if ( ! in_array( $produto->get_type(), $tipos, true ) ) {
				continue;
			}
			if ( ! in_array( $produto->get_status(), $status, true ) ) {
				continue;
			}
			$encontrados[] = $produto;
		}

		if ( $limite > 0 ) {
			$encontrados = array_slice( $encontrados, ( $pagina - 1 ) * $limite, $limite );
		}

		if ( 'ids' === $retorno ) {
			return array_map( static fn( WC_Product $p ): int => $p->get_id(), $encontrados );
		}
		return $encontrados;
	}
}

if ( ! function_exists( 'wc_update_product_stock' ) ) {
	function wc_update_product_stock( mixed $produto, mixed $quantidade = null, string $operacao = 'set' ): mixed {
		$produto = wc_get_product( $produto );
		if ( ! $produto instanceof WC_Product ) {
			return false;
		}
		$atual = (int) $produto->get_stock_quantity();
		$novo  = match ( $operacao ) {
			'increase' => $atual + (int) $quantidade,
			'decrease' => $atual - (int) $quantidade,
			default    => (int) $quantidade,
		};
		$produto->set_stock_quantity( $novo );
		$produto->save();
		/* Mesma separação do WooCommerce: variação tem hook próprio. */
		do_action(
			$produto->is_type( 'variation' ) ? 'woocommerce_variation_set_stock' : 'woocommerce_product_set_stock',
			$produto
		);
		return $novo;
	}
}

if ( ! function_exists( 'wc_delete_product_transients' ) ) {
	function wc_delete_product_transients( int $produto_id = 0 ): void {
		unset( $produto_id );
	}
}

if ( ! function_exists( 'wc_attribute_label' ) ) {
	function wc_attribute_label( string $nome, mixed $produto = null ): string {
		unset( $produto );
		return ucfirst( str_replace( [ 'pa_', '-', '_' ], [ '', ' ', ' ' ], $nome ) );
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( string $campo, mixed $valor, string $taxonomy = '' ): mixed {
		unset( $campo, $taxonomy );
		$termo = new WP_Term( 0, ucfirst( str_replace( '-', ' ', (string) $valor ) ), (string) $valor );
		return $termo;
	}
}

if ( ! function_exists( 'wc_get_loop_prop' ) ) {
	function wc_get_loop_prop( string $prop, mixed $padrao = '' ): mixed {
		unset( $prop );
		return $padrao;
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( int $post_id, string $taxonomy = '', array $args = [] ): array {
		unset( $post_id, $taxonomy, $args );
		return [];
	}
}

if ( ! function_exists( 'wp_set_object_terms' ) ) {
	function wp_set_object_terms( int $post_id, mixed $termos, string $taxonomy, bool $anexar = false ): array {
		unset( $post_id, $termos, $taxonomy, $anexar );
		return [];
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool {
		return '' !== $taxonomy;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( mixed $id, mixed $tamanho = 'thumbnail' ): string|false {
		unset( $tamanho );
		return wp_get_attachment_url( (int) $id );
	}
}

if ( ! function_exists( 'get_preview_post_link' ) ) {
	function get_preview_post_link( int $id ): string {
		return home_url( '/?p=' . $id . '&preview=true' );
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( int $id ): string|false {
		return $id > 0 ? 'https://piloto.example/wp-content/uploads/img-' . $id . '.jpg' : false;
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( int $id ): string|false {
		return $id > 0 ? 'image/jpeg' : false;
	}
}

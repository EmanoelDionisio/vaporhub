<?php
/**
 * Stand-ins para classes do plugin que não são o alvo dos testes.
 *
 * Declarados ANTES dos arquivos reais: como as classes do plugin não usam
 * namespace, a ordem de inclusão é a costura que permite substituir uma peça
 * sem tornar público nada que hoje é privado.
 *
 * @package VaporHubLoja\Tests
 */

/**
 * Log em memória — mesma API pública do VH_Tiny_Log, sem tabela no banco.
 */
final class VH_Tiny_Log {

	public const NIVEL_ERROR   = 'error';
	public const NIVEL_WARNING = 'warning';
	public const NIVEL_INFO    = 'info';
	public const NIVEL_SUCCESS = 'success';

	public const ORIGEM_API     = 'api';
	public const ORIGEM_QUEUE   = 'queue';
	public const ORIGEM_SYNC    = 'sync';
	public const ORIGEM_WEBHOOK = 'webhook';
	public const ORIGEM_MAPPING = 'mapping';
	public const ORIGEM_OAUTH   = 'oauth';
	public const ORIGEM_CONFIG  = 'config';
	public const ORIGEM_SYSTEM  = 'system';

	/** @var array<int, array{nivel:string, origem:string, referencia:string, mensagem:string, contexto:array<string, mixed>}> */
	public static array $entradas = [];

	public static function reset(): void {
		self::$entradas = [];
	}

	/**
	 * @param array<string, mixed> $contexto
	 */
	public static function registrar( string $nivel, string $origem, string $referencia, string $mensagem, array $contexto = [] ): int {
		self::$entradas[] = [
			'nivel'      => $nivel,
			'origem'     => $origem,
			'referencia' => $referencia,
			'mensagem'   => $mensagem,
			'contexto'   => $contexto,
		];
		return count( self::$entradas );
	}

	/**
	 * @param array<string, mixed> $contexto
	 */
	public static function error( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_ERROR, $origem, $referencia, $mensagem, $contexto );
	}

	/**
	 * @param array<string, mixed> $contexto
	 */
	public static function warning( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_WARNING, $origem, $referencia, $mensagem, $contexto );
	}

	/**
	 * @param array<string, mixed> $contexto
	 */
	public static function info( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_INFO, $origem, $referencia, $mensagem, $contexto );
	}

	/**
	 * @param array<string, mixed> $contexto
	 */
	public static function success( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_SUCCESS, $origem, $referencia, $mensagem, $contexto );
	}

	/**
	 * Entradas registradas, opcionalmente filtradas por nível.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function entradas( string $nivel = '' ): array {
		if ( '' === $nivel ) {
			return self::$entradas;
		}
		return array_values(
			array_filter(
				self::$entradas,
				static fn( array $e ): bool => $e['nivel'] === $nivel
			)
		);
	}

	public static function contem( string $trecho, string $nivel = '' ): bool {
		foreach ( self::entradas( $nivel ) as $entrada ) {
			if ( str_contains( (string) $entrada['mensagem'], $trecho ) ) {
				return true;
			}
		}
		return false;
	}

	public static function formatar_data( string $mysql ): string {
		return $mysql;
	}
}

final class VH_Roles {
	public const CAP = 'gerenciar_vh_loja';
}

/**
 * Só o suficiente para chamar um callback REST direto do teste.
 */
if ( ! class_exists( 'WP_REST_Server' ) ) {
	final class WP_REST_Server {
		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
		public const EDITABLE  = 'POST, PUT, PATCH';
		public const DELETABLE = 'DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {

		/** @var array<string, mixed> */
		private array $params = [];

		/** @var array<string, string> */
		private array $headers = [];

		private string $body = '';

		/**
		 * @param array<string, mixed>  $params
		 * @param array<string, string> $headers
		 */
		public function __construct( array $params = [], array $headers = [], string $body = '' ) {
			$this->params  = $params;
			$this->body    = $body;
			foreach ( $headers as $nome => $valor ) {
				$this->headers[ strtolower( str_replace( '_', '-', $nome ) ) ] = $valor;
			}
		}

		public function get_param( string $chave ): mixed {
			return $this->params[ $chave ] ?? null;
		}

		public function set_param( string $chave, mixed $valor ): void {
			$this->params[ $chave ] = $valor;
		}

		public function get_header( string $nome ): ?string {
			return $this->headers[ strtolower( str_replace( '_', '-', $nome ) ) ] ?? null;
		}

		public function get_body(): string {
			return $this->body;
		}

		/**
		 * @return array<string, mixed>|null
		 */
		public function get_json_params(): ?array {
			$dados = json_decode( $this->body, true );
			return is_array( $dados ) ? $dados : null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {

		public mixed $data;
		public int $status;

		/** @var array<string, string> */
		public array $headers = [];

		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_data(): mixed {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}

		public function header( string $nome, string $valor ): void {
			$this->headers[ $nome ] = $valor;
		}
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/** @var array<int, array{namespace:string, rota:string}> */
	$GLOBALS['vh_rotas_rest'] = [];

	function register_rest_route( string $namespace, string $rota, array $args = [] ): bool {
		unset( $args );
		$GLOBALS['vh_rotas_rest'][] = [
			'namespace' => $namespace,
			'rota'      => $rota,
		];
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		unset( $cap );
		return true;
	}
}

final class VH_Router {
	public static function url( string $rota = '', array $args = [] ): string {
		unset( $args );
		return 'https://piloto.example/minha-loja/' . $rota;
	}
}

/**
 * Só o que o mapeamento canônico usa de categorias.
 */
final class VH_Categories_Service {

	public const TAXONOMIA = 'product_cat';

	public static function caminho_hierarquico( WP_Term $termo ): string {
		$partes = [];
		$atual  = $termo;
		$limite = 0;

		while ( $atual instanceof WP_Term && $limite < 10 ) {
			array_unshift( $partes, $atual->name );
			$pai   = (int) $atual->parent;
			$atual = $pai > 0 ? get_term( $pai, self::TAXONOMIA ) : null;
			++$limite;
		}

		return implode( ' >> ', $partes );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook, array $args = [] ): int|false {
		unset( $hook, $args );
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recorrencia, string $hook ): bool {
		unset( $timestamp, $recorrencia, $hook );
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int {
		unset( $hook, $args );
		return 0;
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): DateTimeZone {
		return new DateTimeZone( 'America/Sao_Paulo' );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $formato, ?int $timestamp = null, ?DateTimeZone $fuso = null ): string|false {
		$data = new DateTimeImmutable( '@' . ( $timestamp ?? time() ) );
		return $data->setTimezone( $fuso ?? wp_timezone() )->format( $formato );
	}
}

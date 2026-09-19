<?php
/**
 * Transporte HTTP falso — mesma costura que o `pre_http_request` do WordPress.
 *
 * O cliente real (VH_Tiny_Client) continua no comando: OAuth, refresh de token,
 * tratamento de 4xx/5xx e log. Só a rede é substituída, então os testes exercem
 * o código de verdade e afirmam sobre a requisição que teria saído.
 *
 * @package VaporHubLoja\Tests
 */

final class VH_Fake_HTTP {

	/** @var array<int, array{metodo:string, url:string, endpoint:string, query:array<string,mixed>, body:?array<string,mixed>}> */
	public static array $requisicoes = [];

	/** @var callable|null */
	public static $roteador = null;

	public static function reset(): void {
		self::$requisicoes = [];
		self::$roteador    = null;
	}

	/**
	 * Requisições registradas, opcionalmente filtradas por método e/ou trecho do endpoint.
	 *
	 * @return array<int, array{metodo:string, url:string, endpoint:string, query:array<string,mixed>, body:?array<string,mixed>}>
	 */
	public static function requisicoes( string $metodo = '', string $endpoint = '' ): array {
		$saida = [];
		foreach ( self::$requisicoes as $req ) {
			if ( '' !== $metodo && strtoupper( $metodo ) !== $req['metodo'] ) {
				continue;
			}
			if ( '' !== $endpoint && ! str_starts_with( $req['endpoint'], $endpoint ) ) {
				continue;
			}
			$saida[] = $req;
		}
		return $saida;
	}

	public static function contar( string $metodo = '', string $endpoint = '' ): int {
		return count( self::requisicoes( $metodo, $endpoint ) );
	}

	/**
	 * Última requisição registrada para o par método/endpoint.
	 *
	 * @return array{metodo:string, url:string, endpoint:string, query:array<string,mixed>, body:?array<string,mixed>}|null
	 */
	public static function ultima( string $metodo = '', string $endpoint = '' ): ?array {
		$reqs = self::requisicoes( $metodo, $endpoint );
		return $reqs ? $reqs[ count( $reqs ) - 1 ] : null;
	}

	/**
	 * Corpo enviado na última requisição do par método/endpoint.
	 *
	 * @return array<string, mixed>
	 */
	public static function ultimo_corpo( string $metodo, string $endpoint = '' ): array {
		$req = self::ultima( $metodo, $endpoint );
		return is_array( $req['body'] ?? null ) ? $req['body'] : [];
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function despachar( string $url, array $args ): array {
		$metodo = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$partes = parse_url( $url );
		$caminho = (string) ( $partes['path'] ?? '' );
		$query  = [];
		if ( ! empty( $partes['query'] ) ) {
			parse_str( (string) $partes['query'], $query );
		}

		$endpoint = ltrim( str_replace( '/public-api/v3', '', $caminho ), '/' );

		$body = null;
		if ( isset( $args['body'] ) ) {
			if ( is_array( $args['body'] ) ) {
				$body = $args['body'];
			} elseif ( is_string( $args['body'] ) ) {
				$decodificado = json_decode( $args['body'], true );
				$body         = is_array( $decodificado ) ? $decodificado : [ 'raw' => $args['body'] ];
			}
		}

		self::$requisicoes[] = [
			'metodo'   => $metodo,
			'url'      => $url,
			'endpoint' => $endpoint,
			'query'    => $query,
			'body'     => $body,
		];

		$roteador = self::$roteador ?? [ 'VH_Fake_Tiny_ERP', 'responder' ];
		$resposta = $roteador( $metodo, $endpoint, $query, $body, $url );

		$status = (int) ( $resposta['status'] ?? 200 );
		$corpo  = $resposta['body'] ?? [];

		return [
			'response' => [ 'code' => $status ],
			'body'     => is_string( $corpo ) ? $corpo : (string) wp_json_encode( $corpo ),
			'headers'  => $resposta['headers'] ?? [],
		];
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( string $url, array $args = [] ): mixed {
		return VH_Fake_HTTP::despachar( $url, $args );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = [] ): mixed {
		$args['method'] = 'POST';
		return VH_Fake_HTTP::despachar( $url, $args );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = [] ): mixed {
		$args['method'] = 'GET';
		return VH_Fake_HTTP::despachar( $url, $args );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( mixed $resposta ): int {
		return (int) ( $resposta['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( mixed $resposta ): string {
		return (string) ( $resposta['body'] ?? '' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( mixed $resposta, string $header ): string {
		return (string) ( $resposta['headers'][ strtolower( $header ) ] ?? '' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( mixed ...$args ): string {
		if ( is_array( $args[0] ) ) {
			$params = $args[0];
			$url    = (string) ( $args[1] ?? '' );
		} else {
			$params = [ (string) $args[0] => $args[1] ?? '' ];
			$url    = (string) ( $args[2] ?? '' );
		}

		$separador = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separador . http_build_query( $params );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return 1;
	}
}

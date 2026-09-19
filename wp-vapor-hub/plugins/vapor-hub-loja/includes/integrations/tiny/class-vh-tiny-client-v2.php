<?php
/**
 * Cliente HTTP — Tiny ERP API v2 (Token).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_Client_V2 {

	private const API_BASE    = 'https://api.tiny.com.br/api2/';
	private const MAX_RETRIES = 2;

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	public static function requisicao( string $endpoint, array $params = [], int $tentativa = 0 ) {
		$token = self::token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		return self::requisicao_com_token( $endpoint, $token, $params, $tentativa, true );
	}

	/**
	 * Teste seco — não grava token nem altera status de conexão.
	 *
	 * @return true|WP_Error
	 */
	public static function testar_token( string $token_bruto ) {
		$token = trim( $token_bruto );
		if ( '' === $token ) {
			return new WP_Error(
				'vh_tiny_v2_token_vazio',
				__( 'Informe o token API v2 para testar.', 'vapor-hub-loja' ),
				[ 'status' => 400 ]
			);
		}

		$resp = self::requisicao_com_token(
			'produtos.pesquisa.php',
			$token,
			[ 'pesquisa' => ' ', 'pagina' => 1 ],
			0,
			false
		);

		return is_wp_error( $resp ) ? $resp : true;
	}

	/**
	 * Salva token criptografado e marca conexão após validar na API.
	 *
	 * @return true|WP_Error
	 */
	public static function salvar_e_conectar( string $token_bruto ) {
		$token = trim( $token_bruto );
		if ( '' === $token ) {
			return new WP_Error(
				'vh_tiny_v2_token_vazio',
				__( 'Informe o token API v2.', 'vapor-hub-loja' ),
				[ 'status' => 400 ]
			);
		}

		self::salvar_token( $token );

		$resp = self::requisicao( 'produtos.pesquisa.php', [ 'pesquisa' => ' ', 'pagina' => 1 ] );
		if ( is_wp_error( $resp ) ) {
			self::marcar_conectado( false );
			return $resp;
		}

		self::marcar_conectado( true );
		return true;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	private static function requisicao_com_token(
		string $endpoint,
		string $token,
		array $params = [],
		int $tentativa = 0,
		bool $persistir_erro = true
	) {
		$body = array_merge(
			[
				'token'   => $token,
				'formato' => 'JSON',
			],
			$params
		);

		$resp = wp_remote_post(
			self::API_BASE . ltrim( $endpoint, '/' ),
			[
				'timeout' => 25,
				'body'    => $body,
			]
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		unset( $code );
		$raw = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'vh_tiny_v2_json', __( 'Resposta inválida da API Tiny v2.', 'vapor-hub-loja' ) );
		}

		$retorno = $raw['retorno'] ?? $raw;
		if ( ! is_array( $retorno ) ) {
			return new WP_Error( 'vh_tiny_v2_formato', __( 'Formato de resposta Tiny v2 inesperado.', 'vapor-hub-loja' ) );
		}

		$status = strtoupper( (string) ( $retorno['status'] ?? '' ) );
		if ( 'OK' !== $status && 'SUCESSO' !== $status ) {
			$codigo = (int) ( $retorno['codigo_erro'] ?? 0 );
			if ( 6 === $codigo && $tentativa < self::MAX_RETRIES ) {
				sleep( min( 30, ( 2 ** $tentativa ) * 5 ) );
				return self::requisicao_com_token( $endpoint, $token, $params, $tentativa + 1, $persistir_erro );
			}
			$msg = self::extrair_erro( $retorno );
			if ( $persistir_erro ) {
				VH_Tiny::registrar_erro( $msg );
			}
			return new WP_Error( 'vh_tiny_v2_api', $msg, [ 'status' => 502, 'codigo' => $codigo, 'body' => $retorno ] );
		}

		if ( $persistir_erro ) {
			VH_Tiny::limpar_erro();
		}
		return $retorno;
	}

	/**
	 * @return string|WP_Error
	 */
	public static function token() {
		$dados = VH_Tiny::obter();
		if ( empty( $dados['v2_token'] ) ) {
			return new WP_Error(
				'vh_tiny_v2_sem_token',
				__( 'Token API v2 não configurado.', 'vapor-hub-loja' ),
				[ 'status' => 400 ]
			);
		}
		$token = VH_SEO_Crypto::descriptografar( (string) $dados['v2_token'] );
		if ( '' === $token ) {
			return new WP_Error(
				'vh_tiny_v2_token_invalido',
				__( 'Token API v2 inválido. Salve novamente o token do Tiny.', 'vapor-hub-loja' ),
				[ 'status' => 400 ]
			);
		}
		return $token;
	}

	public static function salvar_token( string $token ): void {
		$dados = VH_Tiny::obter();
		$dados['v2_token']     = VH_SEO_Crypto::criptografar( trim( $token ) );
		$dados['v2_conectado'] = false;
		update_option( VH_Tiny::OPTION, $dados, false );
	}

	public static function marcar_conectado( bool $ok, string $conta = '' ): void {
		$dados = VH_Tiny::obter();
		$dados['v2_conectado'] = $ok;
		if ( '' !== $conta ) {
			$dados['v2_conta'] = sanitize_text_field( $conta );
		}
		update_option( VH_Tiny::OPTION, $dados, false );

		if ( $ok ) {
			VH_Tiny::garantir_webhook_token();
		}
	}

	public static function desconectar(): void {
		$dados = VH_Tiny::obter();
		unset( $dados['v2_token'], $dados['v2_conectado'], $dados['v2_conta'] );
		update_option( VH_Tiny::OPTION, $dados, false );
	}

	public static function conectado(): bool {
		$dados = VH_Tiny::obter();
		return ! empty( $dados['v2_token'] ) && ! empty( $dados['v2_conectado'] );
	}

	public static function credenciais_configuradas(): bool {
		$dados = VH_Tiny::obter();
		return ! empty( $dados['v2_token'] );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function testar_conexao() {
		$resp = self::requisicao( 'produtos.pesquisa.php', [ 'pesquisa' => ' ', 'pagina' => 1 ] );
		if ( is_wp_error( $resp ) ) {
			self::marcar_conectado( false );
			return $resp;
		}
		self::marcar_conectado( true );
		return true;
	}

	/**
	 * @param array<string, mixed> $retorno
	 */
	private static function extrair_erro( array $retorno ): string {
		if ( ! empty( $retorno['erros'] ) && is_array( $retorno['erros'] ) ) {
			$primeiro = reset( $retorno['erros'] );
			if ( is_array( $primeiro ) && ! empty( $primeiro['erro'] ) ) {
				return sanitize_text_field( (string) $primeiro['erro'] );
			}
			if ( is_string( $primeiro ) ) {
				return sanitize_text_field( $primeiro );
			}
		}
		if ( ! empty( $retorno['registros'] ) && is_array( $retorno['registros'] ) ) {
			foreach ( $retorno['registros'] as $reg ) {
				if ( is_array( $reg ) && ! empty( $reg['registro']['status'] ) && 'Erro' === $reg['registro']['status'] ) {
					return sanitize_text_field( (string) ( $reg['registro']['erros'][0]['erro'] ?? __( 'Erro na API Tiny v2.', 'vapor-hub-loja' ) ) );
				}
			}
		}
		return __( 'Erro na API Tiny v2.', 'vapor-hub-loja' );
	}
}

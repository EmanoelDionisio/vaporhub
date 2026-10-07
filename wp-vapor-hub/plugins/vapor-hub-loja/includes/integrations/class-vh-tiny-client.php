<?php
/**
 * Cliente OAuth 2.0 + HTTP — Tiny / Olist ERP API v3.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_Client {

	public const OPTION = 'vh_tiny_erp';

	private const AUTH_URL   = 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth';
	private const TOKEN_URL  = 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token';
	private const REVOKE_URL = 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/logout';
	private const API_BASE   = 'https://api.tiny.com.br/public-api/v3';

	private const MAX_RETRIES = 2;
	private const INTERVALO   = 2;
	private const PROXIMA     = 'vh_tiny_api_proxima';

	/**
	 * @return array<string, mixed>
	 */
	public static function obter(): array {
		$salvo = get_option( self::OPTION, [] );
		return is_array( $salvo ) ? $salvo : [];
	}

	public static function redirect_uri(): string {
		return rest_url( VH_REST_Controller::NS . '/tiny/callback' );
	}

	public static function webhook_url(): string {
		return VH_Tiny::webhook_url();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function padrao(): array {
		return [
			'ativo'              => false,
			'sinc_auto'          => true,
			'permitir_envio'       => true,
			/*
			 * A loja é a dona do catálogo e do estoque. Receber do ERP é opt-in:
			 * este padrão é gravado na opção ao salvar credenciais, e um `true`
			 * aqui ligaria a entrada sem ninguém pedir.
			 */
			'permitir_recebimento' => false,
			'client_id'          => '',
			'client_secret'      => '',
			'access_token'       => '',
			'refresh_token'      => '',
			'expires_at'         => 0,
			'connected_at'       => '',
			'conta_nome'         => '',
			'conta_cnpj'         => '',
			'conta_bloqueada'    => false,
			'webhook_token_hash' => '',
			'webhook_token_cifrado' => '',
			'ultima_reconciliacao' => '',
			'ultimo_erro'        => '',
		];
	}

	public static function salvar_credenciais( string $client_id, string $client_secret = '' ): void {
		$dados = wp_parse_args( self::obter(), self::padrao() );
		$dados['client_id'] = sanitize_text_field( $client_id );

		if ( '' !== trim( $client_secret ) ) {
			$dados['client_secret'] = VH_SEO_Crypto::criptografar( trim( $client_secret ) );
		}

		update_option( self::OPTION, $dados, false );
	}

	public static function salvar_config( array $config ): void {
		$dados = wp_parse_args( self::obter(), self::padrao() );
		if ( array_key_exists( 'ativo', $config ) ) {
			$dados['ativo'] = ! empty( $config['ativo'] );
		}
		if ( array_key_exists( 'sinc_auto', $config ) ) {
			$dados['sinc_auto'] = ! empty( $config['sinc_auto'] );
		}
		if ( array_key_exists( 'permitir_envio', $config ) ) {
			$dados['permitir_envio'] = ! empty( $config['permitir_envio'] );
		}
		if ( array_key_exists( 'permitir_recebimento', $config ) ) {
			$dados['permitir_recebimento']  = ! empty( $config['permitir_recebimento'] );
			$dados['receber_catalogo']      = $dados['permitir_recebimento'];
			$dados['receber_estoque_preco'] = $dados['permitir_recebimento'];
		}
		update_option( self::OPTION, $dados, false );
	}

	public static function client_secret(): string {
		$dados = self::obter();
		if ( empty( $dados['client_secret'] ) ) {
			return '';
		}
		return VH_SEO_Crypto::descriptografar( (string) $dados['client_secret'] );
	}

	public static function credenciais_configuradas(): bool {
		$dados = self::obter();
		return '' !== (string) ( $dados['client_id'] ?? '' ) && ! empty( $dados['client_secret'] );
	}

	public static function tem_tokens(): bool {
		$dados = self::obter();
		return ! empty( $dados['refresh_token'] ) || ! empty( $dados['access_token'] );
	}

	/**
	 * Conexão OAuth operacional (tokens válidos ou renováveis).
	 */
	public static function conectado(): bool {
		return self::verificar_conexao();
	}

	/**
	 * Valida tokens OAuth — espelha o fluxo da API v2 (testar antes de exibir “Conectado”).
	 */
	public static function verificar_conexao(): bool {
		if ( ! self::tem_tokens() ) {
			return false;
		}

		$dados = self::obter();

		/*
		 * Trava de CNPJ: a loja espelha uma empresa só. Enquanto a conta do outro
		 * lado não for a travada, a integração fica parada — sem chamada de rede
		 * aqui, porque conectado() roda em toda tela do painel. Quem confere de
		 * fato é verificar_conta(), chamada no teste de conexão, na reconexão e
		 * antes da reconciliação.
		 */
		if ( ! empty( $dados['conta_bloqueada'] ) ) {
			return false;
		}

		$expira = (int) ( $dados['expires_at'] ?? 0 );

		if ( $expira > time() + 60 && ! empty( $dados['access_token'] ) ) {
			$token = VH_SEO_Crypto::descriptografar( (string) $dados['access_token'] );
			return '' !== $token;
		}

		return ! is_wp_error( self::access_token() );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function testar_conexao() {
		if ( ! self::tem_tokens() ) {
			return new WP_Error(
				'vh_tiny_desconectado',
				__( 'Tiny ERP não conectado.', 'vapor-hub-loja' ),
				[ 'status' => 400 ]
			);
		}

		$resp = self::requisicao( 'GET', 'info' );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$conta = self::conferir_conta( is_array( $resp ) ? $resp : [] );
		if ( is_wp_error( $conta ) ) {
			return $conta;
		}

		self::limpar_erro();
		return true;
	}

	/**
	 * Confere no ERP se a conta conectada é a travada.
	 *
	 * Consulta `GET info` e compara o CNPJ. Divergência bloqueia a integração e
	 * registra o motivo; coincidência libera. Sem CNPJ travado ainda, grava o
	 * atual (primeira conexão de uma instalação já autenticada).
	 */
	public static function verificar_conta(): bool {
		if ( ! self::tem_tokens() ) {
			return false;
		}

		$resp = self::requisicao( 'GET', 'info' );
		if ( is_wp_error( $resp ) ) {
			return false;
		}

		return ! is_wp_error( self::conferir_conta( is_array( $resp ) ? $resp : [] ) );
	}

	public static function conta_bloqueada(): bool {
		return ! empty( self::obter()['conta_bloqueada'] );
	}

	/**
	 * Compara a resposta de `GET info` com o CNPJ travado.
	 *
	 * @param array<string, mixed> $info
	 * @return true|WP_Error
	 */
	private static function conferir_conta( array $info ) {
		$dados   = wp_parse_args( self::obter(), self::padrao() );
		$travado = self::normalizar_cnpj( (string) ( $dados['conta_cnpj'] ?? '' ) );
		$atual   = self::normalizar_cnpj( (string) ( $info['cpfCnpj'] ?? $info['cnpj'] ?? '' ) );
		$nome    = sanitize_text_field( (string) ( $info['nome'] ?? $info['razaoSocial'] ?? $info['nomeFantasia'] ?? '' ) );

		if ( '' === $atual ) {
			/* Conta sem CNPJ na resposta: não há o que comparar, segue o jogo. */
			return true;
		}

		if ( '' === $travado ) {
			$dados['conta_cnpj']      = $atual;
			$dados['conta_bloqueada'] = false;
			if ( '' !== $nome ) {
				$dados['conta_nome'] = $nome;
			}
			update_option( self::OPTION, $dados, false );
			return true;
		}

		if ( $travado !== $atual ) {
			$dados['conta_bloqueada'] = true;
			update_option( self::OPTION, $dados, false );

			$mensagem = sprintf(
				/* translators: 1: locked CNPJ, 2: CNPJ returned by the ERP */
				__( 'Conta Tiny diferente da travada: esperado CNPJ %1$s, recebido %2$s. Sincronização bloqueada até reconectar a empresa correta.', 'vapor-hub-loja' ),
				self::formatar_cnpj( $travado ),
				self::formatar_cnpj( $atual )
			);
			self::registrar_erro( $mensagem );

			return new WP_Error( 'vh_tiny_conta_diferente', $mensagem, [ 'status' => 409 ] );
		}

		if ( ! empty( $dados['conta_bloqueada'] ) || ( '' !== $nome && $nome !== (string) ( $dados['conta_nome'] ?? '' ) ) ) {
			$dados['conta_bloqueada'] = false;
			if ( '' !== $nome ) {
				$dados['conta_nome'] = $nome;
			}
			update_option( self::OPTION, $dados, false );
		}

		return true;
	}

	public static function normalizar_cnpj( string $valor ): string {
		return preg_replace( '/\D/', '', $valor ) ?? '';
	}

	public static function formatar_cnpj( string $valor ): string {
		$digitos = self::normalizar_cnpj( $valor );

		if ( 14 === strlen( $digitos ) ) {
			return preg_replace( '/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digitos ) ?? $digitos;
		}
		if ( 11 === strlen( $digitos ) ) {
			return preg_replace( '/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digitos ) ?? $digitos;
		}

		return $digitos;
	}

	/**
	 * Libera a trava para conectar outra empresa (ação explícita do lojista).
	 */
	public static function liberar_trava_conta(): void {
		$dados                    = wp_parse_args( self::obter(), self::padrao() );
		$dados['conta_cnpj']      = '';
		$dados['conta_bloqueada'] = false;
		update_option( self::OPTION, $dados, false );
		self::limpar_erro();
	}

	public static function ativo(): bool {
		$dados = self::obter();
		return self::conectado() && ! empty( $dados['ativo'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function status_publico(): array {
		$dados = wp_parse_args( self::obter(), self::padrao() );
		$fila  = class_exists( 'VH_Tiny_Queue' ) ? VH_Tiny_Queue::contagem_por_status() : [];

		return [
			'credenciais_ok'       => self::credenciais_configuradas(),
			'conectado'            => self::conectado(),
			'ativo'                => ! empty( $dados['ativo'] ),
			'sinc_auto'            => ! empty( $dados['sinc_auto'] ),
			'client_id'            => (string) ( $dados['client_id'] ?? '' ),
			'redirect_uri'         => self::redirect_uri(),
			'webhook_url'          => self::webhook_url(),
			'conta_nome'           => (string) ( $dados['conta_nome'] ?? '' ),
			'connected_at'         => (string) ( $dados['connected_at'] ?? '' ),
			'ultima_reconciliacao' => (string) ( $dados['ultima_reconciliacao'] ?? '' ),
			'ultimo_erro'          => (string) ( $dados['ultimo_erro'] ?? '' ),
			'fila'                 => $fila,
			'pendencias'           => class_exists( 'VH_Tiny_Sync_Service' ) ? VH_Tiny_Sync_Service::contar_pendencias() : 0,
		];
	}

	/**
	 * @return string|WP_Error
	 */
	public static function url_autorizacao() {
		if ( ! self::credenciais_configuradas() ) {
			return new WP_Error( 'vh_tiny_sem_credenciais', __( 'Informe Client ID e Client Secret antes de conectar.', 'vapor-hub-loja' ) );
		}

		$dados   = self::obter();
		$state   = wp_generate_password( 32, false, false );
		$user_id = get_current_user_id();

		set_transient( 'vh_tiny_oauth_' . $state, $user_id, 10 * MINUTE_IN_SECONDS );

		$params = [
			'client_id'     => $dados['client_id'] ?? '',
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'openid',
			'state'         => $state,
		];

		return add_query_arg( $params, self::AUTH_URL );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function processar_callback( string $code, string $state ) {
		$code  = sanitize_text_field( $code );
		$state = sanitize_text_field( $state );

		if ( '' === $code || '' === $state ) {
			return new WP_Error( 'vh_tiny_oauth_invalido', __( 'Resposta OAuth incompleta.', 'vapor-hub-loja' ) );
		}

		$user_id = get_transient( 'vh_tiny_oauth_' . $state );
		delete_transient( 'vh_tiny_oauth_' . $state );

		if ( ! $user_id || (int) $user_id <= 0 ) {
			return new WP_Error( 'vh_tiny_oauth_state', __( 'Sessão OAuth expirada ou inválida. Tente conectar novamente.', 'vapor-hub-loja' ) );
		}

		$tokens = self::trocar_codigo( $code );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		$dados = wp_parse_args( self::obter(), self::padrao() );
		$dados['access_token']  = VH_SEO_Crypto::criptografar( (string) $tokens['access_token'] );
		$dados['expires_at']    = time() + (int) ( $tokens['expires_in'] ?? 3600 );
		if ( ! empty( $tokens['refresh_token'] ) ) {
			$dados['refresh_token'] = VH_SEO_Crypto::criptografar( (string) $tokens['refresh_token'] );
		}
		$dados['connected_at'] = gmdate( 'c' );
		$dados['ativo']        = true;

		update_option( self::OPTION, $dados, false );

		VH_Tiny::garantir_webhook_token();
		$dados = wp_parse_args( self::obter(), self::padrao() );

		$conta = self::requisicao( 'GET', 'info' );
		if ( ! is_wp_error( $conta ) ) {
			$nome = (string) ( $conta['nome'] ?? $conta['razaoSocial'] ?? $conta['nomeFantasia'] ?? '' );
			if ( '' !== $nome ) {
				$dados['conta_nome'] = sanitize_text_field( $nome );
				update_option( self::OPTION, $dados, false );
			}

			/*
			 * Reconectar com outra empresa é o cenário que mais dói: o catálogo do
			 * outro CNPJ passaria a ser espelhado aqui. Recusa e derruba os tokens.
			 */
			$travada = self::conferir_conta( is_array( $conta ) ? $conta : [] );
			if ( is_wp_error( $travada ) ) {
				self::invalidar_tokens_oauth();
				return $travada;
			}
		}

		return true;
	}

	public static function desconectar(): void {
		self::invalidar_tokens_oauth();
		$dados          = wp_parse_args( self::obter(), self::padrao() );
		$dados['ativo'] = false;
		update_option( self::OPTION, $dados, false );
	}

	/**
	 * Remove tokens OAuth inválidos; preserva credenciais e demais configurações.
	 */
	public static function invalidar_tokens_oauth( string $motivo = '' ): void {
		$dados = wp_parse_args( self::obter(), self::padrao() );
		unset( $dados['access_token'], $dados['refresh_token'], $dados['expires_at'], $dados['connected_at'], $dados['conta_nome'] );
		update_option( self::OPTION, $dados, false );

		if ( '' !== trim( $motivo ) ) {
			self::registrar_erro( $motivo );
		}
	}

	public static function registrar_erro( string $mensagem ): void {
		$dados = wp_parse_args( self::obter(), self::padrao() );
		$dados['ultimo_erro'] = sanitize_text_field( mb_substr( $mensagem, 0, 500 ) );
		update_option( self::OPTION, $dados, false );
		VH_Tiny_Log::error( VH_Tiny_Log::ORIGEM_API, $mensagem );
	}

	public static function limpar_erro(): void {
		$dados = wp_parse_args( self::obter(), self::padrao() );
		$dados['ultimo_erro'] = '';
		update_option( self::OPTION, $dados, false );
	}

	/**
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$dados  = self::obter();
		$expira = (int) ( $dados['expires_at'] ?? 0 );

		if ( ! empty( $dados['access_token'] ) && $expira > time() + 60 ) {
			$token = VH_SEO_Crypto::descriptografar( (string) $dados['access_token'] );
			if ( '' !== $token ) {
				return $token;
			}
		}

		return self::renovar_token();
	}

	/**
	 * Requisição autenticada à API Tiny v3.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|WP_Error
	 */
	public static function requisicao( string $metodo, string $endpoint, array $args = [], int $tentativa = 0 ) {
		if ( ! self::tem_tokens() ) {
			return new WP_Error( 'vh_tiny_desconectado', __( 'Tiny ERP não conectado.', 'vapor-hub-loja' ) );
		}

		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$janela = self::reservar_janela();
		if ( is_wp_error( $janela ) ) {
			return $janela;
		}

		$url = self::API_BASE . '/' . ltrim( $endpoint, '/' );

		$req = [
			'method'  => strtoupper( $metodo ),
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
		];

		if ( ! empty( $args['query'] ) && is_array( $args['query'] ) ) {
			$url = add_query_arg( $args['query'], $url );
		}

		if ( isset( $args['body'] ) ) {
			$req['headers']['Content-Type'] = 'application/json';
			$req['body']                    = wp_json_encode( $args['body'] );
		}

		$resp = wp_remote_request( $url, $req );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( 429 === $code ) {
			$retry = (int) wp_remote_retrieve_header( $resp, 'retry-after' );
			if ( $retry <= 0 ) {
				$retry = 20;
			}
			$retry = min( 60, max( 5, $retry ) );
			set_transient( self::PROXIMA, time() + $retry, $retry + 5 );
			return new WP_Error(
				'vh_tiny_limite',
				sprintf(
					/* translators: %d: seconds to wait */
					__( 'O Tiny limitou as consultas. A importação pausa %d segundos e continua no mesmo produto.', 'vapor-hub-loja' ),
					$retry
				),
				[ 'status' => 429, 'retry_after' => $retry ]
			);
		}

		if ( 401 === $code && 0 === $tentativa ) {
			$novo = self::renovar_token();
			if ( is_wp_error( $novo ) ) {
				return $novo;
			}
			return self::requisicao( $metodo, $endpoint, $args, $tentativa + 1 );
		}

		if ( $code >= 400 ) {
			$msg = self::extrair_mensagem_erro( $body, $code );
			self::registrar_erro( $msg );
			self::registrar_log_api( $metodo, $endpoint, $code, $msg, $body );
			return new WP_Error( 'vh_tiny_api', $msg, [ 'status' => $code, 'body' => $body ] );
		}

		self::limpar_erro();
		return is_array( $body ) ? $body : [];
	}

	/**
	 * Uma consulta a cada 2 segundos. Se o Tiny já pediu uma pausa maior, devolve a espera sem chamar a API.
	 *
	 * @return true|WP_Error
	 */
	private static function reservar_janela(): true|WP_Error {
		$espera = (int) get_transient( self::PROXIMA ) - time();
		if ( $espera > self::INTERVALO ) {
			return new WP_Error(
				'vh_tiny_limite',
				sprintf(
					/* translators: %d: seconds to wait */
					__( 'O Tiny limitou as consultas. A importação pausa %d segundos e continua no mesmo produto.', 'vapor-hub-loja' ),
					$espera
				),
				[ 'status' => 429, 'retry_after' => $espera ]
			);
		}
		if ( $espera > 0 && ! defined( 'VH_TEST' ) ) {
			sleep( $espera );
		}
		if ( ! defined( 'VH_TEST' ) ) {
			set_transient( self::PROXIMA, time() + self::INTERVALO, 30 );
		}
		return true;
	}

	/**
	 * @param array<string, mixed>|null $body
	 */
	private static function extrair_mensagem_erro( $body, int $code ): string {
		$base = '';
		if ( is_array( $body ) ) {
			foreach ( [ 'message', 'mensagem', 'descricao', 'error_description', 'error' ] as $chave ) {
				if ( ! empty( $body[ $chave ] ) && is_string( $body[ $chave ] ) ) {
					$base = sanitize_text_field( $body[ $chave ] );
					break;
				}
			}
			if ( '' === $base && ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
				$primeiro = reset( $body['errors'] );
				if ( is_string( $primeiro ) ) {
					$base = sanitize_text_field( $primeiro );
				}
			}
		}

		$detalhes = self::formatar_detalhes_erro( $body );
		if ( '' !== $detalhes ) {
			return '' !== $base ? $base . ' — ' . $detalhes : $detalhes;
		}

		if ( '' !== $base ) {
			return $base;
		}

		/* translators: %d: HTTP status code */
		return sprintf( __( 'Erro na API Tiny (HTTP %d).', 'vapor-hub-loja' ), $code );
	}

	/**
	 * Extrai detalhes de validação da resposta Tiny (campo + mensagem).
	 *
	 * @param array<string, mixed>|null $body
	 */
	public static function formatar_detalhes_erro( $body ): string {
		if ( ! is_array( $body ) || empty( $body['detalhes'] ) || ! is_array( $body['detalhes'] ) ) {
			return '';
		}

		$partes = [];
		foreach ( $body['detalhes'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$campo = sanitize_text_field( (string) ( $item['campo'] ?? $item['field'] ?? '' ) );
			$msg   = sanitize_text_field( (string) ( $item['mensagem'] ?? $item['message'] ?? '' ) );
			if ( '' === $campo && '' === $msg ) {
				continue;
			}
			$partes[] = '' !== $campo ? $campo . ': ' . $msg : $msg;
		}

		return implode( '; ', $partes );
	}

	/**
	 * @param array<string, mixed>|null $body
	 */
	private static function registrar_log_api( string $metodo, string $endpoint, int $code, string $msg, $body ): void {
		if ( ! class_exists( 'VH_Tiny_Log' ) ) {
			return;
		}

		$contexto = [
			'http_status' => $code,
			'metodo'      => $metodo,
			'endpoint'    => $endpoint,
		];

		if ( is_array( $body ) ) {
			$detalhes = self::formatar_detalhes_erro( $body );
			if ( '' !== $detalhes ) {
				$contexto['detalhes'] = $detalhes;
			}
			if ( ! empty( $body['detalhes'] ) && is_array( $body['detalhes'] ) ) {
				$contexto['api_detalhes'] = $body['detalhes'];
			}
		}

		VH_Tiny_Log::error(
			VH_Tiny_Log::ORIGEM_API,
			sprintf(
				/* translators: 1: HTTP method, 2: API endpoint, 3: error message */
				__( 'Tiny %1$s %2$s — %3$s', 'vapor-hub-loja' ),
				$metodo,
				$endpoint,
				$msg
			),
			'',
			$contexto
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function trocar_codigo( string $code ) {
		$dados = self::obter();
		$resp  = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => [
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'client_id'     => $dados['client_id'] ?? '',
					'client_secret' => self::client_secret(),
					'redirect_uri'  => self::redirect_uri(),
				],
			]
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = is_array( $body ) && ! empty( $body['error_description'] )
				? (string) $body['error_description']
				: __( 'Não foi possível obter tokens do Tiny.', 'vapor-hub-loja' );
			return new WP_Error( 'vh_tiny_token', $msg );
		}

		return $body;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function renovar_token() {
		$dados = self::obter();
		if ( empty( $dados['refresh_token'] ) ) {
			return new WP_Error( 'vh_tiny_sem_refresh', __( 'Conexão expirada. Conecte novamente ao Tiny.', 'vapor-hub-loja' ) );
		}

		$refresh = VH_SEO_Crypto::descriptografar( (string) $dados['refresh_token'] );
		if ( '' === $refresh ) {
			$msg = __( 'Conexão inválida. Conecte novamente ao Tiny.', 'vapor-hub-loja' );
			self::invalidar_tokens_oauth( $msg );
			return new WP_Error( 'vh_tiny_sem_refresh', $msg );
		}

		$resp = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => [
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh,
					'client_id'     => $dados['client_id'] ?? '',
					'client_secret' => self::client_secret(),
				],
			]
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = __( 'Falha ao renovar acesso. Conecte novamente.', 'vapor-hub-loja' );
			if ( self::oauth_falha_permanente( $body ) ) {
				self::invalidar_tokens_oauth( $msg );
			}
			return new WP_Error( 'vh_tiny_refresh', $msg );
		}

		$dados['access_token'] = VH_SEO_Crypto::criptografar( (string) $body['access_token'] );
		$dados['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 );
		if ( ! empty( $body['refresh_token'] ) ) {
			$dados['refresh_token'] = VH_SEO_Crypto::criptografar( (string) $body['refresh_token'] );
		}
		update_option( self::OPTION, $dados, false );

		return (string) $body['access_token'];
	}

	/**
	 * @param array<string, mixed>|null $body
	 */
	private static function oauth_falha_permanente( $body ): bool {
		if ( ! is_array( $body ) ) {
			return false;
		}

		$error = strtolower( sanitize_key( (string) ( $body['error'] ?? '' ) ) );
		return in_array( $error, [ 'invalid_grant', 'invalid_client', 'unauthorized_client' ], true );
	}
}

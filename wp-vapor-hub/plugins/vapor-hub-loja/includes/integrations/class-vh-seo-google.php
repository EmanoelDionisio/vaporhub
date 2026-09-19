<?php
/**
 * OAuth 2.0 Google — Search Console + Analytics (Modelo B: app próprio da loja).
 *
 * Fluxo: credenciais no painel → botão Conectar → consentimento Google → callback
 * REST → tokens criptografados. Leitura de métricas usa refresh automático.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_SEO_Google {

	public const OPTION = 'vh_seo_google';

	private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

	private const SCOPES = [
		'https://www.googleapis.com/auth/webmasters.readonly',
		'https://www.googleapis.com/auth/analytics.readonly',
	];

	/**
	 * URI de redirecionamento registrada no Google Cloud Console.
	 */
	public static function redirect_uri(): string {
		return rest_url( VH_REST_Controller::NS . '/seo/google/callback' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function obter(): array {
		$salvo = get_option( self::OPTION, [] );
		return is_array( $salvo ) ? $salvo : [];
	}

	/**
	 * Persiste credenciais OAuth (secret criptografado).
	 */
	public static function salvar_credenciais( string $client_id, string $client_secret = '' ): void {
		$dados = self::obter();

		$dados['client_id'] = sanitize_text_field( $client_id );

		if ( '' !== trim( $client_secret ) ) {
			$dados['client_secret'] = VH_SEO_Crypto::criptografar( trim( $client_secret ) );
		}

		update_option( self::OPTION, $dados, false );
	}

	/**
	 * Persiste a chave PageSpeed Insights (criptografada).
	 */
	public static function salvar_pagespeed_key( string $api_key ): void {
		$dados = self::obter();
		$api_key = trim( $api_key );
		if ( '' === $api_key ) {
			unset( $dados['pagespeed_key'] );
		} else {
			$dados['pagespeed_key'] = VH_SEO_Crypto::criptografar( $api_key );
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

	public static function pagespeed_key(): string {
		$dados = self::obter();
		if ( empty( $dados['pagespeed_key'] ) ) {
			return '';
		}
		return VH_SEO_Crypto::descriptografar( (string) $dados['pagespeed_key'] );
	}

    public static function credenciais_configuradas(): bool {
        $dados = self::obter();
        $seo   = VH_Settings::obter( 'vh_seo', VH_Settings::seo_padrao() );
        $id    = (string) ( $dados['client_id'] ?? $seo['google_client_id'] ?? '' );
        return '' !== $id && ! empty( $dados['client_secret'] );
    }

	public static function conectado(): bool {
		$dados = self::obter();
		return ! empty( $dados['refresh_token'] ) || ! empty( $dados['access_token'] );
	}

	/**
	 * Status público para o painel (sem segredos).
	 *
	 * @return array<string, mixed>
	 */
	public static function status_publico(): array {
		$dados = self::obter();
		$seo   = VH_Settings::obter( 'vh_seo', VH_Settings::seo_padrao() );

		return [
			'credenciais_ok'  => self::credenciais_configuradas(),
			'conectado'       => self::conectado(),
			'email'           => (string) ( $dados['email'] ?? '' ),
			'client_id'       => (string) ( $dados['client_id'] ?? $seo['google_client_id'] ?? '' ),
			'redirect_uri'    => self::redirect_uri(),
			'pagespeed_key_ok'=> '' !== self::pagespeed_key(),
		];
	}

	/**
	 * Gera URL de autorização Google com state anti-CSRF.
	 *
	 * @return string|WP_Error
	 */
	public static function url_autorizacao() {
		if ( ! self::credenciais_configuradas() ) {
			return new WP_Error( 'vh_seo_sem_credenciais', __( 'Informe Client ID e Client Secret antes de conectar.', 'vapor-hub-loja' ) );
		}

        $dados  = self::obter();
        $seo    = VH_Settings::obter( 'vh_seo', VH_Settings::seo_padrao() );
        $state  = wp_generate_password( 32, false, false );
		$user_id = get_current_user_id();

		set_transient( 'vh_seo_oauth_' . $state, $user_id, 10 * MINUTE_IN_SECONDS );

        $params = [
            'client_id'     => $dados['client_id'] ?? $seo['google_client_id'] ?? '',
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
			'scope'         => implode( ' ', self::SCOPES ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];

		return add_query_arg( $params, self::AUTH_URL );
	}

	/**
	 * Processa o callback OAuth (code + state).
	 *
	 * @return true|WP_Error
	 */
	public static function processar_callback( string $code, string $state ) {
		$code  = sanitize_text_field( $code );
		$state = sanitize_text_field( $state );

		if ( '' === $code || '' === $state ) {
			return new WP_Error( 'vh_seo_oauth_invalido', __( 'Resposta OAuth incompleta.', 'vapor-hub-loja' ) );
		}

		$user_id = get_transient( 'vh_seo_oauth_' . $state );
		delete_transient( 'vh_seo_oauth_' . $state );

		if ( ! $user_id || (int) $user_id !== get_current_user_id() ) {
			return new WP_Error( 'vh_seo_oauth_state', __( 'Sessão OAuth expirada ou inválida. Tente conectar novamente.', 'vapor-hub-loja' ) );
		}

		$tokens = self::trocar_codigo( $code );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}

		$dados = self::obter();
		$dados['access_token']  = VH_SEO_Crypto::criptografar( (string) $tokens['access_token'] );
		$dados['expires_at']    = time() + (int) ( $tokens['expires_in'] ?? 3600 );
		if ( ! empty( $tokens['refresh_token'] ) ) {
			$dados['refresh_token'] = VH_SEO_Crypto::criptografar( (string) $tokens['refresh_token'] );
		}
		$dados['connected_at'] = gmdate( 'c' );

		$email = self::buscar_email_usuario( (string) $tokens['access_token'] );
		if ( $email ) {
			$dados['email'] = $email;
		}

		update_option( self::OPTION, $dados, false );
		self::limpar_caches_metricas();

		return true;
	}

	/**
	 * Revoga tokens e remove conexão.
	 */
	public static function desconectar(): void {
		$token = self::access_token_bruto();
		if ( $token ) {
			wp_remote_post(
				self::REVOKE_URL,
				[
					'timeout' => 8,
					'body'    => [ 'token' => $token ],
				]
			);
		}

		$dados = self::obter();
		unset( $dados['access_token'], $dados['refresh_token'], $dados['expires_at'], $dados['email'], $dados['connected_at'] );
		update_option( self::OPTION, $dados, false );
		self::limpar_caches_metricas();
	}

	/**
	 * Token de acesso válido (renova automaticamente se expirado).
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$dados = self::obter();
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
	 * Requisição autenticada à API Google.
	 *
	 * @param array<string, mixed> $args Args wp_remote_*.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function requisicao( string $url, array $args = [] ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args['headers'] = array_merge(
			$args['headers'] ?? [],
			[ 'Authorization' => 'Bearer ' . $token ]
		);
		$args['timeout'] = $args['timeout'] ?? 15;

		$resp = wp_remote_request( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code >= 400 ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: __( 'Erro na API Google.', 'vapor-hub-loja' );
			return new WP_Error( 'vh_seo_api', $msg, [ 'status' => $code ] );
		}

		return is_array( $body ) ? $body : [];
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
				'body'    => [
					'code'          => $code,
					'client_id'     => $dados['client_id'] ?? '',
					'client_secret' => self::client_secret(),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				],
			]
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['access_token'] ) ) {
			$msg = is_array( $body ) && isset( $body['error_description'] )
				? (string) $body['error_description']
				: __( 'Não foi possível obter tokens do Google.', 'vapor-hub-loja' );
			return new WP_Error( 'vh_seo_token', $msg );
		}

		return $body;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function renovar_token() {
		$dados = self::obter();
		if ( empty( $dados['refresh_token'] ) ) {
			return new WP_Error( 'vh_seo_sem_refresh', __( 'Conexão expirada. Conecte novamente ao Google.', 'vapor-hub-loja' ) );
		}

		$refresh = VH_SEO_Crypto::descriptografar( (string) $dados['refresh_token'] );
		if ( '' === $refresh ) {
			return new WP_Error( 'vh_seo_sem_refresh', __( 'Conexão inválida. Conecte novamente ao Google.', 'vapor-hub-loja' ) );
		}

		$resp = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'body'    => [
					'client_id'     => $dados['client_id'] ?? '',
					'client_secret' => self::client_secret(),
					'refresh_token' => $refresh,
					'grant_type'    => 'refresh_token',
				],
			]
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'vh_seo_refresh', __( 'Falha ao renovar acesso. Conecte novamente.', 'vapor-hub-loja' ) );
		}

		$dados['access_token'] = VH_SEO_Crypto::criptografar( (string) $body['access_token'] );
		$dados['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 );
		update_option( self::OPTION, $dados, false );

		return (string) $body['access_token'];
	}

	private static function access_token_bruto(): string {
		$dados = self::obter();
		if ( empty( $dados['access_token'] ) ) {
			return '';
		}
		return VH_SEO_Crypto::descriptografar( (string) $dados['access_token'] );
	}

	private static function buscar_email_usuario( string $access_token ): string {
		$resp = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			[
				'timeout' => 8,
				'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
			]
		);
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $body ) && ! empty( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
	}

	private static function limpar_caches_metricas(): void {
		delete_transient( 'vh_seo_gsc_resumo' );
		delete_transient( 'vh_seo_ga_resumo' );
		delete_transient( 'vh_seo_pagespeed' );
	}
}

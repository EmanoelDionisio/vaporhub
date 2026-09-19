<?php
/**
 * Endpoints REST de SEO — OAuth Google, métricas e PageSpeed.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_SEO extends VH_REST_Controller {

	protected string $rest_base = 'seo';

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/google/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'status_google' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/google/auth-url',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'url_autorizacao' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/google/callback',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'callback_google' ],
				'permission_callback' => [ $this, 'permissao_callback' ],
				'args'                => [
					'code'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'state' => [ 'sanitize_callback' => 'sanitize_text_field' ],
					'error' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/google/disconnect',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'desconectar_google' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/metricas',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'metricas' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/pagespeed',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'pagespeed' ],
				'permission_callback' => [ $this, 'permissao' ],
				'args'                => [
					'forcar' => [
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => static fn( $v ) => (bool) $v,
					],
				],
			]
		);
	}

	/**
	 * Callback OAuth: exige usuário logado com permissão ao app.
	 */
	public function permissao_callback(): bool {
		return is_user_logged_in() && current_user_can( VH_Roles::CAP );
	}

	public function status_google(): WP_REST_Response {
		return $this->ok( VH_SEO_Google::status_publico() );
	}

	public function url_autorizacao( WP_REST_Request $request ) {
		$url = VH_SEO_Google::url_autorizacao();
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		return $this->ok( [ 'url' => $url ] );
	}

	/**
	 * Recebe redirect do Google e redireciona de volta ao painel SEO.
	 */
	public function callback_google( WP_REST_Request $request ) {
		if ( $request->get_param( 'error' ) ) {
			return $this->redirect_painel( 'erro', sanitize_text_field( (string) $request->get_param( 'error' ) ) );
		}

		$resultado = VH_SEO_Google::processar_callback(
			(string) $request->get_param( 'code' ),
			(string) $request->get_param( 'state' )
		);

		if ( is_wp_error( $resultado ) ) {
			return $this->redirect_painel( 'erro', $resultado->get_error_message() );
		}

		return $this->redirect_painel( 'conectado' );
	}

	public function desconectar_google(): WP_REST_Response {
		VH_SEO_Google::desconectar();
		return $this->ok( VH_SEO_Google::status_publico() );
	}

	public function metricas(): WP_REST_Response {
		$gsc = VH_SEO_SearchConsole::resumo();
		$ga  = VH_SEO_Analytics::resumo();

		return $this->ok(
			[
				'search_console' => is_wp_error( $gsc ) ? [ 'erro' => $gsc->get_error_message() ] : $gsc,
				'analytics'      => is_wp_error( $ga ) ? [ 'erro' => $ga->get_error_message() ] : $ga,
			]
		);
	}

	public function pagespeed( WP_REST_Request $request ) {
		$forcar = (bool) $request->get_param( 'forcar' );
		$dados  = VH_SEO_PageSpeed::analisar( null, $forcar );
		if ( is_wp_error( $dados ) ) {
			return $dados;
		}
		return $this->ok( $dados );
	}

	/**
	 * Redireciona para /minha-loja/seo com feedback na query string.
	 */
	private function redirect_painel( string $status, string $msg = '' ): WP_REST_Response {
		$args = [ 'google' => $status ];
		if ( '' !== $msg ) {
			$args['google_msg'] = rawurlencode( $msg );
		}
		$url = add_query_arg( $args, VH_Router::url( 'seo' ) );

		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );
		return $response;
	}
}

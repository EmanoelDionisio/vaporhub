<?php
/**
 * Endpoints REST — integração Tiny ERP.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Tiny extends VH_REST_Controller {

	protected string $rest_base = 'tiny';

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'status' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/modo',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'definir_modo' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/credenciais',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'salvar_credenciais' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/v2/token',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'salvar_token_v2' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/v2/testar',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'testar_v2' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/importar/raizes',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'importar_raizes' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/importar/previa',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'importar_previa' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/importar/zerar',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'importar_zerar' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/importar',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'importar_lote' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/config',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'salvar_config' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/auth-url',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'url_autorizacao' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/callback',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'callback_oauth' ],
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
			'/' . $this->rest_base . '/disconnect',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'desconectar' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/liberar-trava-conta',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'liberar_trava_conta' ],
				'permission_callback' => [ $this, 'permissao_admin' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/webhook-token',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rotacionar_webhook_token' ],
				'permission_callback' => [ $this, 'permissao_admin' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/sincronizar',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sincronizar_agora' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/logs',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listar_logs' ],
				'permission_callback' => [ $this, 'permissao_admin' ],
				'args'                => [
					'aba'    => [ 'sanitize_callback' => 'sanitize_key' ],
					'pagina' => [ 'sanitize_callback' => 'absint' ],
					'nivel'  => [ 'sanitize_callback' => 'sanitize_key' ],
					'origem' => [ 'sanitize_callback' => 'sanitize_key' ],
					'status' => [ 'sanitize_callback' => 'sanitize_key' ],
					'tipo'   => [ 'sanitize_callback' => 'sanitize_key' ],
					'busca'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/fila',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listar_fila' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/mapeamento',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'obter_mapeamento' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/mapeamento/categorias',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'salvar_mapeamento_categorias' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/' . $this->rest_base . '/mapeamento/atributos',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'salvar_mapeamento_atributos' ],
				'permission_callback' => [ $this, 'permissao' ],
			]
		);

		register_rest_route(
			self::NS,
			'/public/tiny/webhook',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'webhook' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::NS,
			'/public/tiny/webhook/(?P<token>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'webhook' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'token' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				],
			]
		);
	}

	public function permissao_callback( WP_REST_Request $request ): bool {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		if ( '' !== $state && false !== get_transient( 'vh_tiny_oauth_' . $state ) ) {
			return true;
		}

		if ( $request->get_param( 'error' ) ) {
			return true;
		}

		return is_user_logged_in() && current_user_can( VH_Roles::CAP );
	}

	public function permissao_admin(): bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public function status(): WP_REST_Response {
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function definir_modo( WP_REST_Request $request ): WP_REST_Response {
		$modo = sanitize_key( (string) $request->get_param( 'modo' ) );
		VH_Tiny::definir_modo( $modo );
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function salvar_credenciais( WP_REST_Request $request ): WP_REST_Response {
		$client_id     = sanitize_text_field( (string) $request->get_param( 'client_id' ) );
		$client_secret = sanitize_text_field( (string) $request->get_param( 'client_secret' ) );
		VH_Tiny_Client::salvar_credenciais( $client_id, $client_secret );
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function salvar_token_v2( WP_REST_Request $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$resultado = VH_Tiny_Client_V2::salvar_e_conectar( $token );
		if ( is_wp_error( $resultado ) ) {
			$status = (int) ( $resultado->get_error_data()['status'] ?? 400 );
			return new WP_Error(
				$resultado->get_error_code(),
				$resultado->get_error_message(),
				[ 'status' => $status > 0 ? $status : 400 ]
			);
		}
		VH_Tiny::garantir_webhook_token();
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function testar_v2( WP_REST_Request $request ) {
		$token = trim( (string) $request->get_param( 'token' ) );
		if ( '' === $token ) {
			$salvo = VH_Tiny_Client_V2::token();
			if ( is_wp_error( $salvo ) ) {
				return $this->erro(
					'vh_tiny_v2_token_vazio',
					__( 'Informe o token no campo para testar.', 'vapor-hub-loja' ),
					400
				);
			}
			$token = $salvo;
		}

		$resultado = VH_Tiny_Client_V2::testar_token( $token );
		if ( is_wp_error( $resultado ) ) {
			$status = (int) ( $resultado->get_error_data()['status'] ?? 400 );
			return new WP_Error(
				$resultado->get_error_code(),
				$resultado->get_error_message(),
				[ 'status' => $status > 0 ? $status : 400 ]
			);
		}

		return $this->ok( [
			'teste_ok' => true,
			'mensagem' => __( 'Conexão com o Tiny validada. Salve o token para conectar.', 'vapor-hub-loja' ),
		] );
	}

	public function salvar_config( WP_REST_Request $request ): WP_REST_Response {
		$config = [
			'ativo'              => (bool) $request->get_param( 'ativo' ),
			'sinc_auto'          => (bool) $request->get_param( 'sinc_auto' ),
			'permitir_envio'     => (bool) $request->get_param( 'permitir_envio' ),
			'loja_identificador' => (string) $request->get_param( 'loja_identificador' ),
		];

		/* Recebimento tem dois interruptores independentes: catálogo e estoque/preço. */
		if ( null !== $request->get_param( 'receber_catalogo' ) ) {
			$config['receber_catalogo'] = (bool) $request->get_param( 'receber_catalogo' );
		}
		if ( null !== $request->get_param( 'sku_prefixo' ) ) {
			$config['sku_prefixo'] = (string) $request->get_param( 'sku_prefixo' );
		}

		if ( null !== $request->get_param( 'categoria_raiz' ) ) {
			$config['categoria_raiz'] = (string) $request->get_param( 'categoria_raiz' );
		}

		if ( null !== $request->get_param( 'receber_estoque_preco' ) ) {
			$config['receber_estoque_preco'] = (bool) $request->get_param( 'receber_estoque_preco' );
		}

		foreach ( array_keys( VH_Tiny::travas_padrao() ) as $trava ) {
			if ( null !== $request->get_param( $trava ) ) {
				$config[ $trava ] = (bool) $request->get_param( $trava );
			}
		}
		if ( null !== $request->get_param( 'import_raizes' ) ) {
			$config['import_raizes'] = (array) $request->get_param( 'import_raizes' );
		}
		if ( null !== $request->get_param( 'import_marcas' ) ) {
			$config['import_marcas'] = (string) $request->get_param( 'import_marcas' );
		}
		foreach ( [ 'import_exigir_estoque', 'import_exigir_preco' ] as $recorte ) {
			if ( null !== $request->get_param( $recorte ) ) {
				$config[ $recorte ] = (bool) $request->get_param( $recorte );
			}
		}

		VH_Tiny::salvar_config( $config );
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function rotacionar_webhook_token(): WP_REST_Response {
		VH_Tiny::rotacionar_webhook_token();
		VH_Tiny_Log::warning(
			VH_Tiny_Log::ORIGEM_CONFIG,
			__( 'Token do webhook rotacionado: atualize a URL no painel do Tiny.', 'vapor-hub-loja' )
		);
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function liberar_trava_conta(): WP_REST_Response {
		VH_Tiny_Client::liberar_trava_conta();
		VH_Tiny_Log::warning(
			VH_Tiny_Log::ORIGEM_CONFIG,
			__( 'Trava de empresa liberada pelo painel: a próxima conexão define o novo CNPJ.', 'vapor-hub-loja' )
		);
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function url_autorizacao( WP_REST_Request $request ) {
		unset( $request );
		$url = VH_Tiny_Client::url_autorizacao();
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		return $this->ok( [ 'url' => $url ] );
	}

	public function callback_oauth( WP_REST_Request $request ) {
		if ( $request->get_param( 'error' ) ) {
			$msg = sanitize_text_field( (string) $request->get_param( 'error' ) );
			VH_Tiny_Log::error( VH_Tiny_Log::ORIGEM_OAUTH, $msg );
			return $this->redirect_painel( 'erro', $msg );
		}

		$resultado = VH_Tiny_Client::processar_callback(
			(string) $request->get_param( 'code' ),
			(string) $request->get_param( 'state' )
		);

		if ( is_wp_error( $resultado ) ) {
			VH_Tiny_Log::error( VH_Tiny_Log::ORIGEM_OAUTH, $resultado->get_error_message() );
			return $this->redirect_painel( 'erro', $resultado->get_error_message() );
		}

		VH_Tiny_Log::success( VH_Tiny_Log::ORIGEM_OAUTH, __( 'Tiny ERP conectado via OAuth.', 'vapor-hub-loja' ) );
		return $this->redirect_painel( 'conectado' );
	}

	public function desconectar(): WP_REST_Response {
		VH_Tiny::driver()->desconectar();
		VH_Tiny_Log::info( VH_Tiny_Log::ORIGEM_OAUTH, __( 'Tiny ERP desconectado pelo painel.', 'vapor-hub-loja' ) );
		return $this->ok( VH_Tiny::status_publico() );
	}

	public function sincronizar_agora(): WP_REST_Response {
		VH_Tiny_Queue::enfileirar( 'reconciliar', 'manual_' . time(), [] );
		$resultado = VH_Tiny_Queue::processar_manual( 20 );
		$fila      = VH_Tiny_Queue::contagem_por_status();
		VH_Tiny_Log::info(
			VH_Tiny_Log::ORIGEM_SYNC,
			sprintf(
				/* translators: 1: processed count, 2: error count */
				__( 'Sincronização manual: %1$d processados, %2$d erros.', 'vapor-hub-loja' ),
				(int) ( $resultado['processados'] ?? 0 ),
				(int) ( $resultado['erros'] ?? 0 )
			),
			'manual',
			$resultado
		);
		return $this->ok( [
			'status'      => VH_Tiny::status_publico(),
			'processados' => $resultado['processados'] ?? 0,
			'erros'       => $resultado['erros'] ?? 0,
			'lotes'       => $resultado['lotes'] ?? 0,
			'fila'        => $fila,
			'pendentes_fila' => (int) ( $fila['pending'] ?? 0 ) + (int) ( $fila['processing'] ?? 0 ),
		] );
	}

	public function listar_fila(): WP_REST_Response {
		return $this->ok( [
			'fila'       => VH_Tiny_Queue::listar_recentes( 30 ),
			'contagem'   => VH_Tiny_Queue::contagem_por_status(),
			'pendencias' => VH_Tiny_Sync_Service::contar_pendencias(),
		] );
	}

	public function listar_logs( WP_REST_Request $request ): WP_REST_Response {
		$aba    = sanitize_key( (string) $request->get_param( 'aba' ) );
		$pagina = max( 1, (int) $request->get_param( 'pagina' ) );
		$busca  = sanitize_text_field( (string) $request->get_param( 'busca' ) );

		$status_pub = VH_Tiny::status_publico();

		if ( 'fila' === $aba ) {
			$resultado = VH_Tiny_Queue::listar_filtrado( [
				'pagina' => $pagina,
				'limite' => 50,
				'status' => sanitize_key( (string) $request->get_param( 'status' ) ),
				'tipo'   => sanitize_key( (string) $request->get_param( 'tipo' ) ),
				'busca'  => $busca,
			] );

			return $this->ok( [
				'aba'      => 'fila',
				'resumo'   => [
					'ultimo_erro'          => (string) ( $status_pub['ultimo_erro'] ?? '' ),
					'ultima_reconciliacao' => (string) ( $status_pub['ultima_reconciliacao'] ?? '' ),
					'log_contagem'         => VH_Tiny_Log::contagem_por_nivel(),
					'fila_contagem'        => VH_Tiny_Queue::contagem_por_status(),
				],
				'fila'     => $resultado['itens'],
				'total'    => $resultado['total'],
				'paginas'  => $resultado['paginas'],
				'pagina'   => $resultado['pagina'],
			] );
		}

		$resultado = VH_Tiny_Log::listar( [
			'pagina'  => $pagina,
			'por_pag' => 50,
			'nivel'   => sanitize_key( (string) $request->get_param( 'nivel' ) ),
			'origem'  => sanitize_key( (string) $request->get_param( 'origem' ) ),
			'busca'   => $busca,
		] );

		return $this->ok( [
			'aba'      => 'eventos',
			'resumo'   => [
				'ultimo_erro'          => (string) ( $status_pub['ultimo_erro'] ?? '' ),
				'ultima_reconciliacao' => (string) ( $status_pub['ultima_reconciliacao'] ?? '' ),
				'log_contagem'         => VH_Tiny_Log::contagem_por_nivel(),
				'fila_contagem'        => VH_Tiny_Queue::contagem_por_status(),
			],
			'eventos'  => $resultado['itens'],
			'total'    => $resultado['total'],
			'paginas'  => $resultado['paginas'],
			'pagina'   => $resultado['pagina'],
		] );
	}

	public function obter_mapeamento() {
		$dados = VH_Tiny_Mapping_Service::obter_mapeamento();
		if ( is_wp_error( $dados ) ) {
			return $dados;
		}
		return $this->ok( $dados );
	}

	public function salvar_mapeamento_categorias( WP_REST_Request $request ) {
		$vinculos = $request->get_param( 'vinculos' );
		if ( ! is_array( $vinculos ) ) {
			return $this->erro(
				'vh_tiny_map_cats',
				__( 'Informe os vínculos de categorias.', 'vapor-hub-loja' ),
				400
			);
		}

		$resultado = VH_Tiny_Mapping_Service::salvar_categorias( $vinculos );
		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}

		VH_Tiny_Log::info(
			VH_Tiny_Log::ORIGEM_MAPPING,
			sprintf(
				/* translators: 1: linked, 2: created, 3: ignored */
				__( 'Mapeamento categorias: %1$d vinculadas, %2$d criadas, %3$d ignoradas.', 'vapor-hub-loja' ),
				(int) ( $resultado['resultados']['vinculados'] ?? 0 ),
				(int) ( $resultado['resultados']['criados'] ?? 0 ),
				(int) ( $resultado['resultados']['ignorados'] ?? 0 )
			),
			'categorias',
			[
				'erros' => $resultado['resultados']['erros'] ?? [],
			]
		);
		if ( ! empty( $resultado['resultados']['erros'] ) && is_array( $resultado['resultados']['erros'] ) ) {
			foreach ( $resultado['resultados']['erros'] as $erro_linha ) {
				if ( ! is_array( $erro_linha ) || empty( $erro_linha['msg'] ) ) {
					continue;
				}
				VH_Tiny_Log::warning(
					VH_Tiny_Log::ORIGEM_MAPPING,
					(string) $erro_linha['msg'],
					isset( $erro_linha['term_id'] ) ? 'cat_' . (int) $erro_linha['term_id'] : 'categorias',
					$erro_linha
				);
			}
		}

		return $this->ok( $resultado );
	}

	public function salvar_mapeamento_atributos( WP_REST_Request $request ) {
		$mapa = $request->get_param( 'map_atributos' );
		if ( ! is_array( $mapa ) ) {
			return $this->erro(
				'vh_tiny_map_attr',
				__( 'Informe o mapeamento de atributos.', 'vapor-hub-loja' ),
				400
			);
		}

		$resultado = VH_Tiny_Mapping_Service::salvar_atributos( $mapa );
		VH_Tiny_Log::info(
			VH_Tiny_Log::ORIGEM_MAPPING,
			__( 'Mapeamento de atributos salvo.', 'vapor-hub-loja' ),
			'atributos',
			[ 'mapa' => $mapa ]
		);
		return $this->ok( $resultado );
	}

	public function webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dados  = VH_Tiny::obter();
		$driver = VH_Tiny::driver();
		if ( ! $driver->conectado() || empty( $dados['webhook_token_hash'] ) ) {
			VH_Tiny_Log::warning( VH_Tiny_Log::ORIGEM_WEBHOOK, __( 'Webhook rejeitado: integração indisponível.', 'vapor-hub-loja' ) );
			return new WP_Error( 'vh_tiny_webhook_off', __( 'Webhook indisponível.', 'vapor-hub-loja' ), [ 'status' => 503 ] );
		}

		/* Header é o caminho novo; a URL legada segue aceita até o ERP ser atualizado. */
		$token = (string) ( $request->get_header( VH_Tiny::HEADER_WEBHOOK ) ?? '' );
		if ( '' === $token ) {
			$token = (string) $request->get_param( 'token' );
		}

		if ( ! VH_Tiny::validar_webhook_token( $token ) ) {
			VH_Tiny_Log::warning( VH_Tiny_Log::ORIGEM_WEBHOOK, __( 'Webhook rejeitado: token inválido.', 'vapor-hub-loja' ) );
			return new WP_Error( 'vh_tiny_webhook_token', __( 'Token inválido.', 'vapor-hub-loja' ), [ 'status' => 403 ] );
		}

		$ip   = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$key  = 'vh_tiny_wh_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits > 60 ) {
			VH_Tiny_Log::warning( VH_Tiny_Log::ORIGEM_WEBHOOK, __( 'Webhook rejeitado: rate limit.', 'vapor-hub-loja' ), $ip );
			return new WP_Error( 'vh_tiny_rate', __( 'Muitas requisições.', 'vapor-hub-loja' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

		$body = $request->get_json_params();
		if ( ! is_array( $body ) || ! $body ) {
			$body = json_decode( $request->get_body(), true );
		}
		if ( ! is_array( $body ) ) {
			VH_Tiny_Log::error( VH_Tiny_Log::ORIGEM_WEBHOOK, __( 'Webhook rejeitado: payload inválido.', 'vapor-hub-loja' ) );
			return new WP_Error( 'vh_tiny_webhook_body', __( 'Payload inválido.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
		}

		$event_id = sanitize_text_field( (string) ( $body['id'] ?? $body['eventId'] ?? md5( wp_json_encode( $body ) ) ) );
		if ( get_transient( 'vh_tiny_evt_' . $event_id ) ) {
			return $this->ok( [ 'duplicado' => true ] );
		}
		set_transient( 'vh_tiny_evt_' . $event_id, 1, DAY_IN_SECONDS );

		VH_Tiny_Log::info(
			VH_Tiny_Log::ORIGEM_WEBHOOK,
			sprintf(
				/* translators: %s: event id */
				__( 'Webhook recebido: %s', 'vapor-hub-loja' ),
				$event_id
			),
			$event_id,
			[ 'tipo' => (string) ( $body['tipo'] ?? $body['type'] ?? '' ) ]
		);

		/*
		 * Só enfileira. Processar o lote aqui punha o trabalho do ERP dentro da
		 * requisição do webhook, com risco de timeout e reentrega em loop.
		 */
		VH_Tiny_Sync_Service::processar_webhook( $body );

		return $this->ok( [ 'recebido' => true ] );
	}

	public function importar_raizes(): WP_REST_Response|WP_Error {
		$raizes = VH_Tiny_Importacao::raizes();
		if ( is_wp_error( $raizes ) ) {
			return $raizes;
		}
		return $this->ok( [ 'raizes' => $raizes ] );
	}

	public function importar_previa( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$estado = VH_Tiny_Importacao::previa( (bool) $request->get_param( 'reiniciar' ), $this->recorte_pedido( $request ) );
		if ( is_wp_error( $estado ) ) {
			return $estado;
		}
		return $this->ok( $estado );
	}

	public function importar_lote( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$recorte = $this->recorte_pedido( $request );
		$passo = VH_Tiny_Importacao::lote( (int) $request->get_param( 'gravar' ), $recorte );
		if ( is_wp_error( $passo ) ) {
			return $passo;
		}
		return $this->ok( $passo );
	}

	public function importar_zerar(): WP_REST_Response|WP_Error {
		$resultado = VH_Tiny_Importacao::zerar();
		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}
		VH_Tiny_Log::warning(
			VH_Tiny_Log::ORIGEM_CONFIG,
			__( 'Catálogo importado do Tiny apagado para repetir a prova.', 'vapor-hub-loja' )
		);
		return $this->ok( $resultado );
	}

	/**
	 * @return array<string, bool>
	 */
	private function recorte_pedido( WP_REST_Request $request ): array {
		$recorte = [];
		foreach ( [ 'import_exigir_estoque', 'import_exigir_preco' ] as $chave ) {
			if ( null !== $request->get_param( $chave ) ) {
				$recorte[ $chave ] = (bool) $request->get_param( $chave );
			}
		}
		return $recorte;
	}

	private function redirect_painel( string $status, string $msg = '' ): WP_REST_Response {
		$args = [ 'tiny' => $status ];
		if ( '' !== $msg ) {
			$args['tiny_msg'] = rawurlencode( $msg );
		}
		$url = add_query_arg( $args, VH_Router::url( 'tiny', [ 'acao' => 'conexao' ] ) );

		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );
		return $response;
	}
}

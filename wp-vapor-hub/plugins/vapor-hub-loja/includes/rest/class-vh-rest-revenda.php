<?php
/**
 * REST — Cadastros de revenda (público + painel).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Revenda extends VH_REST_Controller {

    protected string $rest_base = 'revenda';

    public function register_routes(): void {
        register_rest_route(
            self::NS,
            '/public/revenda/cadastro',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'cadastro_publico' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NS,
            '/revenda/cadastros',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listar_cadastros' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => array_merge(
                    $this->args_paginacao(),
                    [
                        'status' => [
                            'type'              => 'string',
                            'default'           => '',
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ]
                ),
            ]
        );

        register_rest_route(
            self::NS,
            '/revenda/cadastros/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'obter_cadastro' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [
                    'id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function cadastro_publico( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ) ?: '', 'wp_rest' ) ) {
            return $this->erro( 'nonce_invalido', __( 'Sessão expirada. Atualize a página e tente de novo.', 'vapor-hub-loja' ), 403 );
        }

        $turnstile = sanitize_text_field( (string) $request->get_param( 'turnstile_token' ) );
        if ( ! VH_Auth::validar_turnstile( $turnstile ) ) {
            return $this->erro( 'turnstile', __( 'Verificação de segurança não concluída. Tente novamente.', 'vapor-hub-loja' ), 403 );
        }

        $dados = [
            'nome'           => (string) $request->get_param( 'nome' ),
            'documento'      => (string) $request->get_param( 'documento' ),
            'tipo_documento' => (string) $request->get_param( 'tipo_documento' ),
            'whatsapp'       => (string) $request->get_param( 'whatsapp' ),
            'email'          => (string) $request->get_param( 'email' ),
        ];

        $meta = [
            'ip'         => VH_Auth::ip_cliente(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
            'origem'     => 'popup',
        ];

        $resultado = VH_Revenda_Leads_Service::registrar( $dados, $meta );
        if ( is_wp_error( $resultado ) ) {
            $status = (int) ( $resultado->get_error_data()['status'] ?? 400 );
            return $this->erro( $resultado->get_error_code(), $resultado->get_error_message(), $status );
        }

        VH_Revenda_Leads_Service::notificar_loja_por_email( (int) $resultado, $dados );

        return $this->ok(
            [
                'id'      => (int) $resultado,
                'message' => __( 'Cadastro enviado com sucesso.', 'vapor-hub-loja' ),
            ],
            201
        );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function listar_cadastros( WP_REST_Request $request ): WP_REST_Response {
        $lista = VH_Revenda_Leads_Service::listar(
            [
                'pagina'     => (int) $request->get_param( 'pagina' ),
                'por_pagina' => (int) $request->get_param( 'por_pagina' ),
                'busca'      => (string) $request->get_param( 'busca' ),
                'status'     => (string) $request->get_param( 'status' ),
            ]
        );
        return $this->ok( $lista );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function obter_cadastro( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $id   = (int) $request->get_param( 'id' );
        $item = VH_Revenda_Leads_Service::obter( $id, true );
        if ( ! $item ) {
            return $this->erro( 'nao_encontrado', __( 'Cadastro não encontrado.', 'vapor-hub-loja' ), 404 );
        }
        return $this->ok( $item );
    }
}

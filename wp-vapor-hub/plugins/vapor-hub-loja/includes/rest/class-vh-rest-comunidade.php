<?php
/**
 * REST — Envios de fotos da comunidade (público + painel).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Comunidade extends VH_REST_Controller {

    protected string $rest_base = 'comunidade';

    public function register_routes(): void {
        register_rest_route(
            self::NS,
            '/public/comunidade/envio',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'envio_publico' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NS,
            '/comunidade/envios',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listar_envios' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => array_merge(
                    $this->args_paginacao(),
                    [
                        'busca'  => [
                            'type'              => 'string',
                            'default'           => '',
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
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
            '/comunidade/envios/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'obter_envio' ],
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

        register_rest_route(
            self::NS,
            '/comunidade/envios/(?P<id>\d+)/aprovar',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'aprovar_envio' ],
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

        register_rest_route(
            self::NS,
            '/comunidade/envios/(?P<id>\d+)/recusar',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'recusar_envio' ],
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
    public function envio_publico( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ) ?: '', 'wp_rest' ) ) {
            return $this->erro( 'nonce_invalido', __( 'Sessão expirada. Atualize a página e tente de novo.', 'vapor-hub-loja' ), 403 );
        }

        $turnstile = sanitize_text_field( (string) $request->get_param( 'turnstile_token' ) );
        if ( ! VH_Auth::validar_turnstile( $turnstile ) ) {
            return $this->erro( 'turnstile', __( 'Verificação de segurança não concluída. Tente novamente.', 'vapor-hub-loja' ), 403 );
        }

        $dados = [
            'nome'      => (string) $request->get_param( 'nome' ),
            'instagram' => (string) $request->get_param( 'instagram' ),
            'link'      => (string) $request->get_param( 'link' ),
            'legenda'   => (string) $request->get_param( 'legenda' ),
        ];

        $arquivo = isset( $_FILES['foto'] ) && is_array( $_FILES['foto'] ) ? $_FILES['foto'] : [];

        $meta = [
            'ip'         => VH_Auth::ip_cliente(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
            'origem'     => 'popup',
        ];

        $resultado = VH_Comunidade_Envios_Service::registrar( $dados, $arquivo, $meta );
        if ( is_wp_error( $resultado ) ) {
            $status = (int) ( $resultado->get_error_data()['status'] ?? 400 );
            return $this->erro( $resultado->get_error_code(), $resultado->get_error_message(), $status );
        }

        return $this->ok(
            [
                'id'      => (int) $resultado,
                'message' => __( 'Foto enviada com sucesso! Em breve ela poderá aparecer na galeria após aprovação.', 'vapor-hub-loja' ),
            ],
            201
        );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function listar_envios( WP_REST_Request $request ): WP_REST_Response {
        $lista = VH_Comunidade_Envios_Service::listar(
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
    public function obter_envio( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $id   = (int) $request->get_param( 'id' );
        $item = VH_Comunidade_Envios_Service::obter( $id, true );
        if ( ! $item ) {
            return $this->erro( 'nao_encontrado', __( 'Envio não encontrado.', 'vapor-hub-loja' ), 404 );
        }
        return $this->ok( $item );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function aprovar_envio( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $id        = (int) $request->get_param( 'id' );
        $resultado = VH_Comunidade_Envios_Service::aprovar( $id );
        if ( is_wp_error( $resultado ) ) {
            $status = (int) ( $resultado->get_error_data()['status'] ?? 400 );
            return $this->erro( $resultado->get_error_code(), $resultado->get_error_message(), $status );
        }
        return $this->ok( [ 'message' => __( 'Envio aprovado e publicado na galeria.', 'vapor-hub-loja' ) ] );
    }

    /**
     * @param WP_REST_Request $request
     */
    public function recusar_envio( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $id        = (int) $request->get_param( 'id' );
        $resultado = VH_Comunidade_Envios_Service::recusar( $id );
        if ( is_wp_error( $resultado ) ) {
            $status = (int) ( $resultado->get_error_data()['status'] ?? 400 );
            return $this->erro( $resultado->get_error_code(), $resultado->get_error_message(), $status );
        }
        return $this->ok( [ 'message' => __( 'Envio recusado.', 'vapor-hub-loja' ) ] );
    }
}

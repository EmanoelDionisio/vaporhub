<?php
/**
 * Endpoints REST de Produtos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Products extends VH_REST_Controller {

    protected string $rest_base = 'produtos';

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listar' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => array_merge( $this->args_paginacao(), [
                    'categoria' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_title', 'default' => '' ],
                ] ),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'criar' ],
                'permission_callback' => [ $this, 'permissao' ],
            ],
        ] );

        register_rest_route( self::NS, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'obter' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'atualizar' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'excluir' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
        ] );
    }

    public function listar( WP_REST_Request $request ) {
        if ( ! $this->woocommerce_disponivel() ) {
            return $this->erro( 'vh_wc_indisponivel', __( 'WooCommerce indisponível.', 'vapor-hub-loja' ), 503 );
        }
        return $this->ok( VH_Products_Service::listar( $request->get_params() ) );
    }

    public function obter( WP_REST_Request $request ) {
        $dados = VH_Products_Service::obter( (int) $request['id'] );
        if ( null === $dados ) {
            return $this->erro( 'vh_produto_inexistente', __( 'Produto não encontrado.', 'vapor-hub-loja' ), 404 );
        }
        return $this->ok( $dados );
    }

    public function criar( WP_REST_Request $request ) {
        $params = (array) $request->get_json_params();
        $enviar = ! empty( $params['enviar_tiny'] );
        unset( $params['enviar_tiny'] );

        $res = VH_Products_Service::criar( $params );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->responder_produto_salvo( (int) $res, $enviar, 201 );
    }

    public function atualizar( WP_REST_Request $request ) {
        $params = (array) $request->get_json_params();
        $enviar = ! empty( $params['enviar_tiny'] );
        unset( $params['enviar_tiny'] );

        $id  = (int) $request['id'];
        $res = VH_Products_Service::atualizar( $id, $params );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->responder_produto_salvo( $id, $enviar );
    }

    /**
     * @return WP_REST_Response
     */
    private function responder_produto_salvo( int $produto_id, bool $enviar_tiny, int $status = 200 ) {
        $saida = [ 'id' => $produto_id ];

        if ( $enviar_tiny ) {
            $ctx = VH_Tiny::contexto_envio_produto();
            if ( ! $ctx['disponivel'] ) {
                $saida['tiny'] = [
                    'enviado' => false,
                    'erro'    => (string) $ctx['motivo'],
                ];
            } elseif ( VH_Tiny_Sync_Service::deve_enfileirar_envio( $produto_id ) ) {
                /*
                 * Produto com muitas variações: enfileira e deixa o cron concluir
                 * em segundo plano, evitando timeout da requisição de salvar.
                 */
                VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_' . $produto_id, [ 'produto_id' => $produto_id ] );
                $saida['tiny'] = [
                    'enviado'     => false,
                    'enfileirado' => true,
                    'mensagem'    => __( 'Produto salvo. Por ter muitas variações, o envio ao Tiny foi colocado na fila e será concluído em segundo plano. Acompanhe em Tiny ERP → Logs.', 'vapor-hub-loja' ),
                ];
            } else {
                $sync = VH_Tiny_Sync_Service::empurrar_produto( $produto_id );
                if ( is_wp_error( $sync ) ) {
                    VH_Tiny::registrar_erro( $sync->get_error_message() );
                    $saida['tiny'] = [
                        'enviado' => false,
                        'erro'    => $sync->get_error_message(),
                    ];
                } else {
                    VH_Tiny::limpar_erro();
                    $saida['tiny'] = [
                        'enviado' => true,
                        'modo'    => VH_Tiny::modo(),
                    ];
                }
            }
        }

        return $this->ok( $saida, $status );
    }

    public function excluir( WP_REST_Request $request ) {
        $res = VH_Products_Service::excluir( (int) $request['id'] );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->ok( [ 'id' => (int) $request['id'] ] );
    }
}

<?php
/**
 * Endpoints REST de Pedidos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Orders extends VH_REST_Controller {

    protected string $rest_base = 'pedidos';

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listar' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => array_merge( $this->args_paginacao(), [
                    'status'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => '' ],
                    'data_de'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
                    'data_ate' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
                ] ),
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
        ] );
    }

    public function listar( WP_REST_Request $request ) {
        if ( ! $this->woocommerce_disponivel() ) {
            return $this->erro( 'vh_wc_indisponivel', __( 'WooCommerce indisponível.', 'vapor-hub-loja' ), 503 );
        }
        return $this->ok( VH_Orders_Service::listar( $request->get_params() ) );
    }

    public function obter( WP_REST_Request $request ) {
        $dados = VH_Orders_Service::obter( (int) $request['id'] );
        if ( null === $dados ) {
            return $this->erro( 'vh_pedido_inexistente', __( 'Pedido não encontrado.', 'vapor-hub-loja' ), 404 );
        }
        return $this->ok( $dados );
    }

    public function atualizar( WP_REST_Request $request ) {
        $corpo = (array) $request->get_json_params();
        $res   = VH_Orders_Service::atualizar( (int) $request['id'], $corpo );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->ok( VH_Orders_Service::obter( (int) $request['id'] ) );
    }
}

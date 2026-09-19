<?php
/**
 * Endpoints REST de Cupons.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Coupons extends VH_REST_Controller {

    protected string $rest_base = 'cupons';

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listar' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => $this->args_paginacao(),
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
        return $this->ok( VH_Coupons_Service::listar( $request->get_params() ) );
    }

    public function obter( WP_REST_Request $request ) {
        $dados = VH_Coupons_Service::obter( (int) $request['id'] );
        if ( null === $dados ) {
            return $this->erro( 'vh_cupom_inexistente', __( 'Cupom não encontrado.', 'vapor-hub-loja' ), 404 );
        }
        return $this->ok( $dados );
    }

    public function criar( WP_REST_Request $request ) {
        $res = VH_Coupons_Service::criar( (array) $request->get_json_params() );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->ok( [ 'id' => $res ], 201 );
    }

    public function atualizar( WP_REST_Request $request ) {
        $res = VH_Coupons_Service::atualizar( (int) $request['id'], (array) $request->get_json_params() );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->ok( [ 'id' => (int) $request['id'] ] );
    }

    public function excluir( WP_REST_Request $request ) {
        $res = VH_Coupons_Service::excluir( (int) $request['id'] );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $this->ok( [ 'id' => (int) $request['id'] ] );
    }
}

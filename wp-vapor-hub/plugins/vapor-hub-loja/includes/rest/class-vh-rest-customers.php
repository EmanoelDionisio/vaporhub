<?php
/**
 * Endpoints REST de Clientes (somente leitura).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Customers extends VH_REST_Controller {

    protected string $rest_base = 'clientes';

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listar' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => $this->args_paginacao(),
            ],
        ] );

        register_rest_route( self::NS, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'obter' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
        ] );
    }

    public function listar( WP_REST_Request $request ) {
        return $this->ok( VH_Customers_Service::listar( $request->get_params() ) );
    }

    public function obter( WP_REST_Request $request ) {
        $dados = VH_Customers_Service::obter( (int) $request['id'] );
        if ( null === $dados ) {
            return $this->erro( 'vh_cliente_inexistente', __( 'Cliente não encontrado.', 'vapor-hub-loja' ), 404 );
        }
        return $this->ok( $dados );
    }
}

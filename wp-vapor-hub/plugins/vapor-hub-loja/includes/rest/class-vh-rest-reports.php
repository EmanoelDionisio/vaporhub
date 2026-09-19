<?php
/**
 * Endpoint REST de Relatórios.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Reports extends VH_REST_Controller {

    protected string $rest_base = 'relatorios';

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base . '/resumo', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'resumo' ],
                'permission_callback' => [ $this, 'permissao' ],
            ],
        ] );
    }

    public function resumo() {
        if ( ! $this->woocommerce_disponivel() ) {
            return $this->erro( 'vh_wc_indisponivel', __( 'WooCommerce indisponível.', 'vapor-hub-loja' ), 503 );
        }
        return $this->ok( VH_Reports_Service::resumo() );
    }
}

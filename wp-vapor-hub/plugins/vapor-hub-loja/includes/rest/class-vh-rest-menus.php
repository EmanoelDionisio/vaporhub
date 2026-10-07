<?php
/**
 * Endpoints REST dos menus da vitrine.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Menus extends VH_REST_Controller {

    protected string $rest_base = 'menus';

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'obter' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [
                    'posicao' => [
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'salvar' ],
                'permission_callback' => [ $this, 'permissao' ],
            ],
        ] );
    }

    public function obter( WP_REST_Request $request ) {
        $posicao = sanitize_key( (string) $request->get_param( 'posicao' ) );
        if ( ! VH_Menus_Service::posicao_valida( $posicao ) ) {
            $posicao = 'principal';
        }
        return $this->ok( VH_Menus_Service::estado( $posicao ) );
    }

    public function salvar( WP_REST_Request $request ) {
        $posicao = sanitize_key( (string) $request->get_param( 'posicao' ) );
        $itens   = $request->get_param( 'itens' );
        if ( ! is_array( $itens ) ) {
            return $this->erro( 'vh_menu_invalido', __( 'A estrutura do menu é inválida.', 'vapor-hub-loja' ) );
        }

        $resultado = VH_Menus_Service::salvar( $posicao, $itens );
        if ( is_wp_error( $resultado ) ) {
            return $resultado;
        }
        return $this->ok( $resultado );
    }
}

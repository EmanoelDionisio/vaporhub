<?php
/**
 * Serviço de Clientes (somente leitura).
 *
 * Lista clientes registrados (role "customer") e monta o histórico de pedidos
 * via APIs do WooCommerce. Não expõe a tela nativa users.php.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Customers_Service {

    /**
     * Lista clientes paginados.
     *
     * @param array<string,mixed> $args
     * @return array{itens:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public static function listar( array $args ): array {
        $pagina     = max( 1, (int) ( $args['pagina'] ?? 1 ) );
        $por_pagina = min( 100, max( 1, (int) ( $args['por_pagina'] ?? 20 ) ) );
        $busca      = isset( $args['busca'] ) ? sanitize_text_field( (string) $args['busca'] ) : '';

        $consulta = [
            'role'    => 'customer',
            'number'  => $por_pagina,
            'paged'   => $pagina,
            'orderby' => 'registered',
            'order'   => 'DESC',
        ];
        if ( '' !== $busca ) {
            $consulta['search']         = '*' . $busca . '*';
            $consulta['search_columns'] = [ 'user_email', 'display_name', 'user_login' ];
        }

        $query   = new WP_User_Query( $consulta );
        $total   = (int) $query->get_total();
        $itens   = [];

        foreach ( (array) $query->get_results() as $usuario ) {
            $itens[] = self::resumo( (int) $usuario->ID );
        }

        return [
            'itens'   => $itens,
            'total'   => $total,
            'paginas' => (int) ceil( $total / $por_pagina ),
            'pagina'  => $pagina,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function resumo( int $id ): array {
        $usuario = get_userdata( $id );
        $nome    = trim( get_user_meta( $id, 'billing_first_name', true ) . ' ' . get_user_meta( $id, 'billing_last_name', true ) );

        return [
            'id'        => $id,
            'nome'      => $nome ?: ( $usuario ? $usuario->display_name : '' ),
            'email'     => $usuario ? $usuario->user_email : '',
            'telefone'  => get_user_meta( $id, 'billing_phone', true ),
            'cidade'    => get_user_meta( $id, 'billing_city', true ),
            'registro'  => $usuario ? mysql2date( 'd/m/Y', $usuario->user_registered ) : '',
        ];
    }

    /**
     * Detalhe de um cliente com histórico de pedidos.
     *
     * @return array<string,mixed>|null
     */
    public static function obter( int $id ): ?array {
        $usuario = get_userdata( $id );
        if ( ! $usuario || ! in_array( 'customer', (array) $usuario->roles, true ) ) {
            return null;
        }

        $pedidos    = wc_get_orders( [ 'customer_id' => $id, 'limit' => 10, 'orderby' => 'date', 'order' => 'DESC' ] );
        $lista      = [];
        $total_gasto = 0.0;
        foreach ( $pedidos as $pedido ) {
            $lista[]      = VH_Orders_Service::resumo( $pedido );
            $total_gasto += (float) $pedido->get_total();
        }

        return [
            'id'          => $id,
            'nome'        => trim( $usuario->first_name . ' ' . $usuario->last_name ) ?: $usuario->display_name,
            'email'       => $usuario->user_email,
            'registro'    => mysql2date( 'd/m/Y', $usuario->user_registered ),
            'telefone'    => get_user_meta( $id, 'billing_phone', true ),
            'endereco'    => [
                'address_1' => get_user_meta( $id, 'billing_address_1', true ),
                'city'      => get_user_meta( $id, 'billing_city', true ),
                'state'     => get_user_meta( $id, 'billing_state', true ),
                'postcode'  => get_user_meta( $id, 'billing_postcode', true ),
            ],
            'total_pedidos' => count( $lista ),
            'total_gasto'   => wc_price( $total_gasto ),
            'pedidos'       => $lista,
        ];
    }
}

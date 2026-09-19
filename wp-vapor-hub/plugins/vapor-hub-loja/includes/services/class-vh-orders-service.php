<?php
/**
 * Serviço de Pedidos.
 *
 * Toda leitura/escrita usa exclusivamente as APIs oficiais do WooCommerce
 * (wc_get_orders / wc_get_order / WC_Order), garantindo compatibilidade com o
 * armazenamento HPOS. Não há SQL direto nem WP_Query em "shop_order".
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Orders_Service {

    /**
     * Meta usada para o código de rastreio gerenciado pelo app.
     */
    public const META_RASTREIO = '_vh_codigo_rastreio';

    /**
     * Status que o lojista pode aplicar manualmente (whitelist).
     *
     * @return array<string,string> slug => rótulo
     */
    public static function status_editaveis(): array {
        $todos = wc_get_order_statuses(); // chaves no formato "wc-status".
        $permitidos = [ 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded' ];
        $saida = [];
        foreach ( $permitidos as $chave ) {
            if ( isset( $todos[ $chave ] ) ) {
                $saida[ substr( $chave, 3 ) ] = $todos[ $chave ];
            }
        }
        return $saida;
    }

    /**
     * Lista pedidos paginados com filtros.
     *
     * @param array<string,mixed> $args pagina, por_pagina, busca, status, data_de, data_ate.
     * @return array{itens:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public static function listar( array $args ): array {
        $pagina     = max( 1, (int) ( $args['pagina'] ?? 1 ) );
        $por_pagina = min( 100, max( 1, (int) ( $args['por_pagina'] ?? 20 ) ) );

        $consulta = [
            'limit'    => $por_pagina,
            'paged'    => $pagina,
            'paginate' => true,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ];

        $status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
        if ( $status && array_key_exists( $status, self::status_editaveis() ) ) {
            $consulta['status'] = [ $status ];
        }

        $busca = isset( $args['busca'] ) ? sanitize_text_field( (string) $args['busca'] ) : '';
        if ( '' !== $busca ) {
            $consulta['s'] = $busca;
        }

        $data_de  = isset( $args['data_de'] ) ? sanitize_text_field( (string) $args['data_de'] ) : '';
        $data_ate = isset( $args['data_ate'] ) ? sanitize_text_field( (string) $args['data_ate'] ) : '';
        if ( $data_de && $data_ate ) {
            $consulta['date_created'] = $data_de . '...' . $data_ate;
        } elseif ( $data_de ) {
            $consulta['date_created'] = '>=' . $data_de;
        } elseif ( $data_ate ) {
            $consulta['date_created'] = '<=' . $data_ate;
        }

        $resultado = wc_get_orders( $consulta );

        $itens = [];
        foreach ( $resultado->orders as $pedido ) {
            $itens[] = self::resumo( $pedido );
        }

        return [
            'itens'   => $itens,
            'total'   => (int) $resultado->total,
            'paginas' => (int) $resultado->max_num_pages,
            'pagina'  => $pagina,
        ];
    }

    /**
     * Resumo de um pedido para listagem.
     *
     * @return array<string,mixed>
     */
    public static function resumo( WC_Order $pedido ): array {
        return [
            'id'       => $pedido->get_id(),
            'numero'   => $pedido->get_order_number(),
            'cliente'  => $pedido->get_formatted_billing_full_name(),
            'email'    => $pedido->get_billing_email(),
            'status'   => $pedido->get_status(),
            'status_label' => wc_get_order_status_name( $pedido->get_status() ),
            'total'    => (float) $pedido->get_total(),
            'total_html' => $pedido->get_formatted_order_total(),
            'data'     => $pedido->get_date_created() ? $pedido->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '',
        ];
    }

    /**
     * Detalhe completo de um pedido.
     *
     * @return array<string,mixed>|null
     */
    public static function obter( int $id ): ?array {
        $pedido = wc_get_order( $id );
        if ( ! $pedido instanceof WC_Order ) {
            return null;
        }

        $itens = [];
        foreach ( $pedido->get_items() as $item ) {
            /** @var WC_Order_Item_Product $item */
            $itens[] = [
                'nome'       => $item->get_name(),
                'quantidade' => $item->get_quantity(),
                'total'      => wc_price( $item->get_total(), [ 'currency' => $pedido->get_currency() ] ),
            ];
        }

        $notas = [];
        foreach ( wc_get_order_notes( [ 'order_id' => $id ] ) as $nota ) {
            $notas[] = [
                'conteudo' => $nota->content,
                'data'     => mysql2date( 'd/m/Y H:i', $nota->date_created->date( 'Y-m-d H:i:s' ) ),
                'cliente'  => (bool) $nota->customer_note,
            ];
        }

        return [
            'id'             => $pedido->get_id(),
            'numero'         => $pedido->get_order_number(),
            'status'         => $pedido->get_status(),
            'data'           => $pedido->get_date_created() ? $pedido->get_date_created()->date_i18n( 'd/m/Y H:i' ) : '',
            'total_html'     => $pedido->get_formatted_order_total(),
            'metodo_pagamento' => $pedido->get_payment_method_title(),
            'metodo_envio'   => $pedido->get_shipping_method(),
            'codigo_rastreio' => (string) $pedido->get_meta( self::META_RASTREIO ),
            'itens'          => $itens,
            'notas'          => $notas,
            'cobranca'       => [
                'first_name' => $pedido->get_billing_first_name(),
                'last_name'  => $pedido->get_billing_last_name(),
                'email'      => $pedido->get_billing_email(),
                'phone'      => $pedido->get_billing_phone(),
                'address_1'  => $pedido->get_billing_address_1(),
                'address_2'  => $pedido->get_billing_address_2(),
                'city'       => $pedido->get_billing_city(),
                'state'      => $pedido->get_billing_state(),
                'postcode'   => $pedido->get_billing_postcode(),
            ],
            'entrega'        => [
                'first_name' => $pedido->get_shipping_first_name(),
                'last_name'  => $pedido->get_shipping_last_name(),
                'address_1'  => $pedido->get_shipping_address_1(),
                'address_2'  => $pedido->get_shipping_address_2(),
                'city'       => $pedido->get_shipping_city(),
                'state'      => $pedido->get_shipping_state(),
                'postcode'   => $pedido->get_shipping_postcode(),
            ],
        ];
    }

    /**
     * Aplica alterações a um pedido (status, rastreio, nota, endereços).
     *
     * @param array<string,mixed> $dados
     * @return true|WP_Error
     */
    public static function atualizar( int $id, array $dados ) {
        $pedido = wc_get_order( $id );
        if ( ! $pedido instanceof WC_Order ) {
            return new WP_Error( 'vh_pedido_inexistente', __( 'Pedido não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        $precisa_salvar = false;

        if ( isset( $dados['status'] ) ) {
            $status = sanitize_key( (string) $dados['status'] );
            if ( ! array_key_exists( $status, self::status_editaveis() ) ) {
                return new WP_Error( 'vh_status_invalido', __( 'Status não permitido.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
            }
            if ( $status !== $pedido->get_status() ) {
                $pedido->update_status( $status, __( 'Status alterado via Minha Loja.', 'vapor-hub-loja' ) );
            }
        }

        if ( array_key_exists( 'codigo_rastreio', $dados ) ) {
            $codigo = sanitize_text_field( (string) $dados['codigo_rastreio'] );
            $pedido->update_meta_data( self::META_RASTREIO, $codigo );
            $precisa_salvar = true;
        }

        if ( isset( $dados['cobranca'] ) && is_array( $dados['cobranca'] ) ) {
            self::aplicar_endereco( $pedido, 'billing', $dados['cobranca'] );
            $precisa_salvar = true;
        }

        if ( isset( $dados['entrega'] ) && is_array( $dados['entrega'] ) ) {
            self::aplicar_endereco( $pedido, 'shipping', $dados['entrega'] );
            $precisa_salvar = true;
        }

        if ( $precisa_salvar ) {
            $pedido->save();
        }

        if ( ! empty( $dados['nota'] ) ) {
            $nota         = sanitize_textarea_field( (string) $dados['nota'] );
            $para_cliente = ! empty( $dados['nota_para_cliente'] );
            if ( '' !== $nota ) {
                $pedido->add_order_note( $nota, $para_cliente ? 1 : 0, false );
            }
        }

        /**
         * Disparado após alterações no pedido via Minha Loja.
         *
         * @param int                  $id
         * @param array<string, mixed> $dados
         */
        do_action( 'vh_pedido_salvo', $id, $dados );

        return true;
    }

    /**
     * Aplica campos de endereço sanitizados ao pedido.
     *
     * @param array<string,mixed> $campos
     */
    private static function aplicar_endereco( WC_Order $pedido, string $tipo, array $campos ): void {
        $permitidos = [ 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode' ];
        if ( 'billing' === $tipo ) {
            $permitidos[] = 'email';
            $permitidos[] = 'phone';
        }

        foreach ( $permitidos as $campo ) {
            if ( ! array_key_exists( $campo, $campos ) ) {
                continue;
            }
            $valor  = (string) $campos[ $campo ];
            $valor  = ( 'email' === $campo ) ? sanitize_email( $valor ) : sanitize_text_field( $valor );
            $metodo = 'set_' . $tipo . '_' . $campo;
            if ( is_callable( [ $pedido, $metodo ] ) ) {
                $pedido->{$metodo}( $valor );
            }
        }
    }
}

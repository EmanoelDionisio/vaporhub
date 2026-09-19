<?php
/**
 * Serviço de Relatórios.
 *
 * Agrega KPIs da loja usando exclusivamente APIs do WooCommerce (HPOS-safe).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Reports_Service {

    /**
     * Status considerados como receita realizada/aprovada.
     *
     * @var string[]
     */
    private const STATUS_RECEITA = [ 'processing', 'completed' ];

    /**
     * Resumo completo para o dashboard.
     *
     * @return array<string,mixed>
     */
    public static function resumo(): array {
        $hoje    = VH_DateTime::hoje_local();
        $ha_7    = VH_DateTime::dias_atras_local( 6 );
        $ha_30   = VH_DateTime::dias_atras_local( 29 );
        $ini_mes = VH_DateTime::inicio_mes_local();

        $produtos = wp_count_posts( 'product' );

        return [
            'receita'         => [
                'hoje' => self::receita( $hoje, $hoje ),
                'sete' => self::receita( $ha_7, $hoje ),
                'trinta' => self::receita( $ha_30, $hoje ),
                'mes'  => self::receita( $ini_mes, $hoje ),
            ],
            'ticket_medio'    => self::ticket_medio( $ha_30, $hoje ),
            'pedidos_status'  => self::pedidos_por_status(),
            'top_produtos'    => self::top_produtos( $ha_30, $hoje ),
            'produtos_publicados' => isset( $produtos->publish ) ? (int) $produtos->publish : 0,
        ];
    }

    /**
     * Receita (soma dos totais) no intervalo informado.
     */
    public static function receita( string $de, string $ate ): float {
        $total   = 0.0;
        $pedidos = wc_get_orders( [
            'limit'        => -1,
            'status'       => self::STATUS_RECEITA,
            'date_created' => $de . '...' . $ate,
            'return'       => 'objects',
        ] );
        foreach ( $pedidos as $pedido ) {
            $total += (float) $pedido->get_total();
        }
        return $total;
    }

    /**
     * Ticket médio no intervalo.
     */
    public static function ticket_medio( string $de, string $ate ): float {
        $pedidos = wc_get_orders( [
            'limit'        => -1,
            'status'       => self::STATUS_RECEITA,
            'date_created' => $de . '...' . $ate,
            'return'       => 'objects',
        ] );
        $qtd = count( $pedidos );
        if ( 0 === $qtd ) {
            return 0.0;
        }
        $soma = 0.0;
        foreach ( $pedidos as $pedido ) {
            $soma += (float) $pedido->get_total();
        }
        return $soma / $qtd;
    }

    /**
     * Contagem de pedidos por status (apenas status relevantes ao lojista).
     *
     * @return array<int,array{slug:string,label:string,total:int}>
     */
    public static function pedidos_por_status(): array {
        $saida = [];
        foreach ( VH_Orders_Service::status_editaveis() as $slug => $label ) {
            $ids = wc_get_orders( [
                'limit'  => -1,
                'status' => [ $slug ],
                'return' => 'ids',
            ] );
            $saida[] = [
                'slug'  => $slug,
                'label' => $label,
                'total' => count( $ids ),
            ];
        }
        return $saida;
    }

    /**
     * Top 5 produtos por quantidade vendida no intervalo.
     *
     * @return array<int,array{nome:string,quantidade:int}>
     */
    public static function top_produtos( string $de, string $ate ): array {
        $pedidos = wc_get_orders( [
            'limit'        => -1,
            'status'       => self::STATUS_RECEITA,
            'date_created' => $de . '...' . $ate,
            'return'       => 'objects',
        ] );

        $contagem = [];
        $nomes    = [];
        foreach ( $pedidos as $pedido ) {
            foreach ( $pedido->get_items() as $item ) {
                /** @var WC_Order_Item_Product $item */
                $pid = $item->get_product_id();
                if ( ! $pid ) {
                    continue;
                }
                $contagem[ $pid ] = ( $contagem[ $pid ] ?? 0 ) + (int) $item->get_quantity();
                $nomes[ $pid ]    = $item->get_name();
            }
        }

        arsort( $contagem );
        $top = array_slice( $contagem, 0, 5, true );

        $saida = [];
        foreach ( $top as $pid => $qtd ) {
            $saida[] = [ 'nome' => $nomes[ $pid ] ?? ( '#' . $pid ), 'quantidade' => $qtd ];
        }
        return $saida;
    }
}

<?php
/**
 * Serviço de Cupons.
 *
 * CRUD de cupons via API WC_Coupon. A listagem usa get_posts em shop_coupon
 * (cupons são um CPT padrão, não afetado pelo HPOS).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Coupons_Service {

    /**
     * Tipos de desconto suportados.
     *
     * @return array<string,string>
     */
    public static function tipos(): array {
        return [
            'percent'       => __( 'Percentual (%)', 'vapor-hub-loja' ),
            'fixed_cart'    => __( 'Valor fixo no carrinho (R$)', 'vapor-hub-loja' ),
            'fixed_product' => __( 'Valor fixo por produto (R$)', 'vapor-hub-loja' ),
        ];
    }

    /**
     * Lista cupons paginados.
     *
     * @param array<string,mixed> $args
     * @return array{itens:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public static function listar( array $args ): array {
        $pagina     = max( 1, (int) ( $args['pagina'] ?? 1 ) );
        $por_pagina = min( 100, max( 1, (int) ( $args['por_pagina'] ?? 20 ) ) );
        $busca      = isset( $args['busca'] ) ? sanitize_text_field( (string) $args['busca'] ) : '';

        $consulta = new WP_Query( [
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => $por_pagina,
            'paged'          => $pagina,
            's'              => $busca,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $itens = [];
        foreach ( $consulta->posts as $post ) {
            $itens[] = self::resumo( new WC_Coupon( $post->ID ) );
        }

        return [
            'itens'   => $itens,
            'total'   => (int) $consulta->found_posts,
            'paginas' => (int) $consulta->max_num_pages,
            'pagina'  => $pagina,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function resumo( WC_Coupon $cupom ): array {
        $expira = $cupom->get_date_expires();
        return [
            'id'         => $cupom->get_id(),
            'codigo'     => $cupom->get_code(),
            'tipo'       => $cupom->get_discount_type(),
            'tipo_label' => self::tipos()[ $cupom->get_discount_type() ] ?? $cupom->get_discount_type(),
            'valor'      => $cupom->get_amount(),
            'expira'     => $expira ? $expira->date_i18n( 'd/m/Y' ) : '',
            'usos'       => $cupom->get_usage_count(),
            'limite'     => $cupom->get_usage_limit(),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function obter( int $id ): ?array {
        if ( 'shop_coupon' !== get_post_type( $id ) ) {
            return null;
        }
        $cupom  = new WC_Coupon( $id );
        $expira = $cupom->get_date_expires();
        return [
            'id'             => $cupom->get_id(),
            'codigo'         => $cupom->get_code(),
            'descricao'      => $cupom->get_description(),
            'tipo'           => $cupom->get_discount_type(),
            'valor'          => $cupom->get_amount(),
            'expira'         => $expira ? $expira->date( 'Y-m-d' ) : '',
            'limite'         => $cupom->get_usage_limit(),
            'minimo'         => $cupom->get_minimum_amount(),
        ];
    }

    /**
     * Cria um cupom.
     *
     * @param array<string,mixed> $dados
     * @return int|WP_Error
     */
    public static function criar( array $dados ) {
        $codigo = wc_format_coupon_code( sanitize_text_field( (string) ( $dados['codigo'] ?? '' ) ) );
        if ( '' === $codigo ) {
            return new WP_Error( 'vh_cupom_sem_codigo', __( 'Informe o código do cupom.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }
        if ( wc_get_coupon_id_by_code( $codigo ) ) {
            return new WP_Error( 'vh_cupom_duplicado', __( 'Já existe um cupom com este código.', 'vapor-hub-loja' ), [ 'status' => 409 ] );
        }

        $cupom = new WC_Coupon();
        $cupom->set_code( $codigo );
        self::aplicar( $cupom, $dados );
        $id = $cupom->save();

        if ( ! $id ) {
            return new WP_Error( 'vh_cupom_erro', __( 'Não foi possível salvar o cupom.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }
        return (int) $id;
    }

    /**
     * Atualiza um cupom existente.
     *
     * @param array<string,mixed> $dados
     * @return true|WP_Error
     */
    public static function atualizar( int $id, array $dados ) {
        if ( 'shop_coupon' !== get_post_type( $id ) ) {
            return new WP_Error( 'vh_cupom_inexistente', __( 'Cupom não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }
        $cupom = new WC_Coupon( $id );
        self::aplicar( $cupom, $dados );
        $cupom->save();
        return true;
    }

    /**
     * Exclui um cupom.
     *
     * @return true|WP_Error
     */
    public static function excluir( int $id ) {
        if ( 'shop_coupon' !== get_post_type( $id ) ) {
            return new WP_Error( 'vh_cupom_inexistente', __( 'Cupom não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }
        $cupom = new WC_Coupon( $id );
        $cupom->delete( true );
        return true;
    }

    /**
     * Aplica os dados sanitizados ao cupom.
     *
     * @param array<string,mixed> $dados
     */
    private static function aplicar( WC_Coupon $cupom, array $dados ): void {
        if ( isset( $dados['descricao'] ) ) {
            $cupom->set_description( sanitize_textarea_field( (string) $dados['descricao'] ) );
        }
        if ( isset( $dados['tipo'] ) ) {
            $tipo = sanitize_key( (string) $dados['tipo'] );
            if ( array_key_exists( $tipo, self::tipos() ) ) {
                $cupom->set_discount_type( $tipo );
            }
        }
        if ( isset( $dados['valor'] ) ) {
            $cupom->set_amount( wc_format_decimal( (string) $dados['valor'] ) );
        }
        if ( array_key_exists( 'expira', $dados ) ) {
            $expira = sanitize_text_field( (string) $dados['expira'] );
            $cupom->set_date_expires( '' !== $expira ? $expira : null );
        }
        if ( array_key_exists( 'limite', $dados ) ) {
            $limite = (string) $dados['limite'];
            $cupom->set_usage_limit( '' !== $limite ? absint( $limite ) : 0 );
        }
        if ( array_key_exists( 'minimo', $dados ) ) {
            $minimo = (string) $dados['minimo'];
            $cupom->set_minimum_amount( '' !== $minimo ? wc_format_decimal( $minimo ) : '' );
        }
    }
}

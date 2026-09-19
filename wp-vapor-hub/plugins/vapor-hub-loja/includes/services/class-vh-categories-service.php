<?php
/**
 * Serviço de Categorias de produto (product_cat).
 *
 * Hierarquia nativa do WordPress/WooCommerce (parent) + ordem entre irmãos via
 * term meta "order" (padrão WooCommerce).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Categories_Service {

    public const TAXONOMIA = 'product_cat';

    /**
     * Lista categorias: árvore completa (padrão) ou busca paginada.
     *
     * @param array<string,mixed> $args
     * @return array{itens:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int,modo:string}
     */
    public static function listar( array $args ): array {
        $busca = isset( $args['busca'] ) ? sanitize_text_field( (string) $args['busca'] ) : '';

        if ( '' !== $busca ) {
            return self::listar_busca( $args, $busca );
        }

        $itens = self::listar_arvore_plana();

        return [
            'itens'   => $itens,
            'total'   => count( $itens ),
            'paginas' => 1,
            'pagina'  => 1,
            'modo'    => 'arvore',
        ];
    }

    /**
     * @param array<string,mixed> $args
     */
    private static function listar_busca( array $args, string $busca ): array {
        $pagina     = max( 1, (int) ( $args['pagina'] ?? 1 ) );
        $por_pagina = min( 100, max( 1, (int) ( $args['por_pagina'] ?? 20 ) ) );

        $total = (int) wp_count_terms(
            [
                'taxonomy'   => self::TAXONOMIA,
                'hide_empty' => false,
                'search'     => $busca,
            ]
        );

        $termos = get_terms(
            [
                'taxonomy'   => self::TAXONOMIA,
                'hide_empty' => false,
                'number'     => $por_pagina,
                'offset'     => ( $pagina - 1 ) * $por_pagina,
                'search'     => $busca,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        $itens = [];
        if ( ! is_wp_error( $termos ) ) {
            foreach ( $termos as $termo ) {
                $item            = self::formatar( $termo );
                $item['caminho'] = self::caminho_nomes( $termo );
                $itens[]         = $item;
            }
        }

        return [
            'itens'   => $itens,
            'total'   => $total,
            'paginas' => (int) ceil( $total / $por_pagina ),
            'pagina'  => $pagina,
            'modo'    => 'busca',
        ];
    }

    /**
     * Árvore achatada em ordem de exibição (pais → filhos → netos…).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listar_arvore_plana(): array {
        $grupos = self::agrupar_por_pai( self::todos_termos() );
        $saida  = [];
        self::achatar_arvore( $grupos, 0, 0, $saida );
        return $saida;
    }

    /**
     * Opções para select "Fica dentro de…".
     *
     * @return array<int,array{id:int,label:string,nivel:int}>
     */
    public static function opcoes_pai( int $excluir_id = 0 ): array {
        $bloqueados = $excluir_id > 0 ? array_merge( [ $excluir_id ], self::descendentes_ids( $excluir_id ) ) : [];
        $opcoes     = [
            [
                'id'    => 0,
                'label' => __( 'Nenhuma — categoria principal', 'vapor-hub-loja' ),
                'nivel' => 0,
            ],
        ];

        foreach ( self::listar_arvore_plana() as $item ) {
            if ( in_array( (int) $item['id'], $bloqueados, true ) ) {
                continue;
            }
            $prefixo   = $item['nivel'] > 0 ? str_repeat( '— ', (int) $item['nivel'] ) : '';
            $opcoes[] = [
                'id'    => (int) $item['id'],
                'label' => $prefixo . $item['nome'],
                'nivel' => (int) $item['nivel'],
            ];
        }

        return $opcoes;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function obter( int $id ): ?array {
        $termo = get_term( $id, self::TAXONOMIA );
        if ( ! $termo instanceof WP_Term ) {
            return null;
        }
        $item            = self::formatar( $termo );
        $item['caminho'] = self::caminho_nomes( $termo );
        return $item;
    }

    /**
     * Cria uma categoria.
     *
     * @param array<string,mixed> $dados
     * @return int|WP_Error
     */
    public static function criar( array $dados ) {
        $nome = sanitize_text_field( (string) ( $dados['nome'] ?? '' ) );
        if ( '' === $nome ) {
            return new WP_Error( 'vh_categoria_sem_nome', __( 'Informe o nome da categoria.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $pai_id = isset( $dados['pai_id'] ) ? absint( $dados['pai_id'] ) : 0;
        $valido = self::validar_pai( 0, $pai_id );
        if ( is_wp_error( $valido ) ) {
            return $valido;
        }

        $args_insert = [
            'slug'        => isset( $dados['slug'] ) ? sanitize_title( (string) $dados['slug'] ) : '',
            'description' => isset( $dados['descricao'] ) ? sanitize_textarea_field( (string) $dados['descricao'] ) : '',
        ];
        if ( $pai_id > 0 ) {
            $args_insert['parent'] = $pai_id;
        }

        $resultado = wp_insert_term( $nome, self::TAXONOMIA, $args_insert );
        if ( is_wp_error( $resultado ) ) {
            return new WP_Error( 'vh_categoria_erro', $resultado->get_error_message(), [ 'status' => 400 ] );
        }

        $id = (int) $resultado['term_id'];
        self::definir_imagem( $id, $dados );
        self::definir_ordem_final( $id, $pai_id );

        /**
         * @param int                  $id
         * @param array<string, mixed> $dados
         * @param string               $acao
         */
        do_action( 'vh_categoria_salva', $id, $dados, 'criar' );

        return $id;
    }

    /**
     * Atualiza uma categoria.
     *
     * @param array<string,mixed> $dados
     * @return true|WP_Error
     */
    public static function atualizar( int $id, array $dados ) {
        $termo = get_term( $id, self::TAXONOMIA );
        if ( ! $termo instanceof WP_Term ) {
            return new WP_Error( 'vh_categoria_inexistente', __( 'Categoria não encontrada.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        $campos = [];
        if ( isset( $dados['nome'] ) ) {
            $nome = sanitize_text_field( (string) $dados['nome'] );
            if ( '' === $nome ) {
                return new WP_Error( 'vh_categoria_sem_nome', __( 'Informe o nome da categoria.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
            }
            $campos['name'] = $nome;
        }
        if ( isset( $dados['slug'] ) ) {
            $campos['slug'] = sanitize_title( (string) $dados['slug'] );
        }
        if ( isset( $dados['descricao'] ) ) {
            $campos['description'] = sanitize_textarea_field( (string) $dados['descricao'] );
        }
        if ( array_key_exists( 'pai_id', $dados ) ) {
            $pai_id = absint( $dados['pai_id'] );
            $valido = self::validar_pai( $id, $pai_id );
            if ( is_wp_error( $valido ) ) {
                return $valido;
            }
            $campos['parent'] = $pai_id;
        }

        if ( $campos ) {
            $res = wp_update_term( $id, self::TAXONOMIA, $campos );
            if ( is_wp_error( $res ) ) {
                return new WP_Error( 'vh_categoria_erro', $res->get_error_message(), [ 'status' => 400 ] );
            }

            if ( array_key_exists( 'pai_id', $dados ) ) {
                self::definir_ordem_final( $id, absint( $dados['pai_id'] ) );
            }
        }

        self::definir_imagem( $id, $dados );

        /**
         * @param int                  $id
         * @param array<string, mixed> $dados
         * @param string               $acao
         */
        do_action( 'vh_categoria_salva', $id, $dados, 'atualizar' );

        return true;
    }

    /**
     * Move categoria na árvore (drag-and-drop).
     *
     * @param array<string,mixed> $dados posicao: before|after|inside, referencia_id: int
     * @return true|WP_Error
     */
    public static function mover( int $id, array $dados ) {
        $termo = get_term( $id, self::TAXONOMIA );
        if ( ! $termo instanceof WP_Term ) {
            return new WP_Error( 'vh_categoria_inexistente', __( 'Categoria não encontrada.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        $referencia_id = absint( $dados['referencia_id'] ?? 0 );
        $posicao       = sanitize_key( (string) ( $dados['posicao'] ?? 'after' ) );

        if ( $referencia_id <= 0 ) {
            return new WP_Error( 'vh_categoria_referencia', __( 'Referência inválida para mover a categoria.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( ! in_array( $posicao, [ 'before', 'after', 'inside' ], true ) ) {
            return new WP_Error( 'vh_categoria_posicao', __( 'Posição inválida.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( $id === $referencia_id ) {
            return new WP_Error( 'vh_categoria_mesma', __( 'Não é possível mover a categoria para ela mesma.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $referencia = get_term( $referencia_id, self::TAXONOMIA );
        if ( ! $referencia instanceof WP_Term ) {
            return new WP_Error( 'vh_categoria_referencia', __( 'Categoria de referência não encontrada.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        if ( 'inside' === $posicao ) {
            $novo_pai = $referencia_id;
            $valido   = self::validar_pai( $id, $novo_pai );
            if ( is_wp_error( $valido ) ) {
                return $valido;
            }

            $res = wp_update_term( $id, self::TAXONOMIA, [ 'parent' => $novo_pai ] );
            if ( is_wp_error( $res ) ) {
                return new WP_Error( 'vh_categoria_erro', $res->get_error_message(), [ 'status' => 400 ] );
            }

            self::definir_ordem_final( $id, $novo_pai );
            do_action( 'vh_categoria_salva', $id, $dados, 'atualizar' );
            return true;
        }

        $pai_alvo = (int) $referencia->parent;
        $valido   = self::validar_pai( $id, $pai_alvo );
        if ( is_wp_error( $valido ) ) {
            return $valido;
        }

        if ( (int) $termo->parent !== $pai_alvo ) {
            $res = wp_update_term( $id, self::TAXONOMIA, [ 'parent' => $pai_alvo ] );
            if ( is_wp_error( $res ) ) {
                return new WP_Error( 'vh_categoria_erro', $res->get_error_message(), [ 'status' => 400 ] );
            }
        }

        $irmaos = self::ids_irmaos( $pai_alvo, $id );
        $indice = array_search( $referencia_id, $irmaos, true );
        if ( false === $indice ) {
            $irmaos[] = $id;
        } else {
            array_splice( $irmaos, 'before' === $posicao ? $indice : $indice + 1, 0, [ $id ] );
            $irmaos = array_values( array_unique( array_map( 'intval', $irmaos ) ) );
        }

        self::aplicar_ordem_irmaos( $pai_alvo, $irmaos );
        do_action( 'vh_categoria_salva', $id, $dados, 'atualizar' );
        return true;
    }

    /**
     * Exclui uma categoria (bloqueia se houver produtos ou subcategorias, salvo forçar).
     *
     * @return true|WP_Error
     */
    public static function excluir( int $id, bool $forcar = false ) {
        $termo = get_term( $id, self::TAXONOMIA );
        if ( ! $termo instanceof WP_Term ) {
            return new WP_Error( 'vh_categoria_inexistente', __( 'Categoria não encontrada.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        $filhos = get_terms(
            [
                'taxonomy'   => self::TAXONOMIA,
                'hide_empty' => false,
                'parent'     => $id,
                'number'     => 1,
            ]
        );
        if ( ! is_wp_error( $filhos ) && ! empty( $filhos ) && ! $forcar ) {
            return new WP_Error(
                'vh_categoria_com_filhos',
                __( 'Esta categoria possui subcategorias. Exclua ou mova as subcategorias antes.', 'vapor-hub-loja' ),
                [ 'status' => 409 ]
            );
        }

        if ( $termo->count > 0 && ! $forcar ) {
            return new WP_Error(
                'vh_categoria_com_produtos',
                __( 'Esta categoria possui produtos. Mova os produtos antes de excluir.', 'vapor-hub-loja' ),
                [ 'status' => 409 ]
            );
        }

        /** @param int $id */
        do_action( 'vh_categoria_excluida', $id );

        $res = wp_delete_term( $id, self::TAXONOMIA );
        if ( is_wp_error( $res ) || false === $res || 0 === $res ) {
            return new WP_Error( 'vh_categoria_erro', __( 'Não foi possível excluir a categoria.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        return true;
    }

    /**
     * Caminho hierárquico da categoria (ex.: Pai >> Filho).
     */
    public static function caminho_hierarquico( WP_Term $termo, string $separador = ' >> ' ): string {
        $partes = [ $termo->name ];
        $pai_id = (int) $termo->parent;

        while ( $pai_id > 0 ) {
            $pai = get_term( $pai_id, self::TAXONOMIA );
            if ( ! $pai instanceof WP_Term ) {
                break;
            }
            array_unshift( $partes, $pai->name );
            $pai_id = (int) $pai->parent;
        }

        return implode( $separador, $partes );
    }

    /**
     * @return array<int,WP_Term>
     */
    private static function todos_termos(): array {
        $termos = get_terms(
            [
                'taxonomy'   => self::TAXONOMIA,
                'hide_empty' => false,
                'number'     => 0,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        return is_wp_error( $termos ) ? [] : $termos;
    }

    /**
     * @param array<int,WP_Term> $termos
     * @return array<int,array<int,WP_Term>>
     */
    private static function agrupar_por_pai( array $termos ): array {
        $grupos = [];
        foreach ( $termos as $termo ) {
            $grupos[ (int) $termo->parent ][] = $termo;
        }

        foreach ( $grupos as &$lista ) {
            usort(
                $lista,
                static function ( WP_Term $a, WP_Term $b ): int {
                    $oa = self::ordem_termo( $a );
                    $ob = self::ordem_termo( $b );
                    if ( $oa !== $ob ) {
                        return $oa <=> $ob;
                    }
                    return strcasecmp( $a->name, $b->name );
                }
            );
        }

        return $grupos;
    }

    /**
     * @param array<int,array<int,WP_Term>> $grupos
     * @param array<int,array<string,mixed>> $saida
     */
    private static function achatar_arvore( array $grupos, int $pai_id, int $nivel, array &$saida ): void {
        foreach ( $grupos[ $pai_id ] ?? [] as $termo ) {
            $item            = self::formatar( $termo );
            $item['nivel']   = $nivel;
            $item['filhos']  = count( $grupos[ $termo->term_id ] ?? [] );
            $item['caminho'] = self::caminho_nomes( $termo );
            $saida[]         = $item;
            self::achatar_arvore( $grupos, (int) $termo->term_id, $nivel + 1, $saida );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function formatar( WP_Term $termo ): array {
        $thumb_id = (int) get_term_meta( $termo->term_id, 'thumbnail_id', true );
        return [
            'id'         => (int) $termo->term_id,
            'nome'       => $termo->name,
            'slug'       => $termo->slug,
            'descricao'  => $termo->description,
            'total'      => (int) $termo->count,
            'imagem_id'  => $thumb_id,
            'imagem_url' => $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
            'pai_id'     => (int) $termo->parent,
            'ordem'      => self::ordem_termo( $termo ),
        ];
    }

    private static function ordem_termo( WP_Term $termo ): int {
        $order = get_term_meta( $termo->term_id, 'order', true );
        return is_numeric( $order ) ? (int) $order : 0;
    }

    private static function caminho_nomes( WP_Term $termo ): string {
        $partes = [ $termo->name ];
        $pai_id = (int) $termo->parent;

        while ( $pai_id > 0 ) {
            $pai = get_term( $pai_id, self::TAXONOMIA );
            if ( ! $pai instanceof WP_Term ) {
                break;
            }
            array_unshift( $partes, $pai->name );
            $pai_id = (int) $pai->parent;
        }

        return implode( ' › ', $partes );
    }

    /**
     * @return array<int,int>
     */
    public static function descendentes_ids( int $id ): array {
        $todos    = self::todos_termos();
        $grupos   = self::agrupar_por_pai( $todos );
        $saida    = [];
        $coletar = static function ( int $pai ) use ( &$coletar, &$saida, $grupos ): void {
            foreach ( $grupos[ $pai ] ?? [] as $filho ) {
                $saida[] = (int) $filho->term_id;
                $coletar( (int) $filho->term_id );
            }
        };
        $coletar( $id );
        return $saida;
    }

    /**
     * @return true|WP_Error
     */
    private static function validar_pai( int $id, int $pai_id ) {
        if ( 0 === $pai_id ) {
            return true;
        }

        if ( $id > 0 && $pai_id === $id ) {
            return new WP_Error( 'vh_categoria_pai_invalido', __( 'Uma categoria não pode ser pai dela mesma.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $pai = get_term( $pai_id, self::TAXONOMIA );
        if ( ! $pai instanceof WP_Term ) {
            return new WP_Error( 'vh_categoria_pai_invalido', __( 'Categoria pai não encontrada.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( $id > 0 && in_array( $pai_id, self::descendentes_ids( $id ), true ) ) {
            return new WP_Error( 'vh_categoria_pai_ciclo', __( 'Não é possível mover a categoria para dentro de uma subcategoria dela.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        return true;
    }

    /**
     * @return array<int,int>
     */
    private static function ids_irmaos( int $pai_id, int $incluir_id = 0 ): array {
        $grupos = self::agrupar_por_pai( self::todos_termos() );
        $ids    = [];
        foreach ( $grupos[ $pai_id ] ?? [] as $termo ) {
            if ( (int) $termo->term_id === $incluir_id ) {
                continue;
            }
            $ids[] = (int) $termo->term_id;
        }
        return $ids;
    }

    private static function definir_ordem_final( int $id, int $pai_id ): void {
        $irmaos   = self::ids_irmaos( $pai_id, $id );
        $irmaos[] = $id;
        self::aplicar_ordem_irmaos( $pai_id, $irmaos );
    }

    /**
     * @param array<int,int> $ids_ordenados
     */
    private static function aplicar_ordem_irmaos( int $pai_id, array $ids_ordenados ): void {
        foreach ( array_values( $ids_ordenados ) as $pos => $term_id ) {
            update_term_meta( (int) $term_id, 'order', (int) $pos );
        }
    }

    /**
     * Agrupa a árvore achatada por categoria raiz (para picker de produtos).
     *
     * @return array<int,array{raiz:array<string,mixed>,filhos:array<int,array<string,mixed>>}>
     */
    public static function agrupar_para_produto(): array {
        $grupos = [];
        $indice = -1;

        foreach ( self::listar_arvore_plana() as $item ) {
            if ( 0 === (int) $item['nivel'] ) {
                $grupos[] = [
                    'raiz'   => $item,
                    'filhos' => [],
                ];
                ++$indice;
                continue;
            }

            if ( $indice >= 0 ) {
                $grupos[ $indice ]['filhos'][] = $item;
            } else {
                $grupos[] = [
                    'raiz'   => $item,
                    'filhos' => [],
                ];
                ++$indice;
            }
        }

        return $grupos;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private static function definir_imagem( int $id, array $dados ): void {
        if ( ! array_key_exists( 'imagem_id', $dados ) ) {
            return;
        }
        $imagem_id = absint( $dados['imagem_id'] );
        if ( $imagem_id > 0 && 'attachment' === get_post_type( $imagem_id ) ) {
            update_term_meta( $id, 'thumbnail_id', $imagem_id );
        } else {
            delete_term_meta( $id, 'thumbnail_id' );
        }
    }
}

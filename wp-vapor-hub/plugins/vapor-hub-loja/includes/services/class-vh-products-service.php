<?php
/**
 * Serviço de Produtos.
 *
 * Usa a API de dados do WooCommerce (wc_get_products / WC_Product) para todas as
 * operações. As tags de marketing usadas pelo tema (exclusivo, personalizável)
 * são gerenciadas pela taxonomia product_tag.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Products_Service {

    /** Nº de volumes da expedição (campo `quantidadeVolumes` no Tiny). */
    public const META_VOLUMES = '_vh_volumes';

    /** Tipo de embalagem (campo `embalagem.tipo` no Tiny). */
    public const META_EMBALAGEM = '_vh_embalagem_tipo';

    /** Classificação fiscal (8 dígitos, campo `ncm` no Tiny). */
    public const META_NCM = '_vh_ncm';

    /** Código de barras EAN/GTIN (campo `gtin` no Tiny). */
    public const META_GTIN = '_vh_gtin';

    /** Unidade de medida comercial (campo `unidade` no Tiny). */
    public const META_UNIDADE = '_vh_unidade';

    /** Nome da marca digitado na loja. */
    public const META_MARCA = '_vh_marca';

    /** Id da marca no Tiny, quando a conta libera o cadastro de marcas. */
    public const META_MARCA_ID = '_vh_tiny_marca_id';

    /**
     * Tipos de embalagem aceitos pelo Tiny, na ordem exibida no formulário.
     *
     * @return array<int,string>
     */
    public static function embalagens(): array {
        return [
            0 => __( 'Não especificada', 'vapor-hub-loja' ),
            1 => __( 'Envelope', 'vapor-hub-loja' ),
            2 => __( 'Caixa / pacote', 'vapor-hub-loja' ),
            3 => __( 'Cilindro / rolo', 'vapor-hub-loja' ),
        ];
    }

    /**
     * Unidades de medida sugeridas no formulário (as mesmas do Tiny).
     *
     * @return array<int,string>
     */
    public static function unidades(): array {
        return [ 'UN', 'PC', 'CX', 'KIT', 'PAR', 'KG', 'G', 'L', 'ML', 'M', 'CM' ];
    }

    /**
     * Tags de destaque reconhecidas pelo tema.
     *
     * @return array<string,string> slug => rótulo
     */
    public static function tags_destaque(): array {
        return [
            'exclusivo'      => __( 'Exclusivo', 'vapor-hub-loja' ),
            'personalizavel' => __( 'Personalizável', 'vapor-hub-loja' ),
            'promocao'       => __( 'Promoção', 'vapor-hub-loja' ),
        ];
    }

    /**
     * Lista produtos paginados.
     *
     * @param array<string,mixed> $args
     * @return array{itens:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public static function listar( array $args ): array {
        $pagina     = max( 1, (int) ( $args['pagina'] ?? 1 ) );
        $por_pagina = min( 100, max( 1, (int) ( $args['por_pagina'] ?? 20 ) ) );

        $consulta = [
            'limit'    => $por_pagina,
            'page'     => $pagina,
            'paginate' => true,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'status'   => [ 'publish', 'draft', 'pending' ],
        ];

        $busca = isset( $args['busca'] ) ? sanitize_text_field( (string) $args['busca'] ) : '';
        if ( '' !== $busca ) {
            $consulta['s'] = $busca;
        }
        $categoria = isset( $args['categoria'] ) ? sanitize_title( (string) $args['categoria'] ) : '';
        if ( '' !== $categoria ) {
            $consulta['category'] = [ $categoria ];
        }

        $resultado = wc_get_products( $consulta );

        $itens = [];
        foreach ( $resultado->products as $produto ) {
            $itens[] = self::resumo( $produto );
        }

        return [
            'itens'   => $itens,
            'total'   => (int) $resultado->total,
            'paginas' => (int) $resultado->max_num_pages,
            'pagina'  => $pagina,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function resumo( WC_Product $produto ): array {
        return [
            'id'        => $produto->get_id(),
            'nome'      => $produto->get_name(),
            'sku'       => $produto->get_sku(),
            'tipo'      => $produto->get_type(),
            'preco_html'=> $produto->get_price_html(),
            'status'    => $produto->get_status(),
            'estoque'   => $produto->get_stock_quantity(),
            'em_estoque'=> $produto->is_in_stock(),
            'imagem'    => wp_get_attachment_image_url( $produto->get_image_id(), 'thumbnail' ) ?: '',
            'url_loja'  => 'publish' === $produto->get_status()
                ? $produto->get_permalink()
                : get_preview_post_link( $produto->get_id() ),
            'tiny'      => self::estado_tiny( $produto->get_id() ),
        ];
    }

    /**
     * Estado de sincronização Tiny do produto (para badges/avisos na UI).
     *
     * @return array<string,mixed>|null Null quando a integração Tiny não está disponível.
     */
    public static function estado_tiny( int $produto_id ): ?array {
        if ( ! class_exists( 'VH_Tiny' ) || ! class_exists( 'VH_Tiny_Sync_Service' ) ) {
            return null;
        }
        if ( ! VH_Tiny::pode_enviar() ) {
            return null;
        }
        return VH_Tiny_Sync_Service::estado_produto( $produto_id );
    }

    /**
     * Detalhe de um produto para o formulário.
     *
     * @return array<string,mixed>|null
     */
    public static function obter( int $id ): ?array {
        $produto = wc_get_product( $id );
        if ( ! $produto instanceof WC_Product ) {
            return null;
        }

        $tags_atuais = wp_get_post_terms( $id, 'product_tag', [ 'fields' => 'slugs' ] );
        $tags_atuais = is_wp_error( $tags_atuais ) ? [] : $tags_atuais;

        $galeria = [];
        foreach ( $produto->get_gallery_image_ids() as $img_id ) {
            $galeria[] = [ 'id' => (int) $img_id, 'url' => wp_get_attachment_image_url( $img_id, 'thumbnail' ) ?: '' ];
        }

        $dados = [
            'id'             => $produto->get_id(),
            'nome'           => $produto->get_name(),
            'slug'           => $produto->get_slug(),
            'tipo'           => $produto->get_type(),
            'status'         => $produto->get_status(),
            'descricao'      => $produto->get_description(),
            'descricao_curta'=> $produto->get_short_description(),
            'sku'            => $produto->get_sku(),
            'preco_regular'  => $produto->get_regular_price(),
            'preco_promo'    => $produto->get_sale_price(),
            'gerencia_estoque' => $produto->get_manage_stock(),
            'estoque'        => $produto->get_stock_quantity(),
            'peso'           => $produto->get_weight(),
            'largura'        => $produto->get_width(),
            'altura'         => $produto->get_height(),
            'comprimento'    => $produto->get_length(),
            'volumes'        => (int) get_post_meta( $id, self::META_VOLUMES, true ),
            'embalagem_tipo' => (int) get_post_meta( $id, self::META_EMBALAGEM, true ),
            'ncm'            => (string) get_post_meta( $id, self::META_NCM, true ),
            'gtin'           => (string) get_post_meta( $id, self::META_GTIN, true ),
            'unidade'        => (string) get_post_meta( $id, self::META_UNIDADE, true ),
            'marca'          => (string) get_post_meta( $id, self::META_MARCA, true ),
            'categorias'     => $produto->get_category_ids(),
            'tags'           => $tags_atuais,
            'imagem_id'      => $produto->get_image_id(),
            'imagem_url'     => wp_get_attachment_image_url( $produto->get_image_id(), 'thumbnail' ) ?: '',
            'galeria'        => $galeria,
            'atributos_selecionados' => [],
            'preco_base'     => '',
            'total_variacoes' => 0,
            'tiny'           => self::estado_tiny( $id ),
        ];

        if ( $produto instanceof WC_Product_Variable ) {
            $meta_base = get_post_meta( $id, '_vh_preco_base', true );
            if ( '' !== (string) $meta_base ) {
                $dados['preco_base'] = wc_format_decimal( (string) $meta_base );
            }
            foreach ( $produto->get_attributes() as $attr ) {
                if ( ! $attr instanceof WC_Product_Attribute || ! $attr->get_variation() || ! $attr->is_taxonomy() ) {
                    continue;
                }
                $taxonomy = $attr->get_name();
                $slugs    = [];
                foreach ( $attr->get_options() as $term_id ) {
                    $termo = get_term( (int) $term_id, $taxonomy );
                    if ( $termo && ! is_wp_error( $termo ) ) {
                        $slugs[] = $termo->slug;
                    }
                }
                $dados['atributos_selecionados'][ $taxonomy ] = $slugs;
            }

            $variacoes_ids            = $produto->get_children();
            $dados['total_variacoes'] = count( $variacoes_ids );
            $dados['variacoes']       = [];
            foreach ( $variacoes_ids as $variacao_id ) {
                $var = wc_get_product( (int) $variacao_id );
                if ( ! $var instanceof WC_Product_Variation ) {
                    continue;
                }
                $grade = [];
                foreach ( $var->get_attributes() as $taxonomy => $slug ) {
                    if ( '' === (string) $slug ) {
                        continue;
                    }
                    $termo = get_term_by( 'slug', (string) $slug, (string) $taxonomy );
                    $grade[] = [
                        'chave' => wc_attribute_label( (string) $taxonomy ),
                        'valor' => $termo instanceof WP_Term ? $termo->name : (string) $slug,
                    ];
                }
                $dados['variacoes'][] = [
                    'id'            => (int) $variacao_id,
                    'sku'           => $var->get_sku(),
                    'grade'         => $grade,
                    'preco_regular' => $var->get_regular_price(),
                    'preco_promo'   => $var->get_sale_price(),
                    'estoque'       => $var->get_manage_stock() ? $var->get_stock_quantity() : null,
                ];
            }
            if ( '' === $dados['preco_base'] && $variacoes_ids ) {
                $primeira = wc_get_product( (int) $variacoes_ids[0] );
                if ( $primeira instanceof WC_Product ) {
                    $dados['preco_base'] = $primeira->get_regular_price();
                }
            }

            if ( class_exists( 'VH_Personalizacao_Service' ) ) {
                $dados['personalizacao'] = VH_Personalizacao_Service::obter_config( $id );
            }
        }

        return $dados;
    }

    /**
     * Taxonomias de atributos usadas no sistema (Tiny mapping e legado).
     *
     * @return array<int,array{taxonomy:string,id:int,nome:string,tipo:string,termos:array<int,array<string,mixed>>}>
     */
    public static function atributos_personalizacao(): array {
        if ( class_exists( 'VH_Personalizacao_Service' ) ) {
            return VH_Personalizacao_Service::listar_taxonomias_sistema();
        }
        return [];
    }

    /**
     * Cor (hex) de um termo: usa o meta _vh_cor_hex se definido, senão tenta
     * deduzir pelo nome/slug. Mesma base usada pela loja.
     */
    public static function cor_termo( \WP_Term $termo ): string {
        $meta = (string) get_term_meta( $termo->term_id, '_vh_cor_hex', true );
        if ( $meta && preg_match( '/^#?[0-9a-fA-F]{6}$/', $meta ) ) {
            return '#' . ltrim( $meta, '#' );
        }

        $mapa = [
            'laranja'          => '#f27f0d',
            'laranja-vibrante' => '#f27f0d',
            'verde'            => '#22c55e',
            'vermelho'         => '#ef4444',
            'azul'             => '#3b82f6',
            'preto'            => '#1c1917',
            'branco'           => '#ffffff',
            'amarelo'          => '#facc15',
            'roxo'             => '#a855f7',
            'rosa'             => '#ec4899',
            'cinza'            => '#6b7280',
            'marrom'           => '#92400e',
            'dourado'          => '#d4af37',
            'prata'            => '#c0c0c0',
        ];

        if ( isset( $mapa[ $termo->slug ] ) ) {
            return $mapa[ $termo->slug ];
        }
        $nome = sanitize_title( $termo->name );
        if ( isset( $mapa[ $nome ] ) ) {
            return $mapa[ $nome ];
        }
        return '#c0c0c0';
    }

    /**
     * Acréscimo de preço (R$) de um termo de atributo global.
     */
    public static function preco_acrescimo_termo( \WP_Term $termo ): string {
        $meta = get_term_meta( $termo->term_id, '_vh_preco_acrescimo', true );
        if ( '' === $meta || false === $meta ) {
            return '';
        }
        $valor = wc_format_decimal( (string) $meta );
        return '' === $valor || (float) $valor <= 0 ? '' : $valor;
    }

    /**
     * Sanitiza valor monetário (>= 0, teto de segurança).
     */
    private static function sanitizar_preco_monetario( string $valor ): string {
        $valor = trim( str_replace( ',', '.', $valor ) );
        if ( '' === $valor ) {
            return '';
        }
        if ( ! is_numeric( $valor ) ) {
            return '';
        }
        $num = (float) wc_format_decimal( $valor );
        if ( $num < 0 || $num > 999999.99 ) {
            return '';
        }
        return wc_format_decimal( (string) $num );
    }

    /**
     * Sanitiza acréscimo monetário (>= 0, teto de segurança).
     */
    private static function sanitizar_preco_acrescimo( string $valor ): string {
        $limpo = self::sanitizar_preco_monetario( $valor );
        return '' === $limpo || (float) $limpo <= 0 ? '' : $limpo;
    }

    /**
     * Preço final de uma combinação = base + soma dos acréscimos dos termos.
     *
     * @param array<string,string> $combo taxonomy => slug
     */
    private static function calcular_preco_combinacao( string $preco_base, array $combo ): string {
        $total = max( 0, (float) wc_format_decimal( $preco_base ?: '0' ) );

        foreach ( $combo as $taxonomy => $slug ) {
            $taxonomy = sanitize_key( (string) $taxonomy );
            $slug     = sanitize_title( (string) $slug );
            if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }
            $termo = get_term_by( 'slug', $slug, $taxonomy );
            if ( ! $termo || is_wp_error( $termo ) ) {
                continue;
            }
            $acrescimo = self::preco_acrescimo_termo( $termo );
            if ( '' !== $acrescimo ) {
                $total += (float) $acrescimo;
            }
        }

        return wc_format_decimal( (string) max( 0, $total ) );
    }

    /**
     * Cria um produto simples.
     *
     * @param array<string,mixed> $dados
     * @return int|WP_Error
     */
    public static function criar( array $dados ) {
        $nome = sanitize_text_field( (string) ( $dados['nome'] ?? '' ) );
        if ( '' === $nome ) {
            return new WP_Error( 'vh_produto_sem_nome', __( 'Informe o nome do produto.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $tipo    = self::tipo_desejado( $dados );
        $produto = ( 'variable' === $tipo ) ? new WC_Product_Variable() : new WC_Product_Simple();

        $erro = self::aplicar( $produto, $dados );
        if ( is_wp_error( $erro ) ) {
            return $erro;
        }
        $id = $produto->save();
        if ( ! $id ) {
            return new WP_Error( 'vh_produto_erro', __( 'Não foi possível salvar o produto.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( 'variable' === $tipo ) {
            $variavel = wc_get_product( $id );
            if ( $variavel instanceof WC_Product_Variable ) {
                $sync = self::sincronizar_personalizacao( $variavel, $dados );
                if ( is_wp_error( $sync ) ) {
                    return $sync;
                }
            }
        }

        /**
         * Disparado após criar produto via Minha Loja.
         *
         * @param int                  $id
         * @param array<string, mixed> $dados
         * @param string               $acao
         */
        do_action( 'vh_produto_salvo', (int) $id, $dados, 'criar' );

        return (int) $id;
    }

    /**
     * Normaliza o tipo de produto solicitado pelo formulário.
     *
     * @param array<string,mixed> $dados
     */
    private static function tipo_desejado( array $dados ): string {
        $tipo = sanitize_key( (string) ( $dados['tipo'] ?? 'simple' ) );
        return 'variable' === $tipo ? 'variable' : 'simple';
    }

    /**
     * Atualiza um produto existente (mantém o tipo atual).
     *
     * @param array<string,mixed> $dados
     * @return true|WP_Error
     */
    public static function atualizar( int $id, array $dados ) {
        $produto = wc_get_product( $id );
        if ( ! $produto instanceof WC_Product ) {
            return new WP_Error( 'vh_produto_inexistente', __( 'Produto não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        /* Conversão de tipo (simples ⇄ variável) quando solicitada pelo formulário. */
        if ( isset( $dados['tipo'] ) ) {
            $desejado = self::tipo_desejado( $dados );
            if ( $desejado !== $produto->get_type() ) {
                wp_set_object_terms( $id, $desejado, 'product_type' );
                $recarregado = wc_get_product( $id );
                if ( $recarregado instanceof WC_Product ) {
                    $produto = $recarregado;
                }
            }
        }

        $erro = self::aplicar( $produto, $dados );
        if ( is_wp_error( $erro ) ) {
            return $erro;
        }
        $produto->save();

        if ( $produto instanceof WC_Product_Variable ) {
            $sync = self::sincronizar_personalizacao( $produto, $dados );
            if ( is_wp_error( $sync ) ) {
                return $sync;
            }
            self::aplicar_saldo_variacoes( $produto, $dados );
        }

        /**
         * Disparado após atualizar produto via Minha Loja.
         *
         * @param int                  $id
         * @param array<string, mixed> $dados
         * @param string               $acao
         */
        do_action( 'vh_produto_salvo', $id, $dados, 'atualizar' );

        return true;
    }

    /**
     * Saldo por combinação, como o formulário envia (`variacoes[ID][estoque]`).
     *
     * Só variações deste produto são aceitas: o id chega do navegador e apontar
     * para a variação de outro produto editaria estoque alheio.
     *
     * @param array<string,mixed> $dados
     */
    private static function aplicar_saldo_variacoes( WC_Product_Variable $produto, array $dados ): void {
        $entradas = $dados['variacoes'] ?? null;
        if ( ! is_array( $entradas ) || ! $entradas ) {
            return;
        }

        $filhos = array_map( 'intval', $produto->get_children() );

        foreach ( $entradas as $chave => $valores ) {
            if ( ! is_array( $valores ) ) {
                continue;
            }
            $variacao_id = (int) ( $valores['id'] ?? $chave );
            if ( $variacao_id <= 0 || ! in_array( $variacao_id, $filhos, true ) ) {
                continue;
            }
            if ( ! array_key_exists( 'estoque', $valores ) || '' === trim( (string) $valores['estoque'] ) ) {
                continue;
            }

            self::atualizar_variacao(
                $variacao_id,
                [
                    'gerencia_estoque' => true,
                    'estoque'          => (int) $valores['estoque'],
                ]
            );
        }
    }

    /**
     * Atualiza preço/estoque de uma variação isolada (sem regenerar a grade).
     *
     * @param array<string,mixed> $financeiro
     * @return true|WP_Error
     */
    public static function atualizar_variacao( int $variacao_id, array $financeiro ) {
        $variacao = wc_get_product( $variacao_id );
        if ( ! $variacao instanceof WC_Product_Variation ) {
            return new WP_Error( 'vh_variacao_inexistente', __( 'Variação não encontrada.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        if ( isset( $financeiro['preco_regular'] ) ) {
            $variacao->set_regular_price( wc_format_decimal( (string) $financeiro['preco_regular'] ) );
            $variacao->set_price( wc_format_decimal( (string) $financeiro['preco_regular'] ) );
        }
        if ( array_key_exists( 'preco_promo', $financeiro ) ) {
            $promo = (string) $financeiro['preco_promo'];
            $variacao->set_sale_price( '' === $promo ? '' : wc_format_decimal( $promo ) );
        }
        if ( isset( $financeiro['gerencia_estoque'] ) || isset( $financeiro['estoque'] ) ) {
            $variacao->set_manage_stock( true );
            if ( isset( $financeiro['estoque'] ) ) {
                $variacao->set_stock_quantity( (int) $financeiro['estoque'] );
            }
        }

        $variacao->save();
        wc_delete_product_transients( $variacao->get_parent_id() );

        return true;
    }

    /**
     * Move um produto para a lixeira.
     *
     * @return true|WP_Error
     */
    public static function excluir( int $id ) {
        $produto = wc_get_product( $id );
        if ( ! $produto instanceof WC_Product ) {
            return new WP_Error( 'vh_produto_inexistente', __( 'Produto não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }
        $produto->delete( false );

        /** @param int $id */
        do_action( 'vh_produto_excluido', $id );

        return true;
    }

    /**
     * Aplica os dados sanitizados ao objeto de produto.
     *
     * Preço/estoque só são tocados em produtos simples (produtos variáveis têm
     * esses dados nas variações).
     *
     * @param array<string,mixed> $dados
     * @return true|WP_Error
     */
    private static function aplicar( WC_Product $produto, array $dados ) {
        if ( isset( $dados['nome'] ) ) {
            $produto->set_name( sanitize_text_field( (string) $dados['nome'] ) );
        }
        if ( isset( $dados['slug'] ) ) {
            $produto->set_slug( sanitize_title( (string) $dados['slug'] ) );
        }
        if ( isset( $dados['status'] ) ) {
            $status = sanitize_key( (string) $dados['status'] );
            $produto->set_status( in_array( $status, [ 'publish', 'draft', 'pending' ], true ) ? $status : 'draft' );
        }
        if ( isset( $dados['descricao'] ) ) {
            $produto->set_description( wp_kses_post( (string) $dados['descricao'] ) );
        }
        if ( isset( $dados['descricao_curta'] ) ) {
            $produto->set_short_description( wp_kses_post( (string) $dados['descricao_curta'] ) );
        }
        if ( isset( $dados['sku'] ) ) {
            try {
                $produto->set_sku( wc_clean( (string) $dados['sku'] ) );
            } catch ( WC_Data_Exception $e ) {
                return new WP_Error( 'vh_sku_duplicado', __( 'O SKU informado já está em uso.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
            }
        }

        if ( $produto->is_type( 'simple' ) ) {
            if ( isset( $dados['preco_regular'] ) ) {
                $produto->set_regular_price( wc_format_decimal( (string) $dados['preco_regular'] ) );
            }
            if ( array_key_exists( 'preco_promo', $dados ) ) {
                $promo = (string) $dados['preco_promo'];
                $produto->set_sale_price( '' === $promo ? '' : wc_format_decimal( $promo ) );
            }
            if ( isset( $dados['gerencia_estoque'] ) ) {
                $gerencia = in_array( (string) $dados['gerencia_estoque'], [ '1', 'true', 'on' ], true );
                $produto->set_manage_stock( $gerencia );
                if ( $gerencia && isset( $dados['estoque'] ) ) {
                    $produto->set_stock_quantity( (int) $dados['estoque'] );
                }
            }
        }

        self::aplicar_logistica( $produto, $dados );
        self::aplicar_fiscal( $produto, $dados );

        if ( isset( $dados['categorias'] ) ) {
            $ids = array_map( 'absint', (array) $dados['categorias'] );
            $produto->set_category_ids( array_filter( $ids ) );
        }

        if ( isset( $dados['imagem_id'] ) ) {
            $produto->set_image_id( absint( $dados['imagem_id'] ) ?: '' );
        }
        if ( isset( $dados['galeria_ids'] ) ) {
            $galeria = is_array( $dados['galeria_ids'] )
                ? $dados['galeria_ids']
                : array_filter( array_map( 'trim', explode( ',', (string) $dados['galeria_ids'] ) ) );
            $produto->set_gallery_image_ids( array_filter( array_map( 'absint', $galeria ) ) );
        }

        if ( isset( $dados['tags'] ) ) {
            $permitidas = array_keys( self::tags_destaque() );
            $escolhidas = array_intersect( array_map( 'sanitize_key', (array) $dados['tags'] ), $permitidas );
            $produto->set_tag_ids( self::slugs_para_ids( $escolhidas ) );
        }

        return true;
    }

    /**
     * Peso, medidas, volumes e embalagem — sempre no produto pai.
     *
     * Vale para simples e personalizável: uma medida por produto. A variação
     * herda do pai (comportamento nativo do WooCommerce), que é o que os
     * Correios leem no item do carrinho e o Tiny mostra em “Dimensões e peso”.
     *
     * Campo ausente no formulário não é campo vazio: só mexemos no que veio.
     *
     * @param array<string,mixed> $dados
     */
    private static function aplicar_logistica( WC_Product $produto, array $dados ): void {
        $medidas = [
            'peso'        => 'set_weight',
            'largura'     => 'set_width',
            'altura'      => 'set_height',
            'comprimento' => 'set_length',
        ];

        foreach ( $medidas as $campo => $setter ) {
            if ( ! array_key_exists( $campo, $dados ) ) {
                continue;
            }
            $valor = trim( (string) $dados[ $campo ] );
            $produto->{$setter}( '' === $valor ? '' : wc_format_decimal( $valor ) );
        }

        if ( array_key_exists( 'volumes', $dados ) ) {
            $volumes = absint( $dados['volumes'] );
            if ( $volumes > 0 ) {
                $produto->update_meta_data( self::META_VOLUMES, min( 999, $volumes ) );
            } else {
                $produto->delete_meta_data( self::META_VOLUMES );
            }
        }

        if ( array_key_exists( 'embalagem_tipo', $dados ) ) {
            $tipo = absint( $dados['embalagem_tipo'] );
            if ( $tipo > 0 && array_key_exists( $tipo, self::embalagens() ) ) {
                $produto->update_meta_data( self::META_EMBALAGEM, $tipo );
            } else {
                $produto->delete_meta_data( self::META_EMBALAGEM );
            }
        }
    }

    /**
     * Identidade fiscal do produto: NCM, GTIN, unidade e marca.
     *
     * O formulário aceita o que o lojista tem em mão (NCM com pontos, unidade
     * minúscula) e guardamos no formato que o Tiny espera. Campo ausente não é
     * campo vazio: só mexemos no que veio no corpo da requisição.
     *
     * @param array<string,mixed> $dados
     */
    private static function aplicar_fiscal( WC_Product $produto, array $dados ): void {
        if ( array_key_exists( 'ncm', $dados ) ) {
            $ncm = preg_replace( '/\D/', '', (string) $dados['ncm'] ) ?? '';
            self::gravar_meta_fiscal( $produto, self::META_NCM, substr( $ncm, 0, 8 ) );
        }

        if ( array_key_exists( 'gtin', $dados ) ) {
            $gtin = preg_replace( '/\D/', '', (string) $dados['gtin'] ) ?? '';
            self::gravar_meta_fiscal( $produto, self::META_GTIN, substr( $gtin, 0, 14 ) );
        }

        if ( array_key_exists( 'unidade', $dados ) ) {
            $unidade = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $dados['unidade'] ) ?? '' );
            self::gravar_meta_fiscal( $produto, self::META_UNIDADE, substr( $unidade, 0, 6 ) );
        }

        if ( array_key_exists( 'marca', $dados ) ) {
            $marca = sanitize_text_field( (string) $dados['marca'] );
            self::gravar_meta_fiscal( $produto, self::META_MARCA, mb_substr( trim( $marca ), 0, 60 ) );
        }
    }

    private static function gravar_meta_fiscal( WC_Product $produto, string $meta, string $valor ): void {
        if ( '' === $valor ) {
            $produto->delete_meta_data( $meta );
            return;
        }
        $produto->update_meta_data( $meta, $valor );
    }

    /**
     * Define os atributos de variação do produto e gera/poda as variações.
     *
     * @return true|WP_Error
     */
    private static function sincronizar_personalizacao( WC_Product_Variable $produto, array $dados ) {
        if ( ! class_exists( 'VH_Personalizacao_Service' ) ) {
            return true;
        }

        $config = $dados['personalizacao'] ?? null;
        if ( ! is_array( $config ) || empty( $config['dimensoes'] ) ) {
            return true;
        }

        if ( isset( $dados['preco_base'] ) && ! isset( $config['preco_base'] ) ) {
            $config['preco_base'] = $dados['preco_base'];
        }

        $resultado = VH_Personalizacao_Service::salvar( $produto, $config );
        if ( is_wp_error( $resultado ) ) {
            return $resultado;
        }

        self::gerar_variacoes( $produto, $resultado['selecao'], (string) $resultado['preco_base'] );

        WC_Product_Variable::sync( $produto->get_id() );
        wc_delete_product_transients( $produto->get_id() );

        return true;
    }

    /**
     * Cria as variações faltantes para o produto cartesiano da seleção e remove
     * as variações que não correspondem mais à seleção atual.
     *
     * @param array<string,string[]> $selecao taxonomy => slugs
     */
    public static function gerar_variacoes( WC_Product_Variable $produto, array $selecao, string $preco ): void {
        $product_id  = $produto->get_id();
        $taxonomias  = array_keys( $selecao );
        $combinacoes = self::produto_cartesiano( $selecao );

        $existentes = get_children( [
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'post_status' => [ 'publish', 'private' ],
            'fields'      => 'ids',
        ] );

        $mapa = [];
        foreach ( $existentes as $variacao_id ) {
            $variacao = wc_get_product( (int) $variacao_id );
            if ( ! $variacao instanceof WC_Product_Variation ) {
                continue;
            }
            $atribs = $variacao->get_attributes();

            $valida = ! empty( $selecao );
            foreach ( $selecao as $taxonomy => $slugs ) {
                $slug = $atribs[ $taxonomy ] ?? '';
                if ( '' === $slug || ! in_array( $slug, $slugs, true ) ) {
                    $valida = false;
                    break;
                }
            }
            if ( $valida ) {
                foreach ( $atribs as $taxonomy => $slug ) {
                    if ( '' !== $slug && ! isset( $selecao[ $taxonomy ] ) ) {
                        $valida = false;
                        break;
                    }
                }
            }

            if ( ! $valida ) {
                $tiny_var_id = (int) get_post_meta( (int) $variacao_id, '_vh_tiny_id', true );
                if ( $tiny_var_id > 0 ) {
                    $removidas   = get_post_meta( $product_id, '_vh_tiny_var_removidas', true );
                    $removidas   = is_array( $removidas ) ? $removidas : [];
                    $removidas[] = $tiny_var_id;
                    update_post_meta( $product_id, '_vh_tiny_var_removidas', array_values( array_unique( array_map( 'intval', $removidas ) ) ) );
                }
                $variacao->delete( true );
                continue;
            }

            $mapa[ self::assinatura( $atribs, $taxonomias ) ] = $variacao_id;

            if ( class_exists( 'VH_Tiny_SKU' ) ) {
                $sku = VH_Tiny_SKU::gerar_variacao( $produto, $atribs, (int) $variacao_id );
                if ( '' === $variacao->get_sku() || $variacao->get_sku() !== $sku ) {
                    try {
                        $variacao->set_sku( $sku );
                    } catch ( WC_Data_Exception $e ) {
                        unset( $e );
                    }
                }
            }
            if ( ! $variacao->get_manage_stock() ) {
                $variacao->set_manage_stock( true );
                if ( null === $variacao->get_stock_quantity() ) {
                    $variacao->set_stock_quantity( 0 );
                }
            }

            if ( '' !== $preco ) {
                $preco_final = self::calcular_preco_combinacao( $preco, $atribs );
                $variacao->set_regular_price( $preco_final );
                $variacao->set_price( $preco_final );
                $variacao->save();
            }
        }

        foreach ( $combinacoes as $combo ) {
            $assinatura = self::assinatura( $combo, $taxonomias );
            if ( isset( $mapa[ $assinatura ] ) ) {
                if ( '' !== $preco ) {
                    $existente = wc_get_product( (int) $mapa[ $assinatura ] );
                    if ( $existente instanceof WC_Product_Variation ) {
                        $preco_final = self::calcular_preco_combinacao( $preco, $combo );
                        $existente->set_regular_price( $preco_final );
                        $existente->set_price( $preco_final );
                        $existente->save();
                    }
                }
                continue;
            }
            $variacao = new WC_Product_Variation();
            $variacao->set_parent_id( $product_id );
            $variacao->set_status( 'publish' );
            $variacao->set_attributes( $combo );
            if ( class_exists( 'VH_Tiny_SKU' ) ) {
                try {
                    $variacao->set_sku( VH_Tiny_SKU::gerar_variacao( $produto, $combo, 0 ) );
                } catch ( WC_Data_Exception $e ) {
                    unset( $e );
                }
            }
            if ( '' !== $preco ) {
                $preco_final = self::calcular_preco_combinacao( $preco, $combo );
                $variacao->set_regular_price( $preco_final );
                $variacao->set_price( $preco_final );
            }
            $variacao->set_manage_stock( true );
            $variacao->set_stock_quantity( 0 );
            $variacao->save();
        }
    }

    /**
     * Produto cartesiano da seleção (taxonomy => slugs).
     *
     * @param array<string,string[]> $selecao
     * @return array<int,array<string,string>>
     */
    private static function produto_cartesiano( array $selecao ): array {
        $resultado = [ [] ];
        foreach ( $selecao as $taxonomy => $slugs ) {
            $novo = [];
            foreach ( $resultado as $combo ) {
                foreach ( $slugs as $slug ) {
                    $novo[] = $combo + [ $taxonomy => $slug ];
                }
            }
            $resultado = $novo;
        }
        return $resultado;
    }

    /**
     * Assinatura estável de uma combinação de atributos para comparação.
     *
     * @param array<string,string> $atribs
     * @param string[]             $taxonomias
     */
    private static function assinatura( array $atribs, array $taxonomias ): string {
        $ordenadas = $taxonomias;
        sort( $ordenadas );
        $partes = [];
        foreach ( $ordenadas as $taxonomy ) {
            $partes[] = $taxonomy . '=' . ( $atribs[ $taxonomy ] ?? '' );
        }
        return implode( '|', $partes );
    }

    /**
     * Converte slugs de product_tag em IDs, criando os termos se necessário.
     *
     * @param string[] $slugs
     * @return int[]
     */
    private static function slugs_para_ids( array $slugs ): array {
        $ids = [];
        foreach ( $slugs as $slug ) {
            $termo = get_term_by( 'slug', $slug, 'product_tag' );
            if ( ! $termo ) {
                $novo = wp_insert_term( $slug, 'product_tag', [ 'slug' => $slug ] );
                if ( ! is_wp_error( $novo ) ) {
                    $ids[] = (int) $novo['term_id'];
                }
                continue;
            }
            $ids[] = (int) $termo->term_id;
        }
        return $ids;
    }
}

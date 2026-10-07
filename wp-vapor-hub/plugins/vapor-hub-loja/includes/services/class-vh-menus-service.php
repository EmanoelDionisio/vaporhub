<?php
/**
 * Menus da vitrine editados pelo painel.
 *
 * Usa os menus nativos do WordPress (nav menu) e as posições do tema.
 * O painel não guarda uma árvore paralela.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Menus_Service {

    public const MAX_ITENS        = 120;
    public const MAX_PROFUNDIDADE = 5;
    public const MAX_TITULO       = 80;
    public const MAX_URL          = 500;

    /**
     * Posições que o tema desenha.
     *
     * @return array<string,array{rotulo:string,descricao:string,menu:string}>
     */
    public static function posicoes(): array {
        return [
            'principal'     => [
                'rotulo'    => __( 'Menu principal', 'vapor-hub-loja' ),
                'descricao' => __( 'Links do topo, ao lado da busca. Aceita subníveis.', 'vapor-hub-loja' ),
                'menu'      => 'Vapor Hub — Principal',
            ],
            'departamentos' => [
                'rotulo'    => __( 'Departamentos', 'vapor-hub-loja' ),
                'descricao' => __( 'Faixa de categorias abaixo do topo. Some da loja enquanto estiver vazia.', 'vapor-hub-loja' ),
                'menu'      => 'Vapor Hub — Departamentos',
            ],
            'rodape'        => [
                'rotulo'    => __( 'Rodapé', 'vapor-hub-loja' ),
                'descricao' => __( 'Coluna Loja do rodapé. Vazio, essa coluna lista as categorias raiz.', 'vapor-hub-loja' ),
                'menu'      => 'Vapor Hub — Rodapé',
            ],
        ];
    }

    public static function posicao_valida( string $posicao ): bool {
        return isset( self::posicoes()[ $posicao ] );
    }

    public static function posicao_requisicao(): string {
        $posicao = isset( $_GET['posicao'] ) ? sanitize_key( wp_unslash( $_GET['posicao'] ) ) : 'principal';
        return self::posicao_valida( $posicao ) ? $posicao : 'principal';
    }

    /**
     * Estado do editor para uma posição.
     *
     * @return array{posicao:string,itens:array<int,array<string,mixed>>,atribuido:bool,automatico:bool,origens:array<string,mixed>}
     */
    public static function estado( string $posicao ): array {
        $menu_id    = self::menu_id( $posicao );
        $automatico = ( 'principal' === $posicao && $menu_id <= 0 );

        return [
            'posicao'    => $posicao,
            'itens'      => $automatico ? self::semente_principal() : ( $menu_id > 0 ? self::arvore_do_menu( $menu_id ) : [] ),
            'atribuido'  => $menu_id > 0,
            'automatico' => $automatico,
            'origens'    => self::origens(),
        ];
    }

    /**
     * Grava a árvore inteira da posição.
     *
     * Árvore vazia devolve a posição ao comportamento automático da loja.
     *
     * @param array<int,mixed> $itens
     * @return array<string,mixed>|WP_Error
     */
    public static function salvar( string $posicao, array $itens ) {
        if ( ! self::posicao_valida( $posicao ) ) {
            return self::falha( 'vh_menu_posicao', __( 'Posição de menu inválida.', 'vapor-hub-loja' ) );
        }

        $total = 0;
        $limpo = self::normalizar_nivel( $itens, 1, $total );
        if ( is_wp_error( $limpo ) ) {
            return $limpo;
        }

        if ( [] === $limpo ) {
            self::como_editor_de_menu( static function () use ( $posicao ): void {
                self::esvaziar( $posicao );
            } );
            return self::resposta( $posicao );
        }

        $gravou = self::como_editor_de_menu( static function () use ( $posicao, $limpo ) {
            return self::persistir( $posicao, $limpo );
        } );
        if ( is_wp_error( $gravou ) ) {
            return $gravou;
        }

        return self::resposta( $posicao );
    }

    /**
     * O gestor não tem edit_theme_options. A escrita do menu nativo pede essa
     * capacidade, então ela vale só durante esta gravação e só para quem já
     * administra a loja.
     *
     * @template T
     * @param callable():T $acao
     * @return T
     */
    private static function como_editor_de_menu( callable $acao ) {
        $usuario_id = get_current_user_id();
        $filtro     = static function ( array $allcaps, array $caps, array $args, WP_User $user ) use ( $usuario_id ): array {
            unset( $caps, $args );
            if ( (int) $user->ID === $usuario_id && ! empty( $allcaps[ VH_Roles::CAP ] ) ) {
                $allcaps['edit_theme_options'] = true;
            }
            return $allcaps;
        };

        add_filter( 'user_has_cap', $filtro, 10, 4 );
        try {
            return $acao();
        } finally {
            remove_filter( 'user_has_cap', $filtro, 10 );
        }
    }

    /**
     * @param array<int,array<string,mixed>> $itens
     * @return true|WP_Error
     */
    private static function persistir( string $posicao, array $itens ) {
        $menu_id = self::garantir_menu( $posicao );
        if ( is_wp_error( $menu_id ) ) {
            return $menu_id;
        }

        $antes      = [];
        $existentes = wp_get_nav_menu_items( $menu_id );
        if ( is_array( $existentes ) ) {
            foreach ( $existentes as $existente ) {
                $antes[] = (int) $existente->ID;
            }
        }

        $ordem    = 1;
        $vistos   = [];
        $mantidos = [];
        $gravou   = self::gravar_nivel( $menu_id, $itens, 0, $ordem, $vistos, $mantidos );
        if ( is_wp_error( $gravou ) ) {
            return $gravou;
        }

        foreach ( $antes as $antigo ) {
            if ( ! isset( $mantidos[ $antigo ] ) ) {
                wp_delete_post( $antigo, true );
            }
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private static function resposta( string $posicao ): array {
        $estado = self::estado( $posicao );
        unset( $estado['origens'] );
        return $estado;
    }

    /**
     * @return array{paginas:array<int,array{id:int,titulo:string}>,categorias:array<int,array{id:int,nome:string,profundidade:int}>}
     */
    public static function origens(): array {
        $paginas = get_posts( [
            'post_type'              => 'page',
            'post_status'            => 'publish',
            'posts_per_page'         => 200,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] );

        $lista_paginas = [];
        foreach ( $paginas as $pagina ) {
            $lista_paginas[] = [
                'id'     => (int) $pagina->ID,
                'titulo' => $pagina->post_title,
            ];
        }

        return [
            'paginas'    => $lista_paginas,
            'categorias' => self::categorias_achatadas(),
        ];
    }

    /**
     * Caminho da própria loja (começa com /) ou endereço http(s).
     * Endereço do mesmo domínio vira caminho, para continuar válido no ambiente local.
     */
    public static function url_permitida( string $url ): string {
        $url = trim( wp_strip_all_tags( $url ) );
        if ( '' === $url || strlen( $url ) > self::MAX_URL ) {
            return '';
        }
        if ( preg_match( '/[\s\x00-\x1F\\\\]/', $url ) ) {
            return '';
        }

        if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
            $limpa = esc_url_raw( $url );
            if ( ! is_string( $limpa ) || ! str_starts_with( $limpa, '/' ) || str_starts_with( $limpa, '//' ) ) {
                return '';
            }
            return $limpa;
        }

        $limpa = esc_url_raw( $url, [ 'http', 'https' ] );
        if ( ! is_string( $limpa ) || '' === $limpa ) {
            return '';
        }

        $scheme = wp_parse_url( $limpa, PHP_URL_SCHEME );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return '';
        }

        $caminho_local = self::caminho_do_mesmo_dominio( $limpa );
        if ( '' !== $caminho_local ) {
            return $caminho_local;
        }

        if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $limpa ) ) {
            return '';
        }

        return $limpa;
    }

    private static function caminho_do_mesmo_dominio( string $url ): string {
        $casa   = wp_parse_url( home_url( '/' ) );
        $partes = wp_parse_url( $url );
        if ( ! is_array( $casa ) || ! is_array( $partes ) || empty( $casa['host'] ) || empty( $partes['host'] ) ) {
            return '';
        }
        if ( 0 !== strcasecmp( (string) $casa['host'], (string) $partes['host'] ) ) {
            return '';
        }

        $caminho = (string) ( $partes['path'] ?? '/' );
        if ( ! str_starts_with( $caminho, '/' ) ) {
            $caminho = '/' . $caminho;
        }
        if ( isset( $partes['query'] ) && is_string( $partes['query'] ) && '' !== $partes['query'] ) {
            $caminho .= '?' . $partes['query'];
        }
        if ( isset( $partes['fragment'] ) && is_string( $partes['fragment'] ) && '' !== $partes['fragment'] ) {
            $caminho .= '#' . $partes['fragment'];
        }
        return $caminho;
    }

    /**
     * @param array<int,mixed> $itens
     * @return array<int,array<string,mixed>>|WP_Error
     */
    private static function normalizar_nivel( array $itens, int $nivel, int &$total ) {
        if ( [] === $itens ) {
            return [];
        }
        if ( $nivel > self::MAX_PROFUNDIDADE ) {
            return self::falha(
                'vh_menu_profundo',
                sprintf(
                    /* translators: %d: maximum menu depth */
                    __( 'O menu aceita no máximo %d níveis.', 'vapor-hub-loja' ),
                    self::MAX_PROFUNDIDADE
                )
            );
        }

        $saida = [];
        foreach ( $itens as $bruto ) {
            if ( ! is_array( $bruto ) ) {
                return self::falha( 'vh_menu_invalido', __( 'A estrutura do menu é inválida.', 'vapor-hub-loja' ) );
            }
            $total++;
            if ( $total > self::MAX_ITENS ) {
                return self::falha(
                    'vh_menu_limite',
                    sprintf(
                        /* translators: %d: maximum menu items */
                        __( 'O menu aceita no máximo %d itens.', 'vapor-hub-loja' ),
                        self::MAX_ITENS
                    )
                );
            }

            $item = self::normalizar_item( $bruto );
            if ( is_wp_error( $item ) ) {
                return $item;
            }

            $filhos = $bruto['filhos'] ?? [];
            if ( ! is_array( $filhos ) ) {
                return self::falha( 'vh_menu_invalido', __( 'A estrutura do menu é inválida.', 'vapor-hub-loja' ) );
            }
            $filhos_ok = self::normalizar_nivel( $filhos, $nivel + 1, $total );
            if ( is_wp_error( $filhos_ok ) ) {
                return $filhos_ok;
            }
            $item['filhos'] = $filhos_ok;
            $item['icone']  = ( 1 === $nivel ) ? self::icone_permitido( (string) ( $bruto['icone'] ?? '' ) ) : '';
            $saida[]        = $item;
        }

        return $saida;
    }

    /**
     * @param array<string,mixed> $bruto
     * @return array<string,mixed>|WP_Error
     */
    private static function normalizar_item( array $bruto ) {
        $titulo = sanitize_text_field( (string) ( $bruto['titulo'] ?? '' ) );
        if ( function_exists( 'mb_substr' ) ) {
            $titulo = mb_substr( $titulo, 0, self::MAX_TITULO );
        } else {
            $titulo = substr( $titulo, 0, self::MAX_TITULO );
        }
        if ( '' === $titulo ) {
            return self::falha( 'vh_menu_titulo', __( 'Todo item do menu precisa de um título.', 'vapor-hub-loja' ) );
        }

        $tipo = sanitize_key( (string) ( $bruto['tipo'] ?? '' ) );
        if ( ! in_array( $tipo, [ 'link', 'pagina', 'categoria' ], true ) ) {
            return self::falha( 'vh_menu_tipo', __( 'Tipo de item inválido.', 'vapor-hub-loja' ) );
        }

        if ( 'pagina' === $tipo ) {
            $pagina_id = absint( $bruto['destino'] ?? 0 );
            $pagina    = get_post( $pagina_id );
            if ( ! $pagina instanceof WP_Post || 'page' !== $pagina->post_type || 'publish' !== $pagina->post_status ) {
                return self::falha( 'vh_menu_pagina', __( 'A página selecionada não está publicada.', 'vapor-hub-loja' ) );
            }
            $destino = (string) $pagina_id;
        } elseif ( 'categoria' === $tipo ) {
            $termo_id = absint( $bruto['destino'] ?? 0 );
            $termo    = get_term( $termo_id, 'product_cat' );
            if ( ! $termo instanceof WP_Term ) {
                return self::falha( 'vh_menu_categoria', __( 'A categoria selecionada não existe.', 'vapor-hub-loja' ) );
            }
            $destino = (string) $termo_id;
        } else {
            $destino = self::url_permitida( (string) ( $bruto['destino'] ?? '' ) );
            if ( '' === $destino ) {
                return self::falha( 'vh_menu_url', __( 'O link informado não é permitido. Use um caminho da loja ou um endereço http(s).', 'vapor-hub-loja' ) );
            }
        }

        return [
            'id'      => absint( $bruto['id'] ?? 0 ),
            'titulo'  => $titulo,
            'tipo'    => $tipo,
            'destino' => $destino,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $itens
     * @param array<int,bool>                $vistos
     * @param array<int,bool>                $mantidos
     * @return true|WP_Error
     */
    private static function gravar_nivel( int $menu_id, array $itens, int $pai, int &$ordem, array &$vistos, array &$mantidos ) {
        foreach ( $itens as $item ) {
            $db_id = self::id_pertence( (int) $item['id'], $menu_id, $vistos );
            $dados = self::dados_item( $item, $pai, $ordem );
            if ( is_wp_error( $dados ) ) {
                return $dados;
            }

            $novo = wp_update_nav_menu_item( $menu_id, $db_id, $dados );
            if ( is_wp_error( $novo ) ) {
                return $novo;
            }
            if ( ! $novo ) {
                return self::falha( 'vh_menu_grava', __( 'Não foi possível gravar um item do menu.', 'vapor-hub-loja' ), 500 );
            }

            $novo               = (int) $novo;
            self::gravar_icone( $novo, (string) ( $item['icone'] ?? '' ) );
            $vistos[ $novo ]    = true;
            $mantidos[ $novo ]  = true;
            $ordem++;

            $filhos = self::gravar_nivel( $menu_id, $item['filhos'], $novo, $ordem, $vistos, $mantidos );
            if ( is_wp_error( $filhos ) ) {
                return $filhos;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>|WP_Error
     */
    private static function dados_item( array $item, int $pai, int $ordem ) {
        $base = [
            'menu-item-title'     => $item['titulo'],
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => $pai,
            'menu-item-position'  => $ordem,
        ];

        if ( 'pagina' === $item['tipo'] ) {
            $id  = (int) $item['destino'];
            $url = get_permalink( $id );
            if ( ! is_string( $url ) || '' === $url ) {
                return self::falha( 'vh_menu_pagina', __( 'A página selecionada não está publicada.', 'vapor-hub-loja' ) );
            }
            $base['menu-item-type']      = 'post_type';
            $base['menu-item-object']    = 'page';
            $base['menu-item-object-id'] = $id;
            $base['menu-item-url']       = $url;
            return $base;
        }

        if ( 'categoria' === $item['tipo'] ) {
            $id  = (int) $item['destino'];
            $url = get_term_link( $id, 'product_cat' );
            if ( is_wp_error( $url ) || ! is_string( $url ) ) {
                return self::falha( 'vh_menu_categoria', __( 'A categoria selecionada não existe.', 'vapor-hub-loja' ) );
            }
            $base['menu-item-type']      = 'taxonomy';
            $base['menu-item-object']    = 'product_cat';
            $base['menu-item-object-id'] = $id;
            $base['menu-item-url']       = $url;
            return $base;
        }

        $url = (string) $item['destino'];
        if ( '/' === $url ) {
            $url = home_url( '/' );
        }

        $base['menu-item-type']      = 'custom';
        $base['menu-item-object']    = 'custom';
        $base['menu-item-object-id'] = 0;
        $base['menu-item-url']       = $url;
        return $base;
    }

    /**
     * @param array<int,bool> $vistos
     */
    private static function id_pertence( int $id, int $menu_id, array &$vistos ): int {
        if ( $id <= 0 || isset( $vistos[ $id ] ) ) {
            return 0;
        }
        $vistos[ $id ] = true;

        $post = get_post( $id );
        if ( ! $post instanceof WP_Post || 'nav_menu_item' !== $post->post_type ) {
            return 0;
        }

        $termos = wp_get_object_terms( $id, 'nav_menu', [ 'fields' => 'ids' ] );
        if ( is_wp_error( $termos ) || ! in_array( $menu_id, array_map( 'intval', (array) $termos ), true ) ) {
            return 0;
        }

        return $id;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function arvore_do_menu( int $menu_id ): array {
        $itens = wp_get_nav_menu_items( $menu_id );
        if ( ! is_array( $itens ) ) {
            return [];
        }

        $por_pai = [];
        foreach ( $itens as $item ) {
            $por_pai[ (int) $item->menu_item_parent ][] = $item;
        }

        return self::montar_nivel( $por_pai, 0 );
    }

    /**
     * @param array<int,array<int,object>> $por_pai
     * @return array<int,array<string,mixed>>
     */
    private static function montar_nivel( array $por_pai, int $pai ): array {
        $nos = [];
        foreach ( $por_pai[ $pai ] ?? [] as $item ) {
            $tipo    = 'link';
            $destino = (string) $item->url;
            if ( 'post_type' === $item->type && 'page' === $item->object ) {
                $tipo    = 'pagina';
                $destino = (string) (int) $item->object_id;
            } elseif ( 'taxonomy' === $item->type && 'product_cat' === $item->object ) {
                $tipo    = 'categoria';
                $destino = (string) (int) $item->object_id;
            }

            $nos[] = [
                'id'      => (int) $item->ID,
                'titulo'  => (string) $item->title,
                'tipo'    => $tipo,
                'destino' => $destino,
                'icone'   => self::icone_do_item( $item ),
                'filhos'  => self::montar_nivel( $por_pai, (int) $item->ID ),
            ];
        }
        return $nos;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function semente_principal(): array {
        $itens = [
            self::item_link( __( 'Início', 'vapor-hub-loja' ), home_url( '/' ) ),
        ];

        $loja_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
        if ( $loja_id > 0 ) {
            $itens[] = self::item_pagina( __( 'Loja', 'vapor-hub-loja' ), $loja_id );
        } else {
            $itens[] = self::item_link( __( 'Loja', 'vapor-hub-loja' ), home_url( '/loja/' ) );
        }

        $itens[] = self::item_por_slug( __( 'Acessórios', 'vapor-hub-loja' ), 'acessorios' );
        $itens[] = self::item_por_slug( __( 'Comunidade', 'vapor-hub-loja' ), 'comunidade' );
        $itens[] = self::item_por_slug( __( 'Contato', 'vapor-hub-loja' ), 'contato' );

        return $itens;
    }

    /**
     * @return array<string,mixed>
     */
    private static function item_por_slug( string $titulo, string $slug ): array {
        $paginas = get_posts( [
            'name'                   => $slug,
            'post_type'              => 'page',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] );
        if ( ! empty( $paginas ) ) {
            return self::item_pagina( $titulo, (int) $paginas[0]->ID );
        }
        return self::item_link( $titulo, home_url( '/' . $slug . '/' ) );
    }

    /**
     * @return array<string,mixed>
     */
    private static function item_pagina( string $titulo, int $id ): array {
        return [
            'id'      => 0,
            'titulo'  => $titulo,
            'tipo'    => 'pagina',
            'destino' => (string) $id,
            'icone'   => '',
            'filhos'  => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function item_link( string $titulo, string $url ): array {
        return [
            'id'      => 0,
            'titulo'  => $titulo,
            'tipo'    => 'link',
            'destino' => $url,
            'icone'   => '',
            'filhos'  => [],
        ];
    }

    /**
     * @return array<int,array{id:int,nome:string,profundidade:int}>
     */
    private static function categorias_achatadas(): array {
        $termos = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => 300,
        ] );
        if ( is_wp_error( $termos ) || ! is_array( $termos ) ) {
            return [];
        }

        $por_pai = [];
        foreach ( $termos as $termo ) {
            $por_pai[ (int) $termo->parent ][] = $termo;
        }
        foreach ( $por_pai as &$grupo ) {
            usort(
                $grupo,
                static fn( WP_Term $a, WP_Term $b ): int => strcasecmp( $a->name, $b->name )
            );
        }
        unset( $grupo );

        $saida = [];
        self::achatar_termos( $por_pai, 0, 0, $saida );
        return $saida;
    }

    /**
     * @param array<int,array<int,WP_Term>>                         $por_pai
     * @param array<int,array{id:int,nome:string,profundidade:int}> $saida
     */
    private static function achatar_termos( array $por_pai, int $pai, int $nivel, array &$saida ): void {
        foreach ( $por_pai[ $pai ] ?? [] as $termo ) {
            $saida[] = [
                'id'           => (int) $termo->term_id,
                'nome'         => $termo->name,
                'profundidade' => $nivel,
            ];
            if ( $nivel < 8 ) {
                self::achatar_termos( $por_pai, (int) $termo->term_id, $nivel + 1, $saida );
            }
        }
    }

    /**
     * Catálogo para o seletor do painel. O desenho mora no tema.
     *
     * @return array<int,array{slug:string,rotulo:string,html:string}>
     */
    public static function icones(): array {
        if ( ! function_exists( 'vh_menu_icones' ) || ! function_exists( 'vh_menu_icone_html' ) ) {
            return [];
        }
        $lista = [];
        foreach ( vh_menu_icones() as $slug => $rotulo ) {
            $lista[] = [
                'slug'   => (string) $slug,
                'rotulo' => (string) $rotulo,
                'html'   => vh_menu_icone_html( (string) $slug ),
            ];
        }
        return $lista;
    }

    private static function icone_permitido( string $slug ): string {
        $slug = sanitize_key( $slug );
        if ( '' === $slug || ! function_exists( 'vh_menu_icones' ) ) {
            return '';
        }
        return isset( vh_menu_icones()[ $slug ] ) ? $slug : '';
    }

    private static function icone_do_item( object $item ): string {
        $classes = isset( $item->classes ) ? (array) $item->classes : [];
        if ( function_exists( 'vh_menu_icone_slug' ) ) {
            return vh_menu_icone_slug( $classes );
        }
        foreach ( $classes as $classe ) {
            $classe = (string) $classe;
            if ( str_starts_with( $classe, 'vh-ico-' ) ) {
                return self::icone_permitido( substr( $classe, 7 ) );
            }
        }
        return '';
    }

    /**
     * O ícone vive numa classe do item nativo, só no primeiro nível.
     */
    private static function gravar_icone( int $id, string $icone ): void {
        $classes = [];
        $atuais  = get_post_meta( $id, '_menu_item_classes', true );
        if ( is_array( $atuais ) ) {
            foreach ( $atuais as $classe ) {
                $classe = (string) $classe;
                if ( '' !== $classe && ! str_starts_with( $classe, 'vh-ico-' ) ) {
                    $classes[] = $classe;
                }
            }
        }
        $icone = self::icone_permitido( $icone );
        if ( '' !== $icone ) {
            $classes[] = 'vh-ico-' . $icone;
        }
        update_post_meta( $id, '_menu_item_classes', $classes );
    }

    private static function menu_id( string $posicao ): int {
        $locs = get_nav_menu_locations();
        $id   = ( is_array( $locs ) && isset( $locs[ $posicao ] ) ) ? (int) $locs[ $posicao ] : 0;
        if ( $id <= 0 ) {
            return 0;
        }
        $menu = wp_get_nav_menu_object( $id );
        return ( $menu && ! is_wp_error( $menu ) ) ? (int) $menu->term_id : 0;
    }

    /**
     * @return int|WP_Error
     */
    private static function garantir_menu( string $posicao ) {
        $atual = self::menu_id( $posicao );
        if ( $atual > 0 ) {
            return $atual;
        }

        $nome  = self::posicoes()[ $posicao ]['menu'];
        $termo = wp_get_nav_menu_object( $nome );
        if ( $termo && ! is_wp_error( $termo ) && ! self::menu_em_outra_posicao( (int) $termo->term_id, $posicao ) ) {
            $id = (int) $termo->term_id;
        } else {
            if ( $termo && ! is_wp_error( $termo ) ) {
                $nome .= ' 2';
            }
            $id = wp_create_nav_menu( $nome );
            if ( is_wp_error( $id ) ) {
                return $id;
            }
            $id = (int) $id;
        }

        $locs = get_nav_menu_locations();
        if ( ! is_array( $locs ) ) {
            $locs = [];
        }
        $locs[ $posicao ] = $id;
        set_theme_mod( 'nav_menu_locations', $locs );
        return $id;
    }

    private static function menu_em_outra_posicao( int $menu_id, string $posicao ): bool {
        $locs = get_nav_menu_locations();
        if ( ! is_array( $locs ) ) {
            return false;
        }
        foreach ( self::posicoes() as $chave => $def ) {
            unset( $def );
            if ( $chave !== $posicao && isset( $locs[ $chave ] ) && (int) $locs[ $chave ] === $menu_id ) {
                return true;
            }
        }
        return false;
    }

    private static function esvaziar( string $posicao ): void {
        $menu_id = self::menu_id( $posicao );
        $locs    = get_nav_menu_locations();
        if ( ! is_array( $locs ) ) {
            $locs = [];
        }
        unset( $locs[ $posicao ] );
        set_theme_mod( 'nav_menu_locations', $locs );
        if ( $menu_id > 0 ) {
            wp_delete_nav_menu( $menu_id );
        }
    }

    private static function falha( string $codigo, string $mensagem, int $status = 400 ): WP_Error {
        return new WP_Error( $codigo, $mensagem, [ 'status' => $status ] );
    }
}

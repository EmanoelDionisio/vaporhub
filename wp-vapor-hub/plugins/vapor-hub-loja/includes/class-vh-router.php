<?php
/**
 * Roteador interno do app Minha Loja.
 *
 * Centraliza seções, resolução de ação/id e URLs públicas amigáveis (/minha-loja/*).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Router {

    public const SLUG = 'vh-loja';

    /**
     * Ações válidas por seção. A primeira é o padrão quando nenhuma é informada.
     *
     * @var array<string,string[]>
     */
    private const ACOES = [
        'pedidos'    => [ 'listar', 'ver' ],
        'produtos'   => [ 'listar', 'novo', 'editar' ],
        'categorias' => [ 'listar', 'novo', 'editar' ],
        'clientes'   => [ 'listar', 'ver' ],
        'cupons'     => [ 'listar', 'novo', 'editar' ],
        'revenda'    => [ 'listar', 'config', 'ver' ],
        'comunidade' => [ 'galeria', 'envios', 'ver' ],
        'tiny'       => [ 'conexao', 'mapeamento', 'logs' ],
    ];

    /**
     * Ação inferida quando a URL traz um ID numérico.
     *
     * @var array<string,string>
     */
    private const ACAO_COM_ID = [
        'pedidos'    => 'ver',
        'clientes'   => 'ver',
        'produtos'   => 'editar',
        'categorias' => 'editar',
        'cupons'     => 'editar',
        'revenda'    => 'ver',
        'comunidade' => 'ver',
    ];

    /**
     * Definição das seções da barra lateral.
     *
     * @return array<string,array{label:string,icone:string,grupo:string}>
     */
    public static function secoes(): array {
        return [
            'dashboard'  => [ 'label' => __( 'Visão Geral', 'vapor-hub-loja' ), 'icone' => 'dashicons-dashboard',         'grupo' => 'gestao' ],
            'pedidos'    => [ 'label' => __( 'Pedidos', 'vapor-hub-loja' ),     'icone' => 'dashicons-clipboard',         'grupo' => 'gestao' ],
            'produtos'   => [ 'label' => __( 'Produtos', 'vapor-hub-loja' ),    'icone' => 'dashicons-products',          'grupo' => 'gestao' ],
            'categorias' => [ 'label' => __( 'Categorias', 'vapor-hub-loja' ),  'icone' => 'dashicons-category',          'grupo' => 'gestao' ],
            'clientes'   => [ 'label' => __( 'Clientes', 'vapor-hub-loja' ),    'icone' => 'dashicons-groups',            'grupo' => 'gestao' ],
            'cupons'     => [ 'label' => __( 'Cupons', 'vapor-hub-loja' ),      'icone' => 'dashicons-tickets-alt',       'grupo' => 'gestao' ],
            'relatorios' => [ 'label' => __( 'Relatórios', 'vapor-hub-loja' ),  'icone' => 'dashicons-chart-bar',         'grupo' => 'gestao' ],
            'aparencia'  => [ 'label' => __( 'Aparência', 'vapor-hub-loja' ),   'icone' => 'dashicons-admin-appearance',  'grupo' => 'vitrine' ],
            'menus'      => [ 'label' => __( 'Menus', 'vapor-hub-loja' ),       'icone' => 'dashicons-menu',              'grupo' => 'vitrine' ],
            'comunidade' => [ 'label' => __( 'Comunidade', 'vapor-hub-loja' ),  'icone' => 'dashicons-format-gallery',    'grupo' => 'conteudo' ],
            'revenda'    => [ 'label' => __( 'Revenda', 'vapor-hub-loja' ),     'icone' => 'dashicons-megaphone',         'grupo' => 'conteudo' ],
            'seo'        => [ 'label' => __( 'SEO e Métricas', 'vapor-hub-loja' ), 'icone' => 'dashicons-chart-area',     'grupo' => 'sistema' ],
            'tiny'       => [ 'label' => __( 'Tiny ERP', 'vapor-hub-loja' ),      'icone' => 'dashicons-randomize',       'grupo' => 'sistema' ],
            'seguranca'  => [ 'label' => __( 'Segurança', 'vapor-hub-loja' ),   'icone' => 'dashicons-shield',            'grupo' => 'sistema' ],
            'ajuda'      => [ 'label' => __( 'Ajuda', 'vapor-hub-loja' ),       'icone' => 'dashicons-editor-help',       'grupo' => 'sistema' ],
        ];
    }

    /**
     * Rótulos dos grupos da barra lateral.
     *
     * @return array<string,string>
     */
    public static function grupos(): array {
        return [
            'gestao'   => __( 'Gestão da Loja', 'vapor-hub-loja' ),
            'vitrine'  => __( 'Vitrine', 'vapor-hub-loja' ),
            'conteudo' => __( 'Conteúdo', 'vapor-hub-loja' ),
            'sistema'  => __( 'Sistema', 'vapor-hub-loja' ),
        ];
    }

    /**
     * Seção atual validada (cai para "dashboard" quando inválida).
     */
    public static function secao_atual(): string {
        $qv = get_query_var( VH_Portal::QV_SECAO );
        if ( $qv ) {
            $secao = sanitize_key( (string) $qv );
            return array_key_exists( $secao, self::secoes() ) ? $secao : 'dashboard';
        }

        $secao = isset( $_GET['secao'] ) ? sanitize_key( wp_unslash( $_GET['secao'] ) ) : 'dashboard';
        return array_key_exists( $secao, self::secoes() ) ? $secao : 'dashboard';
    }

    /**
     * Ação atual validada para a seção informada.
     */
    public static function acao_atual( ?string $secao = null ): string {
        $secao = $secao ?? self::secao_atual();
        $acoes = self::ACOES[ $secao ] ?? [ 'listar' ];

        $qv_acao = get_query_var( VH_Portal::QV_ACAO );
        if ( $qv_acao ) {
            $acao = sanitize_key( (string) $qv_acao );
            if ( in_array( $acao, $acoes, true ) ) {
                if ( 'tiny' === $secao && 'logs' === $acao && ! self::pode_ver_logs_tiny() ) {
                    return $acoes[0];
                }
                return $acao;
            }
        }

        $qv_id = absint( get_query_var( VH_Portal::QV_ID ) );
        if ( $qv_id > 0 && isset( self::ACAO_COM_ID[ $secao ] ) ) {
            return self::ACAO_COM_ID[ $secao ];
        }

        if ( isset( $_GET['acao'] ) ) {
            $acao = sanitize_key( wp_unslash( $_GET['acao'] ) );
            if ( in_array( $acao, $acoes, true ) ) {
                if ( 'tiny' === $secao && 'logs' === $acao && ! self::pode_ver_logs_tiny() ) {
                    return $acoes[0];
                }
                return $acao;
            }
        }

        return $acoes[0];
    }

    /**
     * ID numérico atual (0 quando ausente).
     */
    public static function id_atual(): int {
        $qv = absint( get_query_var( VH_Portal::QV_ID ) );
        if ( $qv > 0 ) {
            return $qv;
        }
        return isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    }

    /**
     * Constrói uma URL pública amigável do app (/minha-loja/...).
     *
     * @param string               $secao Slug da seção (ou "entrar"/"sair").
     * @param array<string,scalar> $args  Parâmetros adicionais (acao, id, redirect_to).
     */
    public static function url( string $secao = 'dashboard', array $args = [] ): string {
        if ( 'entrar' === $secao ) {
            $url = home_url( '/minha-loja/entrar/' );
            if ( ! empty( $args['redirect_to'] ) ) {
                $url = add_query_arg( 'redirect_to', rawurlencode( (string) $args['redirect_to'] ), $url );
            }
            return $url;
        }

        if ( 'sair' === $secao ) {
            return wp_nonce_url( home_url( '/minha-loja/sair/' ), 'vh_loja_logout' );
        }

        $acao = isset( $args['acao'] ) ? sanitize_key( (string) $args['acao'] ) : '';
        $id   = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        $query_args = $args;
        unset( $query_args['acao'], $query_args['id'], $query_args['redirect_to'] );

        if ( 'novo' === $acao ) {
            $url = trailingslashit( home_url( '/minha-loja/' . $secao . '/novo' ) );
            return ! empty( $query_args ) ? add_query_arg( $query_args, $url ) : $url;
        }

        if ( $acao && 'listar' !== $acao && $id <= 0 && in_array( $acao, [ 'config', 'envios', 'conexao', 'mapeamento', 'logs' ], true ) ) {
            $url = trailingslashit( home_url( '/minha-loja/' . $secao . '/' . $acao ) );
            return ! empty( $query_args ) ? add_query_arg( $query_args, $url ) : $url;
        }

        if ( $id > 0 ) {
            $url = trailingslashit( home_url( '/minha-loja/' . $secao . '/' . $id ) );
            return ! empty( $query_args ) ? add_query_arg( $query_args, $url ) : $url;
        }

        if ( 'dashboard' === $secao ) {
            $url = trailingslashit( home_url( '/minha-loja' ) );
            return ! empty( $query_args ) ? add_query_arg( $query_args, $url ) : $url;
        }

        $url = trailingslashit( home_url( '/minha-loja/' . $secao ) );
        return ! empty( $query_args ) ? add_query_arg( $query_args, $url ) : $url;
    }

    /**
     * URL legada wp-admin (somente administradores).
     */
    public static function url_admin( string $secao = 'dashboard', array $args = [] ): string {
        $base = [ 'page' => self::SLUG, 'secao' => $secao ];
        return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
    }

    /**
     * Resolve o arquivo de view a ser renderizado para a seção/ação atuais.
     */
    public static function caminho_view( string $secao, string $acao ): string {
        $dir = VH_LOJA_DIR . 'admin/views/';

        $mapa = [
            'dashboard'  => 'dashboard.php',
            'relatorios' => 'relatorios/index.php',
            'aparencia'  => 'aparencia.php',
            'menus'      => 'menus.php',
            'comunidade' => [ 'galeria' => 'comunidade/galeria.php', 'envios' => 'comunidade/envios/lista.php', 'ver' => 'comunidade/envios/detalhe.php' ],
            'revenda'    => [ 'listar' => 'revenda/lista.php', 'config' => 'revenda/config.php', 'ver' => 'revenda/detalhe.php' ],
            'seo'        => 'seo/index.php',
            'tiny'       => [ 'conexao' => 'integracoes/tiny.php', 'mapeamento' => 'integracoes/tiny-mapeamento.php', 'logs' => 'integracoes/tiny-logs.php' ],
            'seguranca'  => 'seguranca.php',
            'ajuda'      => 'ajuda.php',
            'pedidos'    => [ 'listar' => 'pedidos/lista.php', 'ver' => 'pedidos/detalhe.php' ],
            'produtos'   => [ 'listar' => 'produtos/lista.php', 'novo' => 'produtos/form.php', 'editar' => 'produtos/form.php' ],
            'categorias' => [ 'listar' => 'categorias/lista.php', 'novo' => 'categorias/form.php', 'editar' => 'categorias/form.php' ],
            'clientes'   => [ 'listar' => 'clientes/lista.php', 'ver' => 'clientes/detalhe.php' ],
            'cupons'     => [ 'listar' => 'cupons/lista.php', 'novo' => 'cupons/form.php', 'editar' => 'cupons/form.php' ],
        ];

        $alvo = $mapa[ $secao ] ?? 'dashboard.php';
        if ( is_array( $alvo ) ) {
            $alvo = $alvo[ $acao ] ?? reset( $alvo );
        }

        $caminho = $dir . $alvo;
        return file_exists( $caminho ) ? $caminho : $dir . 'dashboard.php';
    }

    /**
     * Título legível da seção/ação atuais (para o cabeçalho do app).
     */
    public static function titulo( string $secao, string $acao ): string {
        $secoes = self::secoes();
        $base   = $secoes[ $secao ]['label'] ?? __( 'Minha Loja', 'vapor-hub-loja' );

        $sufixos = [
            'novo'   => __( 'Novo', 'vapor-hub-loja' ),
            'editar' => __( 'Editar', 'vapor-hub-loja' ),
            'ver'    => __( 'Detalhes', 'vapor-hub-loja' ),
            'config'      => __( 'Configuração', 'vapor-hub-loja' ),
            'conexao'     => __( 'Conexão', 'vapor-hub-loja' ),
            'mapeamento'  => __( 'Mapeamento', 'vapor-hub-loja' ),
            'logs'        => __( 'Logs', 'vapor-hub-loja' ),
        ];

        return isset( $sufixos[ $acao ] ) ? $base . ' — ' . $sufixos[ $acao ] : $base;
    }

    /**
     * Renderiza o shell do app com a view da seção atual.
     */
    public static function render_app(): void {
        if ( ! current_user_can( VH_Roles::CAP ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar o painel Minha Loja.', 'vapor-hub-loja' ) );
        }

        $secao = self::secao_atual();
        $acao  = self::acao_atual( $secao );
        $id    = self::id_atual();
        $view  = self::caminho_view( $secao, $acao );

        require VH_LOJA_DIR . 'admin/layouts/app-shell.php';
    }

    /**
     * Logs Tiny ERP — somente administradores WordPress.
     */
    public static function pode_ver_logs_tiny(): bool {
        return current_user_can( 'manage_options' );
    }
}

<?php
/**
 * App Minha Loja — shell, menu, assets e renderização.
 *
 * Registra um único menu de topo no wp-admin e renderiza um shell com barra
 * lateral própria. A navegação acontece via VH_Router (query var "secao"),
 * nunca por links para telas nativas do WooCommerce.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_App {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'registrar_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
        add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 80 );
        add_action( 'admin_init', [ __CLASS__, 'redirecionar_slugs_legados' ], 5 );

        /*
         * Esconde por completo a barra superior do WordPress para o Gestor da Loja,
         * reforçando a sensação de app dedicado e evitando expor a infraestrutura
         * WordPress. Administradores continuam com a barra.
         *
         * No front, o filtro show_admin_bar resolve. No wp-admin o WordPress força
         * is_admin_bar_showing() a retornar true, então removemos via CSS no head e
         * cancelamos a renderização da barra.
         */
        add_filter( 'show_admin_bar', [ __CLASS__, 'controlar_admin_bar' ] );
        if ( is_admin() ) {
            add_action( 'admin_head', [ __CLASS__, 'estilo_ocultar_admin_bar' ] );
            add_action( 'admin_bar_init', [ __CLASS__, 'cancelar_render_admin_bar' ] );
        }

        /*
         * Menu wp-admin reservado a administradores/desenvolvedores.
         * Gestores usam exclusivamente /minha-loja/*.
         */
    }

    /**
     * Registra o menu de topo "Minha Loja" (somente administradores).
     */
    public static function registrar_menu(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        add_menu_page(
            __( 'Minha Loja', 'vapor-hub-loja' ),
            __( 'Minha Loja', 'vapor-hub-loja' ),
            'manage_options',
            VH_Router::SLUG,
            [ __CLASS__, 'render' ],
            'dashicons-store',
            3
        );
    }

    /**
     * Enfileira assets apenas na tela do app.
     */
    public static function enqueue( string $hook ): void {
        if ( 'toplevel_page_' . VH_Router::SLUG !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        VH_Front::editor_produto();

        wp_enqueue_style(
            'vh-admin-css',
            VH_LOJA_ASSETS . 'css/admin.css',
            [],
            VH_LOJA_VERSION
        );
        wp_add_inline_style( 'vh-admin-css', VH_Settings::css_tokens_marca() );
        wp_enqueue_style(
            'vh-ds-loading',
            VH_LOJA_ASSETS . 'css/design-system-loading.css',
            [ 'vh-admin-css' ],
            VH_LOJA_VERSION
        );
        wp_enqueue_style(
            'vh-app-shell',
            VH_LOJA_ASSETS . 'css/app-shell.css',
            [ 'vh-admin-css', 'vh-ds-loading' ],
            VH_LOJA_VERSION
        );

        wp_enqueue_media();

        if ( current_user_can( 'upload_files' ) ) {
            VH_Media_Profiles::enqueue_assets();
        }

        $deps_admin = [ 'jquery', 'media-editor' ];
        $deps_app   = [ 'jquery', 'wp-api-fetch', 'media-editor' ];
        if ( current_user_can( 'upload_files' ) ) {
            $deps_admin[] = 'vh-media-crop';
            $deps_app[]   = 'vh-media-crop';
        }

        wp_enqueue_script(
            'vh-admin-js',
            VH_LOJA_ASSETS . 'js/admin.js',
            $deps_admin,
            VH_LOJA_VERSION,
            true
        );
        wp_enqueue_script(
            'vh-custom-select-js',
            VH_LOJA_ASSETS . 'js/custom-select.js',
            [],
            VH_LOJA_VERSION,
            true
        );
        wp_localize_script( 'vh-admin-js', 'paLoja', [
            'titulo_midia'      => __( 'Selecionar imagem', 'vapor-hub-loja' ),
            'botao_midia'       => __( 'Usar esta imagem', 'vapor-hub-loja' ),
            'confirmar_remover' => __( 'Deseja remover esta entrada?', 'vapor-hub-loja' ),
            'confirmar_remover_titulo' => __( 'Remover entrada?', 'vapor-hub-loja' ),
            'confirmar_acao_btn'  => __( 'Remover', 'vapor-hub-loja' ),
            'cancelar'            => __( 'Cancelar', 'vapor-hub-loja' ),
            'max_comunidade'    => 6,
            'max_beneficios'    => 8,
            'beneficio_novo'    => __( 'Novo benefício', 'vapor-hub-loja' ),
            'erro_midia'        => __( 'Biblioteca de mídia indisponível. Recarregue a página e tente novamente.', 'vapor-hub-loja' ),
        ] );

        wp_enqueue_script(
            'vh-app-js',
            VH_LOJA_ASSETS . 'js/app.js',
            $deps_app,
            VH_LOJA_VERSION,
            true
        );
        wp_localize_script( 'vh-app-js', 'paLojaApp', [
            'restUrl'   => esc_url_raw( rest_url( VH_REST_Controller::NS . '/' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'urlBase'   => VH_Router::url( '' ),
            'i18n'      => [
                'erroGenerico'        => __( 'Não foi possível concluir a operação. Tente novamente.', 'vapor-hub-loja' ),
                'erroSalvar'          => __( 'Não foi possível salvar as alterações. Tente novamente em instantes.', 'vapor-hub-loja' ),
                'sucessoSalvar'       => __( 'Alterações salvas com sucesso.', 'vapor-hub-loja' ),
                'sucesso'             => __( 'Operação concluída com sucesso.', 'vapor-hub-loja' ),
                'fecharAviso'         => __( 'Fechar aviso', 'vapor-hub-loja' ),
                'cancelar'            => __( 'Cancelar', 'vapor-hub-loja' ),
                'confirmarSalvarTitulo' => __( 'Salvar alterações?', 'vapor-hub-loja' ),
                'confirmarSalvar'     => __( 'Deseja salvar as alterações feitas nesta página?', 'vapor-hub-loja' ),
                'confirmarSalvarBtn'  => __( 'Salvar', 'vapor-hub-loja' ),
                'confirmarTinyTitulo' => __( 'Enviar ao Tiny ERP?', 'vapor-hub-loja' ),
                'confirmarTiny'       => __( 'Salvar o produto e enviar agora para o Tiny ERP?', 'vapor-hub-loja' ),
                'confirmarTinyBtn'    => __( 'Salvar e enviar', 'vapor-hub-loja' ),
                'confirmarSemTinyTitulo' => __( 'Salvar sem enviar ao Tiny?', 'vapor-hub-loja' ),
                'confirmarSemTiny'    => __( 'Este produto ficará diferente do Tiny ERP. Para atualizar o ERP agora, use “Salvar e enviar ao Tiny”.', 'vapor-hub-loja' ),
                'confirmarSemTinyBtn' => __( 'Salvar só na loja', 'vapor-hub-loja' ),
                'sucessoSalvarTiny'   => __( 'Produto salvo e enviado ao Tiny com sucesso.', 'vapor-hub-loja' ),
                'confirmarAcaoTitulo' => __( 'Confirmar ação', 'vapor-hub-loja' ),
                'confirmarAcao'       => __( 'Tem certeza? Esta ação não pode ser desfeita.', 'vapor-hub-loja' ),
                'confirmarAcaoBtn'    => __( 'Confirmar', 'vapor-hub-loja' ),
                'selecionarImagem'    => __( 'Selecionar imagem', 'vapor-hub-loja' ),
                'usarImagem'          => __( 'Usar imagem', 'vapor-hub-loja' ),
                'selecionarImagens'   => __( 'Selecionar imagens', 'vapor-hub-loja' ),
                'usarImagens'         => __( 'Usar imagens', 'vapor-hub-loja' ),
                'erroMidia'           => __( 'Biblioteca de mídia indisponível. Recarregue a página e tente novamente.', 'vapor-hub-loja' ),
                'buscarSelect'        => __( 'Buscar categoria…', 'vapor-hub-loja' ),
                'nenhumResultadoSelect' => __( 'Nenhuma categoria encontrada.', 'vapor-hub-loja' ),
            ],
        ] );

        if ( isset( $_GET['secao'] ) && 'seo' === sanitize_key( wp_unslash( $_GET['secao'] ) ) ) {
            wp_enqueue_script(
                'vh-seo-js',
                VH_LOJA_ASSETS . 'js/seo.js',
                [ 'vh-app-js' ],
                VH_LOJA_VERSION,
                true
            );
            wp_localize_script( 'vh-seo-js', 'paSeoApp', [
                'redirectUri' => esc_url_raw( VH_SEO_Google::redirect_uri() ),
                'i18n'        => [
                    'conectar'           => __( 'Conectar com Google', 'vapor-hub-loja' ),
                    'desconectar'        => __( 'Desconectar', 'vapor-hub-loja' ),
                    'carregando'         => __( 'Carregando métricas…', 'vapor-hub-loja' ),
                    'analisarPagespeed'  => __( 'Analisar Core Web Vitals', 'vapor-hub-loja' ),
                    'conectadoOk'        => __( 'Conta Google conectada com sucesso.', 'vapor-hub-loja' ),
                    'desconectarConfirm' => __( 'Desconectar a conta Google desta loja?', 'vapor-hub-loja' ),
                ],
            ] );
        }

        if ( isset( $_GET['secao'] ) && 'categorias' === sanitize_key( wp_unslash( $_GET['secao'] ) ) ) {
            wp_enqueue_script(
                'vh-categorias-js',
                VH_LOJA_ASSETS . 'js/categorias.js',
                [],
                VH_LOJA_VERSION,
                true
            );
            wp_localize_script(
                'vh-categorias-js',
                'paCategorias',
                [
                    'restUrl' => esc_url_raw( rest_url( VH_REST_Controller::NS . '/' ) ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'i18n'    => [
                        'erroGenerico'           => __( 'Não foi possível mover a categoria. Tente novamente.', 'vapor-hub-loja' ),
                        'salvando'               => __( 'Salvando ordem…', 'vapor-hub-loja' ),
                        'moveInvalidoDescendente' => __( 'Não é possível mover a categoria para dentro de uma subcategoria dela.', 'vapor-hub-loja' ),
                        'moveInvalidoMesma'      => __( 'Não é possível mover a categoria para ela mesma.', 'vapor-hub-loja' ),
                    ],
                ]
            );
        }

        VH_Personalizacao_Service::enqueue_editor_assets();

        if ( isset( $_GET['secao'] ) && 'tiny' === sanitize_key( wp_unslash( $_GET['secao'] ) ) ) {
            wp_enqueue_script(
                'vh-tiny-js',
                VH_LOJA_ASSETS . 'js/tiny.js',
                [ 'vh-app-js' ],
                VH_LOJA_VERSION,
                true
            );
            wp_localize_script( 'vh-tiny-js', 'paTinyApp', [
                'i18n' => [
                    'credenciaisOk'      => __( 'Credenciais salvas.', 'vapor-hub-loja' ),
                    'tokenOk'            => __( 'Token salvo e conexão estabelecida.', 'vapor-hub-loja' ),
                    'testeOk'            => __( 'Teste OK — salve o token para conectar.', 'vapor-hub-loja' ),
                    'conexaoOk'          => __( 'Conexão testada com sucesso.', 'vapor-hub-loja' ),
                    'configOk'           => __( 'Configuração salva.', 'vapor-hub-loja' ),
                    'sincOk'             => __( 'Sincronização enfileirada.', 'vapor-hub-loja' ),
                    'sincProcessados'    => __( '%d tarefas processadas nesta execução. A fila continua no cron.', 'vapor-hub-loja' ),
                    'desconectado'       => __( 'Tiny ERP desconectado.', 'vapor-hub-loja' ),
                    'desconectarTitulo'  => __( 'Desconectar Tiny ERP', 'vapor-hub-loja' ),
                    'desconectarConfirm' => __( 'Desconectar o Tiny ERP desta loja? A sincronização será interrompida até reconectar.', 'vapor-hub-loja' ),
                    'desconectarBtn'     => __( 'Desconectar', 'vapor-hub-loja' ),
                    'cancelar'           => __( 'Cancelar', 'vapor-hub-loja' ),
                    'tokenObrigatorio'   => __( 'Informe o token para salvar.', 'vapor-hub-loja' ),
                    'mapCarregando'      => __( 'Carregando mapeamento…', 'vapor-hub-loja' ),
                    'salvando'           => __( 'Salvando…', 'vapor-hub-loja' ),
                    'salvarCats'         => __( 'Salvar categorias', 'vapor-hub-loja' ),
                    'salvarAttrs'        => __( 'Salvar atributos', 'vapor-hub-loja' ),
                    'mapCatsOk'          => __( 'Categorias salvas.', 'vapor-hub-loja' ),
                    'mapCatsResumo'      => __( '%1$d vinculadas, %2$d criadas, %3$d ignoradas.', 'vapor-hub-loja' ),
                    'mapCatsSemAlteracao'=> __( 'Nenhuma alteração para salvar.', 'vapor-hub-loja' ),
                    'confirmarCriarCatsTitulo' => __( 'Criar categorias no Tiny?', 'vapor-hub-loja' ),
                    'confirmarCriarCats' => __( 'Serão criadas %d categoria(s) novas no Tiny ERP. A hierarquia segue a da loja: uma subcategoria aqui vira subcategoria de quem já estiver mapeada no Tiny. Use isto quando a categoria ainda não existe no ERP. Se ela já existir lá com o mesmo nome, prefira Vincular. Continuar?', 'vapor-hub-loja' ),
                    'confirmarCriarCatsBtn' => __( 'Criar no Tiny', 'vapor-hub-loja' ),
                    'sincPendentesFila'  => __( 'Ainda restam %d tarefa(s) na fila.', 'vapor-hub-loja' ),
                    'mapAttrOk'          => __( 'Atributos salvos.', 'vapor-hub-loja' ),
                    'statusVinculado'    => __( 'Vinculado', 'vapor-hub-loja' ),
                    'statusIgnorado'     => __( 'Ignorado', 'vapor-hub-loja' ),
                    'statusPendente'     => __( 'Pendente', 'vapor-hub-loja' ),
                    'catsPendentes'      => __( '%d categorias pendentes', 'vapor-hub-loja' ),
                    'catsOk'             => __( 'Todas as categorias vinculadas', 'vapor-hub-loja' ),
                    'selecionarTiny'     => __( '— Selecionar —', 'vapor-hub-loja' ),
                    'criarNoTiny'        => __( 'Criar no Tiny', 'vapor-hub-loja' ),
                    'ignorar'            => __( 'Ignorar', 'vapor-hub-loja' ),
                    'vincular'           => __( 'Vincular', 'vapor-hub-loja' ),
                    'tinyJaVinculado'    => __( '(já vinculada)', 'vapor-hub-loja' ),
                    'tinyVinculadaA'     => __( 'já ligada a “%s” na loja (indisponível para outra categoria)', 'vapor-hub-loja' ),
                    'mapCatsErrosTitulo' => __( 'Não foi possível salvar o mapeamento', 'vapor-hub-loja' ),
                    'mapCatsErrosSubtitulo' => __( 'Cada item abaixo explica o motivo e o que fazer. As linhas em destaque na tabela correspondem aos erros.', 'vapor-hub-loja' ),
                    'mapCatSemTiny'      => __( '“%s”: você marcou Vincular, mas não escolheu a categoria do Tiny. Selecione a categoria do Tiny que é exatamente a mesma desta da loja (mesmo nome e mesmo nível — não escolha a categoria “mãe” se esta for subcategoria).', 'vapor-hub-loja' ),
                    'mapCatConflito'     => __( '“%1$s”: Vincular não cria subcategoria no Tiny — só indica que esta categoria da loja é a mesma que já existe no ERP (1 categoria da loja = 1 categoria do Tiny). A categoria “%3$s” do Tiny já está ligada a “%2$s” na loja. Se você quer “%1$s” dentro de “%2$s” no Tiny: (1) em Minha Loja → Categorias, faça “%1$s” ser subcategoria (filha) de “%2$s”; (2) volte aqui e escolha a ação “Criar no Tiny” para “%1$s” — não use Vincular.', 'vapor-hub-loja' ),
                    'mapCatConflitoHierarquiaOk' => __( '“%1$s”: Vincular só serve quando esta categoria é a mesma no Tiny — não para subcategorias. A hierarquia na loja já está correta (“%1$s” é filha de “%2$s”, que já está no Tiny). Troque a ação para “Criar no Tiny” e salve. Não use Vincular nem selecione “%3$s” do Tiny.', 'vapor-hub-loja' ),
                    'mapCatSugestaoCriar' => __( 'Subcategoria de “%s” (já no Tiny) — use “Criar no Tiny”.', 'vapor-hub-loja' ),
                    'termos'             => __( 'termos', 'vapor-hub-loja' ),
                    'logsErros'          => __( 'Erros', 'vapor-hub-loja' ),
                    'logsAvisos'         => __( 'Avisos', 'vapor-hub-loja' ),
                    'logsFilaPend'       => __( 'Fila pendente', 'vapor-hub-loja' ),
                    'logsFilaOk'         => __( 'Jobs concluídos', 'vapor-hub-loja' ),
                    'logsDetalhes'       => __( 'Detalhes', 'vapor-hub-loja' ),
                    'logsColData'        => __( 'Data', 'vapor-hub-loja' ),
                    'logsColNivel'       => __( 'Nível', 'vapor-hub-loja' ),
                    'logsColOrigem'      => __( 'Origem', 'vapor-hub-loja' ),
                    'logsColMsg'         => __( 'Mensagem', 'vapor-hub-loja' ),
                    'logsColTipo'        => __( 'Tipo', 'vapor-hub-loja' ),
                    'logsColRef'         => __( 'Referência', 'vapor-hub-loja' ),
                    'logsColStatus'      => __( 'Status', 'vapor-hub-loja' ),
                    'logsTentativas'     => __( 'Tentativas', 'vapor-hub-loja' ),
                    'logsPagina'         => __( 'Página %1 de %2', 'vapor-hub-loja' ),
                    'logsRegistros'      => __( 'registros', 'vapor-hub-loja' ),
                ],
            ] );
        }
    }

    /**
     * Classes no body para o modo app (isolado para o gestor).
     *
     * @param string $classes
     * @return string
     */
    public static function body_class( $classes ): string {
        $tela = get_current_screen();
        if ( $tela && 'toplevel_page_' . VH_Router::SLUG === $tela->id ) {
            $classes .= ' vh-loja-tela';
            if ( VH_Roles::usuario_eh_gestor() ) {
                $classes .= ' vh-loja-isolado';
            }
        }
        return $classes;
    }

    /**
     * Oculta a barra superior do WordPress quando o usuário é exclusivamente
     * Gestor da Loja. Administradores mantêm a barra normalmente.
     *
     * @param bool $mostrar Valor atual do filtro.
     * @return bool
     */
    public static function controlar_admin_bar( $mostrar ): bool {
        if ( VH_Roles::usuario_eh_gestor() ) {
            return false;
        }
        return (bool) $mostrar;
    }

    /**
     * Cancela a renderização da barra no wp-admin para o Gestor da Loja.
     */
    public static function cancelar_render_admin_bar(): void {
        if ( VH_Roles::usuario_eh_gestor() ) {
            remove_action( 'in_admin_header', 'wp_admin_bar_render', 0 );
        }
    }

    /**
     * Esconde a barra do WordPress (e remove o offset de 32px) no wp-admin para
     * o Gestor da Loja — o filtro show_admin_bar não tem efeito em telas admin.
     */
    public static function estilo_ocultar_admin_bar(): void {
        if ( ! VH_Roles::usuario_eh_gestor() ) {
            return;
        }
        echo '<style id="vh-ocultar-adminbar">#wpadminbar{display:none!important;}html.wp-toolbar{padding-top:0!important;}</style>' . "\n";
    }

    /**
     * Adiciona um atalho "Minha Loja" na barra superior do admin.
     */
    public static function admin_bar( \WP_Admin_Bar $barra ): void {
        if ( ! current_user_can( VH_Roles::CAP ) ) {
            return;
        }
        $barra->add_node( [
            'id'    => 'vh-minha-loja',
            'title' => __( 'Minha Loja', 'vapor-hub-loja' ),
            'href'  => VH_Router::url( 'dashboard' ),
            'meta'  => [ 'title' => __( 'Abrir o painel Minha Loja', 'vapor-hub-loja' ) ],
        ] );
    }

    /**
     * Redireciona slugs de submenus de versões anteriores para a nova rota.
     */
    public static function redirecionar_slugs_legados(): void {
        $pagina = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $mapa   = [
            'vh-loja-dashboard'  => 'dashboard',
            'vh-loja-aparencia'  => 'aparencia',
            'vh-loja-comunidade' => 'comunidade',
            'vh-loja-revenda'    => 'revenda',
            'vh-loja-ajuda'      => 'ajuda',
        ];
        if ( isset( $mapa[ $pagina ] ) ) {
            wp_safe_redirect( VH_Router::url( $mapa[ $pagina ] ), 301 );
            exit;
        }
    }

    /**
     * Renderiza o shell do app com a view da seção atual.
     */
    public static function render(): void {
        VH_Router::render_app();
    }
}

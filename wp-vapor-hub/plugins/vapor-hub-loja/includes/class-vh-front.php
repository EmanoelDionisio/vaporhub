<?php
/**
 * Shell front-end do app Minha Loja (/minha-loja/*).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Front {

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_footer', [ __CLASS__, 'imprimir_templates_midia' ], 5 );
        add_filter( 'show_admin_bar', [ __CLASS__, 'ocultar_admin_bar' ], 99 );
    }

    /**
     * Enfileira assets apenas nas rotas do portal autenticado.
     */
    public static function enqueue(): void {
        if ( ! self::usuario_autorizado_portal() ) {
            return;
        }

        self::registrar_assets();
    }

    /**
     * Porteiro do shell front-end autenticado (exclui login público).
     */
    private static function usuario_autorizado_portal(): bool {
        if ( ! VH_Portal::eh_rota_portal() || 'entrar' === get_query_var( VH_Portal::QV_ROTA ) ) {
            return false;
        }
        return is_user_logged_in() && current_user_can( VH_Roles::CAP );
    }

    /**
     * Biblioteca de mídia: exige capacidade nativa de upload (menor privilégio).
     */
    private static function usuario_pode_usar_midia(): bool {
        return self::usuario_autorizado_portal() && current_user_can( 'upload_files' );
    }

    /**
     * Registra CSS/JS do app (compartilhado com wp-admin para admins).
     */
    public static function registrar_assets(): void {
        wp_enqueue_script( 'jquery' );

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'vh-admin-css',
            VH_LOJA_ASSETS . 'css/admin.css',
            [ 'dashicons' ],
            VH_LOJA_VERSION
        );
        wp_add_inline_style( 'vh-admin-css', VH_Settings::css_tokens_marca() );
        wp_enqueue_style(
            'vh-app-shell',
            VH_LOJA_ASSETS . 'css/app-shell.css',
            [ 'vh-admin-css' ],
            VH_LOJA_VERSION
        );

        /*
         * Dependências só incluem handles efetivamente registrados. Declarar uma
         * dependência inexistente (ex.: wp-color-picker não registrado no front)
         * faz o WordPress descartar o script silenciosamente.
         */
        $deps_admin = [ 'jquery' ];
        $deps_app   = [ 'jquery', 'wp-api-fetch' ];

        if ( self::usuario_pode_usar_midia() ) {
            wp_enqueue_media();
            $deps_admin[] = 'media-editor';
            $deps_app[]   = 'media-editor';
            VH_Media_Profiles::enqueue_assets();
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
            'urlBase'   => trailingslashit( home_url( '/minha-loja' ) ),
            'i18n'      => [
                'erroGenerico'        => __( 'Não foi possível concluir a operação. Tente novamente.', 'vapor-hub-loja' ),
                'salvando'            => __( 'Salvando…', 'vapor-hub-loja' ),
                'sucesso'             => __( 'Operação concluída com sucesso.', 'vapor-hub-loja' ),
                'cancelar'            => __( 'Cancelar', 'vapor-hub-loja' ),
                'confirmarSalvarTitulo' => __( 'Salvar alterações?', 'vapor-hub-loja' ),
                'confirmarSalvar'     => __( 'Deseja salvar as alterações feitas nesta página?', 'vapor-hub-loja' ),
                'confirmarSalvarBtn'  => __( 'Salvar', 'vapor-hub-loja' ),
                'confirmarAcaoTitulo' => __( 'Confirmar ação', 'vapor-hub-loja' ),
                'confirmarAcao'       => __( 'Tem certeza? Esta ação não pode ser desfeita.', 'vapor-hub-loja' ),
                'confirmarAcaoBtn'    => __( 'Confirmar', 'vapor-hub-loja' ),
                'selecionarImagem'    => __( 'Selecionar imagem', 'vapor-hub-loja' ),
                'usarImagem'          => __( 'Usar imagem', 'vapor-hub-loja' ),
                'selecionarImagens'   => __( 'Selecionar imagens', 'vapor-hub-loja' ),
                'usarImagens'         => __( 'Usar imagens', 'vapor-hub-loja' ),
                'erroMidia'           => __( 'Biblioteca de mídia indisponível. Recarregue a página e tente novamente.', 'vapor-hub-loja' ),
            ],
        ] );

        if ( 'seo' === VH_Router::secao_atual() ) {
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

        if ( 'categorias' === VH_Router::secao_atual() ) {
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

        if ( 'tiny' === VH_Router::secao_atual() ) {
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
     * Renderiza documento HTML completo do app.
     */
    public static function render_app(): void {
        self::registrar_assets();
        self::headers_seguranca();

        $secao = VH_Router::secao_atual();
        $acao  = VH_Router::acao_atual( $secao );
        $id    = VH_Router::id_atual();
        $titulo = VH_Router::titulo( $secao, $acao ) . ' — ' . get_bloginfo( 'name' );

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $titulo ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="vh-loja-tela vh-loja-isolado vh-loja-front">
<?php
        VH_Router::render_app();
        wp_footer();
        ?>
</body>
</html>
        <?php
    }

    /**
     * Renderiza tela de login brandada.
     *
     * @param string $erro        Código de erro.
     * @param string $redirect_to Destino pós-login.
     */
    public static function render_login( string $erro, string $redirect_to ): void {
        self::headers_seguranca();

        $mensagens = VH_Auth::mensagens_erro();
        $msg_erro  = $mensagens[ $erro ] ?? '';

        $seg = VH_Settings::obter( 'vh_seguranca', VH_Settings::seguranca_padrao() );
        $turnstile_ativo = ! empty( $seg['turnstile_ativo'] ) && '1' === (string) $seg['turnstile_ativo'];
        $turnstile_site  = $seg['turnstile_site_key'] ?? '';

        $identidade = VH_Settings::obter( 'vh_identidade_visual', VH_Settings::identidade_visual_padrao() );
        $logo       = $identidade['logo_url'] ?? '';

        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style(
            'vh-login-css',
            VH_LOJA_ASSETS . 'css/login.css',
            [],
            VH_LOJA_VERSION
        );
        wp_add_inline_style( 'vh-login-css', VH_Settings::css_tokens_marca() );
        if ( $turnstile_ativo && $turnstile_site ) {
            wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true );
        }

        $titulo = __( 'Entrar — Minha Loja', 'vapor-hub-loja' ) . ' — ' . get_bloginfo( 'name' );

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $titulo ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="vh-loja-login-body">
<?php require VH_LOJA_DIR . 'admin/views/login.php'; ?>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    /**
     * Templates Underscore da biblioteca de mídia (obrigatório fora do wp-admin).
     */
    public static function imprimir_templates_midia(): void {
        if ( ! self::usuario_pode_usar_midia() ) {
            return;
        }
        if ( function_exists( 'wp_print_media_templates' ) ) {
            wp_print_media_templates();
        }
    }

    public static function ocultar_admin_bar( $mostrar ): bool {
        if ( VH_Portal::eh_rota_portal() ) {
            return false;
        }
        return (bool) $mostrar;
    }

    private static function headers_seguranca(): void {
        if ( ! headers_sent() ) {
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
        }
    }
}

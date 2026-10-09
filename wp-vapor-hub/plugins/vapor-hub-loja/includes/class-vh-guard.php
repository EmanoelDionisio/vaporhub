<?php
/**
 * Cerca da área da equipe.
 *
 * O gestor que abre o wp-admin volta para /minha-loja/. O cliente logado que
 * abre o painel, o wp-admin ou o login do WordPress volta para a própria conta.
 * Na segunda tentativa, dentro de 15 minutos, a sessão cai.
 *
 * admin-ajax.php e admin-post.php ficam de fora: a vitrine e o contato usam os dois.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Guard {

    public const JANELA = 900;

    /**
     * Endpoints wp-admin que não são sonda (mídia do gestor, AJAX e formulários públicos).
     *
     * @var string[]
     */
    private const PAGINAS_LIBERADAS = [
        'admin-ajax.php',
        'admin-post.php',
        'async-upload.php',
    ];

    /**
     * Recuperação de senha e senha de post. Não contam como sonda.
     *
     * @var string[]
     */
    private const ACOES_SENHA = [
        'lostpassword',
        'rp',
        'resetpass',
        'postpass',
    ];

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'bloquear_telas' ], 0 );
        add_action( 'login_init', [ __CLASS__, 'bloquear_login' ] );
        add_filter( 'login_url', [ __CLASS__, 'filtrar_login_url' ], 20, 3 );
        add_filter( 'login_redirect', [ __CLASS__, 'destino_login' ], 20, 3 );
        add_action( 'wp_login_failed', [ __CLASS__, 'falha_no_login_wordpress' ], 1, 2 );
        add_filter( 'login_errors', [ __CLASS__, 'erro_login_generico' ] );
        add_filter( 'retrieve_password_message', [ __CLASS__, 'trocar_link_senha' ], 10, 3 );
        add_filter( 'show_admin_bar', [ __CLASS__, 'ocultar_barra_cliente' ], 100 );
        add_action( 'template_redirect', [ __CLASS__, 'fechar_autor' ], 0 );
        add_filter( 'rest_endpoints', [ __CLASS__, 'fechar_lista_de_usuarios' ] );
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'xmlrpc_methods', [ __CLASS__, 'esvaziar_xmlrpc' ] );
        add_filter( 'wp_headers', [ __CLASS__, 'sem_pingback' ] );
        add_filter( 'wp_is_application_passwords_available_for_user', [ __CLASS__, 'sem_senha_aplicativo' ], 10, 2 );
        add_action( 'woocommerce_before_customer_login_form', [ __CLASS__, 'aviso_senha' ] );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
    }

    /**
     * Porta do wp-login.php. Ausente ou diferente de true fica fechada.
     *
     * @param mixed $constante Valor de VH_LOGIN_WORDPRESS, ou null se a constante não existe.
     */
    public static function porta_wordpress_aberta( mixed $constante ): bool {
        return true === $constante;
    }

    public static function login_wordpress_liberado(): bool {
        if ( ! defined( 'VH_LOGIN_WORDPRESS' ) ) {
            return false;
        }
        return self::porta_wordpress_aberta( VH_LOGIN_WORDPRESS );
    }

    /**
     * Deslogado, com a porta fechada, não vê o formulário do WordPress.
     * Senha perdida segue para a conta. O POST de conteúdo protegido continua.
     */
    public static function deve_ocultar_login( bool $liberado, bool $logado, string $acao, string $metodo ): bool {
        $acao   = strtolower( $acao );
        $metodo = strtoupper( $metodo );
        if ( in_array( $acao, [ 'lostpassword', 'rp', 'resetpass' ], true ) ) {
            return false;
        }
        if ( 'postpass' === $acao && 'POST' === $metodo ) {
            return false;
        }
        if ( $logado || $liberado ) {
            return false;
        }
        return true;
    }

    /**
     * Equipe é administrador ou gestor. O restante logado é cliente da loja.
     */
    public static function eh_cliente( bool $logado, bool $manage_options, bool $cap_loja ): bool {
        return $logado && ! $manage_options && ! $cap_loja;
    }

    /**
     * @param array{script?:string,uri?:string,acao?:string,logado?:bool,destino?:string} $ctx
     */
    public static function eh_superficie( array $ctx ): bool {
        $acao = strtolower( (string) ( $ctx['acao'] ?? '' ) );
        if ( in_array( $acao, self::ACOES_SENHA, true ) ) {
            return false;
        }

        $script = strtolower( basename( (string) ( $ctx['script'] ?? '' ) ) );
        if ( in_array( $script, self::PAGINAS_LIBERADAS, true ) ) {
            return false;
        }

        if ( empty( $ctx['logado'] ) ) {
            return self::destino_e_admin( (string) ( $ctx['destino'] ?? '' ) );
        }

        if ( 'wp-login.php' === $script ) {
            return true;
        }

        $uri = strtolower( (string) ( $ctx['uri'] ?? '' ) );
        if ( str_contains( $uri, '/wp-admin' ) ) {
            return true;
        }

        return 1 === preg_match( '#/minha-loja(/|$|\?)#', $uri );
    }

    public static function destino_e_admin( string $url ): bool {
        $url = strtolower( trim( $url ) );
        if ( '' === $url ) {
            return false;
        }
        foreach ( self::ACOES_SENHA as $acao ) {
            if ( str_contains( $url, 'action=' . $acao ) ) {
                return false;
            }
        }

        return str_contains( $url, '/wp-admin' )
            || str_contains( $url, 'wp-login.php' )
            || str_contains( $url, '/minha-loja' );
    }

    public static function deve_sondar_login( bool $eh_cliente, string $pedido, string $destino ): bool {
        if ( ! $eh_cliente ) {
            return false;
        }

        return self::destino_e_admin( $pedido ) || self::destino_e_admin( $destino );
    }

    /**
     * Primeira sonda redireciona. Da segunda em diante, encerra a sessão.
     */
    public static function avaliar_sonda( int $anteriores ): string {
        return $anteriores >= 1 ? 'sair' : 'redirecionar';
    }

    public static function resposta_sonda( int $user_id ): string {
        $chave      = 'vh_sonda_admin_' . $user_id;
        $anteriores = (int) get_transient( $chave );
        $acao       = self::avaliar_sonda( $anteriores );
        set_transient( $chave, $anteriores + 1, self::JANELA );
        return $acao;
    }

    public static function url_conta(): string {
        $base = '';
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $base = (string) wc_get_page_permalink( 'myaccount' );
        }
        if ( '' === $base || str_contains( $base, 'wp-login.php' ) ) {
            $base = home_url( '/minha-conta/' );
        }
        return $base;
    }

    /**
     * Senha perdida e redefinição ficam na conta. O link do WordPress não aparece.
     */
    public static function url_senha( string $acao, string $key = '', string $login = '', int $id = 0 ): string {
        $base = '';
        if ( function_exists( 'wc_lostpassword_url' ) ) {
            $base = (string) wc_lostpassword_url();
        }
        if ( '' === $base || str_contains( $base, 'wp-login.php' ) ) {
            $base = home_url( '/minha-conta/lost-password/' );
        }
        if ( ! in_array( $acao, [ 'rp', 'resetpass' ], true ) || '' === $key ) {
            return $base;
        }

        $args = [ 'key' => $key ];
        if ( '' !== $login ) {
            $args['login'] = $login;
        }
        if ( $id > 0 ) {
            $args['id'] = $id;
        }
        return add_query_arg( $args, $base );
    }

    public static function url_erro_senha(): string {
        return add_query_arg( 'erro', 'senha', self::url_conta() );
    }

    /**
     * Administrador que erra a senha continua no wp-login.php. O restante volta para a conta.
     */
    public static function deve_sair_do_login_wordpress( bool $existe, bool $admin ): bool {
        return ! ( $existe && $admin );
    }

    public static function cliente_atual(): bool {
        return self::eh_cliente(
            is_user_logged_in(),
            current_user_can( 'manage_options' ),
            current_user_can( VH_Roles::CAP )
        );
    }

    /**
     * Conta a sonda e encerra o pedido.
     */
    public static function aplicar_sonda(): void {
        $acao = self::resposta_sonda( get_current_user_id() );
        if ( 'sair' === $acao ) {
            wp_logout();
        }
        wp_safe_redirect( self::url_conta() );
        exit;
    }

    /**
     * Redireciona o gestor para o app. O cliente cai na cerca.
     */
    public static function bloquear_telas(): void {
        if ( wp_doing_ajax() || self::requisicao_permitida() ) {
            return;
        }
        if ( self::cliente_atual() ) {
            self::aplicar_sonda();
        }
        if ( ! VH_Roles::usuario_eh_gestor() ) {
            return;
        }

        wp_safe_redirect( VH_Router::url( 'dashboard' ) );
        exit;
    }

    /**
     * Cliente já logado no wp-login.php: primeira vez volta à conta, a segunda sai.
     */
    public static function bloquear_login(): void {
        $acao = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
        if ( in_array( $acao, [ 'lostpassword', 'rp', 'resetpass' ], true ) ) {
            $key   = isset( $_REQUEST['key'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( (string) $_REQUEST['key'] ) ) : '';
            $login = isset( $_REQUEST['login'] ) ? sanitize_user( wp_unslash( (string) $_REQUEST['login'] ) ) : '';
            $id    = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
            wp_safe_redirect( self::url_senha( $acao, is_string( $key ) ? $key : '', $login, $id ) );
            exit;
        }
        $metodo = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
        if ( self::deve_ocultar_login( self::login_wordpress_liberado(), is_user_logged_in(), $acao, $metodo ) ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }
        if ( 'postpass' === $acao || ! self::cliente_atual() ) {
            return;
        }
        self::aplicar_sonda();
    }

    /**
     * /wp-admin deslogado não monta o endereço do login do WordPress.
     *
     * @param mixed $login_url
     * @param mixed $redirect
     * @param mixed $force
     * @return mixed
     */
    public static function filtrar_login_url( $login_url, $redirect = '', $force = false ) {
        unset( $redirect, $force );
        if ( self::login_wordpress_liberado() || is_user_logged_in() ) {
            return $login_url;
        }
        return home_url( '/' );
    }

    /**
     * Erro de senha no wp-login.php não desenha essa tela para cliente, gestor ou usuário desconhecido.
     *
     * @param string $username
     * @param mixed  $erro
     */
    public static function falha_no_login_wordpress( $username, $erro = null ): void {
        unset( $erro );
        $nome    = is_string( $username ) ? $username : '';
        $usuario = '' !== $nome ? get_user_by( 'login', $nome ) : false;
        if ( ! $usuario instanceof WP_User && is_email( $nome ) ) {
            $usuario = get_user_by( 'email', $nome );
        }
        $admin = $usuario instanceof WP_User && user_can( $usuario, 'manage_options' );
        if ( ! self::deve_sair_do_login_wordpress( $usuario instanceof WP_User, $admin ) ) {
            return;
        }
        if ( $usuario instanceof WP_User && user_can( $usuario, VH_Roles::CAP ) ) {
            wp_safe_redirect( VH_Router::url( 'entrar' ) );
            exit;
        }
        nocache_headers();
        wp_safe_redirect( self::url_erro_senha() );
        exit;
    }

    /**
     * @param string $mensagem
     * @param string $key
     * @param string $login
     */
    public static function trocar_link_senha( $mensagem, $key = '', $login = '' ): string {
        $mensagem = (string) $mensagem;
        if ( ! str_contains( $mensagem, 'wp-login.php' ) ) {
            return $mensagem;
        }
        $destino = self::url_senha( 'rp', (string) $key, (string) $login );
        $trocada = preg_replace( '#https?://\S*wp-login\.php\S*#', $destino, $mensagem );
        return is_string( $trocada ) ? $trocada : $mensagem;
    }

    /**
     * Login pelo wp-login.php com destino administrativo conta como sonda.
     * O login da conta da loja não passa por aqui.
     *
     * @param mixed $redirect_to
     * @param mixed $requested
     * @param mixed $usuario
     * @return mixed
     */
    public static function destino_login( $redirect_to, $requested, $usuario ) {
        if ( ! $usuario instanceof WP_User ) {
            return $redirect_to;
        }

        $pedido  = is_string( $requested ) ? $requested : '';
        $destino = is_string( $redirect_to ) ? $redirect_to : '';
        $eh      = self::eh_cliente(
            true,
            user_can( $usuario, 'manage_options' ),
            user_can( $usuario, VH_Roles::CAP )
        );
        if ( ! self::deve_sondar_login( $eh, $pedido, $destino ) ) {
            return $redirect_to;
        }

        $acao = self::resposta_sonda( (int) $usuario->ID );
        if ( 'sair' === $acao ) {
            wp_logout();
        }
        return self::url_conta();
    }

    /**
     * Usuário inexistente e senha incorreta viram a mesma frase.
     * O aviso que manda o gestor ao portal permanece.
     */
    public static function erro_login_generico( string $erro ): string {
        if ( str_contains( $erro, 'minha-loja/entrar' ) ) {
            return $erro;
        }

        $texto = strtolower( wp_strip_all_tags( $erro ) );
        $vazamentos = [
            'não está cadastrado',
            'nao esta cadastrado',
            'unknown username',
            'unknown email',
            'e-mail desconhecido',
            'email desconhecido',
            'está incorreta',
            'esta incorreta',
            'incorrect password',
            'the password you entered',
            'senha que você digitou',
            'senha que voce digitou',
        ];
        foreach ( $vazamentos as $trecho ) {
            if ( str_contains( $texto, $trecho ) ) {
                return __( 'Usuário ou senha incorretos.', 'vapor-hub-loja' );
            }
        }

        return $erro;
    }

    /**
     * @param bool $mostrar
     */
    public static function ocultar_barra_cliente( $mostrar ): bool {
        if ( self::cliente_atual() ) {
            return false;
        }
        return (bool) $mostrar;
    }

    public static function deve_esconder_usuarios( bool $pode_listar ): bool {
        return ! $pode_listar;
    }

    /**
     * A vitrine não lista usuários. Quem administra e pode listar continua vendo.
     *
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public static function fechar_lista_de_usuarios( array $endpoints ): array {
        if ( ! self::deve_esconder_usuarios( is_user_logged_in() && current_user_can( 'list_users' ) ) ) {
            return $endpoints;
        }
        unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        return $endpoints;
    }

    public static function fechar_autor(): void {
        if ( is_admin() ) {
            return;
        }

        $author = isset( $_GET['author'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['author'] ) ) : (string) get_query_var( 'author' );
        $nome   = isset( $_GET['author_name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['author_name'] ) ) : (string) get_query_var( 'author_name' );
        if ( ! self::pedido_enumera_autor( is_author(), $author, $nome ) ) {
            return;
        }

        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }

    public static function pedido_enumera_autor( bool $arquivo, string $author, string $author_name ): bool {
        if ( $arquivo ) {
            return true;
        }
        if ( '' !== $author && ctype_digit( $author ) && (int) $author > 0 ) {
            return true;
        }
        return '' !== $author_name;
    }

    /**
     * @param array<string, mixed> $metodos
     * @return array<string, mixed>
     */
    public static function esvaziar_xmlrpc( array $metodos ): array {
        unset( $metodos );
        return [];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public static function sem_pingback( array $headers ): array {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * @param bool         $disponivel
     * @param WP_User|mixed $usuario
     */
    public static function sem_senha_aplicativo( $disponivel, $usuario ): bool {
        if ( ! $usuario instanceof WP_User ) {
            return (bool) $disponivel;
        }
        if ( self::eh_cliente( true, user_can( $usuario, 'manage_options' ), user_can( $usuario, VH_Roles::CAP ) ) ) {
            return false;
        }
        return (bool) $disponivel;
    }

    /**
     * Erro de senha na conta da loja. Não explica a área da equipe.
     */
    public static function aviso_senha(): void {
        $erro = isset( $_GET['erro'] ) ? sanitize_key( wp_unslash( (string) $_GET['erro'] ) ) : '';
        if ( 'senha' !== $erro ) {
            return;
        }
        $texto = __( 'Usuário ou senha incorretos.', 'vapor-hub-loja' );
        if ( function_exists( 'wc_print_notice' ) ) {
            wc_print_notice( $texto, 'error' );
            return;
        }
        echo '<ul class="woocommerce-error" role="alert"><li>' . esc_html( $texto ) . '</li></ul>';
    }

    /**
     * Verifica se a requisição admin atual é permitida para o gestor.
     */
    private static function requisicao_permitida(): bool {
        global $pagenow;
        $pagenow = is_string( $pagenow ) ? $pagenow : '';

        return in_array( $pagenow, self::PAGINAS_LIBERADAS, true );
    }
}

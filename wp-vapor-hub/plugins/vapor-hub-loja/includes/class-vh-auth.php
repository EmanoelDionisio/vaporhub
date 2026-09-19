<?php
/**
 * Autenticação dedicada do portal Minha Loja (/minha-loja/entrar).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Auth {

    private const RATE_LIMIT_MAX     = 5;
    private const RATE_LIMIT_SECONDS = 900;

    /** Indica login em andamento pelo portal (evita bloquear wp_signon). */
    private static bool $login_portal = false;

    public static function init(): void {
        add_action( 'admin_post_nopriv_vh_loja_login', [ __CLASS__, 'processar_login' ] );
        add_action( 'admin_post_vh_loja_login', [ __CLASS__, 'processar_login' ] );
        add_filter( 'authenticate', [ __CLASS__, 'bloquear_wp_login_gestor' ], 30, 3 );
    }

    /**
     * Exibe a tela de login brandada do portal.
     */
    public static function render_login(): void {
        if ( is_user_logged_in() && current_user_can( VH_Roles::CAP ) ) {
            wp_safe_redirect( VH_Router::url( 'dashboard' ) );
            exit;
        }

        $erro = isset( $_GET['login'] ) ? sanitize_key( wp_unslash( $_GET['login'] ) ) : '';
        $redirect_to = self::redirect_seguro(
            isset( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : VH_Router::url( 'dashboard' )
        );

        VH_Front::render_login( $erro, $redirect_to );
    }

    /**
     * Processa POST de login via admin-post.php.
     */
    public static function processar_login(): void {
        if ( ! isset( $_POST['vh_loja_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vh_loja_login_nonce'] ) ), 'vh_loja_login' ) ) {
            self::falha_login( 'nonce' );
        }

        if ( self::rate_limit_excedido() ) {
            self::falha_login( 'rate' );
        }

        if ( ! self::validar_turnstile() ) {
            self::falha_login( 'turnstile' );
        }

        $usuario_input = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
        $senha         = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
        $redirect_to   = self::redirect_seguro(
            isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : VH_Router::url( 'dashboard' )
        );

        if ( '' === $usuario_input || '' === $senha ) {
            self::incrementar_tentativas();
            self::falha_login( 'credenciais', $redirect_to );
        }

        self::$login_portal = true;
        $usuario = wp_signon(
            [
                'user_login'    => $usuario_input,
                'user_password' => $senha,
                'remember'      => ! empty( $_POST['rememberme'] ),
            ],
            is_ssl()
        );
        self::$login_portal = false;

        if ( is_wp_error( $usuario ) ) {
            self::incrementar_tentativas();
            self::falha_login( 'credenciais', $redirect_to );
        }

        if ( ! user_can( $usuario, VH_Roles::CAP ) ) {
            wp_logout();
            self::incrementar_tentativas();
            self::falha_login( 'permissao', $redirect_to );
        }

        self::limpar_tentativas();
        wp_safe_redirect( $redirect_to );
        exit;
    }

    /**
     * Logout seguro via /minha-loja/sair.
     */
    public static function processar_logout(): void {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vh_loja_logout' ) ) {
            wp_safe_redirect( VH_Router::url( 'entrar' ) );
            exit;
        }

        wp_logout();
        wp_safe_redirect( VH_Router::url( 'entrar', [ 'redirect_to' => VH_Router::url( 'dashboard' ) ] ) );
        exit;
    }


    /**
     * @param \WP_User|\WP_Error|null $usuario
     * @return \WP_User|\WP_Error|null
     */
    public static function bloquear_wp_login_gestor( $usuario, $username, $password ) {
        unset( $username, $password );

        if ( is_wp_error( $usuario ) || ! $usuario instanceof WP_User ) {
            return $usuario;
        }

        if ( self::$login_portal ) {
            return $usuario;
        }

        if ( VH_Roles::usuario_eh_gestor_puro( $usuario ) ) {
            return new WP_Error(
                'vh_gestor_portal',
                sprintf(
                    /* translators: %s: URL do portal de login */
                    __( 'Gestores da loja devem acessar pelo portal: %s', 'vapor-hub-loja' ),
                    VH_Router::url( 'entrar' )
                )
            );
        }

        return $usuario;
    }

    /**
     * Valida token Cloudflare Turnstile quando ativo.
     */
    public static function validar_turnstile( ?string $token = null ): bool {
        $seg = VH_Settings::obter( 'vh_seguranca', VH_Settings::seguranca_padrao() );
        if ( empty( $seg['turnstile_ativo'] ) || '1' !== (string) $seg['turnstile_ativo'] ) {
            return true;
        }

        $secret = $seg['turnstile_secret_key'] ?? '';
        if ( '' === $secret ) {
            return false;
        }

        if ( null === $token ) {
            $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
        }
        if ( '' === $token ) {
            return false;
        }

        $resposta = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'timeout' => 15,
                'body'    => [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => self::ip_cliente(),
                ],
            ]
        );

        if ( is_wp_error( $resposta ) ) {
            return false;
        }

        $corpo = json_decode( wp_remote_retrieve_body( $resposta ), true );
        return ! empty( $corpo['success'] );
    }

    /**
     * @return array<string,string>
     */
    public static function mensagens_erro(): array {
        return [
            'nonce'      => __( 'Sessão expirada. Tente novamente.', 'vapor-hub-loja' ),
            'rate'       => __( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 'vapor-hub-loja' ),
            'turnstile'  => __( 'Verificação de segurança não concluída. Tente novamente.', 'vapor-hub-loja' ),
            'credenciais'=> __( 'Usuário ou senha incorretos.', 'vapor-hub-loja' ),
            'permissao'  => __( 'Esta conta não tem acesso ao painel Minha Loja.', 'vapor-hub-loja' ),
        ];
    }

    private static function falha_login( string $codigo, string $redirect_to = '' ): void {
        $args = [ 'login' => $codigo ];
        if ( $redirect_to ) {
            $args['redirect_to'] = rawurlencode( $redirect_to );
        }
        wp_safe_redirect( add_query_arg( $args, VH_Router::url( 'entrar' ) ) );
        exit;
    }

    private static function redirect_seguro( $url ): string {
        $url = is_string( $url ) ? $url : '';
        $validado = wp_validate_redirect( $url, VH_Router::url( 'dashboard' ) );
        if ( ! $validado || 0 !== strpos( $validado, home_url( '/minha-loja' ) ) ) {
            return VH_Router::url( 'dashboard' );
        }
        return $validado;
    }

    public static function ip_cliente(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $chave ) {
            if ( empty( $_SERVER[ $chave ] ) ) {
                continue;
            }
            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $chave ] ) );
            if ( str_contains( $ip, ',' ) ) {
                $ip = trim( explode( ',', $ip )[0] );
            }
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        return '';
    }

    private static function chave_rate_limit(): string {
        return 'vh_login_' . md5( self::ip_cliente() );
    }

    private static function rate_limit_excedido(): bool {
        $tentativas = (int) get_transient( self::chave_rate_limit() );
        return $tentativas >= self::RATE_LIMIT_MAX;
    }

    private static function incrementar_tentativas(): void {
        $chave = self::chave_rate_limit();
        $atual = (int) get_transient( $chave );
        set_transient( $chave, $atual + 1, self::RATE_LIMIT_SECONDS );
    }

    private static function limpar_tentativas(): void {
        delete_transient( self::chave_rate_limit() );
    }
}

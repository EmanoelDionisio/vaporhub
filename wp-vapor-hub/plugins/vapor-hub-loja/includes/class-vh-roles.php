<?php
/**
 * Gerenciamento de roles e capacidades.
 *
 * O Gestor da Loja administra 100% da loja dentro do app "Minha Loja".
 * Ele NÃO recebe capacidades nativas amplas do WooCommerce (manage_woocommerce,
 * edit_products, etc.): toda escrita acontece pela camada de serviços do plugin,
 * que usa as APIs oficiais do WooCommerce. O acesso é controlado por uma única
 * capacidade de entrada (gerenciar_vh_loja) verificada em rotas, REST e ações.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Roles {

    public const CAP   = 'gerenciar_vh_loja';
    public const ROLE  = 'gestor_loja';

    /**
     * Capacidades mínimas do Gestor da Loja (princípio do menor privilégio).
     *
     * - read: acesso básico ao admin.
     * - upload_files / edit_posts: necessários para a biblioteca de mídia
     *   (selecionar imagens de produtos, logo, banners e galeria).
     * - gerenciar_vh_loja: porteiro único do app Minha Loja.
     *
     * @return array<string,bool>
     */
    public static function capacidades_gestor(): array {
        return [
            'read'              => true,
            'upload_files'      => true,
            'edit_posts'        => true,
            self::CAP           => true,
        ];
    }

    /**
     * Cria a role gestor_loja na ativação do plugin.
     */
    public static function criar(): void {
        /* Administradores precisam da mesma capacidade para ver o app "Minha Loja". */
        $admin = get_role( 'administrator' );
        if ( $admin && ! $admin->has_cap( self::CAP ) ) {
            $admin->add_cap( self::CAP );
        }

        /*
         * Recria a role para garantir que as capacidades fiquem alinhadas com a
         * versão atual (remove caps amplas que existiam em versões anteriores).
         */
        remove_role( self::ROLE );
        add_role(
            self::ROLE,
            __( 'Gestor da Loja', 'vapor-hub-loja' ),
            self::capacidades_gestor()
        );
    }

    /**
     * Remove a role na desativação do plugin.
     */
    public static function remover(): void {
        $admin = get_role( 'administrator' );
        if ( $admin && $admin->has_cap( self::CAP ) ) {
            $admin->remove_cap( self::CAP );
        }

        remove_role( self::ROLE );
    }

    /**
     * Indica se o usuário é exclusivamente Gestor da Loja (não administrador).
     */
    public static function usuario_eh_gestor_puro( $usuario = null ): bool {
        if ( null === $usuario ) {
            return self::usuario_eh_gestor();
        }
        if ( ! $usuario instanceof \WP_User || ! $usuario->exists() ) {
            return false;
        }
        if ( user_can( $usuario, 'manage_options' ) ) {
            return false;
        }
        return in_array( self::ROLE, (array) $usuario->roles, true );
    }

    /**
     * Indica se o usuário atual é exclusivamente Gestor da Loja (não administrador).
     */
    public static function usuario_eh_gestor(): bool {
        $usuario = wp_get_current_user();
        if ( ! $usuario instanceof \WP_User || ! $usuario->exists() ) {
            return false;
        }
        if ( user_can( $usuario, 'manage_options' ) ) {
            return false;
        }
        return in_array( self::ROLE, (array) $usuario->roles, true );
    }

    /**
     * Restringe (defesa em profundidade) os menus nativos do admin para o gestor.
     *
     * A navegação real acontece pela barra lateral própria do app. Mesmo assim
     * removemos os menus nativos no servidor para que nada vaze caso o CSS falhe.
     */
    public static function restringir_menus(): void {
        add_action( 'admin_menu', static function (): void {
            if ( ! self::usuario_eh_gestor() ) {
                return;
            }

            $menus_remover = [
                'edit.php',
                'edit.php?post_type=page',
                'upload.php',
                'edit-comments.php',
                'tools.php',
                'options-general.php',
                'themes.php',
                'plugins.php',
                'users.php',
                'index.php',
                'edit.php?post_type=product',
                'woocommerce',
                'wc-admin&path=/analytics/overview',
            ];

            foreach ( $menus_remover as $menu ) {
                remove_menu_page( $menu );
            }
        }, 999 );
    }

    /**
     * Redireciona gestor_loja para o app Minha Loja após o login.
     */
    public static function redirecionar_login(): void {
        add_filter( 'login_redirect', static function ( $redirect_to, $requested, $usuario ) {
            if ( ! $usuario instanceof \WP_User ) {
                return $redirect_to;
            }
            if ( in_array( self::ROLE, (array) $usuario->roles, true ) && ! user_can( $usuario, 'manage_options' ) ) {
                return VH_Router::url( 'dashboard' );
            }
            return $redirect_to;
        }, 10, 3 );
    }
}

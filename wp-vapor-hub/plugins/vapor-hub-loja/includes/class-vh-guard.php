<?php
/**
 * Guarda de acesso ao wp-admin para o Gestor da Loja.
 *
 * O gestor administra tudo pelo app Minha Loja em /minha-loja/*. Qualquer
 * tentativa de abrir o wp-admin é redirecionada para o portal público.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Guard {

    /**
     * Endpoints wp-admin permitidos (AJAX de mídia e admin-post de login/logout).
     *
     * @var string[]
     */
    private const PAGINAS_LIBERADAS = [
        'admin-ajax.php',
        'admin-post.php',
        'async-upload.php',
    ];

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'bloquear_telas' ], 1 );
    }

    /**
     * Redireciona o gestor para o app quando a tela atual não é permitida.
     */
    public static function bloquear_telas(): void {
        if ( wp_doing_ajax() ) {
            return;
        }
        if ( ! VH_Roles::usuario_eh_gestor() ) {
            return;
        }
        if ( self::requisicao_permitida() ) {
            return;
        }

        wp_safe_redirect( VH_Router::url( 'dashboard' ) );
        exit;
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

<?php
/**
 * Camada de compatibilidade do admin.
 *
 * A construção do menu, dos assets e do shell migrou para VH_App. Esta classe
 * permanece como ponto de entrada estável (chamado pelo bootstrap) e delega
 * toda a inicialização do app.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Admin {

    /**
     * Inicializa o app Minha Loja.
     */
    public static function init(): void {
        VH_App::init();
    }
}

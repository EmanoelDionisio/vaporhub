<?php
/**
 * Plugin Name: Vapor Hub - Minha Loja
 * Plugin URI:  https://newalliance.tech
 * Description: App de gestão completo da loja Vapor Hub (pedidos, produtos, categorias, clientes, cupons, relatórios e conteúdo) com rota própria e isolado das telas nativas. Desenvolvido por New Alliance Tecnologia.
 * Version:     3.16.32
 * Author:      New Alliance Tecnologia
 * Author URI:  https://newalliance.tech
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vapor-hub-loja
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────────────────────────────
 * Constantes
 * ─────────────────────────────────────────────── */
define( 'VH_LOJA_VERSION', '3.16.32' );
define( 'VH_LOJA_DIR', plugin_dir_path( __FILE__ ) );
define( 'VH_LOJA_URL', plugin_dir_url( __FILE__ ) );
define( 'VH_LOJA_ASSETS', VH_LOJA_URL . 'admin/' );

/* ───────────────────────────────────────────────
 * Verificação de dependências — WooCommerce
 * ─────────────────────────────────────────────── */
function vh_loja_verificar_dependencias(): bool {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p><strong>Vapor Hub - Minha Loja:</strong> %s</p></div>',
                esc_html__( 'Este plugin requer o WooCommerce ativo para funcionar.', 'vapor-hub-loja' )
            );
        } );
        return false;
    }
    return true;
}

/* ───────────────────────────────────────────────
 * Includes
 * ─────────────────────────────────────────────── */
require_once VH_LOJA_DIR . 'includes/class-vh-roles.php';
require_once VH_LOJA_DIR . 'includes/class-vh-settings.php';
require_once VH_LOJA_DIR . 'includes/class-vh-router.php';
require_once VH_LOJA_DIR . 'includes/class-vh-portal.php';
require_once VH_LOJA_DIR . 'includes/class-vh-auth.php';
require_once VH_LOJA_DIR . 'includes/class-vh-front.php';
require_once VH_LOJA_DIR . 'includes/class-vh-guard.php';
require_once VH_LOJA_DIR . 'includes/class-vh-checkout-endereco.php';
require_once VH_LOJA_DIR . 'includes/class-vh-datetime.php';

require_once VH_LOJA_DIR . 'includes/services/class-vh-orders-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-products-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-permalinks.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-personalizacao-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-categories-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-menus-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-customers-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-coupons-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-reports-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-revenda-leads-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-comunidade-envios-service.php';

require_once VH_LOJA_DIR . 'includes/integrations/class-vh-seo-crypto.php';
require_once VH_LOJA_DIR . 'includes/integrations/class-vh-seo-google.php';
require_once VH_LOJA_DIR . 'includes/integrations/class-vh-seo-search-console.php';
require_once VH_LOJA_DIR . 'includes/integrations/class-vh-seo-analytics.php';
require_once VH_LOJA_DIR . 'includes/integrations/class-vh-seo-pagespeed.php';

require_once VH_LOJA_DIR . 'includes/integrations/class-vh-tiny-client.php';
require_once VH_LOJA_DIR . 'includes/integrations/tiny/interface-vh-tiny-driver.php';
require_once VH_LOJA_DIR . 'includes/integrations/tiny/class-vh-tiny.php';
require_once VH_LOJA_DIR . 'includes/integrations/tiny/class-vh-tiny-client-v2.php';
require_once VH_LOJA_DIR . 'includes/integrations/tiny/class-vh-tiny-driver-v3.php';
require_once VH_LOJA_DIR . 'includes/integrations/tiny/class-vh-tiny-driver-v2.php';
require_once VH_LOJA_DIR . 'includes/class-vh-tiny-queue.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-log.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-map.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-sku.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-category-sync.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-mapping-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-sync-service.php';
require_once VH_LOJA_DIR . 'includes/services/class-vh-tiny-importacao.php';
require_once VH_LOJA_DIR . 'includes/class-vh-tiny-module.php';

require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-controller.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-orders.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-products.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-categories.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-customers.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-coupons.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-reports.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-settings.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-menus.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-seo.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-revenda.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-comunidade.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-media.php';
require_once VH_LOJA_DIR . 'includes/rest/class-vh-rest-tiny.php';

require_once VH_LOJA_DIR . 'includes/class-vh-app.php';
require_once VH_LOJA_DIR . 'includes/class-vh-ui-skeleton.php';
require_once VH_LOJA_DIR . 'includes/class-vh-admin.php';
require_once VH_LOJA_DIR . 'includes/class-vh-shortcodes.php';
require_once VH_LOJA_DIR . 'includes/class-vh-media.php';
require_once VH_LOJA_DIR . 'includes/class-vh-media-profiles.php';

/* ───────────────────────────────────────────────
 * Ativação / Desativação
 * ─────────────────────────────────────────────── */
register_activation_hook( __FILE__, static function (): void {
    VH_Roles::criar();
    VH_Portal::agendar_flush_rewrites();
    require_once VH_LOJA_DIR . 'includes/services/class-vh-revenda-leads-service.php';
    require_once VH_LOJA_DIR . 'includes/services/class-vh-comunidade-envios-service.php';
    VH_Revenda_Leads_Service::instalar_tabela();
    VH_Comunidade_Envios_Service::instalar_tabela();
    require_once VH_LOJA_DIR . 'includes/class-vh-tiny-module.php';
    VH_Tiny_Module::instalar();
} );

register_deactivation_hook( __FILE__, static function (): void {
    VH_Roles::remover();
    require_once VH_LOJA_DIR . 'includes/class-vh-tiny-module.php';
    VH_Tiny_Module::desinstalar();
    flush_rewrite_rules();
} );

/* ───────────────────────────────────────────────
 * Controladores REST registrados em rest_api_init
 * ─────────────────────────────────────────────── */
function vh_loja_controladores_rest(): array {
    return [
        'VH_REST_Orders',
        'VH_REST_Products',
        'VH_REST_Categories',
        'VH_REST_Customers',
        'VH_REST_Coupons',
        'VH_REST_Reports',
        'VH_REST_Settings',
        'VH_REST_Menus',
        'VH_REST_SEO',
        'VH_REST_Revenda',
        'VH_REST_Comunidade',
        'VH_REST_Media',
        'VH_REST_Tiny',
    ];
}

/* ───────────────────────────────────────────────
 * Classe principal — Singleton
 * ─────────────────────────────────────────────── */
final class VH_Loja {

    private static ?self $instancia = null;

    public static function instancia(): self {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init(): void {
        if ( ! vh_loja_verificar_dependencias() ) {
            return;
        }

        load_plugin_textdomain( 'vapor-hub-loja', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        $this->talvez_atualizar();

        VH_Settings::registrar_opcoes();
        VH_Permalinks::init();
        VH_Media::init();
        VH_Admin::init();
        VH_Shortcodes::registrar();
        VH_Roles::restringir_menus();
        VH_Roles::redirecionar_login();
        VH_Guard::init();
        VH_Auth::init();
        VH_Front::init();
        VH_Checkout_Endereco::init();
        VH_Portal::init();
        VH_Tiny_Module::init();
        VH_Personalizacao_Service::init();

        add_action( 'rest_api_init', static function (): void {
            foreach ( vh_loja_controladores_rest() as $controlador ) {
                ( new $controlador() )->register_routes();
            }
        } );
    }

    /**
     * Reaplica roles e rewrites quando o plugin é atualizado sem reativação.
     */
    private function talvez_atualizar(): void {
        if ( get_option( 'vh_loja_versao' ) === VH_LOJA_VERSION ) {
            return;
        }
        VH_Roles::criar();
        VH_Portal::agendar_flush_rewrites();
        VH_Revenda_Leads_Service::instalar_tabela();
        VH_Comunidade_Envios_Service::instalar_tabela();
        VH_Tiny_Module::instalar();
        update_option( 'vh_loja_versao', VH_LOJA_VERSION );
    }
}

VH_Loja::instancia();

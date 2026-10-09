<?php
/**
 * Bootstrap da suíte.
 *
 * `ABSPATH` tem de existir ANTES de qualquer arquivo do plugin: todos começam
 * com `defined( 'ABSPATH' ) || exit;` e o processo morreria em silêncio.
 *
 * @package VaporHubLoja\Tests
 */

if ( ! defined( 'VH_TEST' ) ) {
	define( 'VH_TEST', true );
}
define( 'VH_PLUGIN_DIR', dirname( __DIR__ ) . '/wp-vapor-hub/plugins/vapor-hub-loja' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/stubs/wp/' );
}
if ( ! defined( 'VH_LOJA_VERSION' ) ) {
	define( 'VH_LOJA_VERSION', 'testes' );
}
if ( ! defined( 'VH_LOJA_DIR' ) ) {
	define( 'VH_LOJA_DIR', VH_PLUGIN_DIR . '/' );
}
if ( ! defined( 'VH_LOJA_URL' ) ) {
	define( 'VH_LOJA_URL', 'https://piloto.example/wp-content/plugins/vapor-hub-loja/' );
}

error_reporting( E_ALL );

require_once __DIR__ . '/framework/class-vh-test-case.php';
require_once __DIR__ . '/framework/class-vh-test-runner.php';

require_once __DIR__ . '/stubs/wp-kernel.php';
require_once __DIR__ . '/stubs/http.php';
require_once __DIR__ . '/stubs/class-vh-fake-wpdb.php';
require_once __DIR__ . '/stubs/class-vh-fake-tiny-erp.php';

/* Stubs antes dos arquivos reais: a ordem de inclusão é a costura. */
require_once __DIR__ . '/stubs/plugin-stubs.php';

$vh_arquivos_plugin = [
	'includes/class-vh-guard.php',
	'includes/class-vh-datetime.php',
	'includes/class-vh-checkout-endereco.php',
	'includes/integrations/class-vh-seo-crypto.php',
	'includes/integrations/class-vh-tiny-client.php',
	'includes/integrations/tiny/interface-vh-tiny-driver.php',
	'includes/integrations/tiny/class-vh-tiny-client-v2.php',
	'includes/integrations/tiny/class-vh-tiny-driver-v2.php',
	'includes/integrations/tiny/class-vh-tiny-driver-v3.php',
	'includes/integrations/tiny/class-vh-tiny.php',
	'includes/services/class-vh-products-service.php',
	'includes/services/class-vh-permalinks.php',
	'includes/services/class-vh-tiny-map.php',
	'includes/services/class-vh-tiny-sku.php',
	'includes/class-vh-tiny-queue.php',
	'includes/services/class-vh-tiny-sync-service.php',
	'includes/services/class-vh-tiny-importacao.php',
	'includes/services/class-vh-tiny-category-sync.php',
	'includes/class-vh-tiny-module.php',
	'includes/rest/class-vh-rest-controller.php',
	'includes/rest/class-vh-rest-tiny.php',
];

foreach ( $vh_arquivos_plugin as $vh_arquivo ) {
	require_once VH_PLUGIN_DIR . '/' . $vh_arquivo;
}

require_once __DIR__ . '/framework/class-vh-test-env.php';

global $wpdb;
$wpdb = new VH_Fake_WPDB();
VH_Test_Env::$wpdb = $wpdb;

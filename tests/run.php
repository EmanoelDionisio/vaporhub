<?php
/**
 * Entrada CLI da suíte: `php tests/run.php [--suite=unit|integration|all] [--filter=trecho]`.
 *
 * @package VaporHubLoja\Tests
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require_once __DIR__ . '/bootstrap.php';

$opcoes = getopt( '', [ 'suite::', 'filter::' ] );
$suite  = (string) ( $opcoes['suite'] ?? 'all' );
$filtro = (string) ( $opcoes['filter'] ?? '' );

$diretorios = match ( $suite ) {
	'unit'        => [ __DIR__ . '/unit' ],
	'integration' => [ __DIR__ . '/integration' ],
	default       => [ __DIR__ . '/unit', __DIR__ . '/integration' ],
};

printf(
	'Suíte: %s%s%s',
	$suite,
	'' !== $filtro ? ' (filtro: ' . $filtro . ')' : '',
	PHP_EOL . PHP_EOL
);

$runner = new VH_Test_Runner( $filtro );
exit( $runner->executar( $diretorios ) );

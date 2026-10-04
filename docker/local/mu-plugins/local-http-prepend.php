<?php
/**
 * Ajustes de $_SERVER antes do WordPress (auto_prepend). Só o stack local.
 */

$local_port = getenv( 'HTTP_PORT' ) ?: '8083';
$host       = $_SERVER['HTTP_HOST'] ?? '';

$vh_nome = strtolower( preg_replace( '/:\d+$/', '', $host ) ?? '' );
$vh_direto = in_array( $vh_nome, [ 'localhost', '127.0.0.1', 'vaporhub.local' ], true );

if ( $vh_direto ) {
	$_SERVER['HTTPS']       = 'off';
	$_SERVER['SERVER_PORT'] = $local_port;

	if ( ! preg_match( '/:\d+$/', $host ) ) {
		$_SERVER['HTTP_HOST'] = $host . ':' . $local_port;
	}

	$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
}

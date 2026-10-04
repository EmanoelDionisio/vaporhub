<?php
/**
 * Plugin Name: Vapor Hub Local HTTP Fix
 * Description: Dev local na porta 8083. Acesso direto fica em HTTP. Túnel Cloudflare usa HTTPS público, sem a porta 8083.
 */

$vh_local_port = getenv( 'HTTP_PORT' ) ?: '8083';
$vh_local_host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
$vh_local_host = preg_replace( '/:\d+$/', '', $vh_local_host ) ?? '';
$vh_direct     = in_array( $vh_local_host, [ '', '127.0.0.1', 'localhost', 'vaporhub.local' ], true );
$vh_public     = strtolower( (string) ( getenv( 'PUBLIC_HOST' ) ?: '' ) );
$vh_tunnel     = ! $vh_direct && (
	$vh_public === $vh_local_host
	|| ! empty( $_SERVER['HTTP_CF_RAY'] )
	|| ! empty( $_SERVER['HTTP_CF_VISITOR'] )
);

if ( $vh_tunnel ) {
	$_SERVER['HTTPS']                  = 'on';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
	$_SERVER['SERVER_PORT']            = '443';
	$_SERVER['HTTP_HOST']              = $vh_local_host;
	$_SERVER['SERVER_NAME']            = $vh_local_host;
} else {
	$_SERVER['HTTPS']                  = 'off';
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
	$_SERVER['SERVER_PORT']            = $vh_local_port;
}

if ( ! function_exists( 'add_filter' ) ) {
	return;
}

if ( ! $vh_tunnel ) {
	add_filter( 'secure_auth_cookie', '__return_false', 999 );
	add_filter( 'secure_logged_in_cookie', '__return_false', 999 );
	add_filter( 'secure_auth_redirect', '__return_false', 999 );
	add_filter( 'woocommerce_mcp_allow_insecure_transport', '__return_true', 999 );
}

add_filter(
	'set_url_scheme',
	static function ( $url ) {
		if ( ! is_string( $url ) ) {
			return $url;
		}
		$host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
		$host = preg_replace( '/:\d+$/', '', $host ) ?? '';
		if ( in_array( $host, [ '127.0.0.1', 'localhost', 'vaporhub.local', '' ], true ) ) {
			if ( str_starts_with( $url, 'https://127.0.0.1' ) || str_starts_with( $url, 'https://localhost' ) ) {
				return 'http' . substr( $url, 5 );
			}
		}
		return $url;
	},
	1
);

$vh_reescrita_publica = static function ( $url ) {
	if ( ! is_string( $url ) ) {
		return $url;
	}
	$host = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
	$host = preg_replace( '/:\d+$/', '', $host ) ?? '';
	$direto = in_array( $host, [ '', '127.0.0.1', 'localhost', 'vaporhub.local' ], true );
	$publico = strtolower( (string) ( getenv( 'PUBLIC_HOST' ) ?: '' ) );
	$tunel = ! $direto && (
		$publico === $host
		|| ! empty( $_SERVER['HTTP_CF_RAY'] )
		|| ! empty( $_SERVER['HTTP_CF_VISITOR'] )
	);
	if ( ! $tunel || $host === '' ) {
		return $url;
	}
	$origem = 'https://' . $host;
	$url    = preg_replace( '#^https?://127\.0\.0\.1(?::\d+)?#', $origem, $url ) ?? $url;
	$url    = preg_replace( '#^https?://localhost(?::\d+)?#', $origem, $url ) ?? $url;
	$url    = preg_replace( '#^http://' . preg_quote( $host, '#' ) . '(?::\d+)?#', $origem, $url ) ?? $url;
	return $url;
};

add_filter( 'home_url', $vh_reescrita_publica, 1 );
add_filter( 'site_url', $vh_reescrita_publica, 1 );
add_filter( 'rest_url', $vh_reescrita_publica, 1 );
add_filter( 'content_url', $vh_reescrita_publica, 1 );
add_filter( 'plugins_url', $vh_reescrita_publica, 1 );
add_filter( 'theme_root_uri', $vh_reescrita_publica, 1 );
add_filter( 'stylesheet_directory_uri', $vh_reescrita_publica, 1 );
add_filter( 'template_directory_uri', $vh_reescrita_publica, 1 );
add_filter( 'style_loader_src', $vh_reescrita_publica, 1 );
add_filter( 'script_loader_src', $vh_reescrita_publica, 1 );
add_filter( 'clean_url', $vh_reescrita_publica, 1 );
add_filter( 'wp_get_attachment_url', $vh_reescrita_publica, 1 );
add_filter(
	'upload_dir',
	static function ( $uploads ) use ( $vh_reescrita_publica ) {
		if ( ! is_array( $uploads ) ) {
			return $uploads;
		}
		foreach ( [ 'url', 'baseurl' ] as $chave ) {
			if ( isset( $uploads[ $chave ] ) && is_string( $uploads[ $chave ] ) ) {
				$uploads[ $chave ] = $vh_reescrita_publica( $uploads[ $chave ] );
			}
		}
		return $uploads;
	},
	1
);

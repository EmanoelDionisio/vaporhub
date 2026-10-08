<?php
/**
 * Ícones dos itens de primeiro nível dos menus da vitrine.
 *
 * Um único catálogo serve o painel e a vitrine: menus e benefícios.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ícones permitidos. A chave é o que fica gravado no item.
 *
 * @return array<string,string>
 */
function vh_menu_icones(): array {
	return array(
		'casa'        => __( 'Casa', 'vapor-hub' ),
		'sacola'      => __( 'Sacola', 'vapor-hub' ),
		'ferramenta'  => __( 'Ferramenta', 'vapor-hub' ),
		'bateria'     => __( 'Bateria', 'vapor-hub' ),
		'dispositivo' => __( 'Dispositivo', 'vapor-hub' ),
		'frasco'      => __( 'Frasco', 'vapor-hub' ),
		'gota'        => __( 'Gota', 'vapor-hub' ),
		'folha'       => __( 'Folha', 'vapor-hub' ),
		'capsula'     => __( 'Cápsula', 'vapor-hub' ),
		'etiqueta'    => __( 'Etiqueta', 'vapor-hub' ),
		'estrela'      => __( 'Estrela', 'vapor-hub' ),
		'chama'        => __( 'Chama', 'vapor-hub' ),
		'check-circle' => __( 'Conferido', 'vapor-hub' ),
		'settings'     => __( 'Ajuste', 'vapor-hub' ),
		'truck'        => __( 'Entrega', 'vapor-hub' ),
		'users'        => __( 'Pessoas', 'vapor-hub' ),
		'star'         => __( 'Favorito', 'vapor-hub' ),
		'heart'        => __( 'Coração', 'vapor-hub' ),
		'shield'       => __( 'Proteção', 'vapor-hub' ),
		'gift'         => __( 'Presente', 'vapor-hub' ),
	);
}

/**
 * Slug de ícone presente nas classes do item, ou vazio.
 *
 * @param array<int,string> $classes
 */
function vh_menu_icone_slug( array $classes ): string {
	foreach ( $classes as $classe ) {
		$classe = (string) $classe;
		if ( str_starts_with( $classe, 'vh-ico-' ) ) {
			$slug = sanitize_key( substr( $classe, 7 ) );
			return isset( vh_menu_icones()[ $slug ] ) ? $slug : '';
		}
	}
	return '';
}

/**
 * SVG do ícone. Só sai do catálogo; não aceita desenho vindo de fora.
 */
function vh_menu_icone_html( string $slug ): string {
	$caminhos = array(
		'casa'        => '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/>',
		'sacola'      => '<path d="M6.5 8h11l-.8 12H7.3z"/><path d="M9 8V7a3 3 0 0 1 6 0v1"/>',
		'ferramenta'  => '<path d="M14.5 6.5a3.5 3.5 0 0 0-4.8 4.3L4 16.5 7.5 20l5.7-5.7a3.5 3.5 0 0 0 4.3-4.8L15 12l-3-3z"/>',
		'bateria'     => '<rect x="7" y="7" width="10" height="13" rx="2"/><path d="M10 4h4"/>',
		'dispositivo' => '<rect x="8" y="3" width="8" height="18" rx="2"/><path d="M11 17h2"/>',
		'frasco'      => '<path d="M10 3h4"/><path d="M11 3v4l-4 8a3 3 0 0 0 2.6 4.5h4.8A3 3 0 0 0 17 15l-4-8V3"/>',
		'gota'        => '<path d="M12 3s6 6.2 6 10a6 6 0 0 1-12 0c0-3.8 6-10 6-10z"/>',
		'folha'       => '<path d="M5 19c8-1 13-8 14-15-7 1-14 6-14 15z"/><path d="M8.5 15.5c2-2.2 4.2-4 7-6"/>',
		'capsula'     => '<rect x="3.5" y="9" width="17" height="6" rx="3" transform="rotate(-28 12 12)"/>',
		'etiqueta'    => '<path d="M4 12.5 12.5 4H20v7.5L11.5 20z"/><circle cx="15.5" cy="8.5" r="1"/>',
		'estrela'      => '<path d="m12 3.5 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 16.8 7.2 18.4l.9-5.4L4.2 9.2l5.4-.8z"/>',
		'chama'        => '<path d="M12 3s.5 3.2 2.8 5.2C16.6 9.6 18 11.4 18 14a6 6 0 0 1-12 0c0-1.6.7-3 2-4.4C9.2 8.4 10 6.6 10 5c.8 1.1 1.4 1.8 2 2.4"/>',
		'check-circle' => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="m9 11 2.2 2.2L20 5"/>',
		'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M18.4 5.6l-1.6 1.6M7.2 16.8l-1.6 1.6"/>',
		'truck'        => '<rect x="2" y="7" width="11" height="8" rx="1"/><path d="M13 10h4l3 3v2h-7z"/><circle cx="6.5" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/>',
		'users'        => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'star'         => '<path d="m12 2 2.9 6.1L21.5 9l-4.8 4.6 1.1 6.6L12 17.3 6.2 20.2l1.1-6.6L2.5 9l6.6-.9z"/>',
		'heart'        => '<path d="M12 20s-7-4.4-7-9a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 4.6-7 9-7 9z"/>',
		'shield'       => '<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/>',
		'gift'         => '<rect x="3" y="10" width="18" height="10" rx="1"/><path d="M3 14h18M12 10v10M12 10c-2 0-4-1.2-4-3s2-3 4-1c2-2 4-.8 4 1s-2 3-4 3z"/>',
	);
	if ( ! isset( $caminhos[ $slug ] ) ) {
		return '';
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $caminhos[ $slug ] . '</svg>';
}

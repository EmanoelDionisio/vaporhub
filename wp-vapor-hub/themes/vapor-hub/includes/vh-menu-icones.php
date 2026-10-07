<?php
/**
 * Ícones dos itens de primeiro nível dos menus da vitrine.
 *
 * Um único catálogo serve o painel e o desenho do menu.
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
		'estrela'     => __( 'Estrela', 'vapor-hub' ),
		'chama'       => __( 'Chama', 'vapor-hub' ),
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
		'estrela'     => '<path d="m12 3.5 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 16.8 7.2 18.4l.9-5.4L4.2 9.2l5.4-.8z"/>',
		'chama'       => '<path d="M12 3s.5 3.2 2.8 5.2C16.6 9.6 18 11.4 18 14a6 6 0 0 1-12 0c0-1.6.7-3 2-4.4C9.2 8.4 10 6.6 10 5c.8 1.1 1.4 1.8 2 2.4"/>',
	);
	if ( ! isset( $caminhos[ $slug ] ) ) {
		return '';
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $caminhos[ $slug ] . '</svg>';
}

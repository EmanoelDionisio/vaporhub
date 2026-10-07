<?php
/**
 * Walker dos menus da vitrine.
 *
 * O mesmo markup serve o topo, a faixa de departamentos e o menu mobile.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

class VH_Menu_Walker extends Walker_Nav_Menu {

    /**
     * @param string   $output            HTML acumulado.
     * @param WP_Post  $data_object       Item do menu.
     * @param int      $depth             Nível, começando em 0.
     * @param stdClass $args              Argumentos de wp_nav_menu.
     * @param int      $current_object_id ID do objeto atual.
     */
    public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
        unset( $args, $current_object_id );

        $item    = $data_object;
        $classes = empty( $item->classes ) ? [] : (array) $item->classes;
        $classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
        if ( ! in_array( 'menu-item', $classes, true ) ) {
            $classes[] = 'menu-item';
        }

        $output .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';

        $titulo = isset( $item->title ) ? (string) $item->title : '';
        $url    = ! empty( $item->url ) ? esc_url( (string) $item->url ) : '';
        $miolo  = '';
        if ( 0 === (int) $depth && function_exists( 'vh_menu_icone_slug' ) && function_exists( 'vh_menu_icone_html' ) ) {
            $slug = vh_menu_icone_slug( $classes );
            $svg  = '' !== $slug ? vh_menu_icone_html( $slug ) : '';
            if ( '' !== $svg ) {
                $miolo .= '<span class="vh-nav-ico">' . $svg . '</span>';
            }
        }
        $miolo .= '<span class="vh-nav-rotulo">' . esc_html( $titulo ) . '</span>';

        if ( $url ) {
            $attrs = ' href="' . $url . '"';
            if ( ! empty( $item->target ) && '_blank' === $item->target ) {
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }
            $output .= '<a' . $attrs . '>' . $miolo . '</a>';
        } else {
            $output .= '<span class="vh-nav-sem-link">' . $miolo . '</span>';
        }

        if ( in_array( 'menu-item-has-children', $classes, true ) ) {
            $output .= '<button type="button" class="vh-nav-sub" aria-expanded="false"><span class="vh-sr-only">'
                . esc_html( sprintf(
                    /* translators: %s: menu item title */
                    __( 'Abrir submenu de %s', 'vapor-hub' ),
                    wp_strip_all_tags( $titulo )
                ) )
                . '</span></button>';
        }
    }
}

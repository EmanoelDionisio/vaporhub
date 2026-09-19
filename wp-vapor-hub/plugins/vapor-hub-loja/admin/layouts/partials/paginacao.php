<?php
/**
 * Helper de paginação das listagens do app.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'vh_loja_paginacao' ) ) {
    /**
     * Renderiza a navegação de páginas de uma listagem.
     *
     * @param int                  $atual    Página atual.
     * @param int                  $paginas  Total de páginas.
     * @param string               $secao    Seção do app.
     * @param array<string,scalar> $extra    Parâmetros de filtro a preservar.
     */
    function vh_loja_paginacao( int $atual, int $paginas, string $secao, array $extra = [] ): void {
        if ( $paginas <= 1 ) {
            return;
        }

        $extra = array_filter( $extra, static fn( $v ) => '' !== $v && null !== $v );

        echo '<div class="vh-loja-paginacao">';

        if ( $atual > 1 ) {
            printf(
                '<a href="%s">&laquo; %s</a>',
                esc_url( VH_Router::url( $secao, array_merge( $extra, [ 'pagina' => $atual - 1 ] ) ) ),
                esc_html__( 'Anterior', 'vapor-hub-loja' )
            );
        }

        $inicio = max( 1, $atual - 2 );
        $fim    = min( $paginas, $atual + 2 );

        for ( $i = $inicio; $i <= $fim; $i++ ) {
            if ( $i === $atual ) {
                printf( '<span class="vh-pagina-atual">%d</span>', (int) $i );
            } else {
                printf(
                    '<a href="%s">%d</a>',
                    esc_url( VH_Router::url( $secao, array_merge( $extra, [ 'pagina' => $i ] ) ) ),
                    (int) $i
                );
            }
        }

        if ( $atual < $paginas ) {
            printf(
                '<a href="%s">%s &raquo;</a>',
                esc_url( VH_Router::url( $secao, array_merge( $extra, [ 'pagina' => $atual + 1 ] ) ) ),
                esc_html__( 'Próxima', 'vapor-hub-loja' )
            );
        }

        echo '</div>';
    }
}

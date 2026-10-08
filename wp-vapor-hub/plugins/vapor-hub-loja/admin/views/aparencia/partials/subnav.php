<?php
/**
 * Abas internas da Aparência.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_aparencia_acao = isset( $vh_aba ) ? (string) $vh_aba : 'banner';
$vh_aparencia_abas = [
    'banner' => __( 'Banner', 'vapor-hub-loja' ),
    'inicio' => __( 'Página inicial', 'vapor-hub-loja' ),
    'cores'  => __( 'Cores', 'vapor-hub-loja' ),
    'rodape' => __( 'Rodapé', 'vapor-hub-loja' ),
];
?>

<nav class="vh-revenda-subnav" aria-label="<?php esc_attr_e( 'Partes da aparência', 'vapor-hub-loja' ); ?>">
    <?php foreach ( $vh_aparencia_abas as $vh_slug => $vh_rotulo ) : ?>
        <a class="vh-revenda-subnav__link <?php echo $vh_aparencia_acao === $vh_slug ? 'is-ativo' : ''; ?>"
           href="<?php echo esc_url( VH_Router::url( 'aparencia', [ 'acao' => $vh_slug ] ) ); ?>">
            <?php echo esc_html( $vh_rotulo ); ?>
        </a>
    <?php endforeach; ?>
</nav>

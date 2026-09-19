<?php
/**
 * Barra lateral de navegação do app Minha Loja.
 *
 * @var string $secao Seção atual (do shell).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_secoes      = VH_Router::secoes();
$vh_grupos      = VH_Router::grupos();
$vh_secao_atual = $secao;

$vh_logo = '';
if ( class_exists( 'VH_Settings' ) ) {
    $vh_identidade = VH_Settings::obter( 'vh_identidade_visual', VH_Settings::identidade_visual_padrao() );
    $vh_logo       = $vh_identidade['logo_url'] ?? '';
}
?>

<div class="vh-loja-brand">
    <?php if ( $vh_logo ) : ?>
        <img class="vh-loja-brand-logo" src="<?php echo esc_url( $vh_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
    <?php else : ?>
        <span class="dashicons dashicons-store"></span>
        <span class="vh-loja-brand-nome"><?php esc_html_e( 'Minha Loja', 'vapor-hub-loja' ); ?></span>
    <?php endif; ?>
</div>

<nav class="vh-loja-nav">
    <?php foreach ( $vh_grupos as $vh_grupo_id => $vh_grupo_label ) : ?>
        <span class="vh-loja-nav-grupo"><?php echo esc_html( $vh_grupo_label ); ?></span>
        <ul class="vh-loja-nav-lista">
            <?php
            foreach ( $vh_secoes as $vh_slug => $vh_def ) :
                if ( ( $vh_def['grupo'] ?? '' ) !== $vh_grupo_id ) {
                    continue;
                }
                $vh_ativo = ( $vh_slug === $vh_secao_atual ) ? ' vh-loja-nav-item--ativo' : '';
                ?>
                <li>
                    <a class="vh-loja-nav-item<?php echo esc_attr( $vh_ativo ); ?>" href="<?php echo esc_url( VH_Router::url( $vh_slug ) ); ?>">
                        <span class="dashicons <?php echo esc_attr( $vh_def['icone'] ); ?>"></span>
                        <span class="vh-loja-nav-texto"><?php echo esc_html( $vh_def['label'] ); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</nav>

<div class="vh-loja-nav-rodape">
    <a class="vh-loja-nav-sair" href="<?php echo esc_url( VH_Router::url( 'sair' ) ); ?>">
        <span class="dashicons dashicons-exit"></span>
        <?php esc_html_e( 'Sair', 'vapor-hub-loja' ); ?>
    </a>
</div>

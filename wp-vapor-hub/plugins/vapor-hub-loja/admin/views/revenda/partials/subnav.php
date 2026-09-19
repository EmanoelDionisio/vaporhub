<?php
/**
 * Abas internas da seção Revenda.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_revenda_acao = VH_Router::acao_atual( 'revenda' );
?>

<nav class="vh-revenda-subnav" aria-label="<?php esc_attr_e( 'Seções de revenda', 'vapor-hub-loja' ); ?>">
    <a class="vh-revenda-subnav__link <?php echo 'listar' === $vh_revenda_acao || 'ver' === $vh_revenda_acao ? 'is-ativo' : ''; ?>"
       href="<?php echo esc_url( VH_Router::url( 'revenda' ) ); ?>">
        <span class="dashicons dashicons-id-alt"></span>
        <?php esc_html_e( 'Cadastros recebidos', 'vapor-hub-loja' ); ?>
    </a>
    <a class="vh-revenda-subnav__link <?php echo 'config' === $vh_revenda_acao ? 'is-ativo' : ''; ?>"
       href="<?php echo esc_url( VH_Router::url( 'revenda', [ 'acao' => 'config' ] ) ); ?>">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php esc_html_e( 'Configuração', 'vapor-hub-loja' ); ?>
    </a>
</nav>

<?php
/**
 * Abas internas da seção Comunidade.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_comunidade_acao = VH_Router::acao_atual( 'comunidade' );
$vh_pendentes       = VH_Comunidade_Envios_Service::listar( [ 'pagina' => 1, 'por_pagina' => 1, 'status' => 'pendente' ] );
?>

<nav class="vh-revenda-subnav" aria-label="<?php esc_attr_e( 'Seções de comunidade', 'vapor-hub-loja' ); ?>">
    <a class="vh-revenda-subnav__link <?php echo 'galeria' === $vh_comunidade_acao ? 'is-ativo' : ''; ?>"
       href="<?php echo esc_url( VH_Router::url( 'comunidade' ) ); ?>">
        <span class="dashicons dashicons-format-gallery"></span>
        <?php esc_html_e( 'Galeria publicada', 'vapor-hub-loja' ); ?>
    </a>
    <a class="vh-revenda-subnav__link <?php echo 'envios' === $vh_comunidade_acao || 'ver' === $vh_comunidade_acao ? 'is-ativo' : ''; ?>"
       href="<?php echo esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] ) ); ?>">
        <span class="dashicons dashicons-camera"></span>
        <?php esc_html_e( 'Envios recebidos', 'vapor-hub-loja' ); ?>
        <?php if ( (int) $vh_pendentes['total'] > 0 ) : ?>
            <span class="vh-revenda-badge-novos"><?php echo esc_html( (string) (int) $vh_pendentes['total'] ); ?></span>
        <?php endif; ?>
    </a>
</nav>

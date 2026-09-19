<?php
/**
 * View — Painel Visão Geral (Dashboard).
 *
 * Todos os atalhos e links são internos ao app Minha Loja. Nenhum aponta para
 * telas nativas do WooCommerce/WordPress.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_resumo  = VH_Reports_Service::resumo();
$vh_recentes = VH_Orders_Service::listar( [ 'pagina' => 1, 'por_pagina' => 5 ] );
?>

<div class="vh-admin-wrap">

    <!-- KPIs -->
    <div class="vh-kpi-grid vh-kpi-grid--4">
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['receita']['hoje'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Receita hoje', 'vapor-hub-loja' ); ?></span>
        </div>
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['receita']['mes'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Receita no mês', 'vapor-hub-loja' ); ?></span>
        </div>
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['ticket_medio'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Ticket médio (30 dias)', 'vapor-hub-loja' ); ?></span>
        </div>
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo esc_html( (string) $vh_resumo['produtos_publicados'] ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Produtos publicados', 'vapor-hub-loja' ); ?></span>
        </div>
    </div>

    <!-- Atalhos internos -->
    <div class="vh-admin-cards">
        <a href="<?php echo esc_url( VH_Router::url( 'pedidos' ) ); ?>" class="vh-admin-card">
            <span class="vh-admin-card__icone" aria-hidden="true"><span class="dashicons dashicons-clipboard"></span></span>
            <span class="vh-card-titulo"><?php esc_html_e( 'Pedidos', 'vapor-hub-loja' ); ?></span>
            <span class="vh-card-descricao"><?php esc_html_e( 'Gerenciar pedidos da loja', 'vapor-hub-loja' ); ?></span>
        </a>
        <a href="<?php echo esc_url( VH_Router::url( 'produtos', [ 'acao' => 'novo' ] ) ); ?>" class="vh-admin-card">
            <span class="vh-admin-card__icone" aria-hidden="true"><span class="dashicons dashicons-plus-alt"></span></span>
            <span class="vh-card-titulo"><?php esc_html_e( 'Adicionar produto', 'vapor-hub-loja' ); ?></span>
            <span class="vh-card-descricao"><?php esc_html_e( 'Criar um novo produto', 'vapor-hub-loja' ); ?></span>
        </a>
        <a href="<?php echo esc_url( VH_Router::url( 'cupons', [ 'acao' => 'novo' ] ) ); ?>" class="vh-admin-card">
            <span class="vh-admin-card__icone" aria-hidden="true"><span class="dashicons dashicons-tickets-alt"></span></span>
            <span class="vh-card-titulo"><?php esc_html_e( 'Criar cupom', 'vapor-hub-loja' ); ?></span>
            <span class="vh-card-descricao"><?php esc_html_e( 'Oferecer um desconto', 'vapor-hub-loja' ); ?></span>
        </a>
        <a href="<?php echo esc_url( VH_Router::url( 'relatorios' ) ); ?>" class="vh-admin-card">
            <span class="vh-admin-card__icone" aria-hidden="true"><span class="dashicons dashicons-chart-bar"></span></span>
            <span class="vh-card-titulo"><?php esc_html_e( 'Relatórios', 'vapor-hub-loja' ); ?></span>
            <span class="vh-card-descricao"><?php esc_html_e( 'Acompanhar o desempenho', 'vapor-hub-loja' ); ?></span>
        </a>
    </div>

    <!-- Pedidos recentes (links internos) -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Pedidos recentes', 'vapor-hub-loja' ); ?></h2>
        <?php if ( ! empty( $vh_recentes['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Pedido', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Cliente', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Data', 'vapor-hub-loja' ); ?></th>
                        <th class="vh-col-acoes"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_recentes['itens'] as $vh_ped ) : ?>
                        <tr>
                            <td><strong>#<?php echo esc_html( $vh_ped['numero'] ); ?></strong></td>
                            <td><?php echo esc_html( $vh_ped['cliente'] ); ?></td>
                            <td><span class="vh-status vh-status--<?php echo esc_attr( $vh_ped['status'] ); ?>"><?php echo esc_html( $vh_ped['status_label'] ); ?></span></td>
                            <td><?php echo wp_kses_post( $vh_ped['total_html'] ); ?></td>
                            <td><?php echo esc_html( $vh_ped['data'] ); ?></td>
                            <td class="vh-col-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'pedidos', [ 'acao' => 'ver', 'id' => $vh_ped['id'] ] ) ); ?>">
                                    <?php esc_html_e( 'Abrir', 'vapor-hub-loja' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:16px">
                <a class="vh-btn vh-btn--secundario" href="<?php echo esc_url( VH_Router::url( 'pedidos' ) ); ?>">
                    <?php esc_html_e( 'Ver todos os pedidos', 'vapor-hub-loja' ); ?>
                </a>
            </p>
        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum pedido ainda.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>

</div>

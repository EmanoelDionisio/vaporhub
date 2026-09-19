<?php
/**
 * View — Relatórios.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_resumo = VH_Reports_Service::resumo();
?>

<div class="vh-admin-wrap">

    <div class="vh-kpi-grid vh-kpi-grid--6">
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['receita']['hoje'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Receita hoje', 'vapor-hub-loja' ); ?></span>
        </div>
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['receita']['sete'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Últimos 7 dias', 'vapor-hub-loja' ); ?></span>
        </div>
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['receita']['trinta'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Últimos 30 dias', 'vapor-hub-loja' ); ?></span>
        </div>
        <div class="vh-kpi-card">
            <span class="vh-kpi-valor"><?php echo wp_kses_post( wc_price( $vh_resumo['receita']['mes'] ) ); ?></span>
            <span class="vh-kpi-label"><?php esc_html_e( 'Mês atual', 'vapor-hub-loja' ); ?></span>
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

    <div class="vh-form-colunas">
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Pedidos por status', 'vapor-hub-loja' ); ?></h2>
            <dl class="vh-def-lista">
                <?php foreach ( $vh_resumo['pedidos_status'] as $vh_st ) : ?>
                    <div>
                        <dt><span class="vh-status vh-status--<?php echo esc_attr( $vh_st['slug'] ); ?>"><?php echo esc_html( $vh_st['label'] ); ?></span></dt>
                        <dd><strong><?php echo esc_html( (string) $vh_st['total'] ); ?></strong></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Top 5 produtos (30 dias)', 'vapor-hub-loja' ); ?></h2>
            <?php if ( ! empty( $vh_resumo['top_produtos'] ) ) : ?>
                <dl class="vh-def-lista">
                    <?php foreach ( $vh_resumo['top_produtos'] as $vh_prod ) : ?>
                        <div>
                            <dt><?php echo esc_html( $vh_prod['nome'] ); ?></dt>
                            <dd><strong><?php echo esc_html( (string) $vh_prod['quantidade'] ); ?></strong> <?php esc_html_e( 'un.', 'vapor-hub-loja' ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php else : ?>
                <p class="vh-tabela-vazia"><?php esc_html_e( 'Sem vendas no período.', 'vapor-hub-loja' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

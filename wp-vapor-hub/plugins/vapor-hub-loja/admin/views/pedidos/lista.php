<?php
/**
 * View — Lista de Pedidos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina   = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$vh_busca    = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';
$vh_data_de  = isset( $_GET['data_de'] ) ? sanitize_text_field( wp_unslash( $_GET['data_de'] ) ) : '';
$vh_data_ate = isset( $_GET['data_ate'] ) ? sanitize_text_field( wp_unslash( $_GET['data_ate'] ) ) : '';

$vh_lista = VH_Orders_Service::listar( [
    'pagina'     => $vh_pagina,
    'por_pagina' => 20,
    'status'     => $vh_status,
    'busca'      => $vh_busca,
    'data_de'    => $vh_data_de,
    'data_ate'   => $vh_data_ate,
] );

$vh_status_opcoes = VH_Orders_Service::status_editaveis();
?>

<div class="vh-admin-wrap">

    <form method="get" class="vh-loja-toolbar">
        <input type="hidden" name="page" value="<?php echo esc_attr( VH_Router::SLUG ); ?>" />
        <input type="hidden" name="secao" value="pedidos" />

        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-filtro-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-filtro-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Nº, cliente ou e-mail', 'vapor-hub-loja' ); ?>" />
            </div>
            <div class="vh-form-grupo">
                <label for="vh-filtro-status"><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></label>
                <select id="vh-filtro-status" name="status">
                    <option value=""><?php esc_html_e( 'Todos', 'vapor-hub-loja' ); ?></option>
                    <?php foreach ( $vh_status_opcoes as $vh_slug => $vh_label ) : ?>
                        <option value="<?php echo esc_attr( $vh_slug ); ?>" <?php selected( $vh_status, $vh_slug ); ?>>
                            <?php echo esc_html( $vh_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vh-form-grupo">
                <label for="vh-filtro-de"><?php esc_html_e( 'De', 'vapor-hub-loja' ); ?></label>
                <input type="date" id="vh-filtro-de" name="data_de" value="<?php echo esc_attr( $vh_data_de ); ?>" />
            </div>
            <div class="vh-form-grupo">
                <label for="vh-filtro-ate"><?php esc_html_e( 'Até', 'vapor-hub-loja' ); ?></label>
                <input type="date" id="vh-filtro-ate" name="data_ate" value="<?php echo esc_attr( $vh_data_ate ); ?>" />
            </div>
            <button type="submit" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Filtrar', 'vapor-hub-loja' ); ?>
            </button>
        </div>
    </form>

    <div class="vh-admin-section">
        <h2><?php printf( esc_html__( 'Pedidos (%d)', 'vapor-hub-loja' ), (int) $vh_lista['total'] ); ?></h2>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Pedido', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Cliente', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Data', 'vapor-hub-loja' ); ?></th>
                        <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_lista['itens'] as $vh_pedido ) : ?>
                        <tr>
                            <td><strong>#<?php echo esc_html( $vh_pedido['numero'] ); ?></strong></td>
                            <td>
                                <?php echo esc_html( $vh_pedido['cliente'] ); ?>
                                <?php if ( $vh_pedido['email'] ) : ?>
                                    <br /><span style="color:var(--vh-cinza-500);font-size:12px"><?php echo esc_html( $vh_pedido['email'] ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="vh-status vh-status--<?php echo esc_attr( $vh_pedido['status'] ); ?>">
                                    <?php echo esc_html( $vh_pedido['status_label'] ); ?>
                                </span>
                            </td>
                            <td><?php echo wp_kses_post( $vh_pedido['total_html'] ); ?></td>
                            <td><?php echo esc_html( $vh_pedido['data'] ); ?></td>
                            <td class="vh-col-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'pedidos', [ 'acao' => 'ver', 'id' => $vh_pedido['id'] ] ) ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e( 'Abrir', 'vapor-hub-loja' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            require VH_LOJA_DIR . 'admin/layouts/partials/paginacao.php';
            vh_loja_paginacao( (int) $vh_lista['pagina'], (int) $vh_lista['paginas'], 'pedidos', [
                'status'   => $vh_status,
                'busca'    => $vh_busca,
                'data_de'  => $vh_data_de,
                'data_ate' => $vh_data_ate,
            ] );
            ?>
        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum pedido encontrado com os filtros atuais.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

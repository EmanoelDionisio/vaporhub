<?php
/**
 * View — Lista de Cupons.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_busca  = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';

$vh_lista = VH_Coupons_Service::listar( [
    'pagina'     => $vh_pagina,
    'por_pagina' => 20,
    'busca'      => $vh_busca,
] );
?>

<div class="vh-admin-wrap">

    <form method="get" class="vh-loja-toolbar">
        <input type="hidden" name="page" value="<?php echo esc_attr( VH_Router::SLUG ); ?>" />
        <input type="hidden" name="secao" value="cupons" />

        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-cup-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-cup-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Código do cupom', 'vapor-hub-loja' ); ?>" />
            </div>
            <button type="submit" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Filtrar', 'vapor-hub-loja' ); ?>
            </button>
        </div>

        <a class="vh-btn vh-btn--primario" href="<?php echo esc_url( VH_Router::url( 'cupons', [ 'acao' => 'novo' ] ) ); ?>">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Novo cupom', 'vapor-hub-loja' ); ?>
        </a>
    </form>

    <div class="vh-admin-section">
        <h2><?php printf( esc_html__( 'Cupons (%d)', 'vapor-hub-loja' ), (int) $vh_lista['total'] ); ?></h2>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Código', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Tipo', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Valor', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Usos', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Validade', 'vapor-hub-loja' ); ?></th>
                        <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_lista['itens'] as $vh_cup ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $vh_cup['codigo'] ); ?></strong></td>
                            <td><?php echo esc_html( $vh_cup['tipo_label'] ); ?></td>
                            <td>
                                <?php echo 'percent' === $vh_cup['tipo'] ? esc_html( $vh_cup['valor'] . '%' ) : wp_kses_post( wc_price( (float) $vh_cup['valor'] ) ); ?>
                            </td>
                            <td>
                                <?php echo esc_html( $vh_cup['usos'] ); ?><?php echo $vh_cup['limite'] ? esc_html( ' / ' . $vh_cup['limite'] ) : ''; ?>
                            </td>
                            <td><?php echo esc_html( $vh_cup['expira'] ?: '—' ); ?></td>
                            <td class="vh-col-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'cupons', [ 'acao' => 'editar', 'id' => $vh_cup['id'] ] ) ); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e( 'Editar', 'vapor-hub-loja' ); ?>
                                </a>
                                <button type="button" class="vh-tabela-acao vh-tabela-acao--perigo vh-rest-acao"
                                        data-endpoint="cupons/<?php echo esc_attr( $vh_cup['id'] ); ?>"
                                        data-method="DELETE"
                                        data-confirmar="<?php esc_attr_e( 'Excluir este cupom?', 'vapor-hub-loja' ); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e( 'Excluir', 'vapor-hub-loja' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            require_once VH_LOJA_DIR . 'admin/layouts/partials/paginacao.php';
            vh_loja_paginacao( (int) $vh_lista['pagina'], (int) $vh_lista['paginas'], 'cupons', [ 'busca' => $vh_busca ] );
            ?>
        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum cupom cadastrado.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

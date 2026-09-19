<?php
/**
 * View — Lista de Clientes.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_busca  = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';

$vh_lista = VH_Customers_Service::listar( [
    'pagina'     => $vh_pagina,
    'por_pagina' => 20,
    'busca'      => $vh_busca,
] );
?>

<div class="vh-admin-wrap">

    <form method="get" class="vh-loja-toolbar">
        <input type="hidden" name="page" value="<?php echo esc_attr( VH_Router::SLUG ); ?>" />
        <input type="hidden" name="secao" value="clientes" />

        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-cli-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-cli-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Nome ou e-mail', 'vapor-hub-loja' ); ?>" />
            </div>
            <button type="submit" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Filtrar', 'vapor-hub-loja' ); ?>
            </button>
        </div>
    </form>

    <div class="vh-admin-section">
        <h2><?php printf( esc_html__( 'Clientes (%d)', 'vapor-hub-loja' ), (int) $vh_lista['total'] ); ?></h2>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Cliente', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'E-mail', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Telefone', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Cidade', 'vapor-hub-loja' ); ?></th>
                        <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_lista['itens'] as $vh_cli ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $vh_cli['nome'] ?: '—' ); ?></strong></td>
                            <td><?php echo esc_html( $vh_cli['email'] ); ?></td>
                            <td><?php echo esc_html( $vh_cli['telefone'] ?: '—' ); ?></td>
                            <td><?php echo esc_html( $vh_cli['cidade'] ?: '—' ); ?></td>
                            <td class="vh-col-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'clientes', [ 'acao' => 'ver', 'id' => $vh_cli['id'] ] ) ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e( 'Ver', 'vapor-hub-loja' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            require_once VH_LOJA_DIR . 'admin/layouts/partials/paginacao.php';
            vh_loja_paginacao( (int) $vh_lista['pagina'], (int) $vh_lista['paginas'], 'clientes', [ 'busca' => $vh_busca ] );
            ?>
        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum cliente encontrado.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

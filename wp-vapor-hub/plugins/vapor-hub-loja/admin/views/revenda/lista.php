<?php
/**
 * View — Revenda: cadastros recebidos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_busca  = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';
$vh_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

$vh_lista = VH_Revenda_Leads_Service::listar(
    [
        'pagina'     => $vh_pagina,
        'por_pagina' => 20,
        'busca'      => $vh_busca,
        'status'     => $vh_status,
    ]
);

$vh_novos = VH_Revenda_Leads_Service::listar( [ 'pagina' => 1, 'por_pagina' => 1, 'status' => 'novo' ] );
?>

<div class="vh-admin-wrap">

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <form method="get" class="vh-loja-toolbar" action="<?php echo esc_url( VH_Router::url( 'revenda' ) ); ?>">
        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-rev-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-rev-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Nome, e-mail, documento ou WhatsApp', 'vapor-hub-loja' ); ?>" />
            </div>
            <div class="vh-form-grupo">
                <label for="vh-rev-status"><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></label>
                <select id="vh-rev-status" name="status">
                    <option value=""><?php esc_html_e( 'Todos', 'vapor-hub-loja' ); ?></option>
                    <option value="novo" <?php selected( $vh_status, 'novo' ); ?>><?php esc_html_e( 'Novos', 'vapor-hub-loja' ); ?></option>
                    <option value="lido" <?php selected( $vh_status, 'lido' ); ?>><?php esc_html_e( 'Lidos', 'vapor-hub-loja' ); ?></option>
                </select>
            </div>
            <button type="submit" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Filtrar', 'vapor-hub-loja' ); ?>
            </button>
        </div>
    </form>

    <div class="vh-admin-section">
        <h2>
            <?php
            printf(
                esc_html__( 'Cadastros recebidos (%d)', 'vapor-hub-loja' ),
                (int) $vh_lista['total']
            );
            ?>
            <?php if ( (int) $vh_novos['total'] > 0 ) : ?>
                <span class="vh-revenda-badge-novos">
                    <?php
                    printf(
                        esc_html__( '%d novo(s)', 'vapor-hub-loja' ),
                        (int) $vh_novos['total']
                    );
                    ?>
                </span>
            <?php endif; ?>
        </h2>
        <p class="vh-form-descricao">
            <?php esc_html_e( 'Leads enviados pelo formulário de revenda na loja. Protegidos por Turnstile quando ativo em Segurança.', 'vapor-hub-loja' ); ?>
        </p>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Data', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Nome', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Contato', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Navegador', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
                        <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_lista['itens'] as $vh_item ) : ?>
                        <tr class="<?php echo 'novo' === $vh_item['status'] ? 'vh-linha-destaque' : ''; ?>">
                            <td><?php echo esc_html( $vh_item['criado_em'] ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $vh_item['nome'] ); ?></strong>
                                <?php if ( $vh_item['documento'] ) : ?>
                                    <br><span class="vh-texto-suave"><?php echo esc_html( $vh_item['documento'] ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( $vh_item['email'] ); ?>
                                <?php if ( $vh_item['whatsapp'] ) : ?>
                                    <br><span class="vh-texto-suave"><?php echo esc_html( $vh_item['whatsapp'] ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $vh_item['navegador'] ); ?></td>
                            <td>
                                <span class="vh-status vh-status--<?php echo esc_attr( $vh_item['status'] ); ?>">
                                    <?php echo esc_html( 'novo' === $vh_item['status'] ? __( 'Novo', 'vapor-hub-loja' ) : __( 'Lido', 'vapor-hub-loja' ) ); ?>
                                </span>
                            </td>
                            <td class="vh-col-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'revenda', [ 'acao' => 'ver', 'id' => $vh_item['id'] ] ) ); ?>">
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
            vh_loja_paginacao(
                (int) $vh_lista['pagina'],
                (int) $vh_lista['paginas'],
                'revenda',
                [
                    'busca'  => $vh_busca,
                    'status' => $vh_status,
                ]
            );
            ?>
        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum cadastro recebido ainda.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

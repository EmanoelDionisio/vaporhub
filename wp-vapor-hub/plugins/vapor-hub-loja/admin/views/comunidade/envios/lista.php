<?php
/**
 * View — Comunidade: envios recebidos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_busca  = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';
$vh_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

$vh_lista = VH_Comunidade_Envios_Service::listar(
    [
        'pagina'     => $vh_pagina,
        'por_pagina' => 20,
        'busca'      => $vh_busca,
        'status'     => $vh_status,
    ]
);

$vh_pendentes = VH_Comunidade_Envios_Service::listar( [ 'pagina' => 1, 'por_pagina' => 1, 'status' => 'pendente' ] );
?>

<div class="vh-admin-wrap">

    <?php require __DIR__ . '/../partials/subnav.php'; ?>

    <form method="get" class="vh-loja-toolbar" action="<?php echo esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] ) ); ?>">
        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-com-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-com-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Nome, @instagram ou legenda', 'vapor-hub-loja' ); ?>" />
            </div>
            <div class="vh-form-grupo">
                <label for="vh-com-status"><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></label>
                <select id="vh-com-status" name="status">
                    <option value=""><?php esc_html_e( 'Todos', 'vapor-hub-loja' ); ?></option>
                    <option value="pendente" <?php selected( $vh_status, 'pendente' ); ?>><?php esc_html_e( 'Pendentes', 'vapor-hub-loja' ); ?></option>
                    <option value="aprovado" <?php selected( $vh_status, 'aprovado' ); ?>><?php esc_html_e( 'Aprovados', 'vapor-hub-loja' ); ?></option>
                    <option value="recusado" <?php selected( $vh_status, 'recusado' ); ?>><?php esc_html_e( 'Recusados', 'vapor-hub-loja' ); ?></option>
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
                esc_html__( 'Envios recebidos (%d)', 'vapor-hub-loja' ),
                (int) $vh_lista['total']
            );
            ?>
            <?php if ( (int) $vh_pendentes['total'] > 0 ) : ?>
                <span class="vh-revenda-badge-novos">
                    <?php
                    printf(
                        esc_html__( '%d pendente(s)', 'vapor-hub-loja' ),
                        (int) $vh_pendentes['total']
                    );
                    ?>
                </span>
            <?php endif; ?>
        </h2>
        <p class="vh-form-descricao">
            <?php esc_html_e( 'Fotos enviadas pelo formulário da galeria na loja. Aprove para publicar na galeria ou recuse para não exibir.', 'vapor-hub-loja' ); ?>
        </p>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Foto', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Data', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Enviado por', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Legenda', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
                        <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_lista['itens'] as $vh_item ) : ?>
                        <tr class="<?php echo 'pendente' === $vh_item['status'] ? 'vh-linha-destaque' : ''; ?>">
                            <td>
                                <?php if ( ! empty( $vh_item['imagem_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $vh_item['imagem_url'] ); ?>" alt="" class="vh-comunidade-thumb-lista" width="56" height="56" loading="lazy" decoding="async" />
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $vh_item['criado_em'] ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $vh_item['nome'] ); ?></strong>
                                <br><span class="vh-texto-suave">@<?php echo esc_html( $vh_item['instagram'] ); ?></span>
                            </td>
                            <td><?php echo esc_html( $vh_item['legenda'] ?: '—' ); ?></td>
                            <td>
                                <?php
                                $rotulos_status = [
                                    'pendente' => __( 'Pendente', 'vapor-hub-loja' ),
                                    'aprovado' => __( 'Aprovado', 'vapor-hub-loja' ),
                                    'recusado' => __( 'Recusado', 'vapor-hub-loja' ),
                                ];
                                $classe_status = [
                                    'pendente' => 'novo',
                                    'aprovado' => 'completed',
                                    'recusado' => 'cancelled',
                                ];
                                ?>
                                <span class="vh-status vh-status--<?php echo esc_attr( $classe_status[ $vh_item['status'] ] ?? 'pending' ); ?>">
                                    <?php echo esc_html( $rotulos_status[ $vh_item['status'] ] ?? $vh_item['status'] ); ?>
                                </span>
                            </td>
                            <td class="vh-col-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'ver', 'id' => $vh_item['id'] ] ) ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e( 'Ver', 'vapor-hub-loja' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            if ( $vh_lista['paginas'] > 1 ) :
                $base_url = VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] );
                ?>
                <div class="vh-paginacao">
                    <?php for ( $p = 1; $p <= $vh_lista['paginas']; $p++ ) : ?>
                        <?php if ( $p === $vh_pagina ) : ?>
                            <span class="vh-paginacao-atual"><?php echo esc_html( (string) $p ); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'pagina' => $p, 'busca' => $vh_busca, 'status' => $vh_status ], $base_url ) ); ?>">
                                <?php echo esc_html( (string) $p ); ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum envio encontrado.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

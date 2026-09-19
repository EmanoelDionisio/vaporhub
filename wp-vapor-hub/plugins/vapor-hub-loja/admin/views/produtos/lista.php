<?php
/**
 * View — Lista de Produtos.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina    = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_busca     = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';
$vh_categoria = isset( $_GET['categoria'] ) ? sanitize_title( wp_unslash( $_GET['categoria'] ) ) : '';

$vh_lista = VH_Products_Service::listar( [
    'pagina'     => $vh_pagina,
    'por_pagina' => 20,
    'busca'      => $vh_busca,
    'categoria'  => $vh_categoria,
] );

$vh_categorias = VH_Categories_Service::listar_arvore_plana();

$vh_tiny_coluna = class_exists( 'VH_Tiny' ) && VH_Tiny::pode_enviar();
?>

<div class="vh-admin-wrap">

    <form method="get" class="vh-loja-toolbar">
        <input type="hidden" name="page" value="<?php echo esc_attr( VH_Router::SLUG ); ?>" />
        <input type="hidden" name="secao" value="produtos" />

        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-prod-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-prod-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Nome ou SKU', 'vapor-hub-loja' ); ?>" />
            </div>
            <div class="vh-form-grupo">
                <label for="vh-prod-cat"><?php esc_html_e( 'Categoria', 'vapor-hub-loja' ); ?></label>
                <select id="vh-prod-cat" name="categoria">
                    <option value=""><?php esc_html_e( 'Todas', 'vapor-hub-loja' ); ?></option>
                    <?php foreach ( $vh_categorias as $vh_cat ) :
                        $vh_prefixo = (int) ( $vh_cat['nivel'] ?? 0 ) > 0 ? str_repeat( '— ', (int) $vh_cat['nivel'] ) : '';
                        ?>
                        <option value="<?php echo esc_attr( $vh_cat['slug'] ); ?>" <?php selected( $vh_categoria, $vh_cat['slug'] ); ?>>
                            <?php echo esc_html( $vh_prefixo . $vh_cat['nome'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Filtrar', 'vapor-hub-loja' ); ?>
            </button>
        </div>

        <a class="vh-btn vh-btn--primario" href="<?php echo esc_url( VH_Router::url( 'produtos', [ 'acao' => 'novo' ] ) ); ?>">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Novo produto', 'vapor-hub-loja' ); ?>
        </a>
    </form>

    <div class="vh-admin-section">
        <h2><?php printf( esc_html__( 'Produtos (%d)', 'vapor-hub-loja' ), (int) $vh_lista['total'] ); ?></h2>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>
            <table class="vh-admin-tabela">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Produto', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'SKU', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Preço', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Estoque', 'vapor-hub-loja' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
                        <?php if ( $vh_tiny_coluna ) : ?>
                            <th><?php esc_html_e( 'Tiny', 'vapor-hub-loja' ); ?></th>
                        <?php endif; ?>
                        <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vh_lista['itens'] as $vh_prod ) : ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <?php if ( $vh_prod['imagem'] ) : ?>
                                        <img src="<?php echo esc_url( $vh_prod['imagem'] ); ?>" alt="" width="40" height="40" style="border-radius:6px;object-fit:cover" />
                                    <?php endif; ?>
                                    <strong><?php echo esc_html( $vh_prod['nome'] ); ?></strong>
                                </div>
                            </td>
                            <td><?php echo esc_html( $vh_prod['sku'] ?: '—' ); ?></td>
                            <td><?php echo wp_kses_post( $vh_prod['preco_html'] ?: '—' ); ?></td>
                            <td>
                                <?php
                                if ( null !== $vh_prod['estoque'] ) {
                                    echo esc_html( (string) $vh_prod['estoque'] );
                                } else {
                                    echo $vh_prod['em_estoque'] ? esc_html__( 'Em estoque', 'vapor-hub-loja' ) : esc_html__( 'Esgotado', 'vapor-hub-loja' );
                                }
                                ?>
                            </td>
                            <td>
                                <span class="vh-status vh-status--<?php echo esc_attr( 'publish' === $vh_prod['status'] ? 'completed' : 'pending' ); ?>">
                                    <?php echo esc_html( 'publish' === $vh_prod['status'] ? __( 'Publicado', 'vapor-hub-loja' ) : __( 'Rascunho', 'vapor-hub-loja' ) ); ?>
                                </span>
                            </td>
                            <?php if ( $vh_tiny_coluna ) : ?>
                                <td>
                                    <?php
                                    $vh_tiny_est = $vh_prod['tiny'] ?? null;
                                    if ( is_array( $vh_tiny_est ) ) :
                                        ?>
                                        <span class="vh-tiny-sync vh-tiny-sync--<?php echo esc_attr( (string) $vh_tiny_est['status'] ); ?>"
                                              title="<?php echo esc_attr( (string) $vh_tiny_est['rotulo'] ); ?>">
                                            <span class="dashicons dashicons-<?php echo esc_attr( 'sincronizado' === $vh_tiny_est['status'] ? 'yes-alt' : ( 'pendente' === $vh_tiny_est['status'] ? 'warning' : 'minus' ) ); ?>"></span>
                                            <?php echo esc_html( (string) $vh_tiny_est['rotulo'] ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="vh-tiny-sync vh-tiny-sync--off">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="vh-col-acoes">
                                <div class="vh-tabela-acoes" role="group" aria-label="<?php esc_attr_e( 'Ações do produto', 'vapor-hub-loja' ); ?>">
                                <?php if ( ! empty( $vh_prod['url_loja'] ) ) : ?>
                                    <a class="vh-tabela-acao" href="<?php echo esc_url( $vh_prod['url_loja'] ); ?>" target="_blank" rel="noopener noreferrer"
                                       title="<?php esc_attr_e( 'Ver produto na loja', 'vapor-hub-loja' ); ?>">
                                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                        <span class="vh-tabela-acao__texto"><?php esc_html_e( 'Ver', 'vapor-hub-loja' ); ?></span>
                                    </a>
                                <?php endif; ?>
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'produtos', [ 'acao' => 'editar', 'id' => $vh_prod['id'] ] ) ); ?>"
                                   title="<?php esc_attr_e( 'Editar produto', 'vapor-hub-loja' ); ?>">
                                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                    <span class="vh-tabela-acao__texto"><?php esc_html_e( 'Editar', 'vapor-hub-loja' ); ?></span>
                                </a>
                                <button type="button" class="vh-tabela-acao vh-tabela-acao--perigo vh-rest-acao"
                                        data-endpoint="produtos/<?php echo esc_attr( $vh_prod['id'] ); ?>"
                                        data-method="DELETE"
                                        data-confirmar="<?php esc_attr_e( 'Mover este produto para a lixeira?', 'vapor-hub-loja' ); ?>"
                                        title="<?php esc_attr_e( 'Excluir produto', 'vapor-hub-loja' ); ?>">
                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                    <span class="vh-tabela-acao__texto"><?php esc_html_e( 'Excluir', 'vapor-hub-loja' ); ?></span>
                                </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            require_once VH_LOJA_DIR . 'admin/layouts/partials/paginacao.php';
            vh_loja_paginacao( (int) $vh_lista['pagina'], (int) $vh_lista['paginas'], 'produtos', [
                'busca'     => $vh_busca,
                'categoria' => $vh_categoria,
            ] );
            ?>
        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhum produto encontrado.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

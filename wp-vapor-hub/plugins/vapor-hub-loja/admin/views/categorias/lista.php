<?php
/**
 * View — Lista de Categorias (árvore hierárquica).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_pagina = isset( $_GET['pagina'] ) ? max( 1, absint( wp_unslash( $_GET['pagina'] ) ) ) : 1;
$vh_busca  = isset( $_GET['busca'] ) ? sanitize_text_field( wp_unslash( $_GET['busca'] ) ) : '';

$vh_lista = VH_Categories_Service::listar( [
    'pagina'     => $vh_pagina,
    'por_pagina' => 20,
    'busca'      => $vh_busca,
] );

$vh_modo_arvore = ( $vh_lista['modo'] ?? 'arvore' ) === 'arvore';
?>

<div class="vh-admin-wrap">

    <form method="get" class="vh-loja-toolbar">
        <input type="hidden" name="page" value="<?php echo esc_attr( VH_Router::SLUG ); ?>" />
        <input type="hidden" name="secao" value="categorias" />

        <div class="vh-loja-filtros">
            <div class="vh-form-grupo">
                <label for="vh-cat-busca"><?php esc_html_e( 'Buscar', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-cat-busca" name="busca" value="<?php echo esc_attr( $vh_busca ); ?>"
                       placeholder="<?php esc_attr_e( 'Nome da categoria', 'vapor-hub-loja' ); ?>" />
            </div>
            <button type="submit" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Filtrar', 'vapor-hub-loja' ); ?>
            </button>
            <?php if ( '' !== $vh_busca ) : ?>
                <a class="vh-btn vh-btn--secundario" href="<?php echo esc_url( VH_Router::url( 'categorias' ) ); ?>">
                    <?php esc_html_e( 'Ver árvore completa', 'vapor-hub-loja' ); ?>
                </a>
            <?php endif; ?>
        </div>

        <a class="vh-btn vh-btn--primario" href="<?php echo esc_url( VH_Router::url( 'categorias', [ 'acao' => 'novo' ] ) ); ?>">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Nova categoria', 'vapor-hub-loja' ); ?>
        </a>
    </form>

    <div class="vh-admin-section">
        <h2><?php printf( esc_html__( 'Categorias (%d)', 'vapor-hub-loja' ), (int) $vh_lista['total'] ); ?></h2>

        <?php if ( $vh_modo_arvore ) : ?>
            <p class="vh-form-descricao vh-cat-arvore-dica">
                <?php esc_html_e( 'Arraste pelo ícone ≡ para reorganizar. Solte em cima de uma categoria para torná-la subcategoria, ou entre linhas para reordenar no mesmo nível.', 'vapor-hub-loja' ); ?>
            </p>
        <?php endif; ?>

        <?php if ( ! empty( $vh_lista['itens'] ) ) : ?>

            <?php if ( $vh_modo_arvore ) : ?>
                <div id="vh-cat-arvore" class="vh-cat-arvore" role="list">
                    <?php foreach ( $vh_lista['itens'] as $vh_idx => $vh_cat ) :
                        $vh_nivel      = (int) $vh_cat['nivel'];
                        $vh_tem_filhos = (int) $vh_cat['filhos'] > 0;
                        $vh_anterior   = $vh_lista['itens'][ $vh_idx - 1 ] ?? null;
                        $vh_proximo    = $vh_lista['itens'][ $vh_idx + 1 ] ?? null;
                        $vh_classes    = [ 'vh-cat-linha' ];

                        if ( 0 === $vh_nivel ) {
                            $vh_classes[] = 'vh-cat-linha--raiz';
                        } else {
                            $vh_classes[] = 'vh-cat-linha--filho';
                        }
                        if ( $vh_tem_filhos ) {
                            $vh_classes[] = 'vh-cat-linha--pai';
                        }
                        if ( $vh_nivel > 0 && ( null === $vh_proximo || (int) $vh_proximo['nivel'] <= $vh_nivel ) ) {
                            $vh_classes[] = 'vh-cat-linha--filho-fim';
                        }
                        if ( $vh_nivel > 0 && ( null === $vh_anterior || (int) $vh_anterior['nivel'] < $vh_nivel ) ) {
                            $vh_classes[] = 'vh-cat-linha--filho-inicio';
                        }
                        ?>
                        <div class="<?php echo esc_attr( implode( ' ', $vh_classes ) ); ?>" role="listitem"
                             data-id="<?php echo esc_attr( (string) $vh_cat['id'] ); ?>"
                             data-pai-id="<?php echo esc_attr( (string) $vh_cat['pai_id'] ); ?>"
                             data-nivel="<?php echo esc_attr( (string) $vh_nivel ); ?>"
                             style="--vh-cat-nivel: <?php echo esc_attr( (string) $vh_nivel ); ?>;">
                            <?php if ( $vh_nivel > 0 ) : ?>
                                <span class="vh-cat-linha-tree" aria-hidden="true"></span>
                            <?php endif; ?>

                            <span class="vh-cat-drag-handle" role="button" tabindex="0"
                                  title="<?php esc_attr_e( 'Arrastar para reorganizar', 'vapor-hub-loja' ); ?>"
                                  aria-label="<?php esc_attr_e( 'Arrastar categoria', 'vapor-hub-loja' ); ?>">
                                <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                            </span>

                            <div class="vh-cat-linha-corpo">
                                <?php if ( $vh_cat['imagem_url'] ) : ?>
                                    <img class="vh-cat-linha-thumb" src="<?php echo esc_url( $vh_cat['imagem_url'] ); ?>" alt="" width="40" height="40" />
                                <?php else : ?>
                                    <span class="vh-cat-linha-thumb vh-cat-linha-thumb--vazio" aria-hidden="true">
                                        <span class="dashicons dashicons-category"></span>
                                    </span>
                                <?php endif; ?>

                                <div class="vh-cat-linha-textos">
                                    <div class="vh-cat-linha-titulo">
                                        <?php if ( $vh_nivel > 0 ) : ?>
                                            <span class="vh-cat-linha-badge vh-cat-linha-badge--filho"><?php esc_html_e( 'Subcategoria', 'vapor-hub-loja' ); ?></span>
                                        <?php elseif ( $vh_tem_filhos ) : ?>
                                            <span class="vh-cat-linha-badge vh-cat-linha-badge--raiz"><?php esc_html_e( 'Com subcategorias', 'vapor-hub-loja' ); ?></span>
                                        <?php endif; ?>
                                        <strong class="vh-cat-linha-nome"><?php echo esc_html( $vh_cat['nome'] ); ?></strong>
                                    </div>
                                    <?php if ( $vh_nivel > 0 && ! empty( $vh_cat['caminho'] ) ) : ?>
                                        <span class="vh-cat-linha-caminho"><?php echo esc_html( (string) $vh_cat['caminho'] ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( $vh_tem_filhos ) : ?>
                                        <span class="vh-cat-linha-meta">
                                            <?php
                                            printf(
                                                esc_html( _n( '%d subcategoria', '%d subcategorias', (int) $vh_cat['filhos'], 'vapor-hub-loja' ) ),
                                                (int) $vh_cat['filhos']
                                            );
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <span class="vh-cat-linha-produtos">
                                    <?php
                                    printf(
                                        esc_html( _n( '%d produto', '%d produtos', (int) $vh_cat['total'], 'vapor-hub-loja' ) ),
                                        (int) $vh_cat['total']
                                    );
                                    ?>
                                </span>
                            </div>

                            <div class="vh-cat-linha-acoes">
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'categorias', [ 'acao' => 'novo', 'pai' => $vh_cat['id'] ] ) ); ?>"
                                   title="<?php esc_attr_e( 'Adicionar subcategoria', 'vapor-hub-loja' ); ?>">
                                    <span class="dashicons dashicons-plus"></span>
                                    <span class="vh-cat-acao-texto"><?php esc_html_e( 'Subcategoria', 'vapor-hub-loja' ); ?></span>
                                </a>
                                <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'categorias', [ 'acao' => 'editar', 'id' => $vh_cat['id'] ] ) ); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e( 'Editar', 'vapor-hub-loja' ); ?>
                                </a>
                                <button type="button" class="vh-tabela-acao vh-tabela-acao--perigo vh-rest-acao"
                                        data-endpoint="categorias/<?php echo esc_attr( $vh_cat['id'] ); ?>"
                                        data-method="DELETE"
                                        data-confirmar="<?php esc_attr_e( 'Excluir esta categoria?', 'vapor-hub-loja' ); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e( 'Excluir', 'vapor-hub-loja' ); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <table class="vh-admin-tabela">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Categoria', 'vapor-hub-loja' ); ?></th>
                            <th><?php esc_html_e( 'Caminho', 'vapor-hub-loja' ); ?></th>
                            <th><?php esc_html_e( 'Produtos', 'vapor-hub-loja' ); ?></th>
                            <th class="vh-col-acoes"><?php esc_html_e( 'Ações', 'vapor-hub-loja' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $vh_lista['itens'] as $vh_cat ) : ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <?php if ( $vh_cat['imagem_url'] ) : ?>
                                            <img src="<?php echo esc_url( $vh_cat['imagem_url'] ); ?>" alt="" width="40" height="40" style="border-radius:6px;object-fit:cover" />
                                        <?php endif; ?>
                                        <strong><?php echo esc_html( $vh_cat['nome'] ); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $vh_cat['caminho'] ?? '' ); ?></td>
                                <td><?php echo esc_html( (string) $vh_cat['total'] ); ?></td>
                                <td class="vh-col-acoes">
                                    <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'categorias', [ 'acao' => 'editar', 'id' => $vh_cat['id'] ] ) ); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php esc_html_e( 'Editar', 'vapor-hub-loja' ); ?>
                                    </a>
                                    <button type="button" class="vh-tabela-acao vh-tabela-acao--perigo vh-rest-acao"
                                            data-endpoint="categorias/<?php echo esc_attr( $vh_cat['id'] ); ?>"
                                            data-method="DELETE"
                                            data-confirmar="<?php esc_attr_e( 'Excluir esta categoria?', 'vapor-hub-loja' ); ?>">
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
                vh_loja_paginacao( (int) $vh_lista['pagina'], (int) $vh_lista['paginas'], 'categorias', [ 'busca' => $vh_busca ] );
                ?>
            <?php endif; ?>

        <?php else : ?>
            <p class="vh-tabela-vazia"><?php esc_html_e( 'Nenhuma categoria encontrada.', 'vapor-hub-loja' ); ?></p>
        <?php endif; ?>
    </div>
</div>

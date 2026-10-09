<?php
/**
 * View — Criar/Editar Categoria.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id     = VH_Router::id_atual();
$vh_edicao = $vh_id > 0;
$vh_cat    = $vh_edicao ? VH_Categories_Service::obter( $vh_id ) : null;

if ( $vh_edicao && ! $vh_cat ) {
    echo '<div class="vh-admin-wrap"><div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Categoria não encontrada.', 'vapor-hub-loja' ) . '</p></div></div>';
    return;
}

$vh_pai_preset = isset( $_GET['pai'] ) ? absint( wp_unslash( $_GET['pai'] ) ) : 0;

$vh_dados = wp_parse_args(
    (array) $vh_cat,
    [
        'nome'       => '',
        'slug'       => '',
        'descricao'  => '',
        'imagem_id'  => 0,
        'imagem_url' => '',
        'pai_id'     => $vh_pai_preset,
    ]
);

if ( ! $vh_edicao && $vh_pai_preset > 0 ) {
    $vh_dados['pai_id'] = $vh_pai_preset;
}

$vh_opcoes_pai = VH_Categories_Service::opcoes_pai( $vh_edicao ? $vh_id : 0 );
$vh_endpoint   = $vh_edicao ? 'categorias/' . $vh_id : 'categorias';
$vh_metodo     = $vh_edicao ? 'PATCH' : 'POST';
?>

<div class="vh-admin-wrap">

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'categorias' ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para categorias', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <form class="vh-rest-form" data-endpoint="<?php echo esc_attr( $vh_endpoint ); ?>" data-method="<?php echo esc_attr( $vh_metodo ); ?>"
          data-redirect="<?php echo esc_url( VH_Router::url( 'categorias' ) ); ?>">

        <div class="vh-admin-section" style="max-width:680px">
            <h2><?php echo $vh_edicao ? esc_html__( 'Editar categoria', 'vapor-hub-loja' ) : esc_html__( 'Nova categoria', 'vapor-hub-loja' ); ?></h2>

            <div class="vh-form-grupo">
                <label for="vh-cat-pai"><?php esc_html_e( 'Fica dentro de…', 'vapor-hub-loja' ); ?></label>
                <select id="vh-cat-pai" name="pai_id">
                    <?php foreach ( $vh_opcoes_pai as $vh_opcao ) : ?>
                        <option value="<?php echo esc_attr( (string) $vh_opcao['id'] ); ?>"
                            <?php selected( (int) $vh_dados['pai_id'], (int) $vh_opcao['id'] ); ?>>
                            <?php echo esc_html( $vh_opcao['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Escolha uma categoria pai para criar uma subcategoria. Deixe como principal se for um grupo de topo (ex.: POD Descartável, e-Líquidos).', 'vapor-hub-loja' ); ?>
                </p>
            </div>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Nome', 'vapor-hub-loja' ); ?></label>
                <input type="text" name="nome" value="<?php echo esc_attr( $vh_dados['nome'] ); ?>" required />
            </div>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Slug (opcional)', 'vapor-hub-loja' ); ?></label>
                <input type="text" name="slug" value="<?php echo esc_attr( $vh_dados['slug'] ); ?>" />
                <p class="description"><?php esc_html_e( 'Deixe em branco para gerar automaticamente a partir do nome.', 'vapor-hub-loja' ); ?></p>
            </div>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Descrição da categoria', 'vapor-hub-loja' ); ?></label>
                <textarea name="descricao" rows="6"><?php echo esc_textarea( $vh_dados['descricao'] ); ?></textarea>
                <p class="vh-form-descricao"><?php esc_html_e( 'Aparece no topo da página desta categoria na loja. Se o texto for longo, o visitante vê um trecho e o botão Leia mais. É a descrição nativa da categoria no WordPress.', 'vapor-hub-loja' ); ?></p>
            </div>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Imagem da categoria', 'vapor-hub-loja' ); ?></label>
                <div class="vh-upload-wrapper">
                    <div class="vh-upload-preview" id="vh-cat-img-preview">
                        <?php if ( $vh_dados['imagem_url'] ) : ?>
                            <img src="<?php echo esc_url( $vh_dados['imagem_url'] ); ?>" alt="" />
                        <?php else : ?>
                            <span class="dashicons dashicons-format-image"></span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="imagem_id" id="vh-cat-img" value="<?php echo esc_attr( (string) $vh_dados['imagem_id'] ); ?>" />
                    <button type="button" class="vh-btn vh-btn--primario vh-media-enviar-btn" data-crop="categoria" data-value-type="id" data-target="#vh-cat-img" data-preview="#vh-cat-img-preview">
                        <?php esc_html_e( 'Enviar imagem', 'vapor-hub-loja' ); ?>
                    </button>
                    <button type="button" class="vh-btn vh-btn--secundario vh-media-btn" data-target="#vh-cat-img" data-preview="#vh-cat-img-preview">
                        <?php esc_html_e( 'Biblioteca', 'vapor-hub-loja' ); ?>
                    </button>
                    <button type="button" class="vh-btn vh-btn--ghost vh-media-limpar" data-target="#vh-cat-img" data-preview="#vh-cat-img-preview">
                        <?php esc_html_e( 'Remover', 'vapor-hub-loja' ); ?>
                    </button>
                </div>
            </div>

            <div class="vh-form-acoes">
                <button type="submit" class="vh-btn vh-btn--primario">
                    <?php echo $vh_edicao ? esc_html__( 'Salvar categoria', 'vapor-hub-loja' ) : esc_html__( 'Criar categoria', 'vapor-hub-loja' ); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php
/**
 * View — Menus da vitrine.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$posicao  = VH_Menus_Service::posicao_requisicao();
$posicoes = VH_Menus_Service::posicoes();
?>

<div class="vh-admin-wrap">
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Menus da loja', 'vapor-hub-loja' ); ?></h2>
        <p class="vh-form-descricao">
            <?php esc_html_e( 'Monte cada posição com a quantidade de itens e de níveis que a loja precisar. A vitrine usa o mesmo menu.', 'vapor-hub-loja' ); ?>
        </p>

        <div class="vh-menu-posicoes" role="tablist">
            <?php foreach ( $posicoes as $slug => $def ) : ?>
                <a
                    class="vh-menu-posicao<?php echo $slug === $posicao ? ' is-ativa' : ''; ?>"
                    href="<?php echo esc_url( VH_Router::url( 'menus', [ 'posicao' => $slug ] ) ); ?>"
                    <?php echo $slug === $posicao ? 'aria-current="page"' : ''; ?>
                ><?php echo esc_html( $def['rotulo'] ); ?></a>
            <?php endforeach; ?>
        </div>

        <p class="vh-menu-ajuda" id="vh-menu-ajuda"></p>
        <div id="vh-menu-arvore"></div>

        <form id="vh-menu-novo" class="vh-menu-novo"></form>

        <div class="vh-menu-salvar-linha">
            <p id="vh-menu-contagem" class="vh-menu-contagem"></p>
            <button type="button" class="vh-btn vh-btn--primario" id="vh-menu-salvar">
                <?php esc_html_e( 'Salvar menu', 'vapor-hub-loja' ); ?>
            </button>
        </div>
    </div>
</div>

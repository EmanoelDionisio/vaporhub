<?php
/**
 * Shell do app Minha Loja: cabeçalho, barra lateral e área de conteúdo.
 *
 * Variáveis disponíveis (definidas em VH_App::render):
 *
 * @var string $secao Seção atual.
 * @var string $acao  Ação atual.
 * @var int    $id    ID do recurso atual (0 quando ausente).
 * @var string $view  Caminho absoluto da view a renderizar.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_titulo = VH_Router::titulo( $secao, $acao );
?>
<div class="vh-loja-app">

    <aside class="vh-loja-sidebar" aria-label="<?php esc_attr_e( 'Navegação Minha Loja', 'vapor-hub-loja' ); ?>">
        <?php require __DIR__ . '/partials/nav.php'; ?>
    </aside>

    <div class="vh-loja-main">
        <header class="vh-loja-topbar">
            <div class="vh-loja-topbar-titulo">
                <span class="dashicons <?php echo esc_attr( VH_Router::secoes()[ $secao ]['icone'] ?? 'dashicons-store' ); ?>"></span>
                <h1><?php echo esc_html( $vh_titulo ); ?></h1>
            </div>
            <div class="vh-loja-topbar-acoes">
                <a class="vh-btn vh-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <?php esc_html_e( 'Ver loja', 'vapor-hub-loja' ); ?>
                </a>
            </div>
        </header>

        <div class="vh-loja-conteudo">
            <?php require $view; ?>
        </div>
    </div>

</div>

<div id="vh-toast-stack" class="vh-toast-stack" aria-live="polite" aria-atomic="true"></div>

<div id="vh-modal-confirmacao" class="vh-modal" hidden aria-hidden="true">
    <div class="vh-modal__backdrop" data-vh-modal-fechar tabindex="-1"></div>
    <div class="vh-modal__caixa" role="dialog" aria-modal="true" aria-labelledby="vh-modal-titulo">
        <div class="vh-modal__icone" id="vh-modal-icone" aria-hidden="true">
            <span class="dashicons dashicons-yes-alt"></span>
        </div>
        <h2 class="vh-modal__titulo" id="vh-modal-titulo"></h2>
        <p class="vh-modal__mensagem" id="vh-modal-mensagem"></p>
        <div class="vh-modal__acoes">
            <button type="button" class="vh-btn vh-btn--ghost" id="vh-modal-cancelar"></button>
            <button type="button" class="vh-btn vh-btn--primario" id="vh-modal-confirmar"></button>
        </div>
    </div>
</div>

<?php if ( current_user_can( 'upload_files' ) ) : ?>
    <?php require VH_LOJA_DIR . 'admin/views/partials/modal-crop.php'; ?>
<?php endif; ?>

<?php
/**
 * View — Comunidade (repeater de até 6 entradas).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$comunidade = VH_Settings::obter( 'vh_comunidade' );
?>

<div class="vh-admin-wrap">

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Galeria da Comunidade', 'vapor-hub-loja' ); ?></h2>
        <p style="color:var(--vh-cinza-500);margin-bottom:20px;font-size:13px">
            <?php esc_html_e( 'Adicione até 6 fotos publicadas na galeria. Envios do site chegam em Envios recebidos para aprovação.', 'vapor-hub-loja' ); ?>
        </p>

        <form class="vh-rest-form" id="vh-form-comunidade" data-endpoint="settings/comunidade" data-method="PUT">

            <div id="vh-repeater-container">
                <?php
                $total = max( 1, count( $comunidade ) );
                for ( $i = 0; $i < $total; $i++ ) :
                    $item = $comunidade[ $i ] ?? [ 'imagem_url' => '', 'usuario' => '', 'link' => '' ];
                ?>
                    <div class="vh-repeater-item" data-indice="<?php echo esc_attr( $i ); ?>">
                        <div class="vh-repeater-thumb vh-upload-btn"
                             data-crop="comunidade"
                             data-value-type="url"
                             data-target="#vh-comunidade-img-<?php echo esc_attr( $i ); ?>"
                             data-preview="#vh-comunidade-preview-<?php echo esc_attr( $i ); ?>">
                            <span id="vh-comunidade-preview-<?php echo esc_attr( $i ); ?>">
                                <?php if ( ! empty( $item['imagem_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $item['imagem_url'] ); ?>" alt="" />
                                <?php else : ?>
                                    <span class="dashicons dashicons-camera"></span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <input type="hidden"
                               name="vh_comunidade[<?php echo esc_attr( $i ); ?>][imagem_url]"
                               id="vh-comunidade-img-<?php echo esc_attr( $i ); ?>"
                               value="<?php echo esc_attr( $item['imagem_url'] ); ?>" />

                        <input type="text"
                               name="vh_comunidade[<?php echo esc_attr( $i ); ?>][usuario]"
                               value="<?php echo esc_attr( $item['usuario'] ); ?>"
                               placeholder="<?php esc_attr_e( '@usuario', 'vapor-hub-loja' ); ?>" />

                        <input type="url"
                               name="vh_comunidade[<?php echo esc_attr( $i ); ?>][link]"
                               value="<?php echo esc_attr( $item['link'] ); ?>"
                               placeholder="<?php esc_attr_e( 'https://instagram.com/...', 'vapor-hub-loja' ); ?>" />

                        <button type="button" class="vh-btn vh-btn--perigo vh-repeater-remover"
                                title="<?php esc_attr_e( 'Remover entrada', 'vapor-hub-loja' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                <?php endfor; ?>
            </div>

            <div style="margin-top:16px;display:flex;gap:12px;align-items:center">
                <button type="button" id="vh-repeater-adicionar" class="vh-btn vh-btn--secundario">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Adicionar Membro', 'vapor-hub-loja' ); ?>
                </button>
                <span style="color:var(--vh-cinza-500);font-size:12px" id="vh-repeater-contagem">
                    <?php printf( esc_html__( '%d de 6 entradas', 'vapor-hub-loja' ), $total ); ?>
                </span>
            </div>

            <hr class="vh-separador" />

            <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar Comunidade', 'vapor-hub-loja' ); ?></button>
        </form>
    </div>
</div>

<!-- Template para novo item do repeater -->
<script type="text/html" id="tmpl-vh-repeater-item">
    <div class="vh-repeater-item" data-indice="{{INDEX}}">
        <div class="vh-repeater-thumb vh-upload-btn"
             data-crop="comunidade"
             data-value-type="url"
             data-target="#vh-comunidade-img-{{INDEX}}"
             data-preview="#vh-comunidade-preview-{{INDEX}}">
            <span id="vh-comunidade-preview-{{INDEX}}">
                <span class="dashicons dashicons-camera"></span>
            </span>
        </div>

        <input type="hidden"
               name="vh_comunidade[{{INDEX}}][imagem_url]"
               id="vh-comunidade-img-{{INDEX}}"
               value="" />

        <input type="text"
               name="vh_comunidade[{{INDEX}}][usuario]"
               value=""
               placeholder="@usuario" />

        <input type="url"
               name="vh_comunidade[{{INDEX}}][link]"
               value=""
               placeholder="https://instagram.com/..." />

        <button type="button" class="vh-btn vh-btn--perigo vh-repeater-remover"
                title="Remover entrada">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>
</script>

<?php
/**
 * View — Revenda: configuração do CTA e formulário.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$revenda = VH_Settings::obter_revenda();
?>

<div class="vh-admin-wrap">

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <form class="vh-rest-form" id="vh-form-revenda" data-endpoint="settings/revenda" data-method="PUT">

        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Chamada para Revenda', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:20px">
                <?php esc_html_e( 'Configure a seção de revenda exibida na home. Utilize o shortcode [vh_revenda_cta] para exibir.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-form-grupo">
                <div class="vh-toggle-wrapper">
                    <input type="checkbox" class="vh-toggle" id="vh-revenda-mostrar"
                           name="vh_revenda[mostrar]" value="1"
                           <?php checked( $revenda['mostrar'] ?? '1', '1' ); ?> />
                    <label for="vh-revenda-mostrar" style="font-weight:600;cursor:pointer">
                        <?php esc_html_e( 'Mostrar seção de revenda na home', 'vapor-hub-loja' ); ?>
                    </label>
                </div>
            </div>

            <hr class="vh-separador" />

            <div class="vh-form-grupo">
                <label for="vh-revenda-modo"><?php esc_html_e( 'Comportamento do formulário', 'vapor-hub-loja' ); ?></label>
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Define o que acontece quando o visitante envia o cadastro no popup da loja.', 'vapor-hub-loja' ); ?>
                </p>
                <select id="vh-revenda-modo" name="vh_revenda[modo]">
                    <option value="popup" <?php selected( $revenda['modo'] ?? 'popup', 'popup' ); ?>>
                        <?php esc_html_e( 'Salvar no painel Minha Loja (recomendado)', 'vapor-hub-loja' ); ?>
                    </option>
                    <option value="whatsapp" <?php selected( $revenda['modo'] ?? '', 'whatsapp' ); ?>>
                        <?php esc_html_e( 'Abrir WhatsApp com a mensagem pronta', 'vapor-hub-loja' ); ?>
                    </option>
                </select>
            </div>

            <div class="vh-form-grupo">
                <div class="vh-toggle-wrapper">
                    <input type="checkbox" class="vh-toggle" id="vh-revenda-notificar-email"
                           name="vh_revenda[notificar_email]" value="1"
                           <?php checked( $revenda['notificar_email'] ?? '1', '1' ); ?> />
                    <label for="vh-revenda-notificar-email" style="font-weight:600;cursor:pointer">
                        <?php esc_html_e( 'Enviar cópia por e-mail para a loja (SMTP do WordPress)', 'vapor-hub-loja' ); ?>
                    </label>
                </div>
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Além de salvar no painel, dispara um e-mail para o endereço administrativo do site. Confirmação automática ao cliente será adicionada em versão futura.', 'vapor-hub-loja' ); ?>
                </p>
            </div>

            <div class="vh-revenda-futuro">
                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                <p><?php esc_html_e( 'Em breve: confirmação automática por e-mail ao parceiro e disparo interno via WhatsApp.', 'vapor-hub-loja' ); ?></p>
            </div>

            <div class="vh-form-grupo">
                <label for="vh-revenda-whatsapp"><?php esc_html_e( 'Número WhatsApp', 'vapor-hub-loja' ); ?></label>
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Apenas números com DDD. Ex: 11999998888. Usado no modo WhatsApp.', 'vapor-hub-loja' ); ?>
                </p>
                <input type="tel" id="vh-revenda-whatsapp"
                       name="vh_revenda[whatsapp]"
                       value="<?php echo esc_attr( $revenda['whatsapp'] ?? '' ); ?>"
                       placeholder="11999998888"
                       maxlength="13" />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-revenda-titulo"><?php esc_html_e( 'Título da Seção', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-revenda-titulo"
                       name="vh_revenda[titulo]"
                       value="<?php echo esc_attr( $revenda['titulo'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Quer revender Vapor Hub?', 'vapor-hub-loja' ); ?>" />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-revenda-descricao"><?php esc_html_e( 'Descrição', 'vapor-hub-loja' ); ?></label>
                <textarea id="vh-revenda-descricao"
                          name="vh_revenda[descricao]" rows="3"
                          placeholder="<?php esc_attr_e( 'Entre em contato e faça parte do nosso time de revendedores.', 'vapor-hub-loja' ); ?>"
                ><?php echo esc_textarea( $revenda['descricao'] ?? '' ); ?></textarea>
            </div>

            <div class="vh-form-grupo">
                <label for="vh-revenda-botao"><?php esc_html_e( 'Texto do Botão', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-revenda-botao"
                       name="vh_revenda[botao_texto]"
                       value="<?php echo esc_attr( $revenda['botao_texto'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Quero revender', 'vapor-hub-loja' ); ?>" />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-revenda-url"><?php esc_html_e( 'URL Externa (opcional)', 'vapor-hub-loja' ); ?></label>
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Se preenchida, o botão da seção de revenda abre este endereço em nova aba (em vez do popup).', 'vapor-hub-loja' ); ?>
                </p>
                <input type="url" id="vh-revenda-url"
                       name="vh_revenda[url_externa]"
                       value="<?php echo esc_attr( $revenda['url_externa'] ?? '' ); ?>"
                       placeholder="https://" />
            </div>
        </div>

        <p>
            <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar configuração', 'vapor-hub-loja' ); ?></button>
        </p>
    </form>
</div>

<?php
/**
 * View — Segurança (Turnstile e proteções do portal).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$seguranca = VH_Settings::obter( 'vh_seguranca', VH_Settings::seguranca_padrao() );
?>

<div class="vh-admin-wrap">

    <form id="vh-form-seguranca" class="vh-rest-form vh-admin-section" data-endpoint="settings/seguranca" data-method="PUT">
        <h2><?php esc_html_e( 'Segurança do Portal', 'vapor-hub-loja' ); ?></h2>
        <p style="color:var(--vh-cinza-500);margin-bottom:20px;font-size:13px">
            <?php esc_html_e( 'Proteja o login em /minha-loja/entrar com Cloudflare Turnstile. O mesmo interruptor cobre os formulários públicos da loja: entrar, cadastro, senha, contato, avaliação e finalizar compra. O login do WordPress fica fechado para quem está deslogado. A constante VH_LOGIN_WORDPRESS no wp-config é quem abre.', 'vapor-hub-loja' ); ?>
        </p>

        <label class="vh-switch-linha">
            <input type="hidden" name="turnstile_ativo" value="0" />
            <input type="checkbox" class="vh-toggle" id="vh-turnstile-ativo"
                   name="turnstile_ativo" value="1" <?php checked( $seguranca['turnstile_ativo'] ?? '0', '1' ); ?> />
            <span class="vh-switch-texto">
                <strong><?php esc_html_e( 'Ativar Cloudflare Turnstile no login', 'vapor-hub-loja' ); ?></strong>
                <small><?php esc_html_e( 'Quando desligado, o login funciona normalmente sem captcha.', 'vapor-hub-loja' ); ?></small>
            </span>
        </label>

        <div id="vh-turnstile-campos" class="vh-seguranca-campos">
            <div class="vh-form-grupo">
                <label for="vh-turnstile-site-key"><?php esc_html_e( 'Site Key (pública)', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-turnstile-site-key" name="turnstile_site_key"
                       value="<?php echo esc_attr( $seguranca['turnstile_site_key'] ?? '' ); ?>"
                       placeholder="0x..." autocomplete="off" />
            </div>
            <div class="vh-form-grupo">
                <label for="vh-turnstile-secret-key"><?php esc_html_e( 'Secret Key (servidor)', 'vapor-hub-loja' ); ?></label>
                <input type="password" id="vh-turnstile-secret-key" name="turnstile_secret_key"
                       value="<?php echo esc_attr( $seguranca['turnstile_secret_key'] ?? '' ); ?>"
                       placeholder="0x..." autocomplete="new-password" />
                <p class="description"><?php esc_html_e( 'Obtenha as chaves no painel Cloudflare → Turnstile.', 'vapor-hub-loja' ); ?></p>
            </div>
        </div>

        <p style="margin-top:20px">
            <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar Segurança', 'vapor-hub-loja' ); ?></button>
        </p>
    </form>
</div>

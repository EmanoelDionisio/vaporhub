<?php
/**
 * View — Login do portal Minha Loja.
 *
 * Variáveis: $msg_erro, $redirect_to, $logo, $turnstile_ativo, $turnstile_site
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="vh-login-wrap">
    <div class="vh-login-card">
        <div class="vh-login-brand">
            <?php if ( ! empty( $logo ) ) : ?>
                <img class="vh-login-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
            <?php else : ?>
                <span class="dashicons dashicons-store"></span>
                <span class="vh-login-brand-nome"><?php esc_html_e( 'Minha Loja', 'vapor-hub-loja' ); ?></span>
            <?php endif; ?>
        </div>

        <h1 class="vh-login-titulo"><?php esc_html_e( 'Acesse sua loja', 'vapor-hub-loja' ); ?></h1>
        <p class="vh-login-sub"><?php esc_html_e( 'Painel de gestão exclusivo para o time da loja.', 'vapor-hub-loja' ); ?></p>

        <?php if ( $msg_erro ) : ?>
            <div class="vh-notice vh-notice--erro" role="alert"><?php echo esc_html( $msg_erro ); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vh-login-form">
            <input type="hidden" name="action" value="vh_loja_login" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
            <?php wp_nonce_field( 'vh_loja_login', 'vh_loja_login_nonce' ); ?>

            <div class="vh-form-grupo">
                <label for="vh-log"><?php esc_html_e( 'Usuário ou e-mail', 'vapor-hub-loja' ); ?></label>
                <input type="text" name="log" id="vh-log" autocomplete="username" required />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-pwd"><?php esc_html_e( 'Senha', 'vapor-hub-loja' ); ?></label>
                <input type="password" name="pwd" id="vh-pwd" autocomplete="current-password" required />
            </div>

            <label class="vh-login-lembrar">
                <input type="checkbox" name="rememberme" value="1" />
                <?php esc_html_e( 'Lembrar-me', 'vapor-hub-loja' ); ?>
            </label>

            <?php if ( $turnstile_ativo && $turnstile_site ) : ?>
                <div class="vh-login-turnstile">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site ); ?>" data-theme="light"></div>
                </div>
            <?php endif; ?>

            <button type="submit" class="vh-btn vh-btn--primario vh-login-submit">
                <?php esc_html_e( 'Entrar', 'vapor-hub-loja' ); ?>
            </button>
        </form>

        <p class="vh-login-voltar">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Voltar ao site', 'vapor-hub-loja' ); ?></a>
        </p>
    </div>
</div>

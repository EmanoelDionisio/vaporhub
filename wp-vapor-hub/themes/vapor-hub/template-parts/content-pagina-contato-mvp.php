<?php
/**
 * Página Contato — layout em duas colunas (como o MVP React)
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

$status = isset( $_GET['contato'] ) ? sanitize_text_field( wp_unslash( $_GET['contato'] ) ) : '';
?>

<div class="vh-contato-mvp">
	<div class="vh-contato-mvp-intro vh-text-center">
		<h1 class="vh-contato-mvp-titulo"><?php esc_html_e( 'Fale conosco', 'vapor-hub' ); ?></h1>
		<p class="vh-contato-mvp-lead vh-text-muted">
			<?php esc_html_e( 'Dúvidas sobre pedido, PIX, frete ou produto? Fale com a gente.', 'vapor-hub' ); ?>
		</p>
	</div>

	<?php if ( 'ok' === $status ) : ?>
		<p class="vh-contato-alerta vh-contato-alerta--ok" role="status"><?php esc_html_e( 'Mensagem enviada com sucesso. Em breve retornamos o contato.', 'vapor-hub' ); ?></p>
	<?php elseif ( 'turnstile' === $status ) : ?>
		<p class="vh-contato-alerta vh-contato-alerta--erro" role="alert">
			<?php esc_html_e( 'Conclua a verificação de segurança antes de enviar.', 'vapor-hub' ); ?>
		</p>
	<?php elseif ( 'erro' === $status || 'incompleto' === $status ) : ?>
		<p class="vh-contato-alerta vh-contato-alerta--erro" role="alert">
			<?php esc_html_e( 'Não foi possível enviar agora. Verifique os campos e tente de novo.', 'vapor-hub' ); ?>
		</p>
	<?php endif; ?>

	<div class="vh-contato-mvp-grid">
		<div class="vh-contato-mvp-col-info">
			<?php get_template_part( 'template-parts/content', 'contato' ); ?>
		</div>

		<div class="vh-contato-mvp-col-form vh-card">
			<h2 class="vh-contato-form-titulo"><?php esc_html_e( 'Envie uma mensagem', 'vapor-hub' ); ?></h2>
			<form class="vh-contato-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vh_contato_site">
				<?php wp_nonce_field( 'vh_contato_site', 'vh_contato_nonce' ); ?>

				<div class="vh-form-grupo-mvp">
					<label class="vh-label" for="vh_contato_nome"><?php esc_html_e( 'Nome completo', 'vapor-hub' ); ?></label>
					<input class="vh-input" type="text" id="vh_contato_nome" name="vh_contato_nome" required autocomplete="name">
				</div>

				<div class="vh-contato-form-linha">
					<div class="vh-form-grupo-mvp">
						<label class="vh-label" for="vh_contato_email"><?php esc_html_e( 'E-mail', 'vapor-hub' ); ?></label>
						<input class="vh-input" type="email" id="vh_contato_email" name="vh_contato_email" required autocomplete="email">
					</div>
					<div class="vh-form-grupo-mvp">
						<label class="vh-label" for="vh_contato_fone"><?php esc_html_e( 'Telefone / WhatsApp', 'vapor-hub' ); ?></label>
						<input class="vh-input" type="text" id="vh_contato_fone" name="vh_contato_fone" autocomplete="tel">
					</div>
				</div>

				<div class="vh-form-grupo-mvp">
					<label class="vh-label" for="vh_contato_assunto"><?php esc_html_e( 'Assunto', 'vapor-hub' ); ?></label>
					<input class="vh-input" type="text" id="vh_contato_assunto" name="vh_contato_assunto" required>
				</div>

				<div class="vh-form-grupo-mvp">
					<label class="vh-label" for="vh_contato_mensagem"><?php esc_html_e( 'Sua mensagem', 'vapor-hub' ); ?></label>
					<textarea class="vh-textarea" id="vh_contato_mensagem" name="vh_contato_mensagem" rows="5" required></textarea>
				</div>

				<?php if ( function_exists( 'vh_turnstile_campo' ) ) { vh_turnstile_campo(); } ?>

				<button type="submit" class="vh-btn vh-btn-primary vh-btn-lg vh-contato-enviar">
					<?php esc_html_e( 'Enviar mensagem', 'vapor-hub' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>

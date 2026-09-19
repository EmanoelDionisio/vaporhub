<?php
/**
 * Template Part: Popup de envio de foto — Galeria da Comunidade
 *
 * @package VaporHub
 * @since   1.0.61
 */

defined( 'ABSPATH' ) || exit;

$vh_seguranca    = get_option( 'vh_seguranca', array() );
$vh_turnstile_on = is_array( $vh_seguranca )
	&& ! empty( $vh_seguranca['turnstile_ativo'] )
	&& '1' === (string) $vh_seguranca['turnstile_ativo']
	&& ! empty( $vh_seguranca['turnstile_site_key'] );
?>

<div class="vh-popup-comunidade vh-popup-revenda"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-label="<?php esc_attr_e( 'Enviar foto para a galeria', 'vapor-hub' ); ?>">

	<div class="vh-popup-overlay" aria-hidden="true"></div>

	<div class="vh-popup-card">

		<button type="button"
		        class="vh-fechar-popup vh-popup-fechar-topo"
		        aria-label="<?php esc_attr_e( 'Fechar formulário', 'vapor-hub' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/>
				<line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>

		<div class="vh-popup-form-wrapper">
			<div class="vh-popup-header">
				<h3 class="vh-popup-titulo">
					<?php esc_html_e( 'Envie sua foto', 'vapor-hub' ); ?>
				</h3>
				<p class="vh-popup-subtitulo vh-text-muted vh-text-sm">
					<?php esc_html_e( 'Compartilhe sua captura com a comunidade. Após revisão, ela poderá aparecer na galeria da loja.', 'vapor-hub' ); ?>
				</p>
			</div>

			<form class="vh-popup-formulario vh-comunidade-formulario" enctype="multipart/form-data" novalidate>

				<div class="vh-popup-campo">
					<label for="vh-com-foto" class="vh-label">
						<?php esc_html_e( 'Sua foto', 'vapor-hub' ); ?>
					</label>
					<input type="file"
					       id="vh-com-foto"
					       name="foto"
					       class="vh-input vh-input--arquivo"
					       accept="image/jpeg,image/png,image/webp"
					       required>
					<p class="vh-text-xs vh-text-muted vh-popup-ajuda">
						<?php esc_html_e( 'JPEG, PNG ou WebP — máximo 5 MB. Ajuste o enquadramento antes de enviar.', 'vapor-hub' ); ?>
					</p>
					<div class="vh-comunidade-crop-wrap" hidden>
						<img id="vh-com-crop-img" src="" alt="" />
					</div>
					<div class="vh-comunidade-foto-preview" hidden aria-live="polite"></div>
				</div>

				<div class="vh-popup-campo">
					<label for="vh-com-nome" class="vh-label">
						<?php esc_html_e( 'Seu nome', 'vapor-hub' ); ?>
					</label>
					<input type="text"
					       id="vh-com-nome"
					       name="nome"
					       class="vh-input"
					       placeholder="<?php esc_attr_e( 'Como podemos te chamar', 'vapor-hub' ); ?>"
					       autocomplete="name"
					       required>
				</div>

				<div class="vh-popup-campo">
					<label for="vh-com-instagram" class="vh-label">
						<?php esc_html_e( 'Instagram', 'vapor-hub' ); ?>
					</label>
					<input type="text"
					       id="vh-com-instagram"
					       name="instagram"
					       class="vh-input"
					       placeholder="<?php esc_attr_e( '@seuusuario', 'vapor-hub' ); ?>"
					       autocomplete="off"
					       required>
				</div>

				<div class="vh-popup-campo">
					<label for="vh-com-link" class="vh-label">
						<?php esc_html_e( 'Link do perfil (opcional)', 'vapor-hub' ); ?>
					</label>
					<input type="url"
					       id="vh-com-link"
					       name="link"
					       class="vh-input"
					       placeholder="<?php esc_attr_e( 'https://instagram.com/seuusuario', 'vapor-hub' ); ?>"
					       inputmode="url">
				</div>

				<div class="vh-popup-campo">
					<label for="vh-com-legenda" class="vh-label">
						<?php esc_html_e( 'Legenda (opcional)', 'vapor-hub' ); ?>
					</label>
					<textarea id="vh-com-legenda"
					          name="legenda"
					          class="vh-input vh-textarea"
					          rows="3"
					          maxlength="500"
					          placeholder="<?php esc_attr_e( 'Conte um pouco sobre essa captura…', 'vapor-hub' ); ?>"></textarea>
				</div>

				<?php if ( $vh_turnstile_on ) : ?>
					<div class="vh-popup-turnstile">
						<div class="cf-turnstile"
						     data-sitekey="<?php echo esc_attr( $vh_seguranca['turnstile_site_key'] ); ?>"
						     data-theme="light"></div>
					</div>
				<?php endif; ?>

				<button type="submit" class="vh-btn vh-btn-primary vh-btn-lg vh-popup-btn-enviar">
					<?php esc_html_e( 'Enviar foto', 'vapor-hub' ); ?>
				</button>
			</form>
		</div>

		<div class="vh-popup-sucesso" hidden>
			<div class="vh-popup-sucesso-conteudo">
				<div class="vh-popup-sucesso-icone" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
						<polyline points="22 4 12 14.01 9 11.01"/>
					</svg>
				</div>
				<h3 class="vh-popup-sucesso-titulo">
					<?php esc_html_e( 'Foto enviada!', 'vapor-hub' ); ?>
				</h3>
				<p class="vh-popup-sucesso-texto">
					<?php esc_html_e( 'Recebemos sua foto. Nossa equipe vai revisar e, se aprovada, ela aparecerá na galeria da comunidade.', 'vapor-hub' ); ?>
				</p>
				<button type="button" class="vh-btn vh-btn-primary vh-btn-lg vh-fechar-popup vh-popup-sucesso-fechar">
					<?php esc_html_e( 'Fechar', 'vapor-hub' ); ?>
				</button>
			</div>
		</div>

	</div>
</div>

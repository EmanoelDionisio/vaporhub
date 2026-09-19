<?php
/**
 * Template Part: Popup/Modal de Revenda
 *
 * Formulário de cadastro de revendedor com overlay backdrop-blur.
 * Controlado via JS (classes .vh-popup-*).
 * Hidden por padrão — ativado pela classe .vh-popup-ativo.
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$salvo_revenda = get_option( 'vh_revenda', array() );
if ( ! is_array( $salvo_revenda ) ) {
	$salvo_revenda = array();
}

$revenda = wp_parse_args(
	$salvo_revenda,
	array(
		'whatsapp'    => '',
		'modo'        => 'popup',
		'form_action' => admin_url( 'admin-ajax.php' ),
	)
);

/* whatsapp = monta mensagem e abre wa.me no JS; demais modos = AJAX (e-mail). */
$acao_formulario = ( 'whatsapp' === $revenda['modo'] ) ? 'whatsapp' : 'ajax';
?>

<div class="vh-popup-revenda"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-label="<?php esc_attr_e( 'Cadastro de revendedor', 'vapor-hub' ); ?>">

	<!-- Backdrop com blur -->
	<div class="vh-popup-overlay" aria-hidden="true"></div>

	<div class="vh-popup-card">

		<!-- Botão fechar (formulário) -->
		<button type="button"
		        class="vh-fechar-popup vh-popup-fechar-topo"
		        aria-label="<?php esc_attr_e( 'Fechar formulário', 'vapor-hub' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/>
				<line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>

		<!-- Wrapper do formulário -->
		<div class="vh-popup-form-wrapper">
			<div class="vh-popup-header">
				<h3 class="vh-popup-titulo">
					<?php esc_html_e( 'Cadastro de Revendedor', 'vapor-hub' ); ?>
				</h3>
				<p class="vh-popup-subtitulo vh-text-muted vh-text-sm">
					<?php esc_html_e( 'Preencha seus dados para receber informações sobre nosso programa de revenda.', 'vapor-hub' ); ?>
				</p>
			</div>

			<form class="vh-popup-formulario"
			      action="<?php echo esc_url( $revenda['form_action'] ); ?>"
			      method="post"
			      data-action="<?php echo esc_attr( $acao_formulario ); ?>"
			      data-whatsapp="<?php echo esc_attr( $revenda['whatsapp'] ); ?>"
			      novalidate>

				<?php wp_nonce_field( 'vh_revenda_nonce', '_wpnonce' ); ?>
				<input type="hidden" name="action" value="vh_cadastro_revenda">

				<div class="vh-popup-campo">
					<label for="vh-nome" class="vh-label">
						<?php esc_html_e( 'Nome completo', 'vapor-hub' ); ?>
					</label>
					<input type="text"
					       id="vh-nome"
					       name="vh_nome"
					       class="vh-input"
					       placeholder="<?php esc_attr_e( 'Seu nome completo', 'vapor-hub' ); ?>"
					       autocomplete="name"
					       required>
				</div>

				<div class="vh-popup-campo vh-popup-campo--documento">
					<div class="vh-popup-campo__cabecalho">
						<label for="vh-documento" class="vh-label" id="vh-label-documento">
							<?php esc_html_e( 'CPF', 'vapor-hub' ); ?>
						</label>
						<div class="vh-doc-switch"
						     role="radiogroup"
						     aria-label="<?php esc_attr_e( 'Tipo de documento', 'vapor-hub' ); ?>">
							<button type="button"
							        class="vh-doc-switch__opcao is-ativo"
							        data-tipo="cpf"
							        role="radio"
							        aria-checked="true">
								<?php esc_html_e( 'CPF', 'vapor-hub' ); ?>
							</button>
							<button type="button"
							        class="vh-doc-switch__opcao"
							        data-tipo="cnpj"
							        role="radio"
							        aria-checked="false">
								<?php esc_html_e( 'CNPJ', 'vapor-hub' ); ?>
							</button>
						</div>
					</div>
					<input type="hidden"
					       name="vh_tipo_documento"
					       id="vh-tipo-documento"
					       value="cpf">
					<input type="text"
					       id="vh-documento"
					       name="vh_documento"
					       class="vh-input vh-input--mascara"
					       data-mascara="cpf"
					       placeholder="<?php esc_attr_e( '000.000.000-00', 'vapor-hub' ); ?>"
					       inputmode="numeric"
					       autocomplete="off"
					       maxlength="14"
					       required>
				</div>

				<div class="vh-popup-campo">
					<label for="vh-whatsapp" class="vh-label">
						<?php esc_html_e( 'WhatsApp', 'vapor-hub' ); ?>
					</label>
					<input type="tel"
					       id="vh-whatsapp"
					       name="vh_whatsapp"
					       class="vh-input vh-input--mascara"
					       data-mascara="whatsapp"
					       placeholder="<?php esc_attr_e( '(00) 00000-0000', 'vapor-hub' ); ?>"
					       inputmode="numeric"
					       autocomplete="tel"
					       maxlength="15"
					       required>
				</div>

				<div class="vh-popup-campo">
					<label for="vh-email" class="vh-label">
						<?php esc_html_e( 'E-mail', 'vapor-hub' ); ?>
					</label>
					<input type="email"
					       id="vh-email"
					       name="vh_email"
					       class="vh-input"
					       placeholder="<?php esc_attr_e( 'seu@email.com', 'vapor-hub' ); ?>"
					       autocomplete="email"
					       required>
				</div>

				<?php
				$vh_seguranca   = get_option( 'vh_seguranca', array() );
				$vh_turnstile_on = is_array( $vh_seguranca )
					&& ! empty( $vh_seguranca['turnstile_ativo'] )
					&& '1' === (string) $vh_seguranca['turnstile_ativo']
					&& ! empty( $vh_seguranca['turnstile_site_key'] );
				if ( $vh_turnstile_on && 'whatsapp' !== $acao_formulario ) :
					?>
					<div class="vh-popup-turnstile">
						<div class="cf-turnstile"
						     data-sitekey="<?php echo esc_attr( $vh_seguranca['turnstile_site_key'] ); ?>"
						     data-theme="light"></div>
					</div>
				<?php endif; ?>

				<button type="submit" class="vh-btn vh-btn-primary vh-btn-lg vh-popup-btn-enviar">
					<?php esc_html_e( 'Enviar cadastro', 'vapor-hub' ); ?>
				</button>
			</form>
		</div>

		<!-- Estado de sucesso -->
		<div class="vh-popup-sucesso" hidden>
			<div class="vh-popup-sucesso-conteudo">
				<div class="vh-popup-sucesso-icone" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
						<polyline points="22 4 12 14.01 9 11.01"/>
					</svg>
				</div>
				<h3 class="vh-popup-sucesso-titulo">
					<?php esc_html_e( 'Cadastro enviado!', 'vapor-hub' ); ?>
				</h3>
				<p class="vh-popup-sucesso-texto">
					<?php esc_html_e( 'Recebemos seus dados. Em breve nossa equipe entrará em contato pelo WhatsApp ou e-mail informado.', 'vapor-hub' ); ?>
				</p>
				<button type="button" class="vh-btn vh-btn-primary vh-btn-lg vh-fechar-popup vh-popup-sucesso-fechar">
					<?php esc_html_e( 'Fechar', 'vapor-hub' ); ?>
				</button>
			</div>
		</div>

	</div>
</div>

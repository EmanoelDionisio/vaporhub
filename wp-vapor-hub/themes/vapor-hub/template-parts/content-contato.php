<?php
/**
 * Template Part: Informações de Contato
 *
 * Cards com canais de atendimento para a página de contato.
 * O formulário propriamente dito fica via plugin (Fluent Forms, WPForms ou Contact Form 7).
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="vh-contato-info">

	<h2 class="vh-contato-subtitulo">
		<?php esc_html_e( 'Nossos Canais de Atendimento', 'vapor-hub' ); ?>
	</h2>

	<div class="vh-contato-cards">

		<!-- Telefone e WhatsApp -->
		<div class="vh-card vh-contato-card">
			<div class="vh-contato-icone">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
				</svg>
			</div>
			<h3 class="vh-contato-card-titulo">
				<?php esc_html_e( 'Telefone & WhatsApp', 'vapor-hub' ); ?>
			</h3>
			<p class="vh-contato-card-texto">(11) 99999-9999</p>
			<p class="vh-contato-card-texto">(11) 3333-3333</p>
		</div>

		<!-- E-mail -->
		<div class="vh-card vh-contato-card">
			<div class="vh-contato-icone">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect width="20" height="16" x="2" y="4" rx="2"/>
					<path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
				</svg>
			</div>
			<h3 class="vh-contato-card-titulo">
				<?php esc_html_e( 'E-mail', 'vapor-hub' ); ?>
			</h3>
			<p class="vh-contato-card-texto">contato@piloto.example</p>
			<p class="vh-contato-card-texto">suporte@piloto.example</p>
		</div>

	</div>

	<!-- Localização -->
	<div class="vh-card vh-contato-card vh-contato-card-largo">
		<div class="vh-contato-card-flex">
			<div class="vh-contato-icone">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
					<circle cx="12" cy="10" r="3"/>
				</svg>
			</div>
			<div>
				<h3 class="vh-contato-card-titulo">
					<?php esc_html_e( 'Localização', 'vapor-hub' ); ?>
				</h3>
				<p class="vh-contato-card-texto">
					Av. Paulista, 1000 — Bela Vista<br>
					São Paulo, SP — 01310-100<br>
					Brasil
				</p>
			</div>
		</div>
	</div>

	<!-- Horário de Funcionamento -->
	<div class="vh-card vh-contato-card vh-contato-card-largo">
		<div class="vh-contato-card-flex">
			<div class="vh-contato-icone">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="12" cy="12" r="10"/>
					<polyline points="12 6 12 12 16 14"/>
				</svg>
			</div>
			<div>
				<h3 class="vh-contato-card-titulo">
					<?php esc_html_e( 'Horário de Funcionamento', 'vapor-hub' ); ?>
				</h3>
				<p class="vh-contato-card-texto">Segunda a Sexta: 09h às 18h</p>
				<p class="vh-contato-card-texto">Sábado: 09h às 13h</p>
				<p class="vh-contato-card-texto">Domingos e Feriados: Fechado</p>
			</div>
		</div>
	</div>

</div>

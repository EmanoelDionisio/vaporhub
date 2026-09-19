<?php
/**
 * Página 404 — alinhada ao MVP React (NotFound)
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="vh-page vh-404-mvp" role="main">
	<div class="vh-container vh-404-mvp-inner vh-text-center">

		<div class="vh-404-mvp-icone" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
				<path d="M12 9v4"/><path d="M12 17h.01"/>
			</svg>
		</div>

		<p class="vh-404-mvp-numero" aria-hidden="true">404</p>
		<h1 class="vh-404-mvp-titulo"><?php esc_html_e( 'Oops! Página não encontrada', 'vapor-hub' ); ?></h1>
		<p class="vh-404-mvp-texto vh-text-muted">
			<?php esc_html_e( 'A página que você está tentando acessar não existe ou foi movida. Verifique o endereço digitado ou retorne para nossa loja.', 'vapor-hub' ); ?>
		</p>

		<div class="vh-404-mvp-busca">
			<?php get_search_form(); ?>
		</div>

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vh-btn vh-btn-primary vh-btn-lg vh-404-mvp-btn">
			<?php esc_html_e( 'Voltar para o início', 'vapor-hub' ); ?>
		</a>

		<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
			<p class="vh-404-mvp-loja">
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="vh-text-primary vh-font-bold">
					<?php esc_html_e( 'Ver produtos na loja', 'vapor-hub' ); ?>
				</a>
			</p>
		<?php endif; ?>

	</div>
</main>

<?php
get_footer();

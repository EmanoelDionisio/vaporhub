<?php
/**
 * Página com slug "acessorios" — equivalente ao placeholder do MVP
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="vh-page vh-pagina-simples">
	<div class="vh-container vh-section vh-pagina-simples-inner vh-text-center">
		<h1 class="vh-pagina-simples-titulo"><?php esc_html_e( 'Acessórios', 'vapor-hub' ); ?></h1>
		<p class="vh-pagina-simples-texto vh-text-muted">
			<?php esc_html_e( 'Esta seção está em construção. Enquanto isso, explore a loja completa com todos os equipamentos.', 'vapor-hub' ); ?>
		</p>
		<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
			<a class="vh-btn vh-btn-primary vh-btn-lg" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'Ir para a loja', 'vapor-hub' ); ?>
			</a>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();

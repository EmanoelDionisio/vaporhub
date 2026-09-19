<?php
/**
 * Página com slug "comunidade" — equivalente ao placeholder do MVP
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="vh-page vh-pagina-simples">
	<div class="vh-container vh-section vh-pagina-simples-inner vh-text-center">
		<h1 class="vh-pagina-simples-titulo"><?php esc_html_e( 'Comunidade', 'vapor-hub' ); ?></h1>
		<p class="vh-pagina-simples-texto vh-text-muted">
			<?php esc_html_e( 'Grupo VIP e conteúdo da loja. A galeria da página inicial pode ser editada em Minha Loja → Comunidade.', 'vapor-hub' ); ?>
		</p>
		<a class="vh-btn vh-btn-primary vh-btn-lg" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Voltar ao início', 'vapor-hub' ); ?>
		</a>
	</div>
</main>

<?php
get_footer();

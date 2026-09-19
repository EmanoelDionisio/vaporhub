<?php
/**
 * Front Page - Layout principal do MVP no WordPress
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="vh-home">
	<?php get_template_part( 'template-parts/content', 'hero' ); ?>
	<?php get_template_part( 'template-parts/content', 'beneficios' ); ?>
	<?php get_template_part( 'template-parts/content', 'categorias' ); ?>

	<section class="vh-section vh-home-destaques">
		<div class="vh-container">
			<div class="vh-home-head">
				<div>
					<span class="vh-badge vh-badge-exclusivo"><?php esc_html_e( 'Exclusividade', 'vapor-hub' ); ?></span>
					<h2 class="vh-home-title"><?php esc_html_e( 'Destaques da Marca', 'vapor-hub' ); ?></h2>
				</div>
				<a class="vh-btn vh-btn-ghost" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja' ) ); ?>">
					<?php esc_html_e( 'Ver todos', 'vapor-hub' ); ?>
				</a>
			</div>

			<div class="vh-home-grid">
				<?php
				echo do_shortcode(
					'[products limit="8" columns="4" orderby="date" order="DESC"]'
				);
				?>
			</div>
		</div>
	</section>

	<?php get_template_part( 'template-parts/content', 'revenda-cta' ); ?>
	<?php get_template_part( 'template-parts/content', 'comunidade' ); ?>
</main>

<?php
get_footer();


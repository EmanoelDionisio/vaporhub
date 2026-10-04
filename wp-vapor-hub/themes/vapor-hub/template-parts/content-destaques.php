<?php
/**
 * Fallback da home quando as categorias-vitrine ainda não têm produto.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

$loja_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja' );
?>

<section class="vh-section vh-home-destaques" aria-labelledby="vh-home-destaques-titulo">
	<div class="vh-container">
		<header class="vh-vitrine-cabeca">
			<div class="vh-vitrine-textos">
				<p class="vh-vitrine-kicker"><?php esc_html_e( 'Vitrine', 'vapor-hub' ); ?></p>
				<h2 class="vh-vitrine-titulo" id="vh-home-destaques-titulo">
					<?php esc_html_e( 'Em destaque', 'vapor-hub' ); ?>
				</h2>
				<p class="vh-vitrine-descricao">
					<?php esc_html_e( 'Os lançamentos e os mais recentes da loja. Escolha pelo que você usa.', 'vapor-hub' ); ?>
				</p>
			</div>
			<a class="vh-btn vh-btn-ghost vh-vitrine-cta" href="<?php echo esc_url( $loja_url ); ?>">
				<?php esc_html_e( 'Ver todos', 'vapor-hub' ); ?>
			</a>
		</header>

		<div class="vh-home-grid">
			<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date" order="DESC"]' ); ?>
		</div>
	</div>
</section>

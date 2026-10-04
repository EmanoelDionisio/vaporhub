<?php
/**
 * Trilho da home: cabeçalho editorial + grade Woo da categoria.
 *
 * Espera $args['vitrine'] com slug, kicker, titulo, descricao.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

$vitrine = isset( $args['vitrine'] ) && is_array( $args['vitrine'] ) ? $args['vitrine'] : array();
$slug    = isset( $vitrine['slug'] ) ? sanitize_title( (string) $vitrine['slug'] ) : '';

if ( '' === $slug || ! vh_home_categoria_tem_produtos( $slug ) ) {
	return;
}

$termo = vh_home_termo_vitrine( $slug );
$link  = ( $termo instanceof WP_Term ) ? get_term_link( $termo ) : '';
if ( is_wp_error( $link ) ) {
	$link = '';
}
?>

<section class="vh-section vh-vitrine" aria-labelledby="<?php echo esc_attr( 'vh-vitrine-' . $slug ); ?>">
	<div class="vh-container">
		<header class="vh-vitrine-cabeca">
			<div class="vh-vitrine-textos">
				<?php if ( ! empty( $vitrine['kicker'] ) ) : ?>
					<p class="vh-vitrine-kicker"><?php echo esc_html( (string) $vitrine['kicker'] ); ?></p>
				<?php endif; ?>
				<h2 class="vh-vitrine-titulo" id="<?php echo esc_attr( 'vh-vitrine-' . $slug ); ?>">
					<?php echo esc_html( (string) ( $vitrine['titulo'] ?? '' ) ); ?>
				</h2>
				<?php if ( ! empty( $vitrine['descricao'] ) ) : ?>
					<p class="vh-vitrine-descricao"><?php echo esc_html( (string) $vitrine['descricao'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $link ) : ?>
				<a class="vh-btn vh-btn-ghost vh-vitrine-cta" href="<?php echo esc_url( $link ); ?>">
					<?php esc_html_e( 'Ver categoria', 'vapor-hub' ); ?>
				</a>
			<?php endif; ?>
		</header>

		<div class="vh-home-grid">
			<?php
			echo do_shortcode(
				sprintf(
					'[products limit="8" columns="4" category="%s" orderby="date" order="DESC"]',
					esc_attr( $slug )
				)
			);
			?>
		</div>
	</div>
</section>

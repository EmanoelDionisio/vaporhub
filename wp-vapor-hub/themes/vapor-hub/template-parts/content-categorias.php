<?php
/**
 * Template Part: Grid de Categorias de Produtos
 *
 * Exibe as categorias WooCommerce com thumbnail em grid responsivo.
 * Grid: 2 cols mobile, 3 tablet, 6 desktop.
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$args = array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
	'orderby'    => 'count',
	'order'      => 'DESC',
	'number'     => 8,
);

$categorias = get_terms( $args );
if ( ! is_wp_error( $categorias ) && ! empty( $categorias ) ) {
	$padrao = (int) get_option( 'default_product_cat', 0 );
	if ( $padrao > 0 ) {
		$sem_padrao = array_values(
			array_filter(
				$categorias,
				static function ( $cat ) use ( $padrao ) {
					return (int) $cat->term_id !== $padrao;
				}
			)
		);
		if ( ! empty( $sem_padrao ) ) {
			$categorias = array_slice( $sem_padrao, 0, 6 );
		}
	} else {
		$categorias = array_slice( $categorias, 0, 6 );
	}
}

if ( is_wp_error( $categorias ) || empty( $categorias ) ) :
?>
	<section class="vh-categorias vh-section">
		<div class="vh-container vh-text-center">
			<p class="vh-text-muted">
				<?php esc_html_e( 'Nenhuma categoria disponível no momento. Em breve teremos novidades!', 'vapor-hub' ); ?>
			</p>
		</div>
	</section>
<?php
	return;
endif;
?>

<section class="vh-categorias vh-section vh-animar vh-slide-up" aria-label="<?php esc_attr_e( 'Categorias de produtos', 'vapor-hub' ); ?>">
	<div class="vh-container">
		<div class="vh-categorias-header vh-text-center">
			<span class="vh-categorias-eyebrow"><?php esc_html_e( 'Navegue por categoria', 'vapor-hub' ); ?></span>
			<h2 class="vh-categorias-titulo">
				<?php esc_html_e( 'O que você procura', 'vapor-hub' ); ?>
			</h2>
			<p class="vh-text-muted">
				<?php esc_html_e( 'Pods, e-líquidos, nicotina oral e acessórios — escolha pelo que você usa.', 'vapor-hub' ); ?>
			</p>
		</div>

		<div class="vh-categorias-grid">
			<?php foreach ( $categorias as $cat ) :
				$thumb_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
				$imagem   = $thumb_id
					? wp_get_attachment_image_url( $thumb_id, 'vh-categoria' )
					: wc_placeholder_img_src( 'vh-categoria' );
				$alt      = $thumb_id
					? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true )
					: $cat->name;
				$link     = get_term_link( $cat );

				if ( is_wp_error( $link ) ) {
					continue;
				}
			?>
				<a href="<?php echo esc_url( $link ); ?>"
				   class="vh-categorias-card"
				   title="<?php echo esc_attr( $cat->name ); ?>">

					<div class="vh-categorias-card-imagem">
						<?php
						if ( $thumb_id ) {
							echo wp_get_attachment_image(
								(int) $thumb_id,
								'vh-categoria',
								false,
								array(
									'alt'      => $alt,
									'loading'  => 'lazy',
									'decoding' => 'async',
									'sizes'    => '(max-width: 480px) 50vw, (max-width: 781px) 33vw, 160px',
								)
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							?>
							<img src="<?php echo esc_url( $imagem ); ?>"
							     alt="<?php echo esc_attr( $alt ); ?>"
							     loading="lazy"
							     decoding="async"
							     width="320"
							     height="400">
							<?php
						}
						?>
					</div>

					<div class="vh-categorias-card-overlay">
						<span class="vh-categorias-card-nome">
							<?php echo esc_html( $cat->name ); ?>
						</span>
						<span class="vh-categorias-card-qtd vh-text-xs">
							<?php
							printf(
								esc_html( _n( '%d produto', '%d produtos', $cat->count, 'vapor-hub' ) ),
								(int) $cat->count
							);
							?>
						</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

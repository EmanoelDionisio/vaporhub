<?php
/**
 * Fileira de produtos da home. O card é o mesmo da loja.
 *
 * @package VaporHub
 *
 * @var int[]  $ids
 * @var string $rotulo
 * @var string $titulo
 * @var string $ancora
 */

defined( 'ABSPATH' ) || exit;

$ids    = isset( $args['ids'] ) && is_array( $args['ids'] ) ? $args['ids'] : array();
$rotulo = isset( $args['rotulo'] ) ? (string) $args['rotulo'] : '';
$titulo = isset( $args['titulo'] ) ? (string) $args['titulo'] : '';
$ancora = isset( $args['ancora'] ) ? sanitize_html_class( (string) $args['ancora'] ) : 'vh-home-fileira';

if ( ! $ids || '' === $titulo ) {
	return;
}
?>

<section class="vh-section vh-home-fileira" aria-labelledby="<?php echo esc_attr( $ancora ); ?>">
	<div class="vh-container">
		<header class="vh-home-fileira-cabeca">
			<?php if ( '' !== $rotulo ) : ?>
				<p class="vh-vitrine-kicker"><?php echo esc_html( $rotulo ); ?></p>
			<?php endif; ?>
			<h2 class="vh-vitrine-titulo" id="<?php echo esc_attr( $ancora ); ?>">
				<?php echo esc_html( $titulo ); ?>
			</h2>
		</header>

		<div class="vh-home-fileira-trilho">
			<?php
			if ( function_exists( 'woocommerce_product_loop_start' ) ) {
				$GLOBALS['woocommerce_loop']['columns'] = 4;
				woocommerce_product_loop_start();
				foreach ( $ids as $produto_id ) {
					$post_object = get_post( (int) $produto_id );
					if ( ! $post_object ) {
						continue;
					}
					$GLOBALS['post'] = $post_object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $GLOBALS['post'] );
					wc_get_template_part( 'content', 'product' );
				}
				wp_reset_postdata();
				woocommerce_product_loop_end();
			}
			?>
		</div>
	</div>
</section>

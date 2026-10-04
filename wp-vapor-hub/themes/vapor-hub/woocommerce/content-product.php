<?php
/**
 * WooCommerce — Card de Produto (Loop)
 *
 * Override de: woocommerce/templates/content-product.php
 *
 * Estrutura: imagem (aspect 4/5) com badges + hover overlay,
 * título (truncado 2 linhas), preço, botão comprar.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

$produto_id = $product->get_id();
$link       = get_permalink( $produto_id );
$titulo     = get_the_title( $produto_id );
$imagem     = $product->get_image( 'vh-produto-card', array(
	'class'    => 'vh-produto-card-img',
	'loading'  => 'lazy',
	'decoding' => 'async',
	'sizes'    => '(max-width: 480px) 50vw, (max-width: 781px) 33vw, 25vw',
) );

/* --- Verificar badges --- */
$em_promocao  = $product->is_on_sale();
$eh_exclusivo = false;
$eh_novo      = false;

if ( taxonomy_exists( 'product_brand' ) ) {
	$marcas = get_the_terms( $produto_id, 'product_brand' );
	if ( ! is_wp_error( $marcas ) && ! empty( $marcas ) ) {
		foreach ( $marcas as $marca ) {
			if ( mb_strtolower( $marca->name ) === 'vapor hub' ) {
				$eh_exclusivo = true;
				break;
			}
		}
	}
}

if ( ! $eh_exclusivo ) {
	$eh_exclusivo = get_post_meta( $produto_id, '_vh_exclusivo', true ) === 'sim';
}

$data_publicacao = get_the_date( 'U', $produto_id );
$dias_desde      = ( time() - (int) $data_publicacao ) / DAY_IN_SECONDS;
$eh_novo         = $dias_desde <= 30;
?>

<li <?php wc_product_class( 'vh-produto-card', $product ); ?>>
	<a href="<?php echo esc_url( $link ); ?>"
	   class="vh-produto-card-link"
	   aria-label="<?php printf( esc_attr__( 'Ver detalhes de %s', 'vapor-hub' ), esc_attr( $titulo ) ); ?>">

		<!-- Imagem com aspect-ratio 4/5 -->
		<div class="vh-produto-card-imagem">
			<?php echo $imagem; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<!-- Badges -->
			<div class="vh-produto-card-badges">
				<?php if ( $eh_exclusivo ) : ?>
					<span class="vh-badge vh-badge-exclusivo">
						<?php esc_html_e( 'Exclusivo', 'vapor-hub' ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $em_promocao ) : ?>
					<span class="vh-badge vh-badge-promo">
						<?php esc_html_e( 'Promoção', 'vapor-hub' ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $eh_novo && ! $em_promocao ) : ?>
					<span class="vh-badge vh-badge-novo">
						<?php esc_html_e( 'Novo', 'vapor-hub' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<!-- Overlay hover com zoom -->
			<div class="vh-produto-card-hover">
				<span class="vh-produto-card-hover-texto">
					<?php esc_html_e( 'Ver detalhes', 'vapor-hub' ); ?>
				</span>
			</div>
		</div>

		<!-- Conteúdo -->
		<div class="vh-produto-card-conteudo">
			<h2 class="vh-produto-card-titulo">
				<?php echo esc_html( $titulo ); ?>
			</h2>

			<div class="vh-produto-card-preco">
				<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</a>

	<!-- Botão comprar / adicionar ao carrinho -->
	<div class="vh-produto-card-acao">
		<?php
		woocommerce_template_loop_add_to_cart( array(
			'class' => implode( ' ', array_filter( array(
				'vh-btn',
				'vh-btn-primary',
				'vh-produto-card-btn',
				'product_type_' . $product->get_type(),
				$product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
				$product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
			) ) ),
		) );
		?>
	</div>
</li>

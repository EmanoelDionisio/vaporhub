<?php
/**
 * Formulário de busca — estilo pill do MVP (404 e cabeçalho)
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

$acao = home_url( '/' );
if ( class_exists( 'WooCommerce' ) && ( is_shop() || is_product_taxonomy() || is_front_page() ) ) {
	$acao = home_url( '/' );
}
?>
<form role="search" method="get" class="vh-busca-pill" action="<?php echo esc_url( $acao ); ?>">
	<?php if ( class_exists( 'WooCommerce' ) ) : ?>
		<input type="hidden" name="post_type" value="product">
	<?php endif; ?>
	<label class="vh-busca-pill-label">
		<span class="vh-sr-only"><?php esc_html_e( 'Buscar', 'vapor-hub' ); ?></span>
		<svg class="vh-busca-pill-svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
		<input type="search" name="s" class="vh-busca-pill-input" placeholder="<?php esc_attr_e( 'O que você está procurando?', 'vapor-hub' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
	</label>
</form>

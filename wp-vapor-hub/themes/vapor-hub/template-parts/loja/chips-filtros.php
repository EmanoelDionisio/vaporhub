<?php
/**
 * Chips de filtros ativos da loja.
 *
 * @package VaporHub
 *
 * @var array $filtros
 */

defined( 'ABSPATH' ) || exit;

$filtros = isset( $args['filtros'] ) && is_array( $args['filtros'] ) ? $args['filtros'] : array();

if ( empty( $filtros['tem_filtros'] ) ) {
	return;
}

$preco_teto      = (int) $filtros['preco_teto'];
$preco_min_atual = (float) $filtros['preco_min'];
$preco_max_atual = (float) $filtros['preco_max'];
?>

<div class="vh-loja-filtros-ativos" id="vh-loja-filtros-ativos" aria-live="polite">
	<span class="vh-loja-filtros-ativos-label"><?php esc_html_e( 'Filtros ativos:', 'vapor-hub' ); ?></span>
	<?php if ( $preco_min_atual > 0 || $preco_max_atual < $preco_teto ) : ?>
		<span class="vh-loja-filtro-chip">
			<?php
			printf(
				/* translators: 1: preço mínimo, 2: preço máximo */
				esc_html__( 'Preço: %1$s – %2$s', 'vapor-hub' ),
				wp_kses_post( wc_price( $preco_min_atual ) ),
				wp_kses_post( wc_price( $preco_max_atual ) )
			);
			?>
		</span>
	<?php endif; ?>
	<?php if ( ! empty( $filtros['promocao'] ) ) : ?>
		<span class="vh-loja-filtro-chip"><?php esc_html_e( 'Em promoção', 'vapor-hub' ); ?></span>
	<?php endif; ?>
	<?php if ( ! empty( $filtros['novidades'] ) ) : ?>
		<span class="vh-loja-filtro-chip"><?php esc_html_e( 'Novidades', 'vapor-hub' ); ?></span>
	<?php endif; ?>
	<?php if ( ! empty( $filtros['exclusivos'] ) ) : ?>
		<span class="vh-loja-filtro-chip"><?php esc_html_e( 'Exclusivos', 'vapor-hub' ); ?></span>
	<?php endif; ?>
	<?php if ( ! empty( $filtros['estoque'] ) ) : ?>
		<span class="vh-loja-filtro-chip"><?php esc_html_e( 'Disponível agora', 'vapor-hub' ); ?></span>
	<?php endif; ?>
	<?php if ( ! empty( $filtros['variavel'] ) ) : ?>
		<span class="vh-loja-filtro-chip"><?php esc_html_e( 'Com opções', 'vapor-hub' ); ?></span>
	<?php endif; ?>
	<button type="button" class="vh-loja-filtros-limpar" data-vh-loja-limpar>
		<?php esc_html_e( 'Limpar', 'vapor-hub' ); ?>
	</button>
</div>

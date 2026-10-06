<?php
/**
 * WooCommerce — Página Individual do Produto
 *
 * Override de: woocommerce/templates/content-single-product.php
 *
 * Layout: Grid 7/5 colunas (galeria | info) em desktop, empilhado em mobile.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) ) {
	return;
}

/**
 * Hook: woocommerce_before_single_product
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$produto_id = $product->get_id();

/* --- Dados auxiliares --- */
$categorias = get_the_terms( $produto_id, 'product_cat' );
$marca      = '';
if ( taxonomy_exists( 'product_brand' ) ) {
	$marcas = get_the_terms( $produto_id, 'product_brand' );
	if ( ! is_wp_error( $marcas ) && ! empty( $marcas ) ) {
		$marca = $marcas[0]->name;
	}
}

$desconto_percentual = 0;
if ( $product->is_on_sale() ) {
	$preco_regular = (float) $product->get_regular_price();
	$preco_venda   = (float) $product->get_sale_price();
	if ( $preco_regular > 0 ) {
		$desconto_percentual = round( ( ( $preco_regular - $preco_venda ) / $preco_regular ) * 100 );
	}
}
?>

<div id="product-<?php echo esc_attr( $produto_id ); ?>" <?php wc_product_class( 'vh-produto-single', $product ); ?>>

	<!-- Breadcrumb -->
	<div class="vh-produto-breadcrumb">
		<?php woocommerce_breadcrumb(); ?>
	</div>

	<!-- Grid principal: Galeria (7 cols) | Informações (5 cols) -->
	<div class="vh-produto-grid">

		<!-- Coluna da galeria -->
		<div class="vh-produto-galeria">
			<div class="vh-produto-galeria-media">
				<?php
				/**
				 * Hook: woocommerce_before_single_product_summary
				 *
				 * @hooked woocommerce_show_product_sale_flash - 10
				 * @hooked woocommerce_show_product_images - 20
				 */
				do_action( 'woocommerce_before_single_product_summary' );
				?>
				<?php
				$vh_badges_galeria = vh_badges_galeria_produto_html( $product );
				if ( $vh_badges_galeria ) :
					?>
				<div class="vh-produto-galeria-badges" aria-hidden="true">
					<?php echo $vh_badges_galeria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Coluna de informações -->
		<div class="vh-produto-info">

			<!-- Linha superior MVP: série / categoria + avaliação -->
			<div class="vh-produto-linha-eyebrow">
				<div class="vh-produto-eyebrow-esq">
					<?php if ( ! empty( $marca ) ) : ?>
						<span class="vh-produto-marca"><?php echo esc_html( $marca ); ?></span>
					<?php elseif ( ! is_wp_error( $categorias ) && ! empty( $categorias ) ) : ?>
						<a href="<?php echo esc_url( get_term_link( $categorias[0] ) ); ?>" class="vh-produto-categoria-link">
							<?php echo esc_html( $categorias[0]->name ); ?>
						</a>
					<?php endif; ?>
				</div>
				<div class="vh-produto-eyebrow-dir">
					<?php echo wc_get_rating_html( $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="vh-produto-avaliacoes-qtd vh-text-sm vh-text-muted">
						<?php
						$n_aval = (int) $product->get_review_count();
						printf(
							/* translators: %d: número de avaliações */
							esc_html( _n( '(%d avaliação)', '(%d avaliações)', $n_aval, 'vapor-hub' ) ),
							$n_aval
						);
						?>
					</span>
				</div>
			</div>

			<!-- Nome do produto -->
			<h1 class="vh-produto-titulo">
				<?php the_title(); ?>
			</h1>

			<!-- Preço com badge de desconto -->
			<div class="vh-produto-preco-wrapper">
				<div class="vh-produto-preco">
					<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php if ( $desconto_percentual > 0 ) : ?>
					<span class="vh-badge vh-badge-promo">
						-<?php echo esc_html( $desconto_percentual ); ?>%
					</span>
				<?php endif; ?>
			</div>

			<!-- Descrição curta -->
			<?php if ( $product->get_short_description() ) : ?>
				<div class="vh-produto-descricao-curta">
					<?php echo wp_kses_post( $product->get_short_description() ); ?>
				</div>
			<?php endif; ?>

			<!-- Formulário de variações / Add to cart -->
			<div class="vh-produto-form-cart">
				<?php
				if ( $product->is_type( 'variable' ) ) :
					$vh_config = vh_config_personalizacao( $product );
					if ( ! empty( $vh_config ) ) :
						?>
				<div class="vh-configurador" data-vh-configurador>
					<h3 class="vh-configurador-titulo">
						<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>
						</svg>
						<?php esc_html_e( 'Opções', 'vapor-hub' ); ?>
					</h3>

					<?php foreach ( $vh_config as $vh_indice => $vh_attr ) : ?>
						<div class="vh-cfg-passo vh-cfg-passo--<?php echo esc_attr( $vh_attr['tipo'] ); ?>" data-tax="<?php echo esc_attr( $vh_attr['taxonomy'] ); ?>">
							<div class="vh-cfg-passo-topo">
								<span class="vh-cfg-passo-titulo">
									<?php echo esc_html( ( $vh_indice + 1 ) . '. ' . $vh_attr['label'] ); ?>
								</span>
								<span class="vh-cfg-selecionado" data-tax="<?php echo esc_attr( $vh_attr['taxonomy'] ); ?>"></span>
							</div>
							<div class="vh-cfg-opcoes vh-cfg-opcoes--<?php echo esc_attr( $vh_attr['tipo'] ); ?>">
								<?php foreach ( $vh_attr['termos'] as $vh_termo ) : ?>
									<?php if ( 'cor' === $vh_attr['tipo'] ) : ?>
										<button type="button" class="vh-cfg-opcao vh-cfg-swatch"
											data-tax="<?php echo esc_attr( $vh_attr['taxonomy'] ); ?>"
											data-valor="<?php echo esc_attr( $vh_termo['slug'] ); ?>"
											data-nome="<?php echo esc_attr( $vh_termo['nome'] ); ?>"
											style="--vh-swatch: <?php echo esc_attr( $vh_termo['cor'] ); ?>;"
											aria-pressed="false"
											aria-label="<?php echo esc_attr( $vh_termo['nome'] ); ?>"
											title="<?php echo esc_attr( $vh_termo['nome'] ); ?>"></button>
									<?php elseif ( 'imagem' === $vh_attr['tipo'] ) : ?>
										<button type="button" class="vh-cfg-opcao vh-cfg-card vh-cfg-card--imagem"
											data-tax="<?php echo esc_attr( $vh_attr['taxonomy'] ); ?>"
											data-valor="<?php echo esc_attr( $vh_termo['slug'] ); ?>"
											data-nome="<?php echo esc_attr( $vh_termo['nome'] ); ?>"
											aria-pressed="false">
											<?php if ( ! empty( $vh_termo['imagem_url'] ) ) : ?>
												<img class="vh-cfg-card-img" src="<?php echo esc_url( $vh_termo['imagem_url'] ); ?>" alt="<?php echo esc_attr( $vh_termo['nome'] ); ?>" loading="lazy" decoding="async" />
											<?php else : ?>
												<span class="vh-cfg-forma vh-cfg-forma--padrao" aria-hidden="true"></span>
											<?php endif; ?>
											<span class="vh-cfg-card-nome"><?php echo esc_html( $vh_termo['nome'] ); ?></span>
										</button>
									<?php elseif ( 'modelo' === $vh_attr['tipo'] ) : ?>
										<button type="button" class="vh-cfg-opcao vh-cfg-card"
											data-tax="<?php echo esc_attr( $vh_attr['taxonomy'] ); ?>"
											data-valor="<?php echo esc_attr( $vh_termo['slug'] ); ?>"
											data-nome="<?php echo esc_attr( $vh_termo['nome'] ); ?>"
											aria-pressed="false">
											<span class="vh-cfg-forma vh-cfg-forma--padrao" aria-hidden="true"></span>
											<span class="vh-cfg-card-nome"><?php echo esc_html( $vh_termo['nome'] ); ?></span>
										</button>
									<?php else : ?>
										<button type="button" class="vh-cfg-opcao vh-cfg-pill"
											data-tax="<?php echo esc_attr( $vh_attr['taxonomy'] ); ?>"
											data-valor="<?php echo esc_attr( $vh_termo['slug'] ); ?>"
											data-nome="<?php echo esc_attr( $vh_termo['nome'] ); ?>"
											aria-pressed="false">
											<?php echo esc_html( $vh_termo['nome'] ); ?>
										</button>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="vh-cfg-resumo" aria-live="polite">
						<span class="vh-cfg-resumo-icone" aria-hidden="true">
							<svg class="vh-cfg-icone-pendente" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
							<svg class="vh-cfg-icone-pronto" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
						</span>
						<span class="vh-cfg-resumo-corpo">
							<span class="vh-cfg-resumo-titulo"><?php esc_html_e( 'Sua escolha', 'vapor-hub' ); ?></span>
							<span class="vh-cfg-resumo-texto"
								data-pendente="<?php esc_attr_e( 'Selecione todas as opções para continuar', 'vapor-hub' ); ?>"
								data-pronto="<?php esc_attr_e( 'Tudo certo! Você já pode adicionar ao carrinho.', 'vapor-hub' ); ?>"><?php esc_html_e( 'Selecione todas as opções para continuar', 'vapor-hub' ); ?></span>
						</span>
						<span class="vh-cfg-status"
							data-pendente="<?php esc_attr_e( 'Pendente', 'vapor-hub' ); ?>"
							data-pronto="<?php esc_attr_e( 'Pronto!', 'vapor-hub' ); ?>"><?php esc_html_e( 'Pendente', 'vapor-hub' ); ?></span>
					</div>
				</div>
						<?php
					endif;
				endif;

				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
				do_action( 'woocommerce_single_product_summary' );
				?>
			</div>

			<!-- Calculadora de frete -->
			<?php if ( $product->needs_shipping() ) : ?>
				<div class="vh-produto-frete">
					<h4 class="vh-produto-frete-titulo">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
						</svg>
						<?php esc_html_e( 'Calcular frete', 'vapor-hub' ); ?>
					</h4>
					<div class="vh-produto-frete-form">
						<input type="text"
						       class="vh-input vh-input-cep"
						       placeholder="<?php esc_attr_e( 'Digite seu CEP', 'vapor-hub' ); ?>"
						       maxlength="9"
						       inputmode="numeric"
						       aria-label="<?php esc_attr_e( 'CEP para cálculo de frete', 'vapor-hub' ); ?>">
						<button type="button" class="vh-btn vh-btn-secondary vh-btn-calcular-frete">
							<?php esc_html_e( 'Calcular', 'vapor-hub' ); ?>
						</button>
					</div>
					<div class="vh-produto-frete-resultado"></div>
				</div>
			<?php endif; ?>

		</div>

	</div>

	<!-- Tabs: Descrição, Avaliações, etc. -->
	<div class="vh-produto-tabs">
		<?php
		/**
		 * Hook: woocommerce_after_single_product_summary
		 *
		 * @hooked woocommerce_output_product_data_tabs - 10
		 * @hooked woocommerce_upsell_display           - 15
		 * @hooked woocommerce_output_related_products   - 20
		 */
		do_action( 'woocommerce_after_single_product_summary' );
		?>
	</div>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

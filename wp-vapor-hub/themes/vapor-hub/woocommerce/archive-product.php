<?php
/**
 * Arquivo da loja — layout estilo MVP (barra lateral + grade)
 *
 * Override de: woocommerce/templates/archive-product.php
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$categorias = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => 0,
		'orderby'    => 'name',
		'order'      => 'ASC',
		'number'     => 20,
	)
);
if ( is_wp_error( $categorias ) ) {
	$categorias = array();
}

$loja_url        = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
$filtros         = class_exists( 'VH_Loja_Filtros' ) ? VH_Loja_Filtros::estado() : array(
	'exclusivos'  => false,
	'promocao'    => false,
	'novidades'   => false,
	'estoque'     => false,
	'variavel'    => false,
	'preco_min'   => 0,
	'preco_max'   => 300,
	'preco_teto'  => 300,
	'tem_filtros' => false,
);
$filtros_action  = class_exists( 'VH_Loja_Filtros' ) ? VH_Loja_Filtros::url_base() : $loja_url;
$filtros_limpar  = class_exists( 'VH_Loja_Filtros' ) ? VH_Loja_Filtros::url_com_filtros( $filtros_action, array( 'limpar' => true ) ) : $loja_url;
$preco_teto      = (int) $filtros['preco_teto'];
$preco_min_atual = (float) $filtros['preco_min'];
$preco_max_atual = (float) $filtros['preco_max'];
$categoria_slug  = ! empty( $filtros['categoria'] ) ? $filtros['categoria'] : '';
?>

<main id="site-content" class="vh-page vh-page-loja">

	<?php if ( function_exists( 'woocommerce_output_all_notices' ) ) : ?>
		<div class="vh-container vh-loja-notices">
			<?php woocommerce_output_all_notices(); ?>
		</div>
	<?php endif; ?>

	<div class="vh-container vh-loja-container">

		<?php
		$trail_final = __( 'Todos os produtos', 'vapor-hub' );
		if ( is_product_taxonomy() ) {
			$trail_final = single_term_title( '', false );
		}
		?>
		<nav class="vh-breadcrumb-mvp vh-text-sm" aria-label="<?php esc_attr_e( 'Trilha de navegação', 'vapor-hub' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Início', 'vapor-hub' ); ?></a>
			<span class="vh-breadcrumb-sep">/</span>
			<a href="<?php echo esc_url( $loja_url ); ?>"><?php esc_html_e( 'Loja', 'vapor-hub' ); ?></a>
			<span class="vh-breadcrumb-sep">/</span>
			<span class="vh-breadcrumb-atual"><?php echo esc_html( $trail_final ); ?></span>
		</nav>

		<div class="vh-loja-topo">
			<h1 class="vh-loja-titulo"><?php woocommerce_page_title(); ?></h1>
			<div class="vh-loja-controles">
				<div class="vh-loja-ordenacao woocommerce">
					<?php
					if ( function_exists( 'woocommerce_catalog_ordering' ) ) {
						woocommerce_catalog_ordering();
					}
					?>
				</div>
				<button type="button" class="vh-loja-filtros-toggle vh-btn vh-btn-secondary vh-loja-filtros-toggle-mobile" aria-expanded="false" aria-controls="vh-loja-sidebar">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
					<?php esc_html_e( 'Filtros', 'vapor-hub' ); ?>
				</button>
			</div>
		</div>

		<?php if ( is_product_taxonomy() ) : ?>
			<div class="vh-loja-descricao-termo vh-text-muted">
				<?php do_action( 'woocommerce_archive_description' ); ?>
			</div>
		<?php endif; ?>

		<div id="vh-loja-filtros-ativos-wrap">
			<?php VH_Loja_Filtros::renderizar_chips(); ?>
		</div>

		<div class="vh-loja-grid-mvp">

			<aside class="vh-loja-sidebar" id="vh-loja-sidebar" aria-label="<?php esc_attr_e( 'Filtros da loja', 'vapor-hub' ); ?>">
				<div
					class="vh-loja-filtros-form"
					data-vh-loja-filtros
					data-categoria="<?php echo esc_attr( $categoria_slug ); ?>"
					data-base-url="<?php echo esc_url( $filtros_action ); ?>"
				>
					<div class="vh-loja-filtro-bloco">
						<details class="vh-loja-details" open>
							<summary class="vh-loja-details-sumario"><?php esc_html_e( 'Faixa de preço', 'vapor-hub' ); ?></summary>
							<div class="vh-loja-preco-range" data-vh-preco-teto="<?php echo esc_attr( (string) $preco_teto ); ?>">
								<div class="vh-loja-preco-sliders">
									<label class="screen-reader-text" for="vh-loja-preco-min-range"><?php esc_html_e( 'Preço mínimo', 'vapor-hub' ); ?></label>
									<input
										type="range"
										class="vh-loja-range vh-loja-range-min"
										id="vh-loja-preco-min-range"
										min="0"
										max="<?php echo esc_attr( (string) $preco_teto ); ?>"
										step="1"
										value="<?php echo esc_attr( (string) (int) $preco_min_atual ); ?>"
										aria-valuemin="0"
										aria-valuemax="<?php echo esc_attr( (string) $preco_teto ); ?>"
										aria-valuenow="<?php echo esc_attr( (string) (int) $preco_min_atual ); ?>"
									>
									<label class="screen-reader-text" for="vh-loja-preco-max-range"><?php esc_html_e( 'Preço máximo', 'vapor-hub' ); ?></label>
									<input
										type="range"
										class="vh-loja-range vh-loja-range-max"
										id="vh-loja-preco-max-range"
										min="0"
										max="<?php echo esc_attr( (string) $preco_teto ); ?>"
										step="1"
										value="<?php echo esc_attr( (string) (int) $preco_max_atual ); ?>"
										aria-valuemin="0"
										aria-valuemax="<?php echo esc_attr( (string) $preco_teto ); ?>"
										aria-valuenow="<?php echo esc_attr( (string) (int) $preco_max_atual ); ?>"
									>
								</div>
								<div class="vh-loja-preco-labels">
									<span class="vh-loja-preco-min-label"><?php echo wp_kses_post( wc_price( $preco_min_atual ) ); ?></span>
									<span class="vh-loja-preco-max-label"><?php echo wp_kses_post( wc_price( $preco_max_atual ) ); ?></span>
								</div>
								<div class="vh-loja-preco-inputs">
									<label class="vh-loja-preco-field">
										<span><?php esc_html_e( 'Mín.', 'vapor-hub' ); ?></span>
										<input
											type="number"
											class="vh-input vh-loja-preco-min-input"
											min="0"
											max="<?php echo esc_attr( (string) $preco_teto ); ?>"
											step="1"
											value="<?php echo esc_attr( (string) (int) $preco_min_atual ); ?>"
											inputmode="decimal"
											aria-label="<?php esc_attr_e( 'Preço mínimo', 'vapor-hub' ); ?>"
										>
									</label>
									<label class="vh-loja-preco-field">
										<span><?php esc_html_e( 'Máx.', 'vapor-hub' ); ?></span>
										<input
											type="number"
											class="vh-input vh-loja-preco-max-input"
											min="0"
											max="<?php echo esc_attr( (string) $preco_teto ); ?>"
											step="1"
											value="<?php echo esc_attr( (string) (int) $preco_max_atual ); ?>"
											inputmode="decimal"
											aria-label="<?php esc_attr_e( 'Preço máximo', 'vapor-hub' ); ?>"
										>
									</label>
								</div>
							</div>
						</details>
					</div>

					<div class="vh-loja-filtro-card">
						<p class="vh-loja-filtro-titulo"><?php esc_html_e( 'Destaques', 'vapor-hub' ); ?></p>
						<ul class="vh-loja-filtro-checklist" role="list">
							<li>
								<label class="vh-loja-exclusive">
									<input
										type="checkbox"
										class="vh-loja-filtro-flag"
										data-vh-filtro="promocao"
										value="1"
										<?php checked( ! empty( $filtros['promocao'] ) ); ?>
									>
									<span><?php esc_html_e( 'Em promoção', 'vapor-hub' ); ?></span>
								</label>
								<p class="vh-loja-filtro-ajuda vh-text-xs vh-text-muted">
									<?php esc_html_e( 'Produtos com preço reduzido.', 'vapor-hub' ); ?>
								</p>
							</li>
							<li>
								<label class="vh-loja-exclusive">
									<input
										type="checkbox"
										class="vh-loja-filtro-flag"
										data-vh-filtro="novidades"
										value="1"
										<?php checked( ! empty( $filtros['novidades'] ) ); ?>
									>
									<span><?php esc_html_e( 'Novidades', 'vapor-hub' ); ?></span>
								</label>
								<p class="vh-loja-filtro-ajuda vh-text-xs vh-text-muted">
									<?php esc_html_e( 'Lançamentos dos últimos 30 dias.', 'vapor-hub' ); ?>
								</p>
							</li>
							<li>
								<label class="vh-loja-exclusive">
									<input
										type="checkbox"
										class="vh-loja-filtro-flag"
										data-vh-filtro="exclusivos"
										value="1"
										<?php checked( ! empty( $filtros['exclusivos'] ) ); ?>
									>
									<span><?php esc_html_e( 'Apenas exclusivos', 'vapor-hub' ); ?></span>
								</label>
								<p class="vh-loja-filtro-ajuda vh-text-xs vh-text-muted">
									<?php esc_html_e( 'Produtos exclusivos da marca.', 'vapor-hub' ); ?>
								</p>
							</li>
						</ul>
					</div>

					<div class="vh-loja-filtro-card">
						<p class="vh-loja-filtro-titulo"><?php esc_html_e( 'Disponibilidade', 'vapor-hub' ); ?></p>
						<ul class="vh-loja-filtro-checklist" role="list">
							<li>
								<label class="vh-loja-exclusive">
									<input
										type="checkbox"
										class="vh-loja-filtro-flag"
										data-vh-filtro="estoque"
										value="1"
										<?php checked( ! empty( $filtros['estoque'] ) ); ?>
									>
									<span><?php esc_html_e( 'Disponível agora', 'vapor-hub' ); ?></span>
								</label>
								<p class="vh-loja-filtro-ajuda vh-text-xs vh-text-muted">
									<?php esc_html_e( 'Somente itens em estoque.', 'vapor-hub' ); ?>
								</p>
							</li>
							<li>
								<label class="vh-loja-exclusive">
									<input
										type="checkbox"
										class="vh-loja-filtro-flag"
										data-vh-filtro="variavel"
										value="1"
										<?php checked( ! empty( $filtros['variavel'] ) ); ?>
									>
									<span><?php esc_html_e( 'Com opções', 'vapor-hub' ); ?></span>
								</label>
								<p class="vh-loja-filtro-ajuda vh-text-xs vh-text-muted">
									<?php esc_html_e( 'Produtos personalizáveis ou com variações.', 'vapor-hub' ); ?>
								</p>
							</li>
						</ul>
					</div>

					<div class="vh-loja-filtro-bloco">
						<details class="vh-loja-details" open>
							<summary class="vh-loja-details-sumario"><?php esc_html_e( 'Categoria', 'vapor-hub' ); ?></summary>
							<ul class="vh-loja-cat-lista">
								<li>
									<a href="<?php echo esc_url( VH_Loja_Filtros::url_com_filtros( $loja_url ) ); ?>" class="<?php echo is_shop() && ! is_product_taxonomy() ? 'is-active' : ''; ?>">
										<?php esc_html_e( 'Todas as categorias', 'vapor-hub' ); ?>
									</a>
								</li>
								<?php foreach ( $categorias as $cat ) : ?>
									<?php
									$link = get_term_link( $cat );
									if ( is_wp_error( $link ) ) {
										continue;
									}
									$link  = VH_Loja_Filtros::url_com_filtros( $link );
									$ativo = is_product_category( $cat->slug );
									?>
									<li>
										<a href="<?php echo esc_url( $link ); ?>" class="<?php echo $ativo ? 'is-active' : ''; ?>">
											<?php echo esc_html( $cat->name ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					</div>

					<?php if ( ! empty( $filtros['tem_filtros'] ) ) : ?>
						<div class="vh-loja-filtros-acoes">
							<button type="button" class="vh-loja-filtros-limpar-sidebar" data-vh-loja-limpar>
								<?php esc_html_e( 'Limpar filtros', 'vapor-hub' ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( is_active_sidebar( 'loja-lateral' ) ) : ?>
					<div class="vh-loja-widgets">
						<?php dynamic_sidebar( 'loja-lateral' ); ?>
					</div>
				<?php endif; ?>
			</aside>

			<div class="vh-loja-produtos" id="vh-loja-produtos" aria-live="polite">
				<?php
				if ( woocommerce_product_loop() ) {

					woocommerce_product_loop_start();

					if ( wc_get_loop_prop( 'is_shortcode' ) ) {
						$GLOBALS['woocommerce_loop']['columns'] = absint( wc_get_loop_prop( 'columns', 4 ) );
					} else {
						$GLOBALS['woocommerce_loop']['columns'] = 3;
					}

					while ( have_posts() ) {
						the_post();
						do_action( 'woocommerce_shop_loop' );
						wc_get_template_part( 'content', 'product' );
					}

					woocommerce_product_loop_end();

					do_action( 'woocommerce_after_shop_loop' );
				} else {
					do_action( 'woocommerce_no_products_found' );
				}
				?>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();

<?php
/**
 * Cabeçalho estilo MVP (logo, menu, busca, carrinho, conta)
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

$logo_url = function_exists( 'vh_logo_url_resolvida' ) ? vh_logo_url_resolvida() : '';

$loja_url    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
$carrinho_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/carrinho/' );
$conta_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/minha-conta/' );

$qtd_carrinho = 0;
if ( function_exists( 'WC' ) && WC()->cart ) {
	$qtd_carrinho = (int) WC()->cart->get_cart_contents_count();
}
?>

<header class="vh-header vh-header-mvp" id="vh-site-header">
	<div class="vh-header-inner vh-header-mvp-inner">
		<div class="vh-header-logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vh-header-logo-link" rel="home">
				<?php
				if ( function_exists( 'vh_logo_imagem_html' ) ) {
					echo vh_logo_imagem_html(
						array(
							'class'         => 'vh-header-logo-img',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'sizes'         => '(max-width: 781px) 160px, 200px',
						)
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} elseif ( $logo_url ) {
					?>
					<img src="<?php echo esc_url( $logo_url ); ?>"
					     alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
					     class="vh-header-logo-img"
					     width="200"
					     height="96"
					     loading="eager"
					     decoding="async"
					     referrerpolicy="no-referrer">
					<?php
				} else {
					echo '<span class="vh-header-logo-texto">' . esc_html( get_bloginfo( 'name' ) ?: 'Vapor Hub' ) . '</span>';
				}
				?>
			</a>
		</div>

		<nav class="vh-nav-desktop" aria-label="<?php esc_attr_e( 'Menu principal', 'vapor-hub' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'principal',
					'container'      => false,
					'menu_class'     => 'vh-nav-desktop-list',
					'fallback_cb'    => 'vh_menu_principal_fallback',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<div class="vh-header-acoes">
			<form role="search" method="get" class="vh-header-busca" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="vh-sr-only" for="vh-header-s"><?php esc_html_e( 'Buscar', 'vapor-hub' ); ?></label>
				<svg class="vh-header-busca-icone" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
				<input type="search" id="vh-header-s" name="s" class="vh-header-busca-input" placeholder="<?php esc_attr_e( 'Buscar…', 'vapor-hub' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<input type="hidden" name="post_type" value="product">
				<?php endif; ?>
			</form>

			<div class="vh-header-icones">
				<a href="<?php echo esc_url( $carrinho_url ); ?>" class="vh-header-icone vh-header-carrinho" aria-label="<?php esc_attr_e( 'Carrinho', 'vapor-hub' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
					<?php if ( $qtd_carrinho > 0 ) : ?>
						<span class="vh-header-carrinho-badge" aria-hidden="true"><?php echo esc_html( (string) min( 99, $qtd_carrinho ) ); ?></span>
					<?php else : ?>
						<span class="vh-header-carrinho-dot" aria-hidden="true"></span>
					<?php endif; ?>
				</a>

				<a href="<?php echo esc_url( $conta_url ); ?>" class="vh-header-icone vh-header-conta vh-hidden-xs" aria-label="<?php esc_attr_e( 'Minha conta', 'vapor-hub' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				</a>

				<button type="button" class="vh-menu-toggle vh-header-menu-mobile vh-header-icone" aria-expanded="false" aria-controls="vh-nav-mobile" aria-label="<?php esc_attr_e( 'Abrir menu', 'vapor-hub' ); ?>" data-label-abrir="<?php esc_attr_e( 'Abrir menu', 'vapor-hub' ); ?>" data-label-fechar="<?php esc_attr_e( 'Fechar menu', 'vapor-hub' ); ?>">
					<span class="vh-menu-toggle-barras" aria-hidden="true">
						<span></span>
						<span></span>
						<span></span>
					</span>
				</button>
			</div>
		</div>
	</div>

	<div class="vh-nav-mobile-backdrop" aria-hidden="true" tabindex="-1"></div>

	<div id="vh-nav-mobile" class="vh-nav-mobile" aria-hidden="true">
		<div class="vh-nav-mobile-inner">
			<p class="vh-nav-mobile-eyebrow"><?php esc_html_e( 'Navegação', 'vapor-hub' ); ?></p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'principal',
					'container'      => false,
					'menu_class'     => 'vh-nav-mobile-list',
					'fallback_cb'    => 'vh_menu_principal_fallback',
					'depth'          => 1,
				)
			);
			?>
			<div class="vh-nav-mobile-rodape">
				<a href="<?php echo esc_url( $conta_url ); ?>" class="vh-btn vh-btn-secondary vh-nav-mobile-conta">
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
					<?php esc_html_e( 'Minha conta', 'vapor-hub' ); ?>
				</a>
			</div>
		</div>
	</div>
</header>

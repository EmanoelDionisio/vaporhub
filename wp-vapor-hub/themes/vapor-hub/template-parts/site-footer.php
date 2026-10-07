<?php
/**
 * Rodapé estilo MVP
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

$contato_url = function_exists( 'vh_url_pagina_por_slug' ) ? vh_url_pagina_por_slug( 'contato' ) : home_url( '/contato/' );

$rodape = get_option( 'vh_rodape', array() );
if ( ! is_array( $rodape ) ) {
	$rodape = array();
}
$rodape = wp_parse_args(
	$rodape,
	array(
		'descricao' => __( 'Loja de pods, e-líquidos e acessórios com PIX, parcelamento e envio para o Brasil.', 'vapor-hub' ),
		'instagram' => '',
		'youtube'   => '',
		'facebook'  => '',
	)
);

$logo_url = function_exists( 'vh_logo_url_resolvida' ) ? vh_logo_url_resolvida() : '';

$loja_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

$menu_rodape = function_exists( 'vh_menu_tem_itens' ) && vh_menu_tem_itens( 'rodape' );
$cats_loja   = array();
if ( ! $menu_rodape ) {
	$cats_loja = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'parent'     => 0,
			'number'     => 8,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $cats_loja ) ) {
		$cats_loja = array();
	}
}
if ( is_wp_error( $cats_loja ) ) {
	$cats_loja = array();
}

$pagina_trocas = get_posts( array( 'post_type' => 'page', 'name' => 'trocas-e-devolucoes', 'posts_per_page' => 1 ) );
$link_trocas   = ! empty( $pagina_trocas ) ? get_permalink( $pagina_trocas[0] ) : '#';
?>

<footer class="vh-footer-mvp" id="vh-site-footer">
	<div class="vh-container vh-footer-mvp-inner">
		<div class="vh-footer-mvp-grid">

			<div class="vh-footer-mvp-col">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="vh-footer-mvp-logo-link">
					<?php
					if ( function_exists( 'vh_logo_imagem_html' ) ) {
						echo vh_logo_imagem_html(
							array(
								'class'  => 'vh-footer-mvp-logo',
								'loading'=> 'lazy',
								'sizes'  => '(max-width: 781px) 160px, 200px',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} elseif ( $logo_url ) {
						?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="vh-footer-mvp-logo" width="160" height="64" loading="lazy" decoding="async" referrerpolicy="no-referrer">
						<?php
					} else {
						echo '<span class="vh-header-logo-texto">' . esc_html( get_bloginfo( 'name' ) ?: 'Vapor Hub' ) . '</span>';
					}
					?>
				</a>
				<p class="vh-footer-mvp-desc"><?php echo esc_html( $rodape['descricao'] ); ?></p>
				<div class="vh-footer-mvp-social">
					<?php if ( ! empty( $rodape['instagram'] ) ) : ?>
						<a href="<?php echo esc_url( $rodape['instagram'] ); ?>" class="vh-footer-social-btn" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $rodape['youtube'] ) ) : ?>
						<a href="<?php echo esc_url( $rodape['youtube'] ); ?>" class="vh-footer-social-btn" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/></svg>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $rodape['facebook'] ) ) : ?>
						<a href="<?php echo esc_url( $rodape['facebook'] ); ?>" class="vh-footer-social-btn" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="vh-footer-mvp-col">
				<h4 class="vh-footer-mvp-titulo"><?php esc_html_e( 'Loja', 'vapor-hub' ); ?></h4>
				<?php if ( $menu_rodape ) : ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'rodape',
							'container'      => false,
							'menu_class'     => 'vh-footer-mvp-links',
							'depth'          => 0,
							'fallback_cb'    => false,
						)
					);
					?>
				<?php else : ?>
				<ul class="vh-footer-mvp-links">
					<?php foreach ( $cats_loja as $cat ) : ?>
						<li>
							<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
						</li>
					<?php endforeach; ?>
					<li><a href="<?php echo esc_url( $loja_url ); ?>"><?php esc_html_e( 'Outlet', 'vapor-hub' ); ?></a></li>
				</ul>
				<?php endif; ?>
			</div>

			<div class="vh-footer-mvp-col">
				<h4 class="vh-footer-mvp-titulo"><?php esc_html_e( 'Suporte', 'vapor-hub' ); ?></h4>
				<ul class="vh-footer-mvp-links">
					<li><a href="<?php echo esc_url( $contato_url ); ?>"><?php esc_html_e( 'Central de ajuda', 'vapor-hub' ); ?></a></li>
					<li><a href="<?php echo esc_url( $link_trocas ); ?>"><?php esc_html_e( 'Trocas e devoluções', 'vapor-hub' ); ?></a></li>
					<li><a href="<?php echo esc_url( $loja_url ); ?>"><?php esc_html_e( 'Política de envio', 'vapor-hub' ); ?></a></li>
					<li><a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '#' ); ?>"><?php esc_html_e( 'Rastrear pedido', 'vapor-hub' ); ?></a></li>
					<li><a href="#" class="vh-abrir-revenda"><?php esc_html_e( 'Área do revendedor', 'vapor-hub' ); ?></a></li>
				</ul>
			</div>

			<div class="vh-footer-mvp-col">
				<h4 class="vh-footer-mvp-titulo"><?php esc_html_e( 'Fale conosco', 'vapor-hub' ); ?></h4>
				<ul class="vh-footer-mvp-contatos">
					<li>
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
						<span>(11) 99999-9999</span>
					</li>
					<li>
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
						<span>contato@piloto.example</span>
					</li>
					<li>
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
						<span><?php esc_html_e( 'São Paulo, SP — Brasil', 'vapor-hub' ); ?></span>
					</li>
				</ul>
			</div>
		</div>

		<div class="vh-footer-mvp-barra">
			<p class="vh-footer-copy">© <?php echo esc_html( (string) gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'Todos os direitos reservados.', 'vapor-hub' ); ?></p>
			<div class="vh-footer-legal">
				<?php
				$termos = get_posts( array( 'post_type' => 'page', 'name' => 'termos-de-uso', 'posts_per_page' => 1 ) );
				$priv   = get_posts( array( 'post_type' => 'page', 'name' => 'politica-de-privacidade', 'posts_per_page' => 1 ) );
				?>
				<?php if ( ! empty( $termos ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $termos[0] ) ); ?>"><?php esc_html_e( 'Termos de uso', 'vapor-hub' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $priv ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $priv[0] ) ); ?>"><?php esc_html_e( 'Política de privacidade', 'vapor-hub' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</footer>

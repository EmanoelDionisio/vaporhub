<?php
/**
 * Template Part: CTA de Revenda
 *
 * Chaves alinhadas ao plugin (vh_revenda): titulo, descricao, botao_texto,
 * whatsapp, url_externa, mostrar, modo (whatsapp | popup | ajax).
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$salvo = get_option( 'vh_revenda', array() );
if ( ! is_array( $salvo ) ) {
	$salvo = array();
}

$revenda = class_exists( 'VH_Settings' )
	? VH_Settings::obter_revenda()
	: wp_parse_args(
		$salvo,
		array(
			'titulo'       => __( 'Seja um revendedor Vapor Hub', 'vapor-hub' ),
			'descricao'    => __( 'Tenha acesso a preços especiais, kit de mostruário e suporte dedicado. Junte-se a mais de 200 revendedores em todo o Brasil.', 'vapor-hub' ),
			'botao_texto'  => __( 'Quero ser revendedor', 'vapor-hub' ),
			'whatsapp'     => '',
			'url_externa'  => '',
			'mostrar'      => '1',
			'modo'         => 'popup',
		)
	);

if ( isset( $revenda['mostrar'] ) && '0' === $revenda['mostrar'] ) {
	return;
}

$url_externa = ! empty( $revenda['url_externa'] ) ? esc_url( $revenda['url_externa'] ) : '';

$btn_class = 'vh-btn vh-btn-primary vh-btn-lg';
/* Sem URL externa: abre o modal (JS + popup-revenda.php). */
if ( ! $url_externa ) {
	$btn_class .= ' vh-abrir-revenda';
}
?>

<section class="vh-revenda vh-section vh-animar vh-slide-up" id="revenda" aria-label="<?php esc_attr_e( 'Programa de revenda', 'vapor-hub' ); ?>">
	<div class="vh-container">
		<div class="vh-revenda-card">

			<div class="vh-revenda-blur-1" aria-hidden="true"></div>
			<div class="vh-revenda-blur-2" aria-hidden="true"></div>

			<div class="vh-revenda-conteudo">
				<h2 class="vh-revenda-titulo">
					<?php echo esc_html( $revenda['titulo'] ); ?>
				</h2>
				<p class="vh-revenda-descricao">
					<?php echo esc_html( $revenda['descricao'] ); ?>
				</p>

				<?php if ( $url_externa ) : ?>
					<a href="<?php echo $url_externa; ?>"
					   class="<?php echo esc_attr( $btn_class ); ?>"
					   target="_blank"
					   rel="noopener noreferrer">
						<?php echo esc_html( $revenda['botao_texto'] ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="<?php echo esc_attr( $btn_class ); ?>">
						<?php echo esc_html( $revenda['botao_texto'] ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="vh-revenda-detalhe" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.1">
					<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
					<circle cx="9" cy="7" r="4"/>
					<path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
					<path d="M16 3.13a4 4 0 0 1 0 7.75"/>
				</svg>
			</div>

		</div>
	</div>
</section>

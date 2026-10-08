<?php
/**
 * Faixa da home: título, frase e botão.
 *
 * variante forte — fundo da cor principal.
 * variante suave — cartão com a superfície do tema.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

$titulo   = isset( $args['titulo'] ) ? (string) $args['titulo'] : '';
$texto    = isset( $args['texto'] ) ? (string) $args['texto'] : '';
$botao    = isset( $args['botao'] ) ? (string) $args['botao'] : '';
$link     = isset( $args['link'] ) ? (string) $args['link'] : '';
$rotulo   = isset( $args['rotulo'] ) ? (string) $args['rotulo'] : '';
$ancora   = isset( $args['ancora'] ) ? sanitize_html_class( (string) $args['ancora'] ) : 'vh-home-faixa';
$variante = ( isset( $args['variante'] ) && 'suave' === $args['variante'] ) ? 'suave' : 'forte';

if ( '' === $titulo || '' === $link ) {
	return;
}

$titulo_id = $ancora . '-titulo';
?>

<section class="vh-section vh-home-faixa vh-home-faixa--<?php echo esc_attr( $variante ); ?>" id="<?php echo esc_attr( $ancora ); ?>" aria-labelledby="<?php echo esc_attr( $titulo_id ); ?>">
	<div class="vh-container">
		<div class="vh-home-faixa-miolo">
			<div class="vh-home-faixa-texto">
				<?php if ( '' !== $rotulo ) : ?>
					<p class="vh-vitrine-kicker"><?php echo esc_html( $rotulo ); ?></p>
				<?php endif; ?>
				<h2 class="vh-vitrine-titulo vh-home-faixa-titulo" id="<?php echo esc_attr( $titulo_id ); ?>"><?php echo esc_html( $titulo ); ?></h2>
				<?php if ( '' !== $texto ) : ?>
					<p class="vh-home-faixa-frase"><?php echo esc_html( $texto ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $botao ) : ?>
				<a class="vh-btn <?php echo 'suave' === $variante ? 'vh-btn-primary' : 'vh-home-faixa-botao'; ?>" href="<?php echo esc_url( $link ); ?>">
					<?php echo esc_html( $botao ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>

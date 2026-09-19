<?php
/**
 * Template Part: Seção Hero da Home
 *
 * Dados alinhados às chaves salvas pelo plugin (vh_hero): badge_texto, titulo,
 * subtitulo, botao_texto, botao_link, botao_secundario_texto, botao_secundario_link, imagem_fundo.
 *
 * No título, use o caractere | para dividir a primeira linha da linha em destaque (lima),
 * por exemplo: VAPOR COM ATITUDE|NA SUA MÃO
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$loja_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

$salvo = get_option( 'vh_hero', array() );
if ( ! is_array( $salvo ) ) {
	$salvo = array();
}

$hero = wp_parse_args(
	$salvo,
	array(
		'badge_texto'              => __( 'Novidades 2026', 'vapor-hub' ),
		'titulo'                   => __( 'VAPOR COM ATITUDE|NA SUA MÃO', 'vapor-hub' ),
		'subtitulo'                => __( 'Pods, e-líquidos e acessórios com PIX, parcelamento e envio para o Brasil.', 'vapor-hub' ),
		'botao_texto'              => __( 'Comprar agora', 'vapor-hub' ),
		'botao_link'               => $loja_url,
		'botao_secundario_texto'   => __( 'Ver produtos', 'vapor-hub' ),
		'botao_secundario_link'    => $loja_url,
		'imagem_fundo'             => '',
	)
);

$imagem_src = ! empty( $hero['imagem_fundo'] )
	? $hero['imagem_fundo']
	: ( get_stylesheet_directory_uri() . '/assets/img/hero-bg.jpg' );

/*
 * Modo carrossel: quando o switch "Usar carrossel de slides" está ligado, o
 * banner exibe APENAS as imagens cadastradas (artes completas), sem os textos
 * e botões do hero por cima. Cada slide pode ter uma imagem dedicada para
 * celular; sem ela, o tema reaproveita a imagem de computador.
 */
$usar_slides = isset( $hero['usar_slides'] ) && '1' === (string) $hero['usar_slides'];

$hero_slides = vh_hero_slides_dados();

/* Modo "somente imagem" só vale quando há ao menos um slide válido e carrossel ligado. */
$modo_imagem = $usar_slides && ! empty( $hero_slides );
$tem_carrossel = count( $hero_slides ) > 1;

$slides_nav = isset( $hero['slides_navegacao'] ) ? sanitize_key( (string) $hero['slides_navegacao'] ) : 'dots';
if ( ! in_array( $slides_nav, array( 'dots', 'setas', 'ambos', 'nenhum' ), true ) ) {
	$slides_nav = 'dots';
}
$mostrar_pontos = $tem_carrossel && in_array( $slides_nav, array( 'dots', 'ambos' ), true );
$mostrar_setas  = $tem_carrossel && in_array( $slides_nav, array( 'setas', 'ambos' ), true );

$sec_link = ! empty( $hero['botao_secundario_link'] ) ? $hero['botao_secundario_link'] : $loja_url;

$titulo_raw = isset( $hero['titulo'] ) ? (string) $hero['titulo'] : '';
$titulo_linha_destaque = '';
if ( false !== strpos( $titulo_raw, '|' ) ) {
	$partes                = explode( '|', $titulo_raw, 2 );
	$titulo_raw            = trim( $partes[0] );
	$titulo_linha_destaque = isset( $partes[1] ) ? trim( $partes[1] ) : '';
}
?>

<section class="vh-hero<?php echo $modo_imagem ? ' vh-hero--imagem' : ''; ?>" role="banner" aria-label="<?php esc_attr_e( 'Banner principal', 'vapor-hub' ); ?>">

	<div class="vh-hero-bg<?php echo $tem_carrossel ? ' vh-hero-bg--carrossel' : ''; ?>"
	     aria-hidden="true"
	     <?php echo $tem_carrossel ? 'data-vh-hero-slider data-intervalo="5500"' : ''; ?>>
		<?php
		foreach ( $hero_slides as $indice => $slide ) :
			vh_hero_picture_slide( $slide, (int) $indice );
		endforeach;
		?>
	</div>

	<?php if ( ! $modo_imagem ) : ?>
		<div class="vh-hero-overlay" aria-hidden="true"></div>
	<?php endif; ?>

	<?php if ( $mostrar_setas ) : ?>
		<div class="vh-hero-setas" aria-hidden="false">
			<button type="button" class="vh-hero-seta vh-hero-seta--prev" aria-label="<?php esc_attr_e( 'Slide anterior', 'vapor-hub' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
					<path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>
			<button type="button" class="vh-hero-seta vh-hero-seta--next" aria-label="<?php esc_attr_e( 'Próximo slide', 'vapor-hub' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
					<path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>
		</div>
	<?php endif; ?>

	<?php if ( $mostrar_pontos ) : ?>
		<div class="vh-hero-pontos" role="tablist" aria-label="<?php esc_attr_e( 'Selecionar slide', 'vapor-hub' ); ?>">
			<?php foreach ( $hero_slides as $indice => $slide ) : ?>
				<button type="button"
				        class="vh-hero-ponto<?php echo 0 === $indice ? ' vh-hero-ponto--ativo' : ''; ?>"
				        data-slide="<?php echo esc_attr( $indice ); ?>"
				        aria-label="<?php echo esc_attr( sprintf( __( 'Slide %d', 'vapor-hub' ), $indice + 1 ) ); ?>"></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $modo_imagem ) : ?>
		<div class="vh-container vh-hero-conteudo">
			<?php if ( ! empty( $hero['badge_texto'] ) ) : ?>
				<span class="vh-hero-badge">
					<?php echo esc_html( $hero['badge_texto'] ); ?>
				</span>
			<?php endif; ?>

			<h1 class="vh-hero-titulo">
				<?php echo esc_html( $titulo_raw ); ?>
				<?php if ( '' !== $titulo_linha_destaque ) : ?>
					<span class="vh-hero-titulo-destaque"><?php echo esc_html( $titulo_linha_destaque ); ?></span>
				<?php endif; ?>
			</h1>

			<?php if ( ! empty( $hero['subtitulo'] ) ) : ?>
				<p class="vh-hero-subtitulo">
					<?php echo esc_html( $hero['subtitulo'] ); ?>
				</p>
			<?php endif; ?>

			<div class="vh-hero-acoes">
				<?php if ( ! empty( $hero['botao_texto'] ) ) : ?>
					<a href="<?php echo esc_url( $hero['botao_link'] ?: $loja_url ); ?>" class="vh-btn vh-btn-primary vh-btn-lg">
						<?php echo esc_html( $hero['botao_texto'] ); ?>
					</a>
				<?php endif; ?>

				<?php if ( ! empty( $hero['botao_secundario_texto'] ) ) : ?>
					<a href="<?php echo esc_url( $sec_link ); ?>" class="vh-btn vh-btn-secondary vh-btn-lg">
						<?php echo esc_html( $hero['botao_secundario_texto'] ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

</section>

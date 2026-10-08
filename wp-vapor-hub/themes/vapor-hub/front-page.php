<?php
/**
 * Front Page — banner, benefícios e a vitrine da página inicial.
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$vh_home = function_exists( 'vh_home_config' ) ? vh_home_config() : array();
?>

<main id="site-content" class="vh-home">
	<?php get_template_part( 'template-parts/content', 'hero' ); ?>
	<?php get_template_part( 'template-parts/content', 'beneficios' ); ?>

	<?php
	if ( function_exists( 'vh_home_bloco_ligado' ) && vh_home_bloco_ligado( $vh_home, 'destaques_mostrar' ) ) {
		vh_home_render_fileira(
			vh_home_ids_destaque(),
			(string) ( $vh_home['destaques_rotulo'] ?? '' ),
			(string) ( $vh_home['destaques_titulo'] ?? '' ),
			'vh-home-destaques'
		);
	}

	if ( function_exists( 'vh_home_bloco_ligado' ) && vh_home_bloco_ligado( $vh_home, 'categorias_mostrar' ) ) {
		get_template_part(
			'template-parts/content',
			'categorias',
			array(
				'rotulo' => (string) ( $vh_home['categorias_rotulo'] ?? '' ),
				'titulo' => (string) ( $vh_home['categorias_titulo'] ?? '' ),
			)
		);
	}

	if ( function_exists( 'vh_home_bloco_ligado' ) && vh_home_bloco_ligado( $vh_home, 'oferta_mostrar' ) ) {
		get_template_part(
			'template-parts/content',
			'oferta',
			array(
				'variante' => 'forte',
				'titulo'   => (string) ( $vh_home['oferta_titulo'] ?? '' ),
				'texto'    => (string) ( $vh_home['oferta_texto'] ?? '' ),
				'botao'    => (string) ( $vh_home['oferta_botao'] ?? '' ),
				'link'     => vh_home_url_oferta( $vh_home ),
				'ancora'   => 'vh-home-oferta',
			)
		);
	}

	if ( function_exists( 'vh_home_bloco_ligado' ) && vh_home_bloco_ligado( $vh_home, 'vendidos_mostrar' ) ) {
		vh_home_render_fileira(
			vh_home_ids_vendidos(),
			(string) ( $vh_home['vendidos_rotulo'] ?? '' ),
			(string) ( $vh_home['vendidos_titulo'] ?? '' ),
			'vh-home-vendidos'
		);
	}

	if ( function_exists( 'vh_home_bloco_ligado' ) && vh_home_bloco_ligado( $vh_home, 'chamada_mostrar' ) ) {
		get_template_part(
			'template-parts/content',
			'oferta',
			array(
				'variante' => 'suave',
				'rotulo'   => (string) ( $vh_home['chamada_rotulo'] ?? '' ),
				'titulo'   => (string) ( $vh_home['chamada_titulo'] ?? '' ),
				'texto'    => (string) ( $vh_home['chamada_texto'] ?? '' ),
				'botao'    => (string) ( $vh_home['chamada_botao'] ?? '' ),
				'link'     => vh_home_url_bloco( $vh_home, 'chamada_link' ),
				'ancora'   => 'vh-home-chamada',
			)
		);
	}
	?>
</main>

<?php
get_footer();

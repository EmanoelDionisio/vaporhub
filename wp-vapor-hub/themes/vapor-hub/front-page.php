<?php
/**
 * Front Page — vitrine nativa (hero + benefícios + categorias + trilhos).
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="vh-home">
	<?php get_template_part( 'template-parts/content', 'hero' ); ?>
	<?php get_template_part( 'template-parts/content', 'beneficios' ); ?>
	<?php get_template_part( 'template-parts/content', 'categorias' ); ?>

	<?php
	$vitrine_exibida = false;
	if ( function_exists( 'vh_home_vitrines' ) ) {
		foreach ( vh_home_vitrines() as $vitrine ) {
			if ( empty( $vitrine['slug'] ) || ! vh_home_categoria_tem_produtos( (string) $vitrine['slug'] ) ) {
				continue;
			}
			get_template_part( 'template-parts/content', 'vitrine', array( 'vitrine' => $vitrine ) );
			$vitrine_exibida = true;
		}
	}
	if ( ! $vitrine_exibida && function_exists( 'vh_home_tem_produtos_recentes' ) && vh_home_tem_produtos_recentes() ) {
		get_template_part( 'template-parts/content', 'destaques' );
	}
	?>

	<?php get_template_part( 'template-parts/content', 'revenda-cta' ); ?>
	<?php
	set_query_var( 'vh_comunidade_somente_fotos', true );
	get_template_part( 'template-parts/content', 'comunidade' );
	set_query_var( 'vh_comunidade_somente_fotos', false );
	?>
</main>

<?php
get_footer();

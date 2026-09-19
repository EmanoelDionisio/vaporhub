<?php
/**
 * Página com slug "contato" — layout MVP
 *
 * @package VaporHub
 * @since   1.0.2
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="vh-page vh-page-contato">
	<div class="vh-container vh-section">
		<?php get_template_part( 'template-parts/content', 'pagina-contato-mvp' ); ?>
	</div>
</main>

<?php
get_footer();

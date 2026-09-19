<?php
/**
 * Rodapé do tema filho
 *
 * Se o Elementor Pro tiver localização "footer" atribuída, ela é exibida.
 * Caso contrário, usa o layout do MVP (template-parts/site-footer.php).
 *
 * @package VaporHub
 * @since   1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '</div><!-- #page .vh-site-wrap -->';

$elementor_rodape = false;
if ( function_exists( 'elementor_theme_do_location' ) ) {
	$elementor_rodape = elementor_theme_do_location( 'footer' );
}
if ( ! $elementor_rodape ) {
	get_template_part( 'template-parts/site', 'footer' );
}

wp_footer();
?>
</body>
</html>

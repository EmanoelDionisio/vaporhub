<?php
/**
 * Cabeçalho do tema filho
 *
 * Se o Elementor Pro tiver localização "header" atribuída, ela é exibida.
 * Caso contrário, usa o layout do MVP (template-parts/site-header.php).
 *
 * @package VaporHub
 * @since   1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$elementor_cabecalho = false;
if ( function_exists( 'elementor_theme_do_location' ) ) {
	$elementor_cabecalho = elementor_theme_do_location( 'header' );
}
if ( ! $elementor_cabecalho ) {
	get_template_part( 'template-parts/site', 'header' );
}
?>
<div id="page" class="vh-site-wrap">

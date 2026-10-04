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
	<script>
	(function(){try{var s=localStorage.getItem('vh-tema');var d=s==='escuro'||(s!=='claro'&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);var r=document.documentElement;r.setAttribute('data-vh-tema',d?'escuro':'claro');r.style.colorScheme=d?'dark':'light';}catch(e){document.documentElement.setAttribute('data-vh-tema','claro');}})();
	</script>
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

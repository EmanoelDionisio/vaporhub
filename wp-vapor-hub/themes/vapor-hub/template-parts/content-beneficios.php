<?php
/**
 * Template Part: Barra de Benefícios
 *
 * Grid com ícones SVG e textos curtos destacando as vantagens da loja.
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$beneficios_salvos = get_option( 'vh_beneficios', array() );

$beneficios_padrao = class_exists( 'VH_Settings' )
	? VH_Settings::beneficios_padrao()
	: array(
		array(
			'icone'     => 'truck',
			'titulo'    => 'Envio para todo o Brasil',
			'descricao' => 'Calcule o CEP no produto',
		),
		array(
			'icone'     => 'check-circle',
			'titulo'    => 'PIX na hora',
			'descricao' => 'Pagamento instantâneo',
		),
		array(
			'icone'     => 'settings',
			'titulo'    => 'Parcelamento',
			'descricao' => 'No cartão, na vitrine',
		),
		array(
			'icone'     => 'shield',
			'titulo'    => 'Compra segura',
			'descricao' => 'Site protegido',
		),
	);

$beneficios = ! empty( $beneficios_salvos ) ? $beneficios_salvos : $beneficios_padrao;
?>

<section class="vh-beneficios vh-section vh-animar vh-slide-up" aria-label="<?php esc_attr_e( 'Benefícios da loja', 'vapor-hub' ); ?>">
	<div class="vh-container">
		<div class="vh-beneficios-grid">
			<?php foreach ( $beneficios as $item ) :
				if ( empty( trim( (string) ( $item['titulo'] ?? '' ) ) ) ) {
					continue;
				}
				$icone_key = isset( $item['icone'] ) ? (string) $item['icone'] : 'check-circle';
				$svg       = function_exists( 'vh_menu_icone_html' ) ? vh_menu_icone_html( $icone_key ) : '';
				if ( '' === $svg && function_exists( 'vh_menu_icone_html' ) ) {
					$svg = vh_menu_icone_html( 'check-circle' );
				}
			?>
				<div class="vh-beneficios-item">
					<div class="vh-beneficios-icone">
						<?php echo $svg; ?>
					</div>
					<div class="vh-beneficios-texto">
						<strong class="vh-beneficios-titulo">
							<?php echo esc_html( $item['titulo'] ); ?>
						</strong>
						<span class="vh-beneficios-descricao vh-text-muted">
							<?php echo esc_html( $item['descricao'] ); ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

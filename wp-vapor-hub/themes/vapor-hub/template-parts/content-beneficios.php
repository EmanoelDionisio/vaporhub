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

$icones_svg = array(
	'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
	'settings'     => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
	'truck'        => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
	'users'        => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
	'star'         => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
	'heart'        => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
	'shield'       => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
	'gift'         => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
);
?>

<section class="vh-beneficios vh-section vh-animar vh-slide-up" aria-label="<?php esc_attr_e( 'Benefícios da loja', 'vapor-hub' ); ?>">
	<div class="vh-container">
		<div class="vh-beneficios-grid">
			<?php foreach ( $beneficios as $item ) :
				if ( empty( trim( (string) ( $item['titulo'] ?? '' ) ) ) ) {
					continue;
				}
				$icone_key = isset( $item['icone'] ) ? $item['icone'] : 'check-circle';
				$svg       = isset( $icones_svg[ $icone_key ] ) ? $icones_svg[ $icone_key ] : $icones_svg['check-circle'];
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

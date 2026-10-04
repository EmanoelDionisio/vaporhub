<?php
/**
 * Template Part: Galeria da Comunidade
 *
 * Aceita o formato salvo pelo plugin Minha Loja (lista com imagem_url, usuario, link)
 * ou o formato legado (titulo, subtitulo, fotos[], link_envio).
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$raw = get_option( 'vh_comunidade', array() );

$titulo_default    = __( 'Grupo VIP', 'vapor-hub' );
$subtitulo_default = __( 'Conteúdo e novidades para quem já compra na loja.', 'vapor-hub' );

$titulo     = $titulo_default;
$subtitulo  = $subtitulo_default;
$fotos      = array();
$link_envio = '#';
$somente_fotos = (bool) get_query_var( 'vh_comunidade_somente_fotos', false );

/* Formato legado (associativo com fotos ou metadados). */
if ( isset( $raw['titulo'] ) || isset( $raw['subtitulo'] ) || isset( $raw['fotos'] ) || isset( $raw['link_envio'] ) ) {
	if ( ! empty( $raw['titulo'] ) ) {
		$titulo = $raw['titulo'];
	}
	if ( ! empty( $raw['subtitulo'] ) ) {
		$subtitulo = $raw['subtitulo'];
	}
	if ( ! empty( $raw['link_envio'] ) ) {
		$link_envio = $raw['link_envio'];
	}
	if ( ! empty( $raw['fotos'] ) && is_array( $raw['fotos'] ) ) {
		$fotos = $raw['fotos'];
	}
} elseif ( ! empty( $raw ) && is_array( $raw ) ) {
	/* Formato do plugin: lista indexada de { imagem_url, usuario, link }. */
	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$url = isset( $item['imagem_url'] ) ? $item['imagem_url'] : ( isset( $item['imagem'] ) ? $item['imagem'] : '' );
		if ( '' === $url ) {
			continue;
		}
		$fotos[] = array(
			'imagem' => $url,
			'usuario' => isset( $item['usuario'] ) ? $item['usuario'] : '',
			'alt' => isset( $item['alt'] ) ? $item['alt'] : '',
			'link' => isset( $item['link'] ) ? $item['link'] : '',
		);
	}
}

if ( $somente_fotos && empty( $fotos ) ) {
	return;
}
?>

<section class="vh-comunidade vh-section vh-animar vh-slide-up" aria-label="<?php esc_attr_e( 'Galeria da comunidade', 'vapor-hub' ); ?>">
	<div class="vh-container">
		<div class="vh-comunidade-header vh-text-center">
			<span class="vh-comunidade-eyebrow">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
					<path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
					<line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
				</svg>
				<?php esc_html_e( 'COMUNIDADE #VAPORHUB', 'vapor-hub' ); ?>
			</span>
			<h2 class="vh-comunidade-titulo">
				<?php echo esc_html( $titulo ); ?>
			</h2>
			<p class="vh-text-muted">
				<?php echo esc_html( $subtitulo ); ?>
			</p>
		</div>
	</div>

	<?php if ( ! empty( $fotos ) ) : ?>
		<div class="vh-comunidade-scroll">
			<div class="vh-comunidade-trilha">
				<?php foreach ( $fotos as $foto ) :
					$img_url  = isset( $foto['imagem'] ) ? $foto['imagem'] : '';
					$usuario  = isset( $foto['usuario'] ) ? $foto['usuario'] : '';
					$alt_text = isset( $foto['alt'] ) ? $foto['alt'] : $usuario;
					$link_ext = isset( $foto['link'] ) ? $foto['link'] : '';

					if ( empty( $img_url ) ) {
						continue;
					}

					if ( $link_ext ) :
						?>
					<a href="<?php echo esc_url( $link_ext ); ?>"
					   class="vh-comunidade-card"
					   target="_blank"
					   rel="noopener noreferrer">
						<?php
					else :
						?>
					<div class="vh-comunidade-card">
						<?php
					endif;
					?>
						<img src="<?php echo esc_url( $img_url ); ?>"
						     alt="<?php echo esc_attr( $alt_text ); ?>"
						     loading="lazy"
						     decoding="async"
						     width="280"
						     height="350">
						<span class="vh-comunidade-selo" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
								<path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
								<line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
							</svg>
						</span>
						<?php if ( ! empty( $usuario ) ) : ?>
							<div class="vh-comunidade-card-overlay">
								<span class="vh-comunidade-usuario">
									@<?php echo esc_html( $usuario ); ?>
								</span>
							</div>
						<?php endif; ?>
					<?php if ( $link_ext ) : ?>
					</a>
						<?php else : ?>
					</div>
						<?php endif; ?>
				<?php endforeach; ?>

				<!-- Card final: Envie sua foto -->
				<button type="button"
				        class="vh-comunidade-card vh-comunidade-card-envio vh-abrir-comunidade-foto"
				        aria-label="<?php esc_attr_e( 'Envie sua foto para o grupo VIP', 'vapor-hub' ); ?>">
					<div class="vh-comunidade-envio-conteudo">
						<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
							<circle cx="12" cy="13" r="4"/>
						</svg>
						<span class="vh-comunidade-envio-texto">
							<?php esc_html_e( 'Envie sua foto', 'vapor-hub' ); ?>
						</span>
						<span class="vh-comunidade-envio-sub vh-text-xs vh-text-muted">
							<?php esc_html_e( 'Mostre o que você usa.', 'vapor-hub' ); ?>
						</span>
					</div>
				</button>
			</div>
		</div>

	<?php else : ?>
		<div class="vh-container vh-text-center">
			<div class="vh-comunidade-vazio">
				<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="vh-text-muted">
					<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
					<circle cx="12" cy="13" r="4"/>
				</svg>
				<p class="vh-text-muted">
					<?php esc_html_e( 'Em breve, novidades do grupo VIP aparecem aqui. Envie a sua foto ou aguarde o conteúdo.', 'vapor-hub' ); ?>
				</p>
				<button type="button" class="vh-btn vh-btn-primary vh-abrir-comunidade-foto" style="margin-top: var(--vh-espaco-4);">
					<?php esc_html_e( 'Envie sua foto', 'vapor-hub' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

</section>

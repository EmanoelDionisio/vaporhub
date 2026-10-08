<?php
/**
 * Helpers de performance — imagens responsivas, preload LCP, assets sob demanda.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve ID de anexo a partir de uma URL no uploads.
 */
function vh_attachment_id_from_url( string $url ): int {
	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}

	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return (int) $cache[ $url ];
	}

	$id = (int) attachment_url_to_postid( $url );
	if ( ! $id ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( is_string( $path ) && str_ends_with( strtolower( $path ), '.webp' ) ) {
			$path_png = preg_replace( '/\.webp$/i', '.png', $path );
			if ( $path_png ) {
				$id = (int) attachment_url_to_postid( home_url( $path_png ) );
			}
		}
	}

	$cache[ $url ] = $id;
	return $id;
}

/**
 * Valor srcset a partir de URL (fallback: URL única).
 */
function vh_srcset_value_from_url( string $url, string $size ): string {
	$id = vh_attachment_id_from_url( $url );
	if ( $id ) {
		$srcset = wp_get_attachment_image_srcset( $id, $size );
		if ( $srcset ) {
			return $srcset;
		}
	}
	return $url;
}

/**
 * Retorna slides do hero (mesma lógica do template).
 *
 * @return array<int,array{url:string,mobile:string}>
 */
function vh_hero_slides_dados(): array {
	$loja_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

	$salvo = get_option( 'vh_hero', array() );
	if ( ! is_array( $salvo ) ) {
		$salvo = array();
	}

	$hero = wp_parse_args(
		$salvo,
		array(
			'imagem_fundo' => '',
			'usar_slides'  => '0',
			'slides'       => array(),
		)
	);

	$imagem_src = ! empty( $hero['imagem_fundo'] )
		? (string) $hero['imagem_fundo']
		: get_stylesheet_directory_uri() . '/assets/img/hero-bg.jpg';

	$usar_slides = isset( $hero['usar_slides'] ) && '1' === (string) $hero['usar_slides'];
	$slides      = array();

	if ( $usar_slides && ! empty( $hero['slides'] ) && is_array( $hero['slides'] ) ) {
		foreach ( $hero['slides'] as $slide_item ) {
			if ( is_array( $slide_item ) ) {
				$slide_url    = $slide_item['url'] ?? '';
				$slide_mobile = $slide_item['url_mobile'] ?? '';
			} else {
				$slide_url    = (string) $slide_item;
				$slide_mobile = '';
			}
			if ( '' !== $slide_url ) {
				$slides[] = array(
					'url'    => (string) $slide_url,
					'mobile' => (string) $slide_mobile,
				);
			}
		}
	}

	if ( empty( $slides ) ) {
		$slides[] = array(
			'url'    => $imagem_src,
			'mobile' => '',
		);
	}

	return $slides;
}

/**
 * Preload da imagem LCP do hero (primeiro slide).
 */
function vh_preload_hero_lcp(): void {
	if ( ! is_front_page() ) {
		return;
	}

	$slides = vh_hero_slides_dados();
	if ( empty( $slides[0]['url'] ) ) {
		return;
	}

	$primeiro = $slides[0];
	$desktop  = $primeiro['url'];
	$mobile   = $primeiro['mobile'] ?? '';

	if ( '' !== $mobile ) {
		printf(
			'<link rel="preload" as="image" href="%s" media="(max-width: 781px)" fetchpriority="high">' . "\n",
			esc_url( $mobile )
		);
		printf(
			'<link rel="preload" as="image" href="%s" media="(min-width: 782px)" fetchpriority="high">' . "\n",
			esc_url( $desktop )
		);
	} else {
		printf(
			'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
			esc_url( $desktop )
		);
	}
}
add_action( 'wp_head', 'vh_preload_hero_lcp', 2 );

/**
 * ID do logo configurado (painel → custom logo → fallback).
 */
function vh_logo_attachment_id(): int {
	static $cached = null;
	if ( null !== $cached ) {
		return (int) $cached;
	}

	$identidade = vh_identidade_visual_atual();
	if ( ! empty( $identidade['logo_url'] ) ) {
		$cached = vh_attachment_id_from_url( (string) $identidade['logo_url'] );
		if ( $cached ) {
			return (int) $cached;
		}
	}

	if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
		$cached = (int) get_theme_mod( 'custom_logo' );
		return (int) $cached;
	}

	$cached = 0;
	return (int) $cached;
}

/**
 * HTML da logo com srcset quando houver anexo.
 *
 * @param array<string,string> $args class, loading, decoding, fetchpriority, sizes.
 */
function vh_logo_imagem_html( array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'class'         => '',
			'loading'       => 'lazy',
			'decoding'      => 'async',
			'fetchpriority' => 'auto',
			'sizes'         => '(max-width: 781px) 160px, 200px',
			'url'           => '',
			'variante'      => '',
		)
	);

	$classe = trim( (string) $args['class'] . ( '' !== $args['variante'] ? ' vh-logo--' . sanitize_html_class( (string) $args['variante'] ) : '' ) );

	$attrs = array(
		'class'          => $classe,
		'loading'        => $args['loading'],
		'decoding'       => $args['decoding'],
		'alt'            => get_bloginfo( 'name' ),
		'sizes'          => $args['sizes'],
		'referrerpolicy' => 'no-referrer',
	);

	if ( 'high' === $args['fetchpriority'] ) {
		$attrs['fetchpriority'] = 'high';
	}

	$url = '' !== (string) $args['url'] ? esc_url_raw( (string) $args['url'] ) : vh_logo_url_resolvida();
	$id  = '' !== $url && function_exists( 'vh_attachment_id_from_url' ) ? vh_attachment_id_from_url( $url ) : 0;
	if ( ! $id && '' === (string) $args['url'] ) {
		$id = vh_logo_attachment_id();
	}

	if ( $id ) {
		return wp_get_attachment_image( $id, 'vh-logo', false, $attrs );
	}

	if ( '' === $url ) {
		return sprintf(
			'<span class="%1$s vh-header-logo-texto">%2$s</span>',
			esc_attr( $classe ),
			esc_html( get_bloginfo( 'name' ) ?: 'Vapor Hub' )
		);
	}
	return sprintf(
		'<img src="%1$s" alt="%2$s" class="%3$s" width="200" height="80" loading="%4$s" decoding="%5$s" referrerpolicy="no-referrer">',
		esc_url( $url ),
		esc_attr( get_bloginfo( 'name' ) ),
		esc_attr( $classe ),
		esc_attr( $args['loading'] ),
		esc_attr( $args['decoding'] )
	);
}

/**
 * Marca clara e, quando houver, a marca do modo escuro.
 *
 * @param array<string,string> $args Mesmos argumentos de vh_logo_imagem_html().
 */
function vh_logo_par_html( array $args = array() ): string {
	$identidade = vh_identidade_visual_atual();
	$claro      = vh_logo_url_resolvida();
	$escuro     = ! empty( $identidade['logo_escuro_url'] ) ? esc_url_raw( (string) $identidade['logo_escuro_url'] ) : '';

	if ( '' === $escuro || $escuro === $claro ) {
		return vh_logo_imagem_html( $args );
	}

	$marca_clara           = $args;
	$marca_clara['url']    = $claro;
	$marca_clara['variante'] = 'claro';
	$marca_escura          = $args;
	$marca_escura['url']   = $escuro;
	$marca_escura['variante'] = 'escuro';
	$marca_escura['fetchpriority'] = 'auto';

	return vh_logo_imagem_html( $marca_clara ) . vh_logo_imagem_html( $marca_escura );
}

/**
 * Imprime tag <picture> do slide do hero com srcset quando possível.
 *
 * @param array{url:string,mobile:string} $slide
 */
function vh_hero_picture_slide( array $slide, int $indice ): void {
	$slide_url    = $slide['url'];
	$slide_mobile = $slide['mobile'] ?? '';
	$classe_ativa = 0 === $indice ? ' vh-hero-bg-img--ativa' : '';
	$is_lcp       = 0 === $indice;

	$mobile_sizes = '(max-width: 781px) 100vw';
	$desktop_sizes = '100vw';
	?>
	<picture class="vh-hero-bg-img<?php echo esc_attr( $classe_ativa ); ?>">
		<?php if ( '' !== $slide_mobile ) : ?>
			<source media="(max-width: 781px)"
			        srcset="<?php echo esc_attr( vh_srcset_value_from_url( $slide_mobile, 'vh-hero-mobile' ) ); ?>"
			        sizes="<?php echo esc_attr( $mobile_sizes ); ?>">
		<?php endif; ?>
		<?php
		$img_attrs = array(
			'src'      => $slide_url,
			'alt'      => '',
			'loading'  => $is_lcp ? 'eager' : 'lazy',
			'decoding' => 'async',
			'width'    => 1920,
			'height'   => 800,
			'sizes'    => $desktop_sizes,
		);

		if ( $is_lcp ) {
			$img_attrs['fetchpriority'] = 'high';
		}

		$attachment_id = vh_attachment_id_from_url( $slide_url );
		if ( $attachment_id ) {
			$html = wp_get_attachment_image(
				$attachment_id,
				'vh-hero',
				false,
				array_merge(
					$img_attrs,
					array(
						'alt' => '',
					)
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			?>
			<img src="<?php echo esc_url( $slide_url ); ?>"
			     alt=""
			     loading="<?php echo $is_lcp ? 'eager' : 'lazy'; ?>"
			     <?php echo $is_lcp ? 'fetchpriority="high"' : ''; ?>
			     decoding="async"
			     width="1920"
			     height="800"
			     sizes="<?php echo esc_attr( $desktop_sizes ); ?>">
			<?php
		}
		?>
	</picture>
	<?php
}

/**
 * Turnstile ativo nas configurações de segurança.
 */
function vh_turnstile_ativo(): bool {
	$seguranca = get_option( 'vh_seguranca', array() );
	if ( ! is_array( $seguranca ) ) {
		return false;
	}
	return ! empty( $seguranca['turnstile_ativo'] )
		&& '1' === (string) $seguranca['turnstile_ativo']
		&& ! empty( $seguranca['turnstile_site_key'] );
}

/**
 * Dados para lazy load de Cropper/Turnstile no front.
 */
function vh_localizar_assets_performance(): void {
	if ( is_admin() ) {
		return;
	}

	wp_localize_script(
		'vh-theme',
		'vhAssets',
		array(
			'cropperCss'  => 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css',
			'cropperJs'   => 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js',
			'turnstileJs' => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vh_localizar_assets_performance', 27 );

/**
 * Carrinho, checkout e página de pedido — onde blocos WC e atribuição são necessários.
 */
function vh_pagina_funil_wc(): bool {
	if ( ! function_exists( 'is_cart' ) ) {
		return false;
	}

	return is_cart() || is_checkout() || is_order_received_page();
}

/**
 * Páginas com templates nativos do tema (sem depender de layout Elementor no conteúdo).
 */
function vh_pagina_tema_nativo(): bool {
	if ( is_front_page() ) {
		return true;
	}

	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product() || is_product_taxonomy() ) ) {
		return true;
	}

	if ( is_page() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( is_string( $slug ) && in_array( $slug, array( 'comunidade', 'contato', 'acessorios' ), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Remove scripts/CSS não críticos fora do funil de compra (prioridade 9999).
 */
function vh_limpar_assets_nao_criticos(): void {
	if ( is_admin() ) {
		return;
	}

	if ( ! vh_pagina_funil_wc() ) {
		wp_dequeue_script( 'wc-order-attribution' );
		wp_dequeue_script( 'sourcebuster-js' );

		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );

		global $wp_styles;
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( array_keys( $wp_styles->registered ) as $handle ) {
				if ( str_starts_with( $handle, 'wc-blocks' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	if ( vh_pagina_tema_nativo() ) {
		wp_dequeue_script( 'hello-theme-frontend' );
	}

	if ( is_front_page() ) {
		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id > 0 ) {
			wp_dequeue_style( 'elementor-post-' . $front_id );
		}
		wp_dequeue_style( 'elementor-frontend' );
		wp_dequeue_script( 'elementor-frontend' );
	}
}
add_action( 'wp_enqueue_scripts', 'vh_limpar_assets_nao_criticos', 9999 );

/**
 * Elementor enfileira CSS do post após wp_enqueue_scripts — limpa na fila do Elementor.
 */
function vh_limpar_elementor_home(): void {
	if ( ! is_front_page() ) {
		return;
	}

	$front_id = (int) get_option( 'page_on_front' );
	if ( $front_id > 0 ) {
		wp_dequeue_style( 'elementor-post-' . $front_id );
	}

	wp_dequeue_style( 'elementor-frontend' );
}
add_action( 'elementor/frontend/after_enqueue_post_styles', 'vh_limpar_elementor_home', 99 );

/**
 * WooCommerce Blocks enfileira CSS tarde — remove fora do funil antes de imprimir.
 */
function vh_limpar_wc_blocks_tarde(): void {
	if ( is_admin() || vh_pagina_funil_wc() ) {
		return;
	}

	global $wp_styles;
	if ( ! $wp_styles instanceof WP_Styles ) {
		return;
	}

	foreach ( $wp_styles->queue as $handle ) {
		if ( is_string( $handle ) && str_starts_with( $handle, 'wc-blocks' ) ) {
			wp_dequeue_style( $handle );
		}
	}
}
add_action( 'wp_print_styles', 'vh_limpar_wc_blocks_tarde', 100 );

/**
 * WooCommerce: carregar CSS de blocos só quando o bloco é renderizado (classic theme).
 */
function vh_wc_blocks_assets_sob_demanda( bool $carregar ): bool {
	if ( is_admin() || vh_pagina_funil_wc() ) {
		return $carregar;
	}

	return true;
}
add_filter( 'should_load_separate_core_block_assets', 'vh_wc_blocks_assets_sob_demanda' );

/**
 * Hello Elementor: CSS/JS do tema pai só onde o layout Hello é usado (cart/checkout Elementor).
 *
 * @param bool $habilitado Valor padrão do filtro Hello.
 */
function vh_hello_assets_so_funil_elementor( bool $habilitado ): bool {
	if ( vh_pagina_tema_nativo() ) {
		return false;
	}

	return $habilitado;
}
add_filter( 'hello_elementor_enqueue_style', 'vh_hello_assets_so_funil_elementor' );
add_filter( 'hello_elementor_enqueue_theme_style', 'vh_hello_assets_so_funil_elementor' );
add_filter( 'hello_elementor_header_footer', 'vh_hello_assets_so_funil_elementor' );

/**
 * Fontes já carregadas via vh-google-fonts — evita requests duplicados do Elementor.
 */
function vh_desativar_fontes_elementor(): bool {
	return false;
}
add_filter( 'elementor/frontend/print_google_fonts', 'vh_desativar_fontes_elementor' );

/**
 * Google Fonts do tema: carregamento assíncrono (sem alterar style_loader_tag global).
 *
 * @param string $html   Tag link completa.
 * @param string $handle Handle do estilo.
 */
function vh_google_fonts_nao_bloqueante( $html, $handle, $href, $media ) {
	if ( 'vh-google-fonts' !== $handle || ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	$async = sprintf(
		'<link rel="stylesheet" id="%1$s-css" href="%2$s" media="print" onload="this.media=\'all\'">',
		esc_attr( $handle ),
		esc_url( $href )
	);

	return $async . '<noscript>' . $html . '</noscript>';
}
add_filter( 'style_loader_tag', 'vh_google_fonts_nao_bloqueante', 10, 4 );

/**
 * jQuery defer só na home pública (LCP). Painel Minha Loja e wp-admin precisam de jQuery síncrono.
 */
function vh_defer_jquery_home_enqueue(): void {
	if ( is_admin() ) {
		return;
	}

	if ( function_exists( 'VH_Portal' ) && VH_Portal::eh_rota_portal() ) {
		return;
	}

	if ( ! is_front_page() ) {
		return;
	}

	wp_script_add_data( 'jquery-core', 'strategy', 'defer' );
	wp_script_add_data( 'jquery-migrate', 'strategy', 'defer' );
}
add_action( 'wp_enqueue_scripts', 'vh_defer_jquery_home_enqueue', 1000 );

/**
 * Garante jQuery síncrono no wp-admin e no portal /minha-loja (biblioteca de mídia).
 *
 * @param string $tag    Tag <script>.
 * @param string $handle Handle do script.
 * @return string
 */
function vh_jquery_sincrono_contextos_criticos( $tag, $handle ) {
	if ( ! in_array( $handle, array( 'jquery-core', 'jquery-migrate' ), true ) ) {
		return $tag;
	}

	$painel = is_admin();
	if ( ! $painel && function_exists( 'VH_Portal' ) && VH_Portal::eh_rota_portal() ) {
		$painel = true;
	}

	if ( ! $painel ) {
		return $tag;
	}

	$tag = preg_replace( '/\sdefer(=(["\'])defer\2)?/i', '', $tag );
	$tag = preg_replace( '/\sasync(=(["\'])async\2)?/i', '', $tag );

	return $tag;
}
add_filter( 'script_loader_tag', 'vh_jquery_sincrono_contextos_criticos', 999, 2 );

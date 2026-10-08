<?php
/**
 * Vapor Hub — Funções do Tema Filho
 *
 * Tema filho do Hello Elementor para e-commerce de vape, pod e e-líquido.
 * Desenvolvido por New Alliance Tecnologia (newalliance.tech).
 *
 * @package VaporHub
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once get_stylesheet_directory() . '/includes/class-vh-loja-filtros.php';
require_once get_stylesheet_directory() . '/includes/class-vh-seo.php';
require_once get_stylesheet_directory() . '/includes/vh-performance.php';
require_once get_stylesheet_directory() . '/includes/vh-home.php';
require_once get_stylesheet_directory() . '/includes/vh-menu-icones.php';
require_once get_stylesheet_directory() . '/includes/class-vh-menu-walker.php';

/** Versão do tema — usada para cache-busting dos assets */
define( 'VH_VERSION', '1.0.99' );

/** Máximo de requisições de cálculo de frete (PDP) por IP por minuto. */
define( 'VH_FRETE_PRODUTO_RATE_LIMIT', 30 );

/** Quantidade máxima permitida numa simulação de frete na PDP. */
define( 'VH_FRETE_PRODUTO_QTY_MAX', 99 );

/**
 * Plugin de frete esperado pelo tema: Correios for WooCommerce (Cláudio Sanches).
 * Caminho relativo a wp-content/plugins/.
 */
define( 'VH_PLUGIN_CORREIOS_WOOCOMMERCE', 'woocommerce-correios/woocommerce-correios.php' );

/**
 * Indica se o Correios for WooCommerce está ativo (integração de frete disponível).
 */
function vh_correios_para_woocommerce_ativo(): bool {
	return defined( 'WC_CORREIOS_VERSION' );
}

/**
 * Caminho absoluto do arquivo principal do plugin Correios for WooCommerce.
 */
function vh_correios_para_woocommerce_plugin_path(): string {
	return WP_PLUGIN_DIR . '/' . VH_PLUGIN_CORREIOS_WOOCOMMERCE;
}

/**
 * Retorna as opções visuais salvas no plugin com fallback seguro.
 *
 * @return array<string,string>
 */
function vh_identidade_visual_atual(): array {
	$padrao = class_exists( 'VH_Settings' )
		? VH_Settings::identidade_visual_padrao()
		: array(
			'logo_url'               => '',
			'logo_escuro_url'        => '',
			'logo_painel_url'        => '',
			'favicon_url'            => '',
			'fonte_titulo'           => 'plus_jakarta',
			'fonte_corpo'            => 'plus_jakarta',
			'cor_primaria'           => '#7618f1',
			'cor_primaria_hover'     => '#5c10d0',
			'cor_fundo'              => '#f6f4fb',
			'cor_superficie'         => '#ffffff',
			'cor_texto'              => '#1a1228',
			'cor_texto_suave'        => '#6b6680',
			'cor_borda'              => '#e4dff0',
			'cor_fundo_escuro'       => '#0e0b14',
			'cor_superficie_escuro'  => '#17141f',
			'cor_texto_escuro'       => '#f4f1fa',
			'cor_texto_suave_escuro' => '#9b94ab',
			'cor_borda_escuro'       => '#2c2738',
			'estilo_card'            => 'suave',
			'raio_card'              => '20',
		);

	$salvo = get_option( 'vh_identidade_visual', array() );
	if ( ! is_array( $salvo ) ) {
		return $padrao;
	}

	$dados = wp_parse_args( $salvo, $padrao );
	$dados['raio_card'] = (string) max( 6, min( 28, absint( $dados['raio_card'] ) ) );
	foreach ( array( 'cor_primaria', 'cor_primaria_hover', 'cor_fundo', 'cor_superficie', 'cor_texto', 'cor_texto_suave', 'cor_borda', 'cor_fundo_escuro', 'cor_superficie_escuro', 'cor_texto_escuro', 'cor_texto_suave_escuro', 'cor_borda_escuro' ) as $chave_cor ) {
		$dados[ $chave_cor ] = sanitize_hex_color( $dados[ $chave_cor ] ) ?: $padrao[ $chave_cor ];
	}
	return $dados;
}

/**
 * URL do logo para cabeçalho/rodapé: painel → custom logo → upload local do site.
 *
 * Evita fallback em domínio externo após migração.
 *
 * @return string
 */
function vh_logo_url_resolvida(): string {
	$identidade = vh_identidade_visual_atual();
	if ( ! empty( $identidade['logo_url'] ) ) {
		return esc_url_raw( $identidade['logo_url'] );
	}
	if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
		$url = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
		if ( $url ) {
			return esc_url_raw( $url );
		}
	}
	return '';
}

/**
 * URL do favicon configurado em Minha Loja → Aparência.
 *
 * @return string
 */
function vh_favicon_url_resolvida(): string {
	$identidade = vh_identidade_visual_atual();
	if ( ! empty( $identidade['favicon_url'] ) ) {
		return esc_url_raw( (string) $identidade['favicon_url'] );
	}
	return '';
}

/**
 * Prioriza o favicon do painel Minha Loja sobre o ícone nativo do WordPress.
 *
 * @param string $url   URL atual.
 * @param int    $size  Tamanho solicitado.
 * @param int    $blog_id ID do site (multisite).
 * @return string
 */
function vh_filtrar_site_icon_url( string $url, int $size, int $blog_id ): string {
	unset( $size, $blog_id );
	$custom = vh_favicon_url_resolvida();
	return '' !== $custom ? $custom : $url;
}
add_filter( 'get_site_icon_url', 'vh_filtrar_site_icon_url', 10, 3 );

/**
 * Converte cor hexadecimal (#RGB ou #RRGGBB) em componentes RGB 0–255.
 *
 * @param string $hex Cor sanitizada (ex.: #7618f1).
 * @return array{0:int,1:int,2:int}|null
 */
function vh_hex_para_rgb_componentes( string $hex ): ?array {
	$hex = ltrim( strtolower( trim( $hex ) ), '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
		return null;
	}

	return array(
		(int) hexdec( substr( $hex, 0, 2 ) ),
		(int) hexdec( substr( $hex, 2, 2 ) ),
		(int) hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Monta URL do Google Fonts conforme fontes selecionadas.
 *
 * @param array<string,string> $identidade Dados de identidade visual.
 * @return string
 */
function vh_google_fonts_url_por_identidade( array $identidade ): string {
	$mapa = array(
		'plus_jakarta' => 'Plus+Jakarta+Sans:wght@400;600;700',
		'montserrat'   => 'Montserrat:wght@500;600;700',
		'poppins'      => 'Poppins:wght@400;600;700',
		'raleway'      => 'Raleway:wght@400;600;700',
		'nunito'       => 'Nunito:wght@400;600;700',
		'inter'        => 'Inter:wght@400;600;700',
		'open_sans'    => 'Open+Sans:wght@400;600;700',
		'lato'         => 'Lato:wght@400;700',
		'roboto'       => 'Roboto:wght@400;500;700',
	);
	$familias = array();
	foreach ( array( 'fonte_titulo', 'fonte_corpo' ) as $chave ) {
		$slug = isset( $identidade[ $chave ] ) ? $identidade[ $chave ] : 'plus_jakarta';
		if ( isset( $mapa[ $slug ] ) ) {
			$familias[ $slug ] = $mapa[ $slug ];
		}
	}
	if ( empty( $familias ) ) {
		$familias['plus_jakarta'] = $mapa['plus_jakarta'];
	}
	return 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_values( $familias ) ) . '&display=swap&subset=latin-ext';
}

/**
 * CSS dinâmico (cores/tipografia/cards) aplicado no front.
 *
 * @param array<string,string> $identidade Dados de identidade visual.
 * @return string
 */
function vh_css_identidade_visual( array $identidade ): string {
	$pilha_fonte = array(
		'plus_jakarta' => '"Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'montserrat'   => '"Montserrat", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'poppins'      => '"Poppins", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'raleway'      => '"Raleway", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'nunito'       => '"Nunito", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'inter'        => '"Inter", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'open_sans'    => '"Open Sans", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'lato'         => '"Lato", ui-sans-serif, system-ui, -apple-system, sans-serif',
		'roboto'       => '"Roboto", ui-sans-serif, system-ui, -apple-system, sans-serif',
	);
	$fonte_titulo = $pilha_fonte[ $identidade['fonte_titulo'] ] ?? $pilha_fonte['plus_jakarta'];
	$fonte_corpo  = $pilha_fonte[ $identidade['fonte_corpo'] ] ?? $pilha_fonte['plus_jakarta'];

	$rgb_prim = vh_hex_para_rgb_componentes( $identidade['cor_primaria'] );
	if ( $rgb_prim ) {
		$prim_leve  = sprintf( 'rgba(%d,%d,%d,0.1)', $rgb_prim[0], $rgb_prim[1], $rgb_prim[2] );
		$prim_media = sprintf( 'rgba(%d,%d,%d,0.25)', $rgb_prim[0], $rgb_prim[1], $rgb_prim[2] );
		$luma       = ( 0.299 * $rgb_prim[0] + 0.587 * $rgb_prim[1] + 0.114 * $rgb_prim[2] ) / 255;
		$texto_inv  = $luma > 0.62 ? '#1a1228' : '#ffffff';
	} else {
		$prim_leve  = 'rgba(118, 24, 241, 0.1)';
		$prim_media = 'rgba(118, 24, 241, 0.25)';
		$texto_inv  = '#ffffff';
	}

	$estilo_card = isset( $identidade['estilo_card'] ) ? $identidade['estilo_card'] : 'suave';
	$sombra_card = '0 1px 2px rgba(28,20,13,.05)';
	$sombra_card_hover = '0 10px 15px -3px rgba(28,20,13,.08),0 4px 6px -4px rgba(28,20,13,.04)';
	if ( 'moderno' === $estilo_card ) {
		$sombra_card = '0 6px 20px rgba(17, 24, 39, 0.08)';
		$sombra_card_hover = '0 12px 28px rgba(17, 24, 39, 0.16)';
	} elseif ( 'vibrante' === $estilo_card ) {
		if ( $rgb_prim ) {
			$sombra_card       = sprintf( '0 8px 18px rgba(%d,%d,%d,.14)', $rgb_prim[0], $rgb_prim[1], $rgb_prim[2] );
			$sombra_card_hover = sprintf( '0 16px 34px rgba(%d,%d,%d,.28)', $rgb_prim[0], $rgb_prim[1], $rgb_prim[2] );
		} else {
			$sombra_card       = '0 8px 18px rgba(118,24,241,.14)';
			$sombra_card_hover = '0 16px 34px rgba(118,24,241,.28)';
		}
	}

	return "
:root{
--vh-cor-primaria: {$identidade['cor_primaria']};
--vh-cor-primaria-hover: {$identidade['cor_primaria_hover']};
--vh-cor-primaria-leve: {$prim_leve};
--vh-cor-primaria-media: {$prim_media};
--vh-cor-fundo: {$identidade['cor_fundo']};
--vh-cor-superficie: {$identidade['cor_superficie']};
--vh-cor-texto: {$identidade['cor_texto']};
--vh-cor-texto-suave: {$identidade['cor_texto_suave']};
--vh-cor-texto-invertido: {$texto_inv};
--vh-cor-borda: {$identidade['cor_borda']};
--vh-fonte-familia: {$fonte_corpo};
--vh-fonte-titulos: {$fonte_titulo};
--vh-raio-card-custom: {$identidade['raio_card']}px;
--vh-sombra-card-custom: {$sombra_card};
--vh-sombra-card-hover-custom: {$sombra_card_hover};
}
body{font-family:var(--vh-fonte-familia);}
h1,h2,h3,h4,h5,h6,.vh-loja-titulo,.vh-home-title,.vh-produto-titulo{font-family:var(--vh-fonte-titulos);}
.woocommerce ul.products li.product,
.vh-card{border-radius:var(--vh-raio-card-custom);box-shadow:var(--vh-sombra-card-custom);}
.woocommerce ul.products li.product:hover,
.vh-card:hover{box-shadow:var(--vh-sombra-card-hover-custom);}
html[data-vh-tema=\"escuro\"]{
--vh-cor-fundo: {$identidade['cor_fundo_escuro']};
--vh-cor-superficie: {$identidade['cor_superficie_escuro']};
--vh-cor-texto: {$identidade['cor_texto_escuro']};
--vh-cor-texto-suave: {$identidade['cor_texto_suave_escuro']};
--vh-cor-borda: {$identidade['cor_borda_escuro']};
--vh-cor-borda-forte: color-mix(in srgb, {$identidade['cor_borda_escuro']} 72%, {$identidade['cor_texto_escuro']});
--vh-cor-sucesso-fundo: color-mix(in srgb, var(--vh-cor-sucesso) 18%, {$identidade['cor_superficie_escuro']});
--vh-cor-erro-fundo: color-mix(in srgb, var(--vh-cor-erro) 18%, {$identidade['cor_superficie_escuro']});
--vh-cor-info-fundo: color-mix(in srgb, var(--vh-cor-info) 18%, {$identidade['cor_superficie_escuro']});
--vh-cor-alerta-fundo: color-mix(in srgb, var(--vh-cor-alerta) 18%, {$identidade['cor_superficie_escuro']});
}
";
}

/* =========================================================================
   1. ENFILEIRAR ESTILOS E SCRIPTS
   ========================================================================= */

/**
 * Carrega estilos do tema pai, do design system, do WooCommerce
 * personalizado, a fonte Plus Jakarta Sans e o JS do tema.
 */
function vh_enfileirar_assets() {
	$identidade_visual = vh_identidade_visual_atual();

	/* --- Google Fonts: Plus Jakarta Sans --- */
	wp_enqueue_style(
		'vh-google-fonts',
		vh_google_fonts_url_por_identidade( $identidade_visual ),
		array(),
		null
	);

	$deps_tema_filho = array();

	/* Hello Elementor: só no funil Elementor; páginas nativas usam só o tema filho. */
	if ( ! vh_pagina_tema_nativo() ) {
		wp_enqueue_style(
			'hello-elementor',
			get_template_directory_uri() . '/style.css',
			array(),
			VH_VERSION
		);
		$deps_tema_filho = array( 'hello-elementor' );
	}

	/* --- Estilo do tema filho (style.css — cabeçalho obrigatório) --- */
	wp_enqueue_style(
		'vapor-hub',
		get_stylesheet_uri(),
		$deps_tema_filho,
		VH_VERSION
	);

	/* --- Design System --- */
	wp_enqueue_style(
		'vh-design-system',
		get_stylesheet_directory_uri() . '/assets/css/design-system.css',
		array( 'vapor-hub' ),
		VH_VERSION
	);

	/* --- Layout da home (front-page.php + template-parts da página inicial) --- */
	if ( is_front_page() ) {
		wp_enqueue_style(
			'vh-home-layout',
			get_stylesheet_directory_uri() . '/assets/css/home-layout.css',
			array( 'vh-design-system' ),
			VH_VERSION
		);
	}

	/* --- Layout geral (cabeçalho/rodapé MVP, loja, contato, páginas simples) --- */
	wp_enqueue_style(
		'vh-mvp-layout',
		get_stylesheet_directory_uri() . '/assets/css/mvp-layout.css',
		array( 'vh-design-system' ),
		VH_VERSION
	);

	/* --- WooCommerce personalizado (só se o WooCommerce estiver ativo) --- */
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style(
			'vh-woocommerce',
			get_stylesheet_directory_uri() . '/assets/css/woocommerce.css',
			array( 'vh-design-system' ),
			VH_VERSION
		);
	}

	$css_identidade = vh_css_identidade_visual( $identidade_visual );
	wp_add_inline_style( 'vh-design-system', $css_identidade );

	/* --- JavaScript do tema (Cropper/Turnstile carregados sob demanda via vh-performance) --- */
	wp_enqueue_script(
		'vh-theme',
		get_stylesheet_directory_uri() . '/assets/js/theme.js',
		array(),
		VH_VERSION,
		array(
			'strategy' => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vh_enfileirar_assets' );

/**
 * REST + Turnstile para cadastro de revenda no popup.
 */
function vh_localizar_revenda_cadastro(): void {
	if ( is_admin() ) {
		return;
	}

	$seguranca = get_option( 'vh_seguranca', array() );
	if ( ! is_array( $seguranca ) ) {
		$seguranca = array();
	}

	$turnstile_ativo = ! empty( $seguranca['turnstile_ativo'] )
		&& '1' === (string) $seguranca['turnstile_ativo']
		&& ! empty( $seguranca['turnstile_site_key'] );

	wp_localize_script(
		'vh-theme',
		'paRevendaCadastro',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'vh-loja/v1/public/revenda/cadastro' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'turnstileAtivo' => $turnstile_ativo,
			'i18n'           => array(
				'enviando'             => __( 'Enviando…', 'vapor-hub' ),
				'turnstile'            => __( 'Conclua a verificação de segurança antes de enviar.', 'vapor-hub' ),
				'erroGenerico'         => __( 'Não foi possível enviar o cadastro. Tente novamente.', 'vapor-hub' ),
				'erroRede'             => __( 'Erro de conexão. Verifique sua internet e tente novamente.', 'vapor-hub' ),
				'documentoCpfInvalido' => __( 'Informe um CPF válido com 11 dígitos.', 'vapor-hub' ),
				'documentoCnpjInvalido'=> __( 'Informe um CNPJ válido com 14 dígitos.', 'vapor-hub' ),
				'whatsappInvalido'     => __( 'Informe um WhatsApp válido com DDD e número.', 'vapor-hub' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vh_localizar_revenda_cadastro', 26 );

/**
 * Dados para envio de foto da galeria da comunidade (REST + Turnstile).
 */
function vh_localizar_comunidade_envio(): void {
	if ( is_admin() ) {
		return;
	}

	$seguranca = get_option( 'vh_seguranca', array() );
	if ( ! is_array( $seguranca ) ) {
		$seguranca = array();
	}

	$turnstile_ativo = ! empty( $seguranca['turnstile_ativo'] )
		&& '1' === (string) $seguranca['turnstile_ativo']
		&& ! empty( $seguranca['turnstile_site_key'] );

	wp_localize_script(
		'vh-theme',
		'paComunidadeEnvio',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'vh-loja/v1/public/comunidade/envio' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'turnstileAtivo' => $turnstile_ativo,
			'maxMb'          => 5,
			'crop'           => array(
				'width'  => 640,
				'height' => 800,
				'ratio'  => 0.8,
			),
			'i18n'           => array(
				'enviando'        => __( 'Enviando…', 'vapor-hub' ),
				'turnstile'       => __( 'Conclua a verificação de segurança antes de enviar.', 'vapor-hub' ),
				'erroGenerico'    => __( 'Não foi possível enviar a foto. Tente novamente.', 'vapor-hub' ),
				'erroRede'        => __( 'Erro de conexão. Verifique sua internet e tente novamente.', 'vapor-hub' ),
				'fotoObrigatoria' => __( 'Selecione uma foto para enviar.', 'vapor-hub' ),
				'fotoGrande'      => __( 'A foto deve ter no máximo 5 MB.', 'vapor-hub' ),
				'fotoTipo'        => __( 'Use uma imagem JPEG, PNG ou WebP.', 'vapor-hub' ),
				'cropObrigatorio' => __( 'Ajuste o enquadramento da foto antes de enviar.', 'vapor-hub' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vh_localizar_comunidade_envio', 26 );

/**
 * Dados para cálculo de frete na página do produto (AJAX).
 *
 * O nonce é escopado ao ID do produto na página para não reutilizar token entre produtos.
 */
function vh_localizar_script_frete_produto(): void {
	if ( ! class_exists( 'WooCommerce' ) || ! is_product() ) {
		return;
	}
	$produto = wc_get_product( get_queried_object_id() );
	if ( ! $produto instanceof WC_Product || ! $produto->needs_shipping() ) {
		return;
	}
	$pid = (int) $produto->get_id();
	wp_localize_script(
		'vh-theme',
		'paFreteProduto',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'vh_frete_produto_' . $pid ),
			'productId' => $pid,
			'i18n'      => array(
				'carregando'  => __( 'Calculando…', 'vapor-hub' ),
				'calcular'    => __( 'Calcular', 'vapor-hub' ),
				'cepInvalido' => __( 'Digite um CEP com 8 dígitos.', 'vapor-hub' ),
				'erroRede'    => __( 'Erro de conexão. Tente novamente.', 'vapor-hub' ),
				'semMetodos'  => __( 'Nenhum frete disponível para este CEP. Confira zonas e métodos no WooCommerce ou o CEP digitado.', 'vapor-hub' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vh_localizar_script_frete_produto', 25 );

/**
 * Dados para filtros dinâmicos da loja (REST + debounce).
 */
function vh_localizar_script_loja_filtros(): void {
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'VH_Loja_Filtros' ) ) {
		return;
	}

	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	$estado = VH_Loja_Filtros::estado();

	wp_localize_script(
		'vh-theme',
		'paLojaFiltros',
		array(
			'restUrl'    => rest_url( VH_Loja_Filtros::REST_NS . '/loja/produtos' ),
			'categoria'  => $estado['categoria'],
			'baseUrl'    => VH_Loja_Filtros::url_base(),
			'precoTeto'  => (int) $estado['preco_teto'],
			'debounceMs' => 400,
			'params'     => array(
				'exclusivos' => VH_Loja_Filtros::PARAM_EXCLUSIVOS,
				'promocao'   => VH_Loja_Filtros::PARAM_PROMOCAO,
				'novidades'  => VH_Loja_Filtros::PARAM_NOVIDADES,
				'estoque'    => VH_Loja_Filtros::PARAM_ESTOQUE,
				'variavel'   => VH_Loja_Filtros::PARAM_VARIAVEL,
			),
			'i18n'       => array(
				'carregando' => __( 'Atualizando produtos…', 'vapor-hub' ),
				'erro'       => __( 'Não foi possível atualizar os produtos. Tente novamente.', 'vapor-hub' ),
				'limpar'     => __( 'Limpar filtros', 'vapor-hub' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vh_localizar_script_loja_filtros', 25 );

/**
 * IP do cliente para rate limit (WooCommerce já normaliza quando disponível).
 */
function vh_frete_produto_client_ip(): string {
	if ( class_exists( 'WC_Geolocation' ) ) {
		return (string) WC_Geolocation::get_ip_address();
	}
	if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return '';
	}
	return (string) sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
}

/**
 * Rate limit simples por IP para o endpoint de frete na PDP (mitiga abuso / custo de APIs de transporte).
 */
function vh_frete_produto_rate_limit_ok(): bool {
	$ip  = vh_frete_produto_client_ip();
	$key = 'vh_frp_rl_' . md5( '' !== $ip ? $ip : 'unknown' );
	$count = (int) get_transient( $key );
	if ( $count >= VH_FRETE_PRODUTO_RATE_LIMIT ) {
		return false;
	}
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	return true;
}

/**
 * AJAX: calcula frete na PDP usando exclusivamente o motor de envio do WooCommerce
 * (zonas e métodos ativos: frete fixo, Correios for WooCommerce, Melhor Envio, etc.).
 * Não há valor de frete fixo no tema — só o que estiver configurado no admin.
 */
function vh_ajax_frete_produto(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Loja indisponível.', 'vapor-hub' ) ), 400 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	if ( $product_id < 1 ) {
		wp_send_json_error( array( 'message' => __( 'Pedido inválido.', 'vapor-hub' ) ), 400 );
	}

	check_ajax_referer( 'vh_frete_produto_' . $product_id, 'nonce' );

	if ( ! vh_frete_produto_rate_limit_ok() ) {
		wp_send_json_error( array( 'message' => __( 'Muitas tentativas. Aguarde um minuto e tente de novo.', 'vapor-hub' ) ), 429 );
	}

	$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
	$qty          = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 1;
	$qty          = max( 1, min( VH_FRETE_PRODUTO_QTY_MAX, $qty ) );
	$cep_raw      = isset( $_POST['cep'] ) ? wp_unslash( $_POST['cep'] ) : '';
	$cep_digits   = preg_replace( '/\D/', '', is_string( $cep_raw ) ? $cep_raw : '' );

	if ( strlen( $cep_digits ) !== 8 ) {
		wp_send_json_error( array( 'message' => __( 'Informe um CEP com 8 dígitos.', 'vapor-hub' ) ), 400 );
	}

	$cep_formatted = substr( $cep_digits, 0, 5 ) . '-' . substr( $cep_digits, 5, 3 );

	$produto_calc = null;
	if ( $variation_id > 0 ) {
		$produto_calc = wc_get_product( $variation_id );
		if ( ! $produto_calc instanceof WC_Product || (int) $produto_calc->get_parent_id() !== $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Variação inválida para este produto.', 'vapor-hub' ) ), 400 );
		}
	} else {
		$produto_calc = wc_get_product( $product_id );
	}

	if ( ! $produto_calc instanceof WC_Product || ! $produto_calc->needs_shipping() ) {
		wp_send_json_error( array( 'message' => __( 'Este item não exige envio.', 'vapor-hub' ) ), 400 );
	}

	if ( 'publish' !== $produto_calc->get_status() ) {
		wp_send_json_error( array( 'message' => __( 'Produto indisponível.', 'vapor-hub' ) ), 403 );
	}

	if ( ! $produto_calc->is_purchasable() ) {
		wp_send_json_error( array( 'message' => __( 'Este produto não está disponível para consulta.', 'vapor-hub' ) ), 403 );
	}

	$pai = wc_get_product( $product_id );
	if ( $pai && $pai->is_type( 'variable' ) && ! $variation_id ) {
		wp_send_json_error( array( 'message' => __( 'Selecione as opções do produto antes de calcular o frete.', 'vapor-hub' ) ), 400 );
	}

	$line_total = (float) wc_get_price_to_display( $produto_calc ) * $qty;
	$line_key   = 'vh_frete_' . wp_generate_password( 10, false, false );

	if ( WC()->customer ) {
		WC()->customer->set_shipping_country( 'BR' );
		WC()->customer->set_shipping_postcode( $cep_formatted );
	}

	$package = array(
		'contents'        => array(
			$line_key => array(
				'key'          => $line_key,
				'product_id'   => $produto_calc->get_parent_id() ? $produto_calc->get_parent_id() : $produto_calc->get_id(),
				'variation_id' => $produto_calc->is_type( 'variation' ) ? $produto_calc->get_id() : 0,
				'variation'    => $produto_calc->is_type( 'variation' ) ? $produto_calc->get_attributes() : array(),
				'data'         => $produto_calc,
				'quantity'     => $qty,
				'line_total'   => $line_total,
				'line_tax'     => 0.0,
				'line_subtotal'=> $line_total,
				'line_subtotal_tax' => 0.0,
			),
		),
		'contents_cost'   => $line_total,
		'applied_coupons' => array(),
		'user'            => array( 'ID' => get_current_user_id() ),
		'destination'     => array(
			'country'   => 'BR',
			'state'     => '',
			'postcode'  => $cep_formatted,
			'city'      => '',
			'address'   => '',
			'address_2' => '',
		),
		'cart_subtotal'   => $line_total,
	);

	try {
		WC()->shipping()->reset_shipping();
		$packages = WC()->shipping()->calculate_shipping( array( $package ) );
	} catch ( Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( 'vh_frete_produto: ' . $e->getMessage(), array( 'source' => 'vapor-hub' ) );
		}
		wp_send_json_error(
			array( 'message' => __( 'Não foi possível calcular o frete. Tente mais tarde.', 'vapor-hub' ) ),
			500
		);
	}

	if ( empty( $packages ) || empty( $packages[0]['rates'] ) ) {
		wp_send_json_success(
			array(
				'rates'  => array(),
				'notice' => __( 'Nenhum método de envio retornou valor para este CEP. Ajuste zonas e métodos em WooCommerce > Configurações > Entrega (incluindo Correios for WooCommerce, se for o caso).', 'vapor-hub' ),
			)
		);
	}

	$rates_out = array();
	foreach ( $packages[0]['rates'] as $rate ) {
		if ( ! $rate instanceof WC_Shipping_Rate ) {
			continue;
		}
		$label = wp_strip_all_tags( (string) $rate->get_label() );
		$label = sanitize_text_field( $label );
		if ( strlen( $label ) > 200 ) {
			$label = mb_substr( $label, 0, 200 );
		}
		$rates_out[] = array(
			'id'    => sanitize_text_field( (string) $rate->get_id() ),
			'label' => $label,
			'cost'  => html_entity_decode( wp_strip_all_tags( wc_price( $rate->get_cost() ) ), ENT_QUOTES, 'UTF-8' ),
		);
	}

	wp_send_json_success( array( 'rates' => $rates_out ) );
}
add_action( 'wp_ajax_vh_frete_produto', 'vh_ajax_frete_produto' );
add_action( 'wp_ajax_nopriv_vh_frete_produto', 'vh_ajax_frete_produto' );

/**
 * Remove os estilos padrão do WooCommerce para usar os nossos.
 *
 * @param array $estilos Lista de handles de CSS do WooCommerce.
 * @return array
 */
function vh_remover_estilos_woocommerce( $estilos ) {
	unset( $estilos['woocommerce-general'] );
	unset( $estilos['woocommerce-layout'] );
	unset( $estilos['woocommerce-smallscreen'] );
	return $estilos;
}
add_filter( 'woocommerce_enqueue_styles', 'vh_remover_estilos_woocommerce' );

/* =========================================================================
   2. CONFIGURAÇÃO DO TEMA (after_setup_theme)
   ========================================================================= */

function vh_configurar_tema() {

	/* Permitir que o WordPress gerencie a tag <title> */
	add_theme_support( 'title-tag' );

	/* Imagens destacadas */
	add_theme_support( 'post-thumbnails' );

	/* HTML5 semântico em formulários e componentes */
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
		'navigation-widgets',
	) );

	/* --- Suporte WooCommerce --- */
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	/* --- Menus de navegação --- */
	register_nav_menus( array(
		'principal'     => __( 'Menu Principal', 'vapor-hub' ),
		'departamentos' => __( 'Departamentos', 'vapor-hub' ),
		'rodape'        => __( 'Menu do Rodapé', 'vapor-hub' ),
	) );

	/* --- Tamanhos de imagem personalizados --- */
	add_image_size( 'vh-produto-card', 800, 800, true );
	add_image_size( 'vh-produto-card-sm', 400, 400, true );
	add_image_size( 'vh-hero', 1920, 800, true );
	add_image_size( 'vh-hero-mobile', 1080, 1350, true );
	add_image_size( 'vh-hero-mobile-sm', 781, 976, true );
	add_image_size( 'vh-categoria', 640, 800, true );
	add_image_size( 'vh-categoria-sm', 320, 400, true );
	add_image_size( 'vh-comunidade', 640, 800, true );
	add_image_size( 'vh-logo', 200, 0, false );
	add_image_size( 'vh-logo-2x', 400, 0, false );
}
add_action( 'after_setup_theme', 'vh_configurar_tema' );

/**
 * Taxonomia de marcas da vitrine. WooCommerce Brands não entra na stack;
 * o tema registra `product_brand` para filtros e selo de marca.
 */
function vh_registrar_taxonomia_marca() {
	if ( taxonomy_exists( 'product_brand' ) ) {
		return;
	}

	register_taxonomy(
		'product_brand',
		array( 'product' ),
		array(
			'labels'            => array(
				'name'          => __( 'Marcas', 'vapor-hub' ),
				'singular_name' => __( 'Marca', 'vapor-hub' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'marca' ),
		)
	);
}
add_action( 'init', 'vh_registrar_taxonomia_marca', 5 );

/* =========================================================================
   3. ÁREAS DE WIDGETS
   ========================================================================= */

function vh_registrar_widgets() {

	/* Rodapé — 4 colunas */
	for ( $i = 1; $i <= 4; $i++ ) {
		register_sidebar( array(
			'name'          => sprintf( __( 'Rodapé — Coluna %d', 'vapor-hub' ), $i ),
			'id'            => 'rodape-' . $i,
			'description'   => sprintf( __( 'Área de widgets da coluna %d do rodapé.', 'vapor-hub' ), $i ),
			'before_widget' => '<div id="%1$s" class="widget vh-widget-rodape %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="vh-widget-titulo">',
			'after_title'   => '</h4>',
		) );
	}

	/* Barra lateral da loja */
	register_sidebar( array(
		'name'          => __( 'Loja — Barra Lateral', 'vapor-hub' ),
		'id'            => 'loja-lateral',
		'description'   => __( 'Barra lateral exibida nas páginas da loja e categorias.', 'vapor-hub' ),
		'before_widget' => '<div id="%1$s" class="widget vh-widget-loja %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="vh-widget-titulo">',
		'after_title'   => '</h4>',
	) );
}
add_action( 'widgets_init', 'vh_registrar_widgets' );

/* =========================================================================
   4. HOOKS WOOCOMMERCE
   ========================================================================= */

/**
 * Altera o texto do botão "Adicionar ao carrinho" para "Comprar".
 *
 * @param string      $texto   Texto original.
 * @param \WC_Product $produto Objeto do produto.
 * @return string
 */
function vh_texto_adicionar_carrinho( $texto, $produto ) {
	if ( $produto->is_type( 'simple' ) ) {
		return __( 'Comprar', 'vapor-hub' );
	}
	if ( $produto->is_type( 'variable' ) ) {
		return __( 'Ver opções', 'vapor-hub' );
	}
	return $texto;
}
add_filter( 'woocommerce_product_add_to_cart_text', 'vh_texto_adicionar_carrinho', 10, 2 );

/**
 * Altera o texto do botão na página individual do produto.
 *
 * @return string
 */
function vh_texto_botao_individual( $texto = '', $produto = null ) {
	if ( $produto instanceof WC_Product && $produto->is_type( 'variable' ) ) {
		return __( 'Adicionar ao Carrinho', 'vapor-hub' );
	}
	return __( 'Comprar', 'vapor-hub' );
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'vh_texto_botao_individual', 10, 2 );

/**
 * Produto marcado na loja como exclusivo. Não usa o nome da marca.
 *
 * @param WC_Product $product Produto WooCommerce.
 */
function vh_produto_e_exclusivo( WC_Product $product ): bool {
	return get_post_meta( $product->get_id(), '_vh_exclusivo', true ) === 'sim';
}

/**
 * Selos da galeria, numa pilha só. A oferta do WooCommerce fica aqui para não
 * cobrir outro selo.
 *
 * @param WC_Product $product Produto WooCommerce.
 */
function vh_badges_galeria_produto_html( WC_Product $product ): string {
	$badges = array();

	if ( $product->is_on_sale() ) {
		$badges[] = '<span class="vh-badge vh-badge-promo">' . esc_html__( 'Oferta', 'vapor-hub' ) . '</span>';
	}

	if ( vh_produto_e_exclusivo( $product ) ) {
		$badges[] = '<span class="vh-badge vh-badge-exclusivo">' . esc_html__( 'Exclusivo', 'vapor-hub' ) . '</span>';
	}

	return implode( '', $badges );
}

/**
 * A oferta nativa é absoluta e cai no mesmo canto da pilha da galeria.
 */
function vh_remover_selo_oferta_padrao(): void {
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
}
add_action( 'wp', 'vh_remover_selo_oferta_padrao' );

/**
 * Exibe o selo "Exclusivo" em produtos da marca Vapor Hub.
 */
function vh_selo_exclusivo() {
	global $product;

	if ( ! $product instanceof WC_Product || ! vh_produto_e_exclusivo( $product ) ) {
		return;
	}

	echo '<span class="vh-badge vh-badge-exclusivo">' . esc_html__( 'Exclusivo', 'vapor-hub' ) . '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'vh_selo_exclusivo', 9 );

/**
 * Botão "Continuar para finalização" no carrinho com classes da marca.
 *
 * @param string $html HTML do botão.
 */
function vh_botao_finalizar_carrinho_html( $html ) {
	if ( false === strpos( $html, 'vh-btn-primary' ) ) {
		$html = str_replace( 'checkout-button button', 'checkout-button button vh-btn vh-btn-primary', $html );
	}

	return $html;
}
add_filter( 'woocommerce_proceed_to_checkout_button_html', 'vh_botao_finalizar_carrinho_html' );

/**
 * Resolve a cor (hex) de um termo de atributo para os swatches da loja.
 *
 * Prioridade: meta _vh_cor_hex (configurável no painel Minha Loja) → mapa por
 * slug/nome. Sem cor conhecida, devolve vazio para a opção virar texto.
 *
 * @param WP_Term $termo Termo do atributo.
 * @return string Cor em formato #rrggbb, ou vazio.
 */
function vh_cor_termo_hex( $termo ) {
	$meta = (string) get_term_meta( $termo->term_id, '_vh_cor_hex', true );
	if ( $meta && preg_match( '/^#?[0-9a-fA-F]{6}$/', $meta ) ) {
		return '#' . ltrim( $meta, '#' );
	}

	$mapa = array(
		'laranja'          => '#f27f0d',
		'laranja-vibrante' => '#f27f0d',
		'verde'            => '#22c55e',
		'green'            => '#22c55e',
		'vermelho'         => '#ef4444',
		'red'              => '#ef4444',
		'azul'             => '#3b82f6',
		'blue'             => '#3b82f6',
		'preto'            => '#1c1917',
		'black'            => '#1c1917',
		'matte-black'      => '#1c1917',
		'matte-full-black' => '#111111',
		'branco'           => '#f5f5f4',
		'white'            => '#f5f5f4',
		'amarelo'          => '#facc15',
		'yellow'           => '#facc15',
		'roxo'             => '#a855f7',
		'purple'           => '#a855f7',
		'rosa'             => '#ec4899',
		'pink'             => '#ec4899',
		'cinza'            => '#6b7280',
		'grey'             => '#6b7280',
		'gray'             => '#6b7280',
		'gun-metal'        => '#4b5563',
		'gunmetal'         => '#4b5563',
		'marrom'           => '#92400e',
		'dourado'          => '#d4af37',
		'gold'             => '#d4af37',
		'prata'            => '#c0c0c0',
		'silver'           => '#c0c0c0',
		'ss'               => '#d1d5db',
		'matte-ss'         => '#9ca3af',
		'stainless'        => '#d1d5db',
		'stainless-ss'     => '#d1d5db',
	);

	if ( isset( $mapa[ $termo->slug ] ) ) {
		return $mapa[ $termo->slug ];
	}
	$nome = sanitize_title( $termo->name );
	if ( isset( $mapa[ $nome ] ) ) {
		return $mapa[ $nome ];
	}
	return '';
}

/**
 * Classifica exibição no configurador (legado por slug ou meta do produto).
 *
 * @param string $taxonomy
 * @param int    $product_id
 * @return string cor|imagem|texto
 */
function vh_tipo_atributo_produto( $taxonomy, $product_id = 0 ) {
	if ( $product_id > 0 && class_exists( 'VH_Personalizacao_Service' ) ) {
		$tipos = VH_Personalizacao_Service::tipos_por_taxonomia( (int) $product_id );
		if ( isset( $tipos[ $taxonomy ] ) ) {
			return $tipos[ $taxonomy ];
		}
	}
	if ( false !== strpos( $taxonomy, 'cor' ) ) {
		return 'cor';
	}
	if ( false !== strpos( $taxonomy, 'modelo' ) || false !== strpos( $taxonomy, 'formato' ) ) {
		return 'imagem';
	}
	return 'texto';
}

/** @deprecated Use vh_tipo_atributo_produto() */
function vh_tipo_atributo( $taxonomy ) {
	return vh_tipo_atributo_produto( $taxonomy, 0 );
}

/**
 * Monta a configuração de personalização ("Monte a Sua") de um produto variável.
 *
 * Retorna apenas os atributos usados para variação, na ordem definida no produto,
 * com os termos e metadados visuais (cor para swatches, forma para modelos).
 *
 * @param WC_Product $product Produto.
 * @return array<int,array<string,mixed>>
 */
function vh_config_personalizacao( $product ) {
	if ( ! $product instanceof WC_Product_Variable ) {
		return array();
	}

	$product_id = $product->get_id();
	$config     = array();
	$filhas     = array();
	foreach ( $product->get_children() as $vh_filha_id ) {
		$vh_filha = wc_get_product( $vh_filha_id );
		if ( $vh_filha instanceof WC_Product_Variation ) {
			$filhas[] = $vh_filha;
		}
	}

	foreach ( $product->get_attributes() as $attr ) {
		if ( ! $attr instanceof WC_Product_Attribute || ! $attr->get_variation() || ! $attr->is_taxonomy() ) {
			continue;
		}

		$taxonomy = $attr->get_name();
		$tipo     = vh_tipo_atributo_produto( $taxonomy, $product_id );
		$termos   = array();

		foreach ( $attr->get_options() as $term_id ) {
			$termo = get_term( (int) $term_id, $taxonomy );
			if ( ! $termo || is_wp_error( $termo ) ) {
				continue;
			}

			$img_id = class_exists( 'VH_Personalizacao_Service' )
				? (int) VH_Personalizacao_Service::imagem_termo( $termo )['id']
				: 0;

			$compravel = false;
			foreach ( $filhas as $vh_filha ) {
				if ( '' === (string) $vh_filha->get_price() ) {
					continue;
				}
				$valor_attr = (string) ( $vh_filha->get_attributes()[ $taxonomy ] ?? '' );
				if ( '' === $valor_attr || $valor_attr === $termo->slug ) {
					$compravel = true;
					break;
				}
			}

			$termos[] = array(
				'slug'        => $termo->slug,
				'nome'        => $termo->name,
				'compravel'   => $compravel,
				'cor'         => ( 'cor' === $tipo ) ? vh_cor_termo_hex( $termo ) : '',
				'imagem_id'   => $img_id,
				'imagem_url'  => $img_id ? ( wp_get_attachment_image_url( $img_id, 'medium' ) ?: '' ) : '',
			);
		}

		if ( empty( $termos ) ) {
			continue;
		}

		if ( 'cor' === $tipo ) {
			$todas_conhecidas = true;
			foreach ( $termos as $vh_termo_cor ) {
				if ( '' === (string) $vh_termo_cor['cor'] ) {
					$todas_conhecidas = false;
					break;
				}
			}
			if ( ! $todas_conhecidas ) {
				$tipo = 'texto';
				foreach ( $termos as $vh_indice_cor => $vh_termo_cor ) {
					$termos[ $vh_indice_cor ]['cor'] = '';
				}
			}
		}

		$config[] = array(
			'taxonomy' => $taxonomy,
			'label'    => wc_attribute_label( $taxonomy ),
			'tipo'     => $tipo,
			'termos'   => $termos,
		);
	}

	return $config;
}

/**
 * Define o número de produtos por página na loja.
 *
 * @return int
 */
function vh_produtos_por_pagina() {
	return 12;
}
add_filter( 'loop_shop_per_page', 'vh_produtos_por_pagina', 20 );

/**
 * Evita breadcrumb duplicado na página do produto (o tema já exibe em content-single-product.php).
 */
function vh_remover_breadcrumb_padrao_produto(): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 10 );
}
add_action( 'wp', 'vh_remover_breadcrumb_padrao_produto', 5 );

/**
 * Aviso no admin: WooCommerce ativo sem o plugin Correios for WooCommerce (dependência de frete do tema).
 */
function vh_admin_aviso_plugin_correios(): void {
	if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce', false ) ) {
		return;
	}
	if ( vh_correios_para_woocommerce_ativo() ) {
		return;
	}

	$plugin_rel = VH_PLUGIN_CORREIOS_WOOCOMMERCE;
	$plugin_abs  = vh_correios_para_woocommerce_plugin_path();
	$instalado   = is_readable( $plugin_abs );

	if ( $instalado ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$url_ativar = wp_nonce_url(
			admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin_rel ) ),
			'activate-plugin_' . $plugin_rel
		);
		$mensagem = sprintf(
			/* translators: %s: URL para ativar o plugin Correios for WooCommerce. */
			__( 'O tema Vapor Hub utiliza o plugin <strong>Correios for WooCommerce</strong> (Cláudio Sanches) para frete na loja. Ele está instalado, mas inativo. <a href="%s">Ativar agora</a>.', 'vapor-hub' ),
			esc_url( $url_ativar )
		);
	} else {
		$url_repo = 'https://wordpress.org/plugins/woocommerce-correios/';
		$mensagem = sprintf(
			/* translators: %s: URL da página do plugin no WordPress.org. */
			__( 'O tema Vapor Hub requer o plugin <strong>Correios for WooCommerce</strong> (Cláudio Sanches) para integração de frete. <a href="%s" target="_blank" rel="noopener noreferrer">Ver no WordPress.org</a> e instale pelo painel em Plugins > Adicionar plugin.', 'vapor-hub' ),
			esc_url( $url_repo )
		);
	}

	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> — %s</p></div>',
		esc_html__( 'Vapor Hub', 'vapor-hub' ),
		wp_kses(
			$mensagem,
			array(
				'strong' => array(),
				'a'      => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		)
	);
}
add_action( 'admin_notices', 'vh_admin_aviso_plugin_correios' );

/* =========================================================================
   5. COMPATIBILIDADE ELEMENTOR / GUTENBERG
   ========================================================================= */

/**
 * Desativa os estilos do Gutenberg no front-end quando o Elementor está ativo,
 * evitando conflitos de CSS desnecessários.
 */
function vh_desativar_gutenberg_frontend() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}

	/*
	 * Carrinho/checkout usam blocos WooCommerce — manter estilos de blocks
	 * evita layout quebrado (conteúdo fora da tela no mobile).
	 */
	$pagina_blocks_wc = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() );

	/*
	 * wc-blocks e scripts WC são removidos em vh_limpar_assets_nao_criticos() (prioridade 999),
	 * depois que WooCommerce registra os handles.
	 */
	if ( ! $pagina_blocks_wc ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
	}

	wp_dequeue_style( 'global-styles' );
}
add_action( 'wp_enqueue_scripts', 'vh_desativar_gutenberg_frontend', 100 );

/* =========================================================================
   6. UTILITÁRIOS
   ========================================================================= */

/**
 * Adiciona a classe 'vh-body' ao <body> para scoping seguro dos estilos.
 *
 * @param array $classes Classes existentes.
 * @return array
 */
function vh_classes_body( $classes ) {
	$classes[] = 'vh-body';
	if ( function_exists( 'is_account_page' ) && is_account_page() && ! is_user_logged_in() ) {
		$classes[] = 'vh-account-guest';
	}
	return $classes;
}
add_filter( 'body_class', 'vh_classes_body' );

/**
 * Dashicons nos formulários WooCommerce (botão mostrar/ocultar senha nos inputs).
 */
function vh_enqueue_dashicons_loja(): void {
	if ( is_admin() ) {
		return;
	}
	if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
		wp_enqueue_style( 'dashicons' );
	}
}
add_action( 'wp_enqueue_scripts', 'vh_enqueue_dashicons_loja', 25 );

/**
 * URL de uma página publicada pelo slug (fallback para /slug/).
 *
 * @param string $slug Slug da página.
 * @return string
 */
function vh_url_pagina_por_slug( string $slug ): string {
	$paginas = get_posts(
		array(
			'name'             => $slug,
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'suppress_filters' => true,
		)
	);
	if ( ! empty( $paginas ) ) {
		return get_permalink( $paginas[0] );
	}
	return trailingslashit( home_url( '/' . $slug ) );
}

/**
 * A posição tem um menu atribuído e pelo menos um item.
 */
function vh_menu_tem_itens( string $local ): bool {
	$locs = get_nav_menu_locations();
	if ( empty( $locs[ $local ] ) ) {
		return false;
	}
	$itens = wp_get_nav_menu_items( (int) $locs[ $local ] );
	return is_array( $itens ) && count( $itens ) > 0;
}

/**
 * Desenha um menu da vitrine, com subníveis e o mesmo walker no topo e no mobile.
 */
function vh_render_menu( string $local, string $classe ): void {
	$args = array(
		'theme_location' => $local,
		'container'      => false,
		'menu_class'     => $classe,
		'depth'          => 0,
		'fallback_cb'    => ( 'principal' === $local ) ? 'vh_menu_principal_fallback' : false,
	);
	if ( class_exists( 'VH_Menu_Walker' ) ) {
		$args['walker'] = new VH_Menu_Walker();
	}
	wp_nav_menu( $args );
}

/**
 * Menu principal padrão (espelha links do MVP React).
 *
 * @param array<string,mixed>|null $args Argumentos de wp_nav_menu.
 */
function vh_menu_principal_fallback( $args = null ): void {
	$classe_ul = 'vh-nav-desktop-list';
	if ( is_array( $args ) && ! empty( $args['menu_class'] ) ) {
		$classe_ul = $args['menu_class'];
	}

	$links = array(
		array( 'url' => home_url( '/' ), 'label' => __( 'Início', 'vapor-hub' ) ),
		array( 'url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' ), 'label' => __( 'Loja', 'vapor-hub' ) ),
		array( 'url' => vh_url_pagina_por_slug( 'acessorios' ), 'label' => __( 'Acessórios', 'vapor-hub' ) ),
		array( 'url' => vh_url_pagina_por_slug( 'contato' ), 'label' => __( 'Contato', 'vapor-hub' ) ),
	);

	printf( '<ul class="%s vh-nav-fallback">', esc_attr( $classe_ul ) );
	foreach ( $links as $item ) {
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( $item['url'] ),
			esc_html( $item['label'] )
		);
	}
	echo '</ul>';
}

/**
 * Remove o wrapper padrão do WooCommerce só na listagem (layout em colunas estilo MVP).
 */
function vh_wc_loja_sem_wrapper_padrao(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
}
add_action( 'wp', 'vh_wc_loja_sem_wrapper_padrao', 5 );

/**
 * Processa o formulário da página de contato (POST).
 */
function vh_processar_contato_site(): void {
	if ( ! isset( $_POST['vh_contato_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vh_contato_nonce'] ) ), 'vh_contato_site' ) ) {
		wp_safe_redirect( add_query_arg( 'contato', 'erro', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$nome    = isset( $_POST['vh_contato_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['vh_contato_nome'] ) ) : '';
	$email   = isset( $_POST['vh_contato_email'] ) ? sanitize_email( wp_unslash( $_POST['vh_contato_email'] ) ) : '';
	$fone    = isset( $_POST['vh_contato_fone'] ) ? sanitize_text_field( wp_unslash( $_POST['vh_contato_fone'] ) ) : '';
	$assunto = isset( $_POST['vh_contato_assunto'] ) ? sanitize_text_field( wp_unslash( $_POST['vh_contato_assunto'] ) ) : '';
	$mens    = isset( $_POST['vh_contato_mensagem'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vh_contato_mensagem'] ) ) : '';

	if ( '' === $nome || '' === $email || '' === $assunto || '' === $mens ) {
		wp_safe_redirect( add_query_arg( 'contato', 'incompleto', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$destino = get_option( 'admin_email' );
	$corpo   = sprintf(
		"Mensagem do site %s\n\nNome: %s\nE-mail: %s\nTelefone: %s\nAssunto: %s\n\n%s\n",
		home_url( '/' ),
		$nome,
		$email,
		$fone,
		$assunto,
		$mens
	);

	$enviado = wp_mail(
		$destino,
		'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $assunto,
		$corpo,
		array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . $email,
		)
	);

	$url_ok = add_query_arg( 'contato', $enviado ? 'ok' : 'erro', wp_get_referer() ?: vh_url_pagina_por_slug( 'contato' ) );
	wp_safe_redirect( $url_ok );
	exit;
}
add_action( 'admin_post_nopriv_vh_contato_site', 'vh_processar_contato_site' );
add_action( 'admin_post_vh_contato_site', 'vh_processar_contato_site' );

/**
 * Adiciona atributo de preconnect para o Google Fonts,
 * melhorando a performance de carregamento da fonte.
 *
 * @param array  $urls          Lista de URLs.
 * @param string $relation_type Tipo de relação (dns-prefetch ou preconnect).
 * @return array
 */
function vh_preconnect_google_fonts( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href' => 'https://fonts.googleapis.com',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'vh_preconnect_google_fonts', 10, 2 );

/* =========================================================================
   7. POPUP DE REVENDA NO RODAPÉ + AJAX
   ========================================================================= */

/**
 * Inclui o modal de revenda em todas as páginas públicas (gatilho .vh-abrir-revenda).
 */
function vh_incluir_popup_revenda(): void {
	if ( is_admin() ) {
		return;
	}
	get_template_part( 'template-parts/popup', 'revenda' );
}

/**
 * Inclui o modal de envio de foto da comunidade (gatilho .vh-abrir-comunidade-foto).
 */
function vh_incluir_popup_comunidade_foto(): void {
	if ( is_admin() ) {
		return;
	}
	get_template_part( 'template-parts/popup', 'comunidade-foto' );
}

/**
 * Legado admin-ajax — delega ao serviço do plugin quando disponível.
 */
function vh_ajax_cadastro_revenda(): void {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'vh_revenda_nonce' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Sessão expirada. Atualize a página e tente de novo.', 'vapor-hub' ) ),
			403
		);
	}

	if ( ! class_exists( 'VH_Auth' ) || ! class_exists( 'VH_Revenda_Leads_Service' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Serviço indisponível. Atualize a página.', 'vapor-hub' ) ),
			503
		);
	}

	$turnstile = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
	if ( ! VH_Auth::validar_turnstile( $turnstile ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Verificação de segurança não concluída. Tente novamente.', 'vapor-hub' ) ),
			403
		);
	}

	$dados = array(
		'nome'      => isset( $_POST['vh_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['vh_nome'] ) ) : '',
		'documento' => isset( $_POST['vh_documento'] ) ? sanitize_text_field( wp_unslash( $_POST['vh_documento'] ) ) : '',
		'whatsapp'  => isset( $_POST['vh_whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['vh_whatsapp'] ) ) : '',
		'email'     => isset( $_POST['vh_email'] ) ? sanitize_email( wp_unslash( $_POST['vh_email'] ) ) : '',
	);

	$meta = array(
		'ip'         => VH_Auth::ip_cliente(),
		'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
		'origem'     => 'popup',
	);

	$resultado = VH_Revenda_Leads_Service::registrar( $dados, $meta );
	if ( is_wp_error( $resultado ) ) {
		wp_send_json_error(
			array( 'message' => $resultado->get_error_message() ),
			(int) ( $resultado->get_error_data()['status'] ?? 400 )
		);
	}

	VH_Revenda_Leads_Service::notificar_loja_por_email( (int) $resultado, $dados );

	wp_send_json_success(
		array( 'message' => __( 'Cadastro enviado com sucesso.', 'vapor-hub' ) )
	);
}
add_action( 'wp_ajax_nopriv_vh_cadastro_revenda', 'vh_ajax_cadastro_revenda' );
add_action( 'wp_ajax_vh_cadastro_revenda', 'vh_ajax_cadastro_revenda' );

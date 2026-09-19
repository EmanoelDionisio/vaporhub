<?php
/**
 * SEO do site público — meta tags, Open Graph, JSON-LD, robots e sitemap.
 *
 * Não interfere no portal Minha Loja (/minha-loja/*), que permanece noindex.
 * Desativa-se automaticamente se um plugin dedicado (Yoast, Rank Math, etc.) estiver ativo.
 *
 * @package VaporHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Camada de SEO nativa do tema filho.
 */
final class VH_SEO {

	/** Comprimento máximo da meta description. */
	private const DESC_MAX = 160;

	/** Cookie que registra a decisão de consentimento do visitante. */
	private const COOKIE_CONSENT = 'vh_consent';

	/** Cache da opção vh_seo durante a requisição. */
	private static ?array $config = null;

	/**
	 * Registra hooks do front-end público.
	 */
	public static function init(): void {
		if ( is_admin() || self::plugin_seo_ativo() ) {
			return;
		}

		add_filter( 'document_title_separator', array( __CLASS__, 'separador_titulo' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'partes_titulo' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_meta_head' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'render_verificacao' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'render_json_ld' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );

		/* Medição e rastreamento (somente se houver ID configurado). */
		add_action( 'wp_head', array( __CLASS__, 'render_tracking_head' ), 5 );
		add_action( 'wp_body_open', array( __CLASS__, 'render_tracking_body' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'render_consent_banner' ), 50 );

		/* Controle do schema de produto do WooCommerce. */
		add_filter( 'woocommerce_structured_data', array( __CLASS__, 'filtrar_schema_produto' ) );
	}

	/**
	 * Opção vh_seo (configurada no painel Minha Loja), com cache por requisição.
	 *
	 * @return array<string, string>
	 */
	private static function config(): array {
		if ( null === self::$config ) {
			$salvo        = get_option( 'vh_seo', array() );
			self::$config = is_array( $salvo ) ? $salvo : array();
		}
		return self::$config;
	}

	/**
	 * Lê um toggle/valor da configuração com fallback.
	 */
	private static function cfg( string $chave, string $padrao = '' ): string {
		$config = self::config();
		$valor  = $config[ $chave ] ?? $padrao;
		return is_string( $valor ) ? $valor : $padrao;
	}

	/**
	 * Detecta plugins de SEO que já gerenciam meta/canonical/schema.
	 */
	private static function plugin_seo_ativo(): bool {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' )
			|| defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	/**
	 * Contexto indexável: vitrine e páginas públicas, exceto Minha Loja.
	 */
	private static function contexto_publico(): bool {
		if ( class_exists( 'VH_Portal' ) && VH_Portal::eh_rota_portal() ) {
			return false;
		}
		return true;
	}

	/**
	 * Separador do <title> — pipe alinhado ao padrão de e-commerce.
	 *
	 * @param string $sep Separador atual.
	 */
	public static function separador_titulo( string $sep ): string {
		return '|';
	}

	/**
	 * Ajusta partes do título em páginas-chave.
	 *
	 * @param array<string, string> $partes Partes do título.
	 * @return array<string, string>
	 */
	public static function partes_titulo( array $partes ): array {
		if ( ! self::contexto_publico() ) {
			return $partes;
		}

		if ( is_front_page() ) {
			$tagline = get_bloginfo( 'description', 'display' );
			if ( $tagline ) {
				$partes['tagline'] = $tagline;
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$partes['title'] = __( 'Loja', 'vapor-hub' );
		}

		return $partes;
	}

	/**
	 * Meta description, canonical, Open Graph e Twitter Cards.
	 */
	public static function render_meta_head(): void {
		if ( ! self::contexto_publico() ) {
			return;
		}

		$desc      = self::meta_description();
		$canonical = self::url_canonical();
		$titulo    = wp_get_document_title();
		$tipo_og   = self::tipo_open_graph();
		$imagem    = self::imagem_compartilhamento();

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}

		if ( $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}

		echo '<meta property="og:locale" content="' . esc_attr( self::locale_og() ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $titulo ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $canonical ) {
			echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
		}
		echo '<meta property="og:type" content="' . esc_attr( $tipo_og ) . '">' . "\n";
		if ( $imagem ) {
			echo '<meta property="og:image" content="' . esc_url( $imagem ) . '">' . "\n";
			echo '<meta property="og:image:alt" content="' . esc_attr( self::texto_alt_imagem( $imagem ) ) . '">' . "\n";
		}

		echo '<meta name="twitter:card" content="' . esc_attr( $imagem ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $titulo ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $imagem ) {
			echo '<meta name="twitter:image" content="' . esc_url( $imagem ) . '">' . "\n";
		}
	}

	/**
	 * Meta tags de verificação de propriedade (Search Console, Bing).
	 *
	 * Renderizadas apenas na página inicial — local checado pelas ferramentas.
	 */
	public static function render_verificacao(): void {
		if ( ! self::contexto_publico() || ! is_front_page() ) {
			return;
		}

		$google = self::cfg( 'google_site_verification' );
		if ( '' !== $google ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $google ) . '">' . "\n";
		}

		$bing = self::cfg( 'bing_site_verification' );
		if ( '' !== $bing ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( $bing ) . '">' . "\n";
		}
	}

	/**
	 * Scripts de medição no <head>: Consent Mode v2 + GA4 + GTM + Meta Pixel.
	 *
	 * Tudo carrega de forma assíncrona; com consentimento ativo, o padrão é
	 * "denied" até o visitante aceitar (Google Consent Mode v2).
	 */
	public static function render_tracking_head(): void {
		if ( ! self::contexto_publico() ) {
			return;
		}

		$ga4   = self::cfg( 'ga4_id' );
		$gtm   = self::cfg( 'gtm_id' );
		$pixel = self::cfg( 'meta_pixel_id' );

		if ( '' === $ga4 && '' === $gtm && '' === $pixel ) {
			return;
		}

		$consent_ativo = '1' === self::cfg( 'consent_mode', '1' );
		$concedido     = self::consentimento_concedido();

		/* dataLayer + Consent Mode v2 (antes de qualquer tag). */
		echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}</script>\n";

		if ( $consent_ativo ) {
			$estado = $concedido ? 'granted' : 'denied';
			echo "<script>gtag('consent','default',{"
				. "'ad_storage':'{$estado}','ad_user_data':'{$estado}','ad_personalization':'{$estado}',"
				. "'analytics_storage':'{$estado}','wait_for_update':500});</script>\n";
		}

		if ( '' !== $gtm ) {
			$gtm_js = esc_js( $gtm );
			echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm_js}');</script>\n";
		}

		if ( '' !== $ga4 ) {
			$ga4_attr = esc_attr( $ga4 );
			$ga4_js   = esc_js( $ga4 );
			echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga4_attr . '"></script>' . "\n";
			echo "<script>gtag('js',new Date());gtag('config','{$ga4_js}');</script>\n";
		}

		if ( '' !== $pixel ) {
			$pixel_js = esc_js( $pixel );
			$ativa    = ( ! $consent_ativo || $concedido ) ? "fbq('init','{$pixel_js}');fbq('track','PageView');" : '';
			echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');{$ativa}</script>\n";
		}
	}

	/**
	 * Tag <noscript> do GTM logo após a abertura do <body>.
	 */
	public static function render_tracking_body(): void {
		if ( ! self::contexto_publico() ) {
			return;
		}
		$gtm = self::cfg( 'gtm_id' );
		if ( '' === $gtm ) {
			return;
		}
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm )
			. '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
	}

	/**
	 * Banner de consentimento de cookies (leve, sem dependências).
	 */
	public static function render_consent_banner(): void {
		if ( ! self::contexto_publico() ) {
			return;
		}
		if ( '1' !== self::cfg( 'consent_mode', '1' ) ) {
			return;
		}
		/* Sem nenhuma tag configurada, não há o que consentir. */
		if ( '' === self::cfg( 'ga4_id' ) && '' === self::cfg( 'gtm_id' ) && '' === self::cfg( 'meta_pixel_id' ) ) {
			return;
		}
		/* Já decidiu: não reexibe. */
		if ( self::consentimento_definido() ) {
			return;
		}

		$texto = self::cfg( 'consent_banner_texto' );
		if ( '' === $texto ) {
			$texto = __( 'Usamos cookies para melhorar sua experiência e medir o tráfego do site.', 'vapor-hub' );
		}
		$cookie = esc_js( self::COOKIE_CONSENT );
		?>
<div id="vh-consent" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Aviso de cookies', 'vapor-hub' ); ?>" style="position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:560px;margin:0 auto;background:#141416;color:#f4f1ea;border-radius:14px;padding:16px 18px;box-shadow:0 10px 30px rgba(0,0,0,.25);display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;font-size:14px;line-height:1.45">
	<span style="flex:1 1 220px"><?php echo esc_html( $texto ); ?></span>
	<span style="display:flex;gap:8px;flex:0 0 auto">
		<button type="button" data-vh-consent="deny" style="cursor:pointer;border:1px solid rgba(255,255,255,.4);background:transparent;color:#fff;border-radius:9px;padding:8px 14px;font-weight:600"><?php esc_html_e( 'Recusar', 'vapor-hub' ); ?></button>
		<button type="button" data-vh-consent="grant" style="cursor:pointer;border:0;background:#c8f542;color:#0a0a0b;border-radius:9px;padding:8px 16px;font-weight:700"><?php esc_html_e( 'Aceitar', 'vapor-hub' ); ?></button>
	</span>
</div>
<script>(function(){var b=document.getElementById('vh-consent');if(!b)return;function set(v){document.cookie='<?php echo $cookie; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>='+v+';path=/;max-age=15552000;SameSite=Lax';if(window.gtag){var s=v==='grant'?'granted':'denied';gtag('consent','update',{ad_storage:s,ad_user_data:s,ad_personalization:s,analytics_storage:s});}b.parentNode.removeChild(b);if(v==='grant'){setTimeout(function(){location.reload();},200);}}b.addEventListener('click',function(e){var t=e.target.closest('[data-vh-consent]');if(!t)return;set(t.getAttribute('data-vh-consent'));});})();</script>
		<?php
	}

	/**
	 * Remove o schema de produto nativo do WooCommerce se desativado no painel.
	 *
	 * @param array<string, mixed> $dados Dados estruturados acumulados.
	 * @return array<string, mixed>
	 */
	public static function filtrar_schema_produto( $dados ) {
		if ( '0' !== self::cfg( 'schema_product', '1' ) ) {
			return $dados;
		}
		if ( ! is_array( $dados ) ) {
			return $dados;
		}
		foreach ( $dados as $i => $item ) {
			$tipo = is_array( $item ) && isset( $item['@type'] ) ? (string) $item['@type'] : '';
			if ( 'Product' === $tipo ) {
				unset( $dados[ $i ] );
			}
		}
		return array_values( $dados );
	}

	/**
	 * Indica se o visitante aceitou cookies.
	 */
	private static function consentimento_concedido(): bool {
		return isset( $_COOKIE[ self::COOKIE_CONSENT ] ) && 'grant' === $_COOKIE[ self::COOKIE_CONSENT ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Indica se o visitante já tomou uma decisão (aceitar ou recusar).
	 */
	private static function consentimento_definido(): bool {
		return isset( $_COOKIE[ self::COOKIE_CONSENT ] ) && in_array( $_COOKIE[ self::COOKIE_CONSENT ], array( 'grant', 'deny' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Schema.org em JSON-LD (Organization, WebSite, BreadcrumbList).
	 */
	public static function render_json_ld(): void {
		if ( ! self::contexto_publico() ) {
			return;
		}

		$grafos = array();

		if ( is_front_page() ) {
			if ( '0' !== self::cfg( 'schema_organization', '1' ) ) {
				$grafos[] = self::schema_organization();
			}
			if ( '0' !== self::cfg( 'schema_website', '1' ) ) {
				$grafos[] = self::schema_website();
			}
		}

		if ( '0' !== self::cfg( 'schema_breadcrumb', '1' ) ) {
			$migalhas = self::schema_breadcrumb();
			if ( $migalhas ) {
				$grafos[] = $migalhas;
			}
		}

		$grafos = array_values( array_filter( $grafos ) );
		if ( empty( $grafos ) ) {
			return;
		}

		if ( count( $grafos ) === 1 ) {
			$payload = array_merge(
				array( '@context' => 'https://schema.org' ),
				$grafos[0]
			);
		} else {
			$payload = array(
				'@context' => 'https://schema.org',
				'@graph'   => $grafos,
			);
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Regras de indexação para rotas utilitárias e filtros da loja.
	 *
	 * @param array<string, bool|string> $robots Diretivas atuais.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( ! self::contexto_publico() ) {
			return array(
				'noindex'  => true,
				'nofollow' => true,
			);
		}

		if ( self::deve_noindex() ) {
			$robots['noindex'] = true;
		}

		return $robots;
	}

	/**
	 * Complementa robots.txt com rotas que não devem ser rastreadas.
	 *
	 * @param string $output Conteúdo atual.
	 * @param bool   $public Site público.
	 */
	public static function robots_txt( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$linhas = array(
			'',
			'# Vapor Hub — rotas administrativas e utilitárias',
			'Disallow: /minha-loja/',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
		);

		if ( function_exists( 'wc_get_cart_url' ) ) {
			$carrinho = wp_parse_url( wc_get_cart_url(), PHP_URL_PATH );
			if ( is_string( $carrinho ) && $carrinho ) {
				$linhas[] = 'Disallow: ' . trailingslashit( $carrinho );
			}
		}
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			$checkout = wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH );
			if ( is_string( $checkout ) && $checkout ) {
				$linhas[] = 'Disallow: ' . trailingslashit( $checkout );
			}
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$conta = wp_parse_url( wc_get_page_permalink( 'myaccount' ), PHP_URL_PATH );
			if ( is_string( $conta ) && $conta ) {
				$linhas[] = 'Disallow: ' . trailingslashit( $conta );
			}
		}

		$linhas[] = 'Sitemap: ' . home_url( '/wp-sitemap.xml' );

		return trim( $output ) . implode( "\n", $linhas ) . "\n";
	}

	/**
	 * Monta meta description contextual.
	 */
	private static function meta_description(): string {
		$desc = '';

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$custom  = get_post_meta( $post_id, '_vh_meta_description', true );
			if ( is_string( $custom ) && '' !== trim( $custom ) ) {
				$desc = $custom;
			} elseif ( has_excerpt( $post_id ) ) {
				$desc = get_the_excerpt( $post_id );
			} else {
				$desc = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
			}
		} elseif ( is_product_taxonomy() ) {
			$termo = get_queried_object();
			if ( $termo instanceof WP_Term && ! empty( $termo->description ) ) {
				$desc = $termo->description;
			} elseif ( $termo instanceof WP_Term ) {
				$desc = sprintf(
					/* translators: %s: category name */
					__( 'Confira produtos de %s na loja Vapor Hub.', 'vapor-hub' ),
					$termo->name
				);
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$desc = __( 'Catálogo de pods, e-líquidos, vaporizadores e acessórios. Compre online na Vapor Hub.', 'vapor-hub' );
		} elseif ( is_front_page() ) {
			$desc = self::cfg( 'meta_description' );
			if ( '' === $desc ) {
				$desc = get_bloginfo( 'description', 'display' );
			}
			if ( ! $desc ) {
				$rodape = get_option( 'vh_rodape', array() );
				if ( is_array( $rodape ) && ! empty( $rodape['descricao'] ) ) {
					$desc = $rodape['descricao'];
				}
			}
		} elseif ( is_search() ) {
			$desc = sprintf(
				/* translators: %s: search query */
				__( 'Resultados da busca por "%s" na Vapor Hub.', 'vapor-hub' ),
				get_search_query()
			);
		}

		$desc = apply_filters( 'vh_meta_description', $desc );
		return self::truncar_texto( wp_strip_all_tags( (string) $desc ) );
	}

	/**
	 * URL canônica — limpa parâmetros de filtro da vitrine.
	 */
	private static function url_canonical(): string {
		if ( is_singular() ) {
			return get_permalink();
		}

		if ( is_product_taxonomy() ) {
			$termo = get_queried_object();
			if ( $termo instanceof WP_Term ) {
				$url = get_term_link( $termo );
				return is_wp_error( $url ) ? '' : (string) $url;
			}
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			if ( self::tem_filtros_loja_get() ) {
				return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
			}
			return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_search() ) {
			return get_search_link();
		}

		return '';
	}

	/**
	 * Indica se a URL atual deve sair do índice.
	 */
	private static function deve_noindex(): bool {
		if ( is_404() ) {
			return true;
		}
		if ( is_search() ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
			return true;
		}
		if ( self::tem_filtros_loja_get() ) {
			return true;
		}

		return false;
	}

	/**
	 * Parâmetros GET de filtro da vitrine (URLs compartilháveis).
	 */
	private static function tem_filtros_loja_get(): bool {
		if ( ! class_exists( 'VH_Loja_Filtros' ) ) {
			return false;
		}

		$params = VH_Loja_Filtros::params_url();
		$params = array_diff( $params, array( 'paged' ) );
		$params[] = 'orderby';
		$params[] = 'categoria';

		foreach ( $params as $param ) {
			if ( isset( $_GET[ $param ] ) && '' !== (string) wp_unslash( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}
		}

		return false;
	}

	/**
	 * Tipo Open Graph conforme o contexto.
	 */
	private static function tipo_open_graph(): string {
		if ( is_singular( 'product' ) ) {
			return 'product';
		}
		if ( is_singular() ) {
			return 'article';
		}
		return 'website';
	}

	/**
	 * Imagem para compartilhamento social.
	 */
	private static function imagem_compartilhamento(): string {
		if ( is_singular() && has_post_thumbnail() ) {
			$url = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
			if ( $url ) {
				return $url;
			}
		}

		$og = self::cfg( 'og_image' );
		if ( '' !== $og ) {
			return $og;
		}

		if ( function_exists( 'vh_identidade_visual_atual' ) ) {
			$id = vh_identidade_visual_atual();
			if ( ! empty( $id['logo_url'] ) ) {
				return esc_url_raw( $id['logo_url'] );
			}
		}

		if ( has_custom_logo() ) {
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				$url = wp_get_attachment_image_url( $logo_id, 'full' );
				if ( $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Texto alternativo da imagem OG quando disponível.
	 */
	private static function texto_alt_imagem( string $url ): string {
		if ( is_singular() && has_post_thumbnail() ) {
			$alt = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
			if ( is_string( $alt ) && '' !== trim( $alt ) ) {
				return $alt;
			}
			return get_the_title();
		}
		return get_bloginfo( 'name' );
	}

	/**
	 * Locale Open Graph a partir do WordPress.
	 */
	private static function locale_og(): string {
		$locale = get_locale();
		if ( str_starts_with( $locale, 'pt' ) ) {
			return 'pt_BR';
		}
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Organization — home e dados da marca.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function schema_organization(): ?array {
		$rodape = get_option( 'vh_rodape', array() );
		$social = array();
		if ( is_array( $rodape ) ) {
			foreach ( array( 'instagram', 'youtube', 'facebook' ) as $rede ) {
				if ( ! empty( $rodape[ $rede ] ) ) {
					$social[] = esc_url_raw( $rodape[ $rede ] );
				}
			}
		}

		$org = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		$logo = self::imagem_compartilhamento();
		if ( $logo ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}
		if ( $social ) {
			$org['sameAs'] = $social;
		}

		return $org;
	}

	/**
	 * WebSite + SearchAction na home.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function schema_website(): ?array {
		$site = array(
			'@type' => 'WebSite',
			'@id'   => home_url( '/#website' ),
			'url'   => home_url( '/' ),
			'name'  => get_bloginfo( 'name' ),
		);

		$desc = get_bloginfo( 'description', 'display' );
		if ( $desc ) {
			$site['description'] = self::truncar_texto( wp_strip_all_tags( $desc ) );
		}

		$site['publisher'] = array( '@id' => home_url( '/#organization' ) );
		$site['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);

		return $site;
	}

	/**
	 * BreadcrumbList para produto, loja e taxonomias.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function schema_breadcrumb(): ?array {
		$itens = array();
		$pos   = 1;

		$itens[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => __( 'Início', 'vapor-hub' ),
			'item'     => home_url( '/' ),
		);

		if ( function_exists( 'is_product' ) && is_product() ) {
			$loja = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
			$itens[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => __( 'Loja', 'vapor-hub' ),
				'item'     => $loja,
			);

			$cats = get_the_terms( get_queried_object_id(), 'product_cat' );
			if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
				$cat = $cats[0];
				$link = get_term_link( $cat );
				if ( ! is_wp_error( $link ) ) {
					$itens[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => $cat->name,
						'item'     => $link,
					);
				}
			}

			$itens[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			);
		} elseif ( is_product_taxonomy() ) {
			$loja = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' );
			$itens[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => __( 'Loja', 'vapor-hub' ),
				'item'     => $loja,
			);
			$termo = get_queried_object();
			if ( $termo instanceof WP_Term ) {
				$link = get_term_link( $termo );
				if ( ! is_wp_error( $link ) ) {
					$itens[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => $termo->name,
						'item'     => $link,
					);
				}
			}
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$itens[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => __( 'Loja', 'vapor-hub' ),
				'item'     => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/loja/' ),
			);
		} else {
			return null;
		}

		if ( count( $itens ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => self::url_canonical() . '#breadcrumb',
			'itemListElement' => $itens,
		);
	}

	/**
	 * Limita texto para meta description.
	 */
	private static function truncar_texto( string $texto ): string {
		$texto = trim( preg_replace( '/\s+/u', ' ', $texto ) ?? '' );
		if ( '' === $texto ) {
			return '';
		}
		if ( mb_strlen( $texto ) <= self::DESC_MAX ) {
			return $texto;
		}
		return rtrim( mb_substr( $texto, 0, self::DESC_MAX - 1 ) ) . '…';
	}
}

VH_SEO::init();

<?php
/**
 * Registro e sanitização de opções do plugin.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Settings {

    /* ── Opções registradas ── */
    private static array $opcoes = [
        'vh_beneficios',
        'vh_comunidade',
        'vh_revenda',
        'vh_hero',
        'vh_home',
        'vh_rodape',
        'vh_identidade_visual',
        'vh_seguranca',
        'vh_seo',
        'vh_tiny_erp',
    ];

    /**
     * Registra todas as opções via Settings API.
     */
    public static function registrar_opcoes(): void {
        add_action( 'admin_init', [ __CLASS__, 'registrar' ] );
    }

    public static function registrar(): void {
        register_setting( 'vh_aparencia_grupo', 'vh_beneficios', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_beneficios' ],
            'default'           => self::beneficios_padrao(),
        ] );

        register_setting( 'vh_aparencia_grupo', 'vh_hero', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_hero' ],
            'default'           => [],
        ] );

        register_setting( 'vh_aparencia_grupo', 'vh_home', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_home' ],
            'default'           => self::home_padrao(),
        ] );

        register_setting( 'vh_aparencia_grupo', 'vh_rodape', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_rodape' ],
            'default'           => [],
        ] );

        register_setting( 'vh_aparencia_grupo', 'vh_identidade_visual', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_identidade_visual' ],
            'default'           => self::identidade_visual_padrao(),
        ] );

        register_setting( 'vh_comunidade_grupo', 'vh_comunidade', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_comunidade' ],
            'default'           => [],
        ] );

        register_setting( 'vh_revenda_grupo', 'vh_revenda', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_revenda' ],
            'default'           => [],
        ] );

        register_setting( 'vh_seguranca_grupo', 'vh_seguranca', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_seguranca' ],
            'default'           => self::seguranca_padrao(),
        ] );

        register_setting( 'vh_seo_grupo', 'vh_seo', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitizar_seo' ],
            'default'           => self::seo_padrao(),
        ] );
    }

    /* ────────────────────────────────────────────
     * Sanitizações
     * ──────────────────────────────────────────── */

    public static function sanitizar_beneficios( $input ): array {
        $permitidos = array_keys( self::icones_beneficio_disponiveis() );
        $limpo      = [];

        if ( ! is_array( $input ) ) {
            return self::beneficios_padrao();
        }

        foreach ( array_slice( $input, 0, 8 ) as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $titulo    = sanitize_text_field( $item['titulo'] ?? '' );
            $descricao = sanitize_text_field( $item['descricao'] ?? '' );

            if ( '' === trim( $titulo ) && '' === trim( $descricao ) ) {
                continue;
            }

            $icone = sanitize_text_field( $item['icone'] ?? 'check-circle' );
            if ( ! in_array( $icone, $permitidos, true ) ) {
                $icone = 'check-circle';
            }

            $limpo[] = [
                'icone'     => $icone,
                'titulo'    => $titulo,
                'descricao' => $descricao,
            ];
        }

        return ! empty( $limpo ) ? $limpo : self::beneficios_padrao();
    }

    /**
     * Ícones disponíveis na barra de benefícios (admin + front).
     *
     * @return array<string, string>
     */
    public static function icones_beneficio_disponiveis(): array {
        if ( function_exists( 'vh_menu_icones' ) ) {
            return vh_menu_icones();
        }

        return [
            'check-circle' => __( 'Conferido', 'vapor-hub-loja' ),
            'settings'     => __( 'Ajuste', 'vapor-hub-loja' ),
            'truck'        => __( 'Entrega', 'vapor-hub-loja' ),
            'users'        => __( 'Pessoas', 'vapor-hub-loja' ),
            'star'         => __( 'Favorito', 'vapor-hub-loja' ),
            'heart'        => __( 'Coração', 'vapor-hub-loja' ),
            'shield'       => __( 'Proteção', 'vapor-hub-loja' ),
            'gift'         => __( 'Presente', 'vapor-hub-loja' ),
        ];
    }

    /**
     * Botão que abre a biblioteca visual. O valor gravado fica no campo oculto.
     */
    public static function botao_icone( string $name, string $slug, bool $permite_vazio = false ): string {
        $slug = sanitize_key( $slug );
        $mapa = self::icones_beneficio_disponiveis();
        if ( ! isset( $mapa[ $slug ] ) ) {
            $slug = $permite_vazio ? '' : 'check-circle';
        }
        $svg = ( '' !== $slug && function_exists( 'vh_menu_icone_html' ) ) ? vh_menu_icone_html( $slug ) : '';
        $classe = 'vh-menu-icone-abrir' . ( '' !== $slug ? ' tem-icone' : '' );
        $vazio  = $permite_vazio ? ' data-vh-icone-vazio="1"' : '';

        return '<div class="vh-menu-icone-linha">'
            . '<button type="button" class="' . esc_attr( $classe ) . '" aria-haspopup="listbox" aria-expanded="false" aria-label="' . esc_attr__( 'Ícone', 'vapor-hub-loja' ) . '"' . $vazio . '>'
            . '<span class="vh-menu-icone-atual">' . $svg . '</span></button>'
            . '<input type="hidden" class="vh-beneficio-icone vh-menu-icone" name="' . esc_attr( $name ) . '" value="' . esc_attr( $slug ) . '" />'
            . '</div>';
    }

    public static function sanitizar_hero( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $usar_slides = ! empty( $input['usar_slides'] ) ? '1' : '0';

        /*
         * Slides do banner (máx. 8). Cada slide tem a imagem para computador
         * (url) e, opcionalmente, uma imagem dedicada para celular (url_mobile).
         * Sem a versão mobile, o tema reaproveita a imagem de computador.
         */
        $slides = [];
        if ( '1' === $usar_slides && ! empty( $input['slides'] ) && is_array( $input['slides'] ) ) {
            foreach ( array_slice( $input['slides'], 0, 8 ) as $slide ) {
                if ( is_array( $slide ) ) {
                    $url        = esc_url_raw( $slide['url'] ?? '' );
                    $url_mobile = esc_url_raw( $slide['url_mobile'] ?? '' );
                } else {
                    $url        = esc_url_raw( (string) $slide );
                    $url_mobile = '';
                }
                if ( '' !== $url ) {
                    $slides[] = [ 'url' => $url, 'url_mobile' => $url_mobile ];
                }
            }
        }

        $nav = sanitize_key( (string) ( $input['slides_navegacao'] ?? 'dots' ) );
        if ( ! in_array( $nav, [ 'dots', 'setas', 'ambos', 'nenhum' ], true ) ) {
            $nav = 'dots';
        }

        return [
            'badge_texto'              => sanitize_text_field( $input['badge_texto'] ?? '' ),
            'titulo'                   => sanitize_text_field( $input['titulo'] ?? '' ),
            'subtitulo'                => sanitize_text_field( $input['subtitulo'] ?? '' ),
            'botao_texto'              => sanitize_text_field( $input['botao_texto'] ?? '' ),
            'botao_link'               => esc_url_raw( $input['botao_link'] ?? '' ),
            'botao_secundario_texto'   => sanitize_text_field( $input['botao_secundario_texto'] ?? '' ),
            'botao_secundario_link'    => esc_url_raw( $input['botao_secundario_link'] ?? '' ),
            /* Modo carrossel: imagem única invalidada. Modo imagem única: slides invalidados. */
            'imagem_fundo'             => '1' === $usar_slides ? '' : esc_url_raw( $input['imagem_fundo'] ?? '' ),
            'usar_slides'              => $usar_slides,
            'slides'                   => $slides,
            'slides_navegacao'         => $nav,
        ];
    }

    /**
     * Textos da página inicial. A ordem dos blocos fica no tema.
     *
     * @return array<string,string>
     */
    public static function home_padrao(): array {
        return [
            'destaques_mostrar'  => '1',
            'destaques_rotulo'   => 'Mais procurados',
            'destaques_titulo'   => 'Destaques',
            'categorias_mostrar' => '1',
            'categorias_rotulo'  => 'Navegue por categoria',
            'categorias_titulo'  => 'O que você procura',
            'oferta_mostrar'     => '1',
            'oferta_titulo'      => 'Produtos em promoção',
            'oferta_texto'       => 'Peças e sabores com preço reduzido, no mesmo catálogo da loja.',
            'oferta_botao'       => 'Conferir ofertas',
            'oferta_link'        => '',
            'vendidos_mostrar'   => '1',
            'vendidos_rotulo'    => 'Mais vendidos',
            'vendidos_titulo'    => 'Mais vendidos',
            'chamada_mostrar'    => '1',
            'chamada_rotulo'     => 'A loja',
            'chamada_titulo'     => 'Escolha no seu tempo',
            'chamada_texto'      => 'Pods, líquidos e acessórios no mesmo catálogo.',
            'chamada_botao'      => 'Ir para a loja',
            'chamada_link'       => '',
        ];
    }

    /**
     * @param mixed $input
     * @return array<string,string>
     */
    public static function sanitizar_home( $input ): array {
        $padrao = self::home_padrao();
        $atual  = self::obter( 'vh_home', $padrao );
        if ( ! is_array( $atual ) ) {
            $atual = [];
        }
        if ( ! is_array( $input ) ) {
            $input = [];
        }
        $input = array_merge( $padrao, $atual, $input );

        $limpo = [];
        foreach ( [ 'destaques_mostrar', 'categorias_mostrar', 'oferta_mostrar', 'vendidos_mostrar', 'chamada_mostrar' ] as $chave ) {
            $limpo[ $chave ] = ! empty( $input[ $chave ] ) && '0' !== (string) $input[ $chave ] ? '1' : '0';
        }

        $textos = [
            'destaques_rotulo'  => 40,
            'destaques_titulo'  => 80,
            'categorias_rotulo' => 40,
            'categorias_titulo' => 80,
            'oferta_titulo'     => 80,
            'oferta_texto'      => 180,
            'oferta_botao'      => 40,
            'vendidos_rotulo'   => 40,
            'vendidos_titulo'   => 80,
            'chamada_rotulo'    => 40,
            'chamada_titulo'    => 80,
            'chamada_texto'     => 180,
            'chamada_botao'     => 40,
        ];
        foreach ( $textos as $chave => $limite ) {
            $valor = sanitize_text_field( (string) ( $input[ $chave ] ?? '' ) );
            if ( function_exists( 'mb_substr' ) ) {
                $valor = mb_substr( $valor, 0, $limite );
            } else {
                $valor = substr( $valor, 0, $limite );
            }
            $limpo[ $chave ] = '' !== $valor ? $valor : $padrao[ $chave ];
        }

        foreach ( [ 'oferta_link', 'chamada_link' ] as $chave_link ) {
            $link = trim( (string) ( $input[ $chave_link ] ?? '' ) );
            if ( '' === $link ) {
                $limpo[ $chave_link ] = '';
            } elseif ( class_exists( 'VH_Menus_Service' ) ) {
                $limpo[ $chave_link ] = VH_Menus_Service::url_permitida( $link );
            } else {
                $limpo[ $chave_link ] = esc_url_raw( $link );
            }
        }

        return $limpo;
    }

    public static function sanitizar_rodape( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }
        return [
            'descricao' => sanitize_textarea_field( $input['descricao'] ?? '' ),
            'instagram' => esc_url_raw( $input['instagram'] ?? '' ),
            'youtube'   => esc_url_raw( $input['youtube'] ?? '' ),
            'facebook'  => esc_url_raw( $input['facebook'] ?? '' ),
        ];
    }

    public static function sanitizar_identidade_visual( $input ): array {
        $padrao = self::identidade_visual_padrao();
        $atual  = self::obter( 'vh_identidade_visual', $padrao );
        if ( ! is_array( $atual ) ) {
            $atual = $padrao;
        }
        if ( ! is_array( $input ) ) {
            $input = [];
        }
        $input = array_merge( $atual, $input );
        if ( ! is_array( $input ) ) {
            return $padrao;
        }

        $fonte_titulo = sanitize_text_field( $input['fonte_titulo'] ?? $padrao['fonte_titulo'] );
        if ( ! in_array( $fonte_titulo, array( 'plus_jakarta', 'montserrat', 'poppins', 'raleway', 'nunito' ), true ) ) {
            $fonte_titulo = $padrao['fonte_titulo'];
        }

        $fonte_corpo = sanitize_text_field( $input['fonte_corpo'] ?? $padrao['fonte_corpo'] );
        if ( ! in_array( $fonte_corpo, array( 'plus_jakarta', 'inter', 'open_sans', 'lato', 'roboto' ), true ) ) {
            $fonte_corpo = $padrao['fonte_corpo'];
        }

        $estilo_card = sanitize_text_field( $input['estilo_card'] ?? $padrao['estilo_card'] );
        if ( ! in_array( $estilo_card, array( 'suave', 'moderno', 'vibrante' ), true ) ) {
            $estilo_card = $padrao['estilo_card'];
        }

        $tamanho_titulo = sanitize_key( (string) ( $input['tamanho_titulo_produto'] ?? $padrao['tamanho_titulo_produto'] ) );
        if ( ! in_array( $tamanho_titulo, array( 'compacto', 'equilibrado', 'destaque' ), true ) ) {
            $tamanho_titulo = $padrao['tamanho_titulo_produto'];
        }

        $raio_card = absint( $input['raio_card'] ?? $padrao['raio_card'] );
        $raio_card = max( 6, min( 28, $raio_card ) );

        return [
            'logo_url'           => esc_url_raw( $input['logo_url'] ?? '' ),
            'logo_escuro_url'   => esc_url_raw( $input['logo_escuro_url'] ?? '' ),
            'logo_painel_url'   => esc_url_raw( $input['logo_painel_url'] ?? '' ),
            'favicon_url'       => esc_url_raw( $input['favicon_url'] ?? '' ),
            'fonte_titulo'      => $fonte_titulo,
            'fonte_corpo'       => $fonte_corpo,
            'cor_primaria'      => sanitize_hex_color( $input['cor_primaria'] ?? '' ) ?: $padrao['cor_primaria'],
            'cor_primaria_hover'=> sanitize_hex_color( $input['cor_primaria_hover'] ?? '' ) ?: $padrao['cor_primaria_hover'],
            'cor_fundo'         => sanitize_hex_color( $input['cor_fundo'] ?? '' ) ?: $padrao['cor_fundo'],
            'cor_superficie'    => sanitize_hex_color( $input['cor_superficie'] ?? '' ) ?: $padrao['cor_superficie'],
            'cor_texto'         => sanitize_hex_color( $input['cor_texto'] ?? '' ) ?: $padrao['cor_texto'],
            'cor_texto_suave'   => sanitize_hex_color( $input['cor_texto_suave'] ?? '' ) ?: $padrao['cor_texto_suave'],
            'cor_borda'          => sanitize_hex_color( $input['cor_borda'] ?? '' ) ?: $padrao['cor_borda'],
            'cor_fundo_escuro'  => sanitize_hex_color( $input['cor_fundo_escuro'] ?? '' ) ?: $padrao['cor_fundo_escuro'],
            'cor_superficie_escuro' => sanitize_hex_color( $input['cor_superficie_escuro'] ?? '' ) ?: $padrao['cor_superficie_escuro'],
            'cor_texto_escuro'  => sanitize_hex_color( $input['cor_texto_escuro'] ?? '' ) ?: $padrao['cor_texto_escuro'],
            'cor_texto_suave_escuro' => sanitize_hex_color( $input['cor_texto_suave_escuro'] ?? '' ) ?: $padrao['cor_texto_suave_escuro'],
            'cor_borda_escuro'  => sanitize_hex_color( $input['cor_borda_escuro'] ?? '' ) ?: $padrao['cor_borda_escuro'],
            'estilo_card'       => $estilo_card,
            'tamanho_titulo_produto' => $tamanho_titulo,
            'raio_card'         => (string) $raio_card,
        ];
    }

    public static function sanitizar_comunidade( $input ): array {
        $limpo = [];
        if ( ! is_array( $input ) ) {
            return [];
        }
        foreach ( array_slice( $input, 0, 6 ) as $item ) {
            if ( empty( $item['imagem_url'] ) && empty( $item['usuario'] ) ) {
                continue;
            }
            $limpo[] = [
                'imagem_url' => esc_url_raw( $item['imagem_url'] ?? '' ),
                'usuario'    => sanitize_text_field( $item['usuario'] ?? '' ),
                'link'       => esc_url_raw( $item['link'] ?? '' ),
            ];
        }
        return $limpo;
    }

    public static function sanitizar_seguranca( $input ): array {
        if ( ! is_array( $input ) ) {
            return self::seguranca_padrao();
        }

        return [
            'turnstile_ativo'      => ! empty( $input['turnstile_ativo'] ) ? '1' : '0',
            'turnstile_site_key'   => sanitize_text_field( $input['turnstile_site_key'] ?? '' ),
            'turnstile_secret_key' => sanitize_text_field( $input['turnstile_secret_key'] ?? '' ),
        ];
    }

    /**
     * Sanitiza as configurações de SEO e métricas.
     *
     * Todos os valores aqui são públicos (IDs de tag, códigos de verificação,
     * URLs e toggles). Nenhuma chave secreta — integrações OAuth (Fase 2) terão
     * armazenamento próprio e criptografado.
     *
     * @param mixed $input
     * @return array<string,string>
     */
    public static function sanitizar_seo( $input ): array {
        $padrao = self::seo_padrao();
        if ( ! is_array( $input ) ) {
            return $padrao;
        }

        $ga4 = strtoupper( trim( (string) ( $input['ga4_id'] ?? '' ) ) );
        if ( '' !== $ga4 && ! preg_match( '/^G-[A-Z0-9]{4,20}$/', $ga4 ) ) {
            $ga4 = '';
        }

        $gtm = strtoupper( trim( (string) ( $input['gtm_id'] ?? '' ) ) );
        if ( '' !== $gtm && ! preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $gtm ) ) {
            $gtm = '';
        }

        $pixel = preg_replace( '/[^0-9]/', '', (string) ( $input['meta_pixel_id'] ?? '' ) );

        return [
            'google_site_verification' => self::extrair_token_verificacao( (string) ( $input['google_site_verification'] ?? '' ) ),
            'bing_site_verification'   => self::extrair_token_verificacao( (string) ( $input['bing_site_verification'] ?? '' ) ),
            'ga4_id'                   => $ga4,
            'gtm_id'                   => $gtm,
            'meta_pixel_id'            => $pixel,
            'consent_mode'             => ! empty( $input['consent_mode'] ) ? '1' : '0',
            'consent_banner_texto'     => sanitize_text_field( $input['consent_banner_texto'] ?? '' ),
            'meta_description'         => sanitize_textarea_field( $input['meta_description'] ?? '' ),
            'og_image'                 => esc_url_raw( $input['og_image'] ?? '' ),
            'schema_organization'      => ! empty( $input['schema_organization'] ) ? '1' : '0',
            'schema_website'           => ! empty( $input['schema_website'] ) ? '1' : '0',
            'schema_breadcrumb'        => ! empty( $input['schema_breadcrumb'] ) ? '1' : '0',
            'schema_product'           => ! empty( $input['schema_product'] ) ? '1' : '0',
            'google_client_id'         => sanitize_text_field( $input['google_client_id'] ?? '' ),
            'ga4_property_id'          => preg_replace( '/[^0-9]/', '', (string) ( $input['ga4_property_id'] ?? '' ) ),
        ];
    }

    /**
     * Extrai o token de uma meta tag de verificação.
     *
     * Aceita tanto o código puro quanto a tag completa colada pelo usuário
     * (ex.: <meta name="google-site-verification" content="ABC123" />).
     */
    private static function extrair_token_verificacao( string $valor ): string {
        $valor = trim( $valor );
        if ( '' === $valor ) {
            return '';
        }
        if ( preg_match( '/content=["\']([^"\']+)["\']/i', $valor, $m ) ) {
            $valor = $m[1];
        }
        return preg_replace( '/[^A-Za-z0-9_\-]/', '', $valor );
    }

    /**
     * Textos e flags padrão da seção de revenda.
     *
     * @return array<string,string>
     */
    public static function revenda_padrao(): array {
        return [
            'whatsapp'        => '',
            'titulo'          => __( 'Seja um revendedor Vapor Hub', 'vapor-hub-loja' ),
            'descricao'       => __( 'Tenha acesso a preços especiais, kit de mostruário e suporte dedicado. Junte-se a mais de 200 revendedores em todo o Brasil.', 'vapor-hub-loja' ),
            'botao_texto'     => __( 'Quero ser revendedor', 'vapor-hub-loja' ),
            'url_externa'     => '',
            'mostrar'         => '1',
            'notificar_email' => '1',
            'modo'            => 'popup',
        ];
    }

    public static function sanitizar_revenda( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }
        $modo = sanitize_text_field( $input['modo'] ?? '' );
        /* "ajax" era valor legado — trata como envio por e-mail (popup). */
        if ( 'ajax' === $modo ) {
            $modo = 'popup';
        }
        if ( ! in_array( $modo, array( 'whatsapp', 'popup' ), true ) ) {
            $modo = 'popup';
        }

        return [
            'whatsapp'     => preg_replace( '/[^0-9]/', '', $input['whatsapp'] ?? '' ),
            'titulo'       => sanitize_text_field( $input['titulo'] ?? '' ),
            'descricao'    => sanitize_textarea_field( $input['descricao'] ?? '' ),
            'botao_texto'  => sanitize_text_field( $input['botao_texto'] ?? '' ),
            'url_externa'  => esc_url_raw( $input['url_externa'] ?? '' ),
            'mostrar'         => ! empty( $input['mostrar'] ) ? '1' : '0',
            'notificar_email' => ! empty( $input['notificar_email'] ) ? '1' : '0',
            /* popup = formulário no site; whatsapp = envio direto pelo wa.me */
            'modo'            => $modo,
        ];
    }

    /* ────────────────────────────────────────────
     * Helpers
     * ──────────────────────────────────────────── */

    public static function obter( string $chave, array $padrao = [] ): array {
        $valor = get_option( $chave, $padrao );
        return is_array( $valor ) ? $valor : $padrao;
    }

    /**
     * Revenda com textos padrão quando campos salvos estão vazios.
     *
     * @return array<string,string>
     */
    public static function obter_revenda(): array {
        return self::mesclar_com_padrao( self::obter( 'vh_revenda' ), self::revenda_padrao() );
    }

    /**
     * Mescla um array salvo com os padrões, descartando strings vazias.
     *
     * Corrige o efeito colateral de campos salvos como string vazia (ex.: hero),
     * que de outra forma sobrescreveriam textos padrão por valores em branco.
     *
     * @param array<string,mixed> $salvo
     * @param array<string,mixed> $padrao
     * @return array<string,mixed>
     */
    public static function mesclar_com_padrao( array $salvo, array $padrao ): array {
        $resultado = $padrao;
        foreach ( $salvo as $chave => $valor ) {
            if ( is_string( $valor ) && '' === trim( $valor ) ) {
                continue;
            }
            $resultado[ $chave ] = $valor;
        }
        return $resultado;
    }

    public static function beneficios_padrao(): array {
        return [
            [ 'icone' => 'truck',        'titulo' => 'Envio para todo o Brasil', 'descricao' => 'Calcule o CEP no produto' ],
            [ 'icone' => 'check-circle', 'titulo' => 'PIX na hora',              'descricao' => 'Pagamento instantâneo' ],
            [ 'icone' => 'settings',     'titulo' => 'Parcelamento',             'descricao' => 'No cartão, na vitrine' ],
            [ 'icone' => 'shield',       'titulo' => 'Compra segura',            'descricao' => 'Site protegido' ],
        ];
    }

    /**
     * Dashicon do preview admin conforme slug do benefício.
     */
    public static function dashicon_beneficio( string $slug ): string {
        $mapa = [
            'check-circle' => 'dashicons-yes-alt',
            'settings'     => 'dashicons-admin-generic',
            'truck'        => 'dashicons-cart',
            'users'        => 'dashicons-groups',
            'star'         => 'dashicons-star-filled',
            'heart'        => 'dashicons-heart',
            'shield'       => 'dashicons-shield',
            'gift'         => 'dashicons-awards',
        ];

        return $mapa[ $slug ] ?? 'dashicons-yes-alt';
    }

    public static function seguranca_padrao(): array {
        return [
            'turnstile_ativo'      => '0',
            'turnstile_site_key'   => '',
            'turnstile_secret_key' => '',
        ];
    }

    public static function seo_padrao(): array {
        return [
            'google_site_verification' => '',
            'bing_site_verification'   => '',
            'ga4_id'                   => '',
            'gtm_id'                   => '',
            'meta_pixel_id'            => '',
            'consent_mode'             => '1',
            'consent_banner_texto'     => '',
            'meta_description'         => '',
            'og_image'                 => '',
            'schema_organization'      => '1',
            'schema_website'           => '1',
            'schema_breadcrumb'        => '1',
            'schema_product'           => '1',
            'google_client_id'         => '',
            'ga4_property_id'          => '',
        ];
    }

    public static function identidade_visual_padrao(): array {
        return [
            'logo_url'               => '',
            'logo_escuro_url'        => '',
            'logo_painel_url'        => '',
            'favicon_url'            => '',
            'fonte_titulo'       => 'plus_jakarta',
            'fonte_corpo'        => 'plus_jakarta',
            'cor_primaria'       => '#7618f1',
            'cor_primaria_hover' => '#5c10d0',
            'cor_fundo'          => '#f6f4fb',
            'cor_superficie'     => '#ffffff',
            'cor_texto'          => '#1a1228',
            'cor_texto_suave'    => '#6b6680',
            'cor_borda'              => '#e4dff0',
            'cor_fundo_escuro'       => '#0e0b14',
            'cor_superficie_escuro'  => '#17141f',
            'cor_texto_escuro'       => '#f4f1fa',
            'cor_texto_suave_escuro' => '#9b94ab',
            'cor_borda_escuro'       => '#2c2738',
            'estilo_card'        => 'suave',
            'tamanho_titulo_produto' => 'equilibrado',
            'raio_card'          => '20',
        ];
    }

    /**
     * Marca do painel. Sem arquivo próprio, usa a marca do modo claro.
     */
    public static function logo_painel_url(): string {
        $id = self::obter( 'vh_identidade_visual', self::identidade_visual_padrao() );
        if ( ! is_array( $id ) ) {
            return '';
        }
        $painel = esc_url_raw( (string) ( $id['logo_painel_url'] ?? '' ) );
        if ( '' !== $painel ) {
            return $painel;
        }
        return esc_url_raw( (string) ( $id['logo_url'] ?? '' ) );
    }

    /**
     * Marca do painel pintada com a cor principal, sem uma segunda arte.
     */
    public static function html_marca_painel( string $classe ): string {
        $url = self::logo_painel_url();
        if ( '' === $url ) {
            return '';
        }

        return sprintf(
            '<span class="vh-marca-painel %1$s" style="--vh-marca-url:url(\'%2$s\')" role="img" aria-label="%3$s"></span>',
            esc_attr( $classe ),
            esc_url( $url ),
            esc_attr( get_bloginfo( 'name' ) )
        );
    }

    public static function css_tokens_marca(): string {
        $id = self::obter( 'vh_identidade_visual', self::identidade_visual_padrao() );
        if ( ! is_array( $id ) ) {
            $id = array();
        }
        $id    = wp_parse_args( $id, self::identidade_visual_padrao() );
        $prim  = sanitize_hex_color( (string) ( $id['cor_primaria'] ?? '' ) ) ?: '#7618f1';
        $hover = sanitize_hex_color( (string) ( $id['cor_primaria_hover'] ?? '' ) ) ?: '#5c10d0';
        $hex   = ltrim( strtolower( $prim ), '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $leve = '#efe7fe';
        if ( 6 === strlen( $hex ) && ctype_xdigit( $hex ) ) {
            $leve = sprintf(
                'rgba(%d,%d,%d,0.14)',
                hexdec( substr( $hex, 0, 2 ) ),
                hexdec( substr( $hex, 2, 2 ) ),
                hexdec( substr( $hex, 4, 2 ) )
            );
        }

        return sprintf(
            ':root{--vh-primario:%1$s;--vh-primario-hover:%2$s;--vh-primario-light:%3$s;--vh-escuro:#1a1228;}
.vh-marca-painel{display:block;background-color:var(--vh-primario);-webkit-mask-image:var(--vh-marca-url);mask-image:var(--vh-marca-url);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}',
            $prim,
            $hover,
            $leve
        );
    }

    /**
     * Sincroniza o ícone do site WordPress (aba do navegador) com a URL do painel.
     */
    public static function sincronizar_favicon_wp( string $url ): void {
        $url = esc_url_raw( $url );
        if ( '' === $url ) {
            delete_option( 'site_icon' );
            return;
        }

        $id = attachment_url_to_postid( $url );
        if ( $id > 0 ) {
            update_option( 'site_icon', (int) $id );
        }
    }
}

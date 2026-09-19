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
        return [
            'check-circle' => __( 'Check Circle', 'vapor-hub-loja' ),
            'settings'     => __( 'Engrenagem', 'vapor-hub-loja' ),
            'truck'        => __( 'Caminhão', 'vapor-hub-loja' ),
            'users'        => __( 'Usuários', 'vapor-hub-loja' ),
            'star'         => __( 'Estrela', 'vapor-hub-loja' ),
            'heart'        => __( 'Coração', 'vapor-hub-loja' ),
            'shield'       => __( 'Escudo', 'vapor-hub-loja' ),
            'gift'         => __( 'Presente', 'vapor-hub-loja' ),
        ];
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

        $raio_card = absint( $input['raio_card'] ?? $padrao['raio_card'] );
        $raio_card = max( 6, min( 28, $raio_card ) );

        return [
            'logo_url'          => esc_url_raw( $input['logo_url'] ?? '' ),
            'favicon_url'       => esc_url_raw( $input['favicon_url'] ?? '' ),
            'fonte_titulo'      => $fonte_titulo,
            'fonte_corpo'       => $fonte_corpo,
            'cor_primaria'      => sanitize_hex_color( $input['cor_primaria'] ?? '' ) ?: $padrao['cor_primaria'],
            'cor_primaria_hover'=> sanitize_hex_color( $input['cor_primaria_hover'] ?? '' ) ?: $padrao['cor_primaria_hover'],
            'cor_fundo'         => sanitize_hex_color( $input['cor_fundo'] ?? '' ) ?: $padrao['cor_fundo'],
            'cor_superficie'    => sanitize_hex_color( $input['cor_superficie'] ?? '' ) ?: $padrao['cor_superficie'],
            'cor_texto'         => sanitize_hex_color( $input['cor_texto'] ?? '' ) ?: $padrao['cor_texto'],
            'cor_texto_suave'   => sanitize_hex_color( $input['cor_texto_suave'] ?? '' ) ?: $padrao['cor_texto_suave'],
            'cor_borda'         => sanitize_hex_color( $input['cor_borda'] ?? '' ) ?: $padrao['cor_borda'],
            'estilo_card'       => $estilo_card,
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
            [ 'icone' => 'check-circle', 'titulo' => 'PIX na hora',              'descricao' => 'Pagamento instantâneo' ],
            [ 'icone' => 'settings',     'titulo' => 'Parcelamento',             'descricao' => 'No cartão, na vitrine' ],
            [ 'icone' => 'truck',        'titulo' => 'Envio para o Brasil',      'descricao' => 'Calcule o CEP no produto' ],
            [ 'icone' => 'users',        'titulo' => 'Atendimento no WhatsApp',  'descricao' => 'Pedido e pós-venda' ],
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
            'logo_url'           => '',
            'favicon_url'        => '',
            'fonte_titulo'       => 'plus_jakarta',
            'fonte_corpo'        => 'plus_jakarta',
            'cor_primaria'       => '#c8f542',
            'cor_primaria_hover' => '#d6ff6a',
            'cor_fundo'          => '#0a0a0b',
            'cor_superficie'     => '#141416',
            'cor_texto'          => '#f4f1ea',
            'cor_texto_suave'    => '#9a9a92',
            'cor_borda'          => '#2a2a2c',
            'estilo_card'        => 'suave',
            'raio_card'          => '20',
        ];
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

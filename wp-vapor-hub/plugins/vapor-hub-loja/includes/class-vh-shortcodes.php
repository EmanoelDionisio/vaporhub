<?php
/**
 * Shortcodes para uso no front-end (compatível com Elementor via widget shortcode).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Shortcodes {

    /**
     * Registra todos os shortcodes do plugin.
     */
    public static function registrar(): void {
        add_shortcode( 'vh_beneficios',   [ __CLASS__, 'beneficios' ] );
        add_shortcode( 'vh_comunidade',   [ __CLASS__, 'comunidade' ] );
        add_shortcode( 'vh_revenda_cta',  [ __CLASS__, 'revenda_cta' ] );
        add_shortcode( 'vh_categorias',   [ __CLASS__, 'categorias' ] );
    }

    /* ═══════════════════════════════════════════════
     * [vh_beneficios]
     * ═══════════════════════════════════════════════ */
    public static function beneficios(): string {
        $itens = VH_Settings::obter( 'vh_beneficios', VH_Settings::beneficios_padrao() );

        if ( empty( $itens ) ) {
            $itens = VH_Settings::beneficios_padrao();
        }

        ob_start();
        ?>
        <div class="vh-beneficios-grid">
            <?php foreach ( $itens as $item ) :
                if ( empty( trim( (string) ( $item['titulo'] ?? '' ) ) ) ) {
                    continue;
                }
                $icone     = esc_attr( $item['icone'] ?? 'check-circle' );
                $titulo    = esc_html( $item['titulo'] ?? '' );
                $descricao = esc_html( $item['descricao'] ?? '' );
            ?>
                <div class="vh-beneficios-card">
                    <div class="vh-beneficios-icone">
                        <?php echo self::svg_icone( $icone ); ?>
                    </div>
                    <div class="vh-beneficios-texto">
                        <strong class="vh-beneficios-titulo"><?php echo $titulo; ?></strong>
                        <span class="vh-beneficios-descricao"><?php echo $descricao; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════
     * [vh_comunidade]
     * ═══════════════════════════════════════════════ */
    public static function comunidade(): string {
        $itens = VH_Settings::obter( 'vh_comunidade' );

        ob_start();
        ?>
        <div class="vh-comunidade-wrapper">
            <div class="vh-comunidade-scroll">
                <?php if ( ! empty( $itens ) ) : ?>
                    <?php foreach ( array_slice( $itens, 0, 6 ) as $item ) :
                        $imagem  = esc_url( $item['imagem_url'] ?? '' );
                        $usuario = esc_html( $item['usuario'] ?? '' );
                        $link    = esc_url( $item['link'] ?? '#' );
                    ?>
                        <a href="<?php echo $link; ?>" class="vh-comunidade-card" target="_blank" rel="noopener noreferrer">
                            <?php if ( $imagem ) : ?>
                                <img src="<?php echo $imagem; ?>" alt="<?php echo $usuario; ?>" class="vh-comunidade-img" loading="lazy" />
                            <?php else : ?>
                                <div class="vh-comunidade-img vh-comunidade-placeholder"></div>
                            <?php endif; ?>
                            <div class="vh-comunidade-overlay">
                                <span class="vh-comunidade-usuario"><?php echo $usuario ? '@' . $usuario : ''; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>

                <a href="#" class="vh-comunidade-card vh-comunidade-cta" data-action="enviar-foto">
                    <div class="vh-comunidade-cta-content">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span><?php echo esc_html__( 'Envie sua foto', 'vapor-hub-loja' ); ?></span>
                    </div>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════
     * [vh_revenda_cta]
     * ═══════════════════════════════════════════════ */
    public static function revenda_cta(): string {
        $dados = VH_Settings::obter_revenda();

        /* Só oculta quando o lojista desativou explicitamente. Opção vazia = exibir com textos padrão. */
        if ( isset( $dados['mostrar'] ) && '0' === $dados['mostrar'] ) {
            return '';
        }

        $titulo      = esc_html( $dados['titulo'] );
        $descricao   = esc_html( $dados['descricao'] );
        $botao_texto = esc_html( $dados['botao_texto'] );
        $whatsapp     = isset( $dados['whatsapp'] ) ? preg_replace( '/\D/', '', (string) $dados['whatsapp'] ) : '';
        $url_externa  = ! empty( $dados['url_externa'] ) ? esc_url( $dados['url_externa'] ) : '';
        $link_wa      = $whatsapp ? esc_url( 'https://wa.me/55' . $whatsapp ) : '';

        ob_start();
        ?>
        <section class="vh-revenda-section">
            <div class="vh-revenda-blur vh-revenda-blur--1"></div>
            <div class="vh-revenda-blur vh-revenda-blur--2"></div>
            <div class="vh-revenda-content">
                <h2 class="vh-revenda-titulo"><?php echo $titulo; ?></h2>
                <p class="vh-revenda-descricao"><?php echo $descricao; ?></p>
                <?php if ( $url_externa ) : ?>
                    <a href="<?php echo $url_externa; ?>" class="vh-revenda-botao" target="_blank" rel="noopener noreferrer">
                        <?php echo $botao_texto; ?>
                    </a>
                <?php elseif ( $link_wa ) : ?>
                    <a href="<?php echo $link_wa; ?>" class="vh-revenda-botao" target="_blank" rel="noopener noreferrer">
                        <?php echo $botao_texto; ?>
                    </a>
                <?php else : ?>
                    <button type="button" class="vh-revenda-botao vh-abrir-revenda">
                        <?php echo $botao_texto; ?>
                    </button>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════
     * [vh_categorias]
     * ═══════════════════════════════════════════════ */
    public static function categorias(): string {
        $termos = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );

        if ( is_wp_error( $termos ) || empty( $termos ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="vh-categorias-grid">
            <?php foreach ( $termos as $termo ) :
                $thumb_id  = get_term_meta( $termo->term_id, 'thumbnail_id', true );
                $imagem    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';
                $nome      = esc_html( $termo->name );
                $link      = esc_url( get_term_link( $termo ) );
            ?>
                <a href="<?php echo $link; ?>" class="vh-categorias-card">
                    <?php if ( $imagem ) : ?>
                        <img src="<?php echo esc_url( $imagem ); ?>" alt="<?php echo $nome; ?>" class="vh-categorias-img" loading="lazy" />
                    <?php else : ?>
                        <div class="vh-categorias-img vh-categorias-placeholder"></div>
                    <?php endif; ?>
                    <div class="vh-categorias-overlay">
                        <span class="vh-categorias-nome"><?php echo $nome; ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════
     * SVG helper — ícones inline
     * ═══════════════════════════════════════════════ */
    private static function svg_icone( string $nome ): string {
        $icones = [
            'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'settings'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'truck'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
            'users'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'star'         => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'heart'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            'shield'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            'gift'         => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
        ];

        $svg = $icones[ $nome ] ?? $icones['check-circle'];

        return wp_kses( $svg, [
            'svg'      => [ 'xmlns' => [], 'width' => [], 'height' => [], 'viewBox' => [], 'fill' => [], 'stroke' => [], 'stroke-width' => [], 'stroke-linecap' => [], 'stroke-linejoin' => [] ],
            'path'     => [ 'd' => [] ],
            'circle'   => [ 'cx' => [], 'cy' => [], 'r' => [] ],
            'rect'     => [ 'x' => [], 'y' => [], 'width' => [], 'height' => [] ],
            'polygon'  => [ 'points' => [] ],
            'polyline' => [ 'points' => [] ],
            'line'     => [ 'x1' => [], 'y1' => [], 'x2' => [], 'y2' => [] ],
        ] );
    }
}

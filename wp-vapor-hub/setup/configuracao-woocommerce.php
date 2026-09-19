<?php
/**
 * Configuração WooCommerce — Vapor Hub
 * E-commerce de vape, pod e e-líquido — estrutura da vitrine (catálogo vem do Tiny).
 *
 * Desenvolvido por New Alliance Tecnologia (newalliance.tech)
 *
 * Uso:
 *   wp eval-file setup/configuracao-woocommerce.php
 *   ou copie para wp-content/mu-plugins/ (remover após execução)
 *
 * Script idempotente — seguro para rodar múltiplas vezes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Compatibilidade com WP-CLI (eval-file roda dentro do WP)
    if ( php_sapi_name() !== 'cli' ) {
        exit( 'Acesso direto não permitido.' );
    }
}

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────

function vh_log( string $msg ): void {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        WP_CLI::log( $msg );
    } else {
        error_log( '[Vapor Hub] ' . $msg );
    }
}

function vh_success( string $msg ): void {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        WP_CLI::success( $msg );
    } else {
        error_log( '[Vapor Hub][OK] ' . $msg );
    }
}

function vh_warning( string $msg ): void {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        WP_CLI::warning( $msg );
    } else {
        error_log( '[Vapor Hub][AVISO] ' . $msg );
    }
}

// ──────────────────────────────────────────────
// 1. Categorias de Produto
// ──────────────────────────────────────────────

function vh_criar_categorias(): void {
    vh_log( '── Criando categorias de produto (vitrine; produtos entram pelo Tiny)...' );

    $categorias = [
        [ 'nome' => 'Vape', 'slug' => 'vape' ],
        [ 'nome' => 'POD Descartável', 'slug' => 'pod-descartavel' ],
        [ 'nome' => 'Até 2.000 puffs', 'slug' => 'ate-2000-puffs', 'parent' => 'pod-descartavel' ],
        [ 'nome' => '2.000 a 5.000 puffs', 'slug' => '2000-a-5000-puffs', 'parent' => 'pod-descartavel' ],
        [ 'nome' => 'Acima de 5.000 puffs', 'slug' => 'acima-5000-puffs', 'parent' => 'pod-descartavel' ],
        [ 'nome' => 'POD Recarregável', 'slug' => 'pod-recarregavel' ],
        [ 'nome' => 'Aparelhos', 'slug' => 'aparelhos-pod', 'parent' => 'pod-recarregavel' ],
        [ 'nome' => 'Reposições', 'slug' => 'reposicoes-pod', 'parent' => 'pod-recarregavel' ],
        [ 'nome' => 'e-Líquidos', 'slug' => 'e-liquidos' ],
        [ 'nome' => 'Free base', 'slug' => 'free-base', 'parent' => 'e-liquidos' ],
        [ 'nome' => 'Nic salt', 'slug' => 'nic-salt', 'parent' => 'e-liquidos' ],
        [ 'nome' => 'Frutado', 'slug' => 'eliquido-frutado', 'parent' => 'e-liquidos' ],
        [ 'nome' => 'Ice / mentolado', 'slug' => 'eliquido-ice', 'parent' => 'e-liquidos' ],
        [ 'nome' => 'Atabacado', 'slug' => 'eliquido-atabacado', 'parent' => 'e-liquidos' ],
        [ 'nome' => 'Doce', 'slug' => 'eliquido-doce', 'parent' => 'e-liquidos' ],
        [ 'nome' => 'Vaporizador de ervas', 'slug' => 'vaporizador-ervas' ],
        [ 'nome' => 'Nicotina oral', 'slug' => 'nicotina-oral' ],
        [ 'nome' => 'Acessórios', 'slug' => 'acessorios' ],
    ];

    foreach ( $categorias as $cat ) {
        $nome = $cat['nome'];
        $slug = $cat['slug'];
        $args = [ 'slug' => $slug ];

        if ( ! empty( $cat['parent'] ) ) {
            $pai = get_term_by( 'slug', $cat['parent'], 'product_cat' );
            if ( $pai instanceof WP_Term ) {
                $args['parent'] = (int) $pai->term_id;
            }
        }

        if ( term_exists( $slug, 'product_cat' ) ) {
            vh_log( "  ✓ Categoria '{$nome}' já existe." );
            continue;
        }

        $result = wp_insert_term( $nome, 'product_cat', $args );

        if ( is_wp_error( $result ) ) {
            vh_warning( "  ✗ Erro ao criar '{$nome}': " . $result->get_error_message() );
        } else {
            vh_success( "  Categoria '{$nome}' criada (ID {$result['term_id']})." );
        }
    }
}

// ──────────────────────────────────────────────
// 1b. Taxonomia de marcas (vitrine; cadastro real vem do Tiny)
// ──────────────────────────────────────────────

function vh_registrar_marcas(): void {
    vh_log( '── Registrando taxonomia product_brand...' );

    if ( ! taxonomy_exists( 'product_brand' ) ) {
        register_taxonomy(
            'product_brand',
            [ 'product' ],
            [
                'labels'            => [
                    'name'          => 'Marcas',
                    'singular_name' => 'Marca',
                ],
                'hierarchical'      => false,
                'public'            => true,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'rewrite'           => [ 'slug' => 'marca' ],
            ]
        );
        vh_success( 'Taxonomia product_brand registrada.' );
    } else {
        vh_log( '  ✓ Taxonomia product_brand já existia.' );
    }
}

// ──────────────────────────────────────────────
// 2. Atributos globais e termos
// ──────────────────────────────────────────────

function vh_criar_atributos(): void {
    vh_log( '── Criando atributos globais...' );

    $atributos = [
        [
            'name'   => 'Sabor',
            'slug'   => 'sabor',
            'type'   => 'select',
            'termos' => [ 'Manga Ice', 'Morango', 'Uva Ice', 'Menta', 'Tabaco', 'Baunilha' ],
        ],
        [
            'name'   => 'Puffs',
            'slug'   => 'puffs',
            'type'   => 'select',
            'termos' => [ '600', '1500', '5000', '10000' ],
        ],
        [
            'name'   => 'Teor de nicotina',
            'slug'   => 'teor_nicotina',
            'type'   => 'select',
            'termos' => [ '0mg', '20mg', '35mg', '50mg' ],
        ],
        [
            'name'   => 'Tipo de nicotina',
            'slug'   => 'tipo_nicotina',
            'type'   => 'select',
            'termos' => [ 'Free base', 'Nic salt' ],
        ],
        [
            'name'   => 'Quantidade em ml',
            'slug'   => 'quantidade_ml',
            'type'   => 'select',
            'termos' => [ '10ml', '30ml', '60ml' ],
        ],
        [
            'name'   => 'Cores',
            'slug'   => 'cores',
            'type'   => 'select',
            'termos' => [ 'Preto', 'Branco', 'Cinza', 'Azul' ],
        ],
        [
            'name'   => 'Resistência',
            'slug'   => 'resistencia',
            'type'   => 'select',
            'termos' => [ '0.6 ohm', '0.8 ohm', '1.0 ohm' ],
        ],
        [
            'name'   => 'Tipo de sabor',
            'slug'   => 'tipo_sabor',
            'type'   => 'select',
            'termos' => [ 'Frutado', 'Ice', 'Mentolado', 'Atabacado', 'Doce' ],
        ],
        [
            'name'   => 'Teor de CBD',
            'slug'   => 'teor_cbd',
            'type'   => 'select',
            'termos' => [ '0%', '5%', '10%' ],
        ],
    ];

    foreach ( $atributos as $attr ) {
        $taxonomy = wc_attribute_taxonomy_name( $attr['slug'] );
        $existing = wc_attribute_taxonomy_id_by_name( $attr['slug'] );

        if ( $existing ) {
            vh_log( "  ✓ Atributo '{$attr['name']}' já existe (ID {$existing})." );
        } else {
            $id = wc_create_attribute( [
                'name'         => $attr['name'],
                'slug'         => $attr['slug'],
                'type'         => $attr['type'],
                'order_by'     => 'menu_order',
                'has_archives' => false,
            ] );

            if ( is_wp_error( $id ) ) {
                vh_warning( "  ✗ Erro ao criar atributo '{$attr['name']}': " . $id->get_error_message() );
                continue;
            }

            vh_success( "  Atributo '{$attr['name']}' criado (ID {$id})." );

            // Registra a taxonomy na sessão atual para poder inserir termos
            register_taxonomy( $taxonomy, 'product', [
                'labels'       => [ 'name' => $attr['name'] ],
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => [ 'slug' => sanitize_title( $attr['slug'] ) ],
            ] );
        }

        // Garante que a taxonomy está registrada nesta execução
        if ( ! taxonomy_exists( $taxonomy ) ) {
            register_taxonomy( $taxonomy, 'product' );
        }

        foreach ( $attr['termos'] as $termo ) {
            if ( term_exists( $termo, $taxonomy ) ) {
                vh_log( "    ✓ Termo '{$termo}' já existe em {$taxonomy}." );
                continue;
            }

            $t = wp_insert_term( $termo, $taxonomy );

            if ( is_wp_error( $t ) ) {
                vh_warning( "    ✗ Erro ao criar termo '{$termo}': " . $t->get_error_message() );
            } else {
                vh_success( "    Termo '{$termo}' criado em {$taxonomy}." );
            }
        }
    }
}

// ──────────────────────────────────────────────
// 3. Configurações gerais do WooCommerce
// ──────────────────────────────────────────────

function vh_configurar_woocommerce(): void {
    vh_log( '── Configurando opções do WooCommerce...' );

    $opcoes = [
        'woocommerce_currency'               => 'BRL',
        'woocommerce_default_country'         => 'BR',
        'woocommerce_weight_unit'             => 'kg',
        'woocommerce_dimension_unit'          => 'cm',
        'woocommerce_currency_pos'            => 'left_space',
        'woocommerce_price_thousand_sep'      => '.',
        'woocommerce_price_decimal_sep'       => ',',
        'woocommerce_price_num_decimals'      => '2',
        'woocommerce_enable_reviews'          => 'yes',
        'woocommerce_enable_coupons'          => 'yes',
        'woocommerce_calc_taxes'              => 'no',
        'woocommerce_manage_stock'            => 'yes',
        'woocommerce_store_address'           => '',
        'woocommerce_store_city'              => '',
        'woocommerce_store_postcode'          => '',
    ];

    foreach ( $opcoes as $key => $value ) {
        update_option( $key, $value );
    }

    vh_success( 'Opções gerais do WooCommerce configuradas.' );
}

// ──────────────────────────────────────────────
// 4. Páginas obrigatórias
// ──────────────────────────────────────────────

function vh_criar_paginas(): void {
    vh_log( '── Criando páginas obrigatórias...' );

    $paginas = [
        [
            'title'   => 'Loja',
            'slug'    => 'loja',
            'content' => '',
            'option'  => 'woocommerce_shop_page_id',
        ],
        [
            'title'   => 'Carrinho',
            'slug'    => 'carrinho',
            'content' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
            'option'  => 'woocommerce_cart_page_id',
        ],
        [
            'title'   => 'Finalizar Pedido',
            'slug'    => 'finalizar-pedido',
            'content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
            'option'  => 'woocommerce_checkout_page_id',
        ],
        [
            'title'   => 'Minha Conta',
            'slug'    => 'minha-conta',
            'content' => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->',
            'option'  => 'woocommerce_myaccount_page_id',
        ],
        [
            'title'   => 'Termos de Uso',
            'slug'    => 'termos-de-uso',
            'content' => '<p>Termos de uso do Vapor Hub. Conteúdo a ser preenchido.</p>',
            'option'  => 'woocommerce_terms_page_id',
        ],
        [
            'title'   => 'Política de Privacidade',
            'slug'    => 'politica-de-privacidade',
            'content' => '<p>Política de privacidade do Vapor Hub. Conteúdo a ser preenchido.</p>',
            'option'  => 'wp_page_for_privacy_policy',
        ],
        [
            'title'   => 'Trocas e Devoluções',
            'slug'    => 'trocas-e-devolucoes',
            'content' => '<p>Política de trocas e devoluções do Vapor Hub. Conteúdo a ser preenchido.</p>',
            'option'  => '',
        ],
    ];

    foreach ( $paginas as $pagina ) {
        $existing = get_page_by_path( $pagina['slug'] );

        if ( $existing && $existing->post_status !== 'trash' ) {
            vh_log( "  ✓ Página '{$pagina['title']}' já existe (ID {$existing->ID})." );
            $page_id = $existing->ID;
        } else {
            $page_id = wp_insert_post( [
                'post_title'   => $pagina['title'],
                'post_name'    => $pagina['slug'],
                'post_content' => $pagina['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ] );

            if ( is_wp_error( $page_id ) ) {
                vh_warning( "  ✗ Erro ao criar '{$pagina['title']}': " . $page_id->get_error_message() );
                continue;
            }

            vh_success( "  Página '{$pagina['title']}' criada (ID {$page_id})." );
        }

        if ( ! empty( $pagina['option'] ) ) {
            update_option( $pagina['option'], $page_id );
        }
    }
}

// ──────────────────────────────────────────────
// 5. Zona de frete
// ──────────────────────────────────────────────

function vh_criar_zona_frete(): void {
    vh_log( '── Configurando zona de frete...' );

    $zones = WC_Shipping_Zones::get_zones();

    foreach ( $zones as $zone_data ) {
        if ( $zone_data['zone_name'] === 'Brasil' ) {
            vh_log( '  ✓ Zona de frete "Brasil" já existe.' );
            return;
        }
    }

    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name( 'Brasil' );
    $zone->set_zone_order( 0 );
    $zone->save();

    $zone->add_location( 'BR', 'country' );

    /*
     * Se o Correios for WooCommerce (Cláudio Sanches) estiver ativo, não criamos frete fixo aqui:
     * o cálculo na loja deve refletir só o que for configurado nesse plugin (e demais métodos) nas zonas.
     * Sem o plugin, mantemos um flat_rate inicial para ambientes de desenvolvimento/demonstração.
     */
    if ( defined( 'WC_CORREIOS_VERSION' ) ) {
        $zone->save();
        vh_log( '  Correios for WooCommerce ativo: zona "Brasil" criada sem frete fixo. Configure PAC/SEDEX etc. em WooCommerce > Entrega.' );
        vh_success( 'Zona de frete "Brasil" criada (sem flat rate — use os métodos do Correios for WooCommerce).' );
        return;
    }

    $zone->add_shipping_method( 'flat_rate' );
    $zone->save();

    // Configurar valor do frete fixo (apenas quando Correios não está no ambiente)
    $methods = $zone->get_shipping_methods();
    foreach ( $methods as $method ) {
        if ( $method->id === 'flat_rate' ) {
            $option_key = $method->get_instance_option_key();
            $options    = get_option( $option_key, [] );

            $options['title'] = 'Frete Padrão';
            $options['cost']  = '15.90';

            update_option( $option_key, $options );
            break;
        }
    }

    vh_success( 'Zona de frete "Brasil" criada com frete fixo de R$ 15,90 (substitua por Correios no admin quando for para produção).' );
}

// ──────────────────────────────────────────────
// Execução
// ──────────────────────────────────────────────

function vh_setup_completo(): void {
    vh_log( '' );
    vh_log( '╔══════════════════════════════════════════════════╗' );
    vh_log( '║  Vapor Hub — Configuração WooCommerce      ║' );
    vh_log( '║  Desenvolvido por New Alliance Tecnologia        ║' );
    vh_log( '║  https://newalliance.tech                        ║' );
    vh_log( '╚══════════════════════════════════════════════════╝' );
    vh_log( '' );

    if ( ! class_exists( 'WooCommerce' ) ) {
        vh_warning( 'WooCommerce não está ativo. Ative o plugin antes de rodar este script.' );
        return;
    }

    vh_criar_categorias();
    vh_log( '' );

    vh_registrar_marcas();
    vh_log( '' );

    vh_criar_atributos();
    vh_log( '' );

    vh_configurar_woocommerce();
    vh_log( '' );

    vh_criar_paginas();
    vh_log( '' );

    vh_criar_zona_frete();
    vh_log( '' );

    vh_log( '══════════════════════════════════════════════════' );
    vh_success( 'Estrutura da loja pronta. O catálogo de produção entra pelo Tiny ERP (mapeie categorias e puxe com recorte).' );
    vh_log( '  CSV de amostra (dev/teste, não é o pipeline): setup/produtos-importacao.csv' );
    vh_log( '══════════════════════════════════════════════════' );
    vh_log( '' );

    /* Marca para o script remoto (deploy) pular nova execução quando já aplicado. */
    update_option( 'vh_loja_setup_concluido', '1' );
    vh_log( 'Marcador vh_loja_setup_concluido atualizado (deploy pode pular este passo).' );
}

vh_setup_completo();

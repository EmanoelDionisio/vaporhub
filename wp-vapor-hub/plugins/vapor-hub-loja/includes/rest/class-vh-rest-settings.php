<?php
/**
 * Endpoints REST de configurações (Aparência, Comunidade, Revenda, Segurança).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_REST_Settings extends VH_REST_Controller {

    protected string $rest_base = 'settings';

    /**
     * @var array<string,array<string,mixed>>
     */
    private const MAPA = [
        'aparencia'  => [ 'handler' => 'salvar_aparencia' ],
        'comunidade' => [
            'option'  => 'vh_comunidade',
            'sanitize'=> [ VH_Settings::class, 'sanitizar_comunidade' ],
            'campo'   => 'vh_comunidade',
        ],
        'revenda'    => [
            'option'  => 'vh_revenda',
            'sanitize'=> [ VH_Settings::class, 'sanitizar_revenda' ],
            'campo'   => 'vh_revenda',
        ],
        'seguranca'  => [ 'handler' => 'salvar_seguranca' ],
        'seo'        => [
            'option'  => 'vh_seo',
            'sanitize'=> [ VH_Settings::class, 'sanitizar_seo' ],
            'campo'   => 'vh_seo',
            'handler' => 'salvar_seo',
        ],
    ];

    public function register_routes(): void {
        register_rest_route( self::NS, '/' . $this->rest_base . '/(?P<secao>[a-z]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'obter' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [
                    'secao' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => [ $this, 'validar_secao' ],
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'atualizar' ],
                'permission_callback' => [ $this, 'permissao' ],
                'args'                => [
                    'secao' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => [ $this, 'validar_secao' ],
                    ],
                ],
            ],
        ] );
    }

    /**
     * @param mixed $valor
     */
    public function validar_secao( $valor ): bool {
        return is_string( $valor ) && isset( self::MAPA[ $valor ] );
    }

    public function obter( WP_REST_Request $request ) {
        $secao = sanitize_key( (string) $request['secao'] );
        return $this->ok( $this->dados_secao( $secao ) );
    }

    public function atualizar( WP_REST_Request $request ) {
        $secao = sanitize_key( (string) $request['secao'] );
        $corpo = (array) $request->get_json_params();
        $def   = self::MAPA[ $secao ];

        if ( isset( $def['handler'] ) ) {
            $metodo = $def['handler'];
            return $this->$metodo( $corpo );
        }

        $campo = $def['campo'] ?? $def['option'];
        $valor = $corpo[ $campo ] ?? $corpo;
        $limpo = call_user_func( $def['sanitize'], $valor );
        update_option( $def['option'], $limpo );

        return $this->ok( $this->dados_secao( $secao ) );
    }

    /**
     * @return array<string,mixed>
     */
    private function dados_secao( string $secao ): array {
        switch ( $secao ) {
            case 'aparencia':
                return [
                    'vh_hero'              => VH_Settings::obter( 'vh_hero' ),
                    'vh_beneficios'        => VH_Settings::obter( 'vh_beneficios', VH_Settings::beneficios_padrao() ),
                    'vh_rodape'            => VH_Settings::obter( 'vh_rodape' ),
                    'vh_identidade_visual' => VH_Settings::obter( 'vh_identidade_visual', VH_Settings::identidade_visual_padrao() ),
                ];
            case 'comunidade':
                return [
                    'vh_comunidade' => VH_Settings::obter( 'vh_comunidade' ),
                ];
            case 'revenda':
                return [
                    'vh_revenda' => VH_Settings::obter( 'vh_revenda' ),
                ];
            case 'seguranca':
                $seg = VH_Settings::obter( 'vh_seguranca', VH_Settings::seguranca_padrao() );
                unset( $seg['turnstile_secret_key'] );
                return [ 'vh_seguranca' => $seg ];
            case 'seo':
                return [
                    'vh_seo'        => VH_Settings::obter( 'vh_seo', VH_Settings::seo_padrao() ),
                    'google_status' => VH_SEO_Google::status_publico(),
                ];
            default:
                return [];
        }
    }

    private function salvar_aparencia( array $corpo ): WP_REST_Response {
        if ( isset( $corpo['vh_hero'] ) ) {
            update_option( 'vh_hero', VH_Settings::sanitizar_hero( $corpo['vh_hero'] ) );
        }
        if ( isset( $corpo['vh_beneficios'] ) ) {
            update_option( 'vh_beneficios', VH_Settings::sanitizar_beneficios( $corpo['vh_beneficios'] ) );
        }
        if ( isset( $corpo['vh_rodape'] ) ) {
            update_option( 'vh_rodape', VH_Settings::sanitizar_rodape( $corpo['vh_rodape'] ) );
        }
        if ( isset( $corpo['vh_identidade_visual'] ) ) {
            $identidade = VH_Settings::sanitizar_identidade_visual( $corpo['vh_identidade_visual'] );
            update_option( 'vh_identidade_visual', $identidade );
            VH_Settings::sincronizar_favicon_wp( (string) ( $identidade['favicon_url'] ?? '' ) );
        }

        return $this->ok( $this->dados_secao( 'aparencia' ) );
    }

    private function salvar_seguranca( array $corpo ): WP_REST_Response {
        $atual   = VH_Settings::obter( 'vh_seguranca', VH_Settings::seguranca_padrao() );
        $entrada = $corpo['vh_seguranca'] ?? $corpo;
        $limpo   = VH_Settings::sanitizar_seguranca( $entrada );

        if ( empty( $entrada['turnstile_secret_key'] ) && ! empty( $atual['turnstile_secret_key'] ) ) {
            $limpo['turnstile_secret_key'] = $atual['turnstile_secret_key'];
        }

        update_option( 'vh_seguranca', $limpo );

        return $this->ok( $this->dados_secao( 'seguranca' ) );
    }

    private function salvar_seo( array $corpo ): WP_REST_Response {
        $entrada = $corpo['vh_seo'] ?? $corpo;
        $limpo   = VH_Settings::sanitizar_seo( $entrada );
        update_option( 'vh_seo', $limpo );

        $client_secret = (string) ( $entrada['google_client_secret'] ?? '' );
        $pagespeed_key = (string) ( $entrada['pagespeed_api_key'] ?? '' );

        if ( '' !== $limpo['google_client_id'] || '' !== $client_secret ) {
            VH_SEO_Google::salvar_credenciais( $limpo['google_client_id'], $client_secret );
        }

        if ( array_key_exists( 'pagespeed_api_key', $entrada ) ) {
            VH_SEO_Google::salvar_pagespeed_key( $pagespeed_key );
        }

        return $this->ok( $this->dados_secao( 'seo' ) );
    }
}

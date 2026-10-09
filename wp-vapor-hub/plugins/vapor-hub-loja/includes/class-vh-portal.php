<?php
/**
 * Portal público /minha-loja/* — app front-end e login dedicado.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Portal {

    public const QV_ROTA  = 'vh_minha_loja_rota';
    public const QV_SECAO = 'vh_minha_loja';
    public const QV_ACAO  = 'vh_minha_loja_acao';
    public const QV_ID    = 'vh_minha_loja_id';

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'registrar_rewrites' ] );
        add_action( 'init', [ __CLASS__, 'flush_rewrites_pendentes' ], 99 );
        add_filter( 'query_vars', [ __CLASS__, 'registrar_query_vars' ] );
        add_action( 'template_redirect', [ __CLASS__, 'resolver' ], 0 );
        add_filter( 'do_not_cache_page', [ __CLASS__, 'nao_cachear' ] );
    }

    /**
     * Impede cache de plugins externos nas rotas do portal.
     *
     * @param bool $nao_cachear
     */
    public static function nao_cachear( $nao_cachear ): bool {
        if ( self::eh_rota_portal() ) {
            return true;
        }
        return (bool) $nao_cachear;
    }

    public static function agendar_flush_rewrites(): void {
        update_option( 'vh_loja_flush_rewrites', '1', false );
    }

    public static function flush_rewrites_pendentes(): void {
        if ( ! get_option( 'vh_loja_flush_rewrites' ) ) {
            return;
        }
        flush_rewrite_rules();
        delete_option( 'vh_loja_flush_rewrites' );
    }

    /**
     * Indica se a requisição atual é uma rota do portal Minha Loja.
     */
    public static function eh_rota_portal(): bool {
        $rota  = get_query_var( self::QV_ROTA );
        $secao = get_query_var( self::QV_SECAO );
        return ( $rota || $secao );
    }

    public static function registrar_rewrites(): void {
        global $wp_rewrite;
        if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) ) {
            return;
        }

        add_rewrite_rule( '^minha-loja/entrar/?$', 'index.php?' . self::QV_ROTA . '=entrar', 'top' );
        add_rewrite_rule( '^minha-loja/sair/?$', 'index.php?' . self::QV_ROTA . '=sair', 'top' );
        add_rewrite_rule( '^minha-loja/?$', 'index.php?' . self::QV_SECAO . '=dashboard', 'top' );
        add_rewrite_rule( '^minha-loja/([^/]+)/novo/?$', 'index.php?' . self::QV_SECAO . '=$matches[1]&' . self::QV_ACAO . '=novo', 'top' );
        add_rewrite_rule( '^minha-loja/([^/]+)/([0-9]+)/?$', 'index.php?' . self::QV_SECAO . '=$matches[1]&' . self::QV_ID . '=$matches[2]', 'top' );
        add_rewrite_rule( '^minha-loja/([^/]+)/(config|envios|conexao|mapeamento|logs|banner|inicio|cores|rodape)/?$', 'index.php?' . self::QV_SECAO . '=$matches[1]&' . self::QV_ACAO . '=$matches[2]', 'top' );
        add_rewrite_rule( '^minha-loja/([^/]+)/?$', 'index.php?' . self::QV_SECAO . '=$matches[1]', 'top' );
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public static function registrar_query_vars( array $vars ): array {
        $vars[] = self::QV_ROTA;
        $vars[] = self::QV_SECAO;
        $vars[] = self::QV_ACAO;
        $vars[] = self::QV_ID;
        return $vars;
    }

    /**
     * Resolve rotas públicas: login, logout ou app autenticado.
     */
    public static function resolver(): void {
        $rota  = sanitize_key( (string) get_query_var( self::QV_ROTA ) );
        $secao = get_query_var( self::QV_SECAO );

        if ( '' === $rota && ( '' === $secao || false === $secao || null === $secao ) ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        nocache_headers();

        if ( 'entrar' === $rota ) {
            VH_Auth::render_login();
            exit;
        }

        if ( 'sair' === $rota ) {
            VH_Auth::processar_logout();
            exit;
        }

        if ( ! is_user_logged_in() ) {
            $destino = self::url_atual();
            wp_safe_redirect( VH_Router::url( 'entrar', [ 'redirect_to' => $destino ] ) );
            exit;
        }

        if ( ! current_user_can( VH_Roles::CAP ) ) {
            if ( VH_Guard::cliente_atual() ) {
                VH_Guard::aplicar_sonda();
            }
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        VH_Front::render_app();
        exit;
    }

    /**
     * URL canônica da requisição atual do portal (para redirect pós-login).
     */
    public static function url_atual(): string {
        $secao = VH_Router::secao_atual();
        $acao  = VH_Router::acao_atual( $secao );
        $id    = VH_Router::id_atual();

        if ( 'novo' === $acao ) {
            return VH_Router::url( $secao, [ 'acao' => 'novo' ] );
        }
        if ( 'config' === $acao ) {
            return VH_Router::url( $secao, [ 'acao' => 'config' ] );
        }
        if ( in_array( $acao, [ 'conexao', 'mapeamento', 'logs' ], true ) ) {
            return VH_Router::url( $secao, [ 'acao' => $acao ] );
        }
        if ( $id > 0 ) {
            return VH_Router::url( $secao, [ 'id' => $id ] );
        }
        return VH_Router::url( $secao );
    }
}

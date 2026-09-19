<?php
/**
 * Envios de fotos da galeria da comunidade — persistência, upload e moderação.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Comunidade_Envios_Service {

    private const RATE_MAX       = 5;
    private const RATE_SECONDS   = 600;
    private const TAMANHO_MAX_MB = 5;
    private const GALERIA_MAX    = 6;

    public static function nome_tabela(): string {
        global $wpdb;
        return $wpdb->prefix . 'vh_comunidade_envios';
    }

    public static function instalar_tabela(): void {
        global $wpdb;

        $tabela  = self::nome_tabela();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tabela} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nome varchar(200) NOT NULL DEFAULT '',
            instagram varchar(120) NOT NULL DEFAULT '',
            link varchar(500) NOT NULL DEFAULT '',
            legenda varchar(500) NOT NULL DEFAULT '',
            imagem_url varchar(500) NOT NULL DEFAULT '',
            imagem_path varchar(500) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(500) NOT NULL DEFAULT '',
            referrer varchar(500) NOT NULL DEFAULT '',
            origem varchar(32) NOT NULL DEFAULT 'popup',
            status varchar(20) NOT NULL DEFAULT 'pendente',
            revisado_em datetime DEFAULT NULL,
            revisado_por bigint(20) unsigned DEFAULT NULL,
            criado_em datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY criado_em (criado_em),
            KEY instagram (instagram(60))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * @param array<string,string> $dados
     * @param array<string,mixed>  $arquivo $_FILES['foto']
     * @param array<string,string> $meta
     */
    public static function registrar( array $dados, array $arquivo, array $meta = [] ): int|WP_Error {
        global $wpdb;

        $nome      = sanitize_text_field( $dados['nome'] ?? '' );
        $instagram = self::normalizar_instagram( $dados['instagram'] ?? '' );
        $link      = esc_url_raw( $dados['link'] ?? '' );
        $legenda   = sanitize_text_field( $dados['legenda'] ?? '' );

        if ( '' === $nome ) {
            return new WP_Error( 'dados_invalidos', __( 'Informe seu nome.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( '' === $instagram ) {
            return new WP_Error( 'dados_invalidos', __( 'Informe seu usuário do Instagram.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( '' === $link ) {
            $link = 'https://instagram.com/' . rawurlencode( $instagram );
        }

        if ( self::rate_limit_excedido() ) {
            return new WP_Error( 'rate_limit', __( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 'vapor-hub-loja' ), [ 'status' => 429 ] );
        }

        $upload = self::processar_upload( $arquivo );
        if ( is_wp_error( $upload ) ) {
            return $upload;
        }

        self::incrementar_rate();

        $inserido = $wpdb->insert(
            self::nome_tabela(),
            [
                'nome'         => $nome,
                'instagram'    => $instagram,
                'link'         => $link,
                'legenda'      => $legenda,
                'imagem_url'   => $upload['url'],
                'imagem_path'  => $upload['file'],
                'ip'           => sanitize_text_field( $meta['ip'] ?? '' ),
                'user_agent'   => sanitize_text_field( mb_substr( (string) ( $meta['user_agent'] ?? '' ), 0, 500 ) ),
                'referrer'     => esc_url_raw( $meta['referrer'] ?? '' ),
                'origem'       => sanitize_key( $meta['origem'] ?? 'popup' ),
                'status'       => 'pendente',
                'criado_em'    => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $inserido ) {
            if ( ! empty( $upload['file'] ) && file_exists( $upload['file'] ) ) {
                @unlink( $upload['file'] );
            }
            return new WP_Error( 'falha_salvar', __( 'Não foi possível registrar o envio. Tente novamente.', 'vapor-hub-loja' ), [ 'status' => 500 ] );
        }

        $id = (int) $wpdb->insert_id;

        /**
         * Disparado após salvar um envio de foto da comunidade.
         *
         * @param int                  $id
         * @param array<string,string> $dados
         */
        do_action( 'vh_comunidade_envio_salvo', $id, array_merge( $dados, [
            'instagram'   => $instagram,
            'link'        => $link,
            'imagem_url'  => $upload['url'],
        ] ) );

        return $id;
    }

    /**
     * @param array<string,mixed> $args
     * @return array{itens:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public static function listar( array $args ): array {
        global $wpdb;

        $pagina     = max( 1, (int) ( $args['pagina'] ?? 1 ) );
        $por_pagina = min( 100, max( 1, (int) ( $args['por_pagina'] ?? 20 ) ) );
        $busca      = isset( $args['busca'] ) ? sanitize_text_field( (string) $args['busca'] ) : '';
        $status     = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
        $offset     = ( $pagina - 1 ) * $por_pagina;
        $tabela     = self::nome_tabela();

        $where  = 'WHERE 1=1';
        $params = [];

        if ( '' !== $busca ) {
            $like     = '%' . $wpdb->esc_like( $busca ) . '%';
            $where   .= ' AND (nome LIKE %s OR instagram LIKE %s OR legenda LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( '' !== $status && in_array( $status, [ 'pendente', 'aprovado', 'recusado' ], true ) ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        $sql_total = "SELECT COUNT(*) FROM {$tabela} {$where}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql_total, ...$params ) ) : $wpdb->get_var( $sql_total ) );

        $sql_itens = "SELECT * FROM {$tabela} {$where} ORDER BY criado_em DESC LIMIT %d OFFSET %d";
        $params_itens = array_merge( $params, [ $por_pagina, $offset ] );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $linhas = $wpdb->get_results( $wpdb->prepare( $sql_itens, ...$params_itens ), ARRAY_A );

        $itens = [];
        foreach ( (array) $linhas as $linha ) {
            $itens[] = self::formatar( $linha );
        }

        return [
            'itens'   => $itens,
            'total'   => $total,
            'paginas' => (int) ceil( $total / $por_pagina ),
            'pagina'  => $pagina,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function obter( int $id, bool $marcar_visto = false ): ?array {
        global $wpdb;

        $tabela = self::nome_tabela();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $linha = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id = %d", $id ), ARRAY_A );

        if ( ! $linha ) {
            return null;
        }

        return self::formatar( $linha, true );
    }

    public static function aprovar( int $id ): true|WP_Error {
        global $wpdb;

        $item = self::obter( $id );
        if ( ! $item ) {
            return new WP_Error( 'nao_encontrado', __( 'Envio não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        if ( 'aprovado' === $item['status'] ) {
            return true;
        }

        if ( 'recusado' === $item['status'] ) {
            return new WP_Error( 'ja_recusado', __( 'Este envio já foi recusado.', 'vapor-hub-loja' ), [ 'status' => 409 ] );
        }

        $galeria = VH_Settings::obter( 'vh_comunidade' );
        if ( ! is_array( $galeria ) ) {
            $galeria = [];
        }

        $galeria = array_values( array_filter( $galeria, static function ( $entrada ) {
            return is_array( $entrada ) && ! empty( $entrada['imagem_url'] );
        } ) );

        if ( count( $galeria ) >= self::GALERIA_MAX ) {
            return new WP_Error(
                'galeria_cheia',
                sprintf(
                    /* translators: %d: número máximo de fotos na galeria. */
                    __( 'A galeria já tem %d fotos. Remova uma entrada antes de aprovar outro envio.', 'vapor-hub-loja' ),
                    self::GALERIA_MAX
                ),
                [ 'status' => 409 ]
            );
        }

        $galeria[] = [
            'imagem_url' => $item['imagem_url'],
            'usuario'    => $item['instagram'],
            'link'       => $item['link'],
        ];

        update_option( 'vh_comunidade', VH_Settings::sanitizar_comunidade( $galeria ) );

        $atualizado = $wpdb->update(
            self::nome_tabela(),
            [
                'status'       => 'aprovado',
                'revisado_em'  => current_time( 'mysql', true ),
                'revisado_por' => get_current_user_id(),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );

        if ( false === $atualizado ) {
            return new WP_Error( 'falha_aprovar', __( 'Não foi possível aprovar o envio.', 'vapor-hub-loja' ), [ 'status' => 500 ] );
        }

        do_action( 'vh_comunidade_envio_aprovado', $id, $item );

        return true;
    }

    public static function recusar( int $id ): true|WP_Error {
        global $wpdb;

        $item = self::obter( $id );
        if ( ! $item ) {
            return new WP_Error( 'nao_encontrado', __( 'Envio não encontrado.', 'vapor-hub-loja' ), [ 'status' => 404 ] );
        }

        if ( 'aprovado' === $item['status'] ) {
            return new WP_Error( 'ja_aprovado', __( 'Este envio já foi aprovado e publicado na galeria.', 'vapor-hub-loja' ), [ 'status' => 409 ] );
        }

        if ( 'recusado' === $item['status'] ) {
            return true;
        }

        $atualizado = $wpdb->update(
            self::nome_tabela(),
            [
                'status'       => 'recusado',
                'revisado_em'  => current_time( 'mysql', true ),
                'revisado_por' => get_current_user_id(),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );

        if ( false === $atualizado ) {
            return new WP_Error( 'falha_recusar', __( 'Não foi possível recusar o envio.', 'vapor-hub-loja' ), [ 'status' => 500 ] );
        }

        do_action( 'vh_comunidade_envio_recusado', $id, $item );

        return true;
    }

    /**
     * @param array<string,mixed> $arquivo
     * @return array{file:string,url:string,type:string}|WP_Error
     */
    private static function processar_upload( array $arquivo ): array|WP_Error {
        if ( empty( $arquivo['tmp_name'] ) || ! is_uploaded_file( $arquivo['tmp_name'] ) ) {
            return new WP_Error( 'foto_obrigatoria', __( 'Selecione uma foto para enviar.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( ! empty( $arquivo['error'] ) && UPLOAD_ERR_OK !== (int) $arquivo['error'] ) {
            return new WP_Error( 'upload_erro', __( 'Erro ao enviar a foto. Tente outra imagem.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $tamanho_max = self::TAMANHO_MAX_MB * 1024 * 1024;
        if ( ! empty( $arquivo['size'] ) && (int) $arquivo['size'] > $tamanho_max ) {
            return new WP_Error(
                'foto_grande',
                sprintf(
                    /* translators: %d: tamanho máximo em MB. */
                    __( 'A foto deve ter no máximo %d MB.', 'vapor-hub-loja' ),
                    self::TAMANHO_MAX_MB
                ),
                [ 'status' => 400 ]
            );
        }

        $tipos_permitidos = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ];

        require_once ABSPATH . 'wp-admin/includes/file.php';

        add_filter( 'upload_dir', [ __CLASS__, 'filtrar_upload_dir' ] );
        $resultado = wp_handle_upload(
            $arquivo,
            [
                'test_form' => false,
                'mimes'     => $tipos_permitidos,
            ]
        );
        remove_filter( 'upload_dir', [ __CLASS__, 'filtrar_upload_dir' ] );

        if ( isset( $resultado['error'] ) ) {
            return new WP_Error( 'upload_invalido', $resultado['error'], [ 'status' => 400 ] );
        }

        if ( empty( $resultado['file'] ) || empty( $resultado['url'] ) ) {
            return new WP_Error( 'upload_invalido', __( 'Formato de imagem não suportado. Use JPEG, PNG ou WebP.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $ajuste = VH_Media_Profiles::aplicar_perfil( $resultado['file'], 'comunidade' );
        if ( is_wp_error( $ajuste ) ) {
            if ( file_exists( $resultado['file'] ) ) {
                @unlink( $resultado['file'] );
            }
            return $ajuste;
        }

        if ( class_exists( 'VH_Media' ) ) {
            $resultado = VH_Media::converter_para_webp( $resultado );
        }

        return [
            'file' => (string) $resultado['file'],
            'url'  => (string) $resultado['url'],
            'type' => (string) ( $resultado['type'] ?? 'image/webp' ),
        ];
    }

    /**
     * @param array<string,string> $dirs
     * @return array<string,string>
     */
    public static function filtrar_upload_dir( array $dirs ): array {
        $subdir = '/comunidade-envios/' . gmdate( 'Y/m' );
        $dirs['subdir'] = $subdir;
        $dirs['path']   = $dirs['basedir'] . $subdir;
        $dirs['url']    = $dirs['baseurl'] . $subdir;

        if ( ! wp_mkdir_p( $dirs['path'] ) ) {
            return $dirs;
        }

        return $dirs;
    }

    private static function normalizar_instagram( string $valor ): string {
        $valor = trim( $valor );
        $valor = ltrim( $valor, '@#' );
        $valor = preg_replace( '#^https?://(www\.)?instagram\.com/#i', '', $valor ) ?? $valor;
        $valor = trim( $valor, "/ \t\n\r\0\x0B" );
        $valor = preg_replace( '#/.*$#', '', $valor ) ?? $valor;
        $valor = sanitize_user( $valor, true );
        return mb_substr( $valor, 0, 60 );
    }

    /**
     * @param array<string,mixed> $linha
     * @return array<string,mixed>
     */
    private static function formatar( array $linha, bool $completo = false ): array {
        $item = [
            'id'         => (int) $linha['id'],
            'nome'       => (string) $linha['nome'],
            'instagram'  => (string) $linha['instagram'],
            'link'       => (string) $linha['link'],
            'legenda'    => (string) $linha['legenda'],
            'imagem_url' => (string) $linha['imagem_url'],
            'status'     => (string) ( $linha['status'] ?? 'pendente' ),
            'origem'     => (string) ( $linha['origem'] ?? 'popup' ),
            'criado_em'  => self::formatar_data( (string) $linha['criado_em'] ),
            'navegador'  => self::resumir_user_agent( (string) ( $linha['user_agent'] ?? '' ) ),
        ];

        if ( $completo ) {
            $item['ip']           = (string) ( $linha['ip'] ?? '' );
            $item['user_agent']   = (string) ( $linha['user_agent'] ?? '' );
            $item['referrer']     = (string) ( $linha['referrer'] ?? '' );
            $item['imagem_path']  = (string) ( $linha['imagem_path'] ?? '' );
            $item['revisado_em']  = ! empty( $linha['revisado_em'] ) ? self::formatar_data( (string) $linha['revisado_em'] ) : '';
            $item['revisado_por'] = ! empty( $linha['revisado_por'] ) ? (int) $linha['revisado_por'] : 0;
        }

        return $item;
    }

    private static function formatar_data( string $mysql ): string {
        return VH_DateTime::formatar_curto( $mysql );
    }

    private static function resumir_user_agent( string $ua ): string {
        if ( '' === $ua ) {
            return '—';
        }
        $mapa = [
            'Edg/'     => 'Microsoft Edge',
            'Chrome/'  => 'Google Chrome',
            'Firefox/' => 'Mozilla Firefox',
            'Safari/'  => 'Safari',
            'OPR/'     => 'Opera',
        ];
        foreach ( $mapa as $needle => $rotulo ) {
            if ( str_contains( $ua, $needle ) ) {
                return $rotulo;
            }
        }
        return mb_substr( $ua, 0, 48 );
    }

    private static function chave_rate_limit(): string {
        $ip = VH_Auth::ip_cliente();
        return 'vh_comunidade_envio_' . md5( $ip ?: 'anon' );
    }

    private static function rate_limit_excedido(): bool {
        return (int) get_transient( self::chave_rate_limit() ) >= self::RATE_MAX;
    }

    private static function incrementar_rate(): void {
        $chave = self::chave_rate_limit();
        $atual = (int) get_transient( $chave );
        set_transient( $chave, $atual + 1, self::RATE_SECONDS );
    }
}

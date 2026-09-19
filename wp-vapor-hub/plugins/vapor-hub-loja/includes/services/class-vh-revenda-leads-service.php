<?php
/**
 * Cadastros de revendedores — persistência e consulta.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Revenda_Leads_Service {

    private const RATE_MAX     = 8;
    private const RATE_SECONDS = 600;

    public static function nome_tabela(): string {
        global $wpdb;
        return $wpdb->prefix . 'vh_revenda_cadastros';
    }

    /**
     * Cria ou atualiza a tabela via dbDelta.
     */
    public static function instalar_tabela(): void {
        global $wpdb;

        $tabela  = self::nome_tabela();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tabela} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            nome varchar(200) NOT NULL,
            documento varchar(32) NOT NULL DEFAULT '',
            whatsapp varchar(32) NOT NULL DEFAULT '',
            email varchar(200) NOT NULL,
            ip varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(500) NOT NULL DEFAULT '',
            referrer varchar(500) NOT NULL DEFAULT '',
            origem varchar(32) NOT NULL DEFAULT 'popup',
            status varchar(20) NOT NULL DEFAULT 'novo',
            email_enviado tinyint(1) NOT NULL DEFAULT 0,
            whatsapp_enviado tinyint(1) NOT NULL DEFAULT 0,
            criado_em datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY criado_em (criado_em),
            KEY email (email(100))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * @param array<string,string> $dados
     * @param array<string,string> $meta
     */
    public static function registrar( array $dados, array $meta = [] ): int|WP_Error {
        global $wpdb;

        $nome          = sanitize_text_field( $dados['nome'] ?? '' );
        $documento_raw = sanitize_text_field( $dados['documento'] ?? '' );
        $whatsapp_raw  = sanitize_text_field( $dados['whatsapp'] ?? '' );
        $email         = sanitize_email( $dados['email'] ?? '' );
        $tipo_doc      = sanitize_key( $dados['tipo_documento'] ?? '' );

        if ( '' === $nome || '' === $email || ! is_email( $email ) ) {
            return new WP_Error( 'dados_invalidos', __( 'Preencha nome e e-mail válidos.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        $documento = self::normalizar_documento( $documento_raw, $tipo_doc );
        if ( is_wp_error( $documento ) ) {
            return $documento;
        }

        $whatsapp = self::normalizar_whatsapp( $whatsapp_raw );
        if ( is_wp_error( $whatsapp ) ) {
            return $whatsapp;
        }

        if ( self::rate_limit_excedido() ) {
            return new WP_Error( 'rate_limit', __( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 'vapor-hub-loja' ), [ 'status' => 429 ] );
        }

        self::incrementar_rate();

        $inserido = $wpdb->insert(
            self::nome_tabela(),
            [
                'nome'             => $nome,
                'documento'        => $documento,
                'whatsapp'         => $whatsapp,
                'email'            => $email,
                'ip'               => sanitize_text_field( $meta['ip'] ?? '' ),
                'user_agent'       => sanitize_text_field( mb_substr( (string) ( $meta['user_agent'] ?? '' ), 0, 500 ) ),
                'referrer'         => esc_url_raw( $meta['referrer'] ?? '' ),
                'origem'           => sanitize_key( $meta['origem'] ?? 'popup' ),
                'status'           => 'novo',
                'email_enviado'    => 0,
                'whatsapp_enviado' => 0,
                'criado_em'        => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
        );

        if ( false === $inserido ) {
            return new WP_Error( 'falha_salvar', __( 'Não foi possível registrar o cadastro. Tente novamente.', 'vapor-hub-loja' ), [ 'status' => 500 ] );
        }

        $id = (int) $wpdb->insert_id;

        /**
         * Disparado após salvar um cadastro de revenda (SMTP/WhatsApp futuros).
         *
         * @param int                  $id
         * @param array<string,string> $dados
         */
        do_action( 'vh_revenda_cadastro_salvo', $id, array_merge( $dados, [
            'documento' => $documento,
            'whatsapp'  => $whatsapp,
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
            $where   .= ' AND (nome LIKE %s OR email LIKE %s OR documento LIKE %s OR whatsapp LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( '' !== $status && in_array( $status, [ 'novo', 'lido', 'arquivado' ], true ) ) {
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
    public static function obter( int $id, bool $marcar_lido = false ): ?array {
        global $wpdb;

        $tabela = self::nome_tabela();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $linha = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id = %d", $id ), ARRAY_A );

        if ( ! $linha ) {
            return null;
        }

        if ( $marcar_lido && 'novo' === ( $linha['status'] ?? '' ) ) {
            $wpdb->update(
                $tabela,
                [ 'status' => 'lido' ],
                [ 'id' => $id ],
                [ '%s' ],
                [ '%d' ]
            );
            $linha['status'] = 'lido';
        }

        return self::formatar( $linha, true );
    }

    /**
     * @param array<string,mixed> $linha
     * @return array<string,mixed>
     */
    private static function formatar( array $linha, bool $completo = false ): array {
        $item = [
            'id'        => (int) $linha['id'],
            'nome'      => (string) $linha['nome'],
            'documento' => (string) $linha['documento'],
            'whatsapp'  => (string) $linha['whatsapp'],
            'email'     => (string) $linha['email'],
            'status'    => (string) ( $linha['status'] ?? 'novo' ),
            'origem'    => (string) ( $linha['origem'] ?? 'popup' ),
            'criado_em' => self::formatar_data( (string) $linha['criado_em'] ),
            'navegador' => self::resumir_user_agent( (string) ( $linha['user_agent'] ?? '' ) ),
        ];

        if ( $completo ) {
            $item['ip']               = (string) ( $linha['ip'] ?? '' );
            $item['user_agent']       = (string) ( $linha['user_agent'] ?? '' );
            $item['referrer']         = (string) ( $linha['referrer'] ?? '' );
            $item['email_enviado']    = ! empty( $linha['email_enviado'] );
            $item['whatsapp_enviado'] = ! empty( $linha['whatsapp_enviado'] );
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

    public static function notificar_loja_por_email( int $id, array $dados ): bool {
        $revenda = VH_Settings::obter( 'vh_revenda' );
        if ( empty( $revenda['notificar_email'] ) || '1' !== (string) $revenda['notificar_email'] ) {
            return false;
        }

        $destino = get_option( 'admin_email' );
        if ( ! $destino ) {
            return false;
        }

        $assunto = sprintf(
            '[%s] %s',
            wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            __( 'Novo cadastro de revendedor', 'vapor-hub-loja' )
        );

        $mensagem = sprintf(
            // translators: 1: nome, 2: documento, 3: whatsapp, 4: e-mail, 5: ID interno.
            __( "Novo pedido de cadastro como revendedor:\n\nNome: %1\$s\nCPF/CNPJ: %2\$s\nWhatsApp: %3\$s\nE-mail: %4\$s\n\nRegistro #%5\$d no painel Minha Loja → Revenda.\n", 'vapor-hub-loja' ),
            $dados['nome'] ?? '',
            $dados['documento'] ?? '',
            $dados['whatsapp'] ?? '',
            $dados['email'] ?? '',
            $id
        );

        $enviado = wp_mail( $destino, $assunto, $mensagem, [ 'Content-Type: text/plain; charset=UTF-8' ] );

        if ( $enviado ) {
            global $wpdb;
            $wpdb->update(
                self::nome_tabela(),
                [ 'email_enviado' => 1 ],
                [ 'id' => $id ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        return (bool) $enviado;
    }

    private static function chave_rate_limit(): string {
        $ip = VH_Auth::ip_cliente();
        return 'vh_revenda_' . md5( $ip ?: 'anon' );
    }

    private static function rate_limit_excedido(): bool {
        return (int) get_transient( self::chave_rate_limit() ) >= self::RATE_MAX;
    }

    private static function incrementar_rate(): void {
        $chave = self::chave_rate_limit();
        $atual = (int) get_transient( $chave );
        set_transient( $chave, $atual + 1, self::RATE_SECONDS );
    }

    /**
     * @return string|WP_Error
     */
    private static function normalizar_documento( string $valor, string $tipo = '' ): string|WP_Error {
        $digitos = preg_replace( '/\D/', '', $valor ) ?? '';
        $tipo    = 'cnpj' === $tipo || strlen( $digitos ) > 11 ? 'cnpj' : 'cpf';

        if ( 'cnpj' === $tipo ) {
            if ( 14 !== strlen( $digitos ) ) {
                return new WP_Error( 'documento_invalido', __( 'Informe um CNPJ válido com 14 dígitos.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
            }
            return substr( $digitos, 0, 2 ) . '.' . substr( $digitos, 2, 3 ) . '.'
                . substr( $digitos, 5, 3 ) . '/' . substr( $digitos, 8, 4 ) . '-' . substr( $digitos, 12, 2 );
        }

        if ( 11 !== strlen( $digitos ) ) {
            return new WP_Error( 'documento_invalido', __( 'Informe um CPF válido com 11 dígitos.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        return substr( $digitos, 0, 3 ) . '.' . substr( $digitos, 3, 3 ) . '.'
            . substr( $digitos, 6, 3 ) . '-' . substr( $digitos, 9, 2 );
    }

    /**
     * @return string|WP_Error
     */
    private static function normalizar_whatsapp( string $valor ): string|WP_Error {
        $digitos = preg_replace( '/\D/', '', $valor ) ?? '';
        $len     = strlen( $digitos );

        if ( $len < 10 || $len > 11 ) {
            return new WP_Error( 'whatsapp_invalido', __( 'Informe um WhatsApp válido com DDD e número.', 'vapor-hub-loja' ), [ 'status' => 400 ] );
        }

        if ( 10 === $len ) {
            return sprintf( '(%s) %s-%s', substr( $digitos, 0, 2 ), substr( $digitos, 2, 4 ), substr( $digitos, 6, 4 ) );
        }

        return sprintf( '(%s) %s-%s', substr( $digitos, 0, 2 ), substr( $digitos, 2, 5 ), substr( $digitos, 7, 4 ) );
    }
}

<?php
/**
 * Log persistente da integração Tiny ERP (somente leitura admin).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Tiny_Log {

	public const NIVEL_ERROR   = 'error';
	public const NIVEL_WARNING = 'warning';
	public const NIVEL_INFO    = 'info';
	public const NIVEL_SUCCESS = 'success';

	public const ORIGEM_API     = 'api';
	public const ORIGEM_QUEUE   = 'queue';
	public const ORIGEM_SYNC    = 'sync';
	public const ORIGEM_WEBHOOK = 'webhook';
	public const ORIGEM_MAPPING = 'mapping';
	public const ORIGEM_OAUTH   = 'oauth';
	public const ORIGEM_CONFIG  = 'config';
	public const ORIGEM_SYSTEM  = 'system';

	private const RETENCAO_DIAS = 30;
	private const MAX_MENSAGEM  = 2000;

	public static function nome_tabela(): string {
		global $wpdb;
		return $wpdb->prefix . 'vh_tiny_log';
	}

	public static function instalar_tabela(): void {
		global $wpdb;

		$tabela  = self::nome_tabela();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tabela} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			nivel varchar(16) NOT NULL DEFAULT 'info',
			origem varchar(32) NOT NULL DEFAULT 'system',
			referencia varchar(64) NOT NULL DEFAULT '',
			mensagem text NOT NULL,
			contexto longtext NOT NULL,
			criado_em datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY nivel (nivel),
			KEY origem (origem),
			KEY criado_em (criado_em)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array<string, mixed> $contexto
	 */
	public static function registrar( string $nivel, string $origem, string $referencia, string $mensagem, array $contexto = [] ): int {
		global $wpdb;

		$nivel = self::sanitizar_nivel( $nivel );
		$origem = sanitize_key( $origem );
		if ( '' === $origem ) {
			$origem = self::ORIGEM_SYSTEM;
		}

		$referencia = sanitize_text_field( mb_substr( $referencia, 0, 64 ) );
		$mensagem   = sanitize_text_field( mb_substr( trim( $mensagem ), 0, self::MAX_MENSAGEM ) );
		if ( '' === $mensagem ) {
			return 0;
		}

		$wpdb->insert(
			self::nome_tabela(),
			[
				'nivel'      => $nivel,
				'origem'     => $origem,
				'referencia' => $referencia,
				'mensagem'   => $mensagem,
				'contexto'   => wp_json_encode( $contexto ),
				'criado_em'  => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	public static function error( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_ERROR, $origem, $referencia, $mensagem, $contexto );
	}

	public static function warning( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_WARNING, $origem, $referencia, $mensagem, $contexto );
	}

	public static function info( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_INFO, $origem, $referencia, $mensagem, $contexto );
	}

	public static function success( string $origem, string $mensagem, string $referencia = '', array $contexto = [] ): int {
		return self::registrar( self::NIVEL_SUCCESS, $origem, $referencia, $mensagem, $contexto );
	}

	/**
	 * @return array{itens: array<int, array<string, mixed>>, total: int, paginas: int}
	 */
	public static function listar( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'pagina'   => 1,
			'por_pag'  => 50,
			'nivel'    => '',
			'origem'   => '',
			'busca'    => '',
		];
		$args     = wp_parse_args( $args, $defaults );

		$tabela   = self::nome_tabela();
		$where    = [ '1=1' ];
		$params   = [];

		if ( '' !== $args['nivel'] ) {
			$where[]  = 'nivel = %s';
			$params[] = self::sanitizar_nivel( (string) $args['nivel'] );
		}

		if ( '' !== $args['origem'] ) {
			$where[]  = 'origem = %s';
			$params[] = sanitize_key( (string) $args['origem'] );
		}

		$busca = sanitize_text_field( (string) $args['busca'] );
		if ( '' !== $busca ) {
			$like     = '%' . $wpdb->esc_like( $busca ) . '%';
			$where[]  = '(mensagem LIKE %s OR referencia LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		if ( $params ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tabela} WHERE {$where_sql}",
					...$params
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabela} WHERE {$where_sql}" );
		}

		$por_pag = max( 10, min( 100, (int) $args['por_pag'] ) );
		$pagina  = max( 1, (int) $args['pagina'] );
		$offset  = ( $pagina - 1 ) * $por_pag;
		$params[] = $por_pag;
		$params[] = $offset;

		$linhas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, nivel, origem, referencia, mensagem, contexto, criado_em
				FROM {$tabela}
				WHERE {$where_sql}
				ORDER BY id DESC
				LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		$itens = [];
		foreach ( (array) $linhas as $linha ) {
			$itens[] = self::normalizar_linha( $linha );
		}

		return [
			'itens'   => $itens,
			'total'   => $total,
			'paginas' => (int) ceil( $total / $por_pag ),
			'pagina'  => $pagina,
		];
	}

	/**
	 * @return array<string, int>
	 */
	public static function contagem_por_nivel(): array {
		global $wpdb;
		$tabela = self::nome_tabela();
		$linhas = $wpdb->get_results( "SELECT nivel, COUNT(*) AS total FROM {$tabela} GROUP BY nivel", ARRAY_A );
		$saida  = [
			self::NIVEL_ERROR   => 0,
			self::NIVEL_WARNING => 0,
			self::NIVEL_INFO    => 0,
			self::NIVEL_SUCCESS => 0,
		];
		foreach ( (array) $linhas as $linha ) {
			$nivel = self::sanitizar_nivel( (string) ( $linha['nivel'] ?? '' ) );
			if ( isset( $saida[ $nivel ] ) ) {
				$saida[ $nivel ] = (int) ( $linha['total'] ?? 0 );
			}
		}
		return $saida;
	}

	public static function limpar_antigos( int $dias = 0 ): int {
		global $wpdb;
		$dias  = $dias > 0 ? $dias : self::RETENCAO_DIAS;
		$corte = gmdate( 'Y-m-d H:i:s', time() - ( $dias * DAY_IN_SECONDS ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::nome_tabela() . ' WHERE criado_em < %s',
				$corte
			)
		);
	}

	public static function formatar_data( string $mysql ): string {
		return VH_DateTime::formatar( $mysql );
	}

	/**
	 * @return array<string, string>
	 */
	public static function rotulos_nivel(): array {
		return [
			self::NIVEL_ERROR   => __( 'Erro', 'vapor-hub-loja' ),
			self::NIVEL_WARNING => __( 'Aviso', 'vapor-hub-loja' ),
			self::NIVEL_INFO    => __( 'Info', 'vapor-hub-loja' ),
			self::NIVEL_SUCCESS => __( 'Sucesso', 'vapor-hub-loja' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function rotulos_origem(): array {
		return [
			self::ORIGEM_API     => __( 'API', 'vapor-hub-loja' ),
			self::ORIGEM_QUEUE   => __( 'Fila', 'vapor-hub-loja' ),
			self::ORIGEM_SYNC    => __( 'Sync', 'vapor-hub-loja' ),
			self::ORIGEM_WEBHOOK => __( 'Webhook', 'vapor-hub-loja' ),
			self::ORIGEM_MAPPING => __( 'Mapeamento', 'vapor-hub-loja' ),
			self::ORIGEM_OAUTH   => __( 'OAuth', 'vapor-hub-loja' ),
			self::ORIGEM_CONFIG  => __( 'Config', 'vapor-hub-loja' ),
			self::ORIGEM_SYSTEM  => __( 'Sistema', 'vapor-hub-loja' ),
		];
	}

	/**
	 * @param array<string, mixed> $linha
	 * @return array<string, mixed>
	 */
	private static function normalizar_linha( array $linha ): array {
		$contexto = json_decode( (string) ( $linha['contexto'] ?? '{}' ), true );
		if ( ! is_array( $contexto ) ) {
			$contexto = [];
		}

		return [
			'id'         => (int) ( $linha['id'] ?? 0 ),
			'nivel'      => self::sanitizar_nivel( (string) ( $linha['nivel'] ?? '' ) ),
			'origem'     => sanitize_key( (string) ( $linha['origem'] ?? '' ) ),
			'referencia' => (string) ( $linha['referencia'] ?? '' ),
			'mensagem'   => (string) ( $linha['mensagem'] ?? '' ),
			'contexto'   => $contexto,
			'criado_em'  => self::formatar_data( (string) ( $linha['criado_em'] ?? '' ) ),
		];
	}

	private static function sanitizar_nivel( string $nivel ): string {
		$nivel = sanitize_key( $nivel );
		$validos = [ self::NIVEL_ERROR, self::NIVEL_WARNING, self::NIVEL_INFO, self::NIVEL_SUCCESS ];
		return in_array( $nivel, $validos, true ) ? $nivel : self::NIVEL_INFO;
	}
}

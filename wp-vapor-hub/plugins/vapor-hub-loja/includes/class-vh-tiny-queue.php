<?php
/**
 * Fila assíncrona de sincronização Tiny ERP.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Tiny_Queue {

	public const CRON_HOOK     = 'vh_tiny_processar_fila';
	public const CRON_RECONCIL = 'vh_tiny_reconciliar';

	private const MAX_TENTATIVAS = 5;
	private const LOTE           = 15;

	public static function nome_tabela(): string {
		global $wpdb;
		return $wpdb->prefix . 'vh_tiny_fila';
	}

	public static function instalar_tabela(): void {
		global $wpdb;

		$tabela  = self::nome_tabela();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tabela} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tipo varchar(32) NOT NULL,
			referencia varchar(64) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'pending',
			tentativas tinyint(3) unsigned NOT NULL DEFAULT 0,
			erro text NOT NULL,
			criado_em datetime NOT NULL,
			processado_em datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY tipo_ref (tipo, referencia),
			KEY criado_em (criado_em)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function agendar_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'vh_tiny_cinco_min', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::CRON_RECONCIL ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_RECONCIL );
		}
	}

	public static function limpar_cron(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CRON_RECONCIL );
	}

	public static function registrar_intervalo( array $schedules ): array {
		$schedules['vh_tiny_cinco_min'] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'A cada 5 minutos (Tiny ERP)', 'vapor-hub-loja' ),
		];
		return $schedules;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function enfileirar( string $tipo, string $referencia, array $payload = [] ): int {
		global $wpdb;

		$tipo       = sanitize_key( $tipo );
		$referencia = sanitize_text_field( mb_substr( $referencia, 0, 64 ) );

		if ( '' === $tipo ) {
			return 0;
		}

		$existente = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . self::nome_tabela() . " WHERE tipo = %s AND referencia = %s AND status IN ('pending','processing') LIMIT 1",
				$tipo,
				$referencia
			)
		);
		if ( $existente > 0 ) {
			return $existente;
		}

		$wpdb->insert(
			self::nome_tabela(),
			[
				'tipo'       => $tipo,
				'referencia' => $referencia,
				'payload'    => wp_json_encode( $payload ),
				'status'     => 'pending',
				'tentativas' => 0,
				'erro'       => '',
				'criado_em'  => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		$id = (int) $wpdb->insert_id;
		if ( $id > 0 ) {
			VH_Tiny_Log::info(
				VH_Tiny_Log::ORIGEM_QUEUE,
				sprintf(
					/* translators: 1: job type, 2: reference */
					__( 'Job enfileirado: %1$s (%2$s)', 'vapor-hub-loja' ),
					$tipo,
					$referencia
				),
				$referencia,
				[ 'job_id' => $id, 'tipo' => $tipo ]
			);
		}

		return $id;
	}

	public static function formatar_data( string $mysql ): string {
		return VH_DateTime::formatar_curto( $mysql );
	}

	/**
	 * Processa vários lotes seguidos (botão "Sincronizar agora").
	 *
	 * @return array{processados:int, erros:int, lotes:int}
	 */
	public static function processar_manual( int $max_lotes = 20 ): array {
		$processados = 0;
		$erros       = 0;
		$lotes       = 0;

		for ( $i = 0; $i < max( 1, $max_lotes ); $i++ ) {
			$resultado = self::processar_lote();
			++$lotes;
			$processados += (int) ( $resultado['processados'] ?? 0 );
			$erros       += (int) ( $resultado['erros'] ?? 0 );
			if ( 0 === (int) ( $resultado['processados'] ?? 0 ) && 0 === (int) ( $resultado['erros'] ?? 0 ) ) {
				break;
			}
		}

		return [
			'processados' => $processados,
			'erros'       => $erros,
			'lotes'       => $lotes,
		];
	}

	public static function processar_lote(): array {
		$dados = VH_Tiny::obter();
		if ( empty( $dados['ativo'] ) || ! VH_Tiny::driver()->conectado() ) {
			return [ 'processados' => 0, 'erros' => 0 ];
		}

		global $wpdb;
		$tabela = self::nome_tabela();

		self::recuperar_jobs_presos();

		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tabela} WHERE status = %s ORDER BY id ASC LIMIT %d",
				'pending',
				self::LOTE
			),
			ARRAY_A
		);

		$processados = 0;
		$erros       = 0;

		foreach ( (array) $jobs as $job ) {
			$id = (int) $job['id'];
			$locked = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$tabela} SET status = 'processing' WHERE id = %d AND status = 'pending'",
					$id
				)
			);
			if ( ! $locked ) {
				continue;
			}

			$resultado = self::executar_job( $job );
			if ( is_wp_error( $resultado ) ) {
				$data = $resultado->get_error_data();
				if ( is_array( $data ) && ! empty( $data['vh_requeue'] ) ) {
					$wpdb->update(
						$tabela,
						[ 'status' => 'pending' ],
						[ 'id' => $id ],
						[ '%s' ],
						[ '%d' ]
					);
					continue;
				}
				++$erros;
				self::marcar_falha( $id, $resultado->get_error_message(), (int) $job['tentativas'] + 1 );
			} else {
				++$processados;
				self::marcar_concluido( $id );
			}
		}

		return [ 'processados' => $processados, 'erros' => $erros ];
	}

	private static function recuperar_jobs_presos(): void {
		global $wpdb;
		$corte = gmdate( 'Y-m-d H:i:s', time() - ( 15 * MINUTE_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE " . self::nome_tabela() . " SET status = 'pending' WHERE status = 'processing' AND criado_em < %s",
				$corte
			)
		);
	}

	/**
	 * Defesa de direção por tipo de job.
	 *
	 * Um job enfileirado antes de o lojista desligar uma direção não pode rodar
	 * depois. O erro carrega `vh_requeue`, então o job volta para a fila em vez
	 * de queimar tentativas: se a direção voltar a ser autorizada, ele roda.
	 *
	 * @return true|WP_Error
	 */
	public static function direcao_autorizada( string $tipo ) {
		if ( in_array( $tipo, [ 'produto_push', 'categoria_push' ], true ) && ! VH_Tiny::pode_enviar_cadastro() ) {
			return new WP_Error(
				'vh_tiny_direcao_pausada',
				__( 'Envio de cadastro ao Tiny desativado.', 'vapor-hub-loja' ),
				[ 'vh_requeue' => true ]
			);
		}
		if ( 'estoque_push' === $tipo && ! VH_Tiny::campo_liberado( 'estoque', 'saida' ) ) {
			return new WP_Error(
				'vh_tiny_direcao_pausada',
				__( 'Envio de estoque ao Tiny desativado.', 'vapor-hub-loja' ),
				[ 'vh_requeue' => true ]
			);
		}
		if ( 'pedido_push' === $tipo && ! VH_Tiny::campo_liberado( 'pedido', 'saida' ) ) {
			return new WP_Error(
				'vh_tiny_direcao_pausada',
				__( 'Envio de pedido ao Tiny desativado.', 'vapor-hub-loja' ),
				[ 'vh_requeue' => true ]
			);
		}

		if ( 'estoque_pull' === $tipo && ! VH_Tiny::campo_liberado( 'estoque', 'entrada' ) ) {
			return new WP_Error(
				'vh_tiny_direcao_pausada',
				__( 'Recebimento de estoque do Tiny desativado.', 'vapor-hub-loja' ),
				[ 'vh_requeue' => true ]
			);
		}

		if ( 'produto_pull' === $tipo && ! VH_Tiny::pode_receber_vinculo() ) {
			return new WP_Error(
				'vh_tiny_direcao_pausada',
				__( 'Recebimento do Tiny desativado.', 'vapor-hub-loja' ),
				[ 'vh_requeue' => true ]
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $job
	 * @return true|WP_Error
	 */
	private static function executar_job( array $job ) {
		$tipo    = (string) ( $job['tipo'] ?? '' );
		$payload = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		$direcao = self::direcao_autorizada( $tipo );
		if ( is_wp_error( $direcao ) ) {
			return $direcao;
		}

		switch ( $tipo ) {
			case 'produto_push':
				return VH_Tiny_Sync_Service::empurrar_produto( (int) ( $payload['produto_id'] ?? 0 ) );
			case 'categoria_push':
				return VH_Tiny_Category_Sync::empurrar_categoria( (int) ( $payload['term_id'] ?? 0 ) );
			case 'produto_pull':
				return VH_Tiny_Sync_Service::puxar_produto(
					(int) ( $payload['tiny_id'] ?? 0 ),
					(string) ( $payload['sku'] ?? '' )
				);
			case 'estoque_push':
				return VH_Tiny_Sync_Service::empurrar_estoque( (int) ( $payload['produto_id'] ?? 0 ) );
			case 'estoque_pull':
				return VH_Tiny_Sync_Service::puxar_estoque( (int) ( $payload['produto_id'] ?? 0 ) );
			case 'pedido_push':
				return VH_Tiny_Sync_Service::empurrar_pedido( (int) ( $payload['pedido_id'] ?? 0 ) );
			case 'reconciliar':
				VH_Tiny_Sync_Service::reconciliar();
				return true;
			default:
				return new WP_Error( 'vh_tiny_job_desconhecido', __( 'Tipo de job desconhecido.', 'vapor-hub-loja' ) );
		}
	}

	private static function marcar_concluido( int $id ): void {
		global $wpdb;
		$wpdb->update(
			self::nome_tabela(),
			[
				'status'        => 'done',
				'processado_em' => current_time( 'mysql', true ),
				'erro'          => '',
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		$job = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT tipo, referencia FROM ' . self::nome_tabela() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);
		if ( is_array( $job ) ) {
			VH_Tiny_Log::success(
				VH_Tiny_Log::ORIGEM_QUEUE,
				sprintf(
					/* translators: 1: job type, 2: reference */
					__( 'Job concluído: %1$s (%2$s)', 'vapor-hub-loja' ),
					(string) ( $job['tipo'] ?? '' ),
					(string) ( $job['referencia'] ?? '' )
				),
				(string) ( $job['referencia'] ?? '' ),
				[ 'job_id' => $id, 'tipo' => (string) ( $job['tipo'] ?? '' ) ]
			);
		}
	}

	private static function marcar_falha( int $id, string $erro, int $tentativas ): void {
		global $wpdb;
		$status = $tentativas >= self::MAX_TENTATIVAS ? 'failed' : 'pending';
		$wpdb->update(
			self::nome_tabela(),
			[
				'status'     => $status,
				'tentativas' => $tentativas,
				'erro'       => sanitize_text_field( mb_substr( $erro, 0, 1000 ) ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s' ],
			[ '%d' ]
		);

		$job = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT tipo, referencia FROM ' . self::nome_tabela() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);
		$referencia = is_array( $job ) ? (string) ( $job['referencia'] ?? '' ) : '';
		$tipo       = is_array( $job ) ? (string) ( $job['tipo'] ?? '' ) : '';

		if ( 'failed' === $status ) {
			VH_Tiny_Log::error(
				VH_Tiny_Log::ORIGEM_QUEUE,
				$erro,
				$referencia,
				[ 'job_id' => $id, 'tipo' => $tipo, 'tentativas' => $tentativas ]
			);
		} else {
			VH_Tiny_Log::warning(
				VH_Tiny_Log::ORIGEM_QUEUE,
				$erro,
				$referencia,
				[ 'job_id' => $id, 'tipo' => $tipo, 'tentativas' => $tentativas ]
			);
		}
	}

	/**
	 * @return array<string, int>
	 */
	public static function contagem_por_status(): array {
		global $wpdb;
		$tabela = self::nome_tabela();
		$linhas = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$tabela} GROUP BY status", ARRAY_A );
		$saida  = [ 'pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0 ];
		foreach ( (array) $linhas as $linha ) {
			$status = (string) ( $linha['status'] ?? '' );
			if ( isset( $saida[ $status ] ) ) {
				$saida[ $status ] = (int) $linha['total'];
			}
		}
		return $saida;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function listar_recentes( int $limite = 20 ): array {
		return self::listar_filtrado( [ 'limite' => $limite ] )['itens'];
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array{itens: array<int, array<string, mixed>>, total: int, paginas: int, pagina: int}
	 */
	public static function listar_filtrado( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'pagina'  => 1,
			'limite'  => 50,
			'status'  => '',
			'tipo'    => '',
			'busca'   => '',
		];
		$args     = wp_parse_args( $args, $defaults );

		$tabela = self::nome_tabela();
		$where  = [ '1=1' ];
		$params = [];

		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}

		if ( '' !== $args['tipo'] ) {
			$where[]  = 'tipo = %s';
			$params[] = sanitize_key( (string) $args['tipo'] );
		}

		$busca = sanitize_text_field( (string) $args['busca'] );
		if ( '' !== $busca ) {
			$like     = '%' . $wpdb->esc_like( $busca ) . '%';
			$where[]  = '(referencia LIKE %s OR erro LIKE %s OR tipo LIKE %s)';
			$params[] = $like;
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

		$limite = max( 10, min( 100, (int) $args['limite'] ) );
		$pagina = max( 1, (int) $args['pagina'] );
		$offset = ( $pagina - 1 ) * $limite;
		$params[] = $limite;
		$params[] = $offset;

		$linhas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, tipo, referencia, status, tentativas, erro, criado_em, processado_em
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
			$itens[] = self::normalizar_linha_fila( $linha );
		}

		return [
			'itens'   => $itens,
			'total'   => $total,
			'paginas' => (int) ceil( $total / $limite ),
			'pagina'  => $pagina,
		];
	}

	/**
	 * @param array<string, mixed> $linha
	 * @return array<string, mixed>
	 */
	private static function normalizar_linha_fila( array $linha ): array {
		if ( ! empty( $linha['criado_em'] ) ) {
			$linha['criado_em'] = self::formatar_data( (string) $linha['criado_em'] );
		}
		if ( ! empty( $linha['processado_em'] ) ) {
			$linha['processado_em'] = self::formatar_data( (string) $linha['processado_em'] );
		}
		return $linha;
	}

	public static function limpar_concluidos( int $dias = 7 ): int {
		global $wpdb;
		$corte = gmdate( 'Y-m-d H:i:s', time() - ( $dias * DAY_IN_SECONDS ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::nome_tabela() . " WHERE status = 'done' AND processado_em < %s",
				$corte
			)
		);
	}
}

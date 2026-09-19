<?php
/**
 * Orquestração de sincronização WooCommerce ↔ Tiny ERP.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Tiny_Sync_Service {

	private const GUARD_SECONDS = 90;

	/**
	 * Acima deste número de variações o envio manual vai para a fila assíncrona,
	 * evitando timeout da requisição (cada variação é uma chamada à API do Tiny).
	 */
	public const LIMITE_VARIACOES_SINCRONO = 10;

	/** Evita push para o Tiny enquanto importamos produto vindo de lá. */
	private static bool $suprimir_push_loja = false;

	public static function suprimir_push_loja(): bool {
		return self::$suprimir_push_loja;
	}

	public static function contar_pendencias(): int {
		global $wpdb;
		$meta_tiny = esc_sql( VH_Tiny_Map::META_TINY_ID );
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status != 'trash'
			AND pm.meta_key = '_sku' AND pm.meta_value != ''
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2
				WHERE pm2.post_id = pm.post_id AND pm2.meta_key = '{$meta_tiny}' AND pm2.meta_value != ''
			)"
		);
	}

	/**
	 * Categorias (product_cat) ainda sem vínculo no Tiny.
	 */
	public static function contar_categorias_sem_vinculo(): int {
		global $wpdb;
		$meta_cat     = esc_sql( VH_Tiny_Map::META_TINY_CAT_ID );
		$meta_ignorar = esc_sql( VH_Tiny_Map::META_TINY_CAT_IGNORAR );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				WHERE tt.taxonomy = %s
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->termmeta} tm
					WHERE tm.term_id = t.term_id AND tm.meta_key = '{$meta_cat}' AND tm.meta_value != ''
				)
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->termmeta} tm2
					WHERE tm2.term_id = t.term_id AND tm2.meta_key = '{$meta_ignorar}' AND tm2.meta_value != ''
				)",
				'product_cat'
			)
		);
	}

	/**
	 * Decide se o envio manual de um produto deve ir para a fila assíncrona.
	 *
	 * Produtos variáveis com muitas variações geram dezenas de chamadas à API do
	 * Tiny; processá-las de forma síncrona na requisição de salvar costuma estourar
	 * o tempo limite do PHP/servidor. Nesse caso, enfileiramos e deixamos o cron
	 * concluir em segundo plano (com retries e log persistente).
	 */
	public static function deve_enfileirar_envio( int $produto_id ): bool {
		$produto = wc_get_product( $produto_id );
		if ( ! $produto instanceof WC_Product_Variable ) {
			return false;
		}
		return count( $produto->get_children() ) > self::LIMITE_VARIACOES_SINCRONO;
	}

	/**
	 * Categorias do produto que ainda bloqueiam o envio (não mapeadas e não ignoradas).
	 *
	 * @return array<int, array{term_id:int, nome:string}>
	 */
	public static function categorias_bloqueando_envio( int $produto_id ): array {
		$produto = wc_get_product( $produto_id );
		if ( ! $produto instanceof WC_Product ) {
			return [];
		}

		$pendentes = [];
		$vistos    = [];

		foreach ( $produto->get_category_ids() as $cat_id ) {
			$cadeia = self::cadeia_categoria( (int) $cat_id );
			foreach ( $cadeia as $term ) {
				$tid = (int) $term->term_id;
				if ( isset( $vistos[ $tid ] ) ) {
					continue;
				}
				$vistos[ $tid ] = true;

				if ( get_term_meta( $tid, VH_Tiny_Map::META_TINY_CAT_IGNORAR, true ) ) {
					continue;
				}
				if ( (int) get_term_meta( $tid, VH_Tiny_Map::META_TINY_CAT_ID, true ) > 0 ) {
					continue;
				}
				$pendentes[] = [
					'term_id' => $tid,
					'nome'    => $term->name,
				];
			}
		}

		return $pendentes;
	}

	/**
	 * Categoria + ancestrais (do filho para a raiz).
	 *
	 * @return array<int, WP_Term>
	 */
	private static function cadeia_categoria( int $term_id ): array {
		$cadeia = [];
		$atual  = $term_id;
		$limite = 0;

		while ( $atual > 0 && $limite < 20 ) {
			$termo = get_term( $atual, VH_Categories_Service::TAXONOMIA );
			if ( ! $termo instanceof WP_Term ) {
				break;
			}
			$cadeia[] = $termo;
			$atual    = (int) $termo->parent;
			++$limite;
		}

		return $cadeia;
	}

	/**
	 * Garante que a categoria (e ancestrais) estão mapeadas antes de enviar produto.
	 *
	 * @return true|WP_Error
	 */
	public static function categoria_pronta_para_envio( int $term_id ) {
		if ( $term_id <= 0 ) {
			return true;
		}

		$termo = get_term( $term_id, VH_Categories_Service::TAXONOMIA );
		if ( ! $termo instanceof WP_Term ) {
			return true;
		}

		if ( (int) $termo->parent > 0 ) {
			$pai = self::categoria_pronta_para_envio( (int) $termo->parent );
			if ( is_wp_error( $pai ) ) {
				return $pai;
			}
		}

		if ( get_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR, true ) ) {
			return true;
		}

		$tiny_id = (int) get_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, true );
		if ( $tiny_id <= 0 ) {
			return new WP_Error(
				'vh_tiny_cat_nao_mapeada',
				sprintf(
					/* translators: %s: category name */
					__( 'A categoria “%s” ainda não está configurada no Tiny. Em Integrações → Tiny → Mapeamento, use Vincular (mesma categoria nos dois lados) ou Criar no Tiny (categoria nova ou subcategoria).', 'vapor-hub-loja' ),
					$termo->name
				)
			);
		}

		return true;
	}

	/**
	 * Variações publicadas sem SKU (não sincronizáveis).
	 */
	public static function contar_variacoes_sem_sku(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			WHERE p.post_type = 'product_variation'
			AND p.post_status NOT IN ('trash','auto-draft')
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm
				WHERE pm.post_id = p.ID AND pm.meta_key = '_sku' AND pm.meta_value != ''
			)"
		);
	}

	/**
	 * Estado de sincronização de um produto frente ao Tiny.
	 *
	 * Status possíveis:
	 *  - 'sem_sku'        : produto sem SKU (não sincronizável)
	 *  - 'nao_vinculado'  : tem SKU mas nunca foi enviado/casado no Tiny
	 *  - 'pendente'       : vinculado, porém o conteúdo local mudou após a última sync
	 *  - 'sincronizado'   : vinculado e idêntico ao último envio/recebimento
	 *
	 * @return array{status:string, vinculado:bool, pendente:bool, tiny_id:int, last_sync:string, rotulo:string}
	 */
	public static function estado_produto( int $produto_id ): array {
		$produto = wc_get_product( $produto_id );
		$tiny_id = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, true );
		$last    = (string) get_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, true );

		$base = [
			'status'    => 'sem_sku',
			'vinculado' => $tiny_id > 0,
			'pendente'  => false,
			'tiny_id'   => $tiny_id,
			'last_sync' => VH_DateTime::formatar_curto( $last ),
			'rotulo'    => '',
		];

		if ( ! $produto instanceof WC_Product || '' === (string) $produto->get_sku() ) {
			$base['rotulo'] = __( 'Sem SKU', 'vapor-hub-loja' );
			return $base;
		}

		if ( $tiny_id <= 0 ) {
			$base['status'] = 'nao_vinculado';
			$base['rotulo'] = __( 'Não enviado ao Tiny', 'vapor-hub-loja' );
			return $base;
		}

		$hash_atual = VH_Tiny_Map::hash_produto_wc( $produto );
		$hash_ant   = (string) get_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH, true );

		if ( '' === $hash_ant || $hash_atual !== $hash_ant ) {
			$base['status']   = 'pendente';
			$base['pendente'] = true;
			$base['rotulo']   = __( 'Alterações não enviadas', 'vapor-hub-loja' );
			return $base;
		}

		$base['status'] = 'sincronizado';
		$base['rotulo'] = __( 'Sincronizado', 'vapor-hub-loja' );
		return $base;
	}

	/**
	 * Produto vinculado cujo conteúdo local divergiu da última sincronização.
	 */
	public static function produto_pendente_envio( int $produto_id ): bool {
		return 'pendente' === self::estado_produto( $produto_id )['status'];
	}

	/**
	 * Conta produtos vinculados ao Tiny com alterações locais ainda não enviadas.
	 *
	 * Itera apenas sobre produtos já vinculados (`_vh_tiny_id`), comparando o hash
	 * canônico atual com o gravado na última sincronização.
	 */
	public static function contar_alteracoes_locais_pendentes(): int {
		global $wpdb;
		$meta_tiny = esc_sql( VH_Tiny_Map::META_TINY_ID );
		$ids       = $wpdb->get_col(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status IN ('publish','draft')
			AND pm.meta_key = '{$meta_tiny}' AND pm.meta_value != '0' AND pm.meta_value != ''
			LIMIT 2000"
		);

		$total = 0;
		foreach ( (array) $ids as $pid ) {
			if ( self::produto_pendente_envio( (int) $pid ) ) {
				$total++;
			}
		}
		return $total;
	}

	public static function em_guarda( int $produto_id ): bool {
		$guard = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_GUARD, true );
		return $guard > 0 && ( time() - $guard ) < self::GUARD_SECONDS;
	}

	private static function ativar_guarda( int $produto_id ): void {
		update_post_meta( $produto_id, VH_Tiny_Map::META_GUARD, time() );
	}

	private static function limpar_guarda( int $produto_id ): void {
		delete_post_meta( $produto_id, VH_Tiny_Map::META_GUARD );
	}

	private static function driver(): VH_Tiny_Driver_Interface {
		return VH_Tiny::driver();
	}

	private static function integracao_ativa(): bool {
		$dados  = VH_Tiny::obter();
		$driver = self::driver();
		return ! empty( $dados['ativo'] ) && $driver->conectado();
	}

	/**
	 * Envia conteúdo da loja para o Tiny.
	 *
	 * @return true|WP_Error
	 */
	public static function empurrar_produto( int $produto_id, bool $permitir_recriar_grade = true ) {
		if ( ! self::integracao_ativa() ) {
			return new WP_Error( 'vh_tiny_inativo', __( 'Integração Tiny inativa.', 'vapor-hub-loja' ) );
		}

		if ( ! VH_Tiny::pode_enviar() ) {
			return new WP_Error(
				'vh_tiny_envio_desligado',
				__( 'O envio ao Tiny está desativado nas configurações.', 'vapor-hub-loja' )
			);
		}

		$produto = wc_get_product( $produto_id );
		if ( ! $produto instanceof WC_Product || $produto->is_type( 'variation' ) ) {
			return new WP_Error( 'vh_tiny_produto_invalido', __( 'Produto inválido para sincronização.', 'vapor-hub-loja' ) );
		}

		if ( $produto->is_type( 'variable' ) && ! $produto instanceof WC_Product_Variable ) {
			return new WP_Error( 'vh_tiny_produto_invalido', __( 'Produto variável inválido.', 'vapor-hub-loja' ) );
		}

		if ( class_exists( 'VH_Tiny_SKU' ) && $produto->is_type( 'variable' ) && $produto instanceof WC_Product_Variable ) {
			VH_Tiny_SKU::garantir_skus_produto( $produto );
			$produto = wc_get_product( $produto_id );
		}

		$sku = $produto->get_sku();
		if ( '' === $sku ) {
			return new WP_Error( 'vh_tiny_sem_sku', __( 'Produto sem SKU — não sincronizado.', 'vapor-hub-loja' ) );
		}

		foreach ( $produto->get_category_ids() as $cat_id ) {
			$cat_check = self::categoria_pronta_para_envio( (int) $cat_id );
			if ( is_wp_error( $cat_check ) ) {
				VH_Tiny_Log::warning(
					VH_Tiny_Log::ORIGEM_SYNC,
					$cat_check->get_error_message(),
					'wc_' . $produto_id,
					[ 'produto_id' => $produto_id, 'motivo' => 'categoria_nao_mapeada' ]
				);
				return $cat_check;
			}
		}

		$driver   = self::driver();
		$canonico = VH_Tiny_Map::wc_para_canonico( $produto );
		$tiny_id  = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, true );
		$remoto   = null;

		/*
		 * Defesa crítica: se já existe vínculo, confirmamos que o produto no Tiny
		 * tem o MESMO SKU antes de atualizar. Vínculos antigos/incorretos não podem
		 * sobrescrever um produto diferente no ERP real.
		 */
		if ( $tiny_id > 0 ) {
			$remoto = $driver->obter_produto( $tiny_id );
			if ( is_wp_error( $remoto ) ) {
				return $remoto;
			}
			$sku_remoto = is_array( $remoto ) ? trim( (string) ( $remoto['sku'] ?? '' ) ) : '';
			if ( '' === $sku_remoto || 0 !== strcasecmp( $sku_remoto, $sku ) ) {
				delete_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID );
				$tiny_id = 0;
				$remoto  = null;
			} elseif ( 'E' === strtoupper( (string) ( $remoto['situacao'] ?? '' ) ) ) {
				delete_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID );
				$tiny_id = 0;
				$remoto  = null;
			}
		}

		/*
		 * Busca por SKU casa com qualquer produto da conta, que é compartilhada
		 * com outras operações. Só um código com a assinatura da loja autoriza
		 * adotar o cadastro que está lá; fora do padrão, criamos o nosso.
		 */
		if ( $tiny_id <= 0 && VH_Tiny_SKU::da_loja( $sku ) ) {
			$existente = $driver->buscar_produto_por_sku( $sku );
			if ( is_wp_error( $existente ) ) {
				return $existente;
			}
			if ( is_array( $existente ) ) {
				$tiny_id = (int) ( $existente['id_tiny'] ?? 0 );
				if ( $tiny_id > 0 ) {
					update_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, $tiny_id );
					$remoto = $driver->obter_produto( $tiny_id );
					if ( is_wp_error( $remoto ) ) {
						return $remoto;
					}
				}
			}
		}

		if ( $tiny_id > 0 && is_array( $remoto ) && $permitir_recriar_grade && self::grade_remota_incompativel( $canonico, $remoto ) ) {
			self::recriar_produto_por_grade( $produto_id, $tiny_id, $canonico, $remoto, $driver );
			$tiny_id = 0;
			$remoto  = null;
		}

		$somente_pai = self::deve_criar_somente_pai( $canonico );

		if ( $tiny_id > 0 ) {
			$resp = $driver->atualizar_produto( $tiny_id, $canonico );
		} else {
			$resp = $driver->criar_produto( $canonico, $somente_pai );
			if ( ! is_wp_error( $resp ) ) {
				$tiny_id = (int) ( $resp['id_tiny'] ?? 0 );
				if ( $tiny_id > 0 ) {
					update_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, $tiny_id );
					delete_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID_ANTERIOR );
				}
			}
		}

		if ( is_wp_error( $resp ) ) {
			self::alertar_orfao_no_erp( $produto_id, $resp );
			return $resp;
		}

		if ( $tiny_id > 0 && 'variavel' === ( $canonico['tipo'] ?? '' ) && ! empty( $canonico['variacoes'] ) ) {
			$var_resp = $driver->sincronizar_variacoes( $tiny_id, $canonico );
			if ( is_wp_error( $var_resp ) && $permitir_recriar_grade && self::erro_indica_grade_incompativel( $var_resp ) ) {
				self::recriar_produto_por_grade(
					$produto_id,
					$tiny_id,
					$canonico,
					is_array( $remoto ) ? $remoto : [],
					$driver
				);
				return self::empurrar_produto( $produto_id, false );
			}
			if ( is_wp_error( $var_resp ) ) {
				return $var_resp;
			}
			if ( is_array( $var_resp ) && ! empty( $var_resp['variacoes'] ) ) {
				foreach ( $var_resp['variacoes'] as $map ) {
					if ( ! is_array( $map ) ) {
						continue;
					}
					$vid = (int) ( $map['variacao_id'] ?? 0 );
					$tid = (int) ( $map['tiny_id'] ?? 0 );
					if ( $vid > 0 && $tid > 0 ) {
						update_post_meta( $vid, VH_Tiny_Map::META_TINY_ID, $tid );
					}
				}
			}
		}

		if ( $tiny_id > 0 ) {
			self::remover_variacoes_pendentes( $produto_id, $tiny_id, $driver );
		}

		if ( $tiny_id > 0 && ! empty( $canonico['imagens'] ) && is_array( $canonico['imagens'] ) ) {
			$img_resp = $driver->sincronizar_imagens( $tiny_id, $canonico );
			if ( is_wp_error( $img_resp ) ) {
				$aviso = sprintf(
					/* translators: 1: product id, 2: error message */
					__( 'Produto #%1$d sincronizado, mas a imagem falhou: %2$s', 'vapor-hub-loja' ),
					$produto_id,
					$img_resp->get_error_message()
				);
				VH_Tiny::registrar_erro( $aviso );
				VH_Tiny_Log::warning(
					VH_Tiny_Log::ORIGEM_SYNC,
					$aviso,
					'wc_' . $produto_id,
					[ 'produto_id' => $produto_id, 'tiny_id' => $tiny_id, 'motivo' => 'imagem_falhou' ]
				);
			}
		}

		if ( $tiny_id > 0 ) {
			$est_resp = $driver->sincronizar_estoque( $tiny_id, $canonico );
			if ( is_wp_error( $est_resp ) ) {
				return $est_resp;
			}
		}

		update_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH, VH_Tiny_Map::hash_produto_wc( $produto ) );
		update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );

		VH_Tiny_Log::success(
			VH_Tiny_Log::ORIGEM_SYNC,
			sprintf(
				/* translators: 1: product name, 2: tiny id */
				__( 'Produto “%1$s” sincronizado com o Tiny (ID %2$d).', 'vapor-hub-loja' ),
				$produto->get_name(),
				$tiny_id
			),
			'wc_' . $produto_id,
			[ 'produto_id' => $produto_id, 'tiny_id' => $tiny_id, 'sku' => $sku ]
		);

		return true;
	}

	/**
	 * Manda ao ERP apenas o saldo do item, do jeito que uma venda pede.
	 *
	 * A loja é dona do estoque: quando o WooCommerce baixa o saldo, o ERP tem de
	 * receber o número novo sem que o cadastro inteiro seja reenviado. Item sem
	 * vínculo no Tiny não tem saldo a corrigir — o que falta ali é o cadastro, e
	 * por isso o pedido volta para a fila como envio completo.
	 *
	 * @return true|WP_Error
	 */
	public static function empurrar_estoque( int $produto_id ) {
		if ( ! self::integracao_ativa() ) {
			return new WP_Error( 'vh_tiny_inativo', __( 'Integração Tiny inativa.', 'vapor-hub-loja' ) );
		}

		if ( ! VH_Tiny::pode_enviar() ) {
			return new WP_Error(
				'vh_tiny_envio_desligado',
				__( 'O envio ao Tiny está desativado nas configurações.', 'vapor-hub-loja' )
			);
		}

		$produto = wc_get_product( $produto_id );
		if ( ! $produto instanceof WC_Product ) {
			return new WP_Error( 'vh_tiny_produto_invalido', __( 'Produto inválido para sincronização.', 'vapor-hub-loja' ) );
		}

		/* Pai variável não tem saldo próprio: quem controla é cada variação. */
		if ( $produto->is_type( 'variable' ) || ! $produto->get_manage_stock() ) {
			return true;
		}

		$tiny_id = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, true );
		if ( $tiny_id <= 0 ) {
			$alvo = $produto->is_type( 'variation' ) ? (int) $produto->get_parent_id() : $produto_id;
			if ( $alvo > 0 ) {
				VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_' . $alvo, [ 'produto_id' => $alvo ] );
			}
			return true;
		}

		$item = [
			'sku'              => $produto->get_sku(),
			'nome'             => $produto->get_name(),
			'gerencia_estoque' => true,
			'estoque'          => (int) $produto->get_stock_quantity(),
			'preco_regular'    => $produto->get_regular_price(),
		];

		$resp = self::driver()->sincronizar_saldo( $tiny_id, $item );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );

		VH_Tiny_Log::info(
			VH_Tiny_Log::ORIGEM_SYNC,
			sprintf(
				/* translators: 1: stock quantity, 2: tiny id */
				__( 'Saldo %1$d enviado ao Tiny (ID %2$d).', 'vapor-hub-loja' ),
				(int) $produto->get_stock_quantity(),
				$tiny_id
			),
			'wc_' . $produto_id,
			[ 'produto_id' => $produto_id, 'tiny_id' => $tiny_id, 'estoque' => (int) $produto->get_stock_quantity() ]
		);

		return true;
	}

	/**
	 * Remove vínculos Tiny do produto pai e das variações (antes de recriar no ERP).
	 */
	private static function limpar_vinculos_tiny_produto( int $produto_id ): void {
		delete_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID );

		$produto = wc_get_product( $produto_id );
		if ( $produto instanceof WC_Product_Variable ) {
			foreach ( $produto->get_children() as $variacao_id ) {
				delete_post_meta( (int) $variacao_id, VH_Tiny_Map::META_TINY_ID );
			}
		}
	}

	/**
	 * Produtos variáveis com muitas variações: cria o pai com grade no Tiny e sincroniza
	 * variações em chamadas separadas (evita payload gigante e timeout).
	 *
	 * @param array<string, mixed> $canonico
	 */
	private static function deve_criar_somente_pai( array $canonico ): bool {
		if ( 'variavel' !== ( $canonico['tipo'] ?? '' ) ) {
			return false;
		}
		return count( $canonico['variacoes'] ?? [] ) > self::LIMITE_VARIACOES_SINCRONO;
	}

	/**
	 * @param array<string, mixed> $canonico
	 * @param array<string, mixed> $remoto
	 */
	private static function grade_remota_incompativel( array $canonico, array $remoto ): bool {
		if ( 'variavel' !== ( $canonico['tipo'] ?? '' ) ) {
			return false;
		}

		$local  = self::contar_eixos_grade_canonico( $canonico );
		$remoto_eixos = self::contar_eixos_grade_remota( $remoto );
		if ( $local <= 0 || $remoto_eixos <= 0 ) {
			return false;
		}

		return $local !== $remoto_eixos;
	}

	/**
	 * @param array<string, mixed> $canonico
	 */
	private static function contar_eixos_grade_canonico( array $canonico ): int {
		return count( $canonico['grade_atributos'] ?? [] );
	}

	/**
	 * @param array<string, mixed> $remoto
	 */
	private static function contar_eixos_grade_remota( array $remoto ): int {
		$vars = $remoto['variacoes'] ?? [];
		if ( is_array( $vars ) && ! empty( $vars[0]['grade'] ) && is_array( $vars[0]['grade'] ) ) {
			return count( $vars[0]['grade'] );
		}
		return count( $remoto['grade_atributos'] ?? [] );
	}

	/**
	 * @param array<string, mixed> $remoto
	 * @return array<int, string>
	 */
	private static function rotulos_grade_remota( array $remoto ): array {
		$vars = $remoto['variacoes'] ?? [];
		if ( is_array( $vars ) && ! empty( $vars[0]['grade'] ) && is_array( $vars[0]['grade'] ) ) {
			$rotulos = [];
			foreach ( $vars[0]['grade'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$rotulo = trim( (string) ( $item['chave'] ?? '' ) );
				if ( '' !== $rotulo ) {
					$rotulos[] = $rotulo;
				}
			}
			if ( $rotulos ) {
				return $rotulos;
			}
		}

		$rotulos = [];
		foreach ( (array) ( $remoto['grade_atributos'] ?? [] ) as $eixo ) {
			if ( is_array( $eixo ) && ! empty( $eixo['chave'] ) ) {
				$rotulos[] = (string) $eixo['chave'];
			}
		}
		return $rotulos;
	}

	private static function erro_indica_grade_incompativel( WP_Error $erro ): bool {
		$data = $erro->get_error_data();
		$body = is_array( $data ) ? ( $data['body'] ?? null ) : null;
		if ( is_array( $body ) && ! empty( $body['detalhes'] ) && is_array( $body['detalhes'] ) ) {
			foreach ( $body['detalhes'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$campo = strtolower( (string) ( $item['campo'] ?? '' ) );
				$msg   = strtolower( (string) ( $item['mensagem'] ?? '' ) );
				if ( str_contains( $campo, 'grade' ) || str_contains( $msg, 'grade' ) ) {
					return true;
				}
			}
		}

		return str_contains( strtolower( $erro->get_error_message() ), 'grade' );
	}

	/**
	 * A API v3 não permite alterar a quantidade de eixos da grade em produto existente.
	 * Excluímos o cadastro antigo no Tiny e recriamos com a grade atual da loja.
	 *
	 * @param array<string, mixed> $canonico
	 * @param array<string, mixed> $remoto
	 */
	private static function recriar_produto_por_grade(
		int $produto_id,
		int $tiny_id,
		array $canonico,
		array $remoto,
		VH_Tiny_Driver_Interface $driver
	): void {
		VH_Tiny_Log::warning(
			VH_Tiny_Log::ORIGEM_SYNC,
			sprintf(
				/* translators: 1: product id, 2: local axes count, 3: remote axes count */
				__( 'Grade alterada no produto #%1$d (%2$d eixos na loja × %3$d no Tiny). Recriando cadastro no ERP.', 'vapor-hub-loja' ),
				$produto_id,
				self::contar_eixos_grade_canonico( $canonico ),
				self::contar_eixos_grade_remota( $remoto )
			),
			'wc_' . $produto_id,
			[
				'produto_id'     => $produto_id,
				'tiny_id_antigo' => $tiny_id,
				'grade_local'    => array_column( $canonico['grade_atributos'] ?? [], 'chave' ),
				'grade_remota'   => self::rotulos_grade_remota( $remoto ),
				'vars_local'     => count( $canonico['variacoes'] ?? [] ),
				'vars_remota'    => count( $remoto['variacoes'] ?? [] ),
				'motivo'         => 'grade_incompativel',
			]
		);

		/*
		 * Antes de qualquer operação destrutiva no ERP, o id antigo fica guardado:
		 * se a recriação falhar, é ele que diz qual cadastro ficou órfão lá.
		 */
		update_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID_ANTERIOR, $tiny_id );

		$del = $driver->excluir_produto( $tiny_id );
		if ( is_wp_error( $del ) ) {
			$del_data = $del->get_error_data();
			VH_Tiny_Log::error(
				VH_Tiny_Log::ORIGEM_SYNC,
				sprintf(
					/* translators: 1: tiny product id, 2: error message */
					__( 'Falha ao excluir produto Tiny #%1$d antes de recriar: %2$s', 'vapor-hub-loja' ),
					$tiny_id,
					$del->get_error_message()
				),
				'wc_' . $produto_id,
				[
					'tiny_id'    => $tiny_id,
					'api_body'   => is_array( $del_data ) ? ( $del_data['body'] ?? null ) : null,
					'http_status'=> is_array( $del_data ) ? ( $del_data['status'] ?? null ) : null,
				]
			);
		}

		self::limpar_vinculos_tiny_produto( $produto_id );
	}

	/**
	 * Grita quando o cadastro antigo já foi apagado no ERP e o novo não nasceu.
	 *
	 * A fila vai tentar de novo, mas o gestor precisa saber que existe um produto
	 * sem par no Tiny nesse intervalo.
	 */
	private static function alertar_orfao_no_erp( int $produto_id, WP_Error $erro ): void {
		$anterior = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID_ANTERIOR, true );
		if ( $anterior <= 0 ) {
			return;
		}

		VH_Tiny_Log::error(
			VH_Tiny_Log::ORIGEM_SYNC,
			sprintf(
				/* translators: 1: product id, 2: previous tiny id, 3: error message */
				__( 'Produto #%1$d ficou órfão no Tiny: o cadastro %2$d foi removido e a recriação falhou (%3$s). A fila vai tentar de novo.', 'vapor-hub-loja' ),
				$produto_id,
				$anterior,
				$erro->get_error_message()
			),
			'wc_' . $produto_id,
			[
				'produto_id'     => $produto_id,
				'tiny_id_antigo' => $anterior,
				'motivo'         => 'recriacao_falhou',
			]
		);
	}

	/**
	 * Inativa no Tiny variações removidas localmente (best-effort).
	 */
	private static function remover_variacoes_pendentes( int $produto_id, int $tiny_id_pai, VH_Tiny_Driver_Interface $driver ): void {
		$removidas = get_post_meta( $produto_id, VH_Tiny_Map::META_VAR_REMOVIDAS, true );
		if ( ! is_array( $removidas ) || ! $removidas ) {
			return;
		}

		foreach ( $removidas as $tiny_var_id ) {
			$tiny_var_id = (int) $tiny_var_id;
			if ( $tiny_var_id <= 0 ) {
				continue;
			}
			$resp = $driver->remover_variacao( $tiny_id_pai, $tiny_var_id );
			if ( is_wp_error( $resp ) ) {
				VH_Tiny::registrar_erro(
					sprintf(
						/* translators: 1: variation tiny id, 2: error message */
						__( 'Falha ao remover variação Tiny #%1$d: %2$s', 'vapor-hub-loja' ),
						$tiny_var_id,
						$resp->get_error_message()
					)
				);
			}
		}

		delete_post_meta( $produto_id, VH_Tiny_Map::META_VAR_REMOVIDAS );
	}

	/**
	 * Puxa preço/estoque do Tiny para a loja.
	 *
	 * @return true|WP_Error
	 */
	public static function puxar_produto( int $tiny_id = 0, string $sku = '' ) {
		if ( ! self::integracao_ativa() ) {
			return new WP_Error( 'vh_tiny_inativo', __( 'Integração Tiny inativa.', 'vapor-hub-loja' ) );
		}

		$driver     = self::driver();
		$produto_id = 0;
		$variacao_id = 0;

		if ( $tiny_id <= 0 && '' !== $sku ) {
			$existente = $driver->buscar_produto_por_sku( $sku );
			if ( is_wp_error( $existente ) ) {
				return $existente;
			}
			if ( is_array( $existente ) ) {
				$tiny_id = (int) ( $existente['id_tiny'] ?? 0 );
			}
		}

		if ( $tiny_id <= 0 ) {
			return new WP_Error( 'vh_tiny_sem_id', __( 'ID Tiny não informado.', 'vapor-hub-loja' ) );
		}

		$canonico = $driver->obter_produto( $tiny_id );
		if ( is_wp_error( $canonico ) ) {
			return $canonico;
		}

		/*
		 * Situação E é a lixeira do ERP. A conta tem milhares desses itens de
		 * outras operações: nada do que está lá dentro toca a loja.
		 */
		if ( 'E' === strtoupper( (string) ( $canonico['situacao'] ?? '' ) ) ) {
			return new WP_Error(
				'vh_tiny_situacao_excluida',
				__( 'Produto está excluído no Tiny — nada aplicado na loja.', 'vapor-hub-loja' )
			);
		}

		/*
		 * O SKU é a chave de negócio. Resolvemos o produto local SEMPRE por SKU
		 * primeiro (produto, depois variações). O vínculo por _vh_tiny_id só é
		 * aceito se o SKU local bater com o do Tiny — assim um mapeamento antigo
		 * incorreto nunca atualiza o produto errado.
		 */
		$sku_tiny = trim( (string) ( $canonico['sku'] ?? '' ) );
		if ( '' !== $sku_tiny ) {
			$produto_id = wc_get_product_id_by_sku( $sku_tiny );
		}
		if ( $produto_id <= 0 && ! empty( $canonico['variacoes'] ) ) {
			foreach ( (array) $canonico['variacoes'] as $var_tiny ) {
				if ( ! is_array( $var_tiny ) ) {
					continue;
				}
				$sku_var = trim( (string) ( $var_tiny['sku'] ?? '' ) );
				if ( '' === $sku_var ) {
					continue;
				}
				$produto_id = wc_get_product_id_by_sku( $sku_var );
				if ( $produto_id > 0 ) {
					$variacao_id = $produto_id;
					break;
				}
			}
		}

		/* Só usa o vínculo por ID se o SKU confirmar a correspondência. */
		if ( $produto_id <= 0 ) {
			$candidato = self::wc_id_por_tiny( $tiny_id );
			if ( $candidato > 0 ) {
				$prod_cand = wc_get_product( $candidato );
				$sku_cand  = $prod_cand instanceof WC_Product ? trim( (string) $prod_cand->get_sku() ) : '';
				if ( '' !== $sku_tiny && '' !== $sku_cand && 0 === strcasecmp( $sku_cand, $sku_tiny ) ) {
					$produto_id  = $candidato;
					$variacao_id = $candidato;
				} else {
					/* Vínculo divergente: remove para não contaminar a loja. */
					delete_post_meta( $candidato, VH_Tiny_Map::META_TINY_ID );
				}
			}
		}

		if ( $produto_id <= 0 ) {
			return self::importar_produto_de_tiny( $canonico, $tiny_id );
		}

		$produto = wc_get_product( $produto_id );
		if ( ! $produto instanceof WC_Product ) {
			return new WP_Error( 'vh_tiny_sem_par', __( 'Produto local não encontrado.', 'vapor-hub-loja' ) );
		}

		if ( $produto->is_type( 'variation' ) ) {
			return self::puxar_variacao( $produto, $canonico, $tiny_id );
		}

		if ( $produto->is_type( 'variable' ) ) {
			self::ativar_guarda( $produto_id );
			update_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, $tiny_id );

			$atualizou = false;
			if ( ! empty( $canonico['variacoes'] ) && is_array( $canonico['variacoes'] ) ) {
				foreach ( $canonico['variacoes'] as $var_tiny ) {
					if ( ! is_array( $var_tiny ) ) {
						continue;
					}
					$sku_var = (string) ( $var_tiny['sku'] ?? '' );
					if ( '' === $sku_var ) {
						continue;
					}
					$vid = wc_get_product_id_by_sku( $sku_var );
					if ( $vid <= 0 ) {
						continue;
					}
					$var_prod = wc_get_product( $vid );
					if ( ! $var_prod instanceof WC_Product_Variation ) {
						continue;
					}
					self::ativar_guarda( $vid );
					$tid_var = (int) ( $var_tiny['tiny_id'] ?? $var_tiny['id_tiny'] ?? 0 );
					if ( $tid_var > 0 ) {
						update_post_meta( $vid, VH_Tiny_Map::META_TINY_ID, $tid_var );
					}
					$financeiro = VH_Tiny_Map::tiny_para_wc_financeiro( $var_tiny, $var_prod );
					if ( $financeiro ) {
						VH_Products_Service::atualizar_variacao( $vid, $financeiro );
						$atualizou = true;
					}
					self::limpar_guarda( $vid );
				}
			}

			$produto_atual = wc_get_product( $produto_id );
			if ( $produto_atual instanceof WC_Product ) {
				update_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH, VH_Tiny_Map::hash_produto_wc( $produto_atual ) );
			}
			update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );
			self::limpar_guarda( $produto_id );
			return $atualizou ? true : new WP_Error( 'vh_tiny_sem_var', __( 'Nenhuma variação casada por SKU.', 'vapor-hub-loja' ) );
		}

		if ( ! $produto->is_type( 'simple' ) ) {
			return new WP_Error( 'vh_tiny_tipo', __( 'Tipo de produto não suportado para pull.', 'vapor-hub-loja' ) );
		}

		self::ativar_guarda( $produto_id );
		update_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, $tiny_id );

		$financeiro = VH_Tiny_Map::tiny_para_wc_financeiro( $canonico, $produto );

		if ( ! isset( $canonico['estoque'] ) ) {
			$est_resp = $driver->obter_estoque( $tiny_id );
			if ( ! is_wp_error( $est_resp ) && is_array( $est_resp ) ) {
				$financeiro = array_merge( $financeiro, VH_Tiny_Map::tiny_estoque_para_wc( $est_resp ) );
			}
		}

		if ( $financeiro ) {
			VH_Products_Service::atualizar( $produto_id, $financeiro );
		}

		$produto_atual = wc_get_product( $produto_id );
		$hash_final    = $produto_atual instanceof WC_Product
			? VH_Tiny_Map::hash_produto_wc( $produto_atual )
			: VH_Tiny_Map::hash_financeiro_tiny( $financeiro );
		update_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH, $hash_final );
		update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );
		self::limpar_guarda( $produto_id );

		return true;
	}

	/**
	 * Produto no Tiny sem par na loja: cria só com recorte seguro.
	 *
	 * O Tiny é o hub. A loja nova nasce vazia e recebe cadastro daqui — nunca
	 * o ERP inteiro. Sem SKU da loja, sem categoria mapeada, inativo ou variável
	 * (próximo marco): não cria. Nunca DELETE no Tiny.
	 *
	 * @param array<string, mixed> $canonico
	 * @return true|WP_Error
	 */
	private static function importar_produto_de_tiny( array $canonico, int $tiny_id ) {
		$sku = trim( (string) ( $canonico['sku'] ?? '' ) );
		if ( '' === $sku ) {
			return new WP_Error(
				'vh_tiny_sem_sku',
				__( 'Produto Tiny sem SKU: não entra na loja.', 'vapor-hub-loja' )
			);
		}

		if ( class_exists( 'VH_Tiny_SKU' ) && ! VH_Tiny_SKU::da_loja( $sku ) ) {
			VH_Tiny_Log::warning(
				VH_Tiny_Log::ORIGEM_SYNC,
				sprintf(
					/* translators: 1: tiny product id, 2: sku */
					__( 'Produto Tiny #%1$d (SKU %2$s) fora do recorte desta loja (prefixo de SKU).', 'vapor-hub-loja' ),
					$tiny_id,
					$sku
				),
				'tiny_' . $tiny_id,
				[ 'tiny_id' => $tiny_id, 'sku' => $sku, 'motivo' => 'sku_fora_do_recorte' ]
			);
			return new WP_Error(
				'vh_tiny_sku_fora_do_recorte',
				__( 'SKU não pertence a esta loja (prefixo). O mesmo Tiny atende várias operações: este item não entra na vitrine.', 'vapor-hub-loja' )
			);
		}

		$situacao = strtoupper( (string) ( $canonico['situacao'] ?? 'A' ) );
		if ( '' !== $situacao && 'A' !== $situacao ) {
			return new WP_Error(
				'vh_tiny_situacao_inativa',
				__( 'Produto inativo ou excluído no Tiny: nada criado na loja.', 'vapor-hub-loja' )
			);
		}

		$term_ids = VH_Tiny_Map::categorias_tiny_para_wc_ids( $canonico );
		if ( ! $term_ids ) {
			VH_Tiny_Log::warning(
				VH_Tiny_Log::ORIGEM_SYNC,
				sprintf(
					/* translators: 1: tiny product id, 2: sku */
					__( 'Produto Tiny #%1$d (SKU %2$s) ignorado: categoria Tiny sem vínculo na loja.', 'vapor-hub-loja' ),
					$tiny_id,
					$sku
				),
				'tiny_' . $tiny_id,
				[ 'tiny_id' => $tiny_id, 'sku' => $sku, 'motivo' => 'categoria_nao_mapeada' ]
			);
			return new WP_Error(
				'vh_tiny_categoria_nao_mapeada',
				__( 'Categoria do Tiny ainda não está mapeada nesta loja. Vincule no mapeamento antes de importar — o ERP não é despejado inteiro na vitrine.', 'vapor-hub-loja' )
			);
		}

		$eh_variavel = ! empty( $canonico['variacoes'] ) || 'variavel' === ( $canonico['tipo'] ?? '' );
		if ( $eh_variavel ) {
			return new WP_Error(
				'vh_tiny_variavel_pendente',
				__( 'Pull de produto variável ainda não cria pai e variações. Próximo marco: grade sabor/puffs/nicotina a partir do Tiny.', 'vapor-hub-loja' )
			);
		}

		if ( function_exists( 'wc_get_product_id_by_sku' ) && wc_get_product_id_by_sku( $sku ) > 0 ) {
			return new WP_Error(
				'vh_tiny_sku_duplicado',
				__( 'Já existe produto na loja com este SKU.', 'vapor-hub-loja' )
			);
		}

		$dados = [
			'nome'       => (string) ( $canonico['nome'] ?? $sku ),
			'sku'        => $sku,
			'tipo'       => 'simple',
			'status'     => ! empty( $canonico['publicado'] ) ? 'publish' : 'draft',
			'categorias' => $term_ids,
		];
		if ( isset( $canonico['descricao'] ) ) {
			$dados['descricao'] = (string) $canonico['descricao'];
		}
		if ( isset( $canonico['descricao_curta'] ) ) {
			$dados['descricao_curta'] = (string) $canonico['descricao_curta'];
		}

		$financeiro = VH_Tiny_Map::tiny_para_wc_financeiro( $canonico, new WC_Product_Simple() );
		$dados      = array_merge( $dados, $financeiro );

		self::$suprimir_push_loja = true;
		try {
			$id = VH_Products_Service::criar( $dados );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$produto_id = (int) $id;
			update_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, $tiny_id );
			$produto = wc_get_product( $produto_id );
			if ( $produto instanceof WC_Product ) {
				update_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH, VH_Tiny_Map::hash_produto_wc( $produto ) );
			}
			update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );
		} finally {
			self::$suprimir_push_loja = false;
		}

		VH_Tiny_Log::success(
			VH_Tiny_Log::ORIGEM_SYNC,
			sprintf(
				/* translators: 1: sku, 2: tiny id */
				__( 'Produto SKU %1$s criado a partir do Tiny #%2$d (recorte mapeado).', 'vapor-hub-loja' ),
				$sku,
				$tiny_id
			),
			'tiny_' . $tiny_id,
			[ 'tiny_id' => $tiny_id, 'sku' => $sku, 'produto_id' => $produto_id ]
		);

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function puxar_variacao( WC_Product_Variation $variacao, array $canonico_pai, int $tiny_id ): bool|WP_Error {
		$vid = $variacao->get_id();
		self::ativar_guarda( $vid );

		$financeiro = [];
		$sku_local    = $variacao->get_sku();
		if ( ! empty( $canonico_pai['variacoes'] ) ) {
			foreach ( (array) $canonico_pai['variacoes'] as $var_tiny ) {
				if ( ! is_array( $var_tiny ) ) {
					continue;
				}
				$sku_var = (string) ( $var_tiny['sku'] ?? '' );
				$tid     = (int) ( $var_tiny['tiny_id'] ?? $var_tiny['id_tiny'] ?? 0 );
				if ( ( $tid > 0 && $tid === $tiny_id ) || ( '' !== $sku_var && 0 === strcasecmp( $sku_var, $sku_local ) ) ) {
					$financeiro = VH_Tiny_Map::tiny_para_wc_financeiro( $var_tiny, $variacao );
					if ( $tid > 0 ) {
						update_post_meta( $vid, VH_Tiny_Map::META_TINY_ID, $tid );
					}
					break;
				}
			}
		}

		if ( ! $financeiro ) {
			$est_resp = self::driver()->obter_estoque( $tiny_id );
			if ( ! is_wp_error( $est_resp ) && is_array( $est_resp ) ) {
				$financeiro = VH_Tiny_Map::tiny_estoque_para_wc( $est_resp );
			}
		}

		if ( $financeiro ) {
			VH_Products_Service::atualizar_variacao( $vid, $financeiro );
		}

		update_post_meta( $vid, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );
		self::limpar_guarda( $vid );

		return $financeiro ? true : new WP_Error( 'vh_tiny_sem_dados', __( 'Sem dados financeiros para a variação.', 'vapor-hub-loja' ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function puxar_estoque( int $produto_id ) {
		$produto = wc_get_product( $produto_id );
		if ( ! $produto instanceof WC_Product ) {
			return new WP_Error( 'vh_tiny_produto_invalido', __( 'Produto inválido.', 'vapor-hub-loja' ) );
		}

		if ( $produto->is_type( 'variation' ) ) {
			$tiny_id = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, true );
			if ( $tiny_id <= 0 ) {
				return self::puxar_produto( 0, $produto->get_sku() );
			}
			$resp = self::driver()->obter_estoque( $tiny_id );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$financeiro = VH_Tiny_Map::tiny_estoque_para_wc( $resp );
			if ( ! $financeiro ) {
				return true;
			}
			self::ativar_guarda( $produto_id );
			VH_Products_Service::atualizar_variacao( $produto_id, $financeiro );
			update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );
			self::limpar_guarda( $produto_id );
			return true;
		}

		$tiny_id = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, true );
		if ( $tiny_id <= 0 ) {
			return self::puxar_produto( 0, $produto->get_sku() );
		}

		if ( $produto->is_type( 'variable' ) ) {
			$canonico = self::driver()->obter_produto( $tiny_id );
			if ( is_wp_error( $canonico ) ) {
				return $canonico;
			}
			return self::puxar_produto( $tiny_id, $produto->get_sku() );
		}

		/*
		 * Defesa: confirma que o vínculo aponta para o MESMO SKU no Tiny antes de
		 * aplicar estoque. Um mapeamento antigo incorreto não pode alterar o
		 * estoque de um produto que não corresponde.
		 */
		$remoto = self::driver()->obter_produto( $tiny_id );
		if ( is_wp_error( $remoto ) ) {
			return $remoto;
		}
		$sku_local  = trim( (string) $produto->get_sku() );
		$sku_remoto = is_array( $remoto ) ? trim( (string) ( $remoto['sku'] ?? '' ) ) : '';
		if ( '' === $sku_local || '' === $sku_remoto || 0 !== strcasecmp( $sku_local, $sku_remoto ) ) {
			delete_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID );
			return '' !== $sku_local
				? self::puxar_produto( 0, $sku_local )
				: new WP_Error( 'vh_tiny_vinculo_invalido', __( 'Vínculo Tiny divergente e produto sem SKU.', 'vapor-hub-loja' ) );
		}

		$resp = self::driver()->obter_estoque( $tiny_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$financeiro = VH_Tiny_Map::tiny_estoque_para_wc( $resp );
		if ( ! $financeiro ) {
			return true;
		}

		self::ativar_guarda( $produto_id );
		VH_Products_Service::atualizar( $produto_id, $financeiro );
		update_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC, VH_DateTime::agora_utc() );
		self::limpar_guarda( $produto_id );

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function empurrar_pedido( int $pedido_id ) {
		if ( ! self::integracao_ativa() ) {
			return new WP_Error( 'vh_tiny_inativo', __( 'Integração Tiny inativa.', 'vapor-hub-loja' ) );
		}

		$pedido = wc_get_order( $pedido_id );
		if ( ! $pedido instanceof WC_Order ) {
			return new WP_Error( 'vh_tiny_pedido', __( 'Pedido não encontrado.', 'vapor-hub-loja' ) );
		}

		if ( $pedido->get_meta( VH_Tiny_Map::META_TINY_PEDIDO ) ) {
			return true;
		}

		$canonico = VH_Tiny_Map::pedido_para_canonico( $pedido );

		$sem_vinculo = self::itens_sem_vinculo( $canonico );
		if ( $sem_vinculo ) {
			return new WP_Error(
				'vh_tiny_pedido_item',
				sprintf(
					/* translators: %s: comma separated list of SKUs */
					__( 'Pedido não enviado: produto sem vínculo no Tiny (%s). Envie o produto primeiro.', 'vapor-hub-loja' ),
					implode( ', ', $sem_vinculo )
				)
			);
		}

		$resp = self::driver()->criar_pedido( $canonico );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$contato_id = (int) ( $resp['id_contato'] ?? 0 );
		if ( $contato_id > 0 && (int) $pedido->get_meta( VH_Tiny_Map::META_TINY_CONTATO ) !== $contato_id ) {
			$pedido->update_meta_data( VH_Tiny_Map::META_TINY_CONTATO, (string) $contato_id );
			$pedido->save();
		}

		$tiny_pedido_id = (int) ( $resp['id_tiny_pedido'] ?? 0 );
		if ( $tiny_pedido_id > 0 ) {
			$pedido->update_meta_data( VH_Tiny_Map::META_TINY_PEDIDO, (string) $tiny_pedido_id );
			$pedido->save();
		}

		return true;
	}

	/**
	 * SKUs do pedido que ainda não têm cadastro no ERP. A v3 exige `produto.id`
	 * em cada item, então mandar o pedido antes do produto só criaria pedido
	 * torto no ERP.
	 *
	 * @param array<string, mixed> $canonico
	 * @return array<int, string>
	 */
	private static function itens_sem_vinculo( array $canonico ): array {
		$faltando = [];

		foreach ( (array) ( $canonico['itens'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) || ! empty( $item['id_tiny'] ) ) {
				continue;
			}
			$faltando[] = (string) ( $item['sku'] ?? $item['nome'] ?? '?' );
		}

		return $faltando;
	}

	public static function reconciliar(): void {
		if ( ! self::integracao_ativa() ) {
			return;
		}

		$driver = self::driver();

		/*
		 * Uma vez por rodada, confirma no ERP que a conta do outro lado é a
		 * travada. Reconciliar contra outra empresa é o pior cenário possível.
		 */
		if ( 'v3' === $driver->versao() && ! VH_Tiny_Client::verificar_conta() ) {
			return;
		}

		/* Tiny → Loja (puxar): só enfileira se o recebimento estiver autorizado. */
		if ( VH_Tiny::pode_receber() ) {
			if ( 'v2' === $driver->versao() ) {
				self::puxar_atualizacoes_v2( $driver );
			}
			self::reconciliar_vinculos( $driver );
		}

		/* Loja → Tiny (enviar): enfileira todos os produtos locais com SKU. */
		if ( VH_Tiny::pode_enviar() ) {
			$pagina = 1;
			do {
				$produtos = wc_get_products(
					[
						'limit'  => 100,
						'page'   => $pagina,
						'status' => [ 'publish', 'draft' ],
						'return' => 'ids',
						'type'   => [ 'simple', 'variable' ],
					]
				);
				if ( ! $produtos ) {
					break;
				}
				foreach ( $produtos as $pid ) {
					$produto = wc_get_product( $pid );
					if ( ! $produto || '' === $produto->get_sku() ) {
						continue;
					}
					VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_' . $pid, [ 'produto_id' => (int) $pid ] );
				}
				++$pagina;
			} while ( count( $produtos ) >= 100 );
		}

		VH_Tiny::marcar_reconciliacao();
		VH_Tiny_Queue::limpar_concluidos();
	}

	/**
	 * Escopo do recebimento: os produtos da loja que já têm vínculo no ERP.
	 *
	 * A conta do Tiny é compartilhada com outras operações (e tem milhares de
	 * itens em lixeira). Paginar o catálogo do ERP trazia tudo isso para a fila;
	 * partir do vínculo local mantém a reconciliação restrita ao que é da loja.
	 */
	private static function reconciliar_vinculos( VH_Tiny_Driver_Interface $driver ): void {
		global $wpdb;

		$meta_tiny = esc_sql( VH_Tiny_Map::META_TINY_ID );
		$ids       = $wpdb->get_col(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'product'
			AND p.post_status IN ('publish','draft')
			AND pm.meta_key = '{$meta_tiny}' AND pm.meta_value != '' AND pm.meta_value != '0'
			ORDER BY pm.post_id ASC
			LIMIT 2000"
		);

		foreach ( (array) $ids as $pid ) {
			$produto_id = (int) $pid;
			$produto    = wc_get_product( $produto_id );
			if ( ! $produto instanceof WC_Product || $produto->is_type( 'variation' ) ) {
				continue;
			}

			$tiny_id = (int) get_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID, true );
			if ( $tiny_id <= 0 ) {
				continue;
			}

			$remoto = $driver->obter_produto( $tiny_id );
			if ( is_wp_error( $remoto ) || ! is_array( $remoto ) ) {
				continue;
			}

			/* Item na lixeira do ERP não volta para a vitrine. */
			if ( 'E' === strtoupper( (string) ( $remoto['situacao'] ?? '' ) ) ) {
				continue;
			}

			$sku_local  = trim( (string) $produto->get_sku() );
			$sku_remoto = trim( (string) ( $remoto['sku'] ?? '' ) );
			if ( '' === $sku_local || '' === $sku_remoto || 0 !== strcasecmp( $sku_local, $sku_remoto ) ) {
				continue;
			}

			VH_Tiny_Queue::enfileirar(
				'produto_pull',
				'tiny_' . $tiny_id,
				[ 'tiny_id' => $tiny_id, 'sku' => $sku_local ]
			);
		}
	}

	private static function puxar_atualizacoes_v2( VH_Tiny_Driver_Interface $driver ): void {
		$dados = VH_Tiny::obter();
		$desde = (string) ( $dados['ultima_reconciliacao'] ?? '' );
		if ( '' === $desde ) {
			$desde = gmdate( 'c', time() - DAY_IN_SECONDS );
		}

		$atualizacoes = $driver->listar_atualizacoes_estoque( $desde );
		if ( is_wp_error( $atualizacoes ) || ! is_array( $atualizacoes ) ) {
			return;
		}

		foreach ( $atualizacoes as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			self::enfileirar_pull_item( $item );
		}
	}

	/**
	 * Produto local correspondente a um item do ERP: SKU primeiro, vínculo depois.
	 *
	 * Zero significa “não é da loja”: nada é criado a partir do ERP.
	 */
	private static function vinculo_local( int $tiny_id, string $sku ): int {
		$sku = trim( $sku );
		if ( '' !== $sku ) {
			$por_sku = (int) wc_get_product_id_by_sku( $sku );
			if ( $por_sku > 0 ) {
				return $por_sku;
			}
		}

		return $tiny_id > 0 ? self::wc_id_por_tiny( $tiny_id ) : 0;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function enfileirar_pull_item( array $item ): void {
		$tiny_id = (int) ( $item['id_tiny'] ?? $item['tiny_id'] ?? 0 );
		$sku     = (string) ( $item['sku'] ?? '' );

		if ( self::vinculo_local( $tiny_id, $sku ) > 0 ) {
			VH_Tiny_Queue::enfileirar(
				'produto_pull',
				'tiny_' . ( $tiny_id ?: $sku ),
				[ 'tiny_id' => $tiny_id, 'sku' => $sku ]
			);
		}

		if ( ! empty( $item['variacoes'] ) && is_array( $item['variacoes'] ) ) {
			foreach ( $item['variacoes'] as $var ) {
				if ( ! is_array( $var ) ) {
					continue;
				}
				$tid = (int) ( $var['tiny_id'] ?? $var['id_tiny'] ?? 0 );
				$vsk = (string) ( $var['sku'] ?? '' );
				if ( self::vinculo_local( $tid, $vsk ) > 0 ) {
					VH_Tiny_Queue::enfileirar(
						'produto_pull',
						'tiny_' . ( $tid ?: $vsk ),
						[ 'tiny_id' => $tid, 'sku' => $vsk ]
					);
				}
			}
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function processar_webhook( array $payload ): void {
		$norm    = VH_Tiny::driver()->normalizar_webhook( $payload );
		$tipo    = (string) ( $norm['tipo'] ?? '' );
		$tiny_id = (int) ( $norm['tiny_id'] ?? 0 );
		$sku     = (string) ( $norm['sku'] ?? '' );

		if ( 'estoque' === $tipo ) {
			if ( ! VH_Tiny::pode_receber_estoque_preco() ) {
				return;
			}

			/*
			 * Sem par local o evento é de outra operação da mesma conta Tiny:
			 * descarta em silêncio, sem enfileirar nada.
			 */
			$produto_id = self::vinculo_local( $tiny_id, $sku );
			if ( $produto_id <= 0 ) {
				return;
			}

			/* Vínculo por ID sem SKU confirmado: pull do produto revalida antes de aplicar. */
			$prod  = wc_get_product( $produto_id );
			$s_loc = $prod instanceof WC_Product ? trim( (string) $prod->get_sku() ) : '';
			if ( '' !== $sku && '' !== $s_loc && 0 === strcasecmp( $s_loc, $sku ) ) {
				VH_Tiny_Queue::enfileirar( 'estoque_pull', 'wc_' . $produto_id, [ 'produto_id' => $produto_id ] );
				return;
			}

			VH_Tiny_Queue::enfileirar(
				'produto_pull',
				'tiny_' . ( $tiny_id ?: $sku ),
				[ 'tiny_id' => $tiny_id, 'sku' => $sku ]
			);
			return;
		}

		if ( ! VH_Tiny::pode_receber() ) {
			return;
		}

		if ( ( 'produto' === $tipo || $tiny_id > 0 ) && self::vinculo_local( $tiny_id, $sku ) > 0 ) {
			VH_Tiny_Queue::enfileirar(
				'produto_pull',
				'tiny_' . ( $tiny_id ?: $sku ),
				[ 'tiny_id' => $tiny_id, 'sku' => $sku ]
			);
		}
	}

	private static function wc_id_por_tiny( int $tiny_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				VH_Tiny_Map::META_TINY_ID,
				(string) $tiny_id
			)
		);
	}

	/**
	 * Cron v2 — poll de atualizações de estoque (a cada 5 min).
	 */
	public static function poll_atualizacoes_estoque_v2(): void {
		if ( 'v2' !== VH_Tiny::modo() ) {
			return;
		}

		$driver = self::driver();
		if ( ! $driver->conectado() ) {
			return;
		}

		$dados = VH_Tiny::obter();
		$desde = (string) ( $dados['ultimo_poll_estoque_v2'] ?? $dados['ultima_reconciliacao'] ?? '' );
		if ( '' === $desde ) {
			$desde = gmdate( 'c', time() - HOUR_IN_SECONDS );
		}

		$atualizacoes = $driver->listar_atualizacoes_estoque( $desde );
		if ( is_wp_error( $atualizacoes ) || ! is_array( $atualizacoes ) ) {
			return;
		}

		foreach ( $atualizacoes as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			self::enfileirar_pull_item( $item );
		}

		$dados['ultimo_poll_estoque_v2'] = VH_DateTime::agora_utc();
		update_option( VH_Tiny::OPTION, $dados, false );
	}
}

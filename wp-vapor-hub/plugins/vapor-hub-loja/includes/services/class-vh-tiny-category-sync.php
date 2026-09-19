<?php
/**
 * Sincronização de categorias WooCommerce → Tiny ERP.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Tiny_Category_Sync {

	/**
	 * @return true|WP_Error
	 */
	public static function empurrar_categoria( int $term_id ) {
		if ( ! class_exists( 'VH_Tiny' ) || ! VH_Tiny::driver()->conectado() ) {
			return new WP_Error( 'vh_tiny_inativo', __( 'Integração Tiny inativa.', 'vapor-hub-loja' ) );
		}

		$termo = get_term( $term_id, VH_Categories_Service::TAXONOMIA );
		if ( ! $termo instanceof WP_Term ) {
			return new WP_Error( 'vh_categoria_inexistente', __( 'Categoria não encontrada.', 'vapor-hub-loja' ) );
		}

		/*
		 * “Sem categoria” é o depósito do WooCommerce, não uma gaveta da loja.
		 * Marcar como ignorada aqui evita cadastro inútil no ERP e evita que o
		 * produto que caiu nela fique parado esperando mapeamento.
		 */
		if ( self::e_categoria_padrao( $termo ) ) {
			update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR, 1 );
			return true;
		}

		if ( get_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR, true ) ) {
			return true;
		}

		$pai_id = (int) $termo->parent;
		if ( $pai_id > 0 ) {
			$resultado_pai = self::empurrar_categoria( $pai_id );
			if ( is_wp_error( $resultado_pai ) ) {
				return $resultado_pai;
			}
		}

		$canonico = self::termo_para_canonico( $termo );
		$driver   = VH_Tiny::driver();
		$tiny_id  = (int) get_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, true );

		if ( $tiny_id <= 0 ) {
			$existente = $driver->buscar_categoria( (string) $canonico['caminho'] );
			if ( is_wp_error( $existente ) ) {
				return $existente;
			}
			if ( is_array( $existente ) ) {
				$tiny_id = (int) ( $existente['id_tiny'] ?? 0 );
			}
		}

		$era_nova = ( $tiny_id <= 0 );

		if ( $tiny_id > 0 ) {
			$canonico['id_tiny'] = $tiny_id;
			$resp                = $driver->atualizar_categoria( $tiny_id, $canonico );
		} else {
			$resp = $driver->criar_categoria( $canonico );
			if ( ! is_wp_error( $resp ) ) {
				$tiny_id = (int) ( $resp['id_tiny'] ?? 0 );
			}
		}

		if ( is_wp_error( $resp ) ) {
			if ( class_exists( 'VH_Tiny_Log' ) ) {
				VH_Tiny_Log::error(
					VH_Tiny_Log::ORIGEM_MAPPING,
					$resp->get_error_message(),
					'cat_' . $term_id,
					[ 'term_id' => $term_id, 'caminho' => (string) $canonico['caminho'] ]
				);
			}
			return $resp;
		}

		if ( $tiny_id > 0 ) {
			update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, $tiny_id );
			if ( $era_nova && class_exists( 'VH_Tiny_Log' ) ) {
				VH_Tiny_Log::success(
					VH_Tiny_Log::ORIGEM_MAPPING,
					sprintf(
						/* translators: 1: category path, 2: tiny id */
						__( 'Categoria “%1$s” criada no Tiny (ID %2$d).', 'vapor-hub-loja' ),
						(string) $canonico['caminho'],
						$tiny_id
					),
					'cat_' . $term_id,
					[ 'term_id' => $term_id, 'tiny_id' => $tiny_id ]
				);
			}
		}

		return true;
	}

	/**
	 * A categoria que o WooCommerce usa quando o produto não tem nenhuma.
	 */
	private static function e_categoria_padrao( WP_Term $termo ): bool {
		$padrao = (int) get_option( 'default_product_cat', 0 );
		if ( $padrao > 0 && $padrao === (int) $termo->term_id ) {
			return true;
		}
		return in_array( $termo->slug, [ 'uncategorized', 'sem-categoria' ], true );
	}

	/**
	 * Id da raiz da loja no Tiny, criada na primeira vez que for necessária.
	 *
	 * A conta do ERP é compartilhada com outras operações: pendurar tudo em uma
	 * raiz nossa mantém o catálogo da loja separado do resto. Raiz vazia na
	 * configuração devolve 0 e as categorias voltam a nascer no topo.
	 */
	public static function garantir_raiz_tiny(): int {
		$dados = VH_Tiny::obter();
		$nome  = trim( (string) ( $dados['categoria_raiz'] ?? '' ) );
		if ( '' === $nome ) {
			return 0;
		}

		$id = (int) ( $dados['categoria_raiz_id'] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		$driver = VH_Tiny::driver();

		/* Raiz já criada em outra instalação/tentativa: adotar em vez de duplicar. */
		$existente = $driver->buscar_categoria( $nome );
		if ( is_array( $existente ) && (int) ( $existente['id_tiny'] ?? 0 ) > 0 ) {
			$id = (int) $existente['id_tiny'];
		} else {
			$resp = $driver->criar_categoria( [ 'nome' => $nome ] );
			if ( is_wp_error( $resp ) ) {
				return 0;
			}
			$id = (int) ( $resp['id_tiny'] ?? 0 );
		}

		if ( $id > 0 ) {
			VH_Tiny::salvar_config( [ 'categoria_raiz_id' => $id ] );
		}

		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function termo_para_canonico( WP_Term $termo ): array {
		$pai_tiny = 0;
		if ( (int) $termo->parent > 0 ) {
			$pai_tiny = (int) get_term_meta( (int) $termo->parent, VH_Tiny_Map::META_TINY_CAT_ID, true );
		} else {
			$pai_tiny = self::garantir_raiz_tiny();
		}

		return [
			'term_id'    => (int) $termo->term_id,
			'nome'       => $termo->name,
			'caminho'    => VH_Categories_Service::caminho_hierarquico( $termo ),
			'pai_id'     => (int) $termo->parent,
			'pai_id_tiny'=> $pai_tiny > 0 ? $pai_tiny : null,
			'descricao'  => (string) $termo->description,
		];
	}

	public static function desvincular_categoria( int $term_id ): void {
		delete_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID );
		delete_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR );
	}
}

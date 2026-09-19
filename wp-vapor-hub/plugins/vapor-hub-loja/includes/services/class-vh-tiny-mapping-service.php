<?php
/**
 * Onboarding de mapeamento WooCommerce → Tiny ERP (categorias e atributos de grade).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

class VH_Tiny_Mapping_Service {

	private const MAX_VINCULOS_POR_REQUEST = 200;

	/**
	 * @return array<string, string> taxonomy => rótulo Tiny
	 */
	public static function map_atributos(): array {
		$dados = VH_Tiny::obter();
		$mapa  = $dados['map_atributos'] ?? [];
		return is_array( $mapa ) ? $mapa : [];
	}

	public static function rotulo_atributo( string $taxonomy, string $padrao = '' ): string {
		$taxonomy = sanitize_key( $taxonomy );
		$mapa     = self::map_atributos();
		if ( isset( $mapa[ $taxonomy ] ) && '' !== trim( (string) $mapa[ $taxonomy ] ) ) {
			return (string) $mapa[ $taxonomy ];
		}
		return '' !== $padrao ? $padrao : wc_attribute_label( $taxonomy );
	}

	/**
	 * Categorias da loja ainda sem vínculo Tiny (exclui as marcadas como ignorar).
	 */
	public static function contar_categorias_pendentes(): int {
		return VH_Tiny_Sync_Service::contar_categorias_sem_vinculo();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function obter_mapeamento() {
		if ( ! VH_Tiny::driver()->conectado() ) {
			return new WP_Error(
				'vh_tiny_desconectado',
				__( 'Conecte o Tiny ERP antes de configurar o mapeamento.', 'vapor-hub-loja' ),
				[ 'status' => 400 ]
			);
		}

		$tiny_cats = VH_Tiny::driver()->listar_categorias();
		if ( is_wp_error( $tiny_cats ) ) {
			return $tiny_cats;
		}

		return [
			'conectado'        => true,
			'categorias_loja'  => self::listar_categorias_loja(),
			'categorias_tiny'  => $tiny_cats,
			'atributos'        => self::listar_atributos_mapeamento(),
			'campos_produto'   => self::preview_campos_produto(),
			'pendentes'        => self::contar_categorias_pendentes(),
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function listar_categorias_loja(): array {
		$termos = get_terms(
			[
				'taxonomy'   => VH_Categories_Service::TAXONOMIA,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $termos ) || ! is_array( $termos ) ) {
			return [];
		}

		$lista = [];
		foreach ( $termos as $termo ) {
			if ( ! $termo instanceof WP_Term ) {
				continue;
			}
			$tiny_id  = (int) get_term_meta( (int) $termo->term_id, VH_Tiny_Map::META_TINY_CAT_ID, true );
			$ignorado = (bool) get_term_meta( (int) $termo->term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR, true );

			if ( $tiny_id > 0 && ! self::categoria_existe_no_tiny( $tiny_id, (int) $termo->term_id ) ) {
				$tiny_id = 0;
			}

			$status   = $tiny_id > 0 ? 'vinculado' : ( $ignorado ? 'ignorado' : 'pendente' );
			$lista[]  = [
				'term_id'   => (int) $termo->term_id,
				'nome'      => $termo->name,
				'caminho'   => VH_Categories_Service::caminho_hierarquico( $termo ),
				'parent_id' => (int) $termo->parent,
				'tiny_id'   => $tiny_id > 0 ? $tiny_id : null,
				'status'    => $status,
			];
		}

		usort(
			$lista,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['caminho'], (string) $b['caminho'] );
			}
		);

		return $lista;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function listar_atributos_mapeamento(): array {
		$mapa   = self::map_atributos();
		$saida  = [];

		foreach ( VH_Products_Service::atributos_personalizacao() as $attr ) {
			$taxonomy = (string) ( $attr['taxonomy'] ?? '' );
			if ( '' === $taxonomy ) {
				continue;
			}
			$padrao = wc_attribute_label( $taxonomy );
			$saida[] = [
				'taxonomy'      => $taxonomy,
				'nome_loja'     => (string) ( $attr['nome'] ?? $padrao ),
				'rotulo_tiny'   => self::rotulo_atributo( $taxonomy, $padrao ),
				'rotulo_padrao' => $padrao,
				'termos'        => count( (array) ( $attr['termos'] ?? [] ) ),
			];
		}

		return $saida;
	}

	/**
	 * Campos fixos enviados ao Tiny (somente leitura na UI).
	 *
	 * @return array<int, array{campo_loja:string,campo_tiny:string,nota:string}>
	 */
	public static function preview_campos_produto(): array {
		return [
			[
				'campo_loja' => __( 'Nome', 'vapor-hub-loja' ),
				'campo_tiny' => 'nome',
				'nota'       => '',
			],
			[
				'campo_loja' => 'SKU',
				'campo_tiny' => 'codigo',
				'nota'       => __( 'Variações usam SKU próprio; o pai variável é criado sem código.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Descrição', 'vapor-hub-loja' ),
				'campo_tiny' => 'descricaoComplementar',
				'nota'       => '',
			],
			[
				'campo_loja' => __( 'Preço', 'vapor-hub-loja' ),
				'campo_tiny' => 'preco',
				'nota'       => __( 'Somente produtos simples e variações.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Peso bruto (kg)', 'vapor-hub-loja' ),
				'campo_tiny' => 'dimensoes.pesoBruto',
				'nota'       => __( 'Também vai como pesoLiquido enquanto a loja não separar os dois.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Largura, altura e comprimento (cm)', 'vapor-hub-loja' ),
				'campo_tiny' => 'dimensoes.largura / altura / comprimento',
				'nota'       => __( 'Medida do produto pai; a variação herda. Campo vazio não é enviado como zero.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Nº de volumes', 'vapor-hub-loja' ),
				'campo_tiny' => 'dimensoes.quantidadeVolumes',
				'nota'       => '',
			],
			[
				'campo_loja' => __( 'Tipo de embalagem', 'vapor-hub-loja' ),
				'campo_tiny' => 'dimensoes.embalagem.tipo',
				'nota'       => __( 'Envelope, caixa/pacote ou cilindro/rolo.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Estoque', 'vapor-hub-loja' ),
				'campo_tiny' => 'estoque',
				'nota'       => __( 'Somente produtos simples e variações.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => 'NCM',
				'campo_tiny' => 'ncm',
				'nota'       => __( 'Só os 8 dígitos; pontuação é removida ao salvar.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => 'GTIN / EAN',
				'campo_tiny' => 'gtin',
				'nota'       => '',
			],
			[
				'campo_loja' => __( 'Unidade', 'vapor-hub-loja' ),
				'campo_tiny' => 'unidade',
				'nota'       => __( 'Sempre em maiúsculas (UN, PC, CX).', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Marca', 'vapor-hub-loja' ),
				'campo_tiny' => 'marca.id',
				'nota'       => __( 'Enviada só quando a conta libera o cadastro de marcas; sem isso fica apenas na loja.', 'vapor-hub-loja' ),
			],
			[
				'campo_loja' => __( 'Situação', 'vapor-hub-loja' ),
				'campo_tiny' => 'situacao',
				'nota'       => __( 'A = ativo, I = inativo.', 'vapor-hub-loja' ),
			],
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $vinculos
	 * @return array<string, mixed>|WP_Error
	 */
	public static function salvar_categorias( array $vinculos ) {
		if ( ! VH_Tiny::driver()->conectado() ) {
			return new WP_Error( 'vh_tiny_desconectado', __( 'Tiny ERP não conectado.', 'vapor-hub-loja' ) );
		}

		if ( count( $vinculos ) > self::MAX_VINCULOS_POR_REQUEST ) {
			return new WP_Error(
				'vh_tiny_map_limite',
				sprintf(
					/* translators: %d: max items */
					__( 'Envie no máximo %d categorias por vez.', 'vapor-hub-loja' ),
					self::MAX_VINCULOS_POR_REQUEST
				),
				[ 'status' => 400 ]
			);
		}

		$tiny_ids_validos = self::mavh_ids_categorias_tiny();

		$resultados   = [
			'vinculados' => 0,
			'criados'    => 0,
			'ignorados'  => 0,
			'erros'      => [],
		];
		$enriquecidos = [];

		foreach ( $vinculos as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$term_id = absint( $item['term_id'] ?? 0 );
			if ( $term_id <= 0 ) {
				continue;
			}

			$termo = get_term( $term_id, VH_Categories_Service::TAXONOMIA );
			if ( ! $termo instanceof WP_Term ) {
				continue;
			}

			$item['parent_id'] = (int) $termo->parent;
			$enriquecidos[]    = $item;
		}

		usort(
			$enriquecidos,
			static function ( $a, $b ) {
				$pa = (int) ( $a['parent_id'] ?? 0 );
				$pb = (int) ( $b['parent_id'] ?? 0 );
				if ( $pa === $pb ) {
					return (int) ( $a['term_id'] ?? 0 ) <=> (int) ( $b['term_id'] ?? 0 );
				}
				return $pa <=> $pb;
			}
		);

		foreach ( $enriquecidos as $item ) {
			$term_id = absint( $item['term_id'] ?? 0 );
			$acao    = sanitize_key( (string) ( $item['acao'] ?? '' ) );
			if ( $term_id <= 0 || ! in_array( $acao, [ 'vincular', 'criar', 'ignorar' ], true ) ) {
				continue;
			}

			$termo = get_term( $term_id, VH_Categories_Service::TAXONOMIA );
			$tiny_id_atual = (int) get_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, true );

			if ( 'ignorar' === $acao ) {
				delete_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID );
				update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR, '1' );
				++$resultados['ignorados'];
				continue;
			}

			if ( 'vincular' === $acao ) {
				if ( $tiny_id_atual > 0 && (int) ( $item['tiny_id'] ?? 0 ) === $tiny_id_atual ) {
					continue;
				}

				$tiny_id = absint( $item['tiny_id'] ?? 0 );
				if ( $tiny_id <= 0 ) {
					$nome_loja = $termo instanceof WP_Term ? $termo->name : (string) $term_id;
					$resultados['erros'][] = [
						'term_id' => $term_id,
						'msg'     => self::mensagem_sem_tiny_vinculo( $nome_loja ),
					];
					continue;
				}

				if ( ! isset( $tiny_ids_validos[ $tiny_id ] ) ) {
					$resultados['erros'][] = [
						'term_id' => $term_id,
						'msg'     => __( 'A categoria selecionada não existe mais no Tiny.', 'vapor-hub-loja' ),
					];
					continue;
				}

				$conflito = self::termo_com_tiny_id( $tiny_id, $term_id );
				if ( $conflito instanceof WP_Term ) {
					$nome_loja = $termo instanceof WP_Term ? $termo->name : (string) $term_id;
					$resultados['erros'][] = [
						'term_id' => $term_id,
						'msg'     => self::mensagem_conflito_vinculo(
							$nome_loja,
							$conflito->name,
							$tiny_ids_validos[ $tiny_id ]['caminho'] ?? '',
							$termo instanceof WP_Term ? (int) $termo->parent : 0,
							(int) $conflito->term_id
						),
					];
					continue;
				}

				delete_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR );
				update_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID, $tiny_id );
				++$resultados['vinculados'];
				continue;
			}

			if ( ! VH_Tiny::pode_enviar() ) {
				$resultados['erros'][] = [
					'term_id' => $term_id,
					'msg'     => __( 'O envio ao Tiny está desativado. Ative “Permitir envio” para criar categorias.', 'vapor-hub-loja' ),
				];
				continue;
			}

			delete_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_IGNORAR );
			$resp = VH_Tiny_Category_Sync::empurrar_categoria( $term_id );
			if ( is_wp_error( $resp ) ) {
				$resultados['erros'][] = [
					'term_id' => $term_id,
					'msg'     => $resp->get_error_message(),
				];
				continue;
			}
			++$resultados['criados'];
		}

		return [
			'resultados'      => $resultados,
			'categorias_loja' => self::listar_categorias_loja(),
			'pendentes'       => self::contar_categorias_pendentes(),
		];
	}

	/**
	 * @param array<string, string> $map_atributos
	 * @return array<string, mixed>
	 */
	public static function salvar_atributos( array $map_atributos ): array {
		$permitidos = [];
		foreach ( VH_Products_Service::atributos_personalizacao() as $attr ) {
			$taxonomy = sanitize_key( (string) ( $attr['taxonomy'] ?? '' ) );
			if ( '' !== $taxonomy ) {
				$permitidos[ $taxonomy ] = true;
			}
		}

		$limpo = [];
		foreach ( $map_atributos as $taxonomy => $rotulo ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' === $taxonomy || ! isset( $permitidos[ $taxonomy ] ) ) {
				continue;
			}
			$limpo[ $taxonomy ] = sanitize_text_field( (string) $rotulo );
		}

		VH_Tiny::salvar_config( [ 'map_atributos' => $limpo ] );

		return [
			'atributos' => self::listar_atributos_mapeamento(),
		];
	}

	/**
	 * @return array<int, array{caminho:string,nome:string}>
	 */
	private static function mavh_ids_categorias_tiny(): array {
		static $cache = null;

		if ( is_array( $cache ) ) {
			return $cache;
		}

		$cache  = [];
		$lista  = VH_Tiny::driver()->listar_categorias();
		if ( is_wp_error( $lista ) || ! is_array( $lista ) ) {
			return $cache;
		}

		foreach ( $lista as $cat ) {
			$id = (int) ( $cat['id_tiny'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$cache[ $id ] = [
				'nome'    => (string) ( $cat['nome'] ?? '' ),
				'caminho' => (string) ( $cat['caminho'] ?? '' ),
			];
		}

		return $cache;
	}

	private static function mensagem_sem_tiny_vinculo( string $nome_loja ): string {
		return sprintf(
			/* translators: %s: store category name */
			__( '“%s”: você marcou Vincular, mas não escolheu a categoria do Tiny. Selecione a categoria do Tiny que é exatamente a mesma desta da loja (mesmo nome e mesmo nível — não escolha a categoria “mãe” se esta for subcategoria).', 'vapor-hub-loja' ),
			$nome_loja
		);
	}

	private static function mensagem_conflito_vinculo( string $nome_loja, string $nome_dono_loja, string $caminho_tiny, int $parent_id = 0, int $dono_term_id = 0 ): string {
		if ( $parent_id > 0 && $parent_id === $dono_term_id ) {
			return sprintf(
				/* translators: 1: store category name, 2: parent store category name, 3: Tiny category path */
				__( '“%1$s”: Vincular só serve quando esta categoria é a mesma no Tiny — não para subcategorias. A hierarquia na loja já está correta (“%1$s” é filha de “%2$s”, que já está no Tiny). Troque a ação para “Criar no Tiny” e salve. Não use Vincular nem selecione “%3$s” do Tiny.', 'vapor-hub-loja' ),
				$nome_loja,
				$nome_dono_loja,
				$caminho_tiny
			);
		}

		return sprintf(
			/* translators: 1: store category name, 2: conflicting store category name, 3: Tiny category path */
			__( '“%1$s”: Vincular não cria subcategoria no Tiny — só indica que esta categoria da loja é a mesma que já existe no ERP (1 categoria da loja = 1 categoria do Tiny). A categoria “%3$s” do Tiny já está ligada a “%2$s” na loja. Se você quer “%1$s” dentro de “%2$s” no Tiny: (1) em Minha Loja → Categorias, faça “%1$s” ser subcategoria (filha) de “%2$s”; (2) volte aqui e escolha a ação “Criar no Tiny” para “%1$s” — não use Vincular.', 'vapor-hub-loja' ),
			$nome_loja,
			$nome_dono_loja,
			$caminho_tiny
		);
	}

	/**
	 * Confere na API Tiny se o ID gravado localmente ainda existe.
	 */
	private static function categoria_existe_no_tiny( int $tiny_id, int $term_id ): bool {
		if ( $tiny_id <= 0 || 'v3' !== VH_Tiny::modo() || ! VH_Tiny::driver()->conectado() ) {
			return true;
		}

		$resp = VH_Tiny_Client::requisicao( 'GET', 'categorias/' . $tiny_id );
		if ( ! is_wp_error( $resp ) ) {
			return true;
		}

		$status = (int) ( $resp->get_error_data()['status'] ?? 0 );
		if ( 404 !== $status ) {
			return true;
		}

		delete_term_meta( $term_id, VH_Tiny_Map::META_TINY_CAT_ID );

		$termo = get_term( $term_id, VH_Categories_Service::TAXONOMIA );
		$nome  = $termo instanceof WP_Term ? $termo->name : (string) $term_id;

		VH_Tiny_Log::warning(
			VH_Tiny_Log::ORIGEM_MAPPING,
			sprintf(
				/* translators: 1: category name, 2: tiny category id */
				__( 'Vínculo obsoleto removido: “%1$s” (Tiny #%2$d não existe mais).', 'vapor-hub-loja' ),
				$nome,
				$tiny_id
			),
			'cat_' . $term_id,
			[ 'term_id' => $term_id, 'tiny_id' => $tiny_id ]
		);

		return false;
	}

	private static function termo_com_tiny_id( int $tiny_id, int $except_term_id ): ?WP_Term {
		$termos = get_terms(
			[
				'taxonomy'   => VH_Categories_Service::TAXONOMIA,
				'hide_empty' => false,
				'number'     => 1,
				'exclude'    => [ $except_term_id ],
				'meta_query' => [
					[
						'key'     => VH_Tiny_Map::META_TINY_CAT_ID,
						'value'   => (string) $tiny_id,
						'compare' => '=',
					],
				],
			]
		);

		if ( is_wp_error( $termos ) || ! is_array( $termos ) || ! $termos ) {
			return null;
		}

		$termo = $termos[0];
		return $termo instanceof WP_Term ? $termo : null;
	}
}

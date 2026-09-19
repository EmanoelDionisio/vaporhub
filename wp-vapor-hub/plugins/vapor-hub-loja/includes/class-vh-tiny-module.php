<?php
/**
 * Bootstrap do módulo Tiny ERP — hooks, cron e listeners.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

final class VH_Tiny_Module {

	public static function init(): void {
		add_filter( 'cron_schedules', [ VH_Tiny_Queue::class, 'registrar_intervalo' ] );
		add_action( VH_Tiny_Queue::CRON_HOOK, [ self::class, 'cron_poll_estoque_v2' ], 5 );
		add_action( VH_Tiny_Queue::CRON_HOOK, [ VH_Tiny_Queue::class, 'processar_lote' ] );
		add_action( VH_Tiny_Queue::CRON_RECONCIL, [ self::class, 'cron_reconciliar' ] );
		add_action( 'init', [ self::class, 'garantir_cron' ], 20 );

		add_action( 'vh_produto_salvo', [ self::class, 'ao_salvar_produto' ], 10, 3 );
		add_action( 'vh_produto_excluido', [ self::class, 'ao_excluir_produto' ], 10, 1 );
		add_action( 'vh_categoria_salva', [ self::class, 'ao_salvar_categoria' ], 10, 3 );
		add_action( 'vh_categoria_excluida', [ self::class, 'ao_excluir_categoria' ], 10, 1 );
		add_action( 'vh_pedido_salvo', [ self::class, 'ao_salvar_pedido' ], 10, 2 );
		add_action( 'woocommerce_new_order', [ self::class, 'ao_novo_pedido_wc' ], 10, 1 );

		/* Venda no site muda o saldo sem passar pelo formulário do painel. */
		add_action( 'woocommerce_product_set_stock', [ self::class, 'ao_mudar_estoque' ], 10, 1 );
		add_action( 'woocommerce_variation_set_stock', [ self::class, 'ao_mudar_estoque' ], 10, 1 );
	}

	public static function instalar(): void {
		VH_Tiny_Queue::instalar_tabela();
		VH_Tiny_Log::instalar_tabela();
		VH_Tiny_Queue::agendar_cron();
		self::migrar_webhook_token();
	}

	/**
	 * Instalação antiga guardava o token do webhook em texto puro. Passa a guardar
	 * o hash, mantendo o valor em claro no cofre para o painel continuar mostrando
	 * a URL que já está cadastrada no Tiny.
	 */
	private static function migrar_webhook_token(): void {
		if ( ! class_exists( 'VH_Tiny' ) ) {
			return;
		}

		$token = VH_Tiny::webhook_token();
		if ( '' === $token ) {
			return;
		}

		/* Revalidar com o próprio token migra o armazenamento sem trocar o valor. */
		VH_Tiny::validar_webhook_token( $token );
	}

	public static function desinstalar(): void {
		VH_Tiny_Queue::limpar_cron();
	}

	/**
	 * Reagenda crons da fila após deploy/atualização sem reativar o plugin.
	 */
	public static function garantir_cron(): void {
		if ( ! class_exists( 'VH_Tiny' ) || ! class_exists( 'VH_Tiny_Queue' ) ) {
			return;
		}
		$dados = VH_Tiny::obter();
		if ( empty( $dados['ativo'] ) ) {
			return;
		}
		VH_Tiny_Queue::agendar_cron();
	}

	public static function cron_reconciliar(): void {
		if ( ! self::integracao_operacional() ) {
			return;
		}
		$dados = VH_Tiny::obter();
		if ( empty( $dados['sinc_auto'] ) ) {
			return;
		}
		VH_Tiny_Log::limpar_antigos();
		VH_Tiny_Queue::enfileirar( 'reconciliar', 'global', [] );
		VH_Tiny_Queue::processar_lote();
	}

	public static function cron_poll_estoque_v2(): void {
		if ( 'v2' !== VH_Tiny::modo() || ! self::integracao_operacional() ) {
			return;
		}
		$dados = VH_Tiny::obter();
		if ( empty( $dados['sinc_auto'] ) || ! VH_Tiny::pode_receber() ) {
			return;
		}
		VH_Tiny_Sync_Service::poll_atualizacoes_estoque_v2();
	}

	private static function integracao_operacional(): bool {
		$dados = VH_Tiny::obter();
		return ! empty( $dados['ativo'] ) && VH_Tiny::driver()->conectado();
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public static function ao_salvar_produto( int $produto_id, array $dados, string $acao = 'criar' ): void {
		if ( ! self::integracao_operacional() || VH_Tiny_Sync_Service::em_guarda( $produto_id ) || VH_Tiny_Sync_Service::suprimir_push_loja() ) {
			return;
		}
		$dados_cfg = VH_Tiny::obter();
		if ( empty( $dados_cfg['sinc_auto'] ) || ! VH_Tiny::pode_enviar() ) {
			return;
		}

		$hash_novo = VH_Tiny_Map::hash_conteudo_loja( $dados );
		$produto   = wc_get_product( $produto_id );
		if ( $produto instanceof WC_Product ) {
			$hash_novo = VH_Tiny_Map::hash_produto_wc( $produto );
		}
		$hash_ant  = (string) get_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH, true );
		if ( $hash_novo === $hash_ant && 'criar' !== $acao ) {
			return;
		}

		VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_' . $produto_id, [ 'produto_id' => $produto_id ] );
	}

	/**
	 * Saldo mudou no WooCommerce (venda, estorno ou ajuste): manda o número novo
	 * ao ERP sem reenviar o cadastro inteiro.
	 *
	 * O produto pai variável não tem saldo próprio — quem controla é a variação,
	 * e ela chega aqui pelo hook `woocommerce_variation_set_stock`.
	 *
	 * @param WC_Product|int $produto
	 */
	public static function ao_mudar_estoque( $produto ): void {
		$produto = is_numeric( $produto ) ? wc_get_product( (int) $produto ) : $produto;
		if ( ! $produto instanceof WC_Product || $produto->is_type( 'variable' ) ) {
			return;
		}

		$produto_id = $produto->get_id();
		if ( ! self::integracao_operacional() || VH_Tiny_Sync_Service::em_guarda( $produto_id ) || VH_Tiny_Sync_Service::suprimir_push_loja() ) {
			return;
		}

		$dados_cfg = VH_Tiny::obter();
		if ( empty( $dados_cfg['sinc_auto'] ) || ! VH_Tiny::pode_enviar() ) {
			return;
		}

		VH_Tiny_Queue::enfileirar( 'estoque_push', 'estoque_' . $produto_id, [ 'produto_id' => $produto_id ] );
	}

	public static function ao_excluir_produto( int $produto_id ): void {
		delete_post_meta( $produto_id, VH_Tiny_Map::META_TINY_ID );
		delete_post_meta( $produto_id, VH_Tiny_Map::META_SYNC_HASH );
		delete_post_meta( $produto_id, VH_Tiny_Map::META_LAST_SYNC );
		delete_post_meta( $produto_id, VH_Tiny_Map::META_GUARD );

		$produto = wc_get_product( $produto_id );
		if ( $produto instanceof WC_Product_Variable ) {
			foreach ( $produto->get_children() as $vid ) {
				delete_post_meta( (int) $vid, VH_Tiny_Map::META_TINY_ID );
				delete_post_meta( (int) $vid, VH_Tiny_Map::META_GUARD );
			}
		}
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public static function ao_salvar_categoria( int $term_id, array $dados, string $acao = 'criar' ): void {
		unset( $dados, $acao );
		if ( ! self::integracao_operacional() ) {
			return;
		}
		$dados_cfg = VH_Tiny::obter();
		if ( empty( $dados_cfg['sinc_auto'] ) || ! VH_Tiny::pode_enviar() ) {
			return;
		}
		VH_Tiny_Queue::enfileirar( 'categoria_push', 'cat_' . $term_id, [ 'term_id' => $term_id ] );
	}

	public static function ao_excluir_categoria( int $term_id ): void {
		VH_Tiny_Category_Sync::desvincular_categoria( $term_id );
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public static function ao_salvar_pedido( int $pedido_id, array $dados = [] ): void {
		unset( $dados );
		self::enfileirar_pedido( $pedido_id );
	}

	public static function ao_novo_pedido_wc( $pedido_id ): void {
		self::enfileirar_pedido( (int) $pedido_id );
	}

	private static function enfileirar_pedido( int $pedido_id ): void {
		if ( ! self::integracao_operacional() ) {
			return;
		}
		$dados = VH_Tiny::obter();
		if ( empty( $dados['sinc_auto'] ) || ! VH_Tiny::pode_enviar() ) {
			return;
		}
		VH_Tiny_Queue::enfileirar( 'pedido_push', 'order_' . $pedido_id, [ 'pedido_id' => $pedido_id ] );
	}
}

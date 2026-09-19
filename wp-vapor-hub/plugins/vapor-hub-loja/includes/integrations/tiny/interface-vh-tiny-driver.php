<?php
/**
 * Contrato do driver Tiny ERP (v2 ou v3).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

interface VH_Tiny_Driver_Interface {

	public function versao(): string;

	public function ativo(): bool;

	public function conectado(): bool;

	public function credenciais_configuradas(): bool;

	/**
	 * @return array<string, mixed>
	 */
	public function status_driver(): array;

	public function desconectar(): void;

	/**
	 * @return array<string, mixed>|null|WP_Error Produto canônico ou null.
	 */
	public function buscar_produto_por_sku( string $sku );

	/**
	 * @return array<string, mixed>|WP_Error Produto canônico.
	 */
	public function obter_produto( int $tiny_id );

	/**
	 * @param array<string, mixed> $canonico
	 * @param bool                 $somente_pai Produto variável: cria só o pai com grade (variações depois).
	 * @return array<string, mixed>|WP_Error Resposta com id_tiny.
	 */
	public function criar_produto( array $canonico, bool $somente_pai = false );

	/**
	 * Exclui produto no Tiny (usado ao recriar cadastro após mudança de grade).
	 *
	 * @return true|WP_Error
	 */
	public function excluir_produto( int $tiny_id );

	/**
	 * @param array<string, mixed> $canonico
	 * @return true|WP_Error
	 */
	public function atualizar_produto( int $tiny_id, array $canonico );

	/**
	 * @param array<string, mixed> $canonico Produto pai com variacoes[].
	 * @return true|array{variacoes:array<int,array<string,mixed>>}|WP_Error
	 */
	public function sincronizar_variacoes( int $tiny_id_pai, array $canonico );

	/**
	 * Importa imagens da loja para o cadastro do produto no Tiny.
	 *
	 * @return true|WP_Error
	 */
	public function sincronizar_imagens( int $tiny_id, array $canonico );

	/**
	 * Ativa controle de estoque no Tiny e alinha a quantidade com a loja.
	 *
	 * @return true|WP_Error
	 */
	public function sincronizar_estoque( int $tiny_id, array $canonico );

	/**
	 * Alinha somente o saldo de um item (produto simples ou variação), sem
	 * reescrever o cadastro no ERP.
	 *
	 * @param array<string, mixed> $item Item canônico com estoque e preço.
	 * @return true|WP_Error
	 */
	public function sincronizar_saldo( int $tiny_id, array $item );

	/**
	 * Inativa/remove uma variação no Tiny (best-effort).
	 *
	 * @return true|WP_Error
	 */
	public function remover_variacao( int $tiny_id_pai, int $tiny_id_variacao );

	/**
	 * @return array{itens:array<int,array<string,mixed>>,total:int,pagina:int}|WP_Error
	 */
	public function listar_produtos( int $pagina, int $por_pagina );

	/**
	 * @return array<string, mixed>|WP_Error Dados de estoque canônicos.
	 */
	public function obter_estoque( int $tiny_id );

	/**
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>|WP_Error id_tiny_pedido etc.
	 */
	public function criar_pedido( array $canonico );

	/**
	 * @return array<int, array<string, mixed>>|WP_Error Atualizações de estoque (v2 cron).
	 */
	public function listar_atualizacoes_estoque( string $desde );

	/**
	 * @param array<string, mixed> $payload
	 * @return array{tipo:string,tiny_id:int,sku:string}
	 */
	public function normalizar_webhook( array $payload ): array;

	/**
	 * @return array<string, mixed>|null|WP_Error Categoria canônica ou null.
	 */
	public function buscar_categoria( string $caminho );

	/**
	 * @param array<string, mixed> $canonico
	 * @return array<string, mixed>|WP_Error id_tiny.
	 */
	public function criar_categoria( array $canonico );

	/**
	 * @param array<string, mixed> $canonico
	 * @return true|WP_Error
	 */
	public function atualizar_categoria( int $tiny_id, array $canonico );

	/**
	 * Lista categorias do Tiny (achatadas com caminho hierárquico).
	 *
	 * @return array<int, array{id_tiny:int,nome:string,caminho:string}>|WP_Error
	 */
	public function listar_categorias();
}

<?php
/**
 * Controlador REST base do app Minha Loja.
 *
 * Todos os endpoints internos vivem no namespace vh-loja/v1 e exigem a
 * capacidade gerenciar_vh_loja. Esta base centraliza a verificação de permissão
 * e helpers de resposta para garantir que nenhum endpoint responda sem
 * autenticação/autorização.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

abstract class VH_REST_Controller {

    public const NS = 'vh-loja/v1';

    /**
     * Base da rota (ex.: "pedidos").
     */
    protected string $rest_base = '';

    /**
     * Registra as rotas do controlador. Implementado por cada controlador.
     */
    abstract public function register_routes(): void;

    /**
     * Permissão única de acesso ao app (porteiro de todos os endpoints).
     */
    public function permissao(): bool {
        return current_user_can( VH_Roles::CAP );
    }

    /**
     * Garante que o WooCommerce está disponível antes de operar.
     */
    protected function woocommerce_disponivel(): bool {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Resposta de sucesso padronizada.
     *
     * @param mixed $dados
     */
    protected function ok( $dados = null, int $status = 200 ): WP_REST_Response {
        return new WP_REST_Response(
            [ 'sucesso' => true, 'dados' => $dados ],
            $status
        );
    }

    /**
     * Erro padronizado.
     */
    protected function erro( string $codigo, string $mensagem, int $status = 400 ): WP_Error {
        return new WP_Error( $codigo, $mensagem, [ 'status' => $status ] );
    }

    /**
     * Argumento de paginação reutilizável.
     *
     * @return array<string,array<string,mixed>>
     */
    protected function args_paginacao(): array {
        return [
            'pagina'     => [
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v >= 1,
            ],
            'por_pagina' => [
                'type'              => 'integer',
                'default'           => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $v ) => is_numeric( $v ) && (int) $v >= 1 && (int) $v <= 100,
            ],
            'busca'      => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}

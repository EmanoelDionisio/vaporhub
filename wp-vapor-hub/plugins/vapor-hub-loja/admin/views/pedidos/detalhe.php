<?php
/**
 * View — Detalhe / edição de Pedido.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id     = VH_Router::id_atual();
$vh_pedido = $vh_id ? VH_Orders_Service::obter( $vh_id ) : null;

if ( ! $vh_pedido ) {
    echo '<div class="vh-admin-wrap"><div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Pedido não encontrado.', 'vapor-hub-loja' ) . '</p><p><a class="vh-btn vh-btn--secundario" href="' .
        esc_url( VH_Router::url( 'pedidos' ) ) . '">' . esc_html__( 'Voltar para pedidos', 'vapor-hub-loja' ) . '</a></p></div></div>';
    return;
}

$vh_status_opcoes = VH_Orders_Service::status_editaveis();
$vh_endpoint      = 'pedidos/' . $vh_id;
?>

<div class="vh-admin-wrap">

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'pedidos' ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para pedidos', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <div class="vh-form-colunas">

        <div>
            <!-- Itens do pedido -->
            <div class="vh-admin-section">
                <h2><?php printf( esc_html__( 'Pedido #%s', 'vapor-hub-loja' ), esc_html( $vh_pedido['numero'] ) ); ?></h2>
                <table class="vh-admin-tabela">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Produto', 'vapor-hub-loja' ); ?></th>
                            <th><?php esc_html_e( 'Qtd.', 'vapor-hub-loja' ); ?></th>
                            <th><?php esc_html_e( 'Total', 'vapor-hub-loja' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $vh_pedido['itens'] as $vh_item ) : ?>
                            <tr>
                                <td><?php echo esc_html( $vh_item['nome'] ); ?></td>
                                <td><?php echo esc_html( $vh_item['quantidade'] ); ?></td>
                                <td><?php echo wp_kses_post( $vh_item['total'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align:right;font-size:16px;margin-top:16px">
                    <strong><?php esc_html_e( 'Total:', 'vapor-hub-loja' ); ?></strong>
                    <?php echo wp_kses_post( $vh_pedido['total_html'] ); ?>
                </p>
            </div>

            <!-- Endereços -->
            <form class="vh-admin-section vh-rest-form" data-endpoint="<?php echo esc_attr( $vh_endpoint ); ?>" data-method="PATCH" data-reload="1">
                <h2><?php esc_html_e( 'Endereços', 'vapor-hub-loja' ); ?></h2>

                <h4 style="margin:0 0 12px;color:var(--vh-cinza-700)"><?php esc_html_e( 'Cobrança', 'vapor-hub-loja' ); ?></h4>
                <div class="vh-form-linha">
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Nome', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="cobranca[first_name]" value="<?php echo esc_attr( $vh_pedido['cobranca']['first_name'] ); ?>" />
                    </div>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Sobrenome', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="cobranca[last_name]" value="<?php echo esc_attr( $vh_pedido['cobranca']['last_name'] ); ?>" />
                    </div>
                </div>
                <div class="vh-form-linha">
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'E-mail', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="cobranca[email]" value="<?php echo esc_attr( $vh_pedido['cobranca']['email'] ); ?>" />
                    </div>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Telefone', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="cobranca[phone]" value="<?php echo esc_attr( $vh_pedido['cobranca']['phone'] ); ?>" />
                    </div>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Endereço', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="cobranca[address_1]" value="<?php echo esc_attr( $vh_pedido['cobranca']['address_1'] ); ?>" />
                </div>
                <div class="vh-form-linha">
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Cidade', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="cobranca[city]" value="<?php echo esc_attr( $vh_pedido['cobranca']['city'] ); ?>" />
                    </div>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'UF', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="cobranca[state]" value="<?php echo esc_attr( $vh_pedido['cobranca']['state'] ); ?>" />
                    </div>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'CEP', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="cobranca[postcode]" value="<?php echo esc_attr( $vh_pedido['cobranca']['postcode'] ); ?>" />
                </div>

                <hr class="vh-separador" />

                <h4 style="margin:0 0 12px;color:var(--vh-cinza-700)"><?php esc_html_e( 'Entrega', 'vapor-hub-loja' ); ?></h4>
                <div class="vh-form-linha">
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Nome', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="entrega[first_name]" value="<?php echo esc_attr( $vh_pedido['entrega']['first_name'] ); ?>" />
                    </div>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Sobrenome', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="entrega[last_name]" value="<?php echo esc_attr( $vh_pedido['entrega']['last_name'] ); ?>" />
                    </div>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Endereço', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="entrega[address_1]" value="<?php echo esc_attr( $vh_pedido['entrega']['address_1'] ); ?>" />
                </div>
                <div class="vh-form-linha">
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Cidade', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="entrega[city]" value="<?php echo esc_attr( $vh_pedido['entrega']['city'] ); ?>" />
                    </div>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'UF', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="entrega[state]" value="<?php echo esc_attr( $vh_pedido['entrega']['state'] ); ?>" />
                    </div>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'CEP', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="entrega[postcode]" value="<?php echo esc_attr( $vh_pedido['entrega']['postcode'] ); ?>" />
                </div>

                <div class="vh-form-acoes">
                    <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar endereços', 'vapor-hub-loja' ); ?></button>
                </div>
            </form>

            <!-- Notas -->
            <div class="vh-admin-section">
                <h2><?php esc_html_e( 'Notas do Pedido', 'vapor-hub-loja' ); ?></h2>
                <?php if ( ! empty( $vh_pedido['notas'] ) ) : ?>
                    <div class="vh-def-lista" style="margin-bottom:20px">
                        <?php foreach ( $vh_pedido['notas'] as $vh_nota ) : ?>
                            <div>
                                <span><?php echo esc_html( $vh_nota['conteudo'] ); ?></span>
                                <span style="color:var(--vh-cinza-500);font-size:12px;white-space:nowrap">
                                    <?php echo esc_html( $vh_nota['data'] ); ?>
                                    <?php echo $vh_nota['cliente'] ? ' · ' . esc_html__( 'cliente', 'vapor-hub-loja' ) : ''; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="vh-rest-form" data-endpoint="<?php echo esc_attr( $vh_endpoint ); ?>" data-method="PATCH" data-reload="1">
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Adicionar nota', 'vapor-hub-loja' ); ?></label>
                        <textarea name="nota" rows="3" placeholder="<?php esc_attr_e( 'Escreva uma nota sobre o pedido…', 'vapor-hub-loja' ); ?>"></textarea>
                    </div>
                    <div class="vh-toggle-wrapper" style="margin-bottom:12px">
                        <input type="checkbox" class="vh-toggle" id="vh-nota-cliente" name="nota_para_cliente" value="1" />
                        <label for="vh-nota-cliente" style="font-weight:600;cursor:pointer">
                            <?php esc_html_e( 'Enviar como nota ao cliente (notifica por e-mail)', 'vapor-hub-loja' ); ?>
                        </label>
                    </div>
                    <button type="submit" class="vh-btn vh-btn--secundario"><?php esc_html_e( 'Adicionar nota', 'vapor-hub-loja' ); ?></button>
                </form>
            </div>
        </div>

        <div>
            <!-- Status e rastreio -->
            <form class="vh-admin-section vh-rest-form" data-endpoint="<?php echo esc_attr( $vh_endpoint ); ?>" data-method="PATCH" data-reload="1">
                <h2><?php esc_html_e( 'Situação', 'vapor-hub-loja' ); ?></h2>

                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Status do pedido', 'vapor-hub-loja' ); ?></label>
                    <select name="status">
                        <?php foreach ( $vh_status_opcoes as $vh_slug => $vh_label ) : ?>
                            <option value="<?php echo esc_attr( $vh_slug ); ?>" <?php selected( $vh_pedido['status'], $vh_slug ); ?>>
                                <?php echo esc_html( $vh_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Código de rastreio', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="codigo_rastreio" value="<?php echo esc_attr( $vh_pedido['codigo_rastreio'] ); ?>"
                           placeholder="<?php esc_attr_e( 'Ex: PA123456789BR', 'vapor-hub-loja' ); ?>" />
                </div>

                <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar situação', 'vapor-hub-loja' ); ?></button>
            </form>

            <!-- Resumo -->
            <div class="vh-admin-section">
                <h2><?php esc_html_e( 'Resumo', 'vapor-hub-loja' ); ?></h2>
                <dl class="vh-def-lista">
                    <div><dt><?php esc_html_e( 'Data', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( $vh_pedido['data'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Pagamento', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( $vh_pedido['metodo_pagamento'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Envio', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( $vh_pedido['metodo_envio'] ); ?></dd></div>
                </dl>
            </div>
        </div>

    </div>
</div>

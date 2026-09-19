<?php
/**
 * View — Detalhe de Cliente.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id  = VH_Router::id_atual();
$vh_cli = $vh_id ? VH_Customers_Service::obter( $vh_id ) : null;

if ( ! $vh_cli ) {
    echo '<div class="vh-admin-wrap"><div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Cliente não encontrado.', 'vapor-hub-loja' ) . '</p></div></div>';
    return;
}
?>

<div class="vh-admin-wrap">

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'clientes' ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para clientes', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <div class="vh-form-colunas">

        <div>
            <div class="vh-admin-section">
                <h2><?php esc_html_e( 'Últimos pedidos', 'vapor-hub-loja' ); ?></h2>
                <?php if ( ! empty( $vh_cli['pedidos'] ) ) : ?>
                    <table class="vh-admin-tabela">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Pedido', 'vapor-hub-loja' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></th>
                                <th><?php esc_html_e( 'Total', 'vapor-hub-loja' ); ?></th>
                                <th><?php esc_html_e( 'Data', 'vapor-hub-loja' ); ?></th>
                                <th class="vh-col-acoes"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $vh_cli['pedidos'] as $vh_ped ) : ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html( $vh_ped['numero'] ); ?></strong></td>
                                    <td><span class="vh-status vh-status--<?php echo esc_attr( $vh_ped['status'] ); ?>"><?php echo esc_html( $vh_ped['status_label'] ); ?></span></td>
                                    <td><?php echo wp_kses_post( $vh_ped['total_html'] ); ?></td>
                                    <td><?php echo esc_html( $vh_ped['data'] ); ?></td>
                                    <td class="vh-col-acoes">
                                        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'pedidos', [ 'acao' => 'ver', 'id' => $vh_ped['id'] ] ) ); ?>">
                                            <?php esc_html_e( 'Abrir', 'vapor-hub-loja' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="vh-tabela-vazia"><?php esc_html_e( 'Este cliente ainda não fez pedidos.', 'vapor-hub-loja' ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="vh-admin-section">
                <h2><?php echo esc_html( $vh_cli['nome'] ?: __( 'Cliente', 'vapor-hub-loja' ) ); ?></h2>
                <dl class="vh-def-lista">
                    <div><dt><?php esc_html_e( 'E-mail', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( $vh_cli['email'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Telefone', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( $vh_cli['telefone'] ?: '—' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Cliente desde', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( $vh_cli['registro'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Pedidos', 'vapor-hub-loja' ); ?></dt><dd><?php echo esc_html( (string) $vh_cli['total_pedidos'] ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Total gasto', 'vapor-hub-loja' ); ?></dt><dd><?php echo wp_kses_post( $vh_cli['total_gasto'] ); ?></dd></div>
                </dl>
            </div>

            <div class="vh-admin-section">
                <h2><?php esc_html_e( 'Endereço', 'vapor-hub-loja' ); ?></h2>
                <p style="color:var(--vh-cinza-700);line-height:1.7;margin:0">
                    <?php echo esc_html( $vh_cli['endereco']['address_1'] ?: '—' ); ?><br />
                    <?php echo esc_html( trim( $vh_cli['endereco']['city'] . ' ' . $vh_cli['endereco']['state'] ) ); ?><br />
                    <?php echo esc_html( $vh_cli['endereco']['postcode'] ); ?>
                </p>
            </div>
        </div>

    </div>
</div>

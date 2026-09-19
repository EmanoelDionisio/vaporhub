<?php
/**
 * View — Criar/Editar Cupom.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id     = VH_Router::id_atual();
$vh_edicao = $vh_id > 0;
$vh_cupom  = $vh_edicao ? VH_Coupons_Service::obter( $vh_id ) : null;

if ( $vh_edicao && ! $vh_cupom ) {
    echo '<div class="vh-admin-wrap"><div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Cupom não encontrado.', 'vapor-hub-loja' ) . '</p></div></div>';
    return;
}

$vh_dados = wp_parse_args(
    (array) $vh_cupom,
    [ 'codigo' => '', 'descricao' => '', 'tipo' => 'percent', 'valor' => '', 'expira' => '', 'limite' => '', 'minimo' => '' ]
);

$vh_endpoint = $vh_edicao ? 'cupons/' . $vh_id : 'cupons';
$vh_metodo   = $vh_edicao ? 'PATCH' : 'POST';
$vh_tipos    = VH_Coupons_Service::tipos();
?>

<div class="vh-admin-wrap">

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'cupons' ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para cupons', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <form class="vh-rest-form" data-endpoint="<?php echo esc_attr( $vh_endpoint ); ?>" data-method="<?php echo esc_attr( $vh_metodo ); ?>"
          data-redirect="<?php echo esc_url( VH_Router::url( 'cupons' ) ); ?>">

        <div class="vh-admin-section" style="max-width:680px">
            <h2><?php echo $vh_edicao ? esc_html__( 'Editar cupom', 'vapor-hub-loja' ) : esc_html__( 'Novo cupom', 'vapor-hub-loja' ); ?></h2>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Código', 'vapor-hub-loja' ); ?></label>
                <input type="text" name="codigo" value="<?php echo esc_attr( $vh_dados['codigo'] ); ?>" <?php echo $vh_edicao ? 'readonly' : 'required'; ?> />
                <?php if ( $vh_edicao ) : ?>
                    <p class="description"><?php esc_html_e( 'O código não pode ser alterado depois de criado.', 'vapor-hub-loja' ); ?></p>
                <?php endif; ?>
            </div>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Descrição (interna)', 'vapor-hub-loja' ); ?></label>
                <textarea name="descricao" rows="2"><?php echo esc_textarea( $vh_dados['descricao'] ); ?></textarea>
            </div>

            <div class="vh-form-linha">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Tipo de desconto', 'vapor-hub-loja' ); ?></label>
                    <select name="tipo">
                        <?php foreach ( $vh_tipos as $vh_slug => $vh_label ) : ?>
                            <option value="<?php echo esc_attr( $vh_slug ); ?>" <?php selected( $vh_dados['tipo'], $vh_slug ); ?>>
                                <?php echo esc_html( $vh_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Valor do desconto', 'vapor-hub-loja' ); ?></label>
                    <input type="number" step="0.01" min="0" name="valor" value="<?php echo esc_attr( $vh_dados['valor'] ); ?>" required />
                </div>
            </div>

            <div class="vh-form-linha">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Validade', 'vapor-hub-loja' ); ?></label>
                    <input type="date" name="expira" value="<?php echo esc_attr( $vh_dados['expira'] ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Limite de usos', 'vapor-hub-loja' ); ?></label>
                    <input type="number" step="1" min="0" name="limite" value="<?php echo esc_attr( (string) $vh_dados['limite'] ); ?>" />
                </div>
            </div>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Valor mínimo do pedido (R$)', 'vapor-hub-loja' ); ?></label>
                <input type="number" step="0.01" min="0" name="minimo" value="<?php echo esc_attr( (string) $vh_dados['minimo'] ); ?>" />
            </div>

            <div class="vh-form-acoes">
                <button type="submit" class="vh-btn vh-btn--primario">
                    <?php echo $vh_edicao ? esc_html__( 'Salvar cupom', 'vapor-hub-loja' ) : esc_html__( 'Criar cupom', 'vapor-hub-loja' ); ?>
                </button>
            </div>
        </div>
    </form>
</div>

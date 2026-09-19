<?php
/**
 * View — Comunidade: detalhe de um envio.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id   = VH_Router::id_atual();
$vh_item = $vh_id ? VH_Comunidade_Envios_Service::obter( $vh_id, true ) : null;

if ( ! $vh_item ) {
    echo '<div class="vh-admin-wrap">';
    require __DIR__ . '/../partials/subnav.php';
    echo '<div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Envio não encontrado.', 'vapor-hub-loja' ) . '</p><p><a class="vh-btn vh-btn--secundario" href="' .
        esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] ) ) . '">' . esc_html__( 'Voltar para envios', 'vapor-hub-loja' ) . '</a></p></div></div>';
    return;
}

$rotulos_status = [
    'pendente' => __( 'Pendente', 'vapor-hub-loja' ),
    'aprovado' => __( 'Aprovado', 'vapor-hub-loja' ),
    'recusado' => __( 'Recusado', 'vapor-hub-loja' ),
];
$classe_status = [
    'pendente' => 'novo',
    'aprovado' => 'completed',
    'recusado' => 'cancelled',
];
$vh_pode_moderar = 'pendente' === $vh_item['status'];
?>

<div class="vh-admin-wrap">

    <?php require __DIR__ . '/../partials/subnav.php'; ?>

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para envios', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <div class="vh-form-colunas">
        <div>
            <div class="vh-admin-section">
                <h2><?php echo esc_html( $vh_item['nome'] ); ?></h2>
                <p class="vh-form-descricao">
                    <?php
                    printf(
                        esc_html__( 'Enviado em %s', 'vapor-hub-loja' ),
                        esc_html( $vh_item['criado_em'] )
                    );
                    ?>
                </p>

                <?php if ( ! empty( $vh_item['imagem_url'] ) ) : ?>
                    <figure class="vh-comunidade-envio-preview">
                        <img src="<?php echo esc_url( $vh_item['imagem_url'] ); ?>"
                             alt="<?php echo esc_attr( $vh_item['legenda'] ?: $vh_item['nome'] ); ?>"
                             loading="lazy"
                             decoding="async" />
                    </figure>
                <?php endif; ?>

                <dl class="vh-dl-detalhe">
                    <div>
                        <dt><?php esc_html_e( 'Instagram', 'vapor-hub-loja' ); ?></dt>
                        <dd>
                            <a href="<?php echo esc_url( $vh_item['link'] ); ?>" target="_blank" rel="noopener noreferrer">
                                @<?php echo esc_html( $vh_item['instagram'] ); ?>
                            </a>
                        </dd>
                    </div>
                    <?php if ( $vh_item['legenda'] ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'Legenda', 'vapor-hub-loja' ); ?></dt>
                            <dd><?php echo esc_html( $vh_item['legenda'] ); ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></dt>
                        <dd>
                            <span class="vh-status vh-status--<?php echo esc_attr( $classe_status[ $vh_item['status'] ] ?? 'pending' ); ?>">
                                <?php echo esc_html( $rotulos_status[ $vh_item['status'] ] ?? $vh_item['status'] ); ?>
                            </span>
                        </dd>
                    </div>
                    <?php if ( ! empty( $vh_item['revisado_em'] ) ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'Revisado em', 'vapor-hub-loja' ); ?></dt>
                            <dd><?php echo esc_html( $vh_item['revisado_em'] ); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if ( $vh_pode_moderar ) : ?>
                    <div class="vh-comunidade-acoes-moderacao">
                        <button type="button"
                                class="vh-btn vh-btn--primario vh-rest-acao"
                                data-endpoint="comunidade/envios/<?php echo esc_attr( (string) $vh_item['id'] ); ?>/aprovar"
                                data-method="POST"
                                data-redirect="<?php echo esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] ) ); ?>">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e( 'Aprovar e publicar', 'vapor-hub-loja' ); ?>
                        </button>
                        <button type="button"
                                class="vh-btn vh-btn--perigo vh-rest-acao"
                                data-endpoint="comunidade/envios/<?php echo esc_attr( (string) $vh_item['id'] ); ?>/recusar"
                                data-method="POST"
                                data-confirmar="<?php esc_attr_e( 'Recusar este envio? A foto não será exibida na galeria.', 'vapor-hub-loja' ); ?>"
                                data-redirect="<?php echo esc_url( VH_Router::url( 'comunidade', [ 'acao' => 'envios' ] ) ); ?>">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e( 'Recusar', 'vapor-hub-loja' ); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="vh-admin-section">
                <h3><?php esc_html_e( 'Contexto da visita', 'vapor-hub-loja' ); ?></h3>
                <dl class="vh-dl-detalhe">
                    <div>
                        <dt><?php esc_html_e( 'Navegador', 'vapor-hub-loja' ); ?></dt>
                        <dd><?php echo esc_html( $vh_item['navegador'] ); ?></dd>
                    </div>
                    <?php if ( ! empty( $vh_item['ip'] ) ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'Endereço IP', 'vapor-hub-loja' ); ?></dt>
                            <dd><code><?php echo esc_html( $vh_item['ip'] ); ?></code></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $vh_item['referrer'] ) ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'Página de origem', 'vapor-hub-loja' ); ?></dt>
                            <dd><a href="<?php echo esc_url( $vh_item['referrer'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $vh_item['referrer'] ); ?></a></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $vh_item['user_agent'] ) ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'User-Agent', 'vapor-hub-loja' ); ?></dt>
                            <dd><small><?php echo esc_html( $vh_item['user_agent'] ); ?></small></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>

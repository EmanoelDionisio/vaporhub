<?php
/**
 * View — Revenda: detalhe de um cadastro.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id   = VH_Router::id_atual();
$vh_item = $vh_id ? VH_Revenda_Leads_Service::obter( $vh_id, true ) : null;

if ( ! $vh_item ) {
    echo '<div class="vh-admin-wrap">';
    require __DIR__ . '/partials/subnav.php';
    echo '<div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Cadastro não encontrado.', 'vapor-hub-loja' ) . '</p><p><a class="vh-btn vh-btn--secundario" href="' .
        esc_url( VH_Router::url( 'revenda' ) ) . '">' . esc_html__( 'Voltar para cadastros', 'vapor-hub-loja' ) . '</a></p></div></div>';
    return;
}
?>

<div class="vh-admin-wrap">

    <?php require __DIR__ . '/partials/subnav.php'; ?>

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'revenda' ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para cadastros', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <div class="vh-form-colunas">
        <div>
            <div class="vh-admin-section">
                <h2><?php echo esc_html( $vh_item['nome'] ); ?></h2>
                <p class="vh-form-descricao">
                    <?php
                    printf(
                        esc_html__( 'Recebido em %s', 'vapor-hub-loja' ),
                        esc_html( $vh_item['criado_em'] )
                    );
                    ?>
                </p>

                <dl class="vh-dl-detalhe">
                    <div>
                        <dt><?php esc_html_e( 'E-mail', 'vapor-hub-loja' ); ?></dt>
                        <dd><a href="mailto:<?php echo esc_attr( $vh_item['email'] ); ?>"><?php echo esc_html( $vh_item['email'] ); ?></a></dd>
                    </div>
                    <?php if ( $vh_item['whatsapp'] ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'WhatsApp', 'vapor-hub-loja' ); ?></dt>
                            <dd>
                                <a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/\D/', '', $vh_item['whatsapp'] ) ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html( $vh_item['whatsapp'] ); ?>
                                </a>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if ( $vh_item['documento'] ) : ?>
                        <div>
                            <dt><?php esc_html_e( 'CPF/CNPJ', 'vapor-hub-loja' ); ?></dt>
                            <dd><?php echo esc_html( $vh_item['documento'] ); ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></dt>
                        <dd>
                            <span class="vh-status vh-status--<?php echo esc_attr( $vh_item['status'] ); ?>">
                                <?php echo esc_html( 'novo' === $vh_item['status'] ? __( 'Novo', 'vapor-hub-loja' ) : __( 'Lido', 'vapor-hub-loja' ) ); ?>
                            </span>
                        </dd>
                    </div>
                </dl>
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

            <div class="vh-admin-section vh-revenda-futuro">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <p>
                    <?php if ( ! empty( $vh_item['email_enviado'] ) ) : ?>
                        <?php esc_html_e( 'Cópia enviada por e-mail para a loja.', 'vapor-hub-loja' ); ?>
                    <?php else : ?>
                        <?php esc_html_e( 'E-mail à loja não enviado (desativado ou falha SMTP).', 'vapor-hub-loja' ); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

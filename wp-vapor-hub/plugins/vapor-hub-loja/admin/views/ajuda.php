<?php
/**
 * View — Ajuda (guia rápido, links úteis, suporte).
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="vh-admin-wrap">

    <!-- Guia Rápido -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Guia Rápido', 'vapor-hub-loja' ); ?></h2>

        <div class="vh-ajuda-grid">

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e( 'Como acessar o painel', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Gestores da loja entram em /minha-loja/entrar com usuário e senha próprios.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'O wp-admin e wp-login.php são reservados a administradores do sistema.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Para sair, use o link "Sair" na barra lateral do painel.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Em Segurança, é possível ativar Cloudflare Turnstile no login.', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e( 'Como trocar o logo', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Acesse Minha Loja > Aparência.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Vá até "Identidade Visual e Cards da Loja".', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Use "Selecionar Logo" para enviar o novo logo.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'No campo "Favicon", envie um ícone quadrado (512×512) para a aba do navegador.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Clique em "Salvar Aparência".', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-cart"></span>
                    <?php esc_html_e( 'Como adicionar um produto', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Vá em Minha Loja > Produtos > Novo produto.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Preencha nome, descrição e preço.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Adicione a imagem principal e a galeria.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Selecione as categorias e os destaques.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Defina o status como "Publicado" e salve.', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Como ver pedidos', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Acesse Minha Loja > Pedidos.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Use os filtros por status, data ou busca.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Clique em "Abrir" para ver os detalhes.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Atualize status, rastreio e notas pela própria tela.', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-admin-customizer"></span>
                    <?php esc_html_e( 'Como editar banners', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Acesse Minha Loja > Aparência.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Edite os textos do Hero (banner principal).', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Para carrossel: ligue "Usar carrossel de slides" e envie até 3 imagens (1920×800).', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Salve e visualize no site.', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <?php esc_html_e( 'Barra de Benefícios', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Acesse Minha Loja > Aparência.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Edite os 4 benefícios (ícone, título e descrição).', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Salve e os benefícios serão atualizados automaticamente.', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

            <div class="vh-ajuda-card">
                <h3>
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e( 'Galeria da Comunidade', 'vapor-hub-loja' ); ?>
                </h3>
                <ol>
                    <li><?php esc_html_e( 'Acesse Minha Loja > Comunidade.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Adicione até 6 fotos de clientes.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Preencha o @usuario e link do perfil.', 'vapor-hub-loja' ); ?></li>
                    <li><?php esc_html_e( 'Salve para atualizar a galeria.', 'vapor-hub-loja' ); ?></li>
                </ol>
            </div>

        </div>
    </div>

    <!-- Shortcodes Disponíveis -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Shortcodes Disponíveis', 'vapor-hub-loja' ); ?></h2>
        <p style="color:var(--vh-cinza-500);margin-bottom:16px;font-size:13px">
            <?php esc_html_e( 'Cole estes shortcodes em qualquer página ou widget do Elementor para exibir o conteúdo dinâmico.', 'vapor-hub-loja' ); ?>
        </p>
        <table class="vh-admin-tabela">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Shortcode', 'vapor-hub-loja' ); ?></th>
                    <th><?php esc_html_e( 'Descrição', 'vapor-hub-loja' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[vh_beneficios]</code></td>
                    <td><?php esc_html_e( 'Barra com 4 benefícios da loja (ícone + título + descrição)', 'vapor-hub-loja' ); ?></td>
                </tr>
                <tr>
                    <td><code>[vh_comunidade]</code></td>
                    <td><?php esc_html_e( 'Galeria horizontal com fotos da comunidade', 'vapor-hub-loja' ); ?></td>
                </tr>
                <tr>
                    <td><code>[vh_revenda_cta]</code></td>
                    <td><?php esc_html_e( 'Seção de chamada para revenda com botão WhatsApp', 'vapor-hub-loja' ); ?></td>
                </tr>
                <tr>
                    <td><code>[vh_categorias]</code></td>
                    <td><?php esc_html_e( 'Grid de categorias WooCommerce com imagens', 'vapor-hub-loja' ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Links Úteis -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Links Úteis', 'vapor-hub-loja' ); ?></h2>

        <p style="color:var(--vh-cinza-500);margin-bottom:16px;font-size:13px">
            <?php esc_html_e( 'Atalhos para as áreas mais usadas do painel.', 'vapor-hub-loja' ); ?>
        </p>
        <div class="vh-links-uteis">
            <a href="<?php echo esc_url( VH_Router::url( 'produtos', [ 'acao' => 'novo' ] ) ); ?>">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e( 'Adicionar produto', 'vapor-hub-loja' ); ?>
            </a>
            <a href="<?php echo esc_url( VH_Router::url( 'pedidos' ) ); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'Ver pedidos', 'vapor-hub-loja' ); ?>
            </a>
            <a href="<?php echo esc_url( VH_Router::url( 'cupons', [ 'acao' => 'novo' ] ) ); ?>">
                <span class="dashicons dashicons-tickets-alt"></span>
                <?php esc_html_e( 'Criar cupom', 'vapor-hub-loja' ); ?>
            </a>
            <a href="<?php echo esc_url( VH_Router::url( 'aparencia' ) ); ?>">
                <span class="dashicons dashicons-admin-appearance"></span>
                <?php esc_html_e( 'Banners e aparência', 'vapor-hub-loja' ); ?>
            </a>
            <a href="<?php echo esc_url( VH_Router::url( 'relatorios' ) ); ?>">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e( 'Relatórios da Loja', 'vapor-hub-loja' ); ?>
            </a>
        </div>
    </div>

    <!-- Suporte Técnico -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Suporte Técnico', 'vapor-hub-loja' ); ?></h2>
        <p style="color:var(--vh-cinza-500);font-size:14px;line-height:1.7">
            <?php esc_html_e( 'Precisa de ajuda técnica? Nossa equipe está pronta para atender você.', 'vapor-hub-loja' ); ?>
        </p>
        <p style="margin-top:12px">
            <a href="https://newalliance.tech" target="_blank" rel="noopener noreferrer" class="vh-btn vh-btn--primario">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e( 'Falar com Suporte', 'vapor-hub-loja' ); ?>
            </a>
        </p>
    </div>

    <!-- Vídeo Tutorial -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Vídeo Tutorial', 'vapor-hub-loja' ); ?></h2>
        <div class="vh-video-placeholder">
            <span class="dashicons dashicons-video-alt3"></span>
            <p><?php esc_html_e( 'Em breve teremos um vídeo tutorial completo sobre como gerenciar sua loja.', 'vapor-hub-loja' ); ?></p>
            <p style="font-size:12px"><?php esc_html_e( 'Fique de olho nas atualizações!', 'vapor-hub-loja' ); ?></p>
        </div>
    </div>

</div>

<?php
/**
 * View — SEO e Métricas.
 *
 * Fase 1: verificação de propriedade (Search Console/Bing), tags de medição
 * (GA4, GTM, Meta Pixel) com consentimento, metadados padrão e controle de
 * Schema. As conexões automáticas via OAuth (ler dados no painel) chegam na
 * Fase 2, isoladas em includes/integrations/.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$seo = VH_Settings::obter( 'vh_seo', VH_Settings::seo_padrao() );
$google = VH_SEO_Google::status_publico();
$redirect_uri = VH_SEO_Google::redirect_uri();

/* Diagnóstico rápido — apenas leitura, sem custo no site público. */
$sitemap_url = home_url( '/wp-sitemap.xml' );
$robots_url  = home_url( '/robots.txt' );
$home_url    = home_url( '/' );
?>

<div class="vh-admin-wrap">

    <form id="vh-form-seo" class="vh-rest-form" data-endpoint="settings/seo" data-method="PUT">

        <!-- ═══ Verificação de propriedade ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Verificação de Propriedade', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Cole o código (ou a meta tag completa) fornecido por cada ferramenta para confirmar que o site é seu. Esse é o primeiro passo para conectar o Search Console. As tags são inseridas automaticamente no cabeçalho da página inicial.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-form-grupo">
                <label for="vh-seo-gsc"><?php esc_html_e( 'Google Search Console', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-gsc" name="vh_seo[google_site_verification]"
                       value="<?php echo esc_attr( $seo['google_site_verification'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Ex.: aBc123... (conteúdo da meta tag)', 'vapor-hub-loja' ); ?>"
                       autocomplete="off" />
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Em search.google.com/search-console escolha "Tag HTML" e cole aqui.', 'vapor-hub-loja' ); ?>
                </p>
            </div>

            <div class="vh-form-grupo">
                <label for="vh-seo-bing"><?php esc_html_e( 'Bing Webmaster Tools', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-bing" name="vh_seo[bing_site_verification]"
                       value="<?php echo esc_attr( $seo['bing_site_verification'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Conteúdo da meta tag do Bing', 'vapor-hub-loja' ); ?>"
                       autocomplete="off" />
            </div>
        </div>

        <!-- ═══ Medição e Rastreamento ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Medição e Rastreamento', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Informe apenas os IDs públicos. Os scripts carregam de forma assíncrona e respeitam o consentimento de cookies, sem prejudicar o desempenho do site.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-form-grupo">
                <label for="vh-seo-ga4"><?php esc_html_e( 'Google Analytics 4 — ID de medição', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-ga4" name="vh_seo[ga4_id]"
                       value="<?php echo esc_attr( $seo['ga4_id'] ?? '' ); ?>"
                       placeholder="G-XXXXXXXXXX" autocomplete="off" />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-seo-gtm"><?php esc_html_e( 'Google Tag Manager — ID do contêiner', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-gtm" name="vh_seo[gtm_id]"
                       value="<?php echo esc_attr( $seo['gtm_id'] ?? '' ); ?>"
                       placeholder="GTM-XXXXXXX" autocomplete="off" />
                <p class="vh-form-descricao">
                    <?php esc_html_e( 'Se você usa o GTM, recomendamos gerenciar GA4 e Pixel por ele e deixar os outros campos vazios.', 'vapor-hub-loja' ); ?>
                </p>
            </div>

            <div class="vh-form-grupo">
                <label for="vh-seo-pixel"><?php esc_html_e( 'Meta Pixel (Facebook/Instagram) — ID', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-pixel" name="vh_seo[meta_pixel_id]"
                       value="<?php echo esc_attr( $seo['meta_pixel_id'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Somente números', 'vapor-hub-loja' ); ?>" autocomplete="off" />
            </div>
        </div>

        <!-- ═══ Consentimento (LGPD) ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Consentimento de Cookies', 'vapor-hub-loja' ); ?></h2>

            <label class="vh-switch-linha">
                <input type="hidden" name="vh_seo[consent_mode]" value="0" />
                <input type="checkbox" class="vh-toggle" id="vh-seo-consent"
                       name="vh_seo[consent_mode]" value="1" <?php checked( $seo['consent_mode'] ?? '1', '1' ); ?> />
                <span class="vh-switch-texto">
                    <strong><?php esc_html_e( 'Exibir banner e exigir consentimento', 'vapor-hub-loja' ); ?></strong>
                    <small><?php esc_html_e( 'Usa o Consent Mode v2 do Google: a medição só é liberada após o visitante aceitar. Recomendado para conformidade com a LGPD.', 'vapor-hub-loja' ); ?></small>
                </span>
            </label>

            <div id="vh-seo-consent-campos" class="vh-seo-consent-campos">
                <div class="vh-form-grupo" style="margin-top:16px">
                    <label for="vh-seo-consent-texto"><?php esc_html_e( 'Texto do banner', 'vapor-hub-loja' ); ?></label>
                    <input type="text" id="vh-seo-consent-texto" name="vh_seo[consent_banner_texto]"
                           value="<?php echo esc_attr( $seo['consent_banner_texto'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Usamos cookies para melhorar sua experiência e medir o tráfego.', 'vapor-hub-loja' ); ?>" />
                </div>
            </div>
        </div>

        <!-- ═══ Metadados padrão ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Metadados Padrão', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Usados quando uma página não tem descrição ou imagem própria (especialmente a página inicial e o compartilhamento em redes sociais).', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-form-grupo">
                <label for="vh-seo-desc"><?php esc_html_e( 'Descrição padrão (meta description)', 'vapor-hub-loja' ); ?></label>
                <textarea id="vh-seo-desc" name="vh_seo[meta_description]" rows="2"
                          maxlength="220"
                          placeholder="<?php esc_attr_e( 'Pods, e-líquidos e acessórios com PIX e envio para o Brasil.', 'vapor-hub-loja' ); ?>"><?php echo esc_textarea( $seo['meta_description'] ?? '' ); ?></textarea>
                <p class="vh-form-descricao"><?php esc_html_e( 'Ideal entre 120 e 160 caracteres.', 'vapor-hub-loja' ); ?></p>
            </div>

            <div class="vh-form-grupo">
                <label for="vh-seo-og"><?php esc_html_e( 'Imagem de compartilhamento padrão (URL)', 'vapor-hub-loja' ); ?></label>
                <input type="url" id="vh-seo-og" name="vh_seo[og_image]"
                       value="<?php echo esc_attr( $seo['og_image'] ?? '' ); ?>"
                       placeholder="https://..." />
                <p class="vh-form-descricao"><?php esc_html_e( 'Recomendado 1200×630px. Sem valor, usamos o logotipo configurado em Aparência.', 'vapor-hub-loja' ); ?></p>
            </div>
        </div>

        <!-- ═══ Controle de Schema ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Dados Estruturados (Schema.org)', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Controle quais marcações o site emite para o Google. Desligue uma opção apenas se outra ferramenta já gerar o mesmo dado, para evitar duplicidade.', 'vapor-hub-loja' ); ?>
            </p>

            <label class="vh-switch-linha">
                <input type="hidden" name="vh_seo[schema_organization]" value="0" />
                <input type="checkbox" class="vh-toggle" name="vh_seo[schema_organization]" value="1" <?php checked( $seo['schema_organization'] ?? '1', '1' ); ?> />
                <span class="vh-switch-texto">
                    <strong><?php esc_html_e( 'Organização', 'vapor-hub-loja' ); ?></strong>
                    <small><?php esc_html_e( 'Nome, logotipo e redes sociais da marca (página inicial).', 'vapor-hub-loja' ); ?></small>
                </span>
            </label>

            <label class="vh-switch-linha">
                <input type="hidden" name="vh_seo[schema_website]" value="0" />
                <input type="checkbox" class="vh-toggle" name="vh_seo[schema_website]" value="1" <?php checked( $seo['schema_website'] ?? '1', '1' ); ?> />
                <span class="vh-switch-texto">
                    <strong><?php esc_html_e( 'Site e caixa de busca', 'vapor-hub-loja' ); ?></strong>
                    <small><?php esc_html_e( 'Permite a caixa de pesquisa do site nos resultados do Google.', 'vapor-hub-loja' ); ?></small>
                </span>
            </label>

            <label class="vh-switch-linha">
                <input type="hidden" name="vh_seo[schema_breadcrumb]" value="0" />
                <input type="checkbox" class="vh-toggle" name="vh_seo[schema_breadcrumb]" value="1" <?php checked( $seo['schema_breadcrumb'] ?? '1', '1' ); ?> />
                <span class="vh-switch-texto">
                    <strong><?php esc_html_e( 'Trilha de navegação (breadcrumb)', 'vapor-hub-loja' ); ?></strong>
                    <small><?php esc_html_e( 'Caminho Início › Loja › Categoria › Produto nos resultados.', 'vapor-hub-loja' ); ?></small>
                </span>
            </label>

            <label class="vh-switch-linha">
                <input type="hidden" name="vh_seo[schema_product]" value="0" />
                <input type="checkbox" class="vh-toggle" name="vh_seo[schema_product]" value="1" <?php checked( $seo['schema_product'] ?? '1', '1' ); ?> />
                <span class="vh-switch-texto">
                    <strong><?php esc_html_e( 'Produtos (WooCommerce)', 'vapor-hub-loja' ); ?></strong>
                    <small><?php esc_html_e( 'Preço, disponibilidade e avaliações na página de produto. Mantenha ligado salvo conflito com outro plugin.', 'vapor-hub-loja' ); ?></small>
                </span>
            </label>
        </div>

        <!-- ═══ Google Cloud + OAuth ═══ -->
        <div class="vh-admin-section" id="vh-seo-google-section">
            <h2><?php esc_html_e( 'Conexão Google (Search Console + Analytics)', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Crie um app OAuth no Google Cloud Console, cole as credenciais abaixo, salve e clique em Conectar. Um único login autoriza Search Console e Analytics ao mesmo tempo.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-form-grupo">
                <label for="vh-seo-gclient-id"><?php esc_html_e( 'Client ID (OAuth 2.0)', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-gclient-id" name="vh_seo[google_client_id]"
                       value="<?php echo esc_attr( $seo['google_client_id'] ?? $google['client_id'] ?? '' ); ?>"
                       placeholder="123456789.apps.googleusercontent.com" autocomplete="off" />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-seo-gclient-secret"><?php esc_html_e( 'Client Secret', 'vapor-hub-loja' ); ?></label>
                <input type="password" id="vh-seo-gclient-secret" name="vh_seo[google_client_secret]"
                       value="" placeholder="<?php echo esc_attr( $google['credenciais_ok'] ? '•••••••• (salvo — deixe vazio para manter)' : 'Cole o secret do Google Cloud' ); ?>"
                       autocomplete="new-password" />
            </div>

            <div class="vh-form-grupo">
                <label for="vh-seo-ga4-property"><?php esc_html_e( 'ID da propriedade GA4 (numérico)', 'vapor-hub-loja' ); ?></label>
                <input type="text" id="vh-seo-ga4-property" name="vh_seo[ga4_property_id]"
                       value="<?php echo esc_attr( $seo['ga4_property_id'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Ex.: 123456789', 'vapor-hub-loja' ); ?>" autocomplete="off" />
                <p class="vh-form-descricao"><?php esc_html_e( 'Analytics → Admin → Detalhes da propriedade → ID da propriedade.', 'vapor-hub-loja' ); ?></p>
            </div>

            <div class="vh-form-grupo vh-seo-redirect-uri">
                <label><?php esc_html_e( 'URI de redirecionamento (copie no Google Cloud)', 'vapor-hub-loja' ); ?></label>
                <code class="vh-seo-code"><?php echo esc_html( $redirect_uri ); ?></code>
            </div>

            <div class="vh-seo-google-acoes">
                <span id="vh-seo-google-badge" class="vh-seo-badge"><?php echo $google['conectado'] ? esc_html__( 'Conectado', 'vapor-hub-loja' ) : esc_html__( 'Desconectado', 'vapor-hub-loja' ); ?></span>
                <span id="vh-seo-google-email" class="vh-seo-google-email"><?php echo esc_html( $google['email'] ?? '' ); ?></span>
                <button type="button" id="vh-seo-google-connect" class="vh-btn vh-btn--primario" <?php echo $google['conectado'] ? 'hidden' : ''; ?>>
                    <?php esc_html_e( 'Conectar com Google', 'vapor-hub-loja' ); ?>
                </button>
                <button type="button" id="vh-seo-google-disconnect" class="vh-btn vh-btn--secundario" <?php echo $google['conectado'] ? '' : 'hidden'; ?>>
                    <?php esc_html_e( 'Desconectar', 'vapor-hub-loja' ); ?>
                </button>
            </div>
            <p class="vh-form-descricao"><?php esc_html_e( 'Salve Client ID e Secret antes de conectar. O fluxo abre a tela de login do Google na mesma aba.', 'vapor-hub-loja' ); ?></p>
        </div>

        <!-- ═══ PageSpeed / Core Web Vitals ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Core Web Vitals (PageSpeed)', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Análise sob demanda da página inicial (mobile). Usa API key simples — não exige OAuth. Resultado em cache por 24h.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-form-grupo">
                <label for="vh-seo-psi-key"><?php esc_html_e( 'Chave API PageSpeed Insights', 'vapor-hub-loja' ); ?></label>
                <input type="password" id="vh-seo-psi-key" name="vh_seo[pagespeed_api_key]"
                       value="" placeholder="<?php echo esc_attr( $google['pagespeed_key_ok'] ? '•••••••• (salva)' : 'AIza...' ); ?>"
                       autocomplete="new-password" />
            </div>
        </div>

        <p style="margin-top:20px">
            <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar SEO e Métricas', 'vapor-hub-loja' ); ?></button>
        </p>
    </form>

    <!-- Métricas (fora do form — carregadas via REST após OAuth) -->
    <div class="vh-admin-section" id="vh-seo-metricas-wrap" <?php echo $google['conectado'] ? '' : 'hidden'; ?>>
        <h2><?php esc_html_e( 'Painel de Métricas', 'vapor-hub-loja' ); ?></h2>
        <p class="vh-form-descricao"><?php esc_html_e( 'Dados cacheados por 1 hora. Atualize a página para forçar nova consulta.', 'vapor-hub-loja' ); ?></p>
        <div id="vh-seo-metricas-grid" class="vh-seo-metricas-grid"></div>
    </div>

    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Core Web Vitals — resultado', 'vapor-hub-loja' ); ?></h2>
        <p style="margin-bottom:14px">
            <button type="button" id="vh-seo-pagespeed-run" class="vh-btn vh-btn--secundario">
                <?php esc_html_e( 'Analisar página inicial (mobile)', 'vapor-hub-loja' ); ?>
            </button>
        </p>
        <div id="vh-seo-pagespeed-result"></div>
    </div>

    <!-- ═══ Diagnóstico ═══ -->
    <div class="vh-admin-section">
        <h2><?php esc_html_e( 'Diagnóstico', 'vapor-hub-loja' ); ?></h2>
        <ul class="vh-seo-diagnostico">
            <li>
                <span class="dashicons dashicons-media-code"></span>
                <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Sitemap XML', 'vapor-hub-loja' ); ?></a>
                <small><?php esc_html_e( 'Envie este endereço no Search Console.', 'vapor-hub-loja' ); ?></small>
            </li>
            <li>
                <span class="dashicons dashicons-text-page"></span>
                <a href="<?php echo esc_url( $robots_url ); ?>" target="_blank" rel="noopener">robots.txt</a>
                <small><?php esc_html_e( 'Regras de rastreamento (Minha Loja e checkout bloqueados).', 'vapor-hub-loja' ); ?></small>
            </li>
            <li>
                <span class="dashicons dashicons-admin-home"></span>
                <a href="<?php echo esc_url( $home_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Página inicial', 'vapor-hub-loja' ); ?></a>
                <small><?php esc_html_e( 'Onde as tags de verificação são inseridas.', 'vapor-hub-loja' ); ?></small>
            </li>
        </ul>
    </div>
</div>

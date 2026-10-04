<?php
/**
 * View — Aparência. Ordem do banner: Slides (toggle principal) → Conteúdo Hero → Benefícios → Rodapé → Identidade.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$beneficios = VH_Settings::obter( 'vh_beneficios', VH_Settings::beneficios_padrao() );
$hero       = VH_Settings::obter( 'vh_hero' );
$rodape     = VH_Settings::obter( 'vh_rodape' );
$identidade = VH_Settings::obter( 'vh_identidade_visual', VH_Settings::identidade_visual_padrao() );
$usar_slides = isset( $hero['usar_slides'] ) ? (string) $hero['usar_slides'] : '0';
$slides_nav  = isset( $hero['slides_navegacao'] ) ? (string) $hero['slides_navegacao'] : 'dots';
if ( ! in_array( $slides_nav, [ 'dots', 'setas', 'ambos', 'nenhum' ], true ) ) {
    $slides_nav = 'dots';
}

$icones_disponiveis = VH_Settings::icones_beneficio_disponiveis();
$total_beneficios   = max( 1, count( $beneficios ) );
?>

<div class="vh-admin-wrap">

    <form class="vh-rest-form" id="vh-form-aparencia" data-endpoint="settings/aparencia" data-method="PUT">

        <!-- ═══ Slides do Hero (toggle principal — primeira seção) ═══ -->
        <div class="vh-admin-section" id="vh-hero-slides-section">
            <h2><?php esc_html_e( 'Banner da Home — Slides', 'vapor-hub-loja' ); ?></h2>
            <p style="color:var(--vh-cinza-500);margin-bottom:20px;font-size:13px">
                <?php esc_html_e( 'Escolha como exibir o banner principal: carrossel de imagens ou imagem única com textos (seção abaixo).', 'vapor-hub-loja' ); ?>
            </p>

            <label class="vh-switch-linha">
                <input type="hidden" name="vh_hero[usar_slides]" value="0" />
                <input type="checkbox" class="vh-toggle" id="vh-usar-slides"
                       name="vh_hero[usar_slides]" value="1" <?php checked( $usar_slides, '1' ); ?> />
                <span class="vh-switch-texto">
                    <strong><?php esc_html_e( 'Usar carrossel de slides', 'vapor-hub-loja' ); ?></strong>
                    <small><?php esc_html_e( 'Desligado: usa a seção "Conteúdo Hero" (imagem única + textos). Ligado: usa apenas os slides abaixo.', 'vapor-hub-loja' ); ?></small>
                </span>
            </label>

            <div id="vh-slides-wrapper">
                <p style="color:var(--vh-cinza-500);margin:18px 0 16px;font-size:13px">
                    <?php esc_html_e( 'Cada slide tem uma imagem para computador e, se quiser, uma imagem feita para celular. Sem a versão de celular, usamos a mesma imagem do computador. Recomendamos no máximo 3 slides. As imagens são convertidas automaticamente para WebP otimizado. Arraste pelo ícone ≡ para definir a ordem na home (1º, 2º, 3º…).', 'vapor-hub-loja' ); ?>
                </p>

                <div class="vh-form-grupo vh-slides-nav-config">
                    <label><?php esc_html_e( 'Navegação do carrossel', 'vapor-hub-loja' ); ?></label>
                    <p class="vh-form-descricao">
                        <?php esc_html_e( 'Com 2 ou mais slides, escolha como o visitante passa de um banner para outro na home.', 'vapor-hub-loja' ); ?>
                    </p>
                    <div class="vh-segmentos" role="radiogroup" aria-label="<?php esc_attr_e( 'Navegação do carrossel', 'vapor-hub-loja' ); ?>">
                        <?php
                        $nav_opcoes = [
                            'dots'    => __( 'Pontinhos', 'vapor-hub-loja' ),
                            'setas'   => __( 'Setas', 'vapor-hub-loja' ),
                            'ambos'   => __( 'Setas e pontinhos', 'vapor-hub-loja' ),
                            'nenhum'  => __( 'Automático (sem controles)', 'vapor-hub-loja' ),
                        ];
                        foreach ( $nav_opcoes as $nav_val => $nav_label ) :
                        ?>
                            <label class="vh-segmento">
                                <input type="radio" name="vh_hero[slides_navegacao]" value="<?php echo esc_attr( $nav_val ); ?>"
                                    <?php checked( $slides_nav, $nav_val ); ?> />
                                <span><?php echo esc_html( $nav_label ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php
            $slides       = ( ! empty( $hero['slides'] ) && is_array( $hero['slides'] ) ) ? $hero['slides'] : [];
            $total_slides = max( 1, count( $slides ) );
            ?>
            <div id="vh-slides-container" class="vh-slides-container">
                <?php for ( $s = 0; $s < $total_slides; $s++ ) :
                    $slide_url    = $slides[ $s ]['url'] ?? '';
                    $slide_mobile = $slides[ $s ]['url_mobile'] ?? '';
                ?>
                    <div class="vh-slide-item" data-indice="<?php echo esc_attr( $s ); ?>">
                        <div class="vh-slide-ordem-col">
                            <span class="vh-slide-drag-handle" role="button" tabindex="0"
                                  title="<?php esc_attr_e( 'Arrastar para reordenar', 'vapor-hub-loja' ); ?>"
                                  aria-label="<?php esc_attr_e( 'Arrastar slide para reordenar', 'vapor-hub-loja' ); ?>">
                                <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                            </span>
                            <span class="vh-slide-ordem-num"><?php echo esc_html( (string) ( $s + 1 ) ); ?></span>
                        </div>
                        <div class="vh-slide-campos">
                            <div class="vh-slide-campo vh-slide-campo--desktop">
                                <span class="vh-slide-rotulo">
                                    <span class="dashicons dashicons-desktop"></span>
                                    <?php esc_html_e( 'Computador', 'vapor-hub-loja' ); ?>
                                    <small><?php esc_html_e( '1920 × 800', 'vapor-hub-loja' ); ?></small>
                                </span>
                                <div class="vh-slide-thumb vh-upload-btn" data-size="full"
                                     data-crop="hero-desktop"
                                     data-value-type="url"
                                     data-target="#vh-slide-img-<?php echo esc_attr( $s ); ?>"
                                     data-preview="#vh-slide-preview-<?php echo esc_attr( $s ); ?>">
                                    <span id="vh-slide-preview-<?php echo esc_attr( $s ); ?>">
                                        <?php if ( $slide_url ) : ?>
                                            <img src="<?php echo esc_url( $slide_url ); ?>" alt="" />
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image"></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <input type="hidden" name="vh_hero[slides][<?php echo esc_attr( $s ); ?>][url]"
                                       id="vh-slide-img-<?php echo esc_attr( $s ); ?>" value="<?php echo esc_attr( $slide_url ); ?>" />
                            </div>

                            <div class="vh-slide-campo vh-slide-campo--mobile">
                                <span class="vh-slide-rotulo">
                                    <span class="dashicons dashicons-smartphone"></span>
                                    <?php esc_html_e( 'Celular', 'vapor-hub-loja' ); ?>
                                    <small><?php esc_html_e( 'opcional · 1080 × 1350', 'vapor-hub-loja' ); ?></small>
                                </span>
                                <div class="vh-slide-thumb vh-slide-thumb--mobile vh-upload-btn" data-size="full"
                                     data-crop="hero-mobile"
                                     data-value-type="url"
                                     data-target="#vh-slide-img-m-<?php echo esc_attr( $s ); ?>"
                                     data-preview="#vh-slide-preview-m-<?php echo esc_attr( $s ); ?>">
                                    <span id="vh-slide-preview-m-<?php echo esc_attr( $s ); ?>">
                                        <?php if ( $slide_mobile ) : ?>
                                            <img src="<?php echo esc_url( $slide_mobile ); ?>" alt="" />
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image"></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <input type="hidden" name="vh_hero[slides][<?php echo esc_attr( $s ); ?>][url_mobile]"
                                       id="vh-slide-img-m-<?php echo esc_attr( $s ); ?>" value="<?php echo esc_attr( $slide_mobile ); ?>" />
                            </div>
                        </div>

                        <button type="button" class="vh-btn vh-btn--perigo vh-slide-remover"
                                title="<?php esc_attr_e( 'Remover slide', 'vapor-hub-loja' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e( 'Remover slide', 'vapor-hub-loja' ); ?>
                        </button>
                    </div>
                <?php endfor; ?>
            </div>

            <div style="margin-top:16px;display:flex;gap:12px;align-items:center">
                <button type="button" id="vh-slides-adicionar" class="vh-btn vh-btn--secundario">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Adicionar Slide', 'vapor-hub-loja' ); ?>
                </button>
                <span style="color:var(--vh-cinza-500);font-size:12px" id="vh-slides-contagem"></span>
            </div>
            </div><!-- /#vh-slides-wrapper -->
        </div>

        <!-- ═══ Conteúdo Hero (imagem única + textos — oculto quando carrossel ativo) ═══ -->
        <div class="vh-admin-section" id="vh-hero-conteudo-section">
            <h2><?php esc_html_e( 'Conteúdo Hero (Banner Principal)', 'vapor-hub-loja' ); ?></h2>
            <p style="color:var(--vh-cinza-500);margin-bottom:20px;font-size:13px">
                <?php esc_html_e( 'Textos e imagem de fundo única. Disponível quando o carrossel de slides está desligado.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-hero-grid">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Texto do Badge', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="vh_hero[badge_texto]"
                           value="<?php echo esc_attr( $hero['badge_texto'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Ex: Novidade!', 'vapor-hub-loja' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Título Principal', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="vh_hero[titulo]"
                           value="<?php echo esc_attr( $hero['titulo'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Ex: A REVOLUÇÃO NA SUA PESCARIA|SUA PESCARIA', 'vapor-hub-loja' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Use o caractere | para separar a primeira linha da linha em destaque (laranja) no banner.', 'vapor-hub-loja' ); ?></p>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Subtítulo', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="vh_hero[subtitulo]"
                           value="<?php echo esc_attr( $hero['subtitulo'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Ex: As melhores camisetas personalizadas do Brasil', 'vapor-hub-loja' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Texto do Botão Principal', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="vh_hero[botao_texto]"
                           value="<?php echo esc_attr( $hero['botao_texto'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Ex: Ver Coleção', 'vapor-hub-loja' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Link do Botão Principal', 'vapor-hub-loja' ); ?></label>
                    <input type="url" name="vh_hero[botao_link]"
                           value="<?php echo esc_attr( $hero['botao_link'] ?? '' ); ?>"
                           placeholder="https://" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Texto do Botão Secundário', 'vapor-hub-loja' ); ?></label>
                    <input type="text" name="vh_hero[botao_secundario_texto]"
                           value="<?php echo esc_attr( $hero['botao_secundario_texto'] ?? '' ); ?>"
                           placeholder="<?php esc_attr_e( 'Ex: Saiba Mais', 'vapor-hub-loja' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Link do Botão Secundário', 'vapor-hub-loja' ); ?></label>
                    <input type="url" name="vh_hero[botao_secundario_link]"
                           value="<?php echo esc_attr( $hero['botao_secundario_link'] ?? '' ); ?>"
                           placeholder="https://" />
                </div>
                <div class="vh-form-grupo" id="vh-hero-imagem-wrapper">
                    <label><?php esc_html_e( 'Imagem de Fundo', 'vapor-hub-loja' ); ?></label>
                    <p class="description" style="margin:0 0 8px"><?php esc_html_e( 'Tamanho recomendado: 1920 × 800 px (paisagem). Ocupa toda a largura da tela e se adapta automaticamente.', 'vapor-hub-loja' ); ?></p>
                    <div class="vh-upload-wrapper">
                        <div class="vh-upload-preview" id="vh-hero-preview">
                            <?php if ( ! empty( $hero['imagem_fundo'] ) ) : ?>
                                <img src="<?php echo esc_url( $hero['imagem_fundo'] ); ?>" alt="" />
                            <?php else : ?>
                                <span class="dashicons dashicons-format-image"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="vh_hero[imagem_fundo]" id="vh-hero-imagem"
                               value="<?php echo esc_attr( $hero['imagem_fundo'] ?? '' ); ?>" />
                        <button type="button" class="vh-btn vh-btn--secundario vh-upload-btn"
                                data-crop="hero-desktop"
                                data-value-type="url"
                                data-target="#vh-hero-imagem" data-preview="#vh-hero-preview">
                            <?php esc_html_e( 'Enviar imagem', 'vapor-hub-loja' ); ?>
                        </button>
                        <button type="button" class="vh-btn vh-btn--ghost vh-upload-btn"
                                data-target="#vh-hero-imagem" data-preview="#vh-hero-preview">
                            <?php esc_html_e( 'Biblioteca', 'vapor-hub-loja' ); ?>
                        </button>
                        <?php if ( ! empty( $hero['imagem_fundo'] ) ) : ?>
                            <button type="button" class="vh-btn vh-btn--ghost vh-upload-limpar"
                                    data-target="#vh-hero-imagem" data-preview="#vh-hero-preview">
                                <?php esc_html_e( 'Remover', 'vapor-hub-loja' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div><!-- /#vh-hero-conteudo-section -->

        <!-- ═══ Barra de Benefícios ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Barra de Benefícios', 'vapor-hub-loja' ); ?></h2>
            <p class="vh-form-descricao" style="margin-bottom:18px">
                <?php esc_html_e( 'Itens exibidos logo abaixo do banner na home. Adicione quantos precisar (até 8). Shortcode: [vh_beneficios]', 'vapor-hub-loja' ); ?>
            </p>

            <div id="vh-beneficios-container" class="vh-beneficios-repeater">
                <?php for ( $i = 0; $i < $total_beneficios; $i++ ) :
                    $item  = $beneficios[ $i ] ?? [ 'icone' => 'check-circle', 'titulo' => '', 'descricao' => '' ];
                    $icone = $item['icone'] ?? 'check-circle';
                    ?>
                    <div class="vh-beneficio-item" data-indice="<?php echo esc_attr( $i ); ?>">
                        <div class="vh-beneficio-item-cabeca">
                            <span class="vh-beneficio-ordem"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
                            <div class="vh-beneficio-icone-preview" data-icone="<?php echo esc_attr( $icone ); ?>" aria-hidden="true">
                                <span class="dashicons <?php echo esc_attr( VH_Settings::dashicon_beneficio( $icone ) ); ?>"></span>
                            </div>
                            <span class="vh-beneficio-item-titulo-preview"><?php echo esc_html( $item['titulo'] ?: __( 'Novo benefício', 'vapor-hub-loja' ) ); ?></span>
                            <button type="button" class="vh-btn vh-btn--ghost vh-beneficio-remover"
                                    title="<?php esc_attr_e( 'Remover benefício', 'vapor-hub-loja' ); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <div class="vh-beneficio-item-corpo">
                            <div class="vh-form-grupo vh-beneficio-campo-icone">
                                <label><?php esc_html_e( 'Ícone', 'vapor-hub-loja' ); ?></label>
                                <select class="vh-beneficio-select-icone" name="vh_beneficios[<?php echo esc_attr( $i ); ?>][icone]">
                                    <?php foreach ( $icones_disponiveis as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $icone, $val ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="vh-form-grupo">
                                <label><?php esc_html_e( 'Título', 'vapor-hub-loja' ); ?></label>
                                <input type="text" class="vh-beneficio-input-titulo"
                                       name="vh_beneficios[<?php echo esc_attr( $i ); ?>][titulo]"
                                       value="<?php echo esc_attr( $item['titulo'] ); ?>"
                                       placeholder="<?php esc_attr_e( 'Ex: Produção Própria', 'vapor-hub-loja' ); ?>" />
                            </div>
                            <div class="vh-form-grupo">
                                <label><?php esc_html_e( 'Descrição', 'vapor-hub-loja' ); ?></label>
                                <input type="text" name="vh_beneficios[<?php echo esc_attr( $i ); ?>][descricao]"
                                       value="<?php echo esc_attr( $item['descricao'] ); ?>"
                                       placeholder="<?php esc_attr_e( 'Ex: Qualidade garantida', 'vapor-hub-loja' ); ?>" />
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="vh-beneficios-acoes">
                <button type="button" id="vh-beneficios-adicionar" class="vh-btn vh-btn--secundario">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Adicionar benefício', 'vapor-hub-loja' ); ?>
                </button>
                <span class="vh-beneficios-contagem" id="vh-beneficios-contagem">
                    <?php printf( esc_html__( '%1$d de %2$d itens', 'vapor-hub-loja' ), $total_beneficios, 8 ); ?>
                </span>
            </div>
        </div>

        <!-- ═══ Rodapé ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Rodapé', 'vapor-hub-loja' ); ?></h2>

            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Descrição do Rodapé', 'vapor-hub-loja' ); ?></label>
                <textarea name="vh_rodape[descricao]" rows="3"
                          placeholder="<?php esc_attr_e( 'Texto curto sobre a loja exibido no rodapé', 'vapor-hub-loja' ); ?>"
                ><?php echo esc_textarea( $rodape['descricao'] ?? '' ); ?></textarea>
            </div>

            <div class="vh-hero-grid">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Instagram (URL)', 'vapor-hub-loja' ); ?></label>
                    <input type="url" name="vh_rodape[instagram]"
                           value="<?php echo esc_attr( $rodape['instagram'] ?? '' ); ?>"
                           placeholder="https://instagram.com/..." />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'YouTube (URL)', 'vapor-hub-loja' ); ?></label>
                    <input type="url" name="vh_rodape[youtube]"
                           value="<?php echo esc_attr( $rodape['youtube'] ?? '' ); ?>"
                           placeholder="https://youtube.com/..." />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Facebook (URL)', 'vapor-hub-loja' ); ?></label>
                    <input type="url" name="vh_rodape[facebook]"
                           value="<?php echo esc_attr( $rodape['facebook'] ?? '' ); ?>"
                           placeholder="https://facebook.com/..." />
                </div>
            </div>
        </div>

        <!-- ═══ Identidade Visual e Cards da Loja ═══ -->
        <div class="vh-admin-section">
            <h2><?php esc_html_e( 'Identidade Visual e Cards da Loja (geral)', 'vapor-hub-loja' ); ?></h2>
            <p style="color:var(--vh-cinza-500);margin-bottom:20px;font-size:13px">
                <?php esc_html_e( 'Configurações que valem para o site inteiro: logo, favicon, tipografia, paleta de cores e visual dos cards de produto.', 'vapor-hub-loja' ); ?>
            </p>

            <div class="vh-hero-grid">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Logo da Loja (override do tema)', 'vapor-hub-loja' ); ?></label>
                    <div class="vh-upload-wrapper">
                        <div class="vh-upload-preview" id="vh-logo-preview">
                            <?php if ( ! empty( $identidade['logo_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $identidade['logo_url'] ); ?>" alt="" />
                            <?php else : ?>
                                <span class="dashicons dashicons-format-image"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="vh_identidade_visual[logo_url]" id="vh-logo-url"
                               value="<?php echo esc_attr( $identidade['logo_url'] ?? '' ); ?>" />
                        <button type="button" class="vh-btn vh-btn--secundario vh-upload-btn"
                                data-crop="logo"
                                data-target="#vh-logo-url" data-preview="#vh-logo-preview">
                            <?php esc_html_e( 'Selecionar Logo', 'vapor-hub-loja' ); ?>
                        </button>
                        <?php if ( ! empty( $identidade['logo_url'] ) ) : ?>
                            <button type="button" class="vh-btn vh-btn--ghost vh-upload-limpar"
                                    data-target="#vh-logo-url" data-preview="#vh-logo-preview">
                                <?php esc_html_e( 'Remover', 'vapor-hub-loja' ); ?>
                            </button>
                        <?php endif; ?>
                        <p class="vh-form-descricao"><?php esc_html_e( 'Use PNG com fundo transparente. O sistema converte para WebP mantendo a transparência e otimiza para ~400×160 px.', 'vapor-hub-loja' ); ?></p>
                    </div>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Favicon (ícone do site)', 'vapor-hub-loja' ); ?></label>
                    <div class="vh-upload-wrapper">
                        <div class="vh-upload-preview vh-upload-preview--favicon" id="vh-favicon-preview" data-empty-icon="admin-site">
                            <?php if ( ! empty( $identidade['favicon_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $identidade['favicon_url'] ); ?>" alt="" />
                            <?php else : ?>
                                <span class="dashicons dashicons-admin-site"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="vh_identidade_visual[favicon_url]" id="vh-favicon-url"
                               value="<?php echo esc_attr( $identidade['favicon_url'] ?? '' ); ?>" />
                        <button type="button" class="vh-btn vh-btn--secundario vh-upload-btn"
                                data-crop="favicon"
                                data-target="#vh-favicon-url" data-preview="#vh-favicon-preview">
                            <?php esc_html_e( 'Selecionar Favicon', 'vapor-hub-loja' ); ?>
                        </button>
                        <?php if ( ! empty( $identidade['favicon_url'] ) ) : ?>
                            <button type="button" class="vh-btn vh-btn--ghost vh-upload-limpar"
                                    data-target="#vh-favicon-url" data-preview="#vh-favicon-preview">
                                <?php esc_html_e( 'Remover', 'vapor-hub-loja' ); ?>
                            </button>
                        <?php endif; ?>
                        <p class="vh-form-descricao"><?php esc_html_e( 'Quadrado, ideal 512×512 px. Aparece na aba do navegador e atalhos no celular. PNG com transparência é aceito.', 'vapor-hub-loja' ); ?></p>
                    </div>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Fonte de Títulos', 'vapor-hub-loja' ); ?></label>
                    <select name="vh_identidade_visual[fonte_titulo]">
                        <option value="plus_jakarta" <?php selected( $identidade['fonte_titulo'] ?? '', 'plus_jakarta' ); ?>>Plus Jakarta Sans</option>
                        <option value="montserrat" <?php selected( $identidade['fonte_titulo'] ?? '', 'montserrat' ); ?>>Montserrat</option>
                        <option value="poppins" <?php selected( $identidade['fonte_titulo'] ?? '', 'poppins' ); ?>>Poppins</option>
                        <option value="raleway" <?php selected( $identidade['fonte_titulo'] ?? '', 'raleway' ); ?>>Raleway</option>
                        <option value="nunito" <?php selected( $identidade['fonte_titulo'] ?? '', 'nunito' ); ?>>Nunito</option>
                    </select>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Fonte de Texto', 'vapor-hub-loja' ); ?></label>
                    <select name="vh_identidade_visual[fonte_corpo]">
                        <option value="plus_jakarta" <?php selected( $identidade['fonte_corpo'] ?? '', 'plus_jakarta' ); ?>>Plus Jakarta Sans</option>
                        <option value="inter" <?php selected( $identidade['fonte_corpo'] ?? '', 'inter' ); ?>>Inter</option>
                        <option value="open_sans" <?php selected( $identidade['fonte_corpo'] ?? '', 'open_sans' ); ?>>Open Sans</option>
                        <option value="lato" <?php selected( $identidade['fonte_corpo'] ?? '', 'lato' ); ?>>Lato</option>
                        <option value="roboto" <?php selected( $identidade['fonte_corpo'] ?? '', 'roboto' ); ?>>Roboto</option>
                    </select>
                </div>
            </div>

            <div class="vh-hero-grid">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor Primária', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_primaria]" value="<?php echo esc_attr( $identidade['cor_primaria'] ?? '#7618f1' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor Primária (Hover)', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_primaria_hover]" value="<?php echo esc_attr( $identidade['cor_primaria_hover'] ?? '#5c10d0' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor de Fundo', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_fundo]" value="<?php echo esc_attr( $identidade['cor_fundo'] ?? '#f6f4fb' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor de Superfície', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_superficie]" value="<?php echo esc_attr( $identidade['cor_superficie'] ?? '#ffffff' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor de Texto', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_texto]" value="<?php echo esc_attr( $identidade['cor_texto'] ?? '#1a1228' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor de Texto Suave', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_texto_suave]" value="<?php echo esc_attr( $identidade['cor_texto_suave'] ?? '#6b6680' ); ?>" />
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Cor de Borda', 'vapor-hub-loja' ); ?></label>
                    <input type="color" name="vh_identidade_visual[cor_borda]" value="<?php echo esc_attr( $identidade['cor_borda'] ?? '#e4dff0' ); ?>" />
                </div>
            </div>

            <div class="vh-hero-grid">
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Estilo dos Cards de Produto', 'vapor-hub-loja' ); ?></label>
                    <select name="vh_identidade_visual[estilo_card]">
                        <option value="suave" <?php selected( $identidade['estilo_card'] ?? '', 'suave' ); ?>><?php esc_html_e( 'Suave (elegante)', 'vapor-hub-loja' ); ?></option>
                        <option value="moderno" <?php selected( $identidade['estilo_card'] ?? '', 'moderno' ); ?>><?php esc_html_e( 'Moderno (mais contraste)', 'vapor-hub-loja' ); ?></option>
                        <option value="vibrante" <?php selected( $identidade['estilo_card'] ?? '', 'vibrante' ); ?>><?php esc_html_e( 'Vibrante (destaque forte)', 'vapor-hub-loja' ); ?></option>
                    </select>
                </div>
                <div class="vh-form-grupo">
                    <label><?php esc_html_e( 'Raio dos Cards (px)', 'vapor-hub-loja' ); ?></label>
                    <input type="number" min="6" max="28" step="1"
                           name="vh_identidade_visual[raio_card]"
                           value="<?php echo esc_attr( $identidade['raio_card'] ?? '20' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Ajusta os cantos dos cards da loja e da vitrine.', 'vapor-hub-loja' ); ?></p>
                </div>
            </div>
        </div>

        <p>
            <button type="submit" class="vh-btn vh-btn--primario"><?php esc_html_e( 'Salvar Aparência', 'vapor-hub-loja' ); ?></button>
        </p>

    </form>
</div>

<!-- Template para novo slide do hero -->
<script type="text/html" id="tmpl-vh-slide-item">
    <div class="vh-slide-item" data-indice="{{INDEX}}">
        <div class="vh-slide-ordem-col">
            <span class="vh-slide-drag-handle" role="button" tabindex="0"
                  title="<?php esc_attr_e( 'Arrastar para reordenar', 'vapor-hub-loja' ); ?>"
                  aria-label="<?php esc_attr_e( 'Arrastar slide para reordenar', 'vapor-hub-loja' ); ?>">
                <span class="dashicons dashicons-menu" aria-hidden="true"></span>
            </span>
            <span class="vh-slide-ordem-num">1</span>
        </div>
        <div class="vh-slide-campos">
            <div class="vh-slide-campo vh-slide-campo--desktop">
                <span class="vh-slide-rotulo">
                    <span class="dashicons dashicons-desktop"></span>
                    <?php esc_html_e( 'Computador', 'vapor-hub-loja' ); ?>
                    <small><?php esc_html_e( '1920 × 800', 'vapor-hub-loja' ); ?></small>
                </span>
                <div class="vh-slide-thumb vh-upload-btn" data-size="full"
                     data-crop="hero-desktop"
                     data-value-type="url"
                     data-target="#vh-slide-img-{{INDEX}}"
                     data-preview="#vh-slide-preview-{{INDEX}}">
                    <span id="vh-slide-preview-{{INDEX}}">
                        <span class="dashicons dashicons-format-image"></span>
                    </span>
                </div>
                <input type="hidden" name="vh_hero[slides][{{INDEX}}][url]"
                       id="vh-slide-img-{{INDEX}}" value="" />
            </div>

            <div class="vh-slide-campo vh-slide-campo--mobile">
                <span class="vh-slide-rotulo">
                    <span class="dashicons dashicons-smartphone"></span>
                    <?php esc_html_e( 'Celular', 'vapor-hub-loja' ); ?>
                    <small><?php esc_html_e( 'opcional · 1080 × 1350', 'vapor-hub-loja' ); ?></small>
                </span>
                <div class="vh-slide-thumb vh-slide-thumb--mobile vh-upload-btn" data-size="full"
                     data-crop="hero-mobile"
                     data-value-type="url"
                     data-target="#vh-slide-img-m-{{INDEX}}"
                     data-preview="#vh-slide-preview-m-{{INDEX}}">
                    <span id="vh-slide-preview-m-{{INDEX}}">
                        <span class="dashicons dashicons-format-image"></span>
                    </span>
                </div>
                <input type="hidden" name="vh_hero[slides][{{INDEX}}][url_mobile]"
                       id="vh-slide-img-m-{{INDEX}}" value="" />
            </div>
        </div>

        <button type="button" class="vh-btn vh-btn--perigo vh-slide-remover"
                title="<?php esc_attr_e( 'Remover slide', 'vapor-hub-loja' ); ?>">
            <span class="dashicons dashicons-trash"></span>
            <?php esc_html_e( 'Remover slide', 'vapor-hub-loja' ); ?>
        </button>
    </div>
</script>

<!-- Template — novo benefício -->
<script type="text/html" id="tmpl-vh-beneficio-item">
    <div class="vh-beneficio-item" data-indice="{{INDEX}}">
        <div class="vh-beneficio-item-cabeca">
            <span class="vh-beneficio-ordem">{{NUMERO}}</span>
            <div class="vh-beneficio-icone-preview" data-icone="check-circle" aria-hidden="true">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <span class="vh-beneficio-item-titulo-preview"><?php esc_html_e( 'Novo benefício', 'vapor-hub-loja' ); ?></span>
            <button type="button" class="vh-btn vh-btn--ghost vh-beneficio-remover"
                    title="<?php esc_attr_e( 'Remover benefício', 'vapor-hub-loja' ); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
        <div class="vh-beneficio-item-corpo">
            <div class="vh-form-grupo vh-beneficio-campo-icone">
                <label><?php esc_html_e( 'Ícone', 'vapor-hub-loja' ); ?></label>
                <select class="vh-beneficio-select-icone" name="vh_beneficios[{{INDEX}}][icone]">
                    <?php foreach ( $icones_disponiveis as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( 'check-circle', $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Título', 'vapor-hub-loja' ); ?></label>
                <input type="text" class="vh-beneficio-input-titulo"
                       name="vh_beneficios[{{INDEX}}][titulo]" value=""
                       placeholder="<?php esc_attr_e( 'Ex: Produção Própria', 'vapor-hub-loja' ); ?>" />
            </div>
            <div class="vh-form-grupo">
                <label><?php esc_html_e( 'Descrição', 'vapor-hub-loja' ); ?></label>
                <input type="text" name="vh_beneficios[{{INDEX}}][descricao]" value=""
                       placeholder="<?php esc_attr_e( 'Ex: Qualidade garantida', 'vapor-hub-loja' ); ?>" />
            </div>
        </div>
    </div>
</script>

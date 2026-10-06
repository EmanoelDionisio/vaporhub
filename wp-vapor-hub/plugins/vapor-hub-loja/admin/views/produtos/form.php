<?php
/**
 * View — Criar/Editar Produto.
 *
 * @package VaporHubLoja
 */

defined( 'ABSPATH' ) || exit;

$vh_id      = VH_Router::id_atual();
$vh_edicao  = $vh_id > 0;
$vh_produto = $vh_edicao ? VH_Products_Service::obter( $vh_id ) : null;

if ( $vh_edicao && ! $vh_produto ) {
    echo '<div class="vh-admin-wrap"><div class="vh-admin-section"><p class="vh-tabela-vazia">' .
        esc_html__( 'Produto não encontrado.', 'vapor-hub-loja' ) . '</p></div></div>';
    return;
}

$vh_dados = wp_parse_args(
    (array) $vh_produto,
    [
        'nome' => '', 'slug' => '', 'tipo' => 'simple', 'status' => 'draft',
        'descricao' => '', 'descricao_curta' => '', 'sku' => '',
        'preco_regular' => '', 'preco_promo' => '', 'gerencia_estoque' => false,
        'estoque' => '', 'peso' => '', 'categorias' => [], 'tags' => [],
        'imagem_id' => 0, 'imagem_url' => '', 'galeria' => [],
        'largura' => '', 'altura' => '', 'comprimento' => '',
        'volumes' => 0, 'embalagem_tipo' => 0,
        'ncm' => '', 'gtin' => '', 'unidade' => '', 'marca' => '',
        'cat_principal' => 0,
    ]
);

$vh_endpoint = $vh_edicao ? 'produtos/' . $vh_id : 'produtos';
$vh_metodo   = $vh_edicao ? 'PATCH' : 'POST';
$vh_simples  = ( 'simple' === $vh_dados['tipo'] );

$vh_categorias_grupos = VH_Categories_Service::agrupar_para_produto();
$vh_cats_selecionadas   = array_map( 'intval', (array) $vh_dados['categorias'] );
$vh_tags       = VH_Products_Service::tags_destaque();
$vh_personalizacao = ( $vh_edicao && class_exists( 'VH_Personalizacao_Service' ) )
    ? VH_Personalizacao_Service::obter_config( $vh_id )
    : VH_Personalizacao_Service::config_vazia();
$vh_galeria_ids = array_map( static fn( $g ) => $g['id'], $vh_dados['galeria'] );
$vh_tiny_ctx    = class_exists( 'VH_Tiny' ) ? VH_Tiny::contexto_envio_produto() : [
    'disponivel'  => false,
    'modo'        => '',
    'rotulo_modo' => '',
    'motivo'      => '',
    'url_config'  => '',
];
?>

<div class="vh-admin-wrap">

    <p>
        <a class="vh-tabela-acao" href="<?php echo esc_url( VH_Router::url( 'produtos' ) ); ?>">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e( 'Voltar para produtos', 'vapor-hub-loja' ); ?>
        </a>
    </p>

    <?php
    $vh_tiny_estado = $vh_edicao && is_array( $vh_dados['tiny'] ?? null ) ? $vh_dados['tiny'] : null;
    if ( $vh_tiny_estado && in_array( $vh_tiny_estado['status'], [ 'pendente', 'nao_vinculado' ], true ) ) :
        $vh_pend_titulo = 'pendente' === $vh_tiny_estado['status']
            ? __( 'Alterações ainda não enviadas ao Tiny', 'vapor-hub-loja' )
            : __( 'Produto ainda não enviado ao Tiny', 'vapor-hub-loja' );
        $vh_pend_texto = 'pendente' === $vh_tiny_estado['status']
            ? __( 'Este produto foi alterado na loja depois da última sincronização. Use “Salvar e enviar ao Tiny” para atualizar o ERP agora.', 'vapor-hub-loja' )
            : __( 'Este produto nunca foi enviado ao Tiny. Use “Salvar e enviar ao Tiny” para criá-lo no ERP.', 'vapor-hub-loja' );
        ?>
        <div class="vh-tiny-pendencia vh-tiny-pendencia--<?php echo esc_attr( (string) $vh_tiny_estado['status'] ); ?>">
            <span class="dashicons dashicons-warning"></span>
            <span class="vh-tiny-pendencia__texto">
                <strong><?php echo esc_html( $vh_pend_titulo ); ?></strong>
                <?php echo esc_html( $vh_pend_texto ); ?>
            </span>
        </div>
    <?php endif; ?>

    <?php
    $vh_cats_bloqueio = ( $vh_edicao && ! empty( $vh_tiny_ctx['disponivel'] ) && class_exists( 'VH_Tiny_Sync_Service' ) )
        ? VH_Tiny_Sync_Service::categorias_bloqueando_envio( $vh_id )
        : [];
    if ( $vh_cats_bloqueio ) :
        $vh_cats_nomes = implode( ', ', array_map( static fn( $c ) => $c['nome'], $vh_cats_bloqueio ) );
        ?>
        <div class="vh-tiny-pendencia vh-tiny-pendencia--nao_vinculado">
            <span class="dashicons dashicons-category"></span>
            <span class="vh-tiny-pendencia__texto">
                <strong><?php esc_html_e( 'Categorias pendentes no Tiny', 'vapor-hub-loja' ); ?></strong>
                <?php
                printf(
                    /* translators: %s: comma-separated category names */
                    esc_html__( 'Para enviar este produto, configure em Integrações → Tiny → Mapeamento: use Vincular se a categoria já existir no Tiny com o mesmo nome, ou Criar no Tiny se for nova ou subcategoria. Pendente(s): %s.', 'vapor-hub-loja' ),
                    esc_html( $vh_cats_nomes )
                );
                ?>
                <a href="<?php echo esc_url( VH_Router::url( 'tiny', [ 'acao' => 'mapeamento' ] ) ); ?>">
                    <?php esc_html_e( 'Abrir mapeamento de categorias', 'vapor-hub-loja' ); ?>
                </a>
            </span>
        </div>
    <?php endif; ?>

    <form class="vh-rest-form" data-endpoint="<?php echo esc_attr( $vh_endpoint ); ?>" data-method="<?php echo esc_attr( $vh_metodo ); ?>"
          data-redirect="<?php echo esc_url( VH_Router::url( 'produtos' ) ); ?>"
          <?php if ( $vh_tiny_estado && in_array( $vh_tiny_estado['status'], [ 'pendente', 'nao_vinculado' ], true ) ) : ?>data-vh-tiny-pendente="1"<?php endif; ?>>

        <div class="vh-form-colunas">

            <div>
                <div class="vh-admin-section">
                    <h2><?php echo $vh_edicao ? esc_html__( 'Dados do produto', 'vapor-hub-loja' ) : esc_html__( 'Novo produto', 'vapor-hub-loja' ); ?></h2>

                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Nome', 'vapor-hub-loja' ); ?></label>
                        <input type="text" name="nome" value="<?php echo esc_attr( $vh_dados['nome'] ); ?>" required />
                    </div>

                    <?php
                    $vh_editor = static function ( string $id, string $nome, string $rotulo, string $valor, int $altura, int $teto, bool $opcional = false ): void {
                        ?>
                        <div class="vh-form-grupo vh-editor" data-vh-editor
                             data-vh-editor-altura="<?php echo esc_attr( (string) $altura ); ?>"
                             data-vh-editor-teto="<?php echo esc_attr( (string) $teto ); ?>"
                             <?php echo $opcional ? 'data-vh-editor-opcional="1"' : ''; ?>>
                            <div class="vh-editor-topo">
                                <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $rotulo ); ?></label>
                                <div class="vh-editor-acoes">
                                    <?php if ( $opcional ) : ?>
                                        <button type="button" class="vh-editor-abrir"><?php esc_html_e( 'Adicionar', 'vapor-hub-loja' ); ?></button>
                                    <?php endif; ?>
                                    <button type="button" class="vh-editor-limpar" title="<?php esc_attr_e( 'Tira cores e fontes coladas na importação. O texto permanece.', 'vapor-hub-loja' ); ?>">
                                        <?php esc_html_e( 'Limpar estilos', 'vapor-hub-loja' ); ?>
                                    </button>
                                    <button type="button" class="vh-editor-ampliar" aria-expanded="false" aria-label="<?php esc_attr_e( 'Ampliar', 'vapor-hub-loja' ); ?>">
                                        <span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <textarea id="<?php echo esc_attr( $id ); ?>" class="vh-editor-campo" name="<?php echo esc_attr( $nome ); ?>" rows="6"><?php echo esc_textarea( $valor ); ?></textarea>
                        </div>
                        <?php
                    };
                    $vh_editor(
                        'vh-descricao-curta',
                        'descricao_curta',
                        __( 'Descrição curta', 'vapor-hub-loja' ),
                        (string) $vh_dados['descricao_curta'],
                        96,
                        180,
                        true
                    );
                    $vh_editor(
                        'vh-descricao',
                        'descricao',
                        __( 'Descrição completa', 'vapor-hub-loja' ),
                        (string) $vh_dados['descricao'],
                        180,
                        260
                    );
                    ?>
                </div>

                <div class="vh-admin-section" id="vh-secao-tipo">
                    <h2><?php esc_html_e( 'Tipo de produto', 'vapor-hub-loja' ); ?></h2>
                    <div class="vh-toggle-wrapper" style="margin-bottom:10px">
                        <input type="checkbox" class="vh-toggle" id="vh-tipo-toggle" data-vh-tipo-toggle <?php checked( ! $vh_simples ); ?> />
                        <label for="vh-tipo-toggle" style="font-weight:600;cursor:pointer">
                            <?php esc_html_e( 'Produto personalizável (o cliente monta a combinação)', 'vapor-hub-loja' ); ?>
                        </label>
                    </div>
                    <input type="hidden" name="tipo" id="vh-tipo" value="<?php echo $vh_simples ? 'simple' : 'variable'; ?>" />
                    <p class="description" style="color:var(--vh-cinza-500);font-size:13px;margin:0">
                        <?php esc_html_e( 'Ativado: o cliente escolhe as opções (cor, modelo etc.) e cada combinação vira uma variação. O preço final é o preço base somado aos acréscimos de cada opção selecionada. Desativado: produto simples com preço único.', 'vapor-hub-loja' ); ?>
                    </p>
                </div>

                <div class="vh-admin-section" id="vh-secao-variavel"<?php echo $vh_simples ? ' style="display:none"' : ''; ?>>
                    <h2><?php esc_html_e( 'Opções de personalização', 'vapor-hub-loja' ); ?></h2>
                    <p class="description vh-pers-descricao">
                        <?php esc_html_e( 'Configure as opções de personalização deste produto (cor, formato com imagem, tamanho, lado etc.). Cada combinação vira uma variação na loja.', 'vapor-hub-loja' ); ?>
                    </p>
                    <p class="description vh-pers-reuso-dica">
                        <?php esc_html_e( 'Dica: personalizações já usadas em outros produtos (ex.: Cor do EVA, Tamanho do EVA) podem ser reutilizadas — busque abaixo antes de criar uma nova com nome parecido.', 'vapor-hub-loja' ); ?>
                    </p>

                    <div class="vh-form-grupo">
                        <label for="vh-pers-preco-base"><?php esc_html_e( 'Preço base (R$) — valor inicial de cada combinação', 'vapor-hub-loja' ); ?></label>
                        <input type="number" step="0.01" min="0" id="vh-pers-preco-base" name="preco_base"
                            value="<?php echo esc_attr( (string) ( $vh_personalizacao['preco_base'] ?? $vh_dados['preco_base'] ?? '' ) ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Opcional: defina um acréscimo (+ R$) em cada opção abaixo. Ao salvar, o preço final é gravado em cada variação.', 'vapor-hub-loja' ); ?>
                        </p>
                    </div>

                    <div id="vh-pers-editor" class="vh-pers-editor" data-vh-pers-editor></div>

                    <p class="vh-pers-combinacoes description" data-vh-pers-combinacoes hidden></p>

                    <?php if ( $vh_edicao && ! empty( $vh_dados['variacoes'] ) ) : ?>
                        <h3 style="margin:22px 0 8px;font-size:15px"><?php esc_html_e( 'Estoque por combinação', 'vapor-hub-loja' ); ?></h3>
                        <p class="description" style="margin:0 0 10px">
                            <?php esc_html_e( 'A loja é a dona do saldo: o número daqui é o que vale na vitrine e o que o Tiny recebe. Deixe em branco para não alterar.', 'vapor-hub-loja' ); ?>
                        </p>
                        <table class="vh-tabela vh-tabela-variacoes">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Combinação', 'vapor-hub-loja' ); ?></th>
                                    <th><?php esc_html_e( 'SKU', 'vapor-hub-loja' ); ?></th>
                                    <th style="width:120px"><?php esc_html_e( 'Estoque', 'vapor-hub-loja' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $vh_dados['variacoes'] as $vh_var ) : ?>
                                    <?php
                                    $vh_var_id  = (int) ( $vh_var['id'] ?? 0 );
                                    $vh_rotulos = [];
                                    foreach ( (array) ( $vh_var['grade'] ?? [] ) as $vh_eixo ) {
                                        $vh_rotulos[] = ( $vh_eixo['chave'] ?? '' ) . ': ' . ( $vh_eixo['valor'] ?? '' );
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( implode( ' · ', $vh_rotulos ) ); ?></td>
                                        <td><code><?php echo esc_html( (string) ( $vh_var['sku'] ?? '' ) ); ?></code></td>
                                        <td>
                                            <input type="number" step="1" min="0"
                                                name="variacoes[<?php echo esc_attr( (string) $vh_var_id ); ?>][estoque]"
                                                value="<?php echo esc_attr( null === ( $vh_var['estoque'] ?? null ) ? '' : (string) $vh_var['estoque'] ); ?>"
                                                aria-label="<?php esc_attr_e( 'Estoque da combinação', 'vapor-hub-loja' ); ?>" />
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ( $vh_edicao && ! empty( $vh_dados['total_variacoes'] ) ) : ?>
                        <p class="description vh-pers-variacoes-info">
                            <?php
                            printf(
                                /* translators: %d: número de variações já geradas */
                                esc_html__( 'Atualmente este produto tem %d combinações geradas. Salvar recria as faltantes e remove as que não correspondem mais.', 'vapor-hub-loja' ),
                                (int) $vh_dados['total_variacoes']
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="vh-admin-section" id="vh-secao-simples"<?php echo $vh_simples ? '' : ' style="display:none"'; ?>>
                    <h2><?php esc_html_e( 'Preço e estoque', 'vapor-hub-loja' ); ?></h2>
                    <div class="vh-form-linha">
                        <div class="vh-form-grupo">
                            <label><?php esc_html_e( 'Preço regular (R$)', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="0.01" min="0" name="preco_regular" value="<?php echo esc_attr( $vh_dados['preco_regular'] ); ?>" />
                        </div>
                        <div class="vh-form-grupo">
                            <label><?php esc_html_e( 'Preço promocional (R$)', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="0.01" min="0" name="preco_promo" value="<?php echo esc_attr( $vh_dados['preco_promo'] ); ?>" />
                        </div>
                    </div>
                    <div class="vh-form-linha">
                        <div class="vh-form-grupo">
                            <label><?php esc_html_e( 'SKU', 'vapor-hub-loja' ); ?></label>
                            <input type="text" name="sku" value="<?php echo esc_attr( $vh_dados['sku'] ); ?>" />
                        </div>
                    </div>
                    <div class="vh-toggle-wrapper" style="margin-bottom:16px">
                        <input type="checkbox" class="vh-toggle" id="vh-gerencia-estoque" name="gerencia_estoque" value="1" <?php checked( (bool) $vh_dados['gerencia_estoque'] ); ?> />
                        <label for="vh-gerencia-estoque" style="font-weight:600;cursor:pointer">
                            <?php esc_html_e( 'Controlar quantidade em estoque', 'vapor-hub-loja' ); ?>
                        </label>
                    </div>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Quantidade em estoque', 'vapor-hub-loja' ); ?></label>
                        <input type="number" step="1" name="estoque" value="<?php echo esc_attr( (string) $vh_dados['estoque'] ); ?>" />
                    </div>
                </div>

                <div class="vh-admin-section" id="vh-secao-envio">
                    <h2><?php esc_html_e( 'Envio e dimensões', 'vapor-hub-loja' ); ?></h2>
                    <p class="description" style="color:var(--vh-cinza-500);font-size:13px;margin:0 0 14px">
                        <?php esc_html_e( 'Usado no cálculo do frete dos Correios e enviado ao Tiny. Vale para o produto inteiro: nos personalizáveis, cada combinação herda estas medidas.', 'vapor-hub-loja' ); ?>
                    </p>
                    <div class="vh-form-linha">
                        <div class="vh-form-grupo">
                            <label for="vh-envio-peso"><?php esc_html_e( 'Peso bruto (kg)', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="0.001" min="0" id="vh-envio-peso" name="peso" value="<?php echo esc_attr( (string) $vh_dados['peso'] ); ?>" placeholder="0,280" />
                        </div>
                        <div class="vh-form-grupo">
                            <label for="vh-envio-volumes"><?php esc_html_e( 'Nº de volumes', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="1" min="0" max="999" id="vh-envio-volumes" name="volumes" value="<?php echo esc_attr( (string) ( $vh_dados['volumes'] ?: '' ) ); ?>" placeholder="1" />
                        </div>
                    </div>
                    <div class="vh-form-linha">
                        <div class="vh-form-grupo">
                            <label for="vh-envio-largura"><?php esc_html_e( 'Largura (cm)', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="0.1" min="0" id="vh-envio-largura" name="largura" value="<?php echo esc_attr( (string) $vh_dados['largura'] ); ?>" />
                        </div>
                        <div class="vh-form-grupo">
                            <label for="vh-envio-altura"><?php esc_html_e( 'Altura (cm)', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="0.1" min="0" id="vh-envio-altura" name="altura" value="<?php echo esc_attr( (string) $vh_dados['altura'] ); ?>" />
                        </div>
                        <div class="vh-form-grupo">
                            <label for="vh-envio-comprimento"><?php esc_html_e( 'Comprimento (cm)', 'vapor-hub-loja' ); ?></label>
                            <input type="number" step="0.1" min="0" id="vh-envio-comprimento" name="comprimento" value="<?php echo esc_attr( (string) $vh_dados['comprimento'] ); ?>" />
                        </div>
                    </div>
                    <div class="vh-form-grupo">
                        <label for="vh-envio-embalagem"><?php esc_html_e( 'Tipo de embalagem', 'vapor-hub-loja' ); ?></label>
                        <select id="vh-envio-embalagem" name="embalagem_tipo">
                            <?php foreach ( VH_Products_Service::embalagens() as $vh_emb_id => $vh_emb_rotulo ) : ?>
                                <option value="<?php echo esc_attr( (string) $vh_emb_id ); ?>" <?php selected( (int) $vh_dados['embalagem_tipo'], (int) $vh_emb_id ); ?>>
                                    <?php echo esc_html( $vh_emb_rotulo ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="vh-admin-section" id="vh-secao-fiscal">
                    <h2><?php esc_html_e( 'Dados fiscais', 'vapor-hub-loja' ); ?></h2>
                    <p class="description" style="color:var(--vh-cinza-500);font-size:13px;margin:0 0 14px">
                        <?php esc_html_e( 'Vão junto com o produto para o Tiny e são o que a nota fiscal exige. Pode deixar em branco agora e preencher depois: campo vazio não apaga o que já existe no Tiny.', 'vapor-hub-loja' ); ?>
                    </p>
                    <div class="vh-form-linha">
                        <div class="vh-form-grupo">
                            <label for="vh-fiscal-ncm"><?php esc_html_e( 'NCM', 'vapor-hub-loja' ); ?></label>
                            <input type="text" inputmode="numeric" maxlength="10" id="vh-fiscal-ncm" name="ncm" value="<?php echo esc_attr( (string) $vh_dados['ncm'] ); ?>" placeholder="9507.90.00" />
                            <p class="description"><?php esc_html_e( 'Classificação fiscal de 8 dígitos. Pontos são aceitos e removidos ao salvar.', 'vapor-hub-loja' ); ?></p>
                        </div>
                        <div class="vh-form-grupo">
                            <label for="vh-fiscal-gtin"><?php esc_html_e( 'GTIN / EAN', 'vapor-hub-loja' ); ?></label>
                            <input type="text" inputmode="numeric" maxlength="14" id="vh-fiscal-gtin" name="gtin" value="<?php echo esc_attr( (string) $vh_dados['gtin'] ); ?>" placeholder="7898765432109" />
                            <p class="description"><?php esc_html_e( 'Código de barras do produto. Sem código de barras, deixe vazio.', 'vapor-hub-loja' ); ?></p>
                        </div>
                    </div>
                    <div class="vh-form-linha">
                        <div class="vh-form-grupo">
                            <label for="vh-fiscal-unidade"><?php esc_html_e( 'Unidade', 'vapor-hub-loja' ); ?></label>
                            <input type="text" maxlength="6" list="vh-fiscal-unidades" id="vh-fiscal-unidade" name="unidade" value="<?php echo esc_attr( (string) $vh_dados['unidade'] ); ?>" placeholder="UN" />
                            <datalist id="vh-fiscal-unidades">
                                <?php foreach ( VH_Products_Service::unidades() as $vh_unidade ) : ?>
                                    <option value="<?php echo esc_attr( $vh_unidade ); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="vh-form-grupo">
                            <label for="vh-fiscal-marca"><?php esc_html_e( 'Marca', 'vapor-hub-loja' ); ?></label>
                            <input type="text" maxlength="60" id="vh-fiscal-marca" name="marca" value="<?php echo esc_attr( (string) $vh_dados['marca'] ); ?>" placeholder="Vapor Hub" />
                            <p class="description"><?php esc_html_e( 'Fica registrada na loja. Só é enviada ao Tiny quando a conta libera o cadastro de marcas.', 'vapor-hub-loja' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="vh-admin-section">
                    <h2><?php esc_html_e( 'Publicação', 'vapor-hub-loja' ); ?></h2>
                    <div class="vh-form-grupo">
                        <label><?php esc_html_e( 'Status', 'vapor-hub-loja' ); ?></label>
                        <select name="status">
                            <option value="publish" <?php selected( $vh_dados['status'], 'publish' ); ?>><?php esc_html_e( 'Publicado', 'vapor-hub-loja' ); ?></option>
                            <option value="draft" <?php selected( $vh_dados['status'], 'draft' ); ?>><?php esc_html_e( 'Rascunho', 'vapor-hub-loja' ); ?></option>
                            <option value="pending" <?php selected( $vh_dados['status'], 'pending' ); ?>><?php esc_html_e( 'Pendente', 'vapor-hub-loja' ); ?></option>
                        </select>
                    </div>
                    <div class="vh-form-acoes vh-form-acoes--coluna">
                        <button type="submit" class="vh-btn vh-btn--primario">
                            <?php echo $vh_edicao ? esc_html__( 'Salvar produto', 'vapor-hub-loja' ) : esc_html__( 'Criar produto', 'vapor-hub-loja' ); ?>
                        </button>
                        <?php if ( class_exists( 'VH_Tiny' ) ) : ?>
                            <?php
                            $vh_tiny_titulo = $vh_tiny_ctx['disponivel']
                                ? sprintf(
                                    /* translators: %s: Tiny API mode label (API v2 or API v3) */
                                    __( 'Salva e envia imediatamente ao Tiny (%s).', 'vapor-hub-loja' ),
                                    $vh_tiny_ctx['rotulo_modo']
                                )
                                : (string) $vh_tiny_ctx['motivo'];
                            ?>
                            <button
                                type="submit"
                                class="vh-btn vh-btn--secundario"
                                data-vh-enviar-tiny="1"
                                title="<?php echo esc_attr( $vh_tiny_titulo ); ?>"
                                <?php disabled( ! $vh_tiny_ctx['disponivel'] ); ?>
                            >
                                <?php
                                echo $vh_edicao
                                    ? esc_html__( 'Salvar e enviar ao Tiny', 'vapor-hub-loja' )
                                    : esc_html__( 'Criar e enviar ao Tiny', 'vapor-hub-loja' );
                                ?>
                            </button>
                            <?php if ( ! $vh_tiny_ctx['disponivel'] && '' !== (string) $vh_tiny_ctx['motivo'] ) : ?>
                                <p class="vh-form-descricao vh-tiny-produto-aviso">
                                    <?php echo esc_html( (string) $vh_tiny_ctx['motivo'] ); ?>
                                    <a href="<?php echo esc_url( (string) $vh_tiny_ctx['url_config'] ); ?>">
                                        <?php esc_html_e( 'Configurar Tiny ERP', 'vapor-hub-loja' ); ?>
                                    </a>
                                </p>
                            <?php elseif ( $vh_tiny_ctx['disponivel'] ) : ?>
                                <p class="vh-form-descricao">
                                    <?php
                                    printf(
                                        /* translators: %s: Tiny API mode label */
                                        esc_html__( 'Envio imediato via %s. A sincronização automática da fila continua disponível nas integrações.', 'vapor-hub-loja' ),
                                        esc_html( (string) $vh_tiny_ctx['rotulo_modo'] )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="vh-admin-section">
                    <h2><?php esc_html_e( 'Imagem principal', 'vapor-hub-loja' ); ?></h2>
                    <div class="vh-upload-wrapper">
                        <div class="vh-upload-preview" id="vh-prod-img-preview">
                            <?php if ( $vh_dados['imagem_url'] ) : ?>
                                <img src="<?php echo esc_url( $vh_dados['imagem_url'] ); ?>" alt="" />
                            <?php else : ?>
                                <span class="dashicons dashicons-format-image"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="imagem_id" id="vh-prod-img" value="<?php echo esc_attr( (string) $vh_dados['imagem_id'] ); ?>" />
                        <button type="button" class="vh-btn vh-btn--primario vh-media-enviar-btn" data-crop="produto" data-value-type="id" data-target="#vh-prod-img" data-preview="#vh-prod-img-preview">
                            <?php esc_html_e( 'Enviar imagem', 'vapor-hub-loja' ); ?>
                        </button>
                        <button type="button" class="vh-btn vh-btn--secundario vh-media-btn" data-target="#vh-prod-img" data-preview="#vh-prod-img-preview">
                            <?php esc_html_e( 'Biblioteca', 'vapor-hub-loja' ); ?>
                        </button>
                        <button type="button" class="vh-btn vh-btn--ghost vh-media-limpar" data-target="#vh-prod-img" data-preview="#vh-prod-img-preview">
                            <?php esc_html_e( 'Remover', 'vapor-hub-loja' ); ?>
                        </button>
                    </div>
                </div>

                <div class="vh-admin-section">
                    <h2><?php esc_html_e( 'Galeria', 'vapor-hub-loja' ); ?></h2>
                    <div id="vh-prod-galeria-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                        <?php foreach ( $vh_dados['galeria'] as $vh_img ) : ?>
                            <img src="<?php echo esc_url( $vh_img['url'] ); ?>" alt="" width="56" height="56" style="border-radius:6px;object-fit:cover" />
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="galeria_ids" id="vh-prod-galeria" value="<?php echo esc_attr( implode( ',', $vh_galeria_ids ) ); ?>" />
                    <button type="button" class="vh-btn vh-btn--primario vh-media-enviar-btn" data-crop="produto" data-value-type="id" data-galeria="1" data-target="#vh-prod-galeria" data-preview="#vh-prod-galeria-preview">
                        <?php esc_html_e( 'Enviar imagens', 'vapor-hub-loja' ); ?>
                    </button>
                    <button type="button" class="vh-btn vh-btn--secundario vh-media-galeria-btn" data-target="#vh-prod-galeria" data-preview="#vh-prod-galeria-preview">
                        <?php esc_html_e( 'Biblioteca', 'vapor-hub-loja' ); ?>
                    </button>
                </div>

                <div class="vh-admin-section">
                    <h2><?php esc_html_e( 'Categorias', 'vapor-hub-loja' ); ?></h2>
                    <p class="vh-form-descricao">
                        <?php esc_html_e( 'Marque onde o produto deve aparecer na loja. Clique na seta ao lado do nome para abrir ou fechar as subcategorias.', 'vapor-hub-loja' ); ?>
                    </p>
                    <?php if ( $vh_categorias_grupos ) : ?>
                        <div class="vh-cat-produto-wrap">
                            <div class="vh-cat-produto-toolbar">
                                <label class="screen-reader-text" for="vh-cat-produto-busca">
                                    <?php esc_html_e( 'Buscar categoria', 'vapor-hub-loja' ); ?>
                                </label>
                                <input type="search"
                                       id="vh-cat-produto-busca"
                                       class="vh-cat-produto-busca"
                                       placeholder="<?php esc_attr_e( 'Buscar categoria…', 'vapor-hub-loja' ); ?>"
                                       autocomplete="off"
                                       spellcheck="false" />
                            </div>
                            <div id="vh-cat-produto-picker" class="vh-cat-produto-picker" tabindex="0"
                                 aria-label="<?php esc_attr_e( 'Lista de categorias', 'vapor-hub-loja' ); ?>">
                            <?php foreach ( $vh_categorias_grupos as $vh_grupo ) :
                                $vh_raiz   = $vh_grupo['raiz'];
                                $vh_filhos = $vh_grupo['filhos'];
                                $vh_tem_filhos = ! empty( $vh_filhos );
                                $vh_grupo_aberto = in_array( (int) $vh_raiz['id'], $vh_cats_selecionadas, true );
                                if ( ! $vh_grupo_aberto && $vh_tem_filhos ) {
                                    foreach ( $vh_filhos as $vh_filho ) {
                                        if ( in_array( (int) $vh_filho['id'], $vh_cats_selecionadas, true ) ) {
                                            $vh_grupo_aberto = true;
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <?php if ( $vh_tem_filhos ) : ?>
                                    <details class="vh-cat-produto-grupo" <?php echo $vh_grupo_aberto ? 'open' : ''; ?>>
                                        <summary class="vh-cat-produto-grupo-resumo">
                                            <label class="vh-cat-produto-item vh-cat-produto-item--raiz">
                                                <input type="checkbox" name="categorias[]" value="<?php echo esc_attr( (string) $vh_raiz['id'] ); ?>"
                                                    <?php checked( in_array( (int) $vh_raiz['id'], $vh_cats_selecionadas, true ) ); ?> />
                                                <span class="vh-cat-produto-nome"><?php echo esc_html( $vh_raiz['nome'] ); ?></span>
                                                <span class="vh-cat-produto-meta">
                                                    <?php
                                                    printf(
                                                        esc_html( _n( '%d subcategoria', '%d subcategorias', count( $vh_filhos ), 'vapor-hub-loja' ) ),
                                                        count( $vh_filhos )
                                                    );
                                                    ?>
                                                </span>
                                            </label>
                                        </summary>
                                        <div class="vh-cat-produto-grupo-corpo">
                                            <?php foreach ( $vh_filhos as $vh_cat ) :
                                                $vh_nivel = (int) $vh_cat['nivel'];
                                                ?>
                                                <label class="vh-cat-produto-item vh-cat-produto-item--filho"
                                                       style="--vh-cat-nivel: <?php echo esc_attr( (string) max( 1, $vh_nivel ) ); ?>;">
                                                    <input type="checkbox" name="categorias[]" value="<?php echo esc_attr( (string) $vh_cat['id'] ); ?>"
                                                        <?php checked( in_array( (int) $vh_cat['id'], $vh_cats_selecionadas, true ) ); ?> />
                                                    <span class="vh-cat-produto-conteudo">
                                                        <span class="vh-cat-produto-nome"><?php echo esc_html( $vh_cat['nome'] ); ?></span>
                                                        <?php if ( ! empty( $vh_cat['caminho'] ) && $vh_nivel > 1 ) : ?>
                                                            <span class="vh-cat-produto-caminho"><?php echo esc_html( $vh_cat['caminho'] ); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php else : ?>
                                    <label class="vh-cat-produto-item vh-cat-produto-item--raiz vh-cat-produto-item--solta">
                                        <input type="checkbox" name="categorias[]" value="<?php echo esc_attr( (string) $vh_raiz['id'] ); ?>"
                                            <?php checked( in_array( (int) $vh_raiz['id'], $vh_cats_selecionadas, true ) ); ?> />
                                        <span class="vh-cat-produto-nome"><?php echo esc_html( $vh_raiz['nome'] ); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </div>
                            <p class="vh-cat-produto-scroll-dica" aria-hidden="true">
                                <?php esc_html_e( 'Role para ver mais categorias', 'vapor-hub-loja' ); ?>
                            </p>
                        </div>
                    <?php else : ?>
                        <p style="color:var(--vh-cinza-500);font-size:13px"><?php esc_html_e( 'Nenhuma categoria cadastrada ainda.', 'vapor-hub-loja' ); ?></p>
                    <?php endif; ?>
                    <?php
                    $vh_termos_principal = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
                    $vh_lista_principal  = [];
                    if ( is_array( $vh_termos_principal ) ) {
                        foreach ( $vh_termos_principal as $vh_termo_principal ) {
                            if ( $vh_termo_principal instanceof WP_Term && ! in_array( $vh_termo_principal->slug, [ 'uncategorized', 'sem-categoria' ], true ) ) {
                                $vh_lista_principal[] = $vh_termo_principal;
                            }
                        }
                    }
                    if ( $vh_lista_principal ) :
                        usort(
                            $vh_lista_principal,
                            static function ( WP_Term $a, WP_Term $b ): int {
                                return strcasecmp( $a->name, $b->name );
                            }
                        );
                        $vh_por_id = [];
                        foreach ( $vh_lista_principal as $vh_termo_principal ) {
                            $vh_por_id[ $vh_termo_principal->term_id ] = $vh_termo_principal;
                        }
                        ?>
                        <div class="vh-form-grupo" style="margin-top:16px">
                            <label for="vh-cat-principal"><?php esc_html_e( 'Categoria do endereço', 'vapor-hub-loja' ); ?></label>
                            <p class="vh-form-descricao">
                                <?php esc_html_e( 'Se o produto estiver em mais de um ramo, este é o caminho que entra no link. Sem escolha, vale o ramo mais profundo.', 'vapor-hub-loja' ); ?>
                            </p>
                            <select id="vh-cat-principal" name="cat_principal">
                                <option value="0" <?php selected( (int) $vh_dados['cat_principal'], 0 ); ?>><?php esc_html_e( 'Automático (ramo mais profundo)', 'vapor-hub-loja' ); ?></option>
                                <?php foreach ( $vh_lista_principal as $vh_termo_principal ) :
                                    $vh_prof = 0;
                                    $vh_cursor = $vh_termo_principal;
                                    $vh_guarda = 0;
                                    while ( $vh_cursor->parent && isset( $vh_por_id[ $vh_cursor->parent ] ) && $vh_guarda < 8 ) {
                                        ++$vh_prof;
                                        $vh_cursor = $vh_por_id[ $vh_cursor->parent ];
                                        ++$vh_guarda;
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr( (string) $vh_termo_principal->term_id ); ?>" <?php selected( (int) $vh_dados['cat_principal'], (int) $vh_termo_principal->term_id ); ?>>
                                        <?php echo esc_html( str_repeat( '— ', $vh_prof ) . $vh_termo_principal->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="vh-admin-section">
                    <h2><?php esc_html_e( 'Destaques', 'vapor-hub-loja' ); ?></h2>
                    <div class="vh-checkbox-lista">
                        <?php foreach ( $vh_tags as $vh_slug => $vh_label ) : ?>
                            <label>
                                <input type="checkbox" name="tags[]" value="<?php echo esc_attr( $vh_slug ); ?>"
                                    <?php checked( in_array( $vh_slug, (array) $vh_dados['tags'], true ) ); ?> />
                                <?php echo esc_html( $vh_label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

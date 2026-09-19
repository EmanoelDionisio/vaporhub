# Prompt para o agente — Vapor Hub

Cole o bloco abaixo num chat **aberto nesta pasta** (`/home/emanoeldionisio/Projetos/Empresa/Sistemas/vapor-hub`). Leia também `docs/contexto-vapor-hub.md` e **`docs/catalogo-tiny.md`**.

---

Você está no repositório **Vapor Hub**, pasta:

`/home/emanoeldionisio/Projetos/Empresa/Sistemas/vapor-hub`

Leia `docs/contexto-vapor-hub.md` e **`docs/catalogo-tiny.md`** antes de mudar código.

## Regras absolutas

1. **Não modifique nada** em `/home/emanoeldionisio/Projetos/Empresa/Sistemas/piscou-afundou` nem em `landing-piscou-afundou`. Esse produto continua de pesca e segue vida própria.
2. Trabalhe **somente** dentro de `vapor-hub`.
3. Este repo é uma réplica integral do Piscou Afundou. O código ainda fala de pesca, `piscou-afundou`, Gama da Pesca, Manhoso, etc. Sua missão é transformar isso num **produto novo**, um hub comercial de e-commerce para o nicho de **vape, pod e cigarro eletrônico**.
4. **Não faça deploy.** Os caminhos de produção do piloto (FTP, SSH, WP-CLI, domínio, `REMOTE_WP_PATH`) serão passados depois. Não reutilize host/usuário/caminho do `.env.wp-deploy.example` da Gama da Pesca. Atualize só placeholders e nomes no exemplo.
5. Não aponte git remote para `EmanoelDionisio/piscou-afundou`. Remote novo só se o usuário pedir.
6. Não instale a dezena de plugins da CIA do Vapor (Shoptimizer, FunnelKit, RevSlider, etc.). A proposta é a stack **limpa**: Hello Elementor + WooCommerce + Elementor Pro + tema filho + plugin Minha Loja + o mínimo de dependências que o código já exige (ex.: Correios for WooCommerce quando o tema declarar).

## Identidade do produto

| Item | Valor |
|---|---|
| Nome comercial | Vapor Hub |
| Pasta | vapor-hub |
| Tema (slug) | vapor-hub |
| Plugin do painel (slug) | vapor-hub-loja |
| Text domain | vapor-hub |
| Prefixo de classes/funções/opções | trocar `PA_` / `pa_` / `piscou-afundou` de forma consistente (sugestão: `VH_` / `vh_` / `vapor-hub`) |
| Pasta WP | `wp-vapor-hub/` (hoje `wp-piscou-afundou/`) |
| Autor | New Alliance Tecnologia |

Não use no nome do produto: CIA do Vapor, POD & VAPE, Piscou Afundou, Gama da Pesca.

## Referência de nicho (não é o servidor de deploy)

VPS de referência já analisada: `ssh root@5.161.49.209` (hostname `ciadovapor`). Lojas:

- https://ciadovapor.br.com — CIA do Vapor (Shoptimizer, ~8k produtos, ~21k variações, ~26k pedidos, 66 plugins)
- https://podevape.br.com — POD & VAPE Brasil

Use a CIA do Vapor como **referência de vitrine, categorias e negócio**. Não copie o banco Woo nem planeje export/import de produto de loja para loja.

## Catálogo: Tiny ERP é o hub (obrigatório prever)

A loja piloto sobe **do zero**. Os produtos **já estão no Tiny**. O Tiny é o hub de todas as lojas desta administração. É **bem provável** que não haja migração Woo → Woo; o caminho é **importar ou sincronizar a partir do ERP**.

Leia `docs/catalogo-tiny.md`. Resumo operacional:

1. Woo recebe estrutura (categorias, atributos, mapeamento). Produto de verdade entra pelo Tiny.
2. CSV em `setup/` é amostra de teste. **Não** é o pipeline de produção.
3. **Não** desenhe “exportar 8 mil produtos da CIA e importar no piloto”.
4. No código herdado o pull Tiny → Woo **não cria** produto sem par local (`importar_produto_de_tiny()` devolve `pa_tiny_sem_vinculo_local`). Isso era regra do Piscou Afundou (“cadastro nasce na loja”). No Vapor Hub essa regra **não vale**. Durante o rebrand:
   - preserve a integração Tiny (não quebre fila, mapeamento, SKU, webhook, modos);
   - apague/inverta copy, comentários e mensagens que digam que o cadastro é sempre da loja;
   - deixe explícito que o piloto parte de loja vazia + Tiny mestre;
   - **não** ligue um “sincronizar tudo” que despeje a conta Tiny inteira na vitrine — o mesmo ERP serve várias lojas. Import só com recorte (categoria mapeada, ativo, SKU).
5. Não faça push em massa da loja vazia para o Tiny (polui o ERP).
6. Nicho vapor = muitos produtos **variáveis** (sabor, puffs, nicotina). O pull de variável hoje é limitado; isso precisa ficar registrado como restrição/próximo marco, não ignorado.
7. Credenciais Tiny, depósito e recorte exato o usuário passa depois. Não conecte na conta real agora.

Não implemente nesta passagem o import completo de milhares de SKUs reais. **Preveja** a arquitetura: rename Tiny com o novo prefixo, narrativa certa, e um mapa claro do que falta para Tiny → Woo **criar** produto (simples e variável) com filtro. Se, depois do rebrand, ainda couber um esboço seguro de `importar_produto_de_tiny()` (criar só com SKU + categoria mapeada, sem DELETE no Tiny), faça; senão deixe o próximo marco escrito em `docs/`.

### Catálogo-alvo no setup WooCommerce

Categorias (ajuste nomes/slugs com bom gosto, hierarquia se fizer sentido):

- Vape
- POD Descartável (faixas de puffs podem ser subcategorias)
- POD Recarregável (aparelhos / reposições)
- e-Líquidos (free base e nic salt; perfis frutado, ice, mentolado, atabacado, doce)
- Vaporizador de ervas
- Nicotina oral
- Acessórios
- Marcas (ou usar taxonomia `product_brand`)

Atributos de variação:

- Sabor
- Puffs
- Teor de nicotina
- Tipo de nicotina
- Quantidade em ml
- Cores
- Resistência
- Tipo de sabor
- Teor de CBD (pode existir, mesmo que pouco usado)

CSV de exemplo: poucos produtos fictícios do nicho (pod descartável, kit pod, e-líquido), **não** produtos de pesca e **não** substituto do Tiny.

### Visual / UX

Referência: home e PDP da CIA (preto, vitrine, busca, PIX, parcelamento, CEP, WhatsApp). Não copie assets proprietários da CIA. Recrie o design system do tema para esse nicho.

A seção “comunidade de pescadores / #PiscouAfundou” não cabe. Adapte, desligue na home ou substitua por algo do nicho (ex.: grupo VIP / conteúdo), sem inventar marca de loja.

Tom: loja adulta de vapor, conversão, entrega, PIX. Sem copy de pesca esportiva.

## Trabalho a fazer (nesta pasta)

Faça o rebrand **completo** e a troca de nicho, não um find-replace cego. Varra tema, plugin, setup, React (`src/`), testes, scripts, skills Cursor, README, `.env*.example`, `package.json`, Playwright, phpunit, `.wp-env.json`.

Inclua, no mínimo:

1. Renomear pastas `wp-piscou-afundou`, `themes/piscou-afundou`, `plugins/piscou-afundou-loja` e atualizar todos os caminhos.
2. Headers de tema/plugin, text domain, `Template: hello-elementor`, tags, README.
3. Prefixos PHP/JS/CSS/options (`PA_`, `pa_`, `piscou-afundou`, slugs de páginas, transients). Cuidado com a opção `pa_loja_setup_concluido` usada no deploy: ela deve ganhar o prefixo novo e os scripts SSH devem consultar o nome novo.
4. `setup/configuracao-woocommerce.php`: categorias, atributos, textos de log — nicho vapor.
5. Skills `.cursor/skills/wp-deploy-*` e `wp-performance-*`: nomes e descrição para Vapor Hub; deploy continua o mesmo fluxo (lftp + SSH), mas **sem** credenciais/host da Gama da Pesca.
6. Testes: fixtures, strings, seletores e comentários. Manter a suíte passando no que for independente de nicho; ajustar o que quebrar por rename.
7. README da raiz e `wp-*/README.md`: produto Vapor Hub, e-commerce de vapor, New Alliance.
8. Integração Tiny: manter funcionamento; rebrand de slugs/prefixos/meta (`_pa_tiny_*` → prefixo novo, com cuidado para ambientes futuros); inverter a narrativa de origem do cadastro; atualizar `docs/catalogo-tiny.md` se o mapa de arquivos mudar.
9. Atualizar `docs/` deste repo: o contexto pode permanecer, mas registre o que você de fato renomeou (mapa antigo → novo) e o estado da ponte Tiny → loja (o que já puxa vs o que ainda não cria produto).

Não é necessário criar o `.env.wp-deploy` real. Só o example com variáveis vazias/placeholders (`THEME_SLUG=vapor-hub`, `PLUGIN_SLUG=vapor-hub-loja`, host vazio).

Ao final, liste o mapa de nomenclatura (pasta, slug, prefixo, opção de setup, metas Tiny) e o que ficou de propósito para o usuário informar depois (domínio do piloto, FTP/SSH, conta Tiny, recorte de produtos/categorias a importar, se comunidade vira grupo VIP, se B2B/revenda permanece na home).

## Fora de escopo agora

- Commit/push (só se o usuário pedir)
- Deploy
- Importar o catálogo Woo da CIA do Vapor / POD & VAPE (loja → loja)
- Conectar na conta Tiny real e puxar o catálogo de produção
- Abrir repositório GitHub novo
- Mexer na VPS `5.161.49.209`

---

Fim do prompt.

# Produto — Envio, peso e dimensões (escopo de análise)

Documento de estudo para expandir o cadastro logístico de produtos no plugin **Minha Loja**, alinhando **WooCommerce**, **Correios** e **Tiny ERP**.

**Status:** análise / escopo — **sem implementação**  
**Data do estudo:** 2025-06-25  
**Versão do plugin na análise:** 3.9.5  
**Produto de referência:** pod variável (sabor / puffs / nicotina) — o Tiny é o hub; a loja só espelha.

---

## 1. Objetivo

Permitir cadastrar e sincronizar **dados logísticos por produto** (peso, dimensões, volumes, embalagem) de forma que:

1. O **frete Correios** (Sedex, PAC etc.) calcule corretamente no checkout.
2. O **Tiny ERP** receba e devolva os mesmos dados no cadastro do produto.
3. O lojista configure tudo **no Minha Loja**, sem depender do wp-admin nativo do WooCommerce.

---

## 2. Fora do escopo (por enquanto)

- Regras de frete global (zonas, tabelas Correios, prazos) — responsabilidade do WooCommerce + plugin Correios.
- Cubagem avançada / múltiplos pacotes por pedido.
- Integração com transportadoras além dos Correios.
- Alterar preço de venda com base em peso (peso afeta **frete**, não preço do produto).

---

## 3. Contexto do negócio

Campos como **peso bruto/líquido**, **largura**, **altura**, **comprimento**, **nº de volumes** e **tipo de embalagem** aparecem no cadastro do Tiny em “Dimensões e peso”. São relevantes para:

| Camada | Impacto |
|--------|---------|
| **Preço do produto** | Não — preço vem de base + acréscimos das opções de personalização |
| **Frete no checkout** | **Sim** — plugin Correios usa peso e dimensões do item no carrinho |
| **Tiny / NF / expedição** | **Sim** — peso, volumes e embalagem entram na logística e na nota |

Se peso/dimensões estão vazios, o frete pode usar **padrão global** do WooCommerce/Correios (se configurado) ou calcular **de forma incorreta/inconsistente**. Isso afeta o **total do pedido** (produto + frete).

**Configuração correta:** **por produto** (e, em casos específicos, por variação). Configuração **global** serve apenas como **fallback** quando o produto não informar dados.

---

## 4. Estado atual (baseline)

### 4.1 WooCommerce / setup

- Unidades configuradas no setup: **kg** (`woocommerce_weight_unit`) e **cm** (`woocommerce_dimension_unit`).
- Plugin **Correios for WooCommerce** previsto no tema (`woocommerce-correios`).
- Campos nativos: `weight`, `length`, `width`, `height` no produto pai e em cada variação.

### 4.2 Formulário Minha Loja (`admin/views/produtos/form.php`)

| Campo | Produto simples | Produto personalizável (variável) |
|-------|-----------------|-------------------------------------|
| Peso (kg) | Visível em “Preço e estoque” | **Oculto** — seção `#vh-secao-simples` desabilitada pelo toggle |
| Largura / Altura / Comprimento | Não existe | Não existe |
| Nº volumes | Não existe | Não existe |
| Tipo / embalagem | Não existe | Não existe |

O campo **Peso** só aparece quando o toggle “Produto personalizável” está **desligado**. Produtos variáveis com muitas combinações **não têm onde informar peso/dimensões** no painel.

### 4.3 Serviço de produtos (`VH_Products_Service`)

- `set_weight()` só é chamado para produto **simples** (`if ( $produto->is_type( 'simple' ) )`).
- Não há persistência de `length`, `width`, `height`.

### 4.4 Formato canônico Tiny (`VH_Tiny_Map`)

- `wc_para_canonico()` exporta apenas `'peso' => (float) $produto->get_weight()`.
- Não lê `get_length()`, `get_width()`, `get_height()`.
- Hash de sync inclui `peso`, mas não dimensões.

### 4.5 Driver Tiny API v3 (`class-vh-tiny-driver-v3.php`)

**Envio loja → Tiny** — só peso, e só se `> 0`:

```php
$peso = (float) ( $canonico['peso'] ?? 0 );
if ( $peso > 0 ) {
    $payload['dimensoes'] = [
        'pesoBruto'   => $peso,
        'pesoLiquido' => $peso,
    ];
}
```

**Não envia:** `largura`, `altura`, `comprimento`, `quantidadeVolumes`, `embalagem`.

**Variações** (`variacao_canonico_para_v3`): SKU, grade, preço, estoque — **sem** peso/dimensões.

**Retorno Tiny → loja** (`resposta_para_canonico`):

- Lê `dimensoes.pesoBruto` → campo canônico `peso`.
- Ignora largura, altura, comprimento, volumes, embalagem.

**Sync financeira** (`tiny_para_wc_financeiro`): preço e estoque apenas — **peso não entra** na atualização passiva.

**Importação/criação** (`canonico_para_wc_criar`): grava `peso` no WC se `> 0` — somente fluxo de criação a partir do Tiny.

### 4.6 UI de mapeamento Tiny (`VH_Tiny_Mapping_Service::preview_campos_produto`)

Único campo logístico documentado na UI:

| Campo loja | Campo Tiny |
|------------|------------|
| Peso | `pesoBruto` |

### 4.7 Verificação em produção (Nova Mira + API Tiny)

**Produto #194 — WooCommerce:**

| Campo | Valor |
|-------|-------|
| Tipo | `variable` |
| Peso pai | vazio |
| Largura / Altura / Comprimento pai | vazios |
| Variação #195 — peso/dimensões | vazios |
| `_vh_tiny_id` | 984634412 |

**Produto #194 — Tiny (`GET produtos/984634412`):**

```json
"dimensoes": {
  "embalagem": { "id": null, "tipo": 0, "descricao": "" },
  "largura": 0,
  "altura": 0,
  "comprimento": 0,
  "diametro": 0,
  "pesoLiquido": 0,
  "pesoBruto": 0,
  "quantidadeVolumes": 0
}
```

Conclusão: Tiny e loja estão **alinhados em zero** — nenhum dado foi perdido; simplesmente **nunca foi preenchido nem sincronizado**.

---

## 5. Lacunas (mapa técnico)

```
Tiny API                    Canônico PA          WooCommerce           Form Minha Loja
─────────────────────────────────────────────────────────────────────────────────────
pesoBruto / pesoLiquido  →  peso              →  weight              →  só simples
largura                  →  (não existe)      →  width               →  não
altura                   →  (não existe)      →  height              →  não
comprimento              →  (não existe)      →  length              →  não
quantidadeVolumes        →  (não existe)      →  meta custom?        →  não
embalagem / tipo         →  (não existe)      →  meta custom?        →  não
(variação) peso/dim      →  (não existe)      →  por variation       →  não
```

---

## 6. Mapeamento proposto (campos alvo)

| Tiny (`dimensoes`) | WooCommerce | Onde cadastrar | Prioridade |
|--------------------|-------------|----------------|------------|
| `pesoBruto` | `weight` (produto pai) | Form Minha Loja | **P0** |
| `pesoLiquido` | meta `_vh_peso_liquido` ou = bruto | Form (opcional) | P2 |
| `largura` | `width` | Form Minha Loja | **P0** |
| `altura` | `height` | Form Minha Loja | **P0** |
| `comprimento` | `length` | Form Minha Loja | **P0** |
| `quantidadeVolumes` | meta `_vh_volumes` | Form Minha Loja | P1 |
| `embalagem` (tipo/descrição) | meta `_vh_embalagem_*` | Form (select) | P1 |

**Unidades fixas na UI:** kg e cm (consistente com setup WooCommerce).

---

## 7. Decisão central: produto pai vs variação

### Recomendação default (fase 1)

**Logística no produto pai** para produtos personalizáveis em que a combinação só muda cor/modelo visual, não o volume físico.

- Exemplo de pod variável: mesma caixa para qualquer combinação → **1 peso + 1 caixa no pai**.
- WooCommerce herda peso/dimensões do pai na variação quando a variação não define os seus (comportamento nativo).

### Fase 2 (se necessário)

Override por variação ou acréscimo de peso por opção de personalização quando o modelo altera peso/tamanho real (ex.: kit grande vs pequeno, Cilindro +50 g).

### Pergunta em aberto

> Na Vapor Hub, alguma opção de personalização muda **peso ou tamanho da embalagem** de forma relevante, ou é sempre o mesmo produto físico?

- **Sempre igual** → fase 1 só no pai (mais simples).
- **Às vezes muda** → planejar override por opção ou variação na fase 2.

---

## 8. UX proposta (expandir o form existente)

Nova seção **`Envio e dimensões`**, **independente** do toggle simples/variável:

```
┌─ Envio e dimensões ─────────────────────────────┐
│ Peso bruto (kg)     [ 0,280 ]                   │
│ Peso líquido (kg)   [ 0,280 ]  (opcional, P2)   │
│ Largura (cm)        [ 12,0  ]  Altura [ 8,0 ]   │
│ Comprimento (cm)    [ 15,0  ]                   │
│ Nº de volumes       [ 1     ]  (P1)             │
│ Tipo embalagem      [ Pacote/Caixa ▼ ]  (P1)    │
│ ℹ Usado no frete Correios e enviado ao Tiny.    │
└─────────────────────────────────────────────────┘
```

**Motivos:**

- Peso hoje está preso à seção “Preço e estoque” do produto simples — personalizáveis ficam sem campo.
- Logística não é preço; bloco dedicado visível nos dois tipos de produto.

**Posição sugerida no layout:** entre “Tipo de produto” e “Opções de personalização”.

---

## 9. Expansão técnica (sobre o que já existe)

### 9.1 Formato canônico (`VH_Tiny_Map`)

Expandir `wc_para_canonico()` e fluxos `canonico_para_wc_*`:

```php
'envio' => [
    'peso_bruto'     => float,
    'peso_liquido'   => float|null,  // P2
    'largura'        => float,
    'altura'         => float,
    'comprimento'    => float,
    'volumes'        => int,          // P1
    'embalagem_tipo' => string|int,   // P1
]
```

Decidir na implementação: manter `peso` legado em paralelo ou migrar tudo para `envio`.

### 9.2 `VH_Products_Service`

- Salvar bloco `envio` para **simples e variable**.
- Variable: gravar no **produto pai** (`WC_Product_Variable`).

### 9.3 Driver Tiny v3

Expandir `canonico_para_v3()` e `resposta_para_canonico()` com bloco `dimensoes` completo.

### 9.4 Sync bidirecional

| Direção | Hoje | Proposto |
|---------|------|----------|
| Loja → Tiny (push) | Só peso bruto | Bloco `dimensoes` completo (P0) |
| Tiny → Loja (pull criação) | Só peso | Bloco completo |
| Tiny → Loja (pull financeiro) | Ignora peso | Incluir `envio` no hash e atualização |
| Hash de sync | Só `peso` | Incluir campos P0 |

### 9.5 Correios

O plugin Correios lê peso/dimensões do **item do carrinho**. Com dados no pai WC, o checkout passa a calcular Sedex/PAC com valores reais — **sem código extra no Minha Loja**, desde que os campos nativos WC estejam preenchidos.

### 9.6 Arquivos prováveis na implementação

| Arquivo | Mudança |
|---------|---------|
| `admin/views/produtos/form.php` | Nova seção “Envio e dimensões” |
| `admin/js/app.js` | Serializar campos no save REST |
| `includes/services/class-vh-products-service.php` | Persistir envio (simple + variable) |
| `includes/services/class-vh-tiny-map.php` | Canônico + hash + pull |
| `includes/integrations/tiny/class-vh-tiny-driver-v3.php` | Payload `dimensoes` completo |
| `includes/integrations/tiny/class-vh-tiny-driver-v2.php` | Paridade se v2 ainda ativo |
| `includes/services/class-vh-tiny-mapping-service.php` | Preview de campos |
| `includes/rest/class-vh-rest-products.php` | Aceitar campos no body (se necessário) |

---

## 10. Fases sugeridas

### Fase A — Análise / validação (atual)

- [ ] Confirmar: logística sempre no pai ou precisa por variação?
- [ ] Confirmar: peso líquido ≠ bruto na prática ou pode ser igual?
- [ ] Listar valores de `embalagem.tipo` do Tiny (enum da API) para montar o select.
- [ ] Preencher manualmente 2–3 produtos no Tiny e validar resposta da API.
- [ ] Testar checkout Correios com produto sem peso (fallback ou erro).

### Fase B — MVP loja (P0)

- Seção “Envio e dimensões” no form (simples + variável).
- Salvar em campos nativos WC (`weight`, `length`, `width`, `height`).
- Push Tiny com `dimensoes` completo (peso + L×A×C).
- Pull na criação e na sync de conteúdo.

### Fase C — Completude (P1)

- Volumes + tipo embalagem (meta + sync Tiny).
- Hash/sync financeiro incluindo envio.
- Tela de mapeamento Tiny atualizada.

### Fase D — Avançado (P2, se necessário)

- Peso líquido separado.
- Override por variação ou acréscimo por opção de personalização.
- Defaults globais na config da loja (“caixa padrão quando produto não informar”).

**Versão alvo sugerida:** 3.10.0 (Fase B)

---

## 11. Riscos e mitigações

| Risco | Mitigação |
|-------|-----------|
| Produtos legados sem dados | Campos opcionais; aviso no form se vazio; não sobrescrever com zero |
| Tiny sobrescrever com zero | Só enviar campos preenchidos; no pull, não apagar WC se Tiny vier 0 e loja tiver valor |
| 60 variações × peso individual | Evitar na fase 1; usar pai |
| Unidade errada (g vs kg) | Labels `(kg)` / `(cm)`; validar no save |
| Sync passiva quebra cadastro | Preservar padrão “legado” já usado em personalização — save passivo não força migração |

---

## 12. Critérios de aceite (implementação)

1. Produto personalizável #194 pode ter peso 0,280 kg e caixa 12×8×15 cm no Minha Loja.
2. “Salvar e enviar ao Tiny” grava os mesmos valores na tela “Dimensões e peso” do Tiny.
3. Puxar do Tiny preenche de volta o form da loja.
4. Carrinho com esse produto calcula frete Correios usando peso/dimensões informados.
5. Sync passiva (sem editar) não zera dados existentes.

---

## 13. Perguntas abertas

1. **Peso líquido** importa para vocês ou bruto basta (Correios usa peso para cotação)?
2. **Embalagem/volumes** são obrigatórios no Tiny para NF ou só informativos?
3. Desejam **default global** (“caixa padrão 20×15×10, 0,3 kg”) quando produto não preencher?
4. Algum produto muda **fisicamente** entre opções de personalização?
5. Prioridade: **loja primeiro** (frete Correios) ou **Tiny primeiro** (cadastro ERP)?

---

## 14. Referências no código (estado v3.9.5)

| Tópico | Arquivo |
|--------|---------|
| Form peso (só simples) | `admin/views/produtos/form.php` |
| Toggle oculta seção simples | `admin/js/app.js` → `aplicarTipoProduto()` |
| Save peso só simples | `includes/services/class-vh-products-service.php` |
| Canônico WC → Tiny | `includes/services/class-vh-tiny-map.php` → `wc_para_canonico()` |
| Payload Tiny v3 | `includes/integrations/tiny/class-vh-tiny-driver-v3.php` → `canonico_para_v3()` |
| Import Tiny → canônico | `class-vh-tiny-driver-v3.php` → `resposta_para_canonico()` |
| Preview mapeamento | `includes/services/class-vh-tiny-mapping-service.php` |
| Unidades WC no setup | `setup/configuracao-woocommerce.php` |
| Plugin Correios | `themes/vapor-hub/functions.php` |

---

## 15. Resumo

Expandir o **canônico + form + driver v3** existentes para cobrir o bloco `dimensoes` completo do Tiny, gravando nos **campos nativos do WooCommerce no produto pai**, com seção única no form visível para produtos simples e personalizáveis. Fase 1 focada em **peso + largura × altura × comprimento** para destravar frete Correios e sync Tiny.

Próximo passo: fechar as **perguntas da seção 13** e converter **Fase B** em tarefas de implementação.

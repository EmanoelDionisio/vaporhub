# Catálogo: Tiny ERP é o hub — a loja nasce vazia

Decisão de produto do **Vapor Hub**. Vale para o piloto e para as próximas lojas deste nicho.

## Fonte da verdade

O **Tiny ERP** já é o hub de produtos de todas as lojas sob a mesma administração. Estoque, SKU, preço e cadastro vivem lá. Cada WooCommerce é um **canal**, não a origem do catálogo.

Por isso o piloto **não** deve:

- exportar/importar produtos WooCommerce da CIA do Vapor (nem de outra loja) para a loja nova;
- tratar CSV do WordPress como migração real;
- assumir que o lojista vai recadastrar 8 mil itens na mão no Woo.

O piloto **deve**:

1. Subir a loja **do zero** (WordPress + tema + Minha Loja + WooCommerce configurado).
2. Criar na loja só a **estrutura**: páginas, categorias/atributos de vitrine, mapeamento Tiny ↔ Woo.
3. **Importar ou sincronizar** os produtos que já existem no Tiny (os que pertencem a essa operação).
4. Daí em diante, manter o Tiny como hub: a loja recebe cadastro/estoque/preço; não vira outro cadastro mestre.

A CIA do Vapor continua útil como **referência de UX, categorias e atributos**. Não é dump de dados.

## O que o código herdado faz hoje (e por que isso muda)

No Piscou Afundou a integração Tiny foi desenhada ao contrário:

- o produto **nasce em Minha Loja** e **desce** para o ERP;
- o pull Tiny → Woo de item **sem par local foi desligado de propósito**;
- `PA_Tiny_Sync_Service::importar_produto_de_tiny()` **não cria** produto: devolve erro `pa_tiny_sem_vinculo_local` e o log diz que o cadastro é sempre da loja;
- o caminho inverso que existe de verdade é **estoque e preço** de item **já vinculado** (casamento por SKU / `_pa_tiny_id`).

Isso fez sentido numa conta Tiny compartilhada com outras operações: puxar o ERP inteiro publicaria na vitrine produtos de outro negócio.

No Vapor Hub o ERP continua compartilhado entre lojas, mas a **origem do catálogo da loja nova é o Tiny**. A restrição certa não é “nunca criar produto a partir do ERP”. É **nunca despejar o Tiny inteiro** numa loja. Importar só o recorte mapeado (categorias vinculadas, situação ativa, SKU válido, etc.).

## Como o piloto deve funcionar (previsto)

```text
Tiny ERP (hub de todas as lojas)
        │
        │  pull / import filtrado (SKU, categoria mapeada, ativo)
        ▼
Loja Woo vazia (Vapor Hub)
        │
        │  estoque, preço, webhook, reconciliação
        ▼
Tiny continua mestre
```

- **Join key:** SKU. Sem SKU no Tiny, o item não entra.
- **Categorias:** existem na loja para vitrine; o mapeamento Tiny ↔ `product_cat` decide o que pode ser importado.
- **Variáveis:** o nicho vapor é pesado em variação (sabor, puffs, nicotina). O pull de produto variável no código atual é limitado (não cria pai/variações novas). Isso tem de estar no radar: um pod com 12 sabores não pode depender de cadastro manual no Woo.
- **Não empurrar a loja vazia para o Tiny.** Push em massa a partir de Woo sem catálogo local polui o ERP. No piloto, o sentido inicial é Tiny → loja.
- **CSV de `setup/`:** só amostra para teste/dev. Não é o pipeline de produção.

## Recorte (obrigatório)

Como o mesmo Tiny atende várias lojas, o import precisa de filtro. Não basta “sincronizar agora”. Prever, no mínimo:

- só produtos com categoria Tiny já mapeada (ou explicitamente escolhidos);
- só situação ativa no ERP;
- casamento por SKU; não criar duplicata se o SKU já existir na loja;
- não apagar produto no Tiny em hipótese alguma durante o import.

Credenciais da conta Tiny do piloto, depósito, lista de preço e o recorte exato **o usuário passa depois**. A arquitetura já tem que caber isso.

## Arquivos-âncora (prefixo VH_)

| Papel | Arquivo |
|---|---|
| Orquestração sync | `wp-vapor-hub/plugins/vapor-hub-loja/includes/services/class-vh-tiny-sync-service.php` |
| Import com recorte | `importar_produto_de_tiny()` no mesmo arquivo |
| Fila `produto_pull` / `estoque_pull` | `includes/class-vh-tiny-queue.php` |
| Modos (receber catálogo / estoque) | `includes/integrations/tiny/class-vh-tiny.php` |
| SKU da loja (`VH-`) | `includes/services/class-vh-tiny-sku.php` |
| Mapeamento de categorias | `includes/services/class-vh-tiny-mapping-service.php` |
| UI | `admin/views/integracoes/tiny.php` |
| Limitações conhecidas | `wp-vapor-hub/docs/tiny-integracao-fase-2.md` (pull de variáveis) |

## Estado da ponte Tiny → loja (após o rebrand)

O que **já puxa** em item vinculado: estoque, preço, webhook de ID conhecido, reconciliação só no recorte local (não pagina o ERP inteiro).

O que **já cria** sem par local: produto **simples**, se SKU tem prefixo da loja (`VH-`), situação ativa e categoria Tiny mapeada na Woo. Push loja → Tiny fica suprimido nesse create. Sem DELETE no Tiny.

O que **ainda não cria**: produto **variável** (sabor / puffs / nicotina). Devolve `vh_tiny_variavel_pendente`. Próximo marco.

O que **nunca** faz neste piloto: sincronizar a conta Tiny inteira; importar Woo da CIA; CSV como migração.

Receber catálogo e receber estoque/preço continuam **desligados por padrão**. Ligar só com recorte mapeado.

# Elementor Pro — Guia de Configuração do MVP

**Projeto:** Vapor Hub — E-commerce de Pesca Esportiva  
**Desenvolvido por:** [New Alliance Tecnologia](https://newalliance.tech)  
**Versão:** 1.0.0  
**Última atualização:** Abril 2026

> **Este documento é um guia de referência técnica para o desenvolvedor.**  
> Ele descreve como configurar o Elementor Pro para reproduzir fielmente o MVP.  
> Não é um template exportável — é um roteiro de construção.

---

## Sumário

1. [Kit Global (Global Settings)](#1-kit-global-global-settings)
2. [Cabeçalho (Theme Builder › Header)](#2-cabeçalho-theme-builder--header)
3. [Rodapé (Theme Builder › Footer)](#3-rodapé-theme-builder--footer)
4. [Página Inicial (Page Template)](#4-página-inicial-page-template)
5. [Arquivo da Loja (Product Archive)](#5-arquivo-da-loja-product-archive)
6. [Produto Individual (Single Product)](#6-produto-individual-single-product)
7. [Minha Conta](#7-minha-conta)
8. [Página 404](#8-página-404)
9. [Popup Revenda](#9-popup-revenda)
10. [Contato](#10-contato)

---

## 1. Kit Global (Global Settings)

Acesse **Elementor › Configurações › Kit Global** (ou clique no ícone de hambúrguer dentro do editor › Site Settings).

### 1.1 Cores do Sistema

| Token             | Hex       | Uso                                          |
| ----------------- | --------- | -------------------------------------------- |
| **Primária**      | `#c8f542` | Botões, links ativos, destaques, badges      |
| **Secundária**    | `#d6ff6a` | Hover de botões, gradientes, acentos escuros |
| **Texto**         | `#f4f1ea` | Títulos, parágrafos, corpo de texto           |
| **Texto Sec.**    | `#9a9a92` | Subtítulos, labels, textos auxiliares         |
| **Fundo**         | `#0a0a0b` | Background geral do site (body)              |
| **Superfície**    | `#ffffff` | Cards, modais, áreas de conteúdo             |
| **Borda**         | `#2a2a2c` | Divisores, bordas de cards e inputs          |

Configure cada cor no painel **Global Colors** para que fiquem disponíveis como variáveis em todo o site.

### 1.2 Tipografia Global

| Slot              | Família              | Pesos disponíveis          |
| ----------------- | -------------------- | -------------------------- |
| **Primária**      | Plus Jakarta Sans    | 400, 500, 600, 700, 800   |
| **Secundária**    | Plus Jakarta Sans    | 400, 500, 600, 700, 800   |

Em **Global Fonts**, defina ambos os slots com **Plus Jakarta Sans** importada via Google Fonts. Os pesos acima são usados no MVP — certifique-se de que estão habilitados.

Sugestão de escala tipográfica:

| Elemento | Tamanho Desktop | Peso | Line-height |
| -------- | --------------- | ---- | ----------- |
| H1       | 48–56px         | 800  | 1.1         |
| H2       | 36–40px         | 700  | 1.2         |
| H3       | 24–28px         | 700  | 1.3         |
| H4       | 20px            | 600  | 1.4         |
| Body     | 16px            | 400  | 1.6         |
| Small    | 14px            | 500  | 1.5         |

### 1.3 Layout Global

| Propriedade                 | Valor        |
| --------------------------- | ------------ |
| Container padrão (max-width)| **1280px**   |
| Espaçamento de seção (desktop) | **64px** (padding-top e padding-bottom) |
| Espaçamento de seção (mobile)  | **40px**  |
| Gap padrão entre colunas    | 24px         |

---

## 2. Cabeçalho (Theme Builder › Header)

**Caminho:** Elementor › Theme Builder › Header › Adicionar Novo

### 2.1 Comportamento

- **Sticky:** Sim — fixo no topo ao rolar a página
- **Fundo:** `#ffffff` com `backdrop-filter: blur(12px)` e opacidade ~95%
- **Borda inferior:** `1px solid #2a2a2c`
- **Z-index:** 1000
- **Transição:** suavizar background ao rolar (classe `scrolled` via JS se necessário)

### 2.2 Estrutura — Layout de 3 Colunas

Usar um **Container** com `display: flex`, `justify-content: space-between`, `align-items: center`.

#### Coluna Esquerda — Logo

| Propriedade       | Valor                                    |
| ----------------- | ---------------------------------------- |
| Widget            | Image                                    |
| Imagem            | Logo da marca (SVG ou PNG transparente)  |
| Altura            | **96px**                                 |
| Link              | URL da home (`/`)                        |
| Alt text          | "Vapor Hub — Pesca Esportiva"       |

#### Coluna Central — Navegação

| Propriedade       | Valor                                              |
| ----------------- | -------------------------------------------------- |
| Widget            | Nav Menu (Elementor Pro)                           |
| Menu selecionado  | Menu principal do WordPress                        |
| Itens             | Início, Loja, Acessórios, Comunidade, Contato      |
| Estilo do texto   | `font-size: 15px`, `font-weight: 600`, cor `#f4f1ea` |
| Hover             | Cor `#c8f542`, sem underline                       |
| Ativo             | Cor `#c8f542`, `font-weight: 700`                  |
| Alinhamento       | Centro                                             |
| Pointer           | Nenhum (ou underline sutil)                        |

#### Coluna Direita — Ações

Três widgets **Icon** ou botões de ícone lado a lado:

| Ícone     | Ação                                | Detalhes                                         |
| --------- | ----------------------------------- | ------------------------------------------------ |
| 🔍 Busca   | Abre campo de busca overlay/dropdown | Widget Search Form ou ícone com toggle JS        |
| 🛒 Carrinho | Mini-cart WooCommerce               | Widget Menu Cart (Elementor Pro) com badge de quantidade |
| 👤 Conta   | Link para Minha Conta               | Ícone com link para `/minha-conta/`              |

- Ícones: tamanho 20–22px, cor `#f4f1ea`, hover `#c8f542`
- Badge do carrinho: círculo `#c8f542` com texto branco

### 2.3 Menu Mobile (< 1024px)

| Propriedade         | Valor                                     |
| ------------------- | ----------------------------------------- |
| Breakpoint          | 1024px (tablet)                           |
| Tipo                | Hamburger                                 |
| Ícone hamburger     | 3 linhas, cor `#f4f1ea`, 24px             |
| Menu offcanvas      | Sidebar direita, fundo `#ffffff`          |
| Animação            | Slide da direita                          |
| Itens no offcanvas  | Menu completo + ícones de busca/conta/carrinho |
| Fechar              | Ícone X no canto superior direito         |

### 2.4 Condição de Exibição

- **Incluir:** Entire Site (Todas as páginas)

---

## 3. Rodapé (Theme Builder › Footer)

**Caminho:** Elementor › Theme Builder › Footer › Adicionar Novo

### 3.1 Layout Principal — 4 Colunas

Container de **fundo escuro** (`#f4f1ea` ou gradiente dark) com texto claro.

**Grid responsivo:**

| Breakpoint | Colunas |
| ---------- | ------- |
| Desktop    | 4       |
| Tablet     | 2       |
| Mobile     | 1       |

#### Coluna 1 — Marca

- **Logo:** versão branca/clara, altura ~64px
- **Descrição:** parágrafo curto sobre a loja (cor `#9a9a92` ou branco com opacidade)
- **Redes sociais:** widget Social Icons
  - Formato: círculos
  - Cor padrão: branco com fundo transparente ou `rgba(255,255,255,0.1)`
  - Hover: fundo `#c8f542`, ícone branco
  - Redes: Instagram, Facebook, YouTube (ajustar conforme a marca)

#### Coluna 2 — Links da Loja

**Título:** "Loja" — `font-weight: 700`, cor branca, `margin-bottom: 16px`

Lista de links (widget Icon List sem ícone, ou lista simples):

- POD Descartável
- POD Recarregável
- e-Líquidos
- Acessórios
- Nicotina oral
- Vape

Estilo: cor `rgba(255,255,255,0.7)`, hover `#c8f542`

#### Coluna 3 — Links de Suporte

**Título:** "Suporte"

Links:

- Central de Ajuda
- Trocas e Devoluções
- Política de Envio
- Rastrear Pedido
- Área do Revendedor

Mesmo estilo da coluna 2.

#### Coluna 4 — Fale Conosco

**Título:** "Fale Conosco"

Usar widget **Icon List** com ícones à esquerda:

| Ícone             | Texto                            |
| ----------------- | -------------------------------- |
| 📞 Phone          | (XX) XXXX-XXXX                   |
| ✉️ Envelope       | contato@piloto.example     |
| 📍 Map Marker     | Cidade, Estado — Brasil          |

Ícones em `#c8f542`, texto em branco/opaco.

### 3.2 Barra Inferior

Seção separada (ou inner section) abaixo das 4 colunas:

- **Borda superior:** `1px solid rgba(255,255,255,0.1)`
- **Layout:** flex, `justify-content: space-between`
- **Esquerda:** `© 2026 Vapor Hub. Todos os direitos reservados.`
- **Direita:** Links "Termos de Uso" e "Política de Privacidade", separados por `|`
- **Cor do texto:** `rgba(255,255,255,0.5)`
- **Font-size:** 13–14px

### 3.3 Condição de Exibição

- **Incluir:** Entire Site (Todas as páginas)

---

## 4. Página Inicial (Page Template)

Criar uma **Page** com template Elementor Full Width (ou Canvas, se não precisar de header/footer no template — mas como já temos Theme Builder, usar Full Width).

### 4.1 Hero

**Tipo:** Seção full-width (100vw) — pode usar Elementor **Slides** widget ou **Custom Section** com background.

| Propriedade           | Valor                                                    |
| --------------------- | -------------------------------------------------------- |
| Largura               | Full Width (esticado)                                    |
| Altura                | `min-height: 600px` desktop, `400px` mobile              |
| Background            | Imagem de alta qualidade (vapor)               |
| Overlay               | Gradiente escuro: `linear-gradient(135deg, rgba(28,20,13,0.85), rgba(28,20,13,0.4))` |
| Conteúdo              | Centralizado vertical e horizontalmente                  |

**Conteúdo interno (container centralizado, max-width: 800px):**

1. **Badge:** texto "Nova Coleção 2026" em tag/span
   - Background: `rgba(242,127,13,0.15)`
   - Cor: `#c8f542`
   - Padding: `8px 20px`
   - Border-radius: `50px`
   - Font-size: `14px`, `font-weight: 600`

2. **Título H1:** `A REVOLUÇÃO NA SUA PESCARIA`
   - Cor: `#ffffff`
   - Font-size: `56px` desktop, `32px` mobile
   - Font-weight: `800`
   - Text-transform: `uppercase`

3. **Subtítulo:** texto descritivo abaixo do H1
   - Cor: `rgba(255,255,255,0.8)`
   - Font-size: `18px`, `font-weight: 400`
   - Max-width: `600px`, centralizado

4. **Botões (2):**

| Botão        | Estilo          | Cor fundo   | Cor texto | Border-radius |
| ------------ | --------------- | ----------- | --------- | ------------- |
| Ver Coleção  | Primário/sólido | `#c8f542`   | `#ffffff` | 12px          |
| Saiba Mais   | Outline/ghost   | transparente| `#ffffff` | 12px          |

- Padding botões: `14px 32px`
- Font-weight: `600`
- Hover do primário: `#d6ff6a`
- Hover do outline: fundo `rgba(255,255,255,0.1)`

### 4.2 Barra de Benefícios

Logo abaixo do hero. Duas abordagens:

**Opção A — Shortcode:**
```
[vh_beneficios]
```
Renderizado pelo plugin/tema filho. Usar widget **Shortcode** do Elementor.

**Opção B — Manual no Elementor:**

Seção com 4 colunas (Inner Section ou Flexbox Container):

| Ícone               | Título                    | Descrição              |
| -------------------- | ------------------------- | ---------------------- |
| 🚚 Truck            | Frete Grátis              | Acima de R$ 199        |
| 🔒 Shield           | Compra Segura             | Dados protegidos       |
| 🔄 Refresh          | Troca Garantida           | Até 30 dias            |
| 💬 Headphones       | Atendimento Especializado | Suporte no WhatsApp    |

- Background da seção: `#ffffff` ou `#0a0a0b`
- Borda: `1px solid #2a2a2c`
- Ícone: cor `#c8f542`, 24px
- Título: `font-weight: 600`, `14px`
- Descrição: `font-weight: 400`, `13px`, cor `#9a9a92`
- Gap entre colunas: `24px`
- Padding da seção: `20px 0`

### 4.3 Categorias em Destaque

**Opção A — Shortcode:**
```
[vh_categorias]
```

**Opção B — Loop Grid:**

Usar widget **Loop Grid** com query na taxonomy `product_cat`. Criar um **Loop Template** para cada card de categoria:

- Imagem de fundo da categoria
- Overlay gradiente escuro na parte inferior
- Nome da categoria sobre o overlay, em branco
- Border-radius: `16px`
- Hover: leve zoom na imagem (`transform: scale(1.05)`, `transition: 0.3s`)
- Grid: 4 colunas desktop, 2 tablet, 1 mobile

Título da seção: **"Encontre por Categoria"** — H2, centralizado

### 4.4 Produtos em Destaque

| Propriedade         | Valor                                              |
| ------------------- | -------------------------------------------------- |
| Widget              | Products (WooCommerce by Elementor Pro)            |
| Filtro              | **Featured** (Em Destaque)                         |
| Colunas             | 4 desktop, 2 tablet, 1 mobile                     |
| Limite              | 8 produtos                                         |
| Ordenação           | Mais recentes ou personalizada                     |
| Paginação           | Não                                                |

Título da seção: **"Destaques da Marca"** — H2, centralizado

Para estilizar os cards de produto de acordo com o MVP:
- Border-radius: `16px`
- Sombra: `0 1px 3px rgba(0,0,0,0.06)`
- Hover: sombra mais pronunciada, leve translate-Y
- Preço com desconto: preço antigo riscado, novo em `#c8f542` e `font-weight: 700`
- Badge "Promoção": background `#c8f542`, texto branco, canto superior esquerdo
- Botão "Adicionar ao Carrinho": aparece no hover, fundo `#c8f542`

> **Dica:** Use CSS customizado no card ou crie um Loop Template dedicado para personalizar completamente a aparência dos cards de produto.

### 4.5 CTA Revenda

**Opção A — Shortcode:**
```
[vh_revenda_cta]
```

**Opção B — Seção manual:**

| Propriedade           | Valor                                               |
| --------------------- | --------------------------------------------------- |
| Background            | Gradiente escuro (`#f4f1ea` → `#2a1f14`)            |
| Shapes decorativos    | Divs absolutas com blur (`filter: blur(80px)`, cores `#c8f542` com opacidade 10–20%) |
| Overflow              | Hidden                                               |
| Padding               | `80px 0`                                             |

Conteúdo (container centralizado):

- Badge: "Programa de Revenda" — estilo similar ao badge do hero
- Título H2: "Seja um Revendedor Vapor Hub"
- Subtítulo: parágrafo descritivo, cor `rgba(255,255,255,0.7)`
- Lista de benefícios: 3 itens com ícone check, texto branco
- **Botão:** "Quero Ser Revendedor" — fundo `#c8f542`, classe CSS `.vh-abrir-revenda` (abre o popup)

### 4.6 Galeria da Comunidade

**Opção A — Shortcode:**
```
[vh_comunidade]
```

**Opção B — Galeria customizada:**

- Título: **"Comunidade Vapor Hub"** — H2, centralizado
- Subtítulo: "Novidades e conteúdo para quem já compra."
- Layout: scroll horizontal (carrossel) ou grid masonry
- Imagens: border-radius `16px`, hover com overlay e ícone de expand
- Widget sugerido: **Image Carousel** ou **Gallery** com lightbox
- Em mobile: scroll horizontal com snap

---

## 5. Arquivo da Loja (Product Archive)

**Caminho:** Elementor › Theme Builder › Product Archive › Adicionar Novo

### 5.1 Estrutura

```
┌─────────────────────────────────────────────┐
│  Breadcrumbs                                │
├────────────┬────────────────────────────────┤
│  Sidebar   │  Ordenação  |  Resultados      │
│  (Filtros) │────────────────────────────────│
│  25%       │  ┌────┐ ┌────┐ ┌────┐         │
│            │  │Prod│ │Prod│ │Prod│   75%    │
│  - Categ.  │  └────┘ └────┘ └────┘         │
│  - Preço   │  ┌────┐ ┌────┐ ┌────┐         │
│  - Aval.   │  │Prod│ │Prod│ │Prod│         │
│            │  └────┘ └────┘ └────┘         │
│            │────────────────────────────────│
│            │  Paginação                     │
└────────────┴────────────────────────────────┘
```

### 5.2 Breadcrumbs

- Widget: **Breadcrumbs** (Elementor Pro) ou shortcode WooCommerce
- Estilo: font-size `14px`, cor `#9a9a92`, separador `/`
- Margin-bottom: `24px`

### 5.3 Sidebar (25%)

Usar widget **Product Categories**, **Price Filter**, **Rating Filter** e **Active Filters** do WooCommerce:

- Background: `#ffffff`
- Border: `1px solid #2a2a2c`
- Border-radius: `16px`
- Padding: `24px`
- Título de cada filtro: `font-weight: 700`, `font-size: 16px`
- Em mobile: sidebar colapsa em botão "Filtros" que abre offcanvas ou accordion

### 5.4 Grid de Produtos (75%)

| Breakpoint | Colunas |
| ---------- | ------- |
| Desktop    | 3       |
| Tablet     | 2       |
| Mobile     | 1       |

- Widget: **Products** (WooCommerce) ou **Archive Products** (Elementor Pro)
- Estilização dos cards: mesma do item 4.4
- Barra superior: widget **WooCommerce Ordering** + contagem de resultados

### 5.5 Paginação

- Widget: **Post Navigation** / **Pagination**
- Estilo: botões com border-radius `8px`, ativo com fundo `#c8f542` e texto branco

### 5.6 Condição de Exibição

- **Incluir:** Product Archive + Product Category Archives + Product Tag Archives

---

## 6. Produto Individual (Single Product)

**Caminho:** Elementor › Theme Builder › Single Product › Adicionar Novo

### 6.1 Breadcrumbs

Mesmo estilo do arquivo da loja (seção 5.2).

### 6.2 Layout Principal — 7/5 Colunas

Container com 2 colunas: `58.33%` (7/12) para galeria e `41.66%` (5/12) para informações.

#### Coluna Esquerda — Galeria (7/12)

| Propriedade         | Valor                                    |
| ------------------- | ---------------------------------------- |
| Widget              | Product Images (Elementor Pro)           |
| Thumbnails          | Abaixo da imagem principal, em fileira   |
| Border-radius       | `16px` na imagem principal               |
| Lightbox            | Habilitado                               |
| Zoom                | Habilitado no hover                      |

#### Coluna Direita — Informações (5/12)

De cima para baixo:

1. **Marca / Categoria:**
   - Widget: Product Meta ou texto dinâmico
   - Estilo: `font-size: 14px`, cor `#c8f542`, `font-weight: 600`, `text-transform: uppercase`

2. **Nome do Produto (H1):**
   - Widget: Product Title
   - `font-size: 28–32px`, `font-weight: 800`, cor `#f4f1ea`

3. **Preço:**
   - Widget: Product Price
   - Preço regular riscado: cor `#9a9a92`, `font-size: 18px`
   - Preço com desconto: cor `#c8f542`, `font-size: 28px`, `font-weight: 700`

4. **Avaliações:**
   - Widget: Product Rating
   - Estrelas em `#c8f542`

5. **Descrição Curta:**
   - Widget: Product Short Description
   - `font-size: 15px`, cor `#f4f1ea`, `line-height: 1.6`

6. **Grade de Benefícios (2×2):**
   - 4 mini-cards em grid 2 colunas
   - Cada um com ícone + texto curto (ex.: "Frete Grátis", "Troca em 30 dias", "Compra Segura", "Atendimento Especializado")
   - Background: `#0a0a0b`, border: `1px solid #2a2a2c`, border-radius: `12px`, padding `12px`

7. **Formulário de Variações + Add to Cart:**
   - Widget: Add to Cart (Elementor Pro)
   - Seletores de variação estilizados (dropdowns ou swatches se houver plugin)
   - Botão: fundo `#c8f542`, texto branco, `font-weight: 700`, border-radius `12px`, full-width
   - Hover: `#d6ff6a`

8. **Calculadora de Frete:**
   - Shortcode do plugin de frete ou widget HTML customizado
   - Input de CEP com botão "Calcular"
   - Resultados abaixo do input

### 6.3 Tabs de Conteúdo

- Widget: **Product Data Tabs** (Elementor Pro)
- Abas: **Descrição** | **Avaliações**
- Estilo das tabs: text buttons, aba ativa com borda inferior `#c8f542` e `font-weight: 700`
- Conteúdo: tipografia padrão do body

### 6.4 Produtos Relacionados

- Widget: **Related Products** (Elementor Pro)
- Grid: 4 colunas desktop, 2 tablet, 1 mobile
- Título: "Você Também Pode Gostar"
- Estilização dos cards: mesma do item 4.4

### 6.5 Condição de Exibição

- **Incluir:** All Products (Todos os produtos individuais)

---

## 7. Minha Conta

**Caminho:** Elementor › Theme Builder › Single Page › Minha Conta (ou editar a página diretamente com Elementor)

### 7.1 Estrutura

- Widget: **My Account** (Elementor Pro / WooCommerce)
- Layout em **desktop:** tabs/menu lateral à esquerda (sidebar), conteúdo à direita
- Layout em **mobile:** abas horizontais no topo (tabs)

### 7.2 Estilização

| Elemento          | Estilo                                           |
| ----------------- | ------------------------------------------------ |
| Menu lateral      | Background `#ffffff`, border `1px solid #2a2a2c`, border-radius `16px` |
| Item ativo        | Background `#0a0a0b`, borda esquerda `3px solid #c8f542` |
| Formulários       | Inputs com border-radius `12px`, border `1px solid #2a2a2c`, padding `12px 16px` |
| Botões            | Background `#c8f542`, texto branco, border-radius `12px` |
| Labels            | `font-weight: 600`, `font-size: 14px`, cor `#f4f1ea` |

### 7.3 Condição de Exibição

- **Incluir:** Página "Minha Conta" (selecionar a página específica)

---

## 8. Página 404

**Caminho:** Elementor › Theme Builder › Error 404 › Adicionar Novo

> **Nota:** O tema filho já possui `404.php`. Se preferir usar o Elementor para gerenciar visualmente, crie o template abaixo. Caso contrário, o arquivo PHP será usado automaticamente.

### 8.1 Conteúdo

| Elemento       | Valor                                       |
| -------------- | ------------------------------------------- |
| Número grande  | **404** — `font-size: 120px`, `font-weight: 800`, cor `#c8f542` com opacidade |
| Título H1      | "Esse peixe escapou!"                       |
| Subtítulo      | "A página que você procura não existe ou foi movida." |
| Botão          | "Voltar à Home" — fundo `#c8f542`, link para `/` |

- Layout: tudo centralizado, `text-align: center`
- Altura mínima: `60vh`
- Background: `#0a0a0b`

### 8.2 Condição de Exibição

- **Incluir:** 404 Page

---

## 9. Popup Revenda

**Caminho:** Elementor › Templates › Popups › Adicionar Novo

### 9.1 Configuração do Popup

| Propriedade         | Valor                                    |
| ------------------- | ---------------------------------------- |
| Layout              | Modal centralizado                       |
| Largura             | 480px (desktop), 90vw (mobile)           |
| Border-radius       | 20px                                     |
| Background           | `#ffffff`                                |
| Overlay (backdrop)  | `rgba(28,20,13,0.6)` com `backdrop-filter: blur(8px)` |
| Animação de entrada | Fade In + Scale Up                       |
| Fechar              | Ícone X no canto superior direito + clique no overlay |

### 9.2 Gatilho (Trigger)

- **Tipo:** On Click
- **Seletor CSS:** `.vh-abrir-revenda`
- Aplicar esta classe nos botões/links que devem abrir o popup (ex.: botão no CTA Revenda, link "Área do Revendedor" no rodapé)

### 9.3 Conteúdo do Popup

| Ordem | Elemento                                         |
| ----- | ------------------------------------------------ |
| 1     | Título: "Quero Ser Revendedor" — H3, centralizado |
| 2     | Subtítulo: "Preencha seus dados e entraremos em contato" |
| 3     | Campo: **Nome Completo** — input text, obrigatório |
| 4     | Campo: **CPF ou CNPJ** — input text, obrigatório   |
| 5     | Campo: **WhatsApp** — input tel, obrigatório        |
| 6     | Botão: **Enviar** — full-width, fundo `#c8f542`    |

- Widget de formulário: **Form** (Elementor Pro) ou shortcode do WPForms/Fluent Forms
- Ação após envio: exibir mensagem de sucesso + enviar e-mail para o admin
- Estilização dos inputs: border-radius `12px`, border `1px solid #2a2a2c`, padding `12px 16px`

---

## 10. Contato

**Caminho:** Criar página "Contato" e editar com Elementor (template Full Width)

### 10.1 Título

- H1: **"Fale Conosco"**
- Subtítulo opcional: "Estamos prontos para ajudar"
- Centralizado, margin-bottom `48px`

### 10.2 Layout 2 Colunas

#### Coluna Esquerda — Cards de Contato

Empilhar 4 cards (ou inner sections):

| Ícone          | Título                | Conteúdo                          |
| -------------- | --------------------- | --------------------------------- |
| 📞 Phone       | Telefone              | (XX) XXXX-XXXX                    |
| ✉️ Envelope    | E-mail                | contato@piloto.example      |
| 📍 Map Marker  | Endereço              | Cidade, Estado — Brasil           |
| 🕐 Clock       | Horário de Atendimento| Seg–Sex, 9h às 18h                |

Estilo de cada card:
- Background: `#ffffff`
- Border: `1px solid #2a2a2c`
- Border-radius: `16px`
- Padding: `24px`
- Ícone: `#c8f542`, tamanho 24px
- Título: `font-weight: 700`
- Conteúdo: cor `#9a9a92`

#### Coluna Direita — Formulário de Contato

- Widget: **Shortcode** com o formulário do WPForms ou Fluent Forms
- Campos sugeridos: Nome, E-mail, Assunto (select), Mensagem (textarea), Botão Enviar
- Estilização herdada do CSS global dos inputs (border-radius `12px`, etc.)

```
[wpforms id="XX"]
```
ou
```
[fluentform id="XX"]
```

*(Substituir `XX` pelo ID real do formulário criado)*

### 10.3 Condição de Exibição

Não se aplica (é uma Page editada diretamente, não um template do Theme Builder).

---

## Referência Rápida — CSS Customizado Útil

Caso precise aplicar ajustes finos que o Elementor não oferece nativamente, use **Elementor › Custom CSS** (no nível do widget, seção ou em Configurações do Site › Custom CSS):

```css
/* Sticky header com blur */
.elementor-sticky--active {
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  background: rgba(255, 255, 255, 0.95) !important;
}

/* Badge de promoção nos cards de produto */
.woocommerce span.onsale {
  background-color: #c8f542;
  color: #ffffff;
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
}

/* Inputs globais */
input[type="text"],
input[type="email"],
input[type="tel"],
input[type="password"],
textarea,
select {
  border-radius: 12px !important;
  border: 1px solid #2a2a2c !important;
  padding: 12px 16px !important;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

/* Botão WooCommerce padronizado */
.woocommerce .button,
.woocommerce input.button {
  background-color: #c8f542 !important;
  color: #ffffff !important;
  border-radius: 12px !important;
  font-weight: 700 !important;
  font-family: 'Plus Jakarta Sans', sans-serif;
  transition: background-color 0.2s ease;
}

.woocommerce .button:hover,
.woocommerce input.button:hover {
  background-color: #d6ff6a !important;
}
```

---

## Checklist de Implementação

- [ ] Kit Global configurado (cores, fontes, layout)
- [ ] Header criado e testado (desktop + mobile)
- [ ] Footer criado e testado (4 colunas responsivas)
- [ ] Página Inicial montada (hero, benefícios, categorias, destaques, CTA, comunidade)
- [ ] Product Archive configurado (sidebar + grid)
- [ ] Single Product configurado (galeria + info + tabs + relacionados)
- [ ] Minha Conta estilizada
- [ ] Página 404 criada
- [ ] Popup Revenda configurado e gatilho testado
- [ ] Página Contato criada
- [ ] Teste responsivo completo (desktop, tablet, mobile)
- [ ] Teste cross-browser (Chrome, Firefox, Safari, Edge)
- [ ] Performance: verificar se as imagens estão otimizadas e o lazy loading está ativo

---

*Documento gerado para uso interno da equipe de desenvolvimento.*  
*New Alliance Tecnologia — [newalliance.tech](https://newalliance.tech)*

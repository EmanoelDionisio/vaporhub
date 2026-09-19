# Vapor Hub — WordPress + WooCommerce

E-commerce de vape, pod e e-líquido: tema filho, painel Minha Loja e estrutura de vitrine. O catálogo de produção entra pelo Tiny ERP.

**Desenvolvido por [New Alliance Tecnologia](https://newalliance.tech)**

---

## Requisitos

| Componente | Versão mínima |
|---|---|
| WordPress | 6.4 |
| PHP | 8.1 |
| WooCommerce | 8.0 |
| Elementor Pro | 3.18 |
| Hello Elementor (tema pai) | 3.0 |

---

## Estrutura do projeto

```
wp-vapor-hub/
├── themes/vapor-hub/       ← Tema filho do Hello Elementor
│   ├── style.css                  Cabeçalho do tema
│   ├── functions.php              Suporte WooCommerce, assets, hooks
│   ├── 404.php                    Página de erro personalizada
│   ├── assets/
│   │   ├── css/design-system.css  Tokens de design + utilitários
│   │   ├── css/woocommerce.css    Estilos do WooCommerce
│   │   └── js/theme.js            JS do frontend
│   ├── template-parts/            Seções reutilizáveis (hero, benefícios, etc.)
│   └── woocommerce/               Overrides de templates WooCommerce
├── plugins/vapor-hub-loja/ ← Plugin do painel simplificado
│   ├── vapor-hub-loja.php    Arquivo principal
│   ├── includes/                  Classes (admin, roles, settings, shortcodes)
│   └── admin/                     Views, CSS e JS do painel
├── setup/                       ← Scripts de configuração inicial
│   ├── configuracao-woocommerce.php  Categorias, atributos, config WooCommerce
│   ├── produtos-importacao.csv       Produtos de exemplo para importação
│   └── elementor-configuracao.md     Guia de montagem no Elementor Pro
└── docs/
    └── manual-do-cliente.md     ← Manual do lojista em PT-BR
```

---

## Instalação passo a passo

### 1. WordPress e plugins obrigatórios

Instale o WordPress 6.4+ com PHP 8.1+. Instale e ative:

- **Hello Elementor** (tema pai — gratuito no repositório)
- **WooCommerce** (gratuito)
- **Elementor Pro** (licença necessária)

### 2. Tema filho

Copie a pasta `themes/vapor-hub/` para `wp-content/themes/`. Ative em **Aparência > Temas**.

### 3. Plugin do painel

Copie a pasta `plugins/vapor-hub-loja/` para `wp-content/plugins/`. Ative em **Plugins**.

### 4. Configuração do WooCommerce

Execute o script de setup via WP-CLI:

```bash
wp eval-file setup/configuracao-woocommerce.php
```

Isso cria: categorias e atributos do nicho vapor, páginas obrigatórias, zona de frete e configurações de moeda. **Não importa o catálogo real** — isso vem do Tiny.

### 5. Amostra de produtos (dev)

No painel: **Produtos > Importar** — selecione `setup/produtos-importacao.csv` só para testar a vitrine. Em produção o catálogo entra pelo Tiny, com recorte (SKU `VH-`, categoria mapeada, ativo).

### 6. Configurar o Elementor Pro

Siga o guia em `setup/elementor-configuracao.md` para montar:

- Cabeçalho e rodapé no Theme Builder
- Página inicial com as seções do MVP
- Templates de loja, produto individual e 404

### 7. Formulário de contato

Instale **Fluent Forms** (gratuito) ou **WPForms Lite**. Crie um formulário com campos: Nome, E-mail, Telefone, Assunto, Mensagem. Ative o honeypot nas configurações do plugin para proteção contra spam. Insira o shortcode do formulário na página de Contato via Elementor.

### 8. Meio de pagamento

Configure em **WooCommerce > Configurações > Pagamentos**. Recomendados para o Brasil:

- **Mercado Pago** (plugin oficial)
- **PagSeguro**
- **Stripe**

Teste em modo sandbox antes de publicar.

---

## Shortcodes disponíveis (do plugin)

| Shortcode | Descrição |
|---|---|
| `[vh_beneficios]` | Barra de 4 benefícios (editável no painel) |
| `[vh_comunidade]` | Galeria horizontal da comunidade |
| `[vh_revenda_cta]` | Card de chamada à revenda |
| `[vh_categorias]` | Grade de categorias WooCommerce |

Use-os no Elementor via widget **Shortcode**.

---

## Perfil do lojista

O plugin cria o perfil **Gestor da Loja** com acesso simplificado:

- Menu "Minha Loja" com atalhos rápidos
- Acesso a Produtos, Pedidos e Mídias
- Sem acesso a Plugins, Temas ou Configurações avançadas
- Redirecionamento após login para o painel simplificado

---

## Suporte

- **Manual do cliente**: `docs/manual-do-cliente.md`
- **Suporte técnico**: [New Alliance Tecnologia](https://newalliance.tech)

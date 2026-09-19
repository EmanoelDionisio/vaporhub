# Vapor Hub — contexto do produto

Este repositório é a **réplica integral** de `/home/emanoeldionisio/Projetos/Empresa/Sistemas/piscou-afundou`, isolada para virar um produto novo: um **hub comercial de e-commerce WordPress/WooCommerce para o nicho de vape, pod e cigarro eletrônico**.

O projeto **Piscou Afundou permanece intocado**. Daqui para frente, o trabalho de nomenclatura, nicho, visual e piloto acontece **somente nesta pasta**.

## Identidade

| Item | Valor |
|---|---|
| Nome comercial | **Vapor Hub** |
| Pasta / repositório | `/home/emanoeldionisio/Projetos/Empresa/Sistemas/vapor-hub` |
| Papel | Hub/template para lojas do nicho vapor (não é a marca de uma loja específica) |
| Origem | Cópia completa do Piscou Afundou (tema filho Hello Elementor + plugin Minha Loja + setup + React + testes + skills) |
| Estado nesta cópia | Rebrand de pastas/prefixos feito. Nicho vapor no setup, Tiny como hub, visual preto/lima. Credenciais e host do piloto ainda não entram. |

O nome **não** deve citar CIA do Vapor, POD & VAPE, Piscou Afundou nem Gama da Pesca. Ele precisa servir o piloto e as próximas lojas do mesmo nicho.

## O que foi copiado (e o que não foi)

Copiado: árvore inteira do projeto (tema, plugin, setup, `src/`, testes, scripts de deploy, skills Cursor, histórico git local).

Não copiado de propósito:

- `node_modules/` (rodar `npm install` aqui)
- `dist/`, caches de teste
- `.stfolder` (Syncthing)
- `.env.wp-deploy` da loja de pesca (credenciais e host do **outro** produto)

O remote `origin` do GitHub `piscou-afundou` foi **removido** neste clone para ninguém enviar commit para o repositório antigo. O remote novo ainda não existe.

## Lojas de referência (nicho)

Analisadas na VPS `root@5.161.49.209` (hostname `ciadovapor`), Swarm worker com `newalliance/dockerpress`. São a **referência de nicho e de vitrine**, não o destino de deploy deste piloto e **não** a origem do catálogo.

| Loja | URL | Tema atual |
|---|---|---|
| CIA do Vapor | https://ciadovapor.br.com | Shoptimizer 2.9.5 |
| POD & VAPE Brasil | https://podevape.br.com | Shoptimizer 2.6.5 |

### CIA do Vapor (olhada a fundo)

Operação estabelecida e pesada:

- ~8.131 produtos publicados, ~21 mil variações
- ~26 mil pedidos concluídos, ~42 mil anexos
- 66 plugins ativos
- Home Elementor + Revolution Slider
- Checkout FunnelKit
- Pagamento: PIX (PagHiper) + Mercado Pago
- Frete: várias faixas de motoboy em São Paulo + envio Brasil
- Visual: preto, vitrine de conversão, busca, parcelamento, PIX, CEP, WhatsApp

**Menu / categorias de negócio:** Vape, PODs, Vaporizador de ervas, e-Líquidos, Nicotina Oral, Acessórios, Marcas.

**Atributos WooCommerce:** Cores, Puffs, Quantidade em ml, Resistência, Sabor, Teor de CBD, Teor de nicotina, Tipo de nicotina, Tipo de Sabor.

Taxonomia extra: `product_brand` (Marcas).

O problema dessas lojas não é “não ter WooCommerce”. É peso (Shoptimizer + dezenas de plugins + Elementor + FunnelKit + snippets). O Vapor Hub entra como a versão **fluida e limpa** da mesma stack que o Piscou Afundou já prova: tema filho + Minha Loja + setup WooCommerce.

A CIA **não** é a origem dos produtos do piloto. Os produtos já estão no **Tiny ERP**, que funciona como hub de todas as lojas. Detalhe em [`catalogo-tiny.md`](./catalogo-tiny.md).

## Intenção do piloto

1. Loja **nova e limpa** (WordPress do zero). Não ativar este tema em cima da CIA do Vapor em produção e **não** copiar o catálogo Woo de loja para loja.
2. Arquitetura igual à do Piscou Afundou, com nicho, copy e visual de vapor.
3. Catálogo: estrutura (categorias/atributos/mapeamento) na loja; **produtos importados ou sincronizados a partir do Tiny**. CSV local só como amostra de teste.
4. Caminhos reais de deploy (FTP/SSH/WP-CLI) e credenciais Tiny do piloto **serão informados depois**. Até lá, não usar host da Gama da Pesca / Piscou Afundou.

## O que deve permanecer (arquitetura)

- Tema filho de Hello Elementor + WooCommerce + Elementor Pro
- Plugin de painel simplificado (Minha Loja)
- Script `setup/configuracao-woocommerce.php` idempotente
- Integração Tiny (fila, mapeamento, pull de estoque/preço, painel). No Vapor Hub o sentido do catálogo é **Tiny → loja**, não o contrário.
- Checkout BR, PIX/cartão, Correios/frete como capacidade do produto (destino concreto vem depois)
- Testes (PHPUnit / Playwright) e scripts `wp:deploy*`
- Skills de deploy e performance, depois renomeadas para Vapor Hub

## O que deve mudar (produto)

- Todas as nomenclaturas, slugs, text domains, prefixes, pastas `wp-piscou-afundou`, tema `piscou-afundou`, plugin `piscou-afundou-loja`
- Nicho: pesca esportiva → vape / pod / e-líquido
- Categorias, atributos e copy de pesca → vapor. CSV de exemplo = dummy, não migração.
- Narrativa Tiny: deixar de dizer que “o cadastro nasce sempre na loja”.
- Visual: identidade preta/premium de loja de vapor (referência CIA), sem comunidade de pescadores
- Deploy de exemplo: placeholders. Credenciais e host do piloto entram quando o dono do projeto passar.

## Documento irmão

- Prompt do rebrand: [`PROMPT-AGENTE.md`](./PROMPT-AGENTE.md)
- Catálogo / Tiny: [`catalogo-tiny.md`](./catalogo-tiny.md)
- Mapa de nomes: [`mapa-nomenclatura.md`](./mapa-nomenclatura.md)

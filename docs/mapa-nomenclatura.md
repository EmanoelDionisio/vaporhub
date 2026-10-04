# Mapa de nomenclatura — Vapor Hub

Registro do rebrand feito nesta pasta. O prefixo WooCommerce `pa_` em taxonomias de atributo (`pa_sabor`, `pa_puffs`…) é do core e **não** foi trocado.

## Pastas e slugs

| Antes | Depois |
|---|---|
| `wp-piscou-afundou/` | `wp-vapor-hub/` |
| `themes/piscou-afundou` | `themes/vapor-hub` |
| `plugins/piscou-afundou-loja` | `plugins/vapor-hub-loja` |
| text domain `piscou-afundou` | `vapor-hub` |
| text domain `piscou-afundou-loja` | `vapor-hub-loja` |
| skill `wp-deploy-piscou-afundou` | `wp-deploy-vapor-hub` |
| skill `wp-performance-piscou-afundou` | `wp-performance-vapor-hub` |

## Prefixos de código

| Antes | Depois |
|---|---|
| `PA_` / `pa_` (classes, funções, opções, CSS) | `VH_` / `vh_` |
| opção `pa_loja_setup_concluido` | `vh_loja_setup_concluido` |
| REST Minha Loja | `vh-loja/v1` |
| transients / cron com `pa_` | `vh_` |
| metas Tiny `_pa_tiny_*` | `_vh_tiny_*` (`_vh_tiny_id`, `_vh_tiny_sync_hash`, `_vh_tiny_cat_id`, …) |
| SKU loja `GP-` | `VH-` (`VH_Tiny_SKU::PREFIXO_PADRAO`) |
| fila `wp_pa_tiny_fila` | `wp_vh_tiny_fila` |

Atributos WooCommerce continuam `pa_{slug}` (`pa_sabor`, `pa_puffs`, `pa_teor_nicotina`, …).

## Identidade visual padrão

Violeta da logo: primária `#7618f1`, hover `#5c10d0`, fundo `#f6f4fb`, superfície `#ffffff`, texto `#1a1228`. Ajustável em Minha Loja → Aparência.

## O que o usuário informa depois

- Domínio do piloto
- FTP / SSH / `REMOTE_WP_PATH`
- Conta Tiny, depósito, lista de preço e recorte (quais categorias Tiny entram nesta loja)
- Se a seção Comunidade vira grupo VIP de fato ou some da home
- Se B2B/revenda permanece na home

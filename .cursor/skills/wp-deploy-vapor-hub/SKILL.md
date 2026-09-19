---
name: wp-deploy-vapor-hub
description: >-
  Deploy do tema filho, plugin Minha Loja e pasta setup para WordPress via FTP (lftp)
  e validação/remoto com SSH + WP-CLI. Inclui detecção automática para não repetir o
  script configuracao-woocommerce.php quando o ambiente já foi preparado (opção
  vh_loja_setup_concluido). Use quando o usuário pedir deploy WordPress, subir tema,
  enviar wp-vapor-hub, rodar setup remoto, npm run wp:deploy, ou equivalente.
---

# Deploy WordPress — Vapor Hub

Skill do projeto em `.cursor/skills/wp-deploy-vapor-hub/`.

## Pré-requisitos locais

- `lftp` instalado (`sudo apt install -y lftp`)
- Para senha SSH: `sshpass` (`sudo apt install -y sshpass`)
- Arquivo `.env.wp-deploy` na raiz do repositório (copiar de `.env.wp-deploy.example` e preencher)

## Variáveis principais (.env.wp-deploy)

| Variável | Função |
|----------|--------|
| `FTP_*`, `REMOTE_THEMES_DIR`, `REMOTE_PLUGINS_DIR` | Envio por FTP/SFTP |
| `SSH_*`, `REMOTE_WP_PATH`, `REMOTE_WP_CLI` | Conexão e WP-CLI no servidor |
| `LOCAL_SETUP_DIR`, `REMOTE_SETUP_DIR` | Upload opcional da pasta `setup/` (scripts PHP para WP-CLI) |
| `RUN_REMOTE_SETUP` | `true` = após o FTP, executa `scripts/remote-wp-setup.sh` |
| `SKIP_SETUP_IF_DONE` | `true` (padrão) = **não** roda `configuracao-woocommerce.php` se `vh_loja_setup_concluido=1` |
| `FORCE_REMOTE_SETUP` | `true` = **sempre** roda `configuracao-woocommerce.php` (útil após mudanças no script) |
| `DRY_RUN` | `true` = simula FTP sem enviar |

## Comandos npm (raiz do projeto)

- `npm run wp:deploy` — apenas FTP: tema + plugin (+ pasta `setup` se configurada).
- `npm run wp:setup:remote` — apenas SSH/WP-CLI (não envia arquivos).
- `npm run wp:deploy:all` — FTP e, se `RUN_REMOTE_SETUP=true`, SSH.

## Detecção do setup WooCommerce

1. Ao final de uma execução bem-sucedida de `setup/configuracao-woocommerce.php`, o WordPress grava a opção **`vh_loja_setup_concluido`** com valor `1`.
2. Em deploys seguintes, `scripts/remote-wp-setup.sh` consulta essa opção e **pula** `eval-file` do PHP se `SKIP_SETUP_IF_DONE=true` e o marcador está `1`.
3. Para forçar nova execução do PHP: definir **`FORCE_REMOTE_SETUP=true`** no `.env.wp-deploy`, **ou** no servidor `wp option delete vh_loja_setup_concluido`.

Os passos de SSH que **sempre** rodam quando `RUN_REMOTE_SETUP=true`: checagem WordPress, plugins/tema ativos, ativar tema `vapor-hub` se preciso, relatório rápido de opções WooCommerce.

## Segurança

- Não commitar `.env.wp-deploy` com senhas (manter em `.gitignore`).
- Em logs e respostas ao usuário, não repetir senhas ou chaves.

## Ordem recomendada para o agente

1. Confirmar existência de `.env.wp-deploy` ou orientar cópia do exemplo.
2. Para só código front: `npm run wp:deploy`.
3. Para código + validação remota: `npm run wp:deploy:all`.
4. Se o usuário alterou apenas `configuracao-woocommerce.php` e precisa reaplicar: temporariamente `FORCE_REMOTE_SETUP=true` ou apagar a opção no banco.

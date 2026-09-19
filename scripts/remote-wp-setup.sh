#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env.wp-deploy"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Erro: arquivo ${ENV_FILE} nao encontrado."
  echo "Crie a partir do exemplo:"
  echo "  cp .env.wp-deploy.example .env.wp-deploy"
  exit 1
fi

# shellcheck disable=SC1090
source "${ENV_FILE}"

required_vars=(
  SSH_HOST SSH_PORT SSH_USER
  REMOTE_WP_PATH REMOTE_WP_CLI
)

for var in "${required_vars[@]}"; do
  if [[ -z "${!var:-}" ]]; then
    echo "Erro: variavel obrigatoria ausente: ${var}"
    exit 1
  fi
done

REMOTE_SETUP_SCRIPT=""
if [[ -n "${REMOTE_SETUP_DIR:-}" ]]; then
  REMOTE_SETUP_SCRIPT="${REMOTE_SETUP_DIR%/}/configuracao-woocommerce.php"
fi

# Compatibilidade com FTP em chroot (CloudPanel):
# quando REMOTE_SETUP_DIR e absoluto no .env, o upload via FTP pode criar
# a estrutura "wp_root + /caminho/absoluto". Este fallback cobre esse caso.
REMOTE_SETUP_SCRIPT_FALLBACK=""
if [[ -n "${REMOTE_SETUP_SCRIPT}" ]]; then
  REMOTE_SETUP_SCRIPT_FALLBACK="${REMOTE_WP_PATH%/}${REMOTE_SETUP_SCRIPT}"
fi

SSH_OPTS=(
  "-p" "${SSH_PORT}"
  "-o" "StrictHostKeyChecking=accept-new"
)

if [[ -n "${SSH_KEY_PATH:-}" ]]; then
  SSH_OPTS+=("-i" "${SSH_KEY_PATH/#\~/$HOME}")
fi

SSH_CMD=(ssh "${SSH_OPTS[@]}")
if [[ -n "${SSH_PASS:-}" ]]; then
  if ! command -v sshpass >/dev/null 2>&1; then
    echo "Erro: SSH_PASS foi informado, mas 'sshpass' nao esta instalado."
    echo "Instale com: sudo apt update && sudo apt install -y sshpass"
    exit 1
  fi
  SSH_CMD=(sshpass -p "${SSH_PASS}" ssh "${SSH_OPTS[@]}")
fi

DEPLOY_SITE_NAME="${DEPLOY_SITE_NAME:-piloto.example}"

echo "Executando setup remoto..."
echo "Site alvo    : ${DEPLOY_SITE_NAME}"
echo "Host         : ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
echo "WP path      : ${REMOTE_WP_PATH}"
echo "WP-CLI       : ${REMOTE_WP_CLI}"
echo "Setup script : ${REMOTE_SETUP_SCRIPT:-nao definido}"
if [[ -n "${REMOTE_SETUP_SCRIPT_FALLBACK}" ]]; then
  echo "Fallback     : ${REMOTE_SETUP_SCRIPT_FALLBACK}"
fi

FORCE_REMOTE_SETUP="${FORCE_REMOTE_SETUP:-false}"
SKIP_SETUP_IF_DONE="${SKIP_SETUP_IF_DONE:-true}"
echo "FORCE_REMOTE_SETUP : ${FORCE_REMOTE_SETUP} (true = sempre rodar configuracao-woocommerce.php)"
echo "SKIP_SETUP_IF_DONE : ${SKIP_SETUP_IF_DONE} (true = pular se vh_loja_setup_concluido=1)"

"${SSH_CMD[@]}" "${SSH_USER}@${SSH_HOST}" "bash -s" <<EOF
set -euo pipefail

cd "${REMOTE_WP_PATH}"

if ! command -v "${REMOTE_WP_CLI}" >/dev/null 2>&1; then
  echo "Erro: WP-CLI ('${REMOTE_WP_CLI}') nao encontrado no servidor."
  exit 1
fi

echo "1) Validando instalacao WordPress..."
"${REMOTE_WP_CLI}" core is-installed >/dev/null
echo "   OK"

echo "2) Validando plugin e tema..."
"${REMOTE_WP_CLI}" plugin is-active woocommerce >/dev/null
"${REMOTE_WP_CLI}" plugin is-active vapor-hub-loja >/dev/null
ATIVO_TEMA=\$("${REMOTE_WP_CLI}" theme list --status=active --field=name 2>/dev/null | head -n1 | tr -d '\r\n')
if [[ "\${ATIVO_TEMA}" != "vapor-hub" ]]; then
  echo "Aviso: tema ativo atual = \${ATIVO_TEMA}. Ativando 'vapor-hub'..."
  "${REMOTE_WP_CLI}" theme activate vapor-hub
fi
echo "   OK"

SETUP_DONE="\$("${REMOTE_WP_CLI}" option get vh_loja_setup_concluido 2>/dev/null | head -n1 | tr -d '\r\n' || true)"
SKIP_IF_DONE="${SKIP_SETUP_IF_DONE}"
FORCE_SETUP="${FORCE_REMOTE_SETUP}"

RUN_EVAL=true
if [[ "\${SKIP_IF_DONE}" == "true" ]] && [[ "\${SETUP_DONE}" == "1" ]] && [[ "\${FORCE_SETUP}" != "true" ]]; then
  RUN_EVAL=false
fi

if [[ "\${RUN_EVAL}" == "false" ]]; then
  echo "3) Setup WooCommerce ja aplicado (vh_loja_setup_concluido=1). Pulando configuracao-woocommerce.php."
  echo "    Para executar de novo: FORCE_REMOTE_SETUP=true no .env.wp-deploy ou: wp option delete vh_loja_setup_concluido"
elif [[ -n "${REMOTE_SETUP_SCRIPT}" && -f "${REMOTE_SETUP_SCRIPT}" ]]; then
  echo "3) Rodando configuracao WooCommerce..."
  "${REMOTE_WP_CLI}" eval-file "${REMOTE_SETUP_SCRIPT}"
  echo "   OK"
elif [[ -n "${REMOTE_SETUP_SCRIPT_FALLBACK}" && -f "${REMOTE_SETUP_SCRIPT_FALLBACK}" ]]; then
  echo "3) Script encontrado no fallback (FTP chroot). Rodando configuracao WooCommerce..."
  "${REMOTE_WP_CLI}" eval-file "${REMOTE_SETUP_SCRIPT_FALLBACK}"
  echo "   OK"
else
  echo "3) Setup script nao encontrado em '${REMOTE_SETUP_SCRIPT}'. Pulando."
fi

echo "4) Relatorio rapido..."
echo "- Moeda: \$("${REMOTE_WP_CLI}" option get woocommerce_currency || true)"
echo "- Pais da loja: \$("${REMOTE_WP_CLI}" option get woocommerce_default_country || true)"
echo "- Unidade peso: \$("${REMOTE_WP_CLI}" option get woocommerce_weight_unit || true)"
echo "- Unidade medida: \$("${REMOTE_WP_CLI}" option get woocommerce_dimension_unit || true)"
echo "- Tema ativo: \$("${REMOTE_WP_CLI}" theme list --status=active --field=name)"

# Um a um, por slug: 'plugin list' quebra dentro do WP-CLI 2.12 neste WordPress
# e o fatal dele aparece como "erro crítico no seu site", que não é o caso.
echo "- Plugins conferidos:"
for slug in woocommerce vapor-hub-loja elementor; do
  versao="\$("${REMOTE_WP_CLI}" plugin get "\${slug}" --field=version 2>/dev/null | head -n1 | tr -d '\r\n' || true)"
  estado="\$("${REMOTE_WP_CLI}" plugin is-active "\${slug}" >/dev/null 2>&1 && echo ativo || echo inativo)"
  echo "    \${slug}: \${versao:-nao instalado} (\${estado})"
done

echo "- Minha Loja: \$("${REMOTE_WP_CLI}" option get vh_loja_versao 2>/dev/null | head -n1 | tr -d '\r\n' || true)"

echo "Setup remoto concluido."
EOF


#!/usr/bin/env bash
#
# Envia tema filho, plugin Minha Loja e a pasta setup para o servidor por rsync
# sobre SSH — o mesmo canal usado pela validação remota com WP-CLI.
#
# Só sobe o que mudou, e a suíte de testes fica de fora: a pasta do plugin é
# espelhada inteira, então `tests/` dentro dela iria para produção.
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env.wp-deploy"

for programa in rsync ssh; do
  if ! command -v "${programa}" >/dev/null 2>&1; then
    echo "Erro: '${programa}' nao encontrado."
    echo "Instale com: sudo apt update && sudo apt install -y ${programa}"
    exit 1
  fi
done

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Erro: arquivo ${ENV_FILE} nao encontrado."
  echo "Crie a partir do exemplo:"
  echo "  cp .env.wp-deploy.example .env.wp-deploy"
  exit 1
fi

# Quem chama tem a última palavra nas travas: `DRY_RUN=true npm run wp:deploy`
# precisa valer mais que o valor gravado no arquivo.
DRY_RUN_CHAMADA="${DRY_RUN:-}"
DELETE_REMOTE_CHAMADA="${DELETE_REMOTE:-}"

# shellcheck disable=SC1090
source "${ENV_FILE}"

if [[ -n "${DRY_RUN_CHAMADA}" ]]; then
  DRY_RUN="${DRY_RUN_CHAMADA}"
fi
if [[ -n "${DELETE_REMOTE_CHAMADA}" ]]; then
  DELETE_REMOTE="${DELETE_REMOTE_CHAMADA}"
fi

required_vars=(
  SSH_HOST SSH_PORT SSH_USER REMOTE_WP_PATH
  LOCAL_THEME_DIR LOCAL_PLUGIN_DIR
  THEME_SLUG PLUGIN_SLUG DRY_RUN DELETE_REMOTE
)

for var in "${required_vars[@]}"; do
  if [[ -z "${!var:-}" ]]; then
    echo "Erro: variavel obrigatoria ausente: ${var}"
    exit 1
  fi
done

LOCAL_THEME_ABS="${ROOT_DIR}/${LOCAL_THEME_DIR}"
LOCAL_PLUGIN_ABS="${ROOT_DIR}/${LOCAL_PLUGIN_DIR}"

if [[ ! -d "${LOCAL_THEME_ABS}" ]]; then
  echo "Erro: pasta local do tema nao encontrada: ${LOCAL_THEME_ABS}"
  exit 1
fi

if [[ ! -d "${LOCAL_PLUGIN_ABS}" ]]; then
  echo "Erro: pasta local do plugin nao encontrada: ${LOCAL_PLUGIN_ABS}"
  exit 1
fi

WP_ROOT="${REMOTE_WP_PATH%/}"
REMOTE_THEME_TARGET="${WP_ROOT}/${REMOTE_THEMES_DIR:-wp-content/themes}"
REMOTE_THEME_TARGET="${REMOTE_THEME_TARGET//\/\//\/}/${THEME_SLUG}"
REMOTE_PLUGIN_TARGET="${WP_ROOT}/${REMOTE_PLUGINS_DIR:-wp-content/plugins}"
REMOTE_PLUGIN_TARGET="${REMOTE_PLUGIN_TARGET//\/\//\/}/${PLUGIN_SLUG}"

SSH_OPTS=(
  "-p" "${SSH_PORT}"
  "-o" "StrictHostKeyChecking=accept-new"
)

if [[ -n "${SSH_KEY_PATH:-}" && -f "${SSH_KEY_PATH/#\~/$HOME}" ]]; then
  SSH_OPTS+=("-i" "${SSH_KEY_PATH/#\~/$HOME}")
fi

SSH_BIN=(ssh "${SSH_OPTS[@]}")
RSYNC_BIN=(rsync)
SSH_TRANSPORTE="ssh ${SSH_OPTS[*]}"

if [[ -n "${SSH_PASS:-}" ]]; then
  if ! command -v sshpass >/dev/null 2>&1; then
    echo "Erro: SSH_PASS foi informado, mas 'sshpass' nao esta instalado."
    echo "Instale com: sudo apt update && sudo apt install -y sshpass"
    exit 1
  fi
  SSH_BIN=(sshpass -p "${SSH_PASS}" ssh "${SSH_OPTS[@]}")
  RSYNC_BIN=(sshpass -p "${SSH_PASS}" rsync)
fi

# Guardar a data do arquivo é o que faz o deploy seguinte enviar só o que mudou.
# Permissão e dono são do servidor; do lado local vale o conteúdo.
RSYNC_FLAGS=(
  "-rltz"
  "--human-readable"
  "--itemize-changes"
  "--no-perms" "--no-owner" "--no-group" "--omit-dir-times"
  "--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r"
  "--exclude=.git*"
  "--exclude=.DS_Store"
  "--exclude=*.map"
  "--exclude=node_modules/"
  "--exclude=tests/"
  "--exclude=vendor/"
  "--exclude=composer.*"
  "--exclude=phpunit.xml*"
)

if [[ "${DRY_RUN}" == "true" ]]; then
  RSYNC_FLAGS+=("--dry-run")
fi

if [[ "${DELETE_REMOTE}" == "true" ]]; then
  RSYNC_FLAGS+=("--delete")
fi

echo "Iniciando deploy..."
echo "Site alvo    : ${DEPLOY_SITE_NAME:-piloto.example}"
echo "Transporte   : rsync sobre SSH (${SSH_USER}@${SSH_HOST}:${SSH_PORT})"
echo "Tema local   : ${LOCAL_THEME_ABS}"
echo "Tema remoto  : ${REMOTE_THEME_TARGET}"
echo "Plugin local : ${LOCAL_PLUGIN_ABS}"
echo "Plugin remoto: ${REMOTE_PLUGIN_TARGET}"
echo "DryRun       : ${DRY_RUN}"
echo "Apaga sobra  : ${DELETE_REMOTE}"

"${SSH_BIN[@]}" "${SSH_USER}@${SSH_HOST}" \
  "mkdir -p '${REMOTE_THEME_TARGET}' '${REMOTE_PLUGIN_TARGET}'"

# A barra no fim da origem envia o conteúdo, não a pasta dentro da pasta.
enviar() {
  local origem="$1"
  local destino="$2"

  "${RSYNC_BIN[@]}" "${RSYNC_FLAGS[@]}" \
    -e "${SSH_TRANSPORTE}" \
    "${origem%/}/" "${SSH_USER}@${SSH_HOST}:${destino}/"
}

echo
echo "▸ tema ${THEME_SLUG}"
enviar "${LOCAL_THEME_ABS}" "${REMOTE_THEME_TARGET}"

echo
echo "▸ plugin ${PLUGIN_SLUG}"
enviar "${LOCAL_PLUGIN_ABS}" "${REMOTE_PLUGIN_TARGET}"

echo
echo "Deploy de tema + plugin finalizado."

if [[ -n "${LOCAL_SETUP_DIR:-}" && -n "${REMOTE_SETUP_DIR:-}" ]]; then
  LOCAL_SETUP_ABS="${ROOT_DIR}/${LOCAL_SETUP_DIR}"
  if [[ -d "${LOCAL_SETUP_ABS}" ]]; then
    REMOTE_SETUP_TARGET="${REMOTE_SETUP_DIR}"
    if [[ "${REMOTE_SETUP_TARGET}" != /* ]]; then
      REMOTE_SETUP_TARGET="${WP_ROOT}/${REMOTE_SETUP_TARGET}"
    fi

    echo
    echo "▸ pasta setup"
    "${SSH_BIN[@]}" "${SSH_USER}@${SSH_HOST}" "mkdir -p '${REMOTE_SETUP_TARGET}'"
    enviar "${LOCAL_SETUP_ABS}" "${REMOTE_SETUP_TARGET}"
    echo "Setup enviado para: ${REMOTE_SETUP_TARGET}"
  fi
fi

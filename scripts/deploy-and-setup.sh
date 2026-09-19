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

echo "=== Etapa 1/2: Deploy de tema/plugin ==="
bash "${ROOT_DIR}/scripts/deploy-wordpress-assets.sh"

if [[ "${RUN_REMOTE_SETUP:-false}" == "true" ]]; then
  echo ""
  echo "=== Etapa 2/2: Setup remoto WordPress ==="
  bash "${ROOT_DIR}/scripts/remote-wp-setup.sh"
else
  echo ""
  echo "RUN_REMOTE_SETUP=false, setup remoto foi pulado."
fi

echo ""
echo "Fluxo finalizado."


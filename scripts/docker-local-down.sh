#!/usr/bin/env bash
# Para o stack DockerPress isolado do Vapor Hub (porta 8083).
# Volumes permanecem. Não toca 8080/8081/8082.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker/local/docker-compose.yml"
ENV_FILE="${ROOT_DIR}/docker/local/.env"
PROJECT="vaporhub"

if [[ ! -f "${COMPOSE_FILE}" ]]; then
  echo "Erro: ${COMPOSE_FILE} não encontrado." >&2
  exit 1
fi

ARGS=(docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}")
if [[ -f "${ENV_FILE}" ]]; then
  ARGS+=(--env-file "${ENV_FILE}")
fi

"${ARGS[@]}" down
echo "Stack vaporhub parado. Volumes locais mantidos."
echo "Para apagar banco e arquivos WP deste stack: ${ARGS[*]} down -v"

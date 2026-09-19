#!/usr/bin/env bash
# Sintaxe de todo PHP do tema, do plugin e da suíte de teste.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

FALHAS=0
TOTAL=0

while IFS= read -r -d '' arquivo; do
  TOTAL=$((TOTAL + 1))
  if ! saida="$(php -l "${arquivo}" 2>&1)"; then
    echo "${saida}"
    FALHAS=$((FALHAS + 1))
  fi
done < <(find wp-vapor-hub tests -name '*.php' -type f -print0)

echo "php -l: ${TOTAL} arquivo(s), ${FALHAS} com erro."
exit $(( FALHAS > 0 ? 1 : 0 ))

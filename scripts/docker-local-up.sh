#!/usr/bin/env bash
#
# Sobe o WordPress Vapor Hub em DockerPress isolado (HTTP 8083).
# Não altera os stacks nas portas 8080 (Perfex), 8081 (DockerPress) e 8082 (Espaço Sex).
# Não é o deploy FTP/SSH do piloto online.
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_DIR="${ROOT_DIR}/docker/local"
COMPOSE_FILE="${COMPOSE_DIR}/docker-compose.yml"
ENV_EXAMPLE="${COMPOSE_DIR}/.env.example"
ENV_FILE="${COMPOSE_DIR}/.env"
PROJECT="vaporhub"
ENTRYPOINT_DST="${COMPOSE_DIR}/patches/entrypoint.sh"
DOCKERPRESS_ENTRYPOINT="${DOCKERPRESS_ENTRYPOINT:-${ROOT_DIR}/../dockerpress/stacks/local/patches/entrypoint.sh}"

if [[ ! -f "${COMPOSE_FILE}" ]]; then
  echo "Erro: ${COMPOSE_FILE} não encontrado." >&2
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  echo "Docker não está respondendo. Suba o daemon e tente de novo." >&2
  exit 1
fi

if [[ ! -f "${ENV_FILE}" ]]; then
  cp "${ENV_EXAMPLE}" "${ENV_FILE}"
  echo "▸ criei ${ENV_FILE} a partir do exemplo (admin / senha só deste local)."
fi

# shellcheck disable=SC1090
source "${ENV_FILE}"
HTTP_PORT="${HTTP_PORT:-8083}"
SITE_URL="http://127.0.0.1:${HTTP_PORT}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-vaporhub_local}"

if [[ ! -f "${ENTRYPOINT_DST}" ]]; then
  if [[ ! -f "${DOCKERPRESS_ENTRYPOINT}" ]]; then
    echo "Erro: não achei o entrypoint HTTP do DockerPress em:" >&2
    echo "  ${DOCKERPRESS_ENTRYPOINT}" >&2
    echo "Ajuste DOCKERPRESS_ENTRYPOINT ou clone Sistemas/dockerpress ao lado deste repo." >&2
    exit 1
  fi
  cp "${DOCKERPRESS_ENTRYPOINT}" "${ENTRYPOINT_DST}"
  echo "▸ copiei o entrypoint HTTP do DockerPress local (somente este stack)."
fi

if ss -ltn 2>/dev/null | grep -qE ":${HTTP_PORT}\\s"; then
  if ! docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" ps --status running --services 2>/dev/null | grep -qx wordpress; then
    echo "Erro: a porta ${HTTP_PORT} já está em uso e não é o stack vaporhub." >&2
    echo "Escolha outra HTTP_PORT em docker/local/.env." >&2
    exit 1
  fi
fi

echo "▸ subindo DockerPress isolado (projeto ${PROJECT}, HTTP ${HTTP_PORT})…"
docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" up -d

wp() {
  docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" \
    exec -T wordpress wp --allow-root --path=/var/www/html "$@"
}

echo "▸ aguardando o WordPress instalar (primeiro boot do DockerPress pode levar alguns minutos)…"
ready=0
for _ in $(seq 1 90); do
  if wp core is-installed >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 5
done

if [[ "${ready}" -ne 1 ]]; then
  echo "Erro: WordPress não ficou pronto a tempo. Logs:" >&2
  docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" logs --tail=80 wordpress >&2
  exit 1
fi

echo "▸ gravando URL local ${SITE_URL}…"
wp option update siteurl "${SITE_URL}" >/dev/null
wp option update home "${SITE_URL}" >/dev/null
wp option update blogname "Vapor Hub" >/dev/null
wp option update blogdescription "Pods, e-líquidos e acessórios" >/dev/null

# A imagem DockerPress injeta HTTPS/Cloudflare no wp-config; neste stack a porta é HTTP.
docker compose -p "${PROJECT}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" \
  exec -T wordpress php -r '
$f = "/var/www/html/wp-config.php";
$c = file_get_contents($f);
if ($c === false || str_contains($c, "vaporhub-local-http")) {
  exit(0);
}
$snippet = <<<PHP
// vaporhub-local-http — stack 8083, sem Cloudflare
\$_SERVER["HTTP_X_FORWARDED_PROTO"] = "http";
\$_SERVER["HTTPS"] = "off";

PHP;
$needle = "That'\''s all, stop editing!";
if (str_contains($c, $needle)) {
  $c = str_replace($needle, $snippet . $needle, $c);
} else {
  $c .= "\n" . $snippet;
}
file_put_contents($f, $c);
'


echo "▸ instalando Hello Elementor, WooCommerce e Correios…"
wp theme install hello-elementor --force >/dev/null
wp plugin install woocommerce woocommerce-correios --activate --force >/dev/null

echo "▸ ativando tema filho e Minha Loja…"
wp plugin activate vapor-hub-loja >/dev/null
wp theme activate vapor-hub >/dev/null

SETUP_PHP="/var/www/html/wp-content/vapor-hub-setup/configuracao-woocommerce.php"
SETUP_DONE="$(wp option get vh_loja_setup_concluido 2>/dev/null | tr -d '\r\n' || true)"
if [[ "${SETUP_DONE}" == "1" ]]; then
  echo "▸ setup WooCommerce já aplicado (vh_loja_setup_concluido=1)."
else
  echo "▸ rodando configuracao-woocommerce.php…"
  wp eval-file "${SETUP_PHP}"
fi

CSV="/var/www/html/wp-content/vapor-hub-setup/produtos-importacao.csv"
SAMPLE_COUNT="$(wp post list --post_type=product --format=count 2>/dev/null | tail -n1 | tr -d '\r\n' || true)"
if [[ ! "${SAMPLE_COUNT}" =~ ^[0-9]+$ ]]; then
  SAMPLE_COUNT=0
fi
if [[ "${SAMPLE_COUNT}" -gt 0 ]]; then
  echo "▸ produtos já existem (${SAMPLE_COUNT}); CSV de amostra não reimportado."
else
  echo "▸ importando CSV de amostra (não é o catálogo Tiny)…"
  wp eval "
    if ( ! class_exists( 'WC_Product_CSV_Importer' ) ) {
      include_once WC()->plugin_path() . '/includes/import/class-wc-product-csv-importer.php';
    }
    \$importer = new WC_Product_CSV_Importer( '${CSV}', array( 'parse' => true, 'update_existing' => false ) );
    \$result   = \$importer->import();
    echo 'imported=' . (int) ( \$result['imported'] ?? 0 ) . ' failed=' . (int) ( \$result['failed'] ?? 0 ) . PHP_EOL;
  "
  wp eval-file /var/www/html/wp-content/vapor-hub-setup/gerar-variacoes.php || true
fi

wp rewrite structure '/%postname%/' --hard >/dev/null
wp rewrite flush --hard >/dev/null

echo
echo "Pronto — stack isolado, sem tocar 8080/8081/8082."
echo "  Loja:        ${SITE_URL}"
echo "  Minha Loja:  ${SITE_URL}/minha-loja/"
echo "  wp-admin:    ${SITE_URL}/wp-admin/"
echo "  Usuário:     ${ADMIN_USER}"
echo "  Senha:       ${ADMIN_PASS}  (só local; ver docker/local/.env)"
echo
echo "Para parar: npm run wp:local:down"
echo "Piloto online continua sendo FTP/SSH (npm run wp:deploy), não este Compose."

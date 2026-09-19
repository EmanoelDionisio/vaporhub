#!/usr/bin/env bash
#
# Sobe o ambiente E2E: wp-env com WooCommerce, o plugin Minha Loja e o ERP
# mockado como mu-plugin, já preparado por tests/e2e/setup.php.
#
# Uso: npm run e2e:subir
#
set -euo pipefail

cd "$(dirname "$0")/.."

if ! docker info >/dev/null 2>&1; then
	echo "Docker não está respondendo. Suba o daemon antes de rodar o E2E." >&2
	exit 1
fi

echo "▸ subindo wp-env (WordPress + WooCommerce + plugin)…"
npx wp-env start

echo "▸ ativando plugins…"
npx wp-env run cli wp plugin activate woocommerce vapor-hub-loja

echo "▸ preparando loja, frete e integração Tiny mockada…"
npx wp-env run cli wp eval-file wp-content/vh-e2e/setup.php

echo "▸ regravando permalinks (o portal /minha-loja depende deles)…"
npx wp-env run cli wp rewrite flush --hard

echo "▸ limpando debug.log…"
npx wp-env run cli wp eval 'file_put_contents( WP_CONTENT_DIR . "/debug.log", "" );'

echo
echo "Pronto: http://localhost:8888  (admin / password)"
echo "Rode os testes com: npm run test:e2e"

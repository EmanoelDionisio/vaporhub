# Suíte de testes — Vapor Hub

A suíte fica **fora da pasta do plugin** de propósito: `scripts/deploy-wordpress-assets.sh`
espelha a pasta do plugin inteira para produção via `lftp mirror --reverse`.
Teste dentro do plugin iria para o servidor.

## Como rodar

```bash
npm run test              # unit + integração
npm run test:unit
npm run test:integration
npm run test:lint         # php -l em tema, plugin e testes
php tests/run.php --filter=Reconcil   # filtra por classe::método
```

Não precisa de Composer, Docker, banco nem rede. `tests/run.php` é um runner
próprio (`tests/framework/`) que segue a convenção do PHPUnit: arquivos
`*Test.php`, classes que herdam `VH_Test_Case`, métodos `test*`.

Onde houver PHPUnit disponível (PHP com `ext-dom` e `ext-mbstring`), os **mesmos
arquivos** rodam sob ele, porque `VH_Test_Case` herda de
`PHPUnit\Framework\TestCase` quando essa classe existe:

```bash
composer install
vendor/bin/phpunit --testsuite=unit
```

## Como a rede de teste funciona

O código sob teste é o **real** — nada de reimplementar regra de negócio no
teste. O que é substituído são as bordas:

| Borda | Substituto | Por quê |
| --- | --- | --- |
| WordPress + WooCommerce | `tests/stubs/wp-kernel.php` | Opções, post meta, hooks e objetos `WC_Product*` em memória |
| Rede HTTP | `tests/stubs/http.php` | Mesma costura do `pre_http_request`: `VH_Tiny_Client` real, rede falsa |
| Tiny ERP | `tests/stubs/class-vh-fake-tiny-erp.php` | Rotas v3 de verdade, incluindo o cenário dos 1.790 itens `situacao E` |
| Banco (`$wpdb`) | `tests/stubs/class-vh-fake-wpdb.php` | Fila `vh_tiny_fila` e busca de `_vh_tiny_id` em postmeta |
| Log | `tests/stubs/plugin-stubs.php` | `VH_Tiny_Log` em memória, para afirmar sobre o que foi registrado |

Como as classes do plugin não usam namespace, a **ordem de inclusão** no
`tests/bootstrap.php` é a costura: os stubs entram antes dos arquivos reais.

`VH_Test_Env::reset()` zera tudo e deixa a integração no estado auditado em
produção: ativa, modo v3, envio ligado, recebimento desligado.

## Camadas

- `tests/unit/` — mapeamento canônico, payload v3, webhook, SKU. Sem banco.
- `tests/integration/` — orquestração: fila, reconciliação, pull/push, estoque.
- `tests/e2e/` — Playwright contra WordPress e WooCommerce reais no `wp-env`.
- `tests/live/` — fumaça contra a conta real do Tiny. Só com `TINY_LIVE=1`,
  nunca em CI, SKU reservado `ZZ-TEST-*`, nunca `DELETE`, nunca toca nos 1.790.

## E2E: navegador, WordPress real, ERP mockado

```bash
npm run e2e:subir      # sobe o wp-env, instala Woo, prepara a loja
npm run test:e2e       # roda a suíte no Chromium
npm run test:e2e:ui    # modo interativo
npm run e2e:derrubar   # para os contêineres
```

Precisa de Docker. Aqui o WordPress, o WooCommerce e o plugin são de verdade; o
que continua falso é só o ERP, por dois mu-plugins montados em
`tests/e2e/mu-plugins/`:

| Arquivo | Papel |
| --- | --- |
| `vh-tiny-erp-mock.php` | Intercepta `pre_http_request` para `api.tiny.com.br`, responde como o ERP e guarda cada requisição recebida |
| `vh-e2e-frete.php` | Frete determinístico por peso e cubagem, no lugar dos Correios |

O mock também expõe rotas REST (token `X-VH-E2E-Token`) que o Playwright usa
para ler o estado do ERP, zerar os dois lados, processar a fila na hora e
conferir o `debug.log`. `tests/e2e/setup.php` deixa a loja no estado auditado em
produção: páginas em shortcode com os slugs reais, pagamento na entrega, zona de
frete, integração conectada, raiz de categoria e “Sem categoria” ignorada.

O que a suíte cobre: telas do painel abrindo sem erro de PHP, produto completo
(medidas, fiscal, saldo, imagem) nascendo no ERP, reenvio que **atualiza** sem
`DELETE`, peso e caixa mudando a cotação do frete, compra no checkout virando
pedido com numeração `piloto.example-N`, contato e itens vinculados, saldo
descendo ao ERP, webhook aceito por header e recusado com token errado, e
categoria nascendo sob a raiz da loja.

## Integração contínua

`.github/workflows/testes.yml`:

- todo push e pull request: `php -l` e suíte unitária em PHP 8.4 (a versão da VPS);
- todo push e pull request: suíte de integração — roda sem Docker, então não custa esperar;
- execução manual e noturna: E2E no `wp-env`, com relatório do Playwright anexado quando falha;
- o smoke ao vivo nunca roda em CI: não há credencial do ERP no runner.

Unitário vermelho não entra. Teste de caracterização que mude de expectativa
carrega no próprio arquivo a nota do porquê.

## Limites conhecidos

O kernel de `tests/stubs/` não é WordPress de verdade. Ele cobre com fidelidade
os caminhos exercitados nas camadas unit e integração; onde o comportamento do
WordPress importa (formulário, checkout, frete, permalinks), quem responde é o
E2E. A validação final de cada entrega continua sendo `npm run wp:deploy` +
conferência remota por WP-CLI.

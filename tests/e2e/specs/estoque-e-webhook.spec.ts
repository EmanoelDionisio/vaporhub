import { test, expect, type Page } from '@playwright/test';
import {
  entrarNoPainel,
  estadoDoERP,
  limparLog,
  processarFila,
  produtoNaLoja,
  salvarEEnviarAoTiny,
  tokenDoWebhook,
} from '../helpers';

/**
 * Estoque e webhook.
 *
 * A loja é dona do saldo: venda no site desce o saldo no ERP. E o caminho de
 * volta só abre para quem apresenta o token no header — com recebimento
 * desligado, aviso de produto não inventa cadastro na vitrine.
 */
const WEBHOOK = '/?rest_route=/vh-loja/v1/public/tiny/webhook';

async function criarProduto(page: Page, sku: string, estoque: string): Promise<void> {
  await page.goto('/minha-loja/produtos/novo/');

  const form = page.locator('form.vh-rest-form');
  await form.locator('input[name="nome"]').fill(`Pod Estoque ${sku}`);
  await form.locator('input[name="preco_regular"]').fill('25.00');
  await form.locator('input[name="sku"]').fill(sku);
  await form.locator('select[name="status"]').selectOption('publish');

  const gerencia = form.locator('#vh-gerencia-estoque');
  if (!(await gerencia.isChecked())) {
    await gerencia.check();
  }
  await form.locator('input[name="estoque"]').fill(estoque);
  await form.locator('#vh-envio-peso').fill('0.2');

  await salvarEEnviarAoTiny(page);
}

test.describe('estoque e webhook', () => {
  test.beforeEach(async ({ page, request }) => {
    await limparLog(request);
    await entrarNoPainel(page);
  });

  test('saldo editado no painel desce para o ERP', async ({ page, request }) => {
    const sku = `VH-E2E-EST-${Date.now()}`;
    await criarProduto(page, sku, '9');

    const local = await produtoNaLoja(request, sku);
    await limparLog(request);

    await page.goto(`/minha-loja/produtos/${local.id}/`);
    await page.locator('form.vh-rest-form input[name="estoque"]').fill('4');
    await salvarEEnviarAoTiny(page);
    await processarFila(request);

    const estado = await estadoDoERP(request);
    const lancamentos = estado.requisicoes.filter(
      (req) => req.metodo === 'POST' && req.endpoint === `estoque/${local.tiny_id}`
    );

    expect(lancamentos.length, 'saldo lançado no ERP').toBeGreaterThan(0);

    const corpo = (lancamentos[lancamentos.length - 1].body ?? {}) as Record<string, any>;
    expect(corpo.tipo, 'lançamento de balanço, não movimento').toBe('B');
    expect(Number(corpo.quantidade)).toBe(4);
  });

  test('webhook aceita o token no header e recusa token errado', async ({ request }) => {
    const token = await tokenDoWebhook(request);
    expect(token, 'token do webhook disponível no painel').not.toBe('');

    const aceito = await request.post(WEBHOOK, {
      headers: { 'X-PA-Tiny-Token': token, 'Content-Type': 'application/json' },
      data: { tipo: 'estoque', dados: { codigo: 'VH-INEXISTENTE', saldo: 3 } },
    });
    expect(aceito.status(), await aceito.text()).toBe(200);

    const recusado = await request.post(WEBHOOK, {
      headers: { 'X-PA-Tiny-Token': 'token-errado', 'Content-Type': 'application/json' },
      data: { tipo: 'estoque', dados: { codigo: 'VH-INEXISTENTE', saldo: 3 } },
    });
    expect(recusado.status()).toBe(403);
  });

  test('com recebimento desligado, aviso do ERP não cria produto na vitrine', async ({ request }) => {
    const token = await tokenDoWebhook(request);
    const sku = `VH-FANTASMA-${Date.now()}`;

    const resposta = await request.post(WEBHOOK, {
      headers: { 'X-PA-Tiny-Token': token, 'Content-Type': 'application/json' },
      data: { tipo: 'produto', dados: { id: 999999, codigo: sku, descricao: 'Produto fantasma' } },
    });
    expect(resposta.status()).toBe(200);

    await processarFila(request);

    const busca = await request.get(`/?rest_route=/vh-e2e/v1/produto&sku=${sku}`, {
      headers: { 'X-VH-E2E-Token': 'vh-e2e' },
    });
    expect(busca.status(), 'nada foi criado na loja').toBe(404);
  });
});

import { test, expect, type APIRequestContext, type Page } from '@playwright/test';
import {
  entrarNoPainel,
  estadoDoERP,
  limparLog,
  processarFila,
  produtoNaLoja,
  requisicoesExatas,
  salvarEEnviarAoTiny,
} from '../helpers';

/**
 * Vitrine e checkout.
 *
 * O frete lê peso e caixa preenchidos no painel — por isso um produto maior sai
 * mais caro. A compra vira pedido no ERP com a numeração da loja, o cliente em
 * Contatos e cada item amarrado ao cadastro certo.
 */
type Cadastro = { nome: string; sku: string; preco: string; peso: string; medida: string };

async function criarProdutoNoPainel(page: Page, dados: Cadastro): Promise<void> {
  await page.goto('/minha-loja/produtos/novo/');

  const form = page.locator('form.vh-rest-form');
  await form.locator('input[name="nome"]').fill(dados.nome);
  await form.locator('input[name="preco_regular"]').fill(dados.preco);
  await form.locator('input[name="sku"]').fill(dados.sku);
  await form.locator('select[name="status"]').selectOption('publish');

  const gerencia = form.locator('#vh-gerencia-estoque');
  if (!(await gerencia.isChecked())) {
    await gerencia.check();
  }
  await form.locator('input[name="estoque"]').fill('20');

  await form.locator('#vh-envio-peso').fill(dados.peso);
  await form.locator('#vh-envio-largura').fill(dados.medida);
  await form.locator('#vh-envio-altura').fill(dados.medida);
  await form.locator('#vh-envio-comprimento').fill(dados.medida);

  await salvarEEnviarAoTiny(page);
}

/* Pelo link de remoção, não pelo clique: o carrinho usa AJAX com overlay. */
async function esvaziarCarrinho(page: Page): Promise<void> {
  for (let volta = 0; volta < 10; volta += 1) {
    await page.goto('/carrinho/');
    const link = page.locator('a.remove').first();
    if (!(await link.count())) {
      return;
    }
    const href = await link.getAttribute('href');
    if (!href) {
      return;
    }
    await page.goto(href);
  }
}

async function cotarFrete(page: Page, request: APIRequestContext, sku: string): Promise<number> {
  const produto = await produtoNaLoja(request, sku);
  await page.goto(`/carrinho/?add-to-cart=${produto.id}`);
  await page.goto('/carrinho/');

  const linha = page.locator('tr.shipping, .wc-block-components-totals-shipping').first();
  await expect(linha).toBeVisible();

  const texto = await linha.innerText();
  const valor = texto.match(/(\d+[.,]\d{2})/);

  expect(valor, `frete cotado na página do carrinho: ${texto}`).not.toBeNull();
  return Number.parseFloat((valor as RegExpMatchArray)[1].replace('.', '').replace(',', '.'));
}

test.describe('frete e pedido', () => {
  test.beforeEach(async ({ page, request }) => {
    await limparLog(request);
    await entrarNoPainel(page);
  });

  test('peso e caixa preenchidos no painel mudam a cotação do frete', async ({ page, request }) => {
    const leve = `VH-E2E-LEVE-${Date.now()}`;
    const pesado = `VH-E2E-CAIXA-${Date.now()}`;

    await criarProdutoNoPainel(page, {
      nome: 'Pod Leve E2E',
      sku: leve,
      preco: '30.00',
      peso: '0.2',
      medida: '5',
    });
    await criarProdutoNoPainel(page, {
      nome: 'Caixa Pesada E2E',
      sku: pesado,
      preco: '30.00',
      peso: '4',
      medida: '30',
    });

    await esvaziarCarrinho(page);
    const freteLeve = await cotarFrete(page, request, leve);

    await esvaziarCarrinho(page);
    const fretePesado = await cotarFrete(page, request, pesado);

    expect(freteLeve).toBeGreaterThan(0);
    expect(fretePesado, 'caixa maior e mais pesada sai mais caro').toBeGreaterThan(freteLeve);
  });

  test('compra no checkout gera pedido no ERP com numeração da loja', async ({ page, request }) => {
    const sku = `VH-E2E-PED-${Date.now()}`;

    await criarProdutoNoPainel(page, {
      nome: 'Pod Pedido E2E',
      sku,
      preco: '49.90',
      peso: '0.3',
      medida: '10',
    });

    await esvaziarCarrinho(page);
    await limparLog(request);
    await cotarFrete(page, request, sku);

    await page.goto('/finalizar-compra/');
    await preencherCheckout(page);

    await expect(page.locator('.woocommerce-order, body')).toContainText(
      /Obrigado|Pedido recebido|received/i,
      { timeout: 60_000 }
    );

    await processarFila(request);

    const estado = await estadoDoERP(request);
    const pedidos = requisicoesExatas(estado, 'POST', 'pedidos');
    expect(pedidos.length, 'o pedido foi enviado ao ERP').toBeGreaterThan(0);

    const corpo = (pedidos[pedidos.length - 1].body ?? {}) as Record<string, any>;

    expect(String(corpo.ecommerce?.numeroPedidoEcommerce)).toMatch(/^piloto\.example-\d+$/);
    expect(Number(corpo.idContato), 'cliente resolvido em Contatos').toBeGreaterThan(0);
    expect(Array.isArray(corpo.itens) && corpo.itens.length).toBeTruthy();
    expect(Number(corpo.itens[0]?.produto?.id), 'item amarrado ao cadastro do ERP').toBeGreaterThan(0);
    expect(Number(corpo.valorFrete), 'frete cobrado vai no pedido').toBeGreaterThan(0);
    expect(corpo.enderecoEntrega?.enderecoNro).toBe('123');
    expect(corpo.enderecoEntrega?.bairro).toBe('Centro');

    expect(requisicoesExatas(estado, 'POST', 'contatos').length, 'contato criado').toBeGreaterThan(0);
  });
});

async function preencherCheckout(page: Page): Promise<void> {
  const campos: Array<[string, string]> = [
    ['#billing_first_name', 'Cliente'],
    ['#billing_last_name', 'E2E'],
    ['#billing_email', 'cliente-e2e@exemplo.com'],
    ['#billing_phone', '51999990000'],
    ['#billing_postcode', '90000-000'],
    ['#billing_address_1', 'Rua das Palmeiras'],
    ['#billing_number', '123'],
    ['#billing_neighborhood', 'Centro'],
    ['#billing_city', 'Porto Alegre'],
  ];

  for (const [selector, valor] of campos) {
    const campo = page.locator(selector);
    if (await campo.count()) {
      await campo.fill(valor);
    }
  }

  const uf = page.locator('#billing_state');
  if (await uf.count()) {
    await uf.selectOption('RS').catch(() => undefined);
  }

  const cod = page.locator('#payment_method_cod');
  if (await cod.count()) {
    await cod.check();
  }

  await page.locator('#place_order').click();
}

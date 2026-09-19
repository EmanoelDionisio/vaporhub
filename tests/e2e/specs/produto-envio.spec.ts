import { test, expect } from '@playwright/test';
import {
  entrarNoPainel,
  estadoDoERP,
  limparLog,
  produtoNaLoja,
  requisicoesExatas,
  salvarEEnviarAoTiny,
  ultimoCorpo,
} from '../helpers';

/**
 * Cadastro completo no painel e envio ao ERP.
 *
 * É o caminho que o lojista percorre: preenche nome, preço, SKU, saldo, medidas
 * e dados fiscais, clica em “Criar e enviar ao Tiny” e o cadastro nasce no ERP
 * com tudo o que foi digitado.
 */
test.describe('produto do painel ao ERP', () => {
  test.beforeEach(async ({ page, request }) => {
    await limparLog(request);
    await entrarNoPainel(page);
  });

  test('produto simples completo chega inteiro no ERP', async ({ page, request }) => {
    const sku = `VH-E2E-${Date.now()}`;

    await page.goto('/minha-loja/produtos/novo/');

    const form = page.locator('form.vh-rest-form');
    await form.locator('input[name="nome"]').fill('Pod Descartável E2E');
    await form.locator('textarea[name="descricao"]').fill('Pod de teste ponta a ponta.');
    await form.locator('input[name="preco_regular"]').fill('49.90');
    await form.locator('input[name="sku"]').fill(sku);
    await form.locator('select[name="status"]').selectOption('publish');

    const gerencia = form.locator('#vh-gerencia-estoque');
    if (!(await gerencia.isChecked())) {
      await gerencia.check();
    }
    await form.locator('input[name="estoque"]').fill('7');

    await form.locator('#vh-envio-peso').fill('0.28');
    await form.locator('#vh-envio-volumes').fill('1');
    await form.locator('#vh-envio-largura').fill('12');
    await form.locator('#vh-envio-altura').fill('4');
    await form.locator('#vh-envio-comprimento').fill('20');

    await form.locator('#vh-fiscal-ncm').fill('9507.90.00');
    await form.locator('#vh-fiscal-gtin').fill('7898765432109');
    await form.locator('#vh-fiscal-unidade').fill('UN');

    const categoria = form.locator('input[name="categorias[]"]').first();
    if (await categoria.count()) {
      await categoria.check();
    }

    await salvarEEnviarAoTiny(page);

    const estado = await estadoDoERP(request);
    const criados = requisicoesExatas(estado, 'POST', 'produtos');

    expect(criados.length, 'o produto foi criado no ERP').toBeGreaterThan(0);

    const corpo = (criados[criados.length - 1].body ?? {}) as Record<string, any>;

    expect(corpo.descricao).toBe('Pod Descartável E2E');
    expect(corpo.sku ?? corpo.codigo).toBe(sku);
    expect(corpo.tipo).toBe('S');
    expect(corpo.situacao).toBe('A');
    expect(Number(corpo.precos?.preco)).toBe(49.9);
    expect(Number(corpo.dimensoes?.largura)).toBe(12);
    expect(Number(corpo.dimensoes?.altura)).toBe(4);
    expect(Number(corpo.dimensoes?.comprimento)).toBe(20);
    expect(Number(corpo.dimensoes?.pesoBruto)).toBeCloseTo(0.28, 3);
    expect(String(corpo.ncm).replace(/\D/g, '')).toBe('95079000');
    expect(String(corpo.gtin)).toBe('7898765432109');
    expect(corpo.estoque?.controlar).toBeTruthy();
    expect(Number(corpo.estoque?.inicial ?? corpo.estoque?.quantidade)).toBe(7);

    const local = await produtoNaLoja(request, sku);
    expect(Number(local.tiny_id), 'a loja guardou o vínculo com o ERP').toBeGreaterThan(0);
    expect(String(local.peso)).toBe('0.28');
    expect(String(local.ncm).replace(/\D/g, '')).toBe('95079000');
  });

  test('reenvio de produto existente nunca apaga o cadastro no ERP', async ({ page, request }) => {
    const sku = `VH-E2E-${Date.now()}`;

    await page.goto('/minha-loja/produtos/novo/');
    const form = page.locator('form.vh-rest-form');
    await form.locator('input[name="nome"]').fill('Pod Reenvio E2E');
    await form.locator('input[name="preco_regular"]').fill('39.90');
    await form.locator('input[name="sku"]').fill(sku);
    await form.locator('select[name="status"]').selectOption('publish');
    await salvarEEnviarAoTiny(page);

    await limparLog(request);

    /* Abre o produto salvo e reenvia: agora é UPDATE, sem DELETE. */
    const local = await produtoNaLoja(request, sku);
    await page.goto(`/minha-loja/produtos/${local.id}/`);

    const edicao = page.locator('form.vh-rest-form');
    await edicao.locator('input[name="preco_regular"]').fill('44.90');
    await salvarEEnviarAoTiny(page);

    const estado = await estadoDoERP(request);
    const deletes = estado.requisicoes.filter(
      (req) => req.metodo === 'DELETE' && /^produtos\/\d+$/.test(req.endpoint)
    );

    expect(deletes, 'nenhum DELETE de produto no reenvio').toEqual([]);
    const atualizacoes = estado.requisicoes.filter(
      (req) => req.metodo === 'PUT' && /^produtos\/\d+$/.test(req.endpoint)
    );
    expect(atualizacoes.length, 'o reenvio atualizou o cadastro existente').toBeGreaterThan(0);
  });

  test('opções de personalização aparecem logo abaixo do tipo', async ({ page }) => {
    await page.goto('/minha-loja/produtos/novo/');

    const tipo = page.locator('#vh-secao-tipo');
    const personalizacao = page.locator('#vh-secao-variavel');
    const preco = page.locator('#vh-secao-simples');
    const envio = page.locator('#vh-secao-envio');

    await expect(personalizacao).toBeHidden();
    await expect(preco).toBeVisible();

    await page.locator('#vh-tipo-toggle').check();

    await expect(personalizacao).toBeVisible();
    await expect(preco).toBeHidden();
    await expect(personalizacao.getByRole('heading', { name: 'Opções de personalização' })).toBeVisible();

    const caixaTipo = await tipo.boundingBox();
    const caixaPers = await personalizacao.boundingBox();
    const caixaEnvio = await envio.boundingBox();

    expect(caixaPers!.y, 'personalização abaixo do tipo').toBeGreaterThan(caixaTipo!.y);
    expect(caixaPers!.y, 'personalização acima de envio').toBeLessThan(caixaEnvio!.y);
  });
});

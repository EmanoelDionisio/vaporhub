import { test, expect } from '@playwright/test';
import { entrarNoPainel, estadoDoERP, limparLog, processarFila } from '../helpers';

/**
 * Árvore de categorias dentro da raiz da loja.
 *
 * A conta do ERP é compartilhada com outras operações. Toda categoria da loja
 * nasce pendurada em “Loja Vapor Hub”, e a raiz é adotada quando já existe
 * em vez de duplicada.
 */
test.describe('categorias no ERP', () => {
  test.beforeEach(async ({ page, request }) => {
    await limparLog(request);
    await entrarNoPainel(page);
  });

  test('categoria criada no painel nasce sob a raiz da loja', async ({ page, request }) => {
    const nome = `Molinetes E2E ${Date.now()}`;

    await page.goto('/minha-loja/categorias/novo/');
    await page.locator('form.vh-rest-form input[name="nome"]').fill(nome);
    await page.locator('form.vh-rest-form button[type="submit"]').first().click();

    const confirmar = page.locator('#vh-modal-confirmar');
    if (await confirmar.isVisible().catch(() => false)) {
      await confirmar.click();
    }
    await page.waitForURL(/\/minha-loja\/categorias\/?(\?|$)/, { timeout: 30_000 });

    await processarFila(request);

    const estado = await estadoDoERP(request);
    const criadas = estado.requisicoes.filter(
      (req) => req.metodo === 'POST' && req.endpoint === 'categorias'
    );

    expect(criadas.length, 'categoria enviada ao ERP').toBeGreaterThan(0);

    const raizes = estado.categorias.filter(
      (cat) => String((cat as Record<string, unknown>).descricao) === 'Loja Vapor Hub'
    );
    expect(raizes.length, 'raiz existe uma única vez').toBe(1);

    const raizId = Number((raizes[0] as Record<string, unknown>).id);
    const nova = estado.categorias.find(
      (cat) => String((cat as Record<string, unknown>).descricao) === nome
    ) as Record<string, unknown> | undefined;

    expect(nova, 'categoria presente no ERP').toBeTruthy();
    expect(Number(nova?.idCategoriaPai), 'pendurada na raiz da loja').toBe(raizId);
  });

  test('“Sem categoria” não é enviada ao ERP', async ({ request }) => {
    const estado = await estadoDoERP(request);
    const nomes = estado.categorias.map((cat) => String((cat as Record<string, unknown>).descricao));

    expect(nomes).not.toContain('Sem categoria');
    expect(nomes).not.toContain('Uncategorized');
  });
});

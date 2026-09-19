import { test, expect } from '@playwright/test';
import { entrarNoPainel, errosPHP, limparErrosPHP } from '../helpers';

/**
 * Varredura das telas do painel Minha Loja.
 *
 * Uma quebra de PHP em produtos, categorias, Tiny ou mapeamento aparece aqui
 * como falha, em vez de ser descoberta pelo lojista.
 */
const TELAS = [
  { rota: '/minha-loja/', titulo: /Painel|Dashboard|Minha Loja/i },
  { rota: '/minha-loja/produtos/', titulo: /Produtos/i },
  { rota: '/minha-loja/produtos/novo/', titulo: /Novo produto/i },
  { rota: '/minha-loja/categorias/', titulo: /Categorias/i },
  { rota: '/minha-loja/pedidos/', titulo: /Pedidos/i },
  { rota: '/minha-loja/clientes/', titulo: /Clientes/i },
  { rota: '/minha-loja/tiny/conexao/', titulo: /Tiny/i },
  { rota: '/minha-loja/tiny/mapeamento/', titulo: /Mapeamento|Tiny/i },
  { rota: '/minha-loja/tiny/logs/', titulo: /Logs|Tiny/i },
];

test.beforeEach(async ({ page, request }) => {
  await limparErrosPHP(request);
  await entrarNoPainel(page);
});

for (const tela of TELAS) {
  test(`tela ${tela.rota} abre sem erro de PHP`, async ({ page, request }) => {
    const resposta = await page.goto(tela.rota);

    expect(resposta?.status(), `HTTP em ${tela.rota}`).toBeLessThan(400);
    await expect(page.locator('body')).toContainText(tela.titulo);

    const conteudo = await page.content();
    expect(conteudo, `saída de erro visível em ${tela.rota}`).not.toMatch(
      /Fatal error|Parse error|Uncaught|Warning: |Notice: /
    );

    const erros = await errosPHP(request);
    expect(erros, `debug.log em ${tela.rota}`).toEqual([]);
  });
}

test('barra de filtros alinha os campos e o dropdown cabe no conteúdo', async ({ page }) => {
  await page.goto('/minha-loja/produtos/');

  const busca = page.locator('#vh-prod-busca');
  const gatilho = page.locator('.vh-loja-filtros .vh-select-custom__trigger');

  const caixaBusca = await busca.boundingBox();
  const caixaGatilho = await gatilho.boundingBox();

  expect(caixaBusca!.width, 'busca e categoria com larguras diferentes').toBeCloseTo(caixaGatilho!.width, 0);
  expect(caixaBusca!.y, 'campos em linhas diferentes').toBeCloseTo(caixaGatilho!.y, 0);

  await gatilho.click();

  const lista = page.locator('.vh-loja-filtros .vh-select-custom__lista');
  await expect(lista).toBeVisible();

  const caixaLista = await lista.boundingBox();
  expect(caixaLista!.width, 'dropdown mais estreito que o gatilho').toBeGreaterThanOrEqual(caixaGatilho!.width);

  const rolagem = await lista.evaluate((el) => el.scrollWidth - el.clientWidth);
  expect(rolagem, 'dropdown com rolagem horizontal').toBeLessThanOrEqual(0);

  const opcoes = await lista.locator('.vh-select-custom__opcao').all();
  for (const opcao of opcoes) {
    const caixa = await opcao.boundingBox();
    expect(caixa!.height, 'opção quebrando em várias linhas').toBeLessThan(40);
  }
});

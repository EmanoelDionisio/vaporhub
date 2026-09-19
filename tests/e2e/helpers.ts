import { expect, type APIRequestContext, type Page } from '@playwright/test';

export const ADMIN = { user: 'admin', senha: 'password' };
export const E2E_TOKEN = 'vh-e2e';

export type RequisicaoERP = {
  metodo: string;
  endpoint: string;
  query: Record<string, string>;
  body: Record<string, unknown> | null;
  status: number;
};

export type EstadoERP = {
  requisicoes: RequisicaoERP[];
  produtos: Record<string, unknown>[];
  pedidos: Record<string, unknown>[];
  contatos: Record<string, unknown>[];
  categorias: Record<string, unknown>[];
};

const cabecalho = { 'X-VH-E2E-Token': E2E_TOKEN };

export async function entrarNoPainel(page: Page): Promise<void> {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', ADMIN.user);
  await page.fill('#user_pass', ADMIN.senha);
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeAttached();
}

/**
 * Salva o produto e manda ao ERP, passando pelo modal de confirmação.
 *
 * O painel pergunta antes de enviar; sem confirmar, nada sai da loja.
 */
export async function salvarEEnviarAoTiny(page: Page): Promise<void> {
  await page.locator('form.vh-rest-form button[data-vh-enviar-tiny="1"]').click();
  await page.locator('#vh-modal-confirmar').click();
  await page.waitForURL(/\/minha-loja\/produtos\/?(\?|$)/, { timeout: 30_000 });
}

export function requisicoesExatas(
  estado: EstadoERP,
  metodo: string,
  endpoint: string
): RequisicaoERP[] {
  return estado.requisicoes.filter((req) => req.metodo === metodo && req.endpoint === endpoint);
}

export async function produtoNaLoja(
  request: APIRequestContext,
  sku: string
): Promise<Record<string, unknown>> {
  const resposta = await request.get(`/?rest_route=/vh-e2e/v1/produto&sku=${encodeURIComponent(sku)}`, {
    headers: cabecalho,
  });
  expect(resposta.ok(), await resposta.text()).toBeTruthy();
  return (await resposta.json()) as Record<string, unknown>;
}

export async function tokenDoWebhook(request: APIRequestContext): Promise<string> {
  const resposta = await request.get('/?rest_route=/vh-e2e/v1/token-webhook', { headers: cabecalho });
  expect(resposta.ok(), await resposta.text()).toBeTruthy();
  const dados = (await resposta.json()) as { token: string };
  return dados.token ?? '';
}

export async function estadoDoERP(request: APIRequestContext): Promise<EstadoERP> {
  const resposta = await request.get('/?rest_route=/vh-e2e/v1/erp', { headers: cabecalho });
  expect(resposta.ok(), await resposta.text()).toBeTruthy();
  return (await resposta.json()) as EstadoERP;
}

/** Zera ERP, vínculos locais e fila — recomeço limpo dos dois lados. */
export async function limparERP(request: APIRequestContext): Promise<void> {
  const resposta = await request.post('/?rest_route=/vh-e2e/v1/reset', { headers: cabecalho });
  expect(resposta.ok(), await resposta.text()).toBeTruthy();
}

/** Esquece o que o ERP ouviu, preservando os cadastros já criados. */
export async function limparLog(request: APIRequestContext): Promise<void> {
  const resposta = await request.delete('/?rest_route=/vh-e2e/v1/log', { headers: cabecalho });
  expect(resposta.ok(), await resposta.text()).toBeTruthy();
}

export async function processarFila(request: APIRequestContext): Promise<void> {
  const resposta = await request.post('/?rest_route=/vh-e2e/v1/fila', { headers: cabecalho });
  expect(resposta.ok(), await resposta.text()).toBeTruthy();
}

export async function errosPHP(request: APIRequestContext): Promise<string[]> {
  const resposta = await request.get('/?rest_route=/vh-e2e/v1/erros-php', { headers: cabecalho });
  expect(resposta.ok()).toBeTruthy();
  const dados = (await resposta.json()) as { linhas: string[] };
  return dados.linhas ?? [];
}

export async function limparErrosPHP(request: APIRequestContext): Promise<void> {
  await request.delete('/?rest_route=/vh-e2e/v1/erros-php', { headers: cabecalho });
}

export function requisicoes(estado: EstadoERP, metodo: string, endpoint: string): RequisicaoERP[] {
  return estado.requisicoes.filter(
    (req) => req.metodo === metodo && req.endpoint.startsWith(endpoint)
  );
}

export function ultimoCorpo(
  estado: EstadoERP,
  metodo: string,
  endpoint: string
): Record<string, unknown> {
  const lista = requisicoes(estado, metodo, endpoint);
  const ultima = lista[lista.length - 1];
  return (ultima?.body ?? {}) as Record<string, unknown>;
}

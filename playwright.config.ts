import { defineConfig, devices } from '@playwright/test';

/**
 * E2E contra o wp-env com o ERP mockado.
 *
 * Sobe o ambiente antes (`npm run test:e2e:subir`), que instala WooCommerce,
 * ativa o plugin e roda `tests/e2e/setup.php`.
 */
export default defineConfig({
  testDir: './tests/e2e/specs',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI
    ? [['github'], ['list'], ['html', { open: 'never' }]]
    : [['list']],
  use: {
    baseURL: process.env.VH_E2E_URL ?? 'http://localhost:8888',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});

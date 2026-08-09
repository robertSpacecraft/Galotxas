import { defineConfig, devices } from '@playwright/test';

const frontendPort = process.env.E2E_FRONTEND_PORT || '5174';
const backendPort = process.env.E2E_BACKEND_PORT || '8081';
const noindexFrontendPort = process.env.E2E_NOINDEX_FRONTEND_PORT || '5175';
const baseURL = process.env.E2E_BASE_URL || `http://127.0.0.1:${frontendPort}`;
const backendURL = process.env.E2E_BACKEND_URL || `http://127.0.0.1:${backendPort}`;

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  fullyParallel: false,
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  timeout: 30_000,
  expect: {
    timeout: 7_000,
  },
  reporter: [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: process.env.E2E_SKIP_WEBSERVER
    ? undefined
    : [
        {
          command: `VITE_API_BASE_URL=${backendURL}/api/v1 VITE_PUBLIC_SITE_URL=https://example.test VITE_PUBLIC_INDEXING_ENABLED=true npm run dev -- --host 127.0.0.1 --port ${frontendPort} --strictPort`,
          url: baseURL,
          reuseExistingServer: false,
          timeout: 120_000,
        },
        {
          command: `VITE_API_BASE_URL=${backendURL}/api/v1 VITE_PUBLIC_INDEXING_ENABLED=false npm run dev -- --host 127.0.0.1 --port ${noindexFrontendPort} --strictPort`,
          url: `http://127.0.0.1:${noindexFrontendPort}/robots.txt`,
          reuseExistingServer: false,
          timeout: 120_000,
        },
      ],
});

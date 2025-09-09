import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests-api',
  reporter: 'list',
  use: {
    baseURL: 'http://cannarewards-api.local', // Make sure this is your correct Local site URL
  },
});
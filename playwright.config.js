import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests-api',
  reporter: 'list',
  // Optimize for parallel execution
  workers: process.env.CI ? 4 : 12, // Use 12 workers locally, 4 in CI
  timeout: 60000, // Increase timeout to 60 seconds
  use: {
    baseURL: 'http://cannarewards-api.local',
    
    // --- XDEBUG BRUTE FORCE ---
    // This adds the XDEBUG_SESSION_START=1 query parameter to every
    // single request made by Playwright. Our plugin will see this and
    // force the debugger to connect.
    extraHTTPHeaders: {
      'Cookie': 'XDEBUG_SESSION=1'
    },
    // --- END XDEBUG BRUTE FORCE ---
  },
});
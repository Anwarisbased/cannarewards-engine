import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests-api',
  reporter: 'list',
  
  // Add retries: 2 times in CI, 0 times locally for immediate feedback
  retries: process.env.CI ? 2 : 0,
  
  // Optimize for parallel execution
  workers: process.env.CI ? 4 : 12, // Use 12 workers locally, 4 in CI
  timeout: 120000, // Increase global timeout to 120 seconds (2 minutes)
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
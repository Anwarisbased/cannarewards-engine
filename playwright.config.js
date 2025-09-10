import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests-api',
  reporter: 'list',
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
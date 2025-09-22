import { test, expect } from '@playwright/test';

test.describe('Performance: Edge Caching', () => {

  test('/catalog/products should send caching headers and be served from cache on staging', async ({ request }) => {
    const endpoint = '/wp-json/rewards/v2/catalog/products';

    // 1. First Request (Cache MISS). Bust the cache to guarantee a fresh response.
    const missResponse = await request.get(`${endpoint}?cache_bust=${Date.now()}`);
    expect(missResponse.ok()).toBeTruthy();
    
    // 2. Second Request (Potential Cache HIT).
    const hitResponse = await request.get(endpoint);
    expect(hitResponse.ok()).toBeTruthy();
    const hitHeaders = hitResponse.headers();

    // 3. Environment-Aware Assertions
    if (process.env.CI) {
      console.log('Running in CI, asserting Flywheel cache headers...');
      expect(missResponse.headers()['x-fly-cache']).toContain('MISS');
      expect(hitHeaders['x-fly-cache']).toContain('HIT');
      expect(Number(hitHeaders['age'])).toBeGreaterThan(0);
    } else {
      console.log('Running locally, skipping Flywheel cache header assertions.');
      // Locally, we verify that our PHP code is correctly *sending* the right header.
      expect(missResponse.headers()['cache-control']).toBe('public, s-maxage=300, max-age=300');
    }
  });
});
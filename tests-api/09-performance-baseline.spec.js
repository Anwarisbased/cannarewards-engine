import { test, expect } from '@playwright/test';
import { generateUniqueEmail } from './parallel-fix.js';

test.describe('Performance Benchmark: API Response Time', () => {
  let authToken;

  // Create one user for the entire benchmark run
  test.beforeAll(async ({ request }) => {
    const userEmail = generateUniqueEmail('perf_test');
    // Register and login user to get a valid token
    await request.post('/wp-json/rewards/v2/auth/register', {
      data: { email: userEmail, password: 'perf-password', firstName: 'Perf', agreedToTerms: true }
    });
    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: { username: userEmail, password: 'perf-password' }
    });
    const loginData = await loginResponse.json();
    authToken = loginData.token;
  });

  test('p95 response time for /session endpoint should be under 150ms', async ({ request }) => {
    // --- THIS IS THE FIX ---
    // Give this specific test up to 3 minutes to complete its iterations.
    test.setTimeout(180000); 

    const ITERATIONS = 20; // Run enough times to get a meaningful average
    const responseTimes = [];

    for (let i = 0; i < ITERATIONS; i++) {
      const startTime = Date.now();
      const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
        headers: { 'Authorization': `Bearer ${authToken}` }
      });
      const endTime = Date.now();
      
      expect(sessionResponse.ok()).toBeTruthy();
      responseTimes.push(endTime - startTime);
    }

    // Calculate p95
    responseTimes.sort((a, b) => a - b);
    const p95Index = Math.floor(ITERATIONS * 0.95);
    const p95Time = responseTimes[p95Index];
    
    console.log(`Session Endpoint P95 Response Time: ${p95Time}ms`);
    
    // THE ASSERTION: This is your performance contract.
    expect(p95Time).toBeLessThan(150);
  });
});
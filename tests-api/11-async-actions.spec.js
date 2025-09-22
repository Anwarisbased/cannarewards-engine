import { test, expect } from '@playwright/test';
import { generateUniqueEmail, generateUniqueQRCode } from './parallel-fix.js';

test.describe('Performance: Asynchronous API Actions', () => {
  let authToken;
  const testUserEmail = generateUniqueEmail('async_test');

  test.beforeAll(async ({ request }) => {
    // Register, login, and set a known starting state (0 points)
    await request.post('/wp-json/rewards/v2/auth/register', {
      data: { email: testUserEmail, password: 'async-password', firstName: 'Async', agreedToTerms: true }
    });
    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: { username: testUserEmail, password: 'async-password' }
    });
    const loginData = await loginResponse.json();
    authToken = loginData.token;

    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_user_by_email', email: testUserEmail, points_balance: 0, lifetime_points: 0 }
    });
    
    // --- THIS IS THE FIX ---
    // Prepare the test product with SKU PWT-001 to ensure it has points.
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'prepare_test_product' }
    });
  });

  test('/actions/claim should return 202 Accepted and process points in the background', async ({ request }) => {
    const qrCode = generateUniqueQRCode('ASYNC');
    
    // Reset the QR code and prepare the test product
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', { form: { action: 'reset_qr_code', code: qrCode } });
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', { form: { action: 'prepare_test_product' } });

    // Simulate a previous scan to ensure this is not the first scan
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', { 
      form: { action: 'simulate_previous_scan', email: testUserEmail } 
    });

    // Small delay to ensure database updates
    await new Promise(resolve => setTimeout(resolve, 100));

    // 1. Make the action request
    const claimResponse = await request.post('/wp-json/rewards/v2/actions/claim', {
      headers: { 'Authorization': `Bearer ${authToken}` },
      data: { code: qrCode }
    });

    // Log response for debugging
    console.log('Claim response status:', claimResponse.status());
    
    // THE FIRST ASSERTION: Check for immediate acceptance.
    expect(claimResponse.status()).toBe(202);
    const claimBody = await claimResponse.json();
    expect(claimBody.status).toBe('accepted');

    // 2. Wait for background processing to complete.
    await new Promise(resolve => setTimeout(resolve, 2000));

    // 3. Verify the outcome.
    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    const sessionData = await sessionResponse.json();

    // THE SECOND ASSERTION: Prove the background job ran successfully.
    expect(sessionData.data.points_balance).toBe(400);
  });
});
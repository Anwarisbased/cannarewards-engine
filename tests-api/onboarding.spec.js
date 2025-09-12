import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';

const TEST_CODE = 'PWT-001-C03C2878';

test.describe('User Onboarding Golden Path', () => {

  test.beforeEach(async ({ request }) => {
    const reset = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: TEST_CODE }
    });
    expect(reset.ok()).toBeTruthy();
  });

  test('A new user scanning a valid code should register and receive a welcome gift', async ({ request }) => {
    // Increase timeout for this test
    test.setTimeout(60000);

    // STEP 1: Unauthenticated scan
    const unauthenticatedClaim = await request.post('/wp-json/rewards/v2/unauthenticated/claim', {
      data: { code: TEST_CODE }
    });

    await expect(async () => await validateApiContract(unauthenticatedClaim, '/unauthenticated/claim', 'post')).toPass();
    expect(unauthenticatedClaim.ok()).toBeTruthy();
    const claimData = await unauthenticatedClaim.json();
    const registrationToken = claimData.data.registration_token;
    expect(registrationToken).toBeDefined();

    // STEP 2: Register with the token
    const registration = await request.post('/wp-json/rewards/v2/auth/register-with-token', {
      data: {
        email: `goldenpath_${Date.now()}@example.com`,
        password: 'a-secure-password',
        firstName: 'Golden',
        agreedToTerms: true,
        registration_token: registrationToken
      }
    });

    await expect(async () => await validateApiContract(registration, '/auth/register-with-token', 'post')).toPass();
    expect(registration.ok()).toBeTruthy();
    const registrationData = await registration.json();
    const authToken = registrationData.token;
    expect(authToken).toBeDefined();

    // STEP 3: Verify the outcome
    // The scan happens asynchronously now. We need to wait a moment for the event to be processed.
    // In a real-world scenario, the frontend would use polling or websockets. For our test, a short delay is sufficient.
    await new Promise(resolve => setTimeout(resolve, 2000)); // 2 second delay

    const ordersResponse = await request.get('/wp-json/rewards/v2/users/me/orders', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });

    await expect(async () => await validateApiContract(ordersResponse, '/users/me/orders', 'get')).toPass();
    expect(ordersResponse.ok()).toBeTruthy();
    
    const ordersData = await ordersResponse.json();
    
    expect(ordersData.data.orders).toHaveLength(1);
    expect(ordersData.data.orders[0].items).toContain('Playwright Welcome Gift');
  });
});
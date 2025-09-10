// File: tests-api/onboarding.spec.js (NEW)
import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';

// IMPORTANT: This must be a real, unused code from your database or generated CSV.
// It will be reset before each test run.
const TEST_CODE = 'PWT-001-C03C2878';

test.describe('User Onboarding Golden Path', () => {

  // Before each test in this file, reset the state of our test QR code.
  // This ensures each test run is clean and atomic.
  test.beforeEach(async ({ request }) => {
    const reset = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: TEST_CODE }
    });
    // If the helper fails, we stop the test immediately.
    expect(reset.ok()).toBeTruthy();
  });

  test('A new user scanning a valid code should register and receive a welcome gift', async ({ request }) => {

    // STEP 1: An unauthenticated user "scans" the code.
    const unauthenticatedClaim = await request.post('/wp-json/rewards/v2/unauthenticated/claim', {
      data: { code: TEST_CODE }
    });

    // --- CONTRACT ENFORCEMENT ---
    await expect(async () => await validateApiContract(unauthenticatedClaim, '/unauthenticated/claim', 'post')).toPass();
    expect(unauthenticatedClaim.ok(), `Unauthenticated claim failed. Body: ${await unauthenticatedClaim.text()}`).toBeTruthy();

    const claimData = await unauthenticatedClaim.json();
    expect(claimData.data.status).toBe('registration_required');
    const registrationToken = claimData.data.registration_token;
    expect(registrationToken).toBeDefined();

    // STEP 2: The user registers using the token from the previous step.
    const registration = await request.post('/wp-json/rewards/v2/auth/register-with-token', {
      data: {
        email: `goldenpath_${Date.now()}@example.com`,
        password: 'a-secure-password',
        firstName: 'Golden',
        lastName: 'Path',
        agreedToTerms: true,
        registration_token: registrationToken
      }
    });

    // --- CONTRACT ENFORCEMENT ---
    await expect(async () => await validateApiContract(registration, '/auth/register-with-token', 'post')).toPass();
    expect(registration.ok(), `Registration with token failed. Body: ${await registration.text()}`).toBeTruthy();

    const registrationData = await registration.json();
    const authToken = registrationData.token;
    expect(authToken).toBeDefined();

    // STEP 3: Verify the user automatically received their welcome gift.
    // We log in as the new user and check their order history.
    const ordersResponse = await request.get('/wp-json/rewards/v2/users/me/orders', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });

    // --- CONTRACT ENFORCEMENT ---
    await expect(async () => await validateApiContract(ordersResponse, '/users/me/orders', 'get')).toPass();
    expect(ordersResponse.ok(), `Fetching orders failed. Body: ${await ordersResponse.text()}`).toBeTruthy();
    
    const ordersData = await ordersResponse.json();
    
    // Assert that there is exactly one order in their history.
    expect(ordersData.data.orders).toHaveLength(1);
    // Assert that the order is for the correct welcome gift product.
    // IMPORTANT: 'Playwright Welcome Gift' must be the name of the product configured as the welcome reward.
    expect(ordersData.data.orders[0].items).toContain('Playwright Welcome Gift');
  });
});
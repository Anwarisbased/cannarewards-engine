import { test, expect } from '@playwright/test';

const TEST_CODE = 'PWT-001-C03C2878'; // Replace with a real code from your CSV

test.describe('User Onboarding Golden Path', () => {

  test.beforeEach(async ({ request }) => {
    const reset = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: TEST_CODE }
    });
    expect(reset.ok()).toBeTruthy();
  });

  test('A new user scanning a valid code should register and receive a welcome gift', async ({ request }) => {
    
    const unauthenticatedClaim = await request.post('/wp-json/rewards/v2/unauthenticated/claim', {
      data: { code: TEST_CODE }
    });
    expect(unauthenticatedClaim.ok(), `Unauthenticated claim failed. Body: ${await unauthenticatedClaim.text()}`).toBeTruthy();
    const claimData = await unauthenticatedClaim.json();
    expect(claimData.data.status).toBe('registration_required');

    const registrationToken = claimData.data.registration_token;

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
    expect(registration.ok(), `Registration with token failed. Body: ${await registration.text()}`).toBeTruthy();
    const registrationData = await registration.json();
    const authToken = registrationData.token;

    const ordersResponse = await request.get('/wp-json/rewards/v2/users/me/orders', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    expect(ordersResponse.ok(), `Fetching orders failed. Body: ${await ordersResponse.text()}`).toBeTruthy();
    const ordersData = await ordersResponse.json();
    
    expect(ordersData.data.orders).toHaveLength(1);
    expect(ordersData.data.orders[0].items).toContain('Playwright Welcome Gift');
  });
});
import { test, expect } from '@playwright/test';

test.describe('Forensic Audit: User Rank-Up Lifecycle', () => {

  let authToken;
  let testUserEmail;

  test.beforeAll(async ({ request }) => {
    // Clear rank cache
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'clear_rank_cache' }
    });

    // Create test user
    const uniqueEmail = `rankup_audit_${Date.now()}@example.com`;
    await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_user_by_email', email: uniqueEmail }
    });

    const registerResponse = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: uniqueEmail, password: 'test-password', firstName: 'Rankup',
        lastName: 'Audit', agreedToTerms: true,
      }
    });
    expect(registerResponse.ok()).toBeTruthy();

    const loginResponse = await request.post('/wp-json/jwt-auth/v1/token', {
      data: { username: uniqueEmail, password: 'test-password' }
    });
    expect(loginResponse.ok()).toBeTruthy();
    const loginData = await loginResponse.json();
    authToken = loginData.token;
    testUserEmail = uniqueEmail;
  });

  test('should correctly transition from bronze to silver after a product scan', async ({ request }) => {
    // Arrange: Set user to 4800 lifetime points (just below silver threshold)
    const resetResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: {
        action: 'reset_user_by_email', email: testUserEmail,
        points_balance: 100, lifetime_points: 4800
      }
    });
    expect(resetResponse.ok()).toBeTruthy();

    // Verify starting rank is bronze
    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    const sessionData = await sessionResponse.json();
    expect(sessionData.data.rank.key).toBe('bronze');

    // Prepare test product and reset QR code
    const prepareResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'prepare_test_product' }
    });
    expect(prepareResponse.ok()).toBeTruthy();

    // Simulate a previous scan so this won't be treated as first scan
    const simulateResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'simulate_previous_scan', email: testUserEmail }
    });
    expect(simulateResponse.ok()).toBeTruthy();

    // Reset the QR code to use SKU PWT-001
    const testCode = 'PWT-RANKUP-AUDIT';
    const qrResponse = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testCode }
    });
    expect(qrResponse.ok()).toBeTruthy();

    // Act: Scan product to award 400 points (should push user to 5200 lifetime points)
    const claimResponse = await request.post('/wp-json/rewards/v2/actions/claim', {
      headers: { 'Authorization': `Bearer ${authToken}` },
      data: { code: testCode }
    });
    expect(claimResponse.ok()).toBeTruthy();
    const claimData = await claimResponse.json();
    expect(claimData.data.success).toBe(true);

    // Assert: API must now report user's rank as 'silver'
    // Wait a moment for the async points processing to complete
    await new Promise(resolve => setTimeout(resolve, 1000));

    const finalSession = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: { 'Authorization': `Bearer ${authToken}` }
    });
    const finalSessionData = await finalSession.json();
    expect(finalSessionData.data.rank.key).toBe('silver');
  });
});
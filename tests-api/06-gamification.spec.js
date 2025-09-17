import { test, expect } from '@playwright/test';
import { validateApiContract } from './api-contract-validator.js';
import { generateUniqueEmail, generateUniqueTestId, generateUniqueQRCode } from './parallel-fix.js';

test.describe('Gamification Engine (Achievements)', () => {
  let userToken;
  let userId;
  let testQRCode1, testQRCode2, testQRCode3;

  test.beforeAll(async ({ request }) => {
    // Create a user for testing
    const userEmail = generateUniqueEmail('gamer');
    const userPassword = 'a-secure-password';
    
    const registration = await request.post('/wp-json/rewards/v2/auth/register', {
      data: {
        email: userEmail,
        password: userPassword,
        firstName: 'Gamer',
        agreedToTerms: true
      }
    });

    expect(registration.ok()).toBeTruthy();
    const registrationData = await registration.json();
    userId = registrationData.data.userId;

    const login = await request.post('/wp-json/rewards/v2/auth/login', {
      data: {
        email: userEmail,
        password: userPassword
      }
    });

    expect(login.ok()).toBeTruthy();
    const loginData = await login.json();
    userToken = loginData.data.token;
    
    // Generate unique QR codes for testing
    testQRCode1 = generateUniqueQRCode('GAMIFICATION1');
    testQRCode2 = generateUniqueQRCode('GAMIFICATION2');
    testQRCode3 = generateUniqueQRCode('GAMIFICATION3');
    
    // Reset the QR codes using the test helper
    const reset1 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode1 }
    });
    expect(reset1.ok()).toBeTruthy();
    
    const reset2 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode2 }
    });
    expect(reset2.ok()).toBeTruthy();
    
    const reset3 = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'reset_qr_code', code: testQRCode3 }
    });
    expect(reset3.ok()).toBeTruthy();
  });

  test.beforeAll(async ({ request }) => {
    // Set up the test achievement
    const setup = await request.post('/wp-content/plugins/cannarewards-engine/tests-api/test-helper.php', {
      form: { action: 'setup_test_achievement' }
    });
    expect(setup.ok()).toBeTruthy();
  });

  test('User scans products and achievements are awarded', async ({ request }) => {
    test.setTimeout(120000); // Increase timeout to 2 minutes for this test
    
    // First scan - use the authenticated claim endpoint
    const scan1 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode1 },
      headers: { 'Authorization': `Bearer ${userToken}` }
    });

    expect(scan1.ok()).toBeTruthy();
    const scanData1 = await scan1.json();
    expect(scanData1.success).toBeTruthy();

    // Second scan - use the authenticated claim endpoint
    const scan2 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode2 },
      headers: { 'Authorization': `Bearer ${userToken}` }
    });

    expect(scan2.ok()).toBeTruthy();
    const scanData2 = await scan2.json();
    expect(scanData2.success).toBeTruthy();

    // Third scan - use the authenticated claim endpoint
    const scan3 = await request.post('/wp-json/rewards/v2/actions/claim', {
      data: { code: testQRCode3 },
      headers: { 'Authorization': `Bearer ${userToken}` }
    });

    expect(scan3.ok()).toBeTruthy();
    const scanData3 = await scan3.json();
    expect(scanData3.success).toBeTruthy();

    // Wait for processing
    await new Promise(resolve => setTimeout(resolve, 5000));

    // Check that achievements were unlocked and points were awarded
    const sessionResponse = await request.get('/wp-json/rewards/v2/users/me/session', {
      headers: { 'Authorization': `Bearer ${userToken}` }
    });

    expect(sessionResponse.ok()).toBeTruthy();
    const sessionData = await sessionResponse.json();
    
    // The user should have received 500 bonus points from the achievement
    expect(sessionData.data.points_balance).toBeGreaterThanOrEqual(500);
  });
});